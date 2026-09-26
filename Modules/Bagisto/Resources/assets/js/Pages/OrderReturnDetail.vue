<script setup>
import { ref, computed, inject } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { OzPageHeader, OzModuleTabs, OzCard, OzTable, OzPill, OzButton, OzModal, OzField, OzInput, OzTextarea, OzAlert, OzStepper } from '@/Components/Oz'

defineOptions({ layout: AppLayout })

const props = defineProps({
	orderReturn: { type: Object, required: true },
	refundableCap: { type: Number, default: 0 },
	refundMethod: { type: String, default: null },
	orderInvoiced: { type: Boolean, default: null },
	statusLabels: { type: Object, default: () => ({}) },
	invoiceStatusLabels: { type: Object, default: () => ({}) },
	refundMethodLabels: { type: Object, default: () => ({}) },
})

const page = usePage()
const showToast = inject('showToast', null)
const permissions = computed(() => page.props.auth?.permissions ?? [])
const canManage = computed(() => permissions.value.includes('order-return.manage'))
const canRefund = computed(() => permissions.value.includes('order-return.refund'))

const r = computed(() => props.orderReturn)

const STATUS_TONE = { requested: 'warning', approved: 'info', rejected: 'danger', refunded: 'success', cancelled: 'neutral' }
const INVOICE_TONE = { awaiting: 'warning', pending: 'accent', accepted: 'success', rejected: 'danger' }
const INSPECTION = { approved: ['Uygun', 'success'], rejected: ['Uygun değil', 'danger'], pending: ['Devam ediyor', 'neutral'] }
const PAYMENT_METHODS = { card: 'Kredi Kartı', cari: 'Cari Hesap', bank: 'Havale / EFT', cod: 'Kapıda Ödeme' }
const CARGO_METHODS = { contracted: 'Ozm Kids anlaşmalı kargo', own: 'Bayi kendi kargosuyla' }

const itemColumns = [
	{ key: 'item', label: 'Ürün' },
	{ key: 'variant', label: 'Renk/Beden' },
	{ key: 'qty', label: 'Adet', align: 'right' },
	{ key: 'unit_price', label: 'Birim (toptan)', align: 'right', class: 'oz-cell-amount' },
	{ key: 'line_total', label: 'Toplam', align: 'right', class: 'oz-cell-amount' },
]

const invoiceCleared = computed(() => !r.value.invoice_required || r.value.invoice_status === 'accepted')

// Akış: Talep → Ekspertiz → Karar → (İade Faturası) → Ödeme
const steps = computed(() => [
	{ key: 'request', label: 'Talep', description: r.value.created_at?.slice(0, 10) },
	{ key: 'inspection', label: 'Ekspertiz', description: r.value.inspection ? (INSPECTION[r.value.inspection.result]?.[0] ?? '') : 'Bagisto\'da' },
	{ key: 'decision', label: 'Karar', description: r.value.decided_at ? r.value.decided_at.slice(0, 10) : '' },
	...(r.value.invoice_required ? [{ key: 'invoice', label: 'İade Faturası', description: props.invoiceStatusLabels[r.value.invoice_status] ?? '' }] : []),
	{ key: 'refund', label: 'Ödeme', description: r.value.refunded_at?.slice(0, 10) ?? '' },
])

const currentStep = computed(() => {
	const s = r.value.status
	if (s === 'refunded') return 'refund'
	if (s === 'approved') return invoiceCleared.value ? 'refund' : 'invoice'
	if (s === 'requested') return r.value.inspection && r.value.inspection.result !== 'pending' ? 'decision' : 'inspection'
	return 'decision'
})

// --- Karar ---
const decisionOpen = ref(null) // 'approve' | 'reject' | null
const decisionAmount = ref('')
const decisionNote = ref('')

function openDecision(kind) {
	decisionOpen.value = kind
	decisionNote.value = ''
	const suggested = Number(r.value.inspection?.refund_amount ?? 0) || r.value.requested_amount
	decisionAmount.value = String(Math.min(suggested || props.refundableCap, props.refundableCap))
}

// --- Fatura kontrolü ---
const invoiceOpen = ref(null) // 'accept' | 'reject' | null
const invoiceNote = ref('')

