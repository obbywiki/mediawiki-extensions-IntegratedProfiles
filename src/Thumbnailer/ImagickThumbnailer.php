<?php

namespace MediaWiki\Extension\IntegratedProfiles\Thumbnailer;

use Imagick;
use ImagickPixel;
use MediaWiki\Extension\IntegratedProfiles\ThumbnailSizes;
use Throwable;

/**
 * ImageMagick through the PHP imagick extension. Keeps GIF/WebP animation, but decodes every frame in memory, so animated sources are capped by total area.
 *
 * Thank you alistair3149! Parts of the implementation were inspired by Extension:Thumbro.
 */
class ImagickThumbnailer implements Thumbnailer {

	/** @var list<string> */
	private const ANIMATED_EXTENSIONS = [ 'gif', 'webp' ]; // TODO: avif

	public const MAX_ANIMATED_AREA = 25000000; // frames*width*height (matches ThumbroMaxAnimatedArea)

	private string $error = '';

	public function name(): string {
		return 'imagick';
	}

	public function is_available(): bool {
		try {
			return extension_loaded( 'imagick' ) && Imagick::queryFormats( 'WEBP' ) !== [];
		} catch ( Throwable ) {
			return false;
		}
	}

	public function supports( string $ext, bool $animated ): bool {
		return in_array( $ext, self::EXTENSIONS, true ) && ( !$animated || in_array( $ext, self::ANIMATED_EXTENSIONS, true ) );
	}

	public function render( string $src_path, string $ext, bool $animated, array $targets ): bool {
		$this->error = '';

		try {
			$source = self::source( $src_path, $ext );
			return $animated ? $this->render_animated( $source, $targets ) : $this->render_static( $source, $targets );
		} catch ( Throwable $e ) {
			$this->error = get_class( $e ) . ': ' . $e->getMessage();
			return false;
		}
	}

	public function last_error(): string {
		return $this->error;
	}

	/**
	 * Pins the coder to the validated extension, so ImageMagick never picks one (MVG, MSL, SVG, text, ...) from the file's contents.
	 */
	private static function source( string $src_path, string $ext ): string {
		return match ( $ext ) {
			'jpg' => 'jpeg',
			'png' => 'png',
			'gif' => 'gif',
			'webp' => 'webp',
		} . ':' . $src_path;
	}

	/**
	 * @param string $source Path with a coder prefix, see source()
	 * @param list<array{path: string, max_width: int, max_height: int}> $targets
	 * @return bool
	 */
	private function render_static( string $source, array $targets ): bool {
		$image = new Imagick();
		try {
			// first frame only (so  a single frame GIF/WebP does not decode extra frames)
			$image->readImage( $source . '[0]' );
			self::orient( $image );
			if ( $image->getImageColorspace() === Imagick::COLORSPACE_CMYK ) {
				$image->transformImageColorspace( Imagick::COLORSPACE_SRGB );
			}

			foreach ( $targets as $target ) {
				[ $width, $height ] = ThumbnailSizes::fit(
					$image->getImageWidth(), $image->getImageHeight(), $target['max_width'], $target['max_height']
				);

				$copy = clone $image;
				try {
					// also strips profiles and comments
					$copy->thumbnailImage( $width, $height );
					self::prepare_webp( $copy );
					if ( !$copy->writeImage( 'webp:' . $target['path'] ) ) {
						$this->error = 'writeImage failed';
						return false;
					}
				} finally {
					$copy->clear();
				}
			}

			return true;
		} finally {
			$image->clear();
		}
	}

	/**
	 * @param string $source Path with a coder prefix, see source()
	 * @param list<array{path: string, max_width: int, max_height: int}> $targets
	 * @return bool
	 */
	private function render_animated( string $source, array $targets ): bool {
		$probe = new Imagick();
		try {
			$probe->pingImage( $source );
			$area = $probe->getNumberImages() * $probe->getImageWidth() * $probe->getImageHeight();
		} finally {
			$probe->clear();
		}

		if ( $area > self::MAX_ANIMATED_AREA ) {
			$this->error = 'animated area ' . $area . ' exceeds ' . self::MAX_ANIMATED_AREA;
			return false;
		}

		$image = new Imagick( $source );
		$frames = $image->coalesceImages();
		$image->clear();

		try {
			$canvas_width = $frames->getImageWidth();
			$canvas_height = $frames->getImageHeight();

			foreach ( $targets as $target ) {
				[ $width, $height ] = ThumbnailSizes::fit( $canvas_width, $canvas_height, $target['max_width'], $target['max_height'] );

				$copy = clone $frames;
				try {
					foreach ( $copy as $frame ) {
						$frame->thumbnailImage( $width, $height );
						$frame->setImagePage( $width, $height, 0, 0 );
						self::prepare_webp( $frame );
					}

					if ( !$copy->writeImages( 'webp:' . $target['path'], true ) ) {
						$this->error = 'writeImages failed';
						return false;
					}
				} finally {
					$copy->clear();
				}
			}

			return true;
		} finally {
			$frames->clear();
		}
	}

	private static function prepare_webp( Imagick $image ): void {
		$image->setImageFormat( 'webp' );
		$image->setImageCompressionQuality( self::QUALITY );
		$image->setOption( 'webp:method', '4' );
		$image->stripImage();
	}

	/**
	 * Applies the EXIF orientation tag to the pixels so that the stripped thumbnail stays the right way up.
	 */
	private static function orient( Imagick $image ): void {
		$background = new ImagickPixel( 'none' );

		// https://exiftool.org/TagNames/EXIF.html

		match ( $image->getImageOrientation() ) {
			Imagick::ORIENTATION_TOPRIGHT => $image->flopImage(),
			Imagick::ORIENTATION_BOTTOMRIGHT => $image->rotateImage( $background, 180 ),
			Imagick::ORIENTATION_BOTTOMLEFT => $image->flipImage(),
			Imagick::ORIENTATION_LEFTTOP => $image->transposeImage(),
			Imagick::ORIENTATION_RIGHTTOP => $image->rotateImage( $background, 90 ),
			Imagick::ORIENTATION_RIGHTBOTTOM => $image->transverseImage(),
			Imagick::ORIENTATION_LEFTBOTTOM => $image->rotateImage( $background, 270 ),
			default => null,
		};

		$image->setImageOrientation( Imagick::ORIENTATION_TOPLEFT );
	}

}
