<?php

namespace MediaWiki\Extension\IntegratedProfiles\Tests;

use MediaWiki\Extension\IntegratedProfiles\ProfileRenderer;
use MediaWiki\Html\TemplateParser;
use PHPUnit\Framework\TestCase;

class ProfileRendererTest extends TestCase {

	private ProfileRenderer $renderer;

	protected function setUp(): void {
		parent::setUp();
		if ( !class_exists( TemplateParser::class ) ) {
			$this->markTestSkipped( 'Masthead HTML tests need MediaWiki TemplateParser.' );
		}
		$this->renderer = new ProfileRenderer(
			new TemplateParser( dirname( __DIR__, 2 ) . '/templates' )
		);
	}

	public function test_renders_cutout_structure_and_escapes(): void {
		$html = $this->renderer->render_masthead(
			[
				'user_name' => 'User1<script>',
				'real_name' => 'User1',
				'edit_count' => 12,
				'registration' => '20240101120000',
				'groups' => [
					[ 'label' => 'Sysop', 'url' => '/wiki/Project:Sysop' ],
					[ 'label' => '<b>x</b>', 'url' => '/wiki/Project:X' ],
				],
				'fields' => [
					'ip-about' => "Hello\n<script>alert(1)</script>",
				],
				'featured_article' => [
					'title' => 'Featured',
					'display_title' => 'Featured',
					'url' => '/wiki/Featured',
				],
				'links' => [
					'github' => [
						'label' => 'user1',
						'url' => 'https://github.com/user1',
						'kind' => 'github',
					],
				],
				'avatar_url' => '/extensions/IntegratedProfiles/resources/avatars/default.svg',
				'ui' => [
					'color' => '#5288F1',
					'avatar_border_radius' => '50%',
				],
			],
			true,
			[
				'aka' => '(User1)',
				'edit_count' => '12 edits',
				'joined' => 'Joined 1 January 2024',
				'avatar_alt' => 'Profile avatar',
				'avatar_edit' => 'Change avatar',
				'edit' => 'Edit profile',
				'featured_label' => 'Featured article',
			],
			'/wiki/Special:Contributions/User1'
		);

		$this->assertStringContainsString( 'class="ip-masthead"', $html );
		$featured_pos = strpos( $html, 'class="ip-featured"' );
		$about_pos = strpos( $html, 'class="ip-about-block"' );
		$this->assertNotFalse( $featured_pos );
		$this->assertNotFalse( $about_pos );
		$this->assertLessThan( $about_pos, $featured_pos );
		$this->assertStringContainsString( 'class="ip-about"', $html );
		$this->assertStringContainsString( 'class="ip-masthead__band ip-masthead__band--accent"', $html );
		$this->assertStringContainsString( 'class="ip-masthead__hero"', $html );
		$this->assertStringContainsString( 'class="ip-masthead__body"', $html );
		$this->assertStringContainsString( 'class="ip-identity"', $html );
		$this->assertStringNotContainsString( 'ip-identity--hero', $html );
		$this->assertStringContainsString( 'class="ip-identity__meta"', $html );
		$this->assertStringContainsString( 'User1&lt;script&gt;', $html );
		$this->assertStringContainsString( '&lt;script&gt;alert(1)&lt;/script&gt;', $html );
		$this->assertStringNotContainsString( '<script>alert(1)</script>', $html );
		$this->assertStringNotContainsString( 'ip-avatar__decoration', $html );
		$this->assertStringContainsString( 'id="integratedprofiles-edit"', $html );
		$this->assertStringContainsString( 'id="integratedprofiles-avatar-edit"', $html );
		$this->assertStringContainsString( 'aria-label="Change avatar"', $html );
		$this->assertStringContainsString( 'id="integratedprofiles-editor-root"', $html );
		$this->assertStringContainsString( 'https://github.com/user1', $html );
		$this->assertStringContainsString( 'ip-links__item--github', $html );
		$this->assertStringContainsString( 'class="ip-links__icon"', $html );
		$this->assertStringContainsString( 'aria-label="user1"', $html );
		$this->assertMatchesRegularExpression(
			'/ip-masthead__body[\s\S]*ip-links-wrap[\s\S]*ip-masthead__actions/',
			$html
		);
		$this->assertStringContainsString( 'Sysop', $html );
		$this->assertStringContainsString( 'href="/wiki/Project:Sysop"', $html );
		$this->assertStringContainsString( '&lt;b&gt;x&lt;/b&gt;', $html );
		$this->assertStringNotContainsString( '<b>x</b>', $html );
		$this->assertStringContainsString( '12 edits', $html );
		$this->assertStringContainsString( 'Joined 1 January 2024', $html );
		$this->assertStringContainsString(
			'href="/wiki/Special:Contributions/User1"',
			$html
		);
	}

