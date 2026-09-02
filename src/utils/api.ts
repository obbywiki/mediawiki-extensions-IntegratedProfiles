import type {
	IntegratedProfilesConfig,
	ProfileFieldsMap,
	ProfilePayload,
} from '../types/mw';

type ApiErrorBody = {
	error?: { info?: string; code?: string };
	errors?: { text?: string; html?: string }[];
	xhr?: { status?: number };
	textStatus?: string;
	exception?: string | { message?: string };
};

type ApiFailure = {
	code?: string;
	result?: ApiErrorBody;
};

function get_api(): mw.Api {
	return new mw.Api( { parameters: { formatversion: 2 } } );
}

/**
 * @param {Object} params API parameters, including any File to upload
 * @param {Object} [ajax_options] Extra jQuery.ajax options
 * @return {Promise} Resolves with the API response
 */
function post_with_token(
	params: Record<string, string | File>,
	ajax_options?: Record<string, unknown>,
): Promise<Record<string, unknown>> {
	return new Promise( ( resolve, reject ) => {
		get_api().postWithToken( 'csrf', params, ajax_options )
			.done( ( data: Record<string, unknown> ) => resolve( data ) )
			.fail( ( code: string, result: ApiErrorBody ) => reject( { code, result } ) );
	} );
}

/**
 * For better error transparency for 413 (client_max_body_size) errors.
 *
 * @param {ApiErrorBody} body
 * @return {boolean}
 */
function is_request_entity_too_large( body: ApiErrorBody ): boolean {
	if ( body.xhr && body.xhr.status === 413 ) {
		return true;
	}

	const exception = body.exception;
	const exception_text = typeof exception === 'string' ?
		exception :
		( exception && exception.message ? exception.message : '' );
	return /request entity too large|\b413\b/i.test( exception_text );
}

export function api_error_message( err: unknown ): string {
	if ( !err || typeof err !== 'object' ) {
		return String( err );
	}

	const record = err as ApiFailure & ApiErrorBody & { message?: string };
	const body = record.result || record;

	if ( is_request_entity_too_large( body ) ) {
		return msg( 'integratedprofiles-error-upload-too-large' );
	}

	if ( body.error && body.error.info ) {
		return body.error.info;
	}

	const errors = body.errors;
	if ( errors && errors.length ) {
		const first = errors[ 0 ].text || errors[ 0 ].html;

		if ( first ) {
			return first;
		}
	}

	if ( record.message ) {
		return record.message;
	}

	if ( record.code === 'http' ) {
		return '';
	}

	return record.code || String( err );
}

export function msg( key: string, ...params: string[] ): string {
	return mw.message( key, ...params ).text();
}

export async function save_profile_fields(
	fields: Partial<ProfileFieldsMap>,
	username?: string,
): Promise<ProfilePayload> {
	try {
		const payload: Record<string, string> = {
			action: 'setintegratedprofile',
			fields: JSON.stringify( fields ),
			format: 'json',
		};
		if ( username ) {
			payload.username = username;
		}

		const data = await post_with_token( payload ) as {
			setintegratedprofile?: { profile?: ProfilePayload };
		};

		const profile = data.setintegratedprofile && data.setintegratedprofile.profile;
		if ( !profile ) {
			throw new Error( msg( 'integratedprofiles-save-error' ) );
		}
		return profile;
	} catch ( err ) {
		throw new Error( api_error_message( err ) || msg( 'integratedprofiles-save-error' ) );
	}
}

export async function upload_avatar(
	file: File,
	username?: string,
): Promise<ProfilePayload> {
	try {
		const payload: Record<string, string | File> = {
			action: 'integratedprofileuploadavatar',
			file,
			format: 'json',
		};
		if ( username ) {
			payload.username = username;
		}

		// mw.Api only builds a FormData body when multipart is requested; otherwise it
		// runs the File through jQuery.param and the browser throws "Illegal invocation".
		const data = await post_with_token( payload, {
			contentType: 'multipart/form-data',
		} ) as {
			integratedprofileuploadavatar?: { profile?: ProfilePayload };
		};

		const profile = data.integratedprofileuploadavatar &&
			data.integratedprofileuploadavatar.profile;
		if ( !profile ) {
			throw new Error( msg( 'integratedprofiles-avatar-error' ) );
		}
		return profile;
	} catch ( err ) {
		throw new Error( api_error_message( err ) || msg( 'integratedprofiles-avatar-error' ) );
	}
}

