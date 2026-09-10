'use strict';

/**
 * @type {string}
 */
const USER_LINK_SELECTOR = 'a.mw-userlink';

/**
 * @type {string}
 */
const CARD_ID = 'ip-user-card-floating';

/**
 * @type {string}
 */
const VISIBLE_CLASS = 'ext-floatingui-floating--visible';

/**
 * @type {number}
 */
const CACHE_TTL_MS = 30000;

/** @type {HTMLElement|null} */
let card_root = null;

/** @type {HTMLElement|null} */
let card_el = null;

/** @type {HTMLElement|null} */
let card_arrow = null;

/** @type {HTMLAnchorElement|null} */
let open_link = null;

/** @type {Function|null} */
let cleanup_auto_update = null;

/** @type {number} */
let banner_token = 0;

/** @type {number} */
let avatar_token = 0;

/** @type {mw.Api|null} */
let api = null;

/**
 * @typedef {Object} CardPayload
 * @property {string} user
 * @property {number} user_id
 * @property {string} real_name
 * @property {string} about
 * @property {number} edit_count
 * @property {string|null} registration
 * @property {string} avatar_url
 * @property {boolean} has_custom_avatar
 * @property {string} banner
 * @property {string} banner_url
 * @property {{ title: string, display_title: string, url: string }|null} [featured_article]
 * @property {boolean} is_private
 */

/**
 * @typedef {Object} CardCacheRow
 * @property {number} expires
 * @property {CardPayload} payload
 */

/** @type {Map<string, CardCacheRow>} */
const card_cache = new Map();

/** @type {Map<string, Promise<CardPayload>>} */
const card_inflight = new Map();

/**
 * @param {string} href
 * @return {string}
 */
function page_text_from_href( href ) {
	let url;
	try {
		url = new URL( href, location.href );
	} catch {
		return '';
	}

	const title_param = url.searchParams.get( 'title' );
	if ( title_param ) { return title_param; }

	const article_path = String( mw.config.get( 'wgArticlePath' ) || '/wiki/$1' );
	const marker = article_path.indexOf( '$1' );
	const prefix = marker === -1 ? '/wiki/' : article_path.slice( 0, marker );
	const suffix = marker === -1 ? '' : article_path.slice( marker + 2 );
	let path = url.pathname;
	if ( !path.startsWith( prefix ) ) { return ''; }

	path = path.slice( prefix.length );
	if ( suffix && path.slice( -suffix.length ) === suffix ) {
		path = path.slice( 0, -suffix.length );
	}

	return path;
}

/**
 * Canonical username for a user link, or empty to skip.
 *
 * @param {HTMLAnchorElement} el
 * @return {string}
 */
function user_name_from_link( el ) {
	const stored = el.dataset.ipUser;
	if ( stored ) {
		return stored;
	}

	const href = el.getAttribute( 'href' );
	if ( href ) {
		let page = page_text_from_href( href );
		if ( page ) {
			try {
				page = decodeURIComponent( page.replace( /\+/g, ' ' ) );
			} catch {
				page = page.replace( /\+/g, ' ' );
			}

			const title = mw.Title.newFromText( page.replace( /_/g, ' ' ) );
			const ns_ids = mw.config.get( 'wgNamespaceIds' ) || {};
			if ( title && title.getNamespaceId() === ns_ids.user ) {
				const main = title.getMainText();
				if ( !main.includes( '/' ) ) { return main; }

				return '';
			}
		}
	}

	return ( el.textContent || '' ).replace( /[\u200E\u200F]/g, '' ).trim();
}

/**
 * @param {string} user_name
 * @return {string}
 */
function cache_key( user_name ) {
	const spaced = user_name.replace( /_/g, ' ' ).trim();
	if ( !spaced ) { return ''; }

	return spaced.charAt( 0 ).toUpperCase() + spaced.slice( 1 );
}

/**
 * @return {mw.Api}
 */
function get_api() {
	if ( !api ) {
		api = new mw.Api( { parameters: { formatversion: 2 } } );
	}

	return api;
}

