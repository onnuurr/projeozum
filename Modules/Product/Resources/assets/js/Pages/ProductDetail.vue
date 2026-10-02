<script setup>
import { ref, computed, inject } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import {
	OzPageHeader, OzModuleTabs, OzCard, OzButton, OzPill, OzTable, OzTabs, OzEmptyState, OzIcon, OzProgress,
} from '@/Components/Oz'

defineOptions({ layout: AppLayout })

const props = defineProps({
	product: { type: Object, required: true },
	similar: { type: Array, default: () => [] },
})

const showToast = inject('showToast', null)
const page = usePage()
const can = (perm) => (page.props.auth?.permissions ?? []).includes(perm)
const canEdit = computed(() => can('product.add'))
const canViewCard = computed(() => can('product.card.view'))

const PLACEHOLDER = '/images/product-placeholder.svg'
const LOW_STOCK = 20

/* ── Galeri ── */
const images = computed(() => (props.product.images?.length ? props.product.images : [PLACEHOLDER]))
const selectedImage = ref(0)
const zoomActive = ref(false)
const zoomOrigin = ref('50% 50%')

function onZoomMove(e) {
	const r = e.currentTarget.getBoundingClientRect()
	zoomOrigin.value = `${((e.clientX - r.left) / r.width) * 100}% ${((e.clientY - r.top) / r.height) * 100}%`
}
function stepImage(dir) {
	const n = images.value.length
	selectedImage.value = (selectedImage.value + dir + n) % n
}

/* ── Varyant seçimi ── */
const selectedColor = ref(props.product.colors?.[0] ?? null)
const selectedSize = ref(null)

const selectedVariant = computed(() => {
	if (!props.product.variants?.length) return null
	return props.product.variants.find((v) =>
		(!selectedColor.value || v.color_name === selectedColor.value.name)
		&& (!selectedSize.value || v.size === selectedSize.value),
	) ?? null
})

const displayPrice = computed(() => selectedVariant.value?.price ?? props.product.price)
const displayOldPrice = computed(() => selectedVariant.value?.old_price ?? props.product.oldPrice)
const displayStock = computed(() => selectedVariant.value?.stock ?? props.product.stock)

const discountPercent = computed(() => {
	const old = displayOldPrice.value
	if (!old || old <= displayPrice.value) return 0
	return Math.round(((old - displayPrice.value) / old) * 100)
})

function isSizeAvailable(size) {
	if (!props.product.variants?.length) return true
	return props.product.variants.some((v) =>
		(!selectedColor.value || v.color_name === selectedColor.value.name) && v.size === size && v.stock > 0,
	)
}
function toggleSize(size) {
	selectedSize.value = selectedSize.value === size ? null : size
}

function stockState(stock) {
	if (stock <= 0) return { tone: 'danger', label: 'Tükendi' }
	if (stock < LOW_STOCK) return { tone: 'warning', label: `Son ${stock} adet` }
	return { tone: 'success', label: `Stokta · ${stock} adet` }
}
const currentStock = computed(() => stockState(displayStock.value))

/* ── Varyant tablosu ── */
const variantColumns = [
	{ key: 'color_name', label: 'Renk' },
	{ key: 'size', label: 'Beden' },
	{ key: 'sku', label: 'SKU' },
	{ key: 'price', label: 'Fiyat', align: 'right', class: 'oz-cell-amount' },
	{ key: 'stock', label: 'Stok', align: 'right' },
]
function pickVariant(v) {
	selectedColor.value = props.product.colors?.find((c) => c.name === v.color_name) ?? null
	selectedSize.value = v.size ?? null
}
const isPicked = (v) => selectedSize.value && selectedVariant.value?.id === v.id

/* ── Sekmeler ── */
const specEntries = computed(() => Object.entries(props.product.specs ?? {}))
const activeTab = ref('description')
const tabs = computed(() => [
	{ key: 'description', label: 'Açıklama', icon: 'file' },
	{ key: 'specs', label: 'Özellikler', icon: 'rows', count: specEntries.value.length || null },
	{ key: 'reviews', label: 'Değerlendirmeler', icon: 'star', count: props.product.reviewCount || null },
])

const ratingStars = computed(() => Math.round(props.product.rating || 0))
const initials = (name) => (name ?? '').split(' ').map((n) => n[0]).join('').slice(0, 2)

