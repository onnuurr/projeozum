<script setup>
import { ref, reactive, computed, inject, watch, onUnmounted } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import { useDebounceFn } from '@vueuse/core'
import AppLayout from '@/Layouts/AppLayout.vue'
import {
	OzPageHeader, OzModuleTabs, OzStatStrip, OzCard, OzButton, OzPill, OzDropdown, OzEmptyState, OzDrawer, OzModal,
	OzField, OzInput, OzSelect, OzTextarea, OzCheckbox, OzFileDrop, OzAlert, useOzConfirm,
} from '@/Components/Oz'

defineOptions({ layout: AppLayout })

const props = defineProps({
	patterns: { type: Array, default: () => [] },
	productTypes: { type: Array, default: () => [] },
	allTags: { type: Array, default: () => [] },
	filters: { type: Object, default: () => ({}) },
	canManage: { type: Boolean, default: false },
})

const showToast = inject('showToast', null)
const confirm = useOzConfirm()
const errorToast = (title) => (errs) => showToast?.({ type: 'error', title, message: Object.values(errs)[0] || 'Sunucu hatası.' })

const STATUS = { draft: 'Taslak', approved: 'Onaylı', rejected: 'Reddedildi' }
const STATUS_TONE = { draft: 'neutral', approved: 'success', rejected: 'danger' }
const statusOptions = Object.entries(STATUS).map(([value, label]) => ({ value, label }))

/* ── özet (sunucu filtresi uygulanmış liste üzerinden) ── */
const needsWork = (p) => ['processing', 'failed', 'needs_tracing'].includes(p.extractionStatus)
const stats = computed(() => [
	{ key: 'total', label: hasFilters.value ? 'Filtrelenen Kalıp' : 'Toplam Kalıp', value: props.patterns.length, icon: 'layers', tone: 'accent' },
	{ key: 'approved', label: 'Onaylı', value: props.patterns.filter((p) => p.status === 'approved').length, icon: 'success', tone: 'success' },
	{ key: 'draft', label: 'Taslak', value: props.patterns.filter((p) => p.status === 'draft').length, icon: 'edit', tone: 'info' },
	{ key: 'work', label: 'Çıkarım Bekleyen', value: props.patterns.filter(needsWork).length, icon: 'alert', tone: props.patterns.some(needsWork) ? 'warning' : 'success' },
])

/* ── filtreler (sunucu tarafı) ── */
const filters = reactive({
	search: props.filters.search || '',
	product_type: props.filters.product_type || '',
	status: props.filters.status || '',
	tag: props.filters.tag || '',
})
const hasFilters = computed(() => !!(filters.search || filters.product_type || filters.status || filters.tag))
const typeFilterOptions = computed(() => [{ value: '', label: 'Tüm ürün tipleri' }, ...props.productTypes.map((t) => ({ value: t, label: t }))])
const statusFilterOptions = [{ value: '', label: 'Tüm durumlar' }, ...statusOptions]
const tagFilterOptions = computed(() => [{ value: '', label: 'Tüm etiketler' }, ...props.allTags.map((t) => ({ value: t, label: `#${t}` }))])

function apply() {
	const params = Object.fromEntries(Object.entries(filters).filter(([, v]) => v))
	router.get('/atelier/patterns', params, { preserveState: true, preserveScroll: true, replace: true })
}
const debouncedApply = useDebounceFn(apply, 350)
function clearFilters() {
	Object.assign(filters, { search: '', product_type: '', status: '', tag: '' })
	apply()
}

// Dönüştürücü PDF'te gerçek bir kesim şablonu bulamadığında (dikiş talimatı kitapçığı,
// kapak/logo sayfası vb. — bkz. pdf_dxf_service/converter.py::_has_plausible_pattern_page)
// extraction_error hep bu sabit ibareyle gelir; gerçek teknik hatayla karışmasın diye ayrı etiket.
const failedLabel = (p) => (p.extractionError?.includes('dikiş talimatı') ? 'kalıp değil' : 'çıkarım başarısız')

