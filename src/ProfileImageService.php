<?php

namespace MediaWiki\Extension\IntegratedProfiles;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Status\Status;
use MediaWiki\User\UserIdentity;
use Wikimedia\ObjectCache\BagOStuff;

/**
 * Shared upload, deletion, and cache lifecycle for profile images.
 * @internal
 */
abstract class ProfileImageService {

	protected readonly ProfileImageResolver $resolver;

	protected function __construct(
		protected readonly ServiceOptions $options,
		protected readonly ProfileImageStorage $storage,
		BagOStuff $cache,
		protected readonly ProfileSubjectIds $subject_ids,
		private readonly ImageUploadSanitizer $sanitizer,
		private readonly ImageUploadVerifier $verifier,
		private readonly ?ThumbnailGenerator $thumbnails,
		private readonly ?ProfilePagePurger $purger,
		private readonly string $kind,
		private readonly ImageValidator $validator,
		private readonly string $animation_option,
	) {
		$this->resolver = new ProfileImageResolver( $storage, $cache, $subject_ids, $kind );
	}

	/**
	 * @return array<string, mixed>
	 */
	abstract protected function get_uploaded_image_info( int $central_id ): array;

	/**
	 * @param array{owner_id: int, ext: string, mtime: string, version: string, thumbs: bool}|null $resolved
	 * @return array<string, mixed>
	 */
	abstract protected function info_from_resolved( ?array $resolved ): array;

	/**
	 * Validates and stores an uploaded image under the profile owner's central ID.
	 *
	 * @param UserIdentity $user Subject user
	 * @param string $tmp_path Local filesystem path to the upload
	 * @param int $size Byte length reported by the upload
	 * @param bool $allow_animated Whether animated GIF / WebP / APNG is allowed
	 */
	public function upload_for_user( UserIdentity $user, string $tmp_path, int $size, bool $allow_animated = false ): Status {
		$ids = $this->subject_ids->ids_for( $user );
		return $this->upload( $ids['central_id'], $tmp_path, $size, $allow_animated, $user );
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
			return Status::newFatal( "integratedprofiles-error-{$this->kind}-upload" );
		}

		$allow_animated = $allow_animated && (bool)$this->options->get( $this->animation_option );
		$validation = $this->validator->validate_upload( $tmp_path, $size, $allow_animated );
		if ( !$validation['ok'] ) {
			return Status::newFatal( $validation['error'] );
		}

		$status = $this->sanitizer->with_sanitized_file(
			$tmp_path,
			$validation['ext'],

			function ( string $path ) use ( $central_id, $validation, $user ): Status {
				$verified = $this->verifier->verify( $this->kind, $path, $validation['ext'], $central_id, $user );
				if ( !$verified->isOK() ) {
					return $verified;
				}

				$stored = $this->storage->store( $central_id, $validation['ext'], $path );
				if ( !$stored->isOK() ) {
					// the backend's own errors take priority
					$stored->fatal( "integratedprofiles-error-{$this->kind}-upload" );
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

		return Status::newGood( $this->get_uploaded_image_info( $central_id ) );
	}

	/**
	 * Deletes custom image files for the profile owner.
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
			$status->fatal( "integratedprofiles-error-{$this->kind}-delete" );
			return $status;
		}

		if ( $central_id > 0 ) {
			$this->resolver->remember_missing( $central_id );
		}

		return Status::newGood( $this->info_from_resolved( null ) );
	}

}
