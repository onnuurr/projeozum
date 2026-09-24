<script setup>
import { ref, computed, inject, watch } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import axios from 'axios'
import AppLayout from '@/Layouts/AppLayout.vue'
import {
	OzPageHeader, OzModuleTabs, OzStatTile, OzCard, OzButton, OzTable, OzPill, OzDropdown,
	OzDrawer, OzField, OzInput, OzSelect, OzTextarea, OzSwitch, useOzConfirm,
} from '@/Components/Oz'
import MaterialSpecsPanel from '../Components/MaterialSpecsPanel.vue'
import { formatMoney, formatNumber } from '../atelierNav'
import { useCan } from '@/composables/useCan'

defineOptions({ layout: AppLayout })

const props = defineProps({ materials: { type: Array, default: () => [] } })

const { can } = useCan()
const canManage = computed(() => can('atelier.material.manage'))
const showToast = inject('showToast', null)
const confirm = useOzConfirm()

const TYPES = { kumas: 'Kumaş', aksesuar: 'Aksesuar', etiket: 'Etiket' }
const UNITS = { metre: 'Metre', adet: 'Adet', kg: 'Kg' }
const typeOptions = Object.entries(TYPES).map(([value, label]) => ({ value, label }))
const unitOptions = Object.entries(UNITS).map(([value, label]) => ({ value, label }))
const MOVE_TYPES = [{ value: 'in', label: 'Giriş' }, { value: 'out', label: 'Çıkış' }, { value: 'adjust', label: 'Düzeltme' }]
const MOVE_REASONS = [
	{ value: 'purchase', label: 'Satın alma' },
	{ value: 'consume', label: 'Tüketim' },
	{ value: 'scrap', label: 'Fire' },
	{ value: 'correction', label: 'Düzeltme' },
]

/* ── özet ── */
const activeCount = computed(() => props.materials.filter((m) => m.isActive).length)
const outOfStock = computed(() => props.materials.filter((m) => m.isActive && m.currentStock <= 0).length)
const stockValue = computed(() => props.materials.reduce((sum, m) => sum + Math.max(0, m.currentStock) * m.unitCost, 0))
const stats = computed(() => [
	{ key: 'total', label: 'Toplam Hammadde', value: props.materials.length, icon: 'package', tone: 'accent' },
	{ key: 'active', label: 'Aktif', value: activeCount.value, icon: 'success', tone: 'success' },
	{ key: 'out', label: 'Stoğu Tükenen', value: outOfStock.value, icon: 'alert', tone: outOfStock.value ? 'danger' : 'success' },
	{ key: 'value', label: 'Stok Değeri', value: formatMoney(stockValue.value), icon: 'wallet', tone: 'info' },
])

/* ── tablo ── */
const columns = [
	{ key: 'code', label: 'Kod', sortable: true },
	{ key: 'name', label: 'Ad', sortable: true, class: 'oz-cell-strong' },
	{ key: 'type', label: 'Tür', sortable: true, filter: { type: 'select', options: typeOptions } },
	{ key: 'unit', label: 'Birim', sortable: true },
	{ key: 'currentStock', label: 'Stok', align: 'right', sortable: true },
	{ key: 'unitCost', label: 'Birim Maliyet', align: 'right', sortable: true, class: 'oz-cell-amount' },
	{ key: 'isActive', label: 'Durum', sortable: true },
	{ key: 'actions', label: '', align: 'right', width: '48px', searchable: false },
]

const rowActions = () => [
	{ key: 'specs', label: 'Özellikler (AI için)', icon: 'shirt' },
	{ key: 'move', label: 'Stok Hareketi', icon: 'refresh' },
	{ key: 'edit', label: 'Düzenle', icon: 'edit' },
	{ key: 'remove', label: 'Sil', icon: 'trash', danger: true },
]
function onAction(key, m) {
	if (key === 'specs') openSpecs(m)
	else if (key === 'move') openMove(m)
	else if (key === 'edit') openEdit(m)
	else if (key === 'remove') remove(m)
}

/* ── ekle / düzenle ── */
const emptyForm = () => ({ id: null, code: '', name: '', type: 'kumas', unit: 'metre', unit_cost: 0, is_active: true })
const form = useForm(emptyForm())
const editing = ref(false)
const formOpen = ref(false)

function resetForm() {
	editing.value = false
	form.defaults(emptyForm())
	form.reset()
	form.clearErrors()
}
watch(formOpen, (open) => { if (!open) setTimeout(resetForm, 250) })

function openCreate() {
	resetForm()
	formOpen.value = true
}
function openEdit(m) {
	resetForm()
	editing.value = true
	Object.assign(form, { id: m.id, code: m.code, name: m.name, type: m.type, unit: m.unit, unit_cost: m.unitCost, is_active: !!m.isActive })
	formOpen.value = true
}

