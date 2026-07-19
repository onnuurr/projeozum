<template>
	<Head title="Giydirme Detayı" />
	<div class="page-tryon-detail">
		<Breadcrumb
			:items="[
				{ label: 'Ana Sayfa', to: '/workflow', icon: 'home' },
				{ label: 'Creative' },
				{ label: 'Ürün Giydirme', to: '/creative/tryon' },
				{ label: 'Detay' },
			]"
		/>

		<div class="page-header">
			<div>
				<h1 class="page-title">{{ result.product_name }}</h1>
				<p class="page-subtitle">{{ result.mannequin_name }} · {{ result.pose_label }}</p>
			</div>
			<span class="status-badge" :class="result.status">{{ statusLabel(result.status) }}</span>
		</div>

		<div class="kpi-grid">
			<div class="kpi-card">
				<span class="kpi-label">Üretim Modeli</span>
				<span class="kpi-value model-value" :title="result.tryon_model">{{ result.tryon_model || '—' }}</span>
				<span class="kpi-hint">Sürücü: {{ result.tryon_driver || '—' }}</span>
			</div>
			<div class="kpi-card">
				<span class="kpi-label">Üretim Süresi</span>
				<span class="kpi-value">{{ formatDuration(result.generation_duration_ms) }}</span>
				<span class="kpi-hint">{{ formatDate(result.created_at) }}</span>
			</div>
			<div class="kpi-card">
				<span class="kpi-label">Onay Durumu</span>
				<span class="kpi-value">
					<span v-if="result.review_status" class="review-badge" :class="`r-${result.review_status}`">
						{{ reviewLabel(result.review_status) }}
					</span>
					<span v-else>—</span>
				</span>
				<span class="kpi-hint">{{ result.reviewer_name ? `İnceleyen: ${result.reviewer_name}` : 'Henüz incelenmedi' }}</span>
			</div>
		</div>

		<div v-if="result.error" class="card error-card">
			<div class="card-body">⚠ {{ result.error }}</div>
		</div>

		<div class="card">
			<div class="card-header"><h3>Üretilen Görsel</h3></div>
			<div class="card-body image-body">
				<img v-if="result.image_url" :src="result.image_url" :alt="result.product_name" class="main-image" />
				<span v-else class="empty-block">Henüz üretilmiş bir görsel yok.</span>
			</div>
		</div>

		<div class="card">
			<div class="card-header">
				<h3>Detay Görselleri — Otomatik Tespit Sonuçları</h3>
				<span class="hint">{{ garmentExtras.length }} görsel</span>
			</div>
			<div class="card-body">
				<div v-if="garmentExtras.length === 0" class="empty-block">
					Bu giydirmede detay görseli (yaka/düğme/kol ucu vb.) yüklenmemiş.
				</div>
				<div v-else class="extra-grid">
					<div v-for="(extra, i) in garmentExtras" :key="i" class="extra-card">
						<div class="extra-thumb">
							<img v-if="extra.image_url" :src="extra.image_url" alt="Detay görseli" />
						</div>
						<div class="extra-body">
							<div class="extra-label-row">
								<span class="extra-label-tag">Kullanılan etiket</span>
								<strong>{{ extra.label || '—' }}</strong>
							</div>
							<div v-if="extra.detected_labels.length" class="detected-list">
								<div class="sub-title">Otomatik tespit (yerel CLIP modeli)</div>
								<div v-for="cand in extra.detected_labels" :key="cand.key" class="detected-row">
									<span class="detected-name">{{ cand.display }}</span>
									<div class="score-bar-track">
										<div class="score-bar-fill" :style="{ width: `${Math.round(cand.score * 100)}%` }"></div>
									</div>
									<span class="detected-score">%{{ Math.round(cand.score * 100) }}</span>
								</div>
							</div>
							<div v-else class="empty-block small">
								Otomatik tespit sonucu yok (özellik kapalıydı ya da eşik altı kaldı).
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>

		<div v-if="identityHighlights.length" class="card">
			<div class="card-header"><h3>Ürünü Ayırt Eden Özellikler</h3></div>
			<div class="card-body">
				<div class="highlight-list">
					<span v-for="(h, i) in identityHighlights" :key="i" class="highlight-chip">{{ h }}</span>
				</div>
			</div>
		</div>

		<div class="card">
			<div class="card-header">
				<h3>Otomatik Parça Tespiti</h3>
				<span class="hint">{{ detections.length }} tespit{{ garmentScan?.model_version ? ` · ${garmentScan.model_version}` : '' }}</span>
			</div>
			<div class="card-body">
				<div v-if="!garmentScan || !garmentScan.image_url" class="empty-block">
					Bu giydirme için taranmış bir giysi görseli yok.
				</div>
				<template v-else>
					<div class="detection-frame">
						<img :src="garmentScan.image_url" alt="Giysi görseli" class="detection-image" />
						<svg v-if="detections.length" class="detection-overlay" viewBox="0 0 1 1" preserveAspectRatio="none">
							<g v-for="(d, i) in detections" :key="i">
								<rect
									:x="d.bbox.x" :y="d.bbox.y" :width="d.bbox.w" :height="d.bbox.h"
									class="detection-box" :class="[`priority-${priorityOf(d)}`, { 'source-zeroshot': d.source === 'zeroshot' }]"
								/>
							</g>
						</svg>
						<div v-if="detections.length" class="detection-labels">
							<div
								v-for="(d, i) in detections"
								:key="i"
								class="detection-tag" :class="`priority-${priorityOf(d)}`"
								:style="{ left: `${d.bbox.x * 100}%`, top: `${d.bbox.y * 100}%` }"
							>
								{{ d.label_display }} · %{{ Math.round(d.confidence * 100) }}
								<template v-if="d.source === 'zeroshot'"> · AI önerisi</template>
							</div>
						</div>
					</div>

					<div v-if="detections.length === 0" class="empty-block">
						Henüz eğitilmiş bir tespit modeli yok — parçalar (yaka/cep/etek vb.) şimdilik
						<Link :href="`/creative/garment-scans/${garmentScan.id}`">manuel işaretlenebilir</Link>.
					</div>
					<div v-else class="analysis-grid">
						<div v-for="(d, i) in detections" :key="i" class="analysis-card">
							<div class="analysis-thumb">
								<img v-if="d.crop_image_url" :src="d.crop_image_url" alt="Parça yakın çekimi" />
							</div>
							<div class="analysis-body">
								<div class="analysis-head">
									<strong>{{ d.label_display }}</strong>
									<span v-if="d.source === 'zeroshot'" class="priority-badge source-zeroshot">AI önerisi (onaylanmadı)</span>
									<span v-else class="priority-badge" :class="`priority-${priorityOf(d)}`">{{ priorityLabel(priorityOf(d)) }}</span>
								</div>
								<div v-if="analysisFields(d).length" class="analysis-fields">
									<div v-for="f in analysisFields(d)" :key="f.key" class="analysis-field" :style="{ opacity: f.confidence < 0.6 ? 0.5 : 1 }">
										<span class="field-name">{{ f.label }}</span>
										<span class="field-value">{{ f.value }}</span>
										<span class="field-confidence">%{{ Math.round(f.confidence * 100) }}</span>
									</div>
								</div>
								<div v-else class="empty-block small">
									Görsel analiz kapalı ya da sonuç yok.
								</div>
							</div>
						</div>
					</div>
				</template>
				<div v-if="garmentScan && garmentScan.image_url" class="detection-footer">
					<Link :href="`/creative/garment-scans/${garmentScan.id}`" class="link-btn">Parçaları elle düzenle</Link>
				</div>
			</div>
		</div>

		<Link href="/creative/tryon" class="btn btn-ghost back-link">← Giydirme listesine dön</Link>
	</div>
