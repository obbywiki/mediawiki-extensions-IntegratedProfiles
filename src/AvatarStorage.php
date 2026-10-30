<?php

namespace MediaWiki\Extension\IntegratedProfiles;

use Closure;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\MediaWikiServices;
use MediaWiki\Status\Status;
use RuntimeException;
use Wikimedia\FileBackend\FileBackend;
use Wikimedia\FileBackend\FSFileBackend;
use Wikimedia\LockManager\NullLockManager;

// < 1.46
if ( !class_exists( 'Wikimedia\LockManager\NullLockManager' ) ) {
	class_alias( 'NullLockManager', 'Wikimedia\LockManager\NullLockManager' );
}

/**
 * FileBackend I/O for profile avatars under the ipavatars container (also see BannerStorage).
 *
 * Owner IDs are CentralIdLookup IDs (LocalIdLookup returns the local id when CentralAuth (or any other CentralIdLookup provider) is absent).
 */
class AvatarStorage {

	public const SERVICE_NAME = 'IntegratedProfiles.AvatarStorage';
	public const CONTAINER = 'ipavatars';
	public const THUMB_CONTAINER = 'ipavatars-thumbs';
	public const BACKEND_DOMAIN_ID = 'integratedprofiles'; // stable FileBackend domain (for farms sharing UploadDirectory)
	/** @var list<string> */
	public const EXTENSIONS = [ 'jpg', 'png', 'gif', 'webp' ];
	public const CONSTRUCTOR_OPTIONS = [
		'IntegratedProfilesBackend',
		'IntegratedProfilesUploadBaseUrl',
		'UploadDirectory',
		'UploadPath',
		'UploadBaseUrl'
	];

	private ?FileBackend $backend = null;
	private ?ThumbnailStorage $thumbs = null;

