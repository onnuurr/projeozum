<script setup>
import { ref, computed, inject, watch } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import {
	OzPageHeader, OzModuleTabs, OzStatStrip, OzCard, OzButton, OzTable, OzPill, OzDropdown,
	OzDrawer, OzField, OzInput, OzRadioGroup, OzSwitch, useOzConfirm,
} from '@/Components/Oz'
import { formatMoney } from '../atelierNav'
import { useCan } from '@/composables/useCan'

defineOptions({ layout: AppLayout })

const props = defineProps({ operations: { type: Array, default: () => [] } })

const { can } = useCan()
const canManage = computed(() => can('atelier.operation.manage'))
const showToast = inject('showToast', null)
const confirm = useOzConfirm()

const LOCATIONS = { in_house: 'İç Atölye', fason: 'Fason' }
const locationOptions = [
	{ value: 'in_house', label: 'İç atölye', description: 'Kendi atölyemizde yapılır' },
	{ value: 'fason', label: 'Fason', description: 'Dış fasoncuya gönderilir' },
]

const inHouse = computed(() => props.operations.filter((o) => o.defaultLocation === 'in_house').length)
const avgCost = computed(() => props.operations.length
	? props.operations.reduce((s, o) => s + o.defaultUnitCost, 0) / props.operations.length
	: 0)
const stats = computed(() => [
	{ key: 'total', label: 'Toplam Operasyon', value: props.operations.length, icon: 'workflow', tone: 'accent' },
	{ key: 'inHouse', label: 'İç Atölye', value: inHouse.value, icon: 'scissors', tone: 'success' },
	{ key: 'fason', label: 'Fason', value: props.operations.length - inHouse.value, icon: 'factory', tone: 'warning' },
	{ key: 'avg', label: 'Ort. Birim İşçilik', value: formatMoney(avgCost.value), icon: 'coins', tone: 'info' },
])

const columns = [
	{ key: 'sortOrder', label: 'Sıra', align: 'right', sortable: true, width: '64px' },
	{ key: 'code', label: 'Kod', sortable: true },
	{ key: 'name', label: 'Ad', sortable: true, class: 'oz-cell-strong' },
	{ key: 'defaultLocation', label: 'Varsayılan Yer', sortable: true, filter: { type: 'select', options: [{ value: 'in_house', label: 'İç Atölye' }, { value: 'fason', label: 'Fason' }] } },
	{ key: 'defaultUnitCost', label: 'Birim İşçilik', align: 'right', sortable: true, class: 'oz-cell-amount' },
	{ key: 'actions', label: '', align: 'right', width: '48px', searchable: false },
]

const rowActions = () => [
	{ key: 'edit', label: 'Düzenle', icon: 'edit' },
	{ key: 'remove', label: 'Sil', icon: 'trash', danger: true },
]
function onAction(key, o) {
	if (key === 'edit') openEdit(o)
	else if (key === 'remove') remove(o)
}

const emptyForm = () => ({ id: null, code: '', name: '', default_location: 'in_house', default_unit_cost: 0, consumes_materials: false, sort_order: 0 })
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
	// yeni operasyon listenin sonuna eklensin
	form.sort_order = props.operations.reduce((m, o) => Math.max(m, o.sortOrder ?? 0), 0) + 1
	formOpen.value = true
}
function openEdit(o) {
	resetForm()
	editing.value = true
	Object.assign(form, {
		id: o.id, code: o.code, name: o.name,
		default_location: o.defaultLocation, default_unit_cost: o.defaultUnitCost, consumes_materials: o.consumesMaterials, sort_order: o.sortOrder,
	})
	formOpen.value = true
}

function submit() {
	if (form.processing) return
	const opts = {
		preserveScroll: true,
		onSuccess: () => { formOpen.value = false },
		onError: (errs) => showToast?.({ type: 'error', title: 'Kayıt başarısız', message: Object.values(errs)[0] || 'Doğrulama hatası.' }),
	}
	editing.value ? form.put(`/atelier/operations/${form.id}`, opts) : form.post('/atelier/operations', opts)
}

async function remove(o) {
	const ok = await confirm.danger('Operasyonu Sil', `${o.name} operasyonu kalıcı olarak silinecek.`)
	if (ok) router.delete(`/atelier/operations/${o.id}`, { preserveScroll: true })
}
</script>

