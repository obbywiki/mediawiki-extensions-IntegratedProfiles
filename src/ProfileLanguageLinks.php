<?php

namespace MediaWiki\Extension\IntegratedProfiles;

/**
 * Builds and injects synthetic language interwiki links automatically if configured, see $wgIntegratedProfilesLanguageInterwikis.
 */
class ProfileLanguageLinks {

	public const SERVICE_NAME = 'IntegratedProfiles.ProfileLanguageLinks';

	private readonly array $language_interwikis;
	private readonly array $local_interwikis;

	private readonly string $content_language;

	/**
	 * @param list<string>|array<int|string,string> $language_interwikis
	 * @param string $content_language Wiki content language code
	 * @param list<string>|array<int|string,string> $local_interwikis
	 */
	public function __construct(
		array $language_interwikis,
		string $content_language,
		array $local_interwikis = [],
	) {
		$this->language_interwikis = $this->normalize_prefixes( $language_interwikis );
		$this->local_interwikis = $this->normalize_prefixes( $local_interwikis );
		$this->content_language = strtolower( trim( $content_language ) );
	}

	/** 
	 * Returns the canonical English namespace title for a root User: / User_talk: title.
	 */
	public static function canonical_user_title( string $user_name, bool $is_talk ): string {
		return ( $is_talk ? 'User talk:' : 'User:' ) . $user_name;
	}

	/**
	 * Append configured language links for a profile title into &$links.
	 *
	 * $prefixed_text MUST be the canonical English form (User:Name / User talk:Name), NOT from Title::getPrefixedText() (or elsewhere) on a non-English wiki as it will NOT work.
	 *
	 * @param string $prefixed_text Canonical English User: / User talk: title
	 * @param list<string> &$links Existing language links (e.g. "ko:User:Name")
	 */
	public function append_for_profile_title( string $prefixed_text, array &$links ): void {
		if ( $prefixed_text === '' || $this->language_interwikis === [] ) {
			return;
		}

		$existing = [];
		foreach ( $links as $link ) {
			$prefix = $this->prefix_from_link( (string)$link );

			if ( $prefix !== null ) {
				$existing[$prefix] = true;
			}
		}

		foreach ( $this->language_interwikis as $prefix ) {
			if ( isset( $existing[$prefix] ) ) {
				continue;
			}

			if ( $prefix === $this->content_language ) {
				continue;
			}

			if ( in_array( $prefix, $this->local_interwikis, true ) ) {
				continue;
			}

			$links[] = $prefix . ':' . $prefixed_text;
			$existing[$prefix] = true;
		}
	}

	/**
	 * @param list<string>|array<int|string,string> $prefixes
	 * @return list<string>
	 */
	private function normalize_prefixes( array $prefixes ): array {
		$normalized = [];
		foreach ( $prefixes as $prefix ) {
			$prefix = strtolower( trim( (string)$prefix ) );
			$prefix = rtrim( $prefix, ':' );

			if ( $prefix === '' || !preg_match( '/^[a-z0-9_-]+$/', $prefix ) ) {
				continue;
			}

			$normalized[$prefix] = true;
		}
		return array_keys( $normalized );
	}

	private function prefix_from_link( string $link ): ?string {
		$pos = strpos( $link, ':' );

		if ( $pos === false || $pos === 0 ) {
			return null;
		}

		$prefix = strtolower( substr( $link, 0, $pos ) );
		
		return $prefix !== '' ? $prefix : null;
	}

}
