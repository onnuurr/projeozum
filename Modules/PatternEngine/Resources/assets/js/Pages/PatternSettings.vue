<script setup>
import { ref, computed, watch, inject } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import {
	OzPageHeader, OzModuleTabs, OzTabs, OzCard, OzTable, OzButton, OzPill, OzDrawer, OzField, OzInput, OzSelect, OzSwitch, OzCheckbox, useOzConfirm,
} from '@/Components/Oz'

defineOptions({ layout: AppLayout })

// Kalıp Ayarları (çark; atelier.pattern.settings): kalıp tipleri ve her tipin ölçü şablonu,
// ölçü noktası sözlüğü. Kalıp Kartı'nın ölçü tablosu satırları buradan gelir.
const props = defineProps({
	tab: { type: String, default: null },
	types: { type: Array, default: () => [] },
	points: { type: Array, default: () => [] },
})

const showToast = inject('showToast', null)
const confirm = useOzConfirm()
const errorToast = (errs) => showToast?.({ type: 'error', title: 'Kayıt başarısız', message: Object.values(errs)[0] || 'Doğrulama hatası.' })

const BASIS = { body: 'Vücut', garment: 'Hazır giysi' }
const basisOptions = Object.entries(BASIS).map(([value, label]) => ({ value, label }))

const tabs = computed(() => [
	{ key: 'types', label: 'Kalıp Tipleri', icon: 'shirt', count: props.types.length },
	{ key: 'points', label: 'Ölçü Noktaları', icon: 'rows', count: props.points.length },
])
const activeTab = ref(['types', 'points'].includes(props.tab) ? props.tab : 'types')
watch(activeTab, (key) => {
	const url = new URL(window.location.href)
	url.searchParams.set('tab', key)
	window.history.replaceState(window.history.state, '', url)
})

/* ── toplu silme (sırayla; kullanımdaki kayıt silinmez, pasife alınır) ── */
function bulkDelete(urls, done) {
	const next = () => {
		const url = urls.shift()
		if (!url) { done(); return }
		router.delete(url, { preserveScroll: true, preserveState: true, onError: errorToast, onFinish: next })
	}
	next()
}

/* ══════════ KALIP TİPLERİ ══════════ */
const typeColumns = [
	{ key: 'name', label: 'Kalıp tipi', sortable: true },
	{ key: 'points', label: 'Ölçü şablonu', searchable: false },
	{ key: 'pattern_count', label: 'Kalıp', width: '90px', sortable: true },
	{ key: 'is_active', label: 'Durum', width: '100px' },
	{ key: 'actions', label: '', width: '100px', searchable: false },
]
const pointName = computed(() => Object.fromEntries(props.points.map((p) => [p.id, p])))
const typeSelected = ref([])
const typeOpen = ref(false)
const typeBusy = ref(false)
const typeForm = ref({})

function openType(t = null) {
	typeForm.value = t
		? { id: t.id, name: t.name, is_active: t.is_active, sort_order: t.sort_order, point_ids: [...t.point_ids] }
		: { id: null, name: '', is_active: true, sort_order: (props.types.length + 1) * 10, point_ids: [] }
	typeOpen.value = true
}
function togglePoint(id, on) {
	const ids = typeForm.value.point_ids.filter((x) => x !== id)
	if (on) ids.push(id)
	// Şablon sırası = sözlük sırası (Ölçü Noktaları sekmesindeki sıra).
	typeForm.value.point_ids = props.points.filter((p) => ids.includes(p.id)).map((p) => p.id)
}
function saveType() {
	if (typeBusy.value) return
	typeBusy.value = true
	const f = typeForm.value
	const url = f.id ? `/atelier/patterns/settings/types/${f.id}` : '/atelier/patterns/settings/types'
	router[f.id ? 'put' : 'post'](url, f, {
		preserveScroll: true,
		onSuccess: () => { typeOpen.value = false },
		onError: errorToast,
		onFinish: () => { typeBusy.value = false },
	})
}
async function deleteType(t) {
	const ok = await confirm.danger('Kalıp tipini sil', t.pattern_count
		? `${t.name} tipine bağlı ${t.pattern_count} kalıp var; silinmez, pasife alınır.`
		: `${t.name} tipi ve ölçü şablonu kaldırılacak.`)
	if (ok) router.delete(`/atelier/patterns/settings/types/${t.id}`, { preserveScroll: true, onError: errorToast })
}
async function bulkDeleteTypes() {
	const ok = await confirm.danger('Seçilen tipleri sil', `${typeSelected.value.length} tip silinecek (kullanımdakiler pasife alınır).`)
	if (ok) bulkDelete(typeSelected.value.map((id) => `/atelier/patterns/settings/types/${id}`), () => { typeSelected.value = [] })
}

