<template>
	<Head :title="product.name" />
	<div class="page-product-detail">
		<Breadcrumb
			:items="[
				{ label: 'Ana Sayfa', to: '/workflow', icon: 'home' },
				{ label: 'Katalog', to: '/products' },
				{ label: product.category },
				{ label: product.name },
			]"
		/>

		<Link href="/products" class="back-link">
			<svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
				<line x1="19" y1="12" x2="5" y2="12" /><polyline points="12 19 5 12 12 5" />
			</svg>
			Kataloğa dön
		</Link>

		<!-- Üst bölüm: galeri + bilgi -->
		<section class="product-top">
			<!-- Galeri -->
			<div class="gallery">
				<div class="main-image" @mousemove="onZoomMove" @mouseleave="zoomActive = false" @mouseenter="zoomActive = true">
					<img
						:src="images[selectedImage]"
						:alt="product.name"
						:style="zoomActive ? { transformOrigin: `${zoomX}% ${zoomY}%`, transform: 'scale(1.6)' } : {}"
					/>

					<!-- Sol üst rozetler -->
					<div class="gallery-badges">
						<span v-if="discountPercent > 0" class="badge badge-discount">%{{ discountPercent }} İndirim</span>
						<span v-if="product.isNew" class="badge badge-new">Yeni</span>
						<span v-if="product.isBestSeller" class="badge badge-bestseller">Çok Satan</span>
					</div>

					<!-- Sağ üst aksiyon -->
					<div class="gallery-actions">
						<button class="round-btn" :class="{ active: isFavorite }" @click="toggleFavorite" aria-label="Favori">
							<svg width="16" height="16" :fill="isFavorite ? '#ef4444' : 'none'" :stroke="isFavorite ? '#ef4444' : 'currentColor'" stroke-width="2" viewBox="0 0 24 24">
								<path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z" />
							</svg>
						</button>
						<button class="round-btn" @click="shareProduct" aria-label="Paylaş">
							<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
								<circle cx="18" cy="5" r="3" /><circle cx="6" cy="12" r="3" /><circle cx="18" cy="19" r="3" />
								<path d="M8.59 13.51l6.83 3.98M15.41 6.51l-6.82 3.98" />
							</svg>
						</button>
					</div>

					<!-- Sol/Sağ ok -->
					<button class="nav-arrow nav-prev" @click="prevImage" aria-label="Önceki">
						<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
							<polyline points="15 18 9 12 15 6" />
						</svg>
					</button>
					<button class="nav-arrow nav-next" @click="nextImage" aria-label="Sonraki">
						<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
							<polyline points="9 18 15 12 9 6" />
						</svg>
					</button>
				</div>

				<div class="thumbs">
					<button
						v-for="(img, idx) in images"
						:key="idx"
						class="thumb"
						:class="{ active: selectedImage === idx }"
						@click="selectedImage = idx"
					>
						<img :src="img" :alt="`Görsel ${idx + 1}`" loading="lazy" />
					</button>
				</div>
			</div>

			<!-- Bilgi paneli -->
			<div class="info">
				<div class="info-brand">{{ product.brand }}</div>
				<h1 class="info-title">{{ product.name }}</h1>

				<div class="info-meta">
					<div class="rating-inline">
						<div class="stars">
							<svg
								v-for="n in 5"
								:key="n"
								width="13"
								height="13"
								:fill="n <= Math.round(product.rating) ? '#f59e0b' : 'none'"
								:stroke="n <= Math.round(product.rating) ? '#f59e0b' : '#dcdce6'"
								stroke-width="1.8"
								viewBox="0 0 24 24"
							>
								<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2" />
							</svg>
						</div>
						<strong>{{ product.rating.toFixed(1) }}</strong>
						<a href="#reviews" class="meta-link" @click.prevent="activeTab = 'reviews'; scrollToTabs()">
							{{ product.reviewCount }} değerlendirme
						</a>
					</div>
					<span class="meta-sep">·</span>
					<span class="meta-item">SKU <span class="mono">{{ product.sku }}</span></span>
					<span class="meta-sep">·</span>
					<span class="meta-item">{{ product.gender }}</span>
				</div>

				<!-- Fiyat -->
				<div class="price-block">
					<div class="price-row">
						<span v-if="displayOldPrice" class="price-old">{{ formatPrice(displayOldPrice) }}</span>
						<span class="price-new" :class="{ 'has-discount': displayOldPrice }">{{ formatPrice(displayPrice) }}</span>
						<span v-if="discountPercent > 0" class="price-discount">%{{ discountPercent }} indirim</span>
					</div>
					<div class="price-installment">
						Veya <strong>3 taksit × {{ formatPrice(displayPrice / 3) }}</strong>
					</div>
				</div>

				<div class="info-divider"></div>

				<!-- Renk -->
				<div v-if="product.colors.length" class="option-block">
					<div class="option-head">
						<span class="option-label">Renk</span>
						<span class="option-value">{{ selectedColor?.name || 'Seçim yapın' }}</span>
					</div>
					<div class="color-options">
						<button
							v-for="c in product.colors"
							:key="c.name"
							class="color-option"
							:class="{ active: selectedColor?.name === c.name }"
							:style="{ background: c.hex }"
							:title="c.name"
							@click="selectedColor = c"
						></button>
					</div>
				</div>

				<!-- Beden -->
				<div v-if="product.sizes.length" class="option-block">
					<div class="option-head">
						<span class="option-label">Beden</span>
						<a href="#" class="meta-link" @click.prevent="showToast?.({ type: 'info', title: 'Beden Tablosu', message: 'Beden tablosu yakında.' })">Beden tablosu</a>
					</div>
					<div class="size-options">
						<button
							v-for="size in product.sizes"
							:key="size"
							class="size-option"
							:class="{ active: selectedSize === size, disabled: !isSizeAvailable(size) }"
							:disabled="!isSizeAvailable(size)"
							@click="selectedSize = size"
						>{{ size }}</button>
					</div>
				</div>

				<!-- Adet + Stok -->
				<div class="qty-stock-row">
					<div class="qty-block">
						<span class="option-label">Adet</span>
						<div class="qty-stepper">
							<button :disabled="quantity <= 1" @click="quantity--" aria-label="Azalt">−</button>
							<span class="qty-value">{{ quantity }}</span>
							<button :disabled="quantity >= maxQty" @click="quantity++" aria-label="Artır">+</button>
						</div>
					</div>
					<div class="stock-block">
						<div v-if="displayStock === 0" class="stock-text stock-out">
							<span class="dot"></span> {{ selectedVariant ? 'Bu varyant tükendi' : 'Tükendi' }}
						</div>
						<div v-else-if="displayStock < 20" class="stock-text stock-low">
							<span class="dot"></span> Son {{ displayStock }} adet
						</div>
						<div v-else class="stock-text stock-ok">
							<span class="dot"></span> Stokta · {{ displayStock }} adet
						</div>
					</div>
				</div>

				<!-- Aksiyon butonları -->
				<div class="cta-row">
					<button
						class="btn btn-primary btn-lg cta-add"
						:disabled="displayStock === 0"
						@click="addToCart"
					>
						<svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
							<circle cx="9" cy="21" r="1" /><circle cx="20" cy="21" r="1" />
							<path d="M1 1h4l2.68 13.39a2 2 0 002 1.61h9.72a2 2 0 002-1.61L23 6H6" />
						</svg>
						{{ displayStock === 0 ? 'Tükendi' : 'Sepete Ekle' }}
					</button>
					<button
						class="btn btn-outline-danger btn-lg btn-icon"
						:class="{ active: isFavorite }"
						@click="toggleFavorite"
						aria-label="Favori"
					>
						<svg width="16" height="16" :fill="isFavorite ? '#ef4444' : 'none'" :stroke="isFavorite ? '#ef4444' : 'currentColor'" stroke-width="2" viewBox="0 0 24 24">
							<path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z" />
						</svg>
					</button>
				</div>

				<!-- Gönderim/iade/garanti -->
				<div class="perks">
					<div class="perk">
						<svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
							<rect x="1" y="3" width="15" height="13" /><polygon points="16 8 20 8 23 11 23 16 16 16 16 8" />
							<circle cx="5.5" cy="18.5" r="2.5" /><circle cx="18.5" cy="18.5" r="2.5" />
						</svg>
						<div>
							<div class="perk-title">Ücretsiz Kargo</div>
							<div class="perk-sub">2-3 iş günü içinde teslim</div>
						</div>
					</div>
					<div class="perk">
						<svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
							<polyline points="23 4 23 10 17 10" /><polyline points="1 20 1 14 7 14" />
							<path d="M3.51 9a9 9 0 0114.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0020.49 15" />
						</svg>
						<div>
							<div class="perk-title">14 Gün İade</div>
							<div class="perk-sub">Etiket sökülmemiş ürünlerde</div>
						</div>
					</div>
					<div class="perk">
						<svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
							<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
						</svg>
						<div>
							<div class="perk-title">2 Yıl Garanti</div>
							<div class="perk-sub">Üretim hatalarına karşı</div>
						</div>
					</div>
				</div>
			</div>
		</section>

		<!-- Tab'lar -->
		<section class="tabs-section" ref="tabsEl">
			<div class="tabs-head">
				<button
					class="tab-btn"
					:class="{ active: activeTab === 'description' }"
					@click="activeTab = 'description'"
				>Açıklama</button>
				<button
					class="tab-btn"
					:class="{ active: activeTab === 'specs' }"
					@click="activeTab = 'specs'"
				>Özellikler</button>
				<button
					class="tab-btn"
					:class="{ active: activeTab === 'reviews' }"
					@click="activeTab = 'reviews'"
				>Değerlendirmeler <span class="tab-count">{{ product.reviewCount }}</span></button>
			</div>

			<div class="tabs-body">
				<!-- Açıklama -->
				<div v-if="activeTab === 'description'" class="tab-pane">
					<p v-if="product.description" class="description-text">{{ product.description }}</p>
					<p v-else class="empty-text">Bu ürün için henüz açıklama eklenmemiş.</p>
					<ul v-if="product.features.length" class="feature-list">
						<li v-for="f in product.features" :key="f">
							<svg width="14" height="14" fill="none" stroke="#16a34a" stroke-width="2.5" viewBox="0 0 24 24">
								<polyline points="20 6 9 17 4 12" />
							</svg>
							{{ f }}
						</li>
					</ul>
				</div>

				<!-- Özellikler -->
				<div v-else-if="activeTab === 'specs'" class="tab-pane">
					<table v-if="Object.keys(product.specs).length" class="specs-table">
						<tbody>
							<tr v-for="(value, key) in product.specs" :key="key">
								<th>{{ key }}</th>
								<td>{{ value }}</td>
							</tr>
						</tbody>
					</table>
					<p v-else class="empty-text">Özellik bilgisi yok.</p>
				</div>

				<!-- Değerlendirmeler -->
				<div v-else class="tab-pane reviews-pane">
					<div v-if="product.reviewCount === 0 && !product.reviews.length" class="empty-text">
						Bu ürün için henüz değerlendirme yapılmamış.
					</div>
					<div v-else class="reviews-grid">
						<!-- Sol: özet -->
						<div class="reviews-summary">
							<div class="big-rating">{{ product.rating.toFixed(1) }}</div>
							<div class="big-stars">
								<svg
									v-for="n in 5"
									:key="n"
									width="18"
									height="18"
									:fill="n <= Math.round(product.rating) ? '#f59e0b' : 'none'"
									:stroke="n <= Math.round(product.rating) ? '#f59e0b' : '#dcdce6'"
									stroke-width="1.8"
									viewBox="0 0 24 24"
								>
									<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2" />
								</svg>
							</div>
							<div class="reviews-total">{{ product.reviewCount }} değerlendirme</div>

							<div v-if="product.ratingDistribution.length" class="rating-bars">
								<div v-for="d in product.ratingDistribution" :key="d.star" class="rating-bar-row">
									<span class="rb-label">{{ d.star }} ★</span>
									<div class="rb-bar">
										<div class="rb-fill" :style="{ width: d.pct + '%' }"></div>
									</div>
									<span class="rb-count">{{ d.count }}</span>
								</div>
							</div>
						</div>

						<!-- Sağ: yorumlar -->
						<div class="reviews-list">
							<div v-if="!product.reviews.length" class="empty-text">
								{{ product.reviewCount }} kişi puan vermiş — henüz yazılı yorum yok.
							</div>
							<article v-for="(r, i) in product.reviews" :key="i" class="review-item">
								<div class="review-head">
									<div class="review-author">
										<div class="review-avatar">{{ r.author.split(' ').map((n) => n[0]).join('') }}</div>
										<div>
											<div class="review-name">{{ r.author }}</div>
											<div class="review-date">{{ r.date }}</div>
										</div>
									</div>
									<div class="review-stars">
										<svg
											v-for="n in 5"
											:key="n"
											width="12"
											height="12"
											:fill="n <= r.rating ? '#f59e0b' : 'none'"
											:stroke="n <= r.rating ? '#f59e0b' : '#dcdce6'"
											stroke-width="1.8"
											viewBox="0 0 24 24"
										>
											<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2" />
										</svg>
									</div>
								</div>
								<div class="review-title">{{ r.title }}</div>
								<p class="review-body">{{ r.body }}</p>
							</article>
						</div>
					</div>
				</div>
			</div>
		</section>

		<!-- Benzer ürünler -->
		<section v-if="similar.length" class="similar-section">
			<div class="similar-head">
				<h2>Benzer Ürünler</h2>
				<Link href="/products" class="meta-link">Tüm {{ product.category }} ürünleri →</Link>
			</div>
			<div class="similar-grid">
				<ProductCard v-for="p in similar" :key="p.id" :product="p" />
			</div>
		</section>
	</div>
