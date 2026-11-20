'use strict';

const EDITOR_MODULE = 'ext.IntegratedProfiles.app';
const UPLOAD_MODALS_MODULE = 'ext.IntegratedProfiles.uploadModals';

/**
 * @param {HTMLButtonElement|null} trigger
 * @param {boolean} busy
 */
function set_trigger_busy( trigger, busy ) {
	if ( !trigger ) {
		return;
	}

	trigger.disabled = busy;
	if ( busy ) {
		trigger.setAttribute( 'aria-busy', 'true' );
	} else {
		trigger.removeAttribute( 'aria-busy' );
	}
}

/**
 * Prefetches the module for the given trigger.
 *
 * @param {HTMLElement|null} trigger
 * @param {string} module_name
 */
function prefetch_module( trigger, module_name ) {
	if ( !trigger || trigger.dataset.ipPrefetch === '1' ) {
		return;
	}

	trigger.dataset.ipPrefetch = '1';
	const prefetch = () => {
		mw.loader.using( module_name ).catch( () => {} );
	};
	trigger.addEventListener( 'pointerenter', prefetch, { once: true } );
	trigger.addEventListener( 'focus', prefetch, { once: true } );
}

function open_editor() {
	const mount_root = document.getElementById( 'integratedprofiles-editor-root' );
	if ( !mount_root || mount_root.dataset.ipMounted === '1' ) {
		return;
	}

	const edit_button = document.getElementById( 'integratedprofiles-edit' );
	set_trigger_busy( edit_button, true );

	mw.loader.using( EDITOR_MODULE ).then( () => {
		if (
			!mw.IntegratedProfiles ||
			typeof mw.IntegratedProfiles.mount_editor !== 'function'
		) {
			set_trigger_busy( edit_button, false );
			return;
		}

		if ( mount_root.dataset.ipMounted === '1' ) {
			set_trigger_busy( edit_button, false );
			return;
		}

		if ( edit_button ) {
			edit_button.hidden = true;
		}
		set_trigger_busy( edit_button, false );

		mw.IntegratedProfiles.mount_editor( mount_root );
		mount_root.dataset.ipMounted = '1';
	} ).catch( () => {
		set_trigger_busy( edit_button, false );
	} );
}

/**
 * @param {HTMLButtonElement} avatar_button
 */
function open_avatar_modal( avatar_button ) {
	set_trigger_busy( avatar_button, true );

	mw.loader.using( UPLOAD_MODALS_MODULE ).then( () => {
		set_trigger_busy( avatar_button, false );
		if (
			!mw.IntegratedProfiles ||
			typeof mw.IntegratedProfiles.open_avatar_modal !== 'function'
		) {
			return;
		}

		mw.IntegratedProfiles.open_avatar_modal( avatar_button );
	} ).catch( () => {
		set_trigger_busy( avatar_button, false );
	} );
}

/**
 * Move the edit trigger into Citizen's page tools, if applicable.
 */
function merge_edit_button_into_page_tools() {
	const edit_button = document.getElementById( 'integratedprofiles-edit' );
	const page_actions = document.querySelector( '.citizen-page-actions' );
	if ( !edit_button || !page_actions || edit_button.parentElement === page_actions ) { return; }

	const more_menu = page_actions.querySelector( '.citizen-page-actions-more' );
	page_actions.insertBefore( edit_button, more_menu );

	edit_button.classList.remove( 'cdx-button--icon-only', 'cdx-button--action-progressive', 'cdx-button--weight-primary' );
	edit_button.classList.add( 'citizen-cdx-button--size-large', 'cdx-button--weight-quiet' );

	if ( !edit_button.querySelector( '.citizen-ui-icon' ) ) {
		const icon = document.createElement( 'span' );
		icon.className = 'citizen-ui-icon mw-ui-icon-wikimedia-userAvatar';
		icon.setAttribute( 'aria-hidden', 'true' );
		edit_button.insertBefore( icon, edit_button.firstChild );
	}
}

/**
 * The edit trigger is hidden initially so it doesn't show up in the masthead before getting moved into the toolbar.
 */
function reveal_edit_button() {
	const edit_button = document.getElementById( 'integratedprofiles-edit' );

	if ( edit_button ) {
		edit_button.classList.remove( 'ip-edit-button--unplaced' );
	}
}

function bind_edit_button() {
	merge_edit_button_into_page_tools();
	reveal_edit_button();

	const edit_button = document.getElementById( 'integratedprofiles-edit' );
	if ( !edit_button || edit_button.dataset.ipBound === '1' ) { return; }

	edit_button.dataset.ipBound = '1';
	prefetch_module( edit_button, EDITOR_MODULE );
	edit_button.addEventListener( 'click', () => {
		const mount_root = document.getElementById( 'integratedprofiles-editor-root' );
		if ( mount_root ) {
			mount_root.dataset.ipMounted = '';
		}

		open_editor();
	} );
}

function bind_avatar_edit_button() {
	const avatar_button = document.getElementById( 'integratedprofiles-avatar-edit' );
	if ( !avatar_button || avatar_button.dataset.ipBound === '1' ) { return; }

	avatar_button.dataset.ipBound = '1';
	prefetch_module( avatar_button, UPLOAD_MODALS_MODULE );
	avatar_button.addEventListener( 'click', () => {
		open_avatar_modal( avatar_button );
	} );
}

function init() {
	bind_edit_button();
	bind_avatar_edit_button();
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', init );
} else {
	init();
}
