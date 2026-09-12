<template>
	<section class="ip-editor" aria-labelledby="ip-editor-title">
		<header class="ip-editor__header">
			<h2 id="ip-editor-title" class="ip-editor__title">
				{{ msg( 'integratedprofiles-edit-title' ) }}
			</h2>
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
						<button
							type="button"
							class="ip-editor__avatar-button"
							:disabled="busy"
							:aria-label="msg( 'integratedprofiles-avatar-edit' )"
							@click="on_open_avatar_modal"
						>
							<span class="ip-editor__avatar-preview">
								<img
									class="ip-editor__avatar-preview-image"
									:src="avatar_preview_url"
									:alt="msg( 'integratedprofiles-avatar-alt' )"
									width="128"
									height="128"
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
							</span>
							<span class="ip-editor__avatar-hint" aria-hidden="true">
								{{ msg( 'integratedprofiles-avatar-change' ) }}
							</span>
						</button>
					</div>

					<div class="ip-editor__appearance-divider" aria-hidden="true" />

					<div class="ip-editor__appearance-banners">
						<ul
							class="ip-editor__banner-presets"
							role="listbox"
							:aria-label="msg( 'integratedprofiles-banner-label' )"
						>
							<li
								v-for="preset_id in banner_presets"
								:key="preset_id"
								role="option"
								:aria-selected="main_preset_selected( preset_id )"
							>
								<button
									type="button"
									class="ip-editor__banner-swatch"
									:class="banner_swatch_class( preset_id )"
									:style="merge_swatch_style( preset_id )"
									:aria-label="preset_label( preset_id )"
									:title="preset_label( preset_id )"
									:disabled="busy"
									@click="on_select_banner( preset_id )"
								/>
							</li>
							<li
								v-if="has_custom_banner"
								role="option"
								:aria-selected="gradient_selected === 'custom'"
							>
								<button
									type="button"
									class="ip-editor__banner-swatch ip-editor__banner-swatch--custom"
									:class="{ 'ip-editor__banner-swatch--selected': gradient_selected === 'custom' }"
									:style="custom_swatch_style"
									:aria-label="msg( 'integratedprofiles-banner-preset-custom' )"
									:title="msg( 'integratedprofiles-banner-preset-custom' )"
									:disabled="busy"
									@click="on_select_banner( 'custom' )"
								/>
							</li>
							<li
								v-if="replace_gradient_presets && selected_wiki_banner"
								role="presentation"
							>
								<button
									type="button"
									class="ip-editor__banner-swatch ip-editor__banner-swatch--add"
									:aria-label="msg( 'integratedprofiles-banner-wiki-clear' )"
									:title="msg( 'integratedprofiles-banner-wiki-clear' )"
									:disabled="busy"
									@click="on_select_wiki_banner( '' )"
								>
									<svg
										class="ip-editor__banner-add-icon"
										xmlns="http://www.w3.org/2000/svg"
										viewBox="0 0 20 20"
										aria-hidden="true"
										focusable="false"
									>
										<path
											fill="currentColor"
											d="M10 1C14.9706 1 19 5.02944 19 10C19 14.9706 14.9706 19 10 19C5.02944 19 1 14.9706 1 10C1 5.02944 5.02944 1 10 1ZM4.39355 5.80566C3.51773 6.97448 3 8.42706 3 10C3 13.866 6.13401 17 10 17C11.5729 17 13.0246 16.4812 14.1934 15.6055L4.39355 5.80566ZM10 3C8.42832 3 6.97692 3.51705 5.80859 4.3916L15.6064 14.1904C16.4811 13.022 17 11.5718 17 10C17 6.13401 13.866 3 10 3Z"
										/>
									</svg>
								</button>
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
									<svg
										class="ip-editor__banner-add-icon"
										viewBox="0 0 20 20"
										aria-hidden="true"
										focusable="false"
									>
										<path
											fill="currentColor"
											d="M19 19H1v-2h18zM10.703 1l5.211 5.117-1.406 1.422L11 4v11H9V4L5.492 7.54 4.086 6.116 9.296 1z"
										/>
									</svg>
								</button>
							</li>
						</ul>
					</div>
				</div>

				<div
					v-if="show_wiki_banners"
					class="ip-editor__banner-wiki"
				>
					<div class="ip-editor__banner-wiki-header">
						<p class="ip-editor__banner-wiki-label">
							{{ wiki_banner_label }}
						</p>
						<p class="ip-editor__banner-wiki-help">
							{{ msg( 'integratedprofiles-banner-wiki-help' ) }}
						</p>
					</div>
					<ul
						class="ip-editor__banner-presets ip-editor__banner-presets--wiki"
						role="listbox"
						:aria-label="wiki_banner_label"
					>
						<li
							v-for="preset_id in wiki_banner_ids"
							:key="preset_id"
							role="option"
							:aria-selected="selected_wiki_banner === preset_id"
						>
							<button
								type="button"
								class="ip-editor__banner-swatch ip-editor__banner-swatch--wiki"
								:class="{ 'ip-editor__banner-swatch--selected': selected_wiki_banner === preset_id }"
								:style="banner_image_style( preset_id )"
								:aria-label="preset_label( preset_id )"
								:title="preset_label( preset_id )"
								:disabled="busy"
								@click="on_select_wiki_banner( preset_id )"
							/>
						</li>
						<li
							v-if="selected_wiki_banner"
							role="presentation"
						>
							<button
								type="button"
								class="ip-editor__banner-swatch ip-editor__banner-swatch--add"
								:aria-label="msg( 'integratedprofiles-banner-wiki-clear' )"
								:title="msg( 'integratedprofiles-banner-wiki-clear' )"
								:disabled="busy"
								@click="on_select_wiki_banner( '' )"
							>
								<svg
									class="ip-editor__banner-add-icon"
									xmlns="http://www.w3.org/2000/svg"
									viewBox="0 0 20 20"
									aria-hidden="true"
									focusable="false"
								>
									<path
										fill="currentColor"
										d="M10 1C14.9706 1 19 5.02944 19 10C19 14.9706 14.9706 19 10 19C5.02944 19 1 14.9706 1 10C1 5.02944 5.02944 1 10 1ZM4.39355 5.80566C3.51773 6.97448 3 8.42706 3 10C3 13.866 6.13401 17 10 17C11.5729 17 13.0246 16.4812 14.1934 15.6055L4.39355 5.80566ZM10 3C8.42832 3 6.97692 3.51705 5.80859 4.3916L15.6064 14.1904C16.4811 13.022 17 11.5718 17 10C17 6.13401 13.866 3 10 3Z"
									/>
								</svg>
							</button>
						</li>
					</ul>
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
						<label for="ip-field-location">
							{{ msg( 'integratedprofiles-field-location' ) }}
						</label>
						<input
							id="ip-field-location"
							v-model="draft['ip-location']"
							class="cdx-text-input__input"
							type="text"
							:maxlength="about_max"
							:disabled="busy"
							:placeholder="msg( 'integratedprofiles-field-location-placeholder' )"
						>
						<div class="ip-editor__field-meta">
							<p class="ip-editor__help">
								{{ msg( 'integratedprofiles-field-location-help' ) }}
							</p>
							<span
								class="ip-editor__char-count"
								:class="{ 'ip-editor__char-count--warn': location_remaining <= 10 }"
								aria-live="polite"
							>
								{{ location_length }}/{{ about_max }}
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

					<div
						v-if="website_enabled"
						class="ip-editor__field"
					>
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

					<div class="ip-editor__field ip-editor__field--toggle">
						<label class="ip-editor__toggle" for="ip-field-show-pronouns">
							<span class="ip-editor__toggle-copy">
								<span class="ip-editor__toggle-label">
									{{ msg( 'integratedprofiles-field-show-pronouns' ) }}
								</span>
								<span class="ip-editor__help">
									{{ msg( 'integratedprofiles-field-show-pronouns-help' ) }}
								</span>
							</span>
							<span class="ip-editor__toggle-control">
								<input
									id="ip-field-show-pronouns"
									v-model="show_pronouns"
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
						<a
							v-if="user_preferences_url"
							class="ip-editor__prefs-link"
							:href="user_preferences_url"
						>
							{{ msg( 'integratedprofiles-field-show-pronouns-manage' ) }}
						</a>
					</div>
				</div>
			</fieldset>

			<fieldset
				v-if="bundle_social_links.length || show_wiki_profiles"
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
					<details
						v-if="bundle_social_links.length"
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
							<div
								v-for="entry in bundle_social_links"
								:key="entry.id"
								class="ip-editor__social-bundle-row"
							>
								<label
									class="ip-editor__social-bundle-label"
									:for="'ip-field-' + entry.id"
								>
									<span
										class="ip-brand-icon"
										:class="'ip-brand-icon--' + entry.id"
										aria-hidden="true"
									/>
									{{ msg( 'integratedprofiles-field-' + entry.id ) }}
								</label>
								<input
									:id="'ip-field-' + entry.id"
									v-model="draft[entry.key]"
									class="cdx-text-input__input"
									:type="social_input_type( entry )"
									:maxlength="social_maxlength( entry )"
									:disabled="busy"
									:placeholder="
										msg( 'integratedprofiles-field-' + entry.id + '-placeholder' )
									"
								>
							</div>
						</div>
					</details>

					<!-- temporarily disabled -->
					<details
						v-if="show_wiki_profiles"
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

		<footer
			class="ip-editor__footer"
			:class="{ 'ip-editor__footer--settled': footer_settled }"
		>
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
					{{ dismiss_label }}
				</button>
				<button
					type="button"
					class="cdx-button cdx-button--action-progressive cdx-button--weight-primary"
					:disabled="busy || !has_changes"
					@click="on_save"
				>
					{{ msg( 'integratedprofiles-save' ) }}
				</button>
			</div>
		</footer>
		<div
			ref="footer_sentinel"
			class="ip-editor__footer-sentinel"
			aria-hidden="true"
		/>
	</section>
