<template>
	<Head title="Fasoncular" />
	<div class="page-fason">
		<Breadcrumb
			:items="[
				{ label: 'Ana Sayfa', to: '/workflow', icon: 'home' },
				{ label: 'Üretim Atölyesi', to: '/atelier' },
				{ label: 'Fasoncular' },
			]"
		/>

		<AtelierNav current="fason" />

		<PageHeader title="Fasoncular">
			<template #subtitle><strong>{{ suppliers.length }}</strong> fasoncu kaydı</template>
			<template v-if="can('atelier.fason.manage')" #actions>
				<Button variant="primary" with-icon @click="openCreate">
					<template #leading><Plus :size="13" /></template>
					Yeni Fasoncu
				</Button>
			</template>
		</PageHeader>

		<Card title="Fasoncu Listesi" body-class="p-0">
			<DataTable :columns="columns" :data="suppliers" row-key-field="id" empty-title="Fasoncu kaydı yok.">
				<template #name="{ row }">
					<div class="supplier-cell">
						<Avatar :name="row.name" size="sm" />
						<div class="supplier-info">
							<span class="row-name">{{ row.name }}</span>
							<span v-if="row.taxNo" class="tax-no">{{ row.taxNo }}</span>
						</div>
					</div>
				</template>
				<template #phone="{ value }"><a v-if="value" :href="`tel:${value}`" class="link-dim">{{ value }}</a><span v-else class="dim">—</span></template>
				<template #email="{ value }"><a v-if="value" :href="`mailto:${value}`" class="link-dim">{{ value }}</a><span v-else class="dim">—</span></template>
				<template #isActive="{ value }">
					<Badge :color="value ? 'success' : 'neutral'" :label="value ? 'Aktif' : 'Pasif'" variant="tonal" />
				</template>
				<template v-if="can('atelier.fason.manage')" #actions="{ row }">
					<button class="table-action-btn" title="Düzenle" @click="openEdit(row)"><Pencil :size="14" /></button>
					<button class="table-action-btn danger" title="Sil" @click="remove(row)"><Trash2 :size="14" /></button>
				</template>
			</DataTable>
		</Card>

		<AppModal v-model="modalOpen" :title="editing ? 'Fasoncu Düzenle' : 'Yeni Fasoncu'" size="md">
			<form id="fason-form" class="form-grid" @submit.prevent="submit">
				<div class="form-row-inline">
					<div class="form-row" style="flex: 2">
						<label class="form-label">Ad <span class="req">*</span></label>
						<input v-model="form.name" type="text" class="form-input" placeholder="Firma adı" />
						<span v-if="form.errors.name" class="form-error">{{ form.errors.name }}</span>
					</div>
					<div class="form-row">
						<label class="form-label">Yetkili</label>
						<input v-model="form.contact_name" type="text" class="form-input" placeholder="Ad Soyad" />
					</div>
					<div class="form-row">
						<label class="form-label">Telefon</label>
						<input v-model="form.phone" type="text" class="form-input" placeholder="+90 5xx..." />
					</div>
					<div class="form-row">
						<label class="form-label">E-posta</label>
						<input v-model="form.email" type="email" class="form-input" placeholder="ornek@firma.com" />
					</div>
				</div>
				<div class="form-row-inline">
					<div class="form-row" style="flex: 3">
						<label class="form-label">Adres</label>
						<input v-model="form.address" type="text" class="form-input" placeholder="Açık adres" />
					</div>
					<div class="form-row">
						<label class="form-label">Vergi No</label>
						<input v-model="form.tax_no" type="text" class="form-input" placeholder="1234567890" />
					</div>
				</div>
			</form>
			<template #footer="{ close }">
				<Button variant="ghost" :disabled="form.processing" @click="close">İptal</Button>
				<Button type="submit" form="fason-form" variant="primary" :loading="form.processing">
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
import Avatar from '@/Components/Avatar.vue'
import Badge from '@/Components/Badge.vue'
import Button from '@/Components/Button.vue'
import AppModal from '@/Components/AppModal.vue'
import AtelierNav from '../Components/AtelierNav.vue'
import { useCan } from '@/composables/useCan'

defineOptions({ layout: AppLayout })

const { can } = useCan()
const $swal = inject('$swal')
const showToast = inject('showToast', null)

const props = defineProps({ suppliers: Array })

const columns = [
	{ key: 'name', label: 'Ad' },
	{ key: 'contactName', label: 'Yetkili' },
	{ key: 'phone', label: 'Telefon' },
	{ key: 'email', label: 'E-posta' },
	{ key: 'isActive', label: 'Durum' },
]

function emptyForm() {
	return { id: null, name: '', contact_name: '', phone: '', email: '', address: '', tax_no: '', notes: '', is_active: true }
}

const form = useForm(emptyForm())
const editing = ref(false)
const modalOpen = ref(false)

function openCreate() {
	reset()
	modalOpen.value = true
}

function openEdit(s) {
	editing.value = true
	Object.assign(form, { id: s.id, name: s.name, contact_name: s.contactName, phone: s.phone, email: s.email, address: s.address, tax_no: s.taxNo, notes: s.notes, is_active: s.isActive })
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
			showToast?.({ type: 'success', title: editing.value ? 'Fasoncu güncellendi' : 'Fasoncu eklendi', message: form.name })
			modalOpen.value = false
		},
		onError: () => {
			showToast?.({ type: 'error', title: 'Kayıt başarısız', message: Object.values(form.errors)[0] || 'Doğrulama hatası.' })
		},
	}
	editing.value ? form.put(`/atelier/fason-suppliers/${form.id}`, opts) : form.post('/atelier/fason-suppliers', opts)
}
async function remove(s) {
  const ok = await $swal.dangerConfirm({ title: 'Fasoncu silinsin mi?', html: `<b>${s.name}</b> fason tedarikçisi kalıcı olarak silinecek.` })
  if (ok) router.delete(`/atelier/fason-suppliers/${s.id}`)
}
</script>

<style scoped>
.form-grid { display: flex; flex-direction: column; gap: 12px; }
.form-row-inline { display: flex; gap: 12px; flex-wrap: wrap; }
.form-row { display: flex; flex-direction: column; gap: 6px; flex: 1; min-width: 140px; }
.form-label { font-size: 12px; font-weight: 600; color: #1a1a2e; }
.form-label .req { color: #ef4444; }
.form-input { padding: 9px 12px; border: 1px solid #e8e8f0; border-radius: 8px; font-family: inherit; font-size: 13px; color: #1a1a2e; background: #fff; outline: none; transition: border-color .15s; }
.form-input:focus { border-color: var(--color-primary); }
.form-error { font-size: 11.5px; color: var(--color-danger); }

.dim { color: #888; font-size: 12px; }
.row-name { font-weight: 600; color: #1a1a2e; }

.supplier-cell { display: flex; align-items: center; gap: 10px; }
.supplier-info { display: flex; flex-direction: column; gap: 2px; }
.tax-no { font-size: 10.5px; color: #888; font-family: 'SF Mono', Menlo, Consolas, monospace; }

.link-dim { color: #555; text-decoration: none; font-size: 13px; }
.link-dim:hover { color: var(--color-primary); text-decoration: underline; }

.table-action-btn { background: #f3f4f6; border: none; cursor: pointer; font-size: 13px; padding: 5px 9px; border-radius: 6px; color: #6b7280; transition: all .15s; display: inline-flex; align-items: center; }
.table-action-btn:hover { background: var(--color-primary-soft); color: var(--color-primary); }
.table-action-btn.danger:hover { background: #fee2e2; color: #dc2626; }
</style>