function submit() {
	if (form.processing) return
	const opts = {
		preserveScroll: true,
		onSuccess: () => { formOpen.value = false },
		onError: (errs) => showToast?.({ type: 'error', title: 'Kayıt başarısız', message: Object.values(errs)[0] || 'Doğrulama hatası.' }),
	}
	editing.value ? form.put(`/atelier/materials/${form.id}`, opts) : form.post('/atelier/materials', opts)
}

async function remove(m) {
	const ok = await confirm.danger('Hammaddeyi Sil', `${m.name} kalıcı olarak silinecek.`)
	if (ok) router.delete(`/atelier/materials/${m.id}`, { preserveScroll: true })
}

/* ── stok hareketi ── */
const moveOpen = ref(false)
const moveMaterial = ref(null)
const move = useForm({ material_id: null, type: 'in', quantity: 0, reason: 'purchase', note: '' })
function openMove(m) {
	moveMaterial.value = m
	move.clearErrors()
	Object.assign(move, { material_id: m.id, type: 'in', quantity: 0, reason: 'purchase', note: '' })
	moveOpen.value = true
}
function submitMove() {
	if (move.processing) return
	move.post('/atelier/materials/movement', {
		preserveScroll: true,
		onSuccess: () => { moveOpen.value = false },
		onError: (errs) => showToast?.({ type: 'error', title: 'Hareket kaydedilemedi', message: Object.values(errs)[0] || 'Doğrulama hatası.' }),
	})
}

/* ── özellikler (AI ürün açıklaması için) ── */
const specsOpen = ref(false)
const specs = ref({ material_id: null, material_name: '', data: {} })
const specsBusy = ref(false)
function openSpecs(m) {
	specs.value = { material_id: m.id, material_name: m.name, data: { ...(m.specs || {}) } }
	specsOpen.value = true
}
async function submitSpecs() {
	if (!specs.value.material_id || specsBusy.value) return
	specsBusy.value = true
	try {
		await axios.put(`/atelier/materials/${specs.value.material_id}/specs`, { specs: specs.value.data })
		showToast?.({ type: 'success', title: 'Özellikler kaydedildi', message: specs.value.material_name })
		specsOpen.value = false
		router.reload({ only: ['materials'], preserveScroll: true })
	} catch (e) {
		showToast?.({ type: 'error', title: 'Kayıt başarısız', message: e?.response?.data?.errors?.specs?.[0] || 'Sunucu hatası.' })
	} finally {
		specsBusy.value = false
	}
}
</script>