</template>

<script setup>
import { ref, computed, inject, watch } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Breadcrumb from '@/Components/Breadcrumb.vue'
import ProductCard from '@/Components/ProductCard.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
	product: { type: Object, required: true },
	similar: { type: Array, default: () => [] },
	isFavorite: { type: Boolean, default: false },
})

const showToast = inject('showToast')
const cart = inject('cart')

/* ── Galeri ── */
const images = computed(() => {
	const real = props.product.images
	if (Array.isArray(real) && real.length) return real
	// Görsel yüklenmemişse "hazırlanıyor" görseli.
	return ['/images/product-placeholder.svg']
})

const selectedImage = ref(0)
const zoomActive = ref(false)
const zoomX = ref(50)
const zoomY = ref(50)

function onZoomMove(e) {
	const r = e.currentTarget.getBoundingClientRect()
	zoomX.value = ((e.clientX - r.left) / r.width) * 100
	zoomY.value = ((e.clientY - r.top) / r.height) * 100
}

function prevImage() {
	selectedImage.value = (selectedImage.value - 1 + images.value.length) % images.value.length
}

function nextImage() {
	selectedImage.value = (selectedImage.value + 1) % images.value.length
}

/* ── Varyantlar ── */
const selectedColor = ref(props.product.colors?.[0] ?? null)
const selectedSize = ref(null)
const quantity = ref(1)

