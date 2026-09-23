import { describe, expect, it, vi, beforeEach, afterEach } from 'vitest';
import { api_error_message, apply_payload_to_dom } from '../../src/utils/api.ts';

/**
 * mw.Api hands back a jQuery promise, not a native one, and rejects it with
 * ( code, result ). The stubs below mirror that so the code under test exercises
 * the same contract it sees in a browser.
 */
function jq_resolved( value ) {
	return {
		done( callback ) {
			callback( value );
			return this;
		},
		fail() {
			return this;
		},
	};
}

function jq_rejected( code, result ) {
	return {
		done() {
			return this;
		},
		fail( callback ) {
			callback( code, result );
			return this;
		},
	};
}

function stub_mw( postWithToken, config = null ) {
	globalThis.mw = {
		Api: vi.fn( function () {
			this.postWithToken = postWithToken;
		} ),
		message: vi.fn( ( key ) => ( {
			text: () => key,
		} ) ),
		config: {
			get: vi.fn( ( key ) => {
				if ( key === 'wgIntegratedProfiles' ) {
					return config;
				}
				return null;
			} ),
		},
		hook: vi.fn( () => ( {
			fire: vi.fn(),
			add: vi.fn(),
		} ) ),
		loader: {
			getState: vi.fn( () => null ),
			using: vi.fn( () => Promise.resolve() ),
		},
	};
}

describe( 'api_error_message', () => {
	beforeEach( () => {
		stub_mw( vi.fn() );
	} );

	it( 'reads MediaWiki API error.info', () => {
		expect( api_error_message( { error: { info: 'Nope' } } ) ).toBe( 'Nope' );
	} );

	it( 'falls back to Error message', () => {
		expect( api_error_message( new Error( 'boom' ) ) ).toBe( 'boom' );
	} );

	it( 'maps HTTP 413 proxy rejections to a clear upload message', () => {
		expect( api_error_message( {
			code: 'http',
			result: {
				xhr: { status: 413 },
				textStatus: 'error',
				exception: 'Request Entity Too Large',
			},
		} ) ).toBe( 'integratedprofiles-error-upload-too-large' );
	} );

	it( 'does not surface a bare http code', () => {
		expect( api_error_message( {
			code: 'http',
			result: {
				xhr: { status: 502 },
				textStatus: 'error',
				exception: 'Bad Gateway',
			},
		} ) ).toBe( '' );
	} );
} );

