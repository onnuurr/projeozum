<template>
	<Head title="Düzenlenen Faturalar" />
	<div class="page-outgoing-invoices">
		<Breadcrumb
			:items="[
				{ label: 'Ana Sayfa', to: '/workflow', icon: 'home' },
				{ label: 'Finans', to: '/finance' },
				{ label: 'Düzenlenen Faturalar' },
			]"
		/>

		<FinanceNav current="outgoing-invoices" />

		<PageHeader title="Düzenlenen Faturalar">
			<template #subtitle>
				<strong>{{ invoices.length }}</strong> fatura ·
				e-Fatura entegratörü henüz seçilmedi, gönderim şu an gerçek GİB iletimi yapmıyor
			</template>
			<template #actions>
				<Button variant="primary" with-icon @click="openCreate">
					<template #leading><Plus :size="13" /></template>
					Yeni Fatura
				</Button>
			</template>
		</PageHeader>

		<Card title="Fatura Listesi" body-class="p-0">
			<DataTable :columns="columns" :data="invoices" row-key-field="id" empty-title="Kayıt bulunamadı">
				<template #invoiceNo="{ row }">
					<span class="mono">{{ row.invoiceNo }}</span>
					<span v-if="row.convertedFromProforma" class="dim">· {{ row.convertedFromProforma }}'dan</span>
				</template>
				<template #buyerName="{ row }">
					{{ row.buyerName }}
					<span v-if="row.orderNo" class="dim">· sipariş {{ row.orderNo }}</span>
					<span v-else-if="row.tenantName" class="dim">· {{ row.tenantName }}</span>
				</template>
				<template #total="{ row }"><span class="strong">{{ formatMoney(row.total) }}</span> <span class="dim">{{ row.currency }}</span></template>
				<template #status="{ value }"><Badge :color="statusColor(value)" variant="tonal" :label="statusLabel(value)" /></template>
				<template #efaturaStatus="{ value }"><Badge :color="efaturaColor(value)" variant="tonal" :label="efaturaLabel(value)" /></template>
				<template #actions="{ row }">
					<button v-if="row.efaturaStatus === 'not_sent'" class="table-action-btn" title="e-Fatura gönder" @click="send(row)"><Send :size="14" /></button>
					<button class="table-action-btn" title="Düzenle" @click="openEdit(row)"><Pencil :size="14" /></button>
					<button class="table-action-btn danger" title="Sil" @click="remove(row)"><Trash2 :size="14" /></button>
				</template>
			</DataTable>
		</Card>

		<AppModal v-model="modalOpen" :title="editing ? 'Faturayı Düzenle' : 'Yeni Fatura'" size="lg">
			<form id="outgoing-invoice-form" class="form-grid" @submit.prevent="submit">
				<div class="form-row-inline">
					<div class="form-row">
						<label class="form-label">Fatura No <span class="req">*</span></label>
						<input v-model="form.invoice_no" type="text" class="form-input" placeholder="FIN-2026-001" />
						<span v-if="form.errors.invoice_no" class="form-error">{{ form.errors.invoice_no }}</span>
					</div>
					<div class="form-row" style="flex: 2">
						<label class="form-label">Alıcı <span class="req">*</span></label>
						<input v-model="form.buyer_name" type="text" class="form-input" placeholder="Müşteri / firma adı" />
						<span v-if="form.errors.buyer_name" class="form-error">{{ form.errors.buyer_name }}</span>
					</div>
					<div class="form-row">
						<label class="form-label">Vergi No</label>
						<input v-model="form.buyer_tax_number" type="text" class="form-input" placeholder="1234567890" />
					</div>
					<div class="form-row" style="flex: 2">
						<label class="form-label">Adres</label>
						<input v-model="form.buyer_address" type="text" class="form-input" placeholder="isteğe bağlı" />
					</div>
				</div>
				<div class="form-row-inline">
					<div class="form-row">
						<label class="form-label">Fatura Türü</label>
						<select v-model="form.invoice_type" class="form-input">
							<option value="standalone">Standalone</option>
							<option value="sales_order">Sipariş Bazlı</option>
							<option value="tenant_sale">Bayi Satışı</option>
						</select>
					</div>
					<div class="form-row">
						<label class="form-label">Düzenleme Tarihi <span class="req">*</span></label>
						<input v-model="form.issue_date" type="date" class="form-input" />
						<span v-if="form.errors.issue_date" class="form-error">{{ form.errors.issue_date }}</span>
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
					</div>
				</div>
				<div class="form-row-inline">
					<div v-if="editing" class="form-row">
						<label class="form-label">Durum</label>
						<select v-model="form.status" class="form-input">
							<option value="draft">Taslak</option>
							<option value="ready">Hazır</option>
							<option value="sent">Gönderildi</option>
							<option value="accepted">Kabul Edildi</option>
							<option value="rejected">Reddedildi</option>
							<option value="cancelled">İptal</option>
						</select>
					</div>
					<div class="form-row" style="flex: 3">
						<label class="form-label">Not</label>
						<input v-model="form.note" type="text" class="form-input" placeholder="isteğe bağlı" />
					</div>
				</div>
			</form>
			<template #footer="{ close }">
				<Button variant="ghost" :disabled="form.processing" @click="close">İptal</Button>
				<Button type="submit" form="outgoing-invoice-form" variant="primary" :loading="form.processing">
					{{ editing ? 'Güncelle' : 'Ekle' }}
				</Button>
			</template>
		</AppModal>
	</div>
