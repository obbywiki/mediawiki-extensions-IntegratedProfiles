<?php

namespace MediaWiki\Extension\IntegratedProfiles;

/**
 * Pure PHP MIME / size validation for avatar uploads (also see BannerValidator).
 */
class AvatarValidator {

	/** @var array<string, string> MIME type => file extension */
	public const MIME_MAP = [
		'image/jpeg' => 'jpg',
		'image/png' => 'png',
		'image/gif' => 'gif',
		'image/webp' => 'webp'
	];

	public function __construct(
		private readonly int $max_bytes,
	) {
	}

	/**
	 * @return array{ok: true, ext: string}|array{ok: false, error: string}
	 */
	public function validate_upload( string $tmp_path, int $size, bool $allow_animated = false ): array {
		if ( $tmp_path === '' || !is_readable( $tmp_path ) || $size <= 0 || $size > $this->max_bytes ) {
			return [ 'ok' => false, 'error' => 'integratedprofiles-error-avatar-size' ];
		}

		$mime = $this->sniff_mime( $tmp_path );
		$ext = $this->extension_for_mime( $mime );
		if ( $ext === null ) {
			return [ 'ok' => false, 'error' => 'integratedprofiles-error-avatar-type' ];
		}

		$image_info = @getimagesize( $tmp_path );
		if ( $image_info === false ) {
			return [ 'ok' => false, 'error' => 'integratedprofiles-error-avatar-type' ];
		}
		if ( !ImageLimits::allows_dimensions( $image_info[0], $image_info[1] ) ) {
			return [ 'ok' => false, 'error' => 'integratedprofiles-error-image-dimensions' ];
		}

		if ( !$allow_animated && ImageAnimation::is_animated( $tmp_path, $ext ) ) {
			return [ 'ok' => false, 'error' => 'integratedprofiles-error-avatar-animated' ];
		}

		return [ 'ok' => true, 'ext' => $ext ];
	}

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

	public function extension_for_mime( ?string $mime ): ?string {
		if ( $mime === null || $mime === '' ) {
			return null;
		}

		$normalized = strtolower( trim( $mime ) );
		$normalized = explode( ';', $normalized, 2 )[0];

		return self::MIME_MAP[$normalized] ?? null;
	}

	public function sniff_mime( string $path ): ?string {
		if ( !is_readable( $path ) ) {
			return null;
		}

		if ( extension_loaded( 'fileinfo' ) ) {
			try {
				$finfo = new \finfo( FILEINFO_MIME_TYPE );
				$mime = $finfo->file( $path );
				if ( is_string( $mime ) && $mime !== '' ) {
					return $mime;
				}
			} catch ( \Exception ) {
				// magic db failed to load, fall through
			}
		}

		if ( function_exists( 'mime_content_type' ) ) {
			$mime = mime_content_type( $path );
			if ( is_string( $mime ) && $mime !== '' ) {
				return $mime;
			}
		}

		$image_info = @getimagesize( $path );
		if ( is_array( $image_info ) && isset( $image_info['mime'] ) ) {
			return (string)$image_info['mime'];
		}

		return null;
	}

}
