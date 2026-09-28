import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

// pain

function citizen_profile_dom( extras = {} ) {
	const after = extras.after || '';
	const tabs = extras.tabs === false ? '' : '<nav class="ip-tabs"><ul class="ip-tabs__list"></ul></nav>';
	const aside_class = extras.aside_class || 'citizen-page-aside';
	const body_classes = extras.body_classes || 'integratedprofiles-profile citizen-page-aside-enabled';
	const wrap = extras.wrap !== false;
	const masthead_block = wrap ?
		`<div class="ip-profile-chrome"><div class="ip-masthead"></div>${ after }${ tabs }</div>` :
		`<div class="ip-masthead"></div>${ after }${ tabs }`;

	document.body.className = body_classes;
	document.body.innerHTML = `
		<div id="content">
			<div class="citizen-body-container">
				<div id="bodyContent" class="citizen-body">
					${ masthead_block }
					<div id="mw-content-text"><p>article</p></div>
				</div>
				<aside class="${ aside_class }"></aside>
				<footer class="citizen-page-footer"></footer>
			</div>
		</div>
	`;
	if ( extras.omit_aside ) {
		document.querySelector( 'aside' ).remove();
	}
}

async function load_script() {
	vi.resetModules();
	await import( '../../resources/integratedprofiles/page_sidebar.js' );
}

describe( 'page_sidebar chrome placement', () => {
	beforeEach( () => {
		document.body.className = '';
		document.body.innerHTML = '';
	} );

	afterEach( () => {
		document.body.className = '';
		document.body.innerHTML = '';
	} );

	it( 'lifts a server-rendered chrome wrapper into the page grid', async () => {
		citizen_profile_dom();
		await load_script();

		const chrome = document.querySelector( '.ip-profile-chrome' );
		const container = document.querySelector( '.citizen-body-container' );
		const content = document.querySelector( '.citizen-body' );

		expect( chrome ).toBeTruthy();
		expect( chrome.parentElement ).toBe( container );
		expect( chrome.nextElementSibling ).toBe( content );
		expect( chrome.querySelector( '.ip-masthead' ) ).toBeTruthy();
		expect( chrome.querySelector( '.ip-tabs' ) ).toBeTruthy();
		expect( content.querySelector( '.ip-masthead' ) ).toBeNull();
		expect( content.querySelector( '#mw-content-text' ) ).toBeTruthy();
		expect( document.body.classList.contains( 'ip-profile-chrome-placed' ) ).toBe( true );
	} );

	it( 'wraps unwrapped masthead markup as a fallback', async () => {
		citizen_profile_dom( { wrap: false } );
		await load_script();

		const chrome = document.querySelector( '.ip-profile-chrome' );
		expect( chrome.parentElement.classList.contains( 'citizen-body-container' ) ).toBe( true );
		expect( chrome.querySelector( '.ip-masthead' ) ).toBeTruthy();
		expect( chrome.querySelector( '.ip-tabs' ) ).toBeTruthy();
	} );

	it( 'keeps AfterMasthead siblings in the chrome row', async () => {
		citizen_profile_dom( { wrap: false, after: '<div class="ip-after-masthead">hook</div>' } );
		await load_script();

		const chrome = document.querySelector( '.ip-profile-chrome' );
		expect( chrome.querySelector( '.ip-after-masthead' ) ).toBeTruthy();
		expect( document.querySelector( '.citizen-body .ip-after-masthead' ) ).toBeNull();
	} );

	it( 'does not swallow the article when tabs are missing', async () => {
		citizen_profile_dom( { wrap: false, tabs: false } );
		await load_script();

		const chrome = document.querySelector( '.ip-profile-chrome' );
		expect( chrome.querySelector( '.ip-masthead' ) ).toBeTruthy();
		expect( chrome.querySelector( '#mw-content-text' ) ).toBeNull();
		expect( document.querySelector( '.citizen-body #mw-content-text' ) ).toBeTruthy();
	} );

	it( 'still lifts when the aside node has not been parsed yet', async () => {
		citizen_profile_dom( { omit_aside: true } );
		await load_script();

		expect( document.querySelector( '.ip-profile-chrome' ).parentElement.classList.contains( 'citizen-body-container' ) ).toBe( true );
		expect( document.body.classList.contains( 'ip-profile-chrome-placed' ) ).toBe( true );
	} );

	it( 'is a no-op without Citizen aside body classes', async () => {
		citizen_profile_dom( { wrap: false, body_classes: 'integratedprofiles-profile' } );
		await load_script();

		expect( document.querySelector( '.ip-profile-chrome' ) ).toBeNull();
		expect( document.querySelector( '.citizen-body .ip-masthead' ) ).toBeTruthy();
	} );

	it( 'still matches the pre-rename sidebar class', async () => {
		citizen_profile_dom( {
			wrap: false,
			aside_class: 'citizen-page-sidebar',
			body_classes: 'integratedprofiles-profile citizen-toc-enabled'
		} );
		await load_script();

		expect( document.querySelector( '.ip-profile-chrome .ip-masthead' ) ).toBeTruthy();
	} );

	it( 'does not wrap twice', async () => {
		citizen_profile_dom();
		await load_script();
		await load_script();

		expect( document.querySelectorAll( '.ip-profile-chrome' ) ).toHaveLength( 1 );
		expect( document.querySelectorAll( '.ip-masthead' ) ).toHaveLength( 1 );
	} );
} );
