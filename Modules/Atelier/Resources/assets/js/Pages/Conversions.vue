<template>
	<Head title="PDF→DXF Sayısallaştırma" />
	<div class="page-conv">
		<Breadcrumb
			:items="[
				{ label: 'Ana Sayfa', to: '/workflow', icon: 'home' },
				{ label: 'Üretim Atölyesi', to: '/atelier' },
				{ label: 'Sayısallaştırma' },
			]"
		/>

		<AtelierNav current="conversions" />

		<div class="page-header">
			<div>
				<h1 class="page-title">PDF→DXF Sayısallaştırma</h1>
				<p class="page-subtitle">Kalıp PDF'lerini yükle; otomatik DXF + sınıflandırma, son söz operatörde.</p>
			</div>
			<span class="driver-badge" :class="driver">
				<span class="dot"></span>{{ driver === 'http' ? 'Servis bağlı' : 'Demo modu' }}
			</span>
		</div>

		<!-- Yükleme -->
		<div v-if="canManage" class="card upload-card">
			<label class="dropzone" :class="{ dragging }"
				@dragover.prevent="dragging = true" @dragleave.prevent="dragging = false" @drop.prevent="onDrop">
				<svg width="26" height="26" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><path d="M17 8l-5-5-5 5"/><path d="M12 3v12"/></svg>
				<span class="dz-title">PDF kalıpları buraya bırak veya seç</span>
				<span class="dz-sub">Çoklu seçim · yalnızca PDF · maks. 50MB</span>
				<input type="file" accept="application/pdf" multiple @change="onPick" />
			</label>
			<div v-if="form.files.length" class="upload-tray">
				<span v-for="(f, i) in form.files" :key="i" class="file-chip">
					{{ f.name }} <em @click="form.files.splice(i, 1)">✕</em>
				</span>
				<button class="btn btn-primary btn-sm" :disabled="form.processing" @click="upload">
					{{ form.processing ? 'Yükleniyor…' : `${form.files.length} PDF gönder` }}
				</button>
			</div>
			<p v-if="form.errors['files.0']" class="form-error">{{ form.errors['files.0'] }}</p>
		</div>

		<!-- Triyaj filtreleri -->
		<div class="triage">
			<button class="triage-pill" :class="{ active: !filters.status }" @click="setStatus('')">
				Tümü <span class="tp-count">{{ total }}</span>
			</button>
			<button v-for="s in statuses" :key="s.key" class="triage-pill" :class="[{ active: filters.status === s.key }, s.key]" @click="setStatus(s.key)">
				{{ s.label }} <span class="tp-count">{{ counts[s.key] || 0 }}</span>
			</button>
		</div>

		<!-- İş listesi -->
		<div v-if="jobs.length === 0" class="conv-empty card">
			<p>{{ filters.status ? 'Bu durumda iş yok.' : 'Henüz dönüştürme işi yok.' }}</p>
		</div>

		<div v-else class="job-grid">
			<article v-for="j in jobs" :key="j.id" class="job-card" :class="j.classification">
				<div class="jc-head">
					<span class="class-dot" :class="j.classification || 'none'"></span>
					<span class="jc-file" :title="j.fileName">{{ j.fileName }}</span>
					<span class="status-badge" :class="j.status">{{ statusLabel(j.status) }}</span>
				</div>
				<div class="jc-meta">
					<span v-if="j.confidence !== null" class="conf">güven %{{ Math.round(j.confidence) }}</span>
					<span v-if="j.metadata?.parts?.length" class="mtag">{{ j.metadata.parts.length }} parça</span>
					<span v-if="j.metadata?.scale_verified" class="mtag ok">ölçek ✓</span>
					<span v-if="j.pattern" class="mtag linked">⟶ {{ j.pattern.name }}</span>
				</div>
				<ul v-if="j.errors?.length" class="jc-errors">
					<li v-for="(e, i) in j.errors" :key="i">{{ e }}</li>
				</ul>
				<!-- Çıkarım özeti -->
				<div v-if="j.error_report?.metadata" class="jc-extraction">
					<div class="jcx-row">
						<span class="jcx-lbl">Profil:</span>
						{{ j.error_report.metadata.profile ?? '—' }}
						<span class="jcx-sub">({{ j.error_report.metadata.assembly }})</span>
					</div>
					<div v-if="j.error_report.metadata.size_layers?.length" class="jcx-row">
						<span class="jcx-lbl">Beden katmanları:</span>
						{{ j.error_report.metadata.size_layers.join(', ') }}
					</div>
					<div v-if="j.error_report.metadata.parts?.length" class="jcx-row">
						<span class="jcx-lbl">Parçalar:</span>
						<span v-for="p in j.error_report.metadata.parts" :key="p.part_name" class="mtag">
							{{ p.quantity }}× {{ p.part_name }}
						</span>
					</div>
					<div class="jcx-row">
						<span class="jcx-lbl">Ölçü tablosu:</span>
						<span :class="j.error_report.metadata.measurements ? 'mtag ok' : 'mtag warn'">
							{{ j.error_report.metadata.measurements ? 'okundu' : 'operatör doğrulamalı' }}
						</span>
					</div>
					<ul v-if="j.error_report.errors?.length" class="jc-errors">
						<li v-for="(e, i) in j.error_report.errors" :key="i">{{ e }}</li>
					</ul>
				</div>
				<div class="jc-foot">
					<button class="btn btn-ghost btn-sm" @click="review = j">İncele</button>
					<div v-if="canManage" class="jc-actions">
						<button v-if="canApprove(j)" class="btn btn-ok btn-sm" @click="approve(j)">Onayla</button>
						<button v-if="j.status === 'failed'" class="btn btn-ghost btn-sm" @click="retry(j)">Yeniden</button>
						<button v-if="isOpen(j)" class="btn btn-rej btn-sm" @click="reject(j)">Reddet</button>
					</div>
				</div>
			</article>
		</div>

		<!-- İnceleme: PDF + metadata yan yana -->
		<div v-if="review" class="modal-overlay" @click.self="review = null">
			<div class="review-box">
				<div class="rb-head">
					<span class="rb-title">{{ review.fileName }}</span>
					<button class="modal-close" @click="review = null">✕</button>
				</div>
				<div class="rb-body">
					<div class="rb-pdf">
						<embed v-if="review.pdfUrl" :src="review.pdfUrl" type="application/pdf" />
						<div v-else class="rb-noprev">PDF önizleme yok</div>
					</div>
					<div class="rb-side">
						<div class="rb-row">
							<span class="class-dot" :class="review.classification || 'none'"></span>
							<strong>{{ classLabel(review.classification) }}</strong>
							<span v-if="review.confidence !== null" class="conf">%{{ Math.round(review.confidence) }}</span>
						</div>
						<dl class="rb-meta">
							<div><dt>Ürün tipi</dt><dd>{{ review.metadata?.product_type || '—' }}</dd></div>
							<div><dt>Beden</dt><dd>{{ review.metadata?.size_range || '—' }}</dd></div>
							<div><dt>Ölçek</dt><dd>{{ review.metadata?.scale_verified ? `doğrulandı (${review.metadata?.scale_deviation_mm ?? '?'}mm)` : 'doğrulanmadı' }}</dd></div>
							<div v-if="review.metadata?.grid"><dt>Izgara</dt><dd>{{ review.metadata.grid.rows }}×{{ review.metadata.grid.cols }}</dd></div>
							<div v-if="review.metadata?.segment_count"><dt>Segment</dt><dd>{{ review.metadata.segment_count }}</dd></div>
						</dl>
						<div v-if="review.metadata?.parts?.length" class="rb-parts">
							<span class="rb-lbl">Parçalar</span>
							<div class="rb-partchips">
								<span v-for="(p, i) in review.metadata.parts" :key="i" class="part-chip">{{ p.part_name }}<em v-if="p.quantity > 1"> ×{{ p.quantity }}</em></span>
							</div>
						</div>
						<ul v-if="review.errors?.length" class="rb-errors">
							<li v-for="(e, i) in review.errors" :key="i">{{ e }}</li>
						</ul>
						<div class="rb-files">
							<a v-if="review.dxfUrl" :href="review.dxfUrl" class="file-link dxf" download>DXF indir</a>
							<a v-if="review.pdfUrl" :href="review.pdfUrl" class="file-link pdf" target="_blank">PDF aç</a>
						</div>
						<div v-if="canManage" class="rb-decide">
							<button v-if="canApprove(review)" class="btn btn-ok" @click="approve(review); review = null">Onayla → Kütüphaneye ekle</button>
							<button v-if="review.status === 'failed'" class="btn btn-ghost" @click="retry(review); review = null">Yeniden işle</button>
							<button v-if="isOpen(review)" class="btn btn-rej" @click="reject(review); review = null">Reddet</button>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Breadcrumb from '@/Components/Breadcrumb.vue'