function openInvoiceReview(kind) {
	invoiceOpen.value = kind
	invoiceNote.value = ''
}

// --- Para iadesi ---
const refundOpen = ref(false)
const refundReference = ref('')

const busy = ref(false)

function post(url, data, onDone) {
	busy.value = true
	router.post(url, data, {
		preserveScroll: true,
		onSuccess: () => onDone?.(),
		onError: (errs) => showToast?.({ type: 'error', title: 'İşlem yapılamadı', message: Object.values(errs)[0] || 'Sunucu hatası.' }),
		onFinish: () => { busy.value = false },
	})
}

function submitDecision() {
	const url = `/order-returns/${r.value.id}/${decisionOpen.value}`
	const data = decisionOpen.value === 'approve'
		? { amount: decisionAmount.value, note: decisionNote.value || null }
		: { note: decisionNote.value }
	post(url, data, () => { decisionOpen.value = null })
}

function submitInvoiceReview() {
	post(`/order-returns/${r.value.id}/invoice-review`, { accept: invoiceOpen.value === 'accept', note: invoiceNote.value || null }, () => { invoiceOpen.value = null })
}

function submitRefund() {
	post(`/order-returns/${r.value.id}/refund`, { reference: refundReference.value || null }, () => { refundOpen.value = false })
}

const refundHint = computed(() => ({
	cari: 'Tutar bayinin cari hesabına alacak olarak yazılır.',
	card: 'Tutar iyzico üzerinden bayinin ödeme yaptığı karta iade edilir. Bu işlem geri alınamaz.',
	bank: 'Havaleyi bankadan yaptıktan sonra dekont / işlem numarasını girin.',
}[props.refundMethod] ?? ''))

function formatMoney(v) {
	return new Intl.NumberFormat('tr-TR', { style: 'currency', currency: 'TRY', maximumFractionDigits: 2 }).format(Number(v ?? 0))
}

function formatDate(v) {
	return v ? v.slice(0, 16).replace('T', ' ') : '—'
}
</script>

