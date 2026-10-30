<?php
declare( strict_types=1 );

namespace MediaWiki\Extension\IntegratedProfiles\Tests\Unit;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\IntegratedProfiles\AvatarService;
use MediaWiki\Extension\IntegratedProfiles\AvatarStorage;
use MediaWiki\Extension\IntegratedProfiles\ProfilePagePurger;
use MediaWiki\Extension\IntegratedProfiles\ProfileSubjectIds;
use MediaWiki\Extension\IntegratedProfiles\ThumbnailBackfill;
use MediaWiki\Extension\IntegratedProfiles\Thumbnailer\Thumbnailer;
use MediaWiki\Extension\IntegratedProfiles\ThumbnailGenerator;
use MediaWiki\Extension\IntegratedProfiles\ThumbnailSizes;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;
use Psr\Log\NullLogger;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Wikimedia\FileBackend\FSFile\TempFSFileFactory;

/**
 * @group IntegratedProfiles
 * @covers \MediaWiki\Extension\IntegratedProfiles\ThumbnailBackfill
 * @covers \MediaWiki\Extension\IntegratedProfiles\AvatarStorage::owner_ids
 * @covers \MediaWiki\Extension\IntegratedProfiles\ThumbnailGenerator::last_was_unsupported
 * @covers \MediaWiki\Extension\IntegratedProfiles\ThumbnailGenerator::available
 */
class ThumbnailBackfillTest extends MediaWikiUnitTestCase {

	private const PNG_1X1 = "\x89PNG\r\n\x1a\n\x00\x00\x00\rIHDR\x00\x00\x00\x01\x00\x00\x00\x01\x08\x06\x00\x00\x00\x1f\x15\xc4\x89" . "\x00\x00\x00\rIDATx\xdac\xfc\xcf\xc0P\x0f\x00\x04\x85\x01\x80\x84\xa9\x8c!\x00\x00\x00\x00IEND\xaeB`\x82";
	private const WEBP_1X1 = 'UklGRhoAAABXRUJQVlA4TA0AAAAvAAAAEAcQERGIiP4HAA==';

	private string $root;
	private AvatarStorage $storage;

