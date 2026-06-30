<template>
	<Head :title="isEdit ? `${form.name || 'Ürün'} · Düzenle` : 'Yeni Ürün'" />

	<div class="page-product-form">
		<Breadcrumb
			:items="[
				{ label: 'Ana Sayfa', to: '/workflow', icon: 'home' },
				{ label: 'Katalog', to: '/products' },
				{ label: 'Tüm Ürünler', to: '/products' },
				{ label: isEdit ? 'Ürünü Düzenle' : 'Yeni Ürün' },
			]"
		/>

		<!-- Yapışkan başlık çubuğu -->
		<div class="form-topbar">
			<div class="topbar-left">
				<Link href="/products" class="back-btn" aria-label="Geri">
					<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
						<path d="M19 12H5M12 19l-7-7 7-7" />
					</svg>
				</Link>
				<div>
					<h1 class="topbar-title">{{ isEdit ? 'Ürünü Düzenle' : 'Yeni Ürün Ekle' }}</h1>
					<p class="topbar-sub">
						{{ isEdit ? form.name : 'Ürün kataloğuna yeni bir ürün ekleyin' }}
						<span v-if="isEdit && form.sku" class="topbar-sku">{{ form.sku }}</span>
					</p>
				</div>
			</div>
			<div class="topbar-actions">
				<Link href="/products" class="btn btn-ghost" :class="{ disabled: busy }">İptal</Link>
				<button class="btn btn-primary btn-with-icon" @click="submit" :disabled="busy">
					<svg v-if="isEdit" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
						<path d="M20 6L9 17l-5-5" />
					</svg>
					<svg v-else width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
						<path d="M12 5v14M5 12h14" />
					</svg>
					{{ busy ? 'Kaydediliyor…' : (isEdit ? 'Değişiklikleri Kaydet' : 'Ürünü Ekle') }}
				</button>
			</div>
		</div>

		<!-- Doğrulama özeti -->
		<div v-if="hasErrors" class="error-banner">
			<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
				<circle cx="12" cy="12" r="10" /><line x1="12" y1="8" x2="12" y2="12" /><line x1="12" y1="16" x2="12.01" y2="16" />
			</svg>
			<span>Formda eksik veya hatalı alanlar var. İşaretli sekmelerdeki yerleri düzeltin.</span>
		</div>

		<!-- Sekme şeridi -->
		<div class="tab-strip" role="tablist">
			<button
				v-for="tab in tabs"
				:key="tab.key"
				type="button"
				class="tab-btn"
				:class="{ active: activeTab === tab.key }"
				role="tab"
				:aria-selected="activeTab === tab.key"
				@click="activeTab = tab.key"
			>
				{{ tab.label }}
				<span v-if="tabsWithErrors.has(tab.key)" class="tab-error-dot" title="Bu sekmede hata var"></span>
			</button>
		</div>

		<div class="tab-panels">
			<!-- ── Genel Bilgiler ── -->
			<div v-show="activeTab === 'general'" class="tab-panel tab-panel-overflow" role="tabpanel">
				<section class="card">
					<header class="card-head">
						<h2>Temel Bilgiler</h2>
						<p>Ürünün adı, stok kodu ve tanımlayıcı bilgileri.</p>
					</header>
					<div class="card-body">
						<div class="form-group">
							<label class="form-label">Ürün Adı <span class="required">*</span></label>
							<input v-model="form.name" class="form-input" type="text" placeholder="örn. Basic Crew Tişört" @input="onNameInput" />
							<span v-if="errors.name" class="form-error">{{ errors.name }}</span>
						</div>

						<div class="form-group">
							<label class="form-label">Ana SKU <span class="required">*</span></label>
							<div class="sku-row">
								<input v-model="form.sku" class="form-input" type="text" placeholder="TEK-XXX-001" @input="skuTouched = true" />
								<button type="button" class="btn btn-ghost btn-sm sku-gen" :disabled="!form.name || !form.brand_id" @click="generateBaseSku" title="Marka + ürün adından otomatik üret">↻</button>
							</div>
							<span v-if="errors.sku" class="form-error">{{ errors.sku }}</span>
							<span v-else class="form-help">Boş bırakıp ↻ ile otomatik üretebilirsiniz (marka + ürün adı gerekli).</span>
						</div>
					</div>
				</section>

				<section class="card card-overflow">
					<header class="card-head">
						<h2>Organizasyon</h2>
						<p>Ürünün kataloğdaki yeri ve sınıflandırması.</p>
					</header>
					<div class="card-body">
						<div class="form-grid-2">
							<CustomSelect label="Cinsiyet" v-model="form.gender" :options="genderOptions" />
							<div class="form-group">
								<CustomSelect label="Kategori" v-model="form.category_id" :options="categoryOptions" placeholder="Kategori seçiniz…" />
								<span v-if="errors.category_id" class="form-error">{{ errors.category_id }}</span>
							</div>
						</div>
						<div class="form-group">
							<CustomSelect label="Marka" v-model="form.brand_id" :options="brandOptions" placeholder="— Marka yok —" />
							<span v-if="errors.brand_id" class="form-error">{{ errors.brand_id }}</span>
						</div>
						<label class="toggle-row">
							<input type="checkbox" v-model="form.is_new" />
							<span class="toggle-text">Yeni gelen olarak işaretle</span>
						</label>
					</div>
				</section>
			</div>

			<!-- ── SEO ── -->
			<div v-show="activeTab === 'seo'" class="tab-panel" role="tabpanel">
				<section class="card">
					<header class="card-head">
						<h2>Arama Motoru Optimizasyonu</h2>
						<p>Ürünün URL'i ve arama sonuçlarındaki görünümü (opsiyonel).</p>
					</header>
					<div class="card-body">
						<div class="form-group">
							<label class="form-label">URL (slug)</label>
							<input v-model="form.slug" class="form-input" type="text" placeholder="otomatik üretilir" @input="form.slug = slugify(form.slug)" />
							<span v-if="errors.slug" class="form-error">{{ errors.slug }}</span>
							<span v-else class="form-help">/products/{{ form.slug || '…' }} — boş bırakırsanız ürün adından otomatik üretilir.</span>
						</div>
						<div class="form-group">
							<label class="form-label">Meta Başlık</label>
							<input v-model="form.meta_title" class="form-input" type="text" maxlength="191" placeholder="Boşsa ürün adı kullanılır" />
							<span v-if="errors.meta_title" class="form-error">{{ errors.meta_title }}</span>
						</div>
						<div class="form-group">
							<label class="form-label">Meta Açıklama</label>
							<textarea v-model="form.meta_description" class="form-input" rows="3" maxlength="500" placeholder="Arama sonuçlarında görünecek kısa özet…" />
							<span v-if="errors.meta_description" class="form-error">{{ errors.meta_description }}</span>
							<span v-else class="form-help">{{ (form.meta_description || '').length }}/500 karakter</span>
						</div>
						<div class="form-group">
							<label class="form-label">Anahtar Kelimeler</label>
							<input v-model="form.meta_keywords" class="form-input" type="text" maxlength="255" placeholder="tişört, pamuklu, yazlık" />
							<span v-if="errors.meta_keywords" class="form-error">{{ errors.meta_keywords }}</span>
							<span v-else class="form-help">Virgülle ayırın.</span>
						</div>
					</div>
				</section>
			</div>

			<!-- ── Fiyat-Kargo ── -->
			<div v-show="activeTab === 'price'" class="tab-panel" role="tabpanel">
				<section class="card">
					<header class="card-head">
						<h2>Toplu Fiyatlama</h2>
						<p>Tüm varyantlara tek seferde aynı fiyatı uygula. Tek tek fiyatlar Stok-Varyant sekmesinde.</p>
					</header>
					<div class="card-body">
						<div v-if="!form.variants.length" class="inline-hint">
							Henüz varyant yok. Önce Stok-Varyant sekmesinden varyant oluşturun.
						</div>
						<div v-else class="bulk-price-row">
							<input v-model.number="bulkPrice" type="number" step="0.01" min="0" class="form-input bulk-input" placeholder="Fiyat" />
							<button type="button" class="btn btn-secondary btn-sm" @click="applyBulkPrice" :disabled="!bulkPrice">→ {{ form.variants.length }} varyantın hepsine uygula</button>
						</div>
					</div>
				</section>

				<section class="card">
					<header class="card-head">
						<h2>Fiyatlandırma</h2>
						<p>Piyasa ve alış fiyatı (opsiyonel). Satış fiyatları varyantlardan gelir.</p>
					</header>
					<div class="card-body">
						<div class="form-grid-2">
							<div class="form-group">
								<label class="form-label">Piyasa Fiyatı (₺)</label>
								<input v-model.number="form.market_price" class="form-input" type="number" step="0.01" min="0" placeholder="örn. 125.86" />
								<span v-if="errors.market_price" class="form-error">{{ errors.market_price }}</span>
							</div>
							<div class="form-group">
								<label class="form-label">Alış Fiyatı (₺)</label>
								<input v-model.number="form.purchase_price" class="form-input" type="number" step="0.01" min="0" placeholder="örn. 89.90" />
								<span v-if="errors.purchase_price" class="form-error">{{ errors.purchase_price }}</span>
							</div>
						</div>
					</div>
				</section>

				<section class="card">
					<header class="card-head">
						<h2>Kargo</h2>
						<p>Kargo etiketi ve teslimat bilgileri (opsiyonel).</p>
					</header>
					<div class="card-body">
						<label class="toggle-row">
							<input type="checkbox" v-model="form.free_shipping" />
							<span class="toggle-text">Ücretsiz kargo</span>
						</label>

						<div class="form-grid-2">
							<div class="form-group">
								<label class="form-label">Ağırlık (kg)</label>
								<input v-model.number="form.weight" class="form-input" type="number" step="0.001" min="0" placeholder="örn. 0.250" />
								<span v-if="errors.weight" class="form-error">{{ errors.weight }}</span>
							</div>
							<div class="form-group">
								<label class="form-label">Desi</label>
								<input v-model.number="form.desi" class="form-input" type="number" step="0.01" min="0" placeholder="örn. 1.5" />
								<span v-if="errors.desi" class="form-error">{{ errors.desi }}</span>
							</div>
						</div>

						<div class="form-grid-2">
							<div class="form-group">
								<label class="form-label">Kargo Süresi</label>
								<input v-model="form.shipping_time" class="form-input" type="text" maxlength="50" placeholder="örn. 1-3 iş günü" />
								<span v-if="errors.shipping_time" class="form-error">{{ errors.shipping_time }}</span>
							</div>
							<div class="form-group">
								<label class="form-label">Sabit Kargo Ücreti (₺)</label>
								<input v-model.number="form.shipping_fee" class="form-input" type="number" step="0.01" min="0" placeholder="Ücretsiz kargo kapalıysa" :disabled="form.free_shipping" />
								<span v-if="errors.shipping_fee" class="form-error">{{ errors.shipping_fee }}</span>
								<span v-else-if="form.free_shipping" class="form-help">Ücretsiz kargo açık — kargo ücreti uygulanmaz.</span>
							</div>
						</div>
					</div>
				</section>
			</div>

			<!-- ── Stok-Varyant ── -->
			<div v-show="activeTab === 'variant'" class="tab-panel" role="tabpanel">
				<section class="card">
					<header class="card-head">
						<h2>Bedenler & Renkler</h2>
						<p>Seçtiğiniz beden ve renklerin kombinasyonundan varyant matrisi oluşturun.</p>
					</header>
					<div class="card-body">
						<div class="form-group">
							<label class="form-label">Bedenler</label>
							<div class="chips-grid">
								<button v-for="s in sizeOptions" :key="s" type="button" class="chip chip-size"
									:class="{ active: selectedSizes.includes(s) }" @click="toggleSize(s)">{{ s }}</button>
							</div>
						</div>

						<div class="form-group">
							<label class="form-label">Renkler</label>
							<div class="color-grid">
								<button v-for="c in colorPalette" :key="c.name" type="button" class="color-chip"
									:class="{ active: hasColor(c.name) }" @click="toggleColor(c)" :title="c.name">
									<span class="color-swatch" :style="{ background: c.hex }"></span>
									<span class="color-name">{{ c.name }}</span>
								</button>
							</div>
							<details class="color-custom">
								<summary>+ Özel renk</summary>
								<div class="color-custom-row">
									<input v-model="customColor.name" class="form-input" type="text" placeholder="Renk adı" />
									<input v-model="customColor.hex" class="form-input color-hex" type="color" />
									<button type="button" class="btn btn-secondary btn-sm" :disabled="!customColor.name" @click="addCustomColor">Ekle</button>
								</div>
							</details>
						</div>

						<button type="button" class="btn btn-secondary btn-sm regen-btn" @click="regenerateVariants" :disabled="!canGenerate">
							↻ Varyantları Oluştur
							<span v-if="canGenerate" class="hint-inline">— {{ matrixCount }} kombinasyon</span>
						</button>
					</div>
				</section>

				<section class="card">
					<header class="card-head flex-row">
						<div>
							<h2>Varyantlar <span v-if="form.variants.length" class="variant-badge">{{ form.variants.length }}</span></h2>
							<p>Her varyant için SKU, fiyat ve stok girin.</p>
						</div>
						<div class="bulk-controls" v-if="form.variants.length">
							<input v-model.number="bulkStock" type="number" min="0" class="form-input bulk-input" placeholder="Stok" />
							<button type="button" class="btn btn-ghost btn-sm" @click="applyBulkStock" :disabled="bulkStock === null || bulkStock === ''">→ Hepsine uygula</button>
						</div>
					</header>
					<div class="card-body">
						<div v-if="!form.variants.length" class="variant-empty">
							Varyant yok. Beden veya renk seçip "Varyantları Oluştur" butonuna bas, ya da aşağıdan tek satır ekle.
							<button type="button" class="btn btn-secondary btn-sm" @click="addEmptyVariant">+ Boş varyant ekle</button>
						</div>

						<div v-else class="variant-table">
							<div class="variant-thead">
								<span>Renk</span>
								<span>Beden</span>
								<span>SKU</span>
								<span>Fiyat</span>
								<span>Eski Fiyat</span>
								<span>Stok</span>
								<span></span>
							</div>
							<div v-for="(v, idx) in form.variants" :key="v.key" class="variant-row">
								<span class="variant-color">
									<span v-if="v.color_name" class="color-dot" :style="{ background: v.color_hex }" :title="v.color_name"></span>
									<span v-else class="color-dot color-dot-empty" title="Renk yok"></span>
									<span class="variant-color-name">{{ v.color_name || '—' }}</span>
								</span>
								<span class="variant-size">{{ v.size || '—' }}</span>
								<input v-model="v.sku" type="text" class="form-input variant-sku" placeholder="SKU" />
								<input v-model.number="v.price" type="number" step="0.01" min="0" class="form-input" placeholder="Fiyat" />
								<input v-model.number="v.old_price" type="number" step="0.01" min="0" class="form-input" placeholder="—" />
								<input v-model.number="v.stock" type="number" min="0" class="form-input" placeholder="Stok" />
								<button type="button" class="variant-remove" @click="removeVariant(idx)" aria-label="Sil">
									<svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
										<path d="M18 6L6 18M6 6l12 12" />
									</svg>
								</button>
							</div>
							<button type="button" class="btn btn-ghost btn-sm add-row-btn" @click="addEmptyVariant">+ Satır ekle</button>
						</div>

						<span v-if="errors.variants" class="form-error">{{ errors.variants }}</span>
					</div>
				</section>
			</div>

			<!-- ── Resimler ── -->
			<div v-show="activeTab === 'images'" class="tab-panel" role="tabpanel">
				<section class="card">
					<header class="card-head">
						<h2>Görseller</h2>
						<p>İlk görsel otomatik kapak olur. Görseli olmayan ürünlerde "hazırlanıyor" görseli gösterilir.</p>
					</header>
					<div class="card-body">
						<div v-if="existingImages.length || newImages.length" class="img-grid">
							<div v-for="img in existingImages" :key="'e' + img.id" class="img-thumb" :class="{ cover: img.is_cover }">
								<img :src="img.url" alt="" />
								<span v-if="img.is_cover" class="img-badge cover-badge">Kapak</span>
								<div class="img-actions">
									<button type="button" v-if="!img.is_cover" class="img-act" title="Kapak yap" @click="setCover(img)">★</button>
									<button type="button" class="img-act del" title="Sil" @click="deleteExisting(img)">✕</button>
								</div>
							</div>
							<div v-for="(img, i) in newImages" :key="'n' + i" class="img-thumb new">
								<img :src="img.preview" alt="" />
								<span class="img-badge new-badge">Yeni</span>
								<div class="img-actions">
									<button type="button" class="img-act del" title="Kaldır" @click="removeNewImage(i)">✕</button>
								</div>
							</div>
						</div>

						<label class="img-upload">
							<input type="file" accept="image/*" multiple hidden @change="onPickImages" />
							<svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
								<path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M17 8l-5-5-5 5M12 3v12" />
							</svg>
							<span>Görsel ekle (PNG/JPG/WEBP, en fazla 5MB)</span>
						</label>
						<span v-if="errors['images.0'] || errors.images" class="form-error">{{ errors['images.0'] || errors.images }}</span>
					</div>
				</section>
			</div>

			<!-- ── Özellikler ── -->
			<div v-show="activeTab === 'attributes'" class="tab-panel" role="tabpanel">
				<section class="card">
					<header class="card-head">
						<h2>Üretim Bilgileri</h2>
						<p>Kumaş, menşei ve bakım talimatları (opsiyonel).</p>
					</header>
					<div class="card-body">
						<div class="form-grid-2">
							<div class="form-group">
								<label class="form-label">Kumaş İçeriği</label>
								<input v-model="form.material" class="form-input" type="text" placeholder="örn. %100 Pamuk" />
								<span v-if="errors.material" class="form-error">{{ errors.material }}</span>
							</div>
							<div class="form-group">
								<label class="form-label">Menşei Ülke</label>
								<input v-model="form.origin_country" class="form-input" type="text" maxlength="2" placeholder="TR" @input="form.origin_country = (form.origin_country || '').toUpperCase()" />
								<span v-if="errors.origin_country" class="form-error">{{ errors.origin_country }}</span>
							</div>
						</div>
						<div class="form-group">
							<label class="form-label">Yıkama / Bakım Talimatları</label>
							<textarea v-model="form.care_instructions" class="form-input" rows="3" placeholder="örn. 30°C'de yıkanır, ütülenmez…" />
							<span v-if="errors.care_instructions" class="form-error">{{ errors.care_instructions }}</span>
						</div>
					</div>
				</section>
			</div>

			<!-- ── Diğer ── -->
			<div v-show="activeTab === 'other'" class="tab-panel" role="tabpanel">
				<section class="card">
					<header class="card-head">
						<h2>Özel Kodlar & Detaylar</h2>
						<p>Barkod, üretici kodları ve menşei detayları (opsiyonel).</p>
					</header>
					<div class="card-body">
						<div class="form-grid-2">
							<div class="form-group">
								<label class="form-label">Barkod / GTIN</label>
								<input v-model="form.barcode" class="form-input" type="text" maxlength="64" placeholder="örn. 8690000000000" />
								<span v-if="errors.barcode" class="form-error">{{ errors.barcode }}</span>
							</div>
							<div class="form-group">
								<label class="form-label">Üretici / Tedarikçi Kodu</label>
								<input v-model="form.manufacturer_code" class="form-input" type="text" maxlength="64" placeholder="İç üretici kodu" />
								<span v-if="errors.manufacturer_code" class="form-error">{{ errors.manufacturer_code }}</span>
							</div>
						</div>
						<div class="form-group">
							<label class="form-label">GTIP / HS Kodu</label>
							<input v-model="form.gtip_code" class="form-input" type="text" maxlength="32" placeholder="Gümrük tarife pozisyon kodu" />
							<span v-if="errors.gtip_code" class="form-error">{{ errors.gtip_code }}</span>
						</div>
						<label class="toggle-row">
							<input type="checkbox" v-model="form.is_domestic" />
							<span class="toggle-text">Yerli üretim ürünü</span>
						</label>
					</div>
				</section>
			</div>
		</div>
	</div>
