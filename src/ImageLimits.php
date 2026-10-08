<?php

namespace MediaWiki\Extension\IntegratedProfiles;

/**
 * Image dimension limits shared by avatar and banner upload validation.
 */
final class ImageLimits {

	// sync with src/utils/image_dimensions.ts.
	public const MAX_PIXELS = 16000000;
	public const MAX_EDGE = 16384;

	/**
	 * @param int $width
	 * @param int $height
	 * @return bool
	 */
	public static function allows_dimensions( int $width, int $height ): bool {
		return $width > 0 && $height > 0 &&
			$width <= self::MAX_EDGE && $height <= self::MAX_EDGE &&
			$width * $height <= self::MAX_PIXELS;
	}

}
