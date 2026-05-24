<template>
	<Head title="Stok" />
	<div class="page-stocks">
		<Breadcrumb
			:items="[
				{ label: 'Ana Sayfa', to: '/workflow', icon: 'home' },
				{ label: 'Katalog', to: '/products' },
				{ label: 'Stok' },
			]"
		/>

		<div class="page-header">
			<div>
				<h1 class="page-title">Stok Yönetimi</h1>
				<p class="page-subtitle">
					<strong>{{ stocks.length }}</strong> varyant kaydı
					<span v-if="criticalCount > 0" class="critical-chip">{{ criticalCount }} kritik</span>
				</p>
			</div>
			<button class="btn btn-primary btn-with-icon" @click="openMovement()">
				<svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
					<polyline points="22 12 18 12 15 21 9 3 6 12 2 12" />
				</svg>
				Stok Hareketi
			</button>
		</div>

		<div class="card">
			<div class="card-header">
				<h3>Stok Listesi</h3>
				<CustomSelect
					v-model="localFilters.warehouse_id"
					:options="warehouseOptions"
					:show-label="false"
					style="width: 200px; margin-left: auto;"
					@update:modelValue="applyFilters"
				/>
				<label class="check-inline">
					<input type="checkbox" v-model="localFilters.critical_only" @change="applyFilters" />
					<span>Sadece kritik</span>
				</label>
			</div>

			<table class="data-table">
				<thead>
					<tr>
						<th>Ürün / Varyant</th>
						<th style="width: 12%">Depo</th>
						<th style="width: 10%; text-align: right;">Stok</th>
						<th style="width: 10%; text-align: right;">Rezerve</th>
						<th style="width: 10%; text-align: right;">Min.</th>
						<th style="width: 12%">Durum</th>
						<th style="width: 8%"></th>
					</tr>
				</thead>
				<tbody>
					<tr v-if="stocks.length === 0">
						<td colspan="7" class="empty-row">Stok kaydı yok.</td>
					</tr>
					<tr v-for="s in stocks" :key="s.id" :class="{ 'row-critical': s.isCritical }">
						<td>
							<div class="product-cell">
								<div class="product-name">{{ s.productName }}</div>
								<div class="variant-info">
									<span v-if="s.size" class="chip">{{ s.size }}</span>
									<span v-if="s.colorName" class="chip">
										<span class="dot" :style="{ background: s.colorHex }"></span>
										{{ s.colorName }}
									</span>
									<span class="sku">{{ s.sku }}</span>
								</div>
							</div>
						</td>
						<td>
							<div class="warehouse-cell">
								<div class="warehouse-name">{{ s.warehouseName }}</div>
								<div class="warehouse-code">{{ s.warehouseCode }}</div>
							</div>
						</td>
						<td class="num">{{ s.quantity }}</td>
						<td class="num dim">{{ s.reserved_quantity }}</td>
						<td class="num dim">{{ s.min_quantity }}</td>
						<td>
							<span v-if="s.isCritical" class="status-pill status-critical">
								<span class="dot"></span> Kritik
							</span>
							<span v-else-if="s.quantity === 0" class="status-pill status-empty">
								<span class="dot"></span> Tükendi
							</span>
							<span v-else class="status-pill status-ok">
								<span class="dot"></span> Yeterli
							</span>
						</td>
						<td>
							<button class="table-action-btn" @click="openMovement(s)" title="Hareket Ekle">↕️</button>
						</td>
					</tr>
				</tbody>
			</table>
		</div>

		<!-- Stok hareket modal -->
		<AppModal v-model="movementOpen" title="Stok Hareketi" size="md" variant="info">
			<form class="form-grid" @submit.prevent="submitMovement">
				<div v-if="selectedStock" class="info-box">
					<div><strong>{{ selectedStock.productName }}</strong></div>
					<div class="dim">
						{{ selectedStock.size ? selectedStock.size + ' · ' : '' }}
						{{ selectedStock.colorName ?? '' }} · {{ selectedStock.warehouseName }}
					</div>
					<div class="dim">Mevcut stok: <strong>{{ selectedStock.quantity }}</strong></div>
				</div>

				<div v-if="!selectedStock" class="form-row">
					<label class="form-label">Varyant ID <span class="req">*</span></label>
					<input v-model.number="movement.product_variant_id" type="number" class="form-input" />
				</div>
				<div v-if="!selectedStock" class="form-row">
					<label class="form-label">Depo <span class="req">*</span></label>
					<CustomSelect v-model="movement.warehouse_id" :options="warehouseOptionsForForm" :show-label="false" />
				</div>

				<div class="form-row">
					<label class="form-label">Hareket Tipi <span class="req">*</span></label>
					<div class="type-buttons">
						<button
							type="button"
							v-for="t in movementTypes"
							:key="t.value"
							class="type-btn"
							:class="{ active: movement.type === t.value }"
							@click="movement.type = t.value"
						>{{ t.icon }} {{ t.label }}</button>
					</div>
				</div>
				<div class="form-row">
					<label class="form-label">Miktar <span class="req">*</span></label>
					<input v-model.number="movement.quantity" type="number" min="1" class="form-input" />
					<span v-if="errors.quantity" class="form-error">{{ errors.quantity }}</span>
					<span v-if="movement.type === 'adjustment'" class="form-hint">
						Düzeltme için + (artır) veya − (azalt) işaretli sayı girin.
					</span>
				</div>
				<div class="form-row">
					<label class="form-label">Not</label>
					<textarea v-model="movement.note" class="form-input" rows="2" placeholder="Açıklama (opsiyonel)..." />
				</div>
			</form>
			<template #footer="{ close }">
				<button class="btn btn-ghost" @click="close" :disabled="busy">İptal</button>
				<button class="btn btn-primary" @click="submitMovement" :disabled="busy">Kaydet</button>
			</template>
		</AppModal>
	</div>
