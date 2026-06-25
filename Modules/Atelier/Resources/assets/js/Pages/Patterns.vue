<template>
	<Head title="Kalıp Kütüphanesi" />
	<div class="page-patterns">
		<Breadcrumb
			:items="[
				{ label: 'Ana Sayfa', to: '/workflow', icon: 'home' },
				{ label: 'Üretim Atölyesi', to: '/atelier' },
				{ label: 'Kalıp Kütüphanesi' },
			]"
		/>

		<AtelierNav current="patterns" />

		<div class="page-header">
			<div>
				<h1 class="page-title">Kalıp Kütüphanesi</h1>
				<p class="page-subtitle"><strong>{{ patterns.length }}</strong> kalıp · aranabilir DXF arşivi</p>
			</div>
			<div v-if="canManage" class="head-actions">
				<button class="btn btn-ghost" @click="importModal = true">PDF'ten ekle</button>
				<button class="btn btn-primary" @click="openCreate">+ Yeni Kalıp</button>
			</div>
		</div>

		<!-- Filtre çubuğu -->
		<div class="filter-bar card">
			<div class="filter-search">
				<svg class="search-ico" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
				<input v-model="filters.search" type="text" placeholder="Ad, kod veya satıcı ara…" @input="debouncedApply" />
			</div>
			<select v-model="filters.product_type" class="filter-select" @change="apply">
				<option value="">Tüm ürün tipleri</option>
				<option v-for="t in productTypes" :key="t" :value="t">{{ t }}</option>
			</select>
			<select v-model="filters.status" class="filter-select" @change="apply">
				<option value="">Tüm durumlar</option>
				<option value="draft">Taslak</option>
				<option value="approved">Onaylı</option>
				<option value="rejected">Reddedildi</option>
			</select>
			<select v-if="allTags.length" v-model="filters.tag" class="filter-select" @change="apply">
				<option value="">Tüm etiketler</option>
				<option v-for="t in allTags" :key="t" :value="t">{{ t }}</option>
			</select>
			<button v-if="hasFilters" class="btn btn-ghost btn-sm" @click="clearFilters">Temizle</button>
		</div>

		<!-- Galeri -->
		<div v-if="patterns.length === 0" class="lib-empty card">
			<div class="empty-art">◫</div>
			<p>{{ hasFilters ? 'Filtreye uyan kalıp yok.' : 'Henüz kalıp yok.' }}</p>
			<span v-if="!hasFilters && canManage">“Yeni Kalıp” ile arşive ilk kaydı ekle.</span>
		</div>

		<div v-else class="pattern-grid">
			<article v-for="p in patterns" :key="p.id" class="pattern-card">
				<div class="pc-preview" :class="{ empty: !p.previewUrl }" @click="p.previewUrl && (zoom = p.previewUrl)">
					<img v-if="p.previewUrl" :src="p.previewUrl" :alt="p.name" />
					<span v-else class="pc-noimg">önizleme yok</span>
					<span class="status-badge" :class="p.status">{{ statusLabel(p.status) }}</span>
					<span v-if="p.extractionStatus === 'processing'" class="extract-badge processing">
						<span class="ex-spinner"></span> inceleniyor
					</span>
					<span v-else-if="p.extractionStatus === 'failed'" class="extract-badge failed" :title="p.extractionError">
						çıkarım başarısız
					</span>
					<span v-else-if="p.extractionStatus === 'needs_tracing'" class="extract-badge tracing" title="Taranmış/raster — elle sayısallaştırılmalı">
						sayısallaştırma bekliyor
					</span>
				</div>
				<div class="pc-body">
					<div class="pc-title-row">
						<h3 class="pc-name">{{ p.name }}</h3>
						<span v-if="p.code" class="mono-chip">{{ p.code }}</span>
					</div>
					<div class="pc-tags">
						<span class="type-pill">{{ p.productType }}</span>
						<span v-if="p.sizeRange" class="size-chip">{{ p.sizeRange }}</span>
						<span v-if="p.scaleVerified" class="scale-ok" title="Ölçek doğrulandı">✓ ölçek</span>
						<span v-else class="scale-warn" title="Ölçek doğrulanmadı">ölçek?</span>
					</div>

					<div v-if="p.parts.length" class="pc-parts">
						<span v-for="(pt, i) in p.parts.slice(0, 4)" :key="i" class="part-chip">
							{{ pt.partName }}<em v-if="pt.quantity > 1"> ×{{ pt.quantity }}</em>
						</span>
						<span v-if="p.parts.length > 4" class="part-more">+{{ p.parts.length - 4 }}</span>
					</div>

					<div v-if="p.tags.length" class="pc-taglist">
						<span v-for="t in p.tags" :key="t" class="tag-chip">#{{ t }}</span>
					</div>

					<div class="pc-foot">
						<div class="pc-files">
							<a v-if="p.dxfUrl" :href="p.dxfUrl" class="file-link dxf" download>DXF</a>
							<a v-if="p.pdfUrl" :href="p.pdfUrl" class="file-link pdf" target="_blank">PDF</a>
						</div>
						<div v-if="canManage" class="pc-actions">
							<button v-if="p.extractionStatus === 'failed'" class="table-action-btn" @click="retryExtraction(p)" title="Çıkarımı yeniden dene">↻</button>
							<button v-if="p.extractionStatus === 'needs_tracing'" class="table-action-btn" @click="openTracer(p)" title="Sayısallaştır (elle izle)">🖊️</button>
							<button class="table-action-btn" @click="openEdit(p)" title="Düzenle">✏️</button>
							<button class="table-action-btn danger" @click="remove(p)" title="Sil">🗑️</button>
						</div>
					</div>
				</div>
			</article>
		</div>

		<!-- Ekle / Düzenle modalı -->
		<div v-if="modal" class="modal-overlay" @click.self="closeModal">
			<div class="modal-box wide">
				<div class="modal-head">
					<span class="modal-title">{{ editingId ? 'Kalıp Düzenle' : 'Yeni Kalıp' }}</span>
					<button class="modal-close" @click="closeModal">✕</button>
				</div>

				<form @submit.prevent="submit" class="pattern-form">
					<div class="form-2col">
						<div class="form-row span2">
							<label class="form-label">Ad <span class="req">*</span></label>
							<input v-model="form.name" class="form-input" placeholder="örn. Çocuk Kapüşonlu Tulum" />
							<span v-if="form.errors.name" class="form-error">{{ form.errors.name }}</span>
						</div>
						<div class="form-row">
							<label class="form-label">Kod</label>
							<input v-model="form.code" class="form-input" placeholder="KLP-001" />
							<span v-if="form.errors.code" class="form-error">{{ form.errors.code }}</span>
						</div>
						<div class="form-row">
							<label class="form-label">Ürün tipi <span class="req">*</span></label>
							<input v-model="form.product_type" list="pattern-types" class="form-input" placeholder="tulum, ceket…" />
							<datalist id="pattern-types"><option v-for="t in productTypes" :key="t" :value="t" /></datalist>
							<span v-if="form.errors.product_type" class="form-error">{{ form.errors.product_type }}</span>
						</div>
						<div class="form-row">
							<label class="form-label">Beden aralığı</label>
							<input v-model="form.size_range" class="form-input" placeholder="116-122-128-134" />
						</div>
						<div class="form-row">
							<label class="form-label">Durum</label>
							<select v-model="form.status" class="form-input">
								<option value="draft">Taslak</option>
								<option value="approved">Onaylı</option>
								<option value="rejected">Reddedildi</option>
							</select>
						</div>
						<div class="form-row">
							<label class="form-label">Satıcı</label>
							<input v-model="form.vendor" class="form-input" />
						</div>
						<div class="form-row">
							<label class="form-label">Koleksiyon</label>
							<input v-model="form.collection" class="form-input" />
						</div>
						<div class="form-row span2 scale-row">
							<label class="check-label">
								<input type="checkbox" v-model="form.scale_verified" />
								Ölçek doğrulandı
							</label>
							<div class="dev-field">
								<span class="dev-lbl">Sapma (mm)</span>
								<input v-model="form.scale_deviation_mm" type="number" step="0.01" class="form-input dev-input" placeholder="0.00" />
							</div>
						</div>
					</div>

					<!-- Parçalar -->
					<div class="section-block">
						<div class="section-head">
							<span>Parçalar</span>
							<button type="button" class="btn-link" @click="addPart">+ parça ekle</button>
						</div>
						<div v-if="form.parts.length === 0" class="section-empty">Henüz parça eklenmedi (kol, ön, arka, kapüşon…).</div>
						<div v-for="(pt, i) in form.parts" :key="i" class="part-row">
							<input v-model="pt.part_name" class="form-input" placeholder="Parça adı (örn. Kol)" />
							<input v-model.number="pt.quantity" type="number" min="1" class="form-input qty" placeholder="adet" />
							<input v-model="pt.size_range" class="form-input" placeholder="beden (ops.)" />
							<button type="button" class="row-del" @click="form.parts.splice(i, 1)">✕</button>
						</div>
					</div>

					<!-- Etiketler -->
					<div class="section-block">
						<div class="section-head"><span>Etiketler</span></div>
						<div class="tag-input-wrap">
							<span v-for="(t, i) in form.tags" :key="i" class="tag-chip removable" @click="form.tags.splice(i, 1)">
								#{{ t }} <em>✕</em>
							</span>
							<input
								v-model="tagDraft" class="tag-input"
								placeholder="etiket yaz, Enter…"
								@keydown.enter.prevent="addTag"
								@keydown.,.prevent="addTag"
							/>
						</div>
					</div>

					<!-- Dosyalar -->
					<div class="section-block">
						<div class="section-head"><span>Dosyalar</span></div>
						<div class="file-grid">
							<label class="file-drop">
								<span class="fd-label">Önizleme görseli</span>
								<input type="file" accept="image/*" @change="pick('preview_image', $event)" />
								<span class="fd-name">{{ fileName('preview_image') }}</span>
							</label>
							<label class="file-drop">
								<span class="fd-label">DXF</span>
								<input type="file" accept=".dxf" @change="pick('dxf', $event)" />
								<span class="fd-name">{{ fileName('dxf') }}</span>
							</label>
							<label class="file-drop">
								<span class="fd-label">Kaynak PDF</span>
								<input type="file" accept="application/pdf" @change="pick('pdf', $event)" />
								<span class="fd-name">{{ fileName('pdf') }}</span>
							</label>
						</div>
						<p class="file-hint">Geometri dosyada saklanır; veritabanı yalnızca metadatayı tutar.</p>
					</div>

					<div class="form-row span2">
						<label class="form-label">Notlar</label>
						<textarea v-model="form.notes" rows="2" class="form-input" placeholder="İç notlar (ops.)"></textarea>
					</div>

					<div class="modal-foot">
						<button type="button" class="btn btn-ghost" @click="closeModal">Vazgeç</button>
						<button type="submit" class="btn btn-primary" :disabled="form.processing">
							{{ editingId ? 'Güncelle' : 'Kaydet' }}
						</button>
					</div>
				</form>
			</div>
		</div>

		<!-- PDF içe aktarma -->
		<div v-if="importModal" class="modal-overlay" @click.self="closeImport">
			<div class="modal-box">
				<div class="modal-head">
					<span class="modal-title">PDF'ten kalıp ekle</span>
					<button class="modal-close" @click="closeImport">✕</button>
				</div>
				<p class="import-hint">Yüklenen her PDF taslak kalıp olarak eklenir; sistem arka planda inceleyip ürün tipi, beden, parça ve DXF'i çıkarır. <strong>Vektör kalıp PDF'i gerekir</strong> — taranmış/raster (resim) dosyalar baştan elenir.</p>
				<label class="file-drop import-drop" :class="{ dragging }"
					@dragover.prevent="dragging = true" @dragleave.prevent="dragging = false" @drop.prevent="onDrop">
					<span class="fd-label">PDF kalıpları bırak veya seç</span>
					<span class="fd-name">çoklu seçim · yalnızca PDF</span>
					<input type="file" accept="application/pdf" multiple @change="onPickPdf" />
				</label>
				<div v-if="importForm.files.length" class="import-tray">
					<span v-for="(f, i) in importForm.files" :key="i" class="file-chip">
						{{ f.name }} <em @click="importForm.files.splice(i, 1)">✕</em>
					</span>
				</div>
				<span v-if="importForm.errors['files.0']" class="form-error">{{ importForm.errors['files.0'] }}</span>
				<div class="modal-foot">
					<button type="button" class="btn btn-ghost" @click="closeImport">Vazgeç</button>
					<button type="button" class="btn btn-primary" :disabled="importForm.processing || !importForm.files.length" @click="submitImport">
						{{ importForm.processing ? 'Yükleniyor…' : `${importForm.files.length} PDF gönder` }}
					</button>
				</div>
			</div>
		</div>

		<!-- Zoom -->
		<div v-if="zoom" class="zoom-overlay" @click="zoom = null">
			<img :src="zoom" alt="Kalıp önizleme" />
		</div>
	</div>