/* ── kart aksiyonları ── */
const cardActions = (p) => [
	...(p.extractionStatus === 'failed' ? [{ key: 'retry', label: 'Çıkarımı Yeniden Dene', icon: 'refresh' }] : []),
	...(p.extractionStatus === 'needs_tracing' ? [{ key: 'trace', label: 'Elle Sayısallaştır', icon: 'edit' }] : []),
	{ key: 'edit', label: 'Düzenle', icon: 'edit' },
	{ divider: true },
	{ key: 'remove', label: 'Sil', icon: 'trash', danger: true },
]
function onCardAction(key, p) {
	if (key === 'retry') router.post(`/atelier/patterns/${p.id}/retry-extraction`, {}, { preserveScroll: true, onError: errorToast('Yeniden denenemedi') })
	else if (key === 'trace') router.get(`/atelier/patterns/${p.id}/tracer`)
	else if (key === 'edit') openEdit(p)
	else if (key === 'remove') remove(p)
}
async function remove(p) {
	const ok = await confirm.danger('Kalıbı Sil', `${p.name} ve dosyaları kalıcı olarak kaldırılacak.`)
	if (ok) router.delete(`/atelier/patterns/${p.id}`, { preserveScroll: true, onError: errorToast('Silinemedi') })
}

const zoom = ref(null)
const zoomOpen = computed({ get: () => !!zoom.value, set: (v) => { if (!v) zoom.value = null } })

/* ── ekle / düzenle ── */
const emptyForm = () => ({
	name: '', code: '', product_type: '', size_range: '', vendor: '', collection: '',
	status: 'draft', scale_verified: false, scale_deviation_mm: null, notes: '',
	parts: [], tags: [],
	preview_image: null, dxf: null, pdf: null,
})
const form = useForm(emptyForm())
const formOpen = ref(false)
const editingId = ref(null)
const tagDraft = ref('')

// OzFileDrop dizi ister; formda tekli dosya alanları
const fileModel = (field) => computed({
	get: () => (form[field] ? [form[field]] : []),
	set: (list) => { form[field] = list[0] ?? null },
})
const previewFiles = fileModel('preview_image')
const dxfFiles = fileModel('dxf')
const pdfFiles = fileModel('pdf')

function resetForm() {
	form.defaults(emptyForm())
	form.reset()
	form.clearErrors()
	tagDraft.value = ''
}
watch(formOpen, (open) => { if (!open) setTimeout(() => { resetForm(); editingId.value = null }, 250) })

function openCreate() {
	resetForm()
	editingId.value = null
	formOpen.value = true
}
const editing = computed(() => props.patterns.find((p) => p.id === editingId.value) ?? null)
function openEdit(p) {
	resetForm()
	editingId.value = p.id
	Object.assign(form, {
		name: p.name, code: p.code || '', product_type: p.productType, size_range: p.sizeRange || '',
		vendor: p.vendor || '', collection: p.collection || '', status: p.status,
		scale_verified: !!p.scaleVerified, scale_deviation_mm: p.scaleDeviation, notes: p.notes || '',
		// Kalıp Kartı alanları (no/varyant/kumaş/not) çekmecede düzenlenmez ama kayıtta korunur.
		parts: p.parts.map((pt) => ({
			part_name: pt.partName, quantity: pt.quantity, size_range: pt.sizeRange || '',
			part_no: pt.partNo || '', variants: pt.variants || '', fabric_code: pt.fabricCode || '', notes: pt.notes || '',
		})),
		tags: [...p.tags],
	})
	formOpen.value = true
}

