<?php

namespace MediaWiki\Extension\IntegratedProfiles;

use MediaWiki\Status\Status;

/**
 * ...
 */
final class ImageUploadSanitizer {

	/** @var list<int> APP1 EXIF/XMP, APP13 IPTC, COM */
	private const JPEG_DROP_MARKERS = [ 0xE1, 0xED, 0xFE ];

	/** @var list<string> */
	private const PNG_DROP = [ 'tEXt', 'zTXt', 'iTXt', 'tIME', 'eXIf' ];

	/** @var list<string> */
	private const WEBP_DROP = [ 'EXIF', 'XMP ' ];

	/**
	 * Sanitize $src_path into a temp file. Caller must unlink the temp path.
	 *
	 * @return array{ok: true, path: string}|array{ok: false, error: string}
	 */
	public static function sanitize_file( string $src_path, string $ext ): array {
		if ( $src_path === '' || !is_readable( $src_path ) ) {
			return [ 'ok' => false, 'error' => 'integratedprofiles-error-image-unsafe' ];
		}

		$raw = file_get_contents( $src_path );
		if ( !is_string( $raw ) || $raw === '' ) {
			return [ 'ok' => false, 'error' => 'integratedprofiles-error-image-unsafe' ];
		}

		$clean = self::strip_container( $raw, $ext );
		if ( $clean === null || $clean === '' ) {
			$clean = $raw;
		}

		$tmp = tempnam( sys_get_temp_dir(), 'ipimg' );
		if ( $tmp === false || file_put_contents( $tmp, $clean ) === false ) {
			if ( $tmp !== false && is_file( $tmp ) ) {
				unlink( $tmp );
			}
			return [ 'ok' => false, 'error' => 'integratedprofiles-error-image-unsafe' ];
		}

		return [ 'ok' => true, 'path' => $tmp ];
	}

	/**
	 * Sanitize then invoke $store with the temp path, always deleting the temp file.
	 *
	 * @param callable(string): Status $store
	 */
	public static function with_sanitized_file( string $src_path, string $ext, callable $store ): Status {
		$sanitized = self::sanitize_file( $src_path, $ext );
		if ( !$sanitized['ok'] ) {
			return Status::newFatal( $sanitized['error'] );
		}

		try {
			return $store( $sanitized['path'] );
		} finally {
			if ( is_file( $sanitized['path'] ) ) {
				unlink( $sanitized['path'] );
			}
		}
	}

	private static function strip_container( string $data, string $ext ): ?string {
		return match ( $ext ) {
			'jpg' => self::strip_jpeg( $data ),
			'png' => self::strip_png( $data ),
			'gif' => self::strip_gif( $data ),
			'webp' => self::strip_webp( $data ),
			default => null,
		};
	}

	/**
	 * Drop metadata APPn/COM by segment length; copy from SOS onward unparsed.
	 */
	private static function strip_jpeg( string $data ): ?string {
		$len = strlen( $data );
		if ( $len < 4 || !str_starts_with( $data, "\xFF\xD8" ) ) {
			return null;
		}

		$out = "\xFF\xD8";
		$offset = 2;

		while ( $offset + 1 < $len ) {
			if ( $data[$offset] !== "\xFF" ) {
				return null;
			}

			$fill = $offset;
			while ( $fill < $len && $data[$fill] === "\xFF" ) {
				$fill++;
			}
			if ( $fill >= $len ) {
				return null;
			}

			$marker = ord( $data[$fill] );
			$seg_start = $fill - 1;

			if ( $marker === 0xDA || $marker === 0xD9 ) {
				$out .= substr( $data, $seg_start );
				break;
			}

			if ( $marker >= 0xD0 && $marker <= 0xD7 ) {
				$out .= substr( $data, $seg_start, $fill + 1 - $seg_start );
				$offset = $fill + 1;
				continue;
			}

			if ( $fill + 3 > $len ) {
				return null;
			}

			$seg_len = unpack( 'n', substr( $data, $fill + 1, 2 ) )[1];
			$seg_end = $fill + 1 + $seg_len;
			if ( $seg_len < 2 || $seg_end > $len ) {
				return null;
			}

			if ( !in_array( $marker, self::JPEG_DROP_MARKERS, true ) ) {
				$out .= substr( $data, $seg_start, $seg_end - $seg_start );
			}

			$offset = $seg_end;
		}

		$eoi = strrpos( $out, "\xFF\xD9" );
		if ( $eoi !== false ) {
			$out = substr( $out, 0, $eoi + 2 );
		}

		return strlen( $out ) > 2 ? $out : null;
	}