</template>

<script setup lang="ts">
import { computed, onMounted, onUnmounted, reactive, ref } from 'vue';
import type { EnabledSocialLink, IntegratedProfilesConfig, ProfileConnection, ProfileFieldsMap, ProfilePayload } from '../types/mw';
import { apply_payload_to_dom, msg, save_profile_fields } from '../utils/api';

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

// temporarily disabled
const show_wiki_profiles = false;

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
	'ip-location': field_or_empty( props.config.fields && props.config.fields[ 'ip-location' ] ),
	'ip-featured-article': field_or_empty( props.config.fields && props.config.fields[ 'ip-featured-article' ] ),
	'ip-website': field_or_empty( props.config.fields && props.config.fields[ 'ip-website' ] ),
	'ip-twitter': field_or_empty( props.config.fields && props.config.fields[ 'ip-twitter' ] ),
	'ip-github': field_or_empty( props.config.fields && props.config.fields[ 'ip-github' ] ),
	'ip-discord': field_or_empty( props.config.fields && props.config.fields[ 'ip-discord' ] ),
	'ip-roblox': field_or_empty( props.config.fields && props.config.fields[ 'ip-roblox' ] ),
	'ip-youtube': field_or_empty( props.config.fields && props.config.fields[ 'ip-youtube' ] ),
	'ip-mediawiki': field_or_empty( props.config.fields && props.config.fields[ 'ip-mediawiki' ] ),
	'ip-miraheze': field_or_empty( props.config.fields && props.config.fields[ 'ip-miraheze' ] ),
	'ip-fandom': field_or_empty( props.config.fields && props.config.fields[ 'ip-fandom' ] ),
	'ip-banner': field_or_empty( props.config.fields && props.config.fields[ 'ip-banner' ] ) || 'accent',
	'ip-banner-wiki': field_or_empty( props.config.fields && props.config.fields[ 'ip-banner-wiki' ] ),
	'ip-hide-connections': flag_or_off( props.config.fields && props.config.fields[ 'ip-hide-connections' ] ),
	'ip-show-pronouns': flag_or_off( props.config.fields && props.config.fields[ 'ip-show-pronouns' ] ),
	'ip-visibility': normalize_visibility( props.config.fields && props.config.fields[ 'ip-visibility' ] )
} );

