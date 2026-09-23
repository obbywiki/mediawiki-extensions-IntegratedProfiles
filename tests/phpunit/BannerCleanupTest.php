<?php

namespace MediaWiki\Extension\IntegratedProfiles\Tests;

use MediaWiki\Extension\IntegratedProfiles\BannerService;
use MediaWiki\Extension\IntegratedProfiles\ProfileFields;
use MediaWiki\Extension\IntegratedProfiles\ProfileService;
use MediaWiki\User\UserIdentity;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class BannerCleanupTest extends TestCase {

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