<template>
	<Head title="Hammaddeler" />

	<OzPageHeader title="Hammaddeler">
		<template #subtitle><b>{{ materials.length }}</b> hammadde tanımı</template>
		<template #actions>
			<OzButton v-if="canManage" variant="primary" icon="plus" @click="openCreate">Yeni Hammadde</OzButton>
		</template>
	</OzPageHeader>
	<OzModuleTabs />

	<div class="stats">
		<OzStatTile v-for="s in stats" :key="s.key" :label="s.label" :value="s.value" :icon="s.icon" :tone="s.tone" />
	</div>

	<OzCard title="Hammadde Listesi">
		<OzTable :columns="columns" :rows="materials" row-key="id" searchable search-placeholder="Kod ya da ad ara…" empty-title="Hammadde kaydı yok" min-width="720px">
			<template #cell-code="{ value }"><span class="mono">{{ value }}</span></template>
			<template #cell-type="{ value }"><OzPill tone="info">{{ TYPES[value] ?? value }}</OzPill></template>
			<template #cell-unit="{ value }"><span class="dim">{{ UNITS[value] ?? value }}</span></template>
			<template #cell-currentStock="{ row }">
				<span class="mono" :class="{ 'is-out': row.currentStock <= 0 }">{{ formatNumber(row.currentStock, 3) }}</span>
			</template>
			<template #cell-unitCost="{ value }"><span class="mono">{{ formatMoney(value) }}</span></template>
			<template #cell-isActive="{ value }">
				<OzPill :tone="value ? 'success' : 'neutral'">{{ value ? 'Aktif' : 'Pasif' }}</OzPill>
			</template>
			<template v-if="canManage" #cell-actions="{ row }">
				<OzDropdown :items="rowActions()" :label="`${row.name} işlemleri`" @select="(k) => onAction(k, row)" />
			</template>
		</OzTable>
	</OzCard>

	<!-- Ekle / düzenle -->
	<OzDrawer v-model:open="formOpen" :title="editing ? 'Hammaddeyi Düzenle' : 'Yeni Hammadde'" subtitle="Kod, tür, birim ve maliyet bilgileri" :icon="editing ? 'edit' : 'plus'" size="md" :persistent="form.processing">
		<form class="form" @submit.prevent="submit">
			<div class="row-2">
				<OzField label="Kod" required :error="form.errors.code">
					<OzInput v-model="form.code" class="mono" placeholder="KM-001" />
				</OzField>
				<OzField label="Ad" required :error="form.errors.name">
					<OzInput v-model="form.name" placeholder="Hammadde adı" />
				</OzField>
			</div>
			<div class="row-2">
				<OzField label="Tür" :error="form.errors.type">
					<OzSelect v-model="form.type" :options="typeOptions" />
				</OzField>
				<OzField label="Birim" :error="form.errors.unit">
					<OzSelect v-model="form.unit" :options="unitOptions" />
				</OzField>
			</div>
			<div class="row-2">
				<OzField label="Birim Maliyet" :error="form.errors.unit_cost">
					<OzInput v-model.number="form.unit_cost" type="number" min="0" step="0.01" suffix="₺" placeholder="0,00" />
				</OzField>
				<OzField label="Durum">
					<OzSwitch v-model="form.is_active" label="Aktif" />
				</OzField>
			</div>
			<button type="submit" hidden />
		</form>
		<template #footer>
			<OzButton :disabled="form.processing" @click="formOpen = false">İptal</OzButton>
			<OzButton variant="primary" :icon="editing ? 'check' : 'plus'" :loading="form.processing" @click="submit">{{ editing ? 'Kaydet' : 'Ekle' }}</OzButton>
		</template>
	</OzDrawer>

	<!-- Stok hareketi -->
	<OzDrawer v-model:open="moveOpen" title="Stok Hareketi" :subtitle="moveMaterial ? `${moveMaterial.name} · mevcut ${formatNumber(moveMaterial.currentStock, 3)} ${UNITS[moveMaterial.unit] ?? moveMaterial.unit}` : ''" icon="refresh" size="sm" :persistent="move.processing">
		<form class="form" @submit.prevent="submitMove">
			<OzField label="Tip" required :error="move.errors.type">
				<OzSelect v-model="move.type" :options="MOVE_TYPES" />
			</OzField>
			<OzField label="Miktar" required :error="move.errors.quantity" :hint="move.type === 'adjust' ? 'Düzeltmede artı ya da eksi değer girilebilir.' : ''">
				<OzInput v-model.number="move.quantity" type="number" step="0.001" :suffix="moveMaterial ? (UNITS[moveMaterial.unit] ?? moveMaterial.unit) : ''" />
			</OzField>
			<OzField label="Sebep" required :error="move.errors.reason">
				<OzSelect v-model="move.reason" :options="MOVE_REASONS" />
			</OzField>
			<OzField label="Not" :error="move.errors.note">
				<OzTextarea v-model="move.note" :rows="2" placeholder="İrsaliye no, açıklama…" />
			</OzField>
			<button type="submit" hidden />
		</form>
		<template #footer>
			<OzButton :disabled="move.processing" @click="moveOpen = false">Vazgeç</OzButton>
			<OzButton variant="primary" icon="check" :loading="move.processing" @click="submitMove">Kaydet</OzButton>
		</template>
	</OzDrawer>

	<!-- Materyal özellikleri -->
	<OzDrawer v-model:open="specsOpen" title="Materyal Özellikleri" :subtitle="specs.material_name" icon="shirt" size="lg" :persistent="specsBusy">
		<p class="drawer-desc">AI ürün açıklaması bu bilgilerden üretilir. Kumaş için kompozisyon ve gramaj yeterli.</p>
		<MaterialSpecsPanel v-model="specs.data" />
		<template #footer>
			<OzButton :disabled="specsBusy" @click="specsOpen = false">Vazgeç</OzButton>
			<OzButton variant="primary" icon="check" :loading="specsBusy" @click="submitSpecs">Kaydet</OzButton>
		</template>
	</OzDrawer>
</template>

<style scoped>
.stats { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; margin-bottom: 14px; }
@media (max-width: 1040px) { .stats { grid-template-columns: repeat(2, minmax(0, 1fr)); } }

.mono { font-family: ui-monospace, monospace; font-size: 12px; }
.dim { color: var(--oz-muted); }
.is-out { color: var(--oz-danger); font-weight: 700; }

.form { display: flex; flex-direction: column; gap: 14px; }
.row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.drawer-desc { font-size: 12.5px; color: var(--oz-muted); margin: 0 0 14px; }
@media (max-width: 560px) {
	.row-2 { grid-template-columns: 1fr; }
}
</style>
