<?php
declare( strict_types=1 );

namespace MediaWiki\Extension\IntegratedProfiles\Tests\Unit;

use MediaWiki\Extension\IntegratedProfiles\BannerService;
use MediaWiki\Extension\IntegratedProfiles\ProfileFields;
use MediaWiki\Extension\IntegratedProfiles\ProfileService;
use MediaWiki\Status\Status;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\Options\UserOptionsManager;
use MediaWiki\User\User;
use MediaWiki\User\UserIdentity;
use MediaWikiUnitTestCase;
use ReflectionClass;

/**
 * @group IntegratedProfiles
 * @covers \MediaWiki\Extension\IntegratedProfiles\ProfileService
 */
class BannerCleanupTest extends MediaWikiUnitTestCase {

	public function test_leaving_custom_deletes_banner_files(): void {
		$banner = $this->createMock( BannerService::class );
		$banner->expects( $this->once() )
			->method( 'delete_for_user' )
			->with( $this->isInstanceOf( UserIdentity::class ) );

		$service = $this->profile_service_with_banner( $banner );
		$service->maybe_delete_unused_banner( $this->identity(), ProfileFields::BANNER_CUSTOM, 'ocean' );
	}

	public function test_staying_custom_keeps_banner_files(): void {
		$banner = $this->createMock( BannerService::class );
		$banner->expects( $this->never() )->method( 'delete_for_user' );

		$service = $this->profile_service_with_banner( $banner );
		$service->maybe_delete_unused_banner(
			$this->identity(),
			ProfileFields::BANNER_CUSTOM,
			ProfileFields::BANNER_CUSTOM
		);
	}

	public function test_switching_presets_without_custom_skips_delete(): void {
		$banner = $this->createMock( BannerService::class );
		$banner->expects( $this->never() )->method( 'delete_for_user' );

		$service = $this->profile_service_with_banner( $banner );
		$service->maybe_delete_unused_banner(
			$this->identity(),
			'ocean',
			'sunset'
		);
	}

	public function test_delete_banner_resets_custom_to_accent(): void {
		$manager = $this->createMock( UserOptionsManager::class );
		$manager->expects( $this->once() )->method( 'setOption' )
			->with( $this->anything(), ProfileFields::KEY_BANNER, ProfileFields::BANNER_ACCENT, $this->anything() );
		$manager->expects( $this->once() )->method( 'saveOptions' );

		$this->assertStatusGood( $this->delete_banner_with( ProfileFields::BANNER_CUSTOM, $manager ) );
	}

	public function test_delete_banner_keeps_other_presets(): void {
		$manager = $this->createMock( UserOptionsManager::class );
		$manager->expects( $this->never() )->method( 'setOption' );
		$manager->expects( $this->never() )->method( 'saveOptions' );

		$this->assertStatusGood( $this->delete_banner_with( 'ocean', $manager ) );
	}

	public function test_failed_delete_banner_keeps_options(): void {
		$banner = $this->createMock( BannerService::class );
		$banner->method( 'delete_for_user' )->willReturn( Status::newFatal( 'backend-fail-internal', 'x' ) );
		$manager = $this->createMock( UserOptionsManager::class );
		$manager->expects( $this->never() )->method( 'setOption' );

		$this->assertStatusError( 'backend-fail-internal', $this->delete_banner_with( ProfileFields::BANNER_CUSTOM, $manager, $banner ) );
	}

	private function delete_banner_with( string $current, UserOptionsManager $manager, ?BannerService $banner = null ): Status {
		if ( $banner === null ) {
			$banner = $this->createMock( BannerService::class );
			$banner->method( 'delete_for_user' )->willReturn( Status::newGood() );
		}
		$lookup = $this->createMock( UserOptionsLookup::class );
		$lookup->method( 'getOption' )->willReturnCallback(
			static fn ( $user, string $key ) => $key === ProfileFields::KEY_BANNER ? $current : null
		);

		$service = $this->getMockBuilder( ProfileService::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'get_payload' ] )
			->getMock();
		$service->method( 'get_payload' )->willReturn( [] );
		$reflection = new ReflectionClass( ProfileService::class );
		foreach ( [ 'banner_service' => $banner, 'user_options_lookup' => $lookup, 'user_options_manager' => $manager ] as $name => $value ) {
			$reflection->getProperty( $name )->setValue( $service, $value );
		}

		return $service->delete_banner( $this->createMock( User::class ) );
	}

	private function profile_service_with_banner( BannerService $banner ): ProfileService {
		$reflection = new ReflectionClass( ProfileService::class );
		$service = $reflection->newInstanceWithoutConstructor();
		$prop = $reflection->getProperty( 'banner_service' );
		$prop->setValue( $service, $banner );

		return $service;
	}

	private function identity(): UserIdentity {
		$user = $this->createMock( UserIdentity::class );
		$user->method( 'getId' )->willReturn( 7 );
		$user->method( 'getName' )->willReturn( 'User1' );
		$user->method( 'isRegistered' )->willReturn( true );

		return $user;
	}

}
