import { describe, expect, it } from 'vitest';
import { frame_aspect_for_image, inscribed_guide_rect, resize_crop_from_handle, type CropRect } from '../../src/utils/crop';

const image_w = 1000;
const image_h = 800;

describe( 'frame_aspect_for_image', () => {
	it( 'uses the image when it is narrower than the widest banner', () => {
		expect( frame_aspect_for_image( 7.18, 368, 53 ) ).toBeCloseTo( 368 / 53 );
	} );

	it( 'crops to the widest banner when the image is wider', () => {
		expect( frame_aspect_for_image( 7.18, 1600, 100 ) ).toBe( 7.18 );
	} );
} );

describe( 'inscribed_guide_rect', () => {
	it( 'centers a 4:1 guide in a wider banner frame', () => {
		const rect = inscribed_guide_rect( 6.94, 4 );
		expect( rect ).not.toBeNull();
		if ( !rect ) {
			return;
		}
		expect( rect.y ).toBe( 0 );
		expect( rect.height ).toBe( 1 );
		expect( rect.width ).toBeCloseTo( 4 / 6.94 );
		expect( rect.x * 2 + rect.width ).toBeCloseTo( 1 );
	} );

	it( 'omits the guide when it matches the frame', () => {
		expect( inscribed_guide_rect( 4, 4 ) ).toBeNull();
	} );
} );

describe( 'resize_crop_from_handle', () => {
	it( 'keeps the opposite corner fixed while matching aspect', () => {
		const start: CropRect = { x: 100, y: 0, width: 800, height: 800 };
		const next = resize_crop_from_handle( start, 'se', 500, 400, image_w, image_h, 1 );

		expect( next.x ).toBeCloseTo( 100 );
		expect( next.y ).toBeCloseTo( 0 );
		expect( next.width ).toBeCloseTo( 400 );
		expect( next.height ).toBeCloseTo( 400 );
	} );

	it( 'does not shrink below the minimum or leave the image', () => {
		const start: CropRect = { x: 100, y: 0, width: 800, height: 800 };
		const next = resize_crop_from_handle( start, 'se', 110, 10, image_w, image_h, 1 );

		expect( next.width ).toBeGreaterThanOrEqual( 200 );
		expect( next.x ).toBeGreaterThanOrEqual( 0 );
		expect( next.y ).toBeGreaterThanOrEqual( 0 );
		expect( next.x + next.width ).toBeLessThanOrEqual( image_w );
		expect( next.y + next.height ).toBeLessThanOrEqual( image_h );
	} );
} );