	public function test_omits_join_date_when_unavailable(): void {
		$html = $this->renderer->render_masthead(
			[
				'user_name' => 'User2',
				'real_name' => '',
				'edit_count' => 0,
				'registration' => null,
				'fields' => [ 'ip-about' => '' ],
				'links' => [],
				'groups' => [],
				'avatar_url' => '/x.svg',
				'ui' => [ 'color' => '#5288F1', 'avatar_border_radius' => '50%' ],
			],
			false,
			[
				'edit_count' => '0 edits',
				'joined' => '',
				'avatar_alt' => 'Profile avatar',
			],
			''
		);

		$this->assertStringContainsString( 'class="ip-identity__meta"', $html );
		$this->assertStringContainsString( '0 edits', $html );
		$this->assertStringNotContainsString( 'Joined', $html );
		$this->assertStringNotContainsString( 'ip-identity__meta-item--joined', $html );
	}

	public function test_private_masthead_shows_avatar_name_and_notice(): void {
		$html = $this->renderer->render_masthead(
			[
				'is_private' => true,
				'user_name' => 'User1',
				'real_name' => 'Secret',
				'edit_count' => 0,
				'registration' => null,
				'groups' => [
					[ 'label' => 'Sysop', 'url' => '/wiki/Project:Sysop' ],
				],
				'fields' => [
					'ip-about' => '',
					'ip-banner' => 'accent',
					'ip-visibility' => 'private',
				],
				'links' => [
					'github' => [
						'label' => 'private',
						'url' => 'https://github.com/private',
						'kind' => 'github',
					],
				],
				'wiki_profiles' => [],
				'featured_article' => [
					'title' => 'Featured',
					'display_title' => 'Featured',
					'url' => '/wiki/Featured',
				],
				'avatar_url' => '/avatars/private.jpg',
				'has_custom_avatar' => true,
				'banner_url' => '',
				'connections' => [],
				'ui' => [ 'color' => '#5288F1', 'avatar_border_radius' => '50%' ],
			],
			false,
			[
				'aka' => '(Secret)',
				'edit_count' => '0 edits',
				'joined' => '',
				'avatar_alt' => 'Profile avatar',
				'private_notice' => 'This profile\'s details are hidden.',
				'featured_label' => 'Featured article',
			],
			'/wiki/Special:Contributions/User1'
		);

		$this->assertStringContainsString( 'ip-masthead--private', $html );
		$this->assertStringContainsString( 'User1', $html );
		$this->assertStringContainsString( '/avatars/private.jpg', $html );
		$this->assertStringContainsString( 'class="ip-identity__private"', $html );
		$this->assertStringContainsString(
			htmlspecialchars( "This profile's details are hidden.", ENT_QUOTES ),
			$html
		);
		$this->assertStringContainsString( 'ip-masthead__band--accent', $html );
		$this->assertStringNotContainsString( 'ip-identity__meta', $html );
		$this->assertStringNotContainsString( '0 edits', $html );
		$this->assertStringNotContainsString( '(Secret)', $html );
		$this->assertStringNotContainsString( 'Sysop', $html );
		$this->assertStringNotContainsString( 'github.com/private', $html );
		$this->assertStringNotContainsString( 'ip-featured', $html );
		$this->assertStringNotContainsString( 'ip-about-block', $html );
		$this->assertStringNotContainsString( 'id="integratedprofiles-edit"', $html );
	}

