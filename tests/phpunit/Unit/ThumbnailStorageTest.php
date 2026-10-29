<?php
declare( strict_types=1 );

namespace MediaWiki\Extension\IntegratedProfiles\Tests\Unit;

use MediaWiki\Extension\IntegratedProfiles\ThumbnailSizes;
use MediaWiki\Extension\IntegratedProfiles\ThumbnailStorage;
use MediaWikiUnitTestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Wikimedia\FileBackend\FSFileBackend;

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

	private function make_storage( string $override = '', bool $with_container = true ): ThumbnailStorage {
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

		return new ThumbnailStorage( static fn () => $backend, self::CONTAINER, ThumbnailSizes::AVATAR, $override, '/images/' . self::CONTAINER );
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
