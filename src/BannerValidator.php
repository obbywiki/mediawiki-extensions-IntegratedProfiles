<?php

namespace MediaWiki\Extension\IntegratedProfiles;

/**
 * Pure PHP MIME / size validation for banner uploads (also see AvatarValidator).
 */
class BannerValidator {

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
	public function validate_upload( string $tmp_path, int $size ): array {
		if ( $tmp_path === '' || !is_readable( $tmp_path ) || $size <= 0 || $size > $this->max_bytes ) {
			return [ 'ok' => false, 'error' => 'integratedprofiles-error-banner-size' ];
		}

		$mime = $this->sniff_mime( $tmp_path );
		$ext = $this->extension_for_mime( $mime );
		if ( $ext === null ) {
			return [ 'ok' => false, 'error' => 'integratedprofiles-error-banner-type' ];
		}

		$image_info = @getimagesize( $tmp_path );
		if ( $image_info === false ) {
			return [ 'ok' => false, 'error' => 'integratedprofiles-error-banner-type' ];
		}

		return [ 'ok' => true, 'ext' => $ext ];
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

		if ( function_exists( 'finfo_open' ) ) {
			$finfo = finfo_open( FILEINFO_MIME_TYPE );
			if ( $finfo ) {
				$mime = finfo_file( $finfo, $path );
				finfo_close( $finfo );
				
				if ( is_string( $mime ) && $mime !== '' ) {
					return $mime;
				}
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
