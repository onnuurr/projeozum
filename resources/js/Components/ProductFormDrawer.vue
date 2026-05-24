<template>
	<Teleport to="body">
		<aside class="drawer product-form-drawer" :class="{ open: modelValue }" role="dialog" aria-modal="true">
			<div class="drawer-header">
				<div class="drawer-title">
					<div class="drawer-title-icon" :class="isEdit ? 'icon-edit' : 'icon-add'">
						<svg v-if="isEdit" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
							<path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7" />
							<path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z" />
						</svg>
						<svg v-else width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
							<path d="M12 5v14M5 12h14" />
						</svg>
					</div>
					<div>
						<h4>{{ isEdit ? 'Ürünü Düzenle' : 'Yeni Ürün Ekle' }}</h4>
						<p>{{ isEdit ? product.name : 'Ürün kataloğuna yeni bir ürün ekleyin' }}</p>
					</div>
				</div>
				<button class="drawer-close" @click="close" aria-label="Kapat">
					<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
						<path d="M18 6L6 18M6 6l12 12" />
					</svg>
				</button>
			</div>

			<div class="drawer-body">
				<!-- Temel bilgiler -->
				<div class="form-section">
					<div class="drawer-section-title">Temel Bilgiler</div>
					<div class="form-grid-2">
						<div class="form-group full">
							<label class="form-label">Ürün Adı <span class="required">*</span></label>
							<input v-model="form.name" class="form-input" type="text" placeholder="örn. Basic Crew Tişört" @input="onNameInput" />
							<span v-if="errors.name" class="form-error">{{ errors.name }}</span>
						</div>

						<div class="form-group">
							<label class="form-label">Ana SKU <span class="required">*</span></label>
							<div class="sku-row">
								<input v-model="form.sku" class="form-input" type="text" placeholder="TEK-XXX-001" @input="skuTouched = true" />
								<button type="button" class="btn btn-ghost btn-sm sku-gen" :disabled="!form.name || !form.brand_id" @click="generateBaseSku" title="Otomatik üret">↻</button>
							</div>
							<span v-if="errors.sku" class="form-error">{{ errors.sku }}</span>
						</div>

						<CustomSelect label="Cinsiyet" v-model="form.gender" :options="genderOptions" />
						<CustomSelect label="Kategori" v-model="form.category_id" :options="categoryOptions" placeholder="Kategori seçiniz..." />
						<CustomSelect label="Marka" v-model="form.brand_id" :options="brandOptions" placeholder="— Marka yok —" />
					</div>
				</div>

				<!-- Varyant seçimleri -->
				<div class="form-section">
					<div class="drawer-section-title">Bedenler & Renkler</div>
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

				<!-- Varyant listesi -->
				<div class="form-section">
					<div class="drawer-section-title flex-row">
						<span>Varyantlar <span v-if="form.variants.length" class="variant-badge">{{ form.variants.length }}</span></span>
						<div class="bulk-controls" v-if="form.variants.length">
							<input v-model.number="bulkPrice" type="number" step="0.01" class="form-input bulk-input" placeholder="Fiyat" />
							<button type="button" class="btn btn-ghost btn-sm" @click="applyBulkPrice" :disabled="!bulkPrice">→ Hepsine uygula</button>
							<input v-model.number="bulkStock" type="number" min="0" class="form-input bulk-input" placeholder="Stok" />
							<button type="button" class="btn btn-ghost btn-sm" @click="applyBulkStock" :disabled="bulkStock === null || bulkStock === ''">→ Hepsine uygula</button>
						</div>
					</div>

					<div v-if="!form.variants.length" class="variant-empty">
						Varyant yok. Beden veya renk seçip "Varyantları Oluştur" butonuna bas, ya da aşağıdan tek satır ekle.
						<button type="button" class="btn btn-secondary btn-sm" @click="addEmptyVariant">+ Boş varyant ekle</button>
					</div>

					<div v-else class="variant-list">
						<div v-for="(v, idx) in form.variants" :key="v.key" class="variant-row">
							<span v-if="v.color_name" class="color-dot" :style="{ background: v.color_hex }" :title="v.color_name"></span>
							<span v-else class="color-dot color-dot-empty" title="Renk yok"></span>
							<span class="variant-color-name">{{ v.color_name || '—' }}</span>
							<span class="variant-sep">·</span>
							<span class="variant-size">{{ v.size || '—' }}</span>

							<input v-model="v.sku" type="text" class="form-input variant-sku" placeholder="SKU" />
							<input v-model.number="v.price" type="number" step="0.01" min="0" class="form-input variant-price" placeholder="Fiyat" />
							<input v-model.number="v.old_price" type="number" step="0.01" min="0" class="form-input variant-old-price" placeholder="Eski fiyat" />
							<input v-model.number="v.stock" type="number" min="0" class="form-input variant-stock" placeholder="Stok" />

							<button type="button" class="variant-remove" @click="removeVariant(idx)" aria-label="Sil">
								<svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
									<path d="M18 6L6 18M6 6l12 12" />
								</svg>
							</button>
						</div>
					</div>

					<div class="variant-summary" v-if="form.variants.length">
						Toplam stok: <strong>{{ totalStock }}</strong> · Fiyat aralığı: <strong>₺{{ minPrice }}</strong> – <strong>₺{{ maxPrice }}</strong>
					</div>

					<span v-if="errors.variants" class="form-error">{{ errors.variants }}</span>
				</div>

				<!-- Üretim Bilgileri -->
				<div class="form-section">
					<div class="drawer-section-title">Üretim Bilgileri</div>
					<div class="form-grid-2">
						<div class="form-group">
							<label class="form-label">Kumaş İçeriği</label>
							<input v-model="form.material" class="form-input" type="text" placeholder="örn. %100 Pamuk" />
							<span v-if="errors.material" class="form-error">{{ errors.material }}</span>
						</div>
						<div class="form-group">
							<label class="form-label">Menşei Ülke</label>
							<input v-model="form.origin_country" class="form-input" type="text" maxlength="2" placeholder="TR" @input="form.origin_country = form.origin_country.toUpperCase()" />
							<span v-if="errors.origin_country" class="form-error">{{ errors.origin_country }}</span>
						</div>
						<div class="form-group full">
							<label class="form-label">Yıkama Talimatları</label>
							<textarea v-model="form.care_instructions" class="form-input" rows="2" placeholder="örn. 30°C'de yıkanır, ütülenmez..." />
							<span v-if="errors.care_instructions" class="form-error">{{ errors.care_instructions }}</span>
						</div>
					</div>
				</div>

				<!-- Etiketler -->
				<div class="form-section">
					<div class="drawer-section-title">Etiketler</div>
					<label class="toggle-row">
						<input type="checkbox" v-model="form.is_new" />
						<span class="toggle-text">Yeni gelen olarak işaretle</span>
					</label>
					<label class="toggle-row">
						<input type="checkbox" v-model="form.free_shipping" />
						<span class="toggle-text">Ücretsiz kargo</span>
					</label>
				</div>
			</div>

			<div class="drawer-footer">
				<button class="btn btn-ghost" @click="close" :disabled="busy">İptal</button>
				<button class="btn btn-primary btn-with-icon" @click="submit" :disabled="busy">
					<svg v-if="isEdit" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
						<path d="M20 6L9 17l-5-5" />
					</svg>
					<svg v-else width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
						<path d="M12 5v14M5 12h14" />
					</svg>
					{{ isEdit ? 'Değişiklikleri Kaydet' : 'Ürünü Ekle' }}
				</button>
			</div>
		</aside>
	</Teleport>
