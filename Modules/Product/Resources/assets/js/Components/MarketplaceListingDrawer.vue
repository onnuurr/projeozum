<template>
	<Teleport to="body">
		<div class="drawer-overlay" :class="{ open }" @click="close"></div>
		<aside
			class="drawer mp-listing-drawer"
			:class="{ open }"
			role="dialog"
			aria-modal="true"
			aria-label="Pazaryeri Listeleme"
		>
			<div class="drawer-header">
				<div class="drawer-title">
					<div class="drawer-title-icon mp-icon" :style="{ background: marketplace?.color || '#888' }">
						{{ marketplace?.logoText }}
					</div>
					<div>
						<h4>{{ marketplace?.name }} — Yeni Listeleme</h4>
						<p>{{ product.name || 'Ürün listeleme' }}</p>
					</div>
				</div>
				<button class="drawer-close" @click="close" aria-label="Kapat">
					<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
						<path d="M18 6L6 18M6 6l12 12" />
					</svg>
				</button>
			</div>

			<div v-if="loading" class="lst-loading">Yükleniyor…</div>

			<div v-else-if="form" class="drawer-body">
				<div class="lst-alert" :class="form.is_sent ? 'ok' : 'warn'">
					{{ form.is_sent ? `Ürün ${marketplace.name} pazaryerine gönderildi.` : `Ürün ${marketplace.name} pazaryerine gönderilmedi.` }}
				</div>

				<div class="lst-grid">
					<div class="lst-main">
						<div class="form-grid-2">
							<div class="form-group">
								<label class="form-label">Mağaza</label>
								<input v-model="form.store_name" class="form-input" type="text" placeholder="örn. #1 - TEST MAĞAZA" />
							</div>
							<div class="form-group">
								<label class="form-label">Ürün Durumu</label>
								<CustomSelect v-model="form.product_status" :options="statusOptions" :show-label="false" />
							</div>
						</div>

						<div class="form-grid-2">
							<div class="form-group">
								<label class="form-label">{{ marketplace.name }} Onay Durumu</label>
								<div class="lst-readonly">{{ approvalLabel }}</div>
							</div>
							<div class="form-group">
								<label class="form-label">Model Kodu</label>
								<input v-model="form.model_code" class="form-input" type="text" maxlength="64" />
							</div>
						</div>

						<div class="form-group">
							<label class="form-label">Kategori</label>
							<input v-model="form.category_path" class="form-input" type="text" placeholder="Pazaryeri kategorisi" />
						</div>

						<div class="form-group">
							<label class="form-label">Başlık</label>
							<input v-model="form.title" class="form-input" type="text" :placeholder="product.name" maxlength="191" />
						</div>

						<div class="form-grid-3">
							<div class="form-group">
								<label class="form-label">Fiyat</label>
								<div class="lst-price-row">
									<input v-model.number="form.price" class="form-input" type="number" step="0.01" min="0" />
									<CustomSelect v-model="form.currency" :options="currencyOptions" :show-label="false" />
								</div>
							</div>
							<div class="form-group">
								<label class="form-label">Varyant Ek Fiyat</label>
								<input v-model.number="form.variant_extra_price" class="form-input" type="number" step="0.01" min="0" />
							</div>
							<div class="form-group">
								<label class="form-label">Sevkiyat Süresi (gün)</label>
								<input v-model.number="form.shipping_time" class="form-input" type="number" min="0" />
							</div>
						</div>

						<div class="form-group">
							<label class="form-label">Teslimat Şablonu</label>
							<input v-model="form.delivery_template" class="form-input" type="text" placeholder="Hiçbiri seçilmedi" />
						</div>

						<div class="lst-variants">
							<div class="lst-variants-head">Listeleme Varyant Bilgileri</div>
							<div class="table-scroll">
							<table class="lst-table">
								<thead>
									<tr>
										<th>Ürün Varyantları</th>
										<th>{{ marketplace.name }} Varyantları</th>
										<th>Stok Kodu</th>
										<th>Barkod</th>
									</tr>
								</thead>
								<tbody>
									<tr v-for="row in form.variants" :key="row.product_variant_id">
										<td>{{ variantLabel(row.product_variant_id) }}</td>
										<td><input v-model="row.marketplace_variant" class="form-input" type="text" /></td>
										<td><input v-model="row.stock_code" class="form-input" type="text" /></td>
										<td><input v-model="row.barcode" class="form-input" type="text" /></td>
									</tr>
									<tr v-if="!form.variants.length">
										<td colspan="4" class="lst-empty">Varyant yok.</td>
									</tr>
								</tbody>
							</table>
							</div>
						</div>
					</div>

					<aside class="lst-summary">
						<div class="lst-summary-card">
							<img v-if="product.image" :src="product.image" :alt="product.name" class="lst-summary-img" />
							<h3>Ürün Site Özeti</h3>
							<p><b>Adı:</b> {{ product.name }}</p>
							<p><b>Marka:</b> {{ product.brand || '—' }}</p>
							<p><b>Kategori:</b> {{ product.categoryPath || '—' }}</p>
							<p><b>Satış Fiyatı:</b> {{ money(product.price) }}</p>
							<p v-if="product.marketPrice != null"><b>Piyasa Fiyatı:</b> {{ money(product.marketPrice) }}</p>
							<p><b>Satış Durumu:</b> {{ product.status }}</p>
							<a :href="product.editUrl" class="lst-edit-link">Düzenle</a>
						</div>
					</aside>
				</div>
			</div>

			<div class="drawer-footer">
				<button class="btn btn-secondary" @click="close">Kapat</button>
				<button class="btn btn-primary" :disabled="saving || loading" @click="save">
					{{ saving ? 'Kaydediliyor…' : 'Kaydet' }}
				</button>
			</div>
		</aside>
	</Teleport>