</template>

<script setup>
import { Head, Link } from '@inertiajs/vue3'
import { computed } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import Breadcrumb from '@/Components/Breadcrumb.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
	result: { type: Object, required: true },
	garmentExtras: { type: Array, default: () => [] },
	garmentScan: { type: Object, default: null },
})

const detections = computed(() => props.garmentScan?.detections ?? [])
const identityHighlights = computed(() => props.garmentScan?.identity_summary?.highlights ?? [])

function priorityOf(d) {
	return d.default_priority || 'medium'
}

const PRIORITY_LABELS = { critical: 'Kritik', high: 'Yüksek', medium: 'Orta', low: 'Düşük' }
function priorityLabel(p) {
	return PRIORITY_LABELS[p] || p
}

const FIELD_LABELS = {
	color: 'Renk', pattern: 'Desen', texture: 'Doku', fabric: 'Kumaş',
	stitching: 'Dikiş', hardware_type: 'Donanım',
}

// Analiz sonucu VERSIONED bir zarf taşır (analysis_version/model/prompt_version/
// generated_at/data) — burada yalnızca .data içindeki alan/değer/confidence
// üçlüleri gösterilir; ham değer bir prompt kısıtı değil, salt raporlama.
function analysisFields(d) {
	const data = d.analysis?.data
	if (!data) return []

	return Object.entries(FIELD_LABELS)
		.map(([key, label]) => {
			const f = data[key]
			if (!f || !f.value) return null
			const value = f.value === 'OTHER' ? (f.raw_text || 'Diğer') : f.value.replaceAll('_', ' ').toLowerCase()
			return { key, label, value, confidence: f.confidence ?? 0 }
		})
		.filter(Boolean)
}

