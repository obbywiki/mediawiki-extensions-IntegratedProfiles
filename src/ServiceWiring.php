<?php

use MediaWiki\Cache\HTMLCacheUpdater;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\IntegratedProfiles\AvatarService;
use MediaWiki\Extension\IntegratedProfiles\AvatarStorage;
use MediaWiki\Extension\IntegratedProfiles\BannerService;
use MediaWiki\Extension\IntegratedProfiles\BannerStorage;
use MediaWiki\Extension\IntegratedProfiles\HookRunner;
use MediaWiki\Extension\IntegratedProfiles\NewAuthBridge;
use MediaWiki\Extension\IntegratedProfiles\ProfileHandler;
use MediaWiki\Extension\IntegratedProfiles\ProfileLanguageLinks;
use MediaWiki\Extension\IntegratedProfiles\ProfilePagePurger;
use MediaWiki\Extension\IntegratedProfiles\ProfilePermissions;
use MediaWiki\Extension\IntegratedProfiles\ProfileRenderer;
use MediaWiki\Extension\IntegratedProfiles\ProfileService;
use MediaWiki\Extension\IntegratedProfiles\ProfileSubjectIds;
use MediaWiki\Extension\IntegratedProfiles\ProfileTabs;
use MediaWiki\Html\TemplateParser;
use MediaWiki\MediaWikiServices;

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

	AvatarStorage::SERVICE_NAME => static function ( MediaWikiServices $services ): AvatarStorage {
		return new AvatarStorage(
			new ServiceOptions(
				AvatarStorage::CONSTRUCTOR_OPTIONS,
				$services->getMainConfig()
			)
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
			$services->get( ProfileSubjectIds::SERVICE_NAME )
		);
	},

	BannerStorage::SERVICE_NAME => static function ( MediaWikiServices $services ): BannerStorage {
		return new BannerStorage(
			new ServiceOptions(
				BannerStorage::CONSTRUCTOR_OPTIONS,
				$services->getMainConfig()
			)
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
			$services->get( ProfileSubjectIds::SERVICE_NAME )
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
