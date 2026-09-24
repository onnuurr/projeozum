<script setup>
import { computed, inject, reactive, ref, watch } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import { ArrowDown, ArrowUp, Pencil } from 'lucide-vue-next'
import AppLayout from '@/Layouts/AppLayout.vue'
import {
	OzPageHeader, OzModuleTabs, OzBreadcrumbs, OzCard, OzStatTile, OzTabs, OzTable,
	OzPill, OzButton, OzDrawer, OzField, OzInput, OzTextarea, OzSelect, OzCombobox,
	OzSwitch, OzEmptyState, useOzConfirm,
} from '@/Components/Oz'
import MarketplaceListingDrawer from '../Components/MarketplaceListingDrawer.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
	initialTab: { type: String, default: 'genel' },
	product: { type: Object, required: true },
	warehouses: { type: Array, default: () => [] },
	variants: { type: Array, default: () => [] },
	movements: { type: Array, default: () => [] },
	totals: { type: Object, default: () => ({ stock: 0, reserved: 0, available: 0, criticalVariants: 0 }) },
	marketplaces: { type: Array, default: () => [] },
	listings: { type: Object, default: () => ({}) },
})

const showToast = inject('showToast')
const confirm = useOzConfirm()
const page = usePage()

const permissions = computed(() => page.props.auth?.permissions ?? [])
const canStock = computed(() => permissions.value.includes('stock.manage'))
const canPriceList = computed(() => permissions.value.includes('price-list.manage'))
const canEdit = computed(() => permissions.value.includes('product.add'))

const validTabs = ['genel', 'stok', 'fiyat', 'pazaryeri']
const activeTab = ref(validTabs.includes(props.initialTab) ? props.initialTab : 'genel')

const tabs = [
	{ key: 'genel', label: 'Genel', icon: 'info' },
	{ key: 'stok', label: 'Stok', icon: 'package' },
	{ key: 'fiyat', label: 'Fiyat', icon: 'tag' },
	{ key: 'pazaryeri', label: 'Pazaryeri', icon: 'store' },
]

const statuses = { draft: ['Taslak', 'neutral'], published: ['Yayında', 'success'], archived: ['Arşivlendi', 'danger'] }
const statusLabel = computed(() => statuses[props.product.status]?.[0] ?? props.product.status)
const statusColor = computed(() => statuses[props.product.status]?.[1] ?? 'neutral')

/* ── Stok özetleri (tekil-kayıt özet kartları) ── */
const stats = computed(() => [
	{ key: 'stock', label: 'Toplam Stok', value: props.totals.stock, icon: 'package', tone: 'accent' },
	{ key: 'reserved', label: 'Rezerve', value: props.totals.reserved, icon: 'lock', tone: 'info' },
	{ key: 'available', label: 'Kullanılabilir', value: props.totals.available, icon: 'success', tone: 'success' },
	{ key: 'critical', label: 'Kritik Varyant', value: props.totals.criticalVariants, icon: 'warning', tone: 'warning' },
])

