'use strict';

/**
 * Moves the Citizen page sidebar below the profile tab bar, otherwise it would clip into the masthead.
 */
function sync_page_sidebar_offset() {
	// (calculates difference in px and sets it as --ip-sidebar-offset)
	if ( !document.body.classList.contains( 'integratedprofiles-profile' ) ) { return; }
	if ( !document.body.classList.contains( 'citizen-toc-enabled' ) ) { return; }

	const tabs = document.querySelector( '.ip-tabs' );
	const body_content = document.getElementById( 'bodyContent' );
	const sidebar = document.querySelector( '.citizen-page-sidebar' );

	if ( !tabs || !body_content || !sidebar ) { return; }

	const body_top = body_content.getBoundingClientRect().top;
	const tabs_bottom = tabs.getBoundingClientRect().bottom;
	const offset_px = Math.max( 0, Math.round( tabs_bottom - body_top ) );

	sidebar.style.setProperty( '--ip-sidebar-offset', offset_px + 'px' );
}

function bind_page_sidebar_offset() {
	sync_page_sidebar_offset();

	const masthead = document.querySelector( '.ip-masthead' );
	const tabs = document.querySelector( '.ip-tabs' );
	if ( typeof ResizeObserver === 'undefined' ) {
		window.addEventListener( 'resize', sync_page_sidebar_offset );
		return;
	}

	const observer = new ResizeObserver( () => {
		sync_page_sidebar_offset();
	} );

	if ( masthead ) {
		observer.observe( masthead );
	}

	if ( tabs ) {
		observer.observe( tabs );
	}

	window.addEventListener( 'resize', sync_page_sidebar_offset );
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', bind_page_sidebar_offset );
} else {
	bind_page_sidebar_offset();
}
