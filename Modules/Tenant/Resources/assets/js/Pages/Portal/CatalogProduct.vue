<template>
	<Head :title="product.name" />
	<div class="product-detail">
		<Link href="/catalog" class="back-link">← Katalog</Link>
		<div class="grid">
			<div class="img-wrap">
				<img :src="product.image" :alt="product.name" />
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
.img-wrap img { width: 100%; border-radius: 12px; }
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

@media (max-width: 700px) {
	.grid { grid-template-columns: 1fr; }
}
</style>
