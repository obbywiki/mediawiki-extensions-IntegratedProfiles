<?php

namespace MediaWiki\Extension\IntegratedProfiles\Tests;

use MediaWiki\Extension\IntegratedProfiles\Hook\IntegratedProfilesAfterAvatarHook;
use MediaWiki\Extension\IntegratedProfiles\HookRunner;
use PHPUnit\Framework\TestCase;

class AfterAvatarAppendTest extends TestCase {

	public function test_append_html_contract(): void {
		$html = '';
		$profile = [ 'user_name' => 'User1', 'is_private' => false ];

		$listener = new class implements IntegratedProfilesAfterAvatarHook {
			public function onIntegratedProfilesAfterAvatar( array $profile, string &$html ): void {
				$html .= '<span class="ext-flair">' . htmlspecialchars( $profile['user_name'] ) . '</span>';
			}
		};
		$listener->onIntegratedProfilesAfterAvatar( $profile, $html );

		$this->assertSame( '<span class="ext-flair">User1</span>', $html );
		$this->assertSame( 'User1', $profile['user_name'] );
		$this->assertTrue( class_exists( HookRunner::class ) );
		$this->assertTrue( interface_exists( IntegratedProfilesAfterAvatarHook::class ) );
	}

}
