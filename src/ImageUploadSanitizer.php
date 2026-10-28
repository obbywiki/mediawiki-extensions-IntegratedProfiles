<?php

namespace MediaWiki\Extension\IntegratedProfiles;

use MediaWiki\Status\Status;
use Wikimedia\FileBackend\FSFile\TempFSFileFactory;

/**
 * Strips metadata from uploaded avatars and banners by rewriting the container without re-encoding pixels (so animation survives).
 */
class ImageUploadSanitizer {

	private const ERROR = 'integratedprofiles-error-image-unsafe';

	/** @var list<string> */
	private const PNG_KEEP = [ 'IHDR', 'PLTE', 'IDAT', 'IEND', 'tRNS', 'gAMA', 'cHRM', 'sRGB', 'iCCP', 'sBIT', 'pHYs', 'cICP', 'mDCV', 'cLLI', 'acTL', 'fcTL', 'fdAT' ];

	/** @var list<string> */
	private const WEBP_KEEP = [ 'VP8 ', 'VP8L', 'VP8X', 'ALPH', 'ANIM', 'ANMF', 'ICCP' ];

	/** @var list<string> chunks allowed inside an ANMF frame payload */
	private const WEBP_FRAME_KEEP = [ 'VP8 ', 'VP8L', 'ALPH' ];

	/** @var list<string> GIF application extension identifiers (8 byte id + 3 byte auth code) */
	private const GIF_APP_KEEP = [ 'NETSCAPE2.0', 'ANIMEXTS1.0' ];

	public function __construct(
		private readonly TempFSFileFactory $temp_factory,
	) {
	}

	/**
	 * Sanitize $src_path into a temp file, re-check the result, then invoke $store with the temp path.
	 * The temp file is always deleted afterwards.
	 *
	 * @param string $src_path Local filesystem path to the validated upload
	 * @param string $ext File extension from validation
	 * @param callable(string): Status $store
	 */
	public function with_sanitized_file( string $src_path, string $ext, callable $store ): Status {
		$raw = ( $src_path !== '' && is_readable( $src_path ) ) ? file_get_contents( $src_path ) : false;
		if ( !is_string( $raw ) || $raw === '' ) {
			return Status::newFatal( self::ERROR );
		}

		$clean = self::sanitize( $raw, $ext );
		if ( $clean === null ) {
			return Status::newFatal( self::ERROR );
		}

		$tmp = $this->temp_factory->newTempFSFile( 'ipimg', $ext );
		if ( $tmp === null ) {
			return Status::newFatal( self::ERROR );
		}

		try {
			$path = $tmp->getPath();
			if ( file_put_contents( $path, $clean ) === false ) {
				return Status::newFatal( self::ERROR );
			}

			$error = self::recheck( $path, $ext );
			if ( $error !== null ) {
				return Status::newFatal( $error );
			}

			return $store( $path );
		} finally {
			$tmp->purge();
		}
	}

	/**
	 * Rebuilds $data without metadata.
	 *
	 * @return string|null Clean bytes, or null if the file could not be parsed and must be rejected
	 */
	public static function sanitize( string $data, string $ext ): ?string {
		$clean = match ( $ext ) {
			'jpg' => self::strip_jpeg( $data ),
			'png' => self::strip_png( $data ),
			'gif' => self::strip_gif( $data ),
			'webp' => self::strip_webp( $data ),
			default => null
		};

		return ( $clean === null || $clean === '' ) ? null : $clean;
	}

	/**
	 * Validation ran on the raw upload, so check that what gets stored is still an image of the same type within limits.
	 *
	 * @return string|null Error message key, or null if the output is fine
	 */
	private static function recheck( string $path, string $ext ): ?string {
		$info = @getimagesize( $path );
		if ( $info === false || ( AvatarValidator::MIME_MAP[$info['mime'] ?? ''] ?? null ) !== $ext ) {
			return self::ERROR;
		}
		if ( !ImageLimits::allows_dimensions( $info[0], $info[1] ) ) {
			return 'integratedprofiles-error-image-dimensions';
		}

		return null;
	}

