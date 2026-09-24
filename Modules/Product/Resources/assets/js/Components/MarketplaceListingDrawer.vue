<script setup>
import { ref, computed, watch, inject } from 'vue'
import axios from 'axios'
import { usePage } from '@inertiajs/vue3'
import { OzDrawer, OzField, OzInput, OzSelect, OzAlert, OzTable, OzButton } from '@/Components/Oz'

// Dış API bilerek eski (open prop + close event) bırakıldı: ProductCard.vue de kullanıyor,
// henüz Oz'a taşınmadı — v-model:open'a zorlamak orayı da değiştirmeyi gerektirirdi.
const props = defineProps({
	open: { type: Boolean, default: false },
	productId: { type: [Number, String], default: null },
	marketplace: { type: Object, default: null },
})

const emit = defineEmits(['close', 'saved'])

// Görüntüleme product.card.view, kaydetme product.add (products.listings.upsert).
const page = usePage()
const canSave = computed(() => (page.props.auth?.permissions ?? []).includes('product.add'))

const showToast = inject('showToast')

const loading = ref(false)
const saving = ref(false)
const form = ref(null)
const product = ref({})
const variantsMeta = ref([])

const statusOptions = [
	{ value: 'active', label: 'Aktif' },
	{ value: 'passive', label: 'Pasif' },
]
const currencyOptions = [
	{ value: 'TL', label: 'TL' },
	{ value: 'USD', label: 'USD' },
	{ value: 'EUR', label: 'EUR' },
]

const approvalLabels = {
	not_sent: 'Gönderilmedi',
	pending: 'Onay Bekliyor',
	approved: 'Onaylandı',
	rejected: 'Reddedildi',
}
const approvalLabel = computed(() => approvalLabels[form.value?.approval_status] ?? '—')

const variantColumns = computed(() => [
	{ key: 'product_variant', label: 'Ürün Varyantları' },
	{ key: 'marketplace_variant', label: `${props.marketplace?.name ?? ''} Varyantları` },
	{ key: 'stock_code', label: 'Stok Kodu' },
	{ key: 'barcode', label: 'Barkod' },
])