</template>

<script setup>
import { reactive, ref, computed, onMounted, inject } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Breadcrumb from '@/Components/Breadcrumb.vue'
import CustomSelect from '@/Components/CustomSelect.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
	product: { type: Object, default: null },
	categories: { type: Array, default: () => [] },
	brands: { type: Array, default: () => [] },
})

const showToast = inject('showToast')
const page = usePage()

const isEdit = computed(() => !!props.product)

/* ── Sekmeler ── */
const tabs = [
	{ key: 'general',    label: 'Genel Bilgiler' },
	{ key: 'seo',        label: 'SEO' },
	{ key: 'price',      label: 'Fiyat-Kargo' },
	{ key: 'variant',    label: 'Stok-Varyant' },
	{ key: 'images',     label: 'Resimler' },
	{ key: 'attributes', label: 'Özellikler' },
	{ key: 'other',      label: 'Diğer' },
]
const activeTab = ref('general')

// Hata alanı → ait olduğu sekme.
const fieldTabMap = {
	name: 'general', sku: 'general', category_id: 'general', brand_id: 'general', gender: 'general',
	slug: 'seo', meta_title: 'seo', meta_description: 'seo', meta_keywords: 'seo',
	free_shipping: 'price', weight: 'price', desi: 'price', shipping_time: 'price', shipping_fee: 'price',
	market_price: 'price', purchase_price: 'price',
	variants: 'variant',
	images: 'images',
	material: 'attributes', origin_country: 'attributes', care_instructions: 'attributes',
	barcode: 'other', is_domestic: 'other', manufacturer_code: 'other', gtip_code: 'other',
}
function tabForField(field) {
	const base = String(field).split('.')[0]
	return fieldTabMap[base] || 'general'
}

