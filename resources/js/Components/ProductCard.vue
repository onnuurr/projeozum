<template>
	<Link
		:href="`/products/${product.slug ?? product.id}`"
		class="product-card"
		:class="{ 'out-of-stock': !inStock }"
	>
		<!-- Görsel alan -->
		<div class="product-media">
			<img
				:src="imageUrl"
				:alt="product.name"
				class="product-image"
				loading="lazy"
			/>

			<!-- Sol üst rozetler -->
			<div class="badges">
				<span v-if="discountPercent > 0" class="badge badge-discount">%{{ discountPercent }} İndirim</span>
				<span v-else-if="product.isNew" class="badge badge-new">Yeni</span>
				<span v-if="product.isBestSeller" class="badge badge-bestseller">Çok Satan</span>
			</div>

			<!-- Sağ üst aksiyonlar -->
			<div class="actions-stack">
				<button
					class="round-action favorite-btn"
					:class="{ active: isFavorite }"
					:aria-pressed="isFavorite"
					:aria-label="isFavorite ? 'Favorilerden çıkar' : 'Favorilere ekle'"
					@click.stop.prevent="$emit('toggle-favorite', product)"
				>
					<svg width="15" height="15" :fill="isFavorite ? '#ef4444' : 'none'" :stroke="isFavorite ? '#ef4444' : 'currentColor'" stroke-width="2" viewBox="0 0 24 24">
						<path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z" />
					</svg>
				</button>

				<button
					v-if="canEdit"
					class="round-action edit-btn admin-action"
					aria-label="Düzenle"
					@click.stop.prevent="$emit('edit', product)"
				>
					<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
						<path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7" />
						<path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z" />
					</svg>
				</button>

				<button
					v-if="canDelete"
					class="round-action delete-btn admin-action"
					aria-label="Sil"
					@click.stop.prevent="$emit('delete', product)"
				>
					<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
						<polyline points="3 6 5 6 21 6" />
						<path d="M19 6l-2 14a2 2 0 01-2 2H9a2 2 0 01-2-2L5 6" />
						<path d="M10 11v6M14 11v6" />
						<path d="M9 6V4a2 2 0 012-2h2a2 2 0 012 2v2" />
					</svg>
				</button>

				<button
					v-if="canManageAccess"
					class="round-action tenant-btn admin-action"
					aria-label="Tenant Ayarları"
					@click.stop.prevent="$emit('tenant-access', product)"
				>
					<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
						<path d="M3 21h18M5 21V7l8-4v18M19 21V11l-6-4" />
						<path d="M9 9h.01M9 13h.01M9 17h.01" />
					</svg>
				</button>
			</div>

			<!-- Stok dışı overlay -->
			<div v-if="!inStock" class="oos-overlay">
				<span>Tükendi</span>
			</div>

			<!-- Hover'da çıkan hızlı aksiyon -->
			<button
				class="quick-add"
				:disabled="!inStock"
				@click.stop="$emit('add-to-cart', product)"
			>
				<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
					<circle cx="9" cy="21" r="1" />
					<circle cx="20" cy="21" r="1" />
					<path d="M1 1h4l2.68 13.39a2 2 0 002 1.61h9.72a2 2 0 002-1.61L23 6H6" />
				</svg>
				{{ inStock ? 'Sepete Ekle' : 'Tükendi' }}
			</button>
		</div>

		<!-- İçerik -->
		<div class="product-body">
			<div class="product-brand">{{ product.brandLabel ?? product.brand ?? '' }}</div>
			<h3 class="product-name">{{ product.name }}</h3>

			<div class="product-rating">
				<div class="stars">
					<svg
						v-for="n in 5"
						:key="n"
						width="11"
						height="11"
						:fill="n <= Math.round(product.rating) ? '#f59e0b' : 'none'"
						:stroke="n <= Math.round(product.rating) ? '#f59e0b' : '#dcdce6'"
						stroke-width="1.8"
						viewBox="0 0 24 24"
					>
						<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2" />
					</svg>
				</div>
				<span class="rating-num">{{ product.rating.toFixed(1) }}</span>
				<span class="review-count">({{ product.reviewCount }})</span>
			</div>

			<!-- Renkler -->
			<div class="color-swatches" v-if="product.colors?.length">
				<span
					v-for="(c, i) in product.colors.slice(0, 4)"
					:key="i"
					class="swatch"
					:style="{ background: colorHex(c) }"
					:title="colorName(c)"
				></span>
				<span v-if="product.colors.length > 4" class="swatch-more">+{{ product.colors.length - 4 }}</span>
			</div>

			<!-- Fiyat -->
			<div class="product-price">
				<span v-if="product.oldPrice" class="old-price">{{ formatPrice(product.oldPrice) }}</span>
				<span class="new-price" :class="{ 'has-discount': product.oldPrice }">
					{{ formatPrice(product.price) }}
				</span>
			</div>

			<!-- Düşük stok uyarısı -->
			<div v-if="inStock && product.stock < 20" class="low-stock">
				<svg width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
					<path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
					<line x1="12" y1="9" x2="12" y2="13" />
				</svg>
				Son {{ product.stock }} adet
			</div>
			<div v-else-if="product.freeShipping" class="free-shipping">
				<svg width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
					<rect x="1" y="3" width="15" height="13" />
					<polygon points="16 8 20 8 23 11 23 16 16 16 16 8" />
					<circle cx="5.5" cy="18.5" r="2.5" />
					<circle cx="18.5" cy="18.5" r="2.5" />
				</svg>
				Ücretsiz Kargo
			</div>
		</div>
	</Link>
