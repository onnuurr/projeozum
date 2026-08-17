<template>
	<Head title="Hammaddeler" />
	<div class="page-materials">
		<Breadcrumb
			:items="[
				{ label: 'Ana Sayfa', to: '/workflow', icon: 'home' },
				{ label: 'Üretim Atölyesi', to: '/atelier' },
				{ label: 'Hammaddeler' },
			]"
		/>

		<AtelierNav current="materials" />

		<PageHeader title="Hammaddeler">
			<template #subtitle><strong>{{ materials.length }}</strong> hammadde tanımı</template>
			<template v-if="can('atelier.material.manage')" #actions>
				<Button variant="primary" with-icon @click="openCreate">
					<template #leading><Plus :size="13" /></template>
					Yeni Hammadde
				</Button>
			</template>
		</PageHeader>

		<Card title="Hammadde Listesi" body-class="p-0">
			<DataTable :columns="columns" :data="materials" row-key-field="id" empty-title="Hammadde kaydı yok.">
				<template #code="{ value }"><span class="mono-chip">{{ value }}</span></template>
				<template #name="{ value }"><span class="row-name">{{ value }}</span></template>
				<template #type="{ value }"><Badge color="info" :label="value" variant="tonal" /></template>
				<template #currentStock="{ value }"><span class="num">{{ value }}</span></template>
				<template #unitCost="{ value }"><span class="num dim">{{ value }}</span></template>
				<template v-if="can('atelier.material.manage')" #actions="{ row }">
					<button class="table-action-btn" title="Özellikler (AI için)" @click="openSpecs(row)"><Shirt :size="14" /></button>
					<button class="table-action-btn" title="Stok Hareketi" @click="openMove(row)"><ArrowUpDown :size="14" /></button>
					<button class="table-action-btn" title="Düzenle" @click="openEdit(row)"><Pencil :size="14" /></button>
					<button class="table-action-btn danger" title="Sil" @click="remove(row)"><Trash2 :size="14" /></button>
				</template>
			</DataTable>
		</Card>

		<AppModal v-model="formModalOpen" :title="editing ? 'Hammadde Düzenle' : 'Yeni Hammadde'" size="md">
			<form id="material-form" class="form-grid-inline" @submit.prevent="submit">
				<div class="form-row">
					<label class="form-label">Kod <span class="req">*</span></label>
					<input v-model="form.code" type="text" class="form-input" placeholder="örn. KM-001" />
					<span v-if="form.errors.code" class="form-error">{{ form.errors.code }}</span>
				</div>
				<div class="form-row" style="flex: 2">
					<label class="form-label">Ad <span class="req">*</span></label>
					<input v-model="form.name" type="text" class="form-input" placeholder="Hammadde adı" />
					<span v-if="form.errors.name" class="form-error">{{ form.errors.name }}</span>
				</div>
				<div class="form-row">
					<label class="form-label">Tür</label>
					<select v-model="form.type" class="form-input">
						<option value="kumas">Kumaş</option>
						<option value="aksesuar">Aksesuar</option>
						<option value="etiket">Etiket</option>
					</select>
				</div>
				<div class="form-row">
					<label class="form-label">Birim</label>
					<select v-model="form.unit" class="form-input">
						<option value="metre">Metre</option>
						<option value="adet">Adet</option>
						<option value="kg">Kg</option>
					</select>
				</div>
				<div class="form-row">
					<label class="form-label">Birim Maliyet</label>
					<input v-model="form.unit_cost" type="number" step="0.01" class="form-input" placeholder="0.00" />
				</div>
			</form>
			<template #footer="{ close }">
				<Button variant="ghost" :disabled="form.processing" @click="close">İptal</Button>
				<Button type="submit" form="material-form" variant="primary" :loading="form.processing">
					{{ editing ? 'Güncelle' : 'Ekle' }}
				</Button>
			</template>
		</AppModal>

		<!-- Materyal Özellikleri Modal (AI için) -->
		<AppModal v-model="specsModalOpen" title="Materyal Özellikleri" :subtitle="specs.material_name" size="lg">
			<p class="modal-desc">AI ürün açıklaması bu bilgilerden üretilir. Kumaş için kompozisyon ve gramaj yeterli.</p>
			<MaterialSpecsPanel v-model="specs.data" />
			<template #footer="{ close }">
				<Button variant="ghost" @click="close">Kapat</Button>
				<Button variant="primary" :loading="specsBusy" @click="submitSpecs">Kaydet</Button>
			</template>
		</AppModal>

		<!-- Stok Hareketi Modal -->
		<AppModal v-model="moveModalOpen" title="Stok Hareketi" size="sm">
			<form id="move-form" class="form-grid" @submit.prevent="submitMove">
				<div class="form-row">
					<label class="form-label">Tip <span class="req">*</span></label>
					<select v-model="move.type" class="form-input">
						<option value="in">Giriş</option>
						<option value="out">Çıkış</option>
						<option value="adjust">Düzeltme</option>
					</select>
				</div>
				<div class="form-row">
					<label class="form-label">Miktar <span class="req">*</span></label>
					<input v-model="move.quantity" type="number" step="0.001" class="form-input" />
					<span v-if="move.errors.quantity" class="form-error">{{ move.errors.quantity }}</span>
				</div>
				<div class="form-row">
					<label class="form-label">Sebep</label>
					<select v-model="move.reason" class="form-input">
						<option value="purchase">Satın alma</option>
						<option value="consume">Tüketim</option>
						<option value="scrap">Fire</option>
						<option value="correction">Düzeltme</option>
					</select>
				</div>
			</form>
			<template #footer="{ close }">
				<Button variant="ghost" :disabled="move.processing" @click="close">Kapat</Button>
				<Button type="submit" form="move-form" variant="primary" :loading="move.processing">Kaydet</Button>
			</template>
		</AppModal>
	</div>
