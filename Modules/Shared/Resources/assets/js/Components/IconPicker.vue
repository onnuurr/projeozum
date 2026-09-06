<template>
	<div class="icon-picker">
		<button
			v-for="key in icons"
			:key="key"
			type="button"
			class="icon-picker-button"
			:class="{ active: modelValue === key }"
			:title="key"
			@click="toggleIcon(key)"
		>
			<slot name="icon" :key="key" :selected="modelValue === key">
				<span v-html="renderIcon(key)" />
			</slot>
		</button>
	</div>
</template>

<script setup>
defineProps({
	modelValue: [String, null],
	icons: { type: Array, required: true },
	renderIcon: { type: Function, required: true },
})

const emit = defineEmits(['update:modelValue'])

function toggleIcon(key) {
	emit('update:modelValue', modelValue === key ? null : key)
}
</script>

<style scoped>
.icon-picker {
	display: grid;
	grid-template-columns: repeat(8, 1fr);
	gap: 6px;
}

.icon-picker-button {
	width: 100%;
	aspect-ratio: 1;
	display: flex;
	align-items: center;
	justify-content: center;
	border: 1.5px solid var(--color-outline-variant);
	border-radius: 8px;
	background: var(--color-surface);
	color: var(--color-muted);
	cursor: pointer;
	transition: all 0.15s;
	font-size: 16px;
	padding: 0;
}

.icon-picker-button:hover {
	border-color: var(--color-primary);
	color: var(--color-primary);
}

.icon-picker-button.active {
	border-color: var(--color-primary);
	color: var(--color-primary);
	background: var(--color-primary-soft);
}

@media (max-width: 640px) {
	.icon-picker {
		grid-template-columns: repeat(5, 1fr);
	}
}
</style>
