<script setup>
import { reactive, ref, computed, onMounted, inject } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import { Star, X } from 'lucide-vue-next'
import AppLayout from '@/Layouts/AppLayout.vue'
import {
	OzPageHeader, OzModuleTabs, OzBreadcrumbs, OzCard, OzTabs, OzAlert, OzEmptyState,
	OzField, OzInput, OzTextarea, OzSelect, OzCombobox, OzCheckbox, OzSwitch,
	OzButton, OzPill,
} from '@/Components/Oz'
import MaterialPicker from '@/Components/MaterialPicker.vue'
import MarkdownPreview from '@/Components/MarkdownPreview.vue'
import axios from 'axios'
import { useCan } from '@/composables/useCan'

defineOptions({ layout: AppLayout })

const props = defineProps({
	product: { type: Object, default: null },
	categories: { type: Array, default: () => [] },
	brands: { type: Array, default: () => [] },
	attributeDefinitions: { type: Object, default: () => ({}) },
})

const showToast = inject('showToast')
const page = usePage()
const { can } = useCan()

const isEdit = computed(() => !!props.product)

const headerSubtitle = computed(() => {
	if (isEdit.value) return [form.name, form.sku].filter(Boolean).join(' · ')
	return 'Ürün kataloğuna yeni bir ürün ekleyin'
})