// Seçilen renk + beden kombinasyonunun varyantı
const selectedVariant = computed(() => {
	if (!props.product.variants?.length) return null
	return props.product.variants.find((v) => {
		const colorMatch = !selectedColor.value || v.color_name === selectedColor.value.name
		const sizeMatch  = !selectedSize.value  || v.size === selectedSize.value
		return colorMatch && sizeMatch
	}) ?? null
})

// Görüntülenen fiyat/stok: varyant seçildiyse onun değerleri, yoksa base
const displayPrice    = computed(() => selectedVariant.value?.price ?? props.product.price)
const displayOldPrice = computed(() => selectedVariant.value?.old_price ?? props.product.oldPrice)
const displayStock    = computed(() => selectedVariant.value?.stock ?? props.product.stock)

const maxQty = computed(() => Math.min(displayStock.value || 0, 10))

// Renk seçiliyken bu bedende stok var mı?
function isSizeAvailable(size) {
	if (!props.product.variants?.length) return true
	return props.product.variants.some((v) => {
		const colorMatch = !selectedColor.value || v.color_name === selectedColor.value.name
		return colorMatch && v.size === size && v.stock > 0
	})
}

// Renk değişince adet'i sınır içine çek
watch(displayStock, (s) => {
	if (quantity.value > s) quantity.value = Math.max(1, s)
})

