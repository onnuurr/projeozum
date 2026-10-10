<script setup>
import { reactive, ref, computed, onMounted, inject, watch } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import { Check, Star, X } from 'lucide-vue-next'
import AppLayout from '@/Layouts/AppLayout.vue'
import {
	OzPageHeader, OzModuleTabs, OzBreadcrumbs, OzCard, OzTabs, OzAlert, OzEmptyState,
	OzField, OzInput, OzTextarea, OzCombobox, OzSelect, OzCheckbox, OzSwitch,
	OzButton, OzPill, OzFileDrop, OzIcon,
} from '@/Components/Oz'
import MaterialPicker from '@/Components/MaterialPicker.vue'
import MarkdownPreview from '@/Components/MarkdownPreview.vue'
import draggable from 'vuedraggable'
import axios from 'axios'
import { useCan } from '@/composables/useCan'
import { lucideIcon } from '@/lucideIcons.js'

defineOptions({ layout: AppLayout })

const props = defineProps({
	product: { type: Object, default: null },
	categories: { type: Array, default: () => [] },
	brands: { type: Array, default: () => [] },
	attributeDefinitions: { type: Object, default: () => ({}) },
	// Beden & Renkler ekranından yönetilir (variant-option.*).
	sizeOptions: { type: Array, default: () => [] },
	colorOptions: { type: Array, default: () => [] },
	// KDV: `kdv_orani` sistem özelliğinin seçenekleri [{value, label, rate}] + kategori id → miras çözülmüş oran.
	vatOptions: { type: Array, default: () => [] },
	categoryVatRates: { type: Object, default: () => ({}) },
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
	{ key: 'price',       label: 'Fiyat & Stok',   icon: 'tag' },
	{ key: 'seo',         label: 'SEO',            icon: 'globe' },
	{ key: 'shipping',    label: 'Kargo',          icon: 'truck' },
	{ key: 'variant',     label: 'Stok-Varyant',   icon: 'shirt' },
	{ key: 'images',      label: 'Resimler',       icon: 'eye' },
	{ key: 'attributes',  label: 'Özellikler',     icon: 'layers' },
	{ key: 'description', label: 'Açıklama',       icon: 'sparkles' },
	{ key: 'other',       label: 'Diğer',          icon: 'hash' },
]
const activeTab = ref('general')

// Hata alanı → ait olduğu sekme.
const fieldTabMap = {
	name: 'general', category_id: 'general', brand_id: 'general', barcode: 'general', public_description: 'general',
	sku: 'price', market_price: 'price', purchase_price: 'price', sale_price: 'price', discount_price: 'price', max_order_qty: 'price',
	'attributes.kdv_orani': 'price',
	slug: 'seo', meta_title: 'seo', meta_description: 'seo', meta_keywords: 'seo',
	free_shipping: 'shipping', weight: 'shipping', desi: 'shipping', length_cm: 'shipping', width_cm: 'shipping', height_cm: 'shipping', shipping_time: 'shipping', shipping_fee: 'shipping',
	variants: 'variant',
	images: 'images',
	material: 'description', description_materials: 'description',
	attributes: 'attributes',
	public_name: 'description', tenant_description: 'description',
	is_domestic: 'other', manufacturer_code: 'other', gtip_code: 'other',
}
function tabForField(field) {
	const key = String(field)
	if (fieldTabMap[key]) return fieldTabMap[key]
	return fieldTabMap[key.split('.')[0]] || 'general'
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

/* ── AI içerik (açıklama + SEO) ──
 * Kayıt beklemez: formda o an yazılı bilgiler (ad, marka, kategori, kategori özellikleri, kumaş, bakım,
 * materyaller) gönderilir, sunucu bunlardan kaydedilmeyen bir taslakla üretir. Sonuç yalnız forma
 * yazılır; kalıcı olması için ürün kaydedilir. */
const aiBusy = reactive({ descriptions: false, seo: false })
const aiError = ref('')
const aiGeneratedAt = ref(null)
const previewMode = reactive({ public: false, tenant: false })
const aiReady = computed(() => !!form.name?.trim() && !!form.category_id)
const AI_NOT_READY = 'AI için önce Genel sekmesinde ürün adını ve kategoriyi girin.'

function aiDraftPayload(parts) {
	return {
		parts,
		product_id: props.product?.id ?? null,
		name: form.name.trim(),
		category_id: form.category_id,
		brand_id: form.brand_id || null,
		material: form.material?.trim() || null,
		// Dosya alanları (yeni seçilmiş File) AI'ya gitmez
		attributes: Object.fromEntries(Object.entries(form.attributes).filter(([, v]) => !(v instanceof File))),
		description_materials: (form.description_materials || []).map((r, i) => ({
			material_id: r.material_id, role: r.role, sort_order: r.sort_order ?? i,
		})),
	}
}

async function generateAi(part) {
	if (aiBusy[part]) return
	if (!aiReady.value) {
		aiError.value = AI_NOT_READY
		showToast?.({ type: 'warning', title: 'Bilgi eksik', message: AI_NOT_READY })
		return
	}
	aiBusy[part] = true
	aiError.value = ''
	try {
		const { data } = await axios.post('/products/ai-content', aiDraftPayload([part]))
		const r = data.data
		if (part === 'descriptions') {
			form.public_name = r.public_name
			form.public_description = r.public_description
			form.tenant_description = r.tenant_description
		} else {
			// AI'nın yazdığı SEO alanları "elle girilmiş" sayılır: sonraki bilgi değişiklikleri ezmez.
			for (const key of ['meta_title', 'meta_description', 'meta_keywords']) {
				if (r[key]) { form[key] = r[key]; seoTouched[key] = true }
			}
		}
		aiGeneratedAt.value = new Date().toISOString()
		showToast?.({ type: 'success', title: part === 'seo' ? 'AI SEO metinleri üretildi' : 'AI açıklama üretildi', message: 'Gözden geçirip kaydedin.' })
	} catch (e) {
		const errs = e?.response?.data?.errors
		aiError.value = (errs && Object.values(errs)[0]?.[0]) || e?.response?.data?.message || 'AI çağrısı başarısız.'
		showToast?.({ type: 'error', title: 'AI Hatası', message: aiError.value })
	} finally {
		aiBusy[part] = false
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
		newImages.value.push({ file: f, preview: URL.createObjectURL(f), color: '' })
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
// Çok renkli üründe yalın fotoğraf bir renge etiketlenir (variant_colors): Creative mokap o rengi bu
// fotoğraftan giydirir, onaylanan görsel mağazada yalnız o rengin varyantlarına gider. Yeni fotoğrafta
// yüklerken, kayıtlı fotoğrafta kartın altındaki "Renk" seçimiyle. Giydirme çıktılarına (onmodel_*) verilmez.
const productColors = computed(() => [...new Set(form.variants.map((v) => (v.color_name || '').trim()).filter(Boolean))])
const isOnModel = (img) => String(img.path || img.url || '').includes('/onmodel_')
function setImageColor(img, color) {
	const variant_colors = color ? [color] : null
	router.put(`/products/images/${img.id}`, { variant_colors }, {
		preserveScroll: true,
		preserveState: true,
		onSuccess: () => showToast?.({ type: 'success', title: color ? `Görsel “${color}” rengine bağlandı` : 'Görsel bütün renklere ait' }),
		onError: () => showToast?.({ type: 'error', title: 'Görselin rengi kaydedilemedi' }),
	})
	existingImages.value = existingImages.value.map((x) => (x.id === img.id ? { ...x, variant_colors } : x))
}
// Sıra = kapak: ilk görsel kapak olur, mağaza galerisi de aynı sırayla gider (ProductImageController::reorder).
function saveImageOrder(successTitle) {
	const before = existingImages.value.map((x) => ({ ...x }))
	existingImages.value = existingImages.value.map((x, i) => ({ ...x, is_cover: i === 0 }))
	router.put(`/products/${props.product.id}/images/order`, { ids: existingImages.value.map((x) => x.id) }, {
		preserveScroll: true,
		preserveState: true,
		onSuccess: () => showToast?.({ type: 'success', title: successTitle }),
		onError: () => {
			existingImages.value = before
			showToast?.({ type: 'error', title: 'Görsel sırası kaydedilemedi' })
		},
	})
}
function onImagesSorted(e) {
	if (e.oldIndex !== e.newIndex) saveImageOrder('Görsel sırası kaydedildi')
}
function setCover(img) {
	existingImages.value = [img, ...existingImages.value.filter((x) => x.id !== img.id)]
	saveImageOrder('Kapak güncellendi')
}

// Listede olmayan ama ürünün varyantlarında kullanılan beden/renkler de görünsün
// (seçenek sonradan silinmiş/yeniden adlandırılmış olabilir), yoksa düzenlerken seçimi kaldırılamaz.
const sizeOptions = computed(() => [
	...props.sizeOptions,
	...selectedSizes.value.filter((s) => !props.sizeOptions.includes(s)),
])

const colorPalette = computed(() => [
	...props.colorOptions,
	...selectedColors.value.filter((c) => !props.colorOptions.some((o) => o.name === c.name)),
])

// Yerel kopya: yeni eklenen kategori, sayfa yeniden yüklenmeden listeye eklenebilsin diye.
const localCategories = ref([...props.categories])
const categoryOptions = computed(() =>
	localCategories.value.map((c) => ({ value: c.id, label: c.name ?? c.label })),
)

// Seçili kategoride (ve üst kategorilerinde) geçerli global özellikler (Ürün Ayarları → Özellikler).
// Kategori değişince form.attributes DEĞERLERİ elle temizlenmez (kullanıcı verisini kaybetmesin)
// — geçersiz anahtarlar backend'de kayıt anında ProductAttributeService::normalize() ile elenir.
// Değer: seçimde seçenek id'si, çoklu seçimde id dizisi, metin, evet/hayır.
const activeAttributeDefinitions = computed(() => props.attributeDefinitions[form.category_id] ?? [])

// Seçenek ikonu (ortak ikon kaydı; yıkama sembolleri gibi)
const optionIcon = (name) => lucideIcon(name) ?? (() => null)
// İkonlu seçenekleri olan çoklu seçim (ör. yıkama talimatları) açılır liste yerine ikonlu onay kutularıyla gösterilir
const hasOptionIcons = (def) => def.options.some((o) => o.icon)

function openCategoriesTab() {
	window.open('/products/categories', '_blank', 'noopener')
}

// Onay kutuları: seçili seçenek id'lerinin dizisi
function toggleAttributeOption(code, id, on) {
	const list = Array.isArray(form.attributes[code]) ? [...form.attributes[code]] : []
	form.attributes[code] = on ? [...new Set([...list, id])] : list.filter((x) => x !== id)
}

// Görsel/dosya: değer ya yeni seçilen File ya da kayıtlı path (önizleme adresi attributeUrls'te)
const attributeUrls = computed(() => props.product?.attributeUrls ?? {})
const attributePreviews = reactive({})
function setAttributeFile(code, files) {
	if (attributePreviews[code]) URL.revokeObjectURL(attributePreviews[code])
	const file = files[0] ?? null
	form.attributes[code] = file
	attributePreviews[code] = file && file.type.startsWith('image/') ? URL.createObjectURL(file) : ''
}
function attributeFileUrl(code) {
	const v = form.attributes[code]
	if (v instanceof File) return attributePreviews[code] || ''
	return typeof v === 'string' ? attributeUrls.value[code] ?? '' : ''
}
function attributeFileName(code) {
	const v = form.attributes[code]
	return v instanceof File ? v.name : typeof v === 'string' ? v.split('/').pop() : ''
}
function onAttributeFileReject(rejected) {
	const reason = rejected[0]?.reason === 'size' ? 'Dosya çok büyük.' : 'Bu dosya türü yüklenemez.'
	showToast?.({ type: 'error', title: 'Dosya eklenemedi', message: reason })
}

const canCreateCategory = computed(() => page.props.auth?.permissions?.includes?.('category.create') ?? true)
const canCreateBrand = computed(() => page.props.auth?.permissions?.includes?.('brand.manage') ?? true)

// Kategori/marka aramasında olmayan isim yazılınca OzCombobox'ın "ekle" satırı bunları çağırır;
// dönen seçenek combobox tarafından seçilir.
async function handleCreateCategory(name) {
	try {
		const { data } = await axios.post('/products/categories/quick-create', { name })
		if (!localCategories.value.some((c) => c.id === data.id)) {
			localCategories.value.push(data)
		}
		showToast?.({ type: 'success', title: 'Kategori eklendi', message: `"${data.name}" kategoriye eklendi.` })
		return { value: data.id, label: data.name }
	} catch (e) {
		showToast?.({ type: 'error', title: 'Kategori eklenemedi', message: e?.response?.data?.message || 'Bir hata oluştu.' })
		return null
	}
}

// Yerel kopya: yeni eklenen marka, sayfa yeniden yüklenmeden listeye eklenebilsin diye.
const localBrands = ref([...props.brands])
const brandOptions = computed(() =>
	localBrands.value.map((b) => ({ value: b.id, label: b.name ?? b.label })),
)

async function handleCreateBrand(name) {
	try {
		const { data } = await axios.post('/products/brands/quick-create', { name })
		if (!localBrands.value.some((b) => b.id === data.id)) {
			localBrands.value.push(data)
		}
		showToast?.({ type: 'success', title: 'Marka eklendi', message: `"${data.name}" markaya eklendi.` })
		return { value: data.id, label: data.name }
	} catch (e) {
		showToast?.({ type: 'error', title: 'Marka eklenemedi', message: e?.response?.data?.message || 'Bir hata oluştu.' })
		return null
	}
}

const skuTouched = ref(false)
const customColor = reactive({ name: '', hex: '#888888' })

const selectedSizes = ref([])   // string[]
const selectedColors = ref([])  // {name, hex}[]

const bulkPrice = ref(null)
const bulkStock = ref(null)
const bulkSku = ref('')
const bulkOldPrice = ref(null)

let variantKey = 0
const newKey = () => `v${++variantKey}`

const defaultForm = () => ({
	name: '',
	slug: '',
	sku: '',
	category_id: null,
	brand_id: null,
	is_new: false,
	is_featured: false,
	free_shipping: false,
	market_price: null,
	purchase_price: null,
	// Fiyat sekmesi: tüm varyantlara yazılır (varyant price/old_price'a çevrilir), ayrıca kaydedilmez.
	sale_price: null,
	discount_price: null,
	max_order_qty: null,
	material: '',
	attributes: {},
	// SEO
	meta_title: '',
	meta_description: '',
	meta_keywords: '',
	// Kargo
	weight: null,
	desi: null,
	length_cm: null,
	width_cm: null,
	height_cm: null,
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
				price: existing?.price ?? variantPrices().price,
				old_price: existing ? existing.old_price : variantPrices().old_price,
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
		...variantPrices(),
		stock: 0,
	})
}

function removeVariant(idx) {
	form.variants.splice(idx, 1)
}

/* ── Fiyat sekmesi ──
 * Satış fiyatı + indirimli fiyat tüm varyantlara yazılır. Varyant modeli: price = güncel (ödenen) fiyat,
 * old_price = üstü çizili fiyat. İndirim varsa price = indirimli, old_price = satış; yoksa old_price boş.
 * Varyant sekmesinden tek tek farklı fiyat verilebilir; o durumda burası "varyantlarda farklı" gösterir. */
const filledNum = (v) => v !== null && v !== undefined && v !== '' && Number.isFinite(Number(v))

const discountError = computed(() => {
	if (!filledNum(form.discount_price)) return ''
	if (!filledNum(form.sale_price)) return 'Önce satış fiyatını girin.'
	return Number(form.discount_price) < Number(form.sale_price) ? '' : 'İndirimli fiyat satış fiyatından düşük olmalı.'
})

function variantPrices() {
	const sale = filledNum(form.sale_price) ? Number(form.sale_price) : 0
	const hasDiscount = filledNum(form.discount_price) && !discountError.value
	return hasDiscount ? { price: Number(form.discount_price), old_price: sale } : { price: sale, old_price: null }
}

function applyPricesToVariants() {
	if (discountError.value) return
	const prices = variantPrices()
	form.variants.forEach((v) => Object.assign(v, prices))
}

// Varyant fiyatları farklıysa (varyant sekmesinden elle) üstteki alanlar boş kalır, uyarı gösterilir.
const variantPricesDiffer = computed(() => {
	const keys = new Set(form.variants.map((v) => `${Number(v.price) || 0}|${filledNum(v.old_price) ? Number(v.old_price) : ''}`))
	return keys.size > 1
})

// Düzenlemede: tüm varyantlar aynı fiyattaysa üstteki alanlar ondan doldurulur.
function pricesFromVariants(variants) {
	if (!variants.length) return { sale_price: null, discount_price: null }
	const keys = new Set(variants.map((v) => `${Number(v.price) || 0}|${filledNum(v.old_price) ? Number(v.old_price) : ''}`))
	if (keys.size > 1) return { sale_price: null, discount_price: null }
	const v = variants[0]
	const old = filledNum(v.old_price) ? Number(v.old_price) : null
	return old !== null && old > Number(v.price)
		? { sale_price: old, discount_price: Number(v.price) }
		: { sale_price: Number(v.price) || null, discount_price: null }
}

// Maliyet yazılınca satış fiyatı önerisi: maliyet × 2,5 (%150 kâr). Satış fiyatı elle girildiyse ezilmez.
const SALE_PRICE_MARKUP_PERCENT = 250
const salePriceTouched = ref(false)
function suggestSalePrice() {
	const cost = Number(form.purchase_price)
	if (salePriceTouched.value || !filledNum(form.purchase_price) || cost <= 0) return
	form.sale_price = Math.round(cost * SALE_PRICE_MARKUP_PERCENT) / 100
	applyPricesToVariants()
}

function onSalePriceInput() {
	salePriceTouched.value = true
	applyPricesToVariants()
}

/* ── KDV ── */
const VAT_CODE = 'kdv_orani'
const categoryVatRate = computed(() => form.category_id ? (props.categoryVatRates[form.category_id] ?? null) : null)
const vatSelectOptions = computed(() => [
	{ value: '', label: categoryVatRate.value !== null ? `Kategoriden (%${fmtRate(categoryVatRate.value)})` : 'Kategoriden' },
	...props.vatOptions.map((o) => ({ value: o.value, label: o.label })),
])
const effectiveVatRate = computed(() => {
	const picked = props.vatOptions.find((o) => o.value === Number(form.attributes[VAT_CODE]))
	return picked ? picked.rate : categoryVatRate.value
})
const vatModel = computed({
	get: () => form.attributes[VAT_CODE] ?? '',
	set: (v) => { form.attributes[VAT_CODE] = v === '' || v === null ? null : Number(v) },
})
function fmtRate(r) {
	return Number(r).toLocaleString('tr-TR', { maximumFractionDigits: 2 })
}
function fmtMoney(v) {
	return Number(v).toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ₺'
}
// Mağazada (Bagisto) görünecek KDV dahil fiyat — ProductPayloadMapper ile aynı hesap.
const grossHint = computed(() => {
	if (effectiveVatRate.value === null || !filledNum(form.sale_price)) return `KDV hariç.`
	const gross = (v) => fmtMoney(Math.round(Number(v) * (1 + effectiveVatRate.value / 100) * 100) / 100)
	const parts = [`KDV dahil mağaza fiyatı: ${gross(form.sale_price)}`]
	if (filledNum(form.discount_price) && !discountError.value) parts.push(`indirimli ${gross(form.discount_price)}`)
	return parts.join(' · ')
})
const marginHint = computed(() => {
	const { price } = variantPrices()
	if (!filledNum(form.purchase_price) || !price) return 'Girilirse satış fiyatı maliyet × 2,5 olarak önerilir.'
	const cost = Number(form.purchase_price)
	return `Kâr: ${fmtMoney(price - cost)} (%${fmtRate(((price - cost) / price) * 100)} marj)`
})

const filled = (v) => v !== null && v !== undefined && String(v).trim() !== ''

const canApplyBulk = computed(() =>
	[bulkSku.value, bulkPrice.value, bulkOldPrice.value, bulkStock.value].some(filled),
)

/**
 * Varyant tablosundaki "Tümüne uygula" satırı: doldurulan her alan tüm varyantlara yazılır, boş alanlar
 * dokunulmaz. SKU benzersiz olmak zorunda olduğundan aynen kopyalanmaz, kök olarak kullanılır:
 * KÖK-RENK-BEDEN (renk/bedensiz satırlar sıra no alır). Kayıtlı varyantların SKU'su ve stoku
 * değişmez (stok yalnız stok hareketiyle) — bunlar atlanır ve bildirilir.
 */
function applyBulkToVariants() {
	if (!canApplyBulk.value || !form.variants.length) return
	let skippedLocked = 0

	form.variants.forEach((v, idx) => {
		if (filled(bulkPrice.value)) v.price = Number(bulkPrice.value)
		if (filled(bulkOldPrice.value)) v.old_price = Number(bulkOldPrice.value)

		const wantsLocked = filled(bulkSku.value) || filled(bulkStock.value)
		if (v.persisted) {
			if (wantsLocked) skippedLocked++
			return
		}
		if (filled(bulkSku.value)) {
			const base = bulkSku.value.trim().toUpperCase()
			v.sku = v.size || v.color_name ? variantSku(base, v.size, v.color_name) : `${base}-${idx + 1}`
		}
		if (filled(bulkStock.value)) v.stock = Number(bulkStock.value)
	})

	showToast?.({
		type: skippedLocked ? 'warning' : 'success',
		title: `${form.variants.length} varyanta uygulandı`,
		message: skippedLocked
			? `${skippedLocked} kayıtlı varyantın SKU/stoku değiştirilmedi (stok yalnız stok hareketiyle değişir).`
			: undefined,
	})
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
		market_price: numOrNull(form.market_price),
		purchase_price: numOrNull(form.purchase_price),
		max_order_qty: numOrNull(form.max_order_qty),
		is_new: form.is_new ? 1 : 0,
		is_featured: form.is_featured ? 1 : 0,
		free_shipping: form.free_shipping ? 1 : 0,
		material: form.material?.trim() || null,
		attributes: { ...form.attributes },
		// SEO
		meta_title: form.meta_title?.trim() || null,
		meta_description: form.meta_description?.trim() || null,
		meta_keywords: form.meta_keywords?.trim() || null,
		// Kargo
		weight: numOrNull(form.weight),
		desi: numOrNull(form.desi),
		length_cm: numOrNull(form.length_cm),
		width_cm: numOrNull(form.width_cm),
		height_cm: numOrNull(form.height_cm),
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
		// images ile aynı sırada: yüklenen fotoğrafın rengi ('' = bütün renkler)
		image_colors: newImages.value.map((x) => x.color || ''),
	}
}

function submit() {
	if (busy.value) return
	if (discountError.value) {
		errors.value = { discount_price: discountError.value }
		focusFirstErrorTab(errors.value)
		return
	}
	busy.value = true
	errors.value = {}

	const payload = buildPayload()
	const hasFiles = (Array.isArray(payload.images) && payload.images.length > 0)
		|| Object.values(payload.attributes || {}).some((v) => v instanceof File)

	const opts = {
		preserveScroll: true,
		onSuccess: () => {
			showToast?.({
				type: 'success',
				title: isEdit.value ? 'Ürün Güncellendi' : 'Ürün taslak olarak eklendi',
				// Yeni ürün pasif başlar; hazır olunca Ürün Kartı'ndan yayınlanır (mağazaya o zaman gider).
				message: isEdit.value ? payload.name : `${payload.name} — hazır olunca Ürün Kartı'ndan yayınlayın`,
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
		market_price: p.marketPrice ?? null,
		purchase_price: p.purchasePrice ?? null,
		max_order_qty: p.maxOrderQty ?? null,
		is_new: !!p.isNew,
		is_featured: !!p.isFeatured,
		free_shipping: !!p.freeShipping,
		material: p.material ?? '',
		// datetime-local girdisi 'YYYY-MM-DDTHH:mm' bekler; kayıtlı 'YYYY-MM-DD HH:mm:ss' çevrilir
		attributes: Object.fromEntries(Object.entries(p.attributes || {}).map(([k, v]) => [
			k, typeof v === 'string' && /^\d{4}-\d\d-\d\d \d\d:\d\d:\d\d$/.test(v) ? v.replace(' ', 'T').slice(0, 16) : v,
		])),
		// SEO
		meta_title: p.metaTitle ?? '',
		meta_description: p.metaDescription ?? '',
		meta_keywords: p.metaKeywords ?? '',
		// Kargo
		weight: p.weight ?? null,
		desi: p.desi ?? null,
		length_cm: p.length_cm ?? null,
		width_cm: p.width_cm ?? null,
		height_cm: p.height_cm ?? null,
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
	// Kayıtlı SEO değerleri elle girilmiş sayılır (otomatik doldurma ezmez); slug kayıtlı ürünün adresidir.
	for (const key of Object.keys(seoTouched)) seoTouched[key] = String(form[key] ?? '').trim() !== '' || key === 'slug'

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
	Object.assign(form, pricesFromVariants(form.variants))
	salePriceTouched.value = true

	existingImages.value = Array.isArray(p.images) ? p.images.map((x) => ({ ...x })) : []
}

/* ── SEO: ürün bilgilerinden otomatik ──
 * Ad, marka, kategori, kategori özellikleri, kumaş ve açıklama girildikçe SEO alanları kurallarla
 * (AI'sız, anında) dolar. Elle değiştirilen ya da AI'nın yazdığı alan bir daha ezilmez; alan
 * tamamen silinirse otomatik doldurma geri döner. Sınırlar ProductSeoRules ile aynı (60 / 160 / 10). */
const SEO_TITLE_MAX = 60
const SEO_DESCRIPTION_MAX = 160
const SEO_KEYWORDS_MAX = 10
const seoTouched = reactive({ slug: false, meta_title: false, meta_description: false, meta_keywords: false })

function cutAtWord(text, max) {
	const t = String(text || '').replace(/\s+/g, ' ').trim()
	if (t.length <= max) return t
	const cut = t.slice(0, max)
	const space = cut.lastIndexOf(' ')
	return (space > max * 0.6 ? cut.slice(0, space) : cut).replace(/[\s,.;:\-–|]+$/, '')
}
const trLower = (t) => String(t).replace(/I/g, 'ı').replace(/İ/g, 'i').toLocaleLowerCase('tr-TR')
const stripMarkdown = (t) => String(t || '').replace(/[*_`#>]+/g, '').replace(/^\s*-\s+/gm, '').replace(/\s+/g, ' ').trim()

const seoBrandName = computed(() => {
	const b = localBrands.value.find((x) => x.id === form.brand_id)
	return b ? (b.name ?? b.label) : ''
})
const seoCategoryName = computed(() => {
	const c = localCategories.value.find((x) => x.id === form.category_id)
	return c ? (c.name ?? c.label) : ''
})
// Seçimli kategori özelliklerinin okunur değerleri (V Yaka, Slim Fit …)
const seoAttributeLabels = computed(() => activeAttributeDefinitions.value
	.filter((d) => ['select', 'multiselect', 'checkbox'].includes(d.type))
	.flatMap((d) => {
		const v = form.attributes[d.code]
		const ids = Array.isArray(v) ? v : v != null && v !== '' ? [v] : []
		return ids.map((id) => d.options.find((o) => o.value === Number(id))?.label).filter(Boolean)
	}))

const autoSeo = computed(() => {
	const name = form.name?.trim() || ''
	if (!name) return { slug: '', meta_title: '', meta_description: '', meta_keywords: '' }
	const brand = seoBrandName.value
	const category = seoCategoryName.value
	const attrs = seoAttributeLabels.value

	const withBrand = brand && !trLower(name).includes(trLower(brand)) ? `${name} | ${brand}` : name
	const meta_title = cutAtWord(withBrand.length <= SEO_TITLE_MAX ? withBrand : name, SEO_TITLE_MAX)

	const fromDescription = stripMarkdown(form.public_description)
	const facts = [
		[brand, name].filter(Boolean).join(' '),
		[...attrs.slice(0, 3), category].filter(Boolean).join(', '),
		form.material?.trim() ? `Kumaş: ${form.material.trim()}` : '',
	].filter(Boolean).join('. ')
	const meta_description = cutAtWord(fromDescription || `${facts}.`, SEO_DESCRIPTION_MAX)

	const words = [name, category && attrs[0] ? `${attrs[0]} ${category}` : '', category, brand, ...attrs, form.material?.trim() || '']
	const meta_keywords = [...new Set(words.filter(Boolean).map((w) => trLower(w).trim()).filter((w) => w.length <= 40))]
		.slice(0, SEO_KEYWORDS_MAX).join(', ')

	return { slug: slugify(name), meta_title, meta_description, meta_keywords }
})

watch(autoSeo, (next) => {
	for (const key of Object.keys(seoTouched)) {
		if (!seoTouched[key]) form[key] = next[key]
	}
})

// Kullanıcı yazınca alan elle girilmiş sayılır; tamamen silerse otomatiğe döner.
function onSeoInput(key) {
	if (key === 'slug') form.slug = slugify(form.slug)
	seoTouched[key] = String(form[key] ?? '').trim() !== ''
	if (!seoTouched[key]) form[key] = autoSeo.value[key]
}

function seoHint(key, max = null) {
	const len = String(form[key] ?? '').length
	const source = seoTouched[key] ? 'Elle girildi (silerseniz otomatiğe döner)' : 'Otomatik'
	if (!max) return key === 'meta_keywords' ? `${source} · virgülle ayırın` : source
	return `${source} · ${len}/${max} karakter${len > max ? ' — arama sonucunda kesilir' : ''}`
}

// Google arama sonucu önizlemesi
const seoPreview = computed(() => ({
	title: form.meta_title || form.name || 'Ürün adı',
	url: `/products/${form.slug || '…'}`,
	description: form.meta_description || 'Meta açıklama ürün bilgilerinden otomatik oluşturulur.',
}))

/* ── Desi: paket ölçülerinden önerilir (en × boy × yükseklik / 3000), elle değiştirilebilir ── */
const suggestedDesi = computed(() => {
	const [l, w, h] = [form.length_cm, form.width_cm, form.height_cm].map(Number)
	return l > 0 && w > 0 && h > 0 ? Math.round((l * w * h / 3000) * 100) / 100 : null
})
// Kullanıcı desiyi elle girdiyse ölçü değişikliği onu ezmez
const desiTouched = ref(false)
const desiHint = computed(() => {
	if (suggestedDesi.value === null) return 'Ölçüleri girerseniz otomatik hesaplanır'
	return Number(form.desi) === suggestedDesi.value ? 'Ölçülerden hesaplandı' : `Ölçülere göre ${suggestedDesi.value}`
})
watch(suggestedDesi, (v) => {
	if (v !== null && !desiTouched.value) form.desi = v
})

onMounted(() => {
	if (props.product) loadFromProduct(props.product)
	// Kayıtlı desi ölçülerden farklıysa elle girilmiş sayılır
	desiTouched.value = form.desi != null && form.desi !== '' && Number(form.desi) !== suggestedDesi.value
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
			<OzCard title="Temel Bilgiler" subtitle="Ürün adı ve barkod" class="block">
				<div class="stack">
					<OzField label="Ürün Adı" required :error="errors.name">
						<OzInput v-model="form.name" placeholder="örn. Basic Crew Tişört" @input="onNameInput" />
					</OzField>
					<OzField label="Barkod / GTIN" :error="errors.barcode">
						<div class="inline-row">
							<OzInput v-model="form.barcode" maxlength="64" placeholder="örn. 8690000000000" class="grow mono" />
							<OzButton icon="refresh" :loading="barcodeBusy" aria-label="Barkod üret" title="Barkod üret" @click="generateBarcode" />
						</div>
					</OzField>
				</div>
			</OzCard>

			<OzCard title="Organizasyon" subtitle="Ürünün kataloğdaki yeri ve sınıflandırması" class="block">
				<div class="stack">
					<div class="grid-2">
						<OzField label="Kategori" :error="errors.category_id">
							<OzCombobox v-model="form.category_id" :options="categoryOptions" placeholder="Kategori seçiniz…" search-placeholder="Kategori ara ya da yeni ekle…" empty-text="Sonuç bulunamadı" :create="canCreateCategory ? handleCreateCategory : null" create-text="kategorisini ekle" />
						</OzField>
						<OzField label="Marka" :error="errors.brand_id">
							<OzCombobox v-model="form.brand_id" :options="brandOptions" placeholder="— Marka yok —" clearable search-placeholder="Marka ara ya da yeni ekle…" empty-text="Sonuç bulunamadı" :create="canCreateBrand ? handleCreateBrand : null" create-text="markasını ekle" />
						</OzField>
					</div>
					<OzSwitch v-model="form.is_new" label="Yeni gelen olarak işaretle" description="Katalogda “Yeni” rozetiyle öne çıkar" />
					<OzSwitch v-model="form.is_featured" label="Öne çıkan ürün" description="Mağazanın (Bagisto) vitrinindeki “Öne Çıkanlar” bölümünde gösterilir" />
				</div>
			</OzCard>

			<OzCard title="Ürün Açıklaması" subtitle="Mağazada (Bagisto) müşterinin göreceği açıklama · markdown desteklenir" class="block">
				<template #actions>
					<div class="inline-row">
						<OzButton size="sm" @click="previewMode.public = !previewMode.public">
							{{ previewMode.public ? 'Metin' : 'Önizleme' }}
						</OzButton>
						<OzButton
							v-if="canUseAi"
							variant="primary"
							size="sm"
							icon="sparkles"
							:loading="aiBusy.descriptions"
							:title="aiReady ? 'Formdaki ürün bilgileriyle üretir (kaydetmek gerekmez)' : AI_NOT_READY"
							@click="generateAi('descriptions')"
						>
							AI ile Üret
						</OzButton>
					</div>
				</template>
				<div class="stack">
					<OzAlert v-if="aiError" tone="danger" :title="aiError" />
					<OzField :error="errors.public_description" :hint="'AI ile Üret; ad, marka, kategori, özellikler, kumaş ve materyalleri kullanır. Storefront adı ve B2B açıklamayı da (Açıklama sekmesi) doldurur.'">
						<OzTextarea
							v-if="!previewMode.public"
							v-model="form.public_description"
							:rows="8"
							aria-label="Ürün açıklaması"
							placeholder="Müşteriye storytelling — **kalın**, *italik*, - liste"
						/>
						<MarkdownPreview v-else :source="form.public_description" />
					</OzField>
				</div>
			</OzCard>
		</div>

		<!-- ── Fiyat & Stok ── -->
		<div v-show="activeTab === 'price'" class="panel" role="tabpanel">
			<OzCard title="Fiyatlandırma" subtitle="Fiyatlar KDV hariç girilir · tüm varyantlara uygulanır" class="block">
				<div class="stack">
					<OzAlert v-if="variantPricesDiffer" tone="info" title="Varyantlarda farklı fiyatlar var">
						Buraya girilen fiyat tüm varyantların fiyatını değiştirir. Tek tek fiyat için Stok-Varyant sekmesini kullanın.
					</OzAlert>
					<div class="grid-2">
						<OzField label="Satış Fiyatı" :error="errors.sale_price" :hint="grossHint">
							<OzInput v-model="form.sale_price" type="number" min="0" step="0.01" suffix="₺" placeholder="örn. 249.90" @input="onSalePriceInput" />
						</OzField>
						<OzField label="İndirimli Satış Fiyatı" :error="discountError || errors.discount_price" hint="Boşsa indirim yok; girilirse mağazada satış fiyatı üstü çizili görünür.">
							<OzInput v-model="form.discount_price" type="number" min="0" step="0.01" suffix="₺" placeholder="İndirim yoksa boş" @input="applyPricesToVariants" />
						</OzField>
						<OzField label="Maliyet" :error="errors.purchase_price" :hint="marginHint">
							<OzInput v-model="form.purchase_price" type="number" min="0" step="0.01" suffix="₺" placeholder="örn. 89.90" @input="suggestSalePrice" />
						</OzField>
						<OzField label="KDV Oranı" :error="errors['attributes.kdv_orani']" hint="Boş bırakılırsa kategorinin oranı geçerli. Seçenekler Ürün Ayarları › Özellikler › KDV Oranı'ndan gelir.">
							<OzSelect v-model="vatModel" :options="vatSelectOptions" />
						</OzField>
						<OzField label="Piyasa Fiyatı" :error="errors.market_price" hint="Karşılaştırma için (opsiyonel); mağazaya gitmez.">
							<OzInput v-model="form.market_price" type="number" min="0" step="0.01" suffix="₺" placeholder="örn. 299.90" />
						</OzField>
					</div>
				</div>
			</OzCard>

			<OzCard title="Stok & Satış Kuralları" subtitle="Stok kodu ve sipariş sınırı" class="block">
				<div class="stack">
					<div class="grid-2">
						<OzField
							label="Stok Kodu (SKU)"
							required
							:error="errors.sku"
							:hint="isEdit ? 'Oluşturulduktan sonra değiştirilemez (Bagisto eşleşmesi).' : 'Boş bırakıp ⟳ ile otomatik üretebilirsiniz (marka + ürün adı gerekli). Varyant kodları bundan türetilir.'"
						>
							<div class="inline-row">
								<OzInput v-model="form.sku" placeholder="TEK-XXX-001" class="grow mono" :readonly="isEdit" @input="skuTouched = true" />
								<OzButton v-if="!isEdit" icon="refresh" :disabled="!form.name || !form.brand_id" aria-label="Otomatik SKU üret" @click="generateBaseSku" />
							</div>
						</OzField>
						<OzField label="Maksimum Satış Adedi" :error="errors.max_order_qty" hint="Bir siparişte bu üründen (tüm varyantlar) en fazla kaç adet alınabilir. Boş = sınırsız.">
							<OzInput v-model="form.max_order_qty" type="number" min="1" step="1" suffix="adet" placeholder="Sınırsız" />
						</OzField>
					</div>
				</div>
			</OzCard>
		</div>

		<!-- ── SEO ── -->
		<div v-show="activeTab === 'seo'" class="panel" role="tabpanel">
			<OzCard title="Arama Motoru Optimizasyonu" subtitle="Ürün bilgilerinden otomatik oluşur · elle değiştirdiğiniz alan korunur" class="block">
				<template #actions>
					<OzButton
						v-if="canUseAi"
						variant="primary"
						size="sm"
						icon="sparkles"
						:loading="aiBusy.seo"
						:title="aiReady ? 'Formdaki ürün bilgileriyle üretir (kaydetmek gerekmez)' : AI_NOT_READY"
						@click="generateAi('seo')"
					>
						AI ile İyileştir
					</OzButton>
				</template>
				<div class="stack">
					<OzAlert v-if="aiError" tone="danger" :title="aiError" />
					<div class="serp" aria-label="Arama sonucu önizlemesi">
						<span class="serp-url">{{ seoPreview.url }}</span>
						<span class="serp-title">{{ seoPreview.title }}</span>
						<span class="serp-desc">{{ seoPreview.description }}</span>
					</div>
					<OzField label="URL (slug)" :error="errors.slug" :hint="isEdit ? 'Kayıtlı ürünün adresi; değiştirirseniz eski bağlantılar çalışmaz.' : seoHint('slug')">
						<OzInput v-model="form.slug" placeholder="ürün adından oluşur" @input="onSeoInput('slug')" />
					</OzField>
					<OzField label="Meta Başlık" :error="errors.meta_title" :hint="seoHint('meta_title', SEO_TITLE_MAX)">
						<OzInput v-model="form.meta_title" maxlength="191" placeholder="ürün adından oluşur" @input="onSeoInput('meta_title')" />
					</OzField>
					<OzField label="Meta Açıklama" :error="errors.meta_description" :hint="seoHint('meta_description', SEO_DESCRIPTION_MAX)">
						<OzTextarea v-model="form.meta_description" :rows="3" :maxlength="500" placeholder="açıklama ve özelliklerden oluşur" @input="onSeoInput('meta_description')" />
					</OzField>
					<OzField label="Anahtar Kelimeler" :error="errors.meta_keywords" :hint="seoHint('meta_keywords')">
						<OzInput v-model="form.meta_keywords" maxlength="255" placeholder="ad, kategori, marka ve özelliklerden oluşur" @input="onSeoInput('meta_keywords')" />
					</OzField>
				</div>
			</OzCard>
		</div>

		<!-- ── Kargo ── -->
		<div v-show="activeTab === 'shipping'" class="panel" role="tabpanel">
			<OzCard title="Kargo" subtitle="Kargo etiketi ve teslimat bilgileri (opsiyonel)" class="block">
				<div class="stack">
					<OzSwitch v-model="form.free_shipping" label="Ücretsiz kargo" />
					<div class="grid-2">
						<OzField label="Ağırlık" :error="errors.weight">
							<OzInput v-model="form.weight" type="number" min="0" step="0.001" suffix="kg" placeholder="örn. 0.250" />
						</OzField>
						<OzField label="Desi" :error="errors.desi" :hint="desiHint">
							<OzInput v-model="form.desi" type="number" min="0" step="0.01" placeholder="örn. 1.5" @input="desiTouched = true" />
						</OzField>
					</div>
					<div class="grid-3">
						<OzField label="En" :error="errors.length_cm">
							<OzInput v-model="form.length_cm" type="number" min="0" step="0.1" suffix="cm" placeholder="Paket" />
						</OzField>
						<OzField label="Boy" :error="errors.width_cm">
							<OzInput v-model="form.width_cm" type="number" min="0" step="0.1" suffix="cm" placeholder="Paket" />
						</OzField>
						<OzField label="Yükseklik" :error="errors.height_cm">
							<OzInput v-model="form.height_cm" type="number" min="0" step="0.1" suffix="cm" placeholder="Paket" />
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
					<a v-if="can('product-settings.view') && can('variant-option.view')" href="/products/settings?tab=sizes" target="_blank" rel="noopener" class="options-link">Beden & renk listesini düzenle ↗</a>
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

			<OzCard title="Varyantlar" subtitle="Her varyant için SKU, fiyat ve stok girin; ilk satırdan hepsine birden uygulayabilirsiniz" class="block">
				<template #actions>
					<div class="inline-row">
						<OzPill v-if="form.variants.length" tone="accent">{{ form.variants.length }} satır</OzPill>
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
					<div class="variant-row variant-bulk" @keydown.enter.prevent="applyBulkToVariants">
						<span class="variant-bulk-label">Tümüne uygula</span>
						<OzInput v-model="bulkSku" placeholder="Kök SKU" class="mono" title="Her varyanta KÖK-RENK-BEDEN olarak uygulanır" />
						<OzInput v-model="bulkPrice" type="number" min="0" step="0.01" placeholder="Fiyat" />
						<OzInput v-model="bulkOldPrice" type="number" min="0" step="0.01" placeholder="Eski fiyat" />
						<OzInput v-model="bulkStock" type="number" min="0" placeholder="Stok" />
						<button type="button" class="variant-apply" :disabled="!canApplyBulk" :aria-label="`Doldurulan alanları ${form.variants.length} varyantın hepsine uygula`" :title="`${form.variants.length} varyantın hepsine uygula`" @click="applyBulkToVariants">
							<Check :size="13" />
						</button>
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
			<OzCard title="Görseller" subtitle="Sürükleyerek sıralayın · ilk görsel kapak olur" class="block">
				<div class="stack">
					<!-- Kayıtlı görseller sürükle-bırakla sıralanır; ilk sıradaki kapak olur (yeniler kayıttan sonra sona eklenir). -->
					<draggable
						v-if="existingImages.length || newImages.length"
						v-model="existingImages"
						item-key="id"
						tag="div"
						class="img-grid"
						:animation="150"
						filter=".img-actions, .img-color"
						:prevent-on-filter="false"
						ghost-class="img-ghost"
						@end="onImagesSorted"
					>
						<template #item="{ element: img }">
						<div class="img-thumb draggable" :class="{ cover: img.is_cover }" title="Sıralamak için sürükleyin">
							<img :src="img.url" alt="" draggable="false" />
							<span v-if="img.is_cover" class="img-badge cover-badge">Kapak</span>
							<div class="img-actions">
								<button v-if="!img.is_cover" type="button" class="img-act" title="Kapak yap" @click="setCover(img)"><Star :size="13" /></button>
								<button type="button" class="img-act del" title="Sil" @click="deleteExisting(img)"><X :size="13" /></button>
							</div>
							<label v-if="productColors.length > 1 && !isOnModel(img)" class="img-color" :class="{ set: img.variant_colors?.length }"
								title="Bu fotoğraf hangi rengin? Mokap giydirmesi bu fotoğrafla yapılır.">
								<span class="img-color-label">Renk</span>
								<select :value="img.variant_colors?.[0] ?? ''" @change="setImageColor(img, $event.target.value)">
									<option value="">Tüm renkler</option>
									<option v-for="c in productColors" :key="c" :value="c">{{ c }}</option>
								</select>
							</label>
						</div>
						</template>
						<template #footer>
						<div v-for="(img, i) in newImages" :key="'n' + i" class="img-thumb new">
							<img :src="img.preview" alt="" />
							<span class="img-badge new-badge">Yeni</span>
							<div class="img-actions">
								<button type="button" class="img-act del" title="Kaldır" @click="removeNewImage(i)"><X :size="13" /></button>
							</div>
							<label v-if="productColors.length > 1" class="img-color" :class="{ set: img.color }" title="Bu fotoğraf hangi rengin? Kaydedince uygulanır.">
								<span class="img-color-label">Renk</span>
								<select v-model="img.color">
									<option value="">Tüm renkler</option>
									<option v-for="c in productColors" :key="c" :value="c">{{ c }}</option>
								</select>
							</label>
						</div>
						</template>
					</draggable>
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
			<OzCard v-if="!activeAttributeDefinitions.length" title="Kategori Özellikleri" class="block">
				<OzEmptyState
					icon="sliders"
					:title="form.category_id ? 'Bu kategoriye atanmış özellik yok' : 'Önce kategori seçin'"
					:description="form.category_id
						? 'Kalıp, yaka tipi gibi özellikler Kategoriler → satır menüsü → Özellikler ekranından kategoriye atanınca burada çıkar. Üst kategoriye atamak alt kategorilere de geçer.'
						: 'Özellikler, Genel sekmesinde seçilen kategoriye göre burada listelenir.'"
					compact
				>
					<template v-if="form.category_id && can('category.manage')" #actions>
						<OzButton size="sm" icon="external-link" @click="openCategoriesTab">Kategorilere git</OzButton>
					</template>
				</OzEmptyState>
			</OzCard>
			<OzCard v-else title="Kategori Özellikleri" subtitle="Seçili kategoride geçerli özellikler · mağazada (Bagisto) ürün özelliği ve filtre olarak görünür" class="block">
				<template v-if="can('product-settings.view') && can('product-attribute.view')" #actions>
					<a href="/products/settings?tab=attributes" target="_blank" rel="noopener" class="options-link">Özellikleri düzenle ↗</a>
				</template>
				<div class="stack">
					<div class="grid-2 attr-grid">
						<template v-for="def in activeAttributeDefinitions" :key="def.code">
							<OzField v-if="def.type === 'boolean'" :label="def.name" :error="errors[`attributes.${def.code}`]">
								<OzSwitch v-model="form.attributes[def.code]" :label="form.attributes[def.code] ? 'Evet' : 'Hayır'" />
							</OzField>
							<OzField
								v-else
								:label="def.name"
								:required="def.required"
								:error="errors[`attributes.${def.code}`] || errors[`attributes.${def.code}.0`]"
								:class="{ 'span-2': ['textarea', 'checkbox', 'image', 'file'].includes(def.type) || (def.type === 'multiselect' && hasOptionIcons(def)) }"
							>
								<OzCombobox
									v-if="def.type === 'select'"
									v-model="form.attributes[def.code]"
									:options="def.options"
									placeholder="Seçin"
									clearable
								/>
								<OzCombobox
									v-else-if="def.type === 'multiselect' && !hasOptionIcons(def)"
									:model-value="form.attributes[def.code] ?? []"
									:options="def.options"
									placeholder="Seçin"
									multiple
									@update:model-value="(v) => (form.attributes[def.code] = v)"
								/>
								<div v-else-if="def.type === 'checkbox' || def.type === 'multiselect'" class="attr-checks">
									<div v-for="o in def.options" :key="o.value" class="attr-check">
										<OzIcon v-if="o.icon" :name="optionIcon(o.icon)" :size="22" class="attr-check-icon" />
										<OzCheckbox
											:model-value="(form.attributes[def.code] ?? []).includes(o.value)"
											:label="o.label"
											@update:model-value="(on) => toggleAttributeOption(def.code, o.value, on)"
										/>
									</div>
								</div>
								<OzTextarea v-else-if="def.type === 'textarea'" v-model="form.attributes[def.code]" :rows="3" maxlength="5000" />
								<OzInput v-else-if="def.type === 'number'" v-model="form.attributes[def.code]" type="number" step="any" />
								<OzInput v-else-if="def.type === 'price'" v-model="form.attributes[def.code]" type="number" min="0" step="0.01" suffix="₺" />
								<OzInput v-else-if="def.type === 'date'" v-model="form.attributes[def.code]" type="date" />
								<OzInput v-else-if="def.type === 'datetime'" v-model="form.attributes[def.code]" type="datetime-local" />
								<template v-else-if="def.type === 'image' || def.type === 'file'">
									<div v-if="form.attributes[def.code]" class="attr-file">
										<img v-if="def.type === 'image' && attributeFileUrl(def.code)" :src="attributeFileUrl(def.code)" :alt="def.name" class="attr-file-thumb" />
										<a v-if="attributeFileUrl(def.code) && !(form.attributes[def.code] instanceof File)" :href="attributeFileUrl(def.code)" target="_blank" rel="noopener" class="attr-file-name">{{ attributeFileName(def.code) }}</a>
										<span v-else class="attr-file-name">{{ attributeFileName(def.code) }}</span>
										<OzButton size="sm" icon="trash" @click="setAttributeFile(def.code, [])">Kaldır</OzButton>
									</div>
									<OzFileDrop
										v-else
										:model-value="[]"
										:accept="def.type === 'image' ? '.jpg,.jpeg,.png,.webp' : '.pdf,.jpg,.jpeg,.png,.webp'"
										:max-size="(def.type === 'image' ? 5 : 10) * 1024 * 1024"
										:title="def.type === 'image' ? 'Görseli sürükleyin ya da seçin' : 'Dosyayı sürükleyin ya da seçin'"
										@update:model-value="(files) => setAttributeFile(def.code, files)"
										@reject="onAttributeFileReject"
									/>
								</template>
								<OzInput v-else v-model="form.attributes[def.code]" maxlength="255" />
							</OzField>
						</template>
					</div>
				</div>
			</OzCard>
		</div>

		<!-- ── Açıklama ── -->
		<div v-show="activeTab === 'description'" class="panel" role="tabpanel">
			<OzCard title="Üretim Bilgileri" subtitle="Kumaş içeriği (opsiyonel) · mağazada ürün bilgisi olarak görünür, AI açıklamada kullanılır · yıkama talimatı Özellikler sekmesinde" class="block">
				<div class="stack">
					<OzField label="Kumaş İçeriği (özet)" :error="errors.material">
						<OzInput v-model="form.material" placeholder="örn. %100 Pamuk" />
					</OzField>
				</div>
			</OzCard>

			<OzCard title="Materyaller" subtitle="AI açıklamada kullanılacak kumaş ve aksesuarlar" class="block">
				<div class="stack">
					<MaterialPicker v-model="form.description_materials" />
					<p v-if="firstMaterialError" class="field-error">{{ firstMaterialError }}</p>
				</div>
			</OzCard>

			<OzCard title="AI Ürün Açıklaması" subtitle="Formdaki ürün bilgilerinden iki tonda metin · kaydetmeden üretilebilir" class="block">
				<template #actions>
					<div class="inline-row">
						<OzPill v-if="aiGeneratedAt" tone="success">Son üretim: {{ new Date(aiGeneratedAt).toLocaleString('tr-TR') }}</OzPill>
						<OzButton
							v-if="canUseAi"
							variant="primary"
							size="sm"
							icon="sparkles"
							:loading="aiBusy.descriptions"
							:title="aiReady ? 'Formdaki ürün bilgileriyle üretir (kaydetmek gerekmez)' : AI_NOT_READY"
							@click="generateAi('descriptions')"
						>
							AI ile Üret
						</OzButton>
					</div>
				</template>
				<div class="stack">
					<OzAlert v-if="!aiReady" tone="info" title="AI için ürün bilgisi gerekli">
						Genel sekmesinde ürün adını ve kategoriyi girin. Özellikler, kumaş ve materyaller girildikçe AI metni daha isabetli olur.
					</OzAlert>
					<OzAlert v-if="aiError" tone="danger" :title="aiError" />

					<OzField label="Storefront Adı" :error="errors.public_name" hint="B2C, opsiyonel — boşsa “Ürün Adı” kullanılır. AI ile Üret bunu da doldurur.">
						<OzInput v-model="form.public_name" maxlength="255" placeholder="örn. Ege Pamuğu Basic Tişört" />
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
			<OzCard title="Özel Kodlar & Detaylar" subtitle="Üretici, gümrük kodları ve yerli üretim bilgisi (opsiyonel)" class="block">
				<div class="stack">
					<div class="grid-2">
						<OzField label="Üretici / Tedarikçi Kodu" :error="errors.manufacturer_code">
							<OzInput v-model="form.manufacturer_code" maxlength="64" placeholder="İç üretici kodu" />
						</OzField>
						<OzField label="GTIP / HS Kodu" :error="errors.gtip_code">
							<OzInput v-model="form.gtip_code" maxlength="32" placeholder="Gümrük tarife pozisyon kodu" class="mono" />
						</OzField>
					</div>
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
.options-link { align-self: flex-end; font-size: 11.5px; font-weight: 600; color: var(--oz-accent); text-decoration: none; }
.options-link:hover { text-decoration: underline; }
.block { margin-bottom: 14px; }
.serp { display: flex; flex-direction: column; gap: 2px; padding: 12px 14px; border: 1px solid var(--oz-border); border-radius: var(--oz-radius); background: var(--oz-surface-2); }
.serp-url { font-size: 12px; color: var(--oz-muted); }
.serp-title { font-size: 16px; color: var(--oz-accent); font-weight: 500; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.serp-desc { font-size: 13px; color: var(--oz-muted); line-height: 1.45; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.panels { display: flex; flex-direction: column; }
.panel { display: flex; flex-direction: column; }
.stack { display: flex; flex-direction: column; gap: 14px; padding: 18px; }
.grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
.grid-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; }
.span-2 { grid-column: 1 / -1; }
/* Tam satır alanlar (onay kutuları, ikonlu seçim, dosya) arada tek kalan yarım alanın sağını boş bırakmasın:
   dense, boşluğu sıradaki yarım alanla doldurur (ör. Sezon | Menşei yan yana). */
.attr-grid { grid-auto-flow: row dense; }
.attr-checks { display: flex; flex-wrap: wrap; gap: 8px 18px; padding-top: 4px; }
.attr-check { display: inline-flex; align-items: center; gap: 6px; }
.attr-check-icon { color: var(--oz-ink-soft); flex-shrink: 0; }
.attr-file { display: flex; align-items: center; gap: 12px; }
.attr-file-thumb { width: 56px; height: 56px; object-fit: contain; border: 1px solid var(--oz-border); border-radius: 8px; background: #fff; }
.attr-file-name { flex: 1; min-width: 0; font-size: 12px; color: var(--oz-ink-soft); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
a.attr-file-name { color: var(--oz-accent); }
@media (max-width: 640px) {
	.grid-2, .grid-3 { grid-template-columns: 1fr; }
}

.inline-row { display: flex; gap: 8px; align-items: center; }
.inline-row .grow { flex: 1; min-width: 0; }
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
/* "Tümüne uygula" satırı: renk+beden sütunlarını tek etiket kaplar, kalan sütunlar tabloyla hizalı */
.variant-bulk { background: var(--oz-accent-soft); border-style: dashed; border-color: var(--oz-accent); }
.variant-bulk-label { grid-column: span 2; font-size: 11px; font-weight: 700; color: var(--oz-accent); text-transform: uppercase; letter-spacing: .04em; }
.variant-apply {
	background: var(--oz-accent); border: 1px solid var(--oz-accent); color: #fff;
	width: 24px; height: 24px; border-radius: 6px;
	display: flex; align-items: center; justify-content: center;
	cursor: pointer; transition: opacity .12s;
}
.variant-apply:disabled { opacity: .4; cursor: not-allowed; }

/* ── Görseller ── */
.img-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(104px, 1fr)); gap: 10px; }
.img-thumb { position: relative; aspect-ratio: 1; border-radius: 10px; overflow: hidden; border: 1px solid var(--oz-border); background: var(--oz-surface-2); }
.img-thumb.cover { border-color: var(--oz-accent); box-shadow: 0 0 0 2px rgba(var(--oz-accent-rgb), .2); }
.img-thumb.draggable { cursor: grab; }
.img-thumb.draggable:active { cursor: grabbing; }
.img-ghost { opacity: .4; }
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
.img-color { position: absolute; left: 0; right: 0; bottom: 0; display: flex; align-items: center; gap: 6px; padding: 5px 7px; background: var(--oz-surface); border-top: 1px solid var(--oz-border); font-size: 11.5px; }
.img-color.set { background: var(--oz-primary-soft, var(--oz-surface-2)); }
.img-color-label { font-weight: 700; color: var(--oz-ink-soft); }
.img-color select { flex: 1; min-width: 0; font-size: 11.5px; padding: 2px 4px; border-radius: 6px; border: 1px solid var(--oz-border); background: var(--oz-surface); color: var(--oz-ink); }
</style>