/* ── Sunucudan gelen doğrulama hataları ── */
const errors = ref({})
const busy = ref(false)
const hasErrors = computed(() => Object.keys(errors.value).length > 0)

const tabsWithErrors = computed(() => {
	const set = new Set()
	for (const field of Object.keys(errors.value)) set.add(tabForField(field))
	return set
})

/* ── Görseller ── */
const newImages = ref([])      // { file, preview }
const existingImages = ref([]) // { id, url, is_cover }

function onPickImages(e) {
	const files = Array.from(e.target.files || [])
	for (const f of files) {
		newImages.value.push({ file: f, preview: URL.createObjectURL(f) })
	}
	e.target.value = ''
}
function removeNewImage(i) {
	URL.revokeObjectURL(newImages.value[i]?.preview)
	newImages.value.splice(i, 1)
}
function deleteExisting(img) {
	router.delete(`/products/images/${img.id}`, {
		preserveScroll: true,
		preserveState: true,
		onSuccess: () => showToast?.({ type: 'info', title: 'Görsel silindi' }),
		onError: () => showToast?.({ type: 'error', title: 'Görsel silinemedi' }),
	})
	existingImages.value = existingImages.value.filter((x) => x.id !== img.id)
}
function setCover(img) {
	router.put(`/products/images/${img.id}`, { is_cover: true }, {
		preserveScroll: true,
		preserveState: true,
		onSuccess: () => showToast?.({ type: 'success', title: 'Kapak güncellendi' }),
		onError: () => showToast?.({ type: 'error', title: 'Kapak güncellenemedi' }),
	})
	existingImages.value = existingImages.value.map((x) => ({ ...x, is_cover: x.id === img.id }))
}

