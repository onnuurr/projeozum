<template>
	<Head title="Ürün Kataloğu" />
	<div class="page-products">
		<Breadcrumb
			:items="[
				{ label: 'Ana Sayfa', to: '/workflow', icon: 'home' },
				{ label: 'Katalog' },
				{ label: 'Tüm Ürünler' },
			]"
		/>

		<!-- Üst bar: Filtreler toggle + hızlı arama + Yeni Ürün -->
		<div class="toolbar">
			<button class="filter-toggle" :class="{ open: filtersOpen }" @click="filtersOpen = !filtersOpen">
				<svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
					<line x1="4" y1="6" x2="20" y2="6" /><line x1="7" y1="12" x2="17" y2="12" /><line x1="10" y1="18" x2="14" y2="18" />
				</svg>
				<span>Filtreler</span>
				<span v-if="activeFilterCount > 0" class="filter-badge">{{ activeFilterCount }}</span>
				<svg class="toggle-chevron" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
					<polyline points="6 9 12 15 18 9" />
				</svg>
			</button>

			<div class="search-box">
				<svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
					<circle cx="11" cy="11" r="8" /><path d="M21 21l-4.35-4.35" />
				</svg>
				<input v-model="searchQuery" type="text" placeholder="Hızlı ara.." />
			</div>

			<button v-if="canAdd" class="btn btn-primary btn-with-icon toolbar-add" @click="openNewProduct">
				<svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
					<path d="M12 5v14M5 12h14" />
				</svg>
				Yeni Ürün
			</button>
		</div>

		<!-- Açılır filtre paneli -->
		<div v-show="filtersOpen" class="filter-panel">
			<div class="filter-grid">
				<!-- Arama Terimi + alan seçici -->
				<div class="filter-field">
					<label class="filter-label">Arama Terimi</label>
					<div class="search-with-select">
						<input v-model="searchQuery" type="text" class="filter-input" placeholder="Ara..." />
						<div class="search-select">
							<CustomSelect v-model="searchField" :options="searchFieldOptions" :show-label="false" />
						</div>
					</div>
				</div>

				<!-- Sıralama + yön -->
				<div class="filter-field">
					<label class="filter-label">Sıralama</label>
					<div class="sort-row">
						<CustomSelect v-model="sortField" :options="sortFieldOptions" :show-label="false" />
						<CustomSelect v-model="sortDir" :options="sortDirOptions" :show-label="false" />
					</div>
				</div>

				<!-- Kategori -->
				<div class="filter-field">
					<label class="filter-label">Kategori</label>
					<CustomSelect v-model="selectedCategory" :options="categoryOptions" :show-label="false" placeholder="Kategori Seçilmedi" />
				</div>

				<!-- Marka -->
				<div class="filter-field">
					<label class="filter-label">Marka</label>
					<CustomSelect v-model="selectedBrand" :options="brandOptions" :show-label="false" placeholder="Marka Seçilmedi" />
				</div>

				<!-- Stok Miktarı -->
				<div class="filter-field">
					<label class="filter-label">Stok Miktarı</label>
					<div class="range-row">
						<input v-model.number="stockMin" type="number" min="0" class="filter-input" placeholder="0" />
						<span class="range-sep">ile</span>
						<input v-model.number="stockMax" type="number" min="0" class="filter-input" placeholder="0" />
					</div>
				</div>

				<!-- Satış Fiyatı -->
				<div class="filter-field">
					<label class="filter-label">Satış Fiyatı</label>
					<div class="range-row">
						<input v-model.number="priceMin" type="number" min="0" class="filter-input" placeholder="0.00" />
						<span class="range-sep">ile</span>
						<input v-model.number="priceMax" type="number" min="0" class="filter-input" placeholder="0.00" />
					</div>
				</div>

				<!-- Desi -->
				<div class="filter-field">
					<label class="filter-label">Desi</label>
					<div class="range-row">
						<input v-model.number="desiMin" type="number" min="0" class="filter-input" placeholder="0.00" />
						<span class="range-sep">ile</span>
						<input v-model.number="desiMax" type="number" min="0" class="filter-input" placeholder="0.00" />
					</div>
				</div>
			</div>

			<div class="filter-actions">
				<button class="btn btn-primary btn-sm" @click="filtersOpen = true">Filtrele</button>
				<button class="clear-link" @click="clearFilters">Temizle</button>
			</div>
		</div>

		<!-- Başlık -->
		<div class="list-head">
			<div>
				<h1 class="page-title">Ürün Kataloğu</h1>
				<p class="page-subtitle">
					<strong>{{ filtered.length }}</strong> ürün listeleniyor
					<span v-if="activeFilterCount > 0" class="filter-chip">{{ activeFilterCount }} filtre aktif</span>
				</p>
			</div>
		</div>

		<!-- Toplu seçim çubuğu -->
		<div v-if="canDelete && selectedCount" class="bulk-bar">
			<span class="bulk-info"><strong>{{ selectedCount }}</strong> ürün seçili</span>
			<div class="bulk-actions">
				<button class="btn btn-ghost btn-sm" @click="clearSelection">Temizle</button>
				<button class="btn btn-danger btn-sm" :disabled="bulkBusy" @click="bulkDelete">🗑️ Seçilenleri Sil ({{ selectedCount }})</button>
			</div>
		</div>

		<!-- Boş durum -->
		<div v-if="paginated.length === 0" class="empty-state">
			<svg width="42" height="42" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
				<circle cx="11" cy="11" r="8" /><path d="M21 21l-4.35-4.35" />
			</svg>
			<h3>Eşleşen ürün bulunamadı</h3>
			<p>Filtreleri gevşeterek tekrar deneyebilirsiniz.</p>
			<button class="btn btn-secondary btn-sm" @click="clearFilters">Filtreleri Temizle</button>
		</div>

		<!-- Tablo -->
		<div v-else class="table-wrap">
			<div class="table-scroll">
			<table class="product-table">
				<thead>
					<tr>
						<th v-if="canDelete" class="col-check">
							<label class="cb">
								<input type="checkbox" :checked="allVisibleSelected" @change="toggleSelectAllVisible" />
							</label>
						</th>
						<th class="col-id">ID</th>
						<th class="col-img">Görsel</th>
						<th class="col-name">Ürün Adı</th>
						<th class="col-price">Site Fiyatı</th>
						<th class="col-mp">Pazaryeri</th>
						<th class="col-stock">Stok</th>
						<th class="col-readiness">Hazırlık</th>
						<th class="col-actions">İşlemler</th>
					</tr>
				</thead>
				<tbody>
					<tr
						v-for="p in paginated"
						:key="p.id"
						:class="{ 'row-selected': isSelected(p.id), 'row-out': p.stock === 0 }"
					>
						<td v-if="canDelete" class="col-check">
							<label class="cb">
								<input type="checkbox" :checked="isSelected(p.id)" @change="toggleRow(p.id)" />
							</label>
						</td>
						<td class="col-id mono">#{{ p.id }}</td>
						<td class="col-img">
							<div class="thumb">
								<img :src="p.image || '/images/product-placeholder.svg'" :alt="p.name" loading="lazy" />
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
								<svg
									class="status-dot"
									:class="{ ok: p.stock > 0, off: p.stock === 0 }"
									width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"
								>
									<circle cx="12" cy="12" r="10" /><path d="M8 12l2.5 2.5L16 9" />
								</svg>
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
									:disabled="!canAdd"
									@click="canAdd && openListing(p, mp)"
								>
									<img
										v-if="!logoFailed[mp.key]"
										class="mp-logo-img"
										:src="`/images/marketplaces/${mp.key}.svg`"
										:alt="mp.name"
										@error="onLogoError(mp.key)"
									/>
									<span v-else class="mp-badge" :style="{ background: mp.color || '#888' }">{{ mp.logoText }}</span>
									<span class="mp-price">{{ mpPrice(p, mp) }}</span>
								</button>
							</div>
							<span v-else class="mp-empty">—</span>
						</td>
						<td class="col-stock">
							<span v-if="p.stock === 0" class="stock-pill stock-out">Tükendi</span>
							<span v-else-if="p.stock < 20" class="stock-pill stock-low">{{ p.stock }}</span>
							<span v-else class="stock-pill stock-ok">{{ p.stock }}</span>
						</td>
						<td class="col-readiness">
							<div class="readiness-badges">
								<span
									v-for="cap in readinessCapabilities"
									:key="cap.key"
									class="readiness-dot"
									:class="{ ready: p.readiness?.[cap.key]?.ready }"
									:title="readinessTooltip(p, cap)"
								>{{ cap.icon }}</span>
							</div>
						</td>
						<td class="col-actions">
							<div class="action-btns">
								<Link :href="`/products/${p.slug ?? p.id}`" class="icon-btn" title="Önizleme">
									<svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
										<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" /><circle cx="12" cy="12" r="3" />
									</svg>
								</Link>
								<button v-if="canAdd" class="icon-btn" title="Düzenle" @click="editProduct(p)">
									<svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
										<path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7" />
										<path d="M18.5 2.5a2.12 2.12 0 013 3L12 15l-4 1 1-4 9.5-9.5z" />
									</svg>
								</button>
								<button v-if="canDelete" class="icon-btn icon-danger" title="Sil" @click="confirmDeleteProduct(p)">
									<svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
										<polyline points="3 6 5 6 21 6" /><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2" />
									</svg>
								</button>
							</div>
						</td>
					</tr>
				</tbody>
			</table>
			</div>
		</div>

		<!-- Sayfalama -->
		<div v-if="totalPages > 1" class="pagination">
			<div class="pagination-info">
				Sayfa <span>{{ currentPage }}</span> / <span>{{ totalPages }}</span>
			</div>
			<div class="pagination-controls">
				<button class="pagination-btn" :disabled="currentPage === 1" @click="currentPage--">‹</button>
				<button
					v-for="page in visiblePages"
					:key="page"
					class="pagination-btn"
					:class="{ active: currentPage === page }"
					@click="currentPage = page"
				>{{ page }}</button>
				<button class="pagination-btn" :disabled="currentPage === totalPages" @click="currentPage++">›</button>
			</div>
		</div>

		<MarketplaceListingDrawer
			:open="listingOpen"
			:product-id="activeProduct?.id"
			:marketplace="activeMarketplace"
			@close="listingOpen = false"
			@saved="onListingSaved"
		/>
	</div>
