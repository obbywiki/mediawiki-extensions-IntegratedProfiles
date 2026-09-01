<template>
	<section class="ip-editor" aria-labelledby="ip-editor-title">
		<header class="ip-editor__header">
			<h2 id="ip-editor-title" class="ip-editor__title">
				{{ msg( 'integratedprofiles-edit-title' ) }}
			</h2>
			<div class="ip-editor__actions ip-editor__actions--header">
				<button
					type="button"
					class="cdx-button"
					:disabled="busy"
					@click="on_cancel"
				>
					{{ msg( 'integratedprofiles-cancel' ) }}
				</button>
				<button
					type="button"
					class="cdx-button cdx-button--action-progressive cdx-button--weight-primary"
					:disabled="busy"
					@click="on_save"
				>
					{{ msg( 'integratedprofiles-save' ) }}
				</button>
			</div>
		</header>

		<div class="ip-editor__body">
			<fieldset
				class="ip-editor__section"
				aria-labelledby="ip-section-appearance"
			>
				<h3 id="ip-section-appearance" class="ip-editor__section-title">
					<span
						class="ip-editor__glyph ip-editor__glyph--image"
						aria-hidden="true"
					/>
					{{ msg( 'integratedprofiles-editor-section-appearance' ) }}
				</h3>

				<div class="ip-editor__appearance">
					<div class="ip-editor__appearance-avatar">
						<span id="ip-avatar-label" class="ip-editor__sublabel">
							{{ msg( 'integratedprofiles-avatar-edit' ) }}
						</span>
						<button
							type="button"
							class="ip-editor__avatar-preview"
							:disabled="busy"
							aria-labelledby="ip-avatar-label"
							@click="on_open_avatar_modal"
						>
							<img
								class="ip-editor__avatar-preview-image"
								:src="avatar_preview_url"
								:alt="msg( 'integratedprofiles-avatar-alt' )"
								width="96"
								height="96"
							>
							<span class="ip-editor__avatar-overlay" aria-hidden="true">
								<svg
									class="ip-editor__avatar-overlay-icon"
									viewBox="0 0 20 20"
									focusable="false"
								>
									<path
										fill="currentColor"
										d="M2.6 14.3v3.1h3.1l8.9-8.9-3.1-3.1z M12.2 4.7l2.1-2.1a1.2 1.2 0 0 1 1.7 0l1.4 1.4 a1.2 1.2 0 0 1 0 1.7l-2.1 2.1z"
									/>
								</svg>
							</span>
						</button>
					</div>

					<div class="ip-editor__appearance-banners">
						<div class="ip-editor__banner-heading">
							<span id="ip-banner-presets-label" class="ip-editor__sublabel">
								{{ msg( 'integratedprofiles-banner-label' ) }}
							</span>
							<p class="ip-editor__help">
								{{ msg( 'integratedprofiles-banner-help' ) }}
							</p>
						</div>
						<ul
							class="ip-editor__banner-presets"
							role="listbox"
							aria-labelledby="ip-banner-presets-label"
						>
							<li
								v-for="preset_id in banner_presets"
								:key="preset_id"
								role="option"
								:aria-selected="selected_banner === preset_id"
							>
								<button
									type="button"
									class="ip-editor__banner-swatch"
									:class="banner_swatch_class( preset_id )"
									:aria-label="preset_label( preset_id )"
									:title="preset_label( preset_id )"
									:disabled="busy"
									@click="on_select_banner( preset_id )"
								/>
							</li>
							<li
								v-if="has_custom_banner"
								role="option"
								:aria-selected="selected_banner === 'custom'"
							>
								<button
									type="button"
									class="ip-editor__banner-swatch ip-editor__banner-swatch--custom"
									:class="{ 'ip-editor__banner-swatch--selected': selected_banner === 'custom' }"
									:style="custom_swatch_style"
									:aria-label="msg( 'integratedprofiles-banner-preset-custom' )"
									:title="msg( 'integratedprofiles-banner-preset-custom' )"
									:disabled="busy"
									@click="on_select_banner( 'custom' )"
								/>
							</li>
							<li role="presentation">
								<button
									type="button"
									class="ip-editor__banner-swatch ip-editor__banner-swatch--add"
									:aria-label="msg( 'integratedprofiles-banner-edit' )"
									:title="msg( 'integratedprofiles-banner-edit' )"
									:disabled="busy"
									@click="on_open_banner_modal"
								>
									<span
										class="ip-editor__banner-add-icon"
										aria-hidden="true"
									>＋</span>
								</button>
							</li>
						</ul>
					</div>
				</div>
			</fieldset>

			<fieldset
				class="ip-editor__section"
				aria-labelledby="ip-section-profile"
			>
				<h3 id="ip-section-profile" class="ip-editor__section-title">
					<span
						class="ip-editor__glyph ip-editor__glyph--person"
						aria-hidden="true"
					/>
					{{ msg( 'integratedprofiles-editor-section-profile' ) }}
				</h3>

				<div class="ip-editor__fields">
					<div class="ip-editor__field">
						<label for="ip-field-about">
							{{ msg( 'integratedprofiles-field-about' ) }}
						</label>
						<textarea
							id="ip-field-about"
							v-model="draft['ip-about']"
							class="cdx-text-area__textarea"
							rows="2"
							:maxlength="about_max"
							:disabled="busy"
							:placeholder="msg( 'integratedprofiles-field-about-placeholder' )"
						/>
						<div class="ip-editor__field-meta">
							<p class="ip-editor__help">
								{{ msg( 'integratedprofiles-field-about-help' ) }}
							</p>
							<span
								class="ip-editor__char-count"
								:class="{ 'ip-editor__char-count--warn': about_remaining <= 10 }"
								aria-live="polite"
							>
								{{ about_length }}/{{ about_max }}
							</span>
						</div>
					</div>

					<div class="ip-editor__field">
						<label for="ip-field-featured-article">
							{{ msg( 'integratedprofiles-field-featured-article' ) }}
						</label>
						<input
							id="ip-field-featured-article"
							v-model="draft['ip-featured-article']"
							class="cdx-text-input__input"
							type="text"
							:maxlength="link_max"
							:disabled="busy"
							:placeholder="msg( 'integratedprofiles-field-featured-article-placeholder' )"
						>
						<p class="ip-editor__help">
							{{ msg( 'integratedprofiles-field-featured-article-help' ) }}
						</p>
					</div>

					<div class="ip-editor__field">
						<span
							id="ip-field-visibility-label"
							class="ip-editor__label"
						>
							{{ msg( 'integratedprofiles-field-visibility' ) }}
						</span>
						<div
							class="ip-editor__visibility"
							role="radiogroup"
							aria-labelledby="ip-field-visibility-label"
						>
							<label
								v-for="option in visibility_options"
								:key="option.value"
								class="ip-editor__visibility-option"
								:class="{ 'ip-editor__visibility-option--selected': draft[ 'ip-visibility' ] === option.value }"
							>
								<input
									v-model="draft['ip-visibility']"
									class="ip-editor__visibility-input"
									type="radio"
									name="ip-visibility"
									:value="option.value"
									:disabled="busy"
								>
								<span class="ip-editor__visibility-copy">
									<span class="ip-editor__visibility-title">
										{{ option.label }}
									</span>
									<span class="ip-editor__visibility-desc">
										{{ option.help }}
									</span>
								</span>
							</label>
						</div>
						<p class="ip-editor__help">
							{{ msg( 'integratedprofiles-field-visibility-help' ) }}
						</p>
					</div>
				</div>
			</fieldset>

			<fieldset
				class="ip-editor__section"
				aria-labelledby="ip-section-links"
			>
				<h3 id="ip-section-links" class="ip-editor__section-title">
					<span
						class="ip-editor__glyph ip-editor__glyph--link"
						aria-hidden="true"
					/>
					{{ msg( 'integratedprofiles-editor-section-links' ) }}
				</h3>

				<div class="ip-editor__fields">
					<div class="ip-editor__field">
						<label for="ip-field-website">
							{{ msg( 'integratedprofiles-field-website' ) }}
						</label>
						<input
							id="ip-field-website"
							v-model="draft['ip-website']"
							class="cdx-text-input__input"
							type="url"
							:maxlength="link_max"
							:disabled="busy"
							:placeholder="msg( 'integratedprofiles-field-website-placeholder' )"
						>
						<p class="ip-editor__help">
							{{ msg( 'integratedprofiles-field-website-help' ) }}
						</p>
					</div>

					<details
						class="ip-editor__social-bundle"
						:open="social_links_open"
						@toggle="on_social_toggle"
					>
						<summary class="ip-editor__social-bundle-summary">
							<span class="ip-editor__bundle-label">
								{{ msg( 'integratedprofiles-editor-social-links' ) }}
							</span>
						</summary>
						<div class="ip-editor__social-bundle-fields">
							<div class="ip-editor__social-bundle-row">
								<label
									class="ip-editor__social-bundle-label"
									for="ip-field-twitter"
								>
									<span
										class="ip-brand-icon ip-brand-icon--twitter"
										aria-hidden="true"
									/>
									{{ msg( 'integratedprofiles-field-twitter' ) }}
								</label>
								<input
									id="ip-field-twitter"
									v-model="draft['ip-twitter']"
									class="cdx-text-input__input"
									type="text"
									maxlength="64"
									:disabled="busy"
									:placeholder="
										msg( 'integratedprofiles-field-twitter-placeholder' )
									"
								>
							</div>
							<div class="ip-editor__social-bundle-row">
								<label
									class="ip-editor__social-bundle-label"
									for="ip-field-github"
								>
									<span
										class="ip-brand-icon ip-brand-icon--github"
										aria-hidden="true"
									/>
									{{ msg( 'integratedprofiles-field-github' ) }}
								</label>
								<input
									id="ip-field-github"
									v-model="draft['ip-github']"
									class="cdx-text-input__input"
									type="text"
									maxlength="64"
									:disabled="busy"
									:placeholder="msg( 'integratedprofiles-field-github-placeholder' )"
								>
							</div>
						</div>
					</details>

					<details
						class="ip-editor__social-bundle"
						:open="wiki_profiles_open"
						@toggle="on_wiki_profiles_toggle"
					>
						<summary class="ip-editor__social-bundle-summary">
							<span class="ip-editor__bundle-label">
								{{ msg( 'integratedprofiles-editor-wiki-profiles' ) }}
							</span>
						</summary>
						<div class="ip-editor__social-bundle-fields">
							<div class="ip-editor__social-bundle-row">
								<label
									class="ip-editor__social-bundle-label"
									for="ip-field-mediawiki"
								>
									<span
										class="ip-brand-icon ip-brand-icon--mediawiki"
										aria-hidden="true"
									/>
									{{ msg( 'integratedprofiles-field-mediawiki' ) }}
								</label>
								<input
									id="ip-field-mediawiki"
									v-model="draft['ip-mediawiki']"
									class="cdx-text-input__input"
									type="text"
									:maxlength="link_max"
									:disabled="busy"
									:placeholder="msg( 'integratedprofiles-field-mediawiki-placeholder' )"
								>
							</div>
							<div class="ip-editor__social-bundle-row">
								<label
									class="ip-editor__social-bundle-label"
									for="ip-field-miraheze"
								>
									<span
										class="ip-brand-icon ip-brand-icon--miraheze"
										aria-hidden="true"
									/>
									{{ msg( 'integratedprofiles-field-miraheze' ) }}
								</label>
								<input
									id="ip-field-miraheze"
									v-model="draft['ip-miraheze']"
									class="cdx-text-input__input"
									type="text"
									:maxlength="link_max"
									:disabled="busy"
									:placeholder="msg( 'integratedprofiles-field-miraheze-placeholder' )"
								>
							</div>
							<div class="ip-editor__social-bundle-row">
								<label
									class="ip-editor__social-bundle-label"
									for="ip-field-fandom"
								>
									<span
										class="ip-brand-icon ip-brand-icon--fandom"
										aria-hidden="true"
									/>
									{{ msg( 'integratedprofiles-field-fandom' ) }}
								</label>
								<input
									id="ip-field-fandom"
									v-model="draft['ip-fandom']"
									class="cdx-text-input__input"
									type="text"
									:maxlength="link_max"
									:disabled="busy"
									:placeholder="msg( 'integratedprofiles-field-fandom-placeholder' )"
								>
							</div>
						</div>
					</details>
				</div>
			</fieldset>

			<fieldset
				v-if="show_manage_connections || show_connection_privacy"
				class="ip-editor__section"
				aria-labelledby="ip-section-connections"
			>
				<h3 id="ip-section-connections" class="ip-editor__section-title">
					<span
						class="ip-editor__glyph ip-editor__glyph--accounts"
						aria-hidden="true"
					/>
					{{ msg( 'integratedprofiles-editor-section-connections' ) }}
				</h3>

				<div class="ip-editor__fields">
					<template v-if="show_manage_connections && preferences_url">
						<ul class="ip-editor__connections">
							<li
								v-for="provider in connection_rows"
								:key="provider.id"
								class="ip-editor__connection"
							>
								<span class="ip-editor__connection-identity">
									<span
										class="ip-editor__connection-chip"
										:class="'ip-editor__connection-chip--' + provider.id"
										aria-hidden="true"
									>
										<span
											class="ip-brand-icon"
											:class="'ip-brand-icon--' + provider.id"
										/>
									</span>
									<span class="ip-editor__connection-name">
										{{ provider.label }}
									</span>
								</span>
								<p class="ip-editor__connection-status">
									<span class="ip-editor__connection-state">
										{{ provider.status }}
									</span>
									<a
										class="ip-editor__manage-connections"
										:href="preferences_url"
									>
										{{ provider.action_label }}
									</a>
								</p>
							</li>
						</ul>
						<p class="ip-editor__help ip-editor__help--connections">
							{{ msg( 'integratedprofiles-connection-external-help' ) }}
						</p>
					</template>

					<div
						v-if="show_connection_privacy"
						class="ip-editor__field ip-editor__field--toggle"
					>
						<label class="ip-editor__toggle" for="ip-field-hide-connections">
							<span class="ip-editor__toggle-copy">
								<span class="ip-editor__toggle-label">
									{{ msg( 'integratedprofiles-field-hide-connections' ) }}
								</span>
								<span class="ip-editor__help">
									{{ msg( 'integratedprofiles-field-hide-connections-help' ) }}
								</span>
							</span>
							<span class="ip-editor__toggle-control">
								<input
									id="ip-field-hide-connections"
									v-model="hide_connections"
									class="ip-editor__toggle-input"
									type="checkbox"
									role="switch"
									:disabled="busy"
								>
								<span class="ip-editor__toggle-track" aria-hidden="true">
									<span class="ip-editor__toggle-thumb" />
								</span>
							</span>
						</label>
					</div>
				</div>
			</fieldset>
		</div>

		<p
			v-if="error_message"
			class="ip-editor__message ip-editor__message--error"
			role="alert"
		>
			{{ error_message }}
		</p>
		<p
			v-if="success_message"
			class="ip-editor__message ip-editor__message--success"
			role="status"
		>
			{{ success_message }}
		</p>

		<div class="ip-editor__actions">
			<button
				type="button"
				class="cdx-button"
				:disabled="busy"
				@click="on_cancel"
			>
				{{ msg( 'integratedprofiles-cancel' ) }}
			</button>
			<button
				type="button"
				class="cdx-button cdx-button--action-progressive cdx-button--weight-primary"
				:disabled="busy"
				@click="on_save"
			>
				{{ msg( 'integratedprofiles-save' ) }}
			</button>
		</div>
	</section>