const sizeOptions = ['XS', 'S', 'M', 'L', 'XL', 'XXL', '28', '30', '32', '34', '36', '38', '40', '42', '44']

const colorPalette = [
	{ name: 'Siyah',    hex: '#111111' }, { name: 'Beyaz',    hex: '#fafafa' },
	{ name: 'Lacivert', hex: '#1e3a8a' }, { name: 'Gri',      hex: '#6b7280' },
	{ name: 'Bej',      hex: '#d6c7a8' }, { name: 'Bordo',    hex: '#7c1f1f' },
	{ name: 'Yeşil',    hex: '#15803d' }, { name: 'Mavi',     hex: '#2563eb' },
	{ name: 'Kırmızı',  hex: '#dc2626' }, { name: 'Sarı',     hex: '#facc15' },
	{ name: 'Pembe',    hex: '#ec4899' }, { name: 'Mor',      hex: '#7c3aed' },
]

const genderOptions = [
	{ value: 'Erkek',  label: 'Erkek' },
	{ value: 'Kadın',  label: 'Kadın' },
	{ value: 'Unisex', label: 'Unisex' },
]

const categoryOptions = computed(() =>
	props.categories.map((c) => ({ value: c.id, label: c.name ?? c.label })),
)

const brandOptions = computed(() => [
	{ value: null, label: '— Marka yok —' },
	...props.brands.map((b) => ({ value: b.id, label: b.name ?? b.label })),
])

