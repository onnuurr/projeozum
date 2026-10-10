<script setup>
import { ref, computed, inject } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { OzPageHeader, OzModuleTabs, OzCard, OzTable, OzPill, OzTimeline, OzButton, OzModal, OzField, OzTextarea } from '@/Components/Oz'

defineOptions({ layout: AppLayout })

const props = defineProps({
	order: { type: Object, required: true },
	statuses: { type: Object, default: () => ({}) },
	allowedTransitions: { type: Array, default: () => [] },
	ledger: { type: Array, default: () => [] },
	returns: { type: Array, default: () => [] },
	returnStatuses: { type: Object, default: () => ({}) },
	refundedAmount: { type: Number, default: 0 },
})

const RETURN_TONE = { requested: 'warning', approved: 'info', rejected: 'danger', refunded: 'success', cancelled: 'neutral' }

// Siparişin iade durumu: ödenmiş iadeler toplamı sipariş tutarına ulaştıysa tam, değilse kısmi.
const returnState = computed(() => {
	if (props.refundedAmount <= 0) return null
	return props.refundedAmount >= Number(props.order.total) - 0.009
		? { label: 'İade Edildi', tone: 'danger' }
		: { label: 'Kısmi İade', tone: 'warning' }
})

const showToast = inject('showToast', null)
const page = usePage()

const canManage = computed(() => (page.props.auth?.permissions ?? []).includes('order.manage'))

const STATUS_TONE = { pending: 'warning', confirmed: 'info', preparing: 'accent', shipped: 'info', delivered: 'success', cancelled: 'danger' }

const itemColumns = [
	{ key: 'item', label: 'Ürün' },
	{ key: 'variant', label: 'Renk/Beden' },
	{ key: 'qty', label: 'Adet', align: 'right' },
	{ key: 'unit_price', label: 'Birim', align: 'right', class: 'oz-cell-amount' },
	{ key: 'total_price', label: 'Toplam', align: 'right', class: 'oz-cell-amount' },
]
const ledgerColumns = [
	{ key: 'created_at', label: 'Tarih' },
	{ key: 'type', label: 'Tür' },
	{ key: 'reason', label: 'Sebep' },
	{ key: 'amount', label: 'Tutar', align: 'right', class: 'oz-cell-amount' },
	{ key: 'balance_after', label: 'Bakiye', align: 'right', class: 'oz-cell-amount' },
]

const ORDER_TYPES = { b2c: 'B2C', dropship: 'Dropship' }
const PAYMENT_METHODS = { card: 'Kredi Kartı', cari: 'Cari Hesap', bank: 'Havale / EFT', cod: 'Kapıda Ödeme' }
const SHIPPING_METHODS = { cargo: 'Kargo' }
const SOURCES = { bagisto: 'Bayi Mağazası (Bagisto)' }

// shipping_info serbest JSON (ör. Bagisto: { source, shipping_method, address }); adres
// dizi ya da metin olabilir — bilinen alanlar okunur, gerisi yok sayılır.
const shipping = computed(() => props.order.shipping_info ?? {})
const addressLines = computed(() => {
	const a = shipping.value.address
	if (!a) return []
	if (typeof a === 'string') return [a]
	const street = Array.isArray(a.address) ? a.address.join(' ') : (a.address ?? a.address1 ?? a.line1)
	return [
		[a.first_name, a.last_name].filter(Boolean).join(' ') || a.name,
		a.company_name,
		street,
		[a.postcode, a.city, a.state].filter(Boolean).join(' '),
		a.country,
		a.phone,
		a.email,
	].filter(Boolean)
})
const hasDeliveryInfo = computed(() => !!(props.order.order_type || props.order.payment_method || shipping.value.source
	|| shipping.value.shipping_method || addressLines.value.length || props.order.note))

const timelineItems = computed(() => props.order.histories.map((h) => ({
	id: h.id,
	text: `${props.statuses?.[h.from_status] ?? h.from_status ?? 'Başlangıç'} → ${props.statuses?.[h.to_status] ?? h.to_status}`,
	sub: `${h.user ?? 'Sistem'} · ${h.created_at?.slice(0, 16).replace('T', ' ')}${h.note ? ' — ' + h.note : ''}`,
})))

const confirmOpen = ref(false)
const pendingTo = ref(null)
const note = ref('')
const busy = ref(false)

const modalTitle = computed(() => pendingTo.value === 'cancelled' ? 'Siparişi İptal Et' : 'Durum Güncelle')

function promptTransition(to) {
	pendingTo.value = to
	note.value = ''
	confirmOpen.value = true
}

function submitTransition() {
	busy.value = true
	router.put(`/orders/${props.order.id}/status`, { status: pendingTo.value, note: note.value || null }, {
		preserveScroll: true,
		onSuccess: () => {
			confirmOpen.value = false
			showToast?.({ type: 'success', title: 'Durum güncellendi' })
		},
		onError: (errs) => showToast?.({ type: 'error', title: 'Geçiş başarısız', message: Object.values(errs)[0] || 'Sunucu hatası.' }),
		onFinish: () => { busy.value = false },
	})
}