function money(v) {
	return '₺' + Number(v ?? 0).toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

function editProduct() {
	router.visit(`/products/${props.product.id}/edit`)
}

/* ── Son hareketler tablosu (salt-okuma → OzTable) ── */
const movementColumns = [
	{ key: 'createdAt', label: 'Tarih' },
	{ key: 'variant', label: 'Varyant' },
	{ key: 'warehouse', label: 'Depo' },
	{ key: 'type', label: 'Tip' },
	{ key: 'quantity', label: 'Miktar', align: 'right' },
	{ key: 'change', label: 'Önce → Sonra', align: 'right' },
	{ key: 'user', label: 'Kullanıcı' },
	{ key: 'note', label: 'Not' },
]

/* ── Stok hareket drawer ── */
const variantOptions = computed(() =>
	props.variants.map(v => ({ value: v.id, label: [v.size, v.colorName, v.sku].filter(Boolean).join(' · ') })),
)
const warehouseOptions = computed(() =>
	props.warehouses.map(w => ({ value: w.id, label: `${w.name} (${w.code})` })),
)

function movementTypeLabel(type) {
	return { in: 'Giriş', out: 'Çıkış', adjustment: 'Düzeltme', transfer: 'Transfer' }[type] || type
}
function movementTypeColor(type) {
	return { in: 'success', out: 'danger', adjustment: 'info', transfer: 'neutral' }[type] || 'neutral'
}

const movementTypes = [
	{ value: 'in',         label: 'Giriş',    icon: ArrowDown },
	{ value: 'out',        label: 'Çıkış',    icon: ArrowUp },
	{ value: 'adjustment', label: 'Düzeltme', icon: Pencil },
]

// Formdaki her alanın ne anlama geldiğini anlatan rehber (bkz. Kalıp Motoru
// sayfalarındaki aynı desen).
const MOVEMENT_FIELD_GUIDE = [
	{ term: 'Varyant', desc: 'Stok hareketinin uygulanacağı ürün varyantı (beden/renk kombinasyonu).' },
	{ term: 'Depo', desc: 'Hareketin gerçekleştiği fiziksel depo — stok, ürün genelinde değil depo bazında tutulur.' },
	{ term: 'Hareket Tipi', desc: 'Giriş stoğu artırır, Çıkış azaltır. Düzeltme sayım farkı gibi manuel telafiler için kullanılır ve Miktar alanına işaretli (+/−) bir fark girilir.' },
	{ term: 'Miktar', desc: 'Giriş/Çıkış için pozitif adet. Düzeltmede mevcut miktarın üzerine eklenecek/çıkarılacak fark — hedef miktarın kendisi değil.' },
	{ term: 'Not', desc: 'Hareketin nedenine dair opsiyonel açıklama, geçmiş hareketler listesinde görünür.' },
]

const movementOpen = ref(false)
const busy = ref(false)
const errors = ref({})

const movement = reactive({ product_variant_id: null, warehouse_id: null, type: 'in', quantity: 1, note: '' })

watch(movementOpen, (open) => {
	if (!open) {
		setTimeout(() => {
			errors.value = {}
			Object.assign(movement, { product_variant_id: null, warehouse_id: null, type: 'in', quantity: 1, note: '' })
		}, 250)
	}
})

function openMovement(variant = null, warehouseId = null) {
	if (!canStock.value) return
	movement.product_variant_id = variant?.id ?? props.variants[0]?.id ?? null
	movement.warehouse_id = warehouseId ?? props.warehouses[0]?.id ?? null
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
			showToast?.({ type: 'success', title: 'Stok hareketi kaydedildi', message: `${movementTypeLabel(movement.type)} · ${Math.abs(movement.quantity)} adet` })
		},
		onError: (errs) => {
			errors.value = errs
			showToast?.({ type: 'error', title: 'Kayıt başarısız', message: Object.values(errs)[0] || 'Sunucu hatası.' })
		},
		onFinish: () => { busy.value = false },
	})
}

/* ── Fiyat drawer ── */
const priceTypes = [
	{ value: 'retail',   label: 'Perakende' },
	{ value: 'dealer',   label: 'Bayi' },
	{ value: 'dropship', label: 'Dropship' },
]
const currencyOptions = [
	{ value: 'TRY', label: 'TRY' },
	{ value: 'USD', label: 'USD' },
	{ value: 'EUR', label: 'EUR' },
]

const PRICE_FIELD_GUIDE = [
	{ term: 'Tip', desc: 'Bu fiyatın hangi tenant/kanal grubu için geçerli olduğu — Perakende (aynı zamanda varyantın temel satış fiyatına yazılır), Bayi, Dropship. Her tenant kendi tipine karşılık gelen aktif fiyatı görür.' },
	{ term: 'Fiyat', desc: 'Bu tip için birim satış fiyatı.' },
	{ term: 'Para Birimi', desc: 'Fiyatın girildiği para birimi.' },
	{ term: 'Aktif', desc: 'Kapatılırsa bu satır fiyat çözümlemesinde devre dışı kalır ve (Perakende tipindeyse) artık varyantın temel fiyatını güncellemez — satır silinmez, yalnızca pasife alınır.' },
]

