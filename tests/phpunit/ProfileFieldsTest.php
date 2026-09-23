<?php

namespace MediaWiki\Extension\IntegratedProfiles\Tests;

use MediaWiki\Extension\IntegratedProfiles\ProfileFields;
use PHPUnit\Framework\TestCase;

class ProfileFieldsTest extends TestCase {

	private ProfileFields $fields;

	protected function setUp(): void {
		parent::setUp();
		$this->fields = new ProfileFields( 20, 255 );
	}

	public function test_empty_fields_has_all_keys(): void {
		$empty = $this->fields->empty_fields();
		foreach ( ProfileFields::KEYS as $key ) {
			if ( $key === ProfileFields::KEY_BANNER ) {
				$expected = ProfileFields::BANNER_ACCENT;
			} elseif ( $key === ProfileFields::KEY_VISIBILITY ) {
				$expected = ProfileFields::VISIBILITY_PUBLIC;
			} elseif ( ProfileFields::is_flag_key( $key ) ) {
				$expected = '0';
			} else {
				$expected = '';
			}
			$this->assertSame( $expected, $empty[$key] );
		}
	}

	public function test_about_within_limit(): void {
		$result = $this->fields->sanitize_fields( [
			'ip-about' => '  Hello wiki  ',
		] );
		$this->assertSame( [], $result['invalid'] );
		$this->assertSame( 'Hello wiki', $result['fields']['ip-about'] );
	}

	public function test_about_rejects_over_max_length(): void {
		$result = $this->fields->sanitize_fields( [
			'ip-about' => str_repeat( 'a', 21 ),
		] );
		$this->assertSame( [ 'ip-about' ], $result['invalid'] );
		$this->assertArrayNotHasKey( 'ip-about', $result['fields'] );
	}

	public function test_website_accepts_https(): void {
		$result = $this->fields->sanitize_fields( [
			'ip-website' => 'https://obby.wiki/path',
		] );
		$this->assertSame( [], $result['invalid'] );
		$this->assertSame( 'https://obby.wiki/path', $result['fields']['ip-website'] );
	}

	public function test_website_rejects_localhost_and_loopback(): void {
		foreach ( [
			'http://localhost/x',
			'https://127.0.0.1/',
			'https://[::1]/',
			'ftp://example.com',
			'not-a-url',
		] as $url ) {
			$result = $this->fields->sanitize_fields( [ 'ip-website' => $url ] );
			$this->assertSame( [ 'ip-website' ], $result['invalid'], $url );
		}
	}

	public function test_website_empty_ok(): void {
		$result = $this->fields->sanitize_fields( [ 'ip-website' => '  ' ] );
		$this->assertSame( [], $result['invalid'] );
		$this->assertSame( '', $result['fields']['ip-website'] );
	}

	public function test_handles_strip_at_and_validate(): void {
		$result = $this->fields->sanitize_fields( [
			'ip-twitter' => '@User1',
			'ip-github' => 'user1',
		] );
		$this->assertSame( [], $result['invalid'] );
		$this->assertSame( 'User1', $result['fields']['ip-twitter'] );
		$this->assertSame( 'user1', $result['fields']['ip-github'] );
	}

	public function test_handles_reject_invalid(): void {
		$result = $this->fields->sanitize_fields( [
			'ip-twitter' => 'bad handle',
			'ip-github' => 'has/slash',
		] );
		$this->assertContains( 'ip-twitter', $result['invalid'] );
		$this->assertContains( 'ip-github', $result['invalid'] );
	}

	public function test_ignores_unknown_keys(): void {
		$result = $this->fields->sanitize_fields( [
			'ip-about' => 'ok',
			'not-a-field' => 'nope',
		] );
		$this->assertSame( [ 'ip-about' => 'ok' ], $result['fields'] );
		$this->assertSame( [], $result['invalid'] );
	}

	public function test_featured_article_accepts_title(): void {
		$result = $this->fields->sanitize_fields( [
			'ip-featured-article' => '  Towerable/Autumn Stage  ',
		] );
		$this->assertSame( [], $result['invalid'] );
		$this->assertSame( 'Towerable/Autumn Stage', $result['fields']['ip-featured-article'] );
	}

