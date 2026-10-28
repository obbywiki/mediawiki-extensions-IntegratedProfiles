<?php
declare( strict_types=1 );

namespace MediaWiki\Extension\IntegratedProfiles\Tests\Unit;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\IntegratedProfiles\AvatarStorage;
use MediaWiki\Extension\IntegratedProfiles\BannerStorage;
use MediaWikiUnitTestCase;
use Wikimedia\FileBackend\FileBackend;

/**
 * @group IntegratedProfiles
 * @covers \MediaWiki\Extension\IntegratedProfiles\AvatarStorage
 * @covers \MediaWiki\Extension\IntegratedProfiles\BannerStorage
 */
class MediaStorageUrlTest extends MediaWikiUnitTestCase {

	/** @dataProvider provide_storage_classes */
	public function test_override_with_cached_mtime_skips_backend( string $class, string $file ): void {
		$storage = $this->getMockBuilder( $class )
			->setConstructorArgs( [ $this->options( $class ) ] )
			->onlyMethods( [ 'get_backend', 'storage_path' ] )
			->getMock();
		$storage->expects( $this->never() )->method( 'get_backend' );
		$storage->expects( $this->never() )->method( 'storage_path' );

		$this->assertSame( 'https://media.example/' . $file . '?r=42', $storage->public_url( 7, 'png', '42' ) );
		$this->assertSame( 'https://media.example/' . $file, $storage->public_url( 7, 'png', '' ) );
	}

	/** @dataProvider provide_storage_classes */
	public function test_override_without_mtime_still_reads_stat( string $class, string $file ): void {
		$backend = $this->createMock( FileBackend::class );
		$backend->expects( $this->once() )->method( 'getFileStat' )
			->with( [ 'src' => 'mwstore://test/file.png' ] )
			->willReturn( [ 'mtime' => '42' ] );
		$backend->expects( $this->never() )->method( 'getFileHttpUrl' );

		$storage = $this->getMockBuilder( $class )
			->setConstructorArgs( [ $this->options( $class ) ] )
			->onlyMethods( [ 'get_backend', 'storage_path' ] )
			->getMock();
		$storage->method( 'get_backend' )->willReturn( $backend );
		$storage->method( 'storage_path' )->willReturn( 'mwstore://test/file.png' );

		$this->assertSame( 'https://media.example/' . $file . '?r=42', $storage->public_url( 7, 'png' ) );
	}

	public static function provide_storage_classes(): array {
		return [
			'avatar' => [ AvatarStorage::class, 'ipavatars/avatar_7.png' ],
			'banner' => [ BannerStorage::class, 'ipbanners/banner_7.png' ],
		];
	}

	private function options( string $class ): ServiceOptions {
		return new ServiceOptions( $class::CONSTRUCTOR_OPTIONS, [
			'IntegratedProfilesBackend' => '',
			'IntegratedProfilesUploadBaseUrl' => 'https://media.example/',
			'UploadDirectory' => '/unused',
			'UploadPath' => '/images',
			'UploadBaseUrl' => '',
		] );
	}

}
