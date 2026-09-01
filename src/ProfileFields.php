<?php

namespace MediaWiki\Extension\IntegratedProfiles;

/**
 * Handles profile fields, validation, keys, sanitization, URL builders, etc.
 *
 * TODO: probably make some of the rulesets configurable or not hardcoded
 */
class ProfileFields {

	public const KEY_ABOUT = 'ip-about';
	public const KEY_FEATURED_ARTICLE = 'ip-featured-article';
	public const KEY_WEBSITE = 'ip-website';
	public const KEY_TWITTER = 'ip-twitter';
	public const KEY_GITHUB = 'ip-github';
	public const KEY_MEDIAWIKI = 'ip-mediawiki';
	public const KEY_MIRAHEZE = 'ip-miraheze';
	public const KEY_FANDOM = 'ip-fandom';
	public const KEY_BANNER = 'ip-banner';
	public const KEY_HIDE_CONNECTIONS = 'ip-hide-connections';
	public const KEY_VISIBILITY = 'ip-visibility';

	public const VISIBILITY_PUBLIC = 'public';
	public const VISIBILITY_USERS = 'users';
	public const VISIBILITY_PRIVATE = 'private';

	/** @var list<string> Allowed ip-visibility values */
	public const VISIBILITY_PRESETS = [
		self::VISIBILITY_PUBLIC,
		self::VISIBILITY_USERS,
		self::VISIBILITY_PRIVATE,
	];

	public const BANNER_ACCENT = 'accent';
	public const BANNER_CUSTOM = 'custom';

	/** @var list<string> All allowed ip-banner values */
	public const BANNER_PRESETS = [
		'accent',
		'ocean',
		'sunset',
		'forest',
		'midnight',
		'ember',
		'sand',
		'aurora',
		'custom'
	];

	/** @var list<string> Gradient presets (editor swatches; excludes custom) */
	public const BANNER_GRADIENT_PRESETS = [
		'accent',
		'ocean',
		'sunset',
		'forest',
		'midnight',
		'ember',
		'sand',
		'aurora'
	];

	/** @var list<string> */
	public const KEYS = [
		self::KEY_ABOUT,
		self::KEY_FEATURED_ARTICLE,
		self::KEY_WEBSITE,
		self::KEY_TWITTER,
		self::KEY_GITHUB,
		self::KEY_MEDIAWIKI,
		self::KEY_MIRAHEZE,
		self::KEY_FANDOM,
		self::KEY_BANNER,
		self::KEY_HIDE_CONNECTIONS,
		self::KEY_VISIBILITY,
	];

	private const FLAG_KEYS = [
		self::KEY_HIDE_CONNECTIONS => true,
	];

	private const HANDLE_KEYS = [
		self::KEY_TWITTER => true,
		self::KEY_GITHUB => true,
	];

	private const WIKI_PROFILE_BASES = [
		self::KEY_MEDIAWIKI => 'https://www.mediawiki.org/wiki/',
		self::KEY_MIRAHEZE => 'https://meta.miraheze.org/wiki/',
		self::KEY_FANDOM => 'https://community.fandom.com/wiki/',
	];

	private const HANDLE_PATTERN = '/^[A-Za-z0-9._-]{1,64}$/';

	private const WIKI_USERNAME_PATTERN = '/^[^\x00-\x1f\x7f#<>\[\]|{}\/]{1,255}$/u';

	public function __construct(
		private readonly int $about_max_length = 500,
		private readonly int $link_max_length = 255,
	) {
	}

	/**
	 * Returns an empty field map with all the known keys.
	 *
	 * @return array<string, string>
	 */
	public function empty_fields(): array {
		$fields = [];

		foreach ( self::KEYS as $key ) {
			if ( $key === self::KEY_BANNER ) {
				$fields[$key] = self::BANNER_ACCENT;
			} elseif ( $key === self::KEY_VISIBILITY ) {
				$fields[$key] = self::VISIBILITY_PUBLIC;
			} elseif ( isset( self::FLAG_KEYS[$key] ) ) {
				$fields[$key] = '0';
			} else {
				$fields[$key] = '';
			}
		}

		return $fields;
	}

	/**
	 * Returns whether a stored 0/1 pref flag is on.
	 */
	public static function is_flag_on( string $value ): bool {
		$value = strtolower( trim( $value ) );

		return $value === '1' || $value === 'true';
	}

