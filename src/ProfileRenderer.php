<?php

namespace MediaWiki\Extension\IntegratedProfiles;

use MediaWiki\Html\TemplateParser;

/**
 * Server-rendered profile masthead + cutout HTML.
 *
 * TODO: switch from built-in escaping to mw sanitizer
 */
class ProfileRenderer {

	/**
	 * @param TemplateParser $template_parser Mustache renderer rooted at templates/
	 */
	public function __construct(
		private readonly TemplateParser $template_parser
	) {
	}

	/**
	 * @param array $payload ProfileService payload
	 * @param bool $can_edit Whether to show edit controls
	 * @param array $messages Already-localized message strings
	 *   (aka, edit_count, joined, avatar_alt, avatar_edit, edit, you, featured_label,
	 *   wiki_profiles_label, wiki_profile_labels, connection_labels, connection_verified,
	 *   private_notice)
	 * @param string $contributions_url Local URL to Special:Contributions
	 * @param bool $use_floating_ui When Extension:FloatingUI is loaded, emit
	 *   reference/content pairs instead of native title attributes
	 * @param bool $is_owner Whether the viewer is the profile subject
	 * @param string $avatar_decoration Unescaped companion HTML from
	 *   IntegratedProfilesAfterAvatar (IP wraps it in `.ip-avatar__decoration`)
	 */
	public function render_masthead( array $payload, bool $can_edit, array $messages, string $contributions_url, bool $use_floating_ui = false, bool $is_owner = false, string $avatar_decoration = '' ): string {
		return $this->process_template( 'masthead', $this->build_masthead_view( $payload, $can_edit, $messages, $contributions_url, $use_floating_ui, $is_owner, $avatar_decoration ) );
	}

	/**
	 * Tagline + wiki-platform icon chips under the masthead body.
	 *
	 * @param string $about About tagline
	 * @param list<array{kind?: string, username?: string, url?: string}> $wiki_profiles
	 * @param array $messages Localized strings
	 * @param bool $use_floating_ui Soft-dep FloatingUI tips for wiki chips
	 */
	public function render_about_block( string $about, array $wiki_profiles, array $messages, bool $use_floating_ui = false ): string {
		$view = $this->build_about_block_view( $about, $wiki_profiles, $messages, $use_floating_ui );
		if ( $view['about_html'] === '' && !$view['has_wiki_profiles'] ) {
			return '';
		}

		return $this->process_template( 'about_block', $view );
	}

	/**
	 * Compact featured-article row above the masthead tagline.
	 *
	 * @param array{title?: string, display_title?: string, url?: string}|null $featured
	 * @param array $messages Localized strings (featured_label)
	 */
	public function render_featured_article( ?array $featured, array $messages ): string {
		$view = $this->build_featured_article_view( $featured, $messages );
		if ( $view === null ) {
			return '';
		}

		return $this->process_template( 'featured_article', $view );
	}

	/**
	 * Server-rendered profile body tab chrome (no JS).
	 *
	 * @param list<array{id: string, label: string, url: string, active?: bool}> $tabs
	 * @param string $nav_label Accessible name for the tab list
	 */
	public function render_tabs( array $tabs, string $nav_label = 'Profile' ): string {
		if ( $tabs === [] ) {
			return '';
		}

		$tab_items = [];
		foreach ( $tabs as $tab ) {
			if ( !is_array( $tab ) ) {
				continue;
			}

			$id = trim( (string)( $tab['id'] ?? '' ) );
			$label = trim( (string)( $tab['label'] ?? '' ) );
			$url = trim( (string)( $tab['url'] ?? '' ) );

			if ( $id === '' || $label === '' || $url === '' ) {
				continue;
			}

			$tab_items[] = [ 'id' => $id, 'label' => $label, 'url' => $url, 'active' => !empty( $tab['active'] ) ];
		}

		return $this->process_template( 'tabs', [ 'nav_label' => $nav_label, 'tab_items' => $tab_items ] );
	}