	public function test_avatar_decoration_sits_after_image_before_edit(): void {
		$html = $this->renderer->render_masthead(
			[
				'user_name' => 'User1',
				'real_name' => '',
				'edit_count' => 1,
				'fields' => [ 'ip-about' => '' ],
				'links' => [],
				'groups' => [],
				'avatar_url' => '/x.svg',
				'ui' => [ 'color' => '#5288F1', 'avatar_border_radius' => '50%' ],
			],
			true,
			[
				'edit_count' => '1 edit',
				'avatar_alt' => 'Profile avatar',
				'avatar_edit' => 'Change avatar',
			],
			'',
			false,
			false,
			'<span class="ext-flair">badge</span>'
		);

		$this->assertMatchesRegularExpression(
			'/ip-avatar__image[\s\S]*ip-avatar__decoration[\s\S]*id="integratedprofiles-avatar-edit"/',
			$html
		);
		$this->assertStringContainsString(
			'<div class="ip-avatar__decoration"><span class="ext-flair">badge</span></div>',
			$html
		);
	}

	public function test_avatar_decoration_still_spliced_when_private(): void {
		$html = $this->renderer->render_masthead(
			[
				'is_private' => true,
				'user_name' => 'User1',
				'real_name' => '',
				'edit_count' => 0,
				'fields' => [ 'ip-about' => '', 'ip-visibility' => 'private' ],
				'links' => [],
				'groups' => [],
				'avatar_url' => '/avatars/private.jpg',
				'ui' => [ 'color' => '#5288F1', 'avatar_border_radius' => '50%' ],
			],
			false,
			[
				'avatar_alt' => 'Profile avatar',
				'private_notice' => 'This profile\'s details are hidden.',
			],
			'',
			false,
			false,
			'<span class="ext-flair">badge</span>'
		);

		$this->assertStringContainsString( 'ip-masthead--private', $html );
		$this->assertStringContainsString(
			'<div class="ip-avatar__decoration"><span class="ext-flair">badge</span></div>',
			$html
		);
		$this->assertStringNotContainsString( 'id="integratedprofiles-avatar-edit"', $html );
	}

	public function test_renders_you_badge_for_owner(): void {
		$html = $this->renderer->render_masthead(
			[
				'user_name' => 'User1',
				'real_name' => '',
				'edit_count' => 1,
				'fields' => [ 'ip-about' => '' ],
				'links' => [],
				'groups' => [],
				'avatar_url' => '/x.svg',
				'ui' => [ 'color' => '#5288F1', 'avatar_border_radius' => '50%' ],
			],
			true,
			[
				'edit_count' => '1 edit',
				'avatar_alt' => 'Profile avatar',
				'you' => 'You',
			],
			'',
			false,
			true
		);

		$this->assertStringContainsString( 'class="ip-masthead ip-masthead--self"', $html );
		$this->assertStringContainsString( 'class="ip-identity__you"', $html );
		$this->assertStringContainsString( '>You</span>', $html );
	}

	public function test_omits_you_badge_for_other_profiles(): void {
		$html = $this->renderer->render_masthead(
			[
				'user_name' => 'User2',
				'real_name' => '',
				'edit_count' => 1,
				'fields' => [ 'ip-about' => '' ],
				'links' => [],
				'groups' => [],
				'avatar_url' => '/x.svg',
				'ui' => [ 'color' => '#5288F1', 'avatar_border_radius' => '50%' ],
			],
			true,
			[
				'edit_count' => '1 edit',
				'avatar_alt' => 'Profile avatar',
				'you' => 'You',
			],
			'',
			false,
			false
		);

		$this->assertStringContainsString( 'class="ip-masthead"', $html );
		$this->assertStringNotContainsString( 'ip-masthead--self', $html );
		$this->assertStringNotContainsString( 'ip-identity__you', $html );
	}

	public function test_escapes_join_date_label(): void {
		$html = $this->renderer->render_masthead(
			[
				'user_name' => 'User3',
				'real_name' => '',
				'edit_count' => 1,
				'registration' => '20240101120000',
				'fields' => [ 'ip-about' => '' ],
				'links' => [],
				'groups' => [],
				'avatar_url' => '/x.svg',
				'ui' => [ 'color' => '#5288F1', 'avatar_border_radius' => '50%' ],
			],
			false,
			[
				'edit_count' => '1 edit',
				'joined' => 'Joined <img src=x onerror=alert(1)>',
				'avatar_alt' => 'Profile avatar',
			],
			'/contrib'
		);

		$this->assertStringContainsString(
			'Joined &lt;img src=x onerror=alert(1)&gt;',
			$html
		);
		$this->assertStringNotContainsString( '<img src=x', $html );
	}

