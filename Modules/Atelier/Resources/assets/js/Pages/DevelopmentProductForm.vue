<script setup>
import { ref, computed, inject, watch } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import {
	OzPageHeader, OzCard, OzButton, OzField, OzInput, OzSelect, OzCombobox, OzAlert, OzStatTile,
} from '@/Components/Oz'
import { formatMoney, formatNumber } from '../atelierNav'
import RouteStepsEditor from '../Components/RouteStepsEditor.vue'
import axios from 'axios'
import { useCan } from '@/composables/useCan'

defineOptions({ layout: AppLayout })

const props = defineProps({
	categories: { type: Array, default: () => [] },
	brands: { type: Array, default: () => [] },
	materials: { type: Array, default: () => [] },
	operations: { type: Array, default: () => [] },
	fasonSuppliers: { type: Array, default: () => [] },
	// Ürün Ayarları → Özellikler › Cinsiyet seçenekleri [{ value: id, label }]
	genderOptions: { type: Array, default: () => [] },
})

const showToast = inject('showToast', null)

const { can } = useCan()

// Yerel kopyalar: aramadan hızlı eklenen kategori/marka sayfa yenilenmeden listede görünsün.
const localCategories = ref([...props.categories])
const localBrands = ref([...props.brands])
const categoryOptions = computed(() => localCategories.value.map((c) => ({ value: c.id, label: c.name })))
const brandOptions = computed(() => localBrands.value.map((b) => ({ value: b.id, label: b.name })))

// OzCombobox "ekle" satırı: aranan isimle kaydı oluşturur, dönen seçenek otomatik seçilir.
function quickCreate(url, list, noun) {
	return async (name) => {
		try {
			const { data } = await axios.post(url, { name })
			if (!list.value.some((x) => x.id === data.id)) list.value.push(data)
			showToast?.({ type: 'success', title: `${noun} eklendi`, message: `"${data.name}" eklendi.` })
			return { value: data.id, label: data.name }
		} catch (e) {
			showToast?.({ type: 'error', title: `${noun} eklenemedi`, message: e?.response?.data?.message || 'Bir hata oluştu.' })
			return null
		}
	}
}
const createCategory = can('category.create') ? quickCreate('/products/categories/quick-create', localCategories, 'Kategori') : null
const createBrand = can('brand.manage') ? quickCreate('/products/brands/quick-create', localBrands, 'Marka') : null
const materialOptions = computed(() => props.materials.map((m) => ({ value: m.id, label: m.name, description: `${m.code} · ${formatMoney(m.unitCost)} / ${m.unit}` })))
const materialById = computed(() => Object.fromEntries(props.materials.map((m) => [m.id, m])))

const emptyVariant = () => ({ size: '', color_name: '', sku: '', skuTouched: false })
const emptyLine = () => ({ material_id: '', quantity_per_unit: 1, waste_pct: 0 })

const form = useForm({
	name: '', sku: '', category_id: '', brand_id: '', gender: '',
	variants: [emptyVariant()],
	bom_name: 'Varsayılan Reçete',
	lines: [emptyLine()],
	extra_cost_per_unit: 0,
	margin_pct: 50,
	price: 0,
	route: [],
})

// Opsiyonel üretim rotası: tanımlanırsa ilk iş emri bu rotayla dolar. Öneri = operasyon sırası.
function startRoute() {
	form.route = props.operations.map((o, i) => ({
		operation_id: o.id, sequence: i + 1, location_type: o.default_location, fason_supplier_id: null, unit_cost: Number(o.default_unit_cost),
	}))
}

/* ── varyant SKU'su: elle değiştirilmedikçe ürün SKU + beden + renkten türetilir ── */
const slug = (s) => (s ?? '').toString().trim().toLocaleUpperCase('tr')
	.replace(/Ç/g, 'C').replace(/Ğ/g, 'G').replace(/İ/g, 'I').replace(/Ö/g, 'O').replace(/Ş/g, 'S').replace(/Ü/g, 'U')
	.replace(/[^A-Z0-9]+/g, '')
