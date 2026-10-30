/**
 * Mirrors ThumbnailSizes on the PHP side. Sizes are fixed, so these never come from config.
 */
export const AVATAR_THUMB_SIZES = [ 32, 64, 128, 256, 512 ];
export const BANNER_THUMB_SIZES = [ 640, 1280, 1920 ];

/** Masthead avatar edge in CSS pixels (ProfileRenderer::MASTHEAD_AVATAR_PX) */
export const MASTHEAD_AVATAR_PX = 128;

/**
 * size => URL. The API sends an object, or an empty array when there are no thumbnails.
 */
export type ThumbMap = Record<string, string> | string[] | null | undefined;

function bucket( sizes: number[], requested: number ): number {
	for ( const size of sizes ) {
		if ( size >= requested ) {
			return size;
		}
	}

	return sizes[ sizes.length - 1 ];
}

/**
 * @param {ThumbMap} thumbs Thumbnail map from a payload
 * @param {number[]} sizes AVATAR_THUMB_SIZES or BANNER_THUMB_SIZES
 * @param {number} requested Displayed edge or width in device pixels
 * @return {string} '' when there is no thumbnail, so callers keep the original
 */
export function pick_thumb( thumbs: ThumbMap, sizes: number[], requested: number ): string {
	if ( !thumbs || Array.isArray( thumbs ) ) {
		return '';
	}

	const url = thumbs[ String( bucket( sizes, requested ) ) ];
	return typeof url === 'string' ? url : '';
}

/**
 * @param {ThumbMap} thumbs Thumbnail map from a payload
 * @param {number[]} sizes AVATAR_THUMB_SIZES or BANNER_THUMB_SIZES
 * @param {number} css_px Displayed edge or width in CSS pixels
 * @return {string} "a 1x, b 2x", or '' when there is no thumbnail
 */
export function density_srcset( thumbs: ThumbMap, sizes: number[], css_px: number ): string {
	const seen = new Set<string>();
	const candidates: string[] = [];

	for ( const density of [ 1, 2 ] ) {
		const url = pick_thumb( thumbs, sizes, css_px * density );
		if ( url === '' || seen.has( url ) || /\s/.test( url ) ) {
			continue;
		}

		seen.add( url );
		candidates.push( url + ' ' + density + 'x' );
	}

	return candidates.join( ', ' );
}

/**
 * Points the masthead avatar at the thumbnails, or back at the original when there are none (e.g. right after an upload, before generation finished). A stale srcset would win over src, so it is always rewritten.
 *
 * @param {HTMLImageElement} img Masthead avatar
 * @param {string} original Full-resolution URL
 * @param {ThumbMap} thumbs Thumbnail map from a payload
 */
export function apply_avatar_image( img: HTMLImageElement, original: string, thumbs: ThumbMap ): void {
	const thumb = pick_thumb( thumbs, AVATAR_THUMB_SIZES, MASTHEAD_AVATAR_PX );
	const srcset = thumb !== '' ? density_srcset( thumbs, AVATAR_THUMB_SIZES, MASTHEAD_AVATAR_PX ) : '';

	if ( srcset !== '' ) {
		img.setAttribute( 'srcset', srcset );
	} else {
		img.removeAttribute( 'srcset' );
	}

	img.src = thumb !== '' ? thumb : original;
}
