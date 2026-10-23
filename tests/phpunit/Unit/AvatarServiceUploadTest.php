<?php
declare( strict_types=1 );

namespace MediaWiki\Extension\IntegratedProfiles\Tests\Unit;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\IntegratedProfiles\AvatarService;
use MediaWiki\Extension\IntegratedProfiles\AvatarStorage;
use MediaWiki\Extension\IntegratedProfiles\ProfileSubjectIds;
use MediaWiki\Status\Status;
use MediaWikiUnitTestCase;
use Wikimedia\ObjectCache\HashBagOStuff;

/**
 * @group IntegratedProfiles
 * @covers \MediaWiki\Extension\IntegratedProfiles\AvatarService
 */
class AvatarServiceUploadTest extends MediaWikiUnitTestCase {

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

	private function make_service( AvatarStorage $storage ): AvatarService {
		$subject_ids = $this->createMock( ProfileSubjectIds::class );
		$subject_ids->method( 'ids_for' )->willReturn( [ 'central_id' => 1000, 'local_id' => 1 ] );

		return new AvatarService(
			new ServiceOptions(
				AvatarService::CONSTRUCTOR_OPTIONS,
				[
					'IntegratedProfilesAvatarMaxBytes' => 2097152,
					'IntegratedProfilesEnableAnimatedAvatars' => true,
					'ExtensionAssetsPath' => '/extensions',
				]
			),
			$storage,
			new HashBagOStuff(),
			$subject_ids
		);
	}

}
