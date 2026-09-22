export type CropRect = {
	x: number;
	y: number;
	width: number;
	height: number;
};


export const AVATAR_ASPECT = 1;
// not at all very accurate, i can't get it right TODO
export const BANNER_ASPECT = 6;
export const BANNER_GUIDE_ASPECT = 4;
const DESKTOP_HERO_REM = 9.75;
const DESKTOP_LAYOUT_MIN_PX = 1120;
export const AVATAR_MAX_EDGE = 2048;
export const BANNER_MAX_WIDTH = 2560;
export const MIN_ZOOM_DIVISOR = 4;
export const IDENTITY_EPSILON = 1.5;

/**
 * @param {number} image_width
 * @param {number} image_height
 * @param {number} aspect Width divided by height
 * @return {CropRect}
 */
export function max_cover_rect( image_width: number, image_height: number, aspect: number ): CropRect {
	if ( image_width <= 0 || image_height <= 0 || aspect <= 0 ) {
		return {
			x: 0,
			y: 0,
			width: Math.max( 1, image_width ),
			height: Math.max( 1, image_height ),
		};
	}

	const image_aspect = image_width / image_height;
	if ( image_aspect > aspect ) {
		return {
			x: ( image_width - image_height * aspect ) / 2,
			y: 0,
			width: image_height * aspect,
			height: image_height,
		};
	}

	return {
		x: 0,
		y: ( image_height - image_width / aspect ) / 2,
		width: image_width,
		height: image_width / aspect,
	};
}

export function min_crop_width( image_width: number, image_height: number, aspect: number ): number {
	const max = max_cover_rect( image_width, image_height, aspect );
	return Math.max( 1, max.width / MIN_ZOOM_DIVISOR );
}

export function clamp_crop_rect( rect: CropRect, image_width: number, image_height: number ): CropRect {
	let { x, y, width, height } = rect;
	width = Math.min( Math.max( 1, width ), Math.max( 1, image_width ) );
	height = Math.min( Math.max( 1, height ), Math.max( 1, image_height ) );
	x = Math.min( Math.max( 0, x ), Math.max( 0, image_width - width ) );
	y = Math.min( Math.max( 0, y ), Math.max( 0, image_height - height ) );
	return { x, y, width, height };
}

export function crop_around_center( center_x: number, center_y: number, width: number, aspect: number, image_width: number, image_height: number ): CropRect {
	const max = max_cover_rect( image_width, image_height, aspect );
	const min_w = min_crop_width( image_width, image_height, aspect );

	const next_width = Math.min( max.width, Math.max( min_w, width ) );
	const next_height = next_width / aspect;

	return clamp_crop_rect(
		{
			x: center_x - next_width / 2,
			y: center_y - next_height / 2,
			width: next_width,
			height: next_height,
		},
		image_width,
		image_height,
	);
}

export function pan_crop( rect: CropRect, dx_source: number, dy_source: number, image_width: number, image_height: number ): CropRect {
	return clamp_crop_rect(
		{
			x: rect.x + dx_source,
			y: rect.y + dy_source,
			width: rect.width,
			height: rect.height,
		},
		image_width,
		image_height,
	);
}

export type CropHandle = 'nw' | 'ne' | 'sw' | 'se';

function handle_anchor( rect: CropRect, handle: CropHandle ): { x: number; y: number } {
	if ( handle === 'se' ) {
		return { x: rect.x, y: rect.y };
	}
	if ( handle === 'nw' ) {
		return { x: rect.x + rect.width, y: rect.y + rect.height };
	}
	if ( handle === 'ne' ) {
		return { x: rect.x, y: rect.y + rect.height };
	}
	return { x: rect.x + rect.width, y: rect.y };
}

function handle_signs( handle: CropHandle ): { sign_x: number; sign_y: number } {
	if ( handle === 'se' ) {
		return { sign_x: 1, sign_y: 1 };
	}
	if ( handle === 'nw' ) {
		return { sign_x: -1, sign_y: -1 };
	}
	if ( handle === 'ne' ) {
		return { sign_x: 1, sign_y: -1 };
	}
	return { sign_x: -1, sign_y: 1 };
}

/**
 * Resize from one corner, with the opposite corner being fixed.
 *
 * @param {CropRect} rect
 * @param {CropHandle} handle
 * @param {number} pointer_x
 * @param {number} pointer_y
 * @param {number} image_width
 * @param {number} image_height
 * @param {number} aspect Width divided by height
 * @return {CropRect}
 */
export function resize_crop_from_handle( rect: CropRect, handle: CropHandle, pointer_x: number, pointer_y: number, image_width: number, image_height: number, aspect: number ): CropRect {
	const anchor = handle_anchor( rect, handle );
	const { sign_x, sign_y } = handle_signs( handle );

	const dx = Math.max( 0, ( pointer_x - anchor.x ) * sign_x );
	const dy = Math.max( 0, ( pointer_y - anchor.y ) * sign_y );
	const denom = 1 + 1 / ( aspect * aspect );
	let width = ( dx + dy / aspect ) / denom;

	const space_x = sign_x === 1 ? image_width - anchor.x : anchor.x;
	const space_y = sign_y === 1 ? image_height - anchor.y : anchor.y;
	const max_w = Math.max( 1, Math.min(
		max_cover_rect( image_width, image_height, aspect ).width,
		space_x,
		space_y * aspect,
	) );
	const min_w = Math.min( min_crop_width( image_width, image_height, aspect ), max_w );

	width = Math.min( max_w, Math.max( min_w, width ) );
	const height = width / aspect;
	const x = sign_x === 1 ? anchor.x : anchor.x - width;
	const y = sign_y === 1 ? anchor.y : anchor.y - height;

	return clamp_crop_rect(
		{ x, y, width, height },
		image_width,
		image_height,
	);
}

