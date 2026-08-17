<template>
	<Head title="Operasyonlar" />
	<div class="page-operations">
		<Breadcrumb
			:items="[
				{ label: 'Ana Sayfa', to: '/workflow', icon: 'home' },
				{ label: 'Üretim Atölyesi', to: '/atelier' },
				{ label: 'Operasyonlar' },
			]"
		/>

		<AtelierNav current="operations" />

		<PageHeader title="Operasyonlar">
			<template #subtitle><strong>{{ operations.length }}</strong> operasyon tanımı</template>
			<template v-if="can('atelier.operation.manage')" #actions>
				<Button variant="primary" with-icon @click="openCreate">
					<template #leading><Plus :size="13" /></template>
					Yeni Operasyon
				</Button>
			</template>
		</PageHeader>

		<Card title="Operasyon Listesi" body-class="p-0">
			<DataTable :columns="columns" :data="operations" row-key-field="id" empty-title="Operasyon kaydı yok.">
				<template #code="{ value }"><span class="mono-chip">{{ value }}</span></template>
				<template #name="{ value }"><span class="row-name">{{ value }}</span></template>
				<template #defaultLocation="{ value }">
					<Badge :color="value === 'fason' ? 'warning' : 'success'" :label="value === 'fason' ? 'Fason' : 'İç'" variant="tonal" />
				</template>
				<template #defaultUnitCost="{ value }"><span class="num">{{ value }}</span></template>
				<template #sortOrder="{ value }"><span class="num dim">{{ value }}</span></template>
				<template v-if="can('atelier.operation.manage')" #actions="{ row }">
					<button class="table-action-btn" title="Düzenle" @click="openEdit(row)"><Pencil :size="14" /></button>
					<button class="table-action-btn danger" title="Sil" @click="remove(row)"><Trash2 :size="14" /></button>
				</template>
			</DataTable>
		</Card>

		<AppModal v-model="modalOpen" :title="editing ? 'Operasyon Düzenle' : 'Yeni Operasyon'" size="md">
			<form id="operation-form" class="form-grid-inline" @submit.prevent="submit">
				<div class="form-row">
					<label class="form-label">Kod <span class="req">*</span></label>
					<input v-model="form.code" type="text" class="form-input" placeholder="örn. OP-01" />
					<span v-if="form.errors.code" class="form-error">{{ form.errors.code }}</span>
				</div>
				<div class="form-row" style="flex: 2">
					<label class="form-label">Ad <span class="req">*</span></label>
					<input v-model="form.name" type="text" class="form-input" placeholder="Operasyon adı" />
					<span v-if="form.errors.name" class="form-error">{{ form.errors.name }}</span>
				</div>
				<div class="form-row">
					<label class="form-label">Varsayılan Yer</label>
					<select v-model="form.default_location" class="form-input">
						<option value="in_house">İç atölye</option>
						<option value="fason">Fason</option>
					</select>
				</div>
				<div class="form-row">
					<label class="form-label">Birim İşçilik</label>
					<input v-model="form.default_unit_cost" type="number" step="0.01" class="form-input" placeholder="0.00" />
				</div>
				<div class="form-row" style="flex: 0 0 80px">
					<label class="form-label">Sıra</label>
					<input v-model="form.sort_order" type="number" class="form-input" placeholder="0" />
				</div>
			</form>
			<template #footer="{ close }">
				<Button variant="ghost" :disabled="form.processing" @click="close">İptal</Button>
				<Button type="submit" form="operation-form" variant="primary" :loading="form.processing">
					{{ editing ? 'Güncelle' : 'Ekle' }}
				</Button>
			</template>
		</AppModal>
	</div>
</template>