/* ── Aksiyonlar ── */
async function shareProduct() {
	try {
		await navigator.clipboard.writeText(window.location.href)
		showToast?.({ type: 'success', title: 'Bağlantı kopyalandı' })
	} catch {
		showToast?.({ type: 'error', title: 'Bağlantı kopyalanamadı' })
	}
}

function formatPrice(v) {
	return new Intl.NumberFormat('tr-TR', { style: 'currency', currency: 'TRY', maximumFractionDigits: 2 }).format(Number(v ?? 0))
}
</script>

<template>
	<Head :title="product.name" />

	<OzPageHeader :title="product.name">
		<template #subtitle>
			<Link href="/products" class="crumb">Katalog</Link>
			<template v-if="product.category"> · {{ product.category }}</template>
			<template v-if="product.brand"> · {{ product.brand }}</template>
			· <span class="mono">{{ product.sku }}</span>
		</template>
		<template #actions>
			<OzButton icon="chevron-left" @click="router.visit('/products')">Kataloğa Dön</OzButton>
			<OzButton icon="link" @click="shareProduct">Bağlantıyı Kopyala</OzButton>
			<OzButton v-if="canViewCard" icon="file" @click="router.visit(`/products/${product.id}/card`)">Ürün Kartı</OzButton>
			<OzButton v-if="canEdit" variant="primary" icon="edit" @click="router.visit(`/products/${product.id}/edit`)">Düzenle</OzButton>
		</template>
	</OzPageHeader>
	<OzModuleTabs />

	<div class="grid">
		<!-- Galeri -->
		<OzCard class="gallery-card">
			<div
				class="main-image"
				@mouseenter="zoomActive = true"
				@mouseleave="zoomActive = false"
				@mousemove="onZoomMove"
			>
				<img
					:src="images[selectedImage]"
					:alt="product.name"
					:style="zoomActive ? { transformOrigin: zoomOrigin, transform: 'scale(1.6)' } : null"
				/>
				<div class="badges">
					<OzPill v-if="discountPercent > 0" tone="danger">%{{ discountPercent }} indirim</OzPill>
					<OzPill v-if="product.isNew" tone="accent">Yeni</OzPill>
					<OzPill v-if="product.isBestSeller" tone="warning">Çok Satan</OzPill>
				</div>
				<template v-if="images.length > 1">
					<button type="button" class="nav-arrow is-prev" aria-label="Önceki görsel" @click="stepImage(-1)">
						<OzIcon name="chevron-left" :size="16" />
					</button>
					<button type="button" class="nav-arrow is-next" aria-label="Sonraki görsel" @click="stepImage(1)">
						<OzIcon name="chevron-right" :size="16" />
					</button>
					<span class="counter">{{ selectedImage + 1 }} / {{ images.length }}</span>
				</template>
			</div>
			<div v-if="images.length > 1" class="thumbs">
				<button
					v-for="(img, i) in images"
					:key="i"
					type="button"
					class="thumb"
					:class="{ 'is-active': selectedImage === i }"
					:aria-label="`Görsel ${i + 1}`"
					@click="selectedImage = i"
				>
					<img :src="img" alt="" loading="lazy" />
				</button>
			</div>
		</OzCard>

		<!-- Bilgi -->
		<div class="col-side">
			<OzCard title="Fiyat & Stok">
				<template #actions>
					<OzPill :tone="currentStock.tone">{{ currentStock.label }}</OzPill>
				</template>
				<div class="body">
					<div class="price-row">
						<span class="price" :class="{ 'is-discount': discountPercent > 0 }">{{ formatPrice(displayPrice) }}</span>
						<span v-if="discountPercent > 0" class="price-old">{{ formatPrice(displayOldPrice) }}</span>
					</div>
					<div class="price-note">
						{{ selectedVariant && selectedSize ? 'Seçili varyant fiyatı' : (product.variants?.length > 1 ? 'En düşük varyant fiyatı' : 'Satış fiyatı') }}
					</div>

					<div class="meta">
						<div v-if="product.gender" class="meta-row"><span>Cinsiyet</span><b>{{ product.gender }}</b></div>
						<div class="meta-row"><span>Toplam Stok</span><b>{{ product.stock }} adet</b></div>
						<div class="meta-row"><span>Varyant</span><b>{{ product.variants?.length ?? 0 }}</b></div>
						<div v-if="product.reviewCount" class="meta-row">
							<span>Puan</span>
							<b class="rating">
								<OzIcon name="star" :size="13" class="star-on" /> {{ product.rating.toFixed(1) }}
								<small>({{ product.reviewCount }})</small>
							</b>
						</div>
						<div class="meta-row">
							<span>Kargo</span>
							<OzPill :tone="product.freeShipping ? 'success' : 'neutral'">{{ product.freeShipping ? 'Ücretsiz' : 'Ücretli' }}</OzPill>
						</div>
					</div>
				</div>
			</OzCard>

			<OzCard v-if="product.colors?.length || product.sizes?.length" title="Varyant Seçimi">
				<div class="body options">
					<div v-if="product.colors?.length" class="option">
						<div class="option-head">
							<span class="option-label">Renk</span>
							<span class="option-value">{{ selectedColor?.name ?? 'Tümü' }}</span>
						</div>
						<div class="swatches">
							<button
								v-for="c in product.colors"
								:key="c.name"
								type="button"
								class="swatch"
								:class="{ 'is-active': selectedColor?.name === c.name }"
								:style="{ background: c.hex }"
								:title="c.name"
								:aria-label="c.name"
								@click="selectedColor = c"
							/>
						</div>
					</div>
					<div v-if="product.sizes?.length" class="option">
						<div class="option-head">
							<span class="option-label">Beden</span>
							<span class="option-value">{{ selectedSize ?? 'Seçilmedi' }}</span>
						</div>
						<div class="sizes">
							<button
								v-for="size in product.sizes"
								:key="size"
								type="button"
								class="size"
								:class="{ 'is-active': selectedSize === size }"
								:disabled="!isSizeAvailable(size)"
								@click="toggleSize(size)"
							>{{ size }}</button>
						</div>
					</div>
				</div>
			</OzCard>
		</div>
	</div>

	<OzCard v-if="product.variants?.length" title="Varyantlar" :meta="`${product.variants.length} varyant`" class="section">
		<OzTable :columns="variantColumns" :rows="product.variants" row-key="id" min-width="520px">
			<template #cell-color_name="{ row }">
				<span class="color-cell">
					<span v-if="row.color_hex" class="dot" :style="{ background: row.color_hex }" />
					{{ row.color_name || '—' }}
				</span>
			</template>
			<template #cell-size="{ row }">
				<button type="button" class="pick" :class="{ 'is-active': isPicked(row) }" @click="pickVariant(row)">{{ row.size || '—' }}</button>
			</template>
			<template #cell-sku="{ value }"><span class="mono">{{ value || '—' }}</span></template>
			<template #cell-price="{ row }">
				<span class="mono">{{ formatPrice(row.price) }}</span>
				<span v-if="row.old_price && row.old_price > row.price" class="mono strike">{{ formatPrice(row.old_price) }}</span>
			</template>
			<template #cell-stock="{ value }">
				<OzPill :tone="stockState(value).tone">{{ value }}</OzPill>
			</template>
		</OzTable>
	</OzCard>

	<OzCard class="section">
		<OzTabs v-model="activeTab" :tabs="tabs" aria-label="Ürün bilgileri">
			<template #description>
				<div class="tab-body">
					<p v-if="product.description" class="description">{{ product.description }}</p>
					<OzEmptyState v-else compact icon="file" title="Açıklama yok" description="Bu ürün için henüz açıklama eklenmemiş." />
					<ul v-if="product.features?.length" class="features">
						<li v-for="f in product.features" :key="f"><OzIcon name="check" :size="14" /> {{ f }}</li>
					</ul>
				</div>
			</template>

			<template #specs>
				<div class="tab-body">
					<dl v-if="specEntries.length" class="specs">
						<template v-for="[key, value] in specEntries" :key="key">
							<dt>{{ key }}</dt>
							<dd>{{ value }}</dd>
						</template>
					</dl>
					<OzEmptyState v-else compact icon="rows" title="Özellik bilgisi yok" />
				</div>
			</template>

			<template #reviews>
				<div class="tab-body">
					<OzEmptyState
						v-if="!product.reviewCount && !product.reviews?.length"
						compact
						icon="star"
						title="Değerlendirme yok"
						description="Bu ürün için henüz değerlendirme yapılmamış."
					/>
					<div v-else class="reviews">
						<div class="review-summary">
							<div class="big-rating">{{ product.rating.toFixed(1) }}</div>
							<div class="stars">
								<OzIcon v-for="n in 5" :key="n" name="star" :size="16" :class="n <= ratingStars ? 'star-on' : 'star-off'" />
							</div>
							<div class="review-total">{{ product.reviewCount }} değerlendirme</div>
							<div v-if="product.ratingDistribution?.length" class="dist">
								<div v-for="d in product.ratingDistribution" :key="d.star" class="dist-row">
									<span>{{ d.star }}★</span>
									<OzProgress :value="d.pct" tone="warn" />
									<span class="dist-count">{{ d.count }}</span>
								</div>
							</div>
						</div>
						<div class="review-list">
							<p v-if="!product.reviews?.length" class="muted">
								{{ product.reviewCount }} kişi puan vermiş — henüz yazılı yorum yok.
							</p>
							<article v-for="(r, i) in product.reviews" :key="i" class="review">
								<div class="review-head">
									<span class="avatar">{{ initials(r.author) }}</span>
									<div class="review-who">
										<b>{{ r.author }}</b>
										<small>{{ r.date }}</small>
									</div>
									<div class="stars">
										<OzIcon v-for="n in 5" :key="n" name="star" :size="12" :class="n <= r.rating ? 'star-on' : 'star-off'" />
									</div>
								</div>
								<div v-if="r.title" class="review-title">{{ r.title }}</div>
								<p class="review-body">{{ r.body }}</p>
							</article>
						</div>
					</div>
				</div>
			</template>
		</OzTabs>
	</OzCard>

	<OzCard v-if="similar.length" title="Benzer Ürünler" :subtitle="product.category" class="section">
		<div class="similar">
			<Link v-for="p in similar" :key="p.id" :href="`/products/${p.slug ?? p.id}`" class="sim">
				<div class="sim-media">
					<img :src="p.image || PLACEHOLDER" :alt="p.name" loading="lazy" />
					<OzPill v-if="p.stock <= 0" tone="danger" class="sim-pill">Tükendi</OzPill>
				</div>
				<div class="sim-body">
					<div class="sim-brand">{{ p.brand || '—' }}</div>
					<div class="sim-name">{{ p.name }}</div>
					<div class="sim-price">
						<span class="mono">{{ formatPrice(p.price) }}</span>
						<span v-if="p.oldPrice && p.oldPrice > p.price" class="mono strike">{{ formatPrice(p.oldPrice) }}</span>
					</div>
				</div>
			</Link>
		</div>
	</OzCard>