</template>

<script setup>
import { ref, inject, watch } from 'vue'
import { Head, useForm, router } from '@inertiajs/vue3'
import { Plus, Send, Pencil, Trash2 } from 'lucide-vue-next'
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
})

const columns = [
	{ key: 'invoiceNo', label: 'Fatura No' },
	{ key: 'buyerName', label: 'Alıcı' },
	{ key: 'total', label: 'Tutar' },
	{ key: 'status', label: 'Durum' },
	{ key: 'efaturaStatus', label: 'e-Fatura' },
]

const editing = ref(false)
const modalOpen = ref(false)

function emptyForm() {
	return {
		id: null,
		invoice_no: '',
		invoice_type: 'standalone',
		buyer_name: '',
		buyer_tax_number: '',
		buyer_address: '',
		issue_date: '',
		subtotal: 0,
		tax_amount: 0,
		total: 0,
		status: 'draft',
		note: '',
	}
}

const form = useForm(emptyForm())

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
		invoice_type: inv.invoiceType,
		buyer_name: inv.buyerName,
		buyer_tax_number: inv.buyerTaxNumber,
		buyer_address: inv.buyerAddress,
		issue_date: inv.issueDate,
		subtotal: inv.subtotal,
		tax_amount: inv.taxAmount,
		total: inv.total,
		status: inv.status,
		note: inv.note,
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
		form.put(`/finance/outgoing-invoices/${form.id}`, opts)
	} else {
		form.post('/finance/outgoing-invoices', opts)
	}
}

async function remove(inv) {
	const ok = await $swal.dangerConfirm({ title: 'Fatura silinsin mi?', html: `<b>${inv.invoiceNo}</b> kalıcı olarak silinecek.` })
	if (!ok) return
	router.delete(`/finance/outgoing-invoices/${inv.id}`, {
		preserveScroll: true,
		onSuccess: () => showToast?.({ type: 'warning', title: 'Fatura silindi', message: inv.invoiceNo }),
	})
}

function send(inv) {
	router.post(`/finance/outgoing-invoices/${inv.id}/send`, {}, {
		preserveScroll: true,
		onSuccess: () => showToast?.({ type: 'info', title: 'Gönderim denendi', message: 'Entegratör henüz seçilmediği için kayıt not_sent kalabilir.' }),
	})
}

function statusLabel(status) {
	return { draft: 'Taslak', ready: 'Hazır', sent: 'Gönderildi', accepted: 'Kabul Edildi', rejected: 'Reddedildi', cancelled: 'İptal' }[status] ?? status
}
function statusColor(status) {
	return { draft: 'neutral', ready: 'info', sent: 'info', accepted: 'success', rejected: 'danger', cancelled: 'danger' }[status] ?? 'neutral'
}
function efaturaLabel(status) {
	return { not_sent: 'Gönderilmedi', pending: 'Beklemede', success: 'Başarılı', failed: 'Başarısız' }[status] ?? status
}
function efaturaColor(status) {
	return { not_sent: 'neutral', pending: 'warning', success: 'success', failed: 'danger' }[status] ?? 'neutral'
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