const busy = ref( false );
const error_message = ref( '' );
const success_message = ref( '' );
const footer_settled = ref( false );
const footer_sentinel = ref<HTMLElement | null>( null );
let footer_observer: IntersectionObserver | null = null;
const selected_banner = ref( draft[ 'ip-banner' ] || 'accent' );
const has_custom_banner = ref( !!props.config.has_custom_banner );
const banner_url = ref( props.config.banner_url || '' );
const avatar_preview_url = ref( props.config.avatar_url || '' );
const social_links_open = ref( false );
const wiki_profiles_open = ref( Boolean( draft[ 'ip-mediawiki' ] || draft[ 'ip-miraheze' ] || draft[ 'ip-fandom' ] ) );

const DEFAULT_SOCIAL_LINKS: EnabledSocialLink[] = [
	{ id: 'website', key: 'ip-website', type: 'url' },
	{ id: 'twitter', key: 'ip-twitter', type: 'handle' },
	{ id: 'github', key: 'ip-github', type: 'handle' },
	{ id: 'discord', key: 'ip-discord', type: 'discord_username' },
	{ id: 'roblox', key: 'ip-roblox', type: 'roblox_username' },
	{ id: 'youtube', key: 'ip-youtube', type: 'youtube_url' }
];

const enabled_social_links = computed( (): EnabledSocialLink[] => {
	const configured = props.config.enabled_social_links;
	if ( Array.isArray( configured ) ) {
		return configured;
	}

	return DEFAULT_SOCIAL_LINKS;
} );