	/**
	 * @param array<string, mixed> $payload
	 * @return array<string, mixed>
	 */
	public static function scrub_private_payload( array $payload ): array {
		$visibility = self::normalize_visibility(
			(string)( $payload['fields'][ self::KEY_VISIBILITY ] ?? self::VISIBILITY_PRIVATE )
		);

		$empty = ( new self() )->empty_fields();
		$empty[ self::KEY_VISIBILITY ] = $visibility;
		$empty[ self::KEY_BANNER ] = self::BANNER_ACCENT;

		$payload['is_private'] = true;
		$payload['real_name'] = '';
		$payload['edit_count'] = 0;
		$payload['registration'] = null;
		$payload['groups'] = [];
		$payload['fields'] = $empty;
		$payload['links'] = [];
		$payload['wiki_profiles'] = [];
		$payload['featured_article'] = null;
		$payload['banner_url'] = '';
		$payload['has_custom_banner'] = false;
		$payload['connections'] = [];

		return $payload;
	}

	/**
	 * Normalizes a stored/submitted visibility mode.
	 */
	public static function normalize_visibility( string $value ): string {
		$value = strtolower( trim( $value ) );

		if ( $value === '' || !in_array( $value, self::VISIBILITY_PRESETS, true ) ) {
			return self::VISIBILITY_PUBLIC;
		}

		return $value;
	}

	/**
	 * Normalizes a stored/submitted banner ID to an allowlisted preset.
	 */
	public static function normalize_banner( string $value ): string {
		$value = strtolower( trim( $value ) );

		if ( $value === '' || !in_array( $value, self::BANNER_PRESETS, true ) ) {
			return self::BANNER_ACCENT;
		}

		return $value;
	}

	/**
	 * Sanitizes a partial/full field map.
	 *
	 * @param array<string, mixed> $input
	 * @return array{fields: array<string, string>, invalid: list<string>}
	 */
	public function sanitize_fields( array $input ): array {
		$fields = [];
		$invalid = [];

		foreach ( self::KEYS as $key ) {
			if ( !array_key_exists( $key, $input ) ) {
				continue;
			}

			$raw = $input[$key];
			if ( !is_string( $raw ) && !is_numeric( $raw ) ) {
				$invalid[] = $key;
				continue;
			}

			$value = trim( (string)$raw );
			$sanitized = $this->sanitize_one( $key, $value );
			if ( $sanitized === null ) {
				$invalid[] = $key;
				continue;
			}

			$fields[$key] = $sanitized;
		}

		return [ 'fields' => $fields, 'invalid' => $invalid ];
	}

	/**
	 * Builds public link descriptors from sanitized fields.
	 *
	 * @param array<string, string> $fields
	 * @return array<string, array{label: string, url: string, kind: string}>
	 */
	public function build_public_links( array $fields ): array {
		$links = [];

		$website = trim( $fields[self::KEY_WEBSITE] ?? '' );
		if ( $website !== '' && $this->is_valid_website( $website ) ) {
			$links['website'] = [
				'label' => $this->website_label( $website ),
				'url' => $website,
				'kind' => 'website',
			];
		}

		$twitter = $this->normalize_handle( $fields[self::KEY_TWITTER] ?? '' );
		if ( $twitter !== '' ) {
			$links['twitter'] = [
				'label' => '@' . $twitter,
				'url' => 'https://x.com/' . rawurlencode( $twitter ),
				'kind' => 'twitter',
			];
		}

		$github = $this->normalize_handle( $fields[self::KEY_GITHUB] ?? '' );
		if ( $github !== '' ) {
			$links['github'] = [
				'label' => $github,
				'url' => 'https://github.com/' . rawurlencode( $github ),
				'kind' => 'github',
			];
		}

		return $links;
	}

	/**
	 * Builds wiki-platform profile descriptors for the about/tagline icon chips.
	 *
	 * @param array<string, string> $fields
	 * @return list<array{kind: string, username: string, url: string}>
	 */
	public function build_wiki_profiles( array $fields ): array {
		$profiles = [];

		foreach ( self::WIKI_PROFILE_BASES as $key => $base ) {
			$username = $this->normalize_wiki_username( $fields[$key] ?? '' );
			if ( $username === '' ) {
				continue;
			}

			$kind = match ( $key ) {
				self::KEY_MEDIAWIKI => 'mediawiki',
				self::KEY_MIRAHEZE => 'miraheze',
				self::KEY_FANDOM => 'fandom',
				default => 'wiki',
			};
			$profiles[] = [ 'kind' => $kind, 'username' => $username, 'url' => $this->wiki_user_url( $base, $username ) ];
		}

		return $profiles;
	}

