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

		<div class="page-header">
			<div>
				<h1 class="page-title">Alınan Faturalar</h1>
				<p class="page-subtitle"><strong>{{ invoices.length }}</strong> tedarikçi faturası</p>
			</div>
		</div>

		<div class="card" style="margin-bottom: 18px;">
			<div class="card-header">
				<h3>{{ editing ? 'Faturayı Düzenle' : 'Yeni Fatura' }}</h3>
			</div>
			<div class="form-section">
				<form class="form-grid" @submit.prevent="submit">
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
						<div class="form-row form-row-actions">
							<label class="form-label">&nbsp;</label>
							<div class="btn-group">
								<button type="submit" class="btn btn-primary" :disabled="form.processing">
									{{ editing ? 'Güncelle' : 'Ekle' }}
								</button>
								<button v-if="editing" type="button" class="btn btn-ghost" @click="reset">Vazgeç</button>
							</div>
						</div>
					</div>
				</form>
			</div>
		</div>

		<div class="card">
			<div class="card-header">
				<h3>Fatura Listesi</h3>
			</div>
			<div class="table-scroll">
			<table class="data-table">
				<thead>
					<tr>
						<th>Fatura No</th>
						<th>Tedarikçi</th>
						<th>Kategori</th>
						<th>Tutar</th>
						<th>Vade</th>
						<th>Durum</th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					<tr v-if="invoices.length === 0">
						<td colspan="7" class="empty-row">Kayıt bulunamadı</td>
					</tr>
					<tr v-for="inv in invoices" :key="inv.id">
						<td class="mono">{{ inv.invoiceNo }}</td>
						<td>
							{{ inv.supplierName }}
							<span v-if="inv.productionOrderCode" class="dim">· {{ inv.productionOrderCode }}</span>
						</td>
						<td class="dim">{{ inv.category ?? '—' }}</td>
						<td class="strong">{{ formatMoney(inv.total) }} <span class="dim">{{ inv.currency }}</span></td>
						<td class="dim">{{ inv.dueDate ?? '—' }}</td>
						<td><span class="badge" :class="'badge-' + inv.status">{{ statusLabel(inv.status) }}</span></td>
						<td>
							<div class="table-actions">
								<a v-if="inv.hasFile" class="table-action-btn" title="Dosyayı indir" :href="`/finance/supplier-invoices/${inv.id}/file`" target="_blank">📎</a>
								<button v-if="inv.status !== 'paid'" class="table-action-btn" title="Ödendi işaretle" @click="markPaid(inv)">✅</button>
								<button class="table-action-btn" title="Düzenle" @click="edit(inv)">✏️</button>
								<button class="table-action-btn danger" title="Sil" @click="remove(inv)">🗑️</button>
							</div>
						</td>
					</tr>
				</tbody>
			</table>
			</div>
		</div>
	</div>
</template>

<script setup>
import { ref, inject } from 'vue'
import { Head, useForm, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Breadcrumb from '@/Components/Breadcrumb.vue'
import FinanceNav from '../Components/FinanceNav.vue'

defineOptions({ layout: AppLayout })

const $swal = inject('$swal')
const showToast = inject('showToast')

defineProps({
	invoices: { type: Array, default: () => [] },
	productionOrders: { type: Array, default: () => [] },
})

const editing = ref(false)

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

function edit(inv) {
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
}

function reset() {
	editing.value = false
	form.reset()
	Object.assign(form, emptyForm())
	form.clearErrors()
}

function submit() {
	const opts = {
		preserveScroll: true,
		onSuccess: () => {
			showToast?.({ type: 'success', title: editing.value ? 'Fatura güncellendi' : 'Fatura eklendi', message: form.invoice_no })
			reset()
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

function formatMoney(value) {
	return new Intl.NumberFormat('tr-TR', { style: 'currency', currency: 'TRY' }).format(value ?? 0)
}
</script>

<style scoped>
.page-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 20px; gap: 16px; }
.page-title { font-size: 22px; font-weight: 700; color: #1a1a2e; line-height: 1.2; }
.page-subtitle { font-size: 13px; color: #888; margin-top: 4px; }

.card { background: #fff; border-radius: 16px; border: 1px solid #ebebf0; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,.04); }
.card-header { padding: 14px 18px; display: flex; align-items: center; gap: 12px; border-bottom: 1px solid #f0f0f5; }
.card-header h3 { font-size: 15px; font-weight: 700; color: #1a1a2e; }

.form-section { padding: 16px 18px; }
.form-grid { display: flex; flex-direction: column; gap: 12px; }
.form-row-inline { display: flex; gap: 12px; flex-wrap: wrap; align-items: flex-end; }
.form-row { display: flex; flex-direction: column; gap: 6px; flex: 1; min-width: 140px; }
.form-row-actions { flex: 0 0 auto; min-width: unset; }
.form-label { font-size: 12px; font-weight: 600; color: #1a1a2e; }
.form-label .req { color: #ef4444; }
.form-input { padding: 9px 12px; border: 1px solid #e8e8f0; border-radius: 8px; font-family: inherit; font-size: 13px; color: #1a1a2e; background: #fff; outline: none; transition: border-color .15s; }
.form-input:focus { border-color: rgb(var(--color-primary)); }
.form-error { font-size: 11.5px; color: #ef4444; }
.btn-group { display: flex; gap: 8px; }

.data-table { width: 100%; border-collapse: separate; border-spacing: 0; }
.data-table thead tr { background: #f8f8fc; }
.data-table th { text-align: left; padding: 12px 16px; font-size: 11px; font-weight: 600; color: #aaa; border-bottom: 1px solid #f0f0f5; text-transform: uppercase; letter-spacing: 0.04em; }
.data-table td { padding: 12px 16px; font-size: 13px; color: #444; border-bottom: 1px solid #f5f5f8; vertical-align: middle; }
.data-table tr:last-child td { border-bottom: none; }
.data-table tr:hover td { background: #fafafe; }
.empty-row { text-align: center !important; color: #aaa; padding: 32px 0 !important; font-style: italic; }
.dim { font-size: 12px; color: #888; }
.mono { font-family: 'SF Mono', Menlo, Consolas, monospace; font-size: 12px; }
.strong { font-weight: 700; color: #1a1a2e; }

.badge { display: inline-block; padding: 3px 9px; border-radius: 6px; font-size: 11px; font-weight: 700; background: #f0f0f5; color: #555; }
.badge-paid { background: #dcfce7; color: #166534; }
.badge-unpaid { background: #fef9c3; color: #854d0e; }
.badge-partially_paid { background: #e0f2fe; color: #0369a1; }
.badge-cancelled { background: #fee2e2; color: #991b1b; }

.table-actions { display: flex; gap: 4px; justify-content: flex-end; }
.table-action-btn { background: #f3f4f6; border: none; cursor: pointer; font-size: 13px; padding: 5px 9px; border-radius: 6px; color: #6b7280; transition: all .15s; }
.table-action-btn:hover { background: rgb(var(--color-primary-soft)); color: rgb(var(--color-primary)); }
.table-action-btn.danger:hover { background: #fee2e2; color: #dc2626; }
</style>
