<?php
declare( strict_types=1 );

namespace MediaWiki\Extension\IntegratedProfiles\Tests\Unit;

use MediaWiki\Extension\IntegratedProfiles\ImageAnimation;
use MediaWiki\Extension\IntegratedProfiles\ImageUploadSanitizer;
use MediaWiki\Status\Status;
use MediaWikiUnitTestCase;
use Wikimedia\FileBackend\FSFile\TempFSFileFactory;

/**
 * @group IntegratedProfiles
 * @covers \MediaWiki\Extension\IntegratedProfiles\ImageUploadSanitizer
 */
class ImageUploadSanitizerTest extends MediaWikiUnitTestCase {

	private const APP0 = "\xFF\xE0\x00\x10JFIF\x00\x01\x01\x00\x00\x01\x00\x01\x00\x00";
	private const SOF0 = "\xFF\xC0\x00\x0B\x08\x00\x01\x00\x01\x01\x01\x11\x00";
	private const SOS = "\xFF\xDA\x00\x08\x01\x01\x00\x00\x3F\x00";

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
		$this->assertSame( $this->png_1x1(), ImageUploadSanitizer::sanitize( $this->png_1x1(), 'png' ) );
	}

	public function test_png_strips_text_chunk_and_trailing_html(): void {
		$png = $this->png_insert_after_ihdr(
			$this->png_1x1(),
			$this->png_chunk( 'tEXt', "Comment\x00<script>alert(1)</script>" )
		);

		$clean = ImageUploadSanitizer::sanitize( $png . '<html><script>x</script>', 'png' );
		$this->assertSame( $this->png_1x1(), $clean );
	}

	public function test_png_drops_unknown_chunks_and_keeps_colour_and_apng_chunks(): void {
		$keep = $this->png_chunk( 'gAMA', "\x00\x00\xB1\x8F" )
			. $this->png_chunk( 'acTL', "\x00\x00\x00\x01\x00\x00\x00\x00" );
		$drop = $this->png_chunk( 'iDOT', "\x00\x00\x00\x00" )
			. $this->png_chunk( 'caBX', 'c2pa-manifest' );
		$png = $this->png_insert_after_ihdr( $this->png_1x1(), $keep . $drop );

		$clean = ImageUploadSanitizer::sanitize( $png, 'png' );
		$this->assertSame( $this->png_insert_after_ihdr( $this->png_1x1(), $keep ), $clean );
	}

	public function test_png_without_iend_is_rejected(): void {
		$png = $this->png_1x1();
		$this->assertNull( ImageUploadSanitizer::sanitize( substr( $png, 0, -12 ), 'png' ) );
	}

	public function test_does_not_reject_html_bytes_inside_idat(): void {
		$png = $this->png_insert_after_ihdr(
			$this->png_1x1(),
			$this->png_chunk( 'IDAT', '<%<html><script>' )
		);

		$this->assertSame( $png, ImageUploadSanitizer::sanitize( $png, 'png' ) );
	}

	public function test_jpeg_strips_exif_comment_and_trailing_html(): void {
		$jpeg = $this->jpeg( "\xFF\xE1\x00\x10Exif\x00\x00GPS-HERE" . "\xFF\xFE\x00\x0B<script>x" );

		$clean = ImageUploadSanitizer::sanitize( $jpeg . '<html>trail</html>', 'jpg' );
		$this->assertSame( $this->jpeg( '' ), $clean );
	}

	public function test_jpeg_drops_unlisted_app_segments_and_keeps_icc_and_adobe(): void {
		$icc = $this->jpeg_segment( 0xE2, "ICC_PROFILE\x00\x01\x01profile" );
		$adobe = $this->jpeg_segment( 0xEE, "Adobe\x00\x64\x00\x00\x00\x00\x01" );
		$drop = $this->jpeg_segment( 0xE2, "MPF\x00II*\x00" )
			. $this->jpeg_segment( 0xEB, 'JPc2pa creator=Alice' )
			. $this->jpeg_segment( 0xED, 'Photoshop 3.0 IPTC' )
			. $this->jpeg_segment( 0xE3, 'vendor' );

		$clean = ImageUploadSanitizer::sanitize( $this->jpeg( $drop . $icc . $adobe ), 'jpg' );
		$this->assertSame( $this->jpeg( $icc . $adobe ), $clean );
	}

	public function test_jpeg_drops_jfif_thumbnail(): void {
		$app0 = $this->jpeg_segment( 0xE0, "JFIF\x00\x01\x01\x00\x00\x01\x00\x01\x01\x01" . 'RGB' );
		$jpeg = "\xFF\xD8" . $app0 . self::SOF0 . self::SOS . "\x00\xFF\xD9";

		$this->assertSame( $this->jpeg( '' ), ImageUploadSanitizer::sanitize( $jpeg, 'jpg' ) );
	}

	public function test_jpeg_drops_images_appended_after_eoi(): void {
		$secondary = $this->jpeg( "\xFF\xE1\x00\x10Exif\x00\x00GPS-HERE" );

		$clean = ImageUploadSanitizer::sanitize( $this->jpeg( '' ) . $secondary, 'jpg' );
		$this->assertSame( $this->jpeg( '' ), $clean );
	}

	public function test_jpeg_tolerates_stray_bytes_between_segments(): void {
		$jpeg = "\xFF\xD8" . self::APP0 . "\x00"
			. "\xFF\xE1\x00\x10Exif\x00\x00GPS-HERE"
			. self::SOF0 . self::SOS . "\x00\xFF\xD9";

		$this->assertSame( $this->jpeg( '' ), ImageUploadSanitizer::sanitize( $jpeg, 'jpg' ) );
	}

	public function test_jpeg_keeps_entropy_data_with_stuffing_and_restarts(): void {
		$scan = "\x12\xFF\x00\x34\xFF\xD0\x56\xFF\xD1\x78";
		$jpeg = "\xFF\xD8" . self::APP0 . self::SOF0 . self::SOS . $scan . "\xFF\xD9";

		$this->assertSame( $jpeg, ImageUploadSanitizer::sanitize( $jpeg, 'jpg' ) );
	}

	public function test_progressive_jpeg_with_two_scans_is_kept_and_metadata_between_scans_dropped(): void {
		$dht = "\xFF\xC4\x00\x04\x00\x00";
		$com = "\xFF\xFE\x00\x06hide";
		$prog = "\xFF\xC2" . substr( self::SOF0, 2 );
		$head = "\xFF\xD8" . self::APP0 . $prog . self::SOS . "\x00";
		$jpeg = $head . $dht . $com . self::SOS . "\x00\xFF\xD9";

		$clean = ImageUploadSanitizer::sanitize( $jpeg, 'jpg' );
		$this->assertSame( $head . $dht . self::SOS . "\x00\xFF\xD9", $clean );
	}

	public function test_jpeg_without_scan_is_rejected(): void {
		$jpeg = "\xFF\xD8" . self::APP0 . "\xFF\xD9";
		$this->assertNull( ImageUploadSanitizer::sanitize( $jpeg, 'jpg' ) );
	}

	public function test_truncated_jpeg_is_rejected(): void {
		$jpeg = "\xFF\xD8" . self::APP0 . self::SOF0 . self::SOS . "\x00\x01\x02";
		$this->assertNull( ImageUploadSanitizer::sanitize( $jpeg, 'jpg' ) );
	}

	/**
	 * @dataProvider provide_byte_orders
	 * @param string $order TIFF byte order, II or MM` to the `tiff_ifd0()
	 */
	public function test_jpeg_keeps_only_orientation_from_exif( string $order ): void {
		$app1 = $this->jpeg_segment( 0xE1, "Exif\x00\x00" . $this->tiff_ifd0( $order, [
			[ 0x010F, 2, 4, 'iPh' ],
			[ 0x0112, 3, 1, 6 ],
			[ 0x8825, 4, 1, 1234 ],
		] ) );

		$clean = ImageUploadSanitizer::sanitize( $this->jpeg( $app1 ), 'jpg' );
		$this->assertNotNull( $clean );
		$this->assertStringNotContainsString( 'iPh', $clean );

		$expected_app1 = $this->jpeg_segment( 0xE1, "Exif\x00\x00" . $this->tiff_ifd0( 'MM', [ [ 0x0112, 3, 1, 6 ] ] ) );
		$this->assertSame(
			"\xFF\xD8" . self::APP0 . $expected_app1 . self::SOF0 . self::SOS . "\x00\xFF\xD9",
			$clean
		);
	}

	public static function provide_byte_orders(): array {
		return [ 'little endian' => [ 'II' ], 'big endian' => [ 'MM' ] ];
	}

	/**
	 * @dataProvider provide_byte_orders
	 */
	public function test_jpeg_orientation_reads_kept_tag( string $order ): void {
		$app1 = $this->jpeg_segment( 0xE1, "Exif\x00\x00" . $this->tiff_ifd0( $order, [ [ 0x0112, 3, 1, 8 ] ] ) );

		$this->assertSame( 8, ImageUploadSanitizer::jpeg_orientation( $this->jpeg( $app1 ) ) );
		$this->assertSame( 8, ImageUploadSanitizer::jpeg_orientation( ImageUploadSanitizer::sanitize( $this->jpeg( $app1 ), 'jpg' ) ) );
	}

	public function test_jpeg_orientation_null_without_exif_or_for_non_jpeg(): void {
		$this->assertNull( ImageUploadSanitizer::jpeg_orientation( $this->jpeg( '' ) ) );
		$this->assertNull( ImageUploadSanitizer::jpeg_orientation( $this->png_1x1() ) );
		$this->assertNull( ImageUploadSanitizer::jpeg_orientation( "\xFF\xD8\xFF\xE1\x00" ) );
	}

	public function test_jpeg_orientation_ignores_app1_after_scan(): void {
		$app1 = $this->jpeg_segment( 0xE1, "Exif\x00\x00" . $this->tiff_ifd0( 'MM', [ [ 0x0112, 3, 1, 6 ] ] ) );
		$jpeg = "\xFF\xD8" . self::APP0 . self::SOF0 . self::SOS . "\x00" . $app1 . "\xFF\xD9";

		$this->assertNull( ImageUploadSanitizer::jpeg_orientation( $jpeg ) );
	}

	public function test_jpeg_normal_orientation_writes_no_app1(): void {
		$app1 = $this->jpeg_segment( 0xE1, "Exif\x00\x00" . $this->tiff_ifd0( 'II', [ [ 0x0112, 3, 1, 1 ] ] ) );
		$this->assertSame( $this->jpeg( '' ), ImageUploadSanitizer::sanitize( $this->jpeg( $app1 ), 'jpg' ) );
	}

	public function test_gif_strips_comment_but_keeps_animation(): void {
		$gif = $this->build_gif( 2, '<script>alert(1)</script>' );

		$clean = ImageUploadSanitizer::sanitize( $gif . '<html>', 'gif' );
		$this->assertNotNull( $clean );
		$this->assertStringNotContainsString( '<script>', $clean );
		$this->assertStringNotContainsString( '<html>', $clean );
		$this->assertStringContainsString( 'NETSCAPE2.0', $clean );

		$path = $this->write_temp( 'anim.gif', $clean );
		$this->assertTrue( ImageAnimation::is_animated( $path, 'gif' ) );
	}

	public function test_gif_strips_xmp_and_unknown_application_extensions(): void {
		$xmp = "\x21\xFF\x0BXMP DataXMP" . "\x08<x:xmpm>" . "\x00";
		$other = "\x21\xFF\x0BICCRGBG1012" . "\x03abc" . "\x00";
		$plain_text = "\x21\x01\x0C" . str_repeat( "\x00", 12 ) . "\x04text" . "\x00";

		$with_meta = $this->build_gif( 1, '', $xmp . $other . $plain_text );
		$clean = ImageUploadSanitizer::sanitize( $with_meta, 'gif' );
		$this->assertSame( $this->build_gif( 1, '' ), $clean );
	}

	public function test_webp_strips_exif_chunk_and_clears_flag(): void {
		$clean = ImageUploadSanitizer::sanitize( $this->build_webp( 'EXIF' . pack( 'V', 4 ) . 'GPS!' ), 'webp' );
		$this->assertNotNull( $clean );
		$this->assertStringNotContainsString( 'GPS!', $clean );
		$this->assertStringNotContainsString( 'EXIF', $clean );

		$vp8x = strpos( $clean, 'VP8X' );
		$this->assertNotFalse( $vp8x );
		$this->assertSame( 0, ord( $clean[$vp8x + 8] ) & 0x0C );
	}

	public function test_webp_drops_unknown_chunks_including_inside_frames(): void {
		$frame = str_repeat( "\x00", 16 ) . 'VP8 ' . pack( 'V', 3 ) . "\x00\x00\x00\x00" . 'C2PA' . pack( 'V', 4 ) . 'Anon';
		$anmf = 'ANMF' . pack( 'V', strlen( $frame ) ) . $frame;
		$clean = ImageUploadSanitizer::sanitize( $this->build_webp( 'XYZW' . pack( 'V', 4 ) . 'leak' . $anmf ), 'webp' );

		$this->assertNotNull( $clean );
		$this->assertStringNotContainsString( 'leak', $clean );
		$this->assertStringNotContainsString( 'Anon', $clean );
		$this->assertStringContainsString( 'ANMF', $clean );
	}

	public function test_gif_rebuilds_loop_and_control_extensions_without_extra_sub_blocks(): void {
		$padded = $this->build_gif( 2, '' );
		$padded = str_replace( "\x03\x01\x00\x00\x00", "\x03\x01\x00\x00\x0Ahidden-10b\x00", $padded );
		$padded = str_replace( "\x21\xF9\x04\x00\x0A\x00\x00\x00", "\x21\xF9\x04\x00\x0A\x00\x00\x04more\x00", $padded );

		$clean = ImageUploadSanitizer::sanitize( $padded, 'gif' );
		$this->assertSame( $this->build_gif( 2, '' ), $clean );
	}

	public function test_gif_drops_netscape_extension_without_loop_sub_block(): void {
		$gif = str_replace( "\x03\x01\x00\x00\x00", "\x05\x02\x00\x10\x00\x00\x00", $this->build_gif( 1, '' ) );

		$clean = ImageUploadSanitizer::sanitize( $gif, 'gif' );
		$this->assertNotNull( $clean );
		$this->assertStringNotContainsString( 'NETSCAPE2.0', $clean );
	}

	public function test_webp_without_image_data_is_rejected(): void {
		$vp8x = 'VP8X' . pack( 'V', 10 ) . str_repeat( "\x00", 10 );
		$iccp = 'ICCP' . pack( 'V', 4 ) . 'icc!';
		$payload = 'WEBP' . $vp8x . $iccp;

		$this->assertNull( ImageUploadSanitizer::sanitize( 'RIFF' . pack( 'V', strlen( $payload ) ) . $payload, 'webp' ) );
	}

	public function test_webp_frame_without_image_data_is_rejected(): void {
		$frame = str_repeat( "\x00", 16 ) . 'C2PA' . pack( 'V', 4 ) . 'Anon';
		$anmf = 'ANMF' . pack( 'V', strlen( $frame ) ) . $frame;

		$this->assertNull( ImageUploadSanitizer::sanitize( $this->build_webp( $anmf ), 'webp' ) );
	}

	public function test_unknown_extension_is_rejected(): void {
		$this->assertNull( ImageUploadSanitizer::sanitize( $this->png_1x1(), 'bmp' ) );
	}

	public function test_with_sanitized_file_stores_clean_bytes_and_unlinks_temp(): void {
		$png = $this->png_insert_after_ihdr( $this->png_1x1(), $this->png_chunk( 'tEXt', "GPS\x00here" ) );
		$path = $this->write_temp( 'ok.png', $png );
		$seen = null;

		$status = $this->sanitizer()->with_sanitized_file(
			$path,
			'png',
			function ( string $tmp ) use ( &$seen ): Status {
				$seen = $tmp;
				$this->assertStringStartsWith( $this->temp_dir . '/', $tmp );
				$this->assertSame( $this->png_1x1(), file_get_contents( $tmp ) );
				return Status::newGood();
			}
		);

		$this->assertStatusGood( $status );
		$this->assertNotNull( $seen );
		$this->assertFileDoesNotExist( $seen );
	}

	public function test_with_sanitized_file_fails_closed_on_unparsable_input(): void {
		$path = $this->write_temp( 'broken.jpg', "\xFF\xD8" . self::APP0 . self::SOF0 . self::SOS . "\x00" );

		$status = $this->sanitizer()->with_sanitized_file( $path, 'jpg', function (): Status {
			$this->fail( 'store must not be called' );
		} );

		$this->assertStatusError( 'integratedprofiles-error-image-unsafe', $status );
	}

	public function test_with_sanitized_file_rechecks_output_dimensions(): void {
		$gif = $this->build_gif( 1, '' );
		// zero-width logical screen
		$gif[6] = "\x00";
		$path = $this->write_temp( 'zero.gif', $gif );

		$status = $this->sanitizer()->with_sanitized_file( $path, 'gif', function (): Status {
			$this->fail( 'store must not be called' );
		} );

		$this->assertStatusError( 'integratedprofiles-error-image-dimensions', $status );
	}

	private function sanitizer(): ImageUploadSanitizer {
		return new ImageUploadSanitizer( new TempFSFileFactory( $this->temp_dir ) );
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

	/**
	 * Minimal baseline JPEG with $extra segments after APP0.
	 */
	private function jpeg( string $extra ): string {
		return "\xFF\xD8" . self::APP0 . $extra . self::SOF0 . self::SOS . "\x00\xFF\xD9";
	}

	private function jpeg_segment( int $marker, string $payload ): string {
		return "\xFF" . chr( $marker ) . pack( 'n', strlen( $payload ) + 2 ) . $payload;
	}

	/**
	 * @param string $order TIFF byte order, II or MM
	 * @param list<array{int, int, int, int|string}> $entries tag, type, count, inline value
	 */
	private function tiff_ifd0( string $order, array $entries ): string {
		$u16 = $order === 'II' ? 'v' : 'n';
		$u32 = $order === 'II' ? 'V' : 'N';

		$ifd = pack( $u16, count( $entries ) );
		foreach ( $entries as [ $tag, $type, $count, $value ] ) {
			$inline = match ( true ) {
				is_string( $value ) => str_pad( substr( $value, 0, 4 ), 4, "\x00" ),
				$type === 3 => pack( $u16, $value ) . "\x00\x00",
				default => pack( $u32, $value ),
			};
			$ifd .= pack( $u16 . '2', $tag, $type ) . pack( $u32, $count ) . $inline;
		}

		return $order . pack( $u16, 42 ) . pack( $u32, 8 ) . $ifd . pack( $u32, 0 );
	}

	private function build_gif( int $frame_count, string $comment, string $extra = '' ): string {
		$header = 'GIF89a' . "\x01\x00\x01\x00\x80\x00\x00" . "\x00\x00\x00\xFF\xFF\xFF";
		$netscape_block = "\x21\xFF\x0BNETSCAPE2.0\x03\x01\x00\x00\x00";
		$comment_block = $comment === '' ? '' : "\x21\xFE" . chr( strlen( $comment ) ) . $comment . "\x00";
		$frame = "\x21\xF9\x04\x00\x0A\x00\x00\x00"
			. "\x2C\x00\x00\x00\x00\x01\x00\x01\x00\x00"
			. "\x02\x02\x4C\x01\x00";
		$frames = str_repeat( $frame, max( 1, $frame_count ) );

		return $header . $netscape_block . $comment_block . $extra . $frames . "\x3B";
	}

	private function build_webp( string $extra_chunks ): string {
		$vp8x = "\x08\x00\x00\x00" . "\x00\x00\x00" . "\x00\x00\x00";
		$vp8 = "\x00\x00\x00";
		$chunks = 'VP8X' . pack( 'V', 10 ) . $vp8x
			. 'VP8 ' . pack( 'V', strlen( $vp8 ) ) . $vp8 . "\x00"
			. $extra_chunks;
		$payload = 'WEBP' . $chunks;

		return 'RIFF' . pack( 'V', strlen( $payload ) ) . $payload;
	}

}