</template>

<style scoped>
.mono { font-family: ui-monospace, monospace; font-size: 12px; }
.strike { margin-left: 6px; color: var(--oz-muted-2); text-decoration: line-through; }
.crumb { color: var(--oz-accent); text-decoration: none; }
.crumb:hover { text-decoration: underline; }

.grid { display: grid; grid-template-columns: minmax(0, 5fr) minmax(0, 4fr); gap: 16px; align-items: start; }
.col-side { display: flex; flex-direction: column; gap: 16px; }
.section { margin-top: 16px; }
.body { padding: 0 18px 18px; }

/* Galeri */
.gallery-card { padding: 12px; }
.main-image {
	position: relative;
	aspect-ratio: 4 / 5;
	max-height: 640px;
	width: 100%;
	border-radius: var(--oz-radius-sm);
	overflow: hidden;
	background: var(--oz-surface-2);
	cursor: zoom-in;
}
.main-image img { width: 100%; height: 100%; object-fit: cover; transition: transform .15s ease-out; }
.badges { position: absolute; top: 12px; left: 12px; display: flex; flex-direction: column; align-items: flex-start; gap: 6px; }
.nav-arrow {
	position: absolute;
	top: 50%;
	transform: translateY(-50%);
	width: 34px; height: 34px;
	display: grid; place-items: center;
	border: 1px solid var(--oz-border);
	border-radius: 50%;
	background: var(--oz-surface);
	color: var(--oz-ink-soft);
	box-shadow: var(--oz-shadow-sm);
	cursor: pointer;
	opacity: 0;
	transition: opacity .2s;
}
.main-image:hover .nav-arrow, .nav-arrow:focus-visible { opacity: 1; }
.nav-arrow.is-prev { left: 10px; }
.nav-arrow.is-next { right: 10px; }
.counter {
	position: absolute; right: 12px; bottom: 12px;
	padding: 2px 8px; border-radius: 999px;
	background: rgba(15, 23, 42, .6); color: #fff;
	font-size: 11px; font-weight: 600;
}
.thumbs { display: grid; grid-template-columns: repeat(6, minmax(0, 1fr)); gap: 8px; margin-top: 10px; }
.thumb {
	aspect-ratio: 4 / 5;
	padding: 0;
	border: 2px solid transparent;
	border-radius: 8px;
	overflow: hidden;
	background: var(--oz-surface-2);
	cursor: pointer;
	transition: border-color .15s;
}
.thumb:hover { border-color: var(--oz-border-strong); }
.thumb.is-active { border-color: var(--oz-accent); }
.thumb img { width: 100%; height: 100%; object-fit: cover; }