	public function test_hides_edit_controls_for_readers(): void {
		$html = $this->renderer->render_masthead(
			[
				'user_name' => 'User2',
				'real_name' => '',
				'fields' => [ 'ip-about' => '' ],
				'links' => [],
				'groups' => [],
				'avatar_url' => '/x.svg',
				'ui' => [ 'color' => '#5288F1', 'avatar_border_radius' => '50%' ],
			],
			false,
			[
				'edit_count' => '0 edits',
				'avatar_alt' => 'Profile avatar',
			],
			''
		);

		$this->assertStringNotContainsString( 'integratedprofiles-edit', $html );
		$this->assertStringNotContainsString( 'integratedprofiles-avatar-edit', $html );
		$this->assertStringNotContainsString( 'integratedprofiles-editor-root', $html );
	}

	public function test_renders_verified_connections_in_links_row(): void {
		$html = $this->renderer->render_masthead(
			[
				'user_name' => 'User1',
				'real_name' => '',
				'fields' => [ 'ip-about' => '' ],
				'links' => [
					'github' => [
						'label' => 'user1',
						'url' => 'https://github.com/user1',
						'kind' => 'github',
					],
				],
				'groups' => [],
				'connections' => [
					[
						'provider' => 'discord',
						'remote_user' => '111',
						'remote_username' => 'user1',
						'metadata' => [],
					],
					[
						'provider' => 'roblox',
						'remote_user' => '222',
						'remote_username' => '',
						'metadata' => [],
					],
				],
				'avatar_url' => '/x.svg',
				'ui' => [ 'color' => '#5288F1', 'avatar_border_radius' => '50%' ],
			],
			false,
			[
				'edit_count' => '0 edits',
				'avatar_alt' => 'Profile avatar',
				'connection_verified' => 'Verified',
				'connection_labels' => [
					'discord' => 'Discord',
					'roblox' => 'Roblox',
				],
			],
			''
		);

		$this->assertStringContainsString( 'class="ip-links-wrap"', $html );
		$this->assertStringContainsString( 'ip-links__item--github', $html );
		$this->assertStringContainsString( 'ip-links__item--discord', $html );
		$this->assertStringContainsString( 'ip-links__item--verified', $html );
		$this->assertStringContainsString( 'ip-links__badge', $html );
		$this->assertStringContainsString( 'https://discord.com/users/111', $html );
		$this->assertStringContainsString( 'title="Discord: user1"', $html );
		$this->assertStringNotContainsString( 'ext-floatingui-reference', $html );
		$this->assertStringContainsString( 'Verified', $html );
		$this->assertStringContainsString( 'ip-links__item--roblox', $html );
		$this->assertStringContainsString(
			'https://www.roblox.com/users/222/profile',
			$html
		);
		$this->assertStringNotContainsString( 'Manage connected accounts', $html );
		$this->assertStringNotContainsString( 'ip-links__manage', $html );
		$this->assertStringNotContainsString( 'ip-connections', $html );
	}

	public function test_renders_social_discord_chip_without_href(): void {
		$html = $this->renderer->render_masthead(
			[
				'user_name' => 'User1',
				'real_name' => '',
				'fields' => [ 'ip-about' => '' ],
				'links' => [
					'discord' => [
						'label' => 'user1',
						'url' => '',
						'kind' => 'discord',
					],
				],
				'groups' => [],
				'connections' => [],
				'avatar_url' => '/x.svg',
				'ui' => [ 'color' => '#5288F1', 'avatar_border_radius' => '50%' ],
			],
			false,
			[
				'edit_count' => '0 edits',
				'avatar_alt' => 'Profile avatar',
			],
			''
		);

		$this->assertStringContainsString( 'ip-links__item--discord', $html );
		$this->assertStringContainsString( 'title="user1"', $html );
		$this->assertStringContainsString( 'role="img"', $html );
		$this->assertStringNotContainsString( 'href=""', $html );
		$this->assertDoesNotMatchRegularExpression(
			'/<a[^>]*class="ip-links__anchor"/',
			$html
		);
	}

