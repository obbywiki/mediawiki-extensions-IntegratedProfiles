import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import AvatarUploadModal from '../../src/components/AvatarUploadModal.vue';
import BannerUploadModal from '../../src/components/BannerUploadModal.vue';
import type { IntegratedProfilesConfig } from '../../src/types/mw';

vi.mock( '../../src/utils/api', () => ( {
	msg: ( key: string ) => key,
	upload_avatar: vi.fn(), upload_banner: vi.fn(),
	delete_avatar: vi.fn(), delete_banner: vi.fn(), apply_payload_to_dom: vi.fn(),
} ) );
vi.mock( '../../src/utils/image_meta', () => ( {
	inspect_image_file: vi.fn( async () => ( { animated: true } ) ),
} ) );

describe.each( [ 'avatar', 'banner' ] as const )( '%s animation controls', ( kind ) => {
	beforeEach( () => {
		vi.stubGlobal( 'mw', { config: { get: (): null => null } } );
		vi.stubGlobal( 'URL', { createObjectURL: () => 'blob:preview', revokeObjectURL: vi.fn() } );
	} );
	afterEach( () => {
		vi.unstubAllGlobals();
	} );

	it.each( [ false, true ] )( 'uses its own animation flag (%s) for GIF, WebP and APNG', async ( allowed ) => {
		const config = {
			user_name: 'Example', can_edit: true,
			can_upload_avatar: true, can_upload_banner: true,
			can_upload_animated_avatar: kind === 'avatar' ? allowed : !allowed,
			can_upload_animated_banner: kind === 'banner' ? allowed : !allowed,
			fields: {}, avatar_url: '/avatar.png',
		} as IntegratedProfilesConfig;
		for ( const type of [ 'image/gif', 'image/webp', 'image/png' ] ) {
			const wrapper = mount( kind === 'avatar' ? AvatarUploadModal : BannerUploadModal, {
				props: { config }, global: { stubs: { teleport: true } },
			} );
			try {
				const input = wrapper.find( 'input[type="file"]' );
				expect( input.attributes( 'accept' )!.includes( 'image/gif' ) ).toBe( allowed );
				Object.defineProperty( input.element, 'files', { value: [ new File( [ 'animated' ], 'image', { type } ) ] } );
				await input.trigger( 'change' );
				await flushPromises();
				const confirm = wrapper.find( '.ip-upload-modal__confirm' );
				if ( allowed ) {
					expect( wrapper.find( '[role="alert"]' ).exists() ).toBe( false );
					expect( confirm.attributes( 'disabled' ) ).toBeUndefined();
				} else {
					expect( wrapper.find( '[role="alert"]' ).text() ).toBe( `integratedprofiles-error-${ kind }-animated` );
					expect( confirm.exists() ).toBe( false );
				}
			} finally {
				wrapper.unmount();
			}
		}
	} );
} );