/* ── Sekmeler ── */
const TAB_META = [
	{ key: 'general',     label: 'Genel Bilgiler', icon: 'info' },
	{ key: 'seo',         label: 'SEO',            icon: 'globe' },
	{ key: 'price',       label: 'Fiyat-Kargo',    icon: 'tag' },
	{ key: 'variant',     label: 'Stok-Varyant',   icon: 'shirt' },
	{ key: 'images',      label: 'Resimler',       icon: 'eye' },
	{ key: 'attributes',  label: 'Özellikler',     icon: 'layers' },
	{ key: 'description', label: 'Açıklama (AI)',  icon: 'sparkles' },
	{ key: 'other',       label: 'Diğer',          icon: 'hash' },
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
	description_materials: 'attributes', attributes: 'attributes',
	public_name: 'description', public_description: 'description', tenant_description: 'description',
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

// OzTabs'a verilen sekme listesi: hatalı sekmede ikon error'a döner,
// varyant sekmesinde satır sayısı rozet olarak görünür.
const ozTabs = computed(() => TAB_META.map((t) => ({
	...t,
	icon: tabsWithErrors.value.has(t.key) ? 'error' : t.icon,
	count: t.key === 'variant' && form.variants.length ? form.variants.length : undefined,
})))

const firstMaterialError = computed(() => {
	const key = Object.keys(errors.value).find((k) => k.startsWith('description_materials'))
	return key ? errors.value[key] : ''
})

/* ── AI açıklama (M2) ── */
const aiBusy = ref(false)
const aiError = ref('')
const aiGeneratedAt = ref(null)
const previewMode = reactive({ public: false, tenant: false })

async function generateAiDescription() {
	if (!props.product?.id) {
		aiError.value = 'Önce ürünü kaydedin, sonra AI ile açıklama üretin.'
		return
	}
	if (aiBusy.value) return
	aiBusy.value = true
	aiError.value = ''
	try {
		const { data } = await axios.post(`/products/${props.product.id}/ai-description`)
		form.public_description = data.data.public_description
		form.tenant_description = data.data.tenant_description
		aiGeneratedAt.value = new Date().toISOString()
		showToast?.({ type: 'success', title: 'AI açıklama üretildi', message: 'Metinleri gözden geçirip kaydedin.' })
	} catch (e) {
		aiError.value = e?.response?.data?.message || 'AI çağrısı başarısız.'
		showToast?.({ type: 'error', title: 'AI Hatası', message: aiError.value })
	} finally {
		aiBusy.value = false
	}
}

const canUseAi = computed(() => can('product.ai.generate'))

/* ── Barkod (GS1) otomatik üretim ── */
const barcodeBusy = ref(false)

async function generateBarcode() {
	if (barcodeBusy.value) return
	barcodeBusy.value = true
	try {
		const { data } = await axios.post('/products/barcode/generate')
		form.barcode = data.barcode
		showToast?.({ type: 'success', title: 'Barkod üretildi', message: 'Kaydetmeden önce gözden geçirin.' })
	} catch (e) {
		showToast?.({ type: 'error', title: 'Barkod üretilemedi', message: e?.response?.data?.message || 'Barkod üretimi başarısız.' })
	} finally {
		barcodeBusy.value = false
	}
}

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

// Yerel kopya: yeni eklenen kategori, sayfa yeniden yüklenmeden listeye eklenebilsin diye.
const localCategories = ref([...props.categories])
const categoryOptions = computed(() =>
	localCategories.value.map((c) => ({ value: c.id, label: c.name ?? c.label })),
)

// Seçili kategoriye göre değişen özellik tanımları (Faz 3 — Attribute Engine).
// Kategori değişince form.attributes DEĞERLERİ elle temizlenmez (kullanıcı
// verisini kaybetmesin) — tanımsız/eski anahtarlar backend'de kayıt anında
// ProductAttributeService::filterToDefinedKeys() ile elenir.
const activeAttributeDefinitions = computed(() => props.attributeDefinitions[form.category_id] ?? [])

const canCreateCategory = computed(() => page.props.auth?.permissions?.includes?.('category.create') ?? true)
const canCreateBrand = computed(() => page.props.auth?.permissions?.includes?.('brand.manage') ?? true)
const creatingCategory = ref(false)
const creatingBrand = ref(false)
const newCategoryName = ref('')
const newBrandName = ref('')

async function handleCreateCategory(name) {
	const clean = String(name || '').trim()
	if (creatingCategory.value || !clean) return
	creatingCategory.value = true
	try {
		const { data } = await axios.post('/products/categories/quick-create', { name: clean })
		if (!localCategories.value.some((c) => c.id === data.id)) {
			localCategories.value.push(data)
		}
		form.category_id = data.id
		newCategoryName.value = ''
		showToast?.({ type: 'success', title: 'Kategori eklendi', message: `"${data.name}" kategoriye eklendi.` })
	} catch (e) {
		showToast?.({ type: 'error', title: 'Kategori eklenemedi', message: e?.response?.data?.message || 'Bir hata oluştu.' })
	} finally {
		creatingCategory.value = false
	}
}

// Yerel kopya: yeni eklenen marka, sayfa yeniden yüklenmeden listeye eklenebilsin diye.
const localBrands = ref([...props.brands])
const brandOptions = computed(() =>
	localBrands.value.map((b) => ({ value: b.id, label: b.name ?? b.label })),
)

async function handleCreateBrand(name) {
	const clean = String(name || '').trim()
	if (creatingBrand.value || !clean) return
	creatingBrand.value = true
	try {
		const { data } = await axios.post('/products/brands/quick-create', { name: clean })
		if (!localBrands.value.some((b) => b.id === data.id)) {
			localBrands.value.push(data)
		}
		form.brand_id = data.id
		newBrandName.value = ''
		showToast?.({ type: 'success', title: 'Marka eklendi', message: `"${data.name}" markaya eklendi.` })
	} catch (e) {
		showToast?.({ type: 'error', title: 'Marka eklenemedi', message: e?.response?.data?.message || 'Bir hata oluştu.' })
	} finally {
		creatingBrand.value = false
	}
}

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
	attributes: {},
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
	// AI için materyal bağı
	description_materials: [],
	// AI açıklama alanları (M2)
	public_name: '',
	public_description: '',
	tenant_description: '',
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
	const brand = localBrands.value.find((b) => b.id === form.brand_id)
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
				persisted: existing?.persisted ?? false,
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
		attributes: { ...form.attributes },
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
		description_materials: (form.description_materials || []).map((r, i) => ({
			material_id: r.material_id,
			role: r.role,
			sort_order: r.sort_order ?? i,
			notes: r.notes || null,
		})),
		public_name: form.public_name?.trim() || null,
		public_description: form.public_description?.trim() || null,
		tenant_description: form.tenant_description?.trim() || null,
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
	const target = TAB_META.find((t) => errorTabs.has(t.key))
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
		attributes: { ...(p.attributes || {}) },
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
		description_materials: Array.isArray(p.description_materials)
			? p.description_materials.map((r) => ({
				material_id: r.material_id,
				material: r.material || null,
				role: r.role,
				sort_order: r.sort_order ?? 0,
				notes: r.notes || null,
			}))
			: [],
		public_name: p.publicName ?? '',
		public_description: p.publicDescription ?? '',
		tenant_description: p.tenantDescription ?? '',
	})
	aiGeneratedAt.value = p.aiGeneratedAt ?? null

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
		// Kayıtlı varyant: SKU kilitli (Bagisto SKU ile eşleştirir), stok yalnız stok hareketiyle değişir.
		persisted: true,
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