	private static function strip_jpeg( string $data ): ?string {
		$len = strlen( $data );
		if ( $len < 4 || !str_starts_with( $data, "\xFF\xD8" ) ) {
			return null;
		}

		$segments = [];
		$orientation = null;
		$saw_frame = false;
		$saw_scan = false;
		$offset = 2;

		while ( true ) {
			// skip stray bytes between segments
			$marker_at = strpos( $data, "\xFF", $offset );
			if ( $marker_at === false ) {
				return null;
			}

			$fill = $marker_at;
			while ( $fill < $len && $data[$fill] === "\xFF" ) {
				$fill++;
			}
			if ( $fill >= $len ) {
				return null;
			}

			$marker = ord( $data[$fill] );
			$offset = $fill + 1;

			if ( $marker === 0xD9 ) {
				break;
			}
			if ( $marker === 0x00 || $marker === 0xD8 || ( $marker >= 0xD0 && $marker <= 0xD7 ) ) {
				// stuffed byte, nested SOI, or RSTn outside a scan
				return null;
			}

			if ( $offset + 2 > $len ) {
				return null;
			}

			$seg_len = unpack( 'n', substr( $data, $offset, 2 ) )[1];
			$seg_end = $offset + $seg_len;
			if ( $seg_len < 2 || $seg_end > $len ) {
				return null;
			}

			$payload = substr( $data, $offset + 2, $seg_len - 2 );

			if ( $marker === 0xDA ) {
				if ( !$saw_frame ) {
					return null;
				}

				$scan_end = self::jpeg_scan_end( $data, $seg_end, $len );
				if ( $scan_end === null ) {
					return null;
				}

				$segments[] = "\xFF\xDA" . substr( $data, $offset, $scan_end - $offset );
				$saw_scan = true;
				$offset = $scan_end;
				continue;
			}

			if ( $marker === 0xE1 && $orientation === null ) {
				$orientation = self::exif_orientation( $payload );
			}

			$kept = self::jpeg_keep_segment( $marker, $payload );
			if ( $kept !== null ) {
				$segments[] = "\xFF" . chr( $marker ) . pack( 'n', strlen( $kept ) + 2 ) . $kept;
			}
			if ( self::is_jpeg_sof( $marker ) ) {
				$saw_frame = true;
			}

			$offset = $seg_end;
		}

		if ( !$saw_scan ) {
			return null;
		}

		if ( $orientation !== null && $orientation !== 1 ) {
			$app1 = self::exif_orientation_segment( $orientation );
			// JFIF requires APP0 to come first
			$at = ( $segments !== [] && str_starts_with( $segments[0], "\xFF\xE0" ) ) ? 1 : 0;
			array_splice( $segments, $at, 0, [ $app1 ] );
		}

		return "\xFF\xD8" . implode( '', $segments ) . "\xFF\xD9";
	}

	/**
	 * @return string|null Payload to keep (possibly rewritten), or null to drop the segment
	 */
	private static function jpeg_keep_segment( int $marker, string $payload ): ?string {
		if ( $marker === 0xE0 ) {
			if ( !str_starts_with( $payload, "JFIF\x00" ) || strlen( $payload ) < 14 ) {
				return null;
			}

			// strip the embedded JFIF thumb
			return substr( $payload, 0, 12 ) . "\x00\x00";
		}

		if ( $marker === 0xE2 ) {
			return str_starts_with( $payload, "ICC_PROFILE\x00" ) ? $payload : null;
		}
		if ( $marker === 0xEE ) {
			return str_starts_with( $payload, 'Adobe' ) ? $payload : null;
		}

		// DQT, DHT, DAC, DRI, DNL, SOFn
		if ( in_array( $marker, [ 0xDB, 0xC4, 0xCC, 0xDD, 0xDC ], true ) || self::is_jpeg_sof( $marker ) ) {
			return $payload;
		}

		return null;
	}

	private static function is_jpeg_sof( int $marker ): bool {
		return $marker >= 0xC0 && $marker <= 0xCF && $marker !== 0xC4 && $marker !== 0xC8 && $marker !== 0xCC;
	}

	/**
	 * Find the end of entropy-coded data starting at $offset (the first FF xx where xx is not 00, FF or RSTn).
	 *
	 * @return int|null Offset of the 0xFF that starts the next marker
	 */
	private static function jpeg_scan_end( string $data, int $offset, int $len ): ?int {
		while ( true ) {
			$offset = strpos( $data, "\xFF", $offset );
			if ( $offset === false ) {
				return null;
			}

			$next = $offset + 1;
			while ( $next < $len && $data[$next] === "\xFF" ) {
				$next++;
			}
			if ( $next >= $len ) {
				return null;
			}

			$byte = ord( $data[$next] );
			if ( $byte !== 0x00 && ( $byte < 0xD0 || $byte > 0xD7 ) ) {
				return $next - 1;
			}
			$offset = $next + 1;
		}
	}

	/**
	 * Read the IFD0 Orientation tag from an APP1 payload.
	 */
	private static function exif_orientation( string $payload ): ?int {
		if ( !str_starts_with( $payload, "Exif\x00\x00" ) ) {
			return null;
		}

		$tiff = substr( $payload, 6 );
		$tiff_len = strlen( $tiff );
		if ( $tiff_len < 8 ) {
			return null;
		}

		$order = substr( $tiff, 0, 2 );
		if ( $order !== 'II' && $order !== 'MM' ) {
			return null;
		}

		$u16 = $order === 'II' ? 'v' : 'n';
		$u32 = $order === 'II' ? 'V' : 'N';

		if ( unpack( $u16, substr( $tiff, 2, 2 ) )[1] !== 42 ) {
			return null;
		}

		$ifd = unpack( $u32, substr( $tiff, 4, 4 ) )[1];
		if ( $ifd < 8 || $ifd + 2 > $tiff_len ) {
			return null;
		}

		$count = unpack( $u16, substr( $tiff, $ifd, 2 ) )[1];
		for ( $i = 0; $i < $count; $i++ ) {
			$entry = $ifd + 2 + $i * 12;
			if ( $entry + 12 > $tiff_len ) {
				return null;
			}

			if ( unpack( $u16, substr( $tiff, $entry, 2 ) )[1] !== 0x0112 ) {
				continue;
			}

			// SHORT, count 1
			if ( unpack( $u16, substr( $tiff, $entry + 2, 2 ) )[1] !== 3 ) {
				return null;
			}

			$value = unpack( $u16, substr( $tiff, $entry + 8, 2 ) )[1];
			return ( $value >= 1 && $value <= 8 ) ? $value : null;
		}

		return null;
	}

