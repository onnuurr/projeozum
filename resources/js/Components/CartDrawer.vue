<template>
	<Teleport to="body">
		<div
			class="drawer-overlay"
			:class="{ open: modelValue }"
			@click="close"
		></div>
		<aside
			class="drawer cart-drawer"
			:class="{ open: modelValue }"
			role="dialog"
			aria-modal="true"
			aria-label="Sepetim"
		>
			<div class="drawer-header">
				<div class="drawer-title">
					<div class="drawer-title-icon">
						<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
							<circle cx="9" cy="21" r="1" /><circle cx="20" cy="21" r="1" />
							<path d="M1 1h4l2.68 13.39a2 2 0 002 1.61h9.72a2 2 0 002-1.61L23 6H6" />
						</svg>
					</div>
					<div>
						<h4>Sepetim</h4>
						<p v-if="items.length">{{ totalItems }} ürün</p>
						<p v-else>Henüz ürün yok</p>
					</div>
				</div>
				<button class="drawer-close" @click="close" aria-label="Kapat">
					<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
						<path d="M18 6L6 18M6 6l12 12" />
					</svg>
				</button>
			</div>

			<!-- Boş durum -->
			<div v-if="items.length === 0" class="empty-cart">
				<div class="empty-icon">
					<svg width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
						<circle cx="9" cy="21" r="1" /><circle cx="20" cy="21" r="1" />
						<path d="M1 1h4l2.68 13.39a2 2 0 002 1.61h9.72a2 2 0 002-1.61L23 6H6" />
					</svg>
				</div>
				<h3>Sepetiniz boş</h3>
				<p>Beğendiğiniz ürünleri sepete ekleyerek alışverişe başlayabilirsiniz.</p>
				<Link href="/products" class="btn btn-primary btn-with-icon" @click="close">
					<svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
						<rect x="3" y="3" width="18" height="18" rx="2" /><path d="M3 9h18M9 21V9" />
					</svg>
					Kataloğa Göz At
				</Link>
			</div>

			<!-- Sepet ürünleri -->
			<template v-else>
				<div class="cart-progress" v-if="freeShippingTarget > 0">
					<div class="cart-progress-text">
						<template v-if="subtotal >= freeShippingTarget">
							<svg width="14" height="14" fill="#16a34a" viewBox="0 0 24 24">
								<path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" stroke="#fff" stroke-width="2" fill="#16a34a" />
							</svg>
							Tebrikler! <strong>Ücretsiz kargo</strong> kazandın.
						</template>
						<template v-else>
							<svg width="14" height="14" fill="none" stroke="rgb(var(--color-primary))" stroke-width="2" viewBox="0 0 24 24">
								<rect x="1" y="3" width="15" height="13" /><polygon points="16 8 20 8 23 11 23 16 16 16 16 8" />
								<circle cx="5.5" cy="18.5" r="2.5" /><circle cx="18.5" cy="18.5" r="2.5" />
							</svg>
							Ücretsiz kargo için <strong>{{ formatPrice(freeShippingTarget - subtotal) }}</strong> daha ekleyin
						</template>
					</div>
					<div class="cart-progress-bar">
						<div class="cart-progress-fill" :style="{ width: progressPct + '%' }"></div>
					</div>
				</div>

				<div class="cart-body">
					<TransitionGroup name="cart-item" tag="div" class="cart-items">
						<article v-for="item in items" :key="item.key" class="cart-item">
							<Link :href="`/products/${item.productSlug ?? item.productId}`" class="cart-item-image" @click="close">
								<img :src="item.image" :alt="item.name" loading="lazy" />
							</Link>
							<div class="cart-item-body">
								<div class="cart-item-brand">{{ item.brand }}</div>
								<Link :href="`/products/${item.productSlug ?? item.productId}`" class="cart-item-name" @click="close">
									{{ item.name }}
								</Link>
								<div class="cart-item-meta">
									<span v-if="item.color" class="cart-color" :style="{ background: item.color }" :title="item.color"></span>
									<span v-if="item.size">Beden: <strong>{{ item.size }}</strong></span>
								</div>
								<div class="cart-item-foot">
									<div class="qty-stepper">
										<button :disabled="item.qty <= 1" @click="$emit('update-qty', item.key, item.qty - 1)" aria-label="Azalt">−</button>
										<span class="qty-value">{{ item.qty }}</span>
										<button
											:disabled="item.maxQty != null && item.qty >= item.maxQty"
											:title="item.maxQty != null && item.qty >= item.maxQty ? `En fazla ${item.maxQty} adet (stok sınırı)` : ''"
											@click="$emit('update-qty', item.key, item.qty + 1)"
											aria-label="Artır"
										>+</button>
									</div>
									<button class="remove-btn" @click="$emit('remove', item.key)" aria-label="Kaldır">
										<svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
											<polyline points="3 6 5 6 21 6" />
											<path d="M19 6l-1 14H6L5 6" />
											<path d="M10 11v6M14 11v6" />
											<path d="M9 6V4a1 1 0 011-1h4a1 1 0 011 1v2" />
										</svg>
									</button>
								</div>
							</div>
							<div class="cart-item-price">
								<div class="ci-total">{{ formatPrice(item.price * item.qty) }}</div>
								<div v-if="item.qty > 1" class="ci-unit">{{ formatPrice(item.price) }} / adet</div>
							</div>
						</article>
					</TransitionGroup>
				</div>

				<div class="cart-footer">
					<div class="sum-row">
						<span>Ara Toplam</span>
						<strong>{{ formatPrice(subtotal) }}</strong>
					</div>
					<div class="sum-row">
						<span>Kargo</span>
						<strong v-if="subtotal >= freeShippingTarget" class="text-success">Ücretsiz</strong>
						<strong v-else>{{ formatPrice(shippingFee) }}</strong>
					</div>
					<div class="sum-row sum-total">
						<span>Toplam</span>
						<strong>{{ formatPrice(totalWithShipping) }}</strong>
					</div>

					<div class="cart-actions">
						<button class="btn btn-secondary" @click="continueShopping">Alışverişe Devam</button>
						<button class="btn btn-primary btn-with-icon checkout-btn" @click="checkout">
							Ödemeye Geç
							<svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
								<line x1="5" y1="12" x2="19" y2="12" />
								<polyline points="12 5 19 12 12 19" />
							</svg>
						</button>
					</div>

					<button v-if="items.length > 1" class="clear-cart-btn" @click="$emit('clear')">
						Sepeti boşalt
					</button>
				</div>
			</template>
		</aside>
	</Teleport>