</template>

<script setup>
import { ref, reactive, computed, inject, watch, onUnmounted } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Breadcrumb from '@/Components/Breadcrumb.vue'
import AtelierNav from '../Components/AtelierNav.vue'

defineOptions({ layout: AppLayout })

const $swal = inject('$swal')

const props = defineProps({
	patterns: { type: Array, default: () => [] },
	productTypes: { type: Array, default: () => [] },
	allTags: { type: Array, default: () => [] },
	filters: { type: Object, default: () => ({}) },
	canManage: { type: Boolean, default: false },
})

/* Filtreler */
const filters = reactive({
	search: props.filters.search || '',
	product_type: props.filters.product_type || '',
	status: props.filters.status || '',
	tag: props.filters.tag || '',
})
const hasFilters = computed(() => filters.search || filters.product_type || filters.status || filters.tag)

let searchTimer = null
function debouncedApply() {
	clearTimeout(searchTimer)
	searchTimer = setTimeout(apply, 350)
}
function apply() {
	router.get('/atelier/patterns', { ...filters }, { preserveState: true, preserveScroll: true, replace: true })
}
function clearFilters() {
	filters.search = ''; filters.product_type = ''; filters.status = ''; filters.tag = ''
	apply()
}

function statusLabel(s) {
	return { draft: 'Taslak', approved: 'Onaylı', rejected: 'Reddedildi' }[s] || s
}

