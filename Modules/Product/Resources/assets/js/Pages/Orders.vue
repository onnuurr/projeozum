<script setup>
import { reactive, computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { OzPageHeader, OzModuleTabs, OzStatStrip, OzCard, OzTable, OzPill, OzInput, OzSelect, OzButton } from '@/Components/Oz'

defineOptions({ layout: AppLayout })

const props = defineProps({
	orders: { type: Object, required: true },
	statuses: { type: Object, default: () => ({}) },
	statusCounts: { type: Object, default: () => ({}) },
	tenants: { type: Array, default: () => [] },
	filters: { type: Object, default: () => ({}) },
})

const localFilters = reactive({
	status: props.filters.status ?? '',
	tenant_id: props.filters.tenant_id ?? '',
	order_no: props.filters.order_no ?? '',
	date_from: props.filters.date_from ?? '',
	date_to: props.filters.date_to ?? '',
})

const tenantOptions = [{ value: '', label: 'Tüm Bayiler' }, ...props.tenants.map(t => ({ value: t.id, label: `${t.name} (${t.code})` }))]

const columns = [
	{ key: 'order_no', label: 'Sipariş No' },
	{ key: 'tenant', label: 'Bayi' },
	{ key: 'created_at', label: 'Tarih' },
	{ key: 'item_count', label: 'Ürün', align: 'right' },
	{ key: 'total', label: 'Tutar', align: 'right', class: 'oz-cell-amount' },
	{ key: 'status', label: 'Durum' },
	{ key: 'actions', label: '', align: 'right', width: '80px', searchable: false },
]

const STATUS_TONE = { pending: 'warning', confirmed: 'info', preparing: 'accent', shipped: 'info', delivered: 'success', cancelled: 'danger' }

// Sayımlar durum filtresinden bağımsız (diğer filtreler uygulanmış) gelir.
const countOf = (key) => props.statusCounts?.[key] ?? 0
const totalCount = computed(() => Object.values(props.statusCounts ?? {}).reduce((a, b) => a + b, 0))

const stats = computed(() => [
	{ key: 'total', label: 'Toplam Sipariş', value: totalCount.value, icon: 'receipt', tone: 'accent' },
	{ key: 'pending', label: 'Onay Bekleyen', value: countOf('pending'), icon: 'clock', tone: 'warning' },
	{ key: 'shipped', label: 'Kargoda', value: countOf('shipped'), icon: 'truck', tone: 'info' },
	{ key: 'delivered', label: 'Teslim Edildi', value: countOf('delivered'), icon: 'check', tone: 'success' },
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
	router.get('/orders', params, { preserveState: true, preserveScroll: true, replace: true })
}

function formatMoney(v) {
	return new Intl.NumberFormat('tr-TR', { style: 'currency', currency: 'TRY', maximumFractionDigits: 2 }).format(Number(v ?? 0))
}
</script>

<template>
	<Head title="Siparişler" />

	<OzPageHeader title="Siparişler">
		<template #subtitle><b>{{ orders.total }}</b> sipariş kayıtlı</template>
	</OzPageHeader>
	<OzModuleTabs />

	<OzStatStrip :items="stats" />

	<div class="status-chips">
		<button class="chip" :class="{ active: !localFilters.status }" type="button" @click="setStatus('')">Tümü <span class="chip-count">{{ totalCount }}</span></button>
		<button
			v-for="(label, key) in statuses"
			:key="key"
			class="chip"
			:class="{ active: localFilters.status === key }"
			type="button"
			@click="setStatus(key)"
		>{{ label }} <span class="chip-count">{{ countOf(key) }}</span></button>
	</div>

	<div class="filters-row">
		<OzInput v-model="localFilters.order_no" class="f-order" placeholder="Sipariş no ara…" icon="search" @keyup.enter="applyFilters" />
		<OzSelect v-model="localFilters.tenant_id" class="f-tenant" :options="tenantOptions" @update:modelValue="applyFilters" />
		<OzInput v-model="localFilters.date_from" type="date" class="f-date" @change="applyFilters" />
		<OzInput v-model="localFilters.date_to" type="date" class="f-date" @change="applyFilters" />
		<OzButton icon="filter" @click="applyFilters">Filtrele</OzButton>
	</div>

	<OzCard>
		<OzTable :columns="columns" :rows="orders.data" row-key="id" manual empty-title="Sipariş bulunamadı" min-width="760px">
			<template #cell-order_no="{ value }"><span class="mono">{{ value }}</span></template>
			<template #cell-tenant="{ row }">{{ row.tenant?.name ?? '—' }}</template>
			<template #cell-created_at="{ value }">{{ value?.slice(0, 10) }}</template>
			<template #cell-item_count="{ value }"><span class="mono">{{ value }}</span></template>
			<template #cell-total="{ value }"><span class="mono">{{ formatMoney(value) }}</span></template>
			<template #cell-status="{ row }">
				<OzPill :tone="STATUS_TONE[row.status] ?? 'neutral'">{{ statuses[row.status] ?? row.status }}</OzPill>
			</template>
			<template #cell-actions="{ row }">
				<Link :href="`/orders/${row.id}`" class="detail-link">Detay</Link>
			</template>
		</OzTable>
	</OzCard>

	<nav v-if="orders.last_page > 1" class="pagination">
		<Link
			v-for="link in orders.links"
			:key="link.label"
			:href="link.url ?? '#'"
			v-html="link.label"
			:class="['page-link', { active: link.active, disabled: !link.url }]"
		/>
	</nav>
</template>

<style scoped>
.mono { font-family: ui-monospace, monospace; font-size: 12px; }


.status-chips { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 12px; }
.chip { padding: 6px 12px; border-radius: 999px; border: 1px solid var(--oz-border); background: var(--oz-surface); font-size: 12px; font-weight: 600; color: var(--oz-muted); cursor: pointer; transition: all .15s; }
.chip:hover { border-color: var(--oz-border-strong); }
.chip.active { background: var(--oz-accent); color: #fff; border-color: var(--oz-accent); }
.chip-count { display: inline-block; min-width: 18px; margin-left: 4px; padding: 0 5px; border-radius: 999px; background: var(--oz-surface-2); color: var(--oz-muted); font-size: 11px; text-align: center; }
.chip.active .chip-count { background: rgba(255, 255, 255, .22); color: #fff; }

.filters-row { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin-bottom: 16px; }
.f-order { flex: 1 1 200px; max-width: 260px; }
.f-tenant { width: 200px; }
.f-date { width: 150px; }

.detail-link { color: var(--oz-accent); text-decoration: none; padding: 4px 10px; border-radius: 6px; font-size: 12px; font-weight: 600; }
.detail-link:hover { background: var(--oz-accent-soft); }

.pagination { display: flex; gap: 4px; margin-top: 12px; flex-wrap: wrap; }
.page-link { padding: 6px 10px; border-radius: 6px; font-size: 12px; color: var(--oz-muted); text-decoration: none; background: var(--oz-surface); border: 1px solid var(--oz-border); }
.page-link.active { background: var(--oz-accent); color: #fff; border-color: var(--oz-accent); }
.page-link.disabled { color: var(--oz-muted-2, var(--oz-muted)); pointer-events: none; opacity: .5; }
</style>