<template>
	<Head :title="`İade #${r.rma_id}`" />

	<OzPageHeader :title="`İade #${r.rma_id}`">
		<template #subtitle>{{ r.tenant?.name ?? '—' }} · {{ r.created_at?.slice(0, 10) }}</template>
		<template #actions>
			<OzPill :tone="STATUS_TONE[r.status] ?? 'neutral'">{{ statusLabels[r.status] ?? r.status }}</OzPill>
			<template v-if="canManage && r.status === 'requested'">
				<OzButton variant="danger" icon="x" @click="openDecision('reject')">Reddet</OzButton>
				<OzButton variant="primary" icon="check" :disabled="!r.order" @click="openDecision('approve')">Onayla</OzButton>
			</template>
			<template v-if="canManage && r.status === 'approved' && r.invoice_status === 'pending'">
				<OzButton variant="danger" icon="x" @click="openInvoiceReview('reject')">Faturayı Reddet</OzButton>
				<OzButton variant="primary" icon="check" @click="openInvoiceReview('accept')">Faturayı Kabul Et</OzButton>
			</template>
			<OzButton
				v-if="canRefund && r.status === 'approved' && invoiceCleared"
				variant="primary"
				icon="wallet"
				@click="refundOpen = true; refundReference = ''"
			>İadeyi Onayla</OzButton>
		</template>
	</OzPageHeader>
	<OzModuleTabs />

	<OzCard v-if="!['rejected', 'cancelled'].includes(r.status)" class="stepper-card">
		<div class="stepper-wrap">
			<OzStepper :steps="steps" :model-value="currentStep" />
		</div>
	</OzCard>

	<OzAlert v-if="!r.order" tone="warning" title="Sipariş eşleşmedi" class="gap">
		Bagisto sipariş #{{ r.bagisto_order_id }} SaaS'ta bulunamadı; tutar hesaplanamadı ve karar verilemez. Siparişin webhook'la gelip gelmediğini kontrol edin.
	</OzAlert>
	<OzAlert v-else-if="r.status === 'approved' && !invoiceCleared" tone="info" title="Ödeme iade faturasını bekliyor" class="gap">
		Sipariş tarafımızca faturalandırıldığı için bayinin iade faturası kabul edilmeden ödeme yapılamaz.
		<template v-if="r.invoice_status === 'awaiting'"> Bayinin faturayı portaldan yüklemesi bekleniyor.</template>
		<template v-else-if="r.invoice_status === 'rejected'"> Reddedilen faturanın yerine yenisi bekleniyor.</template>
	</OzAlert>

	<div class="grid">
		<div class="col-main">
			<OzCard title="İade Edilen Ürünler">
				<OzTable :columns="itemColumns" :rows="r.items" row-key="sku" min-width="520px" empty-title="Kalem yok">
					<template #cell-item="{ row }">
						<div class="item-cell">
							<img v-if="row.image" :src="`/storage/${row.image}`" class="item-thumb" alt="" />
							<div>
								<div class="item-name">{{ row.name ?? row.sku }}</div>
								<div class="item-sku mono">{{ row.sku }}</div>
							</div>
						</div>
					</template>
					<template #cell-variant="{ row }">{{ [row.color, row.size].filter(Boolean).join(' / ') || '—' }}</template>
					<template #cell-unit_price="{ value }"><span class="mono">{{ formatMoney(value) }}</span></template>
					<template #cell-line_total="{ value }"><span class="mono">{{ formatMoney(value) }}</span></template>
				</OzTable>
				<div class="totals">
					<div class="total-row"><span>Talep edilen (toptan)</span><span class="mono">{{ formatMoney(r.requested_amount) }}</span></div>
					<div v-if="r.approved_amount !== null" class="total-row grand"><span>Onaylanan</span><span class="mono">{{ formatMoney(r.approved_amount) }}</span></div>
				</div>
			</OzCard>

			<OzCard title="Bayinin Talebi">
				<div class="kv">
					<div class="kv-row"><span>Gerekçe</span><span>{{ r.reason ?? '—' }}</span></div>
					<div v-if="r.resolution" class="kv-row"><span>İstenen çözüm</span><span>{{ r.resolution }}</span></div>
					<div v-if="r.cargo_method" class="kv-row"><span>İade kargosu</span><span>{{ CARGO_METHODS[r.cargo_method] ?? r.cargo_method }}</span></div>
					<div v-if="r.bagisto_status" class="kv-row"><span>Bagisto durumu</span><span>{{ r.bagisto_status }}</span></div>
				</div>
				<div v-if="r.information" class="info-block">
					<div class="info-label">Açıklama</div>
					<div class="info-line note">{{ r.information }}</div>
				</div>
				<div v-if="r.images.length" class="info-block">
					<div class="info-label">Bayinin fotoğrafları</div>
					<div class="photos">
						<a v-for="(src, i) in r.images" :key="i" :href="src" target="_blank" rel="noopener"><img :src="src" alt="" /></a>
					</div>
				</div>
			</OzCard>

			<OzCard title="Atölye Ekspertizi" subtitle="Bagisto'da yapılır">
				<p v-if="!r.inspection" class="empty">Henüz ekspertiz kaydı yok.</p>
				<template v-else>
					<div class="kv">
						<div class="kv-row">
							<span>Sonuç</span>
							<OzPill :tone="INSPECTION[r.inspection.result]?.[1] ?? 'neutral'">{{ INSPECTION[r.inspection.result]?.[0] ?? r.inspection.result }}</OzPill>
						</div>
						<div v-if="r.inspection.total" class="kv-row"><span>Kriterler</span><span>{{ r.inspection.passed ?? 0 }} / {{ r.inspection.total }} uygun</span></div>
						<div v-if="r.inspection.refund_amount" class="kv-row"><span>Önerilen iade</span><span class="mono">{{ formatMoney(r.inspection.refund_amount) }}</span></div>
						<div v-if="r.inspection.inspector_name" class="kv-row"><span>Eksper</span><span>{{ r.inspection.inspector_name }}<template v-if="r.inspection.inspector_title"> · {{ r.inspection.inspector_title }}</template></span></div>
						<div v-if="r.inspection.stamp_no" class="kv-row"><span>Mühür no</span><span class="mono">{{ r.inspection.stamp_no }}</span></div>
						<div v-if="r.inspection.inspected_at" class="kv-row"><span>Tarih</span><span>{{ formatDate(r.inspection.inspected_at) }}</span></div>
					</div>
					<div v-if="r.inspection.summary" class="info-block">
						<div class="info-label">Özet</div>
						<div class="info-line note">{{ r.inspection.summary }}</div>
					</div>
					<div v-if="r.inspection.criteria?.length" class="info-block">
						<div class="info-label">Kriterler</div>
						<ul class="criteria">
							<li v-for="c in r.inspection.criteria" :key="c.key">
								<OzPill :tone="c.passed === true ? 'success' : c.passed === false ? 'danger' : 'neutral'">{{ c.passed === true ? 'Uygun' : c.passed === false ? 'Uygun değil' : '—' }}</OzPill>
								<span>{{ c.label }}<template v-if="c.note"> — <i>{{ c.note }}</i></template></span>
							</li>
						</ul>
					</div>
					<div v-if="r.inspection.images?.length" class="info-block">
						<div class="info-label">Ekspertiz fotoğrafları</div>
						<div class="photos">
							<a v-for="(img, i) in r.inspection.images" :key="i" :href="img.url" target="_blank" rel="noopener" :title="img.caption"><img :src="img.url" :alt="img.caption ?? ''" /></a>
						</div>
					</div>
				</template>
			</OzCard>
		</div>

		<div class="col-side">
			<OzCard title="Sipariş">
				<div v-if="r.order" class="kv">
					<div class="kv-row"><span>Sipariş no</span><Link :href="`/orders/${r.order.id}`" class="mono plain-link">{{ r.order.order_no }}</Link></div>
					<div class="kv-row"><span>Tutar</span><span class="mono">{{ formatMoney(r.order.total) }}</span></div>
					<div class="kv-row"><span>Ödeme</span><span>{{ PAYMENT_METHODS[r.order.payment_method] ?? r.order.payment_method ?? '—' }}</span></div>
					<div class="kv-row"><span>Faturalandı mı</span><span>{{ orderInvoiced ? 'Evet' : 'Hayır' }}</span></div>
					<div class="kv-row"><span>İade edilebilir</span><span class="mono">{{ formatMoney(refundableCap) }}</span></div>
				</div>
				<p v-else class="empty">SaaS'ta eşleşen sipariş yok.</p>
			</OzCard>

			<OzCard title="Karar">
				<p v-if="!r.decided_at" class="empty">Henüz karar verilmedi.</p>
				<div v-else class="kv">
					<div class="kv-row"><span>Karar</span><OzPill :tone="r.status === 'rejected' ? 'danger' : 'success'">{{ r.status === 'rejected' ? 'Reddedildi' : 'Onaylandı' }}</OzPill></div>
					<div class="kv-row"><span>Veren</span><span>{{ r.decided_by ?? '—' }}</span></div>
					<div class="kv-row"><span>Tarih</span><span>{{ formatDate(r.decided_at) }}</span></div>
				</div>
				<div v-if="r.decision_note" class="info-block">
					<div class="info-label">Not</div>
					<div class="info-line note">{{ r.decision_note }}</div>
				</div>
			</OzCard>

			<OzCard v-if="r.invoice_required" title="İade Faturası">
				<div class="kv">
					<div class="kv-row"><span>Durum</span><OzPill :tone="INVOICE_TONE[r.invoice_status] ?? 'neutral'">{{ invoiceStatusLabels[r.invoice_status] ?? '—' }}</OzPill></div>
					<template v-if="r.invoice_no">
						<div class="kv-row"><span>Fatura no</span><span class="mono">{{ r.invoice_no }}</span></div>
						<div class="kv-row"><span>Fatura tarihi</span><span>{{ r.invoice_date }}</span></div>
						<div class="kv-row">
							<span>Tutar</span>
							<span class="mono" :class="{ mismatch: Number(r.invoice_amount) !== Number(r.approved_amount) }">{{ formatMoney(r.invoice_amount) }}</span>
						</div>
						<div class="kv-row"><span>Yüklendi</span><span>{{ formatDate(r.invoice_uploaded_at) }}</span></div>
					</template>
				</div>
				<p v-if="r.invoice_no && Number(r.invoice_amount) !== Number(r.approved_amount)" class="warn-text">
					Fatura tutarı onaylanan iade tutarından ({{ formatMoney(r.approved_amount) }}) farklı.
				</p>
				<div v-if="r.invoice_has_file" class="info-block">
					<a :href="`/order-returns/${r.id}/invoice`" class="download-link">Faturayı indir</a>
				</div>
				<div v-if="r.invoice_review_note" class="info-block">
					<div class="info-label">Kontrol notu<template v-if="r.invoice_reviewed_by"> · {{ r.invoice_reviewed_by }}</template></div>
					<div class="info-line note">{{ r.invoice_review_note }}</div>
				</div>
			</OzCard>

			<OzCard title="Ödeme">
				<div v-if="r.refunded_at" class="kv">
					<div class="kv-row"><span>Yöntem</span><span>{{ refundMethodLabels[r.refund_method] ?? r.refund_method }}</span></div>
					<div class="kv-row"><span>Referans</span><span class="mono">{{ r.refund_reference }}</span></div>
					<div class="kv-row"><span>Yapan</span><span>{{ r.refunded_by ?? '—' }}</span></div>
					<div class="kv-row"><span>Tarih</span><span>{{ formatDate(r.refunded_at) }}</span></div>
				</div>
				<div v-else class="kv">
					<div class="kv-row"><span>İade yolu</span><span>{{ refundMethodLabels[refundMethod] ?? '—' }}</span></div>
				</div>
			</OzCard>
		</div>
	</div>

	<OzModal
		:open="decisionOpen !== null"
		:title="decisionOpen === 'approve' ? 'İadeyi Onayla' : 'İadeyi Reddet'"
		size="sm"
		:tone="decisionOpen === 'reject' ? 'danger' : 'accent'"
		:persistent="busy"
		@update:open="(v) => { if (!v) decisionOpen = null }"
	>
		<template v-if="decisionOpen === 'approve'">
			<OzField label="İade tutarı" :hint="`En fazla ${formatMoney(refundableCap)}`" required>
				<OzInput v-model="decisionAmount" type="number" step="0.01" min="0" :max="refundableCap" suffix="₺" />
			</OzField>
			<p v-if="orderInvoiced" class="modal-text">Sipariş faturalandırılmış: onaydan sonra bayiden iade faturası istenecek.</p>
		</template>
		<OzField :label="decisionOpen === 'reject' ? 'Red gerekçesi (bayiye iletilir)' : 'Not (bayiye iletilir, opsiyonel)'" :required="decisionOpen === 'reject'">
			<OzTextarea v-model="decisionNote" :rows="3" />
		</OzField>
		<template #footer>
			<OzButton :disabled="busy" @click="decisionOpen = null">Vazgeç</OzButton>
			<OzButton
				:variant="decisionOpen === 'reject' ? 'danger' : 'primary'"
				:loading="busy"
				:disabled="decisionOpen === 'reject' ? !decisionNote.trim() : !(Number(decisionAmount) > 0)"
				@click="submitDecision"
			>{{ decisionOpen === 'reject' ? 'Reddet' : 'Onayla' }}</OzButton>
		</template>
	</OzModal>

	<OzModal
		:open="invoiceOpen !== null"
		:title="invoiceOpen === 'accept' ? 'İade Faturasını Kabul Et' : 'İade Faturasını Reddet'"
		size="sm"
		:tone="invoiceOpen === 'reject' ? 'danger' : 'accent'"
		:persistent="busy"
		@update:open="(v) => { if (!v) invoiceOpen = null }"
	>
		<p class="modal-text">
			Fatura no <b>{{ r.invoice_no }}</b>, tutar <b>{{ formatMoney(r.invoice_amount) }}</b>
			(onaylanan iade: {{ formatMoney(r.approved_amount) }}).
		</p>
		<OzField :label="invoiceOpen === 'reject' ? 'Red gerekçesi (bayiye iletilir)' : 'Not (opsiyonel)'" :required="invoiceOpen === 'reject'">
			<OzTextarea v-model="invoiceNote" :rows="3" />
		</OzField>
		<template #footer>
			<OzButton :disabled="busy" @click="invoiceOpen = null">Vazgeç</OzButton>
			<OzButton
				:variant="invoiceOpen === 'reject' ? 'danger' : 'primary'"
				:loading="busy"
				:disabled="invoiceOpen === 'reject' && !invoiceNote.trim()"
				@click="submitInvoiceReview"
			>{{ invoiceOpen === 'reject' ? 'Reddet' : 'Kabul Et' }}</OzButton>
		</template>
	</OzModal>

	<OzModal v-model:open="refundOpen" title="İade Ödemesini Yap" size="sm" tone="warning" :persistent="busy">
		<p class="modal-text">
			<b>{{ formatMoney(r.approved_amount) }}</b> tutarındaki iade
			<b>{{ refundMethodLabels[refundMethod] ?? refundMethod }}</b> yoluyla yapılacak. {{ refundHint }}
		</p>
		<OzField v-if="refundMethod === 'bank'" label="Dekont / işlem numarası" required>
			<OzInput v-model="refundReference" placeholder="ör. EFT sorgu no" />
		</OzField>
		<template #footer>
			<OzButton :disabled="busy" @click="refundOpen = false">Vazgeç</OzButton>
			<OzButton variant="primary" :loading="busy" :disabled="refundMethod === 'bank' && !refundReference.trim()" @click="submitRefund">İadeyi Onayla</OzButton>
		</template>
	</OzModal>