/**
 * @param {string} user_name
 * @return {CardPayload|null}
 */
function get_cached_card( user_name ) {
	const key = cache_key( user_name );
	const row = card_cache.get( key );
	if ( !row ) { return null; }
	if ( Date.now() >= row.expires ) {
		card_cache.delete( key );
		return null;
	}

	return row.payload;
}

/**
 * @param {string} user_name
 * @param {CardPayload} payload
 */
function set_cached_card( user_name, payload ) {
	const expires = Date.now() + CACHE_TTL_MS;
	const row = { expires: expires, payload: payload };
	const requested = cache_key( user_name );
	const canonical = cache_key( payload.user || user_name );

	card_cache.set( requested, row );
	if ( canonical && canonical !== requested ) {
		card_cache.set( canonical, row );
	}
}

/**
 * Fetches on click. Reuses an in-flight request, then a 30s in-memory cache,
 * keyed by username for this tab.
 *
 * @param {string} user_name
 * @return {Promise<CardPayload>}
 */
function fetch_card_payload( user_name ) {
	const cached = get_cached_card( user_name );
	if ( cached ) {
		return Promise.resolve( cached );
	}

	const key = cache_key( user_name );
	const pending = card_inflight.get( key );
	if ( pending ) {
		return pending;
	}

	const request = new Promise( ( resolve, reject ) => {
		get_api().get( {
			action: 'query',
			list: 'integratedprofilecard',
			ipcuser: user_name
		} ).done( ( data ) => {
			const list = ( data.query && data.query.integratedprofilecard ) || [];
			const payload = list[ 0 ];
			if ( !payload ) {
				reject( new Error( 'usernotfound' ) );
				return;
			}

			set_cached_card( user_name, payload );
			resolve( payload );
		} ).fail( ( code, result ) => {
			reject( result || code );
		} );
	} ).finally( () => {
		card_inflight.delete( key );
	} );

	card_inflight.set( key, request );
	return request;
}

/**
 * @type {string[]}
 */
const BANNER_PRESETS = [
	'accent',
	'ocean',
	'sunset',
	'forest',
	'midnight',
	'ember',
	'sand',
	'aurora',
	'custom'
];

/**
 * @param {string} tag
 * @param {string} [class_name]
 * @return {HTMLElement}
 */
function h( tag, class_name ) {
	const node = document.createElement( tag );
	if ( class_name ) {
		node.className = class_name;
	}

	return node;
}

/**
 * @return {{ color: string, avatar_border_radius: string }}
 */
function cards_config() {
	const raw = mw.config.get( 'wgIntegratedProfilesCards' ) || {};

	return {
		color: raw.color || '#5288F1',
		avatar_border_radius: raw.avatar_border_radius || '50%'
	};
}

/**
 * @return {string}
 */
function default_avatar_url() {
	const assets = String( mw.config.get( 'wgExtensionAssetsPath' ) || '' ).replace( /\/$/, '' );

	return assets + '/IntegratedProfiles/resources/avatars/default.svg';
}

/**
 * @param {string} user_name
 * @return {string}
 */
function user_page_url( user_name ) {
	return mw.util.getUrl( 'User:' + user_name );
}

/**
 * @param {number} n
 * @return {string}
 */
function format_number( n ) {
	if ( mw.language && mw.language.convertNumber ) {
		return mw.language.convertNumber( n );
	}

	return String( n );
}

/**
 * @param {string|null} registration
 * @return {string}
 */
function format_joined( registration ) {
	if ( !registration || registration.length < 8 ) { return ''; }

	const year = Number( registration.slice( 0, 4 ) );
	const month = Number( registration.slice( 4, 6 ) ) - 1;
	const day = Number( registration.slice( 6, 8 ) );
	if ( !year || month < 0 || month > 11 || !day ) { return ''; }

	const locale = mw.config.get( 'wgUserLanguage' ) || mw.config.get( 'wgContentLanguage' ) || 'en';
	const formatted = new Intl.DateTimeFormat( locale, {
		year: 'numeric',
		month: 'short',
		day: 'numeric'
	} ).format( new Date( year, month, day ) );

	return formatted;
}

