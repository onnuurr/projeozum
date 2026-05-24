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

		<!-- Üst başlık + araç çubuğu -->
		<div class="page-header">
			<div>
				<h1 class="page-title">Ürün Kataloğu</h1>
				<p class="page-subtitle">
					<strong>{{ filtered.length }}</strong> ürün listeleniyor
					<span v-if="activeFilterCount > 0" class="filter-chip">{{ activeFilterCount }} filtre aktif</span>
				</p>
			</div>

			<div class="header-tools">
				<button v-if="canAdd" class="btn btn-primary btn-with-icon" @click="openNewProduct">
					<svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
						<path d="M12 5v14M5 12h14" />
					</svg>
					Yeni Ürün
				</button>

				<div class="search-box">
					<svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
						<circle cx="11" cy="11" r="8" /><path d="M21 21l-4.35-4.35" />
					</svg>
					<input v-model="searchQuery" type="text" placeholder="Ürün ara..." />
				</div>

				<div class="sort-wrap">
					<CustomSelect
						v-model="sortBy"
						:options="sortOptions"
						:show-label="false"
						style="width: 180px"
					/>
				</div>

				<div class="view-toggle" role="tablist" aria-label="Görünüm">
					<button
						class="view-btn"
						:class="{ active: viewMode === 'grid' }"
						@click="viewMode = 'grid'"
						aria-label="Izgara görünüm"
					>
						<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
							<rect x="3" y="3" width="7" height="7" /><rect x="14" y="3" width="7" height="7" />
							<rect x="14" y="14" width="7" height="7" /><rect x="3" y="14" width="7" height="7" />
						</svg>
					</button>
					<button
						class="view-btn"
						:class="{ active: viewMode === 'list' }"
						@click="viewMode = 'list'"
						aria-label="Liste görünümü"
					>
						<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
							<line x1="8" y1="6" x2="21" y2="6" /><line x1="8" y1="12" x2="21" y2="12" /><line x1="8" y1="18" x2="21" y2="18" />
							<line x1="3" y1="6" x2="3.01" y2="6" /><line x1="3" y1="12" x2="3.01" y2="12" /><line x1="3" y1="18" x2="3.01" y2="18" />
						</svg>
					</button>
				</div>
			</div>
		</div>

		<div class="products-layout">
			<!-- Filtre sidebar -->
			<aside class="filters-sidebar">
				<div class="filters-head">
					<h3>Filtreler</h3>
					<button v-if="activeFilterCount > 0" class="clear-btn" @click="clearFilters">Temizle</button>
				</div>

				<!-- Kategori -->
				<details class="filter-group" open>
					<summary>
						<span>Kategori</span>
						<svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
							<polyline points="6 9 12 15 18 9" />
						</svg>
					</summary>
					<div class="filter-body">
						<label
							v-for="cat in categories"
							:key="cat.slug"
							class="check-row"
						>
							<input
								type="checkbox"
								:value="cat.slug"
								v-model="selectedCategories"
							/>
							<span class="check-text">
								<span class="check-icon">{{ cat.icon }}</span>
								{{ cat.label }}
							</span>
							<span class="check-count">{{ countByCategory(cat.slug) }}</span>
						</label>
					</div>
				</details>

				<!-- Marka -->
				<details class="filter-group" open>
					<summary>
						<span>Marka</span>
						<svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
							<polyline points="6 9 12 15 18 9" />
						</svg>
					</summary>
					<div class="filter-body">
						<label
							v-for="brand in brands"
							:key="brand.slug"
							class="check-row"
						>
							<input
								type="checkbox"
								:value="brand.slug"
								v-model="selectedBrands"
							/>
							<span class="check-text">{{ brand.label }}</span>
							<span class="check-count">{{ countByBrand(brand.slug) }}</span>
						</label>
					</div>
				</details>

				<!-- Cinsiyet -->
				<details class="filter-group" open>
					<summary>
						<span>Cinsiyet</span>
						<svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
							<polyline points="6 9 12 15 18 9" />
						</svg>
					</summary>
					<div class="filter-body chips-body">
						<button
							v-for="g in genderOptions"
							:key="g"
							class="chip"
							:class="{ active: selectedGenders.includes(g) }"
							@click="toggleGender(g)"
						>{{ g }}</button>
					</div>
				</details>

				<!-- Beden -->
				<details class="filter-group">
					<summary>
						<span>Beden</span>
						<svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
							<polyline points="6 9 12 15 18 9" />
						</svg>
					</summary>
					<div class="filter-body chips-body chips-grid">
						<button
							v-for="size in sizeOptions"
							:key="size"
							class="chip chip-size"
							:class="{ active: selectedSizes.includes(size) }"
							@click="toggleSize(size)"
						>{{ size }}</button>
					</div>
				</details>

				<!-- Fiyat -->
				<details class="filter-group" open>
					<summary>
						<span>Fiyat Aralığı</span>
						<svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
							<polyline points="6 9 12 15 18 9" />
						</svg>
					</summary>
					<div class="filter-body">
						<div class="price-inputs">
							<div class="price-field">
								<span class="price-prefix">₺</span>
								<input
									v-model.number="priceMin"
									type="number"
									:min="0"
									:placeholder="String(priceBoundary.min)"
								/>
							</div>
							<span class="price-sep">—</span>
							<div class="price-field">
								<span class="price-prefix">₺</span>
								<input
									v-model.number="priceMax"
									type="number"
									:min="0"
									:placeholder="String(priceBoundary.max)"
								/>
							</div>
						</div>
						<div class="price-presets">
							<button
								v-for="p in pricePresets"
								:key="p.label"
								class="price-preset"
								@click="applyPricePreset(p)"
							>{{ p.label }}</button>
						</div>
					</div>
				</details>

				<!-- Durum -->
				<details class="filter-group" open>
					<summary>
						<span>Durum</span>
						<svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
							<polyline points="6 9 12 15 18 9" />
						</svg>
					</summary>
					<div class="filter-body">
						<label class="toggle-row">
							<input type="checkbox" v-model="inStockOnly" />
							<span class="toggle-text">Sadece stokta olanlar</span>
						</label>
						<label class="toggle-row">
							<input type="checkbox" v-model="onSaleOnly" />
							<span class="toggle-text">Sadece indirimdekiler</span>
						</label>
						<label class="toggle-row">
							<input type="checkbox" v-model="newOnly" />
							<span class="toggle-text">Sadece yeni gelenler</span>
						</label>
						<label class="toggle-row">
							<input type="checkbox" v-model="freeShippingOnly" />
							<span class="toggle-text">Ücretsiz kargolu</span>
						</label>
					</div>
				</details>
			</aside>

			<!-- Ürün ana alan -->
			<main class="products-main">
				<!-- Aktif filtre çipleri -->
				<div v-if="activeFilterCount > 0" class="active-chips">
					<span
						v-for="(label, idx) in activeFilterLabels"
						:key="idx"
						class="active-chip"
						@click="removeActiveFilter(idx)"
					>
						{{ label }}
						<svg width="10" height="10" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
							<path d="M18 6L6 18M6 6l12 12" />
						</svg>
					</span>
					<button class="clear-link" @click="clearFilters">Hepsini temizle</button>
				</div>

				<!-- Grid -->
				<div v-if="paginated.length === 0" class="empty-state">
					<svg width="42" height="42" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
						<circle cx="11" cy="11" r="8" /><path d="M21 21l-4.35-4.35" />
					</svg>
					<h3>Eşleşen ürün bulunamadı</h3>
					<p>Filtreleri gevşeterek tekrar deneyebilirsiniz.</p>
					<button class="btn btn-secondary btn-sm" @click="clearFilters">Filtreleri Temizle</button>
				</div>

				<div v-else-if="viewMode === 'grid'" class="products-grid">
					<ProductCard
						v-for="p in paginated"
						:key="p.id"
						:product="p"
						:is-favorite="favorites.has(p.id)"
						:can-edit="canAdd"
						:can-delete="canDelete"
						:can-manage-access="canManageAccess"
						@toggle-favorite="toggleFavorite"
						@add-to-cart="addToCart"
						@edit="editProduct"
						@delete="confirmDeleteProduct"
						@tenant-access="openTenantAccess"
					/>
				</div>

				<div v-else class="products-list">
					<Link
						v-for="p in paginated"
						:key="p.id"
						:href="`/products/${p.slug ?? p.id}`"
						class="list-row"
						:class="{ 'out-of-stock': p.stock === 0 }"
					>
						<div class="list-image">
							<img :src="`https://picsum.photos/seed/tek-p${p.id}/300/375`" :alt="p.name" loading="lazy" />
							<span v-if="discountFor(p) > 0" class="list-badge">%{{ discountFor(p) }}</span>
						</div>
						<div class="list-body">
							<div class="list-brand">{{ p.brand }}</div>
							<h3 class="list-name">{{ p.name }}</h3>
							<div class="list-meta">
								<span class="meta-item">{{ p.category }}</span>
								<span class="meta-sep">·</span>
								<span class="meta-item">{{ p.gender }}</span>
								<span class="meta-sep">·</span>
								<span class="meta-item mono">{{ p.sku }}</span>
							</div>
							<div class="list-rating">
								<svg
									v-for="n in 5"
									:key="n"
									width="11"
									height="11"
									:fill="n <= Math.round(p.rating) ? '#f59e0b' : 'none'"
									:stroke="n <= Math.round(p.rating) ? '#f59e0b' : '#dcdce6'"
									stroke-width="1.8"
									viewBox="0 0 24 24"
								>
									<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2" />
								</svg>
								<span class="list-review">{{ p.rating.toFixed(1) }} ({{ p.reviewCount }})</span>
							</div>
						</div>
						<div class="list-action" @click.stop.prevent>
							<div class="list-price">
								<span v-if="p.oldPrice" class="list-old-price">{{ formatPrice(p.oldPrice) }}</span>
								<span class="list-new-price" :class="{ 'has-discount': p.oldPrice }">{{ formatPrice(p.price) }}</span>
							</div>
							<div v-if="p.stock === 0" class="list-stock-out">Tükendi</div>
							<div v-else-if="p.stock < 20" class="list-stock-low">Son {{ p.stock }} adet</div>
							<div v-else class="list-stock-ok">Stokta</div>
							<div class="list-buttons">
								<button
									class="icon-btn"
									:class="{ active: favorites.has(p.id) }"
									@click="toggleFavorite(p)"
									aria-label="Favori"
								>
									<svg width="14" height="14" :fill="favorites.has(p.id) ? '#ef4444' : 'none'" :stroke="favorites.has(p.id) ? '#ef4444' : 'currentColor'" stroke-width="2" viewBox="0 0 24 24">
										<path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z" />
									</svg>
								</button>
								<button class="btn btn-primary btn-sm btn-with-icon" :disabled="p.stock === 0" @click="addToCart(p)">
									<svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
										<circle cx="9" cy="21" r="1" /><circle cx="20" cy="21" r="1" />
										<path d="M1 1h4l2.68 13.39a2 2 0 002 1.61h9.72a2 2 0 002-1.61L23 6H6" />
									</svg>
									{{ p.stock === 0 ? 'Tükendi' : 'Sepete Ekle' }}
								</button>
							</div>
						</div>
					</Link>
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
			</main>
		</div>

		<ProductFormDrawer
			v-model="formDrawerOpen"
			:product="editingProduct"
			:categories="props.categories"
			:brands="props.brands"
			:busy="formBusy"
			:errors="formErrors"
			@submit="handleFormSubmit"
		/>
	</div>
