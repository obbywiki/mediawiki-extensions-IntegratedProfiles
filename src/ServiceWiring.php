<?php

use MediaWiki\Cache\HTMLCacheUpdater;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\IntegratedProfiles\AvatarService;
use MediaWiki\Extension\IntegratedProfiles\AvatarStorage;
use MediaWiki\Extension\IntegratedProfiles\BannerService;
use MediaWiki\Extension\IntegratedProfiles\BannerStorage;
use MediaWiki\Extension\IntegratedProfiles\HookRunner;
use MediaWiki\Extension\IntegratedProfiles\ImageUploadSanitizer;
use MediaWiki\Extension\IntegratedProfiles\ImageUploadVerifier;
use MediaWiki\Extension\IntegratedProfiles\NewAuthBridge;
use MediaWiki\Extension\IntegratedProfiles\ProfileHandler;
use MediaWiki\Extension\IntegratedProfiles\ProfileLanguageLinks;
use MediaWiki\Extension\IntegratedProfiles\ProfilePagePurger;
use MediaWiki\Extension\IntegratedProfiles\ProfilePermissions;
use MediaWiki\Extension\IntegratedProfiles\ProfileRenderer;
use MediaWiki\Extension\IntegratedProfiles\ProfileService;
use MediaWiki\Extension\IntegratedProfiles\ProfileSubjectIds;
use MediaWiki\Extension\IntegratedProfiles\ProfileTabs;
use MediaWiki\Extension\IntegratedProfiles\ThumbnailGenerator;
use MediaWiki\Extension\IntegratedProfiles\ThumbnailStorage;
use MediaWiki\Html\TemplateParser;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;

/**
 * The CDN purge callback for deleted media (or null), and how long a replaced version's thumbnails are kept (see the docs).
 *
 * @return array{0: (Closure(list<string>): void)|null, 1: int}
 */
$media_cleanup = static function ( MediaWikiServices $services ): array {
	$config = $services->getMainConfig();
	$purge_urls = (bool)$config->get( 'IntegratedProfilesPurgeMediaUrls' ) ?
		static function ( array $urls ) use ( $services ): void {
			$services->get( ProfilePagePurger::SERVICE_NAME )->purge_urls( $urls );
		} :
		null;

	$retention = max( ThumbnailStorage::DEFAULT_RETENTION, AvatarService::CACHE_TTL + (int)$config->get( 'CdnMaxAge' ) );

	return [ $purge_urls, $retention ];
};