</template>

<script setup>
import { ref, computed, watch, inject } from 'vue'
import axios from 'axios'
import CustomSelect from '@/Components/CustomSelect.vue'

const props = defineProps({
	open: { type: Boolean, default: false },
	productId: { type: [Number, String], default: null },
	marketplace: { type: Object, default: null },
})

const emit = defineEmits(['close', 'saved'])

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

function money(v) {
	return '₺' + Number(v ?? 0).toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

function variantLabel(id) {
	return variantsMeta.value.find((v) => v.id === id)?.label ?? '—'
}

function close() {
	emit('close')
}

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

<style scoped>
/* Standart .drawer chrome'u (FilterDrawer global stilleri) kullanır; sadece genişlik + içerik. */
.mp-listing-drawer {
	width: 64vw;
	max-width: calc(100vw - 24px);
	min-width: 560px;
}
@media (max-width: 900px) {
	.mp-listing-drawer { width: 100vw; min-width: 0; }
}

.mp-icon { color: #fff; font-size: 11px; font-weight: 800; letter-spacing: 0.03em; }

.lst-loading { flex: 1; display: flex; align-items: center; justify-content: center; color: #888; font-size: 13px; }

.lst-alert { padding: 12px 16px; border-radius: 10px; font-weight: 600; font-size: 13px; }
.lst-alert.warn { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
.lst-alert.ok { background: #ecfdf3; color: #16a34a; border: 1px solid #bbf7d0; }

.lst-grid { display: grid; grid-template-columns: 1fr 300px; gap: 18px; align-items: start; }
@media (max-width: 760px) { .lst-grid { grid-template-columns: 1fr; } }

.lst-main { display: flex; flex-direction: column; gap: 14px; }
.form-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.form-grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px; }
.form-group { display: flex; flex-direction: column; gap: 5px; }
.form-label { font-size: 12.5px; font-weight: 600; color: #555; }
.form-input { height: 36px; border: 1.5px solid #e8e8f0; border-radius: 9px; background: #fff; padding: 0 12px; font-family: inherit; font-size: 13px; color: #1a1a2e; outline: none; width: 100%; }
.form-input:focus { border-color: rgb(var(--color-primary)); box-shadow: 0 0 0 3px rgb(var(--color-primary) / 0.1); }
.lst-readonly { height: 36px; display: flex; align-items: center; padding: 0 12px; background: #f0f0f5; border-radius: 9px; font-size: 13px; color: #666; }
.lst-price-row { display: grid; grid-template-columns: 1fr 90px; gap: 6px; }
.lst-variants { background: #fff; border: 1px solid #ebebf0; border-radius: 12px; padding: 14px; }
.lst-variants-head { font-size: 13px; font-weight: 700; color: #1a1a2e; margin-bottom: 10px; }
.lst-table { width: 100%; border-collapse: collapse; }
.lst-table th { text-align: left; font-size: 11.5px; color: #888; font-weight: 700; padding: 6px 8px; border-bottom: 1px solid #f0f0f5; }
.lst-table td { padding: 6px 8px; }
.lst-table .form-input { height: 32px; }
.lst-empty { text-align: center; color: #aaa; padding: 14px; }
.lst-summary-card { background: #fafafe; border: 1px solid #ebebf0; border-radius: 12px; padding: 14px; font-size: 12.5px; color: #444; display: flex; flex-direction: column; gap: 4px; }
.lst-summary-card h3 { font-size: 13px; font-weight: 700; color: #1a1a2e; margin: 4px 0; }
.lst-summary-card p { margin: 0; }
.lst-summary-card b { color: #1a1a2e; }
.lst-summary-img { width: 100%; height: 180px; object-fit: cover; border-radius: 9px; margin-bottom: 6px; }
.lst-edit-link { color: rgb(var(--color-primary)); font-weight: 600; margin-top: 6px; text-decoration: none; }

@media (max-width: 560px) {
	.form-grid-2 { grid-template-columns: 1fr; }
	.form-grid-3 { grid-template-columns: 1fr; }
}
</style>