</template>

<style scoped>
.grid { display: grid; grid-template-columns: 2fr 1fr; gap: 16px; align-items: start; }
.col-main, .col-side { display: flex; flex-direction: column; gap: 16px; }
.gap { margin-bottom: 16px; }
.stepper-card { margin-bottom: 16px; }
.stepper-wrap { padding: 16px 18px; overflow-x: auto; }
.mono { font-family: ui-monospace, monospace; font-size: 12px; }
.plain-link { color: var(--oz-accent); text-decoration: none; }
.plain-link:hover { text-decoration: underline; }

.item-cell { display: flex; align-items: center; gap: 10px; }
.item-thumb { width: 40px; height: 40px; border-radius: 8px; object-fit: cover; background: var(--oz-surface-2); }
.item-name { font-weight: 600; color: var(--oz-ink-soft); font-size: 12.5px; }
.item-sku { color: var(--oz-muted); font-size: 11px; }

.totals { margin: 0 18px 16px; border-top: 1px dashed var(--oz-border); padding-top: 12px; }
.total-row { display: flex; justify-content: space-between; padding: 3px 0; font-size: 13px; color: var(--oz-muted); }
.total-row.grand { font-weight: 700; color: var(--oz-ink-soft); font-size: 15px; margin-top: 6px; }

