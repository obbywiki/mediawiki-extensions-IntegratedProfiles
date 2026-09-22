<template>
	<Teleport to="body">
		<div
			class="ip-upload-modal-backdrop ip-banner-modal-backdrop"
			@click.self="on_backdrop_click"
		>
			<div
				ref="dialog_el"
				class="cdx-dialog ip-upload-modal ip-banner-modal"
				role="dialog"
				aria-modal="true"
				:aria-labelledby="title_id"
				tabindex="-1"
				@keydown="on_dialog_keydown"
			>
				<header class="cdx-dialog__header ip-upload-modal__header">
					<div class="cdx-dialog__header__title-group">
						<h2 :id="title_id" class="cdx-dialog__header__title ip-upload-modal__title">
							{{ msg( 'integratedprofiles-banner-modal-title' ) }}
						</h2>
					</div>
					<button
						type="button"
						class="ip-upload-modal__close"
						:aria-label="msg( 'integratedprofiles-modal-close' )"
						:disabled="busy"
						@click="close_modal"
					>
						×
					</button>
				</header>

				<div class="cdx-dialog__body ip-upload-modal__body ip-banner-modal__body">
					<ImageCropper
						v-if="pending_file && preview_url && !skip_crop"
						ref="cropper_el"
						class="ip-banner-modal__cropper"
						:src="preview_url"
						:aspect="banner_aspect"
						:guide_aspect="BANNER_GUIDE_ASPECT"
						:callout="msg( 'integratedprofiles-banner-crop-callout' )"
						fit_image
						:alt="msg( 'integratedprofiles-banner-modal-title' )"
						:disabled="busy"
						@error="on_cropper_error"
						@ready="cropper_ready = true"
					/>
					<div
						v-else
						class="ip-banner-modal__preview"
						:style="preview_style"
					/>

					<input
						id="ip-field-banner"
						ref="file_input"
						class="ip-banner-modal__file-input"
						type="file"
						accept="image/jpeg,image/png,image/gif,image/webp"
						:disabled="busy"
						@change="on_file_selected"
					>

					<div class="ip-banner-modal__controls">
						<label
							for="ip-field-banner"
							class="cdx-button cdx-button--action-progressive
								ip-banner-modal__choose"
							:class="{
								'cdx-button--weight-primary': !pending_file,
								'ip-banner-modal__choose--disabled': busy
							}"
							:aria-disabled="busy"
						>
							{{ choose_label }}
						</label>
						<button
							v-if="has_custom_banner && !pending_file"
							type="button"
							class="cdx-button ip-banner-modal__delete"
							:disabled="busy"
							@click="on_delete"
						>
							{{ msg( 'integratedprofiles-banner-delete' ) }}
						</button>
					</div>

					<p class="ip-upload-modal__help">
						{{ pending_file ?
							( skip_crop ?
								msg( 'integratedprofiles-banner-preview-help' ) :
								msg( 'integratedprofiles-banner-crop-help' ) ) :
							msg( 'integratedprofiles-banner-help-size', banner_max_mb ) }}
					</p>

					<p
						v-if="error_message"
						class="ip-upload-modal__message ip-upload-modal__message--error"
						role="alert"
					>
						{{ error_message }}
					</p>
				</div>

				<footer
					v-if="pending_file"
					class="cdx-dialog__footer ip-upload-modal__footer"
				>
					<div class="ip-upload-modal__actions">
						<button
							type="button"
							class="cdx-button ip-upload-modal__cancel"
							:disabled="busy"
							@click="clear_pending"
						>
							{{ msg( 'integratedprofiles-cancel' ) }}
						</button>
						<button
							type="button"
							class="cdx-button cdx-button--action-progressive
								cdx-button--weight-primary ip-upload-modal__confirm"
							:disabled="busy || !can_confirm"
							@click="on_confirm"
						>
							{{ msg( 'integratedprofiles-banner-confirm' ) }}
							<kbd
								class="ip-upload-modal__enter-hint"
								aria-hidden="true"
							>↵</kbd>
						</button>
					</div>
				</footer>
			</div>
		</div>
	</Teleport>
</template>