</template>

<script setup>
import { ref, inject, watch } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import { Plus, Shirt, ArrowUpDown, Pencil, Trash2 } from 'lucide-vue-next'
import AppLayout from '@/Layouts/AppLayout.vue'
import Breadcrumb from '@/Components/Breadcrumb.vue'
import PageHeader from '@/Components/PageHeader.vue'
import Card from '@/Components/Card.vue'
import DataTable from '@/Components/DataTable.vue'
import Badge from '@/Components/Badge.vue'
import Button from '@/Components/Button.vue'
import AppModal from '@/Components/AppModal.vue'
import AtelierNav from '../Components/AtelierNav.vue'
import MaterialSpecsPanel from '../Components/MaterialSpecsPanel.vue'
import axios from 'axios'
import { useCan } from '@/composables/useCan'

defineOptions({ layout: AppLayout })

const { can } = useCan()
const $swal = inject('$swal')
const showToast = inject('showToast', null)

const props = defineProps({ materials: Array })

const columns = [
	{ key: 'code', label: 'Kod' },
	{ key: 'name', label: 'Ad' },
	{ key: 'type', label: 'Tür' },
	{ key: 'unit', label: 'Birim' },
	{ key: 'currentStock', label: 'Stok', align: 'right' },
	{ key: 'unitCost', label: 'Maliyet', align: 'right' },
]

function emptyForm() {
	return { id: null, code: '', name: '', type: 'kumas', unit: 'metre', unit_cost: 0, is_active: true }
}

const form = useForm(emptyForm())
const editing = ref(false)
const formModalOpen = ref(false)

function openCreate() {
	reset()
	formModalOpen.value = true
}

function openEdit(m) {
	editing.value = true
	Object.assign(form, { id: m.id, code: m.code, name: m.name, type: m.type, unit: m.unit, unit_cost: m.unitCost, is_active: m.isActive })
	formModalOpen.value = true
}

function reset() {
	editing.value = false
	form.reset()
	Object.assign(form, emptyForm())
	form.clearErrors()
}