	public function test_featured_article_rejects_fragment_and_pipe(): void {
		foreach ( [ 'Foo#Bar', 'Foo|Bar', "Foo\nBar" ] as $value ) {
			$result = $this->fields->sanitize_fields( [ 'ip-featured-article' => $value ] );
			$this->assertSame( [ 'ip-featured-article' ], $result['invalid'], $value );
		}
	}

	public function test_featured_article_empty_ok(): void {
		$result = $this->fields->sanitize_fields( [ 'ip-featured-article' => '  ' ] );
		$this->assertSame( [], $result['invalid'] );
		$this->assertSame( '', $result['fields']['ip-featured-article'] );
	}

	public function test_banner_accepts_presets(): void {
		foreach ( ProfileFields::BANNER_PRESETS as $preset ) {
			$result = $this->fields->sanitize_fields( [ 'ip-banner' => $preset ] );
			$this->assertSame( [], $result['invalid'], $preset );
			$this->assertSame( $preset, $result['fields']['ip-banner'], $preset );
		}
	}

	public function test_banner_empty_becomes_accent(): void {
		$result = $this->fields->sanitize_fields( [ 'ip-banner' => '  ' ] );
		$this->assertSame( [], $result['invalid'] );
		$this->assertSame( 'accent', $result['fields']['ip-banner'] );
	}

	public function test_banner_rejects_unknown(): void {
		$result = $this->fields->sanitize_fields( [ 'ip-banner' => 'neon' ] );
		$this->assertSame( [ 'ip-banner' ], $result['invalid'] );
	}

	public function test_normalize_banner(): void {
		$this->assertSame( 'ocean', ProfileFields::normalize_banner( ' Ocean ' ) );
		$this->assertSame( 'accent', ProfileFields::normalize_banner( '' ) );
		$this->assertSame( 'accent', ProfileFields::normalize_banner( 'nope' ) );
	}

	public function test_build_public_links(): void {
		$links = $this->fields->build_public_links( [
			'ip-about' => 'bio',
			'ip-featured-article' => 'Some Page',
			'ip-website' => 'https://example.com/me',
			'ip-twitter' => 'user1',
			'ip-github' => 'user1',
			'ip-mediawiki' => 'User1',
			'ip-miraheze' => 'User1',
			'ip-fandom' => 'User1',
		] );

		$this->assertSame( 'example.com', $links['website']['label'] );
		$this->assertSame( 'https://example.com/me', $links['website']['url'] );
		$this->assertSame( '@user1', $links['twitter']['label'] );
		$this->assertSame( 'https://x.com/user1', $links['twitter']['url'] );
		$this->assertArrayNotHasKey( 'bluesky', $links );
		$this->assertSame( 'https://github.com/user1', $links['github']['url'] );
		$this->assertArrayNotHasKey( 'mediawiki', $links );
		$this->assertArrayNotHasKey( 'miraheze', $links );
		$this->assertArrayNotHasKey( 'fandom', $links );
	}

	public function test_build_wiki_profiles(): void {
		$profiles = $this->fields->build_wiki_profiles( [
			'ip-mediawiki' => 'User1',
			'ip-miraheze' => 'User1',
			'ip-fandom' => 'User1',
		] );

		$this->assertCount( 3, $profiles );
		$this->assertSame( 'mediawiki', $profiles[0]['kind'] );
		$this->assertSame( 'User1', $profiles[0]['username'] );
		$this->assertSame(
			'https://www.mediawiki.org/wiki/User:User1',
			$profiles[0]['url']
		);
		$this->assertSame(
			'https://meta.miraheze.org/wiki/User:User1',
			$profiles[1]['url']
		);
		$this->assertSame(
			'https://community.fandom.com/wiki/User:User1',
			$profiles[2]['url']
		);
	}

	public function test_wiki_usernames_sanitize(): void {
		$result = $this->fields->sanitize_fields( [
			'ip-mediawiki' => '  User_1  ',
			'ip-miraheze' => '@User 2',
			'ip-fandom' => 'User1',
		] );
		$this->assertSame( [], $result['invalid'] );
		$this->assertSame( 'User 1', $result['fields']['ip-mediawiki'] );
		$this->assertSame( 'User 2', $result['fields']['ip-miraheze'] );
		$this->assertSame( 'User1', $result['fields']['ip-fandom'] );
	}

