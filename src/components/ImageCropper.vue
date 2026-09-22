<template>
	<div
		ref="root_el"
		class="ip-cropper"
	>
		<div
			ref="viewport_el"
			class="ip-cropper__viewport"
			:class="viewport_class"
			:style="viewport_style"
			@pointerdown="on_pointer_down"
			@pointermove="on_pointer_move"
			@pointerup="on_pointer_up"
			@pointercancel="on_pointer_up"
		>
			<img
				ref="image_el"
				class="ip-cropper__image"
				:src="src"
				:alt="alt"
				draggable="false"
				@load="on_image_load"
				@error="on_image_error"
			>
			<div
				v-if="crop"
				ref="frame_el"
				class="ip-cropper__frame"
				:style="frame_style"
				:tabindex="disabled ? -1 : 0"
				role="group"
				:aria-label="msg( 'integratedprofiles-crop-frame' )"
				@keydown="on_frame_keydown"
			>
				<div class="ip-cropper__dim" aria-hidden="true" />
				<div
					v-if="round_mask"
					class="ip-cropper__corners"
					aria-hidden="true"
				/>
				<div
					v-for="shade in guide_shades"
					:key="shade.edge"
					class="ip-cropper__guide-shade"
					:class="'ip-cropper__guide-shade--' + shade.edge"
					:style="shade.style"
					aria-hidden="true"
				/>
				<div class="ip-cropper__box" aria-hidden="true" />
				<div
					v-if="round_mask"
					class="ip-cropper__ring"
					aria-hidden="true"
				/>
				<div
					v-if="guide_box_style"
					class="ip-cropper__guide"
					:style="guide_box_style"
					aria-hidden="true"
				/>
				<div class="ip-cropper__move" />
				<button
					v-for="handle in crop_handles"
					:key="handle"
					type="button"
					class="ip-cropper__handle"
					:class="'ip-cropper__handle--' + handle"
					:data-handle="handle"
					:aria-label="msg( 'integratedprofiles-crop-handle' )"
					:disabled="disabled"
					@keydown="on_handle_keydown( handle, $event )"
				>
					<span class="ip-cropper__handle-mark" />
				</button>
			</div>
		</div>
		<p
			v-if="callout"
			class="ip-cropper__callout"
			role="note"
		>
			{{ callout }}
		</p>
	</div>
</template>

<script setup lang="ts">
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { msg } from '../utils/api';
import { crop_around_center, frame_aspect_for_image, inscribed_guide_rect, is_default_cover_crop, is_identity_crop, max_cover_rect, pan_crop, resize_crop_from_handle, type CropHandle, type CropRect } from '../utils/crop';

const props = withDefaults( defineProps<{
	src: string;
	aspect: number;
	round_mask?: boolean;
	fit_image?: boolean;
	guide_aspect?: number;
	callout?: string;
	alt?: string;
	disabled?: boolean;
}>(), {
	round_mask: false,
	fit_image: false,
	guide_aspect: 0,
	callout: '',
	alt: '',
	disabled: false
} );

const emit = defineEmits( [ 'error', 'ready', 'can-reset' ] );

const crop_handles: CropHandle[] = [ 'nw', 'ne', 'sw', 'se' ];

const root_el = ref<HTMLElement | null>( null );
const viewport_el = ref<HTMLElement | null>( null );
const frame_el = ref<HTMLElement | null>( null );
const image_el = ref<HTMLImageElement | null>( null );
const image_width = ref( 0 );
const image_height = ref( 0 );
const host_width = ref( 0 );
const viewport_max_h = ref( 360 );
const crop = ref<CropRect | null>( null );
const moving = ref( false );
const active_handle = ref<CropHandle | null>( null );

let last_pointer_x = 0;
let last_pointer_y = 0;
let resize_origin: CropRect | null = null;
let resize_observer: ResizeObserver | null = null;

const frame_aspect = computed( () => {
	if ( !props.fit_image ) { return props.aspect; }

	return frame_aspect_for_image( props.aspect, image_width.value, image_height.value );
} );

const guide_rect = computed( () => inscribed_guide_rect( frame_aspect.value, props.guide_aspect ) );

const guide_box_style = computed( () => {
	const rect = guide_rect.value;
	if ( !rect ) { return null; }

	return {
		left: ( rect.x * 100 ) + '%',
		top: ( rect.y * 100 ) + '%',
		width: ( rect.width * 100 ) + '%',
		height: ( rect.height * 100 ) + '%'
	};
} );

const guide_shades = computed( () => {
	const rect = guide_rect.value;
	if ( !rect ) { return []; }

	if ( rect.height >= 1 ) {
		const side = ( rect.x * 100 ) + '%';
		return [
			{ edge: 'start', style: { width: side } },
			{ edge: 'end', style: { width: side } }
		];
	}

	const band = ( rect.y * 100 ) + '%';
	return [
		{ edge: 'top', style: { height: band } },
		{ edge: 'bottom', style: { height: band } }
	];
} );

