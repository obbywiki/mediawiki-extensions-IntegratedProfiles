<?php

namespace MediaWiki\Extension\IntegratedProfiles;

/**
 * Handles profile fields, validation, keys, sanitization, URL builders, etc.
 */
class ProfileFields {

	public const KEY_ABOUT = 'ip-about';
	public const KEY_FEATURED_ARTICLE = 'ip-featured-article';
	public const KEY_WEBSITE = 'ip-website';
	public const KEY_TWITTER = 'ip-twitter';
	public const KEY_GITHUB = 'ip-github';
	public const KEY_DISCORD = 'ip-discord';
	public const KEY_ROBLOX = 'ip-roblox';
	public const KEY_YOUTUBE = 'ip-youtube';
	public const KEY_MEDIAWIKI = 'ip-mediawiki';
	public const KEY_MIRAHEZE = 'ip-miraheze';
	public const KEY_FANDOM = 'ip-fandom';
	public const KEY_BANNER = 'ip-banner';
	public const KEY_HIDE_CONNECTIONS = 'ip-hide-connections';
	public const KEY_VISIBILITY = 'ip-visibility';

	// if you want to add/request a new social link, you MUST include a string const AS WELL AS an item in SOCIAL_CATALOG even if it won't be used (yet)
	// just search the other areas in the codebase where these are used to see how to add it

	public const SOCIAL_WEBSITE = 'website';
	public const SOCIAL_TWITTER = 'twitter';
	public const SOCIAL_GITHUB = 'github';
	public const SOCIAL_DISCORD = 'discord';
	public const SOCIAL_ROBLOX = 'roblox';
	public const SOCIAL_YOUTUBE = 'youtube';

	public const SOCIAL_TYPE_URL = 'url';
	public const SOCIAL_TYPE_HANDLE = 'handle';
	public const SOCIAL_TYPE_DISCORD_USERNAME = 'discord_username';
	public const SOCIAL_TYPE_ROBLOX_USERNAME = 'roblox_username';
	public const SOCIAL_TYPE_YOUTUBE_URL = 'youtube_url';

	/**
	 * Read above if you want to add a social link.
	 *
	 * @var array<string, array{key: string, type: string}>
	 */
	public const SOCIAL_CATALOG = [
		self::SOCIAL_WEBSITE => [
			'key' => self::KEY_WEBSITE,
			'type' => self::SOCIAL_TYPE_URL,
		],
		self::SOCIAL_TWITTER => [
			'key' => self::KEY_TWITTER,
			'type' => self::SOCIAL_TYPE_HANDLE,
		],
		self::SOCIAL_GITHUB => [
			'key' => self::KEY_GITHUB,
			'type' => self::SOCIAL_TYPE_HANDLE,
		],
		self::SOCIAL_DISCORD => [
			'key' => self::KEY_DISCORD,
			'type' => self::SOCIAL_TYPE_DISCORD_USERNAME,
		],
		self::SOCIAL_ROBLOX => [
			'key' => self::KEY_ROBLOX,
			'type' => self::SOCIAL_TYPE_ROBLOX_USERNAME,
		],
		self::SOCIAL_YOUTUBE => [
			'key' => self::KEY_YOUTUBE,
			'type' => self::SOCIAL_TYPE_YOUTUBE_URL,
		],
	];

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
		self::KEY_DISCORD,
		self::KEY_ROBLOX,
		self::KEY_YOUTUBE,
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

	// temporarily disabled
	public const SHOW_WIKI_PROFILES = false;

	private const WIKI_PROFILE_BASES = [
		self::KEY_MEDIAWIKI => 'https://www.mediawiki.org/wiki/',
		self::KEY_MIRAHEZE => 'https://meta.miraheze.org/wiki/',
		self::KEY_FANDOM => 'https://community.fandom.com/wiki/',
	];

	private const HANDLE_PATTERN = '/^[A-Za-z0-9._-]{1,64}$/';

	private const DISCORD_USERNAME_PATTERN = '/^[a-z0-9_]{2,32}$/';

	private const ROBLOX_USERNAME_PATTERN = '/^[A-Za-z0-9_]{3,20}$/';

	private const WIKI_USERNAME_PATTERN = '/^[^\x00-\x1f\x7f#<>\[\]|{}\/]{1,255}$/u';

	/** @var list<string> */
	private readonly array $enabled_social_ids;

	/**
	 * @param list<mixed>|null $enabled_social_links Catalog IDs to show/accept. Null enables the full catalog.
	 */
	public function __construct(
		private readonly int $about_max_length = 500,
		private readonly int $link_max_length = 255,
		?array $enabled_social_links = null,
	) {
		$this->enabled_social_ids = self::normalize_enabled_social_links( $enabled_social_links );
	}