	/**
	 * @return string|null Sanitized value, or null when invalid
	 */
	private function sanitize_one( string $key, string $value ): ?string {
		if ( isset( self::FLAG_KEYS[$key] ) ) {
			return self::is_flag_on( $value ) ? '1' : '0';
		}

		if ( $key === self::KEY_VISIBILITY ) {
			$normalized = self::normalize_visibility( $value );
			if ( $value !== '' && strtolower( trim( $value ) ) !== $normalized ) {
				return null;
			}

			return $normalized;
		}

		if ( $key === self::KEY_BANNER ) {
			$normalized = self::normalize_banner( $value );
			// reject unknowns
			if ( $value !== '' && strtolower( trim( $value ) ) !== $normalized ) {
				return null;
			}

			return $normalized;
		}

		if ( $key === self::KEY_ABOUT ) {
			if ( mb_strlen( $value ) > $this->about_max_length ) {
				return null;
			}
			return $value;
		}

		if ( $key === self::KEY_FEATURED_ARTICLE ) {
			if ( $value === '' ) {
				return '';
			}
			if ( mb_strlen( $value ) > $this->link_max_length ) {
				return null;
			}
			if ( preg_match( '/[\x00-\x1f\x7f#|]/', $value ) ) {
				// reject control chars + fragments
				return null;
			}

			return $value;
		}

		if ( $key === self::KEY_WEBSITE ) {
			if ( $value === '' ) {
				return '';
			}
			if ( mb_strlen( $value ) > $this->link_max_length ) {
				return null;
			}
			return $this->is_valid_website( $value ) ? $value : null;
		}

		if ( isset( self::WIKI_PROFILE_BASES[$key] ) ) {
			if ( $value === '' ) {
				return '';
			}

			$username = $this->normalize_wiki_username( $value );
			if ( $username === '' || !preg_match( self::WIKI_USERNAME_PATTERN, $username ) ) {
				return null;
			}
			if ( mb_strlen( $username ) > $this->link_max_length ) {
				return null;
			}

			return $username;
		}

		// socials
		if ( !isset( self::HANDLE_KEYS[$key] ) ) {
			return null;
		}
		if ( $value === '' ) {
			return '';
		}
		$handle = $this->normalize_handle( $value );
		if ( $handle === '' || !preg_match( self::HANDLE_PATTERN, $handle ) ) {
			return null;
		}
		if ( mb_strlen( $handle ) > $this->link_max_length ) {
			return null;
		}

		return $handle;
	}

	public function normalize_handle( string $value ): string {
		$value = trim( $value );
		if ( str_starts_with( $value, '@' ) ) {
			$value = substr( $value, 1 );
		}

		return trim( $value );
	}

	public function normalize_wiki_username( string $value ): string {
		$value = trim( $value );
		if ( str_starts_with( $value, '@' ) ) {
			$value = substr( $value, 1 );
		}

		$value = str_replace( '_', ' ', $value );
		$value = preg_replace( '/\s+/u', ' ', $value ) ?? $value;
		return trim( $value );
	}

	public function wiki_user_url( string $wiki_base, string $username ): string {
		$title = str_replace( ' ', '_', $username );
		return rtrim( $wiki_base, '/' ) . '/User:' . rawurlencode( $title );
	}

	public function is_valid_website( string $url ): bool {
		if ( $url === '' ) {
			return true;
		}

		$parts = parse_url( $url );
		if ( $parts === false ) {
			return false;
		}

		$scheme = strtolower( (string)( $parts['scheme'] ?? '' ) );
		if ( $scheme !== 'http' && $scheme !== 'https' ) {
			return false;
		}

		$host = strtolower( (string)( $parts['host'] ?? '' ) );
		if ( $host === '' || $host === 'localhost' ) {
			return false;
		}

		// reject bare ipv4/ipv6 loopback + private-looking literals
		if ( $host === '::1' || $host === '[::1]' ) {
			return false;
		}
		if ( filter_var( $host, FILTER_VALIDATE_IP ) ) {
			if ( !filter_var( $host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
				return false;
			}
		}

		return true;
	}

	private function website_label( string $url ): string {
		$host = parse_url( $url, PHP_URL_HOST );
		if ( is_string( $host ) && $host !== '' ) {
			return $host;
		}

		return $url;
	}

}