const skuTouched = ref(false)
const customColor = reactive({ name: '', hex: '#888888' })

const selectedSizes = ref([])   // string[]
const selectedColors = ref([])  // {name, hex}[]

const bulkPrice = ref(null)
const bulkStock = ref(null)

let variantKey = 0
const newKey = () => `v${++variantKey}`

const defaultForm = () => ({
	name: '',
	slug: '',
	sku: '',
	category_id: null,
	brand_id: null,
	gender: 'Unisex',
	is_new: false,
	free_shipping: false,
	market_price: null,
	purchase_price: null,
	care_instructions: '',
	material: '',
	origin_country: 'TR',
	// SEO
	meta_title: '',
	meta_description: '',
	meta_keywords: '',
	// Kargo
	weight: null,
	desi: null,
	shipping_time: '',
	shipping_fee: null,
	// Diğer
	barcode: '',
	is_domestic: false,
	manufacturer_code: '',
	gtip_code: '',
	variants: [],
})

const form = reactive(defaultForm())

const matrixCount = computed(() => {
	const s = selectedSizes.value.length || 1
	const c = selectedColors.value.length || 1
	return s * c
})

const canGenerate = computed(() => selectedSizes.value.length > 0 || selectedColors.value.length > 0)

function toggleSize(s) {
	const i = selectedSizes.value.indexOf(s)
	if (i >= 0) selectedSizes.value.splice(i, 1)
	else selectedSizes.value.push(s)
}

function hasColor(name) {
	return selectedColors.value.some((c) => c.name === name)
}

function toggleColor(c) {
	const i = selectedColors.value.findIndex((x) => x.name === c.name)
	if (i >= 0) selectedColors.value.splice(i, 1)
	else selectedColors.value.push({ name: c.name, hex: c.hex })
}

function addCustomColor() {
	if (!customColor.name.trim()) return
	if (!hasColor(customColor.name.trim())) {
		selectedColors.value.push({ name: customColor.name.trim(), hex: customColor.hex })
	}
	customColor.name = ''
	customColor.hex = '#888888'
}

function slugFragment(str) {
	return String(str || '')
		.toUpperCase()
		.replace(/[ĞÜŞİÖÇI]/g, (ch) => ({ Ğ: 'G', Ü: 'U', Ş: 'S', İ: 'I', Ö: 'O', Ç: 'C', I: 'I' }[ch]))
		.replace(/[^A-Z0-9]/g, '')
		.slice(0, 3) || 'PRD'
}

// SEO slug girişini normalize eder (küçük harf, tireli, Türkçe karakter çevirisi).
function slugify(str) {
	return String(str || '')
		.toLowerCase()
		.replace(/[ğüşıöç]/g, (ch) => ({ ğ: 'g', ü: 'u', ş: 's', ı: 'i', ö: 'o', ç: 'c' }[ch]))
		.replace(/[^a-z0-9]+/g, '-')
		.replace(/^-+|-+$/g, '')
}

function generateBaseSku() {
	const brand = props.brands.find((b) => b.id === form.brand_id)
	const brandFrag = slugFragment(brand?.name ?? brand?.label ?? 'PRD')
	const nameFrag  = slugFragment(form.name)
	const rand = String(Math.floor(Math.random() * 900) + 100)
	form.sku = `TEK-${brandFrag}-${nameFrag}-${rand}`
	skuTouched.value = true
}

function onNameInput() {
	if (!skuTouched.value && form.name && form.brand_id) generateBaseSku()
}

function variantSku(base, size, colorName) {
	const parts = [base || 'TEK']
	if (colorName) parts.push(slugFragment(colorName))
	if (size) parts.push(String(size).replace(/[^A-Za-z0-9]/g, ''))
	return parts.join('-')
}

/**
 * selectedSizes × selectedColors kombinasyonlarını üretir.
 * Mevcut varyantları (size + color_name) anahtarıyla eşleştirerek
 * stok/fiyat değerlerini korur.
 */
function regenerateVariants() {
	const existingByKey = new Map(
		form.variants.map((v) => [`${v.size || ''}|${v.color_name || ''}`, v]),
	)

	const sizes = selectedSizes.value.length ? [...selectedSizes.value] : [null]
	const colors = selectedColors.value.length ? [...selectedColors.value] : [{ name: null, hex: null }]

	const next = []
	for (const color of colors) {
		for (const size of sizes) {
			const key = `${size || ''}|${color.name || ''}`
			const existing = existingByKey.get(key)
			next.push({
				key: existing?.key ?? newKey(),
				size,
				color_name: color.name,
				color_hex: color.hex,
				sku: existing?.sku || variantSku(form.sku, size, color.name),
				price: existing?.price ?? Number(bulkPrice.value) ?? 0,
				old_price: existing?.old_price ?? null,
				stock: existing?.stock ?? Number(bulkStock.value) ?? 0,
			})
		}
	}
	form.variants = next
}