</template>

<script setup lang="ts">
import { computed, onMounted, onUnmounted, reactive, ref } from 'vue';
import type {
	IntegratedProfilesConfig,
	ProfileConnection,
	ProfileFieldsMap,
	ProfilePayload
} from '../types/mw';
import {
	apply_payload_to_dom,
	msg,
	save_profile_fields
} from '../utils/api';

const DEFAULT_BANNER_PRESETS = [
	'accent',
	'ocean',
	'sunset',
	'forest',
	'midnight',
	'ember',
	'sand',
	'aurora'
];

const DEFAULT_CONNECTION_PROVIDERS = [ 'discord', 'roblox' ];

const props = defineProps<{
	config: IntegratedProfilesConfig;
}>();

const emit = defineEmits( [ 'close' ] );

function field_or_empty( value: string | undefined ): string {
	return value || '';
}

function flag_or_off( value: string | undefined ): string {
	return value === '1' || value === 'true' ? '1' : '0';
}

function normalize_visibility( value: string | undefined ): string {
	if ( value === 'users' || value === 'private' || value === 'public' ) {
		return value;
	}
	return 'public';
}

const draft = reactive<ProfileFieldsMap>( {
	'ip-about': field_or_empty( props.config.fields && props.config.fields[ 'ip-about' ] ),
	'ip-featured-article': field_or_empty( props.config.fields && props.config.fields[ 'ip-featured-article' ] ),
	'ip-website': field_or_empty( props.config.fields && props.config.fields[ 'ip-website' ] ),
	'ip-twitter': field_or_empty( props.config.fields && props.config.fields[ 'ip-twitter' ] ),
	'ip-github': field_or_empty( props.config.fields && props.config.fields[ 'ip-github' ] ),
	'ip-mediawiki': field_or_empty( props.config.fields && props.config.fields[ 'ip-mediawiki' ] ),
	'ip-miraheze': field_or_empty( props.config.fields && props.config.fields[ 'ip-miraheze' ] ),
	'ip-fandom': field_or_empty( props.config.fields && props.config.fields[ 'ip-fandom' ] ),
	'ip-banner': field_or_empty( props.config.fields && props.config.fields[ 'ip-banner' ] ) || 'accent',
	'ip-hide-connections': flag_or_off( props.config.fields && props.config.fields[ 'ip-hide-connections' ] ),
	'ip-visibility': normalize_visibility( props.config.fields && props.config.fields[ 'ip-visibility' ] )
} );

