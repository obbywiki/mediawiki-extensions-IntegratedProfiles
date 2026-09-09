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

/** @type {HTMLElement|null} */
let card_root = null;

/** @type {HTMLElement|null} */
let card_arrow = null;

/** @type {HTMLAnchorElement|null} */
let open_link = null;

/** @type {Function|null} */
let cleanup_auto_update = null;

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

	const card = document.createElement( 'span' );
	card.className = 'ip-user-card';
	content.append( card );

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

	const root = ensure_card_root();
	root.setAttribute( 'aria-label', user_name );
	document.body.append( root );
	root.classList.add( VISIBLE_CLASS );
	
	cleanup_auto_update = f.autoUpdate( link, root, update_position );
	update_position();
}

function hide_card() {
	if ( cleanup_auto_update ) {
		cleanup_auto_update();
		cleanup_auto_update = null;
	}

	if ( open_link ) {
		open_link.removeAttribute( 'aria-expanded' );
		open_link = null;
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
