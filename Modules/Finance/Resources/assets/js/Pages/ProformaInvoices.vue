<template>
	<Head title="Proforma Faturalar" />
	<div class="page-proformas">
		<Breadcrumb
			:items="[
				{ label: 'Ana Sayfa', to: '/workflow', icon: 'home' },
				{ label: 'Finans', to: '/finance' },
				{ label: 'Proforma Faturalar' },
			]"
		/>

		<FinanceNav current="proformas" />

		<div class="page-header">
			<div>
				<h1 class="page-title">Proforma Faturalar</h1>
				<p class="page-subtitle"><strong>{{ proformas.length }}</strong> proforma kaydı</p>
			</div>
		</div>

		<div class="card" style="margin-bottom: 18px;">
			<div class="card-header">
				<h3>{{ editing ? 'Proformayı Düzenle' : 'Yeni Proforma' }}</h3>
			</div>
			<div class="form-section">
				<form class="form-grid" @submit.prevent="submit">
					<div class="form-row-inline">
						<div class="form-row">
							<label class="form-label">Proforma No <span class="req">*</span></label>
							<input v-model="form.proforma_no" type="text" class="form-input" placeholder="PRF-2026-001" />
							<span v-if="form.errors.proforma_no" class="form-error">{{ form.errors.proforma_no }}</span>
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
						<div class="form-row">
							<label class="form-label">Bağlı Sipariş</label>
							<select v-model="form.order_id" class="form-input">
								<option :value="null">— Standalone —</option>
								<option v-for="o in orders" :key="o.value" :value="o.value">{{ o.label }}</option>
							</select>
						</div>
					</div>
					<div class="form-row-inline">
						<div class="form-row">
							<label class="form-label">Düzenleme Tarihi <span class="req">*</span></label>
							<input v-model="form.issue_date" type="date" class="form-input" />
							<span v-if="form.errors.issue_date" class="form-error">{{ form.errors.issue_date }}</span>
						</div>
						<div class="form-row">
							<label class="form-label">Geçerlilik Tarihi <span class="req">*</span></label>
							<input v-model="form.valid_until" type="date" class="form-input" />
							<span v-if="form.errors.valid_until" class="form-error">{{ form.errors.valid_until }}</span>
						</div>
						<div class="form-row" style="flex: 2">
							<label class="form-label">Not</label>
							<input v-model="form.note" type="text" class="form-input" placeholder="isteğe bağlı" />
						</div>
					</div>

					<div v-if="!form.order_id" class="items-editor">
						<div class="items-header">
							<span class="form-label">Kalemler <span class="req">*</span></span>
							<button type="button" class="btn btn-ghost btn-sm" @click="addItem">+ Satır Ekle</button>
						</div>
						<div v-for="(item, idx) in form.items" :key="idx" class="item-row">
							<input v-model="item.description" type="text" class="form-input" placeholder="Açıklama" style="flex: 3" />
							<input v-model.number="item.qty" type="number" min="1" class="form-input" placeholder="Adet" style="flex: 1" @input="recalcItem(idx)" />
							<input v-model.number="item.unit_price" type="number" step="0.01" min="0" class="form-input" placeholder="Birim Fiyat" style="flex: 1" @input="recalcItem(idx)" />
							<input v-model.number="item.total_price" type="number" step="0.01" min="0" class="form-input" placeholder="Toplam" style="flex: 1" />
							<button type="button" class="table-action-btn danger" @click="removeItem(idx)">🗑️</button>
						</div>
						<span v-if="form.errors.items" class="form-error">{{ form.errors.items }}</span>
					</div>

					<div class="form-row-inline">
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
						<div v-if="editing" class="form-row">
							<label class="form-label">Durum</label>
							<select v-model="form.status" class="form-input">
								<option value="draft">Taslak</option>
								<option value="sent">Gönderildi</option>
								<option value="accepted">Kabul Edildi</option>
								<option value="expired">Süresi Doldu</option>
								<option value="cancelled">İptal</option>
							</select>
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
				<h3>Proforma Listesi</h3>
			</div>
			<table class="data-table">
				<thead>
					<tr>
						<th>Proforma No</th>
						<th>Alıcı</th>
						<th>Tutar</th>
						<th>Geçerlilik</th>
						<th>Durum</th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					<tr v-if="proformas.length === 0">
						<td colspan="6" class="empty-row">Kayıt bulunamadı</td>
					</tr>
					<tr v-for="p in proformas" :key="p.id">
						<td class="mono">{{ p.proformaNo }}</td>
						<td>
							{{ p.buyerName }}
							<span v-if="p.orderNo" class="dim">· sipariş {{ p.orderNo }}</span>
							<span v-else-if="p.tenantName" class="dim">· {{ p.tenantName }}</span>
						</td>
						<td class="strong">{{ formatMoney(p.total) }} <span class="dim">{{ p.currency }}</span></td>
						<td class="dim">{{ p.validUntil }}</td>
						<td><span class="badge" :class="'badge-' + p.status">{{ statusLabel(p.status) }}</span></td>
						<td>
							<div class="table-actions">
								<button
									v-if="!p.convertedInvoiceId && ['draft','sent','accepted'].includes(p.status)"
									class="table-action-btn"
									title="Faturaya dönüştür"
									@click="convert(p)"
								>➡️</button>
								<button class="table-action-btn" title="Düzenle" @click="edit(p)">✏️</button>
								<button class="table-action-btn danger" title="Sil" @click="remove(p)">🗑️</button>
							</div>
						</td>
					</tr>
				</tbody>
			</table>
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
	proformas: { type: Array, default: () => [] },
	orders: { type: Array, default: () => [] },
})

