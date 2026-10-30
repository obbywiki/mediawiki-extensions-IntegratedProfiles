<?php
declare( strict_types=1 );

namespace MediaWiki\Extension\IntegratedProfiles\Tests\Unit;

use Closure;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\IntegratedProfiles\AvatarService;
use MediaWiki\Extension\IntegratedProfiles\AvatarStorage;
use MediaWiki\Extension\IntegratedProfiles\HookRunner;
use MediaWiki\Extension\IntegratedProfiles\ImageUploadSanitizer;
use MediaWiki\Extension\IntegratedProfiles\ImageUploadVerifier;
use MediaWiki\Extension\IntegratedProfiles\ProfilePagePurger;
use MediaWiki\Extension\IntegratedProfiles\ProfileSubjectIds;
use MediaWiki\Extension\IntegratedProfiles\ThumbnailGenerator;
use MediaWiki\Status\Status;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;
use Wikimedia\FileBackend\FSFile\TempFSFileFactory;
use Wikimedia\ObjectCache\HashBagOStuff;

/**
 * @group IntegratedProfiles
 * @covers \MediaWiki\Extension\IntegratedProfiles\AvatarService
 */
class AvatarServiceUploadTest extends MediaWikiUnitTestCase {

	private const PNG_1X1 = "\x89PNG\r\n\x1a\n\x00\x00\x00\rIHDR\x00\x00\x00\x01\x00\x00\x00\x01\x08\x06\x00\x00\x00\x1f\x15\xc4\x89" . "\x00\x00\x00\rIDATx\xdac\xfc\xcf\xc0P\x0f\x00\x04\x85\x01\x80\x84\xa9\x8c!\x00\x00\x00\x00IEND\xaeB`\x82";

	private string $temp_dir;

	protected function setUp(): void {
		parent::setUp();
		$this->temp_dir = sys_get_temp_dir() . '/ip-avatar-upload-tests-' . getmypid();
		if ( !is_dir( $this->temp_dir ) ) {
			mkdir( $this->temp_dir, 0700, true );
		}
	}

	protected function tearDown(): void {
		foreach ( glob( $this->temp_dir . '/*' ) ?: [] as $file ) {
			if ( is_file( $file ) ) {
				unlink( $file );
			}
		}
		if ( is_dir( $this->temp_dir ) ) {
			rmdir( $this->temp_dir );
		}
		parent::tearDown();
	}

	public function test_upload_stores_stripped_bytes_not_original(): void {
		$png = base64_decode(
			'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
			true
		);
		$this->assertNotFalse( $png );

		$ihdr_len = unpack( 'N', substr( $png, 8, 4 ) )[1];
		$at = 8 + 12 + $ihdr_len;
		$comment = 'tEXt' . "Comment\x00<script>alert(1)</script>";
		$chunk = pack( 'N', strlen( $comment ) - 4 ) . $comment . pack( 'N', crc32( $comment ) );
		$dirty = substr( $png, 0, $at ) . $chunk . substr( $png, $at ) . '<html>polyglot</html>';

		$path = $this->temp_dir . '/dirty.png';
		file_put_contents( $path, $dirty );

		$storage = $this->createMock( AvatarStorage::class );
		$storage->expects( $this->once() )
			->method( 'store' )
			->willReturnCallback( function ( int $owner_id, string $ext, string $src_path ) use ( $png ): Status {
				$this->assertSame( 1000, $owner_id );
				$this->assertSame( 'png', $ext );
				$stored = file_get_contents( $src_path );
				$this->assertSame( $png, $stored );
				return Status::newGood();
			} );
		$storage->method( 'find_extension_with_mtime' )
			->willReturn( [ 'ext' => 'png', 'mtime' => '1' ] );
		$storage->method( 'public_url' )
			->willReturn( '/images/ipavatars/avatar_1000.png?r=1' );

		$status = $this->make_service( $storage )->upload( 1000, $path, strlen( $dirty ) );

		$this->assertTrue( $status->isOK() );
		$this->assertSame( $dirty, file_get_contents( $path ) );
	}

	public function test_upload_rejects_jpeg_the_sanitizer_cannot_parse(): void {
		$jpeg = "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00\x01\x01\x00\x00\x01\x00\x01\x00\x00" . "\xFF\xC0\x00\x0B\x08\x00\x01\x00\x01\x01\x01\x11\x00" . "\xFF\xDA\x00\x08\x01\x01\x00\x00\x3F\x00" . "\xFF\xE1\x00\x10Exif\x00\x00GPS-HERE";
		$path = $this->temp_dir . '/broken.jpg';
		file_put_contents( $path, $jpeg );

		$storage = $this->createMock( AvatarStorage::class );
		$storage->expects( $this->never() )->method( 'store' );

		$status = $this->make_service( $storage )->upload( 1000, $path, strlen( $jpeg ) );

		$this->assertStatusError( 'integratedprofiles-error-image-unsafe', $status );
	}

