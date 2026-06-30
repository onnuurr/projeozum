<template>
	<Teleport to="body">
		<div class="drawer-overlay" :class="{ open: modelValue }" @click="close"></div>
		<div class="drawer" :class="{ open: modelValue }" role="dialog" aria-modal="true">
			<div class="drawer-header">
				<div class="drawer-title">
					<div class="drawer-title-icon">
						<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
							<line x1="4" y1="6" x2="20" y2="6" />
							<line x1="8" y1="12" x2="16" y2="12" />
							<line x1="11" y1="18" x2="13" y2="18" />
						</svg>
					</div>
					<div>
						<h4>Gelişmiş Filtreler</h4>
						<p>İş emirlerini filtrele ve sırala</p>
					</div>
				</div>
				<button class="drawer-close" @click="close" aria-label="Kapat">
					<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
						<path d="M18 6L6 18M6 6l12 12" />
					</svg>
				</button>
			</div>

			<div class="drawer-body">
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
			</div>

			<div class="drawer-footer">
				<button class="btn btn-ghost" @click="reset">Sıfırla</button>
				<button class="btn btn-primary btn-with-icon" @click="apply">
					<svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
						<path d="M20 6L9 17l-5-5" />
					</svg>
					Uygula
					<span class="drawer-result-badge">{{ activeCount }}</span>
				</button>
			</div>
		</div>
	</Teleport>
</template>

<script setup>
import { reactive, computed, watch } from 'vue'

const props = defineProps({
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

watch(
	() => props.modelValue,
	(open) => {
		document.body.style.overflow = open ? 'hidden' : ''
	}
)
</script>

<style scoped>
.drawer-check-row {
	display: flex; align-items: center; gap: 8px;
	font-size: 13px; color: #444; cursor: pointer;
}
.drawer-check-row input {
	accent-color: rgb(var(--color-primary)); width: 14px; height: 14px;
}
</style>

<style>
.drawer-overlay {
	position: fixed; inset: 0; z-index: 9000;
	background: rgba(20, 20, 40, .22);
	backdrop-filter: blur(5px);
	-webkit-backdrop-filter: blur(5px);
	opacity: 0; pointer-events: none;
	transition: opacity .22s ease;
}
.drawer-overlay.open { opacity: 1; pointer-events: all; }

.drawer {
	position: fixed;
	top: 12px; right: 12px; bottom: 12px;
	width: 360px;
	background: #fff;
	border-radius: 18px;
	box-shadow:
		0 24px 64px rgba(0, 0, 0, .16),
		0 6px 20px rgba(0, 0, 0, .09),
		0 0 0 1px rgba(0, 0, 0, .04);
	z-index: 9001;
	display: flex; flex-direction: column;
	transform: translateX(calc(100% + 24px));
	transition: transform .32s cubic-bezier(.34, 1.28, .64, 1);
	overflow: hidden;
}
.drawer.open { transform: translateX(0); }

.drawer-header {
	display: flex; align-items: center; justify-content: space-between;
	padding: 18px 20px 16px;
	border-bottom: 1px solid #f0f0f6;
	flex-shrink: 0;
}
.drawer-title { display: flex; align-items: center; gap: 10px; }
.drawer-title-icon {
	width: 34px; height: 34px; border-radius: 9px;
	background: #f0fdf4; color: #16a34a;
	display: flex; align-items: center; justify-content: center;
	flex-shrink: 0;
}
.drawer-title h4 { font-size: 14.5px; font-weight: 600; color: #1a1a2e; }
.drawer-title p { font-size: 12px; color: #9898b0; margin-top: 1px; }

.drawer-close {
	width: 30px; height: 30px; border-radius: 8px; border: none;
	background: #f5f5f8; color: #888; cursor: pointer;
	display: flex; align-items: center; justify-content: center;
	transition: background .15s, color .15s; flex-shrink: 0;
}
.drawer-close:hover { background: #fee2e2; color: #ef4444; }

.drawer-body {
	flex: 1; overflow-y: auto; padding: 18px 20px;
	display: flex; flex-direction: column; gap: 16px;
}
.drawer-body::-webkit-scrollbar { width: 4px; }
.drawer-body::-webkit-scrollbar-track { background: transparent; }
.drawer-body::-webkit-scrollbar-thumb { background: #ddd; border-radius: 4px; }

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
.drawer-chip:hover { border-color: rgb(var(--color-primary) / .5); color: rgb(var(--color-primary)); background: rgb(var(--color-primary-soft)); }
.drawer-chip.active { border-color: rgb(var(--color-primary)); background: rgb(var(--color-primary-soft)); color: rgb(var(--color-primary-hover)); font-weight: 500; }
.drawer-range-row { display: flex; align-items: center; gap: 8px; }
.drawer-range-row .form-input { flex: 1; }
.drawer-range-sep { font-size: 12px; color: #bbb; flex-shrink: 0; }
.drawer-footer {
	padding: 14px 20px;
	border-top: 1px solid #f0f0f6;
	display: flex; gap: 8px; flex-shrink: 0;
}
.drawer-footer .btn { flex: 1; justify-content: center; }
.drawer-result-badge {
	display: inline-flex; align-items: center; justify-content: center;
	background: rgb(var(--color-primary)); color: #fff;
	font-size: 11px; font-weight: 600;
	border-radius: 999px; padding: 1px 7px;
	margin-left: 6px; line-height: 1.6;
}
</style>