describe( 'apply_payload_to_dom', () => {
	beforeEach( () => {
		stub_mw( vi.fn() );
		document.body.innerHTML = `
			<div class="ip-masthead">
				<div class="ip-masthead__hero" aria-hidden="true">
					<div class="ip-masthead__band ip-masthead__band--accent"></div>
				</div>
				<div class="ip-masthead__body">
					<img class="ip-avatar__image" src="/old.svg" alt="" />
					<div class="ip-links-wrap">
						<ul class="ip-links"></ul>
					</div>
				</div>
				<div class="ip-about-block">
					<div class="ip-about">Old</div>
				</div>
				<div id="integratedprofiles-editor-root"></div>
			</div>
		`;
	} );

	afterEach( () => {
		document.body.innerHTML = '';
	} );

	it( 'updates about, links, and avatar from payload', () => {
		apply_payload_to_dom( {
			user_id: 1,
			user_name: 'User1',
			real_name: '',
			edit_count: 1,
			registration: null,
			groups: [],
			fields: {
				'ip-about': 'New bio',
				'ip-website': '',
				'ip-twitter': '',
				'ip-github': '',
				'ip-mediawiki': '',
				'ip-miraheze': '',
				'ip-fandom': '',
				'ip-banner': 'accent',
			},
			links: {
				github: {
					label: 'user1',
					url: 'https://github.com/user1',
					kind: 'github',
				},
			},
			avatar_url: '/new-avatar.png?r=1',
			has_custom_avatar: true,
			banner_url: '',
			has_custom_banner: false,
			connections: [],
			ui: { color: '#5288F1', avatar_border_radius: '50%' },
		} );

		expect( document.querySelector( '.ip-about__text' )?.textContent ).toBe( 'New bio' );
		const link = document.querySelector( '.ip-links a' );
		expect( link?.getAttribute( 'href' ) ).toBe( 'https://github.com/user1' );
		expect( link?.getAttribute( 'aria-label' ) ).toBe( 'user1' );
		expect( link?.querySelector( '.ip-links__icon' ) ).not.toBeNull();
		expect( document.querySelector( '.ip-masthead__body > .ip-links-wrap > .ip-links' ) )
			.not.toBeNull();
		expect( document.querySelector( '.ip-avatar__image' )?.getAttribute( 'src' ) )
			.toBe( '/new-avatar.png?r=1' );
	} );

	it( 'renders social chips without a URL as a span', () => {
		apply_payload_to_dom( {
			user_id: 1,
			user_name: 'User1',
			real_name: '',
			edit_count: 1,
			registration: null,
			groups: [],
			fields: {
				'ip-about': '',
				'ip-banner': 'accent',
			},
			links: {
				discord: {
					label: 'user1',
					url: '',
					kind: 'discord',
				},
			},
			avatar_url: '/x.svg',
			has_custom_avatar: false,
			banner_url: '',
			has_custom_banner: false,
			connections: [],
			ui: { color: '#5288F1', avatar_border_radius: '50%' },
		} );

		expect( document.querySelector( '.ip-links a' ) ).toBeNull();
		const chip = document.querySelector( '.ip-links span.ip-links__anchor' );
		expect( chip?.getAttribute( 'aria-label' ) ).toBe( 'user1' );
		expect( chip?.getAttribute( 'role' ) ).toBe( 'img' );
		expect( chip?.querySelector( '.ip-links__icon' ) ).not.toBeNull();
	} );

	it( 'updates banner preset class on the masthead band', () => {
		apply_payload_to_dom( {
			user_id: 1,
			user_name: 'User1',
			real_name: '',
			edit_count: 1,
			registration: null,
			groups: [],
			fields: {
				'ip-about': '',
				'ip-website': '',
				'ip-twitter': '',
				'ip-github': '',
				'ip-mediawiki': '',
				'ip-miraheze': '',
				'ip-fandom': '',
				'ip-banner': 'ocean',
			},
			links: {},
			avatar_url: '/x.svg',
			has_custom_avatar: false,
			banner_url: '',
			has_custom_banner: false,
			connections: [],
			ui: { color: '#5288F1', avatar_border_radius: '50%' },
		} );

		const band = document.querySelector( '.ip-masthead__band' );
		expect( band?.className ).toBe( 'ip-masthead__band ip-masthead__band--ocean' );
		expect( document.querySelector( '.ip-masthead' )?.style.getPropertyValue( '--ip-banner-image' ) )
			.toBe( '' );
	} );

	it( 'applies custom banner image CSS variable', () => {
		apply_payload_to_dom( {
			user_id: 1,
			user_name: 'User1',
			real_name: '',
			edit_count: 1,
			registration: null,
			groups: [],
			fields: {
				'ip-about': '',
				'ip-website': '',
				'ip-twitter': '',
				'ip-github': '',
				'ip-mediawiki': '',
				'ip-miraheze': '',
				'ip-fandom': '',
				'ip-banner': 'custom',
			},
			links: {},
			avatar_url: '/x.svg',
			has_custom_avatar: false,
			banner_url: '/ipbanners/banner_1.png?r=2',
			has_custom_banner: true,
			connections: [],
			ui: { color: '#5288F1', avatar_border_radius: '50%' },
		} );

		const band = document.querySelector( '.ip-masthead__band' );
		expect( band?.className ).toBe( 'ip-masthead__band ip-masthead__band--custom' );
		expect( document.querySelector( '.ip-masthead' )?.style.getPropertyValue( '--ip-banner-image' ) )
			.toBe( 'url(/ipbanners/banner_1.png?r=2)' );
	} );

	it( 'preserves verified connection icons when updating freeform links', () => {
		document.body.innerHTML = `
			<div class="ip-masthead">
				<div class="ip-masthead__body">
					<img class="ip-avatar__image" src="/old.svg" alt="" />
					<div class="ip-links-wrap">
						<ul class="ip-links">
							<li class="ip-links__item ip-links__item--discord ip-links__item--verified">
								<a class="ip-links__anchor" href="https://discord.com/users/1">
									<span class="ip-links__icon"></span>
									<span class="ip-links__badge"></span>
								</a>
							</li>
						</ul>
					</div>
				</div>
			</div>
		`;

		apply_payload_to_dom( {
			user_id: 1,
			user_name: 'User1',
			real_name: '',
			edit_count: 1,
			registration: null,
			groups: [],
			fields: {
				'ip-about': '',
				'ip-website': '',
				'ip-twitter': '',
				'ip-github': '',
				'ip-mediawiki': '',
				'ip-miraheze': '',
				'ip-fandom': '',
				'ip-banner': 'accent',
			},
			links: {
				github: {
					label: 'user1',
					url: 'https://github.com/user1',
					kind: 'github',
				},
			},
			avatar_url: '/x.svg',
			has_custom_avatar: false,
			connections: [],
			ui: { color: '#5288F1', avatar_border_radius: '50%' },
		} );

		expect( document.querySelector( '.ip-links__item--github' ) ).not.toBeNull();
		expect( document.querySelector( '.ip-links__item--verified' ) ).not.toBeNull();
		expect( document.querySelector( '.ip-links__item--discord' ) ).not.toBeNull();
	} );

	it( 'renders and removes the location row', () => {
		apply_payload_to_dom( {
			user_id: 1,
			user_name: 'User1',
			real_name: '',
			edit_count: 1,
			registration: null,
			groups: [],
			fields: {
				'ip-about': 'New bio',
				'ip-location': 'Seoul',
				'ip-banner': 'accent',
			},
			links: {},
			avatar_url: '/x.svg',
			has_custom_avatar: false,
			banner_url: '',
			has_custom_banner: false,
			connections: [],
			ui: { color: '#5288F1', avatar_border_radius: '50%' },
		} );

		expect( document.querySelector( '.ip-location' )?.getAttribute( 'aria-label' ) )
			.toBe( 'integratedprofiles-location-label' );
		expect( document.querySelector( '.ip-location__text' )?.textContent ).toBe( 'Seoul' );
		expect( document.querySelector( '.ip-facts .ip-location' ) ).not.toBeNull();
		expect( document.querySelector( '.ip-facts + .ip-about-block' ) ).not.toBeNull();

		apply_payload_to_dom( {
			user_id: 1,
			user_name: 'User1',
			real_name: '',
			edit_count: 1,
			registration: null,
			groups: [],
			fields: {
				'ip-about': 'New bio',
				'ip-location': '',
				'ip-banner': 'accent',
			},
			links: {},
			avatar_url: '/x.svg',
			has_custom_avatar: false,
			banner_url: '',
			has_custom_banner: false,
			connections: [],
			ui: { color: '#5288F1', avatar_border_radius: '50%' },
		} );

		expect( document.querySelector( '.ip-location' ) ).toBeNull();
		expect( document.querySelector( '.ip-facts' ) ).toBeNull();
	} );

	it( 'renders website in the facts row, not as a social chip', () => {
		apply_payload_to_dom( {
			user_id: 1,
			user_name: 'User1',
			real_name: '',
			edit_count: 1,
			registration: null,
			groups: [],
			fields: {
				'ip-about': 'New bio',
				'ip-location': 'Seoul',
				'ip-banner': 'accent',
			},
			featured_article: {
				title: 'Main Page',
				display_title: 'Main Page',
				url: '/wiki/Main_Page',
			},
			links: {
				website: {
					label: 'example.com',
					url: 'https://example.com',
					kind: 'website',
				},
				github: {
					label: 'user1',
					url: 'https://github.com/user1',
					kind: 'github',
				},
			},
			avatar_url: '/x.svg',
			has_custom_avatar: false,
			banner_url: '',
			has_custom_banner: false,
			connections: [],
			ui: { color: '#5288F1', avatar_border_radius: '50%' },
		} );

		expect( document.querySelector( '.ip-facts .ip-featured' ) ).not.toBeNull();
		expect( document.querySelector( '.ip-facts .ip-location' ) ).not.toBeNull();
		expect( document.querySelector( '.ip-website' )?.getAttribute( 'aria-label' ) )
			.toBe( 'integratedprofiles-field-website' );
		expect( document.querySelector( '.ip-website__text' )?.textContent ).toBe( 'example.com' );
		expect( document.querySelector( '.ip-website__link' )?.getAttribute( 'href' ) )
			.toBe( 'https://example.com' );
		expect( document.querySelector( '.ip-links__item--website' ) ).toBeNull();
		expect( document.querySelector( '.ip-links__item--github' ) ).not.toBeNull();

		apply_payload_to_dom( {
			user_id: 1,
			user_name: 'User1',
			real_name: '',
			edit_count: 1,
			registration: null,
			groups: [],
			fields: {
				'ip-about': 'New bio',
				'ip-location': '',
				'ip-banner': 'accent',
			},
			featured_article: null,
			links: {
				github: {
					label: 'user1',
					url: 'https://github.com/user1',
					kind: 'github',
				},
			},
			avatar_url: '/x.svg',
			has_custom_avatar: false,
			banner_url: '',
			has_custom_banner: false,
			connections: [],
			ui: { color: '#5288F1', avatar_border_radius: '50%' },
		} );

		expect( document.querySelector( '.ip-website' ) ).toBeNull();
		expect( document.querySelector( '.ip-facts' ) ).toBeNull();
		expect( document.querySelector( '.ip-links__item--github' ) ).not.toBeNull();
	} );

	it( 'removes about and links when cleared', () => {
		apply_payload_to_dom( {
			user_id: 1,
			user_name: 'User1',
			real_name: '',
			edit_count: 1,
			registration: null,
			groups: [],
			fields: {
				'ip-about': '',
				'ip-website': '',
				'ip-twitter': '',
				'ip-github': '',
				'ip-mediawiki': '',
				'ip-miraheze': '',
				'ip-fandom': '',
				'ip-banner': 'accent',
			},
			links: {},
			avatar_url: '/x.svg',
			has_custom_avatar: false,
			connections: [],
			ui: { color: '#5288F1', avatar_border_radius: '50%' },
		} );

		expect( document.querySelector( '.ip-about' ) ).toBeNull();
		expect( document.querySelector( '.ip-about-block' ) ).toBeNull();
		expect( document.querySelector( '.ip-links' ) ).toBeNull();
	} );

	it( 'writes saved fields into wgIntegratedProfiles for the next editor mount', () => {
		const live_config = {
			can_edit: true,
			fields: {
				'ip-about': 'Old bio',
				'ip-location': '',
				'ip-featured-article': '',
				'ip-website': 'https://old.example',
				'ip-twitter': '',
				'ip-github': '',
				'ip-mediawiki': '',
				'ip-miraheze': '',
				'ip-fandom': '',
				'ip-banner': 'accent',
				'ip-hide-connections': '0',
				'ip-visibility': 'public',
			},
			links: {},
			avatar_url: '/old.svg',
			has_custom_avatar: false,
			banner_url: '',
			has_custom_banner: false,
		};
		stub_mw( vi.fn(), live_config );

		apply_payload_to_dom( {
			user_id: 1,
			user_name: 'User1',
			real_name: '',
			edit_count: 1,
			registration: null,
			groups: [],
			fields: {
				'ip-about': 'Saved bio',
				'ip-location': 'Seoul',
				'ip-featured-article': 'Main Page',
				'ip-website': 'https://new.example',
				'ip-twitter': 'user1',
				'ip-github': '',
				'ip-mediawiki': '',
				'ip-miraheze': '',
				'ip-fandom': '',
				'ip-banner': 'ocean',
				'ip-hide-connections': '1',
				'ip-visibility': 'users',
			},
			links: {},
			wiki_profiles: [],
			avatar_url: '/new.svg',
			has_custom_avatar: true,
			banner_url: '',
			has_custom_banner: false,
			connections: [],
			ui: { color: '#5288F1', avatar_border_radius: '50%' },
		} );

		expect( live_config.fields[ 'ip-about' ] ).toBe( 'Saved bio' );
		expect( live_config.fields[ 'ip-location' ] ).toBe( 'Seoul' );
		expect( live_config.fields[ 'ip-featured-article' ] ).toBe( 'Main Page' );
		expect( live_config.fields[ 'ip-website' ] ).toBe( 'https://new.example' );
		expect( live_config.fields[ 'ip-twitter' ] ).toBe( 'user1' );
		expect( live_config.fields[ 'ip-banner' ] ).toBe( 'ocean' );
		expect( live_config.fields[ 'ip-hide-connections' ] ).toBe( '1' );
		expect( live_config.fields[ 'ip-visibility' ] ).toBe( 'users' );
		expect( live_config.avatar_url ).toBe( '/new.svg' );
		expect( live_config.has_custom_avatar ).toBe( true );
	} );
} );

