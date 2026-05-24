<template>
	<div class="tabs">
		<div class="tab-bar" role="tablist">
			<button
				v-for="t in tabs"
				:key="t.key"
				type="button"
				class="tab-btn"
				:class="{ active: modelValue === t.key }"
				:aria-selected="modelValue === t.key"
				@click="$emit('update:modelValue', t.key)"
			>
				<span v-if="t.icon" class="tab-icon">{{ t.icon }}</span>
				{{ t.label }}
				<span v-if="t.badge != null" class="tab-badge">{{ t.badge }}</span>
			</button>
		</div>
		<div class="tab-panel">
			<slot />
		</div>
	</div>
</template>

<script setup>
defineProps({
	modelValue: { type: String, required: true },
	tabs:       { type: Array,  required: true }, // [{ key, label, icon?, badge? }]
})
defineEmits(['update:modelValue'])
</script>

<style scoped>
.tabs { width: 100%; }

.tab-bar {
	display: flex;
	gap: 4px;
	border-bottom: 1px solid #ebebf0;
	padding: 0 4px;
}

.tab-btn {
	background: none;
	border: none;
	cursor: pointer;
	padding: 10px 14px;
	font-family: inherit;
	font-size: 13px;
	font-weight: 500;
	color: #888;
	border-bottom: 2px solid transparent;
	display: inline-flex;
	align-items: center;
	gap: 6px;
	transition: color .15s, border-color .15s;
	position: relative;
	top: 1px;
}

.tab-btn:hover { color: #1a1a2e; }

.tab-btn.active {
	color: #1a1a2e;
	font-weight: 600;
	border-bottom-color: #7c3aed;
}

.tab-icon { font-size: 14px; }

.tab-badge {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	min-width: 18px;
	height: 18px;
	padding: 0 6px;
	border-radius: 9px;
	background: #f0f0f5;
	color: #555;
	font-size: 10.5px;
	font-weight: 700;
}

.tab-btn.active .tab-badge { background: #ede9fe; color: #7c3aed; }

.tab-panel { padding-top: 16px; }
</style>