import AtelierNav from '../Components/AtelierNav.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
	jobs: { type: Array, default: () => [] },
	filters: { type: Object, default: () => ({}) },
	counts: { type: Object, default: () => ({}) },
	driver: { type: String, default: 'mock' },
})
const canManage = computed(() => true) // sayfa zaten can:atelier.view ile korunur; aksiyonlar backend'de kapılı

const statuses = [
	{ key: 'needs_review', label: 'İncelemede' },
	{ key: 'pending', label: 'Beklemede' },
	{ key: 'processing', label: 'İşleniyor' },
	{ key: 'approved', label: 'Onaylı' },
	{ key: 'rejected', label: 'Reddedildi' },
	{ key: 'failed', label: 'Hata' },
]
const total = computed(() => Object.values(props.counts).reduce((a, b) => a + b, 0))

const filters = reactive({ status: props.filters.status || '' })
function setStatus(s) {
	filters.status = s
	router.get('/atelier/conversions', { status: s }, { preserveState: true, preserveScroll: true, replace: true })
}

function statusLabel(s) {
	return { pending: 'Beklemede', processing: 'İşleniyor', needs_review: 'İncelemede', approved: 'Onaylı', rejected: 'Reddedildi', failed: 'Hata' }[s] || s
}
function classLabel(c) {
	return { green: 'Yeşil — otomatik', yellow: 'Sarı — kontrol gerek', red: 'Kırmızı — sorunlu' }[c] || 'Sınıflandırılmadı'
}
function isOpen(j) { return ['needs_review', 'failed', 'pending', 'processing'].includes(j.status) }
function canApprove(j) { return j.status === 'needs_review' && j.dxfUrl }

