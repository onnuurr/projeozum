<template>
	<Head title="Ret Analiz Raporu" />
	<div class="page-review-report-show">
		<Breadcrumb
			:items="[
				{ label: 'Ana Sayfa', to: '/workflow', icon: 'home' },
				{ label: 'Süper Admin' },
				{ label: 'Ret Analiz Raporları', to: '/creative/review-reports' },
				{ label: reportLabel },
			]"
		/>

		<PageHeader title="Ret Analiz Raporu" :subtitle="subtitleText" />

		<div class="kpi-grid">
			<StatWidget :icon="Shirt" :value="report.tryon_results?.total_reviewed ?? 0" title="Giydirme İncelendi" color="info" />
			<StatWidget
				:icon="Percent"
				:value="formatRate(report.tryon_results?.rejection_rate)"
				title="Giydirme Ret Oranı"
				:color="rateColor(report.tryon_results?.rejection_rate)"
			/>
			<StatWidget :icon="User" :value="report.mannequins?.total_reviewed ?? 0" title="Manken İncelendi" color="info" />
			<StatWidget :icon="ImageIcon" :value="report.creative_assets?.total_reviewed ?? 0" title="Creative Studio İncelendi" color="info" />
		</div>

		<Card v-if="insightLines.length" title="Ne Yapılabilir? (AI Önerisi)" class="section-card ai-card">
			<div class="insight-list">
				<p v-for="(line, i) in insightLines" :key="i" class="insight-item">{{ line }}</p>
			</div>
		</Card>

		<ReviewReportSection title="Giydirme" :summary="report.tryon_results" />
		<ReviewReportSection title="Manken" :summary="report.mannequins" />
		<ReviewReportSection title="Creative Studio" :summary="report.creative_assets" />

		<Card v-if="report.detection_summary" title="Giysi Parça Tespiti" class="section-card">
			<template #actions>
				<Badge
					:color="rateColor(report.detection_summary.zero_detection_rate)"
					:label="`${report.detection_summary.scans_with_zero_detections}/${report.detection_summary.total_scans} sıfır tespit · ${formatRate(report.detection_summary.zero_detection_rate)}`"
				/>
			</template>

			<div class="body-stack">
				<p class="detection-hint">
					Sıfır-tespitli oran, henüz eğitilmemiş bir model ya da eğitim verisi yetersizliğinin
					göstergesidir — bkz. <code>creative:train-garment-detector</code>.
				</p>

				<div v-if="labelFrequencyEntries.length" class="sub-block">
					<div class="sub-title">Parça frekansı</div>
					<div class="tag-list">
						<Badge v-for="[label, count] in labelFrequencyEntries" :key="label" color="neutral" variant="tonal" :label="`${label} (${count})`" />
					</div>
				</div>
				<p v-else class="text-2xs text-muted">Bu pencerede hiç parça tespiti yok.</p>

				<div class="sub-block">
					<div class="sub-title">Kaynak dağılımı</div>
					<div class="tag-list">
						<Badge color="neutral" variant="tonal" :label="`Manuel: ${report.detection_summary.by_source?.manual ?? 0}`" />
						<Badge color="neutral" variant="tonal" :label="`Otomatik: ${report.detection_summary.by_source?.auto ?? 0}`" />
						<Badge color="warning" variant="tonal" :label="`AI önerisi (onaysız): ${report.detection_summary.by_source?.zeroshot ?? 0}`" />
						<Badge color="neutral" variant="tonal" :label="`Ort. güven: ${avgConfidenceLabel}`" />
					</div>
				</div>
			</div>
		</Card>
	</div>
</template>

<script setup>
import { Head } from '@inertiajs/vue3'
import { computed } from 'vue'
import { Percent, Shirt, User, Image as ImageIcon } from 'lucide-vue-next'
import AppLayout from '@/Layouts/AppLayout.vue'
import Breadcrumb from '@/Components/Breadcrumb.vue'
import PageHeader from '@/Components/PageHeader.vue'
import Card from '@/Components/Card.vue'
import Badge from '@/Components/Badge.vue'
import StatWidget from '@/Components/StatWidget.vue'
import ReviewReportSection from '../Components/ReviewReportSection.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
	file: { type: String, required: true },
	report: { type: Object, required: true },
})

