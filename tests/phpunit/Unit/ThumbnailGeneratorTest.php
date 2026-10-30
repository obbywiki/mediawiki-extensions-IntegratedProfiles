<?php
declare( strict_types=1 );

namespace MediaWiki\Extension\IntegratedProfiles\Tests\Unit;

use LogicException;
use MediaWiki\Extension\IntegratedProfiles\AvatarStorage;
use MediaWiki\Extension\IntegratedProfiles\Thumbnailer\GdThumbnailer;
use MediaWiki\Extension\IntegratedProfiles\Thumbnailer\ImagickThumbnailer;
use MediaWiki\Extension\IntegratedProfiles\Thumbnailer\Thumbnailer;
use MediaWiki\Extension\IntegratedProfiles\Thumbnailer\VipsThumbnailer;
use MediaWiki\Extension\IntegratedProfiles\ThumbnailGenerator;
use MediaWiki\Extension\IntegratedProfiles\ThumbnailSizes;
use MediaWiki\Extension\IntegratedProfiles\ThumbnailStorage;
use MediaWikiUnitTestCase;
use Psr\Log\NullLogger;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Wikimedia\FileBackend\FileBackend;
use Wikimedia\FileBackend\FSFile\TempFSFileFactory;
use Wikimedia\FileBackend\FSFileBackend;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @group IntegratedProfiles
 * @covers \MediaWiki\Extension\IntegratedProfiles\ThumbnailGenerator
 * @covers \MediaWiki\Extension\IntegratedProfiles\Thumbnailer\VipsThumbnailer
 * @covers \MediaWiki\Extension\IntegratedProfiles\Thumbnailer\ImagickThumbnailer
 * @covers \MediaWiki\Extension\IntegratedProfiles\Thumbnailer\GdThumbnailer
 */
class ThumbnailGeneratorTest extends MediaWikiUnitTestCase {

	private const WEBP_1X1 = 'UklGRhoAAABXRUJQVlA4TA0AAAAvAAAAEAcQERGIiP4HAA=='; // 1x1 lossless WebP
	private const VERSION = '0123456789';

	private string $root;
	private FileBackend $backend;
	private string $src;