describe( 'save_profile_fields', () => {
	it( 'posts CSRF fields JSON and returns profile', async () => {
		const postWithToken = vi.fn().mockReturnValue( jq_resolved( {
			setintegratedprofile: {
				profile: {
					user_id: 1,
					user_name: 'User1',
					fields: {
						'ip-about': 'Hi',
						'ip-website': '',
						'ip-twitter': '',
						'ip-github': '',
						'ip-mediawiki': '',
						'ip-miraheze': '',
						'ip-fandom': '',
					},
					links: {},
				},
			},
		} ) );

		stub_mw( postWithToken );

		const { save_profile_fields } = await import( '../../src/utils/api.ts' );
		const profile = await save_profile_fields( {
			'ip-about': 'Hi',
			'ip-website': '',
			'ip-twitter': '',
			'ip-github': '',
			'ip-mediawiki': '',
			'ip-miraheze': '',
			'ip-fandom': '',
		}, 'User1' );

		expect( postWithToken ).toHaveBeenCalledWith(
			'csrf',
			expect.objectContaining( {
				action: 'setintegratedprofile',
				username: 'User1',
				fields: JSON.stringify( {
					'ip-about': 'Hi',
					'ip-website': '',
					'ip-twitter': '',
					'ip-github': '',
					'ip-mediawiki': '',
					'ip-miraheze': '',
					'ip-fandom': '',
				} ),
			} ),
			undefined
		);
		expect( profile.fields[ 'ip-about' ] ).toBe( 'Hi' );
	} );
} );

