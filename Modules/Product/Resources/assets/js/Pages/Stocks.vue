<script setup>
import { ref, computed, inject, reactive, watch } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import { ArrowUpDown, ArrowDown, ArrowUp, Pencil } from 'lucide-vue-next'
import AppLayout from '@/Layouts/AppLayout.vue'
import { OzPageHeader, OzModuleTabs, OzStatTile, OzCard, OzTable, OzPill, OzSelect, OzSwitch, OzButton, OzDrawer, OzField, OzInput, OzTextarea } from '@/Components/Oz'

defineOptions({ layout: AppLayout })

const props = defineProps({
	stocks: { type: Array, default: () => [] },
	warehouses: { type: Array, default: () => [] },
	filters: { type: Object, default: () => ({}) },
})

const showToast = inject('showToast')
const page = usePage()
const canCard = computed(() => (page.props.auth?.permissions ?? []).includes('product.card.view'))

const criticalCount = computed(() => props.stocks.filter(s => s.isCritical).length)
const emptyCount = computed(() => props.stocks.filter(s => s.quantity === 0).length)
const stats = computed(() => [
	{ key: 'total', label: 'Toplam Varyant', value: props.stocks.length, icon: 'tag', tone: 'accent' },
	{ key: 'quantity', label: 'Toplam Stok', value: props.stocks.reduce((sum, s) => sum + s.quantity, 0), icon: 'package', tone: 'success' },
	{ key: 'critical', label: 'Kritik', value: criticalCount.value, icon: 'warning', tone: criticalCount.value ? 'warning' : 'success' },
	{ key: 'empty', label: 'Tükenen', value: emptyCount.value, icon: 'error', tone: emptyCount.value ? 'danger' : 'success' },
])

const localFilters = reactive({
	warehouse_id: props.filters.warehouse_id ?? '',
	critical_only: !!props.filters.critical_only,
})

const warehouseOptions = computed(() => [
	{ value: '', label: 'Tüm Depolar' },
	...props.warehouses.map(w => ({ value: w.id, label: `${w.name} (${w.code})` })),
])

const warehouseOptionsForForm = computed(() =>
	props.warehouses.map(w => ({ value: w.id, label: `${w.name} (${w.code})` })),
)

function applyFilters() {
	const params = {}
	if (localFilters.warehouse_id) params.warehouse_id = localFilters.warehouse_id
	if (localFilters.critical_only) params.critical_only = 1
	router.get('/products/stocks', params, { preserveState: true, preserveScroll: true, replace: true })
}

const columns = [
	{ key: 'product', label: 'Ürün / Varyant' },
	{ key: 'warehouse', label: 'Depo', width: '12%' },
	{ key: 'quantity', label: 'Stok', align: 'right', width: '10%', class: 'oz-cell-amount' },
	{ key: 'reserved_quantity', label: 'Rezerve', align: 'right', width: '10%' },
	{ key: 'min_quantity', label: 'Min.', align: 'right', width: '10%' },
	{ key: 'status', label: 'Durum', width: '12%' },
	{ key: 'actions', label: '', align: 'right', width: '8%', searchable: false },
]

/* ── Movement ── */
const movementTypes = [
	{ value: 'in',         label: 'Giriş',    icon: ArrowDown },
	{ value: 'out',        label: 'Çıkış',    icon: ArrowUp },
	{ value: 'adjustment', label: 'Düzeltme', icon: Pencil },
]

const movementOpen = ref(false)
const selectedStock = ref(null)
const busy = ref(false)
const errors = ref({})

const movement = reactive({
	product_variant_id: null,
	warehouse_id: null,
	type: 'in',
	quantity: 1,
	note: '',
})

watch(movementOpen, (open) => {
	if (!open) {
		setTimeout(() => {
			selectedStock.value = null
			errors.value = {}
			Object.assign(movement, { product_variant_id: null, warehouse_id: null, type: 'in', quantity: 1, note: '' })
		}, 250)
	}
})

function openMovement(stock = null) {
	if (stock) {
		selectedStock.value = stock
		movement.product_variant_id = stock.variant_id
		movement.warehouse_id = stock.warehouse_id
	}
	movement.type = 'in'
	movement.quantity = 1
	movement.note = ''
	errors.value = {}
	movementOpen.value = true
}

