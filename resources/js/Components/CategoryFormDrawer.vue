<script setup>
import { reactive, computed, ref, watch, nextTick } from 'vue'
import { OzDrawer, OzField, OzInput, OzSelect, OzButton, OzIcon, OzIconPicker } from '@/Components/Oz'
import { categoryIconOptions, categoryIconLabel, DEFAULT_CATEGORY_ICON, categoryIconName, ozIconForCategory } from '@/categoryIcons.js'

const props = defineProps({
	category: { type: Object, default: null },
	parentCategories: { type: Array, default: () => [] },
	busy: { type: Boolean, default: false },
	errors: { type: Object, default: () => ({}) },
	// Kategori zincirinde oran yoksa kullanılan KDV (%), config('product.default_vat_rate')
	defaultVatRate: { type: Number, default: 10 },
})

const open = defineModel('open', { type: Boolean, default: false })
const emit = defineEmits(['submit'])

const isEdit = computed(() => !!props.category)

// Durum otomatik (yayında ürünü yoksa pasif); buradaki seçim kullanıcının niyeti — "Pasif" elle pasiftir,
// ürün gelse de aktifleşmez. Bkz. CategoryStatusService.
// KDV oranı: boş = üst kategoriden miras (yoksa varsayılan). Fiyatlar KDV hariç; mağazaya KDV dahil gider.
const VAT_RATES = [0, 1, 10, 20]
const inheritedVat = computed(() => {
	const byId = new Map(props.parentCategories.map((c) => [c.id, c]))
	const seen = new Set()
	for (let c = byId.get(form.parent_id); c && !seen.has(c.id); c = byId.get(c.parent_id)) {
		seen.add(c.id)
		if (c.vat_rate !== null && c.vat_rate !== undefined) return { rate: c.vat_rate, from: c.name }
	}
	return { rate: props.defaultVatRate, from: null }
})
const vatOptions = computed(() => [
	{ value: '', label: inheritedVat.value.from ? `Üst kategoriden (%${inheritedVat.value.rate} · ${inheritedVat.value.from})` : `Varsayılan (%${inheritedVat.value.rate})` },
	...VAT_RATES.map((r) => ({ value: String(r), label: `%${r}` })),
])

const statusOptions = [
	{ value: 'active', label: 'Otomatik' },
	{ value: 'passive', label: 'Pasif (elle)' },
]

// Seçenekler tam yoluyla ("Kız Çocuk › Dış Giyim"), yola göre sıralı. Düzenlemede kategorinin
// kendisi ve alt kategorileri çıkarılır — kendi altına taşınırsa döngü oluşur (backend de reddeder).
const parentOptions = computed(() => {
	const excludeId = isEdit.value ? props.category?.id : null
	const byId = new Map(props.parentCategories.map((c) => [c.id, c]))
	const options = props.parentCategories.map((c) => {
		const names = [c.name]
		const chain = new Set([c.id])
		for (let p = byId.get(c.parent_id); p && !chain.has(p.id); p = byId.get(p.parent_id)) {
			names.unshift(p.name)
			chain.add(p.id)
		}
		return { value: c.id, label: names.join(' › '), chain }
	})
	return [
		{ value: null, label: '— Yok (Ana kategori) —' },
		...options
			.filter((o) => excludeId === null || !o.chain.has(excludeId))
			.sort((x, y) => x.label.localeCompare(y.label, 'tr'))
			.map(({ value, label }) => ({ value, label })),
	]
})

const slugTouched = ref(false)

const selectedIconLabel = computed(() => categoryIconLabel(form.icon))

const defaultForm = () => ({
	name: '',
	slug: '',
	icon: DEFAULT_CATEGORY_ICON,
	parent_id: null,
	status: 'active',
	sort_order: 0,
	vat_rate: '',
})

const form = reactive(defaultForm())

