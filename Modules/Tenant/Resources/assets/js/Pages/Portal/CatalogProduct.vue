<template>
	<Head :title="product.name" />
	<div class="product-detail">
		<Link href="/catalog" class="back-link">← Katalog</Link>
		<div class="grid">
			<div class="gallery">
				<div class="img-wrap">
					<img :src="gallery[selectedImage]" :alt="product.name" />
				</div>
				<div v-if="gallery.length > 1" class="thumbs">
					<button
						v-for="(img, idx) in gallery"
						:key="idx"
						class="thumb"
						:class="{ active: selectedImage === idx }"
						@click="selectedImage = idx"
					>
						<img :src="img" :alt="`Görsel ${idx + 1}`" loading="lazy" />
					</button>
				</div>
			</div>
			<div class="info">
				<div class="brand">{{ product.brand ?? '—' }}</div>
				<h1 class="name">{{ product.name }}</h1>
				<div class="sku mono">SKU: {{ product.sku }}</div>

				<div class="price-block">
					<div class="tenant-price">{{ formatMoney(currentPrice) }}</div>
					<div v-if="product.purchase_price > 0" class="purchase-price">
						Bizden alış: {{ formatMoney(product.purchase_price) }}
					</div>
				</div>

				<div v-if="product.variants.length > 0" class="variants">
					<h3>Varyant Seçimi</h3>
					<div class="variant-grid">
						<button
							v-for="v in product.variants"
							:key="v.id"
							:class="['variant-btn', { active: selectedVariant?.id === v.id, oos: v.stock === 0 }]"
							:disabled="v.stock === 0"
							@click="selectedVariant = v"
						>
							<span v-if="v.color_name" class="color-dot" :style="`background:${v.color_hex || '#ccc'}`"></span>
							<span class="label">{{ [v.size, v.color_name].filter(Boolean).join(' / ') }}</span>
							<span class="stock">{{ v.stock }} adet</span>
						</button>
					</div>
				</div>

				<div class="qty-row">
					<label>Adet:</label>
					<input v-model.number="qty" type="number" min="1" :max="maxQty" />
				</div>

				<button class="btn-primary" :disabled="adding || !canAdd" @click="addToCart">
					{{ adding ? 'Ekleniyor...' : 'Sepete Ekle' }}
				</button>
			</div>
		</div>

		<!-- Ürün detayları: açıklama, öne çıkan özellikler, teknik özellikler -->
		<section class="details">
			<div class="detail-block">
				<h2 class="detail-title">Ürün Açıklaması</h2>
				<p v-if="product.description" class="description-text">{{ product.description }}</p>
				<p v-else class="empty-text">Bu ürün için henüz açıklama eklenmemiş.</p>

				<ul v-if="product.features && product.features.length" class="feature-list">
					<li v-for="f in product.features" :key="f">
						<svg width="14" height="14" fill="none" stroke="#16a34a" stroke-width="2.5" viewBox="0 0 24 24">
							<polyline points="20 6 9 17 4 12" />
						</svg>
						{{ f }}
					</li>
				</ul>
			</div>

			<div v-if="product.specs && Object.keys(product.specs).length" class="detail-block">
				<h2 class="detail-title">Teknik Özellikler</h2>
				<table class="specs-table">
					<tbody>
						<tr v-for="(value, key) in product.specs" :key="key">
							<th>{{ key }}</th>
							<td>{{ value }}</td>
						</tr>
					</tbody>
				</table>
			</div>
		</section>
	</div>
</template>

<script setup>
import { ref, computed, inject } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import TenantPortalLayout from '@/Layouts/TenantPortalLayout.vue'

defineOptions({ layout: TenantPortalLayout })

const props = defineProps({
	tenant: { type: Object, required: true },
	product: { type: Object, required: true },
})

const showToast = inject('showToast', null)

const selectedVariant = ref(props.product.variants[0] ?? null)
const qty = ref(1)
const adding = ref(false)
const selectedImage = ref(0)

// Gerçek görseller; yoksa "hazırlanıyor" placeholder'ı. Eski tek `image` alanı da desteklenir.
const gallery = computed(() => {
	const imgs = props.product.images
	if (Array.isArray(imgs) && imgs.length) return imgs
	if (props.product.image) return [props.product.image]
	return ['/images/product-placeholder.svg']
})

const currentPrice = computed(() => selectedVariant.value?.tenant_price ?? props.product.tenant_price)
const maxQty = computed(() => selectedVariant.value?.stock ?? 99)
const canAdd = computed(() => qty.value > 0 && (selectedVariant.value?.stock ?? 1) > 0)

function formatMoney(v) {
	return new Intl.NumberFormat('tr-TR', { style: 'currency', currency: 'TRY', maximumFractionDigits: 2 }).format(Number(v ?? 0))
}