	protected function setUp(): void {
		parent::setUp();

		$this->root = sys_get_temp_dir() . '/ip-backfill-' . bin2hex( random_bytes( 6 ) );
		mkdir( $this->root . '/ipavatars', 0777, true );
		mkdir( $this->root . '/tmp' );

		$this->storage = new AvatarStorage( new ServiceOptions( AvatarStorage::CONSTRUCTOR_OPTIONS, [
			'IntegratedProfilesBackend' => '',
			'IntegratedProfilesUploadBaseUrl' => '',
			'UploadDirectory' => $this->root,
			'UploadPath' => '/images',
			'UploadBaseUrl' => '',
		] ) );
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

	public function test_owner_ids_lists_originals_only_in_order(): void {
		foreach ( [ 'avatar_12.webp', 'avatar_3.png', 'avatar_3.jpg', 'avatar_0.png', 'avatar_x.png', 'avatar_7.bmp', 'notes.txt', 'avatar_007.png' ] as $name ) {
			file_put_contents( $this->root . '/ipavatars/' . $name, 'x' );
		}

		$this->assertSame( [ 3, 12 ], $this->storage->owner_ids() );
	}

	public function test_owner_ids_empty_without_container_directory(): void {
		rmdir( $this->root . '/ipavatars' );

		$this->assertSame( [], $this->storage->owner_ids() );
	}

	public function test_generates_missing_sets_skips_current_and_batches_purges(): void {
		$this->original( 3 );
		$this->original( 5 );
		$this->original( 9 );

		$forgotten = [];
		$service = $this->service( $forgotten );
		$purges = [];
		$generator = $this->generator( [ $this->fake() ] );

		$this->backfill( $generator, $purges )->run( $this->storage, $service, [ 5 ] );
		$this->assertSame( [ [ 'User5' ] ], $purges );

		$purges = [];
		$forgotten = [];
		$seen = [];
		$counts = $this->backfill( $generator, $purges )->run( $this->storage, $service, null, [], static function ( int $owner, string $status ) use ( &$seen ): void {
			$seen[$owner] = $status;
		} );

		$this->assertSame( [ 3 => 'generated', 5 => 'current', 9 => 'generated' ], $seen );
		$this->assertSame( 2, $counts[ThumbnailBackfill::GENERATED] );
		$this->assertSame( 1, $counts[ThumbnailBackfill::CURRENT] );
		$this->assertSame( [ 3, 9 ], $forgotten );
		$this->assertSame( [ [ 'User3' ] ], $purges, 'one purge for the whole run, and owner 9 has no local account' );
		foreach ( ThumbnailSizes::AVATAR as $size ) {
			$this->assertFileExists( $this->thumb_dir( 3 ) . '/' . $size . '.webp' );
		}
	}

	public function test_purges_flush_at_batch_size(): void {
		foreach ( [ 1, 2, 3 ] as $owner ) {
			$this->original( $owner );
		}

		$forgotten = [];
		$purges = [];
		$this->backfill( $this->generator( [ $this->fake() ] ), $purges, 2 )->run( $this->storage, $this->service( $forgotten ), null );

		$this->assertSame( [ [ 'User1', 'User2' ], [ 'User3' ] ], $purges );
	}

	public function test_dry_run_reports_without_writing_or_purging(): void {
		$this->original( 3 );

		$forgotten = [];
		$purges = [];
		$fake = $this->fake();
		$counts = $this->backfill( $this->generator( [ $fake ] ), $purges )->run( $this->storage, $this->service( $forgotten ), null, [ 'dry_run' => true ] );

		$this->assertSame( 1, $counts[ThumbnailBackfill::PENDING] );
		$this->assertSame( 0, $fake->calls );
		$this->assertSame( [], $forgotten );
		$this->assertSame( [], $purges );
		$this->assertDirectoryDoesNotExist( $this->root . '/ipavatars-thumbs/3' );
	}

	public function test_start_skips_lower_owners_and_force_regenerates(): void {
		$this->original( 3 );
		$this->original( 5 );

		$forgotten = [];
		$purges = [];
		$fake = $this->fake();
		$backfill = $this->backfill( $this->generator( [ $fake ] ), $purges );

		$counts = $backfill->run( $this->storage, $this->service( $forgotten ), null, [ 'start' => 4 ] );
		$this->assertSame( 1, $counts[ThumbnailBackfill::GENERATED] );
		$this->assertDirectoryDoesNotExist( $this->root . '/ipavatars-thumbs/3' );

		$counts = $backfill->run( $this->storage, $this->service( $forgotten ), [ 5 ], [ 'force' => true ] );
		$this->assertSame( 1, $counts[ThumbnailBackfill::GENERATED] );
		$this->assertSame( 2, $fake->calls );
	}

	public function test_unsupported_failed_and_missing_are_told_apart(): void {
		$this->original( 3 );

		$forgotten = [];
		$purges = [];
		$service = $this->service( $forgotten );

		$this->assertSame( ThumbnailBackfill::UNSUPPORTED, $this->backfill( $this->generator( [ $this->fake( supports: false ) ] ), $purges )->backfill_owner( $this->storage, $service, 3 ) );
		$this->assertSame( ThumbnailBackfill::FAILED, $this->backfill( $this->generator( [ $this->fake( result: false ) ] ), $purges )->backfill_owner( $this->storage, $service, 3 ) );
		$this->assertSame( ThumbnailBackfill::MISSING, $this->backfill( $this->generator( [ $this->fake() ] ), $purges )->backfill_owner( $this->storage, $service, 4 ) );
		$this->assertSame( [], $forgotten );
		$this->assertSame( [], $purges );
	}

	public function test_current_owners_drop_versions_superseded_past_retention(): void {
		$this->original( 3, time() - 3 * 86400 );
		$this->original( 5 );

		$forgotten = [];
		$purges = [];
		$backfill = $this->backfill( $this->generator( [ $this->fake() ] ), $purges );
		$service = $this->service( $forgotten );
		$backfill->run( $this->storage, $service, null );
		foreach ( [ 3, 5 ] as $owner ) {
			$this->storage->thumbs()->store( $owner, 'abcdefabcd', 512, $this->thumb_dir( $owner ) . '/512.webp' );
		}

		$backfill->run( $this->storage, $service, null, [ 'dry_run' => true ] );
		$this->assertTrue( $this->storage->thumbs()->exists( 3, 'abcdefabcd', 512 ), 'a dry run deletes nothing' );

		$counts = $backfill->run( $this->storage, $service, null );
		$this->assertSame( 2, $counts[ThumbnailBackfill::CURRENT] );
		$this->assertFalse( $this->storage->thumbs()->exists( 3, 'abcdefabcd', 512 ) );
		$this->assertFileExists( $this->thumb_dir( 3 ) . '/512.webp' );
		$this->assertTrue( $this->storage->thumbs()->exists( 5, 'abcdefabcd', 512 ), 'replaced too recently' );
	}

	public function test_available_lists_runnable_libraries_in_order(): void {
		$generator = $this->generator( [ $this->fake( 'a' ), $this->fake( 'b', available: false ), $this->fake( 'c' ) ] );

		$this->assertSame( [ 'a', 'c' ], $generator->available() );
	}

	private function original( int $owner, ?int $mtime = null ): void {
		$path = $this->root . '/ipavatars/avatar_' . $owner . '.png';
		file_put_contents( $path, self::PNG_1X1 );
		if ( $mtime !== null ) {
			touch( $path, $mtime );
			clearstatcache();
		}
	}

	private function thumb_dir( int $owner ): string {
		$found = $this->storage->find_extension_with_mtime( $owner );

		return $this->root . '/ipavatars-thumbs/' . $owner . '/' . $found['version'];
	}

	/**
	 * @param list<int> &$forgotten
	 */
	private function service( array &$forgotten ): AvatarService {
		$service = $this->createMock( AvatarService::class );
		$service->method( 'forget_cached' )->willReturnCallback( static function ( int $owner ) use ( &$forgotten ): void {
			$forgotten[] = $owner;
		} );

		return $service;
	}

	/**
	 * @param ThumbnailGenerator $generator
	 * @param list<list<string>> &$purges
	 * @param int $batch
	 */
	private function backfill( ThumbnailGenerator $generator, array &$purges, int $batch = ThumbnailBackfill::DEFAULT_PURGE_BATCH ): ThumbnailBackfill {
		$subject_ids = $this->createMock( ProfileSubjectIds::class );
		$subject_ids->method( 'local_user_for' )->willReturnCallback(
			static fn ( int $owner ): ?UserIdentity => $owner === 9 ? null : new UserIdentityValue( $owner, 'User' . $owner )
		);

		$purger = $this->createMock( ProfilePagePurger::class );
		$purger->method( 'purge_subject_pages_batch' )->willReturnCallback( static function ( iterable $users ) use ( &$purges ): void {
			$names = [];
			foreach ( $users as $user ) {
				$names[] = $user->getName();
			}
			$purges[] = $names;
		} );

		return new ThumbnailBackfill( $generator, $subject_ids, $purger, $batch );
	}

	/**
	 * @param list<Thumbnailer> $thumbnailers
	 */
	private function generator( array $thumbnailers ): ThumbnailGenerator {
		return new ThumbnailGenerator( $thumbnailers, new TempFSFileFactory( $this->root . '/tmp' ), new NullLogger() );
	}

	private function fake( string $name = 'fake', bool $available = true, bool $supports = true, bool $result = true ): Thumbnailer {
		return new class( $name, $available, $supports, $result, base64_decode( self::WEBP_1X1 ) ) implements Thumbnailer {
			public int $calls = 0;

			public function __construct(
				private readonly string $name,
				private readonly bool $available,
				private readonly bool $supports,
				private readonly bool $result,
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
				return $this->supports;
			}

			public function render( string $src_path, string $ext, bool $animated, array $targets ): bool {
				$this->calls++;
				foreach ( $targets as $target ) {
					file_put_contents( $target['path'], $this->bytes );
				}

				return $this->result;
			}

			public function last_error(): string {
				return $this->result ? '' : 'broken';
			}
		};
	}

}