<script setup lang="ts">
import { computed, nextTick, onMounted, onUnmounted, ref } from 'vue';
import type { IntegratedProfilesConfig } from '../types/mw';
import ImageCropper from './ImageCropper.vue';
import { apply_payload_to_dom, delete_banner, msg, upload_banner } from '../utils/api';
import { BANNER_GUIDE_ASPECT, BANNER_MAX_WIDTH, banner_output_max_height, read_banner_frame_aspect, type CropRect } from '../utils/crop';
import { prepare_upload_file } from '../utils/crop_export';
import { file_is_animated } from '../utils/image_meta';

type CropperExpose = {
	get_crop_rect: () => CropRect | null;
	get_image_size: () => { width: number; height: number };
	is_identity: () => boolean;
};

const props = defineProps<{
	config: IntegratedProfilesConfig;
	return_focus?: HTMLElement | null;
}>();

const emit = defineEmits( [ 'close' ] );

function custom_file_url( source: { custom_banner_url?: string; banner_url?: string; has_custom_banner?: boolean; fields?: { 'ip-banner-wiki'?: string }; banner_preset_images?: Record<string, string> } ): string {
	const explicit = ( source.custom_banner_url || '' ).trim();

	if ( explicit ) {
		return explicit;
	}
	if ( !source.has_custom_banner ) {
		return '';
	}

	const wiki_id = ( source.fields && source.fields[ 'ip-banner-wiki' ] ) || '';
	const images = source.banner_preset_images || {};

	if ( wiki_id && images[ wiki_id ] ) {
		return '';
	}

	return ( source.banner_url || '' ).trim();
}

const title_id = 'ip-banner-modal-title';
const dialog_el = ref<HTMLElement | null>( null );
const file_input = ref<HTMLInputElement | null>( null );
const cropper_el = ref<CropperExpose | null>( null );

const busy = ref( false );
const error_message = ref( '' );
const has_custom_banner = ref( !!props.config.has_custom_banner );
const current_banner_url = ref( custom_file_url( props.config ) );
const pending_file = ref<File | null>( null );
const preview_url = ref( '' );
const skip_crop = ref( false );
const cropper_ready = ref( false );
const banner_aspect = ref( read_banner_frame_aspect() );
let pick_generation = 0;

const banner_max_bytes = computed( () => ( props.config.limits && props.config.limits.banner_max_bytes ) || 4194304 );
const banner_max_mb = computed( () => {
	const megabytes = banner_max_bytes.value / ( 1024 * 1024 );

	return Number.isInteger( megabytes ) ? String( megabytes ) : megabytes.toFixed( 1 );
} );

const display_url = computed( () => preview_url.value || current_banner_url.value );

const preview_style = computed( () => {
	const url = display_url.value;
	const style: Record<string, string> = {
		aspectRatio: String( banner_aspect.value )
	};
	if ( url ) {
		style.backgroundImage = 'url(' + url + ')';
	}

	return style;
} );

const choose_label = computed( () => {
	if ( pending_file.value ) {
		return msg( 'integratedprofiles-banner-replace' );
	}

	return msg(
		has_custom_banner.value ? 'integratedprofiles-banner-replace' : 'integratedprofiles-banner-choose'
	);
} );

const can_confirm = computed( () => {
	if ( !pending_file.value ) {
		return false;
	}
	return skip_crop.value || cropper_ready.value;
} );

function revoke_preview(): void {
	if ( preview_url.value ) {
		URL.revokeObjectURL( preview_url.value );
		preview_url.value = '';
	}

	pending_file.value = null;
	skip_crop.value = false;
	cropper_ready.value = false;

	if ( file_input.value ) {
		file_input.value.value = '';
	}
}

function clear_pending(): void {
	revoke_preview();
	error_message.value = '';
}

function close_modal(): void {
	if ( busy.value ) { return; }

	revoke_preview();
	emit( 'close' );
}

function on_backdrop_click(): void {
	close_modal();
}

function on_cropper_error(): void {
	error_message.value = msg( 'integratedprofiles-banner-error' );
}

function on_dialog_keydown( event: KeyboardEvent ): void {
	if ( busy.value ) { return; }

	if ( event.key === 'Escape' ) {
		event.stopPropagation();
		close_modal();
		return;
	}

	if ( event.key === 'Enter' && pending_file.value && can_confirm.value && !( event.target instanceof HTMLButtonElement ) && !( event.target instanceof HTMLLabelElement ) && !( event.target instanceof HTMLInputElement ) ) {
		event.preventDefault();
		event.stopPropagation();
		on_confirm();
	}
}

