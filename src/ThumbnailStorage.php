<?php

namespace MediaWiki\Extension\IntegratedProfiles;

use Closure;
use MediaWiki\Status\Status;
use Wikimedia\FileBackend\FileBackend;

/**
 * FileBackend I/O for generated thumbnails, kept in a sibling container of the originals (ipavatars-thumbs / ipbanners-thumbs).
 *
 * Layout: {container}/{owner_id}/{version}/{size}.webp
 *
 * The version is derived from the original's stat, so a replaced original gets its own directory and thumbnail URLs can be cached as immutable.
 *
 * @see AvatarStorage
 * @see BannerStorage
 */
class ThumbnailStorage {

	public const EXT = 'webp';
	public const CACHE_CONTROL = 'public, max-age=31536000, immutable';
	private const VERSION_LENGTH = 10;

	private ?FileBackend $backend = null;
	private ?bool $configured = null;

	/**
	 * @param Closure(): FileBackend $backend_provider Returns a backend that has $container registered. Only called when needed, so URLs with an override skip backend initialization.
	 * @param string $container Thumbnail container name
	 * @param list<int> $sizes Allowed sizes (see ThumbnailSizes)
	 * @param string $override_base IntegratedProfilesUploadBaseUrl, or '' when unset
	 * @param string $default_http_base Fallback URL base for the container when the backend has no HTTP URL
	 */
	public function __construct(
		private readonly Closure $backend_provider,
		private readonly string $container,
		private readonly array $sizes,
		private readonly string $override_base,
		private readonly string $default_http_base,
	) {
	}

	/**
	 * Derives a thumbnail version from the original's extension, byte size, and mtime. Returns '' when the stat is unusable.
	 */
	public static function version_for( string $ext, int $size, string $mtime ): string {
		if ( $size <= 0 || $mtime === '' ) {
			return '';
		}

		return substr( sha1( $ext . ':' . $size . ':' . $mtime ), 0, self::VERSION_LENGTH );
	}

	public static function is_valid_version( string $version ): bool {
		return strlen( $version ) === self::VERSION_LENGTH && ctype_xdigit( $version );
	}

	/**
	 * @return list<int>
	 */
	public function sizes(): array {
		return $this->sizes;
	}

	public function allows_size( int $size ): bool {
		return in_array( $size, $this->sizes, true );
	}

	public function relative_path( int $owner_id, string $version, int $size ): string {
		return $owner_id . '/' . $version . '/' . $size . '.' . self::EXT;
	}

	public function storage_path( int $owner_id, string $version, int $size ): string {
		$path = $this->container_path() . '/' . $this->relative_path( $owner_id, $version, $size );

		return FileBackend::normalizeStoragePath( $path ) ?? $path;
	}

	public function exists( int $owner_id, string $version, int $size ): bool {
		if ( !$this->is_valid_request( $owner_id, $version, $size ) ) {
			return false;
		}

		return (bool)$this->backend()->fileExists( [
			'src' => $this->storage_path( $owner_id, $version, $size ),
		] );
	}

	/**
	 * Stores a generated thumbnail from a local temp path.
	 */
	public function store( int $owner_id, string $version, int $size, string $src_path ): Status {
		if ( !$this->is_valid_request( $owner_id, $version, $size ) ) {
			return Status::newFatal( 'backend-fail-invalidpath', $this->relative_path( $owner_id, $version, $size ) );
		}

		$backend = $this->backend();
		$prepare = $backend->prepare( [ 'dir' => $this->version_dir( $owner_id, $version ) ] );
		if ( !$prepare->isOK() ) {
			return Status::wrap( $prepare );
		}

		return Status::wrap( $backend->quickStore( [
			'src' => $src_path,
			'dst' => $this->storage_path( $owner_id, $version, $size ),
			'overwrite' => true,
			'headers' => [ 'Cache-Control' => self::CACHE_CONTROL ],
		] ) );
	}

	/**
	 * Whether the backend can resolve the thumbnail container. Backends that were configured before thumbnails existed may only list the original containers.
	 */
	public function is_configured(): bool {
		$this->configured ??= $this->backend()->directoryExists( [ 'dir' => $this->container_path() ] ) !== null;

		return $this->configured;
	}

	/**
	 * Removes every thumbnail (all versions) for the provided owner.
	 */
	public function delete_owner( int $owner_id ): Status {
		// nothing can have been stored in a container that the backend cannot resolve
		if ( $owner_id <= 0 || !$this->is_configured() ) {
			return Status::newGood();
		}

		$backend = $this->backend();
		$dir = $this->owner_dir( $owner_id );
		$files = $backend->getFileList( [ 'dir' => $dir ] );
		if ( $files === null ) {
			return Status::newFatal( 'backend-fail-internal', $backend->getName() );
		}

		$status = Status::newGood();
		foreach ( $files as $rel ) {
			$status->merge( Status::wrap( $backend->quickDelete( [ 'src' => $dir . '/' . $rel ] ) ) );
		}

		// drops the now-empty owner/version directories on filesystem backends
		$backend->clean( [ 'dir' => $dir, 'recursive' => true ] );

		return $status;
	}

	/**
	 * Public HTTP URL for a thumbnail. No cache-bust query, the version directory already changes when the original does.
	 */
	public function public_url( int $owner_id, string $version, int $size ): string {
		$rel = $this->relative_path( $owner_id, $version, $size );

		if ( $this->override_base !== '' ) {
			return rtrim( $this->override_base, '/' ) . '/' . $this->container . '/' . $rel;
		}

		$url = $this->backend()->getFileHttpUrl( [ 'src' => $this->storage_path( $owner_id, $version, $size ) ] );
		if ( is_string( $url ) && $url !== '' ) {
			return $url;
		}

		return $this->default_http_base . '/' . $rel;
	}

	private function backend(): FileBackend {
		$this->backend ??= ( $this->backend_provider )();

		return $this->backend;
	}

	private function container_path(): string {
		return $this->backend()->getContainerStoragePath( $this->container );
	}

	private function owner_dir( int $owner_id ): string {
		return $this->container_path() . '/' . $owner_id;
	}

	private function version_dir( int $owner_id, string $version ): string {
		return $this->owner_dir( $owner_id ) . '/' . $version;
	}

	private function is_valid_request( int $owner_id, string $version, int $size ): bool {
		return $owner_id > 0 && self::is_valid_version( $version ) && $this->allows_size( $size );
	}

}