	/**
	 * Filters a LocalSettings list to known IDs in the catalog while preserving order.
	 *
	 * Null = every social link in the catalog.
	 *
	 * @param list<mixed>|null $ids
	 * @return list<string>
	 */
	public static function normalize_enabled_social_links( ?array $ids ): array {
		if ( $ids === null ) {
			return array_keys( self::SOCIAL_CATALOG );
		}

		$seen = [];
		$enabled = [];
		foreach ( $ids as $id ) {
			if ( !is_string( $id ) ) {
				continue;
			}
			$id = strtolower( trim( $id ) );
			if ( $id === '' || !isset( self::SOCIAL_CATALOG[$id] ) || isset( $seen[$id] ) ) {
				continue;
			}
			$seen[$id] = true;
			$enabled[] = $id;
		}

		return $enabled;
	}

	/**
	 * @return list<array{id: string, key: string, type: string}>
	 */
	public function enabled_social_entries(): array {
		$entries = [];
		foreach ( $this->enabled_social_ids as $id ) {
			$entry = self::SOCIAL_CATALOG[$id];
			$entries[] = [ 'id' => $id, 'key' => $entry['key'], 'type' => $entry['type'] ];
		}

		return $entries;
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
	 * Disabled social keys are ignored (not written, not invalid) so a wiki with a smaller enable list cannot blank global values for other services. This is very important.
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
			if ( $this->is_disabled_social_key( $key ) ) {
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
	 * Builds public link descriptors from sanitized fields for enabled services.
	 *
	 * @param array<string, string> $fields
	 * @return array<string, array{label: string, url: string, kind: string}>
	 */
	public function build_public_links( array $fields ): array {
		$links = [];

		foreach ( $this->enabled_social_ids as $id ) {
			$descriptor = $this->build_one_public_link( $id, $fields );
			if ( $descriptor === null ) {
				continue;
			}
			$links[$id] = $descriptor;
		}

		return $links;
	}

	/**
	 * Drops self-reported Discord/Roblox chips when a verified connection exists (NewAuth).
	 *
	 * @param array<string, array{label: string, url: string, kind: string}> $links
	 * @param list<mixed> $connections
	 * @return array<string, array{label: string, url: string, kind: string}>
	 */
	public function omit_verified_socials( array $links, array $connections ): array {
		$providers = [];
		foreach ( $connections as $row ) {
			if ( !is_array( $row ) ) {
				continue;
			}

			$provider = strtolower( trim( (string)( $row['provider'] ?? '' ) ) );
			if ( $provider !== '' ) {
				$providers[$provider] = true;
			}
		}

		foreach ( [ self::SOCIAL_DISCORD, self::SOCIAL_ROBLOX ] as $kind ) {
			if ( isset( $providers[$kind] ) ) {
				unset( $links[$kind] );
			}
		}

		return $links;
	}

	/**
	 * Builds wiki-platform profile descriptors for the about/tagline icon chips.
	 *
	 * Still produced for stored values / future UI. Masthead and editor stay
	 * hidden while self::SHOW_WIKI_PROFILES is false.
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

		$social_id = self::social_id_for_key( $key );
		if ( $social_id === null ) {
			return null;
		}

		return $this->sanitize_social( $social_id, $value );
	}

	/**
	 * @return string|null Sanitized value, or null when invalid
	 */
	private function sanitize_social( string $id, string $value ): ?string {
		if ( $value === '' ) {
			return '';
		}

		$type = self::SOCIAL_CATALOG[$id]['type'];

		return match ( $type ) {
			self::SOCIAL_TYPE_URL => $this->sanitize_url_value( $value ),
			self::SOCIAL_TYPE_HANDLE => $this->sanitize_handle_value( $value ),
			self::SOCIAL_TYPE_DISCORD_USERNAME => $this->sanitize_discord_username( $value ),
			self::SOCIAL_TYPE_ROBLOX_USERNAME => $this->sanitize_roblox_username( $value ),
			self::SOCIAL_TYPE_YOUTUBE_URL => $this->sanitize_youtube_url( $value ),
			default => null,
		};
	}

	private function sanitize_url_value( string $value ): ?string {
		if ( mb_strlen( $value ) > $this->link_max_length ) {
			return null;
		}

		return $this->is_valid_website( $value ) ? $value : null;
	}

	private function sanitize_handle_value( string $value ): ?string {
		$handle = $this->normalize_handle( $value );
		if ( $handle === '' || !preg_match( self::HANDLE_PATTERN, $handle ) ) {
			return null;
		}
		if ( mb_strlen( $handle ) > $this->link_max_length ) {
			return null;
		}

		return $handle;
	}

	private function sanitize_discord_username( string $value ): ?string {
		$username = strtolower( $this->normalize_handle( $value ) );
		if ( $username === '' || !preg_match( self::DISCORD_USERNAME_PATTERN, $username ) ) {
			return null;
		}

		return $username;
	}

	private function sanitize_roblox_username( string $value ): ?string {
		$username = $this->normalize_handle( $value );
		if ( $username === '' || !preg_match( self::ROBLOX_USERNAME_PATTERN, $username ) ) {
			return null;
		}

		return $username;
	}

	private function sanitize_youtube_url( string $value ): ?string {
		if ( mb_strlen( $value ) > $this->link_max_length ) {
			return null;
		}
		if ( !$this->is_valid_website( $value ) ) {
			return null;
		}

		$host = parse_url( $value, PHP_URL_HOST );
		if ( !is_string( $host ) || !$this->is_youtube_host( $host ) ) {
			return null;
		}

		return $value;
	}

	/**
	 * @param array<string, string> $fields
	 * @return array{label: string, url: string, kind: string}|null
	 */
	private function build_one_public_link( string $id, array $fields ): ?array {
		$key = self::SOCIAL_CATALOG[$id]['key'];
		$type = self::SOCIAL_CATALOG[$id]['type'];
		$raw = $fields[$key] ?? '';

		return match ( $type ) {
			self::SOCIAL_TYPE_URL => $this->website_link( $id, $raw ),
			self::SOCIAL_TYPE_HANDLE => $this->handle_link( $id, $raw ),
			self::SOCIAL_TYPE_DISCORD_USERNAME => $this->discord_link( $raw ),
			self::SOCIAL_TYPE_ROBLOX_USERNAME => $this->roblox_link( $raw ),
			self::SOCIAL_TYPE_YOUTUBE_URL => $this->youtube_link( $raw ),
			default => null
		};
	}

	/**
	 * @return array{label: string, url: string, kind: string}|null
	 */
	private function website_link( string $id, string $raw ): ?array {
		$website = trim( $raw );
		if ( $website === '' || !$this->is_valid_website( $website ) ) {
			return null;
		}

		return [ 'label' => $this->website_label( $website ), 'url' => $website, 'kind' => $id ];
	}

	/**
	 * @return array{label: string, url: string, kind: string}|null
	 */
	private function handle_link( string $id, string $raw ): ?array {
		$handle = $this->normalize_handle( $raw );
		if ( $handle === '' ) {
			return null;
		}

		$url = match ( $id ) {
			self::SOCIAL_TWITTER => 'https://x.com/' . rawurlencode( $handle ),
			self::SOCIAL_GITHUB => 'https://github.com/' . rawurlencode( $handle ),
			default => '',
		};
		if ( $url === '' ) {
			return null;
		}

		$label = $id === self::SOCIAL_TWITTER ? '@' . $handle : $handle;

		return [
			'label' => $label,
			'url' => $url,
			'kind' => $id,
		];
	}

	/**
	 * @return array{label: string, url: string, kind: string}|null
	 */
	private function discord_link( string $raw ): ?array {
		$username = strtolower( $this->normalize_handle( $raw ) );
		if ( $username === '' || !preg_match( self::DISCORD_USERNAME_PATTERN, $username ) ) {
			return null;
		}

		return [
			'label' => $username,
			'url' => '',
			'kind' => self::SOCIAL_DISCORD,
		];
	}

	/**
	 * @return array{label: string, url: string, kind: string}|null
	 */
	private function roblox_link( string $raw ): ?array {
		$username = $this->normalize_handle( $raw );
		if ( $username === '' || !preg_match( self::ROBLOX_USERNAME_PATTERN, $username ) ) {
			return null;
		}

		return [
			'label' => $username,
			'url' => 'https://www.roblox.com/users/profile?username=' . rawurlencode( $username ),
			'kind' => self::SOCIAL_ROBLOX,
		];
	}

	/**
	 * @return array{label: string, url: string, kind: string}|null
	 */
	private function youtube_link( string $raw ): ?array {
		$url = trim( $raw );
		if ( $url === '' || $this->sanitize_youtube_url( $url ) === null ) {
			return null;
		}

		return [
			'label' => $this->website_label( $url ),
			'url' => $url,
			'kind' => self::SOCIAL_YOUTUBE,
		];
	}

	private function is_disabled_social_key( string $key ): bool {
		$id = self::social_id_for_key( $key );
		if ( $id === null ) {
			return false;
		}

		return !in_array( $id, $this->enabled_social_ids, true );
	}

	public static function social_id_for_key( string $key ): ?string {
		foreach ( self::SOCIAL_CATALOG as $id => $entry ) {
			if ( $entry['key'] === $key ) {
				return $id;
			}
		}

		return null;
	}

	private function is_youtube_host( string $host ): bool {
		$host = strtolower( $host );
		if ( str_starts_with( $host, 'www.' ) ) {
			$host = substr( $host, 4 );
		}

		if ( $host === 'youtu.be' ) {
			return true;
		}

		return $host === 'youtube.com' || str_ends_with( $host, '.youtube.com' );
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