function submitMovement() {
	if (busy.value) return
	if (!movement.product_variant_id || !movement.warehouse_id) {
		showToast?.({ type: 'error', title: 'Eksik bilgi', message: 'Varyant ve depo seçilmeli.' })
		return
	}
	busy.value = true
	errors.value = {}
	router.post('/products/stocks/movement', { ...movement }, {
		preserveScroll: true,
		preserveState: true,
		onSuccess: () => {
			movementOpen.value = false
			showToast?.({
				type: 'success',
				title: 'Stok hareketi kaydedildi',
				message: `${movement.type === 'in' ? 'Giriş' : movement.type === 'out' ? 'Çıkış' : 'Düzeltme'} · ${Math.abs(movement.quantity)} adet`,
			})
		},
		onError: (errs) => {
			errors.value = errs
			showToast?.({ type: 'error', title: 'Kayıt başarısız', message: Object.values(errs)[0] || 'Sunucu hatası.' })
		},
		onFinish: () => { busy.value = false },
	})
}
</script>

<template>
	<Head title="Stok" />

	<OzPageHeader title="Stok Yönetimi">
		<template #subtitle>
			<b>{{ stocks.length }}</b> varyant kaydı
			<OzPill v-if="criticalCount > 0" tone="danger">{{ criticalCount }} kritik</OzPill>
		</template>
		<template #actions>
			<OzButton variant="primary" icon="activity" @click="openMovement()">Stok Hareketi</OzButton>
		</template>
	</OzPageHeader>
	<OzModuleTabs />

	<div class="stats">
		<OzStatTile v-for="s in stats" :key="s.key" :label="s.label" :value="s.value" :icon="s.icon" :tone="s.tone" />
	</div>

	<OzCard title="Stok Listesi">
		<template #actions>
			<div class="toolbar">
				<OzSelect v-model="localFilters.warehouse_id" :options="warehouseOptions" class="wh-select" @update:modelValue="applyFilters" />
				<OzSwitch v-model="localFilters.critical_only" label="Sadece kritik" @update:modelValue="applyFilters" />
			</div>
		</template>
		<OzTable :columns="columns" :rows="stocks" row-key="id" manual empty-title="Stok kaydı yok" min-width="760px">
			<template #cell-product="{ row }">
				<div class="product-cell">
					<Link v-if="row.productId && canCard" :href="`/products/${row.productId}/card?tab=stok`" class="product-name product-link">{{ row.productName }}</Link>
					<div v-else class="product-name">{{ row.productName }}</div>
					<div class="variant-info">
						<span v-if="row.size" class="chip">{{ row.size }}</span>
						<span v-if="row.colorName" class="chip">
							<span class="dot" :style="{ background: row.colorHex }"></span>
							{{ row.colorName }}
						</span>
						<span class="sku">{{ row.sku }}</span>
					</div>
				</div>
			</template>
			<template #cell-warehouse="{ row }">
				<div class="warehouse-cell">
					<div class="warehouse-name">{{ row.warehouseName }}</div>
					<div class="warehouse-code">{{ row.warehouseCode }}</div>
				</div>
			</template>
			<template #cell-reserved_quantity="{ value }"><span class="dim">{{ value }}</span></template>
			<template #cell-min_quantity="{ value }"><span class="dim">{{ value }}</span></template>
			<template #cell-status="{ row }">
				<OzPill v-if="row.isCritical" tone="warning">Kritik</OzPill>
				<OzPill v-else-if="row.quantity === 0" tone="danger">Tükendi</OzPill>
				<OzPill v-else tone="success">Yeterli</OzPill>
			</template>
			<template #cell-actions="{ row }">
				<button class="table-action-btn" type="button" title="Hareket Ekle" @click="openMovement(row)"><ArrowUpDown :size="13" /></button>
			</template>
		</OzTable>
	</OzCard>

	<OzDrawer v-model:open="movementOpen" title="Stok Hareketi" :subtitle="selectedStock ? selectedStock.productName : 'Yeni stok hareketi kaydedin'" icon="activity" size="md" :persistent="busy">
		<form class="form" @submit.prevent="submitMovement">
			<div v-if="selectedStock" class="info-box">
				<div><b>{{ selectedStock.productName }}</b></div>
				<div class="dim">
					{{ selectedStock.size ? selectedStock.size + ' · ' : '' }}
					{{ selectedStock.colorName ?? '' }} · {{ selectedStock.warehouseName }}
				</div>
				<div class="dim">Mevcut stok: <b>{{ selectedStock.quantity }}</b></div>
			</div>

			<OzField v-if="!selectedStock" label="Varyant ID" required>
				<OzInput v-model.number="movement.product_variant_id" type="number" />
			</OzField>
			<OzField v-if="!selectedStock" label="Depo" required>
				<OzSelect v-model="movement.warehouse_id" :options="warehouseOptionsForForm" placeholder="Depo seçin" />
			</OzField>

			<OzField label="Hareket Tipi" required>
				<div class="type-buttons">
					<button
						v-for="t in movementTypes"
						:key="t.value"
						type="button"
						class="type-btn"
						:class="{ active: movement.type === t.value }"
						@click="movement.type = t.value"
					><component :is="t.icon" :size="13" /> {{ t.label }}</button>
				</div>
			</OzField>
			<OzField label="Miktar" required :error="errors.quantity" :hint="movement.type === 'adjustment' ? 'Düzeltme için + (artır) veya − (azalt) işaretli sayı girin.' : ''">
				<OzInput v-model.number="movement.quantity" type="number" min="1" />
			</OzField>
			<OzField label="Not">
				<OzTextarea v-model="movement.note" :rows="2" placeholder="Açıklama (opsiyonel)…" />
			</OzField>
			<button type="submit" hidden />
		</form>
		<template #footer>
			<OzButton :disabled="busy" @click="movementOpen = false">İptal</OzButton>
			<OzButton variant="primary" :loading="busy" @click="submitMovement">Kaydet</OzButton>
		</template>
	</OzDrawer>