const busy = ref( false );
const error_message = ref( '' );
const success_message = ref( '' );
const selected_banner = ref( draft[ 'ip-banner' ] || 'accent' );
const has_custom_banner = ref( !!props.config.has_custom_banner );
const banner_url = ref( props.config.banner_url || '' );
const avatar_preview_url = ref( props.config.avatar_url || '' );
const social_links_open = ref( Boolean( draft[ 'ip-twitter' ] || draft[ 'ip-github' ] ) );
const wiki_profiles_open = ref( Boolean( draft[ 'ip-mediawiki' ] || draft[ 'ip-miraheze' ] || draft[ 'ip-fandom' ] ) );

const about_max = computed( () => ( props.config.limits && props.config.limits.about ) || 80 );
const link_max = computed( () => ( props.config.limits && props.config.limits.link ) || 255 );
const about_length = computed( () => ( draft[ 'ip-about' ] || '' ).length );
const about_remaining = computed( () => about_max.value - about_length.value );
const banner_presets = computed( () => {
	if ( props.config.banner_presets && props.config.banner_presets.length ) {
		return props.config.banner_presets;
	}

	return DEFAULT_BANNER_PRESETS;
} );
const custom_swatch_style = computed( () => {
	if ( !banner_url.value ) { return {}; }

	return { backgroundImage: 'url(' + banner_url.value + ')' };
} );
const show_manage_connections = computed(
	() => Boolean( props.config.show_manage_connections )
);
const show_connection_privacy = computed(
	() => Boolean( props.config.show_connection_privacy )
);
const preferences_url = computed(
	() => ( props.config.preferences_url || '' ).trim()
);
const hide_connections = computed( {
	get: () => draft[ 'ip-hide-connections' ] === '1',
	set: ( value: boolean ) => {
		draft[ 'ip-hide-connections' ] = value ? '1' : '0';
	}
} );

