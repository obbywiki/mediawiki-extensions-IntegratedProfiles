<?php
declare( strict_types=1 );

namespace MediaWiki\Extension\IntegratedProfiles\Tests\Unit;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\IntegratedProfiles\AvatarStorage;
use MediaWiki\Extension\IntegratedProfiles\BannerStorage;
use MediaWikiUnitTestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Replacing keeps the replaced version's thumbnails for pages cached elsewhere, deleting removes and purges everything.
 *
 * @group IntegratedProfiles
 * @covers \MediaWiki\Extension\IntegratedProfiles\AvatarStorage
 * @covers \MediaWiki\Extension\IntegratedProfiles\BannerStorage
 * @covers \MediaWiki\Extension\IntegratedProfiles\ThumbnailStorage
 */
class MediaCleanupTest extends MediaWikiUnitTestCase {

	private const OLD_VERSION = 'abcdefabcd';

	private string $root;
	private string $upload;
	/** @var list<list<string>> */
	private array $purged = [];

	protected function setUp(): void {
		parent::setUp();

		$this->root = sys_get_temp_dir() . '/ip-cleanup-' . bin2hex( random_bytes( 6 ) );
		mkdir( $this->root . '/ipavatars', 0777, true );
		mkdir( $this->root . '/ipbanners', 0777, true );
		$this->upload = $this->root . '/upload.bin';
		file_put_contents( $this->upload, 'new bytes' );
	}

	protected function tearDown(): void {
		$items = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $this->root, RecursiveDirectoryIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ( $items as $item ) {
			$item->isDir() ? rmdir( $item->getPathname() ) : unlink( $item->getPathname() );
		}
		rmdir( $this->root );

		parent::tearDown();
	}

	public static function provide_storages(): array {
		return [
			'avatar' => [ AvatarStorage::class, 'ipavatars', 'avatar' ],
			'banner' => [ BannerStorage::class, 'ipbanners', 'banner' ],
		];
	}

	/**
	 * @dataProvider provide_storages
	 */
	public function test_replacing_keeps_the_replaced_version( string $class, string $container, string $prefix ): void {
		$storage = $this->storage( $class );
		$this->original( $container, $prefix . '_7.png', time() - 3 * 86400 );
		$replaced = $storage->find_extension_with_mtime( 7 )['version'];
		$size = max( $storage->thumbs()->sizes() );
		$storage->thumbs()->store( 7, $replaced, $size, $this->upload );
		$storage->thumbs()->store( 7, self::OLD_VERSION, $size, $this->upload );

		$this->assertTrue( $storage->store( 7, 'png', $this->upload )->isOK() );

		$this->assertTrue( $storage->thumbs()->exists( 7, $replaced, $size ), 'pages cached on other wikis still use it' );
		$this->assertFalse( $storage->thumbs()->exists( 7, self::OLD_VERSION, $size ), 'superseded long before the replacement' );
		$this->assertSame( [ [ '/images/' . $container . '-thumbs/7/' . self::OLD_VERSION . '/' . $size . '.webp' ] ], $this->purged );

		// replaced again right away: the version replaced above stays too, and the other format's original goes
		$this->purged = [];
		$png_mtime = $storage->find_extension_with_mtime( 7 )['mtime'];
		$this->assertTrue( $storage->store( 7, 'jpg', $this->upload )->isOK() );

		$this->assertTrue( $storage->thumbs()->exists( 7, $replaced, $size ) );
		$this->assertFalse( $storage->exists( 7, 'png' ) );
		$this->assertSame( [ [ '/images/' . $container . '/' . $prefix . '_7.png?r=' . $png_mtime ] ], $this->purged );
	}

	/**
	 * @dataProvider provide_storages
	 */
	public function test_deleting_removes_and_purges_every_file( string $class, string $container, string $prefix ): void {
		$storage = $this->storage( $class );
		$this->original( $container, $prefix . '_7.webp' );
		$found = $storage->find_extension_with_mtime( 7 );
		$size = min( $storage->thumbs()->sizes() );
		$storage->thumbs()->store( 7, $found['version'], $size, $this->upload );
		$storage->thumbs()->store( 7, self::OLD_VERSION, $size, $this->upload );

		$this->assertTrue( $storage->delete_all( 7 )->isOK() );

		$this->assertNull( $storage->find_extension_with_mtime( 7 ) );
		$this->assertDirectoryDoesNotExist( $this->root . '/' . $container . '-thumbs/7' );
		$this->assertSame( [ '/images/' . $container . '/' . $prefix . '_7.webp?r=' . $found['mtime'] ], $this->purged[0] );
		$this->assertEqualsCanonicalizing( [
			'/images/' . $container . '-thumbs/7/' . $found['version'] . '/' . $size . '.webp',
			'/images/' . $container . '-thumbs/7/' . self::OLD_VERSION . '/' . $size . '.webp',
		], $this->purged[1] );
		$this->assertCount( 2, $this->purged );

		$this->purged = [];
		$this->assertTrue( $storage->delete_all( 7 )->isOK() );
		$this->assertSame( [], $this->purged, 'nothing left to purge' );
	}

	public function test_without_a_purge_callback_nothing_is_purged(): void {
		$storage = new AvatarStorage( $this->options( AvatarStorage::CONSTRUCTOR_OPTIONS ) );
		$this->original( 'ipavatars', 'avatar_7.png' );

		$this->assertTrue( $storage->delete_all( 7 )->isOK() );
		$this->assertFalse( $storage->exists( 7, 'png' ) );
	}

	/**
	 * @param class-string<AvatarStorage|BannerStorage> $class
	 */
	private function storage( string $class ): AvatarStorage|BannerStorage {
		return new $class( $this->options( $class::CONSTRUCTOR_OPTIONS ), function ( array $urls ): void {
			$this->purged[] = $urls;
		} );
	}

	private function options( array $keys ): ServiceOptions {
		return new ServiceOptions( $keys, [
			'IntegratedProfilesBackend' => '',
			'IntegratedProfilesUploadBaseUrl' => '',
			'UploadDirectory' => $this->root,
			'UploadPath' => '/images',
			'UploadBaseUrl' => '',
		] );
	}

	private function original( string $container, string $name, ?int $mtime = null ): void {
		$path = $this->root . '/' . $container . '/' . $name;
		file_put_contents( $path, 'old bytes' );
		if ( $mtime !== null ) {
			touch( $path, $mtime );
			clearstatcache();
		}
	}

}