</template>

<script setup>
import { ref, computed, watch, inject } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Breadcrumb from '@/Components/Breadcrumb.vue'
import CustomSelect from '@/Components/CustomSelect.vue'
import ProductCard from '@/Components/ProductCard.vue'
import ProductFormDrawer from '@/Components/ProductFormDrawer.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
	products: { type: Array, default: () => [] },
	categories: { type: Array, default: () => [] },
	brands: { type: Array, default: () => [] },
	favoriteIds: { type: Array, default: () => [] },
})

const showToast = inject('showToast')
const cart = inject('cart')
const $swal = inject('$swal')

const page = usePage()
const can = (perm) => (page.props.auth?.permissions ?? []).includes(perm)
const canAdd          = computed(() => can('product.add'))
const canDelete       = computed(() => can('product.delete'))
const canManageAccess = computed(() => can('tenant-access.manage'))

function openTenantAccess(product) {
	router.visit(`/products/${product.id}/tenants`)
}

/* ── Sözlükler ── */
const sortOptions = [
	{ value: 'bestseller', label: 'En Çok Satan' },
	{ value: 'new',        label: 'Yeni Gelenler' },
	{ value: 'priceAsc',   label: 'Fiyat: Düşükten Yükseğe' },
	{ value: 'priceDesc',  label: 'Fiyat: Yüksekten Düşüğe' },
	{ value: 'rating',     label: 'En Çok Beğenilen' },
]