const visibility_options = computed( () => [
	{
		value: 'public',
		label: msg( 'integratedprofiles-visibility-public' ),
		help: msg( 'integratedprofiles-visibility-public-help' )
	},
	{
		value: 'users',
		label: msg( 'integratedprofiles-visibility-users' ),
		help: msg( 'integratedprofiles-visibility-users-help' )
	},
	{
		value: 'private',
		label: msg( 'integratedprofiles-visibility-private' ),
		help: msg( 'integratedprofiles-visibility-private-help' )
	}
] );
const connection_rows = computed( () => {
	const configured = props.config.connection_providers;
	const providers = ( configured && configured.length ) ?
		configured :
		DEFAULT_CONNECTION_PROVIDERS;
	const by_provider: Record<string, ProfileConnection> = {};
	const linked = Array.isArray( props.config.connections ) ? props.config.connections : [];
	for ( const row of linked ) {
		if ( !row || typeof row !== 'object' ) { continue; }

		const provider = String( row.provider || '' ).toLowerCase();
		if ( provider ) {
			by_provider[ provider ] = row;
		}
	}

	return providers.map( ( provider_id ) => {
		const row = by_provider[ provider_id ];
		const label = msg( 'integratedprofiles-connection-' + provider_id );
		const display = row ? String( row.remote_username || row.remote_user || '' ).trim() : '';
		const linked_now = Boolean( row );

		let status = msg( 'integratedprofiles-connection-not-linked' );

		if ( linked_now ) {
			status = display ? msg( 'integratedprofiles-connection-linked', display ) : msg( 'integratedprofiles-connection-verified' );
		}

		return { id: provider_id, label, status, action_label: linked_now ? msg( 'integratedprofiles-connection-manage' ) : msg( 'integratedprofiles-connection-connect' ) };
	} );
} );

