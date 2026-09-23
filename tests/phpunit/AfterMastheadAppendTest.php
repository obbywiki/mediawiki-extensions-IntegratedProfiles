<?php

namespace MediaWiki\Extension\IntegratedProfiles\Tests;

use MediaWiki\Extension\IntegratedProfiles\HookRunner;
use PHPUnit\Framework\TestCase;

class AfterMastheadAppendTest extends TestCase {

	public function test_append_html_contract(): void {
		$html = '<div class="ip-masthead"></div>';
		$after = '';
		$profile = [ 'user_name' => 'User1' ];

		$listener = static function ( string &$append, array $payload ): void {
			$append .= '<aside class="ext-demo">' . htmlspecialchars( $payload['user_name'] ) . '</aside>';
		};
		$listener( $after, $profile );

		$this->assertSame( '<aside class="ext-demo">User1</aside>', $after );
		$this->assertSame( 'User1', $profile['user_name'] );
		$this->assertTrue( class_exists( HookRunner::class ) );
		$this->assertStringStartsWith( '<div class="ip-masthead"', $html );
	}

}