	/**
	 * Build an APP1 segment containing only the Orientation tag (in  order to preserve images rotated via a phone camera app).
	 */
	private static function exif_orientation_segment( int $orientation ): string {
		$tiff = "MM\x00\x2A" . pack( 'N', 8 )
			. pack( 'n', 1 )
			. pack( 'nnN', 0x0112, 3, 1 ) . pack( 'n', $orientation ) . "\x00\x00"
			. pack( 'N', 0 );
		$payload = "Exif\x00\x00" . $tiff;

		return "\xFF\xE1" . pack( 'n', strlen( $payload ) + 2 ) . $payload;
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

		while ( $offset + 12 <= $len ) {
			$chunk_len = unpack( 'N', substr( $data, $offset, 4 ) )[1];
			if ( $chunk_len > 0x7FFFFFFF || $offset + 12 + $chunk_len > $len ) {
				return null;
			}

			$type = substr( $data, $offset + 4, 4 );
			$total = 12 + $chunk_len;
			if ( $offset === 8 && $type !== 'IHDR' ) {
				return null;
			}
			if ( in_array( $type, self::PNG_KEEP, true ) ) {
				$out .= substr( $data, $offset, $total );
			}

			if ( $type === 'IDAT' ) {
				$saw_idat = true;
			}
			if ( $type === 'IEND' ) {
				return $saw_idat ? $out : null;
			}

			$offset += $total;
		}

		return null;
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

				$block_start = $offset;
				$skipped = self::skip_gif_sub_blocks( $data, $offset + 2, $len );
				if ( $skipped === null ) {
					return null;
				}
				if ( self::gif_keep_extension( $data, $offset ) ) {
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

	/**
	 * Keep graphic control extensions and the looping application extensions while stripping any other application data (comments, plain text, XMP, etc.).
	 */
	private static function gif_keep_extension( string $data, int $offset ): bool {
		$label = ord( $data[$offset + 1] );
		if ( $label === 0xF9 ) {
			return true;
		}

		if ( $label === 0xFF ) {
			return ( $data[$offset + 2] ?? '' ) === "\x0B" && in_array( substr( $data, $offset + 3, 11 ), self::GIF_APP_KEEP, true );
		}

		return false;
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

		if ( !in_array( substr( $data, 12, 4 ), [ 'VP8 ', 'VP8L', 'VP8X' ], true ) ) {
			return null;
		}

		$chunks = self::filter_riff_chunks( $data, 12, $end, self::WEBP_KEEP );
		if ( $chunks === null || $chunks === '' ) {
			return null;
		}

		return 'RIFF' . pack( 'V', 4 + strlen( $chunks ) ) . 'WEBP' . $chunks;
	}

	/**
	 * @param string $data
	 * @param int $offset First chunk header
	 * @param int $end End of the enclosing chunk
	 * @param list<string> $keep
	 * @return string|null Re-serialised allowlisted chunks, or null on a malformed chunk
	 */
	private static function filter_riff_chunks( string $data, int $offset, int $end, array $keep ): ?string {
		$chunks = '';

		while ( $offset + 8 <= $end ) {
			$fourcc = substr( $data, $offset, 4 );
			$size = unpack( 'V', substr( $data, $offset + 4, 4 ) )[1];
			$payload_start = $offset + 8;
			if ( $payload_start + $size > $end ) {
				return null;
			}

			$payload = substr( $data, $payload_start, $size );
			$offset = $payload_start + $size + ( $size % 2 );

			if ( !in_array( $fourcc, $keep, true ) ) {
				continue;
			}

			if ( $fourcc === 'VP8X' && $size >= 1 ) {
				// clear the EXIF and XMP flags
				$payload[0] = chr( ord( $payload[0] ) & ~0x0C );
			}

			if ( $fourcc === 'ANMF' ) {
				if ( $size < 16 ) {
					return null;
				}

				$frame = self::filter_riff_chunks( $payload, 16, $size, self::WEBP_FRAME_KEEP );
				if ( $frame === null ) {
					return null;
				}

				$payload = substr( $payload, 0, 16 ) . $frame;
			}

			$chunks .= $fourcc . pack( 'V', strlen( $payload ) ) . $payload;
			if ( strlen( $payload ) % 2 ) {
				$chunks .= "\x00";
			}
		}

		return $chunks;
	}

}