const website_enabled = computed(
	() => enabled_social_links.value.some( ( entry ) => entry.id === 'website' )
);

const bundle_social_links = computed(
	() => enabled_social_links.value.filter( ( entry ) => entry.id !== 'website' )
);

social_links_open.value = bundle_social_links.value.some(
	( entry ) => Boolean( draft[ entry.key ] )
);

const about_max = computed( () => ( props.config.limits && props.config.limits.about ) || 80 );
const link_max = computed( () => ( props.config.limits && props.config.limits.link ) || 255 );

function social_input_type( entry: EnabledSocialLink ): string {
	return entry.type === 'url' || entry.type === 'youtube_url' ? 'url' : 'text';
}

function social_maxlength( entry: EnabledSocialLink ): number {
	if ( entry.type === 'discord_username' ) {
		return 32;
	}
	if ( entry.type === 'roblox_username' ) {
		return 20;
	}
	if ( entry.type === 'handle' ) {
		return 64;
	}

	return link_max.value;
}

const banner_preset_images = computed( (): Record<string, string> => {
	return props.config.banner_preset_images || {};
} );
const banner_presets_split = computed( () => Boolean( props.config.banner_presets_split ) );
const wiki_banner_ids = computed( () => Object.keys( banner_preset_images.value ) );
const has_wiki_presets = computed( () => wiki_banner_ids.value.length > 0 );
const replace_gradient_presets = computed(
	() => has_wiki_presets.value && !banner_presets_split.value
);
const show_wiki_banners = computed(
	() => has_wiki_presets.value && banner_presets_split.value
);
const banner_presets = computed( () => {
	if ( replace_gradient_presets.value ) {
		return wiki_banner_ids.value;
	}
	if ( props.config.banner_presets && props.config.banner_presets.length ) {
		return props.config.banner_presets;
	}

	return DEFAULT_BANNER_PRESETS;
} );
const selected_wiki_banner = computed( () => {
	if ( !has_wiki_presets.value ) {
		return '';
	}
	return draft[ 'ip-banner-wiki' ] || '';
} );
const gradient_selected = computed( () => {
	if ( selected_wiki_banner.value ) {
		return '';
	}
	return selected_banner.value;
} );
const wiki_banner_label = computed( () => {
	const sitename = String( mw.config.get( 'wgSiteName' ) || '' );
	return msg( 'integratedprofiles-banner-wiki-label', sitename );
} );

function build_save_fields(): Partial<ProfileFieldsMap> {
	const payload: Partial<ProfileFieldsMap> = {
		'ip-about': draft[ 'ip-about' ],
		'ip-location': draft[ 'ip-location' ],
		'ip-featured-article': draft[ 'ip-featured-article' ],
		'ip-banner': draft[ 'ip-banner' ],
		'ip-hide-connections': draft[ 'ip-hide-connections' ],
		'ip-show-pronouns': draft[ 'ip-show-pronouns' ],
		'ip-visibility': draft[ 'ip-visibility' ]
	};

	if ( has_wiki_presets.value ) {
		payload[ 'ip-banner-wiki' ] = draft[ 'ip-banner-wiki' ];
	}

	if ( show_wiki_profiles ) {
		payload[ 'ip-mediawiki' ] = draft[ 'ip-mediawiki' ];
		payload[ 'ip-miraheze' ] = draft[ 'ip-miraheze' ];
		payload[ 'ip-fandom' ] = draft[ 'ip-fandom' ];
	}

	for ( const entry of enabled_social_links.value ) {
		payload[ entry.key ] = draft[ entry.key ] || '';
	}

	return payload;
}

