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

		<PageHeader title="Proforma Faturalar">
			<template #subtitle><strong>{{ proformas.length }}</strong> proforma kaydı</template>
			<template #actions>
				<Button variant="primary" with-icon @click="openCreate">
					<template #leading><Plus :size="13" /></template>
					Yeni Proforma
				</Button>
			</template>
		</PageHeader>

		<Card title="Proforma Listesi" body-class="p-0">
			<DataTable :columns="columns" :data="proformas" row-key-field="id" empty-title="Kayıt bulunamadı">
				<template #proformaNo="{ value }"><span class="mono">{{ value }}</span></template>
				<template #buyerName="{ row }">
					{{ row.buyerName }}
					<span v-if="row.orderNo" class="dim">· sipariş {{ row.orderNo }}</span>
					<span v-else-if="row.tenantName" class="dim">· {{ row.tenantName }}</span>
				</template>
				<template #total="{ row }"><span class="strong">{{ formatMoney(row.total) }}</span> <span class="dim">{{ row.currency }}</span></template>
				<template #validUntil="{ value }"><span class="dim">{{ value }}</span></template>
				<template #status="{ value }"><Badge :color="statusColor(value)" variant="tonal" :label="statusLabel(value)" /></template>
				<template #actions="{ row }">
					<button
						v-if="!row.convertedInvoiceId && ['draft','sent','accepted'].includes(row.status)"
						class="table-action-btn"
						title="Faturaya dönüştür"
						@click="convert(row)"
					><ArrowRight :size="14" /></button>
					<button class="table-action-btn" title="Düzenle" @click="openEdit(row)"><Pencil :size="14" /></button>
					<button class="table-action-btn danger" title="Sil" @click="remove(row)"><Trash2 :size="14" /></button>
				</template>
			</DataTable>
		</Card>

		<AppModal v-model="modalOpen" :title="editing ? 'Proformayı Düzenle' : 'Yeni Proforma'" size="lg">
			<form id="proforma-form" class="form-grid" @submit.prevent="submit">
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
						<Button type="button" variant="ghost" size="sm" with-icon @click="addItem">
							<template #leading><Plus :size="12" /></template>
							Satır Ekle
						</Button>
					</div>
					<div v-for="(item, idx) in form.items" :key="idx" class="item-row">
						<input v-model="item.description" type="text" class="form-input" placeholder="Açıklama" style="flex: 3" />
						<input v-model.number="item.qty" type="number" min="1" class="form-input" placeholder="Adet" style="flex: 1" @input="recalcItem(idx)" />
						<input v-model.number="item.unit_price" type="number" step="0.01" min="0" class="form-input" placeholder="Birim Fiyat" style="flex: 1" @input="recalcItem(idx)" />
						<input v-model.number="item.total_price" type="number" step="0.01" min="0" class="form-input" placeholder="Toplam" style="flex: 1" />
						<button type="button" class="table-action-btn danger" @click="removeItem(idx)"><Trash2 :size="14" /></button>
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
				</div>
			</form>
			<template #footer="{ close }">
				<Button variant="ghost" :disabled="form.processing" @click="close">İptal</Button>
				<Button type="submit" form="proforma-form" variant="primary" :loading="form.processing">
					{{ editing ? 'Güncelle' : 'Ekle' }}
				</Button>
			</template>
		</AppModal>
	</div>
</template>

<script setup>
import { ref, inject, watch } from 'vue'
import { Head, useForm, router } from '@inertiajs/vue3'
import { Plus, ArrowRight, Pencil, Trash2 } from 'lucide-vue-next'
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
	proformas: { type: Array, default: () => [] },
	orders: { type: Array, default: () => [] },
})

const columns = [
	{ key: 'proformaNo', label: 'Proforma No' },
	{ key: 'buyerName', label: 'Alıcı' },
	{ key: 'total', label: 'Tutar' },
	{ key: 'validUntil', label: 'Geçerlilik' },
	{ key: 'status', label: 'Durum' },
]

const editing = ref(false)
const modalOpen = ref(false)

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

function openCreate() {
	reset()
	modalOpen.value = true
}

function openEdit(p) {
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
			showToast?.({ type: 'success', title: editing.value ? 'Proforma güncellendi' : 'Proforma eklendi', message: form.proforma_no })
			modalOpen.value = false
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
function statusColor(status) {
	return { draft: 'neutral', sent: 'info', accepted: 'success', converted: 'success', expired: 'danger', cancelled: 'danger' }[status] ?? 'neutral'
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

.items-editor { display: flex; flex-direction: column; gap: 8px; padding: 12px; background: rgb(var(--color-bg) / .5); border-radius: 10px; border: 1px solid rgb(var(--color-border)); }
.items-header { display: flex; align-items: center; justify-content: space-between; }
.item-row { display: flex; gap: 8px; align-items: center; }

.dim { font-size: 12px; color: rgb(var(--color-muted)); }
.mono { font-family: 'SF Mono', Menlo, Consolas, monospace; font-size: 12px; }
.strong { font-weight: 700; color: rgb(var(--color-ink)); }

.table-action-btn { display: inline-flex; align-items: center; justify-content: center; background: rgb(var(--color-bg)); border: none; cursor: pointer; padding: 6px; border-radius: 6px; color: rgb(var(--color-muted)); transition: all .15s; }
.table-action-btn:hover { background: rgb(var(--color-primary-soft)); color: rgb(var(--color-primary)); }
.table-action-btn.danger:hover { background: rgb(var(--color-danger) / .12); color: rgb(var(--color-danger)); }
</style>