	public function test_wiki_usernames_reject_illegal(): void {
		foreach ( [ 'ip-mediawiki', 'ip-miraheze', 'ip-fandom' ] as $key ) {
			foreach ( [ 'bad#name', 'has/slash', 'a|b', '' ] as $value ) {
				if ( $value === '' ) {
					continue;
				}
				$result = $this->fields->sanitize_fields( [ $key => $value ] );
				$this->assertSame( [ $key ], $result['invalid'], "$key:$value" );
			}
		}
	}

	public function test_build_public_links_skips_invalid_website(): void {
		$links = $this->fields->build_public_links( [
			'ip-website' => 'http://localhost',
			'ip-twitter' => '',
			'ip-github' => '',
		] );
		$this->assertSame( [], $links );
	}

	public function test_location_trims_and_rejects_overlong(): void {
		$ok = $this->fields->sanitize_fields( [ 'ip-location' => "  Seoul\t" ] );
		$this->assertSame( [], $ok['invalid'] );
		$this->assertSame( 'Seoul', $ok['fields']['ip-location'] );

		$too_long = str_repeat( 'a', ProfileFields::LOCATION_MAX_LENGTH + 1 );
		$bad = $this->fields->sanitize_fields( [ 'ip-location' => $too_long ] );
		$this->assertSame( [ 'ip-location' ], $bad['invalid'] );
	}

	public function test_show_pronouns_flag(): void {
		$on = $this->fields->sanitize_fields( [ 'ip-show-pronouns' => 'true' ] );
		$this->assertSame( [], $on['invalid'] );
		$this->assertSame( '1', $on['fields']['ip-show-pronouns'] );

		$off = $this->fields->sanitize_fields( [ 'ip-show-pronouns' => 'no' ] );
		$this->assertSame( '0', $off['fields']['ip-show-pronouns'] );
	}

	public function test_banner_wiki_is_blank_without_preset_catalog(): void {
		$result = $this->fields->sanitize_fields( [ 'ip-banner-wiki' => 'seasonal' ] );
		$this->assertSame( [], $result['invalid'] );
		$this->assertSame( '', $result['fields']['ip-banner-wiki'] );
	}

	public function test_hide_connections_flag(): void {
		$result = $this->fields->sanitize_fields( [
			'ip-hide-connections' => '1',
		] );
		$this->assertSame( [], $result['invalid'] );
		$this->assertSame( '1', $result['fields']['ip-hide-connections'] );
		$this->assertTrue( ProfileFields::is_flag_on( '1' ) );
		$this->assertFalse( ProfileFields::is_flag_on( '0' ) );

		$off = $this->fields->sanitize_fields( [
			'ip-hide-connections' => 'false',
		] );
		$this->assertSame( '0', $off['fields']['ip-hide-connections'] );
	}

	public function test_visibility_field(): void {
		$result = $this->fields->sanitize_fields( [
			'ip-visibility' => 'users',
		] );
		$this->assertSame( [], $result['invalid'] );
		$this->assertSame( 'users', $result['fields']['ip-visibility'] );

		$private = $this->fields->sanitize_fields( [
			'ip-visibility' => 'private',
		] );
		$this->assertSame( 'private', $private['fields']['ip-visibility'] );

		$invalid = $this->fields->sanitize_fields( [
			'ip-visibility' => 'friends',
		] );
		$this->assertSame( [ 'ip-visibility' ], $invalid['invalid'] );
		$this->assertArrayNotHasKey( 'ip-visibility', $invalid['fields'] );

		$this->assertSame(
			ProfileFields::VISIBILITY_PUBLIC,
			ProfileFields::normalize_visibility( '' )
		);
	}