function banner_swatch_class( preset_id: string ): ( string | Record<string, boolean> )[] {
	return [ 'ip-editor__banner-swatch--' + preset_id, { 'ip-editor__banner-swatch--selected': selected_banner.value === preset_id } ];
}

function preset_label( preset_id: string ): string {
	return msg( 'integratedprofiles-banner-preset-' + preset_id );
}

function on_social_toggle( event: Event ): void {
	const details = event.target as HTMLDetailsElement | null;

	if ( details ) {
		social_links_open.value = details.open;
	}
}

function on_wiki_profiles_toggle( event: Event ): void {
	const details = event.target as HTMLDetailsElement | null;

	if ( details ) {
		wiki_profiles_open.value = details.open;
	}
}

function sync_avatar_from_profile( profile: { avatar_url?: string; has_custom_avatar?: boolean; } ): void {
	if ( profile.avatar_url ) {
		avatar_preview_url.value = profile.avatar_url;
	}

	const live_config = mw.config.get( 'wgIntegratedProfiles' ) as IntegratedProfilesConfig | null;

	if ( live_config && profile.avatar_url ) {
		live_config.avatar_url = profile.avatar_url;
		live_config.has_custom_avatar = !!profile.has_custom_avatar;
	}
}

function sync_banner_from_profile( profile: { fields?: { 'ip-banner'?: string }; banner_url?: string; has_custom_banner?: boolean; } ): void {
	const mode = ( profile.fields && profile.fields[ 'ip-banner' ] ) || 'accent';
	selected_banner.value = mode;
	draft[ 'ip-banner' ] = mode;
	has_custom_banner.value = !!profile.has_custom_banner;
	banner_url.value = profile.banner_url || '';

	const live_config = mw.config.get( 'wgIntegratedProfiles' ) as IntegratedProfilesConfig | null;

	if ( live_config ) {
		live_config.has_custom_banner = has_custom_banner.value;
		live_config.banner_url = banner_url.value;

		if ( live_config.fields ) {
			live_config.fields[ 'ip-banner' ] = mode;
		}
	}
}

