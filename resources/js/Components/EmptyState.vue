<script setup>
defineProps({
	icon: { type: [Object, Function], default: null },
	title: { type: String, default: 'Kayıt bulunamadı' },
	hint: { type: String, default: '' },
});
</script>

<template>
	<div class="py-10 flex flex-col items-center justify-center gap-2 text-center">
		<span v-if="icon" class="w-12 h-12 rounded-full bg-canvas flex items-center justify-center">
			<component :is="icon" :size="20" class="text-muted" />
		</span>
		<p class="text-xs font-medium text-ink">{{ title }}</p>
		<p v-if="hint" class="text-2xs text-muted max-w-xs">{{ hint }}</p>
		<slot />
	</div>
</template>