	public function test_scrub_private_payload_keeps_avatar_and_clears_extras(): void {
		$scrubbed = ProfileFields::scrub_private_payload( [
			'user_id' => 7,
			'user_name' => 'User1',
			'real_name' => 'User1',
			'edit_count' => 12,
			'registration' => '20240101120000',
			'groups' => [ 'sysop' ],
			'fields' => [
				'ip-about' => 'Hello',
				'ip-banner' => 'ocean',
				'ip-visibility' => 'private',
				'ip-website' => 'https://example.com',
			],
			'links' => [
				'website' => [
					'label' => 'example.com',
					'url' => 'https://example.com',
					'kind' => 'website',
				],
			],
			'wiki_profiles' => [
				[ 'kind' => 'mediawiki', 'username' => 'User1', 'url' => 'https://www.mediawiki.org/wiki/User:User1' ],
			],
			'featured_article' => [
				'title' => 'Featured',
				'display_title' => 'Featured',
				'url' => '/wiki/Featured',
			],
			'avatar_url' => '/avatars/user1.jpg',
			'has_custom_avatar' => true,
			'banner_url' => '/banners/user1.jpg',
			'has_custom_banner' => true,
			'connections' => [
				[ 'provider' => 'discord', 'remote_user' => 'user1' ],
			],
			'ui' => [
				'color' => '#5288F1',
				'avatar_border_radius' => '50%',
			],
		] );

		$this->assertTrue( $scrubbed['is_private'] );
		$this->assertSame( 7, $scrubbed['user_id'] );
		$this->assertSame( 'User1', $scrubbed['user_name'] );
		$this->assertSame( '/avatars/user1.jpg', $scrubbed['avatar_url'] );
		$this->assertTrue( $scrubbed['has_custom_avatar'] );
		$this->assertSame( '', $scrubbed['real_name'] );
		$this->assertSame( 0, $scrubbed['edit_count'] );
		$this->assertNull( $scrubbed['registration'] );
		$this->assertSame( [], $scrubbed['groups'] );
		$this->assertSame( [], $scrubbed['links'] );
		$this->assertSame( [], $scrubbed['wiki_profiles'] );
		$this->assertNull( $scrubbed['featured_article'] );
		$this->assertSame( '', $scrubbed['banner_url'] );
		$this->assertFalse( $scrubbed['has_custom_banner'] );
		$this->assertSame( [], $scrubbed['connections'] );
		$this->assertSame( 'private', $scrubbed['fields']['ip-visibility'] );
		$this->assertSame( 'accent', $scrubbed['fields']['ip-banner'] );
		$this->assertSame( '', $scrubbed['fields']['ip-about'] );
		$this->assertSame( '#5288F1', $scrubbed['ui']['color'] );
		$this->assertSame( 'unknown', $scrubbed['gender'] );
		$this->assertSame( '', $scrubbed['custom_banner_url'] );
	}

	public function test_discord_username_sanitizes_and_has_no_url(): void {
		$result = $this->fields->sanitize_fields( [
			'ip-discord' => '@user_1',
		] );
		$this->assertSame( [], $result['invalid'] );
		$this->assertSame( 'user_1', $result['fields']['ip-discord'] );

		$links = $this->fields->build_public_links( [
			'ip-discord' => 'user_1',
		] );
		$this->assertSame( 'user_1', $links['discord']['label'] );
		$this->assertSame( '', $links['discord']['url'] );
		$this->assertSame( 'discord', $links['discord']['kind'] );
	}

	public function test_discord_username_allows_single_dots(): void {
		$result = $this->fields->sanitize_fields( [
			'ip-discord' => 'user.one',
		] );
		$this->assertSame( [], $result['invalid'] );
		$this->assertSame( 'user.one', $result['fields']['ip-discord'] );
	}

	public function test_discord_username_rejects_invalid(): void {
		foreach ( [ 'a', 'has space', 'Upper-Dash', 'has..dot' ] as $value ) {
			$result = $this->fields->sanitize_fields( [ 'ip-discord' => $value ] );
			$this->assertSame( [ 'ip-discord' ], $result['invalid'], $value );
		}
	}

	public function test_roblox_username_builds_shortcut_url(): void {
		$result = $this->fields->sanitize_fields( [
			'ip-roblox' => '@User1',
		] );
		$this->assertSame( [], $result['invalid'] );
		$this->assertSame( 'User1', $result['fields']['ip-roblox'] );

		$links = $this->fields->build_public_links( [
			'ip-roblox' => 'User1',
		] );
		$this->assertSame(
			'https://www.roblox.com/users/profile?username=User1',
			$links['roblox']['url']
		);
	}

