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

		<PageHeader :title="result.product_name" :subtitle="`${result.mannequin_name} · ${result.pose_label}`">
			<template #actions>
				<Badge :color="statusColor(result.status)" :label="statusLabel(result.status)" variant="filled" />
			</template>
		</PageHeader>

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
					<Badge v-if="result.review_status" :color="reviewColor(result.review_status)" :label="reviewLabel(result.review_status)" variant="filled" />
					<span v-else>—</span>
				</span>
				<span class="kpi-hint">{{ result.reviewer_name ? `İnceleyen: ${result.reviewer_name}` : 'Henüz incelenmedi' }}</span>
			</div>
			<div v-if="result.color_audit" class="kpi-card">
				<span class="kpi-label">Renk Sadakati</span>
				<span class="kpi-value">
					<Badge :color="colorAuditColor" :label="colorAuditLabel" variant="filled" />
				</span>
				<span class="kpi-hint">{{ result.color_audit.locked ? 'Renk kilidi uygulandı' : 'Yalnız ölçüldü (kilit kapalı)' }}</span>
			</div>
		</div>

		<Alert v-if="result.error" variant="error" :message="result.error" />

		<Card title="Üretilen Görsel">
			<div class="image-body">
				<img v-if="result.image_url" :src="result.image_url" :alt="result.product_name" class="main-image" />
				<EmptyState v-else :icon="ImageOff" title="Henüz üretilmiş bir görsel yok." />
			</div>
		</Card>

		<Card title="Detay Görselleri — Otomatik Tespit Sonuçları">
			<template #actions>
				<span class="hint">{{ garmentExtras.length }} görsel</span>
			</template>

			<EmptyState
				v-if="garmentExtras.length === 0"
				:icon="ImageOff"
				title="Bu giydirmede detay görseli yüklenmemiş."
				hint="Yaka/düğme/kol ucu vb."
			/>
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
		</Card>

		<Card v-if="identityHighlights.length" title="Ürünü Ayırt Eden Özellikler">
			<div class="highlight-list">
				<Tag v-for="(h, i) in identityHighlights" :key="i" :label="h" color="primary" />
			</div>
		</Card>

		<Card title="Otomatik Parça Tespiti">
			<template #actions>
				<span class="hint">{{ detections.length }} tespit{{ garmentScan?.model_version ? ` · ${garmentScan.model_version}` : '' }}</span>
			</template>

			<EmptyState v-if="!garmentScan || !garmentScan.image_url" :icon="ScanSearch" title="Bu giydirme için taranmış bir giysi görseli yok." />
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
									<Badge v-if="d.source === 'zeroshot'" color="warning" variant="tonal" label="AI önerisi (onaylanmadı)" />
									<Badge v-else :color="priorityColor(priorityOf(d))" variant="tonal" :label="priorityLabel(priorityOf(d))" />
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
		</Card>

		<Button variant="ghost" class="back-link" @click="router.visit('/creative/tryon')">← Giydirme listesine dön</Button>
	</div>
</template>

<script setup>
import { Head, Link, router } from '@inertiajs/vue3'
import { computed } from 'vue'
import { ImageOff, ScanSearch } from 'lucide-vue-next'
import AppLayout from '@/Layouts/AppLayout.vue'
import Breadcrumb from '@/Components/Breadcrumb.vue'
import PageHeader from '@/Components/PageHeader.vue'
import Card from '@/Components/Card.vue'
import Button from '@/Components/Button.vue'
import Alert from '@/Components/Alert.vue'
import Badge from '@/Components/Badge.vue'
import Tag from '@/Components/Tag.vue'
import EmptyState from '@/Components/EmptyState.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
	result: { type: Object, required: true },
	garmentExtras: { type: Array, default: () => [] },
	garmentScan: { type: Object, default: null },
})

const detections = computed(() => props.garmentScan?.detections ?? [])
const identityHighlights = computed(() => props.garmentScan?.identity_summary?.highlights ?? [])

// Renk sadakati (Faz Q) — gözle ayırt edilemez (<5) / hafif fark (5-10) / belirgin
// sapma (>10) eşikleri kullanıcının kendi tahminine dayanır, henüz kalibre edilmedi.
const colorAuditLabel = computed(() => {
	const a = props.result.color_audit
	if (!a) return '—'
	if (a.confidence !== 'high' || a.delta_e === null) return 'Ölçülemedi'
	return `ΔE ${a.delta_e}`
})
const colorAuditColor = computed(() => {
	const a = props.result.color_audit
	if (!a || a.confidence !== 'high' || a.delta_e === null) return 'neutral'
	if (a.delta_e <= 5) return 'success'
	if (a.delta_e <= 10) return 'warning'
	return 'danger'
})

function priorityOf(d) {
	return d.default_priority || 'medium'
}

const PRIORITY_LABELS = { critical: 'Kritik', high: 'Yüksek', medium: 'Orta', low: 'Düşük' }
function priorityLabel(p) {
	return PRIORITY_LABELS[p] || p
}

