<?php
declare( strict_types=1 );

namespace MediaWiki\Extension\IntegratedProfiles\Tests\Integration;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\IntegratedProfiles\AvatarStorage;
use MediaWiki\Extension\IntegratedProfiles\BannerStorage;
use MediaWiki\Extension\IntegratedProfiles\ImageLimits;
use MediaWiki\Extension\IntegratedProfiles\ProfileImageStorage;
use MediaWiki\Extension\IntegratedProfiles\ThumbnailSizes;
use MediaWiki\Extension\IntegratedProfiles\ThumbnailStorage;
use MediaWiki\Status\Status;
use MediaWikiIntegrationTestCase;
use Wikimedia\FileBackend\FileBackend;
use Wikimedia\LockManager\NullLockManager;

/**
 * @group IntegratedProfiles
 * @covers \MediaWiki\Extension\IntegratedProfiles\ProfileImageStorage
 * @covers \MediaWiki\Extension\IntegratedProfiles\AvatarStorage
 * @covers \MediaWiki\Extension\IntegratedProfiles\BannerStorage
 */
class ProfileImageStorageTest extends MediaWikiIntegrationTestCase {

	public static function provide_storage_classes(): array {
		return [
			'avatars' => [ AvatarStorage::class, 'ipavatars', 'avatar_' ],
			'banners' => [ BannerStorage::class, 'ipbanners', 'banner_' ],
		];
	}

	/** @dataProvider provide_storage_classes */
	public function test_storage_paths_and_backend_configuration( string $class, string $container, string $prefix ): void {
		$storage = $this->new_storage( $class );
		$this->assertSame( $prefix . '7.png', $storage->file_name( 7, 'png' ) );
		$this->assertStringEndsWith( '/' . $container . '/' . $prefix . '7.png', $storage->storage_path( 7, 'png' ) );
		$this->assertSame( $storage->get_backend(), $storage->get_backend() );
		$this->assertFalse( $storage->exists( 7, 'png' ) );
		$this->assertNull( $storage->find_extension( 7 ) );
		$this->assertNull( $storage->find_extension_with_mtime( 7 ) );
	}

	/** @dataProvider provide_storage_classes */
	public function test_replacement_and_deletion_preserve_other_owners( string $class, string $container, string $prefix ): void {
		$storage = $this->new_storage( $class );
		$source = $this->getNewTempDirectory() . '/upload';
		file_put_contents( $source, 'original image bytes' );
		$this->assertStatusGood( $storage->store( 7, 'png', $source ) );
		$this->assertStatusGood( $storage->store( 8, 'png', $source ) );
		$this->assertTrue( $storage->exists( 7, 'png' ) );
		$this->assertSame( 'png', $storage->find_extension( 7 ) );
		$found = $storage->find_extension_with_mtime( 7 );
		$this->assertSame( 'png', $found['ext'] );
		$this->assertTrue( ThumbnailStorage::is_valid_version( $found['version'] ) );
		$this->assertSame( [ 7, 8 ], $storage->owner_ids() );
		$this->assertNotSame( '', $found['mtime'] );
		$this->assertSame(
			'/images/' . $container . '/' . $prefix . '7.png?r=' . rawurlencode( $found['mtime'] ),
			$storage->public_url( 7, 'png' )
		);

		file_put_contents( $source, 'replacement image bytes' );
		$this->assertStatusGood( $storage->store( 7, 'webp', $source ) );
		$this->assertFalse( $storage->exists( 7, 'png' ) );
		$this->assertTrue( $storage->exists( 7, 'webp' ) );
		$this->assertSame( 'webp', $storage->find_extension( 7 ) );
		$this->assertStatusGood( $storage->delete_all( 7 ) );
		$this->assertNull( $storage->find_extension( 7 ) );
		$this->assertTrue( $storage->exists( 8, 'png' ) );
		$this->assertSame( [ 8 ], $storage->owner_ids() );
		$this->assertStatusGood( $storage->delete_all( 7 ) );
	}

	/** @dataProvider provide_storage_classes */
	public function test_failed_replacement_does_not_delete_previous_files( string $class ): void {
		$directory = $this->getNewTempDirectory();
		$backend = $this->getMockBuilder( FileBackend::class )
			->setConstructorArgs( [ [
				'name' => 'test-backend',
				'domainId' => 'test',
				'lockManager' => new NullLockManager( [] ),
				'containerPaths' => [ $class::CONTAINER => $directory ],
				'statusWrapper' => [ Status::class, 'wrap' ],
			] ] )
			->onlyMethods( [ 'doQuickOperationsInternal', 'doPrepare' ] )
			->getMockForAbstractClass();
		$backend->method( 'doPrepare' )->willReturn( Status::newGood() );
		$backend->expects( $this->once() )->method( 'doQuickOperationsInternal' )
			->willReturn( Status::newFatal( 'backend-failure' ) );
		$storage = $this->getMockBuilder( $class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'get_backend', 'delete_other_extensions', 'find_extension_with_mtime' ] )
			->getMock();
		$storage->method( 'get_backend' )->willReturn( $backend );
		$storage->expects( $this->never() )->method( 'delete_other_extensions' );

