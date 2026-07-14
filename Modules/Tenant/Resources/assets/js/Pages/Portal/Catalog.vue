<template>
	<Head title="Katalog" />
	<div class="portal-catalog">
		<header class="page-header">
			<h1 class="page-title">Katalog</h1>
			<div class="search-box">
				<input v-model="searchQuery" type="text" placeholder="Ürün ara (isim, SKU, barkod)..." @keyup.enter="applyFilters" />
				<button class="btn-ghost" @click="applyFilters">Ara</button>
			</div>
		</header>

		<div class="filter-bar">
			<select v-model="selectedCategory" class="filter-select" @change="applyFilters">
				<option :value="null">Tüm Kategoriler</option>
				<option v-for="c in filterOptions.categories" :key="c.id" :value="c.id">{{ c.name }}</option>
			</select>
			<select v-model="selectedBrand" class="filter-select" @change="applyFilters">
				<option :value="null">Tüm Markalar</option>
				<option v-for="b in filterOptions.brands" :key="b.id" :value="b.id">{{ b.name }}</option>
			</select>
			<select v-model="selectedSort" class="filter-select" @change="applyFilters">
				<option value="name">İsme göre (A-Z)</option>
				<option value="newest">En yeni</option>
				<option value="price_asc">Fiyat: artan</option>
				<option value="price_desc">Fiyat: azalan</option>
			</select>
			<button v-if="hasActiveFilters" class="btn-ghost" @click="clearFilters">Filtreleri temizle</button>
		</div>

		<div v-if="products.data.length === 0" class="empty">
			Ürün bulunamadı.
		</div>

		<div v-else class="grid">
			<Link
				v-for="p in products.data"
				:key="p.id"
				:href="`/catalog/${p.slug}`"
				class="product-card"
			>
				<div class="product-img" :style="`background-image: url('${p.image}')`"></div>
				<div class="product-info">
					<div class="brand">{{ p.brand ?? '—' }}</div>
					<div class="name">{{ p.name }}</div>
					<div class="prices">
						<span class="tenant-price">{{ formatMoney(p.tenant_price) }}</span>
						<span v-if="p.purchase_price > 0" class="purchase-price" title="Ana firma maliyet">
							maliyet: {{ formatMoney(p.purchase_price) }}
						</span>
					</div>
				</div>
			</Link>
		</div>

		<nav class="pagination" v-if="products.last_page > 1">
			<Link
				v-for="link in products.links"
				:key="link.label"
				:href="link.url ?? '#'"
				v-html="link.label"
				:class="['page-link', { active: link.active, disabled: !link.url }]"
			/>
		</nav>
	</div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import TenantPortalLayout from '@/Layouts/TenantPortalLayout.vue'

defineOptions({ layout: TenantPortalLayout })

const props = defineProps({
	tenant: { type: Object, required: true },
	products: { type: Object, required: true },
	filters: { type: Object, default: () => ({}) },
	filterOptions: { type: Object, default: () => ({ categories: [], brands: [] }) },
})

const searchQuery = ref(props.filters.q ?? '')
const selectedCategory = ref(props.filters.category ?? null)
const selectedBrand = ref(props.filters.brand ?? null)
const selectedSort = ref(props.filters.sort ?? 'name')

const hasActiveFilters = computed(() =>
	!!searchQuery.value || selectedCategory.value !== null || selectedBrand.value !== null || selectedSort.value !== 'name'
)

function applyFilters() {
	const params = {}
	if (searchQuery.value) params.q = searchQuery.value
	if (selectedCategory.value !== null) params.category = selectedCategory.value
	if (selectedBrand.value !== null) params.brand = selectedBrand.value
	if (selectedSort.value !== 'name') params.sort = selectedSort.value

	router.get('/catalog', params, { preserveState: true, preserveScroll: true })
}

function clearFilters() {
	searchQuery.value = ''
	selectedCategory.value = null
	selectedBrand.value = null
	selectedSort.value = 'name'
	applyFilters()
}

function formatMoney(v) {
	return new Intl.NumberFormat('tr-TR', { style: 'currency', currency: 'TRY', maximumFractionDigits: 2 }).format(Number(v ?? 0))
}
</script>

<style scoped>
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; gap: 12px; flex-wrap: wrap; }
.page-title { font-size: 22px; font-weight: 700; color: #1a1a2e; }
.search-box { display: flex; gap: 8px; }
.search-box input { padding: 8px 12px; border: 1px solid #ebebf0; border-radius: 8px; font-size: 13px; min-width: 280px; }
.btn-ghost { padding: 8px 16px; border-radius: 8px; background: #f7f7fb; border: 1px solid #ebebf0; cursor: pointer; font-size: 13px; }
.filter-bar { display: flex; gap: 8px; margin-bottom: 16px; flex-wrap: wrap; align-items: center; }
.filter-select { padding: 8px 12px; border: 1px solid #ebebf0; border-radius: 8px; font-size: 13px; background: #fff; color: #1a1a2e; cursor: pointer; min-width: 160px; }
.grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 16px; }
.product-card { background: #fff; border-radius: 12px; border: 1px solid #ebebf0; overflow: hidden; text-decoration: none; color: inherit; transition: box-shadow .15s; }
.product-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,.08); }
.product-img { aspect-ratio: 3/4; background-size: cover; background-position: center; background-color: #f7f7fb; }
.product-info { padding: 10px 12px; display: flex; flex-direction: column; gap: 4px; }
.brand { font-size: 10px; color: #888; text-transform: uppercase; letter-spacing: 0.04em; }
.name { font-size: 13px; font-weight: 600; color: #1a1a2e; line-height: 1.3; }
.prices { display: flex; justify-content: space-between; align-items: baseline; margin-top: 6px; }
.tenant-price { font-size: 15px; font-weight: 700; color: #4338ca; font-family: 'SF Mono', Menlo, Consolas, monospace; }
.purchase-price { font-size: 10px; color: #888; }
.empty { text-align: center; padding: 64px; color: #aaa; }
.pagination { display: flex; gap: 4px; margin-top: 16px; justify-content: center; }
.page-link { padding: 6px 10px; border-radius: 6px; font-size: 12px; color: #555; text-decoration: none; background: #fff; border: 1px solid #ebebf0; }
.page-link.active { background: #4338ca; color: #fff; border-color: #4338ca; }
.page-link.disabled { color: #ccc; pointer-events: none; }

@media (max-width: 640px) {
	.search-box { width: 100%; }
	.search-box input { min-width: 0; flex: 1; }
	.filter-select { min-width: 0; flex: 1 1 140px; }
}
</style>