	public function test_floating_ui_tips_replace_native_title(): void {
		$html = $this->renderer->render_masthead(
			[
				'user_name' => 'User1',
				'real_name' => '',
				'fields' => [ 'ip-about' => 'Hello' ],
				'links' => [
					[
						'label' => 'user1',
						'url' => 'https://github.com/user1',
						'kind' => 'github',
					],
				],
				'wiki_profiles' => [
					[
						'kind' => 'mediawiki',
						'username' => 'User1',
						'url' => 'https://www.mediawiki.org/wiki/User:User1',
					],
				],
				'groups' => [],
				'connections' => [
					[
						'provider' => 'discord',
						'remote_user' => '111',
						'remote_username' => 'user1',
						'metadata' => [],
					],
				],
				'avatar_url' => '/x.svg',
				'ui' => [ 'color' => '#5288F1', 'avatar_border_radius' => '50%' ],
			],
			true,
			[
				'edit_count' => '0 edits',
				'avatar_alt' => 'Profile avatar',
				'avatar_edit' => 'Change avatar',
				'edit' => 'Edit profile',
				'wiki_profiles_label' => 'Wiki profiles',
				'wiki_profile_labels' => [
					'mediawiki' => 'MediaWiki.org',
				],
				'connection_verified' => 'Verified',
				'connection_labels' => [
					'discord' => 'Discord',
				],
			],
			'',
			true
		);

		$this->assertStringContainsString( 'ext-floatingui-reference', $html );
		$this->assertStringContainsString( 'ext-floatingui-content', $html );
		$this->assertStringContainsString( 'class="ip-tip"', $html );
		$this->assertStringContainsString( 'class="ip-tip ip-tip--verified"', $html );
		$this->assertStringContainsString( 'class="ip-tip__badge"', $html );
		$this->assertStringContainsString( 'class="ip-tip__link"', $html );
		$this->assertStringContainsString(
			'href="https://discord.com/users/111"',
			$html
		);
		$this->assertStringContainsString( '>Discord: user1</a>', $html );
		$this->assertStringContainsString( '>user1</a>', $html );
		$this->assertStringNotContainsString( 'class="ip-wiki-profiles"', $html );
		$this->assertStringNotContainsString( '>MediaWiki.org: User1</a>', $html );
		$this->assertStringContainsString( '>Change avatar</span>', $html );
		$this->assertStringNotContainsString( 'title="Discord: user1"', $html );
		$this->assertStringNotContainsString( 'title="user1"', $html );
		$this->assertStringNotContainsString( 'title="MediaWiki.org: User1"', $html );
		$this->assertStringNotContainsString( 'title="Change avatar"', $html );
		$this->assertStringContainsString( 'aria-label="Change avatar"', $html );
		$this->assertStringContainsString( 'ip-links__item--verified', $html );
		// Corner overlay stays off the chip when FloatingUI owns the tip.
		$this->assertStringNotContainsString( 'ip-links__badge', $html );
	}

	public function test_verified_connections_without_manage_link_on_masthead(): void {
		$html = $this->renderer->render_masthead(
			[
				'user_name' => 'User1',
				'real_name' => '',
				'fields' => [ 'ip-about' => '' ],
				'links' => [],
				'groups' => [],
				'connections' => [
					[
						'provider' => 'discord',
						'remote_user' => '111',
						'remote_username' => 'user1',
						'metadata' => [],
					],
				],
				'avatar_url' => '/x.svg',
				'ui' => [ 'color' => '#5288F1', 'avatar_border_radius' => '50%' ],
			],
			false,
			[
				'edit_count' => '0 edits',
				'avatar_alt' => 'Profile avatar',
				'connection_labels' => [ 'discord' => 'Discord' ],
			],
			''
		);

		$this->assertStringContainsString( 'ip-links__item--discord', $html );
		$this->assertStringNotContainsString( 'Manage connected accounts', $html );
		$this->assertStringNotContainsString( 'ip-links__manage', $html );
	}

	public function test_omits_links_row_when_empty(): void {
		$html = $this->renderer->render_masthead(
			[
				'user_name' => 'User2',
				'real_name' => '',
				'fields' => [ 'ip-about' => '' ],
				'links' => [],
				'wiki_profiles' => [],
				'groups' => [],
				'connections' => [],
				'avatar_url' => '/x.svg',
				'ui' => [ 'color' => '#5288F1', 'avatar_border_radius' => '50%' ],
			],
			false,
			[
				'edit_count' => '0 edits',
				'avatar_alt' => 'Profile avatar',
			],
			''
		);

		$this->assertStringNotContainsString( 'ip-links', $html );
		$this->assertStringNotContainsString( 'ip-about-block', $html );
		$this->assertStringNotContainsString( 'Manage connected accounts', $html );
	}