/* Modal + form */
const modal = ref(false)
const editingId = ref(null)
const zoom = ref(null)
const tagDraft = ref('')

/* PDF içe aktarma */
const importModal = ref(false)
const dragging = ref(false)
const importForm = useForm({ files: [] })
function onPickPdf(e) { importForm.files = [...importForm.files, ...Array.from(e.target.files)]; e.target.value = '' }
function onDrop(e) {
	dragging.value = false
	const pdfs = Array.from(e.dataTransfer.files).filter((f) => f.type === 'application/pdf')
	importForm.files = [...importForm.files, ...pdfs]
}
function closeImport() { importModal.value = false; importForm.reset() }
function submitImport() {
	importForm.post('/atelier/patterns/import', { forceFormData: true, preserveScroll: true, onSuccess: closeImport })
}
function retryExtraction(p) {
	router.post(`/atelier/patterns/${p.id}/retry-extraction`, {}, { preserveScroll: true })
}

function openTracer(p) {
	router.get(`/atelier/patterns/${p.id}/tracer`)
}

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

const form = useForm({
	name: '', code: '', product_type: '', size_range: '', vendor: '', collection: '',
	status: 'draft', scale_verified: false, scale_deviation_mm: null, notes: '',
	parts: [], tags: [],
	preview_image: null, dxf: null, pdf: null,
})

