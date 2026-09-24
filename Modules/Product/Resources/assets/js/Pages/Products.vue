<script setup>
import { ref, computed, watch, inject, reactive } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import { useMotion, useReducedMotion } from '@vueuse/motion'
import { Shirt, Palette, Store } from 'lucide-vue-next'
import AppLayout from '@/Layouts/AppLayout.vue'
import {
	OzPageHeader, OzModuleTabs, OzStatTile, OzButton, OzField, OzInput, OzSelect, OzPill,
	OzDropdown, OzEmptyState, OzPagination, useOzConfirm,
} from '@/Components/Oz'
import MarketplaceListingDrawer from '../Components/MarketplaceListingDrawer.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
	products: { type: Array, default: () => [] },
	categories: { type: Array, default: () => [] },
	brands: { type: Array, default: () => [] },
	marketplaces: { type: Array, default: () => [] },
})

const showToast = inject('showToast')
const confirm = useOzConfirm()

const page = usePage()
const can = (perm) => (page.props.auth?.permissions ?? []).includes(perm)
const canAdd    = computed(() => can('product.add'))
const canDelete = computed(() => can('product.delete'))
// Listeleme drawer'ı GET ucu product.card.view ister; kaydetme product.add.
const canListing = computed(() => canAdd.value && can('product.card.view'))

/* ── Sözlükler ── */
const searchFieldOptions = [
	{ value: 'name', label: 'Ürün Adı' },
	{ value: 'sku',  label: 'Ürün No (SKU)' },
	{ value: 'id',   label: 'Ürün ID' },
]

const sortFieldOptions = [
	{ value: 'default', label: 'Varsayılan' },
	{ value: 'name',    label: 'Ürün Adı' },
	{ value: 'price',   label: 'Satış Fiyatı' },
	{ value: 'stock',   label: 'Stok' },
	{ value: 'new',     label: 'Eklenme' },
]

const sortDirOptions = [
	{ value: 'desc', label: 'Azalan (Z → A)' },
	{ value: 'asc',  label: 'Artan (A → Z)' },
]

const categoryOptions = computed(() => [
	{ value: '', label: 'Kategori Seçilmedi' },
	...props.categories.map((c) => ({ value: c.slug, label: c.label })),
])

const brandOptions = computed(() => [
	{ value: '', label: 'Marka Seçilmedi' },
	...props.brands.map((b) => ({ value: b.slug, label: b.label })),
])

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

/* ── Filtre state ── */
const filtersOpen = ref(false)

// Filtre paneli aç/kapa animasyonu (@vueuse/motion): dış sarmalayıcı yükseklik+opaklık,
// iç panel hafif dikey kayma. Açılış bitince yükseklik `auto` + overflow görünür yapılır ki
// pencere yeniden boyutlanınca / açılır menüler panel dışına taşınca kırpılmasın.
const filterWrap = ref(null)
const filterPanel = ref(null)
const reducedMotion = useReducedMotion()
const { apply: animateWrap, set: setWrap } = useMotion(filterWrap, { initial: { height: 0, opacity: 0 } })
const { apply: animatePanel, set: setPanel } = useMotion(filterPanel, { initial: { y: -10 } })
let filterAnimSeq = 0

watch(filtersOpen, async (open) => {
	const wrap = filterWrap.value
	const panel = filterPanel.value
	if (!wrap || !panel) return
	const seq = ++filterAnimSeq
	wrap.style.overflow = 'hidden'
	if (!open) setWrap({ height: wrap.offsetHeight })
	const height = open ? panel.offsetHeight + parseFloat(getComputedStyle(panel).marginBottom) : 0

	if (reducedMotion.value) {
		setWrap({ height, opacity: open ? 1 : 0 })
		setPanel({ y: 0 })
	} else {
		const ease = { duration: 260, ease: 'easeOut' }
		await Promise.all([
			animateWrap({ height, opacity: open ? 1 : 0, transition: ease }),
			animatePanel({ y: open ? 0 : -10, transition: { type: 'spring', stiffness: 380, damping: 32 } }),
		])
	}

	if (open && seq === filterAnimSeq) {
		wrap.style.height = 'auto'
		wrap.style.overflow = 'visible'
	}
})
const searchQuery = ref('')
const searchField = ref('name')
const selectedCategory = ref('')
const selectedBrand = ref('')
const stockMin = ref(null)
const stockMax = ref(null)
const priceMin = ref(null)
const priceMax = ref(null)
const desiMin = ref(null)
const desiMax = ref(null)
const sortField = ref('default')
const sortDir = ref('desc')
const currentPage = ref(1)
const itemsPerPage = 15

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

