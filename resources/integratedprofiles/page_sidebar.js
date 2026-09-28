'use strict';

// moves `.ip-profile-chrome` into Citizen's page grid as a full-width row so .citizen-page-aside (the sidebar) starts with the article instead of overlapping the banner (v3.24+)

/**
 * @param {Element} masthead
 * @param {Element|null} tabs
 * @return {Element[]}
 */
function collect_chrome_nodes( masthead, tabs ) {
	const nodes = [ masthead ];
	if ( !tabs || tabs === masthead ) { return nodes; }

	if ( tabs.parentNode !== masthead.parentNode ) {
		nodes.push( tabs );

		return nodes;
	}

	let node = masthead.nextElementSibling;
	while ( node && node !== tabs ) {
		if ( is_article_start( node ) ) { break; }

		nodes.push( node );
		node = node.nextElementSibling;
	}

	if ( nodes[ nodes.length - 1 ] !== tabs ) {
		nodes.push( tabs );
	}

	return nodes;
}

/**
 * @param {Element} el
 * @return {boolean}
 */
function is_article_start( el ) {
	return el.id === 'mw-content-text' || el.id === 'mw-content-subtitle' || el.classList.contains( 'mw-parser-output' ) || el.classList.contains( 'ip-tab-panel' );
}

function place_profile_chrome() {
	if ( !document.body.classList.contains( 'integratedprofiles-profile' ) ) { return; }
	if ( !document.body.classList.contains( 'citizen-page-aside-enabled' ) && !document.body.classList.contains( 'citizen-toc-enabled' ) ) { return; }

	const container = document.querySelector( '.citizen-body-container' );
	const content = document.querySelector( '.citizen-body' ) || document.getElementById( 'bodyContent' );

	if ( !container || !content || content.parentNode !== container ) { return; }

	let chrome = document.querySelector( '.ip-profile-chrome' );
	if ( chrome && chrome.parentNode === container ) {
		document.body.classList.add( 'ip-profile-chrome-placed' );

		return;
	}

	if ( chrome && content.contains( chrome ) ) {
		container.insertBefore( chrome, content );
		document.body.classList.add( 'ip-profile-chrome-placed' );

		return;
	}

	const masthead = document.querySelector( '.ip-masthead' );
	const tabs = document.querySelector( '.ip-tabs' );

	if ( !masthead || masthead.closest( '.ip-profile-chrome' ) ) { return; }

	chrome = document.createElement( 'div' );
	chrome.className = 'ip-profile-chrome';
	collect_chrome_nodes( masthead, tabs ).forEach( ( node ) => {
		chrome.appendChild( node );
	} );
	container.insertBefore( chrome, content );
	document.body.classList.add( 'ip-profile-chrome-placed' );
}

function bind_profile_chrome() {
	place_profile_chrome();
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', bind_profile_chrome );
} else {
	bind_profile_chrome();
}