const STATUS_LABELS = { queued: 'Sırada', generating: 'Üretiliyor', done: 'Hazır', failed: 'Başarısız' }
const REVIEW_LABELS = { pending: 'Onay Bekliyor', approved: 'Onaylı', rejected: 'Reddedildi' }
function statusLabel(s) { return STATUS_LABELS[s] || s }
function reviewLabel(r) { return REVIEW_LABELS[r] || r }

function formatDuration(ms) {
	if (!ms && ms !== 0) return '—'
	return `${(ms / 1000).toFixed(1)} sn`
}

function formatDate(iso) {
	if (!iso) return '—'
	return new Date(iso).toLocaleString('tr-TR', { dateStyle: 'medium', timeStyle: 'short' })
}
</script>

<style scoped>
.page-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 20px; gap: 16px; }
.page-title { font-size: 22px; font-weight: 700; color: #1a1a2e; line-height: 1.2; }
.page-subtitle { font-size: 13px; color: #888; margin-top: 4px; }

.kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 20px; }
.kpi-card { display: flex; flex-direction: column; gap: 6px; background: #fff; border: 1px solid #ebebf0; border-radius: 16px; padding: 18px; box-shadow: 0 1px 4px rgba(0,0,0,.04); }
.kpi-label { font-size: 12px; font-weight: 600; color: #888; text-transform: uppercase; letter-spacing: .04em; }
.kpi-value { font-size: 20px; font-weight: 800; color: #1a1a2e; }
.kpi-value.model-value { font-size: 15px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.kpi-hint { font-size: 12px; color: #999; }

.card { background: #fff; border-radius: 16px; border: 1px solid #ebebf0; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,.04); margin-bottom: 18px; }
.card-header { padding: 14px 18px; display: flex; align-items: center; gap: 12px; border-bottom: 1px solid #f0f0f5; }
.card-header h3 { font-size: 15px; font-weight: 700; color: #1a1a2e; }
.card-header .hint { font-size: 12px; color: #aaa; margin-left: auto; }
.card-body { padding: 18px; }
.empty-block { text-align: center; color: #aaa; padding: 12px 0; font-style: italic; font-size: 13px; display: block; }
.empty-block.small { padding: 6px 0; font-size: 12px; text-align: left; }

.error-card { border-color: #fca5a5; }
.error-card .card-body { color: #dc2626; font-size: 13px; }

.image-body { display: flex; justify-content: center; }
.main-image { max-width: 100%; max-height: 560px; border-radius: 12px; object-fit: contain; }

.extra-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 16px; }
.extra-card { display: flex; gap: 12px; border: 1px solid #f0f0f5; border-radius: 12px; padding: 12px; background: #fafafc; }
.extra-thumb { width: 80px; height: 80px; flex-shrink: 0; border-radius: 10px; overflow: hidden; background: #eee; }
.extra-thumb img { width: 100%; height: 100%; object-fit: cover; }
.extra-body { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 8px; }
.extra-label-row { display: flex; align-items: center; gap: 8px; font-size: 13px; color: #1a1a2e; }
.extra-label-tag { font-size: 10.5px; font-weight: 700; color: #888; text-transform: uppercase; letter-spacing: .03em; }

.sub-title { font-size: 11px; font-weight: 700; color: #555; text-transform: uppercase; letter-spacing: .03em; }
.detected-list { display: flex; flex-direction: column; gap: 6px; }
.detected-row { display: grid; grid-template-columns: 90px 1fr 36px; align-items: center; gap: 8px; }
.detected-name { font-size: 12px; color: #333; }
.score-bar-track { height: 6px; border-radius: 4px; background: #eceef2; overflow: hidden; }
.score-bar-fill { height: 100%; border-radius: 4px; background: rgb(var(--color-primary)); }
.detected-score { font-size: 11px; color: #888; text-align: right; }

.status-badge { font-size: 11px; font-weight: 700; padding: 4px 12px; border-radius: 20px; color: #fff; white-space: nowrap; }
.status-badge.queued { background: #9ca3af; }
.status-badge.generating { background: #f59e0b; }
.status-badge.done { background: #16a34a; }
.status-badge.failed { background: #dc2626; }

.review-badge { font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: 20px; color: #fff; white-space: nowrap; }
.review-badge.r-pending { background: #f59e0b; }
.review-badge.r-approved { background: #16a34a; }
.review-badge.r-rejected { background: #dc2626; }

.detection-frame { position: relative; max-width: 560px; margin: 0 auto; }
.detection-image { display: block; width: 100%; height: auto; border-radius: 12px; }
.detection-overlay { position: absolute; inset: 0; width: 100%; height: 100%; pointer-events: none; }
.detection-box { fill: rgba(37, 99, 235, .12); stroke: rgb(var(--color-primary)); stroke-width: .004; }
.detection-labels { position: absolute; inset: 0; pointer-events: none; }
.detection-tag {
	position: absolute; transform: translateY(-100%);
	background: rgb(var(--color-primary)); color: #fff;
	font-size: 10.5px; font-weight: 700; padding: 2px 6px; border-radius: 6px 6px 6px 0;
	white-space: nowrap;
}

/* Öncelik renk kodlaması — critical/high için kırmızı/turuncu vurgu, kutunun
   "korunması gereken" bir parça olduğunu ilk bakışta ayırt ettirir. */
.detection-box.priority-critical { stroke: #dc2626; fill: rgba(220, 38, 38, .14); }
.detection-box.priority-high { stroke: #f59e0b; fill: rgba(245, 158, 11, .14); }
.detection-tag.priority-critical { background: #dc2626; }
.detection-tag.priority-high { background: #f59e0b; }

/* AI önerisi (source='zeroshot'): henüz bir insan onaylamadı — kesikli çizgiyle
   fine-tune/onaylı tespitlerden görsel olarak ayrılır, bkz. ROADMAP.md Faz G.3b. */
.detection-box.source-zeroshot { stroke-dasharray: .012 .01; }
.priority-badge.source-zeroshot { background: #fef3c7; color: #b45309; }

.highlight-list { display: flex; flex-wrap: wrap; gap: 8px; }
.highlight-chip { font-size: 12.5px; font-weight: 600; color: #1a1a2e; background: rgb(var(--color-primary) / .08); border-radius: 999px; padding: 6px 14px; }

.analysis-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 14px; margin-top: 20px; }
.analysis-card { display: flex; gap: 10px; border: 1px solid #f0f0f5; border-radius: 12px; padding: 10px; background: #fafafc; }
.analysis-thumb { width: 64px; height: 64px; flex-shrink: 0; border-radius: 8px; overflow: hidden; background: #eee; }
.analysis-thumb img { width: 100%; height: 100%; object-fit: cover; }
.analysis-body { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 6px; }
.analysis-head { display: flex; align-items: center; justify-content: space-between; gap: 6px; font-size: 13px; color: #1a1a2e; }
.analysis-fields { display: flex; flex-direction: column; gap: 3px; }
.analysis-field { display: grid; grid-template-columns: 60px 1fr 32px; gap: 6px; font-size: 11.5px; color: #555; }
.field-name { color: #999; text-transform: uppercase; font-size: 10px; font-weight: 700; }
.field-value { text-transform: capitalize; }
.field-confidence { color: #aaa; text-align: right; }

.priority-badge { font-size: 10px; font-weight: 700; padding: 2px 8px; border-radius: 10px; text-transform: uppercase; letter-spacing: .02em; white-space: nowrap; }
.priority-badge.priority-critical { background: #fee2e2; color: #dc2626; }
.priority-badge.priority-high { background: #ffedd5; color: #f59e0b; }
.priority-badge.priority-medium { background: #f0f0f5; color: #888; }
.priority-badge.priority-low { background: #f0f0f5; color: #aaa; }

.detection-footer { text-align: center; margin-top: 14px; }
.link-btn { color: rgb(var(--color-primary)); font-size: 13px; font-weight: 600; text-decoration: none; }
.link-btn:hover { text-decoration: underline; }

.back-link { display: inline-block; margin-top: 4px; }

@media (max-width: 700px) {
	.page-header { flex-wrap: wrap; }
	.extra-grid { grid-template-columns: 1fr; }
	.detected-row { grid-template-columns: 80px 1fr 32px; }
	.analysis-grid { grid-template-columns: 1fr; }
}
</style>