	/**
	 * @param array $payload ProfileService payload
	 * @param bool $can_edit Whether to show edit controls
	 * @param array $messages Already-localized message strings
	 * @param string $contributions_url Local URL to Special:Contributions
	 * @param bool $use_floating_ui Soft-dep FloatingUI tips
	 * @param bool $is_owner Whether the viewer is the profile subject
	 * @param string $avatar_decoration Unescaped companion HTML from AfterAvatar
	 * @return array<string, mixed>
	 */
	private function build_masthead_view( array $payload, bool $can_edit, array $messages, string $contributions_url, bool $use_floating_ui, bool $is_owner, string $avatar_decoration ): array {
		$is_private = !empty( $payload['is_private'] );
		$show_edit_controls = $can_edit && !$is_private;

		$color = (string)( $payload['ui']['color'] ?? '#5288F1' );
		$radius = (string)( $payload['ui']['avatar_border_radius'] ?? '50%' );

		$banner_mode = $is_private ? ProfileFields::BANNER_ACCENT : ProfileFields::normalize_banner( (string)( $payload['fields'][ ProfileFields::KEY_BANNER ] ?? ProfileFields::BANNER_ACCENT ) );
		$banner_url = $is_private ? '' : (string)( $payload['banner_url'] ?? '' );

		if ( $banner_mode === ProfileFields::BANNER_CUSTOM && $banner_url === '' ) {
			$banner_mode = ProfileFields::BANNER_ACCENT;
		}

		$masthead_style = '--ip-accent:' . $color . ';--ip-avatar-radius:' . $radius;
		if ( $banner_mode === ProfileFields::BANNER_CUSTOM ) {
			$masthead_style .= ';--ip-banner-image:url(' . $banner_url . ')';
		}

		$aka = '';
		if ( !$is_private ) {
			$real_name = trim( (string)( $payload['real_name'] ?? '' ) );

			if ( $real_name !== '' && ( $messages['aka'] ?? '' ) !== '' ) {
				$aka = (string)$messages['aka'];
			}
		}

		$you_label = '';
		if ( $is_owner ) {
			$you_label = trim( (string)( $messages['you'] ?? '' ) );
			if ( $you_label === '' ) {
				$you_label = 'You';
			}
		}

		$groups = [];
		if ( !$is_private ) {
			$groups = $this->build_group_items( is_array( $payload['groups'] ?? null ) ? $payload['groups'] : [] );
		}

		$edit_count_label = '';
		$joined_label = '';
		$private_notice = '';

		if ( $is_private ) {
			$private_notice = trim( (string)( $messages['private_notice'] ?? '' ) );
		} else {
			$edit_count_label = trim( (string)( $messages['edit_count'] ?? '' ) );
			$joined_label = trim( (string)( $messages['joined'] ?? '' ) );
		}

		$link_items = [];
		if ( !$is_private ) {
			$link_items = $this->build_link_items(
				is_array( $payload['links'] ?? null ) ? $payload['links'] : [],
				is_array( $payload['connections'] ?? null ) ? $payload['connections'] : [],
				$messages,
				$use_floating_ui
			);
		}

		$featured = null;
		$about_view = [ 'about_html' => '', 'has_wiki_profiles' => false, 'wiki_profiles_label' => '', 'wiki_profile_items' => [] ];

		if ( !$is_private ) {
			$featured = $this->build_featured_article_view( is_array( $payload['featured_article'] ?? null ) ? $payload['featured_article'] : null, $messages );

			$about_view = $this->build_about_block_view(
				trim( (string)( $payload['fields']['ip-about'] ?? '' ) ),
				is_array( $payload['wiki_profiles'] ?? null ) ? $payload['wiki_profiles'] : [],
				$messages,
				$use_floating_ui
			);
		}

		$avatar_edit = false;
		if ( $show_edit_controls ) {
			$avatar_edit_label = (string)( $messages['avatar_edit'] ?? 'Change avatar' );
			$avatar_edit = [
				'label' => $avatar_edit_label,
				'show_title' => !$use_floating_ui,
				'use_floating_ui' => $use_floating_ui,

				'tip_text' => $avatar_edit_label,
				'tip_url' => '',
				'has_tip_url' => false,
				'tip_verified' => false,
			];
		}

		return [
			'is_owner' => $is_owner,
			'is_private' => $is_private,

			'masthead_style' => $masthead_style,

			'banner_mode' => $banner_mode,
			'avatar_url' => (string)( $payload['avatar_url'] ?? '' ),
			'avatar_alt' => (string)( $messages['avatar_alt'] ?? '' ),
			'avatar_decoration' => $avatar_decoration,
			'avatar_edit' => $avatar_edit,

			'use_floating_ui' => $use_floating_ui,
			'user_name' => (string)( $payload['user_name'] ?? '' ),

			'aka' => $aka,
			'you_label' => $you_label,
			'has_groups' => $groups !== [],
			'groups' => $groups,

			'private_notice' => $private_notice,
			'has_meta' => $edit_count_label !== '' || $joined_label !== '',
			'has_edit_count' => $edit_count_label !== '',

			'edit_count_label' => $edit_count_label,
			'has_contributions_url' => $contributions_url !== '',
			'contributions_url' => $contributions_url,

			'joined_label' => $joined_label,

			'has_links' => $link_items !== [],
			'link_items' => $link_items,

			'show_edit_controls' => $show_edit_controls,
			'edit_label' => (string)( $messages['edit'] ?? 'Edit profile' ),

			'featured' => $featured,

			'has_about_block' => $about_view['about_html'] !== '' || $about_view['has_wiki_profiles'],
			'about_html' => $about_view['about_html'],

			'has_wiki_profiles' => $about_view['has_wiki_profiles'],
			'wiki_profiles_label' => $about_view['wiki_profiles_label'],
			'wiki_profile_items' => $about_view['wiki_profile_items'],
		];
	}

