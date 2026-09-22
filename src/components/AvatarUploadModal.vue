<template>
	<Teleport to="body">
		<div
			class="ip-upload-modal-backdrop ip-avatar-modal-backdrop"
			@click.self="on_backdrop_click"
		>
			<div
				ref="dialog_el"
				class="cdx-dialog ip-upload-modal ip-avatar-modal"
				role="dialog"
				aria-modal="true"
				:aria-labelledby="title_id"
				tabindex="-1"
				@keydown="on_dialog_keydown"
			>
				<header class="cdx-dialog__header ip-upload-modal__header">
					<div class="cdx-dialog__header__title-group">
						<h2 :id="title_id" class="cdx-dialog__header__title ip-upload-modal__title">
							{{ msg( 'integratedprofiles-avatar-modal-title' ) }}
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

				<div class="cdx-dialog__body ip-upload-modal__body ip-avatar-modal__body">
					<ImageCropper
						v-if="pending_file && preview_url && !skip_crop"
						ref="cropper_el"
						class="ip-avatar-modal__cropper"
						:src="preview_url"
						:aspect="AVATAR_ASPECT"
						round_mask
						:alt="msg( 'integratedprofiles-avatar-alt' )"
						:disabled="busy"
						@error="on_cropper_error"
						@ready="cropper_ready = true"
					/>
					<div
						v-else
						class="ip-avatar-modal__preview"
					>
						<img
							class="ip-avatar-modal__preview-image"
							:src="display_url"
							:alt="msg( 'integratedprofiles-avatar-alt' )"
							width="128"
							height="128"
						>
					</div>

					<input
						id="ip-field-avatar"
						ref="file_input"
						class="ip-avatar-modal__file-input"
						type="file"
						:accept="file_accept"
						:disabled="busy"
						@change="on_file_selected"
					>

					<div class="ip-avatar-modal__controls">
						<label
							for="ip-field-avatar"
							class="cdx-button cdx-button--action-progressive
								ip-avatar-modal__choose"
							:class="{
								'cdx-button--weight-primary': !pending_file,
								'ip-avatar-modal__choose--disabled': busy
							}"
							:aria-disabled="busy"
						>
							{{ choose_label }}
						</label>
						<button
							v-if="has_custom_avatar && !pending_file"
							type="button"
							class="cdx-button ip-avatar-modal__delete"
							:disabled="busy"
							@click="on_delete"
						>
							{{ msg( 'integratedprofiles-avatar-delete' ) }}
						</button>
					</div>

					<p class="ip-upload-modal__help">
						{{ help_text }}
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
							{{ msg( 'integratedprofiles-avatar-confirm' ) }}
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
import {
	apply_payload_to_dom,
	delete_avatar,
	msg,
	upload_avatar
} from '../utils/api';
import { AVATAR_ASPECT, AVATAR_MAX_EDGE, type CropRect } from '../utils/crop';
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

const title_id = 'ip-avatar-modal-title';
const dialog_el = ref<HTMLElement | null>( null );
const file_input = ref<HTMLInputElement | null>( null );
const cropper_el = ref<CropperExpose | null>( null );

const busy = ref( false );
const error_message = ref( '' );
const has_custom_avatar = ref( !!props.config.has_custom_avatar );
const current_avatar_url = ref( props.config.avatar_url || '' );
const pending_file = ref<File | null>( null );
const preview_url = ref( '' );
const skip_crop = ref( false );
const cropper_ready = ref( false );
let pick_generation = 0;

const avatar_max_bytes = computed(
	() => ( props.config.limits && props.config.limits.avatar_max_bytes ) || 2097152
);
const avatar_max_mb = computed( () => {
	const megabytes = avatar_max_bytes.value / ( 1024 * 1024 );
	return Number.isInteger( megabytes ) ? String( megabytes ) : megabytes.toFixed( 1 );
} );

const can_upload_animated_avatar = computed(
	() => !!props.config.can_upload_animated_avatar
);

const file_accept = computed( () => can_upload_animated_avatar.value ?
	'image/jpeg,image/png,image/gif,image/webp' :
	'image/jpeg,image/png,image/webp'
);

const help_text = computed( () => {
	if ( pending_file.value ) {
		return skip_crop.value ?
			msg( 'integratedprofiles-avatar-preview-help' ) :
			msg( 'integratedprofiles-avatar-crop-help' );
	}
	const key = can_upload_animated_avatar.value ?
		'integratedprofiles-avatar-help' :
		'integratedprofiles-avatar-help-static';
	return msg( key, avatar_max_mb.value );
} );