return [
	'IntegratedProfiles.ProfilePermissions' => static function (): ProfilePermissions {
		return new ProfilePermissions();
	},

	'IntegratedProfiles.ProfileRenderer' => static function (): ProfileRenderer {
		return new ProfileRenderer( new TemplateParser( dirname( __DIR__ ) . '/templates' ) );
	},

	'IntegratedProfiles.HookRunner' => static function ( MediaWikiServices $services ): HookRunner {
		return new HookRunner( $services->getHookContainer() );
	},

	ProfileTabs::SERVICE_NAME => static function ( MediaWikiServices $services ): ProfileTabs {
		return new ProfileTabs(
			$services->get( 'IntegratedProfiles.HookRunner' )
		);
	},

	ProfileHandler::SERVICE_NAME => static function ( MediaWikiServices $services ): ProfileHandler {
		return new ProfileHandler(
			$services->get( ProfileService::SERVICE_NAME ),
			$services->get( 'IntegratedProfiles.ProfileRenderer' ),
			$services->get( 'IntegratedProfiles.HookRunner' ),
			$services->get( ProfileTabs::SERVICE_NAME )
		);
	},

	ProfileSubjectIds::SERVICE_NAME => static function ( MediaWikiServices $services ): ProfileSubjectIds {
		return new ProfileSubjectIds(
			$services->getCentralIdLookup()
		);
	},

	ProfileLanguageLinks::SERVICE_NAME => static function ( MediaWikiServices $services ): ProfileLanguageLinks {
		$config = $services->getMainConfig();

		return new ProfileLanguageLinks(
			(array)$config->get( 'IntegratedProfilesLanguageInterwikis' ),
			$services->getContentLanguage()->getCode(),
			(array)$config->get( 'LocalInterwikis' )
		);
	},

	ProfilePagePurger::SERVICE_NAME => static function ( MediaWikiServices $services ): ProfilePagePurger {
		// 1.45: HtmlCacheUpdater; 1.46+: HTMLCacheUpdater
		/** @var HTMLCacheUpdater $html_cache */
		$html_cache = $services->hasService( 'HTMLCacheUpdater' ) ? $services->getService( 'HTMLCacheUpdater' ) : $services->getService( 'HtmlCacheUpdater' );

		return new ProfilePagePurger( $html_cache, $services->getTitleFactory() );
	},

	AvatarStorage::SERVICE_NAME => static function ( MediaWikiServices $services ) use ( $media_cleanup ): AvatarStorage {
		[ $purge_urls, $retention ] = $media_cleanup( $services );

		return new AvatarStorage(
			new ServiceOptions(
				AvatarStorage::CONSTRUCTOR_OPTIONS,
				$services->getMainConfig()
			),
			$purge_urls,
			$retention
		);
	},

	ThumbnailGenerator::SERVICE_NAME => static function ( MediaWikiServices $services ): ThumbnailGenerator {
		$config = $services->getMainConfig();

		return new ThumbnailGenerator(
			ThumbnailGenerator::thumbnailers_from_config(
				(array)$config->get( 'IntegratedProfilesThumbnailers' ),
				(string)$config->get( 'IntegratedProfilesVipsCommand' )
			),
			$services->getTempFSFileFactory(),
			LoggerFactory::getInstance( 'IntegratedProfiles' )
		);
	},

	AvatarService::SERVICE_NAME => static function ( MediaWikiServices $services ): AvatarService {
		$cache_type = $services->getMainConfig()->get( 'IntegratedProfilesCacheType' );
		$cache_factory = $services->getObjectCacheFactory();

		return new AvatarService(
			new ServiceOptions(
				AvatarService::CONSTRUCTOR_OPTIONS,
				$services->getMainConfig()
			),

			$services->get( AvatarStorage::SERVICE_NAME ),
			$cache_type === null ? $cache_factory->getLocalClusterInstance() : $cache_factory->getInstance( $cache_type ),
			$services->get( ProfileSubjectIds::SERVICE_NAME ),
			new ImageUploadSanitizer( $services->getTempFSFileFactory() ),
			new ImageUploadVerifier( $services->get( 'IntegratedProfiles.HookRunner' ) ),
			$services->get( ThumbnailGenerator::SERVICE_NAME ),
			$services->get( ProfilePagePurger::SERVICE_NAME )
		);
	},

	BannerStorage::SERVICE_NAME => static function ( MediaWikiServices $services ) use ( $media_cleanup ): BannerStorage {
		[ $purge_urls, $retention ] = $media_cleanup( $services );

		return new BannerStorage(
			new ServiceOptions(
				BannerStorage::CONSTRUCTOR_OPTIONS,
				$services->getMainConfig()
			),
			$purge_urls,
			$retention
		);
	},

	BannerService::SERVICE_NAME => static function ( MediaWikiServices $services ): BannerService {
		$cache_type = $services->getMainConfig()->get( 'IntegratedProfilesCacheType' );
		$cache_factory = $services->getObjectCacheFactory();

		return new BannerService(
			new ServiceOptions(
				BannerService::CONSTRUCTOR_OPTIONS,
				$services->getMainConfig()
			),

			$services->get( BannerStorage::SERVICE_NAME ),
			$cache_type === null ? $cache_factory->getLocalClusterInstance() : $cache_factory->getInstance( $cache_type ),
			$services->get( ProfileSubjectIds::SERVICE_NAME ),
			new ImageUploadSanitizer( $services->getTempFSFileFactory() ),
			new ImageUploadVerifier( $services->get( 'IntegratedProfiles.HookRunner' ) ),
			$services->get( ThumbnailGenerator::SERVICE_NAME ),
			$services->get( ProfilePagePurger::SERVICE_NAME )
		);
	},

	NewAuthBridge::SERVICE_NAME => static function ( MediaWikiServices $services ): NewAuthBridge {
		return new NewAuthBridge(
			(bool)$services->getMainConfig()->get( 'IntegratedProfilesEnableNewAuthPanel' ),
			$services->getConnectionProvider(),
			$services->getUserFactory()
		);
	},

	ProfileService::SERVICE_NAME => static function ( MediaWikiServices $services ): ProfileService {
		return new ProfileService(
			new ServiceOptions(
				ProfileService::CONSTRUCTOR_OPTIONS,
				$services->getMainConfig()
			),

			$services->get( 'IntegratedProfiles.ProfilePermissions' ),

			$services->get( ProfileSubjectIds::SERVICE_NAME ),
			$services->get( AvatarService::SERVICE_NAME ),
			$services->get( BannerService::SERVICE_NAME ),
			$services->get( NewAuthBridge::SERVICE_NAME ),

			$services->getUserFactory(),
			$services->getUserOptionsLookup(),
			$services->getUserOptionsManager(),
			$services->getUserGroupManager(),
			$services->getUserRegistrationLookup()
		);
	},
];
