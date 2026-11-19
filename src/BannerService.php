<?php

namespace MediaWiki\Extension\IntegratedProfiles;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\User\UserIdentity;
use Wikimedia\ObjectCache\BagOStuff;

/**
 * Resolves profile banner URLs and thumbnails.
 */
class BannerService extends ProfileImageService {

	public const SERVICE_NAME = 'IntegratedProfiles.BannerService';
	public const CACHE_TTL = ProfileImageResolver::CACHE_TTL;
	public const CACHE_KEY_VERSION = ProfileImageResolver::CACHE_KEY_VERSION;
	public const CONSTRUCTOR_OPTIONS = [
		'IntegratedProfilesBannerMaxBytes',
		'IntegratedProfilesEnableAnimatedBanners',
	];

	public function __construct(
		ServiceOptions $options,
		BannerStorage $storage,
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
			'banner',
			new BannerValidator( (int)$options->get( 'IntegratedProfilesBannerMaxBytes' ) ),
			'IntegratedProfilesEnableAnimatedBanners',
		);
	}

	/**
	 * @return array{banner_url: string, has_custom_banner: bool, banner_thumbs: array<int, string>}
	 */
	public function get_banner_info_for_user( UserIdentity $user ): array {
		$ids = $this->subject_ids->ids_for( $user );
		return $this->get_banner_info( $ids['central_id'] );
	}

	/**
	 * @return array{banner_url: string, has_custom_banner: bool, banner_thumbs: array<int, string>}
	 */
	public function get_banner_info( int $central_id ): array {
		if ( $central_id <= 0 ) {
			return $this->info_from_resolved( null );
		}

		return $this->info_from_resolved( $this->resolver->resolve( $central_id ) );
	}

	/**
	 * Batch banner info keyed by canonical username (in order of first occurrence) while skipping anonymous users.
	 *
	 * @param iterable<UserIdentity> $users
	 * @return array<string, array{banner_url: string, has_custom_banner: bool, banner_thumbs: array<int, string>}>
	 */
	public function get_banner_info_for_users( iterable $users ): array {
		$result = [];
		foreach ( $this->resolver->resolve_for_users( $users ) as $name => $resolved ) {
			$result[$name] = $this->info_from_resolved( $resolved );
		}

		return $result;
	}

	/** @inheritDoc */
	protected function get_uploaded_image_info( int $central_id ): array {
		return $this->get_banner_info( $central_id );
	}

	/**
	 * @param array{owner_id: int, ext: string, mtime: string, version: string, thumbs: bool}|null $resolved
	 * @return array{banner_url: string, has_custom_banner: bool, banner_thumbs: array<int, string>}
	 */
	protected function info_from_resolved( ?array $resolved ): array {
		if ( $resolved === null ) {
			return [ 'banner_url' => '', 'has_custom_banner' => false, 'banner_thumbs' => [] ];
		}

		return [
			'banner_url' => $this->storage->public_url(
				$resolved['owner_id'],
				$resolved['ext'],
				$resolved['mtime'] !== '' ? $resolved['mtime'] : null
			),
			'has_custom_banner' => true,
			'banner_thumbs' => $resolved['thumbs'] ? $this->storage->thumbs()->public_urls( $resolved['owner_id'], $resolved['version'] ) : [],
		];
	}

}
