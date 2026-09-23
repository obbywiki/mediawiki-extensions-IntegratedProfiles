import { describe, expect, it } from 'vitest';
import {
	bytes_are_animated,
	extension_for_mime,
	file_name_for_mime,
} from '../../src/utils/image_meta';

function bytes( ...values: number[] ): Uint8Array {
	return Uint8Array.from( values );
}

function ascii_bytes( text: string ): number[] {
	const out: number[] = [];
	for ( let i = 0; i < text.length; i++ ) {
		out.push( text.charCodeAt( i ) );
	}
	return out;
}

function u32_le_bytes( value: number ): number[] {
	return [
		value & 0xff,
		( value >> 8 ) & 0xff,
		( value >> 16 ) & 0xff,
		( value >> 24 ) & 0xff,
	];
}

function u32_be_bytes( value: number ): number[] {
	return [
		( value >> 24 ) & 0xff,
		( value >> 16 ) & 0xff,
		( value >> 8 ) & 0xff,
		value & 0xff,
	];
}

describe( 'extension_for_mime', () => {
	it( 'maps image MIME types', () => {
		expect( extension_for_mime( 'image/jpeg' ) ).toBe( 'jpg' );
		expect( extension_for_mime( 'image/png; charset=binary' ) ).toBe( 'png' );
		expect( extension_for_mime( 'text/plain' ) ).toBeNull();
	} );
} );

describe( 'file_name_for_mime', () => {
	it( 'replaces the original extension', () => {
		expect( file_name_for_mime( 'photo.PNG', 'image/jpeg' ) ).toBe( 'photo.jpg' );
		expect( file_name_for_mime( 'banner.gif', 'image/png' ) ).toBe( 'banner.png' );
	} );
} );

describe( 'bytes_are_animated', () => {
	it( 'treats a single-frame GIF as static', () => {
		const gif = bytes(
			...ascii_bytes( 'GIF89a' ),
			1, 0, 1, 0, 0, 0, 0,
			0x2c,
			0, 0, 0, 0, 1, 0, 1, 0, 0,
			2,
			1, 0,
			0,
			0x3b,
		);
		expect( bytes_are_animated( gif, 'gif' ) ).toBe( false );
	} );

	it( 'treats a two-frame GIF as animated', () => {
		const frame = [
			0x2c,
			0, 0, 0, 0, 1, 0, 1, 0, 0,
			2,
			1, 0,
			0,
		];
		const gif = bytes(
			...ascii_bytes( 'GIF89a' ),
			1, 0, 1, 0, 0, 0, 0,
			...frame,
			...frame,
			0x3b,
		);
		expect( bytes_are_animated( gif, 'gif' ) ).toBe( true );
	} );

	it( 'detects a NETSCAPE loop GIF as animated', () => {
		const gif = bytes(
			...ascii_bytes( 'GIF89a' ),
			1, 0, 1, 0, 0, 0, 0,
			0x21, 0xff,
			11, ...ascii_bytes( 'NETSCAPE2.0' ),
			3, 1, 0, 0,
			0,
			0x2c,
			0, 0, 0, 0, 1, 0, 1, 0, 0,
			2,
			1, 0,
			0,
			0x3b,
		);
		expect( bytes_are_animated( gif, 'gif' ) ).toBe( true );
	} );

	it( 'detects animated WebP ANIM chunks', () => {
		const chunk = [
			...ascii_bytes( 'ANIM' ),
			...u32_le_bytes( 0 ),
		];
		const payload = [ ...ascii_bytes( 'WEBP' ), ...chunk ];
		const webp = bytes(
			...ascii_bytes( 'RIFF' ),
			...u32_le_bytes( payload.length ),
			...payload,
		);
		expect( bytes_are_animated( webp, 'webp' ) ).toBe( true );
	} );

	it( 'detects the VP8X animation flag', () => {
		const chunk = [
			...ascii_bytes( 'VP8X' ),
			...u32_le_bytes( 1 ),
			0x02,
			0,
		];
		const payload = [ ...ascii_bytes( 'WEBP' ), ...chunk ];
		const webp = bytes(
			...ascii_bytes( 'RIFF' ),
			...u32_le_bytes( payload.length ),
			...payload,
		);
		expect( bytes_are_animated( webp, 'webp' ) ).toBe( true );
	} );

	it( 'treats a VP8 WebP as static', () => {
		const chunk = [
			...ascii_bytes( 'VP8 ' ),
			...u32_le_bytes( 0 ),
		];
		const payload = [ ...ascii_bytes( 'WEBP' ), ...chunk ];
		const webp = bytes(
			...ascii_bytes( 'RIFF' ),
			...u32_le_bytes( payload.length ),
			...payload,
		);
		expect( bytes_are_animated( webp, 'webp' ) ).toBe( false );
	} );

	it( 'detects APNG acTL chunks', () => {
		const png = bytes(
			0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a,
			...u32_be_bytes( 8 ),
			...ascii_bytes( 'acTL' ),
			0, 0, 0, 1, 0, 0, 0, 0,
			0, 0, 0, 0,
		);
		expect( bytes_are_animated( png, 'png' ) ).toBe( true );
	} );

	it( 'stops at IDAT and treats a normal PNG as static', () => {
		const png = bytes(
			0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a,
			...u32_be_bytes( 0 ),
			...ascii_bytes( 'IDAT' ),
			0, 0, 0, 0,
		);
		expect( bytes_are_animated( png, 'png' ) ).toBe( false );
	} );

	it( 'never treats JPEG as animated', () => {
		expect( bytes_are_animated( bytes( 0xff, 0xd8, 0xff ), 'jpg' ) ).toBe( false );
	} );
} );
