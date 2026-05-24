<template>
	<Teleport to="body">
		<div class="search-overlay" :class="{ open: modelValue }" @click.self="close">
			<div class="search-modal">
				<div class="search-modal-input-row">
					<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
						<circle cx="11" cy="11" r="8" /><path d="M21 21l-4.35-4.35" />
					</svg>
					<input
						ref="searchInput"
						v-model="query"
						type="text"
						placeholder="Sipariş, müşteri, iş emri ara..."
						@keydown.esc="close"
					/>
					<kbd class="search-modal-esc" @click="close">Esc</kbd>
				</div>

				<template v-for="group in groupedResults" :key="group.label">
					<div class="search-section-label">{{ group.label }}</div>
					<div
						v-for="item in group.items"
						:key="item.text"
						class="search-result-item"
						@click="select(item)"
					>
						<div class="search-result-icon">{{ item.icon }}</div>
						<div>
							<div class="search-result-text">{{ item.text }}</div>
							<div class="search-result-sub">{{ item.sub }}</div>
						</div>
					</div>
				</template>

				<div class="search-modal-footer">
					<div class="search-hint"><kbd>↑</kbd><kbd>↓</kbd> gezin</div>
					<div class="search-hint"><kbd>↵</kbd> aç</div>
					<div class="search-hint"><kbd>Esc</kbd> kapat</div>
				</div>
			</div>
		</div>
	</Teleport>
</template>

<script setup>
import { ref, computed, watch, nextTick } from 'vue'

const props = defineProps({
	modelValue: { type: Boolean, default: false },
	recent: { type: Array, default: () => [] },
	quick: { type: Array, default: () => [] },
})

const emit = defineEmits(['update:modelValue', 'select'])

const query = ref('')
const searchInput = ref(null)

const groupedResults = computed(() => {
	const q = query.value.trim().toLowerCase()
	const filter = (arr) => (q ? arr.filter((i) => i.text.toLowerCase().includes(q)) : arr)
	const groups = []
	const recentFiltered = filter(props.recent)
	const quickFiltered = filter(props.quick)
	if (recentFiltered.length) groups.push({ label: 'Son Aramalar', items: recentFiltered })
	if (quickFiltered.length) groups.push({ label: 'Hızlı Erişim', items: quickFiltered })
	return groups
})

function close() {
	emit('update:modelValue', false)
	query.value = ''
}

function select(item) {
	emit('select', item)
	close()
}

watch(
	() => props.modelValue,
	(open) => {
		if (open) nextTick(() => searchInput.value?.focus())
	}
)
</script>

<style>
.search-overlay {
	position: fixed; inset: 0;
	background: rgba(20, 20, 40, 0.45);
	backdrop-filter: blur(4px);
	z-index: 10000;
	display: flex; align-items: flex-start; justify-content: center;
	padding-top: 80px;
	opacity: 0; pointer-events: none;
	transition: opacity .18s;
}
.search-overlay.open { opacity: 1; pointer-events: all; }

.search-modal {
	background: #fff; border-radius: 14px;
	box-shadow: 0 24px 60px rgba(0, 0, 0, 0.18);
	width: 540px; max-width: calc(100vw - 32px);
	overflow: hidden;
	transform: translateY(-12px) scale(0.98);
	transition: transform .18s;
}
.search-overlay.open .search-modal { transform: translateY(0) scale(1); }

.search-modal-input-row {
	display: flex; align-items: center; gap: 10px;
	padding: 14px 16px; border-bottom: 1px solid #f0f0f5;
}
.search-modal-input-row svg { color: #888; flex-shrink: 0; }
.search-modal-input-row input {
	flex: 1; border: none; outline: none;
	font-family: inherit; font-size: 15px; color: #1a1a2e;
	background: none;
}
.search-modal-input-row input::placeholder { color: #bbb; }

.search-modal-esc {
	font-size: 10px; color: #aaa;
	background: #f5f5f8; border: 1px solid #e0e0ea;
	border-radius: 5px; padding: 2px 7px;
	cursor: pointer; font-family: inherit;
	flex-shrink: 0;
}

.search-section-label {
	font-size: 10.5px; font-weight: 700; color: #aaa;
	text-transform: uppercase; letter-spacing: .06em;
	padding: 10px 16px 4px;
}

.search-result-item {
	display: flex; align-items: center; gap: 10px;
	padding: 8px 16px; cursor: pointer;
	transition: background .1s;
}
.search-result-item:hover { background: #f4f6ff; }

.search-result-icon {
	width: 28px; height: 28px; border-radius: 7px;
	background: #f0f0f8;
	display: flex; align-items: center; justify-content: center;
	color: #888; font-size: 12px; flex-shrink: 0;
}
.search-result-text { font-size: 13px; color: #333; font-weight: 500; }
.search-result-sub { font-size: 11px; color: #aaa; }

.search-modal-footer {
	padding: 8px 16px; border-top: 1px solid #f0f0f5;
	display: flex; gap: 12px;
}
.search-hint {
	display: flex; align-items: center; gap: 4px;
	font-size: 11px; color: #bbb;
}
.search-hint kbd {
	font-size: 10px; background: #f5f5f8;
	border: 1px solid #e0e0ea; border-radius: 4px;
	padding: 1px 5px; font-family: inherit;
}
</style>