/* ── Favori ── */
const isFavorite = ref(props.isFavorite)

watch(() => props.isFavorite, (v) => { isFavorite.value = v })

function toggleFavorite() {
	const willAdd = !isFavorite.value

	// Optimistic
	isFavorite.value = willAdd
	showToast?.({
		type:    willAdd ? 'success' : 'info',
		title:   willAdd ? 'Favorilere eklendi' : 'Favoriden çıkarıldı',
		message: props.product.name,
	})

	router.post(`/products/${props.product.id}/favorite`, {}, {
		preserveScroll: true,
		preserveState: true,
		only: ['isFavorite'],
		onError: () => {
			isFavorite.value = !willAdd
			showToast?.({
				type: 'error',
				title: 'Favori işlemi başarısız',
				message: props.product.name,
			})
		},
	})
}

function shareProduct() {
	const url = window.location.href
	if (navigator.clipboard?.writeText) {
		navigator.clipboard.writeText(url)
		showToast?.({ type: 'success', title: 'Bağlantı Kopyalandı', message: url })
	}
}

/* ── Fiyat ── */
const discountPercent = computed(() => {
	const price = displayPrice.value
	const old   = displayOldPrice.value
	if (!old || old <= price) return 0
	return Math.round(((old - price) / old) * 100)
})