describe( 'upload_avatar / delete_avatar', () => {
	it( 'posts multipart upload and delete actions', async () => {
		const file = new File( [ 'x' ], 'a.png', { type: 'image/png' } );
		const postWithToken = vi.fn()
			.mockReturnValueOnce( jq_resolved( {
				integratedprofileuploadavatar: {
					profile: {
						user_id: 1,
						user_name: 'User1',
						avatar_url: '/ipavatars/avatar_1.png',
						has_custom_avatar: true,
						fields: {},
						links: {},
					},
				},
			} ) )
			.mockReturnValueOnce( jq_resolved( {
				integratedprofiledeleteavatar: {
					profile: {
						user_id: 1,
						user_name: 'User1',
						avatar_url: '/default.svg',
						has_custom_avatar: false,
						fields: {},
						links: {},
					},
				},
			} ) );

		stub_mw( postWithToken );

		vi.resetModules();
		const { upload_avatar, delete_avatar } = await import( '../../src/utils/api.ts' );

		const uploaded = await upload_avatar( file, 'User1' );
		expect( postWithToken ).toHaveBeenCalledWith(
			'csrf',
			expect.objectContaining( {
				action: 'integratedprofileuploadavatar',
				username: 'User1',
				file,
			} ),
			// Without this mw.Api serialises the File and the browser throws.
			expect.objectContaining( { contentType: 'multipart/form-data' } )
		);
		expect( uploaded.has_custom_avatar ).toBe( true );

		const deleted = await delete_avatar( 'User1' );
		expect( postWithToken ).toHaveBeenCalledWith(
			'csrf',
			expect.objectContaining( {
				action: 'integratedprofiledeleteavatar',
				username: 'User1',
			} ),
			undefined
		);
		expect( deleted.has_custom_avatar ).toBe( false );
	} );

	it( 'reports the localized API message rather than the error code', async () => {
		const file = new File( [ 'x' ], 'a.png', { type: 'image/png' } );
		stub_mw( vi.fn().mockReturnValue( jq_rejected( 'avatarsize', {
			error: {
				code: 'avatarsize',
				info: 'Avatar file is missing or too large.',
			},
		} ) ) );

		vi.resetModules();
		const { upload_avatar } = await import( '../../src/utils/api.ts' );

		await expect( upload_avatar( file, 'User1' ) ).rejects.toThrow(
			'Avatar file is missing or too large.'
		);
	} );

	it( 'explains HTTP 413 instead of showing "http"', async () => {
		const file = new File( [ 'x' ], 'a.png', { type: 'image/png' } );
		stub_mw( vi.fn().mockReturnValue( jq_rejected( 'http', {
			xhr: { status: 413 },
			textStatus: 'error',
			exception: 'Request Entity Too Large',
		} ) ) );

		vi.resetModules();
		const { upload_avatar } = await import( '../../src/utils/api.ts' );

		await expect( upload_avatar( file, 'User1' ) ).rejects.toThrow(
			'integratedprofiles-error-upload-too-large'
		);
	} );
} );