	/**
	 * @param ServiceOptions $options
	 * @param (Closure(list<string>): void)|null $purge_urls Purges the URLs of deleted files from the CDN, null skips purging
	 * @param int $thumb_retention See ThumbnailStorage::delete_superseded
	 */
	public function __construct(
		private readonly ServiceOptions $options,
		private readonly ?Closure $purge_urls = null,
		private readonly int $thumb_retention = ThumbnailStorage::DEFAULT_RETENTION,
	) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
	}

	public function file_name( int $owner_id, string $ext ): string {
		return 'avatar_' . $owner_id . '.' . $ext;
	}

	public function storage_path( int $owner_id, string $ext ): string {
		$path = $this->get_backend()->getContainerStoragePath( self::CONTAINER ) . '/' . $this->file_name( $owner_id, $ext );

		return FileBackend::normalizeStoragePath( $path ) ?? $path;
	}

	public function exists( int $owner_id, string $ext ): bool {
		return (bool)$this->get_backend()->fileExists( [
			'src' => $this->storage_path( $owner_id, $ext ),
		] );
	}

	/**
	 * Returns the first matching extension on disk for this owner (or null).
	 */
	public function find_extension( int $owner_id ): ?string {
		$found = $this->find_extension_with_mtime( $owner_id );
		return $found['ext'] ?? null;
	}

	/**
	 * @return array{ext: string, mtime: string, version: string}|null version is '' when the stat has no usable size or mtime
	 */
	public function find_extension_with_mtime( int $owner_id ): ?array {
		$found = $this->lookup_original( $owner_id );

		return is_array( $found ) ? $found : null;
	}

	/**
	 * Like find_extension_with_mtime, but tells a missing original apart from a failed lookup, for callers that delete thumbnails once the original is gone.
	 *
	 * @return array{ext: string, mtime: string, version: string}|false|null false when there is no original, null when the backend could not tell
	 */
	public function lookup_original( int $owner_id ): array|false|null {
		$backend = $this->get_backend();
		$failed = false;
		foreach ( self::EXTENSIONS as $ext ) {
			$path = $this->storage_path( $owner_id, $ext );
			$stat = $backend->getFileStat( [ 'src' => $path ] );
			if ( $stat === null ) {
				$failed = true;
				continue;
			}
			if ( !is_array( $stat ) ) {
				continue;
			}
			$mtime = isset( $stat['mtime'] ) ? (string)$stat['mtime'] : '';
			$size = isset( $stat['size'] ) ? (int)$stat['size'] : 0;

			return [ 'ext' => $ext, 'mtime' => $mtime, 'version' => ThumbnailStorage::version_for( $ext, $size, $mtime ) ];
		}

		return $failed ? null : false;
	}

	/**
	 * Returns every owner with a stored avatar from the container listing (no database involved, so it also covers central IDs from other wikis sharing the upload directory).
	 *
	 * @return list<int> ascending
	 */
	public function owner_ids(): array {
		$backend = $this->get_backend();
		$files = $backend->getFileList( [ 'dir' => $backend->getContainerStoragePath( self::CONTAINER ), 'topOnly' => true ] );
		// a missing directory lists as empty (null means the listing itself failed)
		if ( $files === null ) {
			throw new RuntimeException( 'Could not list the ' . self::CONTAINER . ' container' );
		}

		$ids = [];
		$pattern = '/^avatar_([1-9][0-9]*)\.(?:' . implode( '|', self::EXTENSIONS ) . ')$/';
		foreach ( $files as $rel ) {
			if ( preg_match( $pattern, (string)$rel, $m ) ) {
				$ids[(int)$m[1]] = true;
			}
		}

		$ids = array_keys( $ids );
		sort( $ids );

		return $ids;
	}

	/**
	 * Stores bytes from a local temp path. Writes the new file first, then removes other formats to prevent a failed write from wiping the prior avatar.
	 */
	public function store( int $owner_id, string $ext, string $src_path ): Status {
		$backend = $this->get_backend();
		$container_dir = $backend->getContainerStoragePath( self::CONTAINER );
		$prepare = $backend->prepare( [ 'dir' => $container_dir ] );
		if ( !$prepare->isOK() ) {
			return Status::wrap( $prepare );
		}

		$previous = $this->find_extension_with_mtime( $owner_id );
		$dest = $this->storage_path( $owner_id, $ext );
		$result = $backend->quickStore( [
			'src' => $src_path,
			'dst' => $dest,
			'overwrite' => true,
		] );

		$status = Status::wrap( $result );
		if ( !$status->isOK() ) {
			return $status;
		}

		$status->merge( $this->delete_other_extensions( $owner_id, $ext ) );

		// the replaced original's thumbnails stay, since pages cached on other wikis may still use them. only versions it superseded long enough ago are deleted (fails softly)
		if ( $previous !== null ) {
			$this->thumbs()->delete_superseded( $owner_id, $previous['version'], $previous['mtime'] );
		}

		return $status;
	}

	/**
	 * Handles avatar deletions. Removes every avatar_* file for the specified owner ID.
	 */
	public function delete_all( int $owner_id ): Status {
		$status = $this->delete_other_extensions( $owner_id, null );
		$status->merge( $this->thumbs()->delete_owner( $owner_id ) );

		return $status;
	}

	/**
	 * Handles avatar deletions. Removes every avatar_* file for the specified owner ID except the extension specified (if present).
	 */
	public function delete_other_extensions( int $owner_id, ?string $keep_ext ): Status {
		$backend = $this->get_backend();
		$status = Status::newGood();
		$deleted = [];

		foreach ( self::EXTENSIONS as $ext ) {
			if ( $keep_ext !== null && $ext === $keep_ext ) {
				continue;
			}

			$path = $this->storage_path( $owner_id, $ext );
			$stat = $backend->getFileStat( [ 'src' => $path ] );
			if ( !is_array( $stat ) ) {
				continue;
			}

			$result = Status::wrap( $backend->quickDelete( [ 'src' => $path ] ) );
			$status->merge( $result );
			if ( $result->isOK() ) {
				// the exact URL pages used, cache-bust included (CDNs key on the query string)
				$deleted[] = $this->public_url( $owner_id, $ext, isset( $stat['mtime'] ) ? (string)$stat['mtime'] : '' );
			}
		}

		if ( $deleted !== [] && $this->purge_urls !== null ) {
			( $this->purge_urls )( $deleted );
		}

		return $status;
	}

	/**
	 * Returns the public HTTP URL for a stored avatar, with optional cache-bust query.
	 *
	 * When $mtime is provided, skips getFileStat on the performance hot path.
	 */
	public function public_url( int $owner_id, string $ext, ?string $mtime = null ): string {
		$override = trim( (string)$this->options->get( 'IntegratedProfilesUploadBaseUrl' ) );
		$backend = null;
		$path = null;
		if ( $override === '' || $mtime === null ) {
			$backend = $this->get_backend();
			$path = $this->storage_path( $owner_id, $ext );
		}

		if ( $override !== '' ) {
			$url = rtrim( $override, '/' ) . '/' . self::CONTAINER . '/' . $this->file_name( $owner_id, $ext );
		} else {
			$url = $backend->getFileHttpUrl( [ 'src' => $path ] );
			if ( !is_string( $url ) || $url === '' ) {
				$url = $this->default_http_base() . '/' . $this->file_name( $owner_id, $ext );
			}
		}

		$bust = $mtime;
		if ( $bust === null ) {
			$stat = $backend->getFileStat( [ 'src' => $path ] );
			$bust = is_array( $stat ) && isset( $stat['mtime'] ) ? (string)$stat['mtime'] : null;
		}

		if ( $bust !== null && $bust !== '' ) {
			$url .= ( str_contains( $url, '?' ) ? '&' : '?' ) . 'r=' . rawurlencode( $bust );
		}

		return $url;
	}

	/**
	 * Thumbnail storage sharing this backend. The backend is only built once the thumbnails need it.
	 */
	public function thumbs(): ThumbnailStorage {
		$this->thumbs ??= new ThumbnailStorage(
			fn (): FileBackend => $this->get_backend(),
			self::THUMB_CONTAINER,
			ThumbnailSizes::AVATAR,
			trim( (string)$this->options->get( 'IntegratedProfilesUploadBaseUrl' ) ),
			$this->default_http_base( self::THUMB_CONTAINER ),
			true,
			$this->purge_urls,
			$this->thumb_retention
		);

		return $this->thumbs;
	}

	public function get_backend(): FileBackend {
		if ( $this->backend !== null ) {
			return $this->backend;
		}

		$named = (string)$this->options->get( 'IntegratedProfilesBackend' );

		if ( $named !== '' ) {
			$this->backend = MediaWikiServices::getInstance()->getFileBackendGroup()->get( $named );
		} else {
			$upload_dir = rtrim( (string)$this->options->get( 'UploadDirectory' ), '/\\' );
			$this->backend = new FSFileBackend( [
				'name' => self::CONTAINER . '-backend',
				'domainId' => self::BACKEND_DOMAIN_ID,
				'lockManager' => new NullLockManager( [] ),
				'containerPaths' => [
					self::CONTAINER => $upload_dir . '/' . self::CONTAINER,
					self::THUMB_CONTAINER => $upload_dir . '/' . self::THUMB_CONTAINER,
				],
				'fileMode' => 0644,
				'statusWrapper' => [ Status::class, 'wrap' ],
			] );
		}

		return $this->backend;
	}

	private function default_http_base( string $container = self::CONTAINER ): string {
		$base_url = (string)$this->options->get( 'UploadBaseUrl' );
		$upload_path = (string)$this->options->get( 'UploadPath' );

		if ( $base_url !== '' ) {
			return rtrim( $base_url, '/' ) . '/' . trim( $upload_path, '/' ) . '/' . $container;
		}

		return rtrim( $upload_path, '/' ) . '/' . $container;
	}

}
