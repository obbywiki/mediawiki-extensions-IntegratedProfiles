<?php

namespace MediaWiki\Extension\IntegratedProfiles;

use ExtensionRegistry;
use MediaWiki\MediaWikiServices;
use MediaWiki\User\UserIdentity;
use Throwable;

/**
 * Soft adapter for NewAuth verified OAuth links. 
 * This doesn't do anything without NewAuth installed and configured. Since NewAuth is a private extension, this script isn't well documented because this shouldn't apply to your installation.
 */
class NewAuthBridge {

	public const SERVICE_NAME = 'IntegratedProfiles.NewAuthBridge';
	public const PROFILE_PROVIDERS = [ 'discord', 'roblox' ]; // see na configs

	private array $memo = [];

	public function __construct(
		private readonly bool $enabled,
		private readonly mixed $connection_provider = null,
		private readonly mixed $user_factory = null,
		private readonly mixed $is_newauth_loaded = null,
		private readonly mixed $fetch_raw_links = null,
	) {
	}

	public function get_links_for_user( UserIdentity $user ): array {
		$local_id = $user->getId();
		if ( $local_id > 0 && array_key_exists( $local_id, $this->memo ) ) {
			return $this->memo[$local_id];
		}

		try {
			if ( !$this->enabled ) {
				$links = [];
			} elseif ( !$this->newauth_is_loaded() ) {
				$links = [];
			} else {
				$raw = $this->fetch_raw_links !== null ? ( $this->fetch_raw_links )( $user ) : $this->fetch_from_newauth( $user );
				$links = self::filter_for_profile( is_array( $raw ) ? $raw : [] );
			}
		} catch ( Throwable ) {
			$links = [];
		}

		if ( $local_id > 0 ) {
			$this->memo[$local_id] = $links;
		}

		return $links;
	}

	public static function filter_for_profile( array $raw ): array {
		// see na tables for types
		$allowed = array_fill_keys( self::PROFILE_PROVIDERS, true );
		$links = [];

		foreach ( $raw as $row ) {
			if ( !is_array( $row ) ) {
				continue;
			}

			$provider = strtolower( trim( (string)( $row['provider'] ?? '' ) ) );
			if ( $provider === '' || !isset( $allowed[$provider] ) ) {
				continue;
			}

			$remote_user = trim( (string)( $row['remote_user'] ?? '' ) );
			$remote_username = trim( (string)( $row['remote_username'] ?? '' ) );
			if ( $remote_user === '' && $remote_username === '' ) {
				continue;
			}

			$metadata = $row['metadata'] ?? [];
			$links[] = [ 'provider' => $provider, 'remote_user' => $remote_user, 'remote_username' => $remote_username, 'metadata' => is_array( $metadata ) ? $metadata : [] ];
		}

		return $links;
	}

	private function newauth_is_loaded(): bool {
		if ( $this->is_newauth_loaded !== null ) {
			return (bool)( $this->is_newauth_loaded )();
		}

		return ExtensionRegistry::getInstance()->isLoaded( 'NewAuth' );
	}

	private function fetch_from_newauth( UserIdentity $user ): array {
		$services = MediaWikiServices::getInstance();

		if ( $services->hasService( 'NewAuth.NewAuthService' ) ) {
			$service = $services->get( 'NewAuth.NewAuthService' );
			if ( is_object( $service ) && method_exists( $service, 'get_links_for_user' ) ) {
				$links = $service->get_links_for_user( $user );

				return is_array( $links ) ? $links : [];
			}
		}

		if ( $this->connection_provider === null || $this->user_factory === null || !class_exists( \MediaWiki\Extension\NewAuth\LinkedAccountStore::class ) ) {
			return [];
		}

		$store = new \MediaWiki\Extension\NewAuth\LinkedAccountStore(
			$this->connection_provider,
			$this->user_factory
		);

		$links = $store->get_links_for_user( $user );
		return is_array( $links ) ? $links : [];
	}

}
