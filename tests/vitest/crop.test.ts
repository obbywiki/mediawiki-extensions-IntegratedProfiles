import { describe, expect, it } from 'vitest';
import { BANNER_ASPECT, clamp_crop_rect, crop_width_for_zoom, is_default_cover_crop, is_identity_crop, max_cover_rect, output_size, pan_crop, zoom_percent_for_crop } from '../../src/utils/crop';
import { prepare_upload_file } from '../../src/utils/crop_export';

describe( 'max_cover_rect', () => {
	it( 'uses the full square image for a 1:1 crop', () => {
		expect( max_cover_rect( 512, 512, 1 ) ).toEqual( {
			x: 0,
			y: 0,
			width: 512,
			height: 512,
		} );
	} );

	it( 'takes the largest centered square from a landscape image', () => {
		expect( max_cover_rect( 2000, 1000, 1 ) ).toEqual( {
			x: 500,
			y: 0,
			width: 1000,
			height: 1000,
		} );
	} );

	it( 'takes the largest centered square from a portrait image', () => {
		expect( max_cover_rect( 1000, 2000, 1 ) ).toEqual( {
			x: 0,
			y: 500,
			width: 1000,
			height: 1000,
		} );
	} );

	it( 'uses the full 6:1 image for a banner crop', () => {
		expect( max_cover_rect( 1200, 200, BANNER_ASPECT ) ).toEqual( {
			x: 0,
			y: 0,
			width: 1200,
			height: 200,
		} );
	} );

	it( 'takes a 6:1 strip from a square banner source', () => {
		const rect = max_cover_rect( 1200, 1200, BANNER_ASPECT );
		expect( rect.width ).toBeCloseTo( 1200 );
		expect( rect.height ).toBeCloseTo( 200 );
		expect( rect.x ).toBeCloseTo( 0 );
		expect( rect.y ).toBeCloseTo( 500 );
	} );

	it( 'keeps the full height of a banner wider than the frame', () => {
		const rect = max_cover_rect( 1920, 300, BANNER_ASPECT );
		expect( rect.height ).toBeCloseTo( 300 );
		expect( rect.width ).toBeCloseTo( 1800 );
		expect( rect.x ).toBeCloseTo( 60 );
		expect( rect.y ).toBeCloseTo( 0 );
	} );
} );

describe( 'is_identity_crop', () => {
	it( 'is true when the crop is the whole image', () => {
		expect( is_identity_crop(
			{ x: 0, y: 0, width: 100, height: 100 },
			100,
			100,
		) ).toBe( true );
	} );

	it( 'is false when the crop is a subset', () => {
		expect( is_identity_crop(
			{ x: 500, y: 0, width: 1000, height: 1000 },
			2000,
			1000,
		) ).toBe( false );
	} );
} );

describe( 'is_default_cover_crop', () => {
	it( 'matches the largest cover box', () => {
		const rect = max_cover_rect( 1920, 1080, BANNER_ASPECT );
		expect( is_default_cover_crop( rect, 1920, 1080, BANNER_ASPECT ) ).toBe( true );
	} );

	it( 'is false after a pan', () => {
		const rect = max_cover_rect( 1920, 1080, BANNER_ASPECT );
		expect( is_default_cover_crop(
			{ ...rect, y: rect.y + 20 },
			1920,
			1080,
			BANNER_ASPECT,
		) ).toBe( false );
	} );
} );

describe( 'clamp and pan', () => {
	it( 'keeps the crop inside the image', () => {
		const clamped = clamp_crop_rect(
			{ x: -20, y: 900, width: 100, height: 50 },
			200,
			100,
		);
		
		expect( clamped.x ).toBe( 0 );
		expect( clamped.y ).toBe( 50 );
		expect( clamped.width ).toBe( 100 );
		expect( clamped.height ).toBe( 50 );
	} );

	it( 'does not pan past the image edge', () => {
		const start = { x: 0, y: 0, width: 100, height: 100 };
		const panned = pan_crop( start, -40, 20, 200, 100 );
		expect( panned.x ).toBe( 0 );
		expect( panned.y ).toBe( 0 );
	} );
} );

describe( 'zoom mapping', () => {
	it( 'maps 0% zoom to the largest cover box', () => {
		const width = crop_width_for_zoom( 0, 2000, 1000, 1 );
		expect( width ).toBeCloseTo( 1000 );
		expect( zoom_percent_for_crop(
			{ x: 500, y: 0, width: 1000, height: 1000 },
			2000,
			1000,
			1,
		) ).toBe( 0 );
	} );

	it( 'maps 100% zoom to a quarter of the cover width', () => {
		const width = crop_width_for_zoom( 100, 2000, 1000, 1 );
		expect( width ).toBeCloseTo( 250 );
	} );
} );

describe( 'output_size', () => {
	it( 'keeps source crop pixels when under the cap', () => {
		expect( output_size(
			{ x: 0, y: 0, width: 800, height: 800 },
			2048,
			2048,
		) ).toEqual( { width: 800, height: 800 } );
	} );

	it( 'fits a large square crop to the avatar cap', () => {
		expect( output_size(
			{ x: 0, y: 0, width: 4000, height: 4000 },
			2048,
			2048,
		) ).toEqual( { width: 2048, height: 2048 } );
	} );

	it( 'fits a wide banner crop to the banner cap', () => {
		expect( output_size(
			{ x: 0, y: 0, width: 5120, height: 1280 },
			2560,
			640,
		) ).toEqual( { width: 2560, height: 640 } );
	} );
} );

describe( 'prepare_upload_file', () => {
	it( 'returns the original file for identity and skip-crop cases', async () => {
		const file = new File( [ 'fake' ], 'avatar.png', { type: 'image/png' } );
		const skipped = await prepare_upload_file( file, {
			skip_crop: true,
			crop: { x: 10, y: 10, width: 50, height: 50 },
			image_width: 100,
			image_height: 100,
			max_bytes: 2097152,
			max_width: 2048,
			max_height: 2048,
		} );
		expect( skipped ).toBe( file );

		const identity = await prepare_upload_file( file, {
			skip_crop: false,
			crop: { x: 0, y: 0, width: 100, height: 100 },
			image_width: 100,
			image_height: 100,
			max_bytes: 2097152,
			max_width: 2048,
			max_height: 2048,
		} );
		expect( identity ).toBe( file );
	} );

	it( 'returns the original file for an untouched banner cover crop', async () => {
		const file = new File( [ 'fake' ], 'banner.jpg', { type: 'image/jpeg' } );
		const crop = max_cover_rect( 1920, 1080, BANNER_ASPECT );
		const passed = await prepare_upload_file( file, {
			skip_crop: false,
			crop,
			image_width: 1920,
			image_height: 1080,
			max_bytes: 4194304,
			max_width: 2560,
			max_height: 427,
			aspect: BANNER_ASPECT,
			pass_through_default_cover: true,
		} );
		expect( passed ).toBe( file );
	} );
} );