	public function test_hides_wiki_platform_chips_in_about_block(): void {
		$html = $this->renderer->render_masthead(
			[
				'user_name' => 'User1',
				'real_name' => '',
				'fields' => [ 'ip-about' => 'Hello there' ],
				'links' => [],
				'wiki_profiles' => [
					[
						'kind' => 'mediawiki',
						'username' => 'User1',
						'url' => 'https://www.mediawiki.org/wiki/User:User1',
					],
					[
						'kind' => 'fandom',
						'username' => 'User1',
						'url' => 'https://community.fandom.com/wiki/User:User1',
					],
				],
				'groups' => [],
				'connections' => [],
				'avatar_url' => '/x.svg',
				'ui' => [ 'color' => '#5288F1', 'avatar_border_radius' => '50%' ],
			],
			false,
			[
				'edit_count' => '0 edits',
				'avatar_alt' => 'Profile avatar',
				'wiki_profiles_label' => 'Wiki profiles',
				'wiki_profile_labels' => [
					'mediawiki' => 'MediaWiki.org',
					'miraheze' => 'Miraheze',
					'fandom' => 'Fandom',
				],
			],
			''
		);

		$this->assertStringContainsString( 'class="ip-about-block"', $html );
		$this->assertStringContainsString( 'class="ip-about"', $html );
		$this->assertStringContainsString( 'Hello there', $html );
		// wiki platform chips stay out of the masthead when ProfileFields::SHOW_WIKI_PROFILES is false
		$this->assertStringNotContainsString( 'class="ip-wiki-profiles"', $html );
		$this->assertStringNotContainsString( 'ip-wiki-profiles__item--mediawiki', $html );
		$this->assertStringNotContainsString( 'ip-wiki-profiles__item--fandom', $html );
		$this->assertStringNotContainsString(
			'https://www.mediawiki.org/wiki/User:User1',
			$html
		);
		$this->assertStringNotContainsString( 'ip-links__item--mediawiki', $html );
	}

	public function test_escapes_connection_display(): void {
		$html = $this->renderer->render_masthead(
			[
				'user_name' => 'User3',
				'real_name' => '',
				'fields' => [ 'ip-about' => '' ],
				'links' => [],
				'groups' => [],
				'connections' => [
					[
						'provider' => 'discord',
						'remote_user' => '1',
						'remote_username' => '<img src=x>',
						'metadata' => [],
					],
				],
				'avatar_url' => '/x.svg',
				'ui' => [ 'color' => '#5288F1', 'avatar_border_radius' => '50%' ],
			],
			false,
			[
				'edit_count' => '0 edits',
				'avatar_alt' => 'Profile avatar',
				'connection_labels' => [ 'discord' => 'Discord' ],
			],
			''
		);

		$this->assertStringContainsString( 'Discord: &lt;img src=x&gt;', $html );
		$this->assertStringNotContainsString( 'title="Discord: <img src=x>"', $html );
	}

	public function test_render_tabs_marks_active_and_escapes(): void {
		$html = $this->renderer->render_tabs( [
			[
				'id' => 'about',
				'label' => 'About',
				'url' => '/wiki/User:User1',
				'active' => true,
			],
			[
				'id' => 'contributions',
				'label' => 'Contributions<script>',
				'url' => '/wiki/Special:Contributions/User1',
				'active' => false,
			],
		] );

		$this->assertStringContainsString( 'class="ip-tabs"', $html );
		$this->assertStringContainsString( 'role="tablist"', $html );
		$this->assertStringContainsString( 'ip-tabs__tab--active', $html );
		$this->assertStringContainsString( 'aria-current="page"', $html );
		$this->assertStringContainsString( 'href="/wiki/User:User1"', $html );
		$this->assertStringContainsString(
			'href="/wiki/Special:Contributions/User1"',
			$html
		);
		$this->assertStringContainsString( 'Contributions&lt;script&gt;', $html );
		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertStringContainsString( 'data-ip-tab="contributions"', $html );
	}

