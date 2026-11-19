<?php

namespace MediaWiki\Extension\IntegratedProfiles;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\User\UserIdentity;
use Wikimedia\ObjectCache\BagOStuff;

/**
 * THE AvatarService. There are many like it, but this one is mine. Handles validation, caching, and URL resolution for user avatars.
 */
class AvatarService extends ProfileImageService {

	public const SERVICE_NAME = 'IntegratedProfiles.AvatarService';
	public const CACHE_TTL = ProfileImageResolver::CACHE_TTL;
	public const CACHE_KEY_VERSION = ProfileImageResolver::CACHE_KEY_VERSION;
	public const CONSTRUCTOR_OPTIONS = [
		'IntegratedProfilesAvatarMaxBytes',
		'IntegratedProfilesEnableAnimatedAvatars',
		'ExtensionAssetsPath'
	];

	public function __construct(
		ServiceOptions $options,
		AvatarStorage $storage,
		BagOStuff $cache,
		ProfileSubjectIds $subject_ids,
		ImageUploadSanitizer $sanitizer,
		ImageUploadVerifier $verifier,
		?ThumbnailGenerator $thumbnails = null,
		?ProfilePagePurger $purger = null,
	) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
		parent::__construct(
			$options,
			$storage,
			$cache,
			$subject_ids,
			$sanitizer,
			$verifier,
			$thumbnails,
			$purger,
			'avatar',
			new AvatarValidator( (int)$options->get( 'IntegratedProfilesAvatarMaxBytes' ) ),
			'IntegratedProfilesEnableAnimatedAvatars',
		);
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

	/** @inheritDoc */
	protected function get_uploaded_image_info( int $central_id ): array {
		return $this->get_avatar_info( $central_id );
	}

	/**
	 * @param array{owner_id: int, ext: string, mtime: string, version: string, thumbs: bool}|null $resolved
	 * @return array{avatar_url: string, has_custom_avatar: bool, avatar_thumbs: array<int, string>}
	 */
	protected function info_from_resolved( ?array $resolved ): array {
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