</template>

<script setup>
import { reactive, ref, computed, watch } from 'vue'
import CustomSelect from './CustomSelect.vue'

const props = defineProps({
	modelValue: { type: Boolean, default: false },
	product: { type: Object, default: null },
	categories: { type: Array, default: () => [] },
	brands: { type: Array, default: () => [] },
	busy: { type: Boolean, default: false },
	errors: { type: Object, default: () => ({}) },
})

const emit = defineEmits(['update:modelValue', 'submit'])

const isEdit = computed(() => !!props.product)

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
	sku: '',
	category_id: null,
	brand_id: null,
	gender: 'Unisex',
	is_new: false,
	free_shipping: false,
	care_instructions: '',
	material: '',
	origin_country: 'TR',
	variants: [],
})

const form = reactive(defaultForm())

const matrixCount = computed(() => {
	const s = selectedSizes.value.length || 1
	const c = selectedColors.value.length || 1
	return s * c
})

const canGenerate = computed(() => selectedSizes.value.length > 0 || selectedColors.value.length > 0)

const totalStock = computed(() => form.variants.reduce((acc, v) => acc + (Number(v.stock) || 0), 0))
const minPrice = computed(() => {
	const prices = form.variants.map((v) => Number(v.price) || 0).filter((p) => p > 0)
	return prices.length ? Math.min(...prices).toFixed(2) : '0.00'
})
const maxPrice = computed(() => {
	const prices = form.variants.map((v) => Number(v.price) || 0).filter((p) => p > 0)
	return prices.length ? Math.max(...prices).toFixed(2) : '0.00'
})

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