export async function delete_avatar( username?: string ): Promise<ProfilePayload> {
	try {
		const payload: Record<string, string> = {
			action: 'integratedprofiledeleteavatar',
			format: 'json',
		};

		if ( username ) {
			payload.username = username;
		}

		const data = await post_with_token( payload ) as { integratedprofiledeleteavatar?: { profile?: ProfilePayload } };

		const profile = data.integratedprofiledeleteavatar && data.integratedprofiledeleteavatar.profile;

		if ( !profile ) { throw new Error( msg( 'integratedprofiles-avatar-error' ) ); }

		return profile;
	} catch ( err ) {
		throw new Error( api_error_message( err ) || msg( 'integratedprofiles-avatar-error' ) );
	}
}

export async function upload_banner( file: File, username?: string ): Promise<ProfilePayload> {
	try {
		const payload: Record<string, string | File> = {
			action: 'integratedprofileuploadbanner',
			file,
			format: 'json',
		};

		if ( username ) {
			payload.username = username;
		}

		const data = await post_with_token( payload, {
			contentType: 'multipart/form-data',
		} ) as {
			integratedprofileuploadbanner?: { profile?: ProfilePayload };
		};

		const profile = data.integratedprofileuploadbanner && data.integratedprofileuploadbanner.profile;
		if ( !profile ) { throw new Error( msg( 'integratedprofiles-banner-error' ) ); }

		return profile;
	} catch ( err ) {
		throw new Error( api_error_message( err ) || msg( 'integratedprofiles-banner-error' ) );
	}
}

export async function delete_banner( username?: string ): Promise<ProfilePayload> {
	try {
		const payload: Record<string, string> = {
			action: 'integratedprofiledeletebanner',
			format: 'json',
		};

		if ( username ) {
			payload.username = username;
		}

		const data = await post_with_token( payload ) as {
			integratedprofiledeletebanner?: { profile?: ProfilePayload };
		};

		const profile = data.integratedprofiledeletebanner && data.integratedprofiledeletebanner.profile;
		if ( !profile ) { throw new Error( msg( 'integratedprofiles-banner-error' ) ); }

		return profile;
	} catch ( err ) {
		throw new Error( api_error_message( err ) || msg( 'integratedprofiles-banner-error' ) );
	}
}

/**
 * Syncs changes to the profile payload to the live config.
 *
 * @param {ProfilePayload} profile Saved profile payload from the write API
 */
export function sync_config_from_profile( profile: ProfilePayload ): void {
	if ( !mw.config || typeof mw.config.get !== 'function' ) { return; }

	const live_config = mw.config.get( 'wgIntegratedProfiles' ) as IntegratedProfilesConfig | null | undefined;
	if ( !live_config ) { return; }

	if ( profile.fields ) {
		if ( live_config.fields ) {
			Object.assign( live_config.fields, profile.fields );
		} else {
			live_config.fields = { ...profile.fields };
		}
	}

	if ( profile.links ) {
		live_config.links = profile.links;
	}

	if ( profile.wiki_profiles ) {
		live_config.wiki_profiles = profile.wiki_profiles;
	}

	if ( profile.avatar_url ) {
		live_config.avatar_url = profile.avatar_url;
	}

	live_config.has_custom_avatar = !!profile.has_custom_avatar;

	if ( profile.banner_url !== undefined ) {
		live_config.banner_url = profile.banner_url || '';
	}

	if ( profile.has_custom_banner !== undefined ) {
		live_config.has_custom_banner = !!profile.has_custom_banner;
	}
}

/**
 * Applies the profile payload to the DOM.
 *
 * @param {ProfilePayload} profile Saved profile payload from the write API
 */
