<?php

namespace MediaWiki\Extension\IntegratedProfiles;

use MediaWiki\Deferred\DeferredUpdates;
use MediaWiki\Extension\IntegratedProfiles\Thumbnailer\GdThumbnailer;
use MediaWiki\Extension\IntegratedProfiles\Thumbnailer\ImagickThumbnailer;
use MediaWiki\Extension\IntegratedProfiles\Thumbnailer\Thumbnailer;
use MediaWiki\Extension\IntegratedProfiles\Thumbnailer\VipsThumbnailer;
use Psr\Log\LoggerInterface;
use Throwable;
use Wikimedia\FileBackend\FSFile\TempFSFile;
use Wikimedia\FileBackend\FSFile\TempFSFileFactory;

/**
 * Renders the fixed thumbnail set for a stored original, trying each configured library in order (vips, then imagick, then gd by default).
 *
 * Never throws and never fails the caller. A missing library, an unsupported source, or a broken encoder only means no thumbnails, in which case the original keeps being served.
 */
class ThumbnailGenerator {

	public const SERVICE_NAME = 'IntegratedProfiles.ThumbnailGenerator';

	/**
	 * @param list<Thumbnailer> $thumbnailers In order of preference
	 * @param TempFSFileFactory $temp_factory
	 * @param LoggerInterface $logger
	 */
	public function __construct(
		private readonly array $thumbnailers,
		private readonly TempFSFileFactory $temp_factory,
		private readonly LoggerInterface $logger,
	) {
	}

	/**
	 * Builds the library chain from IntegratedProfilesThumbnailers. Unknown and duplicate names are ignored.
	 *
	 * @param list<string> $names
	 * @param string $vips_command
	 * @return list<Thumbnailer>
	 */
	public static function thumbnailers_from_config( array $names, string $vips_command ): array {
		$chain = [];
		foreach ( $names as $name ) {
			if ( !is_string( $name ) || isset( $chain[$name] ) ) {
				continue;
			}

			$thumbnailer = match ( $name ) {
				'vips' => new VipsThumbnailer( $vips_command ),
				'imagick' => new ImagickThumbnailer(),
				'gd' => new GdThumbnailer(),
				default => null,
			};
			if ( $thumbnailer !== null ) {
				$chain[$name] = $thumbnailer;
			}
		}

		return array_values( $chain );
	}

	/**
	 * Queues generation after the response is sent, so uploads never wait on (or fail because of) thumbnailing.
	 */
	public function schedule( AvatarStorage|BannerStorage $storage, int $owner_id ): void {
		if ( $this->thumbnailers === [] || $owner_id <= 0 ) {
			return;
		}

		DeferredUpdates::addCallableUpdate( function () use ( $storage, $owner_id ): void {
			$this->generate_for( $storage, $owner_id );
		} );
	}

	/**
	 * Generates thumbnails for the owner's current original. The original is re-resolved here, so a run that starts after a replacement or deletion works on the right file (or does nothing).
	 *
	 * @return bool true when a full set was stored
	 */
	public function generate_for( AvatarStorage|BannerStorage $storage, int $owner_id ): bool {
		try {
			$thumbs = $storage->thumbs();
			if ( !$thumbs->is_configured() ) {
				$this->logger->debug( 'Thumbnail container is not configured on the backend, skipping owner {owner}', [ 'owner' => $owner_id ] );
				return false;
			}

			$found = $storage->find_extension_with_mtime( $owner_id );
			$version = $found['version'] ?? '';
			if ( $found === null || !ThumbnailStorage::is_valid_version( $version ) ) {
				return false;
			}

			$ref = $storage->get_backend()->getLocalReference( [ 'src' => $storage->storage_path( $owner_id, $found['ext'] ) ] );
			if ( $ref === null ) {
				$this->logger->warning( 'Could not fetch the original for owner {owner}', [ 'owner' => $owner_id ] );
				return false;
			}

			if ( !$this->generate( $thumbs, $ref->getPath(), $found['ext'], $owner_id, $version ) ) {
				return false;
			}

			$thumbs->delete_other_versions( $owner_id, $version );
			return true;
		} catch ( Throwable $e ) {
			$this->logger->error( 'Thumbnail generation failed for owner {owner}: {message}', [
				'owner' => $owner_id,
				'message' => $e->getMessage(),
				'exception' => $e,
			] );
			return false;
		}
	}

