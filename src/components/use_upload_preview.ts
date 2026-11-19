import { computed, onUnmounted, ref, type ComputedRef, type Ref } from 'vue';
import { msg } from '../utils/api';
import { ImageDimensionsError } from '../utils/image_dimensions';
import { inspect_image_file } from '../utils/image_meta';

type UploadPreview = {
	file_input: Ref<HTMLInputElement | null>;
	error_message: Ref<string>;
	pending_file: Ref<File | null>;
	preview_url: Ref<string>;
	skip_crop: Ref<boolean>;
	cropper_ready: Ref<boolean>;
	can_reset_crop: Ref<boolean>;
	can_confirm: ComputedRef<boolean>;
	file_accept: ComputedRef<string>;
	max_mb: ComputedRef<string>;
	revoke_preview: () => void;
	clear_pending: () => void;
	on_file_selected: ( event: Event ) => Promise<void>;
};

export function use_upload_preview( can_upload: ComputedRef<boolean>, can_upload_animated: ComputedRef<boolean>, max_bytes: ComputedRef<number>, messages: { size: string; animated: string; invalid: string } ): UploadPreview {
	const file_input = ref<HTMLInputElement | null>( null );
	const error_message = ref( '' );
	const pending_file = ref<File | null>( null );
	const preview_url = ref( '' );
	const inspecting_file = ref( false );
	const skip_crop = ref( false );
	const cropper_ready = ref( false );
	const can_reset_crop = ref( false );
	let pick_generation = 0;

	const can_confirm = computed( () => can_upload.value && !!pending_file.value &&
		!inspecting_file.value && ( skip_crop.value || cropper_ready.value ) );
	const file_accept = computed( () => can_upload_animated.value ?
		'image/jpeg,image/png,image/gif,image/webp' :
		'image/jpeg,image/png,image/webp',
	);
	const max_mb = computed( () => {
		const megabytes = max_bytes.value / ( 1024 * 1024 );
		return Number.isInteger( megabytes ) ? String( megabytes ) : megabytes.toFixed( 1 );
	} );

	function revoke_preview(): void {
		pick_generation++;
		inspecting_file.value = false;
		if ( preview_url.value ) {
			URL.revokeObjectURL( preview_url.value );
			preview_url.value = '';
		}

		pending_file.value = null;
		skip_crop.value = false;
		cropper_ready.value = false;
		can_reset_crop.value = false;

		if ( file_input.value ) {
			file_input.value.value = '';
		}
	}

	function clear_pending(): void {
		revoke_preview();
		error_message.value = '';
	}

	async function on_file_selected( event: Event ): Promise<void> {
		if ( !can_upload.value ) { return; }
		const input = event.target as HTMLInputElement;
		const file = input.files && input.files[ 0 ];
		if ( !file ) { return; }

		const generation = ++pick_generation;
		inspecting_file.value = false;

		if ( file.size > max_bytes.value ) {
			error_message.value = msg( messages.size );
			input.value = '';

			return;
		}

		if ( !can_upload_animated.value && file.type === 'image/gif' ) {
			error_message.value = msg( messages.animated );
			input.value = '';

			return;
		}

		let animated;
		inspecting_file.value = true;
		try {
			animated = ( await inspect_image_file( file ) ).animated;
		} catch ( err ) {
			if ( generation !== pick_generation ) { return; }
			error_message.value = msg( err instanceof ImageDimensionsError ? 'integratedprofiles-error-image-dimensions' : messages.invalid );
			input.value = '';

			return;
		} finally {
			if ( generation === pick_generation ) { inspecting_file.value = false; }
		}

		if ( generation !== pick_generation ) { return; }

		if ( animated && !can_upload_animated.value ) {
			error_message.value = msg( messages.animated );
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

	onUnmounted( revoke_preview );

	return {
		file_input,
		error_message,
		pending_file,
		preview_url,
		skip_crop,
		cropper_ready,
		can_reset_crop,
		can_confirm,
		file_accept,
		max_mb,
		revoke_preview,
		clear_pending,
		on_file_selected,
	};
}