function close() {
	if (props.busy) return
	emit('update:modelValue', false)
}

function submit() {
	emit('submit', {
		id: props.product?.id ?? null,
		mode: isEdit.value ? 'edit' : 'create',
		payload: {
			name: form.name.trim(),
			sku: form.sku.trim(),
			category_id: form.category_id,
			brand_id: form.brand_id || null,
			gender: form.gender,
			is_new: !!form.is_new,
			free_shipping: !!form.free_shipping,
			care_instructions: form.care_instructions?.trim() || null,
			material: form.material?.trim() || null,
			origin_country: (form.origin_country || 'TR').toUpperCase(),
			variants: form.variants.map((v) => ({
				size: v.size || null,
				color_name: v.color_name || null,
				color_hex: v.color_hex || null,
				sku: String(v.sku || '').trim(),
				price: Number(v.price) || 0,
				old_price: v.old_price ? Number(v.old_price) : null,
				stock: Number(v.stock) || 0,
			})),
		},
	})
}

function loadFromProduct(p) {
	const variants = Array.isArray(p.variants) ? p.variants : []

	// selectedSizes ve selectedColors mevcut varyantlardan türet
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
}

watch(
	() => props.modelValue,
	(open) => {
		if (open) {
			if (props.product) {
				skuTouched.value = true
				Object.assign(form, {
					name: props.product.name || '',
					sku: props.product.sku || '',
					category_id: props.product.category_id ?? null,
					brand_id: props.product.brand_id ?? null,
					gender: props.product.gender || 'Unisex',
					is_new: !!props.product.isNew,
					free_shipping: !!props.product.freeShipping,
					care_instructions: props.product.careInstructions ?? props.product.care_instructions ?? '',
					material: props.product.material ?? '',
					origin_country: (props.product.originCountry ?? props.product.origin_country ?? 'TR'),
					variants: [],
				})
				loadFromProduct(props.product)
			} else {
				skuTouched.value = false
				selectedSizes.value = []
				selectedColors.value = []
				Object.assign(form, defaultForm())
			}
		} else {
			setTimeout(() => {
				skuTouched.value = false
				selectedSizes.value = []
				selectedColors.value = []
				bulkPrice.value = null
				bulkStock.value = null
				Object.assign(form, defaultForm())
			}, 250)
		}
	},
)
</script>

<style scoped>
.product-form-drawer {
	width: 760px;
	max-width: calc(100vw - 24px);
}

