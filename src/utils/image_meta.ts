/**
 * Client-side MIME / animation sniffing, mirroring AvatarValidator.php.
 */

/* eslint-disable no-bitwise */

export type ImageExt = 'jpg' | 'png' | 'gif' | 'webp';

const MIME_MAP: Record<string, ImageExt> = {
	'image/jpeg': 'jpg',
	'image/png': 'png',
	'image/gif': 'gif',
	'image/webp': 'webp',
};

const PNG_SIG = [ 0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a ];

export function extension_for_mime( mime: string | null | undefined ): ImageExt | null {
	if ( !mime ) { return null; }

	const normalized = mime.toLowerCase().trim().split( ';', 2 )[ 0 ];

	return MIME_MAP[ normalized ] || null;
}

export function extension_for_file( file: File ): ImageExt | null {
	return extension_for_mime( file.type );
}

function ascii( bytes: Uint8Array, offset: number, length: number ): string {
	let text = '';
	const end = Math.min( bytes.length, offset + length );
	for ( let i = offset; i < end; i++ ) {
		text += String.fromCharCode( bytes[ i ] );
	}
	
	return text;
}

function u32_le( bytes: Uint8Array, offset: number ): number {
	return (
		bytes[ offset ] |
		( bytes[ offset + 1 ] << 8 ) |
		( bytes[ offset + 2 ] << 16 ) |
		( bytes[ offset + 3 ] * 0x1000000 )
	) >>> 0;
}

function u32_be( bytes: Uint8Array, offset: number ): number {
	return (
		( bytes[ offset ] * 0x1000000 ) +
		( bytes[ offset + 1 ] << 16 ) +
		( bytes[ offset + 2 ] << 8 ) +
		bytes[ offset + 3 ]
	) >>> 0;
}

function gif_is_animated( data: Uint8Array ): boolean | null {
	const len = data.length;
	if ( len < 13 ) { return null; }

	const sig = ascii( data, 0, 6 );
	if ( sig !== 'GIF87a' && sig !== 'GIF89a' ) { return null; }

	const packed = data[ 10 ];
	const has_gct = ( packed & 0x80 ) !== 0;
	const gct_size = has_gct ? 3 * ( 1 << ( ( packed & 0x07 ) + 1 ) ) : 0;

	let offset = 13 + gct_size;

	if ( offset > len ) { return null; }

	let frames = 0;
	let has_netscape = false;

	while ( offset < len ) {
		const intro = data[ offset ];
		if ( intro === 0x3B ) { break; }

		if ( intro === 0x21 ) {
			if ( offset + 2 > len ) { return null; }

			const label = data[ offset + 1 ];
			offset += 2;

			if ( label === 0xFF && offset < len ) {
				const app_len = data[ offset ];

				if ( offset + 1 + app_len <= len ) {
					const app = ascii( data, offset + 1, app_len );

					if ( app.indexOf( 'NETSCAPE' ) === 0 ) {
						has_netscape = true;
					}
				}
			}

			const skipped = skip_gif_sub_blocks( data, offset, len );
			if ( skipped === null ) { return null; }
			offset = skipped;

			continue;
		}
		if ( intro === 0x2C ) {
			if ( offset + 10 > len ) { return null; }

			const img_packed = data[ offset + 9 ];
			const has_lct = ( img_packed & 0x80 ) !== 0;
			const lct_size = has_lct ? 3 * ( 1 << ( ( img_packed & 0x07 ) + 1 ) ) : 0;
			offset += 10 + lct_size;

			if ( offset >= len ) { return null; }

			offset++;
			const skipped = skip_gif_sub_blocks( data, offset, len );
			if ( skipped === null ) { return null; }

			offset = skipped;
			frames++;

			continue;
		}

		return null;
	}

	if ( frames < 1 ) { return null; }

	return frames > 1 || has_netscape;
}

function skip_gif_sub_blocks( data: Uint8Array, offset: number, len: number ): number | null {
	while ( offset < len ) {
		const size = data[ offset ];
		offset++;

		if ( size === 0 ) { return offset; }
		offset += size;

		if ( offset > len ) { return null; }
	}
	return null;
}

