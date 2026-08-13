<template>
	<Head title="Alınan Faturalar" />
	<div class="page-supplier-invoices">
		<Breadcrumb
			:items="[
				{ label: 'Ana Sayfa', to: '/workflow', icon: 'home' },
				{ label: 'Finans', to: '/finance' },
				{ label: 'Alınan Faturalar' },
			]"
		/>

		<FinanceNav current="supplier-invoices" />

		<PageHeader title="Alınan Faturalar">
			<template #subtitle><strong>{{ invoices.length }}</strong> tedarikçi faturası</template>
			<template #actions>
				<Button variant="primary" with-icon @click="openCreate">
					<template #leading><Plus :size="13" /></template>
					Yeni Fatura
				</Button>
			</template>
		</PageHeader>

		<Card title="Fatura Listesi" body-class="p-0">
			<DataTable :columns="columns" :data="invoices" row-key-field="id" empty-title="Kayıt bulunamadı">
				<template #invoiceNo="{ value }"><span class="mono">{{ value }}</span></template>
				<template #supplierName="{ row }">
					{{ row.supplierName }}
					<span v-if="row.productionOrderCode" class="dim">· {{ row.productionOrderCode }}</span>
				</template>
				<template #category="{ value }"><span class="dim">{{ value ?? '—' }}</span></template>
				<template #total="{ row }"><span class="strong">{{ formatMoney(row.total) }}</span> <span class="dim">{{ row.currency }}</span></template>
				<template #dueDate="{ value }"><span class="dim">{{ value ?? '—' }}</span></template>
				<template #status="{ value }"><Badge :color="statusColor(value)" variant="tonal" :label="statusLabel(value)" /></template>
				<template #actions="{ row }">
					<a v-if="row.hasFile" class="table-action-btn" title="Dosyayı indir" :href="`/finance/supplier-invoices/${row.id}/file`" target="_blank"><Paperclip :size="14" /></a>
					<button v-if="row.status !== 'paid'" class="table-action-btn" title="Ödendi işaretle" @click="markPaid(row)"><CheckCircle2 :size="14" /></button>
					<button class="table-action-btn" title="Düzenle" @click="openEdit(row)"><Pencil :size="14" /></button>
					<button class="table-action-btn danger" title="Sil" @click="remove(row)"><Trash2 :size="14" /></button>
				</template>
			</DataTable>
		</Card>

		<AppModal v-model="modalOpen" :title="editing ? 'Faturayı Düzenle' : 'Yeni Fatura'" size="lg">
			<form id="supplier-invoice-form" class="form-grid" @submit.prevent="submit">
				<div class="form-row-inline">
					<div class="form-row">
						<label class="form-label">Fatura No <span class="req">*</span></label>
						<input v-model="form.invoice_no" type="text" class="form-input" placeholder="FTR-2026-001" />
						<span v-if="form.errors.invoice_no" class="form-error">{{ form.errors.invoice_no }}</span>
					</div>
					<div class="form-row" style="flex: 2">
						<label class="form-label">Tedarikçi <span class="req">*</span></label>
						<input v-model="form.supplier_name" type="text" class="form-input" placeholder="Firma adı" />
						<span v-if="form.errors.supplier_name" class="form-error">{{ form.errors.supplier_name }}</span>
					</div>
					<div class="form-row">
						<label class="form-label">Vergi No</label>
						<input v-model="form.supplier_tax_number" type="text" class="form-input" placeholder="1234567890" />
					</div>
					<div class="form-row">
						<label class="form-label">Kategori</label>
						<input v-model="form.category" type="text" class="form-input" placeholder="hammadde / kargo / hizmet" />
					</div>
				</div>
				<div class="form-row-inline">
					<div class="form-row">
						<label class="form-label">Fatura Tarihi <span class="req">*</span></label>
						<input v-model="form.invoice_date" type="date" class="form-input" />
						<span v-if="form.errors.invoice_date" class="form-error">{{ form.errors.invoice_date }}</span>
					</div>
					<div class="form-row">
						<label class="form-label">Vade Tarihi</label>
						<input v-model="form.due_date" type="date" class="form-input" />
						<span v-if="form.errors.due_date" class="form-error">{{ form.errors.due_date }}</span>
					</div>
					<div class="form-row">
						<label class="form-label">Ara Toplam <span class="req">*</span></label>
						<input v-model.number="form.subtotal" type="number" step="0.01" min="0" class="form-input" @input="recalcTotal" />
					</div>
					<div class="form-row">
						<label class="form-label">KDV <span class="req">*</span></label>
						<input v-model.number="form.tax_amount" type="number" step="0.01" min="0" class="form-input" @input="recalcTotal" />
					</div>
					<div class="form-row">
						<label class="form-label">Toplam <span class="req">*</span></label>
						<input v-model.number="form.total" type="number" step="0.01" min="0" class="form-input" />
						<span v-if="form.errors.total" class="form-error">{{ form.errors.total }}</span>
					</div>
				</div>
				<div class="form-row-inline">
					<div class="form-row">
						<label class="form-label">Durum</label>
						<select v-model="form.status" class="form-input">
							<option value="unpaid">Ödenmedi</option>
							<option value="partially_paid">Kısmi Ödendi</option>
							<option value="paid">Ödendi</option>
							<option value="cancelled">İptal</option>
						</select>
					</div>
					<div class="form-row" style="flex: 2">
						<label class="form-label">Üretim Emri</label>
						<select v-model="form.production_order_id" class="form-input">
							<option :value="null">— Bağlı değil —</option>
							<option v-for="po in productionOrders" :key="po.value" :value="po.value">{{ po.label }}</option>
						</select>
					</div>
					<div class="form-row" style="flex: 3">
						<label class="form-label">Not</label>
						<input v-model="form.note" type="text" class="form-input" placeholder="isteğe bağlı" />
					</div>
				</div>
				<div class="form-row-inline">
					<div class="form-row" style="flex: 2">
						<label class="form-label">Fatura Dosyası (PDF/görsel)</label>
						<input type="file" class="form-input" accept=".pdf,.jpg,.jpeg,.png" @change="onFileChange" />
						<span v-if="editing && form.hadFile && !form.file" class="dim">Mevcut dosya korunacak, değiştirmek için yeni dosya seçin.</span>
						<span v-if="form.errors.file" class="form-error">{{ form.errors.file }}</span>
					</div>
				</div>
			</form>
			<template #footer="{ close }">
				<Button variant="ghost" :disabled="form.processing" @click="close">İptal</Button>
				<Button type="submit" form="supplier-invoice-form" variant="primary" :loading="form.processing">
					{{ editing ? 'Güncelle' : 'Ekle' }}
				</Button>
			</template>
		</AppModal>
	</div>
