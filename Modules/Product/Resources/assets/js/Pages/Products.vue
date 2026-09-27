<script setup>
import { ref, computed, inject, reactive } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import { Shirt, Palette, Store } from 'lucide-vue-next'
import AppLayout from '@/Layouts/AppLayout.vue'
import {
	OzPageHeader, OzModuleTabs, OzStatTile, OzButton, OzPill, OzTable,
	OzDropdown, OzEmptyState, OzCard, OzCheckbox, OzModal, OzIcon, useOzConfirm,
} from '@/Components/Oz'
import { ozFold } from '@/Components/Oz/ozText'
import { isFilterActive, matchesFilter } from '@/Components/Oz/ozTableFilter'
import MarketplaceListingDrawer from '../Components/MarketplaceListingDrawer.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
	products: { type: Array, default: () => [] },
	categories: { type: Array, default: () => [] },
	brands: { type: Array, default: () => [] },
	marketplaces: { type: Array, default: () => [] },
	canAddManufactured: { type: Boolean, default: false },
})

const showToast = inject('showToast')
const confirm = useOzConfirm()

const page = usePage()
const can = (perm) => (page.props.auth?.permissions ?? []).includes(perm)
const canAdd    = computed(() => can('product.add'))
const canDelete = computed(() => can('product.delete'))
// Listeleme drawer'ı GET ucu product.card.view ister; kaydetme product.add.
const canListing = computed(() => canAdd.value && can('product.card.view'))

/* ── İstatistikler ── */
const stats = computed(() => {
	const inStock = props.products.filter((p) => p.stock > 0).length
	const ready = props.products.filter((p) => readinessCapabilities.every((c) => p.readiness?.[c.key]?.ready)).length
	return [
		{ key: 'total', label: 'Toplam Ürün', value: props.products.length, icon: 'grid', tone: 'accent' },
		{ key: 'inStock', label: 'Stokta', value: inStock, icon: 'success', tone: 'success' },
		{ key: 'outOfStock', label: 'Tükendi', value: props.products.length - inStock, icon: 'error', tone: (props.products.length - inStock) ? 'danger' : 'success' },
		{ key: 'ready', label: 'Tümüyle Hazır', value: ready, icon: 'sparkles', tone: 'info' },
	]
})

/* ── Tablo (OzTable: arama, sütun filtreleri, sıralama, sayfalama) ── */
const kindLabel = (p) => {
	if (p.status === 'development') return 'Geçici kart'
	return p.sourcing === 'manufactured' ? 'Üretim ürünü' : 'Hazır ürün'
}
const columns = computed(() => [
	...(canDelete.value ? [{ key: 'select', label: '', width: '44px', searchable: false }] : []),
	{ key: 'id', label: 'ID', sortable: true, class: 'oz-cell-id', width: '64px', searchValue: (p) => `#${p.id}` },
	{ key: 'image', label: 'Görsel', width: '60px', searchable: false },
	{
		key: 'name', label: 'Ürün', sortable: true, class: 'oz-cell-strong',
		searchValue: (p) => [p.name, p.sku, p.barcode].filter(Boolean).join(' '),
		filterValue: kindLabel,
		filter: { type: 'select', label: 'Ürün Türü', options: ['Hazır ürün', 'Üretim ürünü', 'Geçici kart'] },
	},
	{ key: 'category', label: 'Kategori', sortable: true, filter: { type: 'select', placeholder: 'Tüm kategoriler' } },
	{ key: 'brandLabel', label: 'Marka', sortable: true, filter: { type: 'select', placeholder: 'Tüm markalar' } },
	{ key: 'price', label: 'Site Fiyatı', align: 'right', sortable: true, class: 'oz-cell-amount', filter: { type: 'range', suffix: '₺', format: (n) => formatPrice(n) } },
	{ key: 'marketplaces', label: 'Pazaryeri', searchable: false },
	{ key: 'stock', label: 'Stok', align: 'right', sortable: true, filter: { type: 'range' } },
	{ key: 'desi', label: 'Desi', align: 'right', sortable: true, searchable: false, filter: { type: 'range' } },
	{ key: 'readiness', label: 'Hazırlık', searchable: false },
	{ key: 'actions', label: '', align: 'right', width: '48px', searchable: false },
])

