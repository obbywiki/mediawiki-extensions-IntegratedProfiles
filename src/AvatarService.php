<?php

namespace MediaWiki\Extension\IntegratedProfiles;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Status\Status;
use MediaWiki\User\UserIdentity;
use Wikimedia\ObjectCache\BagOStuff;

/**
 * THE AvatarService. There are many like it, but this one is mine. Handles validation, caching, and URL resolution for user avatars.
 */
class AvatarService {

	public const SERVICE_NAME = 'IntegratedProfiles.AvatarService';
	public const CACHE_TTL = ProfileImageResolver::CACHE_TTL;
	public const CACHE_KEY_VERSION = ProfileImageResolver::CACHE_KEY_VERSION;
	public const CONSTRUCTOR_OPTIONS = [
		'IntegratedProfilesAvatarMaxBytes',
		'IntegratedProfilesEnableAnimatedAvatars',
		'ExtensionAssetsPath'
	];

	private readonly AvatarValidator $validator;
	private readonly ProfileImageResolver $resolver;

	public function __construct(
		private readonly ServiceOptions $options,
		private readonly AvatarStorage $storage,
		BagOStuff $cache,
		private readonly ProfileSubjectIds $subject_ids,
		private readonly ImageUploadSanitizer $sanitizer,
		private readonly ImageUploadVerifier $verifier,
		private readonly ?ThumbnailGenerator $thumbnails = null,
		private readonly ?ProfilePagePurger $purger = null,
	) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
		$this->resolver = new ProfileImageResolver( $storage, $cache, $subject_ids, 'avatar', self::CACHE_TTL, self::CACHE_KEY_VERSION );
		$this->validator = new AvatarValidator( (int)$options->get( 'IntegratedProfilesAvatarMaxBytes' ) );
	}

	/**
	 * @return array{avatar_url: string, has_custom_avatar: bool, avatar_thumbs: array<int, string>}
	 */
	public function get_avatar_info_for_user( UserIdentity $user ): array {
		$ids = $this->subject_ids->ids_for( $user );
		return $this->get_avatar_info( $ids['central_id'] );
	}

	/**
	 * Returns the public avatar URL for a user (custom file or extension default).
	 */
	public function get_avatar_url_for_user( UserIdentity $user ): string {
		return $this->get_avatar_info_for_user( $user )['avatar_url'];
	}

	/**
	 * Batch avatar info keyed by canonical username (first occurrence order) while skipping anonymous users. Uses a single cache getMulti for performance.
	 *
	 * @param iterable<UserIdentity> $users
	 * @return array<string, array{avatar_url: string, has_custom_avatar: bool, avatar_thumbs: array<int, string>}>
	 */
	public function get_avatar_info_for_users( iterable $users ): array {
		$result = [];
		foreach ( $this->resolver->resolve_for_users( $users ) as $name => $resolved ) {
			$result[$name] = $this->info_from_resolved( $resolved );
		}

		return $result;
	}

	/**
	 * @param iterable<UserIdentity> $users
	 * @return array<string, string> Username => avatar URL
	 */
	public function get_avatar_urls_for_users( iterable $users ): array {
		$urls = [];

		foreach ( $this->get_avatar_info_for_users( $users ) as $name => $info ) {
			$urls[$name] = $info['avatar_url'];
		}

		return $urls;
	}

	/**
	 * @return array{avatar_url: string, has_custom_avatar: bool, avatar_thumbs: array<int, string>}
	 */
	public function get_avatar_info( int $central_id ): array {
		if ( $central_id <= 0 ) {
			return $this->info_from_resolved( null );
		}

		return $this->info_from_resolved( $this->resolver->resolve( $central_id ) );
	}

	public function default_avatar_url(): string {
		$assets = rtrim( (string)$this->options->get( 'ExtensionAssetsPath' ), '/' );
		return $assets . '/IntegratedProfiles/resources/avatars/default.svg';
	}

	/**
	 * Validates and stores an uploaded avatar temp file under the central id.
	 *
	 * @param UserIdentity $user Subject user
	 * @param string $tmp_path Local filesystem path to the upload
	 * @param int $size Byte length reported by the upload
	 * @param bool $allow_animated Whether animated GIF / WebP / APNG is allowed
	 */
	public function upload_for_user( UserIdentity $user, string $tmp_path, int $size, bool $allow_animated = false ): Status {
		$ids = $this->subject_ids->ids_for( $user );
		return $this->upload(
			$ids['central_id'],
			$tmp_path,
			$size,
			$allow_animated,
			$user
		);
	}

	/**
	 * @param int $central_id Storage owner ID
	 * @param string $tmp_path Local filesystem path to the upload
	 * @param int $size Byte length reported by the upload
	 * @param bool $allow_animated Whether animated GIF / WebP / APNG is allowed
	 * @param UserIdentity|null $user Profile owner, passed to IntegratedProfilesVerifyImageUpload
	 */
	public function upload( int $central_id, string $tmp_path, int $size, bool $allow_animated = false, ?UserIdentity $user = null ): Status {
		if ( $central_id <= 0 ) {
			return Status::newFatal( 'integratedprofiles-error-avatar-upload' );
		}

		$allow_animated = $allow_animated && (bool)$this->options->get( 'IntegratedProfilesEnableAnimatedAvatars' );
		$validation = $this->validator->validate_upload( $tmp_path, $size, $allow_animated );
		if ( !$validation['ok'] ) {
			return Status::newFatal( $validation['error'] );
		}

		$status = $this->sanitizer->with_sanitized_file(
			$tmp_path,
			$validation['ext'],

			function ( string $path ) use ( $central_id, $validation, $user ): Status {
				$verified = $this->verifier->verify( 'avatar', $path, $validation['ext'], $central_id, $user );
				if ( !$verified->isOK() ) {
					return $verified;
				}

				$stored = $this->storage->store( $central_id, $validation['ext'], $path );
				if ( !$stored->isOK() ) {
					// the backend's own errors take priority
					$stored->fatal( 'integratedprofiles-error-avatar-upload' );
				}

				return $stored;
			}
		);

		if ( !$status->isOK() ) {
			return $status;
		}

		$found = $this->storage->find_extension_with_mtime( $central_id );
		$this->resolver->remember( $central_id, $validation['ext'], $found['mtime'] ?? '', $found['version'] ?? '', false );
		// cached pages would keep showing the replaced original
		$this->purge_pages( $user );
		$this->thumbnails?->schedule( $this->storage, $central_id, function () use ( $central_id, $user ): void {
			// next read re-resolves and sees the new thumbnails, and so do pages cached since the upload
			$this->forget_cached( $central_id );
			$this->purge_pages( $user );
		} );

		return Status::newGood( $this->get_avatar_info( $central_id ) );
	}

	/**
	 * Deletes custom avatar files and restores the default URL.
	 */
	public function delete_for_user( UserIdentity $user ): Status {
		$ids = $this->subject_ids->ids_for( $user );
		$status = $this->delete( $ids['central_id'] );
		if ( $status->isOK() ) {
			$this->purge_pages( $user );
		}

		return $status;
	}

	public function forget_cached( int $central_id ): void {
		$this->resolver->forget( $central_id );
	}

	private function purge_pages( ?UserIdentity $user ): void {
		if ( $user !== null ) {
			$this->purger?->purge_subject_pages( $user );
		}
	}

	public function delete( int $central_id ): Status {
		$status = Status::newGood();
		if ( $central_id > 0 ) {
			$status->merge( $this->storage->delete_all( $central_id ) );
		}

		if ( !$status->isOK() ) {
			$status->fatal( 'integratedprofiles-error-avatar-delete' );
			return $status;
		}

		if ( $central_id > 0 ) {
			$this->resolver->remember_missing( $central_id );
		}

		return Status::newGood( $this->info_from_resolved( null ) );
	}

	/**
	 * @param array{owner_id: int, ext: string, mtime: string, version: string, thumbs: bool}|null $resolved
	 * @return array{avatar_url: string, has_custom_avatar: bool, avatar_thumbs: array<int, string>}
	 */
	private function info_from_resolved( ?array $resolved ): array {
		if ( $resolved === null ) {
			return [ 'avatar_url' => $this->default_avatar_url(), 'has_custom_avatar' => false, 'avatar_thumbs' => [] ];
		}

		return [
			'avatar_url' => $this->storage->public_url( $resolved['owner_id'], $resolved['ext'], $resolved['mtime'] !== '' ? $resolved['mtime'] : null ),
			'has_custom_avatar' => true,
			'avatar_thumbs' => $resolved['thumbs'] ? $this->storage->thumbs()->public_urls( $resolved['owner_id'], $resolved['version'] ) : [],
		];
	}

}