<script setup>
import { ref, inject, watch } from 'vue'
import { Head, useForm, router } from '@inertiajs/vue3'
import { Plus, Pencil, Trash2 } from 'lucide-vue-next'
import AppLayout from '@/Layouts/AppLayout.vue'
import Breadcrumb from '@/Components/Breadcrumb.vue'
import PageHeader from '@/Components/PageHeader.vue'
import Card from '@/Components/Card.vue'
import DataTable from '@/Components/DataTable.vue'
import Badge from '@/Components/Badge.vue'
import Button from '@/Components/Button.vue'
import AppModal from '@/Components/AppModal.vue'
import AtelierNav from '../Components/AtelierNav.vue'
import { useCan } from '@/composables/useCan'

defineOptions({ layout: AppLayout })

const { can } = useCan()
const $swal = inject('$swal')
const showToast = inject('showToast', null)

const props = defineProps({ operations: Array })

const columns = [
	{ key: 'code', label: 'Kod' },
	{ key: 'name', label: 'Ad' },
	{ key: 'defaultLocation', label: 'Yer' },
	{ key: 'defaultUnitCost', label: 'Birim İşçilik', align: 'right' },
	{ key: 'sortOrder', label: 'Sıra', align: 'right' },
]

function emptyForm() {
	return { id: null, code: '', name: '', default_location: 'in_house', default_unit_cost: 0, sort_order: 0 }
}

const form = useForm(emptyForm())
const editing = ref(false)
const modalOpen = ref(false)

function openCreate() {
	reset()
	modalOpen.value = true
}

function openEdit(o) {
	editing.value = true
	Object.assign(form, {
		id: o.id, code: o.code, name: o.name,
		default_location: o.defaultLocation, default_unit_cost: o.defaultUnitCost, sort_order: o.sortOrder,
	})
	modalOpen.value = true
}

function reset() {
	editing.value = false
	form.reset()
	Object.assign(form, emptyForm())
	form.clearErrors()
}

watch(modalOpen, (open) => {
	if (!open) setTimeout(reset, 250)
})

function submit() {
	const opts = {
		onSuccess: () => {
			showToast?.({ type: 'success', title: editing.value ? 'Operasyon güncellendi' : 'Operasyon eklendi', message: form.name })
			modalOpen.value = false
		},
		onError: () => {
			showToast?.({ type: 'error', title: 'Kayıt başarısız', message: Object.values(form.errors)[0] || 'Doğrulama hatası.' })
		},
	}
	editing.value ? form.put(`/atelier/operations/${form.id}`, opts) : form.post('/atelier/operations', opts)
}
async function remove(o) {
  const ok = await $swal.dangerConfirm({ title: 'Operasyon silinsin mi?', html: 'Bu operasyon kalıcı olarak silinecek.' })
  if (ok) router.delete(`/atelier/operations/${o.id}`)
}
</script>

<style scoped>
.form-grid-inline { display: flex; flex-wrap: wrap; gap: 12px; }
.form-row { display: flex; flex-direction: column; gap: 6px; flex: 1; min-width: 120px; }
.form-label { font-size: 12px; font-weight: 600; color: #1a1a2e; }
.form-label .req { color: #ef4444; }
.form-input { padding: 9px 12px; border: 1px solid #e8e8f0; border-radius: 8px; font-family: inherit; font-size: 13px; color: #1a1a2e; background: #fff; outline: none; transition: border-color .15s; }
.form-input:focus { border-color: var(--color-primary); }
.form-error { font-size: 11.5px; color: var(--color-danger); }

.num { text-align: right; font-family: 'SF Mono', Menlo, Consolas, monospace; font-weight: 600; }
.dim { color: #888; font-weight: 500; }
.row-name { font-weight: 600; color: #1a1a2e; }
.mono-chip { font-family: 'SF Mono', Menlo, Consolas, monospace; font-size: 11.5px; background: #f0f0f5; padding: 2px 7px; border-radius: 5px; color: #555; }

.table-action-btn { background: #f3f4f6; border: none; cursor: pointer; font-size: 13px; padding: 5px 9px; border-radius: 6px; color: #6b7280; transition: all .15s; display: inline-flex; align-items: center; }
.table-action-btn:hover { background: var(--color-primary-soft); color: var(--color-primary); }
.table-action-btn.danger:hover { background: #fee2e2; color: #dc2626; }
</style>