<template>
	<Head :title="isEdit ? `${form.name || 'Ürün'} · Düzenle` : 'Yeni Ürün'" />

	<OzPageHeader :title="isEdit ? 'Ürünü Düzenle' : 'Yeni Ürün Ekle'" :subtitle="headerSubtitle">
		<template #actions>
			<OzButton variant="ghost" :disabled="busy" @click="router.visit('/products')">İptal</OzButton>
			<OzButton variant="primary" :icon="isEdit ? 'check' : 'plus'" :loading="busy" @click="submit">
				{{ busy ? 'Kaydediliyor…' : (isEdit ? 'Değişiklikleri Kaydet' : 'Ürünü Ekle') }}
			</OzButton>
		</template>
	</OzPageHeader>
	<OzModuleTabs />

	<OzAlert v-if="hasErrors" tone="danger" title="Formda eksik veya hatalı alanlar var" class="block">
		İşaretli sekmelerdeki alanları düzeltin.
	</OzAlert>

	<OzTabs v-model="activeTab" :tabs="ozTabs" aria-label="Ürün bölümleri" class="block" />

	<div class="panels">
		<!-- ── Genel Bilgiler ── -->
		<div v-show="activeTab === 'general'" class="panel" role="tabpanel">
			<OzCard title="Temel Bilgiler" subtitle="Ad, stok kodu ve tanımlayıcı bilgiler" class="block">
				<div class="stack">
					<OzField label="Ürün Adı" required :error="errors.name">
						<OzInput v-model="form.name" placeholder="örn. Basic Crew Tişört" @input="onNameInput" />
					</OzField>
					<OzField
						label="Ana SKU"
						required
						:error="errors.sku"
						:hint="isEdit ? 'SKU oluşturulduktan sonra değiştirilemez (Bagisto eşleşmesi).' : 'Boş bırakıp ⟳ ile otomatik üretebilirsiniz (marka + ürün adı gerekli).'"
					>
						<div class="inline-row">
							<OzInput v-model="form.sku" placeholder="TEK-XXX-001" class="grow" :readonly="isEdit" @input="skuTouched = true" />
							<OzButton v-if="!isEdit" icon="refresh" :disabled="!form.name || !form.brand_id" aria-label="Otomatik SKU üret" @click="generateBaseSku" />
						</div>
					</OzField>
				</div>
			</OzCard>

			<OzCard title="Organizasyon" subtitle="Ürünün kataloğdaki yeri ve sınıflandırması" class="block">
				<div class="stack">
					<div class="grid-2">
						<OzField label="Cinsiyet" :error="errors.gender">
							<OzSelect v-model="form.gender" :options="genderOptions" />
						</OzField>
						<OzField label="Kategori" :error="errors.category_id">
							<OzCombobox v-model="form.category_id" :options="categoryOptions" placeholder="Kategori seçiniz…" search-placeholder="Kategori ara…" empty-text="Sonuç bulunamadı" />
							<div v-if="canCreateCategory" class="inline-row quick-add">
								<OzInput v-model="newCategoryName" placeholder="Yeni kategori adı…" class="grow" @keyup.enter="handleCreateCategory(newCategoryName)" />
								<OzButton size="sm" icon="plus" :loading="creatingCategory" :disabled="!newCategoryName.trim()" @click="handleCreateCategory(newCategoryName)">Ekle</OzButton>
							</div>
						</OzField>
					</div>
					<OzField label="Marka" :error="errors.brand_id">
						<OzCombobox v-model="form.brand_id" :options="brandOptions" placeholder="— Marka yok —" clearable search-placeholder="Marka ara…" empty-text="Sonuç bulunamadı" />
						<div v-if="canCreateBrand" class="inline-row quick-add">
							<OzInput v-model="newBrandName" placeholder="Yeni marka adı…" class="grow" @keyup.enter="handleCreateBrand(newBrandName)" />
							<OzButton size="sm" icon="plus" :loading="creatingBrand" :disabled="!newBrandName.trim()" @click="handleCreateBrand(newBrandName)">Ekle</OzButton>
						</div>
					</OzField>
					<OzSwitch v-model="form.is_new" label="Yeni gelen olarak işaretle" description="Katalogda “Yeni” rozetiyle öne çıkar" />
				</div>
			</OzCard>
		</div>

		<!-- ── SEO ── -->
		<div v-show="activeTab === 'seo'" class="panel" role="tabpanel">
			<OzCard title="Arama Motoru Optimizasyonu" subtitle="URL ve arama sonuçlarındaki görünüm (opsiyonel)" class="block">
				<div class="stack">
					<OzField label="URL (slug)" :error="errors.slug" :hint="`/products/${form.slug || '…'}` + ' — boş bırakırsanız ürün adından otomatik üretilir.'">
						<OzInput v-model="form.slug" placeholder="otomatik üretilir" @input="form.slug = slugify(form.slug)" />
					</OzField>
					<OzField label="Meta Başlık" :error="errors.meta_title">
						<OzInput v-model="form.meta_title" maxlength="191" placeholder="Boşsa ürün adı kullanılır" />
					</OzField>
					<OzField label="Meta Açıklama" :error="errors.meta_description">
						<OzTextarea v-model="form.meta_description" :rows="3" :maxlength="500" placeholder="Arama sonuçlarında görünecek kısa özet…" />
					</OzField>
					<OzField label="Anahtar Kelimeler" :error="errors.meta_keywords" hint="Virgülle ayırın.">
						<OzInput v-model="form.meta_keywords" maxlength="255" placeholder="tişört, pamuklu, yazlık" />
					</OzField>
				</div>
			</OzCard>
		</div>

		<!-- ── Fiyat-Kargo ── -->
		<div v-show="activeTab === 'price'" class="panel" role="tabpanel">
			<OzCard title="Toplu Fiyatlama" subtitle="Tüm varyantlara tek seferde aynı fiyatı uygula" class="block">
				<OzEmptyState v-if="!form.variants.length" icon="tag" title="Henüz varyant yok" description="Önce Stok-Varyant sekmesinden varyant oluşturun." compact />
				<div v-else class="inline-row">
					<OzInput v-model="bulkPrice" type="number" min="0" step="0.01" suffix="₺" placeholder="Fiyat" class="grow" />
					<OzButton size="sm" :disabled="!bulkPrice" @click="applyBulkPrice">→ {{ form.variants.length }} varyantın hepsine uygula</OzButton>
				</div>
			</OzCard>

			<OzCard title="Fiyatlandırma" subtitle="Piyasa ve alış fiyatı (opsiyonel)" class="block">
				<div class="grid-2">
					<OzField label="Piyasa Fiyatı" :error="errors.market_price">
						<OzInput v-model="form.market_price" type="number" min="0" step="0.01" suffix="₺" placeholder="örn. 125.86" />
					</OzField>
					<OzField label="Alış Fiyatı" :error="errors.purchase_price">
						<OzInput v-model="form.purchase_price" type="number" min="0" step="0.01" suffix="₺" placeholder="örn. 89.90" />
					</OzField>
				</div>
			</OzCard>

			<OzCard title="Kargo" subtitle="Kargo etiketi ve teslimat bilgileri (opsiyonel)" class="block">
				<div class="stack">
					<OzSwitch v-model="form.free_shipping" label="Ücretsiz kargo" />
					<div class="grid-2">
						<OzField label="Ağırlık" :error="errors.weight">
							<OzInput v-model="form.weight" type="number" min="0" step="0.001" suffix="kg" placeholder="örn. 0.250" />
						</OzField>
						<OzField label="Desi" :error="errors.desi">
							<OzInput v-model="form.desi" type="number" min="0" step="0.01" placeholder="örn. 1.5" />
						</OzField>
					</div>
					<div class="grid-2">
						<OzField label="Kargo Süresi" :error="errors.shipping_time">
							<OzInput v-model="form.shipping_time" maxlength="50" placeholder="örn. 1-3 iş günü" />
						</OzField>
						<OzField label="Sabit Kargo Ücreti" :error="errors.shipping_fee" hint="Ücretsiz kargo açıkken uygulanmaz.">
							<OzInput v-model="form.shipping_fee" type="number" min="0" step="0.01" suffix="₺" placeholder="Ücretsiz kargo kapalıysa" :disabled="form.free_shipping" />
						</OzField>
					</div>
				</div>
			</OzCard>
		</div>

		<!-- ── Stok-Varyant ── -->
		<div v-show="activeTab === 'variant'" class="panel" role="tabpanel">
			<OzCard title="Bedenler & Renkler" subtitle="Seçtiğiniz beden ve renklerin kombinasyonundan varyant matrisi oluşturun" class="block">
				<div class="stack">
					<div>
						<span class="group-label">Bedenler</span>
						<div class="chips-grid">
							<button v-for="s in sizeOptions" :key="s" type="button" class="chip"
								:class="{ active: selectedSizes.includes(s) }" @click="toggleSize(s)">{{ s }}</button>
						</div>
					</div>
					<div>
						<span class="group-label">Renkler</span>
						<div class="color-grid">
							<button v-for="c in colorPalette" :key="c.name" type="button" class="color-chip"
								:class="{ active: hasColor(c.name) }" :title="c.name" @click="toggleColor(c)">
								<span class="color-swatch" :style="{ background: c.hex }"></span>
								<span class="color-name">{{ c.name }}</span>
							</button>
						</div>
						<details class="color-custom">
							<summary>+ Özel renk</summary>
							<div class="inline-row">
								<OzInput v-model="customColor.name" placeholder="Renk adı" class="grow" />
								<OzInput v-model="customColor.hex" type="color" class="color-hex" aria-label="Özel renk kodu" />
								<OzButton size="sm" :disabled="!customColor.name" @click="addCustomColor">Ekle</OzButton>
							</div>
						</details>
					</div>
					<div>
						<OzButton size="sm" icon="refresh" :disabled="!canGenerate" @click="regenerateVariants">
							Varyantları Oluştur<span v-if="canGenerate" class="hint-inline"> — {{ matrixCount }} kombinasyon</span>
						</OzButton>
					</div>
				</div>
			</OzCard>

			<OzCard title="Varyantlar" subtitle="Her varyant için SKU, fiyat ve stok girin" class="block">
				<template #actions>
					<div class="inline-row">
						<OzPill v-if="form.variants.length" tone="accent">{{ form.variants.length }} satır</OzPill>
						<template v-if="form.variants.length">
							<OzInput v-model="bulkStock" type="number" min="0" placeholder="Stok" class="bulk-input" />
							<OzButton size="sm" :disabled="bulkStock === null || bulkStock === ''" @click="applyBulkStock">→ Hepsine uygula</OzButton>
						</template>
					</div>
				</template>
				<OzEmptyState v-if="!form.variants.length" icon="shirt" title="Varyant yok" description="Beden veya renk seçip “Varyantları Oluştur” butonuna basın, ya da boş satır ekleyin." compact>
					<template #actions><OzButton size="sm" icon="plus" @click="addEmptyVariant">Boş varyant ekle</OzButton></template>
				</OzEmptyState>
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
						<OzInput v-model="v.sku" placeholder="SKU" class="mono" :readonly="v.persisted" />
						<OzInput v-model="v.price" type="number" min="0" step="0.01" placeholder="Fiyat" />
						<OzInput v-model="v.old_price" type="number" min="0" step="0.01" placeholder="—" />
						<OzInput v-model="v.stock" type="number" min="0" placeholder="Stok" :readonly="v.persisted" :title="v.persisted ? 'Kayıtlı varyantın stoku yalnız stok hareketiyle değişir' : undefined" />
						<button type="button" class="variant-remove" aria-label="Satırı sil" @click="removeVariant(idx)">
							<X :size="12" />
						</button>
					</div>
					<OzButton size="sm" icon="plus" @click="addEmptyVariant">Satır ekle</OzButton>
				</div>
				<p v-if="errors.variants" class="field-error">{{ errors.variants }}</p>
			</OzCard>
		</div>

		<!-- ── Resimler ── -->
		<div v-show="activeTab === 'images'" class="panel" role="tabpanel">
			<OzCard title="Görseller" subtitle="İlk görsel otomatik kapak olur" class="block">
				<div class="stack">
					<div v-if="existingImages.length || newImages.length" class="img-grid">
						<div v-for="img in existingImages" :key="'e' + img.id" class="img-thumb" :class="{ cover: img.is_cover }">
							<img :src="img.url" alt="" />
							<span v-if="img.is_cover" class="img-badge cover-badge">Kapak</span>
							<div class="img-actions">
								<button v-if="!img.is_cover" type="button" class="img-act" title="Kapak yap" @click="setCover(img)"><Star :size="13" /></button>
								<button type="button" class="img-act del" title="Sil" @click="deleteExisting(img)"><X :size="13" /></button>
							</div>
						</div>
						<div v-for="(img, i) in newImages" :key="'n' + i" class="img-thumb new">
							<img :src="img.preview" alt="" />
							<span class="img-badge new-badge">Yeni</span>
							<div class="img-actions">
								<button type="button" class="img-act del" title="Kaldır" @click="removeNewImage(i)"><X :size="13" /></button>
							</div>
						</div>
					</div>
					<label class="img-upload">
						<input type="file" accept="image/*" multiple hidden @change="onPickImages" />
						<span class="upload-title">Görsel ekle</span>
						<span class="upload-sub">PNG / JPG / WEBP, en fazla 5MB</span>
					</label>
					<p v-if="errors['images.0'] || errors.images" class="field-error">{{ errors['images.0'] || errors.images }}</p>
				</div>
			</OzCard>
		</div>

		<!-- ── Özellikler ── -->
		<div v-show="activeTab === 'attributes'" class="panel" role="tabpanel">
			<OzCard title="Üretim Bilgileri" subtitle="Kumaş, menşei ve bakım talimatları (opsiyonel)" class="block">
				<div class="stack">
					<div class="grid-2">
						<OzField label="Kumaş İçeriği (özet)" :error="errors.material">
							<OzInput v-model="form.material" placeholder="örn. %100 Pamuk" />
						</OzField>
						<OzField label="Menşei Ülke" :error="errors.origin_country">
							<OzInput v-model="form.origin_country" maxlength="2" placeholder="TR" @input="form.origin_country = (form.origin_country || '').toUpperCase()" />
						</OzField>
					</div>
					<OzField label="Yıkama / Bakım Talimatları" :error="errors.care_instructions">
						<OzTextarea v-model="form.care_instructions" :rows="3" placeholder="örn. 30°C'de yıkanır, ütülenmez…" />
					</OzField>
				</div>
			</OzCard>

			<OzCard title="Materyaller" subtitle="AI açıklamada kullanılacak kumaş ve aksesuarlar" class="block">
				<MaterialPicker v-model="form.description_materials" />
				<p v-if="firstMaterialError" class="field-error">{{ firstMaterialError }}</p>
			</OzCard>

			<OzCard v-if="activeAttributeDefinitions.length" title="Kategori Özellikleri" subtitle="Seçili kategoriye özgü alanlar" class="block">
				<div class="grid-2">
					<template v-for="def in activeAttributeDefinitions" :key="def.key">
						<OzCheckbox
							v-if="def.type === 'boolean'"
							v-model="form.attributes[def.key]"
							:label="def.label"
						/>
						<OzField
							v-else
							:label="def.label"
							:required="def.required"
							:error="errors[`attributes.${def.key}`]"
						>
							<OzSelect
								v-if="def.type === 'enum'"
								v-model="form.attributes[def.key]"
								:options="def.options.map((o) => ({ value: o, label: o }))"
								placeholder="Seçin"
							/>
							<OzInput
								v-else
								v-model="form.attributes[def.key]"
								:type="def.type === 'number' ? 'number' : 'text'"
							/>
						</OzField>
					</template>
				</div>
			</OzCard>
		</div>

		<!-- ── Açıklama (AI) ── -->
		<div v-show="activeTab === 'description'" class="panel" role="tabpanel">
			<OzCard title="AI Ürün Açıklaması" subtitle="Materyaller + ürün adına göre iki tonda metin" class="block">
				<template #actions>
					<div class="inline-row">
						<OzPill v-if="aiGeneratedAt" tone="success">Son üretim: {{ new Date(aiGeneratedAt).toLocaleString('tr-TR') }}</OzPill>
						<OzButton
							v-if="canUseAi"
							variant="primary"
							size="sm"
							icon="sparkles"
							:loading="aiBusy"
							:disabled="!isEdit"
							@click="generateAiDescription"
						>
							AI ile Üret
						</OzButton>
					</div>
				</template>
				<div class="stack">
					<OzAlert v-if="!isEdit" tone="info" title="Önce ürünü kaydedin">
						AI açıklama üretimi için temel bilgilerle ürünü kaydedin.
					</OzAlert>
					<OzAlert v-if="aiError" tone="danger" :title="aiError" />

					<OzField label="Storefront Adı" :error="errors.public_name" hint="B2C, opsiyonel — boşsa “Ürün Adı” kullanılır.">
						<OzInput v-model="form.public_name" maxlength="255" placeholder="örn. Ege Pamuğu Basic Tişört" />
					</OzField>

					<OzField label="B2C Açıklama (Storefront)" :error="errors.public_description">
						<template #default>
							<div class="label-row">
								<OzButton size="sm" @click="previewMode.public = !previewMode.public">
									{{ previewMode.public ? 'Metin' : 'Önizleme' }}
								</OzButton>
							</div>
							<OzTextarea
								v-if="!previewMode.public"
								v-model="form.public_description"
								:rows="8"
								placeholder="Müşteriye storytelling — markdown desteklenir (**kalın**, *italik*, - liste)"
							/>
							<MarkdownPreview v-else :source="form.public_description" />
						</template>
					</OzField>

					<OzField label="B2B Açıklama (Bayi Portalı — Varsayılan)" :error="errors.tenant_description">
						<template #default>
							<div class="label-row">
								<OzButton size="sm" @click="previewMode.tenant = !previewMode.tenant">
									{{ previewMode.tenant ? 'Metin' : 'Önizleme' }}
								</OzButton>
							</div>
							<OzTextarea
								v-if="!previewMode.tenant"
								v-model="form.tenant_description"
								:rows="8"
								placeholder="Bayiye spec-forward: kompozisyon, gramaj, bakım, satılabilirlik. Markdown desteklenir."
							/>
							<MarkdownPreview v-else :source="form.tenant_description" />
						</template>
					</OzField>
				</div>
			</OzCard>
		</div>

		<!-- ── Diğer ── -->
		<div v-show="activeTab === 'other'" class="panel" role="tabpanel">
			<OzCard title="Özel Kodlar & Detaylar" subtitle="Barkod, üretici kodları ve menşei detayları (opsiyonel)" class="block">
				<div class="stack">
					<div class="grid-2">
						<OzField label="Barkod / GTIN" :error="errors.barcode">
							<div class="inline-row">
								<OzInput v-model="form.barcode" maxlength="64" placeholder="örn. 8690000000000" class="grow mono" />
								<OzButton size="sm" icon="refresh" :loading="barcodeBusy" @click="generateBarcode">Yeniden Üret</OzButton>
							</div>
						</OzField>
						<OzField label="Üretici / Tedarikçi Kodu" :error="errors.manufacturer_code">
							<OzInput v-model="form.manufacturer_code" maxlength="64" placeholder="İç üretici kodu" />
						</OzField>
					</div>
					<OzField label="GTIP / HS Kodu" :error="errors.gtip_code">
						<OzInput v-model="form.gtip_code" maxlength="32" placeholder="Gümrük tarife pozisyon kodu" class="mono" />
					</OzField>
					<OzSwitch v-model="form.is_domestic" label="Yerli üretim ürünü" />
				</div>
			</OzCard>
		</div>
	</div>

	<!-- Yapışkan kaydet çubuğu -->
	<div class="savebar">
		<span v-if="hasErrors" class="savebar-hint">Eksik alanlar var — işaretli sekmeleri kontrol edin</span>
		<span v-else class="savebar-hint">{{ isEdit ? 'Değişiklikler kaydedilmedi' : 'Kaydetmeden çıkmayın' }}</span>
		<div class="savebar-actions">
			<OzButton variant="ghost" :disabled="busy" @click="router.visit('/products')">İptal</OzButton>
			<OzButton variant="primary" :icon="isEdit ? 'check' : 'plus'" :loading="busy" @click="submit">
				{{ busy ? 'Kaydediliyor…' : (isEdit ? 'Değişiklikleri Kaydet' : 'Ürünü Ekle') }}
			</OzButton>
		</div>
	</div>