	/**
	 * @param list<mixed> $groups
	 * @return list<array{label: string, url: string, has_url: bool}>
	 */
	private function build_group_items( array $groups ): array {
		$items = [];
		foreach ( $groups as $group ) {
			$label = '';
			$url = '';

			if ( is_array( $group ) ) {
				$label = trim( (string)( $group['label'] ?? '' ) );
				$url = trim( (string)( $group['url'] ?? '' ) );
			} elseif ( is_string( $group ) ) {
				$label = trim( $group );
			}

			if ( $label === '' ) {
				continue;
			}

			$items[] = [ 'label' => $label, 'url' => $url, 'has_url' => $url !== '' ];
		}

		return $items;
	}

	/**
	 * @param string $about
	 * @param list<array{kind?: string, username?: string, url?: string}> $wiki_profiles
	 * @param array $messages
	 * @param bool $use_floating_ui Soft-dep FloatingUI tips for wiki chips
	 * @return array{about_html: string, has_wiki_profiles: bool, wiki_profiles_label: string, wiki_profile_items: list<array<string, mixed>>}
	 */
	private function build_about_block_view( string $about, array $wiki_profiles, array $messages, bool $use_floating_ui ): array {
		$wiki_profile_items = $this->build_wiki_profile_items( $wiki_profiles, $messages, $use_floating_ui );
		$about_html = $about === '' ? '' : nl2br( htmlspecialchars( $about, ENT_QUOTES, 'UTF-8' ), false );

		return [
			'about_html' => $about_html,
			'has_wiki_profiles' => $wiki_profile_items !== [],
			'wiki_profiles_label' => (string)( $messages['wiki_profiles_label'] ?? 'Wiki profiles' ),
			'wiki_profile_items' => $wiki_profile_items
		];
	}

	/**
	 * @param list<array{kind?: string, username?: string, url?: string}> $wiki_profiles
	 * @param array $messages
	 * @param bool $use_floating_ui Soft-dep FloatingUI tips for wiki chips
	 * @return list<array<string, mixed>>
	 */
	private function build_wiki_profile_items(
		array $wiki_profiles,
		array $messages,
		bool $use_floating_ui
	): array {
		$labels = is_array( $messages['wiki_profile_labels'] ?? null ) ? $messages['wiki_profile_labels'] : [];
		$fallback = [
			'mediawiki' => 'MediaWiki.org',
			'miraheze' => 'Miraheze',
			'fandom' => 'Fandom'
		];

		$items = [];
		foreach ( $wiki_profiles as $row ) {
			if ( !is_array( $row ) ) {
				continue;
			}

			$url = trim( (string)( $row['url'] ?? '' ) );
			$kind = trim( (string)( $row['kind'] ?? '' ) );
			$username = trim( (string)( $row['username'] ?? '' ) );

			if ( $url === '' || $kind === '' ) {
				continue;
			}

			$platform = trim( (string)( $labels[$kind] ?? $fallback[$kind] ?? $kind ) );
			$title = $username !== ''
				? ( $platform !== '' ? $platform . ': ' . $username : $username )
				: $platform;

			if ( $title === '' ) {
				continue;
			}

			$items[] = [
				'kind' => $kind,
				'url' => $url,
				'title' => $title,
				'use_floating_ui' => $use_floating_ui,
				'show_title' => !$use_floating_ui,
				'tip_text' => $title,
				'tip_url' => $url,
				'has_tip_url' => true,
				'tip_verified' => false,
			];
		}

		return $items;
	}

