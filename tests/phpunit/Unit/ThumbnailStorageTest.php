<?php
declare( strict_types=1 );

namespace MediaWiki\Extension\IntegratedProfiles\Tests\Unit;

use Closure;
use MediaWiki\Extension\IntegratedProfiles\ThumbnailSizes;
use MediaWiki\Extension\IntegratedProfiles\ThumbnailStorage;
use MediaWikiUnitTestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Wikimedia\FileBackend\FSFileBackend;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @group IntegratedProfiles
 * @covers \MediaWiki\Extension\IntegratedProfiles\ThumbnailStorage
 */
class ThumbnailStorageTest extends MediaWikiUnitTestCase {

	private const CONTAINER = 'ipavatars-thumbs';
	private const VERSION = '0123456789';

	private string $root;
	private string $src;

	protected function setUp(): void {
		parent::setUp();

		$this->root = sys_get_temp_dir() . '/ip-thumbs-' . bin2hex( random_bytes( 6 ) );
		mkdir( $this->root );
		$this->src = $this->root . '/src.webp';
		file_put_contents( $this->src, 'RIFF....WEBP' );
	}

	protected function tearDown(): void {
		$this->remove_tree( $this->root );
		parent::tearDown();
	}

	public function test_version_for_is_stable_and_valid(): void {
		$a = ThumbnailStorage::version_for( 'png', 1234, '20261029120000' );

		$this->assertSame( $a, ThumbnailStorage::version_for( 'png', 1234, '20261029120000' ) );
		$this->assertTrue( ThumbnailStorage::is_valid_version( $a ) );
		$this->assertNotSame( $a, ThumbnailStorage::version_for( 'png', 1235, '20261029120000' ) );
		$this->assertNotSame( $a, ThumbnailStorage::version_for( 'webp', 1234, '20261029120000' ) );
	}

	public function test_version_for_empty_without_usable_stat(): void {
		$this->assertSame( '', ThumbnailStorage::version_for( 'png', 0, '20261029120000' ) );
		$this->assertSame( '', ThumbnailStorage::version_for( 'png', 10, '' ) );
	}

	public function test_is_valid_version_rejects_path_tricks(): void {
		$this->assertFalse( ThumbnailStorage::is_valid_version( '' ) );
		$this->assertFalse( ThumbnailStorage::is_valid_version( '../../etc' ) );
		$this->assertFalse( ThumbnailStorage::is_valid_version( '012345678z' ) );
		$this->assertFalse( ThumbnailStorage::is_valid_version( '01234567890' ) );
	}

	public function test_store_exists_and_layout(): void {
		$thumbs = $this->make_storage();

		$this->assertTrue( $thumbs->store( 7, self::VERSION, 256, $this->src )->isOK() );
		$this->assertTrue( $thumbs->exists( 7, self::VERSION, 256 ) );
		$this->assertFalse( $thumbs->exists( 7, self::VERSION, 128 ) );
		$this->assertFileExists( $this->root . '/' . self::CONTAINER . '/7/' . self::VERSION . '/256.webp' );
	}

	public function test_store_rejects_unknown_size_and_bad_version(): void {
		$thumbs = $this->make_storage();

		$this->assertFalse( $thumbs->store( 7, self::VERSION, 100, $this->src )->isOK() );
		$this->assertFalse( $thumbs->store( 7, 'nope', 256, $this->src )->isOK() );
		$this->assertFalse( $thumbs->store( 0, self::VERSION, 256, $this->src )->isOK() );
		$this->assertDirectoryDoesNotExist( $this->root . '/' . self::CONTAINER . '/7' );
	}

	public function test_delete_owner_removes_all_versions_only_for_that_owner(): void {
		$thumbs = $this->make_storage();
		$thumbs->store( 7, self::VERSION, 64, $this->src );
		$thumbs->store( 7, 'abcdefabcd', 256, $this->src );
		$thumbs->store( 8, self::VERSION, 64, $this->src );

		$this->assertTrue( $thumbs->delete_owner( 7 )->isOK() );

		$this->assertFalse( $thumbs->exists( 7, self::VERSION, 64 ) );
		$this->assertFalse( $thumbs->exists( 7, 'abcdefabcd', 256 ) );
		$this->assertTrue( $thumbs->exists( 8, self::VERSION, 64 ) );
		$this->assertDirectoryDoesNotExist( $this->root . '/' . self::CONTAINER . '/7' );
	}