function webp_is_animated( data: Uint8Array ): boolean {
	const len = data.length;

	if ( len < 12 ) { return false; }
	if ( ascii( data, 0, 4 ) !== 'RIFF' || ascii( data, 8, 4 ) !== 'WEBP' ) { return false; }

	let offset = 12;
	while ( offset + 8 <= len ) {
		const fourcc = ascii( data, offset, 4 );
		const size = u32_le( data, offset + 4 );

		if ( fourcc === 'ANIM' || fourcc === 'ANMF' ) { return true; }

		if ( fourcc === 'VP8X' && offset + 8 < len ) {
			const flags = data[ offset + 8 ];

			if ( ( flags & 0x02 ) !== 0 ) { return true; }
		}

		const next = offset + 8 + size + ( size % 2 );
		if ( next <= offset ) { return false; }

		offset = next;
	}

	return false;
}

function png_is_animated( data: Uint8Array ): boolean {
	if ( data.length < 8 ) { return false; }
	for ( let i = 0; i < PNG_SIG.length; i++ ) {
		if ( data[ i ] !== PNG_SIG[ i ] ) {
			return false;
		}
	}

	const len = data.length;
	let offset = 8;
	while ( offset + 12 <= len ) {
		const chunk_len = u32_be( data, offset );
		const type = ascii( data, offset + 4, 4 );
		if ( type === 'acTL' ) { return true; }
		if ( type === 'IEND' || type === 'IDAT' ) { return false; }
		offset += 12 + chunk_len;
		if ( offset > len ) { return false; }
	}

	return false;
}

export function bytes_are_animated( data: Uint8Array, ext: ImageExt | null ): boolean {
	if ( !ext ) {
		if ( ascii( data, 0, 6 ) === 'GIF87a' || ascii( data, 0, 6 ) === 'GIF89a' ) {
			ext = 'gif';
		} else if ( ascii( data, 0, 4 ) === 'RIFF' && ascii( data, 8, 4 ) === 'WEBP' ) {
			ext = 'webp';
		} else if ( data.length >= 8 && data[ 0 ] === PNG_SIG[ 0 ] && ascii( data, 1, 3 ) === 'PNG' ) {
			ext = 'png';
		} else {
			return false;
		}
	}

	if ( ext === 'gif' ) {
		return gif_is_animated( data ) !== false;
	}
	if ( ext === 'webp' ) {
		return webp_is_animated( data );
	}
	if ( ext === 'png' ) {
		return png_is_animated( data );
	}

	return false;
}

export async function file_is_animated( file: File ): Promise<boolean> {
	const ext = extension_for_file( file );
	
	if ( ext === 'jpg' ) { return false; }

	const buffer = await file.arrayBuffer();
	return bytes_are_animated( new Uint8Array( buffer ), ext );
}

function load_html_image( file: File ): Promise<HTMLImageElement> {
	return new Promise( ( resolve, reject ) => {
		const url = URL.createObjectURL( file );
		const image = new Image();

		image.onload = () => {
			URL.revokeObjectURL( url );
			resolve( image );
		};

		image.onerror = () => {
			URL.revokeObjectURL( url );
			reject( new Error( 'image load failed' ) );
		};

		image.src = url;
	} );
}

export type ImageSource = ImageBitmap | HTMLImageElement;

export function source_size( source: ImageSource ): { width: number; height: number } {
	if ( source instanceof HTMLImageElement ) {
		return { width: source.naturalWidth, height: source.naturalHeight };
	}

	return { width: source.width, height: source.height };
}

export function close_image_source( source: ImageSource ): void {
	if ( !( source instanceof HTMLImageElement ) && typeof source.close === 'function' ) {
		source.close();
	}
}

export async function load_image_source( file: File ): Promise<ImageSource> {
	if ( typeof createImageBitmap === 'function' ) {
		try {
			return await createImageBitmap( file, { imageOrientation: 'from-image' } );
		} catch {
			// fall through to HTMLImageElement
		}
	}

	return load_html_image( file );
}

export function export_mime_for_file( file: File ): string {
	const ext = extension_for_file( file );
	
	if ( ext === 'jpg' ) {
		return 'image/jpeg';
	}
	if ( ext === 'webp' ) {
		return 'image/webp';
	}

	return 'image/png';
}

export function file_name_for_mime( original_name: string, mime: string ): string {
	const base = original_name.replace( /\.[^.]+$/, '' ) || 'image';

	if ( mime === 'image/jpeg' ) {
		return base + '.jpg';
	}
	if ( mime === 'image/webp' ) {
		return base + '.webp';
	}

	return base + '.png';
}
