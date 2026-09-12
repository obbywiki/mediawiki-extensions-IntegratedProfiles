'use strict';

const COLLAPSED_LINES = 2;
const EXPANDED_LINES = 20;
const MEASURE_DEBOUNCE_MS = 80;

/**
 * @param {HTMLElement} el
 * @return {number}
 */
function line_height_px( el ) {
	const styles = window.getComputedStyle( el );
	const line_height = parseFloat( styles.lineHeight );
	if ( Number.isFinite( line_height ) && line_height > 0 ) {
		return line_height;
	}

	const font_size = parseFloat( styles.fontSize );
	return ( Number.isFinite( font_size ) && font_size > 0 ? font_size : 16 ) * 1.4;
}

// one must imagine a world with typescript...

/**
 * @param {HTMLElement} el
 * @return {number}
 */
function unclamped_height( el ) {
	const width = el.clientWidth;
	if ( width <= 0 ) { return el.scrollHeight; }

	const styles = window.getComputedStyle( el );
	const clone = /** @type {HTMLElement} */ ( el.cloneNode( true ) );
	clone.removeAttribute( 'id' );
	clone.classList.add( 'ip-about__text--measure' );
	clone.style.width = width + 'px';
	clone.style.font = styles.font;
	clone.style.fontSize = styles.fontSize;
	clone.style.lineHeight = styles.lineHeight;
	clone.style.letterSpacing = styles.letterSpacing;
	clone.style.whiteSpace = styles.whiteSpace;
	clone.style.overflowWrap = styles.overflowWrap;
	document.body.appendChild( clone );

	const height = clone.scrollHeight;
	clone.remove();

	return height;
}

/**
 * @param {HTMLElement} about
 * @param {boolean} force
 */
function measure_about( about, force ) {
	const text = about.querySelector( '.ip-about__text' );
	const toggle = about.querySelector( '.ip-about__toggle' );
	if ( !( text instanceof HTMLElement ) || !( toggle instanceof HTMLElement ) ) { return; }

	const width = String( text.clientWidth );
	if ( !force && about.dataset.ipAboutWidth === width ) { return; }
	about.dataset.ipAboutWidth = width;

	const line_height = line_height_px( text );
	const height = unclamped_height( text );
	const overflows_collapsed = height > ( line_height * COLLAPSED_LINES ) + 2;
	const overflows_expanded = height > ( line_height * EXPANDED_LINES ) + 2;

	about.classList.toggle( 'ip-about--clamped', overflows_collapsed );
	about.classList.toggle( 'ip-about--capped', overflows_expanded );
	toggle.hidden = !overflows_collapsed;

	if ( !overflows_collapsed ) { about.classList.remove( 'ip-about--expanded' ); }

	toggle.setAttribute(
		'aria-expanded',
		about.classList.contains( 'ip-about--expanded' ) ? 'true' : 'false'
	);
}

/**
 * @param {HTMLElement} about
 */
function schedule_measure( about ) {
	const previous = about.dataset.ipAboutTimer;
	if ( previous ) {
		clearTimeout( Number( previous ) );
	}

	const timer = setTimeout( () => {
		delete about.dataset.ipAboutTimer;
		measure_about( about, false );
	}, MEASURE_DEBOUNCE_MS );
	about.dataset.ipAboutTimer = String( timer );
}

/**
 * @param {HTMLElement} about
 */
function bind_about( about ) {
	const text = about.querySelector( '.ip-about__text' );
	const toggle = about.querySelector( '.ip-about__toggle' );
	if ( !( text instanceof HTMLElement ) || !( toggle instanceof HTMLElement ) ) {
		return;
	}

	if ( about.dataset.ipAboutBound === '1' ) {
		measure_about( about, true );
		return;
	}

	about.dataset.ipAboutBound = '1';

	toggle.addEventListener( 'click', () => {
		about.classList.toggle( 'ip-about--expanded' );
		toggle.setAttribute(
			'aria-expanded',
			about.classList.contains( 'ip-about--expanded' ) ? 'true' : 'false'
		);
	} );

	measure_about( about, true );

	if ( typeof ResizeObserver !== 'function' ) {
		window.addEventListener( 'resize', () => {
			schedule_measure( about );
		} );
		return;
	}

	const observer = new ResizeObserver( () => {
		schedule_measure( about );
	} );
	observer.observe( text );
}

/**
 * @param {Element|Document|undefined} root
 */
function enhance_about_block( root ) {
	const scope = root && root.nodeType === 1 ? root : document;
	const abouts = [];

	if ( scope instanceof HTMLElement && scope.classList.contains( 'ip-about' ) ) {
		abouts.push( scope );
	} else if ( typeof scope.querySelectorAll === 'function' ) {
		scope.querySelectorAll( '.ip-about' ).forEach( ( about ) => {
			if ( about instanceof HTMLElement ) {
				abouts.push( about );
			}
		} );
	}

	abouts.forEach( bind_about );
}

mw.hook( 'integratedprofiles.about' ).add( enhance_about_block );

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', () => {
		enhance_about_block( document );
	} );
} else {
	enhance_about_block( document );
}