/* Yükleme */
const form = useForm({ files: [] })
const dragging = ref(false)
function onPick(e) { form.files = [...form.files, ...Array.from(e.target.files)]; e.target.value = '' }
function onDrop(e) {
	dragging.value = false
	const pdfs = Array.from(e.dataTransfer.files).filter((f) => f.type === 'application/pdf')
	form.files = [...form.files, ...pdfs]
}
function upload() {
	form.post('/atelier/conversions', { forceFormData: true, preserveScroll: true, onSuccess: () => form.reset() })
}

/* Aksiyonlar */
const review = ref(null)
function approve(j) { router.post(`/atelier/conversions/${j.id}/approve`, {}, { preserveScroll: true }) }
function reject(j) { router.post(`/atelier/conversions/${j.id}/reject`, {}, { preserveScroll: true }) }
function retry(j) { router.post(`/atelier/conversions/${j.id}/retry`, {}, { preserveScroll: true }) }
</script>

<style scoped>
.page-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 18px; gap: 16px; }
.page-title { font-size: 22px; font-weight: 700; color: #1a1a2e; }
.page-subtitle { font-size: 13px; color: #888; margin-top: 4px; }
.driver-badge { display: inline-flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 600; padding: 5px 11px; border-radius: 20px; white-space: nowrap; }
.driver-badge .dot { width: 7px; height: 7px; border-radius: 50%; }
.driver-badge.http { background: #ecfdf5; color: #059669; } .driver-badge.http .dot { background: #10b981; }
.driver-badge.mock { background: #fff7ed; color: #c2410c; } .driver-badge.mock .dot { background: #f97316; }

.card { background: #fff; border-radius: 16px; border: 1px solid #ebebf0; box-shadow: 0 1px 4px rgba(0,0,0,.04); }

/* Yükleme */
.upload-card { padding: 16px; margin-bottom: 18px; }
.dropzone { display: flex; flex-direction: column; align-items: center; gap: 5px; padding: 26px; border: 2px dashed #dcdce6; border-radius: 12px; color: #999; cursor: pointer; transition: all .15s; }
.dropzone:hover, .dropzone.dragging { border-color: rgb(var(--color-primary)); color: rgb(var(--color-primary)); background: rgb(var(--color-primary-soft)); }
.dropzone input[type=file] { display: none; }
.dz-title { font-size: 14px; font-weight: 600; color: #555; }
.dz-sub { font-size: 12px; }
.upload-tray { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin-top: 12px; }
.file-chip { font-size: 12px; background: #f3f4f6; color: #555; padding: 4px 9px; border-radius: 6px; }
.file-chip em { font-style: normal; color: #aaa; cursor: pointer; margin-left: 4px; }
.file-chip em:hover { color: #dc2626; }
.form-error { font-size: 11.5px; color: #ef4444; margin-top: 8px; }

/* Triyaj */
.triage { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 16px; }
.triage-pill { display: inline-flex; align-items: center; gap: 7px; padding: 7px 13px; border: 1px solid #ebebf0; background: #fff; border-radius: 20px; font-size: 12.5px; font-weight: 600; color: #777; cursor: pointer; transition: all .15s; }
.triage-pill:hover { border-color: #d0d0dc; }
.triage-pill.active { background: #1a1a2e; color: #fff; border-color: #1a1a2e; }
.triage-pill.needs_review.active { background: #2563eb; border-color: #2563eb; }
.triage-pill.failed.active { background: #dc2626; border-color: #dc2626; }
.tp-count { font-size: 11px; background: rgba(0,0,0,.07); padding: 1px 7px; border-radius: 10px; }
.triage-pill.active .tp-count { background: rgba(255,255,255,.22); }

/* İş kartları */
.conv-empty { padding: 44px 20px; text-align: center; color: #999; font-size: 13px; }
.job-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 14px; }
.job-card { background: #fff; border: 1px solid #ebebf0; border-left-width: 3px; border-radius: 12px; padding: 13px; display: flex; flex-direction: column; gap: 9px; box-shadow: 0 1px 4px rgba(0,0,0,.04); }
.job-card.green { border-left-color: #10b981; } .job-card.yellow { border-left-color: #f59e0b; } .job-card.red { border-left-color: #ef4444; }
.jc-head { display: flex; align-items: center; gap: 8px; }
.class-dot { width: 9px; height: 9px; border-radius: 50%; flex-shrink: 0; background: #ccc; }
.class-dot.green { background: #10b981; } .class-dot.yellow { background: #f59e0b; } .class-dot.red { background: #ef4444; }
.jc-file { font-size: 13px; font-weight: 600; color: #1a1a2e; flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.status-badge { font-size: 10.5px; font-weight: 700; padding: 2px 8px; border-radius: 6px; background: #f1f1f5; color: #777; white-space: nowrap; }
.status-badge.needs_review { background: #eff6ff; color: #2563eb; }
.status-badge.approved { background: #ecfdf5; color: #059669; }
.status-badge.rejected { background: #fef2f2; color: #dc2626; }
.status-badge.failed { background: #fef2f2; color: #dc2626; }
.status-badge.processing { background: #fffbeb; color: #d97706; }
.jc-meta { display: flex; flex-wrap: wrap; gap: 6px; }
.conf { font-size: 11px; font-weight: 600; color: #888; }
.mtag { font-size: 11px; color: #666; background: #f6f6fa; padding: 2px 7px; border-radius: 5px; }
.mtag.ok { color: #059669; } .mtag.linked { color: #059669; font-weight: 600; }
.jc-errors { list-style: none; display: flex; flex-direction: column; gap: 2px; }
.jc-errors li { font-size: 11px; color: #b45309; background: #fffbeb; padding: 3px 7px; border-radius: 5px; }
.jc-foot { display: flex; align-items: center; justify-content: space-between; gap: 8px; border-top: 1px solid #f5f5f8; padding-top: 9px; margin-top: auto; }
.jc-actions { display: flex; gap: 6px; }

.btn { padding: 9px 16px; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; border: 1px solid transparent; transition: all .15s; }
.btn-sm { padding: 6px 11px; font-size: 12px; }
.btn-ghost { background: #f3f4f6; color: #555; }
.btn-ok { background: #059669; color: #fff; }
.btn-rej { background: #fef2f2; color: #dc2626; }
.btn:disabled { opacity: .55; cursor: not-allowed; }

/* İnceleme modalı */
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,.45); display: flex; align-items: center; justify-content: center; z-index: 9000; padding: 24px; }
.review-box { background: #fff; border-radius: 16px; width: 920px; max-width: 100%; max-height: 90vh; display: flex; flex-direction: column; overflow: hidden; box-shadow: 0 12px 50px rgba(0,0,0,.25); }
.rb-head { display: flex; align-items: center; justify-content: space-between; padding: 14px 18px; border-bottom: 1px solid #f0f0f5; }
.rb-title { font-size: 15px; font-weight: 700; color: #1a1a2e; }
.modal-close { background: none; border: none; cursor: pointer; font-size: 16px; color: #888; padding: 2px 6px; border-radius: 6px; }
.modal-close:hover { background: #f0f0f5; }
.rb-body { display: grid; grid-template-columns: 1.4fr 1fr; min-height: 0; flex: 1; }
.rb-pdf { background: #2b2b38; display: flex; align-items: center; justify-content: center; }
.rb-pdf embed { width: 100%; height: 100%; min-height: 420px; }
.rb-noprev { color: #aaa; font-size: 13px; }
.rb-side { padding: 18px; display: flex; flex-direction: column; gap: 14px; overflow-y: auto; }
.rb-row { display: flex; align-items: center; gap: 8px; font-size: 14px; color: #1a1a2e; }
.rb-meta { display: flex; flex-direction: column; gap: 8px; }
.rb-meta > div { display: flex; justify-content: space-between; gap: 12px; font-size: 13px; border-bottom: 1px solid #f5f5f8; padding-bottom: 6px; }
.rb-meta dt { color: #999; } .rb-meta dd { color: #1a1a2e; font-weight: 600; text-align: right; }
.rb-lbl { font-size: 12px; font-weight: 700; color: #888; display: block; margin-bottom: 6px; }
.rb-partchips { display: flex; flex-wrap: wrap; gap: 5px; }
.part-chip { font-size: 11px; background: #f4f4f8; color: #555; padding: 3px 8px; border-radius: 5px; }
.part-chip em { font-style: normal; color: #999; }
.rb-errors { list-style: none; display: flex; flex-direction: column; gap: 3px; }
.rb-errors li { font-size: 12px; color: #b45309; background: #fffbeb; padding: 4px 8px; border-radius: 6px; }
.rb-files { display: flex; gap: 8px; }
.file-link { font-size: 12px; font-weight: 700; padding: 5px 11px; border-radius: 7px; text-decoration: none; }
.file-link.dxf { background: #eef2ff; color: #4f46e5; } .file-link.pdf { background: #fef2f2; color: #dc2626; }
.rb-decide { display: flex; flex-direction: column; gap: 8px; margin-top: auto; border-top: 1px solid #f0f0f5; padding-top: 14px; }
.rb-decide .btn { width: 100%; }

@media (max-width: 760px) {
	.rb-body { grid-template-columns: 1fr; }
	.rb-pdf embed { min-height: 260px; }
}

/* Çıkarım özeti (job-card içi) */
.jc-extraction { display: flex; flex-direction: column; gap: 4px; border-top: 1px solid #f5f5f8; padding-top: 8px; }
.jcx-row { display: flex; align-items: center; flex-wrap: wrap; gap: 4px; font-size: 11.5px; color: #444; }
.jcx-lbl { font-weight: 700; color: #666; flex-shrink: 0; }
.jcx-sub { color: #aaa; }
.mtag.warn { color: #b45309; background: #fffbeb; }
</style>
