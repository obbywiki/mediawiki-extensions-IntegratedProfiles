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

	public function test_sizes_are_ascending(): void {
		foreach ( [ ThumbnailSizes::AVATAR, ThumbnailSizes::BANNER ] as $sizes ) {
			$sorted = $sizes;
			sort( $sorted );
			$this->assertSame( $sorted, $sizes );
		}
	}

}
