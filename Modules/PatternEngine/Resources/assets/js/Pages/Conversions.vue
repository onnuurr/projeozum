<script setup>
import { ref, reactive, computed, inject } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import {
	OzPageHeader, OzModuleTabs, OzStatStrip, OzCard, OzButton, OzPill, OzEmptyState, OzFileDrop, OzModal, useOzConfirm,
} from '@/Components/Oz'
import { useCan } from '@/composables/useCan'

defineOptions({ layout: AppLayout })

const props = defineProps({
	jobs: { type: Array, default: () => [] },
	filters: { type: Object, default: () => ({}) },
	counts: { type: Object, default: () => ({}) },
	driver: { type: String, default: 'mock' },
})

const { can } = useCan()
// Sayfa can:atelier.view ile korunur (salt-okuma yeterli); onayla/reddet/yeniden dene/yükleme
// aksiyonları backend'de ayrı can:atelier.conversion.manage ister — buton görünürlüğü buna göre.
const canManage = computed(() => can('atelier.conversion.manage'))
const showToast = inject('showToast', null)
const confirm = useOzConfirm()

const STATUS = { needs_review: 'İncelemede', pending: 'Beklemede', processing: 'İşleniyor', approved: 'Onaylı', rejected: 'Reddedildi', failed: 'Hata' }
const STATUS_TONE = { needs_review: 'info', pending: 'neutral', processing: 'warning', approved: 'success', rejected: 'danger', failed: 'danger' }
const CLASS_LABEL = { green: 'Yeşil — otomatik', yellow: 'Sarı — kontrol gerek', red: 'Kırmızı — sorunlu' }

const countOf = (k) => props.counts?.[k] ?? 0
const total = computed(() => Object.values(props.counts ?? {}).reduce((a, b) => a + b, 0))
const stats = computed(() => [
	{ key: 'review', label: 'İnceleme Bekleyen', value: countOf('needs_review'), icon: 'eye', tone: 'info' },
	{ key: 'queue', label: 'Kuyrukta', value: countOf('pending') + countOf('processing'), icon: 'clock', tone: 'accent' },
	{ key: 'approved', label: 'Kütüphaneye Eklenen', value: countOf('approved'), icon: 'success', tone: 'success' },
	{ key: 'failed', label: 'Hatalı', value: countOf('failed'), icon: 'alert', tone: countOf('failed') ? 'danger' : 'success' },
])

/* ── durum filtresi (sunucu tarafı) ── */
const filters = reactive({ status: props.filters.status || '' })
function setStatus(s) {
	filters.status = s
	router.get('/atelier/conversions', s ? { status: s } : {}, { preserveState: true, preserveScroll: true, replace: true })
}

const isOpen = (j) => ['needs_review', 'failed', 'pending', 'processing'].includes(j.status)
const canApprove = (j) => j.status === 'needs_review' && !!j.dxfUrl
const classLabel = (c) => CLASS_LABEL[c] ?? 'Sınıflandırılmadı'

/* ── yükleme ── */
const form = useForm({ files: [] })
function onReject(list) {
	showToast?.({ type: 'error', title: 'Bazı dosyalar eklenmedi', message: list.map((r) => `${r.file.name}: ${r.reason}`).join(' · ') })
}
function upload() {
	if (!form.files.length || form.processing) return
	form.post('/atelier/conversions', {
		forceFormData: true,
		preserveScroll: true,
		onSuccess: () => form.reset(),
		onError: (errs) => showToast?.({ type: 'error', title: 'Yükleme başarısız', message: Object.values(errs)[0] || 'Doğrulama hatası.' }),
	})
}
const uploadError = computed(() => form.errors.files || Object.entries(form.errors).find(([k]) => k.startsWith('files.'))?.[1] || '')

/* ── aksiyonlar ── */
const review = ref(null)
const reviewOpen = computed({ get: () => !!review.value, set: (v) => { if (!v) review.value = null } })
const errorToast = (title) => (errs) => showToast?.({ type: 'error', title, message: Object.values(errs)[0] || 'Sunucu hatası.' })