const autoSku = (v) => [slug(form.sku), slug(v.size), slug(v.color_name)].filter(Boolean).join('-')
watch(
	() => [form.sku, ...form.variants.map((v) => `${v.size}|${v.color_name}`)],
	() => form.variants.forEach((v) => { if (!v.skuTouched) v.sku = autoSku(v) }),
)
function addVariant() { form.variants.push(emptyVariant()) }
function removeVariant(i) { form.variants.splice(i, 1) }

/* ── reçete + ön maliyet (sunucu kayıtta aynı formülle yeniden hesaplar) ── */
function lineCost(line) {
	const m = materialById.value[line.material_id]
	if (!m) return 0
	return Number(line.quantity_per_unit || 0) * (1 + Number(line.waste_pct || 0) / 100) * m.unitCost
}
const materialCost = computed(() => form.lines.reduce((s, l) => s + lineCost(l), 0))
const unitCost = computed(() => materialCost.value + Number(form.extra_cost_per_unit || 0))
const suggestedPrice = computed(() => Math.round(unitCost.value * (1 + Number(form.margin_pct || 0) / 100) * 100) / 100)
function addLine() { form.lines.push(emptyLine()) }
function removeLine(i) { form.lines.splice(i, 1) }

/* ── satış fiyatı: elle değiştirilmedikçe önerilen fiyatı izler ── */
const priceTouched = ref(false)
watch(suggestedPrice, (p) => { if (!priceTouched.value) form.price = p }, { immediate: true })
function onPriceInput() { priceTouched.value = true }
function resetPrice() {
	priceTouched.value = false
	form.price = suggestedPrice.value
}
const actualMargin = computed(() => (unitCost.value > 0 ? ((Number(form.price) - unitCost.value) / unitCost.value) * 100 : null))

const err = (key) => form.errors[key]

function submit() {
	if (form.processing) return
	form.transform((d) => ({
		...d,
		brand_id: d.brand_id || null,
		variants: d.variants.map(({ size, color_name, sku }) => ({ size, color_name, sku })),
		route: d.route.map((st) => ({ ...st, fason_supplier_id: st.location_type === 'fason' ? st.fason_supplier_id : null })),
	})).post('/atelier/new-product', {
		onError: (errs) => showToast?.({ type: 'error', title: 'Kayıt başarısız', message: Object.values(errs)[0] || 'Doğrulama hatası.' }),
	})
}
</script>

