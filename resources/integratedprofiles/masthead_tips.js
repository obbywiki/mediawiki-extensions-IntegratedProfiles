'use strict';

/**
 * @return {boolean}
 */
function is_coarse_preview() {
	return window.matchMedia( '(hover: none)' ).matches;
}

function bind_mobile_link_tips() {
	const selector = [ '.ip-links__anchor.ext-floatingui-reference', '.ip-wiki-profiles__anchor.ext-floatingui-reference' ].join( ', ' );

	document.querySelectorAll( selector ).forEach( ( el ) => {
		if ( !( el instanceof HTMLAnchorElement ) ) { return; }
		if ( !el.getAttribute( 'href' ) || el.dataset.ipTipBound === '1' ) { return; }

		el.dataset.ipTipBound = '1';
		el.addEventListener( 'click', ( event ) => {
			if ( !is_coarse_preview() ) { return; }

			event.preventDefault();
			el.focus( { preventScroll: true } );
		} );
	} );
}

bind_mobile_link_tips();
