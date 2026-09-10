<template>
	<Head :title="`Ürün Kartı · ${product.name}`" />
	<div class="page-product-card">
		<Breadcrumb
			:items="[
				{ label: 'Ana Sayfa', to: '/workflow', icon: 'home' },
				{ label: 'Katalog', to: '/products' },
				{ label: product.name },
			]"
		/>

		<Link href="/products" class="back-link">
			<ArrowLeft :size="13" />
			Kataloğa dön
		</Link>

		<PageHeader :title="product.name">
			<template #subtitle>
				<span class="mono">{{ product.sku }}</span>
				<span v-if="product.barcode"> · <span class="mono">{{ product.barcode }}</span></span>
				<Badge :label="statusLabel" :color="statusColor" variant="tonal" size="sm" />
			</template>
			<template #actions>
				<Link :href="`/products/${product.id}/edit`" class="btn btn-ghost btn-sm">
					<Pencil :size="13" />
					Ürünü Düzenle
				</Link>
				<Button v-if="canStock && variants.length > 0" variant="primary" with-icon @click="openMovement()">
					<template #leading><Activity :size="13" /></template>
					Stok Hareketi Ekle
				</Button>
			</template>
		</PageHeader>

		<!-- Sekmeler -->
		<div class="tab-bar">
			<button
				v-for="t in tabs"
				:key="t.key"
				class="tab-btn"
				:class="{ active: activeTab === t.key }"
				@click="activeTab = t.key"
			>
				<component :is="t.icon" :size="14" />
				{{ t.label }}
			</button>
		</div>

		<!-- ══════════ GENEL ══════════ -->
		<template v-if="activeTab === 'genel'">
			<Card v-if="product.images.length" title="Görseller" body-class="p-3">
				<div class="gallery-strip">
					<div v-for="img in product.images" :key="img.id" class="gallery-thumb" :class="{ cover: img.isCover }">
						<img :src="img.url" :alt="product.name" loading="lazy" />
						<span v-if="img.isCover" class="cover-badge">Kapak</span>
					</div>
				</div>
			</Card>

			<Card title="Kimlik" body-class="p-4">
				<dl class="field-grid">
					<div class="field"><dt>SKU</dt><dd class="mono">{{ product.sku || '—' }}</dd></div>
					<div class="field"><dt>Barkod</dt><dd class="mono">{{ product.barcode || '—' }}</dd></div>
					<div class="field"><dt>Durum</dt><dd><Badge :label="statusLabel" :color="statusColor" variant="tonal" /></dd></div>
					<div class="field"><dt>Cinsiyet</dt><dd>{{ product.gender || '—' }}</dd></div>
					<div class="field"><dt>Kategori</dt><dd>{{ product.category || '—' }}</dd></div>
					<div class="field"><dt>Marka</dt><dd>{{ product.brand || '—' }}</dd></div>
				</dl>
			</Card>

			<Card title="Fiyat (Temel)" body-class="p-4">
				<dl class="field-grid">
					<div class="field"><dt>Satış Fiyatı</dt><dd>{{ money(product.price) }}</dd></div>
					<div class="field"><dt>Eski Fiyat</dt><dd>{{ product.oldPrice ? money(product.oldPrice) : '—' }}</dd></div>
					<div class="field"><dt>Piyasa Fiyatı</dt><dd>{{ product.marketPrice ? money(product.marketPrice) : '—' }}</dd></div>
					<div class="field"><dt>Alış Fiyatı</dt><dd>{{ product.purchasePrice ? money(product.purchasePrice) : '—' }}</dd></div>
				</dl>
			</Card>

			<Card title="Lojistik" body-class="p-4">
				<dl class="field-grid">
					<div class="field"><dt>Ağırlık</dt><dd>{{ product.weight ? `${product.weight} kg` : '—' }}</dd></div>
					<div class="field"><dt>Desi</dt><dd>{{ product.desi ?? '—' }}</dd></div>
					<div class="field"><dt>Kargo Süresi</dt><dd>{{ product.shippingTime ? `${product.shippingTime} gün` : '—' }}</dd></div>
					<div class="field"><dt>Kargo Ücreti</dt><dd>{{ product.freeShipping ? 'Ücretsiz' : (product.shippingFee ? money(product.shippingFee) : '—') }}</dd></div>
					<div class="field"><dt>Min. Sipariş</dt><dd>{{ product.minOrderQty ?? '—' }} adet</dd></div>
					<div class="field"><dt>Sipariş Katı</dt><dd>{{ product.orderMultiple ?? '—' }}</dd></div>
					<div class="field"><dt>Menşei</dt><dd>{{ product.originCountry || '—' }} <span v-if="product.isDomestic" class="chip">Yerli</span></dd></div>
					<div class="field"><dt>Üretici Kodu</dt><dd class="mono">{{ product.manufacturerCode || '—' }}</dd></div>
					<div class="field"><dt>GTİP</dt><dd class="mono">{{ product.gtipCode || '—' }}</dd></div>
				</dl>
			</Card>

			<Card v-if="product.material || product.careInstructions" title="İçerik" body-class="p-4">
				<dl class="field-grid">
					<div class="field"><dt>Materyal</dt><dd>{{ product.material || '—' }}</dd></div>
					<div class="field"><dt>Bakım Talimatları</dt><dd>{{ product.careInstructions || '—' }}</dd></div>
				</dl>
			</Card>
		</template>

		<!-- ══════════ STOK ══════════ -->
		<template v-if="activeTab === 'stok'">
			<div class="stat-grid">
				<StatWidget :icon="Boxes" :value="totals.stock" title="Toplam Stok" color="primary" />
				<StatWidget :icon="Lock" :value="totals.reserved" title="Rezerve" color="info" />
				<StatWidget :icon="CheckCircle2" :value="totals.available" title="Kullanılabilir" color="success" />
				<StatWidget :icon="AlertTriangle" :value="totals.criticalVariants" title="Kritik Varyant" color="warning" />
			</div>

			<Card title="Depo Bazlı Stok" body-class="p-0">
				<EmptyState v-if="variants.length === 0" :icon="Boxes" title="Varyant yok" hint="Bu ürüne ait stok takipli varyant bulunamadı." />
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
									<button class="cell-btn" :disabled="!canStock" @click="openMovement(v, cell.warehouseId)" title="Stok hareketi ekle">
										<span class="cell-qty">{{ cell.quantity }}</span>
										<span v-if="cell.reserved > 0" class="cell-reserved">rez. {{ cell.reserved }}</span>
									</button>
								</td>
								<td class="col-total num">{{ v.totalStock }}</td>
							</tr>
						</tbody>
					</table>
				</div>
			</Card>

			<Card title="Son Hareketler" body-class="p-0">
				<EmptyState v-if="movements.length === 0" :icon="ArrowUpDown" title="Hareket kaydı yok" hint="Bu ürün için henüz stok hareketi girilmemiş." />
				<div v-else class="table-scroll">
					<table class="movement-table">
						<thead>
							<tr>
								<th>Tarih</th>
								<th>Varyant</th>
								<th>Depo</th>
								<th>Tip</th>
								<th class="num">Miktar</th>
								<th class="num">Önce → Sonra</th>
								<th>Kullanıcı</th>
								<th>Not</th>
							</tr>
						</thead>
						<tbody>
							<tr v-for="m in movements" :key="m.id">
								<td class="dim">{{ m.createdAt }}</td>
								<td>
									<span v-if="m.size" class="chip">{{ m.size }}</span>
									<span v-if="m.colorName" class="chip">{{ m.colorName }}</span>
									<span class="mono sku">{{ m.sku }}</span>
								</td>
								<td>{{ m.warehouseName }} <span class="dim mono">{{ m.warehouseCode }}</span></td>
								<td><Badge :label="movementTypeLabel(m.type)" :color="movementTypeColor(m.type)" variant="tonal" /></td>
								<td class="num mono" :class="m.quantity > 0 ? 'text-pos' : 'text-neg'">{{ m.quantity > 0 ? '+' : '' }}{{ m.quantity }}</td>
								<td class="num mono dim">{{ m.beforeQuantity }} → {{ m.afterQuantity }}</td>
								<td class="dim">{{ m.userName || '—' }}</td>
								<td class="dim note-cell">{{ m.note || '—' }}</td>
							</tr>
						</tbody>
					</table>
				</div>
			</Card>
		</template>

		<!-- ══════════ FİYAT ══════════ -->
		<template v-if="activeTab === 'fiyat'">
			<Card title="Fiyat Listeleri" body-class="p-0">
				<EmptyState v-if="variants.length === 0" :icon="Tag" title="Varyant yok" hint="Fiyat listesi tanımlamak için önce varyant gerekir." />
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
									<button class="cell-btn" :disabled="!canPriceList" @click="openPrice(v, pt.value)" title="Fiyat düzenle">
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
			</Card>
		</template>

		<!-- ══════════ PAZARYERİ ══════════ -->
		<template v-if="activeTab === 'pazaryeri'">
			<Card title="Pazaryeri Listeleme" body-class="p-3">
				<EmptyState v-if="marketplaces.length === 0" :icon="Store" title="Uygun pazaryeri yok" hint="Bu ürünün kategorisi henüz bir pazaryeri eşlemesine bağlı değil." />
				<div v-else class="mp-grid">
					<button
						v-for="mp in marketplaces"
						:key="mp.key"
						class="mp-card"
						@click="openListing(mp)"
					>
						<span class="mp-logo" :style="{ background: mp.color || '#888' }">{{ mp.logoText }}</span>
						<div class="mp-info">
							<span class="mp-name">{{ mp.name }}</span>
							<Badge
								:label="listings[mp.key]?.isSent ? 'Gönderildi' : 'Gönderilmedi'"
								:color="listings[mp.key]?.isSent ? 'success' : 'neutral'"
								variant="tonal"
								size="sm"
							/>
						</div>
						<span v-if="listings[mp.key]?.price" class="mp-price">{{ money(listings[mp.key].price) }}</span>
					</button>
				</div>
			</Card>
		</template>

		<!-- Stok hareket drawer -->
		<AppDrawer v-model="movementOpen" title="Stok Hareketi" :subtitle="product.name" variant="info" width="480px">
			<form class="form-grid" @submit.prevent="submitMovement">
				<div class="form-row">
					<label class="form-label">Varyant <span class="req">*</span></label>
					<CustomSelect v-model="movement.product_variant_id" :options="variantOptions" :show-label="false" />
				</div>
				<div class="form-row">
					<label class="form-label">Depo <span class="req">*</span></label>
					<CustomSelect v-model="movement.warehouse_id" :options="warehouseOptions" :show-label="false" />
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
						><component :is="t.icon" :size="13" /> {{ t.label }}</button>
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

			<div class="field-guide">
				<p class="fg-title">Alan Rehberi</p>
				<dl>
					<div v-for="item in MOVEMENT_FIELD_GUIDE" :key="item.term" class="fg-item">
						<dt>{{ item.term }}</dt>
						<dd>{{ item.desc }}</dd>
					</div>
				</dl>
			</div>
			<template #footer="{ close }">
				<Button variant="ghost" :disabled="busy" @click="close">İptal</Button>
				<Button variant="primary" :loading="busy" @click="submitMovement">Kaydet</Button>
			</template>
		</AppDrawer>

		<!-- Fiyat drawer -->
		<AppDrawer v-model="priceOpen" title="Fiyat Listesi" :subtitle="priceForm.variantLabel" variant="info" width="420px">
			<form class="form-grid" @submit.prevent="submitPrice">
				<div class="form-row">
					<label class="form-label">Tip</label>
					<div class="lst-readonly">{{ priceTypeLabel(priceForm.type) }}</div>
				</div>
				<div class="form-row">
					<label class="form-label">Fiyat <span class="req">*</span></label>
					<input v-model.number="priceForm.price" type="number" min="0" step="0.01" class="form-input" />
					<span v-if="priceErrors.price" class="form-error">{{ priceErrors.price }}</span>
				</div>
				<div class="form-row">
					<label class="form-label">Para Birimi</label>
					<CustomSelect v-model="priceForm.currency" :options="currencyOptions" :show-label="false" />
				</div>
				<label class="check-inline">
					<input type="checkbox" v-model="priceForm.is_active" />
					<span>Aktif</span>
				</label>
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
			<template #footer="{ close }">
				<Button v-if="priceForm.id" variant="danger" :disabled="priceBusy" @click="deletePrice">Sil</Button>
				<Button variant="ghost" :disabled="priceBusy" @click="close">İptal</Button>
				<Button variant="primary" :loading="priceBusy" @click="submitPrice">Kaydet</Button>
			</template>
		</AppDrawer>

		<!-- Pazaryeri listeleme drawer (paylaşılan component) -->
		<MarketplaceListingDrawer
			:open="listingOpen"
			:product-id="product.id"
			:marketplace="activeMarketplace"
			@close="listingOpen = false"
			@saved="listingOpen = false"
		/>
	</div>
