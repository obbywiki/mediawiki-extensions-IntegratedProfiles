<?php

namespace MediaWiki\Extension\IntegratedProfiles\Tests;

use MediaWiki\Extension\IntegratedProfiles\AvatarService;
use MediaWiki\Extension\IntegratedProfiles\HeaderAvatar;
use PHPUnit\Framework\TestCase;

class HeaderAvatarTest extends TestCase {

	public function test_resolve_url_returns_custom_avatar_for_citizen(): void {
		$service = $this->avatar_service_with_info( [
			'avatar_url' => '/images/ipavatars/avatar_7.png?r=1',
			'has_custom_avatar' => true,
		] );

		$this->assertSame(
			'/images/ipavatars/avatar_7.png?r=1',
			HeaderAvatar::resolve_url( 'citizen', true, 7, $service )
		);
	}

	public function test_resolve_url_skips_anon(): void {
		$service = $this->avatar_service_with_info( [
			'avatar_url' => '/x.png',
			'has_custom_avatar' => true,
		] );

		$this->assertNull(
			HeaderAvatar::resolve_url( 'citizen', false, 0, $service )
		);
	}

	public function test_resolve_url_skips_non_citizen(): void {
		$service = $this->avatar_service_with_info( [
			'avatar_url' => '/x.png',
			'has_custom_avatar' => true,
		] );

		$this->assertNull(
			HeaderAvatar::resolve_url( 'vector', true, 7, $service )
		);
	}

	public function test_resolve_url_skips_default_avatar(): void {
		$service = $this->avatar_service_with_info( [
			'avatar_url' => '/extensions/IntegratedProfiles/resources/avatars/default.svg',
			'has_custom_avatar' => false,
		] );

		$this->assertNull(
			HeaderAvatar::resolve_url( 'citizen', true, 7, $service )
		);
	}

	public function test_css_url_value_escapes_quotes(): void {
		$this->assertSame(
			'url("/images/ipavatars/avatar_1.png?r=1")',
			HeaderAvatar::css_url_value( '/images/ipavatars/avatar_1.png?r=1' )
		);
		$this->assertSame(
			'url("a\\"b")',
			HeaderAvatar::css_url_value( 'a"b' )
		);
	}

	public function test_apply_body_attrs_sets_css_var(): void {
		$body_attrs = [];
		HeaderAvatar::apply_body_attrs(
			$body_attrs,
			'/images/ipavatars/avatar_7.png?r=9'
		);

		$this->assertSame(
			HeaderAvatar::CSS_VAR . ':url("/images/ipavatars/avatar_7.png?r=9");',
			$body_attrs['style']
		);
		$this->assertArrayNotHasKey( 'class', $body_attrs );
	}

	public function test_apply_body_attrs_appends_to_existing_style(): void {
		$body_attrs = [
			'style' => 'color:red;',
		];
		HeaderAvatar::apply_body_attrs( $body_attrs, '/a.png' );

		$this->assertSame(
			'color:red;' . HeaderAvatar::CSS_VAR . ':url("/a.png");',
			$body_attrs['style']
		);
	}

	/**
	 * @param array{avatar_url: string, has_custom_avatar: bool} $info
	 */
	private function avatar_service_with_info( array $info ): AvatarService {
		$service = $this->getMockBuilder( AvatarService::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'get_avatar_info' ] )
			->getMock();
		$service->method( 'get_avatar_info' )->willReturn( $info );

		return $service;
	}

}
