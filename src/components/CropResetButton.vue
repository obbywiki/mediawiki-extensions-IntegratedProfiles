<template>
	<button
		type="button"
		class="cdx-button cdx-button--action-default cdx-button--weight-quiet
			cdx-button--icon-only ip-upload-modal__reset"
		:aria-label="msg( 'integratedprofiles-crop-reset' )"
		:title="msg( 'integratedprofiles-crop-reset' )"
		:disabled="disabled"
		@click="$emit( 'click' )"
	>
		<span class="cdx-button__icon" aria-hidden="true"></span>
	</button>
</template>

<script setup lang="ts">
import { msg } from '../utils/api';

withDefaults( defineProps<{
	disabled?: boolean;
}>(), {
	disabled: false
} );

defineEmits( [ 'click' ] );
</script>
