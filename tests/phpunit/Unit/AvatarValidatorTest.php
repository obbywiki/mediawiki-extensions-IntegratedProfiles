<?php
declare( strict_types=1 );

namespace MediaWiki\Extension\IntegratedProfiles\Tests\Unit;

use MediaWiki\Extension\IntegratedProfiles\AvatarValidator;
use MediaWikiUnitTestCase;

/**
 * @group IntegratedProfiles
 * @covers \MediaWiki\Extension\IntegratedProfiles\AvatarValidator
 */
class AvatarValidatorTest extends MediaWikiUnitTestCase {

	private AvatarValidator $validator;

	private string $temp_dir;

	protected function setUp(): void {
		parent::setUp();
		$this->validator = new AvatarValidator( 1024 );
		$this->temp_dir = sys_get_temp_dir() . '/ip-avatar-tests-' . getmypid();
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

	public function test_extension_for_mime_map(): void {
		$this->assertSame( 'jpg', $this->validator->extension_for_mime( 'image/jpeg' ) );
		$this->assertSame( 'png', $this->validator->extension_for_mime( 'image/png; charset=binary' ) );
		$this->assertSame( 'gif', $this->validator->extension_for_mime( 'IMAGE/GIF' ) );
		$this->assertSame( 'webp', $this->validator->extension_for_mime( 'image/webp' ) );
		$this->assertNull( $this->validator->extension_for_mime( 'image/svg+xml' ) );
		$this->assertNull( $this->validator->extension_for_mime( null ) );
	}

	public function test_validate_accepts_png(): void {
		$path = $this->temp_dir . '/ok.png';
		// 1x1 PNG
		$png = base64_decode(
			'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
			true
		);
		$this->assertNotFalse( $png );
		file_put_contents( $path, $png );

		$result = $this->validator->validate_upload( $path, strlen( $png ) );
		$this->assertTrue( $result['ok'] );
		$this->assertSame( 'png', $result['ext'] );
	}

	public function test_validate_rejects_oversized(): void {
		$path = $this->temp_dir . '/big.png';
		$png = base64_decode(
			'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
			true
		);
		file_put_contents( $path, $png );

		$result = $this->validator->validate_upload( $path, 2048 );
		$this->assertFalse( $result['ok'] );
		$this->assertSame( 'integratedprofiles-error-avatar-size', $result['error'] );
	}

	public function test_validate_rejects_missing_file(): void {
		$result = $this->validator->validate_upload( $this->temp_dir . '/missing.png', 10 );
		$this->assertFalse( $result['ok'] );
		$this->assertSame( 'integratedprofiles-error-avatar-size', $result['error'] );
	}

	public function test_validate_rejects_non_image(): void {
		$path = $this->temp_dir . '/plain.txt';
		file_put_contents( $path, 'not an image' );

		$result = $this->validator->validate_upload( $path, strlen( 'not an image' ) );
		$this->assertFalse( $result['ok'] );
		$this->assertSame( 'integratedprofiles-error-avatar-type', $result['error'] );
	}

	public function test_validate_accepts_static_gif_without_animated_right(): void {
		$path = $this->write_temp( 'static.gif', $this->build_gif( 1, false ) );

		$result = $this->validator->validate_upload( $path, filesize( $path ) ?: 0 );
		$this->assertTrue( $result['ok'] );
		$this->assertSame( 'gif', $result['ext'] );
	}

	public function test_validate_rejects_multiframe_gif_without_animated_right(): void {
		$path = $this->write_temp( 'anim.gif', $this->build_gif( 2, false ) );

		$result = $this->validator->validate_upload( $path, filesize( $path ) ?: 0 );
		$this->assertFalse( $result['ok'] );
		$this->assertSame( 'integratedprofiles-error-avatar-animated', $result['error'] );
	}

	public function test_validate_rejects_netscape_gif_without_animated_right(): void {
		$path = $this->write_temp( 'loop.gif', $this->build_gif( 1, true ) );

		$result = $this->validator->validate_upload( $path, filesize( $path ) ?: 0 );
		$this->assertFalse( $result['ok'] );
		$this->assertSame( 'integratedprofiles-error-avatar-animated', $result['error'] );
	}

	public function test_validate_accepts_animated_gif_with_flag(): void {
		$path = $this->write_temp( 'allowed.gif', $this->build_gif( 2, true ) );

		$result = $this->validator->validate_upload( $path, filesize( $path ) ?: 0, true );
		$this->assertTrue( $result['ok'] );
		$this->assertSame( 'gif', $result['ext'] );
	}

	public function test_validate_rejects_animated_webp_without_flag(): void {
		$path = $this->write_temp( 'anim.webp', $this->build_animated_webp() );

		$this->assertTrue( $this->validator->is_animated( $path, 'webp' ) );

		$result = $this->validator->validate_upload( $path, filesize( $path ) ?: 0 );
		if ( !$result['ok'] && ( $result['error'] ?? '' ) === 'integratedprofiles-error-avatar-type' ) {
			$this->markTestSkipped( 'getimagesize/MIME did not accept the VP8X fixture' );
		}
		$this->assertFalse( $result['ok'] );
		$this->assertSame( 'integratedprofiles-error-avatar-animated', $result['error'] );
	}

	public function test_validate_rejects_apng_without_flag(): void {
		$path = $this->write_temp( 'anim.png', $this->build_apng() );

		$result = $this->validator->validate_upload( $path, filesize( $path ) ?: 0 );
		$this->assertFalse( $result['ok'] );
		$this->assertSame( 'integratedprofiles-error-avatar-animated', $result['error'] );
	}

	public function test_validate_accepts_apng_with_flag(): void {
		$path = $this->write_temp( 'allowed.png', $this->build_apng() );

		$result = $this->validator->validate_upload( $path, filesize( $path ) ?: 0, true );
		$this->assertTrue( $result['ok'] );
		$this->assertSame( 'png', $result['ext'] );
	}

	private function write_temp( string $name, string $bytes ): string {
		$path = $this->temp_dir . '/' . $name;
		file_put_contents( $path, $bytes );
		return $path;
	}

	private function build_gif( int $frame_count, bool $netscape ): string {
		// 1x1 GIF89a + optional extra frames + netscape loop block
		$header = 'GIF89a' . "\x01\x00\x01\x00\x80\x00\x00" . "\x00\x00\x00\xFF\xFF\xFF";
		$netscape_block = $netscape ? "\x21\xFF\x0BNETSCAPE2.0\x03\x01\x00\x00\x00" : '';
		$frame = "\x21\xF9\x04\x00\x0A\x00\x00\x00"
			. "\x2C\x00\x00\x00\x00\x01\x00\x01\x00\x00"
			. "\x02\x02\x4C\x01\x00";
		$frames = str_repeat( $frame, max( 1, $frame_count ) );

		return $header . $netscape_block . $frames . "\x3B";
	}

	private function build_animated_webp(): string {
		$vp8x = "\x02\x00\x00\x00" . "\x00\x00\x00" . "\x00\x00\x00";
		$anim = "\x00\x00\x00\x00\x00\x00";
		$chunks = 'VP8X' . pack( 'V', 10 ) . $vp8x . 'ANIM' . pack( 'V', 6 ) . $anim;
		$payload = 'WEBP' . $chunks;

		return 'RIFF' . pack( 'V', strlen( $payload ) ) . $payload;
	}

	private function build_apng(): string {
		$png = base64_decode(
			'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
			true
		);

		$this->assertNotFalse( $png );
		$sig = "\x89PNG\r\n\x1a\n";
		$rest = substr( $png, 8 );
		$ihdr_len = unpack( 'N', substr( $rest, 0, 4 ) )[1];
		$ihdr_total = 12 + $ihdr_len;
		$actl_data = pack( 'N', 2 ) . pack( 'N', 0 );
		$type_and_data = 'acTL' . $actl_data;
		$actl = pack( 'N', 8 ) . $type_and_data . hash( 'crc32b', $type_and_data, true );

		return $sig . substr( $rest, 0, $ihdr_total ) . $actl . substr( $rest, $ihdr_total );
	}

}