function priceTypeLabel(type) {
	return priceTypes.find(t => t.value === type)?.label ?? type
}
function findPrice(variant, type) {
	return variant.priceLists.find(p => p.type === type)
}

const priceOpen = ref(false)
const priceBusy = ref(false)
const priceErrors = ref({})
const priceForm = reactive({ id: null, variantId: null, variantLabel: '', type: 'retail', price: 0, currency: 'TRY', is_active: true })

function openPrice(variant, type) {
	if (!canPriceList.value) return
	const existing = findPrice(variant, type)
	priceForm.id = existing?.id ?? null
	priceForm.variantId = variant.id
	priceForm.variantLabel = [variant.size, variant.colorName, variant.sku].filter(Boolean).join(' · ')
	priceForm.type = type
	priceForm.price = existing?.price ?? variant.basePrice ?? 0
	priceForm.currency = existing?.currency ?? 'TRY'
	priceForm.is_active = existing?.isActive ?? true
	priceErrors.value = {}
	priceOpen.value = true
}

function submitPrice() {
	if (priceBusy.value || !priceForm.variantId) return
	priceBusy.value = true
	priceErrors.value = {}
	router.post(`/products/variants/${priceForm.variantId}/prices`, {
		type: priceForm.type,
		price: priceForm.price,
		currency: priceForm.currency,
		is_active: priceForm.is_active,
	}, {
		preserveScroll: true,
		preserveState: true,
		onSuccess: () => {
			priceOpen.value = false
			showToast?.({ type: 'success', title: 'Fiyat kaydedildi', message: `${priceTypeLabel(priceForm.type)} · ${money(priceForm.price)}` })
		},
		onError: (errs) => {
			priceErrors.value = errs
			showToast?.({ type: 'error', title: 'Kayıt başarısız', message: Object.values(errs)[0] || 'Sunucu hatası.' })
		},
		onFinish: () => { priceBusy.value = false },
	})
}

async function deletePrice() {
	if (!priceForm.id) return
	const ok = await confirm.danger('Fiyatı Sil', `${priceTypeLabel(priceForm.type)} fiyatı silinecek.`)
	if (!ok) return
	priceBusy.value = true
	router.delete(`/products/prices/${priceForm.id}`, {
		preserveScroll: true,
		preserveState: true,
		onSuccess: () => {
			priceOpen.value = false
			showToast?.({ type: 'warning', title: 'Fiyat silindi' })
		},
		onFinish: () => { priceBusy.value = false },
	})
}

/* ── Pazaryeri drawer ── */
const listingOpen = ref(false)
const activeMarketplace = ref(null)

function openListing(mp) {
	activeMarketplace.value = mp
	listingOpen.value = true
}
</script>