	protected function setUp(): void {
		parent::setUp();

		$this->root = sys_get_temp_dir() . '/ip-thumbgen-' . bin2hex( random_bytes( 6 ) );
		mkdir( $this->root . '/tmp', 0777, true );
		mkdir( $this->root . '/ipavatars', 0777, true );

		$this->backend = new FSFileBackend( [
			'name' => 'ip-thumbgen-test',
			'domainId' => 'integratedprofiles',
			// < 1.46
			'lockManager' => class_exists( 'Wikimedia\LockManager\NullLockManager' ) ?
				new \Wikimedia\LockManager\NullLockManager( [] ) :
				new \NullLockManager( [] ),
			'containerPaths' => [
				'ipavatars' => $this->root . '/ipavatars',
				'ipavatars-thumbs' => $this->root . '/ipavatars-thumbs',
			],
		] );

		$this->src = $this->root . '/ipavatars/avatar_7.png';
		file_put_contents( $this->src, self::png( 64, 32 ) );
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

	public function test_first_capable_thumbnailer_stores_every_size(): void {
		$used = $this->fake( 'second' );
		$generator = $this->generator( [ $this->fake( 'missing', available: false ), $used, $this->fake( 'third' ) ] );

		$this->assertTrue( $generator->generate( $this->thumbs(), $this->src, 'png', 7, self::VERSION ) );
		$this->assertSame( 1, $used->calls );
		foreach ( ThumbnailSizes::AVATAR as $size ) {
			$this->assertFileExists( $this->root . '/ipavatars-thumbs/7/' . self::VERSION . '/' . $size . '.webp' );
		}
	}

	public function test_passes_boxes_for_every_size(): void {
		$fake = $this->fake( 'only' );
		$this->generator( [ $fake ] )->generate( $this->thumbs( false ), $this->src, 'png', 7, self::VERSION );

		$this->assertSame(
			array_map( static fn ( int $size ): array => [ $size, 16384 ], ThumbnailSizes::AVATAR ),
			array_map( static fn ( array $t ): array => [ $t['max_width'], $t['max_height'] ], $fake->targets )
		);
	}

	public function test_falls_through_failure_throw_and_junk_output(): void {
		$last = $this->fake( 'last' );
		$generator = $this->generator( [
			$this->fake( 'fails', result: false ),
			$this->fake( 'throws', throws: true ),
			$this->fake( 'junk', bytes: 'not an image' ),
			$last,
		] );

		$this->assertTrue( $generator->generate( $this->thumbs(), $this->src, 'png', 7, self::VERSION ) );
		$this->assertSame( 1, $last->calls );
	}

	public function test_skips_thumbnailers_that_cannot_keep_animation(): void {
		$static_only = $this->fake( 'static', animated: false );
		$gif = $this->root . '/anim.gif';
		file_put_contents( $gif, self::animated_gif() );

		$this->assertFalse( $this->generator( [ $static_only ] )->generate( $this->thumbs(), $gif, 'gif', 7, self::VERSION ) );
		$this->assertSame( 0, $static_only->calls );
		$this->assertDirectoryDoesNotExist( $this->root . '/ipavatars-thumbs/7' );
	}

	public function test_nothing_available_returns_false_without_storing(): void {
		$generator = $this->generator( [ $this->fake( 'a', available: false ), $this->fake( 'b', result: false ) ] );

		$this->assertFalse( $generator->generate( $this->thumbs(), $this->src, 'png', 7, self::VERSION ) );
		$this->assertDirectoryDoesNotExist( $this->root . '/ipavatars-thumbs/7' );
	}

	public function test_temp_files_are_removed(): void {
		$this->generator( [ $this->fake( 'only' ) ] )->generate( $this->thumbs(), $this->src, 'png', 7, self::VERSION );

		$this->assertSame( [], glob( $this->root . '/tmp/*' ) );
	}

	public function test_generate_for_uses_current_original_and_drops_old_versions(): void {
		$thumbs = $this->thumbs();
		$thumbs->store( 7, 'abcdefabcd', 64, $this->root . '/ipavatars/avatar_7.png' );

		$this->assertTrue( $this->generator( [ $this->fake( 'only' ) ] )->generate_for( $this->storage( $thumbs ), 7 ) );
		$this->assertTrue( $thumbs->exists( 7, self::VERSION, 256 ) );
		$this->assertFalse( $thumbs->exists( 7, 'abcdefabcd', 64 ) );
	}

	public function test_generate_for_keeps_the_replaced_version_after_a_fresh_upload(): void {
		$thumbs = $this->thumbs();
		$thumbs->store( 7, 'abcdefabcd', 64, $this->root . '/ipavatars/avatar_7.png' );

		$this->assertTrue( $this->generator( [ $this->fake( 'only' ) ] )->generate_for( $this->storage( $thumbs, ConvertibleTimestamp::now() ), 7 ) );
		$this->assertTrue( $thumbs->exists( 7, self::VERSION, 256 ) );
		$this->assertTrue( $thumbs->exists( 7, 'abcdefabcd', 64 ), 'pages cached on other wikis still point at it' );
	}

	public function test_generate_for_drops_the_set_when_the_original_is_deleted_while_rendering(): void {
		$thumbs = $this->thumbs();
		$storage = $this->createMock( AvatarStorage::class );
		$storage->method( 'thumbs' )->willReturn( $thumbs );
		$storage->method( 'find_extension_with_mtime' )->willReturnOnConsecutiveCalls( [ 'ext' => 'png', 'mtime' => '20200101000000', 'version' => self::VERSION ], null );
		$storage->method( 'get_backend' )->willReturn( $this->backend );
		$storage->method( 'storage_path' )->willReturn( $this->backend->getContainerStoragePath( 'ipavatars' ) . '/avatar_7.png' );
		$ready = false;

		$this->assertFalse( $this->generator( [ $this->fake( 'only' ) ] )->generate_for( $storage, 7, static function () use ( &$ready ): void {
			$ready = true;
		} ) );
		$this->assertFalse( $ready );
		$this->assertDirectoryDoesNotExist( $this->root . '/ipavatars-thumbs/7' );
	}

	public function test_generate_for_calls_on_ready_only_after_a_full_set(): void {
		$calls = 0;
		$on_ready = static function () use ( &$calls ): void {
			$calls++;
		};

		$this->assertFalse( $this->generator( [ $this->fake( 'fails', result: false ) ] )->generate_for( $this->storage( $this->thumbs() ), 7, $on_ready ) );
		$this->assertSame( 0, $calls );

		$this->assertTrue( $this->generator( [ $this->fake( 'only' ) ] )->generate_for( $this->storage( $this->thumbs() ), 7, $on_ready ) );
		$this->assertSame( 1, $calls );
	}

	public function test_generate_for_survives_a_throwing_on_ready(): void {
		$on_ready = static function (): void {
			throw new RuntimeException( 'cache down' );
		};

		$this->assertTrue( $this->generator( [ $this->fake( 'only' ) ] )->generate_for( $this->storage( $this->thumbs() ), 7, $on_ready ) );
	}

	public function test_generated_set_is_seen_as_complete(): void {
		$thumbs = $this->thumbs();
		$this->assertFalse( $thumbs->set_exists( 7, self::VERSION ) );

		$this->generator( [ $this->fake( 'only' ) ] )->generate_for( $this->storage( $thumbs ), 7 );

		$this->assertTrue( $thumbs->set_exists( 7, self::VERSION ) );
	}

	public function test_generate_for_without_original_does_nothing(): void {
		$fake = $this->fake( 'only' );
		$storage = $this->createMock( AvatarStorage::class );
		$storage->method( 'thumbs' )->willReturn( $this->thumbs() );
		$storage->method( 'find_extension_with_mtime' )->willReturn( null );

		$this->assertFalse( $this->generator( [ $fake ] )->generate_for( $storage, 7 ) );
		$this->assertSame( 0, $fake->calls );
	}

	public function test_generate_for_never_throws(): void {
		$storage = $this->createMock( AvatarStorage::class );
		$storage->method( 'thumbs' )->willThrowException( new RuntimeException( 'backend down' ) );

		$this->assertFalse( $this->generator( [ $this->fake( 'only' ) ] )->generate_for( $storage, 7 ) );
	}

	public function test_generate_for_skips_unconfigured_container(): void {
		$fake = $this->fake( 'only' );
		$unconfigured = new ThumbnailStorage(
			fn (): FileBackend => new FSFileBackend( [
				'name' => 'ip-thumbgen-bare',
				'domainId' => 'integratedprofiles',
				'lockManager' => class_exists( 'Wikimedia\LockManager\NullLockManager' ) ?
					new \Wikimedia\LockManager\NullLockManager( [] ) :
					new \NullLockManager( [] ),
				'containerPaths' => [ 'ipavatars' => $this->root . '/ipavatars' ],
			] ),
			'ipavatars-thumbs', ThumbnailSizes::AVATAR, '', '/images/ipavatars-thumbs'
		);

		$this->assertFalse( $this->generator( [ $fake ] )->generate_for( $this->storage( $unconfigured ), 7 ) );
		$this->assertSame( 0, $fake->calls );
	}

	public function test_thumbnailers_from_config_keeps_order_and_drops_unknown_and_duplicates(): void {
		$chain = ThumbnailGenerator::thumbnailers_from_config( [ 'gd', 'nope', 'vips', 'gd', 7, 'imagick' ], '/usr/bin/vipsthumbnail' );

		$this->assertSame( [ 'gd', 'vips', 'imagick' ], array_map( static fn ( Thumbnailer $t ): string => $t->name(), $chain ) );
		$this->assertSame( [], ThumbnailGenerator::thumbnailers_from_config( [], '' ) );
	}

	public function test_vips_command_only_shrinks_and_loads_all_frames_when_animated(): void {
		$vips = new VipsThumbnailer( '/usr/bin/vipsthumbnail' );

		$this->assertSame(
			[ '/usr/bin/vipsthumbnail', '/in.gif[n=-1]', '--size', '256x256>', '-o', '/out.webp[Q=80,strip]' ],
			$vips->command_for( '/in.gif', true, '/out.webp', 256, 256 )
		);
		$this->assertSame( '/in.png', $vips->command_for( '/in.png', false, '/out.webp', 640, 16384 )[1] );
	}

	public function test_vips_reports_failures_and_declines_animated_png(): void {
		$vips = new VipsThumbnailer( '/usr/bin/vipsthumbnail', static fn (): array => [ 1, 'VipsForeignLoad: bad' ] );

		$this->assertFalse( $vips->render( '/in.png', 'png', false, [ [ 'path' => '/out.webp', 'max_width' => 32, 'max_height' => 32 ] ] ) );
		$this->assertStringContainsString( 'VipsForeignLoad: bad', $vips->last_error() );
		$this->assertFalse( $vips->supports( 'png', true ) );
		$this->assertTrue( $vips->supports( 'webp', true ) );
	}

	public function test_gd_declines_animation_and_refuses_when_memory_is_short(): void {
		$gd = new GdThumbnailer( 1024 );

		$this->assertFalse( $gd->supports( 'gif', true ) );
		$this->assertFalse( $gd->render( $this->src, 'png', false, [ [ 'path' => $this->root . '/o.webp', 'max_width' => 32, 'max_height' => 32 ] ] ) );
		$this->assertStringContainsString( 'not enough memory', $gd->last_error() );
	}

	/**
	 * Test whichever real libraries this machine has.
	 *
	 * @dataProvider provide_real_thumbnailers
	 */
	public function test_real_thumbnailer_fits_box_without_upscaling( string $name ): void {
		$thumbnailer = match ( $name ) {
			'vips' => new VipsThumbnailer( '/usr/bin/vipsthumbnail', static function ( array $cmd ): array {
				// phpcs:ignore MediaWiki.Usage.ForbiddenFunctions.exec
				exec( implode( ' ', array_map( 'escapeshellarg', $cmd ) ) . ' 2>&1', $out, $code );
				return [ $code, implode( "\n", $out ) ];
			} ),
			'imagick' => new ImagickThumbnailer(),
			'gd' => new GdThumbnailer(),
		};
		if ( !$thumbnailer->is_available() || ( $name === 'vips' && !is_executable( '/usr/bin/vipsthumbnail' ) ) ) {
			$this->markTestSkipped( "$name is not installed" );
		}

		$small = $this->root . '/small.webp';
		$large = $this->root . '/large.webp';
		$this->assertTrue(
			$thumbnailer->render( $this->src, 'png', false, [
				[ 'path' => $small, 'max_width' => 32, 'max_height' => 32 ],
				[ 'path' => $large, 'max_width' => 128, 'max_height' => 128 ],
			] ),
			$thumbnailer->last_error()
		);

		$this->assertSame( [ 32, 16, 'image/webp' ], $this->dims( $small ) );
		$this->assertSame( [ 64, 32, 'image/webp' ], $this->dims( $large ) );
	}

	public static function provide_real_thumbnailers(): array {
		return [ 'vips' => [ 'vips' ], 'imagick' => [ 'imagick' ], 'gd' => [ 'gd' ] ];
	}

	/**
	 * @param list<Thumbnailer> $thumbnailers
	 */
	private function generator( array $thumbnailers ): ThumbnailGenerator {
		return new ThumbnailGenerator( $thumbnailers, new TempFSFileFactory( $this->root . '/tmp' ), new NullLogger() );
	}

	private function thumbs( bool $square = true ): ThumbnailStorage {
		return new ThumbnailStorage(
			fn (): FileBackend => $this->backend,
			'ipavatars-thumbs', ThumbnailSizes::AVATAR, '', '/images/ipavatars-thumbs', $square
		);
	}

	private function storage( ThumbnailStorage $thumbs, string $mtime = '20200101000000' ): AvatarStorage {
		$storage = $this->createMock( AvatarStorage::class );
		$storage->method( 'thumbs' )->willReturn( $thumbs );
		$storage->method( 'find_extension_with_mtime' )->willReturn( [ 'ext' => 'png', 'mtime' => $mtime, 'version' => self::VERSION ] );
		$storage->method( 'get_backend' )->willReturn( $this->backend );
		$storage->method( 'storage_path' )->willReturn( $this->backend->getContainerStoragePath( 'ipavatars' ) . '/avatar_7.png' );

		return $storage;
	}

	private function fake( string $name, bool $available = true, bool $animated = true, bool $result = true, bool $throws = false, ?string $bytes = null ): Thumbnailer {
		$bytes ??= base64_decode( self::WEBP_1X1 );

		return new class( $name, $available, $animated, $result, $throws, $bytes ) implements Thumbnailer {
			public int $calls = 0;
			public array $targets = [];

			public function __construct(
				private readonly string $name,
				private readonly bool $available,
				private readonly bool $animated,
				private readonly bool $result,
				private readonly bool $throws,
				private readonly string $bytes,
			) {
			}

			public function name(): string {
				return $this->name;
			}

			public function is_available(): bool {
				return $this->available;
			}

			public function supports( string $ext, bool $animated ): bool {
				return !$animated || $this->animated;
			}

			public function render( string $src_path, string $ext, bool $animated, array $targets ): bool {
				$this->calls++;
				$this->targets = $targets;
				if ( $this->throws ) {
					throw new LogicException( 'boom' );
				}
				foreach ( $targets as $target ) {
					file_put_contents( $target['path'], $this->bytes );
				}

				return $this->result;
			}

			public function last_error(): string {
				return $this->result ? '' : 'failed';
			}
		};
	}

	/**
	 * @return array{0: int, 1: int, 2: string}
	 */
	private function dims( string $path ): array {
		$info = getimagesize( $path );

		return [ $info[0], $info[1], $info['mime'] ];
	}

	private static function png( int $width, int $height ): string {
		$raw = '';
		for ( $y = 0; $y < $height; $y++ ) {
			$raw .= "\x00";
			for ( $x = 0; $x < $width; $x++ ) {
				$raw .= chr( $x * 4 ) . chr( $y * 8 ) . "\x80\xFF";
			}
		}

		$chunk = static fn ( string $type, string $data ): string =>
			pack( 'N', strlen( $data ) ) . $type . $data . pack( 'N', crc32( $type . $data ) );

		return "\x89PNG\r\n\x1a\n" . $chunk( 'IHDR', pack( 'NNCCCCC', $width, $height, 8, 6, 0, 0, 0 ) ) . $chunk( 'IDAT', gzcompress( $raw ) ) . $chunk( 'IEND', '' );
	}

	private static function animated_gif(): string {
		$frame = "\x21\xF9\x04\x00\x0A\x00\x00\x00" . "\x2C\x00\x00\x00\x00\x01\x00\x01\x00\x00" . "\x02\x02\x4C\x01\x00";

		return 'GIF89a' . "\x01\x00\x01\x00\x80\x00\x00" . "\x00\x00\x00\xFF\xFF\xFF" . "\x21\xFF\x0BNETSCAPE2.0\x03\x01\x00\x00\x00" . $frame . $frame . "\x3B";
	}

}
