<?php

namespace MediaWiki\Extension\IntegratedProfiles;

/**
 * Image validation with avatar upload error messages. See ImageValidator.php.
 */
class AvatarValidator extends ImageValidator {

	protected const ERROR_PREFIX = 'integratedprofiles-error-avatar-';

	/**
	 * Determines whether the file is an animated GIF, animated WebP, APNG, or not.
	 *
	 * GIF parse failures are treated as animated. Other errors are treated as not animated.
	 *
	 * @return bool true if the file is animated, false otherwise
	 */
	public function is_animated( string $tmp_path, string $ext ): bool {
		return ImageAnimation::is_animated( $tmp_path, $ext );
	}

}