</template>

<script setup>
import { ref, inject, watch } from 'vue'
import { Head, useForm, router } from '@inertiajs/vue3'
import { Plus, Paperclip, CheckCircle2, Pencil, Trash2 } from 'lucide-vue-next'
import AppLayout from '@/Layouts/AppLayout.vue'
import Breadcrumb from '@/Components/Breadcrumb.vue'
import PageHeader from '@/Components/PageHeader.vue'
import Card from '@/Components/Card.vue'
import DataTable from '@/Components/DataTable.vue'
import Badge from '@/Components/Badge.vue'
import Button from '@/Components/Button.vue'
import AppModal from '@/Components/AppModal.vue'
import FinanceNav from '../Components/FinanceNav.vue'

defineOptions({ layout: AppLayout })

const $swal = inject('$swal')
const showToast = inject('showToast')

defineProps({
	invoices: { type: Array, default: () => [] },
	productionOrders: { type: Array, default: () => [] },
})

const columns = [
	{ key: 'invoiceNo', label: 'Fatura No' },
	{ key: 'supplierName', label: 'Tedarikçi' },
	{ key: 'category', label: 'Kategori' },
	{ key: 'total', label: 'Tutar' },
	{ key: 'dueDate', label: 'Vade' },
	{ key: 'status', label: 'Durum' },
]

const editing = ref(false)
const modalOpen = ref(false)

function emptyForm() {
	return {
		id: null,
		invoice_no: '',
		supplier_name: '',
		supplier_tax_number: '',
		category: '',
		invoice_date: '',
		due_date: '',
		subtotal: 0,
		tax_amount: 0,
		total: 0,
		status: 'unpaid',
		production_order_id: null,
		note: '',
		file: null,
		hadFile: false,
	}
}

const form = useForm(emptyForm())

function onFileChange(e) {
	form.file = e.target.files[0] ?? null
}