/* Fiyat & stok */
.price-row { display: flex; align-items: baseline; gap: 10px; flex-wrap: wrap; }
.price { font-size: 30px; font-weight: 800; letter-spacing: -.02em; color: var(--oz-ink); }
.price.is-discount { color: var(--oz-danger); }
.price-old { font-size: 15px; color: var(--oz-muted-2); text-decoration: line-through; }
.price-note { font-size: 12px; color: var(--oz-muted); margin-top: 2px; }
.meta { margin-top: 14px; padding-top: 12px; border-top: 1px dashed var(--oz-border); }
.meta-row { display: flex; justify-content: space-between; align-items: center; padding: 4px 0; font-size: 13px; color: var(--oz-muted); }
.meta-row b { color: var(--oz-ink-soft); font-weight: 600; }
.rating { display: inline-flex; align-items: center; gap: 4px; }
.rating small { color: var(--oz-muted); font-weight: 500; }
.star-on { color: var(--oz-warning); fill: currentColor; }
.star-off { color: var(--oz-border-strong); }

/* Varyant seçimi */
.options { display: flex; flex-direction: column; gap: 16px; }
.option-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
.option-label { font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; color: var(--oz-muted); }
.option-value { font-size: 12.5px; font-weight: 600; color: var(--oz-ink-soft); }
.swatches { display: flex; flex-wrap: wrap; gap: 8px; }
.swatch {
	width: 30px; height: 30px;
	border-radius: 50%;
	border: 2px solid var(--oz-surface);
	box-shadow: 0 0 0 1px var(--oz-border-strong);
	cursor: pointer;
	transition: box-shadow .15s, transform .15s;
}
.swatch:hover { transform: scale(1.06); }
.swatch.is-active { box-shadow: 0 0 0 2px var(--oz-accent); }
.sizes { display: flex; flex-wrap: wrap; gap: 6px; }
.size {
	min-width: 42px; height: 34px;
	padding: 0 10px;
	border: 1px solid var(--oz-border);
	border-radius: var(--oz-radius-sm);
	background: var(--oz-surface);
	color: var(--oz-ink-soft);
	font: inherit; font-size: 12.5px; font-weight: 600;
	cursor: pointer;
	transition: border-color .12s, background .12s, color .12s;
}
.size:hover:not(:disabled) { border-color: var(--oz-border-strong); }
.size.is-active { background: var(--oz-accent); border-color: var(--oz-accent); color: #fff; }
.size:disabled { opacity: .4; text-decoration: line-through; cursor: not-allowed; }

/* Varyant tablosu */
.color-cell { display: inline-flex; align-items: center; gap: 8px; }
.dot { width: 12px; height: 12px; border-radius: 50%; box-shadow: 0 0 0 1px var(--oz-border-strong); }
.pick {
	padding: 2px 8px;
	border: 1px solid transparent;
	border-radius: 6px;
	background: none;
	color: var(--oz-ink-soft);
	font: inherit; font-weight: 600;
	cursor: pointer;
}
.pick:hover { border-color: var(--oz-border); }
.pick.is-active { background: var(--oz-accent-soft); color: var(--oz-accent); }

/* Sekmeler */
.tab-body { padding: 16px 18px 18px; font-size: 13.5px; color: var(--oz-ink-soft); line-height: 1.7; }
.description { margin: 0 0 14px; white-space: pre-line; }
.features { list-style: none; margin: 0; padding: 0; display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px 24px; }
.features li { display: flex; align-items: center; gap: 8px; font-size: 13px; }
.features li :deep(svg) { color: var(--oz-success); flex-shrink: 0; }
.specs { display: grid; grid-template-columns: minmax(140px, 220px) 1fr; margin: 0; border: 1px solid var(--oz-border); border-radius: var(--oz-radius-sm); overflow: hidden; }
.specs dt, .specs dd { margin: 0; padding: 10px 14px; font-size: 13px; border-bottom: 1px solid var(--oz-border-soft); }
.specs dt { background: var(--oz-surface-2); color: var(--oz-muted); }
.specs dd { color: var(--oz-ink-soft); font-weight: 600; }
.specs dt:nth-last-of-type(1), .specs dd:last-of-type { border-bottom: none; }

.reviews { display: grid; grid-template-columns: 240px 1fr; gap: 24px; }
.review-summary {
	display: flex; flex-direction: column; align-items: center; gap: 4px;
	padding: 18px;
	border: 1px solid var(--oz-border);
	border-radius: var(--oz-radius-sm);
	background: var(--oz-surface-2);
}
.big-rating { font-size: 40px; font-weight: 800; line-height: 1; color: var(--oz-ink); }
.stars { display: inline-flex; gap: 2px; }
.review-total { font-size: 12px; color: var(--oz-muted); margin-bottom: 8px; }
.dist { width: 100%; display: flex; flex-direction: column; gap: 6px; }
.dist-row { display: grid; grid-template-columns: 26px 1fr 28px; align-items: center; gap: 8px; font-size: 11.5px; color: var(--oz-muted); }
.dist-count { text-align: right; }
.review-list { display: flex; flex-direction: column; gap: 14px; }
.review { padding-bottom: 14px; border-bottom: 1px dashed var(--oz-border); }
.review:last-child { border-bottom: none; }
.review-head { display: flex; align-items: center; gap: 10px; margin-bottom: 6px; }
.avatar {
	width: 32px; height: 32px; border-radius: 50%;
	display: grid; place-items: center;
	background: var(--oz-accent-soft); color: var(--oz-accent);
	font-size: 11px; font-weight: 700;
}
.review-who { flex: 1; display: flex; flex-direction: column; line-height: 1.3; }
.review-who b { font-size: 13px; }
.review-who small { font-size: 11px; color: var(--oz-muted); }
.review-title { font-weight: 700; margin-bottom: 2px; }
.review-body { margin: 0; color: var(--oz-muted); }
.muted { color: var(--oz-muted); font-style: italic; margin: 0; }

/* Benzer ürünler */
.similar { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; padding: 0 18px 18px; }
.sim {
	display: flex; flex-direction: column;
	border: 1px solid var(--oz-border);
	border-radius: var(--oz-radius-sm);
	overflow: hidden;
	background: var(--oz-surface);
	text-decoration: none;
	transition: border-color .15s, box-shadow .15s, transform .15s;
}
.sim:hover { border-color: var(--oz-border-strong); box-shadow: var(--oz-shadow-sm); transform: translateY(-2px); }
.sim-media { position: relative; aspect-ratio: 4 / 5; background: var(--oz-surface-2); }
.sim-media img { width: 100%; height: 100%; object-fit: cover; }
.sim-pill { position: absolute; top: 8px; left: 8px; }
.sim-body { padding: 10px 12px 12px; }
.sim-brand { font-size: 10.5px; font-weight: 600; text-transform: uppercase; letter-spacing: .05em; color: var(--oz-muted); }
.sim-name { font-size: 13px; font-weight: 600; color: var(--oz-ink-soft); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; margin: 2px 0 4px; }
.sim-price .mono { font-size: 13px; font-weight: 700; color: var(--oz-ink); }
.sim:hover .sim-name { color: var(--oz-accent); }

@media (max-width: 1040px) {
	.similar { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 900px) {
	.grid { grid-template-columns: 1fr; }
	.reviews { grid-template-columns: 1fr; }
}
@media (max-width: 640px) {
	.features { grid-template-columns: 1fr; }
	.specs { grid-template-columns: 120px 1fr; }
	.thumbs { grid-template-columns: repeat(5, minmax(0, 1fr)); }
	.price { font-size: 26px; }
}
</style>