function addPart() { form.parts.push({ part_name: '', quantity: 1, size_range: '' }) }
function addTag() {
	const t = tagDraft.value.trim().replace(/^#/, '').replace(/,$/, '')
	if (t && !form.tags.includes(t)) form.tags.push(t)
	tagDraft.value = ''
}

function submit() {
	if (form.processing) return
	if (tagDraft.value.trim()) addTag()
	const opts = {
		forceFormData: true,
		preserveScroll: true,
		onSuccess: () => { formOpen.value = false },
		onError: errorToast('Kayıt başarısız'),
	}
	if (editingId.value) form.transform((d) => ({ ...d, _method: 'put' })).post(`/atelier/patterns/${editingId.value}`, opts)
	else form.transform((d) => d).post('/atelier/patterns', opts)
}

/* ── içe aktarma ── */
const importMode = ref('')
const importOpen = computed({ get: () => !!importMode.value, set: (v) => { if (!v) importMode.value = '' } })
const importItems = [
	{ key: 'url', label: 'Linkten', icon: 'link', hint: 'Ürün sayfası adresi' },
	{ key: 'pdf', label: "PDF'ten", icon: 'file', hint: 'Vektör kalıp PDF' },
	{ key: 'photo', label: 'Fotoğraftan', icon: 'upload', hint: 'ArUco marker ile' },
]
const IMPORT_META = {
	url: { title: 'Linkten Kalıp Ekle', icon: 'link' },
	pdf: { title: "PDF'ten Kalıp Ekle", icon: 'file' },
	photo: { title: 'Fotoğraftan Kalıp Ekle', icon: 'upload' },
}
const importUrlForm = useForm({ url: '' })
const importPdfForm = useForm({ files: [] })
const importPhotoForm = useForm({ files: [], marker_mm: 40 })
const activeImportForm = computed(() => ({ url: importUrlForm, pdf: importPdfForm, photo: importPhotoForm })[importMode.value])
const filesError = (f) => f.errors.files || Object.entries(f.errors).find(([k]) => k.startsWith('files.'))?.[1] || ''

function openImport(mode) {
	for (const f of [importUrlForm, importPdfForm, importPhotoForm]) { f.reset(); f.clearErrors() }
	importMode.value = mode
}
function onReject(list) {
	showToast?.({ type: 'error', title: 'Bazı dosyalar eklenmedi', message: list.map((r) => `${r.file.name}: ${r.reason}`).join(' · ') })
}
const canSubmitImport = computed(() => {
	if (importMode.value === 'url') return !!importUrlForm.url
	if (importMode.value === 'pdf') return importPdfForm.files.length > 0
	if (importMode.value === 'photo') return importPhotoForm.files.length > 0
	return false
})
function submitImport() {
	const f = activeImportForm.value
	if (!f || f.processing || !canSubmitImport.value) return
	const url = { url: '/atelier/patterns/import-url', pdf: '/atelier/patterns/import', photo: '/atelier/patterns/import-photo' }[importMode.value]
	f.post(url, {
		forceFormData: importMode.value !== 'url',
		preserveScroll: true,
		onSuccess: () => { importMode.value = '' },
		onError: errorToast('İçe aktarılamadı'),
	})
}
const importSubmitLabel = computed(() => {
	if (importMode.value === 'pdf') return `${importPdfForm.files.length} PDF gönder`
	if (importMode.value === 'photo') return `${importPhotoForm.files.length} fotoğraf gönder`
	return 'İçe Aktar'
})

/* Çıkarım devam ederken listeyi hafifçe taze tut.
   İşçi/servis çalışmıyorsa taslak kalıcı 'processing' kalır → sonsuz dönmesin
   diye en çok MAX_POLLS deneme (≈80sn) sonra durur (yeni içe aktarım sıfırlar). */
let pollTimer = null
let pollsLeft = 0
const MAX_POLLS = 20
function syncPolling() {
	const hasProcessing = props.patterns.some((p) => p.extractionStatus === 'processing')
	if (hasProcessing) {
		if (!pollTimer) {
			pollsLeft = MAX_POLLS
			pollTimer = setInterval(() => {
				if (pollsLeft-- <= 0) { clearInterval(pollTimer); pollTimer = null; return }
				router.reload({ only: ['patterns'], preserveScroll: true })
			}, 4000)
		}
	} else if (pollTimer) {
		clearInterval(pollTimer); pollTimer = null
	}
}
watch(() => props.patterns, syncPolling, { immediate: true, deep: false })
onUnmounted(() => { if (pollTimer) clearInterval(pollTimer) })
</script>

<template>
	<Head title="Kalıp Kütüphanesi" />

	<OzPageHeader title="Kalıp Kütüphanesi">
		<template #subtitle><b>{{ patterns.length }}</b> kalıp · aranabilir DXF arşivi</template>
		<template v-if="canManage" #actions>
			<OzDropdown :items="importItems" label="İçe aktar" placement="bottom-end" @select="openImport">
				<template #trigger="{ toggle, attrs, open }">
					<OzButton icon="upload" v-bind="attrs" :class="{ 'is-open': open }" @click.stop="toggle">İçe Aktar</OzButton>
				</template>
			</OzDropdown>
			<OzButton variant="primary" icon="plus" @click="openCreate">Yeni Kalıp</OzButton>
		</template>
	</OzPageHeader>
	<OzModuleTabs />

	<OzStatStrip :items="stats" />

	<OzCard title="Kalıplar" :meta="hasFilters ? 'filtrelenmiş' : `${patterns.length} kayıt`">
		<div class="toolbar">
			<OzInput v-model="filters.search" icon="search" placeholder="Ad, kod veya satıcı ara…" class="tb-search" @update:model-value="debouncedApply" />
			<OzSelect v-model="filters.product_type" :options="typeFilterOptions" class="tb-select" @update:model-value="apply" />
			<OzSelect v-model="filters.status" :options="statusFilterOptions" class="tb-select" @update:model-value="apply" />
			<OzSelect v-if="allTags.length" v-model="filters.tag" :options="tagFilterOptions" class="tb-select" @update:model-value="apply" />
			<OzButton v-if="hasFilters" size="sm" icon="x" @click="clearFilters">Temizle</OzButton>
		</div>

		<OzEmptyState
			v-if="!patterns.length"
			:icon="hasFilters ? 'search' : 'layers'"
			:title="hasFilters ? 'Filtreye uyan kalıp yok' : 'Henüz kalıp yok'"
			:description="!hasFilters && canManage ? '“Yeni Kalıp” ya da “İçe Aktar” ile arşive ilk kaydı ekleyin.' : ''"
		>
			<template v-if="hasFilters" #actions><OzButton size="sm" icon="x" @click="clearFilters">Filtreleri Temizle</OzButton></template>
		</OzEmptyState>

		<div v-else class="grid">
			<article v-for="p in patterns" :key="p.id" class="pattern">
				<button type="button" class="preview" :class="{ empty: !p.previewUrl }" :disabled="!p.previewUrl" :aria-label="`${p.name} önizlemesini büyüt`" @click="zoom = p.previewUrl">
					<img v-if="p.previewUrl" :src="p.previewUrl" :alt="p.name" loading="lazy">
					<span v-else class="noimg">önizleme yok</span>
					<OzPill class="badge-status" :tone="STATUS_TONE[p.status] ?? 'neutral'">{{ STATUS[p.status] ?? p.status }}</OzPill>
					<span v-if="p.extractionStatus === 'processing'" class="badge-x is-processing"><span class="spin" /> inceleniyor</span>
					<span v-else-if="p.extractionStatus === 'failed'" class="badge-x is-failed" :title="p.extractionError">{{ failedLabel(p) }}</span>
					<span v-else-if="p.extractionStatus === 'needs_tracing'" class="badge-x is-tracing" title="Taranmış/raster — elle sayısallaştırılmalı">sayısallaştırma bekliyor</span>
				</button>
				<div class="body">
					<div class="title-row">
						<h3 class="name"><Link :href="`/atelier/patterns/${p.id}`" class="name-link">{{ p.name }}</Link></h3>
						<span v-if="p.code" class="code">{{ p.code }}</span>
					</div>
					<div class="tags">
						<span class="type">{{ p.productType }}</span>
						<span v-if="p.sizeRange" class="chip mono">{{ p.sizeRange }}</span>
						<span v-if="p.scaleVerified" class="scale ok" title="Ölçek doğrulandı">ölçek ✓</span>
						<span v-else class="scale warn" title="Ölçek doğrulanmadı">ölçek?</span>
					</div>
					<div v-if="p.parts.length" class="chips">
						<span v-for="(pt, i) in p.parts.slice(0, 4)" :key="i" class="chip">{{ pt.partName }}<template v-if="pt.quantity > 1"> ×{{ pt.quantity }}</template></span>
						<span v-if="p.parts.length > 4" class="more">+{{ p.parts.length - 4 }}</span>
					</div>
					<div v-if="p.tags.length" class="chips">
						<span v-for="t in p.tags" :key="t" class="hashtag">#{{ t }}</span>
					</div>
					<footer class="foot">
						<div class="files">
							<Link :href="`/atelier/patterns/${p.id}`" class="file-link is-card" title="Ölçü tablosu, parçalar, kumaş sarfiyatı">Kart</Link>
							<a v-if="p.dxfUrl" :href="p.dxfUrl" class="file-link is-dxf" download>DXF</a>
							<a v-if="p.pdfUrl" :href="p.pdfUrl" class="file-link is-pdf" target="_blank" rel="noopener">PDF</a>
						</div>
						<OzDropdown v-if="canManage" :items="cardActions(p)" :label="`${p.name} işlemleri`" @select="(k) => onCardAction(k, p)" />
					</footer>
				</div>
			</article>
		</div>
	</OzCard>

	<!-- Ekle / düzenle -->
	<OzDrawer v-model:open="formOpen" :title="editingId ? 'Kalıbı Düzenle' : 'Yeni Kalıp'" subtitle="Geometri dosyada saklanır; veritabanı yalnızca metadatayı tutar" :icon="editingId ? 'edit' : 'plus'" size="lg" :persistent="form.processing">
		<form class="form" @submit.prevent="submit">
			<OzField label="Ad" required :error="form.errors.name">
				<OzInput v-model="form.name" placeholder="örn. Çocuk Kapüşonlu Tulum" />
			</OzField>
			<div class="row-3">
				<OzField label="Kod" :error="form.errors.code">
					<OzInput v-model="form.code" class="mono" placeholder="KLP-001" />
				</OzField>
				<OzField label="Ürün tipi" required :error="form.errors.product_type">
					<OzInput v-model="form.product_type" list="pattern-types" placeholder="tulum, ceket…" />
					<datalist id="pattern-types"><option v-for="t in productTypes" :key="t" :value="t" /></datalist>
				</OzField>
				<OzField label="Beden aralığı" :error="form.errors.size_range">
					<OzInput v-model="form.size_range" placeholder="116-122-128-134" />
				</OzField>
			</div>
			<div class="row-3">
				<OzField label="Durum" :error="form.errors.status">
					<OzSelect v-model="form.status" :options="statusOptions" />
				</OzField>
				<OzField label="Satıcı" :error="form.errors.vendor">
					<OzInput v-model="form.vendor" />
				</OzField>
				<OzField label="Koleksiyon" :error="form.errors.collection">
					<OzInput v-model="form.collection" />
				</OzField>
			</div>
			<div class="scale-row">
				<OzCheckbox v-model="form.scale_verified" label="Ölçek doğrulandı" description="Test karesi / referans ölçüyle kontrol edildi" />
				<OzField label="Sapma" :error="form.errors.scale_deviation_mm" class="dev">
					<OzInput v-model.number="form.scale_deviation_mm" type="number" step="0.01" suffix="mm" placeholder="0,00" />
				</OzField>
			</div>

			<section class="section">
				<div class="section-head">
					<span class="section-title">Parçalar</span>
					<OzButton size="sm" icon="plus" @click="addPart">Parça Ekle</OzButton>
				</div>
				<p v-if="!form.parts.length" class="dim small">Henüz parça eklenmedi (kol, ön, arka, kapüşon…).</p>
				<div v-for="(pt, i) in form.parts" :key="i" class="part">
					<OzInput v-model="pt.part_name" placeholder="Parça adı (örn. Kol)" :aria-label="`${i + 1}. parça adı`" />
					<OzInput v-model.number="pt.quantity" type="number" min="1" suffix="adet" :aria-label="`${i + 1}. parça adedi`" />
					<OzInput v-model="pt.size_range" placeholder="beden (ops.)" :aria-label="`${i + 1}. parça bedeni`" />
					<OzButton size="sm" icon="trash" :aria-label="`${i + 1}. parçayı kaldır`" @click="form.parts.splice(i, 1)" />
				</div>
			</section>

			<section class="section">
				<span class="section-title">Etiketler</span>
				<div v-if="form.tags.length" class="chips">
					<button v-for="(t, i) in form.tags" :key="t" type="button" class="hashtag removable" :aria-label="`${t} etiketini kaldır`" @click="form.tags.splice(i, 1)">#{{ t }} ×</button>
				</div>
				<OzInput v-model="tagDraft" icon="tag" placeholder="Etiket yazıp Enter'a basın…" @keydown.enter.prevent="addTag" @keydown.,.prevent="addTag" />
			</section>

			<section class="section">
				<span class="section-title">Dosyalar</span>
				<p v-if="editing && (editing.dxfUrl || editing.pdfUrl || editing.previewUrl)" class="dim small">Yeni dosya seçilmezse mevcut dosyalar korunur.</p>
				<div class="files-grid">
					<OzField label="Önizleme görseli" :error="form.errors.preview_image">
						<OzFileDrop v-model="previewFiles" accept="image/*" :max-size="5 * 1024 * 1024" title="Görsel bırakın" hint="JPG/PNG · 5 MB" @reject="onReject" />
					</OzField>
					<OzField label="DXF" :error="form.errors.dxf">
						<OzFileDrop v-model="dxfFiles" accept=".dxf" :max-size="20 * 1024 * 1024" title="DXF bırakın" hint="DXF · 20 MB" @reject="onReject" />
					</OzField>
					<OzField label="Kaynak PDF" :error="form.errors.pdf">
						<OzFileDrop v-model="pdfFiles" accept="application/pdf,.pdf" :max-size="50 * 1024 * 1024" title="PDF bırakın" hint="PDF · 50 MB" @reject="onReject" />
					</OzField>
				</div>
			</section>

			<OzField label="Notlar" :error="form.errors.notes">
				<OzTextarea v-model="form.notes" :rows="2" placeholder="İç notlar (opsiyonel)" />
			</OzField>
			<button type="submit" hidden />
		</form>
		<template #footer>
			<OzButton :disabled="form.processing" @click="formOpen = false">Vazgeç</OzButton>
			<OzButton variant="primary" :icon="editingId ? 'check' : 'plus'" :loading="form.processing" @click="submit">{{ editingId ? 'Kaydet' : 'Ekle' }}</OzButton>
		</template>
	</OzDrawer>

	<!-- İçe aktarma -->
	<OzDrawer v-model:open="importOpen" :title="IMPORT_META[importMode]?.title ?? ''" :icon="IMPORT_META[importMode]?.icon ?? 'upload'" size="md" :persistent="!!activeImportForm?.processing">
		<form class="form" @submit.prevent="submitImport">
			<template v-if="importMode === 'url'">
				<p class="dim">Ürün sayfasının adresini yapıştırın; sayfadaki PDF ve önizleme görseli otomatik bulunup indirilir, taslak kalıp olarak eklenir ve arka planda incelenir.</p>
				<OzField label="Sayfa adresi" required :error="importUrlForm.errors.url">
					<OzInput v-model="importUrlForm.url" type="url" icon="link" placeholder="https://…" />
				</OzField>
			</template>
			<template v-else-if="importMode === 'pdf'">
				<OzAlert tone="info" title="Vektör kalıp PDF'i gerekir">
					Her PDF taslak kalıp olarak eklenir; sistem arka planda ürün tipi, beden, parça ve DXF'i çıkarır. Taranmış/raster dosyalar elle sayısallaştırmaya düşer.
				</OzAlert>
				<OzField label="PDF dosyaları" required :error="filesError(importPdfForm)">
					<OzFileDrop v-model="importPdfForm.files" accept="application/pdf,.pdf" multiple title="PDF kalıpları bırakın" hint="Çoklu seçim · yalnızca PDF" @reject="onReject" />
				</OzField>
			</template>
			<template v-else-if="importMode === 'photo'">
				<OzAlert tone="info" title="Ölçü referansı: ArUco marker">
					Kalıp kağıdını düz bir yüzeye serip yanına <b>DICT_4X4_50</b> marker koyarak fotoğraflayın; marker aynı düzlemde ve net görünmeli.
					Sistem ölçek + perspektifi düzeltip DXF çıkarır; marker bulunamazsa taslak "başarısız" düşer.
				</OzAlert>
				<OzField label="Marker kenar uzunluğu" required :error="importPhotoForm.errors.marker_mm" hint="Basılı marker'ın gerçek kenarı">
					<OzInput v-model.number="importPhotoForm.marker_mm" type="number" min="5" max="500" step="1" suffix="mm" />
				</OzField>
				<OzField label="Fotoğraflar" required :error="filesError(importPhotoForm)">
					<OzFileDrop v-model="importPhotoForm.files" accept="image/jpeg,image/png,image/webp" multiple title="Fotoğrafları bırakın" hint="JPG, PNG, WEBP" @reject="onReject" />
				</OzField>
			</template>
			<button type="submit" hidden />
		</form>
		<template #footer>
			<OzButton :disabled="activeImportForm?.processing" @click="importMode = ''">Vazgeç</OzButton>
			<OzButton variant="primary" icon="upload" :disabled="!canSubmitImport" :loading="activeImportForm?.processing" @click="submitImport">{{ importSubmitLabel }}</OzButton>
		</template>
	</OzDrawer>

	<OzModal v-model:open="zoomOpen" title="Kalıp önizleme" icon="layers" size="lg">
		<img v-if="zoom" :src="zoom" alt="Kalıp önizleme" class="zoom-img">
	</OzModal>
</template>

<style scoped>

.dim { color: var(--oz-muted); font-size: 12.5px; line-height: 1.5; margin: 0; }
.small { font-size: 12px; }
.mono { font-family: ui-monospace, monospace; }
:deep(.is-open) { background: var(--oz-accent-soft); color: var(--oz-accent); }

.toolbar { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; padding: 0 18px 14px; }
.tb-search { flex: 1 1 240px; max-width: 360px; }
.tb-select { width: 180px; }

.grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(min(250px, 100%), 1fr)); gap: 14px; padding: 0 18px 18px; }
.pattern { display: flex; flex-direction: column; border: 1px solid var(--oz-border); border-radius: var(--oz-radius-sm); overflow: hidden; background: var(--oz-surface); transition: box-shadow .15s, transform .15s; }
.pattern:hover { box-shadow: var(--oz-shadow); transform: translateY(-2px); }
.preview { position: relative; display: flex; align-items: center; justify-content: center; aspect-ratio: 4 / 3; padding: 0; border: none; background: var(--oz-surface-2); cursor: zoom-in; }
.preview.empty { cursor: default; }
.preview img { width: 100%; height: 100%; object-fit: cover; }
.preview:focus-visible { outline: 2px solid var(--oz-accent); outline-offset: -2px; }
.noimg { font-size: 11.5px; font-style: italic; color: var(--oz-muted); }
.badge-status { position: absolute; top: 8px; left: 8px; }
.badge-x { position: absolute; bottom: 8px; left: 8px; display: inline-flex; align-items: center; gap: 5px; padding: 3px 9px; border-radius: 6px; font-size: 10.5px; font-weight: 700; color: #fff; }
.badge-x.is-processing { background: rgba(var(--oz-info-rgb), .92); }
.badge-x.is-failed { background: rgba(var(--oz-danger-rgb), .92); cursor: help; }
.badge-x.is-tracing { background: rgba(var(--oz-warning-rgb), .95); cursor: help; }
.spin { width: 9px; height: 9px; border: 2px solid rgba(255, 255, 255, .45); border-top-color: #fff; border-radius: 50%; animation: pe-spin .7s linear infinite; }
@keyframes pe-spin { to { transform: rotate(360deg); } }

.body { display: flex; flex-direction: column; gap: 8px; padding: 11px 12px; flex: 1; }
.title-row { display: flex; align-items: flex-start; justify-content: space-between; gap: 8px; }
.name { margin: 0; font-size: 13.5px; font-weight: 700; line-height: 1.3; color: var(--oz-ink-soft); }
.code { flex-shrink: 0; font-family: ui-monospace, monospace; font-size: 10.5px; color: var(--oz-muted); background: var(--oz-surface-2); border: 1px solid var(--oz-border-soft); padding: 1px 6px; border-radius: 5px; }
.tags, .chips { display: flex; align-items: center; flex-wrap: wrap; gap: 5px; }
.type { font-size: 11px; font-weight: 600; color: var(--oz-info); background: var(--oz-info-soft); padding: 1px 8px; border-radius: 6px; }
.chip { font-size: 10.5px; color: var(--oz-muted); background: var(--oz-surface-2); border: 1px solid var(--oz-border-soft); padding: 1px 7px; border-radius: 5px; }
.more { font-size: 10.5px; color: var(--oz-muted); }
.scale { font-size: 10.5px; font-weight: 700; }
.scale.ok { color: var(--oz-success); }
.scale.warn { color: var(--oz-warning); }
.hashtag { font: inherit; font-size: 10.5px; font-weight: 600; color: var(--oz-accent); background: var(--oz-accent-soft); border: none; padding: 2px 7px; border-radius: 5px; }
.hashtag.removable { cursor: pointer; }
.hashtag.removable:hover { color: var(--oz-danger); background: var(--oz-danger-soft); }
.foot { display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-top: auto; padding-top: 9px; border-top: 1px solid var(--oz-border-soft); }
.files { display: flex; gap: 6px; }
.file-link { font-size: 11px; font-weight: 700; padding: 3px 9px; border-radius: 6px; text-decoration: none; }
.file-link.is-dxf { background: var(--oz-accent-soft); color: var(--oz-accent); }
.file-link.is-pdf { background: var(--oz-danger-soft); color: var(--oz-danger); }
.file-link.is-card { background: var(--oz-info-soft); color: var(--oz-info); }
.name-link { color: inherit; text-decoration: none; }
.name-link:hover { color: var(--oz-accent); }

.form { display: flex; flex-direction: column; gap: 14px; }
.row-3 { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; }
.scale-row { display: flex; align-items: flex-end; gap: 18px; flex-wrap: wrap; }
.dev { width: 160px; }
.section { display: flex; flex-direction: column; gap: 10px; padding-top: 14px; border-top: 1px solid var(--oz-border-soft); }
.section-head { display: flex; align-items: center; justify-content: space-between; }
.section-title { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: var(--oz-muted); }
.part { display: grid; grid-template-columns: minmax(0, 2fr) 110px minmax(0, 1fr) auto; gap: 8px; align-items: center; }
.files-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px; }
.zoom-img { display: block; max-width: 100%; max-height: 70vh; margin: 0 auto; border-radius: var(--oz-radius-sm); }

@media (max-width: 680px) {
	.row-3, .files-grid { grid-template-columns: 1fr; }
	.part { grid-template-columns: 1fr 1fr; }
	.part > :first-child { grid-column: 1 / -1; }
	.tb-select { width: auto; flex: 1 1 140px; }
}
</style>
