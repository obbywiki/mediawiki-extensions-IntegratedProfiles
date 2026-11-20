import { createApp, type App } from 'vue';
import ProfileEditor from '../components/ProfileEditor.vue';
import type { IntegratedProfilesConfig } from '../types/mw';

function mount_editor( mount_root: HTMLElement ): App | null {
	const config = mw.config.get( 'wgIntegratedProfiles' ) as IntegratedProfilesConfig | null;
	if ( !config || ( !config.can_edit && !config.can_remove ) ) {
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

mw.IntegratedProfiles = { ...mw.IntegratedProfiles, mount_editor };