</template>

<script setup>
import { computed, inject, reactive, ref, watch } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import {
	Activity, ArrowDown, ArrowLeft, ArrowUp, ArrowUpDown,
	AlertTriangle, Boxes, CheckCircle2, Info, Lock, Pencil, Store, Tag,
} from 'lucide-vue-next'
import AppLayout from '@/Layouts/AppLayout.vue'
import Breadcrumb from '@/Components/Breadcrumb.vue'
import PageHeader from '@/Components/PageHeader.vue'
import Card from '@/Components/Card.vue'
import StatWidget from '@/Components/StatWidget.vue'
import Badge from '@/Components/Badge.vue'
import Button from '@/Components/Button.vue'
import AppDrawer from '@/Components/AppDrawer.vue'
import CustomSelect from '@/Components/CustomSelect.vue'
import EmptyState from '@/Components/EmptyState.vue'
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
const $swal = inject('$swal')
const page = usePage()

const permissions = computed(() => page.props.auth?.permissions ?? [])
const canStock = computed(() => permissions.value.includes('stock.manage'))
const canPriceList = computed(() => permissions.value.includes('price-list.manage'))

const validTabs = ['genel', 'stok', 'fiyat', 'pazaryeri']
const activeTab = ref(validTabs.includes(props.initialTab) ? props.initialTab : 'genel')