function approve(j) {
	review.value = null
	router.post(`/atelier/conversions/${j.id}/approve`, {}, { preserveScroll: true, onError: errorToast('Onaylanamadı') })
}
function retry(j) {
	review.value = null
	router.post(`/atelier/conversions/${j.id}/retry`, {}, { preserveScroll: true, onError: errorToast('Yeniden işlenemedi') })
}
async function reject(j) {
	const ok = await confirm.danger('İş reddedilsin mi?', `${j.fileName} kütüphaneye eklenmeyecek.`, { confirmText: 'Reddet' })
	if (!ok) return
	review.value = null
	router.post(`/atelier/conversions/${j.id}/reject`, {}, { preserveScroll: true, onError: errorToast('Reddedilemedi') })
}
</script>

<template>
	<Head title="PDF→DXF Sayısallaştırma" />

	<OzPageHeader title="PDF→DXF Sayısallaştırma" subtitle="Kalıp PDF'lerini yükle; otomatik DXF + sınıflandırma, son söz operatörde.">
		<template #actions>
			<OzPill :tone="driver === 'http' ? 'success' : 'warning'">{{ driver === 'http' ? 'Servis bağlı' : 'Demo modu' }}</OzPill>
		</template>
	</OzPageHeader>
	<OzModuleTabs />

	<OzStatStrip :items="stats" />

	<OzCard v-if="canManage" title="PDF Yükle" subtitle="çoklu seçim, her dosya ayrı iş olur" class="upload">
		<div class="upload-body">
			<OzFileDrop v-model="form.files" accept="application/pdf,.pdf" multiple :max-size="50 * 1024 * 1024" title="PDF kalıpları buraya bırakın" hint="PDF · en fazla 50 MB" :invalid="!!uploadError" @reject="onReject" />
			<p v-if="uploadError" class="field-error">{{ uploadError }}</p>
			<div v-if="form.files.length" class="upload-actions">
				<OzButton :disabled="form.processing" @click="form.reset()">Temizle</OzButton>
				<OzButton variant="primary" icon="upload" :loading="form.processing" @click="upload">{{ form.files.length }} PDF gönder</OzButton>
			</div>
		</div>
	</OzCard>

	<div class="chips" role="tablist" aria-label="Durum filtresi">
		<button class="chip" :class="{ active: !filters.status }" type="button" @click="setStatus('')">Tümü <span class="chip-count">{{ total }}</span></button>
		<button
			v-for="(label, key) in STATUS"
			:key="key"
			class="chip"
			:class="[{ active: filters.status === key }, `is-${key}`]"
			type="button"
			@click="setStatus(key)"
		>{{ label }} <span class="chip-count">{{ countOf(key) }}</span></button>
	</div>

	<OzCard title="Dönüştürme İşleri" :meta="`${jobs.length} iş`">
		<OzEmptyState v-if="!jobs.length" icon="file" :title="filters.status ? 'Bu durumda iş yok' : 'Henüz dönüştürme işi yok'" :description="!filters.status && canManage ? 'Yukarıdan PDF yükleyerek başlayın.' : ''" compact />
		<div v-else class="job-grid">
			<article v-for="j in jobs" :key="j.id" class="job" :class="`cls-${j.classification || 'none'}`">
				<header class="job-head">
					<span class="dot" :title="classLabel(j.classification)" />
					<span class="job-file" :title="j.fileName">{{ j.fileName }}</span>
					<OzPill :tone="STATUS_TONE[j.status] ?? 'neutral'">{{ STATUS[j.status] ?? j.status }}</OzPill>
				</header>
				<div class="tags">
					<span v-if="j.confidence !== null" class="tag strong">güven %{{ Math.round(j.confidence) }}</span>
					<span v-if="j.metadata?.scale_verified" class="tag ok">ölçek ✓</span>
					<span v-if="j.pattern" class="tag ok">→ {{ j.pattern.name }}</span>
				</div>
				<dl v-if="j.metadata" class="extract">
					<div><dt>Profil</dt><dd>{{ j.metadata.profile ?? '—' }} <span v-if="j.metadata.assembly" class="dim">({{ j.metadata.assembly }})</span></dd></div>
					<div v-if="j.metadata.size_layers?.length"><dt>Beden</dt><dd>{{ j.metadata.size_layers.join(', ') }}</dd></div>
					<div v-if="j.metadata.parts?.length">
						<dt>Parçalar</dt>
						<dd class="parts"><span v-for="p in j.metadata.parts" :key="p.part_name" class="tag">{{ p.quantity }}× {{ p.part_name }}</span></dd>
					</div>
					<div>
						<dt>Ölçü tablosu</dt>
						<dd><span class="tag" :class="j.metadata.measurements ? 'ok' : 'warn'">{{ j.metadata.measurements ? 'okundu' : 'operatör doğrulamalı' }}</span></dd>
					</div>
				</dl>
				<ul v-if="j.errors?.length" class="errors">
					<li v-for="(e, i) in j.errors" :key="i">{{ e }}</li>
				</ul>
				<footer class="job-foot">
					<OzButton size="sm" icon="eye" @click="review = j">İncele</OzButton>
					<div v-if="canManage" class="job-actions">
						<OzButton v-if="j.status === 'failed'" size="sm" icon="refresh" @click="retry(j)">Yeniden</OzButton>
						<OzButton v-if="isOpen(j)" size="sm" variant="danger" @click="reject(j)">Reddet</OzButton>
						<OzButton v-if="canApprove(j)" size="sm" variant="primary" icon="check" @click="approve(j)">Onayla</OzButton>
					</div>
				</footer>
			</article>
		</div>
	</OzCard>

	<!-- İnceleme: PDF + çıkarım özeti yan yana -->
	<OzModal v-model:open="reviewOpen" :title="review?.fileName ?? ''" :subtitle="review ? classLabel(review.classification) : ''" icon="file" size="lg">
		<div v-if="review" class="rv">
			<div class="rv-pdf">
				<embed v-if="review.pdfUrl" :src="review.pdfUrl" type="application/pdf">
				<OzEmptyState v-else icon="file" title="PDF önizleme yok" compact />
			</div>
			<div class="rv-side">
				<div class="rv-class">
					<span class="dot" :class="`cls-${review.classification || 'none'}`" />
					<b>{{ classLabel(review.classification) }}</b>
					<span v-if="review.confidence !== null" class="dim">%{{ Math.round(review.confidence) }}</span>
				</div>
				<dl class="kv">
					<div><dt>Ürün tipi</dt><dd>{{ review.metadata?.product_type || '—' }}</dd></div>
					<div><dt>Beden</dt><dd>{{ review.metadata?.size_range || '—' }}</dd></div>
					<div><dt>Ölçek</dt><dd>{{ review.metadata?.scale_verified ? `doğrulandı (${review.metadata?.scale_deviation_mm ?? '?'} mm)` : 'doğrulanmadı' }}</dd></div>
					<div v-if="review.metadata?.grid"><dt>Izgara</dt><dd>{{ review.metadata.grid.rows }}×{{ review.metadata.grid.cols }}</dd></div>
					<div v-if="review.metadata?.segment_count"><dt>Segment</dt><dd>{{ review.metadata.segment_count }}</dd></div>
				</dl>
				<div v-if="review.metadata?.parts?.length" class="rv-block">
					<span class="rv-lbl">Parçalar</span>
					<div class="parts"><span v-for="(p, i) in review.metadata.parts" :key="i" class="tag">{{ p.part_name }}<template v-if="p.quantity > 1"> ×{{ p.quantity }}</template></span></div>
				</div>
				<div v-if="review.metadata?.measurements?.labels?.length" class="rv-block">
					<span class="rv-lbl">Ölçü tablosu ({{ review.metadata.measurements.unit || 'cm' }})</span>
					<div class="measure-scroll">
						<table class="measure">
							<thead>
								<tr>
									<th class="corner">Beden</th>
									<th v-for="(s, i) in review.metadata.measurements.sizes" :key="i">{{ s }}</th>
								</tr>
							</thead>
							<tbody>
								<tr v-for="lbl in review.metadata.measurements.labels" :key="lbl">
									<th>{{ lbl }}</th>
									<td v-for="(v, i) in review.metadata.measurements.matrix[lbl]" :key="i">{{ v ?? '—' }}</td>
								</tr>
							</tbody>
						</table>
					</div>
					<p v-if="review.metadata.measurements.note" class="dim small">{{ review.metadata.measurements.note }}</p>
				</div>
				<ul v-if="review.errors?.length" class="errors">
					<li v-for="(e, i) in review.errors" :key="i">{{ e }}</li>
				</ul>
				<div class="files">
					<a v-if="review.dxfUrl" :href="review.dxfUrl" class="file-link is-dxf" download>DXF indir</a>
					<a v-if="review.pdfUrl" :href="review.pdfUrl" class="file-link is-pdf" target="_blank" rel="noopener">PDF aç</a>
				</div>
			</div>
		</div>
		<template v-if="canManage && review && isOpen(review)" #footer>
			<OzButton v-if="review.status === 'failed'" icon="refresh" @click="retry(review)">Yeniden İşle</OzButton>
			<OzButton variant="danger" @click="reject(review)">Reddet</OzButton>
			<OzButton v-if="canApprove(review)" variant="primary" icon="check" @click="approve(review)">Onayla → Kütüphaneye Ekle</OzButton>
		</template>
	</OzModal>
