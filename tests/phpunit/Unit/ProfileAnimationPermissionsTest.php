<?php
declare( strict_types=1 );

namespace MediaWiki\Extension\IntegratedProfiles\Tests\Unit;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\IntegratedProfiles\ProfileService;
use MediaWiki\User\User;
use MediaWikiUnitTestCase;
use ReflectionClass;

/**
 * @group IntegratedProfiles
 * @covers \MediaWiki\Extension\IntegratedProfiles\ProfileService
 */
class ProfileAnimationPermissionsTest extends MediaWikiUnitTestCase {

	/** @dataProvider provide_animation_permissions */
	public function test_animation_permissions_are_independent(
		bool $avatar_right, bool $banner_right, bool $avatars_enabled, bool $banners_enabled
	): void {
		$config = [
			'IntegratedProfilesEnableAnimatedAvatars' => $avatars_enabled,
			'IntegratedProfilesEnableAnimatedBanners' => $banners_enabled,
		];
		$reflection = new ReflectionClass( ProfileService::class );
		$service = $reflection->newInstanceWithoutConstructor();
		$reflection->getProperty( 'options' )->setValue( $service,
			new ServiceOptions( array_keys( $config ), $config ) );
		$actor = $this->createMock( User::class );
		$actor->method( 'isAllowed' )->willReturnCallback(
			static fn ( $right ) => match ( $right ) {
				'integratedprofiles-animated-avatar' => $avatar_right,
				'integratedprofiles-animated-banner' => $banner_right,
				default => false,
			}
		);

		$this->assertSame( $avatar_right && $avatars_enabled, $service->can_use_animated_avatar( $actor ) );
		$this->assertSame( $banner_right && $banners_enabled, $service->can_use_animated_banner( $actor ) );
	}

	public static function provide_animation_permissions(): iterable {
		foreach ( [ false, true ] as $avatar_right ) {
			foreach ( [ false, true ] as $banner_right ) {
				foreach ( [ false, true ] as $avatars_enabled ) {
					foreach ( [ false, true ] as $banners_enabled ) {
						yield [ $avatar_right, $banner_right, $avatars_enabled, $banners_enabled ];
					}
				}
			}
		}
	}
}