	public function test_delete_superseded_keeps_only_the_current_one_after_retention(): void {
		$thumbs = $this->make_storage( retention: 100 );
		$thumbs->store( 7, self::VERSION, 64, $this->src );
		$thumbs->store( 7, self::VERSION, 256, $this->src );
		$thumbs->store( 7, 'abcdefabcd', 256, $this->src );

		$this->assertTrue( $thumbs->delete_superseded( 7, self::VERSION, '20261029120000', $this->unix( '20261029120000' ) + 101 )->isOK() );

		$this->assertTrue( $thumbs->exists( 7, self::VERSION, 64 ) );
		$this->assertTrue( $thumbs->exists( 7, self::VERSION, 256 ) );
		$this->assertFalse( $thumbs->exists( 7, 'abcdefabcd', 256 ) );
		$this->assertDirectoryDoesNotExist( $this->root . '/' . self::CONTAINER . '/7/abcdefabcd' );
	}

	public function test_delete_superseded_keeps_older_versions_within_retention(): void {
		$thumbs = $this->make_storage( retention: 100 );
		$thumbs->store( 7, self::VERSION, 256, $this->src );
		$thumbs->store( 7, 'abcdefabcd', 256, $this->src );

		$thumbs->delete_superseded( 7, self::VERSION, '20261029120000', $this->unix( '20261029120000' ) + 100 );
		$this->assertTrue( $thumbs->exists( 7, 'abcdefabcd', 256 ), 'pages cached before the replacement may still use it' );

		$thumbs->delete_superseded( 7, self::VERSION, '', PHP_INT_MAX );
		$thumbs->delete_superseded( 7, 'nope', '20261029120000', PHP_INT_MAX );
		$this->assertTrue( $thumbs->exists( 7, 'abcdefabcd', 256 ), 'an unusable stat or version must never delete anything' );
		$this->assertTrue( $thumbs->exists( 7, self::VERSION, 256 ) );
	}

	public function test_deleted_thumbnails_are_purged(): void {
		$purged = [];
		$thumbs = $this->make_storage( 'https://cdn.example/ip', retention: 0, purge: static function ( array $urls ) use ( &$purged ): void {
			$purged[] = $urls;
		} );
		$thumbs->store( 7, self::VERSION, 256, $this->src );
		$thumbs->store( 7, 'abcdefabcd', 64, $this->src );
		$thumbs->store( 7, 'abcdefabcd', 256, $this->src );

		$thumbs->delete_superseded( 7, self::VERSION, '20261029120000' );
		$thumbs->delete_owner( 7 );
		$thumbs->delete_owner( 7 );

		$prefix = 'https://cdn.example/ip/' . self::CONTAINER . '/7/';
		$this->assertCount( 2, $purged, 'nothing deleted, nothing purged' );
		$this->assertEqualsCanonicalizing( [ $prefix . 'abcdefabcd/64.webp', $prefix . 'abcdefabcd/256.webp' ], $purged[0] );
		$this->assertSame( [ $prefix . self::VERSION . '/256.webp' ], $purged[1] );
	}

	public function test_box_is_square_for_avatars_and_width_only_for_banners(): void {
		$this->assertSame( [ 256, 256 ], $this->make_storage()->box( 256 ) );

		$banner = new ThumbnailStorage( static fn () => null, 'ipbanners-thumbs', ThumbnailSizes::BANNER, '', '/images/ipbanners-thumbs', false );
		$this->assertSame( [ 1280, 16384 ], $banner->box( 1280 ) );
	}