function addToCart() {
	if (!canAdd.value) return
	adding.value = true
	router.post('/cart', {
		product_id: props.product.id,
		variant_id: selectedVariant.value?.id,
		color: selectedVariant.value?.color_name,
		size: selectedVariant.value?.size,
		qty: qty.value,
	}, {
		preserveScroll: true,
		preserveState: true,
		onSuccess: () => {
			showToast?.({ type: 'success', title: 'Sepete eklendi', message: props.product.name })
		},
		onError: (errs) => {
			showToast?.({ type: 'error', title: 'Eklenemedi', message: Object.values(errs)[0] || 'Hata.' })
		},
		onFinish: () => { adding.value = false },
	})
}
</script>

<style scoped>
.back-link { font-size: 12px; color: #4338ca; text-decoration: none; }
.product-detail { display: flex; flex-direction: column; gap: 16px; }
.grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }
.gallery { display: flex; flex-direction: column; gap: 10px; }
.img-wrap { aspect-ratio: 3/4; background: #f7f7fb; border-radius: 12px; overflow: hidden; }
.img-wrap img { width: 100%; height: 100%; object-fit: cover; }
.thumbs { display: grid; grid-template-columns: repeat(5, 1fr); gap: 8px; }
.thumb { aspect-ratio: 3/4; border: 2px solid transparent; border-radius: 8px; overflow: hidden; background: #f7f7fb; cursor: pointer; padding: 0; }
.thumb.active { border-color: #4338ca; }
.thumb img { width: 100%; height: 100%; object-fit: cover; }
.details { margin-top: 24px; display: flex; flex-direction: column; gap: 20px; }
.detail-block { background: #fff; border: 1px solid #ebebf0; border-radius: 12px; padding: 18px 20px; }
.detail-title { font-size: 14px; font-weight: 700; color: #1a1a2e; margin-bottom: 12px; }
.description-text { font-size: 13.5px; color: #444; line-height: 1.7; white-space: pre-line; }
.empty-text { font-size: 13px; color: #888; font-style: italic; }
.feature-list { list-style: none; padding: 0; margin: 14px 0 0; display: grid; grid-template-columns: 1fr 1fr; gap: 8px 20px; }
.feature-list li { display: flex; align-items: center; gap: 8px; font-size: 13px; color: #444; }
.specs-table { width: 100%; border-collapse: separate; border-spacing: 0; }
.specs-table th, .specs-table td { padding: 10px 12px; font-size: 13px; text-align: left; border-bottom: 1px solid #f5f5f8; }
.specs-table th { width: 200px; color: #888; font-weight: 500; background: #fafafe; }
.specs-table td { color: #1a1a2e; font-weight: 600; }
.specs-table tr:last-child th, .specs-table tr:last-child td { border-bottom: none; }
@media (max-width: 700px) { .feature-list { grid-template-columns: 1fr; } .specs-table th { width: 120px; } }
.info { display: flex; flex-direction: column; gap: 12px; }
.brand { font-size: 11px; color: #888; text-transform: uppercase; letter-spacing: 0.05em; }
.name { font-size: 24px; font-weight: 700; color: #1a1a2e; }
.sku { font-size: 11px; color: #888; }
.mono { font-family: 'SF Mono', Menlo, Consolas, monospace; }
.price-block { background: #f7f7fb; padding: 16px; border-radius: 12px; }
.tenant-price { font-size: 28px; font-weight: 700; color: #4338ca; font-family: 'SF Mono', Menlo, Consolas, monospace; }
.purchase-price { font-size: 12px; color: #888; margin-top: 4px; }
.variants h3 { font-size: 12px; color: #555; font-weight: 700; text-transform: uppercase; margin-bottom: 8px; }
.variant-grid { display: flex; flex-wrap: wrap; gap: 6px; }
.variant-btn { display: flex; flex-direction: column; align-items: center; gap: 4px; padding: 8px 12px; border: 1px solid #ebebf0; background: #fff; border-radius: 8px; cursor: pointer; font-size: 11px; }
.variant-btn.active { border-color: #4338ca; background: #eef2ff; }
.variant-btn.oos { opacity: 0.4; cursor: not-allowed; }
.color-dot { width: 14px; height: 14px; border-radius: 50%; border: 1px solid #ddd; }
.label { font-weight: 600; color: #1a1a2e; }
.stock { font-size: 10px; color: #888; }
.qty-row { display: flex; align-items: center; gap: 12px; }
.qty-row input { width: 80px; padding: 8px 12px; border: 1px solid #ebebf0; border-radius: 8px; font-size: 13px; }
.btn-primary { padding: 12px 24px; background: #4338ca; color: #fff; border: none; border-radius: 8px; font-weight: 600; font-size: 14px; cursor: pointer; }
.btn-primary:disabled { opacity: 0.5; cursor: not-allowed; }
.btn-primary:hover:not(:disabled) { background: #3730a3; }
</style>