function money(v) {
	return '₺' + Number(v ?? 0).toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

function variantLabel(id) {
	return variantsMeta.value.find((v) => v.id === id)?.label ?? '—'
}

function close() {
	emit('close')
}
const isOpen = computed({ get: () => props.open, set: (v) => { if (!v) close() } })

async function load() {
	if (!props.productId || !props.marketplace) return
	loading.value = true
	form.value = null
	try {
		const { data } = await axios.get(`/products/${props.productId}/marketplaces/${props.marketplace.key}/listing`)
		product.value = data.product
		variantsMeta.value = data.variants
		form.value = data.listing
	} catch (e) {
		showToast?.({ type: 'error', title: 'Listeleme yüklenemedi', message: e?.response?.statusText || 'Hata' })
		close()
	} finally {
		loading.value = false
	}
}

async function save() {
	if (!form.value) return
	saving.value = true
	try {
		const { data } = await axios.put(
			`/products/${props.productId}/marketplaces/${props.marketplace.key}/listing`,
			form.value,
		)
		showToast?.({ type: 'success', title: 'Kaydedildi', message: `${props.marketplace.name} listelemesi güncellendi.` })
		emit('saved', { marketplaceKey: props.marketplace.key, ...data.listing })
		close()
	} catch (e) {
		const msg = e?.response?.data?.message || Object.values(e?.response?.data?.errors ?? {})[0]?.[0] || 'Sunucu hatası.'
		showToast?.({ type: 'error', title: 'Kaydedilemedi', message: msg })
	} finally {
		saving.value = false
	}
}

watch(() => props.open, (v) => { if (v) load() })
</script>

<template>
	<OzDrawer v-if="marketplace" v-model:open="isOpen" size="lg" :subtitle="product.name || 'Ürün listeleme'" :persistent="saving">
		<template #header>
			<div class="mp-logo" :style="{ background: marketplace.color || '#888', color: '#fff' }">{{ marketplace.logoText }}</div>
			<div class="titles">
				<h3>{{ marketplace.name }} — Yeni Listeleme</h3>
				<p>{{ product.name || 'Ürün listeleme' }}</p>
			</div>
		</template>

		<p v-if="loading" class="lst-loading">Yükleniyor…</p>

		<template v-else-if="form">
			<OzAlert :tone="form.is_sent ? 'success' : 'warning'" class="lst-alert">
				{{ form.is_sent ? `Ürün ${marketplace.name} pazaryerine gönderildi.` : `Ürün ${marketplace.name} pazaryerine gönderilmedi.` }}
			</OzAlert>

			<div class="lst-grid">
				<div class="lst-main">
					<div class="grid-2">
						<OzField label="Mağaza">
							<OzInput v-model="form.store_name" placeholder="örn. #1 - TEST MAĞAZA" />
						</OzField>
						<OzField label="Ürün Durumu">
							<OzSelect v-model="form.product_status" :options="statusOptions" />
						</OzField>
					</div>

					<div class="grid-2">
						<OzField :label="`${marketplace.name} Onay Durumu`">
							<div class="lst-readonly">{{ approvalLabel }}</div>
						</OzField>
						<OzField label="Model Kodu">
							<OzInput v-model="form.model_code" maxlength="64" />
						</OzField>
					</div>

					<OzField label="Kategori">
						<OzInput v-model="form.category_path" placeholder="Pazaryeri kategorisi" />
					</OzField>

					<OzField label="Başlık">
						<OzInput v-model="form.title" :placeholder="product.name" maxlength="191" />
					</OzField>

					<div class="grid-3">
						<OzField label="Fiyat">
							<div class="price-row">
								<OzInput v-model.number="form.price" type="number" step="0.01" min="0" />
								<OzSelect v-model="form.currency" :options="currencyOptions" />
							</div>
						</OzField>
						<OzField label="Varyant Ek Fiyat">
							<OzInput v-model.number="form.variant_extra_price" type="number" step="0.01" min="0" />
						</OzField>
						<OzField label="Sevkiyat Süresi (gün)">
							<OzInput v-model.number="form.shipping_time" type="number" min="0" />
						</OzField>
					</div>

					<OzField label="Teslimat Şablonu">
						<OzInput v-model="form.delivery_template" placeholder="Hiçbiri seçilmedi" />
					</OzField>

					<div class="lst-variants">
						<div class="lst-variants-head">Listeleme Varyant Bilgileri</div>
						<OzTable :columns="variantColumns" :rows="form.variants" row-key="product_variant_id" empty-title="Varyant yok" min-width="480px">
							<template #cell-product_variant="{ row }">{{ variantLabel(row.product_variant_id) }}</template>
							<template #cell-marketplace_variant="{ row }"><OzInput v-model="row.marketplace_variant" /></template>
							<template #cell-stock_code="{ row }"><OzInput v-model="row.stock_code" /></template>
							<template #cell-barcode="{ row }"><OzInput v-model="row.barcode" /></template>
						</OzTable>
					</div>
				</div>

				<aside class="lst-summary">
					<img v-if="product.image" :src="product.image" :alt="product.name" class="lst-summary-img">
					<h3>Ürün Site Özeti</h3>
					<p><b>Adı:</b> {{ product.name }}</p>
					<p><b>Marka:</b> {{ product.brand || '—' }}</p>
					<p><b>Kategori:</b> {{ product.categoryPath || '—' }}</p>
					<p><b>Satış Fiyatı:</b> {{ money(product.price) }}</p>
					<p v-if="product.marketPrice != null"><b>Piyasa Fiyatı:</b> {{ money(product.marketPrice) }}</p>
					<p><b>Satış Durumu:</b> {{ product.status }}</p>
					<a :href="product.editUrl" class="lst-edit-link">Düzenle</a>
				</aside>
			</div>
		</template>

		<template #footer>
			<OzButton @click="close">Kapat</OzButton>
			<OzButton v-if="canSave" variant="primary" :disabled="loading" :loading="saving" @click="save">Kaydet</OzButton>
		</template>
	</OzDrawer>
</template>

<style scoped>
.mp-logo { width: 36px; height: 36px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 800; letter-spacing: 0.03em; flex-shrink: 0; }
.titles { flex: 1; min-width: 0; }
.titles h3 { margin: 0; font-size: 15px; font-weight: 700; color: var(--oz-ink-soft); letter-spacing: -.01em; }
.titles p { margin: 3px 0 0; font-size: 12px; color: var(--oz-muted); }

.lst-loading { text-align: center; color: var(--oz-muted); font-size: 13px; padding: 40px 0; }
.lst-alert { margin-bottom: 16px; }

.lst-grid { display: grid; grid-template-columns: 1fr 260px; gap: 18px; align-items: start; }
@media (max-width: 760px) { .lst-grid { grid-template-columns: 1fr; } }

.lst-main { display: flex; flex-direction: column; gap: 14px; }
.grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px; }
.lst-readonly { height: 36px; display: flex; align-items: center; padding: 0 12px; background: var(--oz-surface-2); border-radius: 9px; font-size: 13px; color: var(--oz-muted); }
.price-row { display: grid; grid-template-columns: 1fr 90px; gap: 6px; }

.lst-variants { background: var(--oz-surface); border: 1px solid var(--oz-border); border-radius: 12px; padding: 14px; }
.lst-variants-head { font-size: 13px; font-weight: 700; color: var(--oz-ink-soft); margin-bottom: 10px; }

.lst-summary { background: var(--oz-surface-2); border: 1px solid var(--oz-border); border-radius: 12px; padding: 14px; font-size: 12.5px; color: var(--oz-ink); display: flex; flex-direction: column; gap: 4px; }
.lst-summary h3 { font-size: 13px; font-weight: 700; color: var(--oz-ink-soft); margin: 4px 0; }
.lst-summary p { margin: 0; }
.lst-summary b { color: var(--oz-ink-soft); }
.lst-summary-img { width: 100%; height: 180px; object-fit: cover; border-radius: 9px; margin-bottom: 6px; }
.lst-edit-link { color: var(--oz-accent); font-weight: 600; margin-top: 6px; text-decoration: none; }

@media (max-width: 560px) {
	.grid-2, .grid-3 { grid-template-columns: 1fr; }
}
</style>