	public function test_set_exists_needs_the_largest_size(): void {
		$thumbs = $this->make_storage();
		$thumbs->store( 7, self::VERSION, 32, $this->src );
		$thumbs->store( 7, self::VERSION, 256, $this->src );

		$this->assertFalse( $thumbs->set_exists( 7, self::VERSION ), 'a partial set must keep serving the original' );

		$thumbs->store( 7, self::VERSION, 512, $this->src );
		$this->assertTrue( $thumbs->set_exists( 7, self::VERSION ) );
		$this->assertFalse( $thumbs->set_exists( 7, 'abcdefabcd' ) );
		$this->assertFalse( $thumbs->set_exists( 7, '' ) );
	}

	public function test_set_exists_is_false_for_unconfigured_container(): void {
		$this->assertFalse( $this->make_storage( '', false )->set_exists( 7, self::VERSION ) );
	}

	public function test_public_urls_cover_every_size_in_order(): void {
		$urls = $this->make_storage()->public_urls( 7, self::VERSION );

		$this->assertSame( ThumbnailSizes::AVATAR, array_keys( $urls ) );
		$this->assertSame( '/images/ipavatars-thumbs/7/' . self::VERSION . '/64.webp', $urls[64] );
	}

	public function test_public_urls_empty_for_invalid_input(): void {
		$thumbs = $this->make_storage();

		$this->assertSame( [], $thumbs->public_urls( 7, '' ) );
		$this->assertSame( [], $thumbs->public_urls( 7, '../../etc' ) );
		$this->assertSame( [], $thumbs->public_urls( 0, self::VERSION ) );
	}

	public function test_delete_owner_without_thumbs_is_ok(): void {
		$this->assertTrue( $this->make_storage()->delete_owner( 42 )->isOK() );
	}

	public function test_unconfigured_container_skips_cleanup_but_refuses_store(): void {
		$thumbs = $this->make_storage( '', false );

		$this->assertFalse( $thumbs->is_configured() );
		$this->assertTrue( $thumbs->delete_owner( 7 )->isOK() );
		$this->assertFalse( $thumbs->store( 7, self::VERSION, 256, $this->src )->isOK() );
	}

	public function test_configured_container_before_first_write(): void {
		$this->assertTrue( $this->make_storage()->is_configured() );
	}

	public function test_public_url_uses_default_base_without_cache_bust(): void {
		$this->assertSame(
			'/images/ipavatars-thumbs/7/' . self::VERSION . '/256.webp',
			$this->make_storage()->public_url( 7, self::VERSION, 256 )
		);
	}

	public function test_public_url_honors_override(): void {
		$this->assertSame(
			'https://cdn.example/ip/ipavatars-thumbs/7/' . self::VERSION . '/128.webp',
			$this->make_storage( 'https://cdn.example/ip/' )->public_url( 7, self::VERSION, 128 )
		);
	}

	private function unix( string $ts ): int {
		return (int)ConvertibleTimestamp::convert( TS_UNIX, $ts );
	}

	private function make_storage( string $override = '', bool $with_container = true, int $retention = ThumbnailStorage::DEFAULT_RETENTION, ?Closure $purge = null ): ThumbnailStorage {
		$backend = new FSFileBackend( [
			'name' => 'ip-thumbs-test',
			'domainId' => 'integratedprofiles',
			// < 1.46
			'lockManager' => class_exists( 'Wikimedia\LockManager\NullLockManager' ) ?
				new \Wikimedia\LockManager\NullLockManager( [] ) :
				new \NullLockManager( [] ),
			'containerPaths' => $with_container ?
				[ self::CONTAINER => $this->root . '/' . self::CONTAINER ] :
				[ 'ipavatars' => $this->root . '/ipavatars' ],
		] );

		return new ThumbnailStorage( static fn () => $backend, self::CONTAINER, ThumbnailSizes::AVATAR, $override, '/images/' . self::CONTAINER, true, $purge, $retention );
	}

	private function remove_tree( string $dir ): void {
		if ( !is_dir( $dir ) ) {
			return;
		}

		$items = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $dir, RecursiveDirectoryIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ( $items as $item ) {
			$item->isDir() ? rmdir( $item->getPathname() ) : unlink( $item->getPathname() );
		}
		rmdir( $dir );
	}

}