/* ══════════ ÖLÇÜ NOKTALARI ══════════ */
const pointColumns = [
	{ key: 'name', label: 'Ölçü', sortable: true },
	{ key: 'key', label: 'Anahtar', sortable: true },
	{ key: 'basis', label: 'Tür', width: '120px', sortable: true },
	{ key: 'unit', label: 'Birim', width: '80px' },
	{ key: 'is_active', label: 'Durum', width: '100px' },
	{ key: 'actions', label: '', width: '100px', searchable: false },
]
const pointSelected = ref([])
const pointOpen = ref(false)
const pointBusy = ref(false)
const pointForm = ref({})

function openPoint(p = null) {
	pointForm.value = p
		? { ...p }
		: { id: null, key: '', name: '', hint: '', basis: 'garment', unit: 'cm', sort_order: (props.points.length + 1) * 10, is_active: true }
	pointOpen.value = true
}
function savePoint() {
	if (pointBusy.value) return
	pointBusy.value = true
	const f = pointForm.value
	const url = f.id ? `/atelier/patterns/settings/points/${f.id}` : '/atelier/patterns/settings/points'
	router[f.id ? 'put' : 'post'](url, f, {
		preserveScroll: true,
		onSuccess: () => { pointOpen.value = false },
		onError: errorToast,
		onFinish: () => { pointBusy.value = false },
	})
}
async function deletePoint(p) {
	const ok = await confirm.danger('Ölçü noktasını sil', `${p.name} silinecek. Değer girilmiş kalıplar varsa silinmez, pasife alınır.`)
	if (ok) router.delete(`/atelier/patterns/settings/points/${p.id}`, { preserveScroll: true, onError: errorToast })
}
async function bulkDeletePoints() {
	const ok = await confirm.danger('Seçilen ölçüleri sil', `${pointSelected.value.length} ölçü noktası silinecek (değer girilmiş olanlar pasife alınır).`)
	if (ok) bulkDelete(pointSelected.value.map((id) => `/atelier/patterns/settings/points/${id}`), () => { pointSelected.value = [] })
}
const pointsByBasis = computed(() => ({
	body: props.points.filter((p) => p.basis === 'body'),
	garment: props.points.filter((p) => p.basis === 'garment'),
}))
</script>

