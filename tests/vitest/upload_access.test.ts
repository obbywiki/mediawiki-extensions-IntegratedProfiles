import { beforeEach, describe, expect, it, vi } from 'vitest';
import type { IntegratedProfilesConfig } from '../../src/types/mw';

const create_app = vi.hoisted( () => vi.fn( () => ( { mount: vi.fn(), unmount: vi.fn() } ) ) );
vi.mock( 'vue', () => ( { createApp: create_app } ) );
vi.mock( '../../src/components/AvatarUploadModal.vue', () => ( { default: {} } ) );
vi.mock( '../../src/components/BannerUploadModal.vue', () => ( { default: {} } ) );
vi.mock( '../../src/components/ProfileEditor.vue', () => ( { default: {} } ) );

describe( 'upload modal entry points', () => {
	let config: IntegratedProfilesConfig;

	beforeEach( async () => {
		vi.resetModules();
		vi.clearAllMocks();
		document.body.innerHTML = '<div id="integratedprofiles-modal-root"></div>';
		config = {
			can_edit: true, can_remove: true,
			can_upload_avatar: false, can_upload_banner: false,
		} as IntegratedProfilesConfig;
		vi.stubGlobal( 'mw', { config: { get: () => config } } );
		await import( '../../src/app/main' );
	} );

	it.each( [ 'avatar', 'banner' ] as const )( 'requires the matching right to open the %s modal', ( kind ) => {
		config[ kind === 'avatar' ? 'can_upload_banner' : 'can_upload_avatar' ] = true;
		const open = kind === 'avatar' ? mw.IntegratedProfiles!.open_avatar_modal : mw.IntegratedProfiles!.open_banner_modal;
		const mount = kind === 'avatar' ? mw.IntegratedProfiles!.mount_avatar_modal : mw.IntegratedProfiles!.mount_banner_modal;
		const root = document.getElementById( 'integratedprofiles-modal-root' )!;
		expect( open() ).toBeNull();
		expect( mount( root ) ).toBeNull();
		expect( create_app ).not.toHaveBeenCalled();
		config[ kind === 'avatar' ? 'can_upload_avatar' : 'can_upload_banner' ] = true;
		expect( open() ).not.toBeNull();
		expect( create_app ).toHaveBeenCalledOnce();
	} );

	it( 'does not let upload rights bypass removal-only profile access', () => {
		config.can_edit = false;
		config.can_upload_avatar = true;
		config.can_upload_banner = true;
		expect( mw.IntegratedProfiles!.open_avatar_modal() ).toBeNull();
		expect( mw.IntegratedProfiles!.open_banner_modal() ).toBeNull();
		expect( create_app ).not.toHaveBeenCalled();
	} );
} );
