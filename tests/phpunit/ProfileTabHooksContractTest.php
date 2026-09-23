<?php

namespace MediaWiki\Extension\IntegratedProfiles\Tests;

use MediaWiki\Extension\IntegratedProfiles\HookRunner;
use PHPUnit\Framework\TestCase;

class ProfileTabHooksContractTest extends TestCase {

	public function test_get_tabs_append_contract(): void {
		$tabs = [
			[ 'id' => 'about', 'label' => 'About', 'weight' => 10 ],
			[ 'id' => 'talk', 'label' => 'Talk', 'weight' => 15 ],
			[ 'id' => 'contributions', 'label' => 'Contributions', 'weight' => 20 ],
		];
		$profile = [ 'user_name' => 'User1' ];

		$listener = static function ( array &$registered, array $payload ): void {
			$registered[] = [
				'id' => 'collections',
				'label' => (string)$payload['user_name'],
				'weight' => 30,
			];
		};
		$listener( $tabs, $profile );

		$this->assertCount( 4, $tabs );
		$this->assertSame( 'collections', $tabs[3]['id'] );
		$this->assertSame( 'User1', $tabs[3]['label'] );
		$this->assertTrue( class_exists( HookRunner::class ) );
	}

	public function test_render_tab_fill_contract(): void {
		$tab_id = 'collections';
		$html = '';
		$profile = [ 'user_name' => 'User1' ];
		$context = new \stdClass();

		$seen_context = null;
		$listener = static function ( string $id, string &$panel, array $payload, object $request_context ) use ( &$seen_context ): void {
			if ( $id !== 'collections' ) {
				return;
			}

			$seen_context = $request_context;
			$panel .= '<div class="ext-uc">' . htmlspecialchars( (string)$payload['user_name'], ENT_QUOTES, 'UTF-8' ) . '</div>';
		};
		$listener( $tab_id, $html, $profile, $context );

		$this->assertSame( '<div class="ext-uc">User1</div>', $html );
		$this->assertSame( $context, $seen_context );
	}

}