function openCreate() {
	editingId.value = null
	form.reset()
	form.clearErrors()
	tagDraft.value = ''
	modal.value = true
}
function openEdit(p) {
	editingId.value = p.id
	form.clearErrors()
	form.name = p.name; form.code = p.code || ''; form.product_type = p.productType
	form.size_range = p.sizeRange || ''; form.vendor = p.vendor || ''; form.collection = p.collection || ''
	form.status = p.status; form.scale_verified = p.scaleVerified; form.scale_deviation_mm = p.scaleDeviation
	form.notes = p.notes || ''
	form.parts = p.parts.map((pt) => ({ part_name: pt.partName, quantity: pt.quantity, size_range: pt.sizeRange || '' }))
	form.tags = [...p.tags]
	form.preview_image = null; form.dxf = null; form.pdf = null
	tagDraft.value = ''
	modal.value = true
}
function closeModal() { modal.value = false }

function addPart() { form.parts.push({ part_name: '', quantity: 1, size_range: '' }) }
function addTag() {
	const t = tagDraft.value.trim().replace(/^#/, '')
	if (t && !form.tags.includes(t)) form.tags.push(t)
	tagDraft.value = ''
}
function pick(field, e) { form[field] = e.target.files[0] || null }
function fileName(field) { return form[field] ? form[field].name : 'dosya seç' }

function submit() {
	if (tagDraft.value.trim()) addTag()
	const opts = { forceFormData: true, preserveScroll: true, onSuccess: closeModal }
	if (editingId.value) {
		form.transform((d) => ({ ...d, _method: 'put' })).post(`/atelier/patterns/${editingId.value}`, opts)
	} else {
		form.post('/atelier/patterns', opts)
	}
}

async function remove(p) {
	const ok = await $swal.dangerConfirm({ title: 'Kalıp silinsin mi?', html: `<b>${p.name}</b> ve dosyaları kaldırılacak.` })
	if (ok) router.delete(`/atelier/patterns/${p.id}`, { preserveScroll: true })
}
</script>

<style scoped>
.page-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 18px; gap: 16px; }
.page-title { font-size: 22px; font-weight: 700; color: #1a1a2e; }
.page-subtitle { font-size: 13px; color: #888; margin-top: 4px; }

.card { background: #fff; border-radius: 16px; border: 1px solid #ebebf0; box-shadow: 0 1px 4px rgba(0,0,0,.04); }

/* Filtre çubuğu */
.filter-bar { display: flex; align-items: center; gap: 10px; padding: 12px 14px; margin-bottom: 18px; flex-wrap: wrap; }
.filter-search { display: flex; align-items: center; gap: 8px; flex: 1; min-width: 200px; background: #f7f7fb; border: 1px solid #eee; border-radius: 9px; padding: 8px 12px; }
.filter-search .search-ico { color: #aaa; flex-shrink: 0; }
.filter-search input { border: none; background: none; outline: none; font-size: 13px; width: 100%; color: #1a1a2e; }
.filter-select { padding: 9px 12px; border: 1px solid #e8e8f0; border-radius: 9px; font-size: 13px; color: #444; background: #fff; outline: none; cursor: pointer; }
.filter-select:focus { border-color: rgb(var(--color-primary)); }

/* Galeri */
.lib-empty { padding: 56px 20px; text-align: center; color: #999; }
.lib-empty .empty-art { font-size: 40px; color: rgb(var(--color-primary)); opacity: .4; margin-bottom: 8px; }
.lib-empty p { font-size: 14px; font-weight: 600; color: #555; }
.lib-empty span { font-size: 12.5px; }

.pattern-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 16px; }
.pattern-card { background: #fff; border: 1px solid #ebebf0; border-radius: 14px; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,.04); display: flex; flex-direction: column; transition: box-shadow .15s, transform .15s; }
.pattern-card:hover { box-shadow: 0 6px 22px rgba(0,0,0,.09); transform: translateY(-2px); }

.pc-preview { position: relative; aspect-ratio: 4 / 3; background: #f4f4f8; display: flex; align-items: center; justify-content: center; cursor: zoom-in; }
.pc-preview.empty { cursor: default; }
.pc-preview img { width: 100%; height: 100%; object-fit: cover; }
.pc-noimg { font-size: 11.5px; color: #bbb; font-style: italic; }
.status-badge { position: absolute; top: 8px; left: 8px; font-size: 10.5px; font-weight: 700; padding: 2px 8px; border-radius: 6px; }
.status-badge.draft { background: #f1f1f5; color: #777; }
.status-badge.approved { background: #ecfdf5; color: #059669; }
.status-badge.rejected { background: #fef2f2; color: #dc2626; }

.pc-body { padding: 12px 13px; display: flex; flex-direction: column; gap: 8px; flex: 1; }
.pc-title-row { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
.pc-name { font-size: 14px; font-weight: 700; color: #1a1a2e; line-height: 1.25; }
.mono-chip { font-family: 'SF Mono', Menlo, Consolas, monospace; font-size: 10.5px; background: #f0f0f5; padding: 2px 6px; border-radius: 5px; color: #666; white-space: nowrap; }
.pc-tags { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
.type-pill { padding: 2px 9px; background: #eff6ff; color: #3b82f6; border-radius: 6px; font-size: 11px; font-weight: 600; }
.size-chip { font-family: 'SF Mono', Menlo, Consolas, monospace; font-size: 10.5px; color: #888; background: #f7f7fb; padding: 2px 7px; border-radius: 5px; }
.scale-ok { font-size: 10.5px; font-weight: 600; color: #059669; }
.scale-warn { font-size: 10.5px; font-weight: 600; color: #d97706; }

.pc-parts { display: flex; flex-wrap: wrap; gap: 4px; }
.part-chip { font-size: 10.5px; background: #f6f6fa; color: #666; padding: 2px 7px; border-radius: 5px; }
.part-chip em { color: #999; font-style: normal; }
.part-more { font-size: 10.5px; color: #aaa; padding: 2px 4px; }
.pc-taglist { display: flex; flex-wrap: wrap; gap: 4px; }
.tag-chip { font-size: 10.5px; color: rgb(var(--color-primary)); background: rgb(var(--color-primary-soft)); padding: 2px 7px; border-radius: 5px; }

.pc-foot { display: flex; align-items: center; justify-content: space-between; gap: 8px; border-top: 1px solid #f5f5f8; padding-top: 9px; margin-top: auto; }
.pc-files { display: flex; gap: 6px; }
.file-link { font-size: 11px; font-weight: 700; padding: 3px 9px; border-radius: 6px; text-decoration: none; }
.file-link.dxf { background: #eef2ff; color: #4f46e5; }
.file-link.pdf { background: #fef2f2; color: #dc2626; }
.pc-actions { display: flex; gap: 4px; }
.table-action-btn { background: #f3f4f6; border: none; cursor: pointer; font-size: 12px; padding: 5px 8px; border-radius: 6px; transition: all .15s; }
.table-action-btn:hover { background: rgb(var(--color-primary-soft)); }
.table-action-btn.danger:hover { background: #fee2e2; }

/* Modal */
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,.4); display: flex; align-items: flex-start; justify-content: center; z-index: 9000; padding: 40px 16px; overflow-y: auto; }
.modal-box { background: #fff; border-radius: 16px; padding: 22px 24px; width: 380px; max-width: calc(100vw - 32px); display: flex; flex-direction: column; gap: 16px; box-shadow: 0 8px 40px rgba(0,0,0,.15); }
.modal-box.wide { width: 640px; }
.modal-head { display: flex; align-items: center; justify-content: space-between; }
.modal-title { font-size: 16px; font-weight: 700; color: #1a1a2e; }
.modal-close { background: none; border: none; cursor: pointer; font-size: 15px; color: #888; padding: 2px 6px; border-radius: 6px; }
.modal-close:hover { background: #f0f0f5; color: #1a1a2e; }
.modal-foot { display: flex; gap: 8px; justify-content: flex-end; padding-top: 4px; }

.pattern-form { display: flex; flex-direction: column; gap: 16px; }
.form-2col { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.form-row { display: flex; flex-direction: column; gap: 6px; }
.form-row.span2 { grid-column: 1 / -1; }
.form-label { font-size: 12px; font-weight: 600; color: #1a1a2e; }
.form-label .req, .req { color: #ef4444; }
.form-input { padding: 9px 12px; border: 1px solid #e8e8f0; border-radius: 8px; font-family: inherit; font-size: 13px; color: #1a1a2e; background: #fff; outline: none; transition: border-color .15s; width: 100%; }
.form-input:focus { border-color: rgb(var(--color-primary)); }
textarea.form-input { resize: vertical; }
.form-error { font-size: 11.5px; color: #ef4444; }
.scale-row { flex-direction: row; align-items: center; gap: 18px; flex-wrap: wrap; }
.check-label { display: flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 600; color: #444; cursor: pointer; }
.dev-field { display: flex; align-items: center; gap: 8px; }
.dev-lbl { font-size: 12px; color: #888; }
.dev-input { width: 110px; }

.section-block { border-top: 1px solid #f0f0f5; padding-top: 14px; display: flex; flex-direction: column; gap: 10px; }
.section-head { display: flex; align-items: center; justify-content: space-between; font-size: 13px; font-weight: 700; color: #1a1a2e; }
.section-empty { font-size: 12px; color: #aaa; font-style: italic; }
.btn-link { background: none; border: none; padding: 0; font-size: 12.5px; font-weight: 600; color: rgb(var(--color-primary)); cursor: pointer; }
.btn-link:hover { text-decoration: underline; }
.part-row { display: grid; grid-template-columns: 1fr 80px 1fr 32px; gap: 8px; align-items: center; }
.part-row .qty { text-align: center; }
.row-del { background: #f3f4f6; border: none; border-radius: 6px; cursor: pointer; color: #999; height: 34px; transition: all .15s; }
.row-del:hover { background: #fee2e2; color: #dc2626; }

.tag-input-wrap { display: flex; flex-wrap: wrap; gap: 6px; align-items: center; border: 1px solid #e8e8f0; border-radius: 8px; padding: 7px 9px; min-height: 40px; }
.tag-chip.removable { cursor: pointer; }
.tag-chip.removable em { font-style: normal; opacity: .6; }
.tag-chip.removable:hover { background: #fee2e2; color: #dc2626; }
.tag-input { border: none; outline: none; font-size: 13px; flex: 1; min-width: 120px; font-family: inherit; }

.file-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; }
.file-drop { display: flex; flex-direction: column; gap: 4px; border: 1px dashed #d8d8e2; border-radius: 9px; padding: 10px; cursor: pointer; transition: border-color .15s; }
.file-drop:hover { border-color: rgb(var(--color-primary)); }
.file-drop input[type=file] { display: none; }
.fd-label { font-size: 11.5px; font-weight: 600; color: #555; }
.fd-name { font-size: 11px; color: #999; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.file-hint { font-size: 11px; color: #aaa; }

.btn { padding: 9px 16px; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; border: 1px solid transparent; transition: all .15s; }
.btn-sm { padding: 7px 12px; font-size: 12px; }
.btn-primary { background: rgb(var(--color-primary)); color: #fff; }
.btn-primary:disabled { opacity: .55; cursor: not-allowed; }
.btn-ghost { background: #f3f4f6; color: #555; }

.zoom-overlay { position: fixed; inset: 0; background: rgba(15,15,25,.82); display: flex; align-items: center; justify-content: center; z-index: 9500; cursor: zoom-out; padding: 32px; }
.zoom-overlay img { max-width: 90vw; max-height: 90vh; border-radius: 10px; box-shadow: 0 12px 50px rgba(0,0,0,.5); }

.head-actions { display: flex; gap: 10px; }

/* Çıkarım rozetleri */
.extract-badge { position: absolute; bottom: 8px; left: 8px; display: inline-flex; align-items: center; gap: 5px; font-size: 10.5px; font-weight: 700; padding: 3px 9px; border-radius: 6px; }
.extract-badge.processing { background: rgba(37,99,235,.92); color: #fff; }
.extract-badge.failed { background: rgba(220,38,38,.92); color: #fff; cursor: help; }
.extract-badge.tracing { background: rgba(245,158,11,.95); color: #fff; cursor: help; }
.ex-spinner { width: 9px; height: 9px; border: 2px solid rgba(255,255,255,.45); border-top-color: #fff; border-radius: 50%; animation: ex-spin .7s linear infinite; }
@keyframes ex-spin { to { transform: rotate(360deg); } }

/* İçe aktarma modalı */
.import-hint { font-size: 12.5px; color: #888; line-height: 1.45; margin-top: -4px; }
.import-drop { align-items: center; text-align: center; padding: 24px; }
.import-drop.dragging { border-color: rgb(var(--color-primary)); background: rgb(var(--color-primary-soft)); }
.import-tray { display: flex; flex-wrap: wrap; gap: 7px; }
.file-chip { font-size: 12px; background: #f3f4f6; color: #555; padding: 4px 9px; border-radius: 6px; }
.file-chip em { font-style: normal; color: #aaa; cursor: pointer; margin-left: 4px; }
.file-chip em:hover { color: #dc2626; }

@media (max-width: 680px) {
	.form-2col { grid-template-columns: 1fr; }
	.file-grid { grid-template-columns: 1fr; }
	.modal-box.wide { width: 100%; }
}
</style>