<template>
	<Head :title="`Ürün Kartı · ${product.name}`" />

	<OzBreadcrumbs :items="[
		{ label: 'Katalog', to: '/products', icon: 'layers' },
		{ label: product.name },
	]" />

	<OzPageHeader :title="product.name">
		<template #subtitle>
			<span class="mono">{{ product.sku }}</span>
			<span v-if="product.barcode"> · <span class="mono">{{ product.barcode }}</span></span>
			&nbsp;<OzPill :tone="statusColor">{{ statusLabel }}</OzPill>
		</template>
		<template #actions>
			<OzButton v-if="canEdit" variant="ghost" icon="edit" @click="editProduct">Ürünü Düzenle</OzButton>
			<OzButton v-if="canStock && variants.length > 0" variant="primary" icon="activity" @click="openMovement()">
				Stok Hareketi Ekle
			</OzButton>
		</template>
	</OzPageHeader>
	<OzModuleTabs />

	<OzTabs v-model="activeTab" :tabs="tabs" aria-label="Ürün kartı bölümleri" class="block" />

	<div class="panels">
		<!-- ══════════ GENEL ══════════ -->
		<template v-if="activeTab === 'genel'">
			<OzCard v-if="product.images.length" title="Görseller" class="block">
				<div class="gallery-strip">
					<div v-for="img in product.images" :key="img.id" class="gallery-thumb" :class="{ cover: img.isCover }">
						<img :src="img.url" :alt="product.name" loading="lazy" />
						<span v-if="img.isCover" class="cover-badge">Kapak</span>
					</div>
				</div>
			</OzCard>

			<OzCard title="Kimlik" class="block">
				<dl class="field-grid">
					<div class="field"><dt>SKU</dt><dd class="mono">{{ product.sku || '—' }}</dd></div>
					<div class="field"><dt>Barkod</dt><dd class="mono">{{ product.barcode || '—' }}</dd></div>
					<div class="field"><dt>Durum</dt><dd><OzPill :tone="statusColor">{{ statusLabel }}</OzPill></dd></div>
					<div class="field"><dt>Cinsiyet</dt><dd>{{ product.gender || '—' }}</dd></div>
					<div class="field"><dt>Kategori</dt><dd>{{ product.category || '—' }}</dd></div>
					<div class="field"><dt>Marka</dt><dd>{{ product.brand || '—' }}</dd></div>
				</dl>
			</OzCard>

			<OzCard title="Fiyat (Temel)" class="block">
				<dl class="field-grid">
					<div class="field"><dt>Satış Fiyatı</dt><dd>{{ money(product.price) }}</dd></div>
					<div class="field"><dt>Eski Fiyat</dt><dd>{{ product.oldPrice ? money(product.oldPrice) : '—' }}</dd></div>
					<div class="field"><dt>Piyasa Fiyatı</dt><dd>{{ product.marketPrice ? money(product.marketPrice) : '—' }}</dd></div>
					<div class="field"><dt>Alış Fiyatı</dt><dd>{{ product.purchasePrice ? money(product.purchasePrice) : '—' }}</dd></div>
				</dl>
			</OzCard>

			<OzCard title="Lojistik" class="block">
				<dl class="field-grid">
					<div class="field"><dt>Ağırlık</dt><dd>{{ product.weight ? `${product.weight} kg` : '—' }}</dd></div>
					<div class="field"><dt>Desi</dt><dd>{{ product.desi ?? '—' }}</dd></div>
					<div class="field"><dt>Kargo Süresi</dt><dd>{{ product.shippingTime ? `${product.shippingTime} gün` : '—' }}</dd></div>
					<div class="field"><dt>Kargo Ücreti</dt><dd>{{ product.freeShipping ? 'Ücretsiz' : (product.shippingFee ? money(product.shippingFee) : '—') }}</dd></div>
					<div class="field"><dt>Min. Sipariş</dt><dd>{{ product.minOrderQty ?? '—' }} adet</dd></div>
					<div class="field"><dt>Sipariş Katı</dt><dd>{{ product.orderMultiple ?? '—' }}</dd></div>
					<div class="field"><dt>Menşei</dt><dd>{{ product.originCountry || '—' }} <OzPill v-if="product.isDomestic" tone="accent">Yerli</OzPill></dd></div>
					<div class="field"><dt>Üretici Kodu</dt><dd class="mono">{{ product.manufacturerCode || '—' }}</dd></div>
					<div class="field"><dt>GTİP</dt><dd class="mono">{{ product.gtipCode || '—' }}</dd></div>
				</dl>
			</OzCard>

			<OzCard v-if="product.material || product.careInstructions" title="İçerik" class="block">
				<dl class="field-grid">
					<div class="field"><dt>Materyal</dt><dd>{{ product.material || '—' }}</dd></div>
					<div class="field"><dt>Bakım Talimatları</dt><dd>{{ product.careInstructions || '—' }}</dd></div>
				</dl>
			</OzCard>
		</template>

		<!-- ══════════ STOK ══════════ -->
		<template v-if="activeTab === 'stok'">
			<div class="stats">
				<OzStatTile v-for="s in stats" :key="s.key" :label="s.label" :value="s.value" :icon="s.icon" :tone="s.tone" />
			</div>

			<OzCard title="Depo Bazlı Stok" :meta="variants.length ? `${variants.length} varyant` : ''" class="block">
				<OzEmptyState v-if="variants.length === 0" icon="package" title="Varyant yok" description="Bu ürüne ait stok takipli varyant bulunamadı." compact />
				<div v-else class="table-scroll">
					<table class="matrix-table">
						<thead>
							<tr>
								<th class="col-variant">Varyant</th>
								<th v-for="w in warehouses" :key="w.id" class="col-wh">
									<div class="wh-head">
										<span>{{ w.name }}</span>
										<span class="wh-code">{{ w.code }}</span>
									</div>
								</th>
								<th class="col-total">Toplam</th>
							</tr>
						</thead>
						<tbody>
							<tr v-for="v in variants" :key="v.id">
								<td class="col-variant">
									<div class="variant-cell">
										<div class="variant-info">
											<span v-if="v.size" class="chip">{{ v.size }}</span>
											<span v-if="v.colorName" class="chip">
												<span class="dot" :style="{ background: v.colorHex }"></span>
												{{ v.colorName }}
											</span>
										</div>
										<span class="mono sku">{{ v.sku }}</span>
									</div>
								</td>
								<td v-for="cell in v.warehouses" :key="cell.warehouseId" class="col-wh" :class="{ 'cell-critical': cell.isCritical }">
									<button class="cell-btn" :disabled="!canStock" title="Stok hareketi ekle" @click="openMovement(v, cell.warehouseId)">
										<span class="cell-qty">{{ cell.quantity }}</span>
										<span v-if="cell.reserved > 0" class="cell-reserved">rez. {{ cell.reserved }}</span>
									</button>
								</td>
								<td class="col-total num">{{ v.totalStock }}</td>
							</tr>
						</tbody>
					</table>
				</div>
			</OzCard>

			<OzCard title="Son Hareketler" :meta="movements.length ? `son ${movements.length} kayıt` : ''" class="block">
				<OzTable :columns="movementColumns" :rows="movements" row-key="id" min-width="820px" empty-title="Hareket kaydı yok" empty-text="Bu ürün için henüz stok hareketi girilmemiş.">
					<template #cell-variant="{ row }">
						<span v-if="row.size" class="chip">{{ row.size }}</span>
						<span v-if="row.colorName" class="chip">{{ row.colorName }}</span>
						<span class="mono sku">{{ row.sku }}</span>
					</template>
					<template #cell-warehouse="{ row }">{{ row.warehouseName }} <span class="dim mono">{{ row.warehouseCode }}</span></template>
					<template #cell-type="{ row }"><OzPill :tone="movementTypeColor(row.type)">{{ movementTypeLabel(row.type) }}</OzPill></template>
					<template #cell-quantity="{ row }">
						<span class="mono" :class="row.quantity > 0 ? 'text-pos' : 'text-neg'">{{ row.quantity > 0 ? '+' : '' }}{{ row.quantity }}</span>
					</template>
					<template #cell-change="{ row }"><span class="mono dim">{{ row.beforeQuantity }} → {{ row.afterQuantity }}</span></template>
					<template #cell-user="{ row }"><span class="dim">{{ row.userName || '—' }}</span></template>
					<template #cell-note="{ row }"><span class="dim note-cell">{{ row.note || '—' }}</span></template>
				</OzTable>
			</OzCard>
		</template>

		<!-- ══════════ FİYAT ══════════ -->
		<template v-if="activeTab === 'fiyat'">
			<OzCard title="Fiyat Listeleri" :meta="variants.length ? `${variants.length} varyant` : ''" class="block">
				<OzEmptyState v-if="variants.length === 0" icon="tag" title="Varyant yok" description="Fiyat listesi tanımlamak için önce varyant gerekir." compact />
				<div v-else class="table-scroll">
					<table class="matrix-table">
						<thead>
							<tr>
								<th class="col-variant">Varyant</th>
								<th v-for="pt in priceTypes" :key="pt.value" class="col-wh">{{ pt.label }}</th>
							</tr>
						</thead>
						<tbody>
							<tr v-for="v in variants" :key="v.id">
								<td class="col-variant">
									<div class="variant-cell">
										<div class="variant-info">
											<span v-if="v.size" class="chip">{{ v.size }}</span>
											<span v-if="v.colorName" class="chip">
												<span class="dot" :style="{ background: v.colorHex }"></span>
												{{ v.colorName }}
											</span>
										</div>
										<span class="mono sku">{{ v.sku }}</span>
									</div>
								</td>
								<td v-for="pt in priceTypes" :key="pt.value" class="col-wh">
									<button class="cell-btn" :disabled="!canPriceList" title="Fiyat düzenle" @click="openPrice(v, pt.value)">
										<template v-if="findPrice(v, pt.value)">
											<span class="cell-qty">{{ money(findPrice(v, pt.value).price) }}</span>
											<span v-if="!findPrice(v, pt.value).isActive" class="cell-reserved">pasif</span>
										</template>
										<span v-else class="dim">—</span>
									</button>
								</td>
							</tr>
						</tbody>
					</table>
				</div>
			</OzCard>
		</template>

		<!-- ══════════ PAZARYERİ ══════════ -->
		<template v-if="activeTab === 'pazaryeri'">
			<OzCard title="Pazaryeri Listeleme" :meta="marketplaces.length ? `${marketplaces.length} kanal` : ''" class="block">
				<OzEmptyState v-if="marketplaces.length === 0" icon="store" title="Uygun pazaryeri yok" description="Bu ürünün kategorisi henüz bir pazaryeri eşlemesine bağlı değil." compact />
				<div v-else class="mp-grid">
					<button
						v-for="mp in marketplaces"
						:key="mp.key"
						type="button"
						class="mp-card"
						@click="openListing(mp)"
					>
						<span class="mp-logo" :style="{ background: mp.color || '#888' }">{{ mp.logoText }}</span>
						<div class="mp-info">
							<span class="mp-name">{{ mp.name }}</span>
							<OzPill :tone="listings[mp.key]?.isSent ? 'success' : 'neutral'">
								{{ listings[mp.key]?.isSent ? 'Gönderildi' : 'Gönderilmedi' }}
							</OzPill>
						</div>
						<span v-if="listings[mp.key]?.price" class="mp-price">{{ money(listings[mp.key].price) }}</span>
					</button>
				</div>
			</OzCard>
		</template>
	</div>

	<!-- Stok hareket drawer -->
	<OzDrawer v-model:open="movementOpen" title="Stok Hareketi" :subtitle="product.name" icon="activity" tone="info">
		<form class="form-stack" @submit.prevent="submitMovement">
			<OzField label="Varyant" required :error="errors.product_variant_id">
				<OzCombobox v-model="movement.product_variant_id" :options="variantOptions" placeholder="Varyant seçin" search-placeholder="Varyant ara…" empty-text="Sonuç bulunamadı" />
			</OzField>
			<OzField label="Depo" required :error="errors.warehouse_id">
				<OzCombobox v-model="movement.warehouse_id" :options="warehouseOptions" placeholder="Depo seçin" search-placeholder="Depo ara…" empty-text="Sonuç bulunamadı" />
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
			<OzField label="Miktar" required :error="errors.quantity" hint="Düzeltme için + (artır) veya − (azalt) işaretli sayı girin.">
				<OzInput v-model="movement.quantity" type="number" min="1" />
			</OzField>
			<OzField label="Not" :error="errors.note">
				<OzTextarea v-model="movement.note" :rows="2" placeholder="Açıklama (opsiyonel)..." />
			</OzField>
		</form>

		<div class="field-guide">
			<p class="fg-title">Alan Rehberi</p>
			<dl>
				<div v-for="item in MOVEMENT_FIELD_GUIDE" :key="item.term" class="fg-item">
					<dt>{{ item.term }}</dt>
					<dd>{{ item.desc }}</dd>
				</div>
			</dl>
		</div>
		<template #footer>
			<OzButton variant="ghost" :disabled="busy" @click="movementOpen = false">İptal</OzButton>
			<OzButton variant="primary" :loading="busy" @click="submitMovement">Kaydet</OzButton>
		</template>
	</OzDrawer>

	<!-- Fiyat drawer -->
	<OzDrawer v-model:open="priceOpen" title="Fiyat Listesi" :subtitle="priceForm.variantLabel" icon="tag" tone="info" size="sm">
		<form class="form-stack" @submit.prevent="submitPrice">
			<OzField label="Tip">
				<OzInput :model-value="priceTypeLabel(priceForm.type)" readonly />
			</OzField>
			<OzField label="Fiyat" required :error="priceErrors.price">
				<OzInput v-model="priceForm.price" type="number" min="0" step="0.01" suffix="₺" />
			</OzField>
			<OzField label="Para Birimi">
				<OzSelect v-model="priceForm.currency" :options="currencyOptions" />
			</OzField>
			<OzSwitch v-model="priceForm.is_active" label="Aktif" />
		</form>

		<div class="field-guide">
			<p class="fg-title">Alan Rehberi</p>
			<dl>
				<div v-for="item in PRICE_FIELD_GUIDE" :key="item.term" class="fg-item">
					<dt>{{ item.term }}</dt>
					<dd>{{ item.desc }}</dd>
				</div>
			</dl>
		</div>
		<template #footer>
			<OzButton v-if="priceForm.id" variant="danger" :disabled="priceBusy" @click="deletePrice">Sil</OzButton>
			<OzButton variant="ghost" :disabled="priceBusy" @click="priceOpen = false">İptal</OzButton>
			<OzButton variant="primary" :loading="priceBusy" @click="submitPrice">Kaydet</OzButton>
		</template>
	</OzDrawer>

	<!-- Pazaryeri listeleme drawer (paylaşılan component — API'si korunuyor) -->
	<MarketplaceListingDrawer
		:open="listingOpen"
		:product-id="product.id"
		:marketplace="activeMarketplace"
		@close="listingOpen = false"
		@saved="listingOpen = false"
	/>
</template>

<style scoped>
.block { margin-bottom: 14px; }
.panels { display: flex; flex-direction: column; }
.mono { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; }
.dim { color: var(--oz-muted); }
.num { text-align: right; }

/* ── Stok özetleri (standart liste stats grid'i) ── */
.stats { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; margin-bottom: 14px; }
@media (max-width: 1040px) { .stats { grid-template-columns: repeat(2, minmax(0, 1fr)); } }

/* ── Genel bilgiler ── */
.field-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 12px 20px; padding: 18px; }
.field dt { font-size: 10.5px; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; color: var(--oz-muted); margin-bottom: 3px; }
.field dd { margin: 0; font-size: 13px; font-weight: 600; color: var(--oz-ink-soft); }