	public function test_roblox_username_rejects_invalid(): void {
		foreach ( [ 'ab', 'has space', 'way_too_long_username_ok' ] as $value ) {
			$result = $this->fields->sanitize_fields( [ 'ip-roblox' => $value ] );
			$this->assertSame( [ 'ip-roblox' ], $result['invalid'], $value );
		}
	}

	public function test_youtube_url_allowlist(): void {
		foreach ( [
			'https://www.youtube.com/@user1',
			'https://youtube.com/channel/UCabc',
			'https://m.youtube.com/@user1',
			'https://music.youtube.com/channel/UCabc',
			'https://youtu.be/dQw4w9WgXcQ',
		] as $url ) {
			$result = $this->fields->sanitize_fields( [ 'ip-youtube' => $url ] );
			$this->assertSame( [], $result['invalid'], $url );
			$this->assertSame( $url, $result['fields']['ip-youtube'], $url );
		}

		$links = $this->fields->build_public_links( [
			'ip-youtube' => 'https://www.youtube.com/@user1',
		] );
		$this->assertSame( 'www.youtube.com', $links['youtube']['label'] );
		$this->assertSame( 'https://www.youtube.com/@user1', $links['youtube']['url'] );
	}

	public function test_youtube_url_rejects_other_hosts(): void {
		foreach ( [
			'https://example.com/watch',
			'https://youtub.com/@user1',
			'not-a-url',
		] as $url ) {
			$result = $this->fields->sanitize_fields( [ 'ip-youtube' => $url ] );
			$this->assertSame( [ 'ip-youtube' ], $result['invalid'], $url );
		}
	}

	public function test_disabled_social_keys_are_ignored_on_sanitize(): void {
		$fields = new ProfileFields( 20, 255, [ 'website', 'twitter' ] );
		$result = $fields->sanitize_fields( [
			'ip-website' => 'https://example.com',
			'ip-discord' => 'user2',
			'ip-github' => 'user1',
		] );
		$this->assertSame( [], $result['invalid'] );
		$this->assertSame( 'https://example.com', $result['fields']['ip-website'] );
		$this->assertArrayNotHasKey( 'ip-discord', $result['fields'] );
		$this->assertArrayNotHasKey( 'ip-github', $result['fields'] );
	}

	public function test_build_public_links_respects_enabled_list(): void {
		$fields = new ProfileFields( 20, 255, [ 'discord', 'bogus' ] );
		$links = $fields->build_public_links( [
			'ip-website' => 'https://example.com/me',
			'ip-twitter' => 'user1',
			'ip-discord' => 'user1',
		] );
		$this->assertArrayHasKey( 'discord', $links );
		$this->assertArrayNotHasKey( 'website', $links );
		$this->assertArrayNotHasKey( 'twitter', $links );
	}

	public function test_empty_enabled_list_emits_no_social_links(): void {
		$fields = new ProfileFields( 20, 255, [] );
		$links = $fields->build_public_links( [
			'ip-website' => 'https://example.com/me',
			'ip-twitter' => 'user1',
		] );
		$this->assertSame( [], $links );
	}

	public function test_omit_verified_socials_drops_matching_kinds(): void {
		$links = $this->fields->build_public_links( [
			'ip-discord' => 'user1',
			'ip-roblox' => 'Builder',
			'ip-github' => 'user1',
		] );
		$filtered = $this->fields->omit_verified_socials( $links, [
			[ 'provider' => 'discord', 'remote_user' => '111' ],
		] );
		$this->assertArrayNotHasKey( 'discord', $filtered );
		$this->assertArrayHasKey( 'roblox', $filtered );
		$this->assertArrayHasKey( 'github', $filtered );
	}

	public function test_normalize_enabled_social_links(): void {
		$this->assertSame(
			array_keys( ProfileFields::SOCIAL_CATALOG ),
			ProfileFields::normalize_enabled_social_links( null )
		);
		$this->assertSame(
			[ 'discord', 'website' ],
			ProfileFields::normalize_enabled_social_links( [ 'Discord', 'bogus', 'website', 'discord' ] )
		);
		$this->assertSame( [], ProfileFields::normalize_enabled_social_links( [] ) );
	}

}