	/**
	 * Renders every size with the first library that can, then stores the set.
	 *
	 * @return bool true when a full set was stored
	 */
	public function generate( ThumbnailStorage $thumbs, string $src_path, string $ext, int $owner_id, string $version ): bool {
		$animated = ImageAnimation::is_animated( $src_path, $ext );

		foreach ( $this->thumbnailers as $thumbnailer ) {
			try {
				if ( !$thumbnailer->is_available() || !$thumbnailer->supports( $ext, $animated ) ) {
					continue;
				}

				$result = $this->try_thumbnailer( $thumbnailer, $thumbs, $src_path, $ext, $animated, $owner_id, $version );
			} catch ( Throwable $e ) {
				$this->logger->warning( 'Thumbnailer {name} threw: {message}', [
					'name' => $thumbnailer->name(),
					'message' => $e->getMessage(),
					'exception' => $e,
				] );
				continue;
			}

			if ( $result !== null ) {
				return $result;
			}
		}

		$this->logger->info( 'No thumbnailer could handle {ext} (animated: {animated}) for owner {owner}', [
			'ext' => $ext,
			'animated' => $animated ? 'yes' : 'no',
			'owner' => $owner_id,
		] );

		return false;
	}

	/**
	 * @return bool|null true when stored, false when storing failed, null when this library could not render the set
	 */
	private function try_thumbnailer( Thumbnailer $thumbnailer, ThumbnailStorage $thumbs, string $src_path, string $ext, bool $animated, int $owner_id, string $version ): ?bool {
		/** @var array<int, TempFSFile> $temps */
		$temps = [];
		$targets = [];

		try {
			foreach ( $thumbs->sizes() as $size ) {
				$temp = $this->temp_factory->newTempFSFile( 'ipthumb', ThumbnailStorage::EXT );
				if ( $temp === null ) {
					$this->logger->warning( 'Could not create a temp file for thumbnails' );
					return false;
				}

				[ $max_width, $max_height ] = $thumbs->box( $size );
				$temps[$size] = $temp;
				$targets[] = [ 'path' => $temp->getPath(), 'max_width' => $max_width, 'max_height' => $max_height ];
			}

			if ( !$thumbnailer->render( $src_path, $ext, $animated, $targets ) || !$this->outputs_valid( $targets ) ) {
				$this->logger->warning( 'Thumbnailer {name} failed for owner {owner}: {error}', [
					'name' => $thumbnailer->name(),
					'owner' => $owner_id,
					'error' => $thumbnailer->last_error() !== '' ? $thumbnailer->last_error() : 'invalid output',
				] );
				return null;
			}

			foreach ( $temps as $size => $temp ) {
				$status = $thumbs->store( $owner_id, $version, $size, $temp->getPath() );
				if ( !$status->isOK() ) {
					$this->logger->warning( 'Could not store the {size} thumbnail for owner {owner}', [ 'size' => $size, 'owner' => $owner_id ] );
					return false;
				}
			}

			return true;
		} finally {
			foreach ( $temps as $temp ) {
				$temp->purge();
			}
		}
	}

	/**
	 * Verify every target is a real WebP and not nonsense.
	 *
	 * @param list<array{path: string, max_width: int, max_height: int}> $targets
	 */
	private function outputs_valid( array $targets ): bool {
		foreach ( $targets as $target ) {
			clearstatcache( true, $target['path'] );
			$info = @getimagesize( $target['path'] );

			if ( $info === false || ( $info['mime'] ?? '' ) !== 'image/webp' || $info[0] < 1 || $info[1] < 1 || $info[0] > $target['max_width'] || $info[1] > $target['max_height'] ) {
				return false;
			}
		}

		return true;
	}

}
