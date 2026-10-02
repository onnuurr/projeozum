<script setup>
import { reactive, computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { OzPageHeader, OzModuleTabs, OzStatStrip, OzCard, OzTable, OzPill, OzInput, OzSelect, OzButton, OzDonut } from '@/Components/Oz'

defineOptions({ layout: AppLayout })

const props = defineProps({
	summary: { type: Object, required: true },
	byTenant: { type: Array, default: () => [] },
	byChannel: { type: Array, default: () => [] },
	byProduct: { type: Array, default: () => [] },
	orders: { type: Array, default: () => [] },
	orderTotal: { type: Number, default: 0 },
	filters: { type: Object, default: () => ({}) },
	tenants: { type: Array, default: () => [] },
	statusLabels: { type: Object, default: () => ({}) },
	channelLabels: { type: Object, default: () => ({}) },
})

const local = reactive({
	date_from: props.filters.date_from ?? '',
	date_to: props.filters.date_to ?? '',
	tenant_id: props.filters.tenant_id ?? '',
	status: props.filters.status ?? '',
	channel: props.filters.channel ?? '',
	product: props.filters.product ?? '',
})

const tenantOptions = computed(() => [{ value: '', label: 'Tüm Bayiler' }, ...props.tenants.map(t => ({ value: t.id, label: `${t.name} (${t.code})` }))])
const statusOptions = computed(() => [{ value: '', label: 'Tüm Durumlar' }, ...Object.entries(props.statusLabels).map(([value, label]) => ({ value, label }))])
const channelOptions = computed(() => [{ value: '', label: 'Tüm Kanallar' }, ...Object.entries(props.channelLabels).map(([value, label]) => ({ value, label }))])

const activeTenant = computed(() => props.tenants.find(t => String(t.id) === String(local.tenant_id)))
const hasFilters = computed(() => Object.values(local).some(Boolean))

const params = () => Object.fromEntries(Object.entries(local).filter(([, v]) => v !== '' && v !== null))

function apply() {
	router.get('/finance/tenant-purchases', params(), { preserveState: true, preserveScroll: true, replace: true })
}

function reset() {
	Object.keys(local).forEach(k => { local[k] = '' })
	apply()
}

function drillTenant(row) {
	if (!row.tenantId) return
	local.tenant_id = row.tenantId
	apply()
}

function setChannel(channel) {
	local.channel = local.channel === channel ? '' : channel
	apply()
}

// Dosya yanıtı: Inertia ziyareti değil, tarayıcının kendi indirmesi.
function downloadExcel() {
	const q = new URLSearchParams(params()).toString()
	window.location.href = '/finance/tenant-purchases/export' + (q ? `?${q}` : '')
}

const stats = computed(() => [
	{ key: 'gross', label: 'Brüt Ciro', value: formatMoney(props.summary.grossAmount), icon: 'wallet', tone: 'accent', context: `${props.summary.orderCount} sipariş` },
	{ key: 'net', label: 'Net Ciro (iade düşülmüş)', value: formatMoney(props.summary.netAmount), icon: 'trending-up', tone: 'success', context: `${formatMoney(props.summary.refundedAmount)} iade` },
	{ key: 'avg', label: 'Ortalama Sipariş', value: formatMoney(props.summary.averageOrder), icon: 'receipt', tone: 'info', context: `${props.summary.tenantCount} aktif bayi` },
	{ key: 'dropship', label: 'Dropship Payı', value: `%${props.summary.dropshipShare}`, icon: 'truck', tone: 'warning', context: 'brüt cirodan' },
])

const CHANNEL_TONES = ['accent', 'info', 'success', 'warning', 'danger', 'neutral']
const channelSegments = computed(() => props.byChannel.map((c, i) => ({ label: c.label, value: c.grossAmount, tone: CHANNEL_TONES[i % CHANNEL_TONES.length] })))

const tenantColumns = [
	{ key: 'tenant', label: 'Bayi', sortable: true },
	{ key: 'orderCount', label: 'Sipariş', align: 'right', sortable: true },
	{ key: 'grossAmount', label: 'Brüt Ciro', align: 'right', class: 'oz-cell-amount', sortable: true },
	{ key: 'refundedAmount', label: 'İade', align: 'right', class: 'oz-cell-amount', sortable: true },
	{ key: 'returnRate', label: 'İade %', align: 'right', sortable: true },
	{ key: 'netAmount', label: 'Net Ciro', align: 'right', class: 'oz-cell-amount', sortable: true },
	{ key: 'averageOrder', label: 'Ort. Sipariş', align: 'right', class: 'oz-cell-amount', sortable: true },
	{ key: 'balance', label: 'Cari Bakiye / Limit', align: 'right', class: 'oz-cell-amount' },
	{ key: 'lastOrderAt', label: 'Son Sipariş', sortable: true },
]

const channelColumns = [
	{ key: 'label', label: 'Kanal' },
	{ key: 'orderCount', label: 'Sipariş', align: 'right' },
	{ key: 'grossAmount', label: 'Ciro', align: 'right', class: 'oz-cell-amount' },
	{ key: 'share', label: 'Pay', align: 'right' },
]

const productColumns = [
	{ key: 'productName', label: 'Ürün', sortable: true },
	{ key: 'qty', label: 'Adet', align: 'right', sortable: true },
	{ key: 'orderCount', label: 'Sipariş', align: 'right', sortable: true },
	{ key: 'totalAmount', label: 'Tutar', align: 'right', class: 'oz-cell-amount', sortable: true },
]

const orderColumns = [
	{ key: 'createdAt', label: 'Tarih' },
	{ key: 'orderNo', label: 'Sipariş No' },
	{ key: 'tenant', label: 'Bayi' },
	{ key: 'channel', label: 'Kanal' },
	{ key: 'itemCount', label: 'Kalem', align: 'right' },
	{ key: 'amount', label: 'Tutar', align: 'right', class: 'oz-cell-amount' },
	{ key: 'status', label: 'Durum' },
]

const STATUS_TONE = { pending: 'warning', confirmed: 'info', preparing: 'accent', shipped: 'info', delivered: 'success', cancelled: 'danger' }

function formatMoney(v) {
	return new Intl.NumberFormat('tr-TR', { style: 'currency', currency: 'TRY', maximumFractionDigits: 2 }).format(Number(v ?? 0))
}
</script>

<template>
	<Head title="Bayi Satış Raporu" />

	<OzPageHeader title="Bayi Satış Raporu">
		<template #subtitle>
			Bayi portalından gelen siparişler<template v-if="activeTenant"> · <b>{{ activeTenant.name }}</b></template>
		</template>
		<template #actions>
			<OzButton icon="download" @click="downloadExcel">Excel'e Aktar</OzButton>
		</template>
	</OzPageHeader>
	<OzModuleTabs />

	<div class="filters-row">
		<OzInput v-model="local.date_from" type="date" class="f-date" @change="apply" />
		<OzInput v-model="local.date_to" type="date" class="f-date" @change="apply" />
		<OzSelect v-model="local.tenant_id" class="f-select" :options="tenantOptions" @update:modelValue="apply" />
		<OzSelect v-model="local.channel" class="f-select" :options="channelOptions" @update:modelValue="apply" />
		<OzSelect v-model="local.status" class="f-status" :options="statusOptions" @update:modelValue="apply" />
		<OzInput v-model="local.product" class="f-product" placeholder="Ürün / marka ara…" icon="search" @keyup.enter="apply" />
		<OzButton icon="filter" @click="apply">Filtrele</OzButton>
		<OzButton v-if="hasFilters" variant="ghost" icon="x" @click="reset">Temizle</OzButton>
	</div>

	<OzStatStrip :items="stats" />

	<OzCard title="Bayi Bazlı Özet" subtitle="satıra tıklayınca o bayiye iner" :meta="`${byTenant.length} bayi`">
		<OzTable
			:columns="tenantColumns"
			:rows="byTenant"
			row-key="tenantId"
			min-width="960px"
			:page-size="15"
			:default-sort="{ key: 'grossAmount', dir: 'desc' }"
			empty-title="Bu filtrelerde sipariş yok"
			class="clickable-rows"
			@row-click="drillTenant"
		>
			<template #cell-tenant="{ row }">
				<div class="tenant-name">{{ row.tenant }}</div>
				<div class="dim mono">{{ row.tenantCode }}</div>
			</template>
			<template #cell-grossAmount="{ value }"><span class="mono strong">{{ formatMoney(value) }}</span></template>
			<template #cell-refundedAmount="{ value }"><span class="mono">{{ value ? formatMoney(value) : '—' }}</span></template>
			<template #cell-returnRate="{ value }">
				<OzPill v-if="value" :tone="value >= 10 ? 'danger' : value >= 5 ? 'warning' : 'neutral'">%{{ value }}</OzPill>
				<span v-else class="dim">—</span>
			</template>
			<template #cell-netAmount="{ value }"><span class="mono">{{ formatMoney(value) }}</span></template>
			<template #cell-averageOrder="{ value }"><span class="mono">{{ formatMoney(value) }}</span></template>
			<template #cell-balance="{ row }">
				<span class="mono">{{ row.balance !== null ? formatMoney(row.balance) : '—' }}</span>
				<div v-if="row.creditLimit" class="dim mono">/ {{ formatMoney(row.creditLimit) }}</div>
			</template>
		</OzTable>
	</OzCard>

	<div class="grid">
		<OzCard title="Satış Kanalları" subtitle="bayinin kendisi mi, dropship mi">
			<div v-if="byChannel.length" class="channel-body">
				<OzDonut :segments="channelSegments" center-label="Ciro" :format="formatMoney" :size="140" stacked />
				<OzTable :columns="channelColumns" :rows="byChannel" row-key="channel" min-width="320px" class="clickable-rows" @row-click="(r) => setChannel(r.channel)">
					<template #cell-label="{ row }">
						<span :class="{ 'active-channel': local.channel === row.channel }">{{ row.label }}</span>
					</template>
					<template #cell-grossAmount="{ value }"><span class="mono">{{ formatMoney(value) }}</span></template>
					<template #cell-share="{ value }"><span class="mono">%{{ value }}</span></template>
				</OzTable>
			</div>
			<p v-else class="empty">Bu filtrelerde sipariş yok.</p>
		</OzCard>

		<OzCard title="Ürün Bazlı Kırılım" :meta="byProduct.length >= 50 ? 'ilk 50 · tamamı Excel\'de' : ''">
			<OzTable
				:columns="productColumns"
				:rows="byProduct"
				row-key="productName"
				min-width="420px"
				:page-size="10"
				:default-sort="{ key: 'totalAmount', dir: 'desc' }"
				empty-title="Ürün yok"
			>
				<template #cell-productName="{ row }">
					<div class="tenant-name">{{ row.productName }}</div>
					<div v-if="row.brand" class="dim">{{ row.brand }}</div>
				</template>
				<template #cell-totalAmount="{ value }"><span class="mono strong">{{ formatMoney(value) }}</span></template>
			</OzTable>
		</OzCard>
	</div>

	<OzCard title="Siparişler" :meta="orderTotal > orders.length ? `son ${orders.length} / ${orderTotal} · tamamı Excel'de` : `${orderTotal} sipariş`">
		<OzTable :columns="orderColumns" :rows="orders" row-key="id" min-width="820px" :page-size="20" empty-title="Sipariş bulunamadı">
			<template #cell-createdAt="{ value }"><span class="mono">{{ value?.slice(0, 10) }}</span></template>
			<template #cell-orderNo="{ row }"><Link :href="`/orders/${row.id}`" class="mono plain-link">{{ row.orderNo }}</Link></template>
			<template #cell-amount="{ value }"><span class="mono">{{ formatMoney(value) }}</span></template>
			<template #cell-status="{ value }"><OzPill :tone="STATUS_TONE[value] ?? 'neutral'">{{ statusLabels[value] ?? value }}</OzPill></template>
		</OzTable>
	</OzCard>
</template>

<style scoped>
.mono { font-family: ui-monospace, monospace; font-size: 12px; }
.strong { font-weight: 700; color: var(--oz-ink-soft); }
.dim { font-size: 11.5px; color: var(--oz-muted); }
.tenant-name { font-weight: 600; font-size: 12.5px; color: var(--oz-ink-soft); }
.plain-link { color: var(--oz-accent); text-decoration: none; }
.plain-link:hover { text-decoration: underline; }

.filters-row { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin-bottom: 14px; }
.f-date { width: 150px; }
.f-select { width: 200px; }
.f-status { width: 160px; }
.f-product { flex: 1 1 180px; max-width: 240px; }


.grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin: 16px 0; align-items: start; }
@media (max-width: 1100px) { .grid { grid-template-columns: 1fr; } }

.channel-body { display: flex; flex-direction: column; gap: 12px; padding-top: 6px; }
.active-channel { font-weight: 700; color: var(--oz-accent); }
.clickable-rows :deep(tbody tr) { cursor: pointer; }

.empty { color: var(--oz-muted); font-style: italic; font-size: 13px; margin: 0 18px 16px; }
</style>