async function on_file_selected( event: Event ): Promise<void> {
	const input = event.target as HTMLInputElement;
	const file = input.files && input.files[ 0 ];
	if ( !file ) { return; }

	const generation = ++pick_generation;

	if ( file.size > banner_max_bytes.value ) {
		error_message.value = msg( 'integratedprofiles-error-banner-size' );
		input.value = '';

		return;
	}

	let animated;
	try {
		animated = await file_is_animated( file );
	} catch {
		animated = file.type === 'image/gif';
	}

	if ( generation !== pick_generation ) {
		return;
	}

	revoke_preview();
	error_message.value = '';
	skip_crop.value = animated;
	cropper_ready.value = animated;
	pending_file.value = file;
	preview_url.value = URL.createObjectURL( file );
}

function sync_config_banner( file_url: string, custom: boolean ): void {
	const live_config = mw.config.get( 'wgIntegratedProfiles' ) as IntegratedProfilesConfig | null;
	if ( live_config ) {
		live_config.custom_banner_url = custom ? file_url : '';
		live_config.has_custom_banner = custom;

		if ( custom ) {
			live_config.banner_url = file_url;
		}

		if ( live_config.fields ) {
			live_config.fields[ 'ip-banner' ] = custom ? 'custom' : 'accent';
		}
	}
}

async function on_confirm(): Promise<void> {
	const file = pending_file.value;
	if ( !file || !can_confirm.value ) { return; }

	busy.value = true;
	error_message.value = '';
	try {
		let to_upload = file;
		if ( !skip_crop.value && cropper_el.value ) {
			const size = cropper_el.value.get_image_size();
			try {
				to_upload = await prepare_upload_file( file, {
					skip_crop: false,
					crop: cropper_el.value.get_crop_rect(),
					image_width: size.width,
					image_height: size.height,
					max_bytes: banner_max_bytes.value,
					max_width: BANNER_MAX_WIDTH,
					max_height: banner_output_max_height( banner_aspect.value ),
					aspect: banner_aspect.value,
					pass_through_default_cover: true
				} );
			} catch {
				error_message.value = msg( 'integratedprofiles-banner-error' );
				return;
			}
		}

		const profile = await upload_banner( to_upload, props.config.user_name );
		apply_payload_to_dom( profile );
		has_custom_banner.value = !!profile.has_custom_banner;
		current_banner_url.value = custom_file_url( profile );
		sync_config_banner( current_banner_url.value, has_custom_banner.value );
		document.dispatchEvent( new CustomEvent( 'ip-banner-updated', { detail: profile } ) );

		revoke_preview();
		emit( 'close' );
	} catch ( err ) {
		error_message.value = err instanceof Error ?
			err.message :
			msg( 'integratedprofiles-banner-error' );
	} finally {
		busy.value = false;
	}
}

async function on_delete(): Promise<void> {
	busy.value = true;
	error_message.value = '';
	try {
		const profile = await delete_banner( props.config.user_name );
		apply_payload_to_dom( profile );
		has_custom_banner.value = !!profile.has_custom_banner;
		current_banner_url.value = custom_file_url( profile );
		sync_config_banner( current_banner_url.value, has_custom_banner.value );
		document.dispatchEvent( new CustomEvent( 'ip-banner-updated', { detail: profile } ) );
		emit( 'close' );
	} catch ( err ) {
		error_message.value = err instanceof Error ?
			err.message :
			msg( 'integratedprofiles-banner-error' );
	} finally {
		busy.value = false;
	}
}

function sync_banner_aspect(): void {
	banner_aspect.value = read_banner_frame_aspect();
}

onMounted( () => {
	sync_banner_aspect();
	document.body.classList.add( 'ip-banner-modal-open' );
	window.addEventListener( 'resize', sync_banner_aspect );
	nextTick( () => {
		sync_banner_aspect();
		dialog_el.value?.focus();
	} );
} );

onUnmounted( () => {
	window.removeEventListener( 'resize', sync_banner_aspect );
	revoke_preview();
	document.body.classList.remove( 'ip-banner-modal-open' );

	const focus_target = props.return_focus;
	if ( focus_target && typeof focus_target.focus === 'function' ) {
		focus_target.focus();
	}
} );
</script>