<template>
	<Head title="Üretilecek Ürün Ekle" />

	<OzPageHeader title="Üretilecek Ürün Ekle">
		<template #subtitle>Reçete ve maliyetle <b>geçici stok kartı</b> açılır · ilk üretim stoğa girince ürüne dönüşür</template>
		<template #actions>
			<Link href="/products"><OzButton icon="x">Vazgeç</OzButton></Link>
			<OzButton variant="primary" icon="check" :loading="form.processing" @click="submit">Geçici Kartı Aç</OzButton>
		</template>
	</OzPageHeader>

	<OzAlert tone="info" title="Nasıl çalışır?" class="intro">
		Kart “Geçici Kart” durumunda açılır: stok 0'dır, mağazaya (Bagisto) gönderilmez ve bayilere görünmez.
		Kaydettikten sonra iş emri ekranına yönlendirilirsiniz. İlk üretim stoğa alınınca ürün <b>Taslak</b> durumuna geçer;
		iş emri kapatılınca gerçek birim maliyet alış fiyatı olarak yazılır.
		Yayına almak ayrı bir adımdır.
	</OzAlert>

	<form class="layout" @submit.prevent="submit">
		<div class="main">
			<OzCard title="1 · Ürün Kimliği">
				<div class="body grid-2">
					<OzField label="Ürün Adı" required :error="err('name')" class="span-2">
						<OzInput v-model="form.name" placeholder="Örn. Keten Gömlek" />
					</OzField>
					<OzField label="Ürün SKU" required :error="err('sku')">
						<OzInput v-model="form.sku" placeholder="Örn. KTN-GML-01" />
					</OzField>
					<OzField label="Cinsiyet" required :error="err('gender')">
						<OzSelect v-model="form.gender" :options="genderOptions" placeholder="Seçin" />
					</OzField>
					<OzField label="Kategori" required :error="err('category_id')">
						<OzCombobox v-model="form.category_id" :options="categoryOptions" placeholder="Kategori seçin" search-placeholder="Kategori ara ya da yeni ekle…" :create="createCategory" create-text="kategorisini ekle" />
					</OzField>
					<OzField label="Marka" :error="err('brand_id')">
						<OzCombobox v-model="form.brand_id" :options="brandOptions" placeholder="Markasız" clearable search-placeholder="Marka ara ya da yeni ekle…" :create="createBrand" create-text="markasını ekle" />
					</OzField>
				</div>
			</OzCard>

			<OzCard title="2 · Varyantlar" :meta="`${form.variants.length} varyant`">
				<div class="body rows">
					<p v-if="err('variants')" class="row-error">{{ err('variants') }}</p>
					<div v-for="(v, i) in form.variants" :key="i" class="row row-variant">
						<OzField label="Beden" :error="err(`variants.${i}.size`)">
							<OzInput v-model="v.size" placeholder="M" />
						</OzField>
						<OzField label="Renk" :error="err(`variants.${i}.color_name`)">
							<OzInput v-model="v.color_name" placeholder="Ekru" />
						</OzField>
						<OzField label="Varyant SKU" :error="err(`variants.${i}.sku`)">
							<OzInput v-model="v.sku" @update:model-value="v.skuTouched = true" />
						</OzField>
						<OzButton class="row-remove" size="sm" icon="trash" :disabled="form.variants.length === 1" :aria-label="`${i + 1}. varyantı kaldır`" @click="removeVariant(i)" />
					</div>
					<OzButton size="sm" icon="plus" @click="addVariant">Varyant Ekle</OzButton>
				</div>
			</OzCard>

			<OzCard title="3 · Reçete (1 adet için)" :meta="`${form.lines.length} satır`">
				<div class="body rows">
					<OzField label="Reçete Adı" :error="err('bom_name')" class="bom-name">
						<OzInput v-model="form.bom_name" />
					</OzField>
					<OzAlert v-if="!materials.length" tone="warning" title="Aktif hammadde yok">
						Önce Atölye › Hammaddeler'den birim maliyetli hammadde kartı açın.
					</OzAlert>
					<p v-if="err('lines')" class="row-error">{{ err('lines') }}</p>
					<div v-for="(line, i) in form.lines" :key="i" class="row row-line">
						<OzField label="Hammadde" :error="err(`lines.${i}.material_id`)">
							<OzCombobox v-model="line.material_id" :options="materialOptions" placeholder="Hammadde seçin" />
						</OzField>
						<OzField label="Birim / adet" :error="err(`lines.${i}.quantity_per_unit`)">
							<OzInput v-model.number="line.quantity_per_unit" type="number" min="0" step="0.0001" :suffix="materialById[line.material_id]?.unit ?? ''" />
						</OzField>
						<OzField label="Fire" :error="err(`lines.${i}.waste_pct`)">
							<OzInput v-model.number="line.waste_pct" type="number" min="0" step="0.01" suffix="%" />
						</OzField>
						<div class="line-cost">
							<span class="dim">Tutar</span>
							<b class="mono">{{ formatMoney(lineCost(line)) }}</b>
						</div>
						<OzButton class="row-remove" size="sm" icon="trash" :disabled="form.lines.length === 1" :aria-label="`${i + 1}. satırı kaldır`" @click="removeLine(i)" />
					</div>
					<OzButton size="sm" icon="plus" @click="addLine">Satır Ekle</OzButton>
				</div>
			</OzCard>

			<OzCard title="4 · Üretim Rotası" :meta="form.route.length ? `${form.route.length} aşama` : 'isteğe bağlı'">
				<div class="body rows">
					<template v-if="form.route.length">
						<RouteStepsEditor v-model="form.route" :operations="operations" :fason-suppliers="fasonSuppliers" :errors="form.errors" error-key="route" :required="false" />
						<OzButton size="sm" icon="x" class="route-clear" @click="form.route = []">Rotayı kaldır</OzButton>
					</template>
					<template v-else>
						<p class="dim hint">Aşama sırası (Kesim → Dikim → …) ve varsayılan iç/fason kararları. Tanımlarsanız iş emri bu rotayla otomatik dolar; tanımlamazsanız ilk iş emrinde de kaydedebilirsiniz.</p>
						<OzButton size="sm" icon="workflow" class="route-clear" :disabled="!operations.length" @click="startRoute">Rota tanımla</OzButton>
					</template>
				</div>
			</OzCard>
		</div>

		<aside class="side">
			<OzCard title="5 · Maliyet ve Fiyat">
				<div class="body price">
					<div class="tiles">
						<OzStatTile label="Hammadde" :value="formatMoney(materialCost)" icon="package" tone="info" />
						<OzStatTile label="Birim Maliyet" :value="formatMoney(unitCost)" icon="receipt" tone="accent" />
					</div>
					<OzField label="İşçilik / fason (tahmini, adet başı)" :error="err('extra_cost_per_unit')" hint="Gerçek tutar iş emri adımlarından hesaplanır.">
						<OzInput v-model.number="form.extra_cost_per_unit" type="number" min="0" step="0.01" suffix="₺" />
					</OzField>
					<OzField label="Kâr oranı" :error="err('margin_pct')">
						<OzInput v-model.number="form.margin_pct" type="number" min="0" step="1" suffix="%" />
					</OzField>
					<div class="suggest">
						<span class="dim">Önerilen satış fiyatı</span>
						<b class="mono">{{ formatMoney(suggestedPrice) }}</b>
					</div>
					<OzField label="Satış fiyatı (tüm varyantlar)" required :error="err('price')">
						<OzInput v-model.number="form.price" type="number" min="0" step="0.01" suffix="₺" @update:model-value="onPriceInput" />
					</OzField>
					<div class="price-foot">
						<span v-if="actualMargin !== null" class="dim">Gerçekleşen kâr: <b>%{{ formatNumber(actualMargin, 1) }}</b></span>
						<OzButton v-if="priceTouched" size="sm" icon="refresh" @click="resetPrice">Önerilene dön</OzButton>
					</div>
				</div>
			</OzCard>
		</aside>
		<button type="submit" hidden />
	</form>