describe( 'upload_banner / delete_banner', () => {
	it( 'posts multipart upload and delete actions', async () => {
		const file = new File( [ 'x' ], 'b.png', { type: 'image/png' } );
		const postWithToken = vi.fn()
			.mockReturnValueOnce( jq_resolved( {
				integratedprofileuploadbanner: {
					profile: {
						user_id: 1,
						user_name: 'User1',
						banner_url: '/ipbanners/banner_1.png',
						has_custom_banner: true,
						fields: { 'ip-banner': 'custom' },
						links: {},
					},
				},
			} ) )
			.mockReturnValueOnce( jq_resolved( {
				integratedprofiledeletebanner: {
					profile: {
						user_id: 1,
						user_name: 'User1',
						banner_url: '',
						has_custom_banner: false,
						fields: { 'ip-banner': 'accent' },
						links: {},
					},
				},
			} ) );

		stub_mw( postWithToken );

		vi.resetModules();
		const { upload_banner, delete_banner } = await import( '../../src/utils/api.ts' );

		const uploaded = await upload_banner( file, 'User1' );
		expect( postWithToken ).toHaveBeenCalledWith(
			'csrf',
			expect.objectContaining( {
				action: 'integratedprofileuploadbanner',
				username: 'User1',
				file,
			} ),
			expect.objectContaining( { contentType: 'multipart/form-data' } )
		);
		expect( uploaded.has_custom_banner ).toBe( true );

		const deleted = await delete_banner( 'User1' );
		expect( postWithToken ).toHaveBeenCalledWith(
			'csrf',
			expect.objectContaining( {
				action: 'integratedprofiledeletebanner',
				username: 'User1',
			} ),
			undefined
		);
		expect( deleted.has_custom_banner ).toBe( false );
	} );
} );