	private static function strip_png( string $data ): ?string {
		$sig = "\x89PNG\r\n\x1a\n";
		if ( !str_starts_with( $data, $sig ) ) {
			return null;
		}

		$len = strlen( $data );
		$offset = 8;
		$out = $sig;
		$saw_idat = false;
		$saw_iend = false;

		while ( $offset + 12 <= $len ) {
			$chunk_len = unpack( 'N', substr( $data, $offset, 4 ) )[1];
			if ( $chunk_len < 0 || $offset + 12 + $chunk_len > $len ) {
				return null;
			}

			$type = substr( $data, $offset + 4, 4 );
			$total = 12 + $chunk_len;
			if ( !in_array( $type, self::PNG_DROP, true ) ) {
				$out .= substr( $data, $offset, $total );
			}

			if ( $type === 'IDAT' ) {
				$saw_idat = true;
			}
			if ( $type === 'IEND' ) {
				$saw_iend = true;
				break;
			}

			$offset += $total;
		}

		return ( $saw_idat && $saw_iend ) ? $out : null;
	}

	private static function strip_gif( string $data ): ?string {
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

		$out = substr( $data, 0, $offset );
		$frames = 0;

		while ( $offset < $len ) {
			$intro = ord( $data[$offset] );
			if ( $intro === 0x3B ) {
				$out .= "\x3B";
				return $frames >= 1 ? $out : null;
			}
			if ( $intro === 0x21 ) {
				if ( $offset + 2 > $len ) {
					return null;
				}

				$label = ord( $data[$offset + 1] );
				$block_start = $offset;
				$skipped = self::skip_gif_sub_blocks( $data, $offset + 2, $len );

				if ( $skipped === null ) {
					return null;
				}
				if ( $label !== 0xFE ) {
					$out .= substr( $data, $block_start, $skipped - $block_start );
				}

				$offset = $skipped;
				continue;
			}
			if ( $intro === 0x2C ) {
				if ( $offset + 10 > $len ) {
					return null;
				}
				$block_start = $offset;
				$img_packed = ord( $data[$offset + 9] );
				$has_lct = ( $img_packed & 0x80 ) !== 0;
				$lct_size = $has_lct ? 3 * ( 1 << ( ( $img_packed & 0x07 ) + 1 ) ) : 0;
				$lz_start = $offset + 10 + $lct_size;
				if ( $lz_start >= $len ) {
					return null;
				}

				$skipped = self::skip_gif_sub_blocks( $data, $lz_start + 1, $len );
				if ( $skipped === null ) {
					return null;
				}

				$out .= substr( $data, $block_start, $skipped - $block_start );
				$offset = $skipped;
				$frames++;
				continue;
			}

			return null;
		}

		return null;
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

	private static function strip_webp( string $data ): ?string {
		$len = strlen( $data );
		if ( $len < 12 || substr( $data, 0, 4 ) !== 'RIFF' || substr( $data, 8, 4 ) !== 'WEBP' ) {
			return null;
		}

		$riff_size = unpack( 'V', substr( $data, 4, 4 ) )[1];
		$end = 8 + $riff_size;
		if ( $end > $len || $end < 12 ) {
			return null;
		}

		$offset = 12;
		$chunks = '';

		while ( $offset + 8 <= $end ) {
			$fourcc = substr( $data, $offset, 4 );
			$size = unpack( 'V', substr( $data, $offset + 4, 4 ) )[1];
			$payload_start = $offset + 8;
			if ( $payload_start + $size > $end ) {
				return null;
			}

			$payload = substr( $data, $payload_start, $size );
			$next = $payload_start + $size + ( $size % 2 );

			if ( $fourcc === 'VP8X' && strlen( $payload ) >= 1 ) {
				$payload[0] = chr( ord( $payload[0] ) & ~0x0C );
			}

			if ( !in_array( $fourcc, self::WEBP_DROP, true ) ) {
				$chunks .= $fourcc . pack( 'V', strlen( $payload ) ) . $payload;
				if ( strlen( $payload ) % 2 ) {
					$chunks .= "\x00";
				}
			}

			if ( $next <= $offset ) {
				return null;
			}
			$offset = $next;
		}

		if ( $chunks === '' ) {
			return null;
		}

		return 'RIFF' . pack( 'V', 4 + strlen( $chunks ) ) . 'WEBP' . $chunks;
	}

}