.kv { margin: 0 18px 16px; }
.kv-row { display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 4px 0; font-size: 13px; color: var(--oz-muted); }
.kv-row > span:last-child, .kv-row > a { color: var(--oz-ink-soft); text-align: right; }
.mismatch { color: var(--oz-danger, var(--oz-ink-soft)); font-weight: 700; }
.warn-text { margin: 0 18px 12px; font-size: 12.5px; color: var(--oz-warning, var(--oz-muted)); }

.info-block { margin: 0 18px 16px; padding-top: 12px; border-top: 1px dashed var(--oz-border); }
.info-label { font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; color: var(--oz-muted); margin-bottom: 6px; }
.info-line { font-size: 13px; color: var(--oz-ink-soft); line-height: 1.5; }
.info-line.note { white-space: pre-line; }

.photos { display: flex; flex-wrap: wrap; gap: 8px; }
.photos img { width: 84px; height: 84px; object-fit: cover; border-radius: 8px; border: 1px solid var(--oz-border); background: var(--oz-surface-2); }

.criteria { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 6px; font-size: 12.5px; color: var(--oz-ink-soft); }
.criteria li { display: flex; align-items: flex-start; gap: 8px; }

.download-link { color: var(--oz-accent); font-size: 13px; font-weight: 600; text-decoration: none; }
.download-link:hover { text-decoration: underline; }

.modal-text { font-size: 13.5px; color: var(--oz-muted); margin: 0 0 14px; }
.empty { color: var(--oz-muted); font-style: italic; font-size: 13px; margin: 0 18px 16px; }

@media (max-width: 900px) { .grid { grid-template-columns: 1fr; } }
</style>