const tabs = [
	{ key: 'genel', label: 'Genel', icon: Info },
	{ key: 'stok', label: 'Stok', icon: Boxes },
	{ key: 'fiyat', label: 'Fiyat', icon: Tag },
	{ key: 'pazaryeri', label: 'Pazaryeri', icon: Store },
]

const statuses = { draft: ['Taslak', 'neutral'], published: ['Yayında', 'success'], archived: ['Arşivlendi', 'danger'] }
const statusLabel = computed(() => statuses[props.product.status]?.[0] ?? props.product.status)
const statusColor = computed(() => statuses[props.product.status]?.[1] ?? 'neutral')

function money(v) {
	return '₺' + Number(v ?? 0).toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

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
	const ok = await $swal.dangerConfirm({
		title: 'Fiyatı Sil',
		html: `<strong>${priceTypeLabel(priceForm.type)}</strong> fiyatı silinecek.`,
		confirmText: 'Sil',
		cancelText: 'Vazgeç',
	})
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

<style scoped>
.page-product-card { display: flex; flex-direction: column; gap: 14px; }

.back-link {
	display: inline-flex; align-items: center; gap: 6px;
	font-size: 12.5px; font-weight: 600; color: rgb(var(--color-muted-rgb));
	text-decoration: none; width: fit-content;
}
.back-link:hover { color: rgb(var(--color-primary-rgb)); }

.mono { font-family: 'SF Mono', Menlo, Consolas, monospace; }
.dim { color: rgb(var(--color-muted-rgb)); }
.num { text-align: right; }

/* Sekmeler */
.tab-bar { display: flex; gap: 4px; border-bottom: 1px solid rgb(var(--color-border-rgb)); }
.tab-btn {
	display: inline-flex; align-items: center; gap: 6px;
	padding: 9px 14px; background: none; border: none; border-bottom: 2px solid transparent;
	font-family: inherit; font-size: 13px; font-weight: 600; color: rgb(var(--color-muted-rgb));
	cursor: pointer; transition: all .15s; margin-bottom: -1px;
}
.tab-btn:hover { color: rgb(var(--color-ink-rgb)); }
.tab-btn.active { color: rgb(var(--color-primary-rgb)); border-bottom-color: rgb(var(--color-primary-rgb)); }

/* Genel bilgiler */
.field-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 12px 20px; }
.field dt { font-size: 10.5px; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; color: rgb(var(--color-muted-rgb)); margin-bottom: 3px; }
.field dd { font-size: 13px; font-weight: 600; color: rgb(var(--color-ink-rgb)); }