/* ── Filtre işlemi ── */
const filtered = computed(() => {
	const q = searchQuery.value.trim().toLowerCase()

	let list = props.products.filter((p) => {
		if (q) {
			let hay
			if (searchField.value === 'sku') hay = (p.sku || '').toLowerCase()
			else if (searchField.value === 'id') hay = String(p.id)
			else hay = (p.name || '').toLowerCase()
			if (!hay.includes(q)) return false
		}
		if (selectedCategory.value && p.categorySlug !== selectedCategory.value) return false
		if (selectedBrand.value && p.brand !== selectedBrand.value) return false
		if (stockMin.value != null && p.stock < stockMin.value) return false
		if (stockMax.value != null && p.stock > stockMax.value) return false
		if (priceMin.value != null && p.price < priceMin.value) return false
		if (priceMax.value != null && p.price > priceMax.value) return false
		if (desiMin.value != null && (p.desi ?? 0) < desiMin.value) return false
		if (desiMax.value != null && (p.desi ?? 0) > desiMax.value) return false
		return true
	})

	const dir = sortDir.value === 'asc' ? 1 : -1
	switch (sortField.value) {
		case 'name':  list = [...list].sort((a, b) => dir * a.name.localeCompare(b.name, 'tr')); break
		case 'price': list = [...list].sort((a, b) => dir * (a.price - b.price)); break
		case 'stock': list = [...list].sort((a, b) => dir * (a.stock - b.stock)); break
		case 'new':   list = [...list].sort((a, b) => dir * ((a.isNew ? 1 : 0) - (b.isNew ? 1 : 0) || a.id - b.id)); break
		case 'default':
		default:      list = [...list].sort((a, b) => dir * (a.id - b.id))
	}

	return list
})

const paginated = computed(() => {
	const start = (currentPage.value - 1) * itemsPerPage
	return filtered.value.slice(start, start + itemsPerPage)
})

// Filtre değişince sayfa 1'e dön
watch(
	[searchQuery, searchField, selectedCategory, selectedBrand, stockMin, stockMax, priceMin, priceMax, desiMin, desiMax, sortField, sortDir],
	() => { currentPage.value = 1 },
)

/* ── Aktif filtre sayısı ── */
const activeFilterCount = computed(() => {
	let n = 0
	if (searchQuery.value.trim()) n++
	if (selectedCategory.value) n++
	if (selectedBrand.value) n++
	if (stockMin.value != null || stockMax.value != null) n++
	if (priceMin.value != null || priceMax.value != null) n++
	if (desiMin.value != null || desiMax.value != null) n++
	return n
})

function clearFilters() {
	searchQuery.value = ''
	searchField.value = 'name'
	selectedCategory.value = ''
	selectedBrand.value = ''
	stockMin.value = null
	stockMax.value = null
	priceMin.value = null
	priceMax.value = null
	desiMin.value = null
	desiMax.value = null
}