const viewport_class = computed( () => ( {
	'ip-cropper__viewport--disabled': props.disabled,
	'ip-cropper__viewport--moving': moving.value,
	'ip-cropper__viewport--resize-nwse': active_handle.value === 'nw' || active_handle.value === 'se',
	'ip-cropper__viewport--resize-nesw': active_handle.value === 'ne' || active_handle.value === 'sw'
} ) );

const viewport_style = computed( () => {
	if ( image_width.value <= 0 || image_height.value <= 0 || host_width.value <= 0 ) {
		return { width: '100%', minHeight: '12rem' };
	}

	const scale = Math.min(
		host_width.value / image_width.value,
		viewport_max_h.value / image_height.value
	);

	return {
		width: Math.max( 1, Math.round( image_width.value * scale ) ) + 'px',
		height: Math.max( 1, Math.round( image_height.value * scale ) ) + 'px'
	};
} );

const frame_style = computed( () => {
	const rect = crop.value;
	if ( !rect || image_width.value <= 0 || image_height.value <= 0 ) {
		return {};
	}

	return {
		left: ( rect.x / image_width.value * 100 ) + '%',
		top: ( rect.y / image_height.value * 100 ) + '%',
		width: ( rect.width / image_width.value * 100 ) + '%',
		height: ( rect.height / image_height.value * 100 ) + '%'
	};
} );

const can_reset = computed( () => {
	if ( !crop.value || image_width.value <= 0 || image_height.value <= 0 ) { return false; }

	return !is_default_cover_crop(
		crop.value,
		image_width.value,
		image_height.value,
		frame_aspect.value
	);
} );

function is_crop_handle( value: string | null ): value is CropHandle {
	return value === 'nw' || value === 'ne' || value === 'sw' || value === 'se';
}

function measure_host(): void {
	if ( root_el.value ) {
		host_width.value = root_el.value.clientWidth;
	}
	if ( typeof window !== 'undefined' ) {
		viewport_max_h.value = Math.min( window.innerHeight * 0.52, 360 );
	}
}

function reset_crop(): void {
	if ( image_width.value <= 0 || image_height.value <= 0 ) {
		crop.value = null;

		return;
	}

	crop.value = max_cover_rect( image_width.value, image_height.value, frame_aspect.value );
	emit( 'ready' );
}

function on_image_load(): void {
	const img = image_el.value;

	if ( !img ) { return; }

	image_width.value = img.naturalWidth;
	image_height.value = img.naturalHeight;
	measure_host();
	reset_crop();
	nextTick( () => {
		measure_host();
	} );
}

function on_image_error(): void {
	crop.value = null;
	emit( 'error' );
}

function end_gesture( event: PointerEvent ): void {
	moving.value = false;
	active_handle.value = null;
	resize_origin = null;

	const target = event.currentTarget as HTMLElement;

	if ( target.hasPointerCapture( event.pointerId ) ) {
		target.releasePointerCapture( event.pointerId );
	}
}

function on_pointer_down( event: PointerEvent ): void {
	if ( props.disabled || !crop.value ) { return; }
	if ( event.button !== 0 && event.pointerType === 'mouse' ) { return; }

	const target = event.target;
	const handle_el = target instanceof Element ? target.closest( '[data-handle]' ) : null;
	const handle = handle_el ? handle_el.getAttribute( 'data-handle' ) : null;

	if ( is_crop_handle( handle ) ) {
		active_handle.value = handle;
		moving.value = false;
		resize_origin = { ...crop.value };
	} else {
		active_handle.value = null;
		moving.value = true;
		resize_origin = null;
	}

	last_pointer_x = event.clientX;
	last_pointer_y = event.clientY;
	( event.currentTarget as HTMLElement ).setPointerCapture( event.pointerId );
}

function on_pointer_move( event: PointerEvent ): void {
	if ( !crop.value || image_width.value <= 0 || image_height.value <= 0 ) { return; }

	const viewport = viewport_el.value;
	if ( !viewport ) { return; }

	const bounds = viewport.getBoundingClientRect();
	if ( bounds.width <= 0 || bounds.height <= 0 ) { return; }

	if ( active_handle.value && resize_origin ) {
		const pointer_x = ( event.clientX - bounds.left ) / bounds.width * image_width.value;
		const pointer_y = ( event.clientY - bounds.top ) / bounds.height * image_height.value;

		crop.value = resize_crop_from_handle(
			resize_origin,
			active_handle.value,
			pointer_x,
			pointer_y,
			image_width.value,
			image_height.value,
			frame_aspect.value
		);

		return;
	}

	if ( !moving.value ) { return; }

	const dx = ( event.clientX - last_pointer_x ) / bounds.width * image_width.value;
	const dy = ( event.clientY - last_pointer_y ) / bounds.height * image_height.value;

	last_pointer_x = event.clientX;
	last_pointer_y = event.clientY;

	crop.value = pan_crop(
		crop.value,
		dx,
		dy,
		image_width.value,
		image_height.value
	);
}

