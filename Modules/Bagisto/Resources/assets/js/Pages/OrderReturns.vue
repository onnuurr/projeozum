<script setup>
import { reactive, computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { OzPageHeader, OzModuleTabs, OzStatTile, OzCard, OzTable, OzPill, OzInput, OzButton } from '@/Components/Oz'

defineOptions({ layout: AppLayout })

const props = defineProps({
	returns: { type: Object, required: true },
	stats: { type: Object, default: () => ({}) },
	filters: { type: Object, default: () => ({}) },
	statusLabels: { type: Object, default: () => ({}) },
	invoiceStatusLabels: { type: Object, default: () => ({}) },
})

const localFilters = reactive({
	status: props.filters.status ?? '',
	search: props.filters.search ?? '',
})

// İş kuyruğu sekmeleri: karar → fatura kontrolü → ödeme; ardından kapanmış durumlar.
const chips = computed(() => [
	{ key: '', label: 'Tümü' },
	{ key: 'requested', label: 'Karar Bekleyen' },
	{ key: 'awaiting_invoice', label: 'Fatura Bekleniyor' },
	{ key: 'invoice_review', label: 'Fatura Kontrolü' },
	{ key: 'ready_to_refund', label: 'Ödeme Bekleyen' },
	{ key: 'refunded', label: props.statusLabels.refunded ?? 'İade Edildi' },
	{ key: 'rejected', label: props.statusLabels.rejected ?? 'Reddedildi' },
	{ key: 'cancelled', label: props.statusLabels.cancelled ?? 'İptal Edildi' },
])

const columns = [
	{ key: 'rma_id', label: 'Talep' },
	{ key: 'order_no', label: 'Sipariş' },
	{ key: 'tenant', label: 'Bayi' },
	{ key: 'created_at', label: 'Tarih' },
	{ key: 'reason', label: 'Gerekçe' },
	{ key: 'item_count', label: 'Adet', align: 'right' },
	{ key: 'amount', label: 'Tutar', align: 'right', class: 'oz-cell-amount' },
	{ key: 'status', label: 'Durum' },
	{ key: 'actions', label: '', align: 'right', width: '80px', searchable: false },
]

const STATUS_TONE = { requested: 'warning', approved: 'info', rejected: 'danger', refunded: 'success', cancelled: 'neutral' }
const INVOICE_TONE = { awaiting: 'warning', pending: 'accent', accepted: 'success', rejected: 'danger' }
const INSPECTION = { approved: ['Ekspertiz uygun', 'success'], rejected: ['Ekspertiz uygun değil', 'danger'], pending: ['Ekspertizde', 'neutral'] }

const statTiles = computed(() => [
	{ key: 'requested', label: 'Karar Bekleyen', value: props.stats.awaiting_decision ?? 0, icon: 'clock', tone: 'warning' },
	{ key: 'invoice_review', label: 'Fatura Kontrolü', value: props.stats.invoice_review ?? 0, icon: 'file', tone: 'accent' },
	{ key: 'ready_to_refund', label: 'Ödeme Bekleyen', value: props.stats.ready_to_refund ?? 0, icon: 'wallet', tone: 'info' },
	{ key: 'refunded', label: 'Bu Ay İade Edilen', value: formatMoney(props.stats.refunded_this_month ?? 0), icon: 'undo', tone: 'success' },
])

function setStatus(key) {
	localFilters.status = key
	applyFilters()
}

function applyFilters() {
	const params = {}
	Object.entries(localFilters).forEach(([k, v]) => {
		if (v) params[k] = v
	})
	router.get('/order-returns', params, { preserveState: true, preserveScroll: true, replace: true })
}

function formatMoney(v) {
	return new Intl.NumberFormat('tr-TR', { style: 'currency', currency: 'TRY', maximumFractionDigits: 2 }).format(Number(v ?? 0))
}
</script>

<template>
	<Head title="İadeler" />

	<OzPageHeader title="İadeler">
		<template #subtitle>Bayi portalından gelen iade talepleri · <b>{{ returns.total }}</b> kayıt</template>
	</OzPageHeader>
	<OzModuleTabs />

	<div class="stats">
		<button v-for="s in statTiles" :key="s.key" type="button" class="stat-btn" @click="setStatus(s.key)">
			<OzStatTile :label="s.label" :value="s.value" :icon="s.icon" :tone="s.tone" />
		</button>
	</div>

	<div class="status-chips">
		<button
			v-for="c in chips"
			:key="c.key"
			class="chip"
			:class="{ active: localFilters.status === c.key }"
			type="button"
			@click="setStatus(c.key)"
		>{{ c.label }}</button>
	</div>

	<div class="filters-row">
		<OzInput v-model="localFilters.search" class="f-search" placeholder="Talep no, sipariş no veya bayi ara…" icon="search" @keyup.enter="applyFilters" />
		<OzButton icon="filter" @click="applyFilters">Filtrele</OzButton>
	</div>

	<OzCard>
		<OzTable :columns="columns" :rows="returns.data" row-key="id" manual empty-title="İade talebi bulunamadı" min-width="880px">
			<template #cell-rma_id="{ value }"><span class="mono">#{{ value }}</span></template>
			<template #cell-order_no="{ row }">
				<Link v-if="row.order_id" :href="`/orders/${row.order_id}`" class="mono plain-link">{{ row.order_no }}</Link>
				<span v-else class="mono muted">{{ row.order_no }}</span>
			</template>
			<template #cell-tenant="{ value }">{{ value ?? '—' }}</template>
			<template #cell-created_at="{ value }">{{ value?.slice(0, 10) }}</template>
			<template #cell-reason="{ row }">
				<div class="reason">{{ row.reason ?? '—' }}</div>
				<OzPill v-if="INSPECTION[row.inspection_result]" :tone="INSPECTION[row.inspection_result][1]">{{ INSPECTION[row.inspection_result][0] }}</OzPill>
			</template>
			<template #cell-item_count="{ value }"><span class="mono">{{ value }}</span></template>
			<template #cell-amount="{ row }">
				<span class="mono">{{ formatMoney(row.approved_amount ?? row.requested_amount) }}</span>
			</template>
			<template #cell-status="{ row }">
				<div class="status-cell">
					<OzPill :tone="STATUS_TONE[row.status] ?? 'neutral'">{{ statusLabels[row.status] ?? row.status }}</OzPill>
					<OzPill v-if="row.status === 'approved' && row.invoice_status" :tone="INVOICE_TONE[row.invoice_status] ?? 'neutral'">
						Fatura: {{ invoiceStatusLabels[row.invoice_status] ?? row.invoice_status }}
					</OzPill>
				</div>
			</template>
			<template #cell-actions="{ row }">
				<Link :href="`/order-returns/${row.id}`" class="detail-link">Detay</Link>
			</template>
		</OzTable>
	</OzCard>

	<nav v-if="returns.last_page > 1" class="pagination">
		<Link
			v-for="link in returns.links"
			:key="link.label"
			:href="link.url ?? '#'"
			v-html="link.label"
			:class="['page-link', { active: link.active, disabled: !link.url }]"
		/>
	</nav>
</template>

<style scoped>
.mono { font-family: ui-monospace, monospace; font-size: 12px; }
.muted { color: var(--oz-muted); }
.plain-link { color: var(--oz-accent); text-decoration: none; }
.plain-link:hover { text-decoration: underline; }

.stats { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; margin-bottom: 14px; }
.stat-btn { all: unset; display: block; cursor: pointer; border-radius: 14px; }
.stat-btn:focus-visible { outline: 2px solid var(--oz-accent); outline-offset: 2px; }
@media (max-width: 1040px) { .stats { grid-template-columns: repeat(2, minmax(0, 1fr)); } }

.status-chips { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 12px; }
.chip { padding: 6px 12px; border-radius: 999px; border: 1px solid var(--oz-border); background: var(--oz-surface); font-size: 12px; font-weight: 600; color: var(--oz-muted); cursor: pointer; transition: all .15s; }
.chip:hover { border-color: var(--oz-border-strong); }
.chip.active { background: var(--oz-accent); color: #fff; border-color: var(--oz-accent); }

.filters-row { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin-bottom: 16px; }
.f-search { flex: 1 1 260px; max-width: 360px; }

.reason { font-size: 12.5px; color: var(--oz-ink-soft); margin-bottom: 3px; max-width: 220px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.status-cell { display: flex; flex-direction: column; align-items: flex-start; gap: 4px; }

.detail-link { color: var(--oz-accent); text-decoration: none; padding: 4px 10px; border-radius: 6px; font-size: 12px; font-weight: 600; }
.detail-link:hover { background: var(--oz-accent-soft); }

.pagination { display: flex; gap: 4px; margin-top: 12px; flex-wrap: wrap; }
.page-link { padding: 6px 10px; border-radius: 6px; font-size: 12px; color: var(--oz-muted); text-decoration: none; background: var(--oz-surface); border: 1px solid var(--oz-border); }
.page-link.active { background: var(--oz-accent); color: #fff; border-color: var(--oz-accent); }
.page-link.disabled { pointer-events: none; opacity: .5; }
</style>