// Tablo durumu dışarıda tutulur: "filtrelenenlerin tümünü seç" OzTable'ın gördüğü satırları bilmeli.
const tableSearch = ref('')
const tableFilters = ref({})
const filterCols = computed(() => columns.value.filter((c) => c.filter))
const filteredProducts = computed(() => {
	const q = ozFold(tableSearch.value.trim())
	const searchCols = columns.value.filter((c) => c.searchable !== false)
	return props.products.filter((p) =>
		(!q || ozFold(searchCols.map((c) => (c.searchValue ? c.searchValue(p) : p[c.key])).filter((v) => v != null).join(' ')).includes(q))
		&& filterCols.value.every((c) => !isFilterActive(c.filter, tableFilters.value[c.key])
			|| matchesFilter(c.filter, tableFilters.value[c.key], c.filterValue ? c.filterValue(p) : p[c.key])))
})
const isFiltering = computed(() => filteredProducts.value.length !== props.products.length)

/* ── Yardımcılar ── */
function formatPrice(value) {
	return '₺' + Number(value ?? 0).toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

/* ── Hazırlık göstergesi (Faz 4) ──
 * Salt-okuma: backend'in ProductReadinessService::evaluate() çıktısını
 * (ProductCatalogPresenter::catalogRow()'daki `readiness` key'i) gösterir.
 * Buradaki rozetler hiçbir akışı engellemez, yalnızca bilgi verir. */
const readinessCapabilities = [
	{ key: 'try_on', icon: Shirt, label: 'Sanal Giydirme' },
	{ key: 'creative_render', icon: Palette, label: 'Kreatif Görsel Üretimi' },
	{ key: 'marketplace_push', icon: Store, label: 'Pazaryeri Gönderimi' },
]

function readinessTooltip(p, cap) {
	const result = p.readiness?.[cap.key]
	if (!result) return cap.label
	if (result.ready) return `${cap.label} — Hazır`
	return `${cap.label} — Eksik: ${result.missing.join(', ')}`
}

/* ── Pazaryeri rozetleri ──
 * Ürünün kategorisine eşlenmiş pazaryerleri varsa onları kullan; yoksa
 * (yapım aşaması) test amaçlı tüm pazaryerlerini göster. */
function marketplacesFor(p) {
	return (p.marketplaces && p.marketplaces.length) ? p.marketplaces : props.marketplaces
}

/* ── Pazaryeri listeleme drawer ── */
const listingOpen = ref(false)
const activeProduct = ref(null)
const activeMarketplace = ref(null)
const listingOverlay = reactive({}) // `${productId}:${key}` -> { price, isSent }

function listingSummary(p, mp) {
	return listingOverlay[`${p.id}:${mp.key}`] ?? p.listings?.[mp.key] ?? null
}
function mpPrice(p, mp) {
	const s = listingSummary(p, mp)
	return s && s.price != null ? formatPrice(s.price) : platformPrice(p, mp)
}
function openListing(p, mp) {
	activeProduct.value = p
	activeMarketplace.value = mp
	listingOpen.value = true
}
function onListingSaved(payload) {
	if (!activeProduct.value) return
	listingOverlay[`${activeProduct.value.id}:${payload.marketplaceKey}`] = {
		price: payload.price,
		isSent: payload.isSent,
	}
}

// Logo görseli yüklenemezse renkli text rozet'e düş.
const logoFailed = ref({})
function onLogoError(key) { logoFailed.value[key] = true }

// Platforma özel fiyat henüz yok; test için site fiyatından deterministik
// bir placeholder üret (her pazaryeri için sabit çarpan).
const MP_PRICE_FACTOR = {
	trendyol: 1.00,
	hepsiburada: 1.05,
	amazon: 1.08,
	n11: 1.03,
	gittigidiyor: 1.02,
}
function platformPrice(p, mp) {
	const factor = MP_PRICE_FACTOR[mp.key] ?? 1
	return formatPrice((p.price ?? 0) * factor)
}

// İki tür yeni ürün: hazır (alınan) ürün doğrudan kart açar; üretilecek ürün önce Atölye'de
// reçete + maliyetle geçici stok kartı açar, ilk iş emri tamamlanınca ürüne döner.
// Yeni Ürün butonu her zaman seçim modalını açar; üretilecek ürün yetkisi yoksa o seçenek pasif görünür.
const newProductOpen = ref(false)
const newProductChoices = computed(() => [
	{ key: 'product', title: 'Yeni Ürün', desc: 'Hazır (satın alınan) ürün kartı', icon: 'package', href: '/products/create', disabled: false },
	{ key: 'manufactured', title: 'Üretilecek Ürün', desc: 'Reçeteli geçici stok kartı', icon: 'factory', href: '/atelier/new-product', disabled: !props.canAddManufactured },
])
function onNewProductClick() {
	newProductOpen.value = true
}
function chooseNewProduct(choice) {
	if (choice.disabled) return
	newProductOpen.value = false
	router.visit(choice.href)
}

function editProduct(product) {
	router.visit(`/products/${product.id}/edit`)
}

const rowActions = (p) => [
	{ key: 'view', label: 'Önizleme', icon: 'eye' },
	...(canAdd.value ? [{ key: 'edit', label: 'Düzenle', icon: 'edit' }] : []),
	...(canDelete.value ? [{ divider: true }, { key: 'remove', label: 'Sil', icon: 'trash', danger: true }] : []),
]
function onRowAction(key, p) {
	if (key === 'view') router.visit(`/products/${p.slug ?? p.id}`)
	else if (key === 'edit') editProduct(p)
	else if (key === 'remove') confirmDeleteProduct(p)
}

async function confirmDeleteProduct(product) {
	const ok = await confirm.danger('Ürünü Sil', `“${product.name}” silinecek. Bu üründeki ${product.variants?.length ?? 0} varyant da kalıcı olarak kaldırılacak.`)
	if (!ok) return

	router.delete(`/products/${product.id}`, {
		preserveScroll: true,
		preserveState: true,
		onSuccess: () => {
			showToast?.({ type: 'warning', title: 'Ürün Silindi', message: `${product.name} kaldırıldı.` })
		},
		onError: (errs) => {
			showToast?.({ type: 'error', title: 'Silme Başarısız', message: Object.values(errs)[0] || 'Sunucu hatası.' })
		},
	})
}

/* ── Toplu seçim ── */
const selected = ref(new Set())
const bulkBusy = ref(false)
const selectedCount = computed(() => selected.value.size)

function isSelected(id) { return selected.value.has(id) }
function toggleRow(id) {
	const next = new Set(selected.value)
	next.has(id) ? next.delete(id) : next.add(id)
	selected.value = next
}
const allFilteredSelected = computed(() =>
	filteredProducts.value.length > 0 && filteredProducts.value.every((p) => selected.value.has(p.id)),
)
function toggleSelectAllFiltered() {
	const ids = filteredProducts.value.map((p) => p.id)
	const next = new Set(selected.value)
	if (allFilteredSelected.value) ids.forEach((id) => next.delete(id))
	else ids.forEach((id) => next.add(id))
	selected.value = next
}
function clearSelection() { selected.value = new Set() }

async function bulkDelete() {
	const ids = [...selected.value]
	if (!ids.length) return
	const ok = await confirm.danger('Ürünleri Sil', `${ids.length} ürün ve bunlara bağlı varyantlar kalıcı olarak silinecek.`)
	if (!ok) return

	bulkBusy.value = true
	router.post('/products/bulk-destroy', { ids }, {
		preserveScroll: true,
		preserveState: false,
		onSuccess: () => {
			showToast?.({ type: 'warning', title: 'Ürünler Silindi', message: `${ids.length} ürün kaldırıldı.` })
			clearSelection()
		},
		onError: (errs) => {
			showToast?.({ type: 'error', title: 'Silme Başarısız', message: Object.values(errs)[0] || 'Sunucu hatası.' })
		},
		onFinish: () => { bulkBusy.value = false },
	})
}
</script>

<template>
	<Head title="Ürün Kataloğu" />

	<OzPageHeader title="Ürün Kataloğu">
		<template #subtitle>
			<b>{{ filteredProducts.length }}</b> ürün listeleniyor
			<span v-if="isFiltering" class="dim"> / {{ products.length }}</span>
		</template>
		<template #actions>
			<OzButton v-if="canAdd" variant="primary" icon="plus" @click="onNewProductClick">Yeni Ürün</OzButton>
		</template>
	</OzPageHeader>
	<OzModuleTabs />

	<div class="stats">
		<OzStatTile v-for="s in stats" :key="s.key" :label="s.label" :value="s.value" :icon="s.icon" :tone="s.tone" />
	</div>

	<OzCard title="Ürün Listesi">
		<OzEmptyState v-if="!products.length" icon="package" title="Henüz ürün yok" description="Kataloğa ilk ürününüzü ekleyerek başlayın.">
			<template v-if="canAdd" #actions><OzButton size="sm" variant="primary" icon="plus" @click="onNewProductClick">Yeni Ürün</OzButton></template>
		</OzEmptyState>

		<OzTable
			v-else
			v-model:search="tableSearch"
			v-model:filters="tableFilters"
			:columns="columns"
			:rows="products"
			:page-size="15"
			:default-sort="{ key: 'id', dir: 'desc' }"
			searchable
			search-placeholder="Ürün adı, SKU ya da barkod ara…"
			min-width="1240px"
		>
			<template v-if="canDelete" #toolbar>
				<OzCheckbox
					:model-value="allFilteredSelected"
					:label="isFiltering ? `Filtrelenenleri seç (${filteredProducts.length})` : `Tümünü seç (${products.length})`"
					@update:model-value="toggleSelectAllFiltered"
				/>
			</template>

			<template #cell-select="{ row }">
				<OzCheckbox :model-value="isSelected(row.id)" :aria-label="`${row.name} seç`" @update:model-value="toggleRow(row.id)" />
			</template>
			<template #cell-id="{ value }">#{{ value }}</template>
			<template #cell-image="{ row }">
				<div class="thumb">
					<img :src="row.image || '/images/product-placeholder.svg'" :alt="row.name" loading="lazy">
				</div>
			</template>
			<template #cell-name="{ row }">
				<div class="name-cell">
					<span class="name-text">
						{{ row.name }}
						<OzPill v-if="row.status === 'development'" tone="warning" title="Üretimi tamamlanınca ürüne dönüşür">Geçici Kart</OzPill>
					</span>
					<div class="oz-cell-sub mono">{{ row.sku || '—' }}<template v-if="row.barcode"> · {{ row.barcode }}</template></div>
				</div>
			</template>
			<template #cell-category="{ value }">{{ value || '—' }}</template>
			<template #cell-brandLabel="{ value }">{{ value || '—' }}</template>
			<template #cell-price="{ row }">
				{{ formatPrice(row.price) }}
				<div v-if="row.marketPrice != null || row.purchasePrice != null" class="oz-cell-sub">
					<template v-if="row.marketPrice != null">P: {{ formatPrice(row.marketPrice) }}</template>
					<template v-if="row.marketPrice != null && row.purchasePrice != null"> · </template>
					<template v-if="row.purchasePrice != null">A: {{ formatPrice(row.purchasePrice) }}</template>
				</div>
			</template>
			<template #cell-marketplaces="{ row }">
				<div v-if="marketplacesFor(row).length" class="mp-list">
					<button
						v-for="mp in marketplacesFor(row)"
						:key="mp.key"
						type="button"
						class="mp-item"
						:class="{ sent: listingSummary(row, mp)?.isSent }"
						:title="mp.name"
						:disabled="!canListing"
						@click="canListing && openListing(row, mp)"
					>
						<img
							v-if="!logoFailed[mp.key]"
							class="mp-logo-img"
							:src="`/images/marketplaces/${mp.key}.svg`"
							:alt="mp.name"
							@error="onLogoError(mp.key)"
						>
						<span v-else class="mp-badge" :style="{ background: mp.color || '#888' }">{{ mp.logoText }}</span>
						<span class="mp-price">{{ mpPrice(row, mp) }}</span>
					</button>
				</div>
				<span v-else class="dim">—</span>
			</template>
			<template #cell-stock="{ value }">
				<OzPill v-if="value === 0" tone="danger">Tükendi</OzPill>
				<OzPill v-else-if="value < 20" tone="warning">{{ value }}</OzPill>
				<OzPill v-else tone="success">{{ value }}</OzPill>
			</template>
			<template #cell-desi="{ value }">
				<span v-if="value != null" class="mono">{{ Number(value).toLocaleString('tr-TR', { maximumFractionDigits: 2 }) }}</span>
				<span v-else class="dim">—</span>
			</template>
			<template #cell-readiness="{ row }">
				<div class="readiness-badges">
					<span
						v-for="cap in readinessCapabilities"
						:key="cap.key"
						class="readiness-dot"
						:class="{ ready: row.readiness?.[cap.key]?.ready }"
						:title="readinessTooltip(row, cap)"
					><component :is="cap.icon" :size="12" /></span>
				</div>
			</template>
			<template #cell-actions="{ row }">
				<OzDropdown :items="rowActions(row)" :label="`${row.name} işlemleri`" @select="(k) => onRowAction(k, row)" />
			</template>
		</OzTable>
	</OzCard>

	<!-- Toplu seçim çubuğu (yüzen) -->
	<div class="bulk-dock" :class="{ 'is-active': canDelete && selectedCount > 0 }">
		<Transition name="bulk">
			<div v-if="canDelete && selectedCount > 0" class="bulk-bar" role="toolbar" aria-label="Toplu işlemler">
				<span class="bulk-info"><b>{{ selectedCount }}</b> ürün seçili</span>
				<div class="bulk-actions">
					<OzButton size="sm" @click="clearSelection">Temizle</OzButton>
					<OzButton variant="danger" size="sm" icon="trash" :loading="bulkBusy" @click="bulkDelete">Seçilenleri Sil ({{ selectedCount }})</OzButton>
				</div>
			</div>
		</Transition>
	</div>

	<MarketplaceListingDrawer
		:open="listingOpen"
		:product-id="activeProduct?.id"
		:marketplace="activeMarketplace"
		@close="listingOpen = false"
		@saved="onListingSaved"
	/>

	<OzModal v-model:open="newProductOpen" title="Yeni Ürün" subtitle="Hangi türde ürün eklemek istiyorsunuz?" icon="plus" size="md">
		<div class="choice-grid">
			<button
				v-for="c in newProductChoices" :key="c.key" type="button" class="choice"
				:disabled="c.disabled" :title="c.disabled ? 'Bu seçenek için yetkiniz yok' : undefined"
				@click="chooseNewProduct(c)"
			>
				<span class="choice-ico"><OzIcon :name="c.icon" :size="26" /></span>
				<b>{{ c.title }}</b>
				<small>{{ c.desc }}</small>
			</button>
		</div>
	</OzModal>
</template>

<style scoped>
/* ── Yeni ürün seçim modalı ── */
.choice-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
@media (max-width: 480px) { .choice-grid { grid-template-columns: 1fr; } }
.choice {
	display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 8px;
	padding: 26px 16px; text-align: center; background: var(--oz-surface); color: var(--oz-ink);
	border: 1px solid var(--oz-border); border-radius: 14px; cursor: pointer;
	transition: border-color .15s, background .15s, transform .15s;
}
.choice:hover:not(:disabled), .choice:focus-visible { border-color: var(--oz-accent); background: var(--oz-accent-soft); outline: none; transform: translateY(-1px); }
.choice:disabled { opacity: .5; cursor: not-allowed; }
.choice-ico {
	display: inline-flex; align-items: center; justify-content: center;
	width: 56px; height: 56px; border-radius: 14px; background: var(--oz-accent-soft); color: var(--oz-accent);
}
.choice b { font-size: 15px; font-weight: 600; }
.choice small { font-size: 12.5px; color: var(--oz-muted); line-height: 1.4; }

.stats { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; margin-bottom: 14px; }
@media (max-width: 1040px) { .stats { grid-template-columns: repeat(2, minmax(0, 1fr)); } }

/* ── Toplu seçim çubuğu ── */
/* Toplu seçim çubuğu: akışta yer kaplamaz (tabloyu itmez), kaydırma alanının altında yüzer.
   Dock, .oz-shell-content içinde sticky; seçim varken sona boşluk açar ki son satırları örtmesin. */
.bulk-dock { position: sticky; bottom: 0; height: 0; z-index: 5; pointer-events: none; transition: height .2s ease; }
.bulk-dock.is-active { height: 76px; }
.bulk-bar {
	position: absolute; left: 50%; bottom: 16px; transform: translateX(-50%);
	display: flex; align-items: center; gap: 14px; width: max-content; max-width: calc(100% - 24px);
	padding: 8px 8px 8px 16px; border-radius: 999px; pointer-events: auto;
	background: var(--oz-surface); border: 1px solid var(--oz-border); box-shadow: var(--oz-shadow);
}
.bulk-info { font-size: 12.5px; color: var(--oz-ink-soft); white-space: nowrap; }
.bulk-info b { color: var(--oz-accent); }
.bulk-actions { display: flex; gap: 8px; }
.bulk-enter-active, .bulk-leave-active { transition: opacity .18s ease, transform .22s cubic-bezier(.2, .8, .2, 1); }
.bulk-enter-from, .bulk-leave-to { opacity: 0; transform: translate(-50%, 14px); }
@media (max-width: 768px) {
	.bulk-bar { bottom: 10px; gap: 10px; padding-left: 14px; }
}

/* ── Tablo hücreleri ── */
.dim { color: var(--oz-muted); font-weight: 400; }
.mono { font-family: ui-monospace, monospace; }

.thumb { width: 44px; height: 44px; border-radius: 8px; overflow: hidden; background: var(--oz-surface-2); flex-shrink: 0; }
.thumb img { width: 100%; height: 100%; object-fit: cover; }

.name-cell { display: flex; flex-direction: column; min-width: 0; }
.name-text { display: inline-flex; align-items: center; gap: 6px; flex-wrap: wrap; }

.readiness-badges { display: flex; gap: 4px; }
.readiness-dot { width: 22px; height: 22px; border-radius: 7px; display: inline-flex; align-items: center; justify-content: center; color: var(--oz-muted); background: var(--oz-surface-2); cursor: default; }
.readiness-dot.ready { background: var(--oz-success-soft); color: var(--oz-success); }

.mp-list { display: flex; flex-wrap: nowrap; gap: 8px; overflow-x: auto; padding-bottom: 2px; max-width: 340px; }
.mp-item { display: flex; flex-direction: column; align-items: center; gap: 3px; flex: 0 0 auto; background: none; border: none; cursor: pointer; padding: 2px; opacity: .55; transition: opacity .12s; }
.mp-item:hover { opacity: 1; }
.mp-item.sent { opacity: 1; }
.mp-item:disabled { cursor: default; pointer-events: none; }
.mp-badge { display: inline-flex; align-items: center; justify-content: center; width: 26px; height: 26px; color: #fff; font-size: 10px; font-weight: 800; border-radius: 7px; letter-spacing: 0.02em; }
.mp-logo-img { width: 26px; height: 26px; display: block; border-radius: 7px; }
.mp-price { font-size: 11px; font-weight: 600; color: var(--oz-muted); white-space: nowrap; }
</style>
