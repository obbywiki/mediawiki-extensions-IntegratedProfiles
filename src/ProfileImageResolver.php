<?php

namespace MediaWiki\Extension\IntegratedProfiles;

use MediaWiki\User\UserIdentity;
use Wikimedia\ObjectCache\BagOStuff;

/**
 * Cached original and thumbnail resolution shared by avatar and banner services.
 */
class ProfileImageResolver {

	public const CACHE_TTL = 86400;
	public const CACHE_KEY_VERSION = '4';

	public function __construct(
		private readonly ProfileImageStorage $storage,
		private readonly BagOStuff $cache,
		private readonly ProfileSubjectIds $subject_ids,
		private readonly string $namespace,
		private readonly int $ttl = self::CACHE_TTL,
		private readonly string $cache_version = self::CACHE_KEY_VERSION,
	) {
	}

	/**
	 * Batch resolution by canonical username, skipping anonymous users and duplicates.
	 *
	 * @param iterable<UserIdentity> $users
	 * @return array<string, array{owner_id: int, ext: string, mtime: string, version: string, thumbs: bool}|null>
	 */
	public function resolve_for_users( iterable $users ): array {
		/** @var array<string, array{central_id: int, local_id: int}> $subjects */
		$subjects = [];
		foreach ( $users as $user ) {
			if ( $user->getId() <= 0 ) {
				continue;
			}

			$name = $user->getName();
			if ( $name === '' || isset( $subjects[$name] ) ) {
				continue;
			}

			$subjects[$name] = $this->subject_ids->ids_for( $user );
		}

		if ( $subjects === [] ) {
			return [];
		}

		$keys_by_central = [];
		foreach ( $subjects as $ids ) {
			$central_id = $ids['central_id'];
			if ( $central_id > 0 && !isset( $keys_by_central[$central_id] ) ) {
				$keys_by_central[$central_id] = $this->cache_key( $central_id );
			}
		}

		$multi = $keys_by_central === [] ? [] : $this->cache->getMulti( array_values( $keys_by_central ) );

		$result = [];
		foreach ( $subjects as $name => $ids ) {
			$central_id = $ids['central_id'];

			if ( $central_id <= 0 ) {
				$result[$name] = null;
				continue;
			}

			$key = $keys_by_central[$central_id];
			if ( array_key_exists( $key, $multi ) ) {
				$parsed = $this->parse_cache_value( $multi[$key], $central_id );
				if ( $parsed === false ) {
					$result[$name] = null;
					continue;
				}

				if ( is_array( $parsed ) ) {
					$result[$name] = $parsed;
					continue;
				}

				// invalid cache value will fall through to storage
			}

			$result[$name] = $this->resolve_file_from_storage( $central_id );
		}

		return $result;
	}

	/**
	 * @return array{owner_id: int, ext: string, mtime: string, version: string, thumbs: bool}|null
	 */
	public function resolve( int $central_id ): ?array {
		if ( $central_id <= 0 ) {
			return null;
		}

		$cached = $this->read_cache( $central_id );
		if ( $cached === false ) {
			return null;
		}

		if ( is_array( $cached ) ) {
			return $cached;
		}

		return $this->resolve_file_from_storage( $central_id );
	}

	/**
	 * @return array{owner_id: int, ext: string, mtime: string, version: string, thumbs: bool}|null
	 */
	private function resolve_file_from_storage( int $central_id ): ?array {
		$found = $this->storage->lookup_original( $central_id );
		if ( $found === null ) {
			// the backend could not tell, so cache nothing (a cached negative would hide the file for CACHE_TTL)
			return null;
		}
		if ( $found !== false ) {
			$version = $found['version'] ?? '';
			$thumbs = $this->storage->thumbs()->set_exists( $central_id, $version );
			$this->remember( $central_id, $found['ext'], $found['mtime'], $version, $thumbs );

			return [ 'owner_id' => $central_id, 'ext' => $found['ext'], 'mtime' => $found['mtime'], 'version' => $version, 'thumbs' => $thumbs ];
		}

		$this->remember_missing( $central_id );
		return null;
	}

	/**
	 * @return array{owner_id: int, ext: string, mtime: string, version: string, thumbs: bool}|false|null array on hit, false for a cached absence, null for a miss or invalid value
	 */
	private function read_cache( int $central_id ): array|false|null {
		$raw = $this->cache->get( $this->cache_key( $central_id ) );
		if ( $raw === false || $raw === null ) {
			return null;
		}

		return $this->parse_cache_value( $raw, $central_id );
	}

	/**
	 * @return array{owner_id: int, ext: string, mtime: string, version: string, thumbs: bool}|false|null array on hit, false for a cached absence, null for a miss or invalid value
	 */
	private function parse_cache_value( mixed $raw, int $central_id ): array|false|null {
		if ( $raw === false || $raw === null ) {
			return null;
		}
		if ( $raw === '' ) {
			return false;
		}
		if ( !is_string( $raw ) ) {
			return null;
		}

		$parts = explode( ':', $raw, 5 );
		if ( count( $parts ) !== 5 ) {
			return null;
		}

		[ $owner_raw, $ext, $mtime, $version, $thumbs ] = $parts;
		$owner_id = (int)$owner_raw;

		if ( $owner_id !== $central_id || !in_array( $ext, ProfileImageStorage::EXTENSIONS, true ) ||
			( $version !== '' && !ThumbnailStorage::is_valid_version( $version ) )
		) {
			return null;
		}

		return [ 'owner_id' => $owner_id, 'ext' => $ext, 'mtime' => $mtime, 'version' => $version, 'thumbs' => $thumbs === '1' && $version !== '' ];
	}

	public function remember( int $central_id, string $ext, string $mtime, string $version, bool $thumbs ): void {
		$this->cache->set(
			$this->cache_key( $central_id ),
			$central_id . ':' . $ext . ':' . $mtime . ':' . $version . ':' . ( $thumbs ? '1' : '0' ),
			$this->ttl
		);
	}

	public function remember_missing( int $central_id ): void {
		$this->cache->set( $this->cache_key( $central_id ), '', $this->ttl );
	}

	private function cache_key( int $central_id ): string {
		return $this->cache->makeGlobalKey(
			'integratedprofiles',
			$this->namespace,
			$this->cache_version,
			(string)$central_id
		);
	}

	public function forget( int $central_id ): void {
		if ( $central_id > 0 ) {
			$this->cache->delete( $this->cache_key( $central_id ) );
		}
	}

}