// NFD + aksan silme: ç→c, ş→s, ğ→g, ü→u, ö→o, İ→i (İ'nin noktası ayrı karakter olarak kalıp
// "i-c-giyim" üretmesin diye). ı ayrışmadığı için elle çevrilir.
function slugify(str) {
	return String(str || '')
		.normalize('NFD').replace(/[\u0300-\u036f]/g, '')
		.replace(/ı/g, 'i')
		.toLowerCase()
		.replace(/[^a-z0-9]+/g, '-')
		.replace(/^-+|-+$/g, '')
}

// Ad yazıldıkça slug güncellenir — ekleme ve düzenlemede. Üst kategori seçiliyse onun slug'ı
// başa eklenir ("kiz-cocuk" + "Etek" → "kiz-cocuk-etek"; üstün slug'ı zaten kendi üstünü içerir).
// Kullanıcı slug'ı elle değiştirirse durur; slug alanını boşaltırsa otomatik doldurma yeniden başlar.
function autoSlug() {
	const parentSlug = props.parentCategories.find((c) => c.id === form.parent_id)?.slug
	const own = slugify(form.name)
	return parentSlug && own ? `${parentSlug}-${own}` : own
}

function onNameInput() {
	if (!slugTouched.value) form.slug = autoSlug()
}

function onSlugInput() {
	slugTouched.value = form.slug.trim() !== ''
	if (!slugTouched.value) form.slug = autoSlug()
}

// Sıra: yeni kategoride (ve başka üst kategoriye taşınınca) kardeşlerin sonu otomatik önerilir.
// Kullanıcı elle değiştirirse öneri durur; alanı boşaltırsa otomatik öneriye döner.
const sortTouched = ref(false)

function nextSortOrder(parentId) {
	const siblings = props.parentCategories.filter((c) => (c.parent_id ?? null) === (parentId ?? null) && c.id !== props.category?.id)
	return siblings.reduce((max, c) => Math.max(max, Number(c.sort_order) || 0), 0) + 1
}

function autoSortOrder() {
	return isEdit.value && (form.parent_id ?? null) === (props.category.parent_id ?? null)
		? props.category.sort_order ?? 0
		: nextSortOrder(form.parent_id)
}

// Form açılırken yapılan atama (loadingForm) üst kategori değişikliği sayılmaz — düzenlemede mevcut
// slug'ı ezmesin; yalnızca kullanıcı üst kategoriyi değiştirince slug yeniden üretilir.
let loadingForm = false
watch(() => form.parent_id, () => {
	if (!open.value || loadingForm) return
	if (!sortTouched.value) form.sort_order = autoSortOrder()
	if (!slugTouched.value && form.name) form.slug = autoSlug()
})

function onSortInput() {
	sortTouched.value = form.sort_order !== '' && form.sort_order !== null
	if (!sortTouched.value) form.sort_order = autoSortOrder()
}

function submit() {
	emit('submit', {
		id: props.category?.id ?? null,
		mode: isEdit.value ? 'edit' : 'create',
		payload: {
			name: form.name.trim(),
			slug: form.slug.trim(),
			icon: form.icon || DEFAULT_CATEGORY_ICON,
			parent_id: form.parent_id || null,
			status: form.status,
			sort_order: form.sort_order === '' || form.sort_order === null ? null : Number(form.sort_order),
			vat_rate: form.vat_rate === '' || form.vat_rate === null ? null : Number(form.vat_rate),
		},
	})
}

watch(open, (isOpen) => {
	if (isOpen) {
		loadingForm = true
		nextTick(() => { loadingForm = false })
		if (props.category) {
			slugTouched.value = false
			Object.assign(form, {
				name: props.category.name || '',
				slug: props.category.slug || '',
				icon: categoryIconName(props.category.icon),
				parent_id: props.category.parent_id ?? null,
				status: props.category.manuallyPassive ? 'passive' : 'active',
				sort_order: props.category.sort_order ?? 0,
				vat_rate: props.category.vat_rate === null || props.category.vat_rate === undefined ? '' : String(Number(props.category.vat_rate)),
			})
			sortTouched.value = false
		} else {
			slugTouched.value = false
			sortTouched.value = false
			Object.assign(form, defaultForm(), { sort_order: nextSortOrder(null) })
		}
	} else {
		setTimeout(() => {
			slugTouched.value = false
			sortTouched.value = false
			Object.assign(form, defaultForm())
		}, 250)
	}
})
</script>

