<?php

namespace MediaWiki\Extension\IntegratedProfiles\Tests;

use MediaWiki\Extension\IntegratedProfiles\BannerValidator;
use PHPUnit\Framework\TestCase;

class BannerValidatorTest extends TestCase {

	private BannerValidator $validator;

	private string $temp_dir;

	protected function setUp(): void {
		parent::setUp();
		$this->validator = new BannerValidator( 1024 );
		$this->temp_dir = sys_get_temp_dir() . '/ip-banner-tests-' . getmypid();

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
		$png = base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', true );

		$this->assertNotFalse( $png );
		file_put_contents( $path, $png );

		$result = $this->validator->validate_upload( $path, strlen( $png ) );
		$this->assertTrue( $result['ok'] );
		$this->assertSame( 'png', $result['ext'] );
	}

	public function test_validate_rejects_oversized(): void {
		$path = $this->temp_dir . '/big.png';
		$png = base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', true );

		file_put_contents( $path, $png );

		$result = $this->validator->validate_upload( $path, 2048 );
		$this->assertFalse( $result['ok'] );
		$this->assertSame( 'integratedprofiles-error-banner-size', $result['error'] );
	}

	public function test_validate_rejects_missing_file(): void {
		$result = $this->validator->validate_upload( $this->temp_dir . '/missing.png', 10 );
		$this->assertFalse( $result['ok'] );
		$this->assertSame( 'integratedprofiles-error-banner-size', $result['error'] );
	}

	public function test_validate_rejects_non_image(): void {
		$path = $this->temp_dir . '/plain.txt';
		file_put_contents( $path, 'not an image' );

		$result = $this->validator->validate_upload( $path, strlen( 'not an image' ) );
		$this->assertFalse( $result['ok'] );
		$this->assertSame( 'integratedprofiles-error-banner-type', $result['error'] );
	}

}
