<?php

namespace MediaWiki\Extension\IntegratedProfiles;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Status\Status;
use MediaWiki\User\UserIdentity;
use Wikimedia\ObjectCache\BagOStuff;

/**
 * Similar to AvatarService, but for banners.
 */
class BannerService {

	public const SERVICE_NAME = 'IntegratedProfiles.BannerService';
	public const CACHE_TTL = 86400;
	public const CONSTRUCTOR_OPTIONS = [
		'IntegratedProfilesBannerMaxBytes',
	];

	private readonly BannerValidator $validator;

	public function __construct(
		private readonly ServiceOptions $options,
		private readonly BannerStorage $storage,
		private readonly BagOStuff $cache,
		private readonly ProfileSubjectIds $subject_ids,
	) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
		$this->validator = new BannerValidator(
			(int)$options->get( 'IntegratedProfilesBannerMaxBytes' )
		);
	}

	/**
	 * @return array{banner_url: string, has_custom_banner: bool}
	 */
	public function get_banner_info_for_user( UserIdentity $user ): array {
		$ids = $this->subject_ids->ids_for( $user );
		return $this->get_banner_info( $ids['central_id'], $ids['local_id'] );
	}

	/**
	 * @return array{banner_url: string, has_custom_banner: bool}
	 */
	public function get_banner_info( int $central_id, ?int $local_id = null ): array {
		if ( $central_id <= 0 ) {
			return $this->info_from_resolved( null );
		}

		return $this->info_from_resolved(
			$this->resolve_file( $central_id, $local_id ?? $central_id )
		);
	}

	/**
	 * Batch banner info keyed by canonical username (in order of first occurrence) while skipping anonymous users.
	 *
	 * @param iterable<UserIdentity> $users
	 * @return array<string, array{banner_url: string, has_custom_banner: bool}>
	 */
	public function get_banner_info_for_users( iterable $users ): array {
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
			$local_id = $ids['local_id'];

			if ( $central_id <= 0 ) {
				$result[$name] = $this->info_from_resolved( null );
				continue;
			}

			$key = $keys_by_central[$central_id];
			if ( array_key_exists( $key, $multi ) ) {
				$parsed = $this->parse_cache_value( $multi[$key] );
				if ( $parsed === false ) {
					$result[$name] = $this->info_from_resolved( null );
					continue;
				}

				if ( is_array( $parsed ) ) {
					$result[$name] = $this->info_from_resolved( $parsed );
					continue;
				}

				// invalid cache value will fall through to storage
			}

			$result[$name] = $this->info_from_resolved(
				$this->resolve_file_from_storage( $central_id, $local_id )
			);
		}

		return $result;
	}

	/**
	 * Validates and stores an uploaded banner temp file under the central ID.
	 */
	public function upload_for_user( UserIdentity $user, string $tmp_path, int $size ): Status {
		$ids = $this->subject_ids->ids_for( $user );
		return $this->upload( $ids['central_id'], $tmp_path, $size, $ids['local_id'] );
	}

	/**
	 * @param int $central_id Storage owner ID
	 * @param string $tmp_path Local filesystem path to the upload
	 * @param int $size Byte length reported by the upload
	 * @param int|null $local_id Legacy local ID to purge after write
	 */
	public function upload( int $central_id, string $tmp_path, int $size, ?int $local_id = null ): Status {
		$validation = $this->validator->validate_upload( $tmp_path, $size );
		if ( !$validation['ok'] ) {
			return Status::newFatal( $validation['error'] );
		}

		$status = $this->storage->store( $central_id, $validation['ext'], $tmp_path );
		if ( !$status->isOK() ) {
			$status->fatal( 'integratedprofiles-error-banner-upload' );
			return $status;
		}

		if ( $local_id !== null && $local_id > 0 && $local_id !== $central_id ) {
			$status->merge( $this->storage->delete_all( $local_id ) );
		}

		$mtime = $this->storage->find_extension_with_mtime( $central_id )['mtime'] ?? '';
		$this->write_cache( $central_id, $central_id, $validation['ext'], $mtime );

		return Status::newGood( $this->get_banner_info( $central_id, $local_id ) );
	}

	/**
	 * Deletes custom banner files.
	 */
	public function delete_for_user( UserIdentity $user ): Status {
		$ids = $this->subject_ids->ids_for( $user );
		return $this->delete( $ids['central_id'], $ids['local_id'] );
	}

	public function delete( int $central_id, ?int $local_id = null ): Status {
		$status = Status::newGood();
		if ( $central_id > 0 ) {
			$status->merge( $this->storage->delete_all( $central_id ) );
		}

		if ( $local_id !== null && $local_id > 0 && $local_id !== $central_id ) {
			$status->merge( $this->storage->delete_all( $local_id ) );
		}

		if ( !$status->isOK() ) {
			$status->fatal( 'integratedprofiles-error-banner-delete' );
			return $status;
		}

		if ( $central_id > 0 ) {
			$this->write_cache_negative( $central_id );
		}

		return Status::newGood( [ 'banner_url' => '', 'has_custom_banner' => false ] );
	}

	/**
	 * @param array{owner_id: int, ext: string, mtime: string}|null $resolved
	 * @return array{banner_url: string, has_custom_banner: bool}
	 */
	private function info_from_resolved( ?array $resolved ): array {
		if ( $resolved === null ) {
			return [ 'banner_url' => '', 'has_custom_banner' => false ];
		}

		return [
			'banner_url' => $this->storage->public_url(
				$resolved['owner_id'],
				$resolved['ext'],
				$resolved['mtime'] !== '' ? $resolved['mtime'] : null
			),
			'has_custom_banner' => true,
		];
	}

	/**
	 * @return array{owner_id: int, ext: string, mtime: string}|null
	 */
	private function resolve_file( int $central_id, int $local_id ): ?array {
		$cached = $this->read_cache( $central_id );
		if ( $cached === false ) {
			return null;
		}
		if ( is_array( $cached ) ) {
			return $cached;
		}

		return $this->resolve_file_from_storage( $central_id, $local_id );
	}

	/**
	 * @return array{owner_id: int, ext: string, mtime: string}|null
	 */
	private function resolve_file_from_storage( int $central_id, int $local_id ): ?array {
		$found = $this->storage->find_extension_with_mtime( $central_id );
		if ( $found !== null ) {
			$this->write_cache( $central_id, $central_id, $found['ext'], $found['mtime'] );

			return [ 'owner_id' => $central_id, 'ext' => $found['ext'], 'mtime' => $found['mtime'] ];
		}

		if ( $local_id > 0 && $local_id !== $central_id ) {
			$legacy = $this->storage->find_extension_with_mtime( $local_id );
			if ( $legacy !== null ) {
				$this->write_cache( $central_id, $local_id, $legacy['ext'], $legacy['mtime'] );

				return [ 'owner_id' => $local_id, 'ext' => $legacy['ext'], 'mtime' => $legacy['mtime'] ];
			}
		}

		$this->write_cache_negative( $central_id );
		return null;
	}

	/**
	 * @return array{owner_id: int, ext: string, mtime: string}|false|null
	 */
	private function read_cache( int $central_id ): array|false|null {
		$raw = $this->cache->get( $this->cache_key( $central_id ) );
		if ( $raw === false || $raw === null ) {
			return null;
		}

		return $this->parse_cache_value( $raw );
	}

	/**
	 * @return array{owner_id: int, ext: string, mtime: string}|false|null
	 *   array on hit, false for negative cache, null for miss/invalid
	 */
	private function parse_cache_value( mixed $raw ): array|false|null {
		if ( $raw === false || $raw === null ) {
			return null;
		}
		if ( $raw === '' ) {
			return false;
		}
		if ( !is_string( $raw ) ) {
			return null;
		}

		$parts = explode( ':', $raw, 3 );
		if ( count( $parts ) !== 3 ) {
			return null;
		}

		[ $owner_raw, $ext, $mtime ] = $parts;
		$owner_id = (int)$owner_raw;
		if ( $owner_id <= 0 || !in_array( $ext, BannerStorage::EXTENSIONS, true ) ) {
			return null;
		}

		return [ 'owner_id' => $owner_id, 'ext' => $ext, 'mtime' => $mtime ];
	}

	private function write_cache( int $central_id, int $owner_id, string $ext, string $mtime ): void {
		$this->cache->set( $this->cache_key( $central_id ), $owner_id . ':' . $ext . ':' . $mtime, self::CACHE_TTL );
	}

	private function write_cache_negative( int $central_id ): void {
		$this->cache->set( $this->cache_key( $central_id ), '', self::CACHE_TTL );
	}

	private function cache_key( int $central_id ): string {
		return $this->cache->makeGlobalKey( 'integratedprofiles', 'banner', (string)$central_id );
	}

}