/**
 * @param {string} url
 * @return {string}
 */
function css_url( url ) {
	return 'url("' + String( url ).replace( /\\/g, '\\\\' ).replace( /"/g, '\\"' ) + '")';
}

/**
 * Paint one frame at opacity 0 so adding --ready can fade.
 *
 * @param {HTMLElement} el
 * @return {void}
 */
function force_reflow( el ) {
	el.getBoundingClientRect();
}

/**
 * @param {HTMLImageElement} img
 * @return {boolean}
 */
function image_is_cached( img ) {
	return img.complete && img.naturalWidth > 0;
}

/**
 * @param {HTMLElement} el
 * @param {string} ready_class
 * @param {string} instant_class
 * @param {boolean} instant
 * @param {function(): boolean} is_stale
 */
function reveal_ready( el, ready_class, instant_class, instant, is_stale ) {
	if ( is_stale() ) { return; }

	if ( instant ) {
		el.classList.add( instant_class, ready_class );
		return;
	}

	el.classList.remove( instant_class );
	force_reflow( el );
	requestAnimationFrame( () => {
		if ( is_stale() ) { return; }

		el.classList.add( ready_class );
	} );
}

/**
 * @param {HTMLElement} banner
 * @param {CardPayload} payload
 */
function apply_banner( banner, payload ) {
	const token = ++banner_token;
	banner.className = 'ip-user-card__banner ip-user-card__banner--instant';
	banner.style.removeProperty( '--ip-user-card-banner-image' );

	/**
	 * @param {boolean} instant
	 */
	function reveal( instant ) {
		if ( token !== banner_token ) { return; }

		reveal_ready(
			banner,
			'ip-user-card__banner--ready',
			'ip-user-card__banner--instant',
			instant,
			() => token !== banner_token
		);
	}

	let mode = payload.banner || 'accent';
	if ( mode === 'custom' && payload.banner_url ) {
		const img = new Image();
		img.onerror = function () {
			if ( token !== banner_token ) { return; }

			banner.classList.add( 'ip-user-card__banner--accent' );
			reveal( true );
		};
		img.src = payload.banner_url;
		if ( image_is_cached( img ) ) {
			banner.classList.add( 'ip-user-card__banner--custom' );
			banner.style.setProperty( '--ip-user-card-banner-image', css_url( payload.banner_url ) );
			reveal( true );
			return;
		}

		img.onload = function () {
			if ( token !== banner_token ) { return; }

			banner.classList.add( 'ip-user-card__banner--custom' );
			banner.style.setProperty( '--ip-user-card-banner-image', css_url( payload.banner_url ) );
			reveal( false );
		};
		return;
	}

	if ( mode === 'custom' || !BANNER_PRESETS.includes( mode ) ) {
		mode = 'accent';
	}

	banner.classList.add( 'ip-user-card__banner--' + mode );
	reveal( true );
}

function apply_card_chrome() {
	if ( !card_el ) { return; }

	const config = cards_config();
	card_el.style.setProperty( '--ip-user-card-accent', config.color );
	card_el.style.setProperty( '--ip-user-card-avatar-radius', config.avatar_border_radius );
}

/**
 * @param {HTMLImageElement} avatar
 * @param {string} url
 */
function set_avatar_src( avatar, url ) {
	const token = ++avatar_token;
	avatar.classList.add( 'ip-user-card__avatar--instant' );
	avatar.classList.remove( 'ip-user-card__avatar--ready' );

	/**
	 * @param {boolean} instant
	 */
	function reveal( instant ) {
		if ( token !== avatar_token ) { return; }

		reveal_ready( avatar, 'ip-user-card__avatar--ready', 'ip-user-card__avatar--instant', instant, () => token !== avatar_token );
	}

	avatar.onload = null;
	avatar.onerror = function () {
		if ( token !== avatar_token ) { return; }

		avatar.onerror = null;
		avatar.src = default_avatar_url();
		if ( image_is_cached( avatar ) ) {
			reveal( true );

			return;
		}

		avatar.onload = function () {
			reveal( false );
		};
	};
	avatar.src = url;
	if ( image_is_cached( avatar ) ) {
		reveal( true );

		return;
	}

	avatar.onload = function () {
		reveal( false );
	};
}

/**
 * @param {Element} item
 * @param {string} label_text
 * @param {string} value_text
 */
function fill_meta_pair( item, label_text, value_text ) {
	const label = item.querySelector( '.ip-user-card__meta-label' );
	const value = item.querySelector( '.ip-user-card__meta-value' );

	if ( label ) {
		label.textContent = label_text;
	}
	if ( value ) {
		value.textContent = value_text;
	}
}

/**
 * @param {CardPayload} payload
 */
function fill_featured( payload ) {
	if ( !card_el ) { return; }

	const extras = card_el.querySelector( '.ip-user-card__extras' );
	const featured = card_el.querySelector( '.ip-user-card__featured' );
	const title_el = card_el.querySelector( '.ip-user-card__featured-title' );
	const row = payload.featured_article;
	const title_text = ( row && ( row.display_title || row.title ) ) || '';
	const show = !payload.is_private && !!row && !!row.url && !!title_text;

	if ( extras ) { extras.hidden = !show; }

	if ( !( featured instanceof HTMLAnchorElement ) ) { return; }

	featured.hidden = !show;
	if ( !show || !row || !title_text ) {
		featured.removeAttribute( 'href' );
		featured.removeAttribute( 'aria-label' );
		if ( title_el ) { title_el.textContent = ''; }

		return;
	}

	featured.href = row.url;
	featured.setAttribute( 'aria-label', mw.message( 'integratedprofiles-featured-label' ).text() );
	if ( title_el ) {
		title_el.textContent = title_text;
	}
}

/**
 * @param {string} user_name
 */
function show_card_skeleton( user_name ) {
	if ( !card_el ) { return; }

	banner_token += 1;
	avatar_token += 1;
	apply_card_chrome();
	card_el.className = 'ip-user-card ip-user-card--skeleton';

	const profile_url = user_page_url( user_name );

	const banner = card_el.querySelector( '.ip-user-card__banner' );
	if ( banner ) {
		banner.className = 'ip-user-card__banner';
		banner.style.removeProperty( '--ip-user-card-banner-image' );
	}

	const user_name_el = card_el.querySelector( '.ip-user-card__user-name' );
	const aka = card_el.querySelector( '.ip-user-card__aka' );
	const about = card_el.querySelector( '.ip-user-card__about' );
	const edits = card_el.querySelector( '.ip-user-card__meta-item--edits' );
	const joined = card_el.querySelector( '.ip-user-card__meta-item--joined' );
	const notice = card_el.querySelector( '.ip-user-card__meta-item--private' );
	const extras = card_el.querySelector( '.ip-user-card__extras' );
	const featured = card_el.querySelector( '.ip-user-card__featured' );
	const featured_title = card_el.querySelector( '.ip-user-card__featured-title' );
	const avatar = card_el.querySelector( '.ip-user-card__avatar' );
	const avatar_link = card_el.querySelector( '.ip-user-card__avatar-link' );

	if ( user_name_el instanceof HTMLAnchorElement ) {
		user_name_el.textContent = user_name;
		user_name_el.href = profile_url;
	}

	if ( aka ) {
		aka.hidden = true;
		aka.textContent = '';
	}

	if ( about ) {
		about.hidden = true;
		about.textContent = '';
	}

	if ( edits ) {
		edits.hidden = false;
		fill_meta_pair( edits, '', '' );
	}

	if ( joined ) {
		joined.hidden = false;
		fill_meta_pair( joined, '', '' );
	}

	if ( notice ) {
		notice.hidden = true;
		notice.textContent = '';
	}

	if ( extras ) {
		extras.hidden = true;
	}

	if ( featured instanceof HTMLAnchorElement ) {
		featured.hidden = true;
		featured.removeAttribute( 'href' );
		featured.removeAttribute( 'aria-label' );
	}

	if ( featured_title ) {
		featured_title.textContent = '';
	}

	if ( avatar_link instanceof HTMLAnchorElement ) {
		avatar_link.href = profile_url;
	}

	if ( avatar instanceof HTMLImageElement ) {
		avatar.classList.remove( 'ip-user-card__avatar--ready' );
		avatar.removeAttribute( 'src' );
		avatar.alt = '';
	}
}

/**
 * @param {CardPayload} payload
 */
function fill_card( payload ) {
	if ( !card_el ) { return; }

	apply_card_chrome();
	card_el.classList.remove( 'ip-user-card--skeleton' );
	card_el.classList.toggle( 'ip-user-card--private', !!payload.is_private );

	const user_name = payload.user || '';
	const profile_url = user_page_url( user_name );
	const real_name_text = ( payload.real_name || '' ).trim();
	const about_text = ( payload.about || '' ).trim();
	const show_aka = !!real_name_text && !payload.is_private;

	const banner = card_el.querySelector( '.ip-user-card__banner' );
	if ( banner ) {
		apply_banner( banner, payload );
	}

	const user_name_el = card_el.querySelector( '.ip-user-card__user-name' );
	const aka = card_el.querySelector( '.ip-user-card__aka' );
	const about = card_el.querySelector( '.ip-user-card__about' );
	const edits = card_el.querySelector( '.ip-user-card__meta-item--edits' );
	const joined = card_el.querySelector( '.ip-user-card__meta-item--joined' );
	const notice = card_el.querySelector( '.ip-user-card__meta-item--private' );
	const avatar_link = card_el.querySelector( '.ip-user-card__avatar-link' );
	const avatar = card_el.querySelector( '.ip-user-card__avatar' );

	if ( user_name_el instanceof HTMLAnchorElement ) {
		user_name_el.textContent = user_name;
		user_name_el.href = profile_url;
	}

	if ( aka ) {
		aka.hidden = !show_aka;
		aka.textContent = show_aka ? mw.message( 'integratedprofiles-aka', real_name_text ).text() : '';
	}

	if ( about ) {
		about.hidden = !about_text || !!payload.is_private;
		about.textContent = about_text;
	}

	if ( edits ) {
		const show_edits = !payload.is_private;
		edits.hidden = !show_edits;
		fill_meta_pair(
			edits,
			show_edits ? mw.message( 'integratedprofiles-user-card-edits', payload.edit_count ).text() : '',
			show_edits ? format_number( payload.edit_count ) : ''
		);
	}

	if ( joined ) {
		const joined_value = payload.is_private ? '' : format_joined( payload.registration );
		joined.hidden = !joined_value;
		fill_meta_pair(
			joined,
			joined_value ? mw.message( 'integratedprofiles-user-card-joined' ).text() : '',
			joined_value
		);
	}

	if ( notice ) {
		notice.hidden = !payload.is_private;
		notice.textContent = payload.is_private ? mw.message( 'integratedprofiles-private-notice' ).text() : '';
	}

	fill_featured( payload );

	if ( avatar_link instanceof HTMLAnchorElement ) {
		avatar_link.href = profile_url;
	}

	if ( avatar instanceof HTMLImageElement ) {
		avatar.alt = mw.message( 'integratedprofiles-avatar-alt' ).text();
		set_avatar_src( avatar, payload.avatar_url || default_avatar_url() );
	}

	update_position();
}

/**
 * Middle/right buttons and modifier clicks keep native behavior.
 *
 * @param {MouseEvent} event
 * @return {boolean}
 */
function is_modified_click( event ) {
	return event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey;
}

/**
 * @param {EventTarget|null} target
 * @return {Element|null}
 */
function event_element( target ) {
	if ( target instanceof Element ) { return target; }
	if ( target && target.parentElement ) { return target.parentElement; }

	return null;
}

/**
 * @param {Element} el
 * @return {HTMLAnchorElement|null}
 */
function user_link_from_target( el ) {
	const link = el.closest( USER_LINK_SELECTOR );
	if ( !( link instanceof HTMLAnchorElement ) ) { return null; }
	if ( !link.getAttribute( 'href' ) ) { return null; }
	if ( card_root && card_root.contains( link ) ) { return null; }

	return link;
}

/**
 * @param {number} value
 * @return {number}
 */
function round_by_dpr( value ) {
	const dpr = window.devicePixelRatio || 1;

	return Math.round( value * dpr ) / dpr;
}

/**
 * @return {Object|null}
 */
function floating_dom() {
	return window.FloatingUIDOM || null;
}

/**
 * @return {HTMLElement}
 */
function ensure_card_root() {
	if ( card_root ) { return card_root; }

	card_root = document.createElement( 'div' );
	card_root.id = CARD_ID;
	card_root.className = 'ext-floatingui-floating';
	card_root.setAttribute( 'role', 'dialog' );
	card_root.setAttribute( 'tabindex', '-1' );

	const inner = document.createElement( 'div' );
	inner.className = 'ext-floatingui-floating-inner';

	const content = document.createElement( 'div' );
	content.className = 'ext-floatingui-floating-content';

	const card = h( 'div', 'ip-user-card ip-user-card--skeleton' );
	const panel = h( 'div', 'ip-user-card__panel' );
	panel.append(
		h( 'div', 'ip-user-card__banner ip-user-card__banner--accent' )
	);

	const body = h( 'div', 'ip-user-card__content' );
	const identity = h( 'div', 'ip-user-card__identity' );
	const name_line = h( 'div', 'ip-user-card__name-line' );
	const user_link = document.createElement( 'a' );
	user_link.className = 'ip-user-card__user-name';
	const aka = h( 'span', 'ip-user-card__aka' );
	aka.hidden = true;
	name_line.append( user_link, aka );
	const about = h( 'div', 'ip-user-card__about' );
	about.hidden = true;
	identity.append( name_line, about );

	const meta = h( 'div', 'ip-user-card__meta' );
	const edits_item = h( 'span', 'ip-user-card__meta-item ip-user-card__meta-item--edits' );
	edits_item.append(
		h( 'span', 'ip-user-card__meta-label' ),
		h( 'span', 'ip-user-card__meta-value' )
	);
	const joined_item = h( 'span', 'ip-user-card__meta-item ip-user-card__meta-item--joined' );
	joined_item.append(
		h( 'span', 'ip-user-card__meta-label' ),
		h( 'span', 'ip-user-card__meta-value' )
	);
	const private_item = h( 'span', 'ip-user-card__meta-item ip-user-card__meta-item--private' );
	private_item.hidden = true;
	meta.append( edits_item, joined_item, private_item );

	const extras = h( 'div', 'ip-user-card__extras' );
	extras.hidden = true;
	const featured = document.createElement( 'a' );
	featured.className = 'ip-user-card__featured';
	featured.hidden = true;
	const featured_icon = h( 'span', 'ip-user-card__featured-icon' );
	featured_icon.setAttribute( 'aria-hidden', 'true' );
	const featured_title = h( 'span', 'ip-user-card__featured-title' );
	featured.append( featured_icon, featured_title );
	extras.append( featured );

	const footer = h( 'div', 'ip-user-card__footer' );
	footer.append( extras, meta );
	body.append( identity, footer );
	panel.append( body );

	const avatar_link = document.createElement( 'a' );
	avatar_link.className = 'ip-user-card__avatar-link';
	const avatar = document.createElement( 'img' );
	avatar.className = 'ip-user-card__avatar';
	avatar.alt = '';
	avatar_link.append( avatar );
	card.append( panel, avatar_link );

	content.append( card );
	card_el = card;

	card_arrow = document.createElement( 'div' );
	card_arrow.className = 'ext-floatingui-floating-arrow';

	inner.append( content, card_arrow );
	card_root.append( inner );

	return card_root;
}

function update_position() {
	const f = floating_dom();
	if ( !f || !open_link || !card_root || !card_arrow ) { return; }

	const reference_el = open_link;
	const floating_el = card_root;
	const arrow_el = card_arrow;

	f.computePosition( reference_el, floating_el, {
		placement: 'bottom-start',
		middleware: [
			f.offset( 8 ),
			f.autoPlacement( {
				allowedPlacements: [ 'top', 'bottom', 'top-start', 'bottom-start' ]
			} ),
			f.shift( { padding: 16 } ),
			f.arrow( { element: arrow_el, padding: 4 } )
		]
	} ).then( ( { x, y, placement, middlewareData } ) => {
		if ( open_link !== reference_el ) { return; }

		Object.assign( floating_el.style, {
			transform: 'translate(' + round_by_dpr( x ) + 'px,' + round_by_dpr( y ) + 'px)'
		} );
		floating_el.dataset.mwExtFloatinguiPlacement = placement;

		if ( !middlewareData.arrow ) { return; }

		const arrow_x = middlewareData.arrow.x;
		const arrow_y = middlewareData.arrow.y;
		const static_side = {
			top: 'bottom',
			right: 'left',
			bottom: 'top',
			left: 'right'
		}[ placement.split( '-' )[ 0 ] ];

		Object.assign( arrow_el.style, {
			left: typeof arrow_x === 'number' ? round_by_dpr( arrow_x ) + 'px' : '',
			top: typeof arrow_y === 'number' ? round_by_dpr( arrow_y ) + 'px' : '',
			right: '',
			bottom: '',
			[ static_side ]: '-4px'
		} );
	} );
}

/**
 * @param {HTMLAnchorElement} link
 */
function show_card( link ) {
	const f = floating_dom();
	if ( !f ) { return; }

	if ( open_link === link ) { return; }

	hide_card();

	const user_name = user_name_from_link( link );
	if ( !user_name ) { return; }

	link.dataset.ipUser = user_name;
	open_link = link;
	link.setAttribute( 'aria-expanded', 'true' );
	link.setAttribute( 'aria-haspopup', 'dialog' );

	const root = ensure_card_root();
	show_card_skeleton( user_name );
	root.setAttribute( 'aria-label', user_name );
	document.body.append( root );
	root.focus();
	root.classList.add( VISIBLE_CLASS );

	cleanup_auto_update = f.autoUpdate( link, root, update_position );
	update_position();

	const cached = get_cached_card( user_name );
	if ( cached ) {
		fill_card( cached );
		return;
	}

	fetch_card_payload( user_name ).then( ( payload ) => {
		if ( open_link !== link ) { return; }

		fill_card( payload );
	} ).catch( () => {
		// a later click can retry
	} );
}

function hide_card() {
	if ( cleanup_auto_update ) {
		cleanup_auto_update();
		cleanup_auto_update = null;
	}

	if ( open_link ) {
		const previous = open_link;
		previous.setAttribute( 'aria-expanded', 'false' );

		open_link = null;

		if ( card_root && card_root.contains( document.activeElement ) ) { previous.focus(); }
	}

	if ( card_root ) {
		card_root.classList.remove( VISIBLE_CLASS );
		card_root.remove();
	}
}

/**
 * @param {MouseEvent} event
 */
function on_click( event ) {
	if ( is_modified_click( event ) ) { return; }

	const target = event_element( event.target );
	if ( !target ) { return; }

	if ( card_root && card_root.contains( target ) ) { return; }

	const link = user_link_from_target( target );
	if ( !link || !user_name_from_link( link ) ) {
		if ( open_link ) { hide_card(); }

		return;
	}

	event.preventDefault();
	if ( open_link === link ) {
		hide_card();

		return;
	}

	show_card( link );
}

/**
 * @param {KeyboardEvent} event
 */
function on_keydown( event ) {
	if ( event.key !== 'Escape' || !open_link ) { return; }

	hide_card();
}

document.addEventListener( 'click', on_click );
document.addEventListener( 'keydown', on_keydown );
