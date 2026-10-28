<?php

namespace MediaWiki\Extension\IntegratedProfiles;

/**
 * Detects animated GIF, animated WebP, and APNG. Shared by avatar and banner validation. A lot of the code here was previously in AvatarValidator.php.
 *
 * TODO: support animated AVI/AVIF
 *
 * @see AvatarValidator
 * @see BannerValidator
 */
final class ImageAnimation {

	/**
	 * Determines whether the file is an animated GIF, animated WebP, APNG, or none.
	 *
	 * GIF parse failures are treated as animated. Other errors are treated as not animated.
	 *
	 * @return bool true if the file is animated, false otherwise
	 */
	public static function is_animated( string $tmp_path, string $ext ): bool {
		if ( !in_array( $ext, [ 'gif', 'webp', 'png' ], true ) ) {
			return false;
		}

		if ( !is_readable( $tmp_path ) ) {
			return $ext === 'gif';
		}

		$data = file_get_contents( $tmp_path );
		if ( !is_string( $data ) || $data === '' ) {
			return $ext === 'gif';
		}

		return match ( $ext ) {
			'gif' => self::gif_is_animated( $data ) !== false,
			'webp' => self::webp_is_animated( $data ),
			'png' => self::png_is_animated( $data ),
			default => false
		};
	}

	/**
	 * @return bool|null true when animated, false when a single-frame GIF, null when inconclusive
	 */
	private static function gif_is_animated( string $data ): ?bool {
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

				$skipped = self::skip_gif_sub_blocks( $data, $offset, $len );

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
				$skipped = self::skip_gif_sub_blocks( $data, $offset, $len );

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

	private static function skip_gif_sub_blocks( string $data, int $offset, int $len ): ?int {
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

	private static function webp_is_animated( string $data ): bool {
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

	private static function png_is_animated( string $data ): bool {
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
