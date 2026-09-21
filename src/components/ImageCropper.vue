<template>
	<div
		ref="root_el"
		class="ip-cropper"
	>
		<div
			ref="viewport_el"
			class="ip-cropper__viewport"
			:class="{ 'ip-cropper__viewport--disabled': disabled }"
			:style="viewport_style"
			@pointerdown="on_pointer_down"
			@pointermove="on_pointer_move"
			@pointerup="on_pointer_up"
			@pointercancel="on_pointer_up"
			@wheel.prevent="on_wheel"
		>
			<img
				ref="image_el"
				class="ip-cropper__image"
				:src="src"
				:alt="alt"
				draggable="false"
				:style="image_style"
				@load="on_image_load"
				@error="on_image_error"
			>
			<div
				ref="frame_el"
				class="ip-cropper__frame"
				:style="frame_style"
			>
				<div class="ip-cropper__dim" aria-hidden="true" />
				<svg
					class="ip-cropper__outline"
					viewBox="0 0 100 100"
					preserveAspectRatio="none"
					aria-hidden="true"
				>
					<rect
						class="ip-cropper__outline-path ip-cropper__outline-path--dark"
						x="0.75"
						y="0.75"
						width="98.5"
						height="98.5"
						fill="none"
						vector-effect="non-scaling-stroke"
					/>
					<rect
						class="ip-cropper__outline-path ip-cropper__outline-path--light"
						x="0.75"
						y="0.75"
						width="98.5"
						height="98.5"
						fill="none"
						vector-effect="non-scaling-stroke"
					/>
					<circle
						v-if="round_mask"
						class="ip-cropper__outline-path ip-cropper__outline-path--dark"
						cx="50"
						cy="50"
						r="49.25"
						fill="none"
						vector-effect="non-scaling-stroke"
					/>
					<circle
						v-if="round_mask"
						class="ip-cropper__outline-path ip-cropper__outline-path--light"
						cx="50"
						cy="50"
						r="49.25"
						fill="none"
						vector-effect="non-scaling-stroke"
					/>
				</svg>
			</div>
		</div>
		<label class="ip-cropper__zoom">
			<span class="ip-cropper__zoom-label">{{ zoom_label }}</span>
			<input
				class="ip-cropper__zoom-input"
				type="range"
				min="0"
				max="100"
				step="1"
				:value="zoom_percent"
				:disabled="disabled || !crop"
				:aria-label="zoom_label"
				@input="on_zoom_input"
			>
		</label>
	</div>
</template>

<script setup lang="ts">
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import {
	clamp_crop_rect,
	crop_width_for_zoom,
	image_to_frame_scale,
	is_identity_crop,
	max_cover_rect,
	pan_crop,
	zoom_percent_for_crop,
	type CropRect
} from '../utils/crop';

const props = withDefaults( defineProps<{
	src: string;
	aspect: number;
	round_mask?: boolean;
	alt?: string;
	zoom_label?: string;
	disabled?: boolean;
}>(), {
	round_mask: false,
	alt: '',
	zoom_label: '',
	disabled: false
} );

const emit = defineEmits( [ 'error', 'ready' ] );

const root_el = ref<HTMLElement | null>( null );
const viewport_el = ref<HTMLElement | null>( null );
const frame_el = ref<HTMLElement | null>( null );
const image_el = ref<HTMLImageElement | null>( null );
const image_width = ref( 0 );
const image_height = ref( 0 );
const frame_width = ref( 0 );
const frame_height = ref( 0 );
const frame_offset_x = ref( 0 );
const frame_offset_y = ref( 0 );
const host_width = ref( 0 );
const crop = ref<CropRect | null>( null );
const dragging = ref( false );

let last_pointer_x = 0;
let last_pointer_y = 0;
let resize_observer: ResizeObserver | null = null;

const frame_style = computed( () => ( {
	aspectRatio: String( props.aspect )
} ) );

const viewport_style = computed( () => {
	if ( frame_width.value <= 0 || image_width.value <= 0 ) {
		return props.round_mask ? {} : { width: '100%' };
	}

	const cover = max_cover_rect(
		image_width.value,
		image_height.value,
		props.aspect
	);
	const scale = image_to_frame_scale( frame_width.value, cover.width );
	const disp_w = image_width.value * scale;
	const disp_h = image_height.value * scale;
	const max_h = Math.max(
		frame_height.value,
		typeof window === 'undefined' ? frame_height.value : window.innerHeight * 0.55
	);

	if ( !props.round_mask ) {
		return {
			width: '100%',
			height: Math.min( Math.max( frame_height.value, disp_h ), max_h ) + 'px'
		};
	}

	const max_w = host_width.value > 0 ? host_width.value : disp_w;
	return {
		width: Math.min( max_w, Math.max( frame_width.value, disp_w ) ) + 'px',
		height: Math.min( max_h, Math.max( frame_height.value, disp_h ) ) + 'px'
	};
} );

const image_style = computed( () => {
	const rect = crop.value;
	if ( !rect || frame_width.value <= 0 ) {
		return { visibility: 'hidden' as const };
	}
	const scale = image_to_frame_scale( frame_width.value, rect.width );
	return {
		width: image_width.value + 'px',
		height: image_height.value + 'px',
		transform: 'translate(' +
			( frame_offset_x.value - rect.x * scale ) + 'px, ' +
			( frame_offset_y.value - rect.y * scale ) + 'px) scale(' + scale + ')',
		transformOrigin: '0 0'
	};
} );