function addEmptyVariant() {
	form.variants.push({
		key: newKey(),
		size: null,
		color_name: null,
		color_hex: null,
		sku: variantSku(form.sku, null, null) + '-' + (form.variants.length + 1),
		price: 0,
		old_price: null,
		stock: 0,
	})
}

function removeVariant(idx) {
	form.variants.splice(idx, 1)
}

function applyBulkPrice() {
	if (!bulkPrice.value) return
	form.variants.forEach((v) => { v.price = Number(bulkPrice.value) })
}

function applyBulkStock() {
	if (bulkStock.value === null || bulkStock.value === '') return
	form.variants.forEach((v) => { v.stock = Number(bulkStock.value) })
}

function numOrNull(v) {
	return v !== null && v !== '' && v !== undefined ? Number(v) : null
}

function buildPayload() {
	return {
		name: form.name.trim(),
		slug: form.slug?.trim() || null,
		sku: form.sku.trim(),
		category_id: form.category_id,
		brand_id: form.brand_id || null,
		gender: form.gender,
		market_price: numOrNull(form.market_price),
		purchase_price: numOrNull(form.purchase_price),
		is_new: form.is_new ? 1 : 0,
		free_shipping: form.free_shipping ? 1 : 0,
		care_instructions: form.care_instructions?.trim() || null,
		material: form.material?.trim() || null,
		origin_country: (form.origin_country || 'TR').toUpperCase(),
		// SEO
		meta_title: form.meta_title?.trim() || null,
		meta_description: form.meta_description?.trim() || null,
		meta_keywords: form.meta_keywords?.trim() || null,
		// Kargo
		weight: numOrNull(form.weight),
		desi: numOrNull(form.desi),
		shipping_time: form.shipping_time?.trim() || null,
		shipping_fee: form.free_shipping ? null : numOrNull(form.shipping_fee),
		// Diğer
		barcode: form.barcode?.trim() || null,
		is_domestic: form.is_domestic ? 1 : 0,
		manufacturer_code: form.manufacturer_code?.trim() || null,
		gtip_code: form.gtip_code?.trim() || null,
		variants: form.variants.map((v) => ({
			size: v.size || null,
			color_name: v.color_name || null,
			color_hex: v.color_hex || null,
			sku: String(v.sku || '').trim(),
			price: Number(v.price) || 0,
			old_price: v.old_price ? Number(v.old_price) : null,
			stock: Number(v.stock) || 0,
		})),
		images: newImages.value.map((x) => x.file),
	}
}

function submit() {
	if (busy.value) return
	busy.value = true
	errors.value = {}

	const payload = buildPayload()
	const hasFiles = Array.isArray(payload.images) && payload.images.length > 0

	const opts = {
		preserveScroll: true,
		onSuccess: () => {
			showToast?.({
				type: 'success',
				title: isEdit.value ? 'Ürün Güncellendi' : 'Ürün Eklendi',
				message: payload.name,
			})
		},
		onError: (errs) => {
			errors.value = errs
			const first = Object.values(errs)[0]
			showToast?.({ type: 'error', title: 'Kayıt Başarısız', message: first || 'Doğrulama hatası.' })
			focusFirstErrorTab(errs)
		},
		onFinish: () => { busy.value = false },
	}

	if (isEdit.value) {
		const id = props.product.id
		// Dosya varken PUT multipart desteklenmez → POST + _method spoof.
		if (hasFiles) {
			router.post(`/products/${id}`, { ...payload, _method: 'put' }, opts)
		} else {
			router.put(`/products/${id}`, payload, opts)
		}
	} else {
		router.post('/products', payload, opts)
	}
}

// Hata içeren ilk sekmeye (tab sırasına göre) geç ve sayfayı yukarı kaydır.
function focusFirstErrorTab(errs) {
	const errorTabs = new Set(Object.keys(errs).map(tabForField))
	const target = tabs.find((t) => errorTabs.has(t.key))
	if (target) activeTab.value = target.key
	window.scrollTo({ top: 0, behavior: 'smooth' })
}

/* ── Düzenleme: gelen üründen formu doldur ── */
function loadFromProduct(p) {
	skuTouched.value = true
	Object.assign(form, {
		name: p.name || '',
		slug: p.slug || '',
		sku: p.sku || '',
		category_id: p.category_id ?? null,
		brand_id: p.brand_id ?? null,
		gender: p.gender || 'Unisex',
		market_price: p.marketPrice ?? null,
		purchase_price: p.purchasePrice ?? null,
		is_new: !!p.isNew,
		free_shipping: !!p.freeShipping,
		care_instructions: p.careInstructions ?? '',
		material: p.material ?? '',
		origin_country: p.originCountry ?? 'TR',
		// SEO
		meta_title: p.metaTitle ?? '',
		meta_description: p.metaDescription ?? '',
		meta_keywords: p.metaKeywords ?? '',
		// Kargo
		weight: p.weight ?? null,
		desi: p.desi ?? null,
		shipping_time: p.shippingTime ?? '',
		shipping_fee: p.shippingFee ?? null,
		// Diğer
		barcode: p.barcode ?? '',
		is_domestic: !!p.isDomestic,
		manufacturer_code: p.manufacturerCode ?? '',
		gtip_code: p.gtipCode ?? '',
		variants: [],
	})

	const variants = Array.isArray(p.variants) ? p.variants : []
	const sizesSet = new Set()
	const colorMap = new Map()
	for (const v of variants) {
		if (v.size) sizesSet.add(v.size)
		if (v.color_name) colorMap.set(v.color_name, v.color_hex)
	}
	selectedSizes.value = [...sizesSet]
	selectedColors.value = [...colorMap.entries()].map(([name, hex]) => ({ name, hex }))

	form.variants = variants.map((v) => ({
		key: newKey(),
		size: v.size,
		color_name: v.color_name,
		color_hex: v.color_hex,
		sku: v.sku,
		price: Number(v.price) || 0,
		old_price: v.old_price !== null ? Number(v.old_price) : null,
		stock: Number(v.stock) || 0,
	}))

	existingImages.value = Array.isArray(p.images) ? p.images.map((x) => ({ ...x })) : []
}

