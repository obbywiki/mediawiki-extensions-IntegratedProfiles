/* eslint-disable no-bitwise */

// Bound decoded pixel memory independently of the compressed file size.
export const MAX_IMAGE_PIXELS = 16000000;
export const MAX_IMAGE_EDGE = 16384;

export type ImageDimensions = { width: number; height: number };

export class ImageDimensionsError extends Error {}

export function image_size_within_limits( width: number, height: number ): boolean {
	return Number.isInteger( width ) && Number.isInteger( height ) &&
		width > 0 && height > 0 && width <= MAX_IMAGE_EDGE && height <= MAX_IMAGE_EDGE &&
		width * height <= MAX_IMAGE_PIXELS;
}

// Read dimensions without decoding compressed pixels; ignore MIME and filename hints.
export function read_image_dimensions( bytes: Uint8Array ): ImageDimensions | null {
	const view = new DataView( bytes.buffer, bytes.byteOffset, bytes.byteLength );
	function text( offset: number, length: number ): string {
		return String.fromCharCode( ...bytes.subarray( offset, offset + length ) );
	}

	if ( bytes.length >= 33 && text( 0, 8 ) === '\x89PNG\r\n\x1a\n' &&
		view.getUint32( 8 ) === 13 && text( 12, 4 ) === 'IHDR' ) {
		return { width: view.getUint32( 16 ), height: view.getUint32( 20 ) };
	}
	if ( bytes.length >= 13 && ( text( 0, 6 ) === 'GIF87a' || text( 0, 6 ) === 'GIF89a' ) ) {
		return { width: view.getUint16( 6, true ), height: view.getUint16( 8, true ) };
	}
	if ( bytes.length >= 2 && bytes[ 0 ] === 0xff && bytes[ 1 ] === 0xd8 ) {
		let offset = 2;
		while ( offset < bytes.length ) {
			if ( bytes[ offset++ ] !== 0xff ) { return null; }
			while ( bytes[ offset ] === 0xff ) { offset++; }
			const marker = bytes[ offset++ ];
			if ( marker === undefined || marker === 0xda || marker === 0xd9 ) { return null; }
			if ( marker === 0x01 || ( marker >= 0xd0 && marker <= 0xd7 ) ) { continue; }
			if ( offset + 2 > bytes.length ) { return null; }
			const length = view.getUint16( offset );
			if ( length < 2 || offset + length > bytes.length ) { return null; }
			if ( marker >= 0xc0 && marker <= 0xcf &&
				marker !== 0xc4 && marker !== 0xc8 && marker !== 0xcc ) {
				if ( length < 8 ) { return null; }
				return {
					width: view.getUint16( offset + 5 ), height: view.getUint16( offset + 3 ),
				};
			}
			offset += length;
		}
	}
	if ( bytes.length >= 12 && text( 0, 4 ) === 'RIFF' && text( 8, 4 ) === 'WEBP' ) {
		const end = view.getUint32( 4, true ) + 8;
		if ( end > bytes.length || end < 12 ) { return null; }
		let offset = 12;
		while ( offset + 8 <= end ) {
			const chunk = text( offset, 4 );
			const length = view.getUint32( offset + 4, true );
			const start = offset + 8;
			if ( start + length > end ) { return null; }
			if ( chunk === 'VP8X' && length === 10 ) {
				function uint24( pos: number ): number {
					return bytes[ pos ] + bytes[ pos + 1 ] * 256 + bytes[ pos + 2 ] * 65536;
				}
				return { width: uint24( start + 4 ) + 1, height: uint24( start + 7 ) + 1 };
			}
			if ( chunk === 'VP8L' && length >= 5 && bytes[ start ] === 0x2f ) {
				const bits = view.getUint32( start + 1, true );
				return { width: ( bits & 0x3fff ) + 1, height: ( ( bits >>> 14 ) & 0x3fff ) + 1 };
			}
			if ( chunk === 'VP8 ' && length >= 10 && text( start + 3, 3 ) === '\x9d\x01\x2a' ) {
				return {
					width: view.getUint16( start + 6, true ) & 0x3fff,
					height: view.getUint16( start + 8, true ) & 0x3fff,
				};
			}
			offset = start + length + length % 2;
		}
	}
	return null;
}

export function validate_image_dimensions( bytes: Uint8Array ): ImageDimensions {
	const dimensions = read_image_dimensions( bytes );
	if ( !dimensions ) { throw new Error( 'image dimensions unavailable' ); }
	if ( !image_size_within_limits( dimensions.width, dimensions.height ) ) {
		throw new ImageDimensionsError( 'image dimensions too large or invalid' );
	}
	return dimensions;
}