const display_url = computed( () => preview_url.value || current_avatar_url.value );

const choose_label = computed( () => {
	if ( pending_file.value ) {
		return msg( 'integratedprofiles-avatar-replace' );
	}
	return msg(
		has_custom_avatar.value ?
			'integratedprofiles-avatar-replace' :
			'integratedprofiles-avatar-choose'
	);
} );

const can_confirm = computed( () => {
	if ( !pending_file.value ) { return false; }

	return skip_crop.value || cropper_ready.value;
} );

function read_masthead_avatar_url(): string {
	const avatar_img = document.querySelector( '.ip-avatar__image' ) as HTMLImageElement | null;
	return ( avatar_img && avatar_img.src ) || props.config.avatar_url || '';
}

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
	if ( busy.value ) {
		return;
	}
	revoke_preview();
	emit( 'close' );
}

function on_backdrop_click(): void {
	close_modal();
}

function on_cropper_error(): void {
	error_message.value = msg( 'integratedprofiles-avatar-error' );
}

function on_dialog_keydown( event: KeyboardEvent ): void {
	if ( busy.value ) {
		return;
	}
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

	if ( file.size > avatar_max_bytes.value ) {
		error_message.value = msg( 'integratedprofiles-error-avatar-size' );
		input.value = '';

		return;
	}

	if ( !can_upload_animated_avatar.value && file.type === 'image/gif' ) {
		error_message.value = msg( 'integratedprofiles-error-avatar-animated' );
		input.value = '';

		return;
	}

	let animated;
	try {
		animated = await file_is_animated( file );
	} catch {
		animated = file.type === 'image/gif';
	}

	if ( generation !== pick_generation ) { return; }

	if ( animated && !can_upload_animated_avatar.value ) {
		error_message.value = msg( 'integratedprofiles-error-avatar-animated' );
		input.value = '';

		return;
	}

	revoke_preview();
	error_message.value = '';
	skip_crop.value = animated;
	cropper_ready.value = animated;
	pending_file.value = file;
	preview_url.value = URL.createObjectURL( file );
}

function sync_config_avatar( avatar_url: string, custom: boolean ): void {
	const live_config = mw.config.get( 'wgIntegratedProfiles' ) as IntegratedProfilesConfig | null;
	if ( live_config ) {
		live_config.avatar_url = avatar_url;
		live_config.has_custom_avatar = custom;
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
					max_bytes: avatar_max_bytes.value,
					max_width: AVATAR_MAX_EDGE,
					max_height: AVATAR_MAX_EDGE
				} );
			} catch {
				error_message.value = msg( 'integratedprofiles-avatar-error' );

				return;
			}
		}

		const profile = await upload_avatar( to_upload, props.config.user_name );
		apply_payload_to_dom( profile );

		has_custom_avatar.value = !!profile.has_custom_avatar;
		current_avatar_url.value = profile.avatar_url || read_masthead_avatar_url();
		sync_config_avatar( current_avatar_url.value, has_custom_avatar.value );

		document.dispatchEvent( new CustomEvent( 'ip-avatar-updated', { detail: profile } ) );

		revoke_preview();
		emit( 'close' );
	} catch ( err ) {
		error_message.value = err instanceof Error ?
			err.message :
			msg( 'integratedprofiles-avatar-error' );
	} finally {
		busy.value = false;
	}
}

async function on_delete(): Promise<void> {
	busy.value = true;
	error_message.value = '';

	try {
		const profile = await delete_avatar( props.config.user_name );
		apply_payload_to_dom( profile );

		has_custom_avatar.value = !!profile.has_custom_avatar;
		current_avatar_url.value = profile.avatar_url || '';
		sync_config_avatar( current_avatar_url.value, has_custom_avatar.value );

		document.dispatchEvent( new CustomEvent( 'ip-avatar-updated', { detail: profile } ) );
		emit( 'close' );
	} catch ( err ) {
		error_message.value = err instanceof Error ? err.message : msg( 'integratedprofiles-avatar-error' );
	} finally {
		busy.value = false;
	}
}

onMounted( () => {
	current_avatar_url.value = read_masthead_avatar_url();
	document.body.classList.add( 'ip-avatar-modal-open' );

	nextTick( () => {
		dialog_el.value?.focus();
	} );
} );

onUnmounted( () => {
	revoke_preview();
	document.body.classList.remove( 'ip-avatar-modal-open' );

	const focus_target = props.return_focus;
	if ( focus_target && typeof focus_target.focus === 'function' ) {
		focus_target.focus();
	}
} );
</script>
