import { describe, expect, it } from 'vitest';
import { apply_avatar_image, AVATAR_THUMB_SIZES, density_srcset, pick_thumb } from '../../src/utils/thumbs';
import { sync_banner_dom } from '../../src/utils/api';

const AVATAR_THUMBS = {
	32: '/t/32.webp',
	64: '/t/64.webp',
	128: '/t/128.webp',
	256: '/t/256.webp',
	512: '/t/512.webp',
};

describe( 'pick_thumb', () => {
	it( 'snaps up to the nearest size and clamps to the largest', () => {
		expect( pick_thumb( AVATAR_THUMBS, AVATAR_THUMB_SIZES, 104 ) ).toBe( '/t/128.webp' );
		expect( pick_thumb( AVATAR_THUMBS, AVATAR_THUMB_SIZES, 4000 ) ).toBe( '/t/512.webp' );
	} );

	it( 'returns nothing for the empty array the API sends without thumbnails', () => {
		expect( pick_thumb( [], AVATAR_THUMB_SIZES, 128 ) ).toBe( '' );
		expect( pick_thumb( undefined, AVATAR_THUMB_SIZES, 128 ) ).toBe( '' );
	} );
} );

describe( 'density_srcset', () => {
	it( 'lists 1x and 2x once each', () => {
		expect( density_srcset( AVATAR_THUMBS, AVATAR_THUMB_SIZES, 128 ) ).toBe( '/t/128.webp 1x, /t/256.webp 2x' );
		expect( density_srcset( AVATAR_THUMBS, AVATAR_THUMB_SIZES, 512 ) ).toBe( '/t/512.webp 1x' );
	} );
} );

describe( 'apply_avatar_image', () => {
	it( 'uses thumbnails with a density srcset', () => {
		const img = document.createElement( 'img' );
		apply_avatar_image( img, '/orig.jpg', AVATAR_THUMBS );

		expect( img.getAttribute( 'src' ) ).toBe( '/t/128.webp' );
		expect( img.getAttribute( 'srcset' ) ).toBe( '/t/128.webp 1x, /t/256.webp 2x' );
	} );

	it( 'drops a stale srcset so a fresh upload without thumbnails shows', () => {
		const img = document.createElement( 'img' );
		img.setAttribute( 'srcset', '/old/128.webp 1x' );

		apply_avatar_image( img, '/new.jpg', [] );

		expect( img.getAttribute( 'src' ) ).toBe( '/new.jpg' );
		expect( img.hasAttribute( 'srcset' ) ).toBe( false );
	} );
} );

describe( 'sync_banner_dom', () => {
	function masthead(): HTMLElement {
		document.body.innerHTML = '<div class="ip-masthead"><div class="ip-masthead__band"></div></div>';
		return document.querySelector( '.ip-masthead' ) as HTMLElement;
	}

	it( 'paints the largest banner thumbnail', () => {
		const el = masthead();
		sync_banner_dom( { banner_url: '/b.jpg', banner_thumbs: { 640: '/b/640.webp', 1920: '/b/1920.webp' } } );

		expect( el.style.getPropertyValue( '--ip-banner-image' ) ).toBe( 'url(/b/1920.webp)' );
	} );

	it( 'falls back to the original without thumbnails', () => {
		const el = masthead();
		sync_banner_dom( { banner_url: '/b.jpg', banner_thumbs: [] } );

		expect( el.style.getPropertyValue( '--ip-banner-image' ) ).toBe( 'url(/b.jpg)' );
	} );
} );
