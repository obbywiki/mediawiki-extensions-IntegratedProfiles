export type CropRect = {
	x: number;
	y: number;
	width: number;
	height: number;
};

export const AVATAR_ASPECT = 1;
export const BANNER_ASPECT = 6;
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
	const hero = document.querySelector( '.ip-masthead__hero' );
	if ( hero instanceof HTMLElement && hero.clientWidth > 1 && hero.clientHeight > 1 ) { return hero.clientWidth / hero.clientHeight; }

	return BANNER_ASPECT;
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