</template>

<style scoped>

.dim { color: var(--oz-muted); font-weight: 400; }
.small { font-size: 11px; margin: 4px 0 0; }
.field-error { margin: 0; font-size: 12px; color: var(--oz-danger); }

.upload { margin-bottom: 14px; }
.upload-body { display: flex; flex-direction: column; gap: 10px; padding: 0 18px 16px; }
.upload-actions { display: flex; justify-content: flex-end; gap: 8px; }

.chips { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 12px; }
.chip { padding: 6px 12px; border-radius: 999px; border: 1px solid var(--oz-border); background: var(--oz-surface); font-size: 12px; font-weight: 600; color: var(--oz-muted); cursor: pointer; transition: all .15s; }
.chip:hover { border-color: var(--oz-border-strong); }
.chip.active { background: var(--oz-accent); color: #fff; border-color: var(--oz-accent); }
.chip.active.is-failed, .chip.active.is-rejected { background: var(--oz-danger); border-color: var(--oz-danger); }
.chip-count { display: inline-block; min-width: 18px; margin-left: 4px; padding: 0 5px; border-radius: 999px; background: var(--oz-surface-2); color: var(--oz-muted); font-size: 11px; text-align: center; }
.chip.active .chip-count { background: rgba(255, 255, 255, .22); color: #fff; }

/* sınıflandırma rengi: yeşil otomatik / sarı kontrol / kırmızı sorunlu */
.cls-green { --cls: var(--oz-success); }
.cls-yellow { --cls: var(--oz-warning); }
.cls-red { --cls: var(--oz-danger); }
.cls-none { --cls: var(--oz-border-strong); }
.dot { width: 9px; height: 9px; border-radius: 50%; flex-shrink: 0; background: var(--cls, var(--oz-border-strong)); }

.job-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(min(300px, 100%), 1fr)); gap: 12px; padding: 0 18px 18px; }
.job {
	display: flex; flex-direction: column; gap: 9px; padding: 12px 13px;
	border: 1px solid var(--oz-border); border-left: 3px solid var(--cls); border-radius: var(--oz-radius-sm); background: var(--oz-surface);
}
.job-head { display: flex; align-items: center; gap: 8px; min-width: 0; }
.job-file { flex: 1; min-width: 0; font-size: 13px; font-weight: 600; color: var(--oz-ink-soft); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.tags, .parts { display: flex; flex-wrap: wrap; gap: 5px; }
.tag { display: inline-flex; align-items: center; font-size: 11px; color: var(--oz-muted); background: var(--oz-surface-2); border: 1px solid var(--oz-border-soft); padding: 1px 7px; border-radius: 5px; }
.tag.strong { color: var(--oz-ink-soft); font-weight: 600; }
.tag.ok { color: var(--oz-success); background: var(--oz-success-soft); border-color: transparent; font-weight: 600; }
.tag.warn { color: var(--oz-warning); background: var(--oz-warning-soft); border-color: transparent; font-weight: 600; }
.extract { display: flex; flex-direction: column; gap: 5px; margin: 0; padding-top: 8px; border-top: 1px solid var(--oz-border-soft); }
.extract > div { display: grid; grid-template-columns: 88px minmax(0, 1fr); gap: 8px; align-items: baseline; font-size: 12px; }
.extract dt { color: var(--oz-muted); font-weight: 600; }
.extract dd { margin: 0; color: var(--oz-ink); }
.errors { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 3px; }
.errors li { font-size: 11.5px; color: var(--oz-warning); background: var(--oz-warning-soft); padding: 4px 8px; border-radius: 6px; }
.job-foot { display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-top: auto; padding-top: 9px; border-top: 1px solid var(--oz-border-soft); }
.job-actions { display: flex; gap: 6px; flex-wrap: wrap; justify-content: flex-end; }

/* inceleme penceresi */
.rv { display: grid; grid-template-columns: minmax(0, 1.3fr) minmax(0, 1fr); gap: 16px; min-height: 460px; }
.rv-pdf { border-radius: var(--oz-radius-sm); overflow: hidden; background: var(--oz-surface-2); border: 1px solid var(--oz-border); display: flex; align-items: center; justify-content: center; }
.rv-pdf embed { width: 100%; height: 100%; min-height: 460px; }
.rv-side { display: flex; flex-direction: column; gap: 14px; min-width: 0; }
.rv-class { display: flex; align-items: center; gap: 8px; font-size: 13.5px; color: var(--oz-ink-soft); }
.kv { display: flex; flex-direction: column; margin: 0; }
.kv > div { display: flex; justify-content: space-between; gap: 12px; padding: 6px 0; font-size: 12.5px; border-bottom: 1px solid var(--oz-border-soft); }
.kv dt { color: var(--oz-muted); }
.kv dd { margin: 0; color: var(--oz-ink-soft); font-weight: 600; text-align: right; }
.rv-block { display: flex; flex-direction: column; gap: 6px; }
.rv-lbl { font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: var(--oz-muted); }
.measure-scroll { overflow-x: auto; border: 1px solid var(--oz-border); border-radius: 8px; }
.measure { border-collapse: collapse; font-size: 11px; white-space: nowrap; }
.measure th, .measure td { border: 1px solid var(--oz-border-soft); padding: 3px 7px; text-align: center; color: var(--oz-ink); }
.measure thead th { background: var(--oz-surface-2); font-weight: 700; }
.measure tbody th { background: var(--oz-surface-2); color: var(--oz-accent); font-weight: 700; text-align: left; position: sticky; left: 0; }
.measure .corner { position: sticky; left: 0; z-index: 1; }
.files { display: flex; gap: 8px; }
.file-link { font-size: 12px; font-weight: 700; padding: 5px 11px; border-radius: 7px; text-decoration: none; }
.file-link.is-dxf { background: var(--oz-accent-soft); color: var(--oz-accent); }
.file-link.is-pdf { background: var(--oz-danger-soft); color: var(--oz-danger); }
@media (max-width: 760px) {
	.rv { grid-template-columns: 1fr; }
	.rv-pdf embed { min-height: 260px; }
}
</style>