function on_pointer_up( event: PointerEvent ): void {
	if ( !moving.value && !active_handle.value ) { return; }
	end_gesture( event );
}

function corner_of( rect: CropRect, handle: CropHandle ): { x: number; y: number } {
	if ( handle === 'nw' ) {
		return { x: rect.x, y: rect.y };
	}
	if ( handle === 'ne' ) {
		return { x: rect.x + rect.width, y: rect.y };
	}
	if ( handle === 'sw' ) {
		return { x: rect.x, y: rect.y + rect.height };
	}
	return { x: rect.x + rect.width, y: rect.y + rect.height };
}

function on_handle_keydown( handle: CropHandle, event: KeyboardEvent ): void {
	if ( props.disabled || !crop.value || image_width.value <= 0 ) { return; }

	const outward: Record<CropHandle, string[]> = {
		nw: [ 'ArrowLeft', 'ArrowUp' ],
		ne: [ 'ArrowRight', 'ArrowUp' ],
		sw: [ 'ArrowLeft', 'ArrowDown' ],
		se: [ 'ArrowRight', 'ArrowDown' ]
	};
	const inward: Record<CropHandle, string[]> = {
		nw: [ 'ArrowRight', 'ArrowDown' ],
		ne: [ 'ArrowLeft', 'ArrowDown' ],
		sw: [ 'ArrowRight', 'ArrowUp' ],
		se: [ 'ArrowLeft', 'ArrowUp' ]
	};

	let direction = 0;
	if ( outward[ handle ].includes( event.key ) ) {
		direction = 1;
	} else if ( inward[ handle ].includes( event.key ) ) {
		direction = -1;
	}
	if ( !direction ) { return; }

	event.preventDefault();
	event.stopPropagation();

	const rect = crop.value;
	const corner = corner_of( rect, handle );
	const center_x = rect.x + rect.width / 2;
	const center_y = rect.y + rect.height / 2;
	const vx = corner.x - center_x;
	const vy = corner.y - center_y;
	const len = Math.hypot( vx, vy ) || 1;
	const step = Math.max( 8, Math.max( rect.width, rect.height ) * 0.06 ) * direction;

	crop.value = resize_crop_from_handle(
		rect,
		handle,
		corner.x + ( vx / len ) * step,
		corner.y + ( vy / len ) * step,
		image_width.value,
		image_height.value,
		frame_aspect.value
	);
}

function on_frame_keydown( event: KeyboardEvent ): void {
	if ( props.disabled || !crop.value || event.target !== frame_el.value ) { return; }

	const rect = crop.value;

	if ( event.shiftKey ) {
		const grow = event.key === 'ArrowRight' || event.key === 'ArrowUp';
		const shrink = event.key === 'ArrowLeft' || event.key === 'ArrowDown';
		if ( !grow && !shrink ) { return; }

		event.preventDefault();
		const delta = ( grow ? 1 : -1 ) * Math.max( 8, rect.width * 0.06 );
		crop.value = crop_around_center(
			rect.x + rect.width / 2,
			rect.y + rect.height / 2,
			rect.width + delta,
			frame_aspect.value,
			image_width.value,
			image_height.value
		);

		return;
	}

	const step_x = Math.max( 8, image_width.value * 0.02 );
	const step_y = Math.max( 8, image_height.value * 0.02 );
	const pans: Record<string, [ number, number ]> = {
		ArrowLeft: [ -step_x, 0 ],
		ArrowRight: [ step_x, 0 ],
		ArrowUp: [ 0, -step_y ],
		ArrowDown: [ 0, step_y ]
	};
	const pan = pans[ event.key ];
	if ( !pan ) { return; }

	event.preventDefault();
	crop.value = pan_crop(
		rect,
		pan[ 0 ],
		pan[ 1 ],
		image_width.value,
		image_height.value
	);
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

defineExpose( { get_crop_rect, get_image_size, is_identity, reset_crop } );

watch( can_reset, ( value ) => {
	emit( 'can-reset', value );
}, { immediate: true } );

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
	window.addEventListener( 'resize', measure_host );

	if ( typeof ResizeObserver !== 'function' ) { return; }

	resize_observer = new ResizeObserver( () => {
		measure_host();
	} );

	if ( root_el.value ) {
		resize_observer.observe( root_el.value );
	}
} );

onUnmounted( () => {
	window.removeEventListener( 'resize', measure_host );
	if ( resize_observer ) {
		resize_observer.disconnect();
		resize_observer = null;
	}
} );
</script>
