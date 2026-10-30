<?php

namespace MediaWiki\Extension\IntegratedProfiles\Thumbnailer;

use GdImage;
use MediaWiki\Extension\IntegratedProfiles\ImageUploadSanitizer;
use MediaWiki\Extension\IntegratedProfiles\ThumbnailSizes;
use Throwable;

/**
 * PHP's bundled GD, the most widely available fallback. It has no animation support, so animated uploads are declined (and keep being served as originals) rather than flattened.
 *
 * This is here for compatibility. Preferably, everyone uses vips instead. Worth revisiting in the future if GD supports animations (GIF/WebP), because currently it just preserves the first frame only, so animated files are served as originals.
 */
class GdThumbnailer implements Thumbnailer {

	private const LOADERS = [
		'jpg' => 'imagecreatefromjpeg',
		'png' => 'imagecreatefrompng',
		'gif' => 'imagecreatefromgif',
		'webp' => 'imagecreatefromwebp',
	];

	private const BYTES_PER_PIXEL = 5; // bytes per pixel GD needs for a truecolor image, with headroom for its own bookkeeping

	private string $error = '';

	/**
	 * @param int|null $memory_limit Bytes available to PHP, -1 for unlimited. Defaults to memory_limit.
	 */
	public function __construct(
		private readonly ?int $memory_limit = null,
	) {
	}

	public function name(): string {
		return 'gd';
	}

	public function is_available(): bool {
		if ( !function_exists( 'imagewebp' ) || !function_exists( 'gd_info' ) ) {
			return false;
		}

		return !empty( gd_info()['WebP Support'] );
	}

	public function supports( string $ext, bool $animated ): bool {
		return !$animated && isset( self::LOADERS[$ext] ) && function_exists( self::LOADERS[$ext] );
	}

	public function render( string $src_path, string $ext, bool $animated, array $targets ): bool {
		$this->error = '';

		$info = @getimagesize( $src_path );
		if ( $info === false ) {
			$this->error = 'unreadable source';
			return false;
		}

		// prevent an OOM by preemptively refusing tasks that are too expensive
		if ( !$this->fits_in_memory( (int)$info[0], (int)$info[1], $targets ) ) {
			$this->error = 'not enough memory for ' . $info[0] . 'x' . $info[1];
			return false;
		}

		try {
			$image = $this->load( $src_path, $ext );
			if ( $image === null ) {
				return false;
			}

			foreach ( $targets as $target ) {
				if ( !$this->write( $image, $target ) ) {
					return false;
				}
			}

			return true;
		} catch ( Throwable $e ) {
			$this->error = get_class( $e ) . ': ' . $e->getMessage();
			return false;
		}
	}

	public function last_error(): string {
		return $this->error;
	}

	/**
	 * @param int $width
	 * @param int $height
	 * @param list<array{path: string, max_width: int, max_height: int}> $targets
	 * @return bool
	 */
	private function fits_in_memory( int $width, int $height, array $targets ): bool {
		$limit = $this->memory_limit ?? \wfShorthandToInteger( (string)ini_get( 'memory_limit' ), -1 );
		if ( $limit < 0 ) {
			return true;
		}

		$largest = 0;
		foreach ( $targets as $target ) {
			[ $w, $h ] = ThumbnailSizes::fit( $width, $height, $target['max_width'], $target['max_height'] );
			$largest = max( $largest, $w * $h );
		}

		// the source plus a rotated copy (one destination at a time)
		$needed = ( 2 * $width * $height + $largest ) * self::BYTES_PER_PIXEL;

		return memory_get_usage() + $needed < $limit;
	}

	private function load( string $src_path, string $ext ): ?GdImage {
		$loader = self::LOADERS[$ext];
		$image = @$loader( $src_path );
		if ( !$image instanceof GdImage ) {
			$this->error = $loader . ' failed';
			return null;
		}

		if ( !imageistruecolor( $image ) ) {
			// keeps palette transparency when resampling
			imagepalettetotruecolor( $image );
		}

		if ( $ext === 'jpg' ) {
			$data = file_get_contents( $src_path );
			$orientation = is_string( $data ) ? ImageUploadSanitizer::jpeg_orientation( $data ) : null;
			if ( $orientation !== null && $orientation !== 1 ) {
				$image = self::orient( $image, $orientation );
			}
		}

		return $image;
	}

	/**
	 * @param GdImage $image
	 * @param array{path: string, max_width: int, max_height: int} $target
	 * @return bool
	 */
	private function write( GdImage $image, array $target ): bool {
		$src_width = imagesx( $image );
		$src_height = imagesy( $image );
		[ $width, $height ] = ThumbnailSizes::fit( $src_width, $src_height, $target['max_width'], $target['max_height'] );

		$out = $image;
		if ( $width !== $src_width || $height !== $src_height ) {
			$out = imagecreatetruecolor( $width, $height );
			imagealphablending( $out, false );
			imagefill( $out, 0, 0, imagecolorallocatealpha( $out, 0, 0, 0, 127 ) );
			imagecopyresampled( $out, $image, 0, 0, 0, 0, $width, $height, $src_width, $src_height );
		}

		imagesavealpha( $out, true );
		if ( !imagewebp( $out, $target['path'], self::QUALITY ) ) {
			$this->error = 'imagewebp failed';
			return false;
		}

		return true;
	}

	/**
	 * Applies an EXIF orientation (2-8) to the pixels. imagerotate() turns counterclockwise.
	 */
	private static function orient( GdImage $image, int $orientation ): GdImage {
		// https://exiftool.org/TagNames/EXIF.html
		$angle = match ( $orientation ) {
			3 => 180,
			5, 6 => 270,
			7, 8 => 90,
			default => 0,
		};

		if ( $angle !== 0 ) {
			$rotated = imagerotate( $image, $angle, 0 );
			if ( $rotated instanceof GdImage ) {
				$image = $rotated;
			}
		}

		if ( in_array( $orientation, [ 2, 5, 7 ], true ) ) {
			imageflip( $image, IMG_FLIP_HORIZONTAL );
		} elseif ( $orientation === 4 ) {
			imageflip( $image, IMG_FLIP_VERTICAL );
		}

		return $image;
	}

}