function recalcTotal() {
	const subtotal = Number(form.subtotal) || 0
	const tax = Number(form.tax_amount) || 0
	form.total = Math.round((subtotal + tax) * 100) / 100
}

function openCreate() {
	reset()
	modalOpen.value = true
}

function openEdit(inv) {
	editing.value = true
	Object.assign(form, {
		id: inv.id,
		invoice_no: inv.invoiceNo,
		supplier_name: inv.supplierName,
		supplier_tax_number: inv.supplierTaxNumber,
		category: inv.category,
		invoice_date: inv.invoiceDate,
		due_date: inv.dueDate,
		subtotal: inv.subtotal,
		tax_amount: inv.taxAmount,
		total: inv.total,
		status: inv.status,
		production_order_id: inv.productionOrderId,
		note: inv.note,
		file: null,
		hadFile: inv.hasFile,
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
		preserveScroll: true,
		onSuccess: () => {
			showToast?.({ type: 'success', title: editing.value ? 'Fatura güncellendi' : 'Fatura eklendi', message: form.invoice_no })
			modalOpen.value = false
		},
		onError: () => {
			showToast?.({ type: 'error', title: 'Kayıt başarısız', message: Object.values(form.errors)[0] || 'Doğrulama hatası.' })
		},
	}
	if (editing.value) {
		form.put(`/finance/supplier-invoices/${form.id}`, opts)
	} else {
		form.post('/finance/supplier-invoices', opts)
	}
}

async function remove(inv) {
	const ok = await $swal.dangerConfirm({
		title: 'Fatura silinsin mi?',
		html: `<b>${inv.invoiceNo}</b> kalıcı olarak silinecek.`,
	})
	if (!ok) return
	router.delete(`/finance/supplier-invoices/${inv.id}`, {
		preserveScroll: true,
		onSuccess: () => showToast?.({ type: 'warning', title: 'Fatura silindi', message: inv.invoiceNo }),
	})
}

function markPaid(inv) {
	router.post(`/finance/supplier-invoices/${inv.id}/mark-paid`, {}, {
		preserveScroll: true,
		onSuccess: () => showToast?.({ type: 'success', title: 'Ödendi olarak işaretlendi', message: inv.invoiceNo }),
	})
}

function statusLabel(status) {
	return { unpaid: 'Ödenmedi', partially_paid: 'Kısmi Ödendi', paid: 'Ödendi', cancelled: 'İptal' }[status] ?? status
}
function statusColor(status) {
	return { unpaid: 'warning', partially_paid: 'info', paid: 'success', cancelled: 'danger' }[status] ?? 'neutral'
}

function formatMoney(value) {
	return new Intl.NumberFormat('tr-TR', { style: 'currency', currency: 'TRY' }).format(value ?? 0)
}
</script>

<style scoped>
.form-grid { display: flex; flex-direction: column; gap: 12px; }
.form-row-inline { display: flex; gap: 12px; flex-wrap: wrap; align-items: flex-end; }
.form-row { display: flex; flex-direction: column; gap: 6px; flex: 1; min-width: 140px; }
.form-label { font-size: 12px; font-weight: 600; color: rgb(var(--color-ink)); }
.form-label .req { color: rgb(var(--color-danger)); }
.form-input { padding: 9px 12px; border: 1px solid rgb(var(--color-border)); border-radius: 8px; font-family: inherit; font-size: 13px; color: rgb(var(--color-ink)); background: rgb(var(--color-surface)); outline: none; transition: border-color .15s; }
.form-input:focus { border-color: rgb(var(--color-primary)); }
.form-error { font-size: 11.5px; color: rgb(var(--color-danger)); }

.dim { font-size: 12px; color: rgb(var(--color-muted)); }
.mono { font-family: 'SF Mono', Menlo, Consolas, monospace; font-size: 12px; }
.strong { font-weight: 700; color: rgb(var(--color-ink)); }

.table-action-btn { display: inline-flex; align-items: center; justify-content: center; background: rgb(var(--color-bg)); border: none; cursor: pointer; padding: 6px; border-radius: 6px; color: rgb(var(--color-muted)); transition: all .15s; }
.table-action-btn:hover { background: rgb(var(--color-primary-soft)); color: rgb(var(--color-primary)); }
.table-action-btn.danger:hover { background: rgb(var(--color-danger) / .12); color: rgb(var(--color-danger)); }
</style>