</template>

<script setup>
import { ref, computed, inject, reactive, watch } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Breadcrumb from '@/Components/Breadcrumb.vue'
import AppModal from '@/Components/AppModal.vue'
import CustomSelect from '@/Components/CustomSelect.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
	stocks: { type: Array, default: () => [] },
	warehouses: { type: Array, default: () => [] },
	filters: { type: Object, default: () => ({}) },
})

const showToast = inject('showToast')

const criticalCount = computed(() => props.stocks.filter(s => s.isCritical).length)

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

/* ── Movement ── */
const movementTypes = [
	{ value: 'in',         label: 'Giriş',    icon: '⬇️' },
	{ value: 'out',        label: 'Çıkış',    icon: '⬆️' },
	{ value: 'adjustment', label: 'Düzeltme', icon: '✏️' },
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

<style scoped>
.page-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 20px; gap: 16px; }
.page-title { font-size: 22px; font-weight: 700; color: #1a1a2e; }
.page-subtitle { font-size: 13px; color: #888; margin-top: 4px; }
.critical-chip { display: inline-block; margin-left: 8px; padding: 2px 8px; background: #fee2e2; color: #dc2626; border-radius: 999px; font-size: 11px; font-weight: 700; }

.card { background: #fff; border-radius: 16px; border: 1px solid #ebebf0; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,.04); }
.card-header { padding: 14px 18px; display: flex; align-items: center; gap: 12px; border-bottom: 1px solid #f0f0f5; }
.card-header h3 { font-size: 15px; font-weight: 700; color: #1a1a2e; }

.check-inline { display: inline-flex; align-items: center; gap: 6px; font-size: 13px; color: #1a1a2e; cursor: pointer; }

.data-table { width: 100%; border-collapse: separate; border-spacing: 0; }
.data-table thead tr { background: #f8f8fc; }
.data-table th { text-align: left; padding: 12px 16px; font-size: 11px; font-weight: 600; color: #aaa; border-bottom: 1px solid #f0f0f5; text-transform: uppercase; letter-spacing: 0.04em; }
.data-table td { padding: 12px 16px; font-size: 13px; color: #444; border-bottom: 1px solid #f5f5f8; vertical-align: middle; }
.data-table tr:last-child td { border-bottom: none; }
.data-table tr:hover td { background: #fafafe; }
.data-table tr.row-critical td { background: #fff8f8; }
.data-table tr.row-critical:hover td { background: #fff0f0; }
.empty-row { text-align: center !important; color: #aaa; padding: 32px 0 !important; font-style: italic; }
.num { text-align: right; font-family: 'SF Mono', Menlo, Consolas, monospace; font-weight: 600; }
.dim { color: #888; font-weight: 500; }

.product-cell { display: flex; flex-direction: column; gap: 4px; }
.product-name { font-weight: 600; color: #1a1a2e; font-size: 13px; }
.variant-info { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
.chip { display: inline-flex; align-items: center; gap: 4px; padding: 2px 8px; background: #f0f0f5; color: #555; border-radius: 6px; font-size: 11px; font-weight: 600; }
.chip .dot { width: 8px; height: 8px; border-radius: 50%; border: 1px solid #d0d0db; }
.sku { font-size: 11px; color: #888; font-family: 'SF Mono', Menlo, Consolas, monospace; }

.warehouse-cell { display: flex; flex-direction: column; gap: 2px; }
.warehouse-name { font-size: 12.5px; color: #1a1a2e; font-weight: 600; }
.warehouse-code { font-size: 10.5px; color: #888; font-family: 'SF Mono', Menlo, Consolas, monospace; }

.status-pill { display: inline-flex; align-items: center; gap: 5px; padding: 3px 10px; border-radius: 999px; font-size: 11px; font-weight: 600; }
.status-pill .dot { width: 5px; height: 5px; border-radius: 50%; background: currentColor; }
.status-ok { background: #dcfce7; color: #16a34a; }
.status-critical { background: #fef3c7; color: #d97706; }
.status-empty { background: #fee2e2; color: #dc2626; }

.table-action-btn { background: #f3f4f6; border: none; cursor: pointer; font-size: 13px; padding: 5px 9px; border-radius: 6px; color: #6b7280; transition: all .15s; }
.table-action-btn:hover { background: #e0e7ff; color: #4f46e5; }

/* Form */
.form-grid { display: flex; flex-direction: column; gap: 14px; }
.form-row { display: flex; flex-direction: column; gap: 6px; }
.form-label { font-size: 12px; font-weight: 600; color: #1a1a2e; }
.form-label .req { color: #ef4444; }
.form-input { padding: 9px 12px; border: 1px solid #e8e8f0; border-radius: 8px; font-family: inherit; font-size: 13px; color: #1a1a2e; background: #fff; outline: none; transition: border-color .15s; }
.form-input:focus { border-color: #7c3aed; }
.form-hint { font-size: 11px; color: #888; }
.form-error { font-size: 11.5px; color: #ef4444; }

.info-box {
	padding: 12px 14px;
	background: #fafafe;
	border: 1px solid #f0f0f5;
	border-radius: 10px;
	font-size: 13px;
	display: flex; flex-direction: column; gap: 4px;
}

.type-buttons { display: flex; gap: 6px; }
.type-btn {
	flex: 1; padding: 10px 14px;
	background: #fff; border: 1.5px solid #e8e8f0;
	border-radius: 9px; font-family: inherit; font-size: 12.5px;
	font-weight: 600; color: #555; cursor: pointer;
	transition: all .15s;
}
.type-btn:hover { border-color: #c0c0d8; }
.type-btn.active { border-color: #7c3aed; background: #ede9fe; color: #5b21b6; }
</style>
