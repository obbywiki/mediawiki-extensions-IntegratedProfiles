<?php

namespace MediaWiki\Extension\IntegratedProfiles;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\MediaWikiServices;
use MediaWiki\Status\Status;
use Wikimedia\FileBackend\FileBackend;
use Wikimedia\FileBackend\FSFileBackend;
use Wikimedia\LockManager\NullLockManager;

/**
 * FileBackend I/O for profile banners under the ipbanners container (also see AvatarStorage).
 *
 * Owner IDs are CentralIdLookup IDs (local id is used instead if CentralAuth is absent).
 */
class BannerStorage {

	public const SERVICE_NAME = 'IntegratedProfiles.BannerStorage';
	public const CONTAINER = 'ipbanners';
	public const BACKEND_DOMAIN_ID = 'integratedprofiles'; // stable FileBackend domain (for farms sharing UploadDirectory)
	/** @var list<string> */
	public const EXTENSIONS = [ 'jpg', 'png', 'gif', 'webp' ];
	public const CONSTRUCTOR_OPTIONS = [
		'IntegratedProfilesBackend',
		'UploadDirectory',
		'UploadPath',
		'UploadBaseUrl',
	];

	private ?FileBackend $backend = null;

	public function __construct(
		private readonly ServiceOptions $options,
	) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
	}

	public function file_name( int $owner_id, string $ext ): string {
		return 'banner_' . $owner_id . '.' . $ext;
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
	 * @return array{ext: string, mtime: string}|null
	 */
	public function find_extension_with_mtime( int $owner_id ): ?array {
		$backend = $this->get_backend();
		foreach ( self::EXTENSIONS as $ext ) {
			$path = $this->storage_path( $owner_id, $ext );
			$stat = $backend->getFileStat( [ 'src' => $path ] );

			if ( !is_array( $stat ) ) {
				continue;
			}

			$mtime = isset( $stat['mtime'] ) ? (string)$stat['mtime'] : '';

			return [ 'ext' => $ext, 'mtime' => $mtime ];
		}
		return null;
	}

	/**
	 * Stores bytes from a local temp path. Writes the new file first, then removes other formats to prevent a failed write from wiping the prior banner.
	 */
	public function store( int $owner_id, string $ext, string $src_path ): Status {
		$backend = $this->get_backend();
		$container_dir = $backend->getContainerStoragePath( self::CONTAINER );
		$prepare = $backend->prepare( [ 'dir' => $container_dir ] );
		if ( !$prepare->isOK() ) {
			return Status::wrap( $prepare );
		}

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
		return $status;
	}

	/**
	 * Handles banner deletions. Removes every banner_* file for the specified owner ID.
	 */
	public function delete_all( int $owner_id ): Status {
		return $this->delete_other_extensions( $owner_id, null );
	}

	/**
	 * Handles banner deletions. Removes every banner_* file for the specified owner ID except the extension specified (if present).
	 */
	public function delete_other_extensions( int $owner_id, ?string $keep_ext ): Status {
		$backend = $this->get_backend();
		$status = Status::newGood();

		foreach ( self::EXTENSIONS as $ext ) {
			if ( $keep_ext !== null && $ext === $keep_ext ) {
				continue;
			}

			$path = $this->storage_path( $owner_id, $ext );
			if ( !$backend->fileExists( [ 'src' => $path ] ) ) {
				continue;
			}

			$result = $backend->quickDelete( [ 'src' => $path ] );
			$status->merge( Status::wrap( $result ) );
		}

		return $status;
	}

	/**
	 * Returns the public HTTP URL for a stored banner, with optional cache-bust query.
	 *
	 * When $mtime is provided, skips getFileStat on the performance hot path.
	 */
	public function public_url( int $owner_id, string $ext, ?string $mtime = null ): string {
		$backend = $this->get_backend();
		$path = $this->storage_path( $owner_id, $ext );

		$url = $backend->getFileHttpUrl( [ 'src' => $path ] );
		if ( !is_string( $url ) || $url === '' ) {
			$url = $this->default_http_base() . '/' . $this->file_name( $owner_id, $ext );
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

	public function get_backend(): FileBackend {
		if ( $this->backend !== null ) {
			return $this->backend;
		}

		$named = (string)$this->options->get( 'IntegratedProfilesBackend' );
		$services = MediaWikiServices::getInstance();

		if ( $named !== '' ) {
			$this->backend = $services->getFileBackendGroup()->get( $named );
		} else {
			$upload_dir = rtrim( (string)$this->options->get( 'UploadDirectory' ), '/\\' );
			$this->backend = new FSFileBackend( [
				'name' => self::CONTAINER . '-backend',
				'domainId' => self::BACKEND_DOMAIN_ID,
				'lockManager' => new NullLockManager( [] ),
				'containerPaths' => [
					self::CONTAINER => $upload_dir . '/' . self::CONTAINER,
				],
				'fileMode' => 0644,
				'statusWrapper' => [ Status::class, 'wrap' ],
			] );
		}

		return $this->backend;
	}

	private function default_http_base(): string {
		$base_url = (string)$this->options->get( 'UploadBaseUrl' );
		$upload_path = (string)$this->options->get( 'UploadPath' );

		if ( $base_url !== '' ) {
			return rtrim( $base_url, '/' ) . '/' . trim( $upload_path, '/' ) . '/' . self::CONTAINER;
		}

		return rtrim( $upload_path, '/' ) . '/' . self::CONTAINER;
	}

}