const genderOptions = ['Erkek', 'Kadın', 'Unisex']
const sizeOptions = ['XS', 'S', 'M', 'L', 'XL', 'XXL', '28', '30', '32', '34', '36', '38']

const pricePresets = [
	{ label: '0 — 300',     min: 0,    max: 300 },
	{ label: '300 — 600',   min: 300,  max: 600 },
	{ label: '600 — 1000',  min: 600,  max: 1000 },
	{ label: '1000+',       min: 1000, max: null },
]

/* ── Filtre state ── */
const searchQuery = ref('')
const selectedCategories = ref([])
const selectedBrands = ref([])
const selectedGenders = ref([])
const selectedSizes = ref([])
const priceMin = ref(null)
const priceMax = ref(null)
const inStockOnly = ref(false)
const onSaleOnly = ref(false)
const newOnly = ref(false)
const freeShippingOnly = ref(false)
const sortBy = ref('bestseller')
const viewMode = ref('grid')
const currentPage = ref(1)
const itemsPerPage = 12

const favorites = ref(new Set(props.favoriteIds))

watch(
	() => props.favoriteIds,
	(ids) => { favorites.value = new Set(ids) },
)

/* ── Yardımcılar ── */
const priceBoundary = computed(() => {
	const prices = props.products.map((p) => p.price)
	return {
		min: Math.floor(Math.min(...prices, 0)),
		max: Math.ceil(Math.max(...prices, 0)),
	}
})