	public function test_render_tabs_empty_returns_empty_string(): void {
		$this->assertSame( '', $this->renderer->render_tabs( [] ) );
	}

	public function test_render_featured_article_card(): void {
		$html = $this->renderer->render_featured_article(
			[
				'title' => 'Towerable',
				'display_title' => 'Towerable',
				'url' => '/wiki/Towerable',
			],
			[ 'featured_label' => 'Featured article' ]
		);

		$this->assertStringContainsString( 'class="ip-featured"', $html );
		$this->assertStringContainsString( 'aria-label="Featured article"', $html );
		$this->assertStringContainsString( 'class="ip-featured__icon"', $html );
		$this->assertStringContainsString( 'href="/wiki/Towerable"', $html );
		$this->assertStringContainsString( 'class="ip-featured__title"', $html );
		$this->assertStringContainsString( 'Towerable', $html );
	}

	public function test_render_featured_article_null_is_empty(): void {
		$this->assertSame(
			'',
			$this->renderer->render_featured_article( null, [ 'featured_label' => 'Featured' ] )
		);
	}

	public function test_render_featured_article_escapes(): void {
		$html = $this->renderer->render_featured_article(
			[
				'title' => 'A<script>',
				'display_title' => 'A<script>',
				'url' => '/wiki/A"onclick=x',
			],
			[ 'featured_label' => 'Feat<script>' ]
		);

		$this->assertStringContainsString( 'A&lt;script&gt;', $html );
		$this->assertStringContainsString( 'Feat&lt;script&gt;', $html );
		$this->assertStringContainsString( 'href="/wiki/A&quot;onclick=x"', $html );
		$this->assertStringNotContainsString( '<script>', $html );
	}

	public function test_renders_banner_preset_class(): void {
		$html = $this->renderer->render_masthead(
			[
				'user_name' => 'User1',
				'real_name' => '',
				'fields' => [
					'ip-about' => '',
					'ip-banner' => 'ocean',
				],
				'links' => [],
				'groups' => [],
				'avatar_url' => '/x.svg',
				'banner_url' => '',
				'has_custom_banner' => false,
				'ui' => [ 'color' => '#5288F1', 'avatar_border_radius' => '50%' ],
			],
			false,
			[
				'edit_count' => '0 edits',
				'avatar_alt' => 'Profile avatar',
			],
			''
		);

		$this->assertStringContainsString(
			'class="ip-masthead__band ip-masthead__band--ocean"',
			$html
		);
		$this->assertStringNotContainsString( '--ip-banner-image', $html );
	}

	public function test_renders_custom_banner_image(): void {
		$html = $this->renderer->render_masthead(
			[
				'user_name' => 'User1',
				'real_name' => '',
				'fields' => [
					'ip-about' => '',
					'ip-banner' => 'custom',
				],
				'links' => [],
				'groups' => [],
				'avatar_url' => '/x.svg',
				'banner_url' => '/ipbanners/banner_1.png?r=1',
				'has_custom_banner' => true,
				'ui' => [ 'color' => '#5288F1', 'avatar_border_radius' => '50%' ],
			],
			false,
			[
				'edit_count' => '0 edits',
				'avatar_alt' => 'Profile avatar',
			],
			''
		);

		$this->assertStringContainsString(
			'class="ip-masthead__band ip-masthead__band--custom"',
			$html
		);
		$this->assertStringContainsString(
			'--ip-banner-image:url(/ipbanners/banner_1.png?r=1)',
			$html
		);
	}

	public function test_custom_banner_without_url_falls_back_to_accent(): void {
		$html = $this->renderer->render_masthead(
			[
				'user_name' => 'User1',
				'real_name' => '',
				'fields' => [
					'ip-about' => '',
					'ip-banner' => 'custom',
				],
				'links' => [],
				'groups' => [],
				'avatar_url' => '/x.svg',
				'banner_url' => '',
				'has_custom_banner' => false,
				'ui' => [ 'color' => '#5288F1', 'avatar_border_radius' => '50%' ],
			],
			false,
			[
				'edit_count' => '0 edits',
				'avatar_alt' => 'Profile avatar',
			],
			''
		);

		$this->assertStringContainsString(
			'class="ip-masthead__band ip-masthead__band--accent"',
			$html
		);
		$this->assertStringNotContainsString( '--ip-banner-image', $html );
	}

}