</template>

<script setup>
import { ref, computed, watch, inject, reactive } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Breadcrumb from '@/Components/Breadcrumb.vue'
import CustomSelect from '@/Components/CustomSelect.vue'
import MarketplaceListingDrawer from '../Components/MarketplaceListingDrawer.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
	products: { type: Array, default: () => [] },
	categories: { type: Array, default: () => [] },
	brands: { type: Array, default: () => [] },
	favoriteIds: { type: Array, default: () => [] },
	marketplaces: { type: Array, default: () => [] },
})

const showToast = inject('showToast')
const $swal = inject('$swal')

const page = usePage()
const can = (perm) => (page.props.auth?.permissions ?? []).includes(perm)
const canAdd    = computed(() => can('product.add'))
const canDelete = computed(() => can('product.delete'))

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

/* ── Filtre state ── */
const filtersOpen = ref(false)
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
	{ key: 'try_on', icon: '👕', label: 'Sanal Giydirme' },
	{ key: 'creative_render', icon: '🎨', label: 'Kreatif Görsel Üretimi' },
	{ key: 'marketplace_push', icon: '🏪', label: 'Pazaryeri Gönderimi' },
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

const totalPages = computed(() => Math.max(1, Math.ceil(filtered.value.length / itemsPerPage)))

const paginated = computed(() => {
	const start = (currentPage.value - 1) * itemsPerPage
	return filtered.value.slice(start, start + itemsPerPage)
})