onMounted(() => {
	if (props.product) loadFromProduct(props.product)
	// İlk yüklemede sunucudan gelmiş hata varsa göster ve ilgili sekmeye geç.
	if (page.props.errors && Object.keys(page.props.errors).length) {
		errors.value = { ...page.props.errors }
		focusFirstErrorTab(errors.value)
	}
})
</script>

<style scoped>
.page-product-form { padding-bottom: 40px; }

/* ── Topbar ── */
.form-topbar {
	position: sticky;
	top: 0;
	z-index: 20;
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 16px;
	background: rgba(255, 255, 255, 0.92);
	backdrop-filter: blur(8px);
	border: 1px solid #ebebf0;
	border-radius: 14px;
	padding: 12px 16px;
	margin-bottom: 18px;
	box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
}
.topbar-left { display: flex; align-items: center; gap: 12px; min-width: 0; }
.back-btn {
	width: 36px; height: 36px; flex-shrink: 0;
	display: flex; align-items: center; justify-content: center;
	border: 1px solid #e8e8f0; border-radius: 9px;
	background: #fff; color: #555; cursor: pointer;
	transition: all .12s; text-decoration: none;
}
.back-btn:hover { background: #f5f5f8; color: #1a1a2e; }
.topbar-title { font-size: 18px; font-weight: 700; color: #1a1a2e; line-height: 1.2; }
.topbar-sub {
	font-size: 12.5px; color: #888; margin-top: 2px;
	display: flex; align-items: center; gap: 8px;
	white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.topbar-sku {
	font-family: 'SF Mono', Consolas, monospace;
	font-size: 11px; color: rgb(var(--color-primary-hover));
	background: #f3eefe; padding: 1px 7px; border-radius: 5px;
}
.topbar-actions { display: flex; gap: 8px; flex-shrink: 0; }
.btn.disabled { pointer-events: none; opacity: .55; }

/* ── Hata bandı ── */
.error-banner {
	display: flex; align-items: center; gap: 10px;
	background: #fef2f2; border: 1px solid #fecaca;
	color: #b91c1c; font-size: 13px; font-weight: 500;
	padding: 11px 14px; border-radius: 10px; margin-bottom: 16px;
}
.error-banner svg { flex-shrink: 0; }

/* ── Sekme şeridi ── */
.tab-strip {
	display: flex;
	gap: 4px;
	flex-wrap: wrap;
	background: #fff;
	border: 1px solid #ebebf0;
	border-radius: 12px;
	padding: 6px;
	margin-bottom: 18px;
	box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
}
.tab-btn {
	position: relative;
	display: inline-flex;
	align-items: center;
	gap: 6px;
	border: none;
	background: none;
	font-family: inherit;
	font-size: 13px;
	font-weight: 600;
	color: #666;
	padding: 8px 14px;
	border-radius: 8px;
	cursor: pointer;
	transition: all .12s;
	white-space: nowrap;
}
.tab-btn:hover { background: #f5f5f8; color: #1a1a2e; }
.tab-btn.active { background: #1a1a2e; color: #fff; }
.tab-error-dot {
	width: 7px; height: 7px;
	border-radius: 50%;
	background: #ef4444;
	flex-shrink: 0;
	box-shadow: 0 0 0 2px rgba(239, 68, 68, 0.18);
}
.tab-btn.active .tab-error-dot { box-shadow: 0 0 0 2px rgba(255, 255, 255, 0.25); }

/* ── Panel ── */
.tab-panel { display: flex; flex-direction: column; gap: 18px; }

/* ── Kart ── */
.card {
	background: #fff;
	border: 1px solid #ebebf0;
	border-radius: 14px;
	box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
	overflow: hidden;
}
/* Select dropdown'ı kesilmesin diye: içinde CustomSelect olan kart taşmaya izin verir. */
.card.card-overflow { overflow: visible; }
.tab-panel-overflow { overflow: visible; }
.card-head {
	padding: 14px 18px;
	border-bottom: 1px solid #f0f0f5;
}
.card-head.flex-row {
	display: flex; align-items: flex-start; justify-content: space-between;
	gap: 12px; flex-wrap: wrap;
}
.card-head h2 { font-size: 14.5px; font-weight: 700; color: #1a1a2e; }
.card-head p { font-size: 12px; color: #999; margin-top: 3px; }
.card-body {
	padding: 18px;
	display: flex; flex-direction: column; gap: 14px;
}

/* ── Form temel ── */
.form-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
@media (max-width: 640px) {
	.form-grid-2 { grid-template-columns: 1fr; }
}
.form-group { display: flex; flex-direction: column; gap: 5px; }
.form-label { font-size: 12.5px; font-weight: 600; color: #444; }
.required { color: #ef4444; margin-left: 2px; }
.form-error { color: #dc2626; font-size: 11px; margin-top: 2px; }
.form-help { color: #aaa; font-size: 11px; margin-top: 2px; }
.hint-inline { color: #888; font-weight: 500; margin-left: 4px; }
.inline-hint {
	font-size: 12.5px; color: #888;
	padding: 14px; border: 1px dashed #d8d8e8; border-radius: 10px;
	text-align: center;
}

.sku-row { display: flex; gap: 6px; align-items: center; }
.sku-row .form-input { flex: 1; }
.sku-gen { padding: 0 10px; height: 36px; font-size: 16px; line-height: 1; }

.bulk-price-row { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }

/* ── Görseller ── */
.img-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(104px, 1fr)); gap: 10px; }
.img-thumb { position: relative; aspect-ratio: 1; border-radius: 10px; overflow: hidden; border: 1.5px solid #e8e8f0; background: #f5f5fa; }
.img-thumb.cover { border-color: rgb(var(--color-primary)); box-shadow: 0 0 0 2px rgb(var(--color-primary) / .2); }
.img-thumb.new { border-style: dashed; border-color: rgb(var(--color-primary) / .5); }
.img-thumb img { width: 100%; height: 100%; object-fit: cover; display: block; }
.img-badge { position: absolute; top: 5px; left: 5px; font-size: 9.5px; font-weight: 700; padding: 1px 6px; border-radius: 5px; }
.cover-badge { background: rgb(var(--color-primary)); color: #fff; }
.new-badge { background: rgb(var(--color-primary)); color: #fff; }
.img-actions { position: absolute; top: 4px; right: 4px; display: flex; gap: 4px; opacity: 0; transition: opacity .12s; }
.img-thumb:hover .img-actions { opacity: 1; }
.img-act { width: 22px; height: 22px; border: none; border-radius: 6px; background: rgba(26,26,46,.7); color: #fff; font-size: 11px; cursor: pointer; display: flex; align-items: center; justify-content: center; }
.img-act:hover { background: rgba(26,26,46,.9); }
.img-act.del:hover { background: #dc2626; }
.img-upload { display: flex; align-items: center; justify-content: center; gap: 10px; padding: 16px; border: 1.5px dashed rgb(var(--color-primary) / .35); border-radius: 10px; cursor: pointer; color: rgb(var(--color-primary)); font-size: 12.5px; font-weight: 600; background: rgb(var(--color-primary-soft)); transition: all .12s; }
.img-upload:hover { border-color: rgb(var(--color-primary) / .5); background: #f3eefe; }

/* ── Chips / renkler ── */
.chips-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(54px, 1fr)); gap: 6px; }
.chip {
	background: #fff; border: 1.5px solid #e8e8f0; color: #444;
	font-family: inherit; font-size: 12px; font-weight: 600;
	padding: 7px 0; border-radius: 8px; cursor: pointer;
	text-align: center; transition: all .12s;
}
.chip:hover { border-color: #c0c0d8; }
.chip.active { background: #1a1a2e; color: #fff; border-color: #1a1a2e; }

.color-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(110px, 1fr)); gap: 6px; }
.color-chip {
	display: flex; align-items: center; gap: 8px;
	padding: 7px 10px; background: #fff;
	border: 1.5px solid #e8e8f0; border-radius: 8px;
	cursor: pointer; transition: all .12s;
	font-family: inherit; font-size: 11.5px; color: #444; font-weight: 600;
}
.color-chip:hover { border-color: #c0c0d8; }
.color-chip.active { border-color: rgb(var(--color-primary)); background: rgb(var(--color-primary-soft)); color: #1a1a2e; }
.color-swatch { width: 16px; height: 16px; border-radius: 50%; border: 1.5px solid rgba(0, 0, 0, 0.08); flex-shrink: 0; }
.color-name { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

.color-custom { margin-top: 2px; }
.color-custom summary { font-size: 12px; color: rgb(var(--color-primary)); cursor: pointer; font-weight: 600; padding: 4px 0; list-style: none; }
.color-custom summary::-webkit-details-marker { display: none; }
.color-custom-row { display: flex; gap: 6px; margin-top: 6px; align-items: center; }
.color-custom-row .form-input { flex: 1; }
.color-hex { max-width: 50px; padding: 2px; height: 36px; }

.regen-btn { align-self: flex-start; }

/* ── Varyantlar ── */
.variant-badge {
	display: inline-block; background: rgb(var(--color-primary)); color: #fff;
	font-size: 11px; font-weight: 700; padding: 1px 8px;
	border-radius: 999px; margin-left: 6px;
}
.bulk-controls { display: flex; align-items: center; gap: 4px; flex-wrap: wrap; }
.bulk-input { width: 90px; height: 30px; font-size: 11.5px; padding: 0 8px; }

.variant-empty {
	display: flex; flex-direction: column; align-items: center; gap: 8px;
	padding: 28px; border: 1px dashed #d8d8e8; border-radius: 10px;
	color: #888; font-size: 12.5px; text-align: center;
}

.variant-table { display: flex; flex-direction: column; gap: 6px; }
.variant-thead, .variant-row {
	display: grid;
	grid-template-columns: 1.4fr 60px 1.6fr 1fr 1fr 0.9fr 28px;
	align-items: center; gap: 8px;
}
.variant-thead {
	padding: 0 10px 4px;
	font-size: 10.5px; font-weight: 700; color: #aaa;
	text-transform: uppercase; letter-spacing: .04em;
}
.variant-row {
	padding: 8px 10px;
	background: #fafafe; border: 1px solid #f0f0f6; border-radius: 8px;
}
.variant-color { display: flex; align-items: center; gap: 6px; min-width: 0; }
.color-dot { width: 14px; height: 14px; border-radius: 50%; border: 1.5px solid rgba(0, 0, 0, 0.08); flex-shrink: 0; }
.color-dot-empty { background: #e8e8f0; border-style: dashed; }
.variant-color-name { font-size: 11.5px; font-weight: 600; color: #1a1a2e; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.variant-size { font-size: 11.5px; font-weight: 700; color: #1a1a2e; text-align: center; }
.variant-row .form-input { height: 30px; font-size: 11.5px; padding: 0 8px; }
.variant-sku { font-family: 'SF Mono', Consolas, monospace; font-size: 11px !important; }
.variant-remove {
	background: #fff; border: 1px solid #fecaca; color: #dc2626;
	width: 24px; height: 24px; border-radius: 6px;
	display: flex; align-items: center; justify-content: center;
	cursor: pointer; transition: all .12s;
}
.variant-remove:hover { background: #fef2f2; }
.add-row-btn { align-self: flex-start; margin-top: 4px; }

/* ── Toggle ── */
.toggle-row {
	display: flex; align-items: center; gap: 10px;
	padding: 9px 11px; border-radius: 8px;
	background: #fafafe; border: 1px solid #f0f0f6; cursor: pointer;
}
.toggle-row input { accent-color: rgb(var(--color-primary)); width: 14px; height: 14px; cursor: pointer; }
.toggle-text { font-size: 12.5px; color: #444; font-weight: 600; }
</style>