	public function test_upload_hook_sees_sanitized_file_and_can_reject(): void {
		$png = self::PNG_1X1;
		$path = $this->temp_dir . '/flagged.png';
		file_put_contents( $path, $png . '<html>polyglot</html>' );

		$storage = $this->createMock( AvatarStorage::class );
		$storage->expects( $this->never() )->method( 'store' );
		$storage->expects( $this->never() )->method( 'find_extension_with_mtime' );

		$user = new UserIdentityValue( 1, 'Alice' );
		$calls = 0;
		$hooks = [
			'IntegratedProfilesVerifyImageUpload' => function ( $kind, $hook_path, $mime, $central_id, $hook_user, &$error ) use ( $png, $user, $path, &$calls ) {
				$calls++;
				$this->assertSame( 'avatar', $kind );
				$this->assertNotSame( $path, $hook_path );
				$this->assertSame( $png, file_get_contents( $hook_path ) );
				$this->assertSame( 'image/png', $mime );
				$this->assertSame( 1000, $central_id );
				$this->assertSame( $user, $hook_user );
				$error = [ 'nsfwimagemoderation-upload-rejected', 'detail' ];
			},
		];

		$status = $this->make_service( $storage, $hooks )->upload_for_user( $user, $path, filesize( $path ) );

		$this->assertSame( 1, $calls );
		$this->assertStatusError( 'nsfwimagemoderation-upload-rejected', $status );
	}

	public function test_upload_stores_when_hook_passes(): void {
		$path = $this->temp_dir . '/clean.png';
		file_put_contents( $path, self::PNG_1X1 );

		$storage = $this->createMock( AvatarStorage::class );
		$storage->expects( $this->once() )->method( 'store' )->willReturn( Status::newGood() );
		$storage->method( 'find_extension_with_mtime' )->willReturn( [ 'ext' => 'png', 'mtime' => '1' ] );
		$storage->method( 'public_url' )->willReturn( '/images/ipavatars/avatar_1000.png?r=1' );

		$hooks = [ 'IntegratedProfilesVerifyImageUpload' => static fn () => true ];
		$status = $this->make_service( $storage, $hooks )->upload( 1000, $path, filesize( $path ) );

		$this->assertStatusGood( $status );
	}

	public function test_successful_upload_schedules_thumbnails(): void {
		$path = $this->temp_dir . '/thumbs.png';
		file_put_contents( $path, self::PNG_1X1 );

		$storage = $this->createMock( AvatarStorage::class );
		$storage->method( 'store' )->willReturn( Status::newGood() );
		$storage->method( 'find_extension_with_mtime' )->willReturn( [ 'ext' => 'png', 'mtime' => '1', 'version' => '0123456789' ] );
		$storage->method( 'public_url' )->willReturn( '/images/ipavatars/avatar_1000.png?r=1' );

		$on_ready = null;
		$thumbnails = $this->createMock( ThumbnailGenerator::class );
		$thumbnails->expects( $this->once() )->method( 'schedule' )
			->with( $storage, 1000, $this->isInstanceOf( Closure::class ) )
			->willReturnCallback( static function ( $storage, $owner, Closure $callback ) use ( &$on_ready ): void {
				$on_ready = $callback;
			} );

		$cache = new HashBagOStuff();
		$this->assertStatusGood( $this->make_service( $storage, [], $thumbnails, $cache )->upload( 1000, $path, filesize( $path ) ) );

		$key = $cache->makeGlobalKey( 'integratedprofiles', 'avatar', AvatarService::CACHE_KEY_VERSION, '1000' );
		$this->assertSame( '1000:png:1:0123456789:0', $cache->get( $key ), 'thumbnails are not ready right after the upload' );

		$on_ready();
		$this->assertFalse( $cache->get( $key ), 'the ready callback drops the entry so the next read sees the thumbnails' );
	}

	public function test_failed_store_does_not_schedule_thumbnails(): void {
		$path = $this->temp_dir . '/fail.png';
		file_put_contents( $path, self::PNG_1X1 );

		$storage = $this->createMock( AvatarStorage::class );
		$storage->method( 'store' )->willReturn( Status::newFatal( 'backend-fail-internal', 'x' ) );

		$thumbnails = $this->createMock( ThumbnailGenerator::class );
		$thumbnails->expects( $this->never() )->method( 'schedule' );

		$this->assertFalse( $this->make_service( $storage, [], $thumbnails )->upload( 1000, $path, filesize( $path ) )->isOK() );
	}