/**
 * 0 = largest cover box, 100 = 4x zoom (smallest box).
 *
 * @param {number} zoom_percent
 * @param {number} image_width
 * @param {number} image_height
 * @param {number} aspect Width divided by height
 * @return {number}
 */
export function crop_width_for_zoom( zoom_percent: number, image_width: number, image_height: number, aspect: number ): number {
	const max = max_cover_rect( image_width, image_height, aspect );
	const min_w = min_crop_width( image_width, image_height, aspect );

	const t = Math.min( 1, Math.max( 0, zoom_percent / 100 ) );

	return max.width - t * ( max.width - min_w );
}

export function zoom_percent_for_crop( rect: CropRect, image_width: number, image_height: number, aspect: number ): number {
	const max = max_cover_rect( image_width, image_height, aspect );
	const min_w = min_crop_width( image_width, image_height, aspect );

	const span = max.width - min_w;

	if ( span <= 0 ) { return 0; }

	return Math.round( 100 * ( 1 - ( rect.width - min_w ) / span ) );
}

export function is_identity_crop( rect: CropRect, image_width: number, image_height: number ): boolean {
	return (
		rect.x <= IDENTITY_EPSILON &&
		rect.y <= IDENTITY_EPSILON &&
		rect.width >= image_width - IDENTITY_EPSILON &&
		rect.height >= image_height - IDENTITY_EPSILON
	);
}

export function is_default_cover_crop( rect: CropRect, image_width: number, image_height: number, aspect: number ): boolean {
	const max = max_cover_rect( image_width, image_height, aspect );
	return (
		Math.abs( rect.x - max.x ) <= IDENTITY_EPSILON &&
		Math.abs( rect.y - max.y ) <= IDENTITY_EPSILON &&
		Math.abs( rect.width - max.width ) <= IDENTITY_EPSILON &&
		Math.abs( rect.height - max.height ) <= IDENTITY_EPSILON
	);
}

/**
 * @return {number}
 */
export function read_banner_frame_aspect(): number {
	// best guesses

	const hero = document.querySelector( '.ip-masthead__hero' );
	
	let live = 0;
	if ( hero instanceof HTMLElement && hero.clientWidth > 1 && hero.clientHeight > 1 ) {
		live = hero.clientWidth / hero.clientHeight;
	}

	const narrow = typeof window !== 'undefined' &&
		typeof window.matchMedia === 'function' &&
		window.matchMedia( '(max-width: 640px)' ).matches;
	if ( !narrow && live > 0 ) {
		return live;
	}

	const root_px = typeof document === 'undefined' ? 16 : ( parseFloat( getComputedStyle( document.documentElement ).fontSize ) || 16 );
	const desktop = DESKTOP_LAYOUT_MIN_PX / ( DESKTOP_HERO_REM * root_px );

	return Math.max( live, desktop, BANNER_ASPECT );
}

/**
 * Keep the widest frame unless the image is narrower than that frame.
 *
 * @param {number} largest
 * @param {number} image_width
 * @param {number} image_height
 * @return {number}
 */
export function frame_aspect_for_image(
	largest: number,
	image_width: number,
	image_height: number,
): number {
	if ( largest <= 0 || image_width <= 0 || image_height <= 0 ) {
		return largest > 0 ? largest : 1;
	}

	const image_aspect = image_width / image_height;
	if ( image_aspect < largest ) {
		return image_aspect;
	}

	return largest;
}

/**
 * Position of a guide rectangle inscribed in a crop frame, in 0–1 frame units.
 * Null when the guide would match the frame.
 *
 * @param {number} frame_aspect
 * @param {number} guide_aspect
 * @return {CropRect|null}
 */
export function inscribed_guide_rect( frame_aspect: number, guide_aspect: number ): CropRect | null {
	if ( frame_aspect <= 0 || guide_aspect <= 0 ) {
		return null;
	}

	const span = Math.max( frame_aspect, guide_aspect );
	if ( Math.abs( frame_aspect - guide_aspect ) / span < 0.025 ) {
		return null;
	}

	if ( frame_aspect > guide_aspect ) {
		const width = guide_aspect / frame_aspect;
		return {
			x: ( 1 - width ) / 2,
			y: 0,
			width,
			height: 1,
		};
	}

	const height = frame_aspect / guide_aspect;
	return {
		x: 0,
		y: ( 1 - height ) / 2,
		width: 1,
		height,
	};
}

export function banner_output_max_height( aspect: number ): number {
	if ( aspect <= 0 ) {
		return BANNER_MAX_WIDTH / BANNER_ASPECT;
	}
	return BANNER_MAX_WIDTH / aspect;
}

export function output_size( rect: CropRect, max_width: number, max_height: number ): { width: number; height: number } {
	let width = Math.max( 1, Math.round( rect.width ) );
	let height = Math.max( 1, Math.round( rect.height ) );

	if ( width > max_width || height > max_height ) {
		const fit = Math.min( max_width / width, max_height / height );
		width = Math.max( 1, Math.round( width * fit ) );
		height = Math.max( 1, Math.round( height * fit ) );
	}

	return { width, height };
}

export function image_to_frame_scale( frame_width: number, crop_width: number ): number {
	if ( crop_width <= 0 ) { return 1; }

	return frame_width / crop_width;
}
