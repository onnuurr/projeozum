<script setup>
defineProps({
	title: { type: String, default: '' },
	bodyClass: { type: String, default: 'p-3 sm:p-4' },
});
</script>

<template>
	<div class="bg-surface-container-lowest rounded-lg border border-outline-variant overflow-hidden">
		<div v-if="title || $slots.actions" class="p-2.5 border-b border-outline-variant bg-surface-container-low flex items-center justify-between gap-3">
			<h3 class="text-xs font-semibold text-ink truncate">{{ title }}</h3>
			<slot name="actions" />
		</div>
		<div :class="bodyClass">
			<slot />
		</div>
	</div>
</template>