const PRIORITY_COLORS = { critical: 'danger', high: 'warning', medium: 'neutral', low: 'neutral' }
function priorityColor(p) {
	return PRIORITY_COLORS[p] ?? 'neutral'
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

const STATUS_COLORS = { queued: 'neutral', generating: 'warning', done: 'success', failed: 'danger' }
const REVIEW_COLORS = { pending: 'warning', approved: 'success', rejected: 'danger' }
function statusColor(s) { return STATUS_COLORS[s] ?? 'neutral' }
function reviewColor(r) { return REVIEW_COLORS[r] ?? 'neutral' }

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
.hint { font-size: 12px; color: var(--color-muted); white-space: nowrap; }

.kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 20px; }
.kpi-card { display: flex; flex-direction: column; gap: 6px; background: var(--color-surface); border: 1px solid var(--color-outline-variant); border-radius: 16px; padding: 18px; box-shadow: 0 1px 4px rgba(0,0,0,.04); }
.kpi-label { font-size: 12px; font-weight: 600; color: var(--color-muted); text-transform: uppercase; letter-spacing: .04em; }
.kpi-value { font-size: 20px; font-weight: 800; color: var(--color-ink); }
.kpi-value.model-value { font-size: 15px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.kpi-hint { font-size: 12px; color: var(--color-muted); }

.empty-block { text-align: center; color: var(--color-muted); padding: 12px 0; font-style: italic; font-size: 13px; display: block; }
.empty-block.small { padding: 6px 0; font-size: 12px; text-align: left; }

.image-body { display: flex; justify-content: center; }
.main-image { max-width: 100%; max-height: 560px; border-radius: 12px; object-fit: contain; }

.extra-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 16px; }
.extra-card { display: flex; gap: 12px; border: 1px solid var(--color-outline-variant); border-radius: 12px; padding: 12px; background: var(--color-surface-container-low); }
.extra-thumb { width: 80px; height: 80px; flex-shrink: 0; border-radius: 10px; overflow: hidden; background: var(--color-surface-container-high); }
.extra-thumb img { width: 100%; height: 100%; object-fit: cover; }
.extra-body { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 8px; }
.extra-label-row { display: flex; align-items: center; gap: 8px; font-size: 13px; color: var(--color-ink); }
.extra-label-tag { font-size: 10.5px; font-weight: 700; color: var(--color-muted); text-transform: uppercase; letter-spacing: .03em; }

.sub-title { font-size: 11px; font-weight: 700; color: var(--color-on-surface-variant); text-transform: uppercase; letter-spacing: .03em; }
.detected-list { display: flex; flex-direction: column; gap: 6px; }
.detected-row { display: grid; grid-template-columns: 90px 1fr 36px; align-items: center; gap: 8px; }
.detected-name { font-size: 12px; color: var(--color-ink); }
.score-bar-track { height: 6px; border-radius: 4px; background: var(--color-surface-container-high); overflow: hidden; }
.score-bar-fill { height: 100%; border-radius: 4px; background: var(--color-primary); }
.detected-score { font-size: 11px; color: var(--color-muted); text-align: right; }

.detection-frame { position: relative; max-width: 560px; margin: 0 auto; }
.detection-image { display: block; width: 100%; height: auto; border-radius: 12px; }
.detection-overlay { position: absolute; inset: 0; width: 100%; height: 100%; pointer-events: none; }
.detection-box { fill: color-mix(in srgb, var(--color-primary) 12%, transparent); stroke: var(--color-primary); stroke-width: .004; }
.detection-labels { position: absolute; inset: 0; pointer-events: none; }
.detection-tag {
	position: absolute; transform: translateY(-100%);
	background: var(--color-primary); color: var(--color-on-primary);
	font-size: 10.5px; font-weight: 700; padding: 2px 6px; border-radius: 6px 6px 6px 0;
	white-space: nowrap;
}

/* Öncelik renk kodlaması — critical/high için kırmızı/turuncu vurgu, kutunun
   "korunması gereken" bir parça olduğunu ilk bakışta ayırt ettirir. */
.detection-box.priority-critical { stroke: var(--color-danger); fill: color-mix(in srgb, var(--color-danger) 14%, transparent); }
.detection-box.priority-high { stroke: var(--color-warning); fill: color-mix(in srgb, var(--color-warning) 14%, transparent); }
.detection-tag.priority-critical { background: var(--color-danger); }
.detection-tag.priority-high { background: var(--color-warning); }

/* AI önerisi (source='zeroshot'): henüz bir insan onaylamadı — kesikli çizgiyle
   fine-tune/onaylı tespitlerden görsel olarak ayrılır, bkz. ROADMAP.md Faz G.3b. */
.detection-box.source-zeroshot { stroke-dasharray: .012 .01; }

.highlight-list { display: flex; flex-wrap: wrap; gap: 8px; }

.analysis-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 14px; margin-top: 20px; }
.analysis-card { display: flex; gap: 10px; border: 1px solid var(--color-outline-variant); border-radius: 12px; padding: 10px; background: var(--color-surface-container-low); }
.analysis-thumb { width: 64px; height: 64px; flex-shrink: 0; border-radius: 8px; overflow: hidden; background: var(--color-surface-container-high); }
.analysis-thumb img { width: 100%; height: 100%; object-fit: cover; }
.analysis-body { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 6px; }
.analysis-head { display: flex; align-items: center; justify-content: space-between; gap: 6px; font-size: 13px; color: var(--color-ink); }
.analysis-fields { display: flex; flex-direction: column; gap: 3px; }
.analysis-field { display: grid; grid-template-columns: 60px 1fr 32px; gap: 6px; font-size: 11.5px; color: var(--color-on-surface-variant); }
.field-name { color: var(--color-muted); text-transform: uppercase; font-size: 10px; font-weight: 700; }
.field-value { text-transform: capitalize; }
.field-confidence { color: var(--color-muted); text-align: right; }

.detection-footer { text-align: center; margin-top: 14px; }
.link-btn { color: var(--color-primary); font-size: 13px; font-weight: 600; text-decoration: none; }
.link-btn:hover { text-decoration: underline; }

.back-link { display: inline-block; margin-top: 4px; }

@media (max-width: 700px) {
	.extra-grid { grid-template-columns: 1fr; }
	.detected-row { grid-template-columns: 80px 1fr 32px; }
	.analysis-grid { grid-template-columns: 1fr; }
}
</style>