	public function test_upload_purges_profile_pages_now_and_once_thumbnails_are_ready(): void {
		$path = $this->temp_dir . '/purge.png';
		file_put_contents( $path, self::PNG_1X1 );
		$user = new UserIdentityValue( 1, 'Alice' );

		$storage = $this->createMock( AvatarStorage::class );
		$storage->method( 'store' )->willReturn( Status::newGood() );
		$storage->method( 'find_extension_with_mtime' )->willReturn( [ 'ext' => 'png', 'mtime' => '1', 'version' => '0123456789' ] );
		$storage->method( 'public_url' )->willReturn( '/images/ipavatars/avatar_1000.png?r=1' );

		$on_ready = null;
		$thumbnails = $this->createMock( ThumbnailGenerator::class );
		$thumbnails->method( 'schedule' )->willReturnCallback( static function ( $storage, $owner, Closure $callback ) use ( &$on_ready ): void {
			$on_ready = $callback;
		} );

		$purged = [];
		$purger = $this->createMock( ProfilePagePurger::class );
		$purger->method( 'purge_subject_pages' )->willReturnCallback( static function ( $subject ) use ( &$purged ): void {
			$purged[] = $subject->getName();
		} );

		$this->assertStatusGood( $this->make_service( $storage, [], $thumbnails, null, $purger )->upload_for_user( $user, $path, filesize( $path ) ) );
		$this->assertSame( [ 'Alice' ], $purged, 'cached pages reference the replaced thumbnails' );

		$on_ready();
		$this->assertSame( [ 'Alice', 'Alice' ], $purged, 'pages cached since the upload switch to the thumbnails' );
	}

	public function test_rejected_upload_does_not_purge(): void {
		$path = $this->temp_dir . '/fail.png';
		file_put_contents( $path, self::PNG_1X1 );

		$storage = $this->createMock( AvatarStorage::class );
		$storage->method( 'store' )->willReturn( Status::newFatal( 'backend-fail-internal', 'x' ) );

		$purger = $this->createMock( ProfilePagePurger::class );
		$purger->expects( $this->never() )->method( 'purge_subject_pages' );

		$this->assertFalse( $this->make_service( $storage, [], null, null, $purger )->upload_for_user( new UserIdentityValue( 1, 'Alice' ), $path, filesize( $path ) )->isOK() );
	}

	public function test_delete_purges_only_on_success(): void {
		$user = new UserIdentityValue( 1, 'Alice' );

		$ok = $this->createMock( AvatarStorage::class );
		$ok->method( 'delete_all' )->willReturn( Status::newGood() );
		$purger = $this->createMock( ProfilePagePurger::class );
		$purger->expects( $this->once() )->method( 'purge_subject_pages' )->with( $user );
		$this->assertStatusGood( $this->make_service( $ok, [], null, null, $purger )->delete_for_user( $user ) );

		$failing = $this->createMock( AvatarStorage::class );
		$failing->method( 'delete_all' )->willReturn( Status::newFatal( 'backend-fail-internal', 'x' ) );
		$untouched = $this->createMock( ProfilePagePurger::class );
		$untouched->expects( $this->never() )->method( 'purge_subject_pages' );
		$this->assertFalse( $this->make_service( $failing, [], null, null, $untouched )->delete_for_user( $user )->isOK() );
	}

	private function make_service( AvatarStorage $storage, array $hooks = [], ?ThumbnailGenerator $thumbnails = null, ?HashBagOStuff $cache = null, ?ProfilePagePurger $purger = null ): AvatarService {
		$subject_ids = $this->createMock( ProfileSubjectIds::class );
		$subject_ids->method( 'ids_for' )->willReturn( [ 'central_id' => 1000, 'local_id' => 1 ] );

		return new AvatarService(
			new ServiceOptions( AvatarService::CONSTRUCTOR_OPTIONS, [ 'IntegratedProfilesAvatarMaxBytes' => 2097152, 'IntegratedProfilesEnableAnimatedAvatars' => true, 'ExtensionAssetsPath' => '/extensions' ] ),
			$storage,
			$cache ?? new HashBagOStuff(),
			$subject_ids,
			new ImageUploadSanitizer( new TempFSFileFactory( $this->temp_dir ) ),
			new ImageUploadVerifier( new HookRunner( $this->createHookContainer( $hooks ) ) ),
			$thumbnails,
			$purger
		);
	}

}
