import { flushPromises, mount, type VueWrapper } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import ProfileEditor from '../../src/components/ProfileEditor.vue';
import type { IntegratedProfilesConfig } from '../../src/types/mw';

const api = vi.hoisted( () => ( {
	clear: vi.fn(), save: vi.fn(), delete_avatar: vi.fn(), delete_banner: vi.fn(),
	apply: vi.fn(), sync_banner: vi.fn(),
} ) );
vi.mock( '../../src/utils/api', () => ( {
	msg: ( key: string, ...params: string[] ) => [ key, ...params ].join( ':' ),
	clear_profile_fields: api.clear,
	save_profile_fields: api.save,
	delete_avatar: api.delete_avatar,
	delete_banner: api.delete_banner,
	apply_payload_to_dom: api.apply,
	sync_banner_dom: api.sync_banner,
} ) );

let config: IntegratedProfilesConfig;
function mount_editor(): VueWrapper {
	return mount( ProfileEditor, { props: { config } } );
}
function profile( overrides: Record<string, unknown> = {} ): Record<string, unknown> {
	return {
		fields: { ...config.fields, 'ip-about': '' },
		avatar_url: '/default.svg', has_custom_avatar: false,
		has_custom_banner: false, banner_url: '', ...overrides,
	};
}

describe( 'profile editor permission modes', () => {
	beforeEach( () => {
		vi.clearAllMocks();
		config = {
			user_name: 'Example', can_edit: false, can_remove: true,
			can_upload_avatar: false, can_upload_banner: false,
			fields: { 'ip-about': 'Hello', 'ip-location': '', 'ip-banner': 'custom', 'ip-visibility': 'public' },
			removable_fields: [ 'ip-about', 'ip-location' ],
			has_custom_avatar: true, has_custom_banner: true,
			avatar_url: '/avatar.png', banner_url: '/banner.png',
			show_connection_privacy: true, show_manage_connections: true,
			enabled_social_links: [],
		} as unknown as IntegratedProfilesConfig;
		vi.stubGlobal( 'mw', {
			config: { get: ( key: string ) => key === 'wgIntegratedProfiles' ? config : 'Test wiki' },
			message: () => ( { exists: () => true } ),
		} );
	} );

	it( 'reuses the editor fields and previews with replacement and privacy controls restricted', () => {
		const wrapper = mount_editor();
		expect( wrapper.find( '#ip-field-about' ).element ).toHaveProperty( 'value', 'Hello' );
		for ( const input of wrapper.findAll( 'textarea, input[type="text"], input[type="url"]' ) ) {
			expect( input.attributes( 'readonly' ) ).toBeDefined();
		}
		expect( wrapper.find( '.ip-editor__avatar-preview-image' ).attributes( 'src' ) ).toBe( '/avatar.png' );
		expect( wrapper.find( '.ip-editor__avatar-button' ).attributes( 'disabled' ) ).toBeDefined();
		const delete_button = wrapper.find( '#ip-field-about + button' );
		expect( delete_button.classes() ).toContain( 'cdx-button--icon-only' );
		expect( delete_button.classes() ).toContain( 'cdx-button--action-destructive' );
		expect( delete_button.attributes( 'aria-label' ) ).toContain( 'integratedprofiles-field-about' );
		expect( delete_button.find( 'svg' ).attributes( 'aria-hidden' ) ).toBe( 'true' );
		expect( delete_button.element.parentElement?.classList.contains( 'ip-editor__removal-target--active' ) ).toBe( true );
		expect( wrapper.find( '.ip-editor__banner-presets' ).exists() ).toBe( false );
		expect( wrapper.find( '#ip-field-visibility-label, #ip-field-show-pronouns, #ip-field-hide-connections' ).exists() ).toBe( false );
		expect( wrapper.find( '[aria-labelledby="ip-section-connections"]' ).exists() ).toBe( false );
		wrapper.unmount();
	} );

	it( 'stages removals and sends only field names through the clear API on save', async () => {
		api.clear.mockResolvedValue( profile() );
		const wrapper = mount_editor();
		await wrapper.find( '#ip-field-about + button' ).trigger( 'click' );
		expect( api.clear ).not.toHaveBeenCalled();
		expect( wrapper.find( '#ip-field-about' ).element ).toHaveProperty( 'value', '' );
		await wrapper.find( '.ip-editor__actions button:last-child' ).trigger( 'click' );
		await flushPromises();
		expect( api.clear ).toHaveBeenCalledWith( [ 'ip-about' ], 'Example' );
		expect( api.save ).not.toHaveBeenCalled();
		expect( api.apply ).toHaveBeenCalled();
		expect( wrapper.find( '[role="status"]' ).exists() ).toBe( true );
		expect( wrapper.find( '.ip-editor__actions button:last-child' ).attributes( 'disabled' ) ).toBeDefined();
		wrapper.unmount();
	} );

	it( 'retains staged removals for retry after an API denial', async () => {
		api.clear.mockRejectedValue( new Error( 'Permission denied' ) );
		const wrapper = mount_editor();
		await wrapper.find( '#ip-field-about + button' ).trigger( 'click' );
		await wrapper.find( '.ip-editor__actions button:last-child' ).trigger( 'click' );
		await flushPromises();
		expect( wrapper.find( '[role="alert"]' ).text() ).toBe( 'Permission denied' );
		expect( wrapper.find( '.ip-editor__actions button:last-child' ).attributes( 'disabled' ) ).toBeUndefined();
		wrapper.unmount();
	} );

	it( 'deletes media in place without discarding staged content removals', async () => {
		api.delete_banner.mockResolvedValue( profile( { fields: { ...config.fields, 'ip-banner': 'accent' } } ) );
		api.clear.mockResolvedValue( profile() );
		const wrapper = mount_editor();
		await wrapper.find( '#ip-field-about + button' ).trigger( 'click' );
		await wrapper.find( '.ip-editor__appearance-banners button' ).trigger( 'click' );
		await flushPromises();
		expect( api.delete_banner ).toHaveBeenCalledWith( 'Example' );
		expect( wrapper.find( '.ip-editor__appearance-banners button' ).exists() ).toBe( false );
		expect( wrapper.find( '.ip-editor__actions button:last-child' ).attributes( 'disabled' ) ).toBeUndefined();
		await wrapper.find( '.ip-editor__actions button:last-child' ).trigger( 'click' );
		await flushPromises();
		expect( api.clear ).toHaveBeenCalledWith( [ 'ip-about' ], 'Example' );
		expect( wrapper.find( '.ip-editor__actions button:last-child' ).attributes( 'disabled' ) ).toBeDefined();
		wrapper.unmount();
	} );

	it.each( [ [ true, false ], [ false, true ], [ false, false ], [ true, true ] ] )(
		'controls avatar (%s) and banner (%s) uploads independently while keeping editing',
		async ( avatar, banner ) => {
			config.can_edit = true;
			config.can_upload_avatar = avatar;
			config.can_upload_banner = banner;
			const open_avatar = vi.fn();
			const open_banner = vi.fn();
			mw.IntegratedProfiles = {
				open_avatar_modal: open_avatar, open_banner_modal: open_banner,
				mount_editor: vi.fn(), mount_avatar_modal: vi.fn(), mount_banner_modal: vi.fn(),
			};
			const wrapper = mount_editor();
			expect( wrapper.find( '.ip-editor__avatar-button' ).attributes( 'disabled' ) !== undefined ).toBe( !avatar );
			expect( wrapper.find( '.ip-editor__banner-swatch--add' ).exists() ).toBe( banner );
			expect( wrapper.find( '.ip-editor__banner-presets' ).exists() ).toBe( true );
			expect( wrapper.find( '#ip-field-about' ).attributes( 'readonly' ) ).toBeUndefined();
			await wrapper.find( '.ip-editor__avatar-button' ).trigger( 'click' );
			expect( open_avatar ).toHaveBeenCalledTimes( avatar ? 1 : 0 );
			if ( banner ) {
				await wrapper.find( '.ip-editor__banner-swatch--add' ).trigger( 'click' );
				expect( open_banner ).toHaveBeenCalledOnce();
			}
			wrapper.unmount();
		}
	);

	it.each( [ 'avatar', 'banner' ] )( 'keeps %s deletion available without upload permission', async ( kind ) => {
		config.can_edit = true;
		api.delete_avatar.mockResolvedValue( profile() );
		api.delete_banner.mockResolvedValue( profile() );
		const wrapper = mount_editor();
		await wrapper.find( `[aria-label="integratedprofiles-${ kind }-delete"]` ).trigger( 'click' );
		await flushPromises();
		expect( kind === 'avatar' ? api.delete_avatar : api.delete_banner ).toHaveBeenCalledWith( 'Example' );
		wrapper.unmount();
	} );

	it( 'keeps normal editing and the existing save API for users with edit permission', async () => {
		config.can_edit = true;
		api.save.mockResolvedValue( profile() );
		const wrapper = mount_editor();
		expect( wrapper.find( '#ip-field-about' ).attributes( 'readonly' ) ).toBeUndefined();
		expect( wrapper.find( '#ip-field-about + button' ).exists() ).toBe( false );
		expect( wrapper.find( '#ip-field-visibility-label' ).exists() ).toBe( true );
		await wrapper.find( '#ip-field-about' ).setValue( 'Updated' );
		await wrapper.find( '.ip-editor__actions button:last-child' ).trigger( 'click' );
		await flushPromises();
		expect( api.save ).toHaveBeenCalledWith( expect.objectContaining( { 'ip-about': 'Updated' } ), 'Example' );
		expect( api.clear ).not.toHaveBeenCalled();
		wrapper.unmount();
	} );
} );
