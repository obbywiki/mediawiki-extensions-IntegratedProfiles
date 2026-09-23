<?php
declare( strict_types=1 );

namespace MediaWiki\Extension\IntegratedProfiles\Tests\Unit;

use MediaWiki\Context\IContextSource;
use MediaWiki\Extension\IntegratedProfiles\HookRunner;
use MediaWiki\HookContainer\HookContainer;
use MediaWikiUnitTestCase;

/**
 * @group IntegratedProfiles
 * @covers \MediaWiki\Extension\IntegratedProfiles\HookRunner
 */
class HookRunnerTest extends MediaWikiUnitTestCase {

	public function test_after_masthead_runs_hook(): void {
		$html = '<div class="ip-masthead"></div>';
		$profile = [ 'user_name' => 'User1' ];
		$hook_container = $this->createMock( HookContainer::class );
		$hook_container->expects( $this->once() )
			->method( 'run' )
			->with(
				'IntegratedProfilesAfterMasthead',
				$this->callback( static function ( array $args ) use ( $profile ): bool {
					return count( $args ) === 2
						&& is_string( $args[0] )
						&& $args[1] === $profile;
				} )
			);

		( new HookRunner( $hook_container ) )->onIntegratedProfilesAfterMasthead( $html, $profile );
	}

	public function test_after_avatar_runs_hook(): void {
		$html = '';
		$profile = [ 'user_name' => 'User1', 'is_private' => false ];
		$hook_container = $this->createMock( HookContainer::class );
		$hook_container->expects( $this->once() )
			->method( 'run' )
			->with(
				'IntegratedProfilesAfterAvatar',
				$this->callback( static function ( array $args ) use ( $profile ): bool {
					return count( $args ) === 2
						&& $args[0] === $profile
						&& is_string( $args[1] );
				} )
			);

		( new HookRunner( $hook_container ) )->onIntegratedProfilesAfterAvatar( $profile, $html );
	}

	public function test_get_tabs_runs_hook(): void {
		$tabs = [
			[ 'id' => 'about', 'label' => 'About', 'weight' => 10 ],
		];
		$profile = [ 'user_name' => 'User1' ];
		$hook_container = $this->createMock( HookContainer::class );
		$hook_container->expects( $this->once() )
			->method( 'run' )
			->with(
				'IntegratedProfilesGetTabs',
				$this->callback( static function ( array $args ) use ( $profile ): bool {
					return count( $args ) === 2
						&& is_array( $args[0] )
						&& $args[1] === $profile;
				} )
			);

		( new HookRunner( $hook_container ) )->onIntegratedProfilesGetTabs( $tabs, $profile );
	}

	public function test_render_tab_runs_hook(): void {
		$html = '';
		$profile = [ 'user_name' => 'User1' ];
		$context = $this->createMock( IContextSource::class );
		$hook_container = $this->createMock( HookContainer::class );
		$hook_container->expects( $this->once() )
			->method( 'run' )
			->with(
				'IntegratedProfilesRenderTab',
				$this->callback( static function ( array $args ) use ( $profile, $context ): bool {
					return count( $args ) === 4
						&& $args[0] === 'collections'
						&& is_string( $args[1] )
						&& $args[2] === $profile
						&& $args[3] === $context;
				} )
			);

		( new HookRunner( $hook_container ) )->onIntegratedProfilesRenderTab(
			'collections',
			$html,
			$profile,
			$context
		);
	}

}