.gallery-strip { display: flex; gap: 8px; flex-wrap: wrap; }
.gallery-thumb { position: relative; width: 64px; height: 64px; border-radius: 8px; overflow: hidden; border: 1px solid rgb(var(--color-border-rgb)); }
.gallery-thumb img { width: 100%; height: 100%; object-fit: cover; }
.gallery-thumb.cover { border-color: rgb(var(--color-primary-rgb)); }
.cover-badge { position: absolute; bottom: 0; left: 0; right: 0; background: rgb(var(--color-primary-rgb) / .85); color: #fff; font-size: 8.5px; font-weight: 700; text-align: center; padding: 1px 0; }

/* Stat kutuları */
.stat-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; }
@media (min-width: 900px) { .stat-grid { grid-template-columns: repeat(4, 1fr); } }

/* Matris tablo */
.table-scroll { overflow-x: auto; }
.matrix-table, .movement-table { width: 100%; border-collapse: separate; border-spacing: 0; }
.matrix-table thead tr, .movement-table thead tr { background: rgb(var(--color-bg-rgb) / .6); }
.matrix-table th, .movement-table th {
	text-align: left; padding: 10px 14px; font-size: 10.5px; font-weight: 700; color: rgb(var(--color-muted-rgb));
	border-bottom: 1px solid rgb(var(--color-border-rgb)); text-transform: uppercase; letter-spacing: .04em; white-space: nowrap;
}
.matrix-table td, .movement-table td {
	padding: 8px 14px; font-size: 13px; color: rgb(var(--color-ink-rgb)); border-bottom: 1px solid rgb(var(--color-border-rgb) / .6); vertical-align: middle;
}
.matrix-table tr:last-child td, .movement-table tr:last-child td { border-bottom: none; }
.matrix-table tr:hover td, .movement-table tr:hover td { background: rgb(var(--color-bg-rgb) / .4); }

