import { createApp, type App } from 'vue';
import AvatarUploadModal from '../components/AvatarUploadModal.vue';
import BannerUploadModal from '../components/BannerUploadModal.vue';
import ProfileEditor from '../components/ProfileEditor.vue';
import type { IntegratedProfilesConfig } from '../types/mw';

let avatar_modal_app: App | null = null;
let banner_modal_app: App | null = null;

function read_config(): IntegratedProfilesConfig | null {
	const config = mw.config.get( 'wgIntegratedProfiles' ) as IntegratedProfilesConfig | null;
	if ( !config || !config.can_edit ) { return null; }

	return config;
}

function modal_root(): HTMLElement | null {
	return document.getElementById( 'integratedprofiles-modal-root' );
}

function unmount_avatar_modal(): void {
	const mount_root = modal_root();

	if ( avatar_modal_app ) {
		avatar_modal_app.unmount();
		avatar_modal_app = null;
	}

	if ( mount_root && !banner_modal_app ) {
		mount_root.replaceChildren();
		mount_root.dataset.ipMounted = '';
	}
}

function unmount_banner_modal(): void {
	const mount_root = modal_root();

	if ( banner_modal_app ) {
		banner_modal_app.unmount();
		banner_modal_app = null;
	}

	if ( mount_root && !avatar_modal_app ) {
		mount_root.replaceChildren();
		mount_root.dataset.ipMounted = '';
	}
}

function mount_avatar_modal( mount_root: HTMLElement, return_focus?: HTMLElement | null ): App | null {
	const config = read_config();
	if ( !config ) { return null; }

	unmount_banner_modal();

	if ( avatar_modal_app || mount_root.dataset.ipMounted === '1' ) {
		return avatar_modal_app;
	}

	const app = createApp( AvatarUploadModal, {
		config,
		return_focus: return_focus || null,
		onClose: () => {
			unmount_avatar_modal();
		},
	} );

	app.mount( mount_root );
	mount_root.dataset.ipMounted = '1';
	avatar_modal_app = app;

	return app;
}

function mount_banner_modal(
	mount_root: HTMLElement,
	return_focus?: HTMLElement | null,
): App | null {
	const config = read_config();
	if ( !config ) {
		return null;
	}

	unmount_avatar_modal();

	if ( banner_modal_app || mount_root.dataset.ipMounted === '1' ) {
		return banner_modal_app;
	}

	const app = createApp( BannerUploadModal, {
		config,
		return_focus: return_focus || null,
		onClose: () => {
			unmount_banner_modal();
		},
	} );
	app.mount( mount_root );
	mount_root.dataset.ipMounted = '1';
	banner_modal_app = app;
	return app;
}

function open_avatar_modal( return_focus?: HTMLElement | null ): App | null {
	const mount_root = modal_root();
	if ( !mount_root ) {
		return null;
	}
	return mount_avatar_modal( mount_root, return_focus );
}

function open_banner_modal( return_focus?: HTMLElement | null ): App | null {
	const mount_root = modal_root();
	if ( !mount_root ) {
		return null;
	}
	return mount_banner_modal( mount_root, return_focus );
}

function mount_editor( mount_root: HTMLElement ): App | null {
	const config = read_config();
	if ( !config ) {
		return null;
	}

	const app = createApp( ProfileEditor, {
		config,
		onClose: () => {
			app.unmount();
			mount_root.replaceChildren();
			mount_root.dataset.ipMounted = '';
			const edit_button = document.getElementById( 'integratedprofiles-edit' );
			if ( edit_button ) {
				edit_button.hidden = false;
			}
		},
	} );
	app.mount( mount_root );
	return app;
}

mw.IntegratedProfiles = {
	mount_editor,
	mount_avatar_modal,
	open_avatar_modal,
	mount_banner_modal,
	open_banner_modal,
};