async function on_select_banner( preset_id: string ): Promise<void> {
	if ( busy.value || selected_banner.value === preset_id ) { return; }
	if ( preset_id === 'custom' && !has_custom_banner.value ) { return; }

	busy.value = true;
	error_message.value = '';
	success_message.value = '';
	try {
		const profile = await save_profile_fields( { 'ip-banner': preset_id }, props.config.user_name );

		apply_payload_to_dom( profile );
		sync_banner_from_profile( profile );
	} catch ( err ) {
		error_message.value = err instanceof Error ? err.message : msg( 'integratedprofiles-save-error' );
	} finally {
		busy.value = false;
	}
}

function on_banner_updated( event: Event ): void {
	const custom = event as CustomEvent<ProfilePayload>;

	if ( custom.detail ) {
		sync_banner_from_profile( custom.detail );
	}
}

function on_avatar_updated( event: Event ): void {
	const custom = event as CustomEvent<ProfilePayload>;

	if ( custom.detail ) {
		sync_avatar_from_profile( custom.detail );
	}
}

async function on_save(): Promise<void> {
	busy.value = true;
	error_message.value = '';
	success_message.value = '';

	try {
		const profile = await save_profile_fields(
			{ ...draft },
			props.config.user_name
		);

		apply_payload_to_dom( profile );
		sync_banner_from_profile( profile );
		success_message.value = msg( 'integratedprofiles-save-success' );
	} catch ( err ) {
		error_message.value = err instanceof Error ?
			err.message :
			msg( 'integratedprofiles-save-error' );
	} finally {
		busy.value = false;
	}
}

function on_open_avatar_modal( event: MouseEvent ): void {
	const trigger = event.currentTarget as HTMLElement | null;

	if ( mw.IntegratedProfiles && typeof mw.IntegratedProfiles.open_avatar_modal === 'function' ) {
		mw.IntegratedProfiles.open_avatar_modal( trigger );
	}
}

function on_open_banner_modal( event: MouseEvent ): void {
	const trigger = event.currentTarget as HTMLElement | null;

	if ( mw.IntegratedProfiles && typeof mw.IntegratedProfiles.open_banner_modal === 'function' ) {
		mw.IntegratedProfiles.open_banner_modal( trigger );
	}
}

function on_cancel(): void {
	emit( 'close' );
}

onMounted( () => {
	document.addEventListener( 'ip-banner-updated', on_banner_updated );
	document.addEventListener( 'ip-avatar-updated', on_avatar_updated );
} );

onUnmounted( () => {
	document.removeEventListener( 'ip-banner-updated', on_banner_updated );
	document.removeEventListener( 'ip-avatar-updated', on_avatar_updated );
} );
</script>