.col-variant { min-width: 200px; }
.col-wh { text-align: center; min-width: 110px; }
.col-total { text-align: right; font-weight: 700; }
.wh-head { display: flex; flex-direction: column; align-items: center; gap: 2px; }
.wh-code { font-weight: 500; text-transform: none; letter-spacing: 0; }

.variant-cell { display: flex; flex-direction: column; gap: 3px; }
.variant-info { display: flex; align-items: center; gap: 5px; flex-wrap: wrap; }
.chip { display: inline-flex; align-items: center; gap: 4px; padding: 1px 7px; background: rgb(var(--color-bg-rgb)); color: rgb(var(--color-muted-rgb)); border-radius: 6px; font-size: 10.5px; font-weight: 600; }
.chip .dot { width: 7px; height: 7px; border-radius: 50%; border: 1px solid rgb(var(--color-border-rgb)); }
.sku { font-size: 10.5px; color: rgb(var(--color-muted-rgb)); }

.cell-btn {
	display: flex; flex-direction: column; align-items: center; gap: 1px; margin: 0 auto;
	background: none; border: none; cursor: pointer; padding: 4px 10px; border-radius: 7px; transition: background .15s;
}
.cell-btn:hover:not(:disabled) { background: rgb(var(--color-primary-soft-rgb)); }
.cell-btn:disabled { cursor: default; }
.cell-qty { font-weight: 700; font-family: 'SF Mono', Menlo, Consolas, monospace; }
.cell-reserved { font-size: 9.5px; color: rgb(var(--color-muted-rgb)); }
.cell-critical .cell-qty { color: rgb(var(--color-warning-rgb)); }