export function apply_payload_to_dom( profile: ProfilePayload ): void {
	sync_config_from_profile( profile );

	const avatar_img = document.querySelector( '.ip-avatar__image' ) as HTMLImageElement | null;
	if ( avatar_img && profile.avatar_url ) {
		avatar_img.src = profile.avatar_url;
	}

	sync_banner_dom( profile );
	sync_featured_article_dom( profile );
	sync_about_block_dom( profile );

	let links_el = document.querySelector( '.ip-links' ) as HTMLUListElement | null;
	const links = Object.values( profile.links || {} );
	const verified_items = links_el ? Array.from( links_el.querySelectorAll( '.ip-links__item--verified' ) ) : [];

	if ( links.length === 0 && verified_items.length === 0 ) {
		const wrap = document.querySelector( '.ip-links-wrap' );

		if ( wrap ) {
			wrap.remove();
		} else if ( links_el ) {
			links_el.remove();
		}

		return;
	}

	if ( !links_el ) {
		const body = document.querySelector( '.ip-masthead__body' );
		if ( !body ) { return; }

		const wrap = document.createElement( 'div' );
		wrap.className = 'ip-links-wrap';

		links_el = document.createElement( 'ul' );
		links_el.className = 'ip-links';

		wrap.appendChild( links_el );

		const actions = body.querySelector( '.ip-masthead__actions' );

		if ( actions ) {
			body.insertBefore( wrap, actions );
		} else {
			body.appendChild( wrap );
		}
	}

	links_el.textContent = '';
	for ( const link of links ) {
		const li = document.createElement( 'li' );
		li.className = 'ip-links__item ip-links__item--' + ( link.kind || '' );

		const url = ( link.url || '' ).trim();
		const anchor = url ? document.createElement( 'a' ) : document.createElement( 'span' );
		anchor.className = 'ip-links__anchor';
		if ( url ) {
			const a = anchor as HTMLAnchorElement;
			a.href = url;
			a.rel = 'nofollow noopener';
			a.target = '_blank';
		} else {
			anchor.setAttribute( 'role', 'img' );
		}
		anchor.title = link.label;
		anchor.setAttribute( 'aria-label', link.label );

		const icon = document.createElement( 'span' );
		icon.className = 'ip-links__icon';
		icon.setAttribute( 'aria-hidden', 'true' );

		anchor.appendChild( icon );
		li.appendChild( anchor );
		links_el.appendChild( li );
	}
	for ( const verified of verified_items ) {
		links_el.appendChild( verified );
	}
}

const WIKI_PROFILE_LABEL_KEYS: Record<string, string> = {
	mediawiki: 'integratedprofiles-field-mediawiki',
	miraheze: 'integratedprofiles-field-miraheze',
	fandom: 'integratedprofiles-field-fandom',
};

/**
 * Sync tagline + wiki-platform icon chips under the masthead.
 *
 * @param {ProfilePayload} profile Saved profile payload from the write API
 */