const zoom_percent = computed( () => {
	if ( !crop.value || image_width.value <= 0 ) { return 0; }

	return zoom_percent_for_crop(
		crop.value,
		image_width.value,
		image_height.value,
		props.aspect
	);
} );

function measure_host(): void {
	if ( root_el.value ) {
		host_width.value = root_el.value.clientWidth;
	}
}

function measure_frame(): void {
	const frame = frame_el.value;
	if ( !frame ) { return; }

	frame_width.value = frame.clientWidth;
	frame_height.value = frame.clientHeight;
	frame_offset_x.value = frame.offsetLeft;
	frame_offset_y.value = frame.offsetTop;
}

function reset_crop(): void {
	if ( image_width.value <= 0 || image_height.value <= 0 ) {
		crop.value = null;

		return;
	}

	crop.value = max_cover_rect( image_width.value, image_height.value, props.aspect );
	emit( 'ready' );
}

function on_image_load(): void {
	const img = image_el.value;

	if ( !img ) { return; }

	image_width.value = img.naturalWidth;
	image_height.value = img.naturalHeight;
	measure_host();
	measure_frame();
	reset_crop();
	nextTick( () => {
		measure_frame();
	} );
}

function on_image_error(): void {
	crop.value = null;
	emit( 'error' );
}

function on_pointer_down( event: PointerEvent ): void {
	if ( props.disabled || !crop.value ) { return; }
	if ( event.button !== 0 && event.pointerType === 'mouse' ) { return; }

	dragging.value = true;
	last_pointer_x = event.clientX;
	last_pointer_y = event.clientY;
	( event.currentTarget as HTMLElement ).setPointerCapture( event.pointerId );
}

function on_pointer_move( event: PointerEvent ): void {
	if ( !dragging.value || !crop.value || frame_width.value <= 0 ) { return; }

	const scale = image_to_frame_scale( frame_width.value, crop.value.width );
	const dx_source = -( event.clientX - last_pointer_x ) / scale;
	const dy_source = -( event.clientY - last_pointer_y ) / scale;

	last_pointer_x = event.clientX;
	last_pointer_y = event.clientY;

	crop.value = pan_crop(
		crop.value,
		dx_source,
		dy_source,
		image_width.value,
		image_height.value
	);
}

function on_pointer_up( event: PointerEvent ): void {
	if ( !dragging.value ) { return; }
	dragging.value = false;

	const target = event.currentTarget as HTMLElement;

	if ( target.hasPointerCapture( event.pointerId ) ) {
		target.releasePointerCapture( event.pointerId );
	}
}

function zoom_to_percent( percent: number, origin_x: number, origin_y: number ): void {
	if ( image_width.value <= 0 || !crop.value ) { return; }

	const width = crop_width_for_zoom(
		percent,
		image_width.value,
		image_height.value,
		props.aspect
	);

	const rel_x = crop.value.width > 0 ? ( origin_x - crop.value.x ) / crop.value.width : 0.5;
	const rel_y = crop.value.height > 0 ? ( origin_y - crop.value.y ) / crop.value.height : 0.5;
	const next_height = width / props.aspect;

	crop.value = clamp_crop_rect(
		{
			x: origin_x - rel_x * width,
			y: origin_y - rel_y * next_height,
			width,
			height: next_height
		},
		image_width.value,
		image_height.value
	);
}

function on_zoom_input( event: Event ): void {
	if ( !crop.value ) { return; }

	const percent = Number( ( event.target as HTMLInputElement ).value );
	zoom_to_percent(
		percent,
		crop.value.x + crop.value.width / 2,
		crop.value.y + crop.value.height / 2
	);
}

function on_wheel( event: WheelEvent ): void {
	if ( props.disabled || !crop.value || frame_width.value <= 0 ) { return; }

	const scale = image_to_frame_scale( frame_width.value, crop.value.width );
	const bounds = frame_el.value?.getBoundingClientRect();

	const frame_x = bounds ? event.clientX - bounds.left : frame_width.value / 2;
	const frame_y = bounds ? event.clientY - bounds.top : frame_height.value / 2;
	const origin_x = crop.value.x + frame_x / scale;
	const origin_y = crop.value.y + frame_y / scale;

	const delta = event.deltaY > 0 ? -6 : 6;
	zoom_to_percent( zoom_percent.value + delta, origin_x, origin_y );
}

function get_crop_rect(): CropRect | null {
	return crop.value ? { ...crop.value } : null;
}

function get_image_size(): { width: number; height: number } {
	return { width: image_width.value, height: image_height.value };
}

function is_identity(): boolean {
	if ( !crop.value ) { return true; }

	return is_identity_crop( crop.value, image_width.value, image_height.value );
}

defineExpose( { get_crop_rect, get_image_size, is_identity } );

watch( () => props.src, () => {
	crop.value = null;
	image_width.value = 0;
	image_height.value = 0;
} );

watch( () => props.aspect, () => {
	reset_crop();
} );

onMounted( () => {
	measure_host();
	measure_frame();

	if ( typeof ResizeObserver !== 'function' ) { return; }

	resize_observer = new ResizeObserver( () => {
		measure_host();
		measure_frame();
	} );
	
	if ( root_el.value ) {
		resize_observer.observe( root_el.value );
	}
	if ( viewport_el.value ) {
		resize_observer.observe( viewport_el.value );
	}
	if ( frame_el.value ) {
		resize_observer.observe( frame_el.value );
	}
} );

onUnmounted( () => {
	if ( resize_observer ) {
		resize_observer.disconnect();
		resize_observer = null;
	}
} );
</script>