	/**
	 * @param array{title?: string, display_title?: string, url?: string}|null $featured
	 * @param array $messages
	 * @return array{label: string, url: string, display: string}|null
	 */
	private function build_featured_article_view( ?array $featured, array $messages ): ?array {
		if ( $featured === null ) {
			return null;
		}

		$url = trim( (string)( $featured['url'] ?? '' ) );
		$display = trim( (string)( $featured['display_title'] ?? $featured['title'] ?? '' ) );
		if ( $url === '' || $display === '' ) {
			return null;
		}

		return [
			'label' => (string)( $messages['featured_label'] ?? 'Featured article' ),
			'url' => $url,
			'display' => $display
		];
	}

	/**
	 * @param array<string|int, mixed> $links
	 * @param list<mixed> $connections
	 * @param array $messages
	 * @param bool $use_floating_ui Soft-dep FloatingUI tips for icon chips
	 * @return list<array<string, mixed>>
	 */
	private function build_link_items( array $links, array $connections, array $messages, bool $use_floating_ui ): array {
		$items = [];

		foreach ( $links as $link ) {
			if ( !is_array( $link ) ) {
				continue;
			}
			$url = trim( (string)( $link['url'] ?? '' ) );
			$label = trim( (string)( $link['label'] ?? '' ) );
			$kind = trim( (string)( $link['kind'] ?? '' ) );

			if ( $url === '' || $label === '' || $kind === '' ) {
				continue;
			}

			$items[] = $this->build_link_item( $kind, $url, $label, false, '', $use_floating_ui );
		}

		$labels = is_array( $messages['connection_labels'] ?? null ) ? $messages['connection_labels'] : [];
		$verified_word = trim( (string)( $messages['connection_verified'] ?? 'Verified' ) );

		foreach ( $connections as $connection ) {
			if ( !is_array( $connection ) ) {
				continue;
			}

			$provider = strtolower( trim( (string)( $connection['provider'] ?? '' ) ) );
			if ( $provider === '' ) {
				continue;
			}

			$display = trim( (string)( $connection['remote_username'] ?? '' ) );
			if ( $display === '' ) {
				$display = trim( (string)( $connection['remote_user'] ?? '' ) );
			}

			$remote_user = trim( (string)( $connection['remote_user'] ?? '' ) );
			if ( $display === '' && $remote_user === '' ) {
				continue;
			}

			$provider_label = trim( (string)( $labels[$provider] ?? '' ) );
			if ( $provider_label === '' ) {
				$provider_label = $provider;
			}

			$url = self::connection_profile_url( $provider, $remote_user );
			$title = $provider_label;
			if ( $display !== '' ) {
				$title .= ': ' . $display;
			}

			$aria = $title;
			if ( $verified_word !== '' ) {
				$aria .= ' (' . $verified_word . ')';
			}

			$items[] = $this->build_link_item( $provider, $url, $title, true, $aria, $use_floating_ui );
		}

		return $items;
	}

	/**
	 * @param string $kind Provider / link kind class suffix
	 * @param string $url Absolute or local URL (empty = non-linked icon)
	 * @param string $title Visible title / tooltip
	 * @param bool $verified NewAuth-verified styling + badge
	 * @param string $aria_label Override aria-label (defaults to $title)
	 * @param bool $use_floating_ui Soft-dep FloatingUI tip instead of title=
	 * @return array<string, mixed>
	 */
	private function build_link_item(
		string $kind,
		string $url,
		string $title,
		bool $verified,
		string $aria_label,
		bool $use_floating_ui
	): array {
		return [
			'kind' => $kind,

			'url' => $url,
			'has_url' => $url !== '',

			'title' => $title,
			'aria_label' => $aria_label !== '' ? $aria_label : $title,

			'verified' => $verified,

			'use_floating_ui' => $use_floating_ui,

			'show_title' => !$use_floating_ui,
			'show_corner_badge' => $verified && !$use_floating_ui,

			'tip_text' => $title,
			'tip_url' => $url,
			'has_tip_url' => $url !== '',
			'tip_verified' => $verified
		];
	}

	/**
	 * @param string $name Template name without .mustache
	 * @param array<string, mixed> $args Mustache view data
	 * @return string
	 */
	private function process_template( string $name, array $args ): string {
		return $this->template_parser->processTemplate( $name, $args );
	}

	/**
	 * Public profile URL for a verified provider (empty if unknown).
	 */
	private static function connection_profile_url( string $provider, string $remote_user ): string {
		$remote_user = trim( $remote_user );
		if ( $remote_user === '' ) {
			return '';
		}
		$id = rawurlencode( $remote_user );

		return match ( $provider ) {
			'discord' => 'https://discord.com/users/' . $id,
			'roblox' => 'https://www.roblox.com/users/' . $id . '/profile',
			default => ''
		};
	}

}
