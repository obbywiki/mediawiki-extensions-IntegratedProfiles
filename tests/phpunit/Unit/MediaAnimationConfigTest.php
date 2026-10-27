<?php
declare( strict_types=1 );

namespace MediaWiki\Extension\IntegratedProfiles\Tests\Unit;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\IntegratedProfiles\AvatarService;
use MediaWiki\Extension\IntegratedProfiles\AvatarStorage;
use MediaWiki\Extension\IntegratedProfiles\BannerService;
use MediaWiki\Extension\IntegratedProfiles\BannerStorage;
use MediaWiki\Extension\IntegratedProfiles\ProfileSubjectIds;
use MediaWiki\Status\Status;
use MediaWikiUnitTestCase;
use Wikimedia\ObjectCache\HashBagOStuff;

/**
 * @group IntegratedProfiles
 * @covers \MediaWiki\Extension\IntegratedProfiles\AvatarService
 * @covers \MediaWiki\Extension\IntegratedProfiles\BannerService
 * @covers \MediaWiki\Extension\IntegratedProfiles\AvatarValidator
 * @covers \MediaWiki\Extension\IntegratedProfiles\BannerValidator
 * @covers \MediaWiki\Extension\IntegratedProfiles\ImageAnimation
 */
class MediaAnimationConfigTest extends MediaWikiUnitTestCase {

	/** @dataProvider provide_animation_config */
	public function test_storage_enforces_its_own_animation_switch( string $kind, bool $enabled, bool $allowed ): void {
		$is_avatar = $kind === 'avatar';
		$config = [
			'IntegratedProfilesAvatarMaxBytes' => 2097152,
			'IntegratedProfilesBannerMaxBytes' => 4194304,
			'IntegratedProfilesEnableAnimatedAvatars' => $is_avatar ? $enabled : !$enabled,
			'IntegratedProfilesEnableAnimatedBanners' => $is_avatar ? !$enabled : $enabled,
			'ExtensionAssetsPath' => '/extensions',
		];
		$storage = $this->createMock( $is_avatar ? AvatarStorage::class : BannerStorage::class );
		$storage->expects( $enabled && $allowed ? $this->once() : $this->never() )
			->method( 'store' )->with( 7, 'gif', $this->anything() )->willReturn( Status::newGood() );
		$service_class = $is_avatar ? AvatarService::class : BannerService::class;
		$service = new $service_class(
			new ServiceOptions( $service_class::CONSTRUCTOR_OPTIONS, $config ),
			$storage, new HashBagOStuff(), $this->createMock( ProfileSubjectIds::class )
		);
		$path = tempnam( sys_get_temp_dir(), 'ip-animation-' );
		$gif = 'GIF89a' . "\x01\x00\x01\x00\x80\x00\x00\x00\x00\x00\xFF\xFF\xFF"
			. "\x21\xFF\x0BNETSCAPE2.0\x03\x01\x00\x00\x00"
			. "\x2C\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x02\x4C\x01\x00\x3B";
		file_put_contents( $path, $gif );
		try {
			$status = $service->upload( 7, $path, strlen( $gif ), $allowed );
			$this->assertSame( $enabled && $allowed, $status->isOK() );
			if ( !$status->isOK() ) {
				$this->assertTrue( $status->hasMessage( "integratedprofiles-error-$kind-animated" ) );
			}
		} finally {
			unlink( $path );
		}
	}

	public static function provide_animation_config(): iterable {
		foreach ( [ 'avatar', 'banner' ] as $kind ) {
			foreach ( [ false, true ] as $enabled ) {
				foreach ( [ false, true ] as $allowed ) {
					yield [ $kind, $enabled, $allowed ];
				}
			}
		}
	}
}