// Breadcrumb'da ham dosya adı (ör. "2026-07-16.json") yerine sayfa başlığıyla
// tutarlı, okunur bir tarih gösterilir.
const reportLabel = computed(() => {
	if (!props.report.generated_at) return props.file
	return new Date(props.report.generated_at).toLocaleDateString('tr-TR', { dateStyle: 'medium' })
})

const subtitleText = computed(() => `${formatDate(props.report.generated_at)} — son ${props.report.window_days} gün`)

// AI önerisi rapor üretilirken bir kez yazılır (bkz. CreativeReviewReportCommand);
// bu alan olmayan eski raporlarda (özellik eklenmeden önce üretilmiş) kart hiç gösterilmez.
// Prompt "•" ile madde işareti istese de Gemini genelde markdown döner (ör. "*   metin",
// "`kod`", "**kalın**") — bunlar temizlenmezse kart içinde ham işaretler görünür.
const insightLines = computed(() => {
	const text = props.report.ai_insight
	if (!text) return []
	return text
		.split('\n')
		.map(l => l
			.replace(/^[•*-]\s+/, '')
			.replace(/^\d+[.)]\s+/, '')
			.replace(/\*\*(.+?)\*\*/g, '$1')
			.replace(/`([^`]+)`/g, '$1')
			.trim())
		.filter(Boolean)
})

function formatDate(iso) {
	if (!iso) return '—'
	return new Date(iso).toLocaleString('tr-TR', { dateStyle: 'medium', timeStyle: 'short' })
}

function formatRate(rate) {
	return rate === null || rate === undefined ? '—' : `%${Math.round(rate * 100)}`
}

function rateColor(rate) {
	if (rate === null || rate === undefined) return 'neutral'
	if (rate < 0.15) return 'success'
	if (rate < 0.30) return 'warning'
	return 'danger'
}

// zero_detection_rate için "iyi" tersine döner: DÜŞÜK sıfır-tespit oranı iyidir
// (rateColor'ın ret oranı yorumuyla aynı yön — küçük değer yeşil).
const labelFrequencyEntries = computed(() =>
	Object.entries(props.report.detection_summary?.label_frequency || {}).sort((a, b) => b[1] - a[1]),
)

const avgConfidenceLabel = computed(() => {
	const c = props.report.detection_summary?.avg_confidence
	return c !== null && c !== undefined ? `%${Math.round(c * 100)}` : '—'
})
</script>

<style scoped>
.kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 20px; }
.section-card { margin-bottom: 18px; }
.body-stack { display: flex; flex-direction: column; gap: 18px; }

.ai-card { border-color: color-mix(in srgb, var(--color-primary) 25%, transparent); }
.insight-list { display: flex; flex-direction: column; gap: 8px; }
.insight-item {
	position: relative;
	margin: 0;
	padding: 9px 14px 9px 30px;
	font-size: 13px;
	line-height: 1.5;
	color: var(--color-ink);
	background: color-mix(in srgb, var(--color-primary) 5%, transparent);
	border-radius: 8px;
}
.insight-item::before {
	content: '';
	position: absolute;
	left: 12px;
	top: 15px;
	width: 6px;
	height: 6px;
	border-radius: 50%;
	background: var(--color-primary);
}

.detection-hint { font-size: 12.5px; color: var(--color-muted); margin: 0; }
.detection-hint code { background: var(--color-canvas); padding: 1px 5px; border-radius: 4px; font-size: 11.5px; }

.sub-block { display: flex; flex-direction: column; gap: 8px; }
.sub-title { font-size: 12px; font-weight: 700; color: var(--color-muted); text-transform: uppercase; letter-spacing: .03em; }
.tag-list { display: flex; flex-wrap: wrap; gap: 6px; }
</style>