function countByCategory(slug) {
	return props.products.filter((p) => p.categorySlug === slug).length
}

function countByBrand(slug) {
	return props.products.filter((p) => p.brand === slug).length
}

function toggleGender(g) {
	const idx = selectedGenders.value.indexOf(g)
	if (idx >= 0) selectedGenders.value.splice(idx, 1)
	else selectedGenders.value.push(g)
}

function toggleSize(size) {
	const idx = selectedSizes.value.indexOf(size)
	if (idx >= 0) selectedSizes.value.splice(idx, 1)
	else selectedSizes.value.push(size)
}

function applyPricePreset(preset) {
	priceMin.value = preset.min
	priceMax.value = preset.max
}

function discountFor(p) {
	if (!p.oldPrice || p.oldPrice <= p.price) return 0
	return Math.round(((p.oldPrice - p.price) / p.oldPrice) * 100)
}

function formatPrice(value) {
	return '₺' + value.toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

/* ── Filtre işlemi ── */
const filtered = computed(() => {
	const q = searchQuery.value.trim().toLowerCase()

	let list = props.products.filter((p) => {
		if (q && !p.name.toLowerCase().includes(q) && !p.brand.toLowerCase().includes(q) && !p.sku.toLowerCase().includes(q)) return false
		if (selectedCategories.value.length && !selectedCategories.value.includes(p.categorySlug)) return false
		if (selectedBrands.value.length && !selectedBrands.value.includes(p.brand)) return false
		if (selectedGenders.value.length && !selectedGenders.value.includes(p.gender)) return false
		if (selectedSizes.value.length && !p.sizes.some((s) => selectedSizes.value.includes(s))) return false
		if (priceMin.value != null && p.price < priceMin.value) return false
		if (priceMax.value != null && p.price > priceMax.value) return false
		if (inStockOnly.value && p.stock === 0) return false
		if (onSaleOnly.value && !p.oldPrice) return false
		if (newOnly.value && !p.isNew) return false
		if (freeShippingOnly.value && !p.freeShipping) return false
		return true
	})

	switch (sortBy.value) {
		case 'priceAsc':  list = [...list].sort((a, b) => a.price - b.price); break
		case 'priceDesc': list = [...list].sort((a, b) => b.price - a.price); break
		case 'rating':    list = [...list].sort((a, b) => b.rating - a.rating); break
		case 'new':       list = [...list].sort((a, b) => (b.isNew ? 1 : 0) - (a.isNew ? 1 : 0) || b.id - a.id); break
		case 'bestseller':
		default:          list = [...list].sort((a, b) => (b.reviewCount * b.rating) - (a.reviewCount * a.rating))
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
	[searchQuery, selectedCategories, selectedBrands, selectedGenders, selectedSizes, priceMin, priceMax, inStockOnly, onSaleOnly, newOnly, freeShippingOnly, sortBy],
	() => { currentPage.value = 1 },
	{ deep: true },
)

/* ── Aktif filtre çipleri ── */
const activeFilterCount = computed(() => {
	let n = 0
	n += selectedCategories.value.length
	n += selectedBrands.value.length
	n += selectedGenders.value.length
	n += selectedSizes.value.length
	if (priceMin.value != null || priceMax.value != null) n++
	if (inStockOnly.value) n++
	if (onSaleOnly.value) n++
	if (newOnly.value) n++
	if (freeShippingOnly.value) n++
	return n
})

const activeFilterLabels = computed(() => {
	const out = []
	const catMap = Object.fromEntries(props.categories.map((c) => [c.slug, c.label]))
	selectedCategories.value.forEach((slug) => out.push({ kind: 'cat', value: slug, label: catMap[slug] || slug }))
	selectedBrands.value.forEach((b) => out.push({ kind: 'brand', value: b, label: b }))
	selectedGenders.value.forEach((g) => out.push({ kind: 'gender', value: g, label: g }))
	selectedSizes.value.forEach((s) => out.push({ kind: 'size', value: s, label: 'Beden ' + s }))
	if (priceMin.value != null || priceMax.value != null) {
		const min = priceMin.value ?? 0
		const max = priceMax.value ?? '∞'
		out.push({ kind: 'price', label: `₺${min} — ₺${max}` })
	}
	if (inStockOnly.value)     out.push({ kind: 'inStock', label: 'Stokta' })
	if (onSaleOnly.value)      out.push({ kind: 'onSale', label: 'İndirimli' })
	if (newOnly.value)         out.push({ kind: 'new', label: 'Yeni' })
	if (freeShippingOnly.value) out.push({ kind: 'shipping', label: 'Ücretsiz Kargo' })
	return out.map((x) => x.label)
})

function removeActiveFilter(idx) {
	// Sırayı eşitlemek için aynı mantıkla bul
	let i = 0
	for (const slug of [...selectedCategories.value]) {
		if (i === idx) { selectedCategories.value = selectedCategories.value.filter((x) => x !== slug); return }
		i++
	}
	for (const b of [...selectedBrands.value]) {
		if (i === idx) { selectedBrands.value = selectedBrands.value.filter((x) => x !== b); return }
		i++
	}
	for (const g of [...selectedGenders.value]) {
		if (i === idx) { selectedGenders.value = selectedGenders.value.filter((x) => x !== g); return }
		i++
	}
	for (const s of [...selectedSizes.value]) {
		if (i === idx) { selectedSizes.value = selectedSizes.value.filter((x) => x !== s); return }
		i++
	}
	if (priceMin.value != null || priceMax.value != null) {
		if (i === idx) { priceMin.value = null; priceMax.value = null; return }
		i++
	}
	if (inStockOnly.value)      { if (i === idx) { inStockOnly.value = false; return } i++ }
	if (onSaleOnly.value)       { if (i === idx) { onSaleOnly.value = false; return } i++ }
	if (newOnly.value)          { if (i === idx) { newOnly.value = false; return } i++ }
	if (freeShippingOnly.value) { if (i === idx) { freeShippingOnly.value = false; return } i++ }
}

function clearFilters() {
	selectedCategories.value = []
	selectedBrands.value = []
	selectedGenders.value = []
	selectedSizes.value = []
	priceMin.value = null
	priceMax.value = null
	inStockOnly.value = false
	onSaleOnly.value = false
	newOnly.value = false
	freeShippingOnly.value = false
	searchQuery.value = ''
}

/* ── Favori / Sepet ── */
function toggleFavorite(product) {
	const willAdd = !favorites.value.has(product.id)

	// Optimistic
	const next = new Set(favorites.value)
	if (willAdd) next.add(product.id)
	else next.delete(product.id)
	favorites.value = next

	showToast?.({
		type:    willAdd ? 'success' : 'info',
		title:   willAdd ? 'Favorilere eklendi' : 'Favoriden çıkarıldı',
		message: product.name,
	})

	router.post(`/products/${product.id}/favorite`, {}, {
		preserveScroll: true,
		preserveState: true,
		only: ['favoriteIds'],
		onError: () => {
			// Hata: optimistic değişikliği geri al
			const revert = new Set(favorites.value)
			if (willAdd) revert.delete(product.id)
			else revert.add(product.id)
			favorites.value = revert
			showToast?.({
				type: 'error',
				title: 'Favori işlemi başarısız',
				message: product.name,
			})
		},
	})
}

function addToCart(product) {
	if (product.stock === 0) return
	// Hızlı ekleme: ilk renk + boyut seçilmemiş. Kullanıcı drawer'da varyantı görür,
	// gerekirse detay sayfasından yeniden eklemeye yönlendirilebilir.
	cart?.add({
		product,
		color: product.colors?.[0] ?? null,
		size: null,
	})
}

/* ── Ürün formu (drawer) ── */
const formDrawerOpen = ref(false)
const editingProduct = ref(null)
const formBusy = ref(false)
const formErrors = ref({})

watch(formDrawerOpen, (open) => {
	if (!open) {
		setTimeout(() => {
			editingProduct.value = null
			formErrors.value = {}
		}, 250)
	}
})

function openNewProduct() {
	editingProduct.value = null
	formErrors.value = {}
	formDrawerOpen.value = true
}

function editProduct(product) {
	editingProduct.value = { ...product }
	formErrors.value = {}
	formDrawerOpen.value = true
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

function handleFormSubmit({ mode, id, payload }) {
	if (formBusy.value) return
	formBusy.value = true
	formErrors.value = {}

	const opts = {
		preserveScroll: true,
		preserveState: true,
		onSuccess: () => {
			formDrawerOpen.value = false
			showToast?.({
				type: 'success',
				title: mode === 'edit' ? 'Ürün Güncellendi' : 'Ürün Eklendi',
				message: payload.name,
			})
		},
		onError: (errs) => {
			formErrors.value = errs
			const first = Object.values(errs)[0]
			showToast?.({ type: 'error', title: 'Kayıt Başarısız', message: first || 'Doğrulama hatası.' })
		},
		onFinish: () => { formBusy.value = false },
	}

	if (mode === 'edit') {
		router.put(`/products/${id}`, payload, opts)
	} else {
		router.post('/products', payload, opts)
	}
}
</script>

<style scoped>
.page-header {
	display: flex;
	align-items: flex-start;
	justify-content: space-between;
	margin-bottom: 20px;
	gap: 16px;
	flex-wrap: wrap;
}

.page-title {
	font-size: 22px;
	font-weight: 700;
	color: #1a1a2e;
	line-height: 1.2;
}

.page-subtitle {
	font-size: 13px;
	color: #888;
	margin-top: 4px;
	display: flex;
	align-items: center;
	gap: 8px;
}
.page-subtitle strong { color: #1a1a2e; font-weight: 700; }

.filter-chip {
	display: inline-flex;
	align-items: center;
	padding: 2px 8px;
	background: #ede9fe;
	color: #7c3aed;
	border-radius: 999px;
	font-size: 11px;
	font-weight: 600;
}

.header-tools {
	display: flex;
	align-items: center;
	gap: 8px;
	flex-shrink: 0;
}

.search-box {
	display: flex;
	align-items: center;
	gap: 6px;
	background: #fff;
	border: 1px solid #e8e8f0;
	border-radius: 9px;
	padding: 6px 12px;
	min-width: 260px;
}
.search-box svg { color: #aaa; flex-shrink: 0; }
.search-box input {
	border: none; background: none; outline: none;
	font-family: inherit; font-size: 13px;
	width: 100%;
}
.search-box input::placeholder { color: #bbb; }

.view-toggle {
	display: flex;
	background: #fff;
	border: 1px solid #e8e8f0;
	border-radius: 9px;
	overflow: hidden;
	flex-shrink: 0;
}

.view-btn {
	width: 34px;
	height: 34px;
	background: none;
	border: none;
	cursor: pointer;
	color: #888;
	display: flex;
	align-items: center;
	justify-content: center;
	transition: background .12s, color .12s;
}
.view-btn:hover { background: #f5f5f8; color: #555; }
.view-btn.active { background: #1a1a2e; color: #fff; }

/* ── Layout ── */
.products-layout {
	display: grid;
	grid-template-columns: 260px 1fr;
	gap: 20px;
	align-items: flex-start;
}

@media (max-width: 1100px) {
	.products-layout { grid-template-columns: 220px 1fr; }
}

@media (max-width: 900px) {
	.products-layout { grid-template-columns: 1fr; }
}

/* ── Filter Sidebar ── */
.filters-sidebar {
	background: #fff;
	border: 1px solid #ebebf0;
	border-radius: 14px;
	padding: 14px 16px;
	position: sticky;
	top: 12px;
	max-height: calc(100vh - 120px);
	overflow-y: auto;
	box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
}
.filters-sidebar::-webkit-scrollbar { width: 4px; }
.filters-sidebar::-webkit-scrollbar-thumb { background: #ddd; border-radius: 4px; }

.filters-head {
	display: flex;
	align-items: center;
	justify-content: space-between;
	padding-bottom: 10px;
	border-bottom: 1px solid #f0f0f5;
	margin-bottom: 8px;
}
.filters-head h3 {
	font-size: 14px;
	font-weight: 700;
	color: #1a1a2e;
}
.clear-btn {
	background: none;
	border: none;
	color: #4a6cf7;
	font-size: 11.5px;
	font-weight: 600;
	cursor: pointer;
	padding: 2px 6px;
	border-radius: 6px;
}
.clear-btn:hover { background: #eef0ff; }

/* Filter group (collapsible) */
.filter-group {
	border-bottom: 1px solid #f0f0f5;
	padding: 4px 0;
}
.filter-group:last-child { border-bottom: none; }

.filter-group summary {
	list-style: none;
	display: flex;
	align-items: center;
	justify-content: space-between;
	padding: 10px 2px;
	cursor: pointer;
	font-size: 12.5px;
	font-weight: 700;
	color: #1a1a2e;
	user-select: none;
}
.filter-group summary::-webkit-details-marker { display: none; }
.filter-group summary svg {
	color: #aaa;
	transition: transform .2s;
}
.filter-group[open] summary svg { transform: rotate(180deg); }

.filter-body {
	display: flex;
	flex-direction: column;
	gap: 2px;
	padding-bottom: 8px;
}

.check-row {
	display: flex;
	align-items: center;
	gap: 8px;
	padding: 6px 4px;
	border-radius: 7px;
	cursor: pointer;
	transition: background .12s;
}
.check-row:hover { background: #fafafe; }
.check-row input[type="checkbox"] {
	width: 14px; height: 14px;
	accent-color: #4a6cf7;
	cursor: pointer;
	flex-shrink: 0;
}
.check-text {
	font-size: 12.5px;
	color: #444;
	flex: 1;
	display: inline-flex;
	align-items: center;
	gap: 6px;
}
.check-icon { font-size: 13px; }
.check-count {
	font-size: 10.5px;
	color: #aaa;
	background: #f5f5f8;
	padding: 1px 6px;
	border-radius: 999px;
	font-weight: 600;
}

.toggle-row {
	display: flex;
	align-items: center;
	gap: 8px;
	padding: 7px 4px;
	border-radius: 7px;
	cursor: pointer;
	transition: background .12s;
}
.toggle-row:hover { background: #fafafe; }
.toggle-row input { accent-color: #4a6cf7; width: 14px; height: 14px; cursor: pointer; }
.toggle-text { font-size: 12.5px; color: #444; }

/* Chips */
.chips-body {
	flex-direction: row;
	flex-wrap: wrap;
	gap: 5px;
	padding: 4px 2px 8px;
}

.chip {
	background: #fff;
	border: 1.5px solid #e8e8f0;
	color: #444;
	font-family: inherit;
	font-size: 11.5px;
	font-weight: 600;
	padding: 5px 11px;
	border-radius: 999px;
	cursor: pointer;
	transition: all .12s;
}
.chip:hover { border-color: #c0c0d8; }
.chip.active {
	background: #1a1a2e;
	color: #fff;
	border-color: #1a1a2e;
}

.chips-grid {
	display: grid;
	grid-template-columns: repeat(4, 1fr);
	gap: 5px;
}
.chip-size {
	padding: 6px 0;
	text-align: center;
	font-size: 11px;
}

/* Price */
.price-inputs {
	display: flex;
	align-items: center;
	gap: 8px;
	margin: 6px 0 10px;
}

.price-field {
	flex: 1;
	display: flex;
	align-items: center;
	background: #fafafe;
	border: 1.5px solid #e8e8f0;
	border-radius: 8px;
	padding: 0 8px;
	height: 32px;
}
.price-field:focus-within {
	border-color: #4a6cf7;
	background: #fff;
}
.price-prefix { color: #aaa; font-size: 12px; margin-right: 3px; }
.price-field input {
	border: none; background: none; outline: none;
	font-family: inherit; font-size: 12.5px;
	width: 100%;
	color: #1a1a2e;
}

.price-sep { color: #aaa; font-size: 13px; }

.price-presets {
	display: grid;
	grid-template-columns: repeat(2, 1fr);
	gap: 4px;
}
.price-preset {
	background: #fafafe;
	border: 1px solid #ebebf0;
	color: #555;
	font-family: inherit;
	font-size: 11px;
	font-weight: 600;
	padding: 5px 6px;
	border-radius: 6px;
	cursor: pointer;
	transition: all .12s;
}
.price-preset:hover { border-color: #c0c0d8; color: #1a1a2e; }

/* ── Aktif çipler ── */
.active-chips {
	display: flex;
	flex-wrap: wrap;
	gap: 6px;
	margin-bottom: 14px;
	align-items: center;
}
.active-chip {
	display: inline-flex;
	align-items: center;
	gap: 5px;
	padding: 5px 10px;
	background: #ede9fe;
	color: #7c3aed;
	border-radius: 999px;
	font-size: 11.5px;
	font-weight: 600;
	cursor: pointer;
	transition: background .12s;
}
.active-chip:hover { background: #ddd6fe; }
.active-chip svg { opacity: .7; }

.clear-link {
	background: none;
	border: none;
	color: #888;
	font-family: inherit;
	font-size: 11.5px;
	cursor: pointer;
	text-decoration: underline;
	padding: 2px 4px;
}
.clear-link:hover { color: #4a6cf7; }

/* ── Grid ── */
.products-grid {
	display: grid;
	grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
	gap: 16px;
}

/* ── Liste görünümü ── */
.products-list {
	display: flex;
	flex-direction: column;
	gap: 12px;
}

.list-row {
	display: grid;
	grid-template-columns: 120px 1fr 220px;
	gap: 16px;
	background: #fff;
	border: 1px solid #ebebf0;
	border-radius: 12px;
	padding: 14px;
	transition: box-shadow .15s, border-color .15s;
	text-decoration: none;
	color: inherit;
	cursor: pointer;
}
.list-row:hover {
	border-color: #d8d8e8;
	box-shadow: 0 6px 18px rgba(0, 0, 0, 0.06);
}
.list-row.out-of-stock { opacity: .75; }

.list-image {
	position: relative;
	width: 120px;
	height: 150px;
	border-radius: 9px;
	overflow: hidden;
	background: #f5f5fa;
	flex-shrink: 0;
}
.list-image img {
	width: 100%; height: 100%;
	object-fit: cover;
}
.list-badge {
	position: absolute;
	top: 6px; left: 6px;
	background: #dc2626;
	color: #fff;
	font-size: 10px;
	font-weight: 700;
	padding: 2px 6px;
	border-radius: 4px;
}

.list-body { display: flex; flex-direction: column; gap: 5px; min-width: 0; }
.list-brand {
	font-size: 10.5px;
	font-weight: 700;
	color: #888;
	text-transform: uppercase;
	letter-spacing: 0.08em;
}
.list-name {
	font-size: 14px;
	font-weight: 600;
	color: #1a1a2e;
	margin: 0;
	line-height: 1.4;
}
.list-meta {
	display: flex;
	align-items: center;
	gap: 6px;
	font-size: 11.5px;
	color: #888;
	margin-top: 2px;
}
.list-meta .mono { font-family: 'SF Mono', Consolas, monospace; }
.meta-sep { opacity: .5; }
.list-rating { display: flex; align-items: center; gap: 4px; margin-top: 4px; }
.list-review { font-size: 11px; color: #888; margin-left: 3px; }

.list-action {
	display: flex;
	flex-direction: column;
	align-items: flex-end;
	gap: 6px;
	justify-content: space-between;
}

.list-price { display: flex; align-items: baseline; gap: 6px; }
.list-old-price {
	font-size: 12px; color: #aaa;
	text-decoration: line-through;
}
.list-new-price {
	font-size: 17px;
	font-weight: 800;
	color: #1a1a2e;
}
.list-new-price.has-discount { color: #dc2626; }

.list-stock-out { font-size: 11px; color: #dc2626; font-weight: 600; }
.list-stock-low { font-size: 11px; color: #ca8a04; font-weight: 600; }
.list-stock-ok  { font-size: 11px; color: #16a34a; font-weight: 600; }

.list-buttons { display: flex; gap: 6px; align-items: center; }
.icon-btn {
	width: 30px; height: 30px;
	border-radius: 8px;
	border: 1.5px solid #e8e8f0;
	background: #fff;
	color: #6b7280;
	cursor: pointer;
	display: flex; align-items: center; justify-content: center;
	transition: all .15s;
}
.icon-btn:hover { background: #fef2f2; border-color: #fecaca; color: #ef4444; }
.icon-btn.active { color: #ef4444; border-color: #fecaca; background: #fef2f2; }

/* ── Boş durum ── */
.empty-state {
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	gap: 8px;
	padding: 60px 20px;
	background: #fff;
	border: 1px dashed #d8d8e8;
	border-radius: 14px;
	color: #888;
	text-align: center;
}
.empty-state svg { color: #c0c0d8; margin-bottom: 4px; }
.empty-state h3 {
	font-size: 15px;
	font-weight: 700;
	color: #1a1a2e;
	margin: 0;
}
.empty-state p { font-size: 12.5px; color: #888; margin: 0 0 8px 0; }

/* ── Sayfalama ── */
.pagination {
	display: flex;
	align-items: center;
	justify-content: space-between;
	padding: 18px 4px 4px;
	margin-top: 12px;
	border-top: 1px solid #f0f0f5;
}

.pagination-info { font-size: 13px; color: #888; }
.pagination-info span { color: #1a1a2e; font-weight: 600; }

.pagination-controls { display: flex; align-items: center; gap: 4px; }

.pagination-btn {
	min-width: 32px; height: 32px;
	padding: 0 8px;
	border: 1px solid #e8e8f0;
	border-radius: 6px;
	background: #fff; color: #666;
	font-size: 13px; font-weight: 500;
	cursor: pointer;
	display: flex; align-items: center; justify-content: center;
	transition: all .15s;
}
.pagination-btn:hover:not(:disabled) { border-color: #ccc; color: #333; background: #f9f9fb; }
.pagination-btn.active {
	background: #1a1a2e; color: #fff;
	border-color: #1a1a2e;
}
.pagination-btn:disabled { opacity: 0.4; cursor: not-allowed; }

@media (max-width: 760px) {
	.search-box { min-width: 180px; }
	.list-row { grid-template-columns: 100px 1fr; }
	.list-action { grid-column: 1 / -1; flex-direction: row; align-items: center; }
}
</style>