const visiblePages = computed(() => {
	const total = totalPages.value
	const current = currentPage.value
	if (total <= 7) return Array.from({ length: total }, (_, i) => i + 1)
	if (current <= 4) return [1, 2, 3, 4, 5, total]
	if (current >= total - 3) return [1, total - 4, total - 3, total - 2, total - 1, total]
	return [1, current - 1, current, current + 1, total]
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

async function confirmDeleteProduct(product) {
	const ok = await $swal.dangerConfirm({
		title: 'Ürünü Sil',
		html: `<strong>${product.name}</strong> silinecek.<br>Bu üründeki <strong>${product.variants?.length ?? 0}</strong> varyant da kalıcı olarak kaldırılacak.`,
		confirmText: 'Sil',
		cancelText: 'Vazgeç',
	})
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
	const ok = await $swal.dangerConfirm({
		title: 'Ürünleri Sil',
		html: `<strong>${ids.length}</strong> ürün ve bunlara bağlı varyantlar kalıcı olarak silinecek.`,
		confirmText: 'Sil',
		cancelText: 'Vazgeç',
	})
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

<style scoped>
.page-products { display: flex; flex-direction: column; }

/* ── Üst araç çubuğu ── */
.toolbar {
	display: flex;
	align-items: center;
	gap: 10px;
	margin-bottom: 12px;
	flex-wrap: wrap;
}

.filter-toggle {
	display: inline-flex;
	align-items: center;
	gap: 8px;
	background: #fff;
	border: 1px solid #e8e8f0;
	border-radius: 9px;
	padding: 8px 14px;
	font-family: inherit;
	font-size: 13px;
	font-weight: 600;
	color: #1a1a2e;
	cursor: pointer;
	transition: border-color .15s, background .15s;
}
.filter-toggle:hover { border-color: #ccc; }
.filter-toggle.open { border-color: rgb(var(--color-primary)); }
.filter-toggle svg { color: #888; }
.filter-toggle .toggle-chevron { transition: transform .2s; }
.filter-toggle.open .toggle-chevron { transform: rotate(180deg); }
.filter-badge {
	display: inline-flex; align-items: center; justify-content: center;
	min-width: 18px; height: 18px; padding: 0 5px;
	background: rgb(var(--color-primary)); color: #fff;
	font-size: 11px; font-weight: 700; border-radius: 999px;
}

.search-box {
	display: flex;
	align-items: center;
	gap: 6px;
	background: #fff;
	border: 1px solid #e8e8f0;
	border-radius: 9px;
	padding: 8px 12px;
	flex: 1;
	min-width: 240px;
}
.search-box svg { color: #aaa; flex-shrink: 0; }
.search-box input {
	border: none; background: none; outline: none;
	font-family: inherit; font-size: 13px;
	width: 100%;
}
.search-box input::placeholder { color: #bbb; }

.toolbar-add { flex-shrink: 0; }

/* ── Filtre paneli ── */
.filter-panel {
	background: #fff;
	border: 1px solid #ebebf0;
	border-radius: 14px;
	padding: 18px 20px;
	margin-bottom: 16px;
	box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
}

.filter-grid {
	display: grid;
	grid-template-columns: repeat(4, 1fr);
	gap: 14px 18px;
}

@media (max-width: 1100px) { .filter-grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 620px)  { .filter-grid { grid-template-columns: 1fr; } }

.filter-field { display: flex; flex-direction: column; gap: 5px; min-width: 0; }
.filter-label { font-size: 12.5px; font-weight: 600; color: #555; }

.filter-input {
	height: 36px;
	border: 1.5px solid #e8e8f0;
	border-radius: 9px;
	background: #fafafe;
	padding: 0 12px;
	font-family: inherit;
	font-size: 13px;
	color: #1a1a2e;
	outline: none;
	width: 100%;
	transition: border-color .15s, box-shadow .15s, background .15s;
}
.filter-input:focus {
	border-color: rgb(var(--color-primary));
	box-shadow: 0 0 0 3px rgb(var(--color-primary) / 0.1);
	background: #fff;
}

.search-with-select { display: grid; grid-template-columns: 1fr 130px; gap: 6px; }
.sort-row { display: grid; grid-template-columns: 1fr 1fr; gap: 6px; }

.range-row { display: flex; align-items: center; gap: 8px; }
.range-row .filter-input { flex: 1; min-width: 0; }
.range-sep { font-size: 12px; color: #aaa; flex-shrink: 0; }

.filter-actions {
	display: flex;
	align-items: center;
	gap: 14px;
	margin-top: 16px;
	padding-top: 14px;
	border-top: 1px solid #f0f0f5;
}
.clear-link {
	background: none; border: none;
	color: #888; font-family: inherit; font-size: 12.5px;
	cursor: pointer; padding: 4px 6px; border-radius: 6px;
}
.clear-link:hover { color: rgb(var(--color-primary)); background: rgb(var(--color-primary-soft)); }

/* ── Başlık ── */
.list-head { margin-bottom: 12px; }
.page-title { font-size: 22px; font-weight: 700; color: #1a1a2e; line-height: 1.2; }
.page-subtitle {
	font-size: 13px; color: #888; margin-top: 4px;
	display: flex; align-items: center; gap: 8px;
}
.page-subtitle strong { color: #1a1a2e; font-weight: 700; }
.filter-chip {
	display: inline-flex; align-items: center;
	padding: 2px 8px;
	background: rgb(var(--color-primary-soft));
	color: rgb(var(--color-primary));
	border-radius: 999px; font-size: 11px; font-weight: 600;
}

/* ── Toplu seçim çubuğu ── */
.bulk-bar {
	display: flex;
	align-items: center;
	gap: 14px;
	background: #faf5ff;
	border: 1px solid #ece5fb;
	border-radius: 10px;
	padding: 9px 14px;
	margin-bottom: 14px;
}
.bulk-info { font-size: 12.5px; color: rgb(var(--color-primary-hover)); }
.bulk-info strong { font-weight: 800; }
.bulk-actions { display: flex; gap: 8px; margin-left: auto; }
.btn-danger { background: #dc2626; color: #fff; border: none; }
.btn-danger:hover:not(:disabled) { background: #b91c1c; }
.btn-danger:disabled { opacity: .55; cursor: not-allowed; }

/* ── Tablo ── */
.table-wrap {
	background: #fff;
	border: 1px solid #ebebf0;
	border-radius: 14px;
	overflow: hidden;
	box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
}

.product-table { width: 100%; border-collapse: collapse; }

.product-table thead th.col-price { text-align: center; }
.product-table thead th {
	text-align: left;
	font-size: 11.5px;
	font-weight: 700;
	text-transform: uppercase;
	letter-spacing: 0.04em;
	color: #888;
	background: #fafafc;
	padding: 12px 14px;
	border-bottom: 1px solid #ebebf0;
	white-space: nowrap;
}

.product-table tbody td {
	padding: 11px 14px;
	border-bottom: 1px solid #f2f2f6;
	font-size: 13px;
	color: #1a1a2e;
	vertical-align: middle;
}
.product-table tbody tr:last-child td { border-bottom: none; }
.product-table tbody tr { transition: background .12s; }
.product-table tbody tr:hover { background: #fafafe; }
.product-table tbody tr.row-selected { background: rgb(var(--color-primary-soft)); }
.product-table tbody tr.row-out { opacity: .7; }

.col-check { width: 44px; }
.col-id { width: 64px; }
.col-img { width: 60px; }
.col-name { width: 180px; }
.col-price { width: 120px; }
.col-mp { width: 360px; }
.col-stock { width: 80px; }
.col-readiness { width: 90px; }
.col-actions { width: 120px; }

.readiness-badges { display: flex; gap: 4px; }
.readiness-dot {
	width: 22px; height: 22px;
	border-radius: 7px;
	display: inline-flex; align-items: center; justify-content: center;
	font-size: 11px;
	background: #f5f5f8;
	filter: grayscale(1);
	opacity: .45;
	cursor: default;
}
.readiness-dot.ready { background: #dcfce7; filter: none; opacity: 1; }

.mono { font-family: 'SF Mono', Consolas, monospace; color: #888; font-size: 12px; }

.cb { display: inline-flex; cursor: pointer; }
.cb input { width: 16px; height: 16px; accent-color: rgb(var(--color-primary)); cursor: pointer; }

.thumb {
	width: 44px; height: 44px;
	border-radius: 8px; overflow: hidden;
	background: #f5f5fa; flex-shrink: 0;
}
.thumb img { width: 100%; height: 100%; object-fit: cover; }

.name-cell { display: flex; flex-direction: column; gap: 3px; min-width: 0; }
.name-text { font-weight: 700; color: #1a1a2e; font-size: 13.5px; }
.name-line { font-size: 11.5px; color: #888; }
.name-line b { color: #555; font-weight: 600; }

.col-price { white-space: nowrap; text-align: center; }
.price-cell { display: flex; flex-direction: column; align-items: center; gap: 2px; }
.status-dot { margin-bottom: 2px; }
.status-dot.ok { color: #16a34a; }
.status-dot.off { color: #cbd5e1; }
.price-val { font-weight: 800; color: #1a1a2e; font-size: 13.5px; }
.price-pa { font-size: 11px; color: #888; }

.mp-list {
	display: flex; flex-wrap: nowrap; gap: 8px;
	overflow-x: auto; padding-bottom: 2px;
}
.mp-list::-webkit-scrollbar { height: 4px; }
.mp-list::-webkit-scrollbar-thumb { background: #ddd; border-radius: 4px; }
.mp-item { display: flex; flex-direction: column; align-items: center; gap: 3px; flex: 0 0 auto; background: none; border: none; cursor: pointer; padding: 2px; opacity: .55; transition: opacity .12s; }
.mp-item:hover { opacity: 1; }
.mp-item.sent { opacity: 1; }
.mp-item:disabled { cursor: default; pointer-events: none; }
.mp-badge {
	display: inline-flex; align-items: center; justify-content: center;
	width: 26px; height: 26px;
	color: #fff; font-size: 10px; font-weight: 800;
	border-radius: 7px; letter-spacing: 0.02em;
}
.mp-logo-img { width: 26px; height: 26px; display: block; border-radius: 7px; }
.mp-price { font-size: 11px; font-weight: 600; color: #555; white-space: nowrap; }
.mp-empty { color: #ccc; }

.stock-pill {
	display: inline-flex; align-items: center;
	padding: 3px 9px; border-radius: 999px;
	font-size: 12px; font-weight: 700;
}
.stock-ok  { background: #ecfdf3; color: #16a34a; }
.stock-low { background: #fef9c3; color: #ca8a04; }
.stock-out { background: #fef2f2; color: #dc2626; }

.action-btns { display: flex; gap: 6px; align-items: center; }
.icon-btn {
	width: 30px; height: 30px;
	border-radius: 8px;
	border: 1.5px solid #e8e8f0;
	background: #fff;
	color: #6b7280;
	cursor: pointer;
	display: flex; align-items: center; justify-content: center;
	transition: all .15s;
	text-decoration: none;
}
.icon-btn:hover { background: #f5f5f8; border-color: #d8d8e8; color: #1a1a2e; }
.icon-btn.icon-danger:hover { background: #fef2f2; border-color: #fecaca; color: #ef4444; }

/* ── Boş durum ── */
.empty-state {
	display: flex; flex-direction: column;
	align-items: center; justify-content: center; gap: 8px;
	padding: 60px 20px;
	background: #fff;
	border: 1px dashed #d8d8e8;
	border-radius: 14px;
	color: #888; text-align: center;
}
.empty-state svg { color: #c0c0d8; margin-bottom: 4px; }
.empty-state h3 { font-size: 15px; font-weight: 700; color: #1a1a2e; margin: 0; }
.empty-state p { font-size: 12.5px; color: #888; margin: 0 0 8px 0; }

/* ── Sayfalama ── */
.pagination {
	display: flex; align-items: center; justify-content: space-between;
	padding: 18px 4px 4px; margin-top: 12px;
}
.pagination-info { font-size: 13px; color: #888; }
.pagination-info span { color: #1a1a2e; font-weight: 600; }
.pagination-controls { display: flex; align-items: center; gap: 4px; }
.pagination-btn {
	min-width: 32px; height: 32px; padding: 0 8px;
	border: 1px solid #e8e8f0; border-radius: 6px;
	background: #fff; color: #666;
	font-size: 13px; font-weight: 500; cursor: pointer;
	display: flex; align-items: center; justify-content: center;
	transition: all .15s;
}
.pagination-btn:hover:not(:disabled) { border-color: #ccc; color: #333; background: #f9f9fb; }
.pagination-btn.active { background: #1a1a2e; color: #fff; border-color: #1a1a2e; }
.pagination-btn:disabled { opacity: 0.4; cursor: not-allowed; }

@media (max-width: 760px) {
	.col-mp, .col-id, .col-readiness { display: none; }
}
</style>