.icon-add  { background: #eef0ff !important; color: #4a6cf7 !important; }
.icon-edit { background: #fef3c7 !important; color: #d97706 !important; }

.form-section {
	display: flex;
	flex-direction: column;
	gap: 12px;
	padding-bottom: 16px;
	border-bottom: 1px solid #f0f0f5;
	margin-bottom: 16px;
}
.form-section:last-child { border-bottom: none; margin-bottom: 0; }
.drawer-section-title.flex-row { display: flex; align-items: center; justify-content: space-between; gap: 8px; flex-wrap: wrap; }

.form-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.form-group { display: flex; flex-direction: column; gap: 5px; }
.form-group.full { grid-column: 1 / -1; }
.required { color: #ef4444; margin-left: 2px; }
.form-error { color: #dc2626; font-size: 11px; margin-top: 2px; }
.hint-inline { color: #888; font-weight: 500; margin-left: 4px; }

.sku-row { display: flex; gap: 6px; align-items: center; }
.sku-row .form-input { flex: 1; }
.sku-gen { padding: 0 10px; height: 36px; font-size: 16px; line-height: 1; }

.chips-grid { display: grid; grid-template-columns: repeat(6, 1fr); gap: 6px; }
.chip {
	background: #fff; border: 1.5px solid #e8e8f0; color: #444;
	font-family: inherit; font-size: 12px; font-weight: 600;
	padding: 6px 0; border-radius: 8px; cursor: pointer;
	text-align: center; transition: all .12s;
}
.chip:hover { border-color: #c0c0d8; }
.chip.active { background: #1a1a2e; color: #fff; border-color: #1a1a2e; }

.color-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 6px; }
.color-chip {
	display: flex; align-items: center; gap: 8px;
	padding: 6px 10px; background: #fff;
	border: 1.5px solid #e8e8f0; border-radius: 8px;
	cursor: pointer; transition: all .12s;
	font-family: inherit; font-size: 11.5px; color: #444; font-weight: 600;
}
.color-chip:hover { border-color: #c0c0d8; }
.color-chip.active { border-color: #4a6cf7; background: #f0f4ff; color: #1a1a2e; }
.color-swatch {
	width: 16px; height: 16px; border-radius: 50%;
	border: 1.5px solid rgba(0, 0, 0, 0.08); flex-shrink: 0;
}
.color-name { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

.color-custom { margin-top: 4px; }
.color-custom summary {
	font-size: 12px; color: #4a6cf7; cursor: pointer;
	font-weight: 600; padding: 4px 0; list-style: none;
}
.color-custom summary::-webkit-details-marker { display: none; }
.color-custom-row { display: flex; gap: 6px; margin-top: 6px; align-items: center; }
.color-custom-row .form-input { flex: 1; }
.color-hex { max-width: 50px; padding: 2px; height: 36px; }

.regen-btn { align-self: flex-start; }

/* Varyant listesi */
.variant-badge {
	display: inline-block;
	background: #4a6cf7; color: #fff;
	font-size: 11px; font-weight: 700;
	padding: 1px 8px; border-radius: 999px;
	margin-left: 6px;
}

.bulk-controls { display: flex; align-items: center; gap: 4px; flex-wrap: wrap; }
.bulk-input { width: 90px; height: 30px; font-size: 11.5px; padding: 0 8px; }

.variant-empty {
	display: flex; flex-direction: column; align-items: center; gap: 8px;
	padding: 24px; border: 1px dashed #d8d8e8; border-radius: 10px;
	color: #888; font-size: 12.5px; text-align: center;
}

.variant-list { display: flex; flex-direction: column; gap: 6px; }
.variant-row {
	display: grid;
	grid-template-columns: 14px 90px 8px 36px 130px 90px 90px 70px 24px;
	align-items: center; gap: 8px;
	padding: 8px 10px;
	background: #fafafe; border: 1px solid #f0f0f6; border-radius: 8px;
}
.color-dot {
	width: 14px; height: 14px; border-radius: 50%;
	border: 1.5px solid rgba(0, 0, 0, 0.08); flex-shrink: 0;
}
.color-dot-empty { background: #e8e8f0; border-style: dashed; }
.variant-color-name { font-size: 11.5px; font-weight: 600; color: #1a1a2e; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.variant-sep { color: #ccc; }
.variant-size { font-size: 11.5px; font-weight: 700; color: #1a1a2e; text-align: center; }
.variant-sku { font-size: 11px; height: 30px; padding: 0 8px; font-family: 'SF Mono', Consolas, monospace; }
.variant-price, .variant-old-price, .variant-stock { font-size: 11.5px; height: 30px; padding: 0 8px; }
.variant-remove {
	background: #fff; border: 1px solid #fecaca; color: #dc2626;
	width: 24px; height: 24px; border-radius: 6px;
	display: flex; align-items: center; justify-content: center;
	cursor: pointer; transition: all .12s;
}
.variant-remove:hover { background: #fef2f2; }

.variant-summary {
	font-size: 12px; color: #666;
	padding: 8px 12px;
	background: #f8fafc; border-radius: 8px;
}
.variant-summary strong { color: #1a1a2e; font-weight: 700; }

.toggle-row {
	display: flex; align-items: center; gap: 10px;
	padding: 8px 10px; border-radius: 8px;
	background: #fafafe; border: 1px solid #f0f0f6; cursor: pointer;
}
.toggle-row input { accent-color: #4a6cf7; width: 14px; height: 14px; cursor: pointer; }
.toggle-text { font-size: 12.5px; color: #444; font-weight: 600; }
</style>