function formatPrice(value) {
	return '₺' + Number(value || 0).toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

/* ── Sepete ekle ── */
function addToCart() {
	if (displayStock.value === 0) return
	if (props.product.sizes.length && !selectedSize.value) {
		showToast?.({ type: 'warning', title: 'Beden seçin', message: 'Lütfen bir beden seçiniz.' })
		return
	}
	if (props.product.colors.length && !selectedColor.value) {
		showToast?.({ type: 'warning', title: 'Renk seçin', message: 'Lütfen bir renk seçiniz.' })
		return
	}
	cart?.add({
		product: props.product,
		variant: selectedVariant.value,
		color: selectedColor.value,
		size: selectedSize.value,
		qty: quantity.value,
	})
}

/* ── Tab'lar ── */
const activeTab = ref('description')
const tabsEl = ref(null)

function scrollToTabs() {
	tabsEl.value?.scrollIntoView({ behavior: 'smooth', block: 'start' })
}
</script>

<style scoped>
.back-link {
	display: inline-flex;
	align-items: center;
	gap: 5px;
	color: #888;
	font-size: 12.5px;
	font-weight: 500;
	text-decoration: none;
	margin: 4px 0 14px;
	padding: 5px 10px;
	border-radius: 8px;
	transition: background .12s, color .12s;
}
.back-link:hover { background: #fafafe; color: rgb(var(--color-primary)); }

/* ── Üst bölüm ── */
.product-top {
	display: grid;
	grid-template-columns: minmax(320px, 1fr) 1fr;
	gap: 32px;
	margin-bottom: 24px;
}

@media (max-width: 900px) {
	.product-top { grid-template-columns: 1fr; gap: 20px; }
}

/* ── Galeri ── */
.gallery {
	display: flex;
	flex-direction: column;
	gap: 10px;
	position: sticky;
	top: 12px;
	align-self: flex-start;
}

.main-image {
	position: relative;
	aspect-ratio: 4 / 5;
	background: linear-gradient(135deg, #f5f5fa, #ebebf5);
	border-radius: 16px;
	overflow: hidden;
	cursor: zoom-in;
}

.main-image img {
	width: 100%;
	height: 100%;
	object-fit: cover;
	transition: transform .15s ease-out;
}

.gallery-badges {
	position: absolute;
	top: 14px; left: 14px;
	display: flex; flex-direction: column; gap: 6px;
	z-index: 2;
}

.badge {
	display: inline-block;
	padding: 4px 9px;
	border-radius: 6px;
	font-size: 10.5px;
	font-weight: 700;
	letter-spacing: 0.02em;
	text-transform: uppercase;
}
.badge-discount   { background: #dc2626; color: #fff; }
.badge-new        { background: #1a1a2e; color: #fff; }
.badge-bestseller { background: #f59e0b; color: #fff; }

.gallery-actions {
	position: absolute;
	top: 14px; right: 14px;
	display: flex; flex-direction: column; gap: 8px;
	z-index: 2;
}

.round-btn {
	width: 36px; height: 36px;
	border-radius: 50%;
	background: rgba(255, 255, 255, 0.95);
	border: none;
	cursor: pointer;
	display: flex; align-items: center; justify-content: center;
	color: #6b7280;
	box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
	transition: transform .15s, color .15s;
}
.round-btn:hover { transform: scale(1.08); }
.round-btn.active { color: #ef4444; }

/* Nav arrows */
.nav-arrow {
	position: absolute;
	top: 50%; transform: translateY(-50%);
	width: 36px; height: 36px;
	border-radius: 50%;
	background: rgba(255, 255, 255, 0.85);
	border: none;
	cursor: pointer;
	display: flex; align-items: center; justify-content: center;
	color: #1a1a2e;
	opacity: 0;
	transition: opacity .2s, background .15s;
	z-index: 2;
}
.main-image:hover .nav-arrow { opacity: 1; }
.nav-arrow:hover { background: #fff; }
.nav-prev { left: 12px; }
.nav-next { right: 12px; }

/* Thumbs */
.thumbs {
	display: grid;
	grid-template-columns: repeat(5, 1fr);
	gap: 8px;
}

.thumb {
	aspect-ratio: 4 / 5;
	border: 2px solid transparent;
	border-radius: 10px;
	overflow: hidden;
	background: #f5f5fa;
	cursor: pointer;
	padding: 0;
	transition: border-color .15s, transform .15s;
}
.thumb:hover { transform: translateY(-2px); }
.thumb.active { border-color: #1a1a2e; }
.thumb img { width: 100%; height: 100%; object-fit: cover; }

/* ── Info paneli ── */
.info {
	display: flex;
	flex-direction: column;
	gap: 12px;
}

.info-brand {
	font-size: 11.5px;
	font-weight: 700;
	color: #888;
	text-transform: uppercase;
	letter-spacing: 0.1em;
}

.info-title {
	font-size: 26px;
	font-weight: 700;
	color: #1a1a2e;
	line-height: 1.2;
	margin: 0;
}

.info-meta {
	display: flex;
	align-items: center;
	gap: 10px;
	font-size: 12.5px;
	color: #666;
	flex-wrap: wrap;
}

.rating-inline {
	display: flex;
	align-items: center;
	gap: 6px;
}

.rating-inline strong {
	font-size: 13.5px;
	color: #1a1a2e;
	font-weight: 700;
}

.stars { display: flex; gap: 1px; }

.meta-link {
	color: rgb(var(--color-primary));
	text-decoration: none;
	font-weight: 500;
}
.meta-link:hover { text-decoration: underline; }

.meta-sep { color: #ddd; }
.meta-item { color: #666; }
.mono { font-family: 'SF Mono', Menlo, Consolas, monospace; font-size: 12px; }

/* Price */
.price-block {
	display: flex;
	flex-direction: column;
	gap: 4px;
	margin-top: 4px;
}

.price-row {
	display: flex;
	align-items: baseline;
	gap: 10px;
	flex-wrap: wrap;
}

.price-old {
	font-size: 16px;
	color: #aaa;
	text-decoration: line-through;
	font-weight: 500;
}

.price-new {
	font-size: 32px;
	font-weight: 800;
	color: #1a1a2e;
	letter-spacing: -0.02em;
}
.price-new.has-discount { color: #dc2626; }

.price-discount {
	display: inline-block;
	padding: 3px 10px;
	background: #fee2e2;
	color: #dc2626;
	border-radius: 6px;
	font-size: 11.5px;
	font-weight: 700;
	letter-spacing: 0.02em;
}

.price-installment { font-size: 12.5px; color: #888; }
.price-installment strong { color: #1a1a2e; font-weight: 700; }

.info-divider {
	height: 1px;
	background: #f0f0f5;
	margin: 6px 0;
}

/* Options */
.option-block { display: flex; flex-direction: column; gap: 8px; }

.option-head {
	display: flex;
	align-items: center;
	justify-content: space-between;
}

.option-label {
	font-size: 11.5px;
	font-weight: 700;
	color: #1a1a2e;
	text-transform: uppercase;
	letter-spacing: 0.06em;
}

.option-value { font-size: 12px; color: #888; }

/* Renk */
.color-options {
	display: flex;
	gap: 8px;
	flex-wrap: wrap;
}

.color-option {
	width: 34px; height: 34px;
	border-radius: 50%;
	border: 2px solid #fff;
	box-shadow: 0 0 0 1.5px #e5e7eb;
	cursor: pointer;
	transition: box-shadow .15s, transform .15s;
}
.color-option:hover { transform: scale(1.06); }
.color-option.active {
	box-shadow: 0 0 0 2.5px #1a1a2e;
	transform: scale(1.06);
}

/* Beden */
.size-options {
	display: flex;
	gap: 6px;
	flex-wrap: wrap;
}

.size-option {
	min-width: 44px;
	height: 40px;
	padding: 0 12px;
	background: #fff;
	border: 1.5px solid #e8e8f0;
	border-radius: 9px;
	color: #1a1a2e;
	font-family: inherit;
	font-size: 13px;
	font-weight: 600;
	cursor: pointer;
	transition: all .12s;
}
.size-option:hover { border-color: #c0c0d8; }
.size-option.active {
	background: #1a1a2e;
	color: #fff;
	border-color: #1a1a2e;
}
.size-option.disabled,
.size-option:disabled {
	opacity: 0.4;
	cursor: not-allowed;
	text-decoration: line-through;
}
.size-option.disabled:hover,
.size-option:disabled:hover { border-color: #e8e8f0; }

.empty-text {
	color: #888;
	font-size: 13px;
	font-style: italic;
	padding: 12px 0;
}

/* Qty + stok */
.qty-stock-row {
	display: flex;
	gap: 20px;
	align-items: flex-end;
	flex-wrap: wrap;
}

.qty-block { display: flex; flex-direction: column; gap: 8px; }

.qty-stepper {
	display: inline-flex;
	align-items: center;
	background: #fff;
	border: 1.5px solid #e8e8f0;
	border-radius: 9px;
	overflow: hidden;
}

.qty-stepper button {
	width: 36px; height: 40px;
	border: none;
	background: none;
	font-size: 16px;
	font-weight: 600;
	color: #444;
	cursor: pointer;
	transition: background .12s;
}
.qty-stepper button:hover:not(:disabled) { background: #f5f5f8; }
.qty-stepper button:disabled { color: #ccc; cursor: not-allowed; }

.qty-value {
	min-width: 36px;
	text-align: center;
	font-size: 14px;
	font-weight: 700;
	color: #1a1a2e;
	user-select: none;
}

.stock-block { flex: 1; }

.stock-text {
	display: inline-flex;
	align-items: center;
	gap: 6px;
	font-size: 12.5px;
	font-weight: 600;
	padding: 8px 12px;
	border-radius: 8px;
}
.stock-text .dot {
	width: 7px; height: 7px;
	border-radius: 50%;
	background: currentColor;
}
.stock-ok  { background: #f0fdf4; color: #16a34a; }
.stock-low { background: #fffbeb; color: #ca8a04; }
.stock-out { background: #fef2f2; color: #dc2626; }

/* CTA */
.cta-row {
	display: flex;
	gap: 10px;
	margin-top: 6px;
}

.cta-add {
	flex: 1;
	justify-content: center;
	font-size: 14px;
}

.btn-outline-danger.active {
	background: #fef2f2;
	border-color: #fecaca;
}

/* Perks */
.perks {
	display: grid;
	grid-template-columns: repeat(3, 1fr);
	gap: 10px;
	margin-top: 12px;
	padding: 14px;
	background: #fafafe;
	border-radius: 12px;
	border: 1px solid #f0f0f5;
}

.perk {
	display: flex;
	align-items: flex-start;
	gap: 10px;
}
.perk svg { color: rgb(var(--color-primary)); flex-shrink: 0; margin-top: 2px; }

.perk-title {
	font-size: 12px;
	font-weight: 700;
	color: #1a1a2e;
	margin-bottom: 1px;
}
.perk-sub { font-size: 11px; color: #888; }

@media (max-width: 700px) {
	.perks { grid-template-columns: 1fr; }
	.info-title { font-size: 22px; }
	.price-new { font-size: 26px; }
}

/* ── Tab'lar ── */
.tabs-section {
	background: #fff;
	border: 1px solid #ebebf0;
	border-radius: 16px;
	overflow: hidden;
	margin-bottom: 24px;
	box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
}

.tabs-head {
	display: flex;
	border-bottom: 1px solid #f0f0f5;
	background: #fafafe;
	padding: 0 8px;
}

.tab-btn {
	flex: 0 0 auto;
	padding: 14px 22px;
	background: none;
	border: none;
	border-bottom: 2px solid transparent;
	font-family: inherit;
	font-size: 13px;
	font-weight: 600;
	color: #888;
	cursor: pointer;
	transition: color .15s, border-color .15s;
}
.tab-btn:hover { color: #1a1a2e; }
.tab-btn.active {
	color: #1a1a2e;
	border-bottom-color: #1a1a2e;
}

.tab-count {
	margin-left: 4px;
	background: #f0f0f5;
	padding: 1px 7px;
	border-radius: 999px;
	font-size: 10.5px;
	font-weight: 700;
	color: #666;
}
.tab-btn.active .tab-count { background: rgb(var(--color-primary-soft)); color: rgb(var(--color-primary)); }

.tabs-body { padding: 22px; }

.tab-pane { font-size: 13.5px; color: #444; line-height: 1.7; }

.description-text { margin-bottom: 16px; }

.feature-list {
	list-style: none;
	padding: 0;
	margin: 0;
	display: grid;
	grid-template-columns: 1fr 1fr;
	gap: 10px 24px;
}
.feature-list li {
	display: flex;
	align-items: center;
	gap: 8px;
	font-size: 13px;
	color: #444;
}

/* Specs table */
.specs-table {
	width: 100%;
	border-collapse: separate;
	border-spacing: 0;
}
.specs-table th, .specs-table td {
	padding: 12px 14px;
	font-size: 13px;
	text-align: left;
	border-bottom: 1px solid #f5f5f8;
}
.specs-table th {
	width: 200px;
	color: #888;
	font-weight: 500;
	background: #fafafe;
}
.specs-table td {
	color: #1a1a2e;
	font-weight: 600;
}
.specs-table tr:last-child th, .specs-table tr:last-child td { border-bottom: none; }

/* Reviews */
.reviews-grid {
	display: grid;
	grid-template-columns: 260px 1fr;
	gap: 30px;
}

@media (max-width: 800px) {
	.reviews-grid { grid-template-columns: 1fr; }
	.feature-list { grid-template-columns: 1fr; }
	.specs-table th { width: 130px; }
}

.reviews-summary {
	background: #fafafe;
	border: 1px solid #f0f0f5;
	border-radius: 12px;
	padding: 20px;
	display: flex;
	flex-direction: column;
	align-items: center;
	gap: 4px;
}

.big-rating {
	font-size: 44px;
	font-weight: 800;
	color: #1a1a2e;
	line-height: 1;
}

.big-stars { display: flex; gap: 2px; margin-top: 4px; }

.reviews-total { font-size: 12px; color: #888; margin: 4px 0 14px; }

.rating-bars {
	display: flex;
	flex-direction: column;
	gap: 6px;
	width: 100%;
}

.rating-bar-row {
	display: flex;
	align-items: center;
	gap: 8px;
	font-size: 11.5px;
}

.rb-label {
	width: 26px;
	font-weight: 600;
	color: #666;
}

.rb-bar {
	flex: 1;
	height: 6px;
	background: #f0f0f5;
	border-radius: 999px;
	overflow: hidden;
}

.rb-fill {
	height: 100%;
	background: linear-gradient(90deg, #f59e0b, #f97316);
	border-radius: 999px;
}

.rb-count {
	width: 30px;
	text-align: right;
	font-size: 11px;
	color: #888;
}

.reviews-list {
	display: flex;
	flex-direction: column;
	gap: 16px;
}

.review-item {
	padding-bottom: 16px;
	border-bottom: 1px solid #f0f0f5;
}
.review-item:last-of-type { border-bottom: none; }

.review-head {
	display: flex;
	justify-content: space-between;
	align-items: flex-start;
	gap: 12px;
	margin-bottom: 6px;
}

.review-author {
	display: flex;
	align-items: center;
	gap: 10px;
}

.review-avatar {
	width: 34px; height: 34px;
	border-radius: 50%;
	background: linear-gradient(135deg, rgb(var(--color-primary)), rgb(var(--color-primary-hover)));
	color: #fff;
	display: flex; align-items: center; justify-content: center;
	font-size: 11px;
	font-weight: 700;
}

.review-name { font-size: 13px; font-weight: 600; color: #1a1a2e; }
.review-date { font-size: 11px; color: #888; }

.review-stars { display: flex; gap: 1px; }

.review-title {
	font-size: 13.5px;
	font-weight: 700;
	color: #1a1a2e;
	margin-bottom: 4px;
}

.review-body {
	font-size: 13px;
	color: #555;
	line-height: 1.6;
	margin: 0;
}

/* Similar */
.similar-section { margin-top: 8px; }

.similar-head {
	display: flex;
	align-items: center;
	justify-content: space-between;
	margin-bottom: 14px;
}

.similar-head h2 {
	font-size: 18px;
	font-weight: 700;
	color: #1a1a2e;
}

.similar-grid {
	display: grid;
	grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
	gap: 16px;
}
</style>