</template>

<style scoped>
.block { margin-bottom: 14px; }
.panels { display: flex; flex-direction: column; }
.panel { display: flex; flex-direction: column; }
.stack { display: flex; flex-direction: column; gap: 14px; padding: 18px; }
.grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
@media (max-width: 640px) {
	.grid-2 { grid-template-columns: 1fr; }
}

.inline-row { display: flex; gap: 8px; align-items: center; }
.inline-row .grow { flex: 1; min-width: 0; }
.quick-add { margin-top: 8px; }
.group-label { display: block; font-size: 12px; font-weight: 600; color: var(--oz-ink-soft); margin-bottom: 8px; }
.hint-inline { color: var(--oz-muted); font-weight: 500; margin-left: 4px; }
.field-error { margin: 0; padding: 0 18px 14px; font-size: 11px; font-weight: 500; color: var(--oz-danger); }
.label-row { display: flex; align-items: center; justify-content: flex-end; margin-bottom: 8px; }
.mono :deep(input) { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; }

/* ── Beden / renk seçimleri (kit karşılığı yok, token ile) ── */
.chips-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(54px, 1fr)); gap: 6px; }
.chip {
	background: var(--oz-surface); border: 1px solid var(--oz-border); color: var(--oz-ink);
	font: inherit; font-size: 12px; font-weight: 600;
	padding: 7px 0; border-radius: 8px; cursor: pointer;
	text-align: center; transition: background .12s, border-color .12s, color .12s;
}
.chip:hover { border-color: var(--oz-muted-2); }
.chip.active { background: var(--oz-ink-soft); color: var(--oz-surface); border-color: var(--oz-ink-soft); }

