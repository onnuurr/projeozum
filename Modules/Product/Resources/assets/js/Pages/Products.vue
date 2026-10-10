<script setup>
import { ref, computed, inject, reactive } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import { Shirt, Palette, Store } from 'lucide-vue-next'
import AppLayout from '@/Layouts/AppLayout.vue'
import {
	OzPageHeader, OzModuleTabs, OzStatStrip, OzButton, OzPill, OzTable,
	OzDropdown, OzEmptyState, OzCard, OzModal, OzIcon, useOzConfirm,
} from '@/Components/Oz'
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
	// Görsel + ad + SKU/barkod + #ID tek hücrede (eskiden ID / Görsel / Ürün ayrı sütunlardı)
	{
		key: 'name', label: 'Ürün', sortable: true, class: 'oz-cell-strong',
		searchValue: (p) => [p.name, p.sku, p.barcode, `#${p.id}`].filter(Boolean).join(' '),
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

// Başlıktaki "N ürün listeleniyor" için OzTable'ın süzdüğü satırlar (defineExpose: filteredRows)
const table = ref(null)
const listedCount = computed(() => table.value?.filteredRows.length ?? props.products.length)
const isFiltering = computed(() => listedCount.value !== props.products.length)

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
// Yeni Ürün: yetkili olunan seçenekler (üretilecek ürün yetkisi yoksa o seçenek hiç gösterilmez).
// Tek seçenek kalırsa modal açılmadan doğrudan ona gidilir.
const newProductOpen = ref(false)
const newProductChoices = computed(() => [
	{ key: 'product', title: 'Yeni Ürün', desc: 'Hazır (satın alınan) ürün kartı', icon: 'package', href: '/products/create' },
	...(props.canAddManufactured
		? [{ key: 'manufactured', title: 'Üretilecek Ürün', desc: 'Reçeteli geçici stok kartı', icon: 'factory', href: '/atelier/new-product' }]
		: []),
])
function onNewProductClick() {
	if (newProductChoices.value.length === 1) chooseNewProduct(newProductChoices.value[0])
	else newProductOpen.value = true
}
function chooseNewProduct(choice) {
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

/* ── Toplu seçim (OzTable selectable; toplu işlem çubuğu tablonun #bulk slot'unda) ── */
const selected = ref([])
const bulkBusy = ref(false)

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
			selected.value = []
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
			<b>{{ listedCount }}</b> ürün listeleniyor
			<span v-if="isFiltering" class="dim"> / {{ products.length }}</span>
		</template>
		<template #actions>
			<OzButton v-if="canAdd" variant="primary" icon="plus" @click="onNewProductClick">Yeni Ürün</OzButton>
		</template>
	</OzPageHeader>
	<OzModuleTabs />

	<OzStatStrip :items="stats" />

	<OzCard title="Ürün Listesi">
		<OzEmptyState v-if="!products.length" icon="package" title="Henüz ürün yok" description="Kataloğa ilk ürününüzü ekleyerek başlayın.">
			<template v-if="canAdd" #actions><OzButton size="sm" variant="primary" icon="plus" @click="onNewProductClick">Yeni Ürün</OzButton></template>
		</OzEmptyState>

		<OzTable
			v-else
			ref="table"
			v-model:selected="selected"
			:columns="columns"
			:rows="products"
			:page-size="15"
			:default-sort="{ key: 'id', dir: 'desc' }"
			:selectable="canDelete"
			item-label="ürün"
			searchable
			search-placeholder="Ürün adı, SKU, barkod ya da #ID ara…"
			min-width="1080px"
		>
			<template #bulk>
				<OzButton variant="danger" size="sm" icon="trash" :loading="bulkBusy" @click="bulkDelete">Seçilenleri Sil ({{ selected.length }})</OzButton>
			</template>

			<template #cell-name="{ row }">
				<div class="product-cell">
					<div class="thumb">
						<img :src="row.image || '/images/product-placeholder.svg'" :alt="row.name" loading="lazy">
					</div>
					<div class="name-cell">
						<span class="name-text">
							{{ row.name }}
							<OzPill v-if="row.status === 'development'" tone="warning" :dot="false" title="Üretimi tamamlanınca ürüne dönüşür">Geçici kart</OzPill>
							<OzPill v-else-if="row.status === 'draft'" tone="neutral" :dot="false" title="Pasif — mağazada görünmez; Ürün Kartı'ndan yayınlayın">Taslak</OzPill>
						</span>
						<div class="oz-cell-sub mono">
							<span class="pid">#{{ row.id }}</span> · {{ row.sku || '—' }}<template v-if="row.barcode"> · {{ row.barcode }}</template>
						</div>
					</div>
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
			<!-- Stok: sade sayı; yalnızca az/tükendi durumunda renk + nokta (her satırda pill göz yoruyordu) -->
			<template #cell-stock="{ value }">
				<span v-if="value === 0" class="stock is-out" title="Stokta yok"><i aria-hidden="true" />Tükendi</span>
				<span v-else-if="value < 20" class="stock is-low" title="Stok azalıyor (20'nin altı)"><i aria-hidden="true" />{{ value.toLocaleString('tr-TR') }}</span>
				<span v-else class="stock">{{ value.toLocaleString('tr-TR') }}</span>
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
.choice:hover, .choice:focus-visible { border-color: var(--oz-accent); background: var(--oz-accent-soft); outline: none; transform: translateY(-1px); }
.choice-ico {
	display: inline-flex; align-items: center; justify-content: center;
	width: 56px; height: 56px; border-radius: 14px; background: var(--oz-accent-soft); color: var(--oz-accent);
}
.choice b { font-size: 15px; font-weight: 600; }
.choice small { font-size: 12.5px; color: var(--oz-muted); line-height: 1.4; }


/* ── Tablo hücreleri ── */
.stock { display: inline-flex; align-items: center; gap: 6px; font-weight: 600; font-variant-numeric: tabular-nums; color: var(--oz-ink-soft); }
.stock i { width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
.stock.is-low { color: var(--oz-pill-warning-fg); }
.stock.is-out { color: var(--oz-pill-danger-fg); font-size: 11.5px; }
.dim { color: var(--oz-muted); font-weight: 400; }
.mono { font-family: ui-monospace, monospace; }

.thumb { width: 44px; height: 44px; border-radius: 8px; overflow: hidden; background: var(--oz-surface-2); flex-shrink: 0; }
.thumb img { width: 100%; height: 100%; object-fit: cover; }

.product-cell { display: flex; align-items: center; gap: 12px; min-width: 240px; }
.name-cell { display: flex; flex-direction: column; min-width: 0; }
.pid { color: var(--oz-accent); font-weight: 700; }
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
