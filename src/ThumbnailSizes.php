<?php

namespace MediaWiki\Extension\IntegratedProfiles;

/**
 * Fixed thumbnail sizes for avatars and banners. Intentionally not configurable for the sake of consistencies between installs. Removing something here should be considered a breaking change.
 *
 * Avatar sizes are square edge lengths. Banner sizes are widths (height follows the source aspect).
 */
final class ThumbnailSizes {

	/** @var list<int> */
	public const AVATAR = [ 32, 64, 128, 256, 512 ];
	public const AVATAR_DEFAULT = 256;

	/** @var list<int> */
	public const BANNER = [ 640, 1280, 1920 ];
	public const BANNER_DEFAULT = 1280;

	/**
	 * Snaps a requested size up to the nearest bucket (or the largest).
	 *
	 * @param list<int> $sizes Ascending bucket list (AVATAR or BANNER)
	 * @param int $requested Requested edge or width in pixels
	 * @return int
	 */
	public static function bucket( array $sizes, int $requested ): int {
		foreach ( $sizes as $size ) {
			if ( $size >= $requested ) {
				return $size;
			}
		}

		return $sizes[count( $sizes ) - 1];
	}

	/**
	 * @param array<int|string, string> $urls size => URL, may be empty
	 * @param list<int> $sizes AVATAR or BANNER
	 * @param int $requested Displayed edge or width in device pixels
	 * @return string|null null when no thumbnail is available, so callers keep the original
	 */
	public static function pick( array $urls, array $sizes, int $requested ): ?string {
		$url = $urls[self::bucket( $sizes, $requested )] ?? null;

		return is_string( $url ) && $url !== '' ? $url : null;
	}

	/**
	 * @param array<int|string, string> $urls size => URL, may be empty
	 * @param list<int> $sizes AVATAR or BANNER
	 * @param int $css_px Displayed edge or width in CSS pixels
	 * @return string '' when no thumbnail is available
	 */
	public static function density_srcset( array $urls, array $sizes, int $css_px ): string {
		$candidates = [];
		$seen = [];
		foreach ( [ 1, 2 ] as $density ) {
			$url = self::pick( $urls, $sizes, $css_px * $density );
			// whitespace would split a srcset candidate
			if ( $url === null || isset( $seen[$url] ) || preg_match( '/\s/', $url ) ) {
				continue;
			}

			$seen[$url] = true;
			$candidates[] = $url . ' ' . $density . 'x';
		}

		return implode( ', ', $candidates );
	}

	/**
	 * Scales $width*$height to fit inside the box while maintaining the aspect ratio.
	 *
	 * @return array{0: int, 1: int}
	 */
	public static function fit( int $width, int $height, int $max_width, int $max_height ): array {
		if ( $width <= 0 || $height <= 0 ) {
			return [ 1, 1 ];
		}

		$scale = min( 1.0, $max_width / $width, $max_height / $height );

		return [ max( 1, (int)round( $width * $scale ) ), max( 1, (int)round( $height * $scale ) ) ];
	}

}