.gallery-strip { display: flex; gap: 8px; flex-wrap: wrap; padding: 14px 18px 18px; }
.gallery-thumb { position: relative; width: 64px; height: 64px; border-radius: 8px; overflow: hidden; border: 1px solid var(--oz-border); }
.gallery-thumb img { width: 100%; height: 100%; object-fit: cover; }
.gallery-thumb.cover { border-color: var(--oz-accent); }
.cover-badge { position: absolute; bottom: 0; left: 0; right: 0; background: rgba(var(--oz-accent-rgb), .85); color: #fff; font-size: 8.5px; font-weight: 700; text-align: center; padding: 1px 0; }

/* ── Matris tablo (tıklanabilir hücreler — özel tablo + token) ── */
.table-scroll { overflow-x: auto; }
.matrix-table { width: 100%; border-collapse: separate; border-spacing: 0; }
.matrix-table thead tr { background: var(--oz-surface-2); }
.matrix-table th {
	text-align: left; padding: 10px 14px; font-size: 10.5px; font-weight: 700; color: var(--oz-muted);
	border-bottom: 1px solid var(--oz-border); text-transform: uppercase; letter-spacing: .04em; white-space: nowrap;
}
.matrix-table td {
	padding: 8px 14px; font-size: 13px; color: var(--oz-ink); border-bottom: 1px solid var(--oz-border-soft); vertical-align: middle;
}
.matrix-table tr:last-child td { border-bottom: none; }
.matrix-table tbody tr:hover td { background: var(--oz-surface-2); }

.col-variant { min-width: 200px; }
.col-wh { text-align: center; min-width: 110px; }
.col-total { text-align: right; font-weight: 700; }
.wh-head { display: flex; flex-direction: column; align-items: center; gap: 2px; }
.wh-code { font-weight: 500; text-transform: none; letter-spacing: 0; }

.variant-cell { display: flex; flex-direction: column; gap: 3px; }
.variant-info { display: flex; align-items: center; gap: 5px; flex-wrap: wrap; }
.chip { display: inline-flex; align-items: center; gap: 4px; padding: 1px 7px; background: var(--oz-surface-2); color: var(--oz-muted); border-radius: 6px; font-size: 10.5px; font-weight: 600; }
.chip .dot { width: 7px; height: 7px; border-radius: 50%; border: 1px solid var(--oz-border-strong); }
.sku { font-size: 10.5px; color: var(--oz-muted); }

.cell-btn {
	display: flex; flex-direction: column; align-items: center; gap: 1px; margin: 0 auto;
	background: none; border: none; cursor: pointer; padding: 4px 10px; border-radius: 7px; transition: background .15s;
	font: inherit; color: var(--oz-ink);
}
.cell-btn:hover:not(:disabled) { background: var(--oz-accent-soft); }
.cell-btn:disabled { cursor: default; }
.cell-btn:focus-visible { outline: 2px solid var(--oz-accent); outline-offset: 1px; }
.cell-qty { font-weight: 700; font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; }
.cell-reserved { font-size: 9.5px; color: var(--oz-muted); }
.cell-critical .cell-qty { color: var(--oz-warning); }

.text-pos { color: var(--oz-success); }
.text-neg { color: var(--oz-danger); }
.note-cell { display: inline-block; max-width: 220px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; vertical-align: bottom; }

/* ── Pazaryeri kartları (kit karşılığı yok, token ile) ── */
.mp-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 10px; padding: 18px; }
.mp-card {
	display: flex; align-items: center; gap: 10px; padding: 12px; text-align: left;
	background: var(--oz-surface); border: 1px solid var(--oz-border); border-radius: 10px;
	cursor: pointer; transition: border-color .15s, box-shadow .15s; font: inherit;
}
.mp-card:hover { border-color: rgba(var(--oz-accent-rgb), .4); box-shadow: var(--oz-shadow-sm); }
.mp-card:focus-visible { outline: 2px solid var(--oz-accent); outline-offset: 1px; }
.mp-logo { width: 34px; height: 34px; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 800; font-size: 12px; flex-shrink: 0; }
.mp-info { display: flex; flex-direction: column; align-items: flex-start; gap: 4px; flex: 1; min-width: 0; }
.mp-name { font-size: 13px; font-weight: 700; color: var(--oz-ink-soft); }
.mp-price { font-size: 12px; font-weight: 700; color: var(--oz-ink-soft); font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; }

