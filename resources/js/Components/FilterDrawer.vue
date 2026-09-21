<template>
	<AppDrawer
		:model-value="modelValue"
		title="Gelişmiş Filtreler"
		subtitle="İş emirlerini filtrele ve sırala"
		width="360px"
		@update:model-value="$emit('update:modelValue', $event)"
	>
		<template #icon>
			<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
				<line x1="4" y1="6" x2="20" y2="6" />
				<line x1="8" y1="12" x2="16" y2="12" />
				<line x1="11" y1="18" x2="13" y2="18" />
			</svg>
		</template>

		<div>
			<div class="drawer-section-title">Durum</div>
			<div class="drawer-chip-row" style="margin-top: 10px">
				<span
					v-for="d in statuses"
					:key="d"
					class="drawer-chip"
					:class="{ active: filters.status.includes(d) }"
					@click="toggleChip('status', d)"
				>{{ d }}</span>
			</div>
		</div>

		<div>
			<div class="drawer-section-title">Öncelik</div>
			<div class="drawer-chip-row" style="margin-top: 10px">
				<span
					v-for="p in priorities"
					:key="p"
					class="drawer-chip"
					:class="{ active: filters.priority.includes(p) }"
					@click="toggleChip('priority', p)"
				>{{ p }}</span>
			</div>
		</div>

		<div>
			<div class="drawer-section-title">Tarih Aralığı</div>
			<div class="drawer-range-row" style="margin-top: 10px">
				<input v-model="filters.dateStart" class="form-input" type="text" placeholder="Başlangıç" style="font-size: 12.5px" />
				<span class="drawer-range-sep">—</span>
				<input v-model="filters.dateEnd" class="form-input" type="text" placeholder="Bitiş" style="font-size: 12.5px" />
			</div>
		</div>

		<div>
			<div class="drawer-section-title">Kumaş Türü</div>
			<div class="drawer-filter-group" style="margin-top: 10px">
				<label v-for="k in fabrics" :key="k" class="drawer-check-row">
					<input type="checkbox" :checked="filters.fabrics.includes(k)" @change="toggleChip('fabrics', k)" />
					{{ k }}
				</label>
			</div>
		</div>

		<div>
			<div class="drawer-section-title">Sıralama</div>
			<div class="drawer-chip-row" style="margin-top: 10px">
				<span
					v-for="s in sorts"
					:key="s"
					class="drawer-chip"
					:class="{ active: filters.sort === s }"
					@click="filters.sort = s"
				>{{ s }}</span>
			</div>
		</div>

		<template #footer>
			<Button variant="ghost" @click="reset">Sıfırla</Button>
			<Button variant="primary" with-icon @click="apply">
				<template #leading><Check :size="13" /></template>
				Uygula
				<template #trailing><span class="drawer-result-badge">{{ activeCount }}</span></template>
			</Button>
		</template>
	</AppDrawer>
</template>

<script setup>
import { reactive, computed } from 'vue'
import { Check } from 'lucide-vue-next'
import Button from './Button.vue'
import AppDrawer from './AppDrawer.vue'

defineProps({
	modelValue: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'apply', 'reset'])

const statuses = ['Tümü', 'Beklemede', 'Üretimde', 'Tamamlandı', 'İptal']
const priorities = ['Düşük', 'Orta', 'Yüksek', 'Kritik']
const fabrics = ['Pamuk', 'Polyester', 'İpek', 'Yün', 'Keten']
const sorts = ['Tarihe Göre ↑', 'Tarihe Göre ↓', 'Önceliğe Göre', 'Duruma Göre']

const filters = reactive({
	status: ['Tümü'],
	priority: ['Orta', 'Yüksek'],
	fabrics: ['Pamuk', 'Polyester'],
	sort: 'Tarihe Göre ↑',
	dateStart: '01.04.2024',
	dateEnd: '30.04.2024',
})

const activeCount = computed(() => {
	return filters.status.length + filters.priority.length
})

function toggleChip(key, value) {
	const arr = filters[key]
	const idx = arr.indexOf(value)
	if (idx >= 0) arr.splice(idx, 1)
	else arr.push(value)
}

function reset() {
	filters.status = []
	filters.priority = []
	filters.fabrics = []
	emit('reset')
}

function apply() {
	emit('apply', { ...filters, activeCount: activeCount.value })
	close()
}

function close() {
	emit('update:modelValue', false)
}
</script>

<style scoped>
.drawer-check-row {
	display: flex; align-items: center; gap: 8px;
	font-size: 13px; color: #444; cursor: pointer;
}
.drawer-check-row input {
	accent-color: rgb(var(--color-primary-rgb)); width: 14px; height: 14px;
}

.drawer-section-title {
	font-size: 11px; font-weight: 600; color: #9898b0;
	text-transform: uppercase; letter-spacing: .06em;
	margin-bottom: -6px;
}
.drawer-filter-group { display: flex; flex-direction: column; gap: 6px; }
.drawer-chip-row { display: flex; flex-wrap: wrap; gap: 6px; }
.drawer-chip {
	padding: 5px 12px; border-radius: 999px;
	border: 1.5px solid #e8e8f2; background: #fafafa;
	font-size: 12.5px; color: #555; cursor: pointer;
	transition: all .15s; user-select: none;
}
.drawer-chip:hover { border-color: rgb(var(--color-primary-rgb) / .5); color: rgb(var(--color-primary-rgb)); background: rgb(var(--color-primary-soft-rgb)); }
.drawer-chip.active { border-color: rgb(var(--color-primary-rgb)); background: rgb(var(--color-primary-soft-rgb)); color: rgb(var(--color-primary-hover-rgb)); font-weight: 500; }
.drawer-range-row { display: flex; align-items: center; gap: 8px; }
.drawer-range-row .form-input { flex: 1; }
.drawer-range-sep { font-size: 12px; color: #bbb; flex-shrink: 0; }
.drawer-result-badge {
	display: inline-flex; align-items: center; justify-content: center;
	background: rgb(var(--color-primary-rgb)); color: #fff;
	font-size: 11px; font-weight: 600;
	border-radius: 999px; padding: 1px 7px;
	margin-left: 6px; line-height: 1.6;
}
</style>
