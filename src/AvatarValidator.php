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

		if ( !$allow_animated && $this->is_animated( $tmp_path, $ext ) ) {
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
		if ( !is_readable( $tmp_path ) ) {
			return $ext === 'gif';
		}

		$data = file_get_contents( $tmp_path );
		if ( !is_string( $data ) || $data === '' ) {
			return $ext === 'gif';
		}

		return match ( $ext ) {
			'gif' => $this->gif_is_animated( $data ) !== false,
			'webp' => $this->webp_is_animated( $data ),
			'png' => $this->png_is_animated( $data ),
			default => false
		};
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

	/**
	 * @return bool|null true when animated, false when a single-frame GIF, null when inconclusive
	 */
	private function gif_is_animated( string $data ): ?bool {
		$len = strlen( $data );
		if ( $len < 13 ) {
			return null;
		}

		$sig = substr( $data, 0, 6 );
		if ( $sig !== 'GIF87a' && $sig !== 'GIF89a' ) {
			return null;
		}

		$packed = ord( $data[10] );
		$has_gct = ( $packed & 0x80 ) !== 0;
		$gct_size = $has_gct ? 3 * ( 1 << ( ( $packed & 0x07 ) + 1 ) ) : 0;
		$offset = 13 + $gct_size;

		if ( $offset > $len ) {
			return null;
		}

		$frames = 0;
		$has_netscape = false;

		while ( $offset < $len ) {
			$intro = ord( $data[$offset] );
			if ( $intro === 0x3B ) {
				break;
			}
			if ( $intro === 0x21 ) {
				if ( $offset + 2 > $len ) {
					return null;
				}

				$label = ord( $data[$offset + 1] );
				$offset += 2;

				if ( $label === 0xFF && $offset < $len ) {
					$app_len = ord( $data[$offset] );
					if ( $offset + 1 + $app_len <= $len ) {
						$app = substr( $data, $offset + 1, $app_len );
						if ( str_starts_with( $app, 'NETSCAPE' ) ) {
							$has_netscape = true;
						}
					}
				}

				$skipped = $this->skip_gif_sub_blocks( $data, $offset, $len );

				if ( $skipped === null ) {
					return null;
				}

				$offset = $skipped;
				continue;
			}
			if ( $intro === 0x2C ) {
				if ( $offset + 10 > $len ) {
					return null;
				}

				$img_packed = ord( $data[$offset + 9] );
				$has_lct = ( $img_packed & 0x80 ) !== 0;
				$lct_size = $has_lct ? 3 * ( 1 << ( ( $img_packed & 0x07 ) + 1 ) ) : 0;
				$offset += 10 + $lct_size;

				if ( $offset >= $len ) {
					return null;
				}

				$offset++;
				$skipped = $this->skip_gif_sub_blocks( $data, $offset, $len );

				if ( $skipped === null ) {
					return null;
				}

				$offset = $skipped;
				$frames++;
				continue;
			}
			return null;
		}

		if ( $frames < 1 ) {
			return null;
		}

		return $frames > 1 || $has_netscape;
	}

	private function skip_gif_sub_blocks( string $data, int $offset, int $len ): ?int {
		while ( $offset < $len ) {
			$size = ord( $data[$offset] );
			$offset++;

			if ( $size === 0 ) {
				return $offset;
			}

			$offset += $size;
			if ( $offset > $len ) {
				return null;
			}
		}

		return null;
	}

	private function webp_is_animated( string $data ): bool {
		$len = strlen( $data );
		if ( $len < 12 ) {
			return false;
		}
		if ( substr( $data, 0, 4 ) !== 'RIFF' || substr( $data, 8, 4 ) !== 'WEBP' ) {
			return false;
		}

		$offset = 12;
		while ( $offset + 8 <= $len ) {
			$fourcc = substr( $data, $offset, 4 );
			$size = unpack( 'V', substr( $data, $offset + 4, 4 ) )[1];

			if ( $fourcc === 'ANIM' || $fourcc === 'ANMF' ) {
				return true;
			}

			if ( $fourcc === 'VP8X' && $offset + 8 < $len ) {
				$flags = ord( $data[$offset + 8] );
				if ( ( $flags & 0x02 ) !== 0 ) {
					return true;
				}
			}

			$next = $offset + 8 + $size + ( $size % 2 );
			if ( $next <= $offset ) {
				return false;
			}

			$offset = $next;
		}

		return false;
	}

	private function png_is_animated( string $data ): bool {
		$sig = "\x89PNG\r\n\x1a\n";
		if ( !str_starts_with( $data, $sig ) ) {
			return false;
		}

		$len = strlen( $data );
		$offset = 8;
		while ( $offset + 12 <= $len ) {
			$chunk_len = unpack( 'N', substr( $data, $offset, 4 ) )[1];
			if ( $chunk_len < 0 ) {
				return false;
			}

			$type = substr( $data, $offset + 4, 4 );
			if ( $type === 'acTL' ) {
				return true;
			}

			if ( $type === 'IEND' || $type === 'IDAT' ) {
				return false;
			}

			$offset += 12 + $chunk_len;
			if ( $offset > $len ) {
				return false;
			}
		}

		return false;
	}

}