watch(formModalOpen, (open) => {
	if (!open) setTimeout(reset, 250)
})

function submit() {
	const opts = {
		onSuccess: () => {
			showToast?.({ type: 'success', title: editing.value ? 'Hammadde güncellendi' : 'Hammadde eklendi', message: form.name })
			formModalOpen.value = false
		},
		onError: () => {
			showToast?.({ type: 'error', title: 'Kayıt başarısız', message: Object.values(form.errors)[0] || 'Doğrulama hatası.' })
		},
	}
	editing.value ? form.put(`/atelier/materials/${form.id}`, opts) : form.post('/atelier/materials', opts)
}
async function remove(m) {
  const ok = await $swal.dangerConfirm({ title: 'Silinsin mi?', html: `<b>${m.name}</b> malzemesi kalıcı olarak silinecek.` })
  if (ok) router.delete(`/atelier/materials/${m.id}`)
}

const moveModalOpen = ref(false)
const move = useForm({ material_id: null, type: 'in', quantity: 0, reason: 'purchase', note: '' })
function openMove(m) { move.material_id = m.id; move.type = 'in'; move.quantity = 0; move.reason = 'purchase'; moveModalOpen.value = true }
function submitMove() {
  move.post('/atelier/materials/movement', { onSuccess: () => { move.reset(); moveModalOpen.value = false } })
}

const specsModalOpen = ref(false)
const specs = ref({ material_id: null, material_name: '', data: {} })
const specsBusy = ref(false)
function openSpecs(m) {
  specs.value = { material_id: m.id, material_name: m.name, data: { ...(m.specs || {}) } }
  specsModalOpen.value = true
}
async function submitSpecs() {
  if (!specs.value.material_id || specsBusy.value) return
  specsBusy.value = true
  try {
    await axios.put(`/atelier/materials/${specs.value.material_id}/specs`, { specs: specs.value.data })
    showToast?.({ type: 'success', title: 'Özellikler kaydedildi' })
    specsModalOpen.value = false
    router.reload({ only: ['materials'], preserveScroll: true })
  } catch (e) {
    const msg = e?.response?.data?.errors?.specs?.[0] || 'Kayıt başarısız.'
    showToast?.({ type: 'error', title: 'Hata', message: msg })
  } finally {
    specsBusy.value = false
  }
}
</script>

<style scoped>
.form-grid-inline { display: flex; flex-wrap: wrap; gap: 12px; }
.form-grid { display: flex; flex-direction: column; gap: 14px; }
.form-row { display: flex; flex-direction: column; gap: 6px; flex: 1; min-width: 120px; }
.form-label { font-size: 12px; font-weight: 600; color: #1a1a2e; }
.form-label .req { color: #ef4444; }
.form-input { padding: 9px 12px; border: 1px solid #e8e8f0; border-radius: 8px; font-family: inherit; font-size: 13px; color: #1a1a2e; background: #fff; outline: none; transition: border-color .15s; }
.form-input:focus { border-color: var(--color-primary); }
.form-error { font-size: 11.5px; color: #ef4444; }

.num { text-align: right; font-family: 'SF Mono', Menlo, Consolas, monospace; font-weight: 600; }
.dim { color: #888; font-weight: 500; }
.row-name { font-weight: 600; color: #1a1a2e; }
.mono-chip { font-family: 'SF Mono', Menlo, Consolas, monospace; font-size: 11.5px; background: #f0f0f5; padding: 2px 7px; border-radius: 5px; color: #555; }

.table-action-btn { background: #f3f4f6; border: none; cursor: pointer; font-size: 13px; padding: 5px 9px; border-radius: 6px; color: #6b7280; transition: all .15s; display: inline-flex; align-items: center; }
.table-action-btn:hover { background: var(--color-primary-soft); color: var(--color-primary); }
.table-action-btn.danger:hover { background: #fee2e2; color: #dc2626; }

.modal-desc { font-size: 12.5px; color: #888; margin: -6px 0 2px; }
</style>