<template>
	<OzDrawer
		v-model:open="open"
		:title="isEdit ? 'Kategoriyi Düzenle' : 'Yeni Kategori'"
		:subtitle="isEdit ? category.name : 'Ürün kataloğuna yeni bir kategori ekleyin'"
		:icon="isEdit ? 'edit' : 'tag'"
		size="md"
		:persistent="busy"
	>
		<form class="form" @submit.prevent="submit">
			<OzField label="Kategori Adı" required :error="errors.name">
				<OzInput v-model="form.name" placeholder="örn. Erkek Tişört" @input="onNameInput" />
			</OzField>
			<OzField label="Slug" required :error="errors.slug">
				<OzInput v-model="form.slug" class="mono" placeholder="erkek-tisort" @input="onSlugInput" />
			</OzField>
			<OzField label="İkon" :error="errors.icon">
				<div class="icon-preview">
					<div class="icon-badge"><OzIcon :name="ozIconForCategory(form.icon)" :size="18" /></div>
					<span>{{ selectedIconLabel }}</span>
				</div>
				<OzIconPicker v-model="form.icon" :options="categoryIconOptions" :resolve="ozIconForCategory" :page-size="96" max-height="200px" />
			</OzField>
			<div class="row-2">
				<OzField label="Üst Kategori" :error="errors.parent_id">
					<OzSelect v-model="form.parent_id" :options="parentOptions" />
				</OzField>
				<OzField label="Durum" :hint="form.status === 'active' ? 'Yayında ürünü varsa aktif, yoksa pasif olur.' : 'Ürün gelse de pasif kalır.'">
					<OzSelect v-model="form.status" :options="statusOptions" />
				</OzField>
			</div>
			<OzField label="KDV Oranı" :error="errors.vat_rate" hint="Ürün fiyatları KDV hariç girilir; mağazaya (Bagisto) bu oranla KDV dahil gider, bayi siparişinde KDV ayrı hesaplanır.">
				<OzSelect v-model="form.vat_rate" :options="vatOptions" />
			</OzField>
			<OzField label="Sıra" :error="errors.sort_order" :hint="sortTouched ? 'Elle girildi — boşaltırsanız otomatik sıraya döner.' : 'Otomatik: kendi grubunun sonuna eklenir. Değiştirebilirsiniz.'">
				<OzInput v-model.number="form.sort_order" type="number" min="0" placeholder="Otomatik" @input="onSortInput" />
			</OzField>
			<button type="submit" hidden />
		</form>
		<template #footer>
			<OzButton :disabled="busy" @click="open = false">İptal</OzButton>
			<OzButton variant="primary" :icon="isEdit ? 'check' : 'plus'" :loading="busy" @click="submit">{{ isEdit ? 'Değişiklikleri Kaydet' : 'Kategori Ekle' }}</OzButton>
		</template>
	</OzDrawer>
</template>

<style scoped>
.mono { font-family: ui-monospace, monospace; }
.form { display: flex; flex-direction: column; gap: 14px; }
.icon-preview { display: flex; align-items: center; gap: 10px; margin-bottom: 8px; font-size: 12.5px; font-weight: 600; color: var(--oz-ink-soft); }
.icon-badge { width: 34px; height: 34px; border-radius: 10px; background: var(--oz-accent-soft); color: var(--oz-accent); display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
@media (max-width: 560px) {
	.row-2 { grid-template-columns: 1fr; }
}
</style>
