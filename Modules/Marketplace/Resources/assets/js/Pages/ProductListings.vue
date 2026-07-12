<template>
	<Head title="Ürün ↔ Pazaryeri Bağlantıları" />
	<div class="page-product-listings">
		<Breadcrumb
			:items="[
				{ label: 'Ana Sayfa', to: '/workflow', icon: 'home' },
				{ label: 'Pazaryeri' },
				{ label: 'Ürün Bağlantıları' },
			]"
		/>

		<div class="page-header">
			<div>
				<h1 class="page-title">Ürün ↔ Pazaryeri Bağlantıları</h1>
				<p class="page-subtitle">Bir ürün seç, pazaryeri listeleme bilgilerini düzenle</p>
			</div>
		</div>

		<div class="card search-card">
			<div class="search-box">
				<svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
					<circle cx="11" cy="11" r="8" /><path d="M21 21l-4.35-4.35" />
				</svg>
				<input v-model="query" type="text" placeholder="Ürün adı veya SKU ile ara..." @input="onSearchInput" />
			</div>

			<div v-if="loading" class="search-status">Aranıyor…</div>
			<div v-else-if="query && results.length === 0" class="search-status">Sonuç bulunamadı.</div>

			<ul v-if="results.length" class="result-list">
				<li v-for="p in results" :key="p.id">
					<button type="button" class="result-item" @click="selectProduct(p)">
						<span class="result-name">{{ p.name }}</span>
						<span class="result-sku">{{ p.sku }}</span>
					</button>
				</li>
			</ul>
		</div>

		<div v-if="activeProduct" class="card active-product-card">
			<div class="active-product-header">
				<span class="active-product-name">{{ activeProduct.name }}</span>
				<span class="active-product-sku">{{ activeProduct.sku }}</span>
			</div>
			<div class="mp-list">
				<button
					v-for="mp in marketplaces"
					:key="mp.key"
					type="button"
					class="mp-item"
					:title="mp.name"
					@click="openListing(mp)"
				>
					<span class="mp-badge" :style="{ background: mp.color || '#888' }">{{ mp.logoText }}</span>
					<span class="mp-name">{{ mp.name }}</span>
				</button>
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
import { ref, inject } from 'vue'
import { Head } from '@inertiajs/vue3'
import axios from 'axios'
import AppLayout from '@/Layouts/AppLayout.vue'
import Breadcrumb from '@/Components/Breadcrumb.vue'
import MarketplaceListingDrawer from '../Components/MarketplaceListingDrawer.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
	marketplaces: { type: Array, default: () => [] },
})

const showToast = inject('showToast')

const query = ref('')
const results = ref([])
const loading = ref(false)
let searchTimer = null

function onSearchInput() {
	clearTimeout(searchTimer)
	if (!query.value.trim()) {
		results.value = []
		return
	}
	searchTimer = setTimeout(runSearch, 300)
}

async function runSearch() {
	loading.value = true
	try {
		const { data } = await axios.get('/marketplace/products/search', { params: { q: query.value.trim() } })
		results.value = data.data
	} finally {
		loading.value = false
	}
}

const activeProduct = ref(null)
function selectProduct(p) {
	activeProduct.value = p
	results.value = []
	query.value = ''
}

const listingOpen = ref(false)
const activeMarketplace = ref(null)
function openListing(mp) {
	activeMarketplace.value = mp
	listingOpen.value = true
}
function onListingSaved() {
	showToast?.({ type: 'success', title: 'Kaydedildi', message: 'Pazaryeri listeleme bilgisi güncellendi.' })
}
</script>

<style scoped>
.page-header { margin-bottom: 20px; }
.page-title { font-size: 22px; font-weight: 700; color: #1a1a2e; line-height: 1.2; }
.page-subtitle { font-size: 13px; color: #888; margin-top: 4px; }

.card { background: #fff; border-radius: 16px; border: 1px solid #ebebf0; box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04); padding: 18px 20px; }
.search-card { margin-bottom: 16px; }

.search-box {
	display: flex; align-items: center; gap: 8px;
	background: #f5f5f8; border: 1px solid #e8e8f0; border-radius: 9px; padding: 9px 14px;
}
.search-box svg { color: #aaa; flex-shrink: 0; }
.search-box input { border: none; background: none; outline: none; font-family: inherit; font-size: 13.5px; width: 100%; }
.search-box input::placeholder { color: #bbb; }

.search-status { padding: 12px 4px; font-size: 12.5px; color: #888; }

.result-list { list-style: none; margin: 10px 0 0; padding: 0; border-top: 1px solid #f0f0f5; }
.result-item {
	width: 100%; display: flex; justify-content: space-between; align-items: center;
	background: none; border: none; text-align: left; cursor: pointer;
	padding: 10px 6px; font-family: inherit; border-bottom: 1px solid #f5f5f8;
}
.result-item:hover { background: #fafafe; }
.result-name { font-size: 13.5px; font-weight: 600; color: #1a1a2e; }
.result-sku { font-size: 11.5px; color: #888; font-family: 'SF Mono', Consolas, monospace; }

.active-product-header { display: flex; align-items: baseline; gap: 10px; margin-bottom: 14px; }
.active-product-name { font-size: 15px; font-weight: 700; color: #1a1a2e; }
.active-product-sku { font-size: 12px; color: #888; font-family: 'SF Mono', Consolas, monospace; }

.mp-list { display: flex; flex-wrap: wrap; gap: 10px; }
.mp-item {
	display: flex; align-items: center; gap: 8px;
	background: #fafafe; border: 1.5px solid #e8e8f0; border-radius: 10px;
	padding: 8px 14px; cursor: pointer; font-family: inherit; transition: border-color .15s, background .15s;
}
.mp-item:hover { border-color: rgb(var(--color-primary)); background: #fff; }
.mp-badge {
	display: inline-flex; align-items: center; justify-content: center;
	width: 26px; height: 26px; color: #fff; font-size: 10px; font-weight: 800;
	border-radius: 7px; letter-spacing: 0.02em;
}
.mp-name { font-size: 13px; font-weight: 600; color: #1a1a2e; }
</style>