/* ── Drawer formları ── */
.form-stack { display: flex; flex-direction: column; gap: 14px; }
.type-buttons { display: flex; gap: 6px; }
.type-btn {
	display: inline-flex; align-items: center; justify-content: center; gap: 5px;
	flex: 1; padding: 10px 14px;
	background: var(--oz-surface); border: 1px solid var(--oz-border);
	border-radius: 9px; font: inherit; font-size: 12.5px;
	font-weight: 600; color: var(--oz-muted); cursor: pointer;
	transition: border-color .15s, background .15s, color .15s;
}
.type-btn:hover { border-color: rgba(var(--oz-accent-rgb), .4); }
.type-btn.active { border-color: var(--oz-accent); background: var(--oz-accent-soft); color: var(--oz-accent); }
.type-btn:focus-visible { outline: 2px solid var(--oz-accent); outline-offset: 1px; }

/* ── Alan rehberi ── */
.field-guide { border-top: 1px solid var(--oz-border); padding-top: 12px; margin-top: 16px; }
.fg-title { font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: var(--oz-muted); margin: 0 0 8px; }
.fg-title + dl { margin: 0; }
.fg-item { margin-bottom: 8px; }
.fg-item:last-child { margin-bottom: 0; }
.fg-item dt { font-size: 12px; font-weight: 600; color: var(--oz-ink-soft); }
.fg-item dd { font-size: 11.5px; color: var(--oz-muted); margin: 1px 0 0; }
</style>
