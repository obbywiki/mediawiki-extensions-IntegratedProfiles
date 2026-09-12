<?php

namespace MediaWiki\Extension\IntegratedProfiles;

use ExtensionRegistry;

/**
 * Wiki-configurable banners (see $wgIntegratedProfilesBannerPresetImages).
 */
class BannerPresets {

	/** @var array<string, string> id => sanitized URL */
	private readonly array $images;

	/**
	 * @param array<mixed, mixed> $raw $wgIntegratedProfilesBannerPresetImages
	 */
	public function __construct( array $raw ) {
		$this->images = self::parse( $raw );
	}

	/**
	 * @return array<string, string>
	 */
	public function images(): array {
		return $this->images;
	}

	public function is_split(): bool {
		return ExtensionRegistry::getInstance()->isLoaded( 'GlobalPreferences' );
	}

	public function url_for( string $id ): string {
		return $this->images[$id] ?? '';
	}

	public function normalize_wiki_id( string $value ): string {
		$value = strtolower( trim( $value ) );
		if ( $value === '' || $value === ProfileFields::BANNER_CUSTOM ) {
			return '';
		}
		if ( !self::is_id( $value ) || $this->url_for( $value ) === '' ) {
			return '';
		}

		return $value;
	}

	/**
	 * @return array{url: string}
	 */
	public function resolve( string $global_mode, string $wiki_id, bool $has_custom, string $custom_url ): array {
		$global_mode = ProfileFields::normalize_banner( $global_mode );
		$wiki_id = $this->normalize_wiki_id( $wiki_id );

		if ( $wiki_id !== '' ) {
			$url = $this->url_for( $wiki_id );
			if ( $url !== '' ) {
				return [ 'url' => $url ];
			}
		}

		if ( $global_mode === ProfileFields::BANNER_CUSTOM && $has_custom && $custom_url !== '' ) {
			return [ 'url' => $custom_url ];
		}

		if ( !$this->is_split() && $global_mode !== ProfileFields::BANNER_CUSTOM ) {
			$url = $this->url_for( $global_mode );
			if ( $url !== '' ) {
				return [ 'url' => $url ];
			}
		}

		return [ 'url' => '' ];
	}

	/**
	 * @param array<mixed, mixed> $raw
	 * @return array<string, string>
	 */
	private static function parse( array $raw ): array {
		$images = [];
		foreach ( $raw as $id => $url ) {
			if ( !is_string( $id ) || !is_string( $url ) ) {
				continue;
			}

			$id = strtolower( trim( $id ) );
			$url = trim( $url );
			
			if ( !self::is_id( $id ) || $id === ProfileFields::BANNER_CUSTOM ) {
				continue;
			}
			if ( !self::is_allowed_url( $url ) ) {
				continue;
			}

			$images[$id] = $url;
		}

		return $images;
	}

	private static function is_id( string $id ): bool {
		return (bool)preg_match( '/^[a-z0-9-]{1,32}$/', $id );
	}

	private static function is_allowed_url( string $url ): bool {
		if ( $url === '' || str_starts_with( $url, '//' ) ) {
			return false;
		}
		if ( str_starts_with( $url, '/' ) ) {
			return true;
		}

		$parts = parse_url( $url );
		if ( $parts === false ) {
			return false;
		}

		$scheme = strtolower( (string)( $parts['scheme'] ?? '' ) );

		return $scheme === 'http' || $scheme === 'https';
	}

}