.color-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(110px, 1fr)); gap: 6px; }
.color-chip {
	display: flex; align-items: center; gap: 8px;
	padding: 7px 10px; background: var(--oz-surface);
	border: 1px solid var(--oz-border); border-radius: 8px;
	cursor: pointer; transition: border-color .12s, background .12s;
	font: inherit; font-size: 11.5px; color: var(--oz-ink); font-weight: 600;
}
.color-chip:hover { border-color: var(--oz-muted-2); }
.color-chip.active { border-color: var(--oz-accent); background: var(--oz-accent-soft); }
.color-swatch { width: 16px; height: 16px; border-radius: 50%; border: 1px solid rgba(0, 0, 0, 0.08); flex-shrink: 0; }
.color-name { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

.color-custom { margin-top: 8px; }
.color-custom summary { font-size: 12px; color: var(--oz-accent); cursor: pointer; font-weight: 600; padding: 4px 0; list-style: none; }
.color-custom summary::-webkit-details-marker { display: none; }
.color-custom .inline-row { margin-top: 6px; }
.color-hex { max-width: 52px; flex-shrink: 0; }

/* ── Varyant tablosu (OzTable'da header slotu yok — özel tablo + token) ── */
.bulk-input { width: 90px; }
.variant-table { display: flex; flex-direction: column; gap: 6px; padding: 18px; overflow-x: auto; }
.variant-thead, .variant-row {
	display: grid;
	grid-template-columns: 1.4fr 60px 1.6fr 1fr 1fr 0.9fr 28px;
	align-items: center; gap: 8px;
	min-width: 560px;
}
.variant-thead {
	font-size: 10.5px; font-weight: 700; color: var(--oz-muted);
	text-transform: uppercase; letter-spacing: .04em;
	padding: 0 2px 4px;
}
.variant-row {
	padding: 8px 10px;
	background: var(--oz-surface-2); border: 1px solid var(--oz-border-soft); border-radius: 8px;
}
.variant-color { display: flex; align-items: center; gap: 6px; min-width: 0; }
.color-dot { width: 14px; height: 14px; border-radius: 50%; border: 1px solid rgba(0, 0, 0, 0.08); flex-shrink: 0; }
.color-dot-empty { background: var(--oz-surface-2); border-style: dashed; border-color: var(--oz-border-strong); }
.variant-color-name { font-size: 11.5px; font-weight: 600; color: var(--oz-ink-soft); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.variant-size { font-size: 11.5px; font-weight: 700; color: var(--oz-ink-soft); text-align: center; }
.variant-remove {
	background: var(--oz-surface); border: 1px solid rgba(var(--oz-danger-rgb), .4); color: var(--oz-danger);
	width: 24px; height: 24px; border-radius: 6px;
	display: flex; align-items: center; justify-content: center;
	cursor: pointer; transition: background .12s;
}
.variant-remove:hover { background: var(--oz-danger-soft); }

/* ── Görseller ── */
.img-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(104px, 1fr)); gap: 10px; }
.img-thumb { position: relative; aspect-ratio: 1; border-radius: 10px; overflow: hidden; border: 1px solid var(--oz-border); background: var(--oz-surface-2); }
.img-thumb.cover { border-color: var(--oz-accent); box-shadow: 0 0 0 2px rgba(var(--oz-accent-rgb), .2); }
.img-thumb.new { border-style: dashed; border-color: rgba(var(--oz-accent-rgb), .5); }
.img-thumb img { width: 100%; height: 100%; object-fit: cover; display: block; }
.img-badge { position: absolute; top: 5px; left: 5px; font-size: 9.5px; font-weight: 700; padding: 1px 6px; border-radius: 5px; background: var(--oz-accent); color: #fff; }
.img-actions { position: absolute; top: 4px; right: 4px; display: flex; gap: 4px; opacity: 0; transition: opacity .12s; }
.img-thumb:hover .img-actions { opacity: 1; }
.img-act { width: 22px; height: 22px; border: none; border-radius: 6px; background: rgba(15, 23, 42, .7); color: #fff; cursor: pointer; display: flex; align-items: center; justify-content: center; }
.img-act:hover { background: rgba(15, 23, 42, .9); }
.img-act.del:hover { background: var(--oz-danger); }
.img-upload {
	display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 2px;
	padding: 16px; border: 1.5px dashed rgba(var(--oz-accent-rgb), .35); border-radius: 10px;
	cursor: pointer; background: var(--oz-accent-soft); transition: border-color .12s;
}
.img-upload:hover { border-color: rgba(var(--oz-accent-rgb), .55); }
.upload-title { font-size: 12.5px; font-weight: 600; color: var(--oz-accent); }
.upload-sub { font-size: 11px; color: var(--oz-muted); }

/* ── Yapışkan kaydet çubuğu (Ayarlar deseni) ── */
.savebar {
	position: sticky; bottom: 0; z-index: 20;
	display: flex; align-items: center; gap: 12px;
	background: var(--oz-surface); border: 1px solid var(--oz-border);
	border-radius: var(--oz-radius); box-shadow: var(--oz-shadow-sm);
	padding: 12px 16px; margin-top: 4px; margin-bottom: 40px;
}
.savebar-hint { font-size: 12px; color: var(--oz-muted); }
.savebar-actions { display: flex; gap: 8px; margin-left: auto; }
@media (max-width: 640px) {
	.savebar { flex-direction: column; align-items: stretch; }
	.savebar-actions { margin-left: 0; }
	.savebar-actions > * { flex: 1; }
}
</style>
