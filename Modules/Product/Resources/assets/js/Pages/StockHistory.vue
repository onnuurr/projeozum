<script setup>
import { computed, reactive } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { OzPageHeader, OzModuleTabs, OzCard, OzTable, OzPill, OzSelect, OzPagination, OzButton, OzIcon } from '@/Components/Oz'

// Stok hareket geçmişi (log): her stok değişikliği — tekil hareket, toplu işlem satırı, sipariş,
// açılış stoğu — bir StockMovement'tır. Toplu işlemler (StockOperation) üstte başlık olarak
// listelenir; birine tıklayınca yalnız o işlemin satırları gösterilir.
defineOptions({ layout: AppLayout })

const props = defineProps({
	movements: { type: Object, required: true },      // paginator: data, current_page, per_page, total
	operation: { type: Object, default: null },
	operations: { type: Array, default: () => [] },
	warehouses: { type: Array, default: () => [] },
	filters: { type: Object, default: () => ({}) },
})

const TYPE_LABELS = { in: 'Giriş', out: 'Çıkış', adjustment: 'Düzeltme', transfer: 'Transfer' }
const TYPE_TONES = { in: 'success', out: 'danger', adjustment: 'warning', transfer: 'info' }
const MODE_LABELS = { in: 'Toplu giriş', out: 'Toplu çıkış', count: 'Sayım' }

const local = reactive({
	type: props.filters.type || '',
	warehouse_id: props.filters.warehouse_id || '',
})
const typeOptions = [{ value: '', label: 'Tüm hareketler' }, ...Object.entries(TYPE_LABELS).map(([value, label]) => ({ value, label }))]
const warehouseOptions = computed(() => [{ value: '', label: 'Tüm depolar' }, ...props.warehouses.map(w => ({ value: w.id, label: w.name }))])

function go(params) {
	const query = { ...params }
	Object.keys(query).forEach(k => { if (query[k] === '' || query[k] === null || query[k] === undefined) delete query[k] })
	router.get('/products/stocks/history', query, { preserveScroll: true, preserveState: true, replace: true })
}
function applyFilters() {
	go({ type: local.type, warehouse_id: local.warehouse_id, operation_id: props.filters.operation_id, variant_id: props.filters.variant_id })
}
function openOperation(id) { go({ operation_id: id }) }
function clearOperation() { go({ type: local.type, warehouse_id: local.warehouse_id }) }

const page = computed({
	get: () => props.movements.current_page,
	set: (p) => go({ ...props.filters, page: p }),
})

const columns = [
	{ key: 'createdAt', label: 'Tarih', width: '13%' },
	{ key: 'product', label: 'Ürün / Varyant' },
	{ key: 'warehouseName', label: 'Depo', width: '11%' },
	{ key: 'type', label: 'Hareket', width: '10%' },
	{ key: 'quantity', label: 'Miktar', align: 'right', width: '8%' },
	{ key: 'balance', label: 'Önce → Sonra', align: 'right', width: '11%' },
	{ key: 'userName', label: 'Kullanıcı', width: '11%' },
	{ key: 'note', label: 'Not', width: '16%' },
]
</script>