</template>

<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'

const props = defineProps({
	product: { type: Object, required: true },
	isFavorite: { type: Boolean, default: false },
	canEdit: { type: Boolean, default: false },
	canDelete: { type: Boolean, default: false },
	canManageAccess: { type: Boolean, default: false },
})

defineEmits(['toggle-favorite', 'add-to-cart', 'edit', 'delete', 'tenant-access'])

const inStock = computed(() => props.product.stock > 0)

// Renk hem string (legacy) hem {name,hex} obje gelebilir — ikisini de tolere et
function colorHex(c) {
	return typeof c === 'string' ? c : c?.hex || '#ccc'
}
function colorName(c) {
	return typeof c === 'string' ? c : c?.name || ''
}

const discountPercent = computed(() => {
	const { price, oldPrice } = props.product
	if (!oldPrice || oldPrice <= price) return 0
	return Math.round(((oldPrice - price) / oldPrice) * 100)
})

const imageUrl = computed(() => `https://picsum.photos/seed/tek-p${props.product.id}/600/750`)

function formatPrice(value) {
	return '₺' + value.toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}
</script>

<style scoped>
.product-card {
	background: #fff;
	border: 1px solid #ebebf0;
	border-radius: 14px;
	overflow: hidden;
	display: flex;
	flex-direction: column;
	transition: transform .25s ease, box-shadow .25s ease, border-color .15s;
	cursor: pointer;
	position: relative;
	text-decoration: none;
	color: inherit;
}

.product-card:hover {
	transform: translateY(-4px);
	box-shadow: 0 16px 32px rgba(0, 0, 0, 0.08);
	border-color: #d8d8e8;
}