function sync_about_block_dom( profile: ProfilePayload ): void {
	const about = ( ( profile.fields && profile.fields[ 'ip-about' ] ) || '' ).trim();
	const wiki_profiles = Array.isArray( profile.wiki_profiles ) ? profile.wiki_profiles : [];
	let block = document.querySelector( '.ip-about-block' ) as HTMLElement | null;

	if ( about === '' && wiki_profiles.length === 0 ) {
		if ( block ) {
			block.remove();
		}

		return;
	}

	if ( !block ) {
		const masthead = document.querySelector( '.ip-masthead' );
		if ( !masthead ) { return; }

		block = document.createElement( 'div' );
		block.className = 'ip-about-block';

		const editor = document.getElementById( 'integratedprofiles-editor-root' );

		if ( editor ) {
			masthead.insertBefore( block, editor );
		} else {
			masthead.appendChild( block );
		}
	}

	block.textContent = '';

	if ( about !== '' ) {
		const about_el = document.createElement( 'div' );
		about_el.className = 'ip-about';
		about_el.textContent = about;
		block.appendChild( about_el );
	}

	if ( wiki_profiles.length === 0 ) { return; }

	const list = document.createElement( 'ul' );
	list.className = 'ip-wiki-profiles';
	list.setAttribute( 'aria-label', msg( 'integratedprofiles-wiki-profiles-label' ) );

	for ( const row of wiki_profiles ) {
		if ( !row || typeof row !== 'object' ) { continue; }

		const kind = String( row.kind || '' ).trim();
		const url = String( row.url || '' ).trim();
		const username = String( row.username || '' ).trim();

		if ( !kind || !url ) { continue; }

		const label_key = WIKI_PROFILE_LABEL_KEYS[ kind ];
		const platform = label_key ? msg( label_key ) : kind;
		const title = username ? ( platform + ': ' + username ) : platform;
		const li = document.createElement( 'li' );

		li.className = 'ip-wiki-profiles__item ip-wiki-profiles__item--' + kind;

		const a = document.createElement( 'a' );

		a.className = 'ip-wiki-profiles__anchor';
		a.href = url;
		a.rel = 'nofollow noopener';
		a.target = '_blank';
		a.title = title;
		a.setAttribute( 'aria-label', title );

		const icon = document.createElement( 'span' );

		icon.className = 'ip-wiki-profiles__icon';
		icon.setAttribute( 'aria-hidden', 'true' );

		a.appendChild( icon );
		li.appendChild( a );
		list.appendChild( li );
	}

	if ( list.childElementCount > 0 ) {
		block.appendChild( list );
	}
}

/**
 * Syncs the masthead band class / custom background from the profile payload.
 *
 * @param {ProfilePayload} profile Saved profile payload from the write API
 */
export function sync_banner_dom( profile: ProfilePayload ): void {
	const band = document.querySelector( '.ip-masthead__band' ) as HTMLElement | null;
	const masthead = document.querySelector( '.ip-masthead' ) as HTMLElement | null;
	if ( !band ) { return; }

	let mode = ( ( profile.fields && profile.fields[ 'ip-banner' ] ) || 'accent' ).trim();
	const banner_url = ( profile.banner_url || '' ).trim();

	if ( mode === 'custom' && banner_url === '' ) {
		mode = 'accent';
	}

	band.className = 'ip-masthead__band ip-masthead__band--' + mode;

	if ( masthead ) {
		if ( mode === 'custom' && banner_url !== '' ) {
			masthead.style.setProperty( '--ip-banner-image', 'url(' + banner_url + ')' );
		} else {
			masthead.style.removeProperty( '--ip-banner-image' );
		}
	}
}

/**
 * Syncs the featured-article row in sync after a profile save (above .ip-about).
 *
 * @param {ProfilePayload} profile Saved profile payload from the write API
 */
function sync_featured_article_dom( profile: ProfilePayload ): void {
	const featured = profile.featured_article;
	const existing = document.querySelector( '.ip-featured' ) as HTMLElement | null;
	const label = msg( 'integratedprofiles-featured-label' );

	if ( !featured || !featured.url || !featured.display_title ) {
		if ( existing ) {
			existing.remove();
		}

		return;
	}

	let section = existing;
	if ( !section ) {
		const masthead = document.querySelector( '.ip-masthead' );
		if ( !masthead ) { return; }

		section = document.createElement( 'section' );
		section.className = 'ip-featured';

		const about = masthead.querySelector( '.ip-about' );
		const editor = document.getElementById( 'integratedprofiles-editor-root' );
		const before = about || editor;

		if ( before ) {
			masthead.insertBefore( section, before );
		} else {
			masthead.appendChild( section );
		}
	}

	section.setAttribute( 'aria-label', label );
	section.textContent = '';

	const icon = document.createElement( 'span' );
	icon.className = 'ip-featured__icon';
	icon.setAttribute( 'aria-hidden', 'true' );

	const link = document.createElement( 'a' );
	link.className = 'ip-featured__link';
	link.href = featured.url;

	const title = document.createElement( 'span' );
	title.className = 'ip-featured__title';
	title.textContent = featured.display_title;
	link.appendChild( title );

	section.appendChild( icon );
	section.appendChild( link );
}