</template>

<style scoped>
.intro { margin-bottom: 14px; }
.layout { display: grid; grid-template-columns: minmax(0, 1fr) 340px; gap: 14px; align-items: start; }
.main { display: flex; flex-direction: column; gap: 14px; min-width: 0; }
.side { position: sticky; top: 14px; }
@media (max-width: 1040px) {
	.layout { grid-template-columns: 1fr; }
	.side { position: static; }
}

.body { padding: 0 18px 18px; }
.grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.span-2 { grid-column: 1 / -1; }
.rows { display: flex; flex-direction: column; gap: 10px; }
.bom-name { max-width: 320px; }
.row {
	display: grid; gap: 10px; align-items: end;
	padding: 10px 12px; border: 1px solid var(--oz-border-soft); border-radius: var(--oz-radius-sm); background: var(--oz-surface-2);
}
.row-variant { grid-template-columns: minmax(0, 1fr) minmax(0, 1fr) minmax(0, 1.4fr) auto; }
.row-line { grid-template-columns: minmax(0, 2fr) minmax(0, 1fr) minmax(0, .8fr) minmax(0, .9fr) auto; }
.row-remove { margin-bottom: 2px; }
.row-error { margin: 0; font-size: 12px; color: var(--oz-danger); }
.line-cost { display: flex; flex-direction: column; gap: 4px; padding-bottom: 8px; font-size: 12px; }

.route-clear { align-self: flex-start; }
.hint { margin: 0; font-size: 12.5px; }
.price { display: flex; flex-direction: column; gap: 12px; }
.tiles { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
.suggest {
	display: flex; align-items: baseline; justify-content: space-between; gap: 8px;
	padding: 10px 12px; border: 1px dashed var(--oz-border); border-radius: var(--oz-radius-sm); font-size: 12.5px;
}
.suggest b { font-size: 15px; color: var(--oz-ink); }
.price-foot { display: flex; align-items: center; justify-content: space-between; gap: 8px; font-size: 12px; min-height: 28px; }

.mono { font-family: ui-monospace, monospace; }
.dim { color: var(--oz-muted); }

@media (max-width: 640px) {
	.grid-2, .tiles { grid-template-columns: 1fr; }
	.row-variant, .row-line { grid-template-columns: 1fr 1fr; }
	.row-line > :first-child { grid-column: 1 / -1; }
}
</style>