<template>
	<Head title="Operasyonlar" />

	<OzPageHeader title="Operasyonlar">
		<template #subtitle><b>{{ operations.length }}</b> operasyon tanımı · iş emri rotalarında kullanılır</template>
		<template #actions>
			<OzButton v-if="canManage" variant="primary" icon="plus" @click="openCreate">Yeni Operasyon</OzButton>
		</template>
	</OzPageHeader>
	<OzModuleTabs />

	<OzStatStrip :items="stats" />

	<OzCard title="Operasyon Listesi">
		<OzTable :columns="columns" :rows="operations" row-key="id" :default-sort="{ key: 'sortOrder', dir: 'asc' }" searchable search-placeholder="Operasyon ara…" empty-title="Operasyon kaydı yok" min-width="600px">
			<template #cell-sortOrder="{ value }"><span class="mono dim">{{ value }}</span></template>
			<template #cell-code="{ value }"><span class="mono">{{ value }}</span></template>
			<template #cell-name="{ row }">
				{{ row.name }}
				<OzPill v-if="row.consumesMaterials" tone="info" class="consumes">Hammadde düşer</OzPill>
			</template>
			<template #cell-defaultLocation="{ value }">
				<OzPill :tone="value === 'fason' ? 'warning' : 'success'">{{ LOCATIONS[value] ?? value }}</OzPill>
			</template>
			<template #cell-defaultUnitCost="{ value }"><span class="mono">{{ formatMoney(value) }}</span></template>
			<template v-if="canManage" #cell-actions="{ row }">
				<OzDropdown :items="rowActions()" :label="`${row.name} işlemleri`" @select="(k) => onAction(k, row)" />
			</template>
		</OzTable>
	</OzCard>

	<OzDrawer v-model:open="formOpen" :title="editing ? 'Operasyonu Düzenle' : 'Yeni Operasyon'" subtitle="Rota adımlarında varsayılan olarak gelen değerler" :icon="editing ? 'edit' : 'plus'" size="md" :persistent="form.processing">
		<form class="form" @submit.prevent="submit">
			<div class="row-2">
				<OzField label="Kod" required :error="form.errors.code">
					<OzInput v-model="form.code" class="mono" placeholder="OP-01" />
				</OzField>
				<OzField label="Ad" required :error="form.errors.name">
					<OzInput v-model="form.name" placeholder="Kesim" />
				</OzField>
			</div>
			<OzField label="Varsayılan Yer" required :error="form.errors.default_location">
				<OzRadioGroup v-model="form.default_location" :options="locationOptions" variant="cards" aria-label="Varsayılan yer" />
			</OzField>
			<div class="row-2">
				<OzField label="Birim İşçilik" required :error="form.errors.default_unit_cost">
					<OzInput v-model.number="form.default_unit_cost" type="number" min="0" step="0.01" suffix="₺" placeholder="0,00" />
				</OzField>
				<OzField label="Sıra" :error="form.errors.sort_order">
					<OzInput v-model.number="form.sort_order" type="number" min="0" placeholder="0" />
				</OzField>
			</div>
			<OzSwitch
				v-model="form.consumes_materials"
				label="Hammadde bu aşamada düşer"
				description="İş emrinde hammadde planlamada rezerve edilir; bu operasyonda üretim kaydı girildikçe stoktan düşülür (genelde Kesim). Rotada işaretli aşama yoksa ilk aşamada düşer."
			/>
			<button type="submit" hidden />
		</form>
		<template #footer>
			<OzButton :disabled="form.processing" @click="formOpen = false">İptal</OzButton>
			<OzButton variant="primary" :icon="editing ? 'check' : 'plus'" :loading="form.processing" @click="submit">{{ editing ? 'Kaydet' : 'Ekle' }}</OzButton>
		</template>
	</OzDrawer>
</template>

<style scoped>

.mono { font-family: ui-monospace, monospace; font-size: 12px; }
.dim { color: var(--oz-muted); }
.form { display: flex; flex-direction: column; gap: 14px; }
.consumes { margin-left: 8px; }
.row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
@media (max-width: 560px) {
	.row-2 { grid-template-columns: 1fr; }
}
</style>