</template>

<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'

const props = defineProps({
	modelValue: { type: Boolean, default: false },
	items: { type: Array, default: () => [] },
})

const emit = defineEmits(['update:modelValue', 'update-qty', 'remove', 'clear', 'checkout'])

const freeShippingTarget = 500
const shippingFee = 49.90

const totalItems = computed(() => props.items.reduce((acc, i) => acc + i.qty, 0))
const subtotal = computed(() => props.items.reduce((acc, i) => acc + i.price * i.qty, 0))
const totalWithShipping = computed(() =>
	subtotal.value >= freeShippingTarget ? subtotal.value : subtotal.value + shippingFee
)
const progressPct = computed(() => Math.min(100, (subtotal.value / freeShippingTarget) * 100))

function formatPrice(value) {
	return '₺' + Number(value).toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

function close() {
	emit('update:modelValue', false)
}

function continueShopping() {
	close()
}

function checkout() {
	emit('checkout')
}
</script>

<style scoped>
.cart-drawer {
	width: 440px;
	max-width: calc(100vw - 24px);
}

@media (max-width: 480px) {
	.cart-drawer { width: 100vw; }
}

/* ── Boş durum ── */
.empty-cart {
	flex: 1;
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	padding: 40px 24px;
	text-align: center;
	gap: 8px;
}

.empty-icon {
	width: 80px; height: 80px;
	border-radius: 50%;
	background: #f5f5fa;
	display: flex; align-items: center; justify-content: center;
	color: #c0c0d8;
	margin-bottom: 12px;
}

.empty-cart h3 {
	font-size: 16px;
	font-weight: 700;
	color: #1a1a2e;
	margin: 0;
}

.empty-cart p {
	font-size: 13px;
	color: #888;
	line-height: 1.5;
	margin: 0 0 8px 0;
	max-width: 280px;
}

/* ── Free shipping progress ── */
.cart-progress {
	padding: 12px 16px 14px;
	background: rgb(var(--color-primary-soft));
	border-bottom: 1px solid #f0f0f5;
	flex-shrink: 0;
}

.cart-progress-text {
	font-size: 12px;
	color: #555;
	display: flex;
	align-items: center;
	gap: 6px;
	margin-bottom: 8px;
}
.cart-progress-text strong { color: #1a1a2e; font-weight: 700; }
.cart-progress-text svg { flex-shrink: 0; }

.cart-progress-bar {
	height: 5px;
	background: #e8e8f5;
	border-radius: 999px;
	overflow: hidden;
}

.cart-progress-fill {
	height: 100%;
	background: linear-gradient(90deg, rgb(var(--color-primary)), rgb(var(--color-primary)));
	border-radius: 999px;
	transition: width .3s ease;
}

/* ── Body ── */
.cart-body {
	flex: 1;
	overflow-y: auto;
	padding: 4px 0;
}
.cart-body::-webkit-scrollbar { width: 4px; }
.cart-body::-webkit-scrollbar-thumb { background: #ddd; border-radius: 4px; }

.cart-items {
	display: flex;
	flex-direction: column;
}

.cart-item {
	display: grid;
	grid-template-columns: 78px 1fr auto;
	gap: 14px;
	padding: 14px 16px;
	border-bottom: 1px solid #f5f5f8;
	transition: background .15s;
}
.cart-item:hover { background: #fafafe; }
.cart-item:last-child { border-bottom: none; }

.cart-item-image {
	display: block;
	width: 78px;
	height: 98px;
	border-radius: 10px;
	overflow: hidden;
	background: #f5f5fa;
}
.cart-item-image img {
	width: 100%; height: 100%;
	object-fit: cover;
	transition: transform .2s;
}
.cart-item-image:hover img { transform: scale(1.05); }

.cart-item-body {
	display: flex;
	flex-direction: column;
	gap: 4px;
	min-width: 0;
}

.cart-item-brand {
	font-size: 10.5px;
	font-weight: 700;
	color: #888;
	text-transform: uppercase;
	letter-spacing: 0.08em;
}

.cart-item-name {
	font-size: 13px;
	font-weight: 600;
	color: #1a1a2e;
	text-decoration: none;
	line-height: 1.35;
	display: -webkit-box;
	-webkit-line-clamp: 2;
	-webkit-box-orient: vertical;
	overflow: hidden;
}
.cart-item-name:hover { color: rgb(var(--color-primary)); }

.cart-item-meta {
	display: flex;
	align-items: center;
	gap: 10px;
	font-size: 11.5px;
	color: #666;
	margin-top: 2px;
}
.cart-item-meta strong { color: #1a1a2e; font-weight: 700; }

.cart-color {
	width: 14px;
	height: 14px;
	border-radius: 50%;
	border: 1.5px solid #fff;
	box-shadow: 0 0 0 1px #e5e7eb;
	flex-shrink: 0;
}

.cart-item-foot {
	display: flex;
	align-items: center;
	gap: 8px;
	margin-top: 6px;
}

.qty-stepper {
	display: inline-flex;
	align-items: center;
	background: #fff;
	border: 1.5px solid #e8e8f0;
	border-radius: 7px;
	overflow: hidden;
}

.qty-stepper button {
	width: 26px; height: 28px;
	border: none;
	background: none;
	font-size: 14px;
	font-weight: 600;
	color: #444;
	cursor: pointer;
	transition: background .12s;
}
.qty-stepper button:hover:not(:disabled) { background: #f5f5f8; }
.qty-stepper button:disabled { color: #ccc; cursor: not-allowed; }

.qty-value {
	min-width: 26px;
	text-align: center;
	font-size: 12.5px;
	font-weight: 700;
	color: #1a1a2e;
	user-select: none;
}

.remove-btn {
	background: none;
	border: none;
	width: 28px; height: 28px;
	border-radius: 7px;
	color: #aaa;
	cursor: pointer;
	display: flex; align-items: center; justify-content: center;
	transition: all .15s;
}
.remove-btn:hover {
	background: #fef2f2;
	color: #dc2626;
}

.cart-item-price {
	display: flex;
	flex-direction: column;
	align-items: flex-end;
	gap: 2px;
	min-width: 64px;
}

.ci-total {
	font-size: 14px;
	font-weight: 800;
	color: #1a1a2e;
	white-space: nowrap;
}

.ci-unit {
	font-size: 10.5px;
	color: #999;
	white-space: nowrap;
}

/* Item enter/leave anim */
.cart-item-enter-active,
.cart-item-leave-active {
	transition: all .25s ease;
}
.cart-item-enter-from {
	opacity: 0;
	transform: translateX(20px);
}
.cart-item-leave-to {
	opacity: 0;
	transform: translateX(20px);
	max-height: 0;
	padding-top: 0;
	padding-bottom: 0;
	margin: 0;
	border-bottom: 0;
}

/* ── Footer ── */
.cart-footer {
	border-top: 1px solid #f0f0f5;
	padding: 14px 16px 16px;
	background: #fff;
	flex-shrink: 0;
}

.sum-row {
	display: flex;
	justify-content: space-between;
	align-items: center;
	font-size: 13px;
	color: #555;
	margin-bottom: 6px;
}
.sum-row strong { color: #1a1a2e; font-weight: 700; }

.sum-row.sum-total {
	font-size: 16px;
	color: #1a1a2e;
	font-weight: 700;
	margin-top: 10px;
	padding-top: 10px;
	border-top: 1px dashed #e8e8f0;
	margin-bottom: 14px;
}
.sum-row.sum-total strong { font-size: 18px; font-weight: 800; }

.text-success { color: #16a34a !important; }

.cart-actions {
	display: grid;
	grid-template-columns: 1fr 1.4fr;
	gap: 8px;
}

.cart-actions .btn { justify-content: center; }

.checkout-btn { font-weight: 700; }

.clear-cart-btn {
	width: 100%;
	background: none;
	border: none;
	padding: 8px 4px 0;
	margin-top: 6px;
	font-size: 11.5px;
	color: #aaa;
	cursor: pointer;
	text-align: center;
	transition: color .15s;
}
.clear-cart-btn:hover { color: #dc2626; text-decoration: underline; }
</style>

<style>
/* CartDrawer Teleport'la body'ye gittiği için scoped olmayan minimal flex destek */
.cart-drawer.drawer {
	display: flex;
	flex-direction: column;
}
</style>