<template>
	<Head title="Stok Hareket Geçmişi" />

	<OzPageHeader title="Stok Hareket Geçmişi" subtitle="Bütün stok değişiklikleri: kim, ne zaman, hangi depoda, ne kadar">
		<template #actions>
			<Link href="/products/stocks" class="oz-link-btn"><OzIcon name="chevron-left" :size="14" /> Stok Listesi</Link>
			<OzButton variant="primary" icon="layers" @click="router.visit('/products/stocks/bulk')">Toplu Stok İşlemi</OzButton>
		</template>
	</OzPageHeader>
	<OzModuleTabs />

	<OzCard v-if="operations.length" title="Toplu İşlemler" class="ops-card">
		<div class="ops">
			<button v-for="o in operations" :key="o.id" type="button" class="op" :class="{ active: filters.operation_id === o.id }" @click="openOperation(o.id)">
				<span class="op-head"><b>#{{ o.id }} {{ MODE_LABELS[o.mode] || o.mode }}</b><span class="dim">{{ o.createdAt }}</span></span>
				<span class="op-meta">{{ o.warehouseName }} · {{ o.lineCount }} varyant · <span class="up">+{{ o.totalIn }}</span> / <span class="down">−{{ o.totalOut }}</span></span>
				<span class="op-meta dim">{{ o.userName || '—' }}<template v-if="o.note"> · {{ o.note }}</template></span>
			</button>
		</div>
	</OzCard>

	<div v-if="operation" class="op-banner">
		<div>
			<b>Toplu işlem #{{ operation.id }} — {{ MODE_LABELS[operation.mode] || operation.mode }}</b>
			<span class="dim"> · {{ operation.createdAt }} · {{ operation.warehouseName }} · {{ operation.userName || '—' }}</span>
			<div class="dim">
				{{ operation.lineCount }} varyant değişti<template v-if="operation.skippedCount">, {{ operation.skippedCount }} varyant aynı kaldı</template>
				· <span class="up">+{{ operation.totalIn }}</span> / <span class="down">−{{ operation.totalOut }}</span> adet
				<template v-if="operation.note"> · Not: {{ operation.note }}</template>
			</div>
		</div>
		<OzButton size="sm" @click="clearOperation">Tüm hareketler</OzButton>
	</div>

	<OzCard title="Hareketler">
		<template #actions>
			<div class="toolbar">
				<OzSelect v-model="local.type" :options="typeOptions" class="sel" @update:modelValue="applyFilters" />
				<OzSelect v-model="local.warehouse_id" :options="warehouseOptions" class="sel" @update:modelValue="applyFilters" />
			</div>
		</template>
		<OzTable :columns="columns" :rows="movements.data" row-key="id" manual empty-title="Hareket kaydı yok" min-width="900px">
			<template #cell-product="{ row }">
				<div class="product-cell">
					<div class="product-name">{{ row.productName }}</div>
					<div class="variant-info">
						<span v-if="row.size" class="chip">{{ row.size }}</span>
						<span v-if="row.colorName" class="chip">{{ row.colorName }}</span>
						<span class="mono">{{ row.sku }}</span>
					</div>
				</div>
			</template>
			<template #cell-type="{ row }">
				<OzPill :tone="TYPE_TONES[row.type] || 'neutral'">{{ TYPE_LABELS[row.type] || row.type }}</OzPill>
				<button v-if="row.operationId && !operation" type="button" class="op-link" @click="openOperation(row.operationId)">#{{ row.operationId }}</button>
			</template>
			<template #cell-quantity="{ value }"><b :class="value > 0 ? 'up' : 'down'">{{ value > 0 ? '+' : '' }}{{ value }}</b></template>
			<template #cell-balance="{ row }"><span class="dim">{{ row.before_quantity }} → </span><b>{{ row.after_quantity }}</b></template>
			<template #cell-userName="{ value }"><span class="dim">{{ value || '—' }}</span></template>
			<template #cell-note="{ value }"><span class="note" :title="value || ''">{{ value || '' }}</span></template>
		</OzTable>
		<OzPagination v-if="movements.total > movements.per_page" v-model="page" :total="movements.total" :page-size="movements.per_page" class="pager" />
	</OzCard>
</template>

<style scoped>
.oz-link-btn { display: inline-flex; align-items: center; gap: 5px; font-size: 13px; font-weight: 600; color: var(--oz-ink-soft); text-decoration: none; padding: 6px 10px; border-radius: 8px; }
.oz-link-btn:hover { background: var(--oz-surface-2); }
.ops-card { margin-bottom: 16px; }
.ops { display: flex; gap: 10px; overflow-x: auto; padding-bottom: 4px; }
.op { flex: 0 0 240px; display: flex; flex-direction: column; gap: 3px; text-align: left; padding: 9px 12px; border: 1px solid var(--oz-border); border-radius: 10px; background: var(--oz-surface); cursor: pointer; font-family: inherit; }
.op:hover, .op.active { border-color: var(--oz-accent); }
.op-head { display: flex; justify-content: space-between; gap: 8px; font-size: 12.5px; color: var(--oz-ink-soft); }
.op-meta { font-size: 11.5px; color: var(--oz-ink-soft); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.op-banner { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 16px; padding: 10px 14px; border-radius: 12px; border: 1px solid var(--oz-accent); background: color-mix(in srgb, var(--oz-accent) 6%, var(--oz-surface)); font-size: 13px; }
.toolbar { display: flex; gap: 10px; flex-wrap: wrap; }
.sel { width: 170px; }
.product-cell { display: flex; flex-direction: column; gap: 3px; }
.product-name { font-weight: 600; color: var(--oz-ink-soft); font-size: 13px; }
.variant-info { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
.chip { display: inline-flex; padding: 2px 8px; background: var(--oz-surface-2); color: var(--oz-muted); border-radius: 6px; font-size: 11px; font-weight: 600; }
.mono { font-size: 11px; color: var(--oz-muted); font-family: ui-monospace, monospace; }
.dim { color: var(--oz-muted); font-weight: 500; }
.up { color: var(--oz-success); }
.down { color: var(--oz-danger); }
.op-link { margin-left: 6px; border: none; background: none; color: var(--oz-accent); font-family: inherit; font-size: 11.5px; font-weight: 700; cursor: pointer; padding: 0; }
.note { display: block; max-width: 220px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-size: 12px; color: var(--oz-muted); }
.pager { margin-top: 12px; }
</style>