const editing = ref(false)

function emptyForm() {
	return {
		id: null,
		proforma_no: '',
		order_id: null,
		buyer_name: '',
		buyer_tax_number: '',
		issue_date: '',
		valid_until: '',
		subtotal: 0,
		tax_amount: 0,
		total: 0,
		status: 'draft',
		note: '',
		items: [{ description: '', qty: 1, unit_price: 0, total_price: 0 }],
	}
}

const form = useForm(emptyForm())

function addItem() {
	form.items.push({ description: '', qty: 1, unit_price: 0, total_price: 0 })
}
function removeItem(idx) {
	form.items.splice(idx, 1)
}
function recalcItem(idx) {
	const item = form.items[idx]
	item.total_price = Math.round((Number(item.qty) || 0) * (Number(item.unit_price) || 0) * 100) / 100
}
function recalcTotal() {
	const subtotal = Number(form.subtotal) || 0
	const tax = Number(form.tax_amount) || 0
	form.total = Math.round((subtotal + tax) * 100) / 100
}

function edit(p) {
	editing.value = true
	Object.assign(form, {
		id: p.id,
		proforma_no: p.proformaNo,
		order_id: p.orderId,
		buyer_name: p.buyerName,
		buyer_tax_number: p.buyerTaxNumber,
		issue_date: p.issueDate,
		valid_until: p.validUntil,
		subtotal: p.subtotal,
		tax_amount: p.taxAmount,
		total: p.total,
		status: p.status,
		note: p.note,
		items: p.items.length ? p.items.map(i => ({ description: i.description, qty: i.qty, unit_price: i.unitPrice, total_price: i.totalPrice })) : [{ description: '', qty: 1, unit_price: 0, total_price: 0 }],
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
			showToast?.({ type: 'success', title: editing.value ? 'Proforma güncellendi' : 'Proforma eklendi', message: form.proforma_no })
			reset()
		},
		onError: () => {
			showToast?.({ type: 'error', title: 'Kayıt başarısız', message: Object.values(form.errors)[0] || 'Doğrulama hatası.' })
		},
	}
	if (editing.value) {
		form.put(`/finance/proformas/${form.id}`, opts)
	} else {
		form.post('/finance/proformas', opts)
	}
}

async function remove(p) {
	const ok = await $swal.dangerConfirm({ title: 'Proforma silinsin mi?', html: `<b>${p.proformaNo}</b> kalıcı olarak silinecek.` })
	if (!ok) return
	router.delete(`/finance/proformas/${p.id}`, {
		preserveScroll: true,
		onSuccess: () => showToast?.({ type: 'warning', title: 'Proforma silindi', message: p.proformaNo }),
	})
}

async function convert(p) {
	const ok = await $swal.dangerConfirm({
		title: 'Faturaya dönüştürülsün mü?',
		html: `<b>${p.proformaNo}</b> için düzenlenen (satış) faturası oluşturulacak.`,
		confirmText: 'Dönüştür',
	})
	if (!ok) return
	router.post(`/finance/proformas/${p.id}/convert`, {}, {
		preserveScroll: true,
		onSuccess: () => showToast?.({ type: 'success', title: 'Faturaya dönüştürüldü', message: p.proformaNo }),
	})
}

function statusLabel(status) {
	return { draft: 'Taslak', sent: 'Gönderildi', accepted: 'Kabul Edildi', expired: 'Süresi Doldu', converted: 'Dönüştürüldü', cancelled: 'İptal' }[status] ?? status
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
.btn-sm { padding: 5px 10px; font-size: 12px; }

.items-editor { display: flex; flex-direction: column; gap: 8px; padding: 12px; background: #fafafe; border-radius: 10px; border: 1px solid #f0f0f5; }
.items-header { display: flex; align-items: center; justify-content: space-between; }
.item-row { display: flex; gap: 8px; align-items: center; }

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
.badge-accepted, .badge-converted { background: #dcfce7; color: #166534; }
.badge-draft { background: #f0f0f5; color: #555; }
.badge-sent { background: #e0f2fe; color: #0369a1; }
.badge-expired, .badge-cancelled { background: #fee2e2; color: #991b1b; }

.table-actions { display: flex; gap: 4px; justify-content: flex-end; }
.table-action-btn { background: #f3f4f6; border: none; cursor: pointer; font-size: 13px; padding: 5px 9px; border-radius: 6px; color: #6b7280; transition: all .15s; }
.table-action-btn:hover { background: rgb(var(--color-primary-soft)); color: rgb(var(--color-primary)); }
.table-action-btn.danger:hover { background: #fee2e2; color: #dc2626; }
</style>
