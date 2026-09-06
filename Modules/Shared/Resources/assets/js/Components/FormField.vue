<template>
	<div class="form-field">
		<label v-if="label" :class="['form-field-label', { required }]">
			{{ label }}
		</label>
		<slot />
	</div>
</template>

<script setup>
defineProps({
	label: String,
	required: Boolean,
})
</script>

<style scoped>
.form-field {
	display: flex;
	flex-direction: column;
	gap: 6px;
}

.form-field-label {
	font-size: 12.5px;
	font-weight: 600;
	color: var(--color-muted);
	text-transform: uppercase;
	letter-spacing: 0.03em;
}

.form-field-label.required::after {
	content: ' *';
	color: var(--color-danger);
}
</style>
