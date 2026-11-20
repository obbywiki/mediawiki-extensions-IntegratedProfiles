import { afterEach, expect, it, vi } from 'vitest';

afterEach( () => {
	document.body.replaceChildren();
	vi.unstubAllGlobals();
} );

it( 'loads only the upload module when the masthead avatar button is used', async () => {
	document.body.innerHTML = '<button id="integratedprofiles-edit"></button>' +
		'<button id="integratedprofiles-avatar-edit"></button>';
	const using = vi.fn().mockResolvedValue( undefined );
	const open_avatar_modal = vi.fn();
	vi.stubGlobal( 'mw', { loader: { using }, IntegratedProfiles: { open_avatar_modal } } );

	await import( '../../resources/integratedprofiles/profile_page.js' );
	document.dispatchEvent( new Event( 'DOMContentLoaded' ) );

	const avatar_button = document.getElementById( 'integratedprofiles-avatar-edit' );
	avatar_button.dispatchEvent( new Event( 'pointerenter' ) );
	avatar_button.click();
	await vi.waitFor( () => expect( open_avatar_modal ).toHaveBeenCalledWith( avatar_button ) );
	expect( using.mock.calls ).toEqual( [
		[ 'ext.IntegratedProfiles.uploadModals' ],
		[ 'ext.IntegratedProfiles.uploadModals' ]
	] );
} );
