import { is_default_cover_crop, is_identity_crop, output_size, type CropRect } from './crop';
import { close_image_source, export_mime_for_file, file_name_for_mime, load_image_source, source_size, type ImageSource } from './image_meta';

const JPEG_QUALITIES = [ 0.92, 0.8, 0.65, 0.5 ];
const SIZE_STEP = 0.85;
const MAX_SHRINK_STEPS = 8;

function integer_source_rect( rect: CropRect, image_width: number, image_height: number ): CropRect {
	const x = Math.max( 0, Math.floor( rect.x ) );
	const y = Math.max( 0, Math.floor( rect.y ) );

	let width = Math.max( 1, Math.round( rect.width ) );
	let height = Math.max( 1, Math.round( rect.height ) );

	if ( x + width > image_width ) {
		width = Math.max( 1, image_width - x );
	}
	if ( y + height > image_height ) {
		height = Math.max( 1, image_height - y );
	}

	return { x, y, width, height };
}

function map_rect( rect: CropRect, from_width: number, from_height: number, to_width: number, to_height: number ): CropRect {
	if ( from_width === to_width && from_height === to_height ) { return rect; }
	if ( from_width <= 0 || from_height <= 0 ) { return rect; }

	return {
		x: rect.x * to_width / from_width,
		y: rect.y * to_height / from_height,
		width: rect.width * to_width / from_width,
		height: rect.height * to_height / from_height,
	};
}

function canvas_to_blob( canvas: HTMLCanvasElement, mime: string, quality?: number ): Promise<Blob> {
	return new Promise( ( resolve, reject ) => {
		canvas.toBlob( ( blob ) => {
			if ( blob ) {
				resolve( blob );
				return;
			}
			reject( new Error( 'canvas export failed' ) );
		}, mime, quality );
	} );
}

function has_quality( mime: string ): boolean {
	return mime === 'image/jpeg' || mime === 'image/webp';
}

function draw_crop( source: ImageSource, rect: CropRect, out_width: number, out_height: number ): HTMLCanvasElement {
	const canvas = document.createElement( 'canvas' );
	canvas.width = out_width;
	canvas.height = out_height;

	const ctx = canvas.getContext( '2d' );
	if ( !ctx ) { throw new Error( 'canvas export failed' ); }

	ctx.imageSmoothingEnabled = true;
	ctx.imageSmoothingQuality = 'high';
	ctx.drawImage(
		source,
		rect.x,
		rect.y,
		rect.width,
		rect.height,
		0,
		0,
		out_width,
		out_height,
	);

	return canvas;
}

export async function export_crop( source: ImageSource, rect: CropRect, mime: string, max_bytes: number, max_width: number, max_height: number ): Promise<Blob> {
	const size = source_size( source );
	const src_rect = integer_source_rect( rect, size.width, size.height );

	let out = output_size( src_rect, max_width, max_height );
	let last_blob: Blob | null = null;
	let export_mime = mime;

	const qualities = has_quality( export_mime ) ? JPEG_QUALITIES : [ undefined ];

	for ( let step = 0; step < MAX_SHRINK_STEPS; step++ ) {
		const canvas = draw_crop( source, src_rect, out.width, out.height );

		for ( const quality of qualities ) {
			try {
				last_blob = await canvas_to_blob( canvas, export_mime, quality );
			} catch ( _err ) {
				if ( export_mime !== 'image/png' ) {
					last_blob = await canvas_to_blob( canvas, 'image/png' );
					export_mime = 'image/png';
				} else {
					throw _err;
				}
			}

			if ( last_blob.size <= max_bytes ) { return last_blob; }
		}

		out = {
			width: Math.max( 1, Math.round( out.width * SIZE_STEP ) ),
			height: Math.max( 1, Math.round( out.height * SIZE_STEP ) ),
		};
	}

	if ( last_blob && last_blob.size <= max_bytes ) { return last_blob; }

	throw new Error( 'canvas export too large' );
}

export async function prepare_upload_file( file: File,
	options: {
		skip_crop: boolean;
		crop: CropRect | null;
		image_width: number;
		image_height: number;
		max_bytes: number;
		max_width: number;
		max_height: number;
		aspect?: number;
		pass_through_default_cover?: boolean;
	},
): Promise<File> {
	if ( options.skip_crop || !options.crop || options.image_width <= 0 || options.image_height <= 0 ) { return file; }

	if ( is_identity_crop( options.crop, options.image_width, options.image_height ) ) { return file; }

	if ( options.pass_through_default_cover && options.aspect && is_default_cover_crop( options.crop, options.image_width, options.image_height, options.aspect ) ) { return file; }

	const source = await load_image_source( file );
	try {
		const loaded = source_size( source );
		const rect = map_rect( options.crop, options.image_width, options.image_height, loaded.width, loaded.height );

		let mime = export_mime_for_file( file );
		const blob = await export_crop( source, rect, mime, options.max_bytes, options.max_width, options.max_height );

		mime = blob.type || mime;

		return new File( [ blob ], file_name_for_mime( file.name, mime ), { type: mime } );
	} finally {
		close_image_source( source );
	}
}