function formatMoney(v) {
	return new Intl.NumberFormat('tr-TR', { style: 'currency', currency: 'TRY', maximumFractionDigits: 2 }).format(Number(v ?? 0))
}

// Siparişteki farklı KDV oranları (ör. " (%10)" ya da " (%10, %20)")
const vatRatesLabel = computed(() => {
	const rates = [...new Set((props.order.items ?? []).map((i) => i.vat_rate).filter((r) => r > 0))]
	return rates.length ? ` (${rates.map((r) => `%${r}`).join(', ')})` : ''
})
</script>

<template>
	<Head :title="`Sipariş ${order.order_no}`" />

	<OzPageHeader :title="order.order_no">
		<template #subtitle>{{ order.tenant?.name ?? '—' }} · {{ order.created_at?.slice(0, 10) }}</template>
		<template #actions>
			<OzPill :tone="STATUS_TONE[order.status] ?? 'neutral'">{{ statuses[order.status] ?? order.status }}</OzPill>
			<OzPill v-if="returnState" :tone="returnState.tone">{{ returnState.label }}</OzPill>
			<template v-if="canManage">
				<OzButton
					v-for="to in allowedTransitions"
					:key="to"
					:variant="to === 'cancelled' ? 'danger' : 'primary'"
					:loading="busy"
					@click="promptTransition(to)"
				>{{ statuses[to] ?? to }}</OzButton>
			</template>
		</template>
	</OzPageHeader>
	<OzModuleTabs />

	<div class="grid">
		<div class="col-main">
			<OzCard title="Kalemler">
				<OzTable :columns="itemColumns" :rows="order.items" row-key="id" min-width="520px">
					<template #cell-item="{ row }">
						<div class="item-cell">
							<img v-if="row.product_image_url" :src="row.product_image_url" class="item-thumb" alt="" />
							<div>
								<div class="item-name">{{ row.product_name }}</div>
								<div class="item-brand">{{ row.product_brand }}</div>
							</div>
						</div>
					</template>
					<template #cell-variant="{ row }">{{ [row.color, row.size].filter(Boolean).join(' / ') || '—' }}</template>
					<template #cell-unit_price="{ value }"><span class="mono">{{ formatMoney(value) }}</span></template>
					<template #cell-total_price="{ value }"><span class="mono">{{ formatMoney(value) }}</span></template>
				</OzTable>
				<div class="totals">
					<div class="total-row"><span>Ara Toplam{{ order.tax_amount ? ' (KDV hariç)' : '' }}</span><span class="mono">{{ formatMoney(order.subtotal) }}</span></div>
					<div class="total-row"><span>Kargo</span><span class="mono">{{ formatMoney(order.shipping_fee) }}</span></div>
					<div v-if="order.tax_amount" class="total-row"><span>KDV{{ vatRatesLabel }}</span><span class="mono">{{ formatMoney(order.tax_amount) }}</span></div>
					<div class="total-row grand"><span>Genel Toplam</span><span class="mono">{{ formatMoney(order.total) }}</span></div>
				</div>
			</OzCard>

			<OzCard v-if="order.carrier || order.cargo_customer_code" title="Kargo">
				<div class="totals no-border">
					<div class="total-row"><span>Firma</span><span>{{ order.carrier ?? '—' }}</span></div>
					<div class="total-row"><span>Müşteri Kodu</span><span class="mono">{{ order.cargo_customer_code ?? '—' }}</span></div>
				</div>
			</OzCard>

			<OzCard v-if="ledger.length" title="Kredi Hareketleri">
				<OzTable :columns="ledgerColumns" :rows="ledger" row-key="id" min-width="480px">
					<template #cell-created_at="{ value }">{{ value?.slice(0, 10) }}</template>
					<template #cell-type="{ value }">
						<OzPill :tone="value === 'debit' ? 'danger' : 'success'">{{ value === 'debit' ? 'Borç' : 'Alacak' }}</OzPill>
					</template>
					<template #cell-amount="{ value }"><span class="mono">{{ formatMoney(value) }}</span></template>
					<template #cell-balance_after="{ value }"><span class="mono">{{ formatMoney(value) }}</span></template>
				</OzTable>
			</OzCard>
		</div>

		<div class="col-side">
			<OzCard v-if="hasDeliveryInfo" title="Teslimat & Ödeme">
				<div class="totals no-border">
					<div v-if="order.order_type" class="total-row"><span>Sipariş Tipi</span><span>{{ ORDER_TYPES[order.order_type] ?? order.order_type }}</span></div>
					<div v-if="shipping.source" class="total-row"><span>Kaynak</span><span>{{ SOURCES[shipping.source] ?? shipping.source }}</span></div>
					<div v-if="order.payment_method" class="total-row"><span>Ödeme</span><span>{{ PAYMENT_METHODS[order.payment_method] ?? order.payment_method }}</span></div>
					<div v-if="shipping.shipping_method" class="total-row"><span>Gönderim</span><span>{{ SHIPPING_METHODS[shipping.shipping_method] ?? shipping.shipping_method }}</span></div>
				</div>
				<div v-if="addressLines.length" class="info-block">
					<div class="info-label">Teslimat Adresi</div>
					<div v-for="(line, i) in addressLines" :key="i" class="info-line">{{ line }}</div>
				</div>
				<div v-if="order.note" class="info-block">
					<div class="info-label">Not</div>
					<div class="info-line note">{{ order.note }}</div>
				</div>
			</OzCard>

			<OzCard v-if="returns.length" title="İadeler" :meta="refundedAmount > 0 ? `${formatMoney(refundedAmount)} iade edildi` : ''">
				<div class="returns">
					<Link v-for="rt in returns" :key="rt.id" :href="`/order-returns/${rt.id}`" class="return-row">
						<div>
							<div class="return-title">Talep #{{ rt.rma_id }} <span class="mono">{{ formatMoney(rt.amount) }}</span></div>
							<div class="return-sub">{{ rt.created_at?.slice(0, 10) }}<template v-if="rt.reason"> · {{ rt.reason }}</template></div>
						</div>
						<OzPill :tone="RETURN_TONE[rt.status] ?? 'neutral'">{{ returnStatuses[rt.status] ?? rt.status }}</OzPill>
					</Link>
				</div>
			</OzCard>

			<OzCard title="Durum Zaman Çizelgesi">
				<p v-if="!order.histories.length" class="empty">Henüz geçiş yok.</p>
				<OzTimeline v-else :items="timelineItems" />
			</OzCard>
		</div>
	</div>

	<OzModal v-model:open="confirmOpen" :title="modalTitle" size="sm" :tone="pendingTo === 'cancelled' ? 'danger' : 'accent'" :persistent="busy">
		<p class="modal-text">
			Sipariş durumunu <b>{{ statuses[pendingTo] ?? pendingTo }}</b> yapmak istediğinize emin misiniz?
		</p>
		<OzField label="Not (opsiyonel)">
			<OzTextarea v-model="note" :rows="3" placeholder="Açıklama…" />
		</OzField>
		<template #footer>
			<OzButton :disabled="busy" @click="confirmOpen = false">Vazgeç</OzButton>
			<OzButton :variant="pendingTo === 'cancelled' ? 'danger' : 'primary'" :loading="busy" @click="submitTransition">Onayla</OzButton>
		</template>
	</OzModal>
