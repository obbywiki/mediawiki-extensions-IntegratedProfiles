<?php
declare( strict_types=1 );

namespace MediaWiki\Extension\IntegratedProfiles\Tests\Unit;

use MediaWiki\Extension\IntegratedProfiles\Hooks;
use MediaWiki\Extension\IntegratedProfiles\ProfileFields;
use MediaWiki\Extension\IntegratedProfiles\ProfilePagePurger;
use MediaWiki\Extension\IntegratedProfiles\ProfileService;
use MediaWiki\User\UserIdentity;
use MediaWikiUnitTestCase;
use ReflectionClass;

/**
 * @group IntegratedProfiles
 * @covers \MediaWiki\Extension\IntegratedProfiles\Hooks
 */
class HooksSaveUserOptionsTest extends MediaWikiUnitTestCase {

	public function test_visibility_only_save_purges_and_skips_banner_cleanup(): void {
		$user = $this->identity();
		$modified = [ ProfileFields::KEY_VISIBILITY => ProfileFields::VISIBILITY_PRIVATE ];
		$original = [ ProfileFields::KEY_VISIBILITY => ProfileFields::VISIBILITY_PUBLIC ];

		$profile = $this->createMock( ProfileService::class );
		$profile->expects( $this->never() )->method( 'maybe_delete_unused_banner' );

		$purger = $this->createMock( ProfilePagePurger::class );
		$purger->expects( $this->once() )->method( 'maybe_purge_on_visibility_change' )->with( $user, $modified, $original );

		$this->hooks( $profile, $purger )->onSaveUserOptions( $user, $modified, $original );
	}

	public function test_banner_and_visibility_save_runs_both(): void {
		$user = $this->identity();
		$modified = [
			ProfileFields::KEY_BANNER => 'ocean',
			ProfileFields::KEY_VISIBILITY => ProfileFields::VISIBILITY_USERS,
		];
		$original = [
			ProfileFields::KEY_BANNER => ProfileFields::BANNER_CUSTOM,
			ProfileFields::KEY_VISIBILITY => ProfileFields::VISIBILITY_PUBLIC,
		];

		$profile = $this->createMock( ProfileService::class );
		$profile->expects( $this->once() )->method( 'maybe_delete_unused_banner' )->with( $user, ProfileFields::BANNER_CUSTOM, 'ocean' );

		$purger = $this->createMock( ProfilePagePurger::class );
		$purger->expects( $this->once() )->method( 'maybe_purge_on_visibility_change' )->with( $user, $modified, $original );

		$this->hooks( $profile, $purger )->onSaveUserOptions( $user, $modified, $original );
	}

	private function hooks( ProfileService $profile, ProfilePagePurger $purger ): Hooks {
		$reflection = new ReflectionClass( Hooks::class );
		$hooks = $reflection->newInstanceWithoutConstructor();

		$profile_prop = $reflection->getProperty( 'profile_service' );
		$profile_prop->setValue( $hooks, $profile );

		$purger_prop = $reflection->getProperty( 'profile_page_purger' );
		$purger_prop->setValue( $hooks, $purger );

		return $hooks;
	}

	private function identity(): UserIdentity {
		$user = $this->createMock( UserIdentity::class );
		$user->method( 'getId' )->willReturn( 7 );
		$user->method( 'getName' )->willReturn( 'User1' );
		$user->method( 'isRegistered' )->willReturn( true );

		return $user;
	}

}
