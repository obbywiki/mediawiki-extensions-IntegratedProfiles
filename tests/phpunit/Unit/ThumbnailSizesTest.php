<?php
declare( strict_types=1 );

namespace MediaWiki\Extension\IntegratedProfiles\Tests\Unit;

use MediaWiki\Extension\IntegratedProfiles\ThumbnailSizes;
use MediaWikiUnitTestCase;

/**
 * @group IntegratedProfiles
 * @covers \MediaWiki\Extension\IntegratedProfiles\ThumbnailSizes
 */
class ThumbnailSizesTest extends MediaWikiUnitTestCase {

	public static function provide_avatar_buckets(): array {
		return [
			'tiny' => [ 1, 32 ],
			'exact' => [ 64, 64 ],
			'between' => [ 104, 128 ],
			'2x hero' => [ 208, 256 ],
			'largest' => [ 512, 512 ],
			'oversized clamps' => [ 4000, 512 ],
		];
	}

	/**
	 * @dataProvider provide_avatar_buckets
	 */
	public function test_avatar_bucket( int $requested, int $expected ): void {
		$this->assertSame( $expected, ThumbnailSizes::bucket( ThumbnailSizes::AVATAR, $requested ) );
	}

	public function test_banner_bucket(): void {
		$this->assertSame( 640, ThumbnailSizes::bucket( ThumbnailSizes::BANNER, 320 ) );
		$this->assertSame( 1280, ThumbnailSizes::bucket( ThumbnailSizes::BANNER, 1000 ) );
		$this->assertSame( 1920, ThumbnailSizes::bucket( ThumbnailSizes::BANNER, 9999 ) );
	}

	public function test_defaults_are_buckets(): void {
		$this->assertContains( ThumbnailSizes::AVATAR_DEFAULT, ThumbnailSizes::AVATAR );
		$this->assertContains( ThumbnailSizes::BANNER_DEFAULT, ThumbnailSizes::BANNER );
	}

	public static function provide_fit(): array {
		return [
			'square shrink' => [ 2048, 2048, 256, 256, [ 256, 256 ] ],
			'wide in square box' => [ 1000, 500, 128, 128, [ 128, 64 ] ],
			'tall in square box' => [ 500, 1000, 128, 128, [ 64, 128 ] ],
			'banner width only' => [ 3000, 500, 1280, 16384, [ 1280, 213 ] ],
			'never upscales' => [ 40, 20, 512, 512, [ 40, 20 ] ],
			'extreme aspect keeps 1px' => [ 10000, 2, 32, 32, [ 32, 1 ] ],
			'invalid source' => [ 0, 10, 32, 32, [ 1, 1 ] ],
		];
	}

	/**
	 * @dataProvider provide_fit
	 */
	public function test_fit( int $width, int $height, int $max_width, int $max_height, array $expected ): void {
		$this->assertSame( $expected, ThumbnailSizes::fit( $width, $height, $max_width, $max_height ) );
	}

	public function test_pick_snaps_to_bucket_and_handles_missing(): void {
		$urls = [ 64 => '/64.webp', 128 => '/128.webp', 512 => '/512.webp' ];

		$this->assertSame( '/128.webp', ThumbnailSizes::pick( $urls, ThumbnailSizes::AVATAR, 104 ) );
		$this->assertSame( '/512.webp', ThumbnailSizes::pick( $urls, ThumbnailSizes::AVATAR, 9000 ) );
		$this->assertNull( ThumbnailSizes::pick( $urls, ThumbnailSizes::AVATAR, 200 ), 'no 256 entry' );
		$this->assertNull( ThumbnailSizes::pick( [], ThumbnailSizes::AVATAR, 64 ) );
		$this->assertSame( '/128.webp', ThumbnailSizes::pick( [ '128' => '/128.webp' ], ThumbnailSizes::AVATAR, 128 ), 'string keys from JSON round trips' );
	}

	public function test_density_srcset(): void {
		$urls = [ 32 => '/32.webp', 64 => '/64.webp', 128 => '/128.webp', 256 => '/256.webp', 512 => '/512.webp' ];

		$this->assertSame( '/128.webp 1x, /256.webp 2x', ThumbnailSizes::density_srcset( $urls, ThumbnailSizes::AVATAR, 128 ) );
		$this->assertSame( '/512.webp 1x', ThumbnailSizes::density_srcset( $urls, ThumbnailSizes::AVATAR, 512 ), 'the same bucket is listed once' );
		$this->assertSame( '', ThumbnailSizes::density_srcset( [], ThumbnailSizes::AVATAR, 128 ) );
		$this->assertSame( '/128.webp 1x', ThumbnailSizes::density_srcset( [ 128 => '/128.webp', 256 => '/a b.webp' ], ThumbnailSizes::AVATAR, 128 ), 'whitespace would split a candidate' );
	}

	public function test_sizes_are_ascending(): void {
		foreach ( [ ThumbnailSizes::AVATAR, ThumbnailSizes::BANNER ] as $sizes ) {
			$sorted = $sizes;
			sort( $sorted );
			$this->assertSame( $sorted, $sizes );
		}
	}

}
