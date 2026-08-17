<script setup>
defineProps({
	badge: { type: [String, Object], default: null },
	title: { type: String, required: true },
	subtitle: { type: String, default: '' },
});
</script>

<template>
	<div class="flex items-start justify-between gap-4 flex-wrap mb-5">
		<div>
			<span
				v-if="badge || $slots.badge"
				class="inline-flex items-center gap-1 rounded-full bg-primary/10 px-2.5 py-1 text-[0.6875rem] font-medium text-primary mb-2"
			>
				<slot name="badge">{{ typeof badge === 'string' ? badge : badge?.label }}</slot>
			</span>
			<h1 class="text-headline-sm text-on-surface mt-2">{{ title }}</h1>
			<p v-if="subtitle || $slots.subtitle" class="text-body-sm text-muted mt-1">
				<slot name="subtitle">{{ subtitle }}</slot>
			</p>
		</div>
		<div v-if="$slots.actions" class="flex items-center gap-2.5 flex-wrap w-full sm:w-auto">
			<slot name="actions" />
		</div>
	</div>
</template>