/* ── Ürün formu (tam sayfa) ── */
function openNewProduct() {
	router.visit('/products/create')
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
const allVisibleSelected = computed(() =>
	paginated.value.length > 0 && paginated.value.every((p) => selected.value.has(p.id)),
)
function toggleSelectAllVisible() {
	const ids = paginated.value.map((p) => p.id)
	const next = new Set(selected.value)
	if (allVisibleSelected.value) ids.forEach((id) => next.delete(id))
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
			<b>{{ filtered.length }}</b> ürün listeleniyor
			<OzPill v-if="activeFilterCount > 0" tone="accent">{{ activeFilterCount }} filtre aktif</OzPill>
		</template>
		<template #actions>
			<OzButton v-if="canAdd" variant="primary" icon="plus" @click="openNewProduct">Yeni Ürün</OzButton>
		</template>
	</OzPageHeader>
	<OzModuleTabs />

	<div class="stats">
		<OzStatTile v-for="s in stats" :key="s.key" :label="s.label" :value="s.value" :icon="s.icon" :tone="s.tone" />
	</div>

	<!-- Üst bar: Filtreler toggle + hızlı arama -->
	<div class="toolbar">
		<OzButton :class="{ 'is-open': filtersOpen }" icon="sliders" @click="filtersOpen = !filtersOpen">
			Filtreler
			<span v-if="activeFilterCount > 0" class="filter-badge">{{ activeFilterCount }}</span>
		</OzButton>
		<OzInput v-model="searchQuery" icon="search" placeholder="Hızlı ara…" class="search-box" />
	</div>

	<!-- Açılır filtre paneli -->
	<div ref="filterWrap" class="filter-collapse" :inert="!filtersOpen" :aria-hidden="!filtersOpen">
		<div ref="filterPanel" class="filter-panel">
			<div class="filter-grid">
				<OzField label="Arama Terimi">
					<div class="search-with-select">
						<OzInput v-model="searchQuery" placeholder="Ara…" />
						<OzSelect v-model="searchField" :options="searchFieldOptions" />
					</div>
				</OzField>

				<OzField label="Sıralama">
					<div class="sort-row">
						<OzSelect v-model="sortField" :options="sortFieldOptions" />
						<OzSelect v-model="sortDir" :options="sortDirOptions" />
					</div>
				</OzField>

				<OzField label="Kategori">
					<OzSelect v-model="selectedCategory" :options="categoryOptions" />
				</OzField>

				<OzField label="Marka">
					<OzSelect v-model="selectedBrand" :options="brandOptions" />
				</OzField>

				<OzField label="Stok Miktarı">
					<div class="range-row">
						<OzInput v-model.number="stockMin" type="number" min="0" placeholder="0" />
						<span class="range-sep">ile</span>
						<OzInput v-model.number="stockMax" type="number" min="0" placeholder="0" />
					</div>
				</OzField>

				<OzField label="Satış Fiyatı">
					<div class="range-row">
						<OzInput v-model.number="priceMin" type="number" min="0" placeholder="0.00" />
						<span class="range-sep">ile</span>
						<OzInput v-model.number="priceMax" type="number" min="0" placeholder="0.00" />
					</div>
				</OzField>

				<OzField label="Desi">
					<div class="range-row">
						<OzInput v-model.number="desiMin" type="number" min="0" placeholder="0.00" />
						<span class="range-sep">ile</span>
						<OzInput v-model.number="desiMax" type="number" min="0" placeholder="0.00" />
					</div>
				</OzField>
			</div>

			<div class="filter-actions">
				<OzButton size="sm" @click="clearFilters">Temizle</OzButton>
			</div>
		</div>
	</div>

	<!-- Boş durum -->
	<OzEmptyState v-if="!paginated.length" icon="search" title="Eşleşen ürün bulunamadı" description="Filtreleri gevşeterek tekrar deneyebilirsiniz.">
		<template #actions><OzButton size="sm" icon="x" @click="clearFilters">Filtreleri Temizle</OzButton></template>
	</OzEmptyState>

	<!-- Tablo -->
	<div v-else class="table-wrap">
		<div class="table-scroll">
			<table class="product-table">
				<thead>
					<tr>
						<th v-if="canDelete" class="col-check">
							<input type="checkbox" :checked="allVisibleSelected" aria-label="Tümünü seç" @change="toggleSelectAllVisible">
						</th>
						<th class="col-id">ID</th>
						<th class="col-img">Görsel</th>
						<th class="col-name">Ürün Adı</th>
						<th class="col-price">Site Fiyatı</th>
						<th class="col-mp">Pazaryeri</th>
						<th class="col-stock">Stok</th>
						<th class="col-readiness">Hazırlık</th>
						<th class="col-actions"></th>
					</tr>
				</thead>
				<tbody>
					<tr
						v-for="p in paginated"
						:key="p.id"
						:class="{ 'row-selected': isSelected(p.id), 'row-out': p.stock === 0 }"
					>
						<td v-if="canDelete" class="col-check">
							<input type="checkbox" :checked="isSelected(p.id)" :aria-label="`${p.name} seç`" @change="toggleRow(p.id)">
						</td>
						<td class="col-id mono">#{{ p.id }}</td>
						<td class="col-img">
							<div class="thumb">
								<img :src="p.image || '/images/product-placeholder.svg'" :alt="p.name" loading="lazy">
							</div>
						</td>
						<td class="col-name">
							<div class="name-cell">
								<span class="name-text">{{ p.name }}</span>
								<span class="name-line">Kategori: <b>{{ p.category || '—' }}</b></span>
								<span class="name-line">Kodu: <b>{{ p.sku || '—' }}</b></span>
								<span class="name-line">Barkod: <b>{{ p.barcode || '—' }}</b></span>
							</div>
						</td>
						<td class="col-price">
							<div class="price-cell">
								<span class="price-val">{{ formatPrice(p.price) }}</span>
								<span v-if="p.marketPrice != null" class="price-pa">P: {{ formatPrice(p.marketPrice) }}</span>
								<span v-if="p.purchasePrice != null" class="price-pa">A: {{ formatPrice(p.purchasePrice) }}</span>
							</div>
						</td>
						<td class="col-mp">
							<div v-if="marketplacesFor(p).length" class="mp-list">
								<button
									v-for="mp in marketplacesFor(p)"
									:key="mp.key"
									type="button"
									class="mp-item"
									:class="{ sent: listingSummary(p, mp)?.isSent }"
									:title="mp.name"
									:disabled="!canListing"
									@click="canListing && openListing(p, mp)"
								>
									<img
										v-if="!logoFailed[mp.key]"
										class="mp-logo-img"
										:src="`/images/marketplaces/${mp.key}.svg`"
										:alt="mp.name"
										@error="onLogoError(mp.key)"
									>
									<span v-else class="mp-badge" :style="{ background: mp.color || '#888' }">{{ mp.logoText }}</span>
									<span class="mp-price">{{ mpPrice(p, mp) }}</span>
								</button>
							</div>
							<span v-else class="mp-empty">—</span>
						</td>
						<td class="col-stock">
							<OzPill v-if="p.stock === 0" tone="danger">Tükendi</OzPill>
							<OzPill v-else-if="p.stock < 20" tone="warning">{{ p.stock }}</OzPill>
							<OzPill v-else tone="success">{{ p.stock }}</OzPill>
						</td>
						<td class="col-readiness">
							<div class="readiness-badges">
								<span
									v-for="cap in readinessCapabilities"
									:key="cap.key"
									class="readiness-dot"
									:class="{ ready: p.readiness?.[cap.key]?.ready }"
									:title="readinessTooltip(p, cap)"
								><component :is="cap.icon" :size="12" /></span>
							</div>
						</td>
						<td class="col-actions" @click.stop>
							<OzDropdown :items="rowActions(p)" :label="`${p.name} işlemleri`" @select="(k) => onRowAction(k, p)" />
						</td>
					</tr>
				</tbody>
			</table>
		</div>
	</div>

	<!-- Sayfalama -->
	<div v-if="filtered.length > itemsPerPage" class="pagination-wrap">
		<OzPagination v-model="currentPage" :total="filtered.length" :page-size="itemsPerPage" />
	</div>

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
</template>

<style scoped>
.stats { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; margin-bottom: 14px; }
@media (max-width: 1040px) { .stats { grid-template-columns: repeat(2, minmax(0, 1fr)); } }

/* ── Üst araç çubuğu ── */
.toolbar { display: flex; align-items: center; gap: 10px; margin-bottom: 12px; flex-wrap: wrap; }
.toolbar :deep(.is-open) { border-color: var(--oz-accent); color: var(--oz-accent); background: var(--oz-accent-soft); }
.filter-badge {
	display: inline-flex; align-items: center; justify-content: center;
	min-width: 18px; height: 18px; padding: 0 5px; margin-left: 2px;
	background: var(--oz-accent); color: #fff;
	font-size: 11px; font-weight: 700; border-radius: 999px;
}
.search-box { flex: 1; min-width: 240px; max-width: 360px; }

/* ── Filtre paneli ── */
/* Yükseklik/opaklık @vueuse/motion ile animasyonlu; başlangıç kapalı (JS gelmeden de gizli). */
.filter-collapse { height: 0; opacity: 0; overflow: hidden; }
.filter-panel {
	background: var(--oz-surface);
	border: 1px solid var(--oz-border);
	border-radius: var(--oz-radius);
	padding: 18px 20px;
	margin-bottom: 16px;
	box-shadow: var(--oz-shadow-sm);
}
.filter-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px 18px; }
@media (max-width: 1100px) { .filter-grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 620px)  { .filter-grid { grid-template-columns: 1fr; } }

.search-with-select { display: grid; grid-template-columns: 1fr 130px; gap: 6px; }
.sort-row { display: grid; grid-template-columns: 1fr 1fr; gap: 6px; }
.range-row { display: flex; align-items: center; gap: 8px; }
.range-row > * { flex: 1; min-width: 0; }
.range-sep { font-size: 12px; color: var(--oz-muted); flex-shrink: 0; }

.filter-actions { display: flex; align-items: center; gap: 14px; margin-top: 16px; padding-top: 14px; border-top: 1px solid var(--oz-border-soft); }

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

/* ── Tablo ── */
.table-wrap { background: var(--oz-surface); border: 1px solid var(--oz-border); border-radius: var(--oz-radius); overflow: hidden; box-shadow: var(--oz-shadow-sm); }
.table-scroll { overflow-x: auto; }
.product-table { width: 100%; border-collapse: collapse; }
.product-table thead th.col-price { text-align: center; }
.product-table thead th {
	text-align: left; font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em;
	color: var(--oz-muted); background: var(--oz-th-bg); padding: 12px 14px; border-bottom: 1px solid var(--oz-border); white-space: nowrap;
}
.product-table tbody td { padding: 11px 14px; border-top: 1px solid var(--oz-border-soft); font-size: 12.5px; color: var(--oz-ink); vertical-align: middle; }
.product-table tbody tr { transition: background .12s; }
.product-table tbody tr:hover td { background: var(--oz-surface-2); }
.product-table tbody tr.row-selected td { background: var(--oz-accent-soft); }
.product-table tbody tr.row-out { opacity: .7; }

.col-check { width: 44px; }
.col-id { width: 64px; }
.col-img { width: 60px; }
.col-name { width: 180px; }
.col-price { width: 120px; }
.col-mp { width: 360px; }
.col-stock { width: 80px; }
.col-readiness { width: 90px; }
.col-actions { width: 48px; }

.readiness-badges { display: flex; gap: 4px; }
.readiness-dot { width: 22px; height: 22px; border-radius: 7px; display: inline-flex; align-items: center; justify-content: center; color: var(--oz-muted); background: var(--oz-surface-2); cursor: default; }
.readiness-dot.ready { background: var(--oz-success-soft); color: var(--oz-success); }

.mono { font-family: ui-monospace, monospace; color: var(--oz-muted); font-size: 12px; }

.thumb { width: 44px; height: 44px; border-radius: 8px; overflow: hidden; background: var(--oz-surface-2); flex-shrink: 0; }
.thumb img { width: 100%; height: 100%; object-fit: cover; }

.name-cell { display: flex; flex-direction: column; gap: 3px; min-width: 0; }
.name-text { font-weight: 700; color: var(--oz-ink-soft); font-size: 13px; }
.name-line { font-size: 11.5px; color: var(--oz-muted); }
.name-line b { color: var(--oz-ink); font-weight: 600; }

.col-price { white-space: nowrap; text-align: center; }
.price-cell { display: flex; flex-direction: column; align-items: center; gap: 2px; }
.price-val { font-weight: 800; color: var(--oz-ink-soft); font-size: 13px; }
.price-pa { font-size: 11px; color: var(--oz-muted); }

.mp-list { display: flex; flex-wrap: nowrap; gap: 8px; overflow-x: auto; padding-bottom: 2px; }
.mp-item { display: flex; flex-direction: column; align-items: center; gap: 3px; flex: 0 0 auto; background: none; border: none; cursor: pointer; padding: 2px; opacity: .55; transition: opacity .12s; }
.mp-item:hover { opacity: 1; }
.mp-item.sent { opacity: 1; }
.mp-item:disabled { cursor: default; pointer-events: none; }
.mp-badge { display: inline-flex; align-items: center; justify-content: center; width: 26px; height: 26px; color: #fff; font-size: 10px; font-weight: 800; border-radius: 7px; letter-spacing: 0.02em; }
.mp-logo-img { width: 26px; height: 26px; display: block; border-radius: 7px; }
.mp-price { font-size: 11px; font-weight: 600; color: var(--oz-muted); white-space: nowrap; }
.mp-empty { color: var(--oz-muted-2, var(--oz-muted)); }

.pagination-wrap { padding: 14px 4px 4px; }

@media (max-width: 760px) {
	.col-mp, .col-id, .col-readiness { display: none; }
}
</style>
