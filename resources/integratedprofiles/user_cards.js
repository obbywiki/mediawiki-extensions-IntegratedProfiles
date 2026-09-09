'use strict';

/**
 * @type {string}
 */
const USER_LINK_SELECTOR = 'a.mw-userlink';

/**
 * @return {boolean}
 */
function is_coarse_preview() {
	return window.matchMedia( '(hover: none)' ).matches;
}

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
 * @param {HTMLAnchorElement} el
 */
function bind_coarse_click( el ) {
	el.addEventListener( 'click', ( event ) => {
		if ( !is_coarse_preview() ) { return; }

		event.preventDefault();
		el.focus( { preventScroll: true } );
	} );
}

/**
 * @param {Element} el
 * @return {boolean}
 */
function should_skip_link( el ) {
	if ( !( el instanceof HTMLAnchorElement ) ) { return true; }
	if ( el.dataset.ipUserCard === '1' ) { return true; }
	if ( !el.getAttribute( 'href' ) ) { return true; }
	if ( el.closest( '#ext-floatingui-floating' ) ) { return true; }
	if ( el.closest( '.ip-user-card' ) ) { return true; }

	return false;
}

/**
 * @param {HTMLAnchorElement} el
 */
function ensure_empty_tip( el ) {
	const next = el.nextElementSibling;
	if ( next && next.classList.contains( 'ext-floatingui-content' ) ) { return; }

	const content = document.createElement( 'span' );
	content.className = 'ext-floatingui-content';
	content.setAttribute( 'aria-hidden', 'true' );

	const card = document.createElement( 'span' );
	card.className = 'ip-user-card';
	content.append( card );
	el.after( content );
}

/**
 * @param {Element} el
 */
function bind_user_link( el ) {
	if ( should_skip_link( el ) ) { return; }

	const user_name = user_name_from_link( el );
	if ( !user_name ) { return; }

	el.dataset.ipUserCard = '1';
	el.dataset.ipUser = user_name;
	el.classList.add( 'ext-floatingui-reference', 'ip-user-card-ref' );
	ensure_empty_tip( el );
	bind_coarse_click( el );
}

function bind_user_links() {
	document.querySelectorAll( USER_LINK_SELECTOR ).forEach( bind_user_link );
}

bind_user_links();
mw.loader.using( 'ext.floatingUI' );