function clone_save_fields(): Partial<ProfileFieldsMap> {
	return Object.assign( {}, build_save_fields() );
}

function field_snapshot_value(
	fields: Partial<ProfileFieldsMap>,
	key: string
): string {
	return fields[ key as keyof ProfileFieldsMap ] || '';
}

const saved_fields = ref<Partial<ProfileFieldsMap>>( clone_save_fields() );

function remember_saved_fields( patch?: Partial<ProfileFieldsMap> ): void {
	if ( patch ) {
		saved_fields.value = Object.assign( {}, saved_fields.value, patch );
		return;
	}

	saved_fields.value = clone_save_fields();
}

const has_changes = computed( () => {
	const current = build_save_fields();
	const baseline = saved_fields.value;
	const keys = new Set( [
		...Object.keys( current ),
		...Object.keys( baseline )
	] );

	for ( const key of keys ) {
		if ( field_snapshot_value( current, key ) !== field_snapshot_value( baseline, key ) ) {
			return true;
		}
	}

	return false;
} );

const dismiss_label = computed( () => (
	has_changes.value ?
		msg( 'integratedprofiles-cancel' ) :
		msg( 'integratedprofiles-modal-close' )
) );

const about_length = computed( () => ( draft[ 'ip-about' ] || '' ).length );
const about_remaining = computed( () => about_max.value - about_length.value );
const location_length = computed( () => ( draft[ 'ip-location' ] || '' ).length );
const location_remaining = computed( () => about_max.value - location_length.value );
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
const user_preferences_url = computed(
	() => ( props.config.user_preferences_url || '' ).trim()
);
const hide_connections = computed( {
	get: () => draft[ 'ip-hide-connections' ] === '1',
	set: ( value: boolean ) => {
		draft[ 'ip-hide-connections' ] = value ? '1' : '0';
	}
} );
const show_pronouns = computed( {
	get: () => draft[ 'ip-show-pronouns' ] === '1',
	set: ( value: boolean ) => {
		draft[ 'ip-show-pronouns' ] = value ? '1' : '0';
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
	return [
		'ip-editor__banner-swatch--' + preset_id,
		{
			'ip-editor__banner-swatch--wiki': !!banner_preset_images.value[ preset_id ],
			'ip-editor__banner-swatch--selected': main_preset_selected( preset_id )
		}
	];
}

function main_preset_selected( preset_id: string ): boolean {
	if ( replace_gradient_presets.value ) {
		return selected_wiki_banner.value === preset_id;
	}

	return gradient_selected.value === preset_id;
}

function banner_image_style( preset_id: string ): Record<string, string> {
	const url = banner_preset_images.value[ preset_id ];
	if ( !url ) { return {}; }

	return { backgroundImage: 'url(' + url + ')' };
}

function merge_swatch_style( preset_id: string ): Record<string, string> {
	if ( banner_presets_split.value ) {
		return {};
	}

	return banner_image_style( preset_id );
}

function preset_label( preset_id: string ): string {
	const key = 'integratedprofiles-banner-preset-' + preset_id;
	if ( mw.message( key ).exists() ) {
		return msg( key );
	}

	return preset_id;
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

function sync_banner_from_profile( profile: { fields?: { 'ip-banner'?: string; 'ip-banner-wiki'?: string }; banner_url?: string; has_custom_banner?: boolean; } ): void {
	const mode = ( profile.fields && profile.fields[ 'ip-banner' ] ) || 'accent';
	const wiki_mode = ( profile.fields && profile.fields[ 'ip-banner-wiki' ] ) || '';
	selected_banner.value = mode;
	draft[ 'ip-banner' ] = mode;
	draft[ 'ip-banner-wiki' ] = wiki_mode;
	has_custom_banner.value = !!profile.has_custom_banner;
	banner_url.value = profile.banner_url || '';

	const live_config = mw.config.get( 'wgIntegratedProfiles' ) as IntegratedProfilesConfig | null;

	if ( live_config ) {
		live_config.has_custom_banner = has_custom_banner.value;
		live_config.banner_url = banner_url.value;

		if ( live_config.fields ) {
			live_config.fields[ 'ip-banner' ] = mode;
			live_config.fields[ 'ip-banner-wiki' ] = wiki_mode;
		}
	}
}

async function on_select_banner( preset_id: string ): Promise<void> {
	if ( busy.value || main_preset_selected( preset_id ) ) { return; }
	if ( preset_id === 'custom' && !has_custom_banner.value ) { return; }

	busy.value = true;
	error_message.value = '';
	success_message.value = '';
	try {
		const fields: Partial<ProfileFieldsMap> = { 'ip-banner': preset_id };
		if ( replace_gradient_presets.value && preset_id !== 'custom' ) {
			fields[ 'ip-banner-wiki' ] = preset_id;
			delete fields[ 'ip-banner' ];
		} else if ( has_wiki_presets.value ) {
			fields[ 'ip-banner-wiki' ] = '';
		}
		const profile = await save_profile_fields( fields, props.config.user_name );

		apply_payload_to_dom( profile );
		sync_banner_from_profile( profile );
		remember_saved_fields( {
			'ip-banner': draft[ 'ip-banner' ],
			'ip-banner-wiki': draft[ 'ip-banner-wiki' ]
		} );
	} catch ( err ) {
		error_message.value = err instanceof Error ? err.message : msg( 'integratedprofiles-save-error' );
	} finally {
		busy.value = false;
	}
}

async function on_select_wiki_banner( preset_id: string ): Promise<void> {
	if ( busy.value || selected_wiki_banner.value === preset_id ) { return; }

	busy.value = true;
	error_message.value = '';
	success_message.value = '';
	try {
		const profile = await save_profile_fields( { 'ip-banner-wiki': preset_id }, props.config.user_name );

		apply_payload_to_dom( profile );
		sync_banner_from_profile( profile );
		remember_saved_fields( { 'ip-banner-wiki': draft[ 'ip-banner-wiki' ] } );
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
		remember_saved_fields( {
			'ip-banner': draft[ 'ip-banner' ],
			'ip-banner-wiki': draft[ 'ip-banner-wiki' ]
		} );
	}
}

function on_avatar_updated( event: Event ): void {
	const custom = event as CustomEvent<ProfilePayload>;

	if ( custom.detail ) {
		sync_avatar_from_profile( custom.detail );
	}
}

async function on_save(): Promise<void> {
	if ( busy.value || !has_changes.value ) {
		return;
	}

	busy.value = true;
	error_message.value = '';
	success_message.value = '';

	try {
		const profile = await save_profile_fields(
			build_save_fields(),
			props.config.user_name
		);

		apply_payload_to_dom( profile );
		sync_banner_from_profile( profile );
		remember_saved_fields();
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

function sync_footer_settled(): void {
	const el = footer_sentinel.value;
	if ( !el ) {
		return;
	}

	const rect = el.getBoundingClientRect();
	footer_settled.value = rect.top < window.innerHeight && rect.bottom > 0;
}

onMounted( () => {
	document.addEventListener( 'ip-banner-updated', on_banner_updated );
	document.addEventListener( 'ip-avatar-updated', on_avatar_updated );
	sync_footer_settled();

	if ( typeof IntersectionObserver === 'function' && footer_sentinel.value ) {
		footer_observer = new IntersectionObserver( ( entries ) => {
			const entry = entries[ 0 ];
			if ( entry ) {
				footer_settled.value = entry.isIntersecting;
			}
		} );
		footer_observer.observe( footer_sentinel.value );
	}
} );

onUnmounted( () => {
	document.removeEventListener( 'ip-banner-updated', on_banner_updated );
	document.removeEventListener( 'ip-avatar-updated', on_avatar_updated );

	if ( footer_observer ) {
		footer_observer.disconnect();
		footer_observer = null;
	}
} );
</script>
