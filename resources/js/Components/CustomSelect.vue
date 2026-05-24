<template>
	<div class="form-group">
		<label v-if="showLabel && label" class="form-label">{{ label }}</label>
		<div class="custom-select" ref="rootEl">
			<div class="cs-trigger" :class="{ open }" @click.stop="toggle">
				<span :class="{ 'cs-placeholder': !selected }">
					<template v-if="selected">
						<span v-if="selected.dot" class="cs-dot" :style="{ background: selected.dot }"></span>
						{{ selected.label }}
					</template>
					<template v-else>{{ placeholder }}</template>
				</span>
				<svg class="cs-chevron" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
					<path d="M6 9l6 6 6-6" />
				</svg>
			</div>
			<div class="cs-dropdown" :class="{ open }">
				<div class="cs-search-wrap" @click.stop>
					<div class="cs-search-inner">
						<svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
							<circle cx="11" cy="11" r="8" /><path d="M21 21l-4.35-4.35" />
						</svg>
						<input v-model="query" ref="searchInput" class="cs-search-input" placeholder="Ara..." @click.stop />
					</div>
				</div>
				<div
					v-for="opt in filteredOptions"
					:key="opt.value"
					class="cs-option"
					:class="{ selected: opt.value === modelValue }"
					@click.stop="select(opt)"
				>
					<span v-if="opt.dot" class="cs-dot" :style="{ background: opt.dot }"></span>
					{{ opt.label }}
				</div>
				<div v-if="filteredOptions.length === 0" class="cs-empty">Sonuç bulunamadı</div>
			</div>
		</div>
	</div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount, nextTick } from 'vue'

const props = defineProps({
	modelValue: { default: null },
	label: { type: String, default: '' },
	showLabel: { type: Boolean, default: true },
	placeholder: { type: String, default: 'Seçiniz...' },
	options: { type: Array, required: true },
})

const emit = defineEmits(['update:modelValue'])

const open = ref(false)
const query = ref('')
const rootEl = ref(null)
const searchInput = ref(null)

const selected = computed(() => props.options.find((o) => o.value === props.modelValue) || null)

const filteredOptions = computed(() => {
	const q = query.value.trim().toLowerCase()
	if (!q) return props.options
	return props.options.filter((o) => o.label.toLowerCase().includes(q))
})

function toggle() {
	open.value = !open.value
	if (open.value) nextTick(() => searchInput.value?.focus())
}

function select(opt) {
	emit('update:modelValue', opt.value)
	open.value = false
	query.value = ''
}

function handleOutside(e) {
	if (rootEl.value && !rootEl.value.contains(e.target)) {
		open.value = false
		query.value = ''
	}
}

onMounted(() => document.addEventListener('click', handleOutside))
onBeforeUnmount(() => document.removeEventListener('click', handleOutside))
</script>

<style scoped>
.form-group { display: flex; flex-direction: column; gap: 5px; }
.custom-select { position: relative; }
.cs-trigger {
	height: 36px; padding: 0 12px;
	border: 1.5px solid #e8e8f0; border-radius: 9px;
	background: #fafafe; cursor: pointer;
	display: flex; align-items: center; justify-content: space-between;
	font-family: inherit; font-size: 13px; color: #1a1a2e;
	transition: border-color .15s, box-shadow .15s;
	user-select: none;
}
.cs-trigger:hover { border-color: #ccc; }
.cs-trigger.open {
	border-color: #4a6cf7;
	box-shadow: 0 0 0 3px rgba(74, 108, 247, 0.1);
	background: #fff;
}
.cs-trigger > span { flex: 1; display: flex; align-items: center; gap: 8px; }
.cs-placeholder { color: #bbb; }
.cs-chevron { transition: transform .15s; color: #aaa; flex-shrink: 0; }
.cs-trigger.open .cs-chevron { transform: rotate(180deg); }

.cs-dropdown {
	position: absolute; top: calc(100% + 6px); left: 0; right: 0;
	background: #fff; border-radius: 10px;
	border: 1px solid #e8e8f0;
	box-shadow: 0 8px 28px rgba(0, 0, 0, 0.12);
	z-index: 500; padding: 4px;
	opacity: 0; pointer-events: none;
	transform: translateY(-6px);
	transition: opacity .15s, transform .15s;
}
.cs-dropdown.open { opacity: 1; pointer-events: all; transform: translateY(0); }

.cs-option {
	padding: 8px 10px; border-radius: 7px; cursor: pointer;
	font-size: 13px; color: #333;
	display: flex; align-items: center; gap: 8px;
	transition: background .1s;
}
.cs-option:hover { background: #f0f2ff; color: #1a1a2e; }
.cs-option.selected { background: #f0f2ff; color: #4a6cf7; font-weight: 600; }

.cs-empty {
	padding: 10px; text-align: center;
	font-size: 12px; color: #bbb;
}

.cs-search-wrap {
	padding: 6px 6px 4px;
	border-bottom: 1px solid #f0f0f5;
	margin-bottom: 3px;
}
.cs-search-inner {
	display: flex; align-items: center; gap: 6px;
	background: #f5f5f8; border: 1.5px solid transparent;
	border-radius: 7px; padding: 4px 8px;
	transition: border-color .15s, background .15s;
}
.cs-search-inner:focus-within { background: #fff; border-color: #c0c8f8; }
.cs-search-inner svg { color: #bbb; flex-shrink: 0; }
.cs-search-input {
	border: none; background: none; outline: none;
	font-family: inherit; font-size: 12.5px; color: #333;
	width: 100%;
}
.cs-search-input::placeholder { color: #bbb; }

.cs-dot {
	width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0;
}
</style>