		$this->assertFalse( $storage->store( 7, 'webp', '/tmp/upload' )->isOK() );
	}

	/** @dataProvider provide_storage_classes */
	public function test_public_url_override_and_cache_busting( string $class, string $container, string $prefix ): void {
		$storage = $this->new_storage( $class, [
			'IntegratedProfilesUploadBaseUrl' => ' https://cdn.example.test/profiles/ ',
		] );
		$url = 'https://cdn.example.test/profiles/' . $container . '/' . $prefix . '7.png';
		$this->assertSame( $url, $storage->public_url( 7, 'png', '' ) );
		$this->assertSame( $url . '?r=a%20b', $storage->public_url( 7, 'png', 'a b' ) );
	}

	/** @dataProvider provide_storage_classes */
	public function test_upload_base_url_fallback( string $class, string $container, string $prefix ): void {
		$storage = $this->new_storage( $class, [
			'UploadBaseUrl' => 'https://uploads.example.test/',
			'UploadPath' => '/images/',
		] );
		$this->assertSame(
			'https://uploads.example.test/images/' . $container . '/' . $prefix . '7.png',
			$storage->public_url( 7, 'png', '' )
		);
	}

	/** @dataProvider provide_storage_classes */
	public function test_avatar_and_banner_storage_are_isolated( string $class ): void {
		$directory = $this->getNewTempDirectory();
		$storage = $this->new_storage( $class, [ 'UploadDirectory' => $directory ] );
		$other_class = $class === AvatarStorage::class ? BannerStorage::class : AvatarStorage::class;
		$other = $this->new_storage( $other_class, [ 'UploadDirectory' => $directory ] );
		$source = $directory . '/upload';
		file_put_contents( $source, 'image bytes' );
		$this->assertStatusGood( $storage->store( 7, 'png', $source ) );
		$this->assertStatusGood( $other->store( 7, 'png', $source ) );
		$this->assertStatusGood( $storage->delete_all( 7 ) );
		$this->assertTrue( $other->exists( 7, 'png' ) );
	}

	/** @dataProvider provide_storage_classes */
	public function test_thumbnail_policy_and_container( string $class, string $container ): void {
		$storage = $this->new_storage( $class );
		$thumbs = $storage->thumbs();
		$is_avatar = $class === AvatarStorage::class;
		$this->assertSame( $thumbs, $storage->thumbs() );
		$this->assertSame( $is_avatar ? ThumbnailSizes::AVATAR : ThumbnailSizes::BANNER, $thumbs->sizes() );
		$size = $thumbs->sizes()[0];
		$this->assertSame( [ $size, $is_avatar ? $size : ImageLimits::MAX_EDGE ], $thumbs->box( $size ) );
		$this->assertSame(
			'/images/' . $container . '-thumbs/7/0123456789/' . $size . '.webp',
			$thumbs->public_url( 7, '0123456789', $size )
		);
	}

	/** @dataProvider provide_storage_classes */
	public function test_deletion_purges_original_url_with_mtime( string $class ): void {
		$purged = [];
		$storage = $this->new_storage( $class, [], static function ( array $urls ) use ( &$purged ): void {
			array_push( $purged, ...$urls );
		} );
		$source = $this->getNewTempDirectory() . '/upload';
		file_put_contents( $source, 'image bytes' );
		$this->assertStatusGood( $storage->store( 7, 'png', $source ) );
		$original_url = $storage->public_url( 7, 'png' );
		$this->assertStatusGood( $storage->delete_all( 7 ) );
		$this->assertSame( [ $original_url ], $purged );
	}

	private function new_storage( string $class, array $overrides = [], ?\Closure $purge_urls = null ): ProfileImageStorage {
		return new $class( new ServiceOptions( ProfileImageStorage::CONSTRUCTOR_OPTIONS, $overrides + [
			'IntegratedProfilesBackend' => '',
			'IntegratedProfilesUploadBaseUrl' => '',
			'UploadDirectory' => $this->getNewTempDirectory(),
			'UploadPath' => '/images',
			'UploadBaseUrl' => '',
		] ), $purge_urls );
	}

}