/* ── Media ── */
.product-media {
	position: relative;
	aspect-ratio: 4 / 5;
	overflow: hidden;
	background: linear-gradient(135deg, #f5f5fa, #ebebf5);
}

.product-image {
	width: 100%;
	height: 100%;
	object-fit: cover;
	transition: transform .55s ease;
}

.product-card:hover .product-image {
	transform: scale(1.06);
}

/* ── Rozetler ── */
.badges {
	position: absolute;
	top: 10px;
	left: 10px;
	display: flex;
	flex-direction: column;
	gap: 5px;
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
	backdrop-filter: blur(8px);
}

.badge-discount   { background: #dc2626; color: #fff; }
.badge-new        { background: #1a1a2e; color: #fff; }
.badge-bestseller { background: #f59e0b; color: #fff; }

/* ── Sağ üst aksiyon kümesi ── */
.actions-stack {
	position: absolute;
	top: 10px;
	right: 10px;
	display: flex;
	flex-direction: column;
	gap: 6px;
	z-index: 2;
}

.round-action {
	width: 32px;
	height: 32px;
	border-radius: 50%;
	background: rgba(255, 255, 255, 0.95);
	border: none;
	cursor: pointer;
	display: flex;
	align-items: center;
	justify-content: center;
	color: #6b7280;
	box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
	transition: transform .15s, background .15s, color .15s;
	padding: 0;
}
.round-action:hover { transform: scale(1.1); background: #fff; }

.favorite-btn.active { color: #ef4444; }
.edit-btn:hover   { color: #4a6cf7; }
.delete-btn:hover { color: #dc2626; }
.tenant-btn:hover { color: #7c3aed; }

/* Admin butonları sadece kart hover'unda görünür */
.admin-action {
	opacity: 0;
	transform: translateX(8px);
	transition: opacity .18s, transform .18s, background .15s, color .15s;
}
.product-card:hover .admin-action {
	opacity: 1;
	transform: translateX(0);
}

/* ── Stok dışı overlay ── */
.oos-overlay {
	position: absolute;
	inset: 0;
	background: rgba(255, 255, 255, 0.7);
	display: flex;
	align-items: center;
	justify-content: center;
	z-index: 3;
}
.oos-overlay span {
	background: #1a1a2e;
	color: #fff;
	padding: 8px 22px;
	border-radius: 999px;
	font-size: 12px;
	font-weight: 700;
	letter-spacing: 0.04em;
	text-transform: uppercase;
}

.product-card.out-of-stock .product-image { filter: grayscale(60%); opacity: .7; }

/* ── Hover hızlı aksiyon ── */
.quick-add {
	position: absolute;
	left: 12px;
	right: 12px;
	bottom: 12px;
	height: 36px;
	background: #1a1a2e;
	color: #fff;
	border: none;
	border-radius: 9px;
	font-family: inherit;
	font-size: 12.5px;
	font-weight: 600;
	cursor: pointer;
	display: flex;
	align-items: center;
	justify-content: center;
	gap: 6px;
	opacity: 0;
	transform: translateY(8px);
	transition: opacity .2s, transform .2s, background .15s;
	z-index: 2;
}

.product-card:hover .quick-add {
	opacity: 1;
	transform: translateY(0);
}

.quick-add:hover { background: #2a2a4e; }
.quick-add:disabled { background: #9ca3af; cursor: not-allowed; }

/* ── Body ── */
.product-body {
	padding: 14px 14px 16px;
	display: flex;
	flex-direction: column;
	gap: 6px;
	flex: 1;
}

.product-brand {
	font-size: 10.5px;
	font-weight: 700;
	color: #888;
	text-transform: uppercase;
	letter-spacing: 0.08em;
}

.product-name {
	font-size: 13.5px;
	font-weight: 600;
	color: #1a1a2e;
	margin: 0;
	line-height: 1.4;
	display: -webkit-box;
	-webkit-line-clamp: 2;
	-webkit-box-orient: vertical;
	overflow: hidden;
	min-height: 38px;
}

.product-rating {
	display: flex;
	align-items: center;
	gap: 5px;
	margin-top: 2px;
}

.stars { display: flex; gap: 1px; }

.rating-num {
	font-size: 11.5px;
	font-weight: 600;
	color: #1a1a2e;
}

.review-count {
	font-size: 11px;
	color: #999;
}

/* ── Renk swatch ── */
.color-swatches {
	display: flex;
	align-items: center;
	gap: 4px;
	margin-top: 2px;
}

.swatch {
	width: 14px;
	height: 14px;
	border-radius: 50%;
	border: 1.5px solid #fff;
	box-shadow: 0 0 0 1px #e5e7eb;
}

.swatch-more {
	font-size: 10.5px;
	color: #888;
	margin-left: 2px;
	font-weight: 500;
}

/* ── Fiyat ── */
.product-price {
	display: flex;
	align-items: baseline;
	gap: 8px;
	margin-top: 4px;
}

.old-price {
	font-size: 12px;
	color: #aaa;
	text-decoration: line-through;
	font-weight: 500;
}

.new-price {
	font-size: 17px;
	font-weight: 800;
	color: #1a1a2e;
}

.new-price.has-discount {
	color: #dc2626;
}

/* ── Stok / kargo uyarısı ── */
.low-stock,
.free-shipping {
	display: inline-flex;
	align-items: center;
	gap: 4px;
	font-size: 10.5px;
	font-weight: 600;
	margin-top: 2px;
}

.low-stock { color: #dc2626; }
.free-shipping { color: #16a34a; }
</style>