</template>

<style scoped>
.grid { display: grid; grid-template-columns: 2fr 1fr; gap: 16px; align-items: start; }
.col-main, .col-side { display: flex; flex-direction: column; gap: 16px; }
.mono { font-family: ui-monospace, monospace; font-size: 12px; }

.item-cell { display: flex; align-items: center; gap: 10px; }
.item-thumb { width: 40px; height: 40px; border-radius: 8px; object-fit: cover; background: var(--oz-surface-2); }
.item-name { font-weight: 600; color: var(--oz-ink-soft); font-size: 12.5px; }
.item-brand { font-size: 11px; color: var(--oz-muted); }

.totals { margin: 0 18px 16px; border-top: 1px dashed var(--oz-border); padding-top: 12px; }
.totals.no-border { margin: 0 18px 16px; border-top: none; padding-top: 0; }
.total-row { display: flex; justify-content: space-between; padding: 3px 0; font-size: 13px; color: var(--oz-muted); }
.total-row.grand { font-weight: 700; color: var(--oz-ink-soft); font-size: 15px; margin-top: 6px; }

.info-block { margin: 0 18px 16px; padding-top: 12px; border-top: 1px dashed var(--oz-border); }
.info-label { font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; color: var(--oz-muted); margin-bottom: 6px; }
.info-line { font-size: 13px; color: var(--oz-ink-soft); line-height: 1.5; }
.info-line.note { white-space: pre-line; }

.returns { display: flex; flex-direction: column; margin: 0 18px 16px; }
.return-row { display: flex; justify-content: space-between; align-items: center; gap: 10px; padding: 8px 0; border-bottom: 1px dashed var(--oz-border); text-decoration: none; }
.return-row:last-child { border-bottom: none; }
.return-row:hover .return-title { color: var(--oz-accent); }
.return-title { font-size: 13px; font-weight: 600; color: var(--oz-ink-soft); }
.return-sub { font-size: 11.5px; color: var(--oz-muted); max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

.modal-text { font-size: 13.5px; color: var(--oz-muted); margin: 0 0 14px; }
.empty { color: var(--oz-muted); font-style: italic; font-size: 13px; margin: 0 18px 16px; }

@media (max-width: 900px) { .grid { grid-template-columns: 1fr; } }
</style>