.text-pos { color: rgb(var(--color-success-rgb)); }
.text-neg { color: rgb(var(--color-danger-rgb)); }
.note-cell { max-width: 220px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

/* Pazaryeri kartları */
.mp-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 10px; }
.mp-card {
	display: flex; align-items: center; gap: 10px; padding: 12px; text-align: left;
	background: var(--color-surface); border: 1px solid rgb(var(--color-border-rgb)); border-radius: 10px;
	cursor: pointer; transition: all .15s; font-family: inherit;
}
.mp-card:hover { border-color: rgb(var(--color-primary-rgb) / .4); box-shadow: 0 1px 6px rgb(var(--color-ink-rgb) / .06); }
.mp-logo { width: 34px; height: 34px; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 800; font-size: 12px; flex-shrink: 0; }
.mp-info { display: flex; flex-direction: column; gap: 4px; flex: 1; min-width: 0; }
.mp-name { font-size: 13px; font-weight: 700; color: rgb(var(--color-ink-rgb)); }
.mp-price { font-size: 12px; font-weight: 700; color: rgb(var(--color-ink-rgb)); font-family: 'SF Mono', Menlo, Consolas, monospace; }

/* Form */
.form-grid { display: flex; flex-direction: column; gap: 14px; }
.form-row { display: flex; flex-direction: column; gap: 6px; }
.form-label { font-size: 12px; font-weight: 600; color: rgb(var(--color-ink-rgb)); }
.form-label .req { color: rgb(var(--color-danger-rgb)); }
.form-input { padding: 9px 12px; border: 1px solid rgb(var(--color-border-rgb)); border-radius: 8px; font-family: inherit; font-size: 13px; color: rgb(var(--color-ink-rgb)); background: var(--color-surface); outline: none; transition: border-color .15s; }
.form-input:focus { border-color: rgb(var(--color-primary-rgb)); }
.form-hint { font-size: 11px; color: rgb(var(--color-muted-rgb)); }
.form-error { font-size: 11.5px; color: rgb(var(--color-danger-rgb)); }
.lst-readonly { padding: 9px 12px; border: 1px solid rgb(var(--color-border-rgb)); border-radius: 8px; font-size: 13px; color: rgb(var(--color-muted-rgb)); background: rgb(var(--color-bg-rgb) / .5); }
.check-inline { display: inline-flex; align-items: center; gap: 6px; font-size: 13px; color: rgb(var(--color-ink-rgb)); cursor: pointer; }

.type-buttons { display: flex; gap: 6px; }
.type-btn {
	display: inline-flex; align-items: center; justify-content: center; gap: 5px;
	flex: 1; padding: 10px 14px;
	background: var(--color-surface); border: 1.5px solid rgb(var(--color-border-rgb));
	border-radius: 9px; font-family: inherit; font-size: 12.5px;
	font-weight: 600; color: rgb(var(--color-muted-rgb)); cursor: pointer;
	transition: all .15s;
}
.type-btn:hover { border-color: rgb(var(--color-primary-rgb) / .4); }
.type-btn.active { border-color: rgb(var(--color-primary-rgb)); background: rgb(var(--color-primary-soft-rgb)); color: rgb(var(--color-primary-rgb)); }

/* Alan rehberi (drawer form'larının altında) */
.field-guide { border-top: 1px solid rgb(var(--color-border-rgb)); padding-top: 12px; margin-top: 2px; }
.fg-title { font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: rgb(var(--color-muted-rgb)); margin-bottom: 8px; }
.fg-item { margin-bottom: 8px; }
.fg-item:last-child { margin-bottom: 0; }
.fg-item dt { font-size: 12px; font-weight: 600; color: rgb(var(--color-ink-rgb)); }
.fg-item dd { font-size: 11.5px; color: rgb(var(--color-muted-rgb)); margin-top: 1px; }
</style>