</template>

<style scoped>
.stats { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; margin-bottom: 14px; }
@media (max-width: 1040px) { .stats { grid-template-columns: repeat(2, minmax(0, 1fr)); } }

.toolbar { display: flex; align-items: center; gap: 14px; flex-wrap: wrap; }
.wh-select { width: 200px; }
.dim { color: var(--oz-muted); font-weight: 500; }

.product-cell { display: flex; flex-direction: column; gap: 4px; }
.product-name { font-weight: 600; color: var(--oz-ink-soft); font-size: 13px; }
.product-link { text-decoration: none; }
.product-link:hover { color: var(--oz-accent); text-decoration: underline; }
.variant-info { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
.chip { display: inline-flex; align-items: center; gap: 4px; padding: 2px 8px; background: var(--oz-surface-2); color: var(--oz-muted); border-radius: 6px; font-size: 11px; font-weight: 600; }
.chip .dot { width: 8px; height: 8px; border-radius: 50%; border: 1px solid var(--oz-border-strong); }
.sku { font-size: 11px; color: var(--oz-muted); font-family: ui-monospace, monospace; }

.warehouse-cell { display: flex; flex-direction: column; gap: 2px; }
.warehouse-name { font-size: 12.5px; color: var(--oz-ink-soft); font-weight: 600; }
.warehouse-code { font-size: 10.5px; color: var(--oz-muted); font-family: ui-monospace, monospace; }

.table-action-btn { display: inline-flex; align-items: center; justify-content: center; background: var(--oz-surface-2); border: none; cursor: pointer; padding: 6px; border-radius: 6px; color: var(--oz-muted); transition: all .15s; }
.table-action-btn:hover { background: var(--oz-accent-soft); color: var(--oz-accent); }

.form { display: flex; flex-direction: column; gap: 14px; }
.info-box {
	padding: 12px 14px;
	background: var(--oz-surface-2);
	border: 1px solid var(--oz-border);
	border-radius: 10px;
	font-size: 13px;
	display: flex; flex-direction: column; gap: 4px;
}

.type-buttons { display: flex; gap: 6px; }
.type-btn {
	display: inline-flex; align-items: center; justify-content: center; gap: 5px;
	flex: 1; padding: 10px 14px;
	background: var(--oz-surface); border: 1.5px solid var(--oz-border);
	border-radius: 9px; font-family: inherit; font-size: 12.5px;
	font-weight: 600; color: var(--oz-muted); cursor: pointer;
	transition: all .15s;
}
.type-btn:hover { border-color: var(--oz-border-strong); }
.type-btn.active { border-color: var(--oz-accent); background: var(--oz-accent-soft); color: var(--oz-accent); }
</style>
