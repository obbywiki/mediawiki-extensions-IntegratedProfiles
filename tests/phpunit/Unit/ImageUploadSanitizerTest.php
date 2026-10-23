<?php
declare( strict_types=1 );

namespace MediaWiki\Extension\IntegratedProfiles\Tests\Unit;

use MediaWiki\Extension\IntegratedProfiles\AvatarValidator;
use MediaWiki\Extension\IntegratedProfiles\ImageUploadSanitizer;
use MediaWiki\Status\Status;
use MediaWikiUnitTestCase;

/**
 * @group IntegratedProfiles
 * @covers \MediaWiki\Extension\IntegratedProfiles\ImageUploadSanitizer
 */
class ImageUploadSanitizerTest extends MediaWikiUnitTestCase {

	private string $temp_dir;

	protected function setUp(): void {
		parent::setUp();
		$this->temp_dir = sys_get_temp_dir() . '/ip-sanitize-tests-' . getmypid();
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

	public function test_png_roundtrip_keeps_pixels(): void {
		$path = $this->write_temp( 'ok.png', $this->png_1x1() );
		$result = ImageUploadSanitizer::sanitize_file( $path, 'png' );

		$this->assertTrue( $result['ok'] );
		$this->assertSame( $this->png_1x1(), file_get_contents( $result['path'] ) );
		unlink( $result['path'] );
	}

	public function test_png_strips_text_chunk_and_trailing_html(): void {
		$png = $this->png_insert_after_ihdr(
			$this->png_1x1(),
			$this->png_chunk( 'tEXt', "Comment\x00<script>alert(1)</script>" )
		);
		$path = $this->write_temp( 'poly.png', $png . '<html><script>x</script>' );

		$result = ImageUploadSanitizer::sanitize_file( $path, 'png' );
		$this->assertTrue( $result['ok'] );

		$clean = file_get_contents( $result['path'] );
		$this->assertNotFalse( $clean );
		$this->assertStringNotContainsString( 'tEXt', $clean );
		$this->assertStringNotContainsString( '<html>', $clean );
		$this->assertStringNotContainsString( '<script>', $clean );
		$this->assertSame( $this->png_1x1(), $clean );
		unlink( $result['path'] );
	}

	public function test_jpeg_strips_exif_comment_and_trailing_html(): void {
		$jpeg = $this->minimal_jpeg( "\xFF\xE1\x00\x10Exif\x00\x00GPS-HERE" . "\xFF\xFE\x00\x0B<script>x" );
		$path = $this->write_temp( 'geo.jpg', $jpeg . '<html>trail</html>' );

		$result = ImageUploadSanitizer::sanitize_file( $path, 'jpg' );
		$this->assertTrue( $result['ok'] );

		$clean = file_get_contents( $result['path'] );
		$this->assertNotFalse( $clean );
		$this->assertStringNotContainsString( 'Exif', $clean );
		$this->assertStringNotContainsString( 'GPS-HERE', $clean );
		$this->assertStringNotContainsString( '<script>', $clean );
		$this->assertStringNotContainsString( '<html>', $clean );
		$this->assertStringStartsWith( "\xFF\xD8", $clean );
		$this->assertStringEndsWith( "\xFF\xD9", $clean );
		unlink( $result['path'] );
	}

	public function test_gif_strips_comment_but_keeps_animation(): void {
		$gif = $this->build_gif( 2, true, '<script>alert(1)</script>' );
		$path = $this->write_temp( 'anim.gif', $gif . '<html>' );

		$result = ImageUploadSanitizer::sanitize_file( $path, 'gif' );
		$this->assertTrue( $result['ok'] );

		$clean = file_get_contents( $result['path'] );
		$this->assertNotFalse( $clean );
		$this->assertStringNotContainsString( '<script>', $clean );
		$this->assertStringNotContainsString( '<html>', $clean );
		$this->assertStringContainsString( 'NETSCAPE', $clean );

		$validator = new AvatarValidator( 65536 );
		$this->assertTrue( $validator->is_animated( $result['path'], 'gif' ) );
		unlink( $result['path'] );
	}

	public function test_webp_strips_exif_chunk_and_clears_flag(): void {
		$path = $this->write_temp( 'meta.webp', $this->build_webp_with_exif() );

		$result = ImageUploadSanitizer::sanitize_file( $path, 'webp' );
		$this->assertTrue( $result['ok'] );

		$clean = file_get_contents( $result['path'] );
		$this->assertNotFalse( $clean );
		$this->assertStringNotContainsString( 'GPS!', $clean );
		$this->assertStringNotContainsString( 'EXIF', $clean );

		$vp8x = strpos( $clean, 'VP8X' );
		$this->assertNotFalse( $vp8x );
		$flags = ord( $clean[$vp8x + 8] );
		$this->assertSame( 0, $flags & 0x0C );
		unlink( $result['path'] );
	}

	public function test_png_keeps_unknown_chunks(): void {
		$idot = $this->png_chunk( 'iDOT', "\x00\x00\x00\x00" );
		$png = $this->png_insert_after_ihdr( $this->png_1x1(), $idot );
		$path = $this->write_temp( 'idot.png', $png );

		$result = ImageUploadSanitizer::sanitize_file( $path, 'png' );
		$this->assertTrue( $result['ok'] );
		$this->assertSame( $png, file_get_contents( $result['path'] ) );
		unlink( $result['path'] );
	}

	public function test_does_not_reject_html_bytes_inside_idat(): void {
		$png = $this->png_insert_after_ihdr(
			$this->png_1x1(),
			$this->png_chunk( 'IDAT', '<%<html><script>' )
		);
		$path = $this->write_temp( 'idat.png', $png );

		$result = ImageUploadSanitizer::sanitize_file( $path, 'png' );
		$this->assertTrue( $result['ok'] );
		unlink( $result['path'] );
	}

	public function test_progressive_jpeg_with_two_scans_is_kept(): void {
		$app0 = "\xFF\xE0\x00\x10JFIF\x00\x01\x01\x00\x00\x01\x00\x01\x00\x00";
		$sos = "\xFF\xDA\x00\x08\x01\x01\x00\x00\x3F\x00";
		$dht = "\xFF\xC4\x00\x04\x00\x00";
		$jpeg = "\xFF\xD8" . $app0 . $sos . "\x00" . $dht . $sos . "\x00\xFF\xD9";
		$path = $this->write_temp( 'prog.jpg', $jpeg );

		$result = ImageUploadSanitizer::sanitize_file( $path, 'jpg' );
		$this->assertTrue( $result['ok'] );
		$this->assertSame( $jpeg, file_get_contents( $result['path'] ) );
		unlink( $result['path'] );
	}

	public function test_unparsable_jpeg_falls_back_instead_of_rejecting(): void {
		$jpeg = "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00\x01\x01\x00\x00\x01\x00\x01\x00\x00\xFF\xD9";
		$path = $this->write_temp( 'empty.jpg', $jpeg );

		$result = ImageUploadSanitizer::sanitize_file( $path, 'jpg' );
		$this->assertTrue( $result['ok'] );
		$this->assertSame( $jpeg, file_get_contents( $result['path'] ) );
		unlink( $result['path'] );
	}

	public function test_with_sanitized_file_unlinks_temp(): void {
		$path = $this->write_temp( 'ok.png', $this->png_1x1() );
		$seen = null;

		$status = ImageUploadSanitizer::with_sanitized_file(
			$path,
			'png',
			function ( string $tmp ) use ( &$seen ): Status {
				$seen = $tmp;
				$this->assertFileExists( $tmp );
				return Status::newGood();
			}
		);

		$this->assertTrue( $status->isOK() );
		$this->assertNotNull( $seen );
		$this->assertFileDoesNotExist( $seen );
	}

	private function write_temp( string $name, string $bytes ): string {
		$path = $this->temp_dir . '/' . $name;
		file_put_contents( $path, $bytes );
		return $path;
	}

	private function png_1x1(): string {
		$png = base64_decode(
			'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
			true
		);
		$this->assertNotFalse( $png );
		return $png;
	}

	private function png_chunk( string $type, string $payload ): string {
		return pack( 'N', strlen( $payload ) ) . $type . $payload . pack( 'N', crc32( $type . $payload ) );
	}

	private function png_insert_after_ihdr( string $png, string $chunk ): string {
		$ihdr_len = unpack( 'N', substr( $png, 8, 4 ) )[1];
		$at = 8 + 12 + $ihdr_len;
		return substr( $png, 0, $at ) . $chunk . substr( $png, $at );
	}

	private function minimal_jpeg( string $extra_markers ): string {
		$app0 = "\xFF\xE0\x00\x10JFIF\x00\x01\x01\x00\x00\x01\x00\x01\x00\x00";
		$sos = "\xFF\xDA\x00\x08\x01\x01\x00\x00\x3F\x00";
		return "\xFF\xD8" . $extra_markers . $app0 . $sos . "\x00\xFF\xD9";
	}

	private function build_gif( int $frame_count, bool $netscape, string $comment ): string {
		$header = 'GIF89a' . "\x01\x00\x01\x00\x80\x00\x00" . "\x00\x00\x00\xFF\xFF\xFF";
		$netscape_block = $netscape ? "\x21\xFF\x0BNETSCAPE2.0\x03\x01\x00\x00\x00" : '';
		$comment_block = "\x21\xFE" . chr( strlen( $comment ) ) . $comment . "\x00";
		$frame = "\x21\xF9\x04\x00\x0A\x00\x00\x00"
			. "\x2C\x00\x00\x00\x00\x01\x00\x01\x00\x00"
			. "\x02\x02\x4C\x01\x00";
		$frames = str_repeat( $frame, max( 1, $frame_count ) );

		return $header . $netscape_block . $comment_block . $frames . "\x3B";
	}

	private function build_webp_with_exif(): string {
		$vp8x = "\x08\x00\x00\x00" . "\x00\x00\x00" . "\x00\x00\x00";
		$vp8 = "\x00\x00\x00";
		$exif = 'GPS!';
		$chunks = 'VP8X' . pack( 'V', 10 ) . $vp8x
			. 'VP8 ' . pack( 'V', strlen( $vp8 ) ) . $vp8 . "\x00"
			. 'EXIF' . pack( 'V', strlen( $exif ) ) . $exif;
		$payload = 'WEBP' . $chunks;

		return 'RIFF' . pack( 'V', strlen( $payload ) ) . $payload;
	}

}