<template>
	<Head title="Kalıp Ayarları" />

	<OzPageHeader title="Kalıp Ayarları">
		<template #subtitle>Kalıp tipleri ve ölçü şablonları — Kalıp Kartı'ndaki ölçü tablolarının satırları buradan gelir</template>
	</OzPageHeader>
	<OzModuleTabs />

	<OzTabs v-model="activeTab" :tabs="tabs" aria-label="Kalıp ayarları" class="block" />

	<OzCard v-if="activeTab === 'types'" title="Kalıp tipleri" subtitle="Her tip kendi vücut + hazır giysi ölçü şablonunu taşır">
		<template #actions>
			<OzButton variant="primary" size="sm" icon="plus" @click="openType()">Yeni tip</OzButton>
		</template>
		<OzTable v-model:selected="typeSelected" :columns="typeColumns" :rows="types" row-key="id" selectable item-label="tip"
			empty-title="Kalıp tipi yok" searchable search-placeholder="Tip ara…">
			<template #bulk>
				<OzButton size="sm" variant="danger" icon="trash" @click="bulkDeleteTypes">Sil ({{ typeSelected.length }})</OzButton>
			</template>
			<template #cell-points="{ row }">
				<span class="dim">
					{{ row.point_ids.filter((id) => pointName[id]?.basis === 'body').length }} vücut ·
					{{ row.point_ids.filter((id) => pointName[id]?.basis === 'garment').length }} hazır giysi
				</span>
			</template>
			<template #cell-is_active="{ row }">
				<OzPill :tone="row.is_active ? 'success' : 'neutral'">{{ row.is_active ? 'Aktif' : 'Pasif' }}</OzPill>
			</template>
			<template #cell-actions="{ row }">
				<div class="row-actions">
					<OzButton size="sm" icon="edit" :aria-label="`${row.name} düzenle`" @click="openType(row)" />
					<OzButton size="sm" icon="trash" :aria-label="`${row.name} sil`" @click="deleteType(row)" />
				</div>
			</template>
		</OzTable>
	</OzCard>

	<OzCard v-else title="Ölçü noktaları" subtitle="Vücut ölçüleri PDF beden tablolarına, hazır giysi ölçüleri üretim kontrolüne">
		<template #actions>
			<OzButton variant="primary" size="sm" icon="plus" @click="openPoint()">Yeni ölçü</OzButton>
		</template>
		<OzTable v-model:selected="pointSelected" :columns="pointColumns" :rows="points" row-key="id" selectable item-label="ölçü"
			empty-title="Ölçü noktası yok" searchable search-placeholder="Ölçü ara…">
			<template #bulk>
				<OzButton size="sm" variant="danger" icon="trash" @click="bulkDeletePoints">Sil ({{ pointSelected.length }})</OzButton>
			</template>
			<template #cell-key="{ row }"><span class="mono">{{ row.key }}</span></template>
			<template #cell-basis="{ row }">
				<OzPill :tone="row.basis === 'body' ? 'info' : 'accent'" :dot="false">{{ BASIS[row.basis] }}</OzPill>
			</template>
			<template #cell-is_active="{ row }">
				<OzPill :tone="row.is_active ? 'success' : 'neutral'">{{ row.is_active ? 'Aktif' : 'Pasif' }}</OzPill>
			</template>
			<template #cell-actions="{ row }">
				<div class="row-actions">
					<OzButton size="sm" icon="edit" :aria-label="`${row.name} düzenle`" @click="openPoint(row)" />
					<OzButton size="sm" icon="trash" :aria-label="`${row.name} sil`" @click="deletePoint(row)" />
				</div>
			</template>
		</OzTable>
	</OzCard>

	<!-- Kalıp tipi çekmecesi -->
	<OzDrawer v-model:open="typeOpen" :title="typeForm.id ? 'Kalıp tipini düzenle' : 'Yeni kalıp tipi'" subtitle="Seçilen ölçüler bu tipteki kalıpların ölçü tablosunda satır olur"
		icon="shirt" size="lg" :persistent="typeBusy">
		<form class="form" @submit.prevent="saveType">
			<div class="form-row">
				<OzField label="Ad" required><OzInput v-model="typeForm.name" placeholder="sweatshirt, pantolon…" /></OzField>
				<OzField label="Sıra"><OzInput v-model="typeForm.sort_order" type="number" /></OzField>
			</div>
			<OzSwitch v-model="typeForm.is_active" label="Aktif" description="Pasif tip yeni kalıplarda seçilemez" />
			<div v-for="basis in ['body', 'garment']" :key="basis" class="point-group">
				<h4>{{ BASIS[basis] }} ölçüleri</h4>
				<div class="point-grid">
					<OzCheckbox v-for="p in pointsByBasis[basis]" :key="p.id" :label="p.name" :description="p.hint || ''"
						:model-value="typeForm.point_ids?.includes(p.id)" @update:model-value="togglePoint(p.id, $event)" />
				</div>
			</div>
		</form>
		<template #footer>
			<OzButton :disabled="typeBusy" @click="typeOpen = false">Vazgeç</OzButton>
			<OzButton variant="primary" icon="check" :loading="typeBusy" @click="saveType">Kaydet</OzButton>
		</template>
	</OzDrawer>

	<!-- Ölçü noktası çekmecesi -->
	<OzDrawer v-model:open="pointOpen" :title="pointForm.id ? 'Ölçüyü düzenle' : 'Yeni ölçü noktası'" icon="rows" size="md" :persistent="pointBusy">
		<form class="form" @submit.prevent="savePoint">
			<OzField label="Ad" required><OzInput v-model="pointForm.name" placeholder="Göğüs Eni (½)" /></OzField>
			<OzField label="Anahtar" required hint="Küçük harf + alt çizgi; PDF eşlemesi bu anahtarı kullanır">
				<OzInput v-model="pointForm.key" placeholder="g_chest_half" />
			</OzField>
			<OzField label="Nasıl ölçülür"><OzInput v-model="pointForm.hint" placeholder="Koltuk altının 2 cm altından…" /></OzField>
			<div class="form-row">
				<OzField label="Tür" required><OzSelect v-model="pointForm.basis" :options="basisOptions" /></OzField>
				<OzField label="Birim"><OzInput v-model="pointForm.unit" /></OzField>
				<OzField label="Sıra"><OzInput v-model="pointForm.sort_order" type="number" /></OzField>
			</div>
			<OzSwitch v-model="pointForm.is_active" label="Aktif" />
		</form>
		<template #footer>
			<OzButton :disabled="pointBusy" @click="pointOpen = false">Vazgeç</OzButton>
			<OzButton variant="primary" icon="check" :loading="pointBusy" @click="savePoint">Kaydet</OzButton>
		</template>
	</OzDrawer>
</template>

<style scoped>
.block { margin-bottom: 14px; }
.dim { color: var(--oz-muted); font-size: 12.5px; }
.mono { font-family: ui-monospace, monospace; font-size: 12px; }
.row-actions { display: flex; gap: 4px; justify-content: flex-end; }
.form { display: flex; flex-direction: column; gap: 14px; }
.form-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 12px; }
.point-group h4 { margin: 6px 0 8px; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: var(--oz-muted); }
.point-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 8px 14px; }
</style>
