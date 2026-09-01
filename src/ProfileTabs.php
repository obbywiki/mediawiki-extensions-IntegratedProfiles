<?php

namespace MediaWiki\Extension\IntegratedProfiles;

/**
 * This script is responsible for handling tabs themselves, not injecting new ones.
 * If you are looking to register your own tab from an extension, see HookRunner.php.
 */
class ProfileTabs {

	public const SERVICE_NAME = 'IntegratedProfiles.ProfileTabs';
	public const QUERY_PARAM = 'iptab';
	public const ID_ABOUT = 'about';
	public const ID_CONTRIBUTIONS = 'contributions';

	public function __construct(
		private readonly HookRunner $hook_runner,
	) {
	}

	/**
	 * Build the ordered tab list and resolve the active tab.
	 *
	 * @see onIntegratedProfilesGetTabs in HookRunner.php for documentation.
	 */
	public function build( string $iptab, string $page_local_url, array $labels, array $profile, string $contributions_url = '', ?string $forced_active = null ): array {
		$tabs = [
			[
				'id' => self::ID_ABOUT,
				'label' => (string)( $labels['about'] ?? 'About' ),
				'weight' => 10
			],
			[
				'id' => self::ID_CONTRIBUTIONS,
				'label' => (string)( $labels['contributions'] ?? 'Contributions' ),
				'weight' => 20
			],
		];

		$this->hook_runner->onIntegratedProfilesGetTabs( $tabs, $profile );

		return self::resolve(
			$tabs,
			$iptab,
			$page_local_url,
			$contributions_url,
			$forced_active
		);
	}

	/**
	 * Normalizes registered tabs, picks the active ID, and attaches the URLs.
	 *
	 * @param list<mixed> $tabs Raw tab entries (core + companions)
	 * @return array{ active: string, tabs: list<array{id: string, label: string, url: string, weight: int, active: bool}> }
	 *
	 * @see onIntegratedProfilesGetTabs in HookRunner.php for documentation.
	 */
	public static function resolve( array $tabs, string $iptab, string $page_local_url, string $contributions_url = '', ?string $forced_active = null ): array {
		$tabs = self::normalize_and_sort( $tabs );

		$requested = self::sanitize_tab_id( $iptab );
		$active = self::ID_ABOUT;

		if ( $forced_active !== null ) {
			$forced = self::sanitize_tab_id( $forced_active );

			if ( $forced !== '' && self::has_tab_id( $tabs, $forced ) ) {
				$active = $forced;
			}
		} elseif ( $requested !== '' && self::has_tab_id( $tabs, $requested ) ) {
			if ( !( $requested === self::ID_CONTRIBUTIONS && $contributions_url !== '' ) ) {
				$active = $requested;
			}
		}

		$items = [];
		foreach ( $tabs as $tab ) {
			$id = $tab['id'];
			if ( $id === self::ID_CONTRIBUTIONS && $contributions_url !== '' ) {
				$url = $contributions_url;
			} else {
				$url = self::tab_url( $page_local_url, $id );
			}

			$items[] = [
				'id' => $id,
				'label' => $tab['label'],
				'url' => $url,
				'weight' => $tab['weight'],
				'active' => $id === $active
			];
		}

		return [ 'active' => $active, 'tabs' => $items ];
	}

	/**
	 * Returns the local URL for a tab. The About tab is special, and doesn't use the `iptab` query parameter (for crawlability).
	 */
	public static function tab_url( string $page_local_url, string $tab_id ): string {
		$tab_id = self::sanitize_tab_id( $tab_id );
		if ( $tab_id === '' || $tab_id === self::ID_ABOUT ) {
			return self::strip_iptab( $page_local_url );
		}

		$base = self::strip_iptab( $page_local_url );
		$sep = str_contains( $base, '?' ) ? '&' : '?';

		return $base . $sep . self::QUERY_PARAM . '=' . rawurlencode( $tab_id );
	}

	/**
	 * @param list<mixed> $tabs
	 * @return list<array{id: string, label: string, weight: int}>
	 */
	public static function normalize_and_sort( array $tabs ): array {
		$by_id = [];
		foreach ( $tabs as $tab ) {
			if ( !is_array( $tab ) ) {
				continue;
			}

			$id = self::sanitize_tab_id( (string)( $tab['id'] ?? '' ) );
			if ( $id === '' ) {
				continue;
			}

			$label = trim( (string)( $tab['label'] ?? '' ) );
			if ( $label === '' ) {
				$label = $id;
			}

			$weight = (int)( $tab['weight'] ?? 100 );
			$by_id[$id] = [ 'id' => $id, 'label' => $label, 'weight' => $weight ];
		}

		$normalized = array_values( $by_id );
		usort( $normalized,
			static function ( array $a, array $b ): int {
				if ( $a['weight'] === $b['weight'] ) {
					return strcmp( $a['id'], $b['id'] );
				}

				return $a['weight'] <=> $b['weight'];
			}
		);

		return $normalized;
	}

	public static function sanitize_tab_id( string $tab_id ): string {
		$tab_id = strtolower( trim( $tab_id ) );
		if ( $tab_id === '' ) {
			return '';
		}

		if ( !preg_match( '/^[a-z0-9][a-z0-9_-]{0,63}$/', $tab_id ) ) {
			return '';
		}

		return $tab_id;
	}

	/**
	 * @param list<array{id: string}> $tabs
	 */
	private static function has_tab_id( array $tabs, string $id ): bool {
		foreach ( $tabs as $tab ) {
			if ( ( $tab['id'] ?? '' ) === $id ) {
				return true;
			}
		}

		return false;
	}

	private static function strip_iptab( string $url ): string {
		$parts = parse_url( $url );
		if ( $parts === false ) {
			return $url;
		}

		$query = [];
		if ( isset( $parts['query'] ) && $parts['query'] !== '' ) {
			parse_str( $parts['query'], $query );
			unset( $query[self::QUERY_PARAM] );
		}

		$rebuilt = '';
		if ( isset( $parts['path'] ) ) {
			$rebuilt .= $parts['path'];
		} elseif ( $url !== '' && !str_starts_with( $url, '?' ) ) {
			$qpos = strpos( $url, '?' );
			$rebuilt = $qpos === false ? $url : substr( $url, 0, $qpos );
		}

		if ( $query !== [] ) {
			$rebuilt .= '?' . http_build_query( $query );
		}

		if ( isset( $parts['fragment'] ) ) {
			$rebuilt .= '#' . $parts['fragment'];
		}

		return $rebuilt !== '' ? $rebuilt : $url;
	}

}
