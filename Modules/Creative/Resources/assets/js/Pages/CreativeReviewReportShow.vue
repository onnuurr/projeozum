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

		<div class="page-header">
			<div>
				<h1 class="page-title">Ret Analiz Raporu</h1>
				<p class="page-subtitle">
					{{ formatDate(report.generated_at) }} — son {{ report.window_days }} gün
				</p>
			</div>
		</div>

		<div class="kpi-grid">
			<div class="kpi-card">
				<span class="kpi-label">Giydirme İncelendi</span>
				<span class="kpi-value">{{ report.tryon_results?.total_reviewed ?? 0 }}</span>
				<span class="kpi-hint">{{ report.tryon_results?.total_rejected ?? 0 }} reddedildi</span>
			</div>
			<div class="kpi-card">
				<span class="kpi-label">Giydirme Ret Oranı</span>
				<span class="kpi-value" :class="rateColorClass(report.tryon_results?.rejection_rate)">
					{{ formatRate(report.tryon_results?.rejection_rate) }}
				</span>
				<span class="kpi-hint">Manken: {{ formatRate(report.mannequins?.rejection_rate) }}</span>
			</div>
			<div class="kpi-card">
				<span class="kpi-label">Manken İncelendi</span>
				<span class="kpi-value">{{ report.mannequins?.total_reviewed ?? 0 }}</span>
				<span class="kpi-hint">{{ report.mannequins?.total_rejected ?? 0 }} reddedildi</span>
			</div>
			<div class="kpi-card">
				<span class="kpi-label">Creative Studio İncelendi</span>
				<span class="kpi-value">{{ report.creative_assets?.total_reviewed ?? 0 }}</span>
				<span class="kpi-hint">{{ report.creative_assets?.total_rejected ?? 0 }} reddedildi</span>
			</div>
		</div>

		<div v-if="insightLines.length" class="card ai-card">
			<div class="card-header">
				<svg class="header-icon" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
					<path d="M12 3l1.5 4.5L18 9l-4.5 1.5L12 15l-1.5-4.5L6 9l4.5-1.5L12 3z" stroke-linejoin="round" />
					<path d="M5 16l.8 2.2L8 19l-2.2.8L5 22l-.8-2.2L2 19l2.2-.8L5 16z" stroke-linejoin="round" />
				</svg>
				<h3>Ne Yapılabilir? (AI Önerisi)</h3>
			</div>
			<div class="card-body">
				<div class="insight-list">
					<p v-for="(line, i) in insightLines" :key="i" class="insight-item">{{ line }}</p>
				</div>
			</div>
		</div>

		<ReportSection title="Giydirme" icon="shirt" :summary="report.tryon_results" />
		<ReportSection title="Manken" icon="user" :summary="report.mannequins" />
		<ReportSection title="Creative Studio" icon="image" :summary="report.creative_assets" />

		<div v-if="report.detection_summary" class="card">
			<div class="card-header">
				<h3>Giysi Parça Tespiti</h3>
				<span class="rate-badge" :class="rateColorClass(report.detection_summary.zero_detection_rate)">
					{{ report.detection_summary.scans_with_zero_detections }}/{{ report.detection_summary.total_scans }} sıfır tespit · {{ formatRate(report.detection_summary.zero_detection_rate) }}
				</span>
			</div>
			<div class="card-body">
				<p class="detection-hint">
					Sıfır-tespitli oran, henüz eğitilmemiş bir model ya da eğitim verisi yetersizliğinin
					göstergesidir — bkz. <code>creative:train-garment-detector</code>.
				</p>
				<div v-if="labelFrequencyEntries.length" class="sub-block">
					<div class="sub-title">Parça frekansı</div>
					<div class="tag-list">
						<span v-for="[label, count] in labelFrequencyEntries" :key="label" class="tag-chip">{{ label }} ({{ count }})</span>
					</div>
				</div>
				<div v-else class="empty-block">Bu pencerede hiç parça tespiti yok.</div>
				<div class="sub-block">
					<div class="sub-title">Kaynak dağılımı</div>
					<div class="tag-list">
						<span class="tag-chip">Manuel: {{ report.detection_summary.by_source?.manual ?? 0 }}</span>
						<span class="tag-chip">Otomatik: {{ report.detection_summary.by_source?.auto ?? 0 }}</span>
						<span class="tag-chip zeroshot">AI önerisi (onaysız): {{ report.detection_summary.by_source?.zeroshot ?? 0 }}</span>
						<span class="tag-chip">Ort. güven: {{ report.detection_summary.avg_confidence !== null ? `%${Math.round(report.detection_summary.avg_confidence * 100)}` : '—' }}</span>
					</div>
				</div>
			</div>
		</div>
	</div>
</template>

<script setup>
import { Head } from '@inertiajs/vue3'
import { computed, h } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import Breadcrumb from '@/Components/Breadcrumb.vue'

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

function rateColorClass(rate) {
	if (rate === null || rate === undefined) return 'rate-neutral'
	if (rate < 0.15) return 'rate-good'
	if (rate < 0.30) return 'rate-warn'
	return 'rate-bad'
}

// zero_detection_rate için "iyi" tersine döner: DÜŞÜK sıfır-tespit oranı iyidir
// (rateColorClass'ın ret oranı yorumuyla aynı yön — küçük değer yeşil).
const labelFrequencyEntries = computed(() =>
	Object.entries(props.report.detection_summary?.label_frequency || {}).sort((a, b) => b[1] - a[1]),
)

// Kart başlıklarındaki küçük ikonlar — lucide "shirt" / "user" çizgileriyle aynı.
const SECTION_ICON_PATHS = {
	shirt: 'M20.38 3.46 16 2a4 4 0 0 1-8 0L3.62 3.46a2 2 0 0 0-1.34 2.23l.58 3.47a1 1 0 0 0 .99.84H6v10c0 1.1.9 2 2 2h8a2 2 0 0 0 2-2V10h2.15a1 1 0 0 0 .99-.84l.58-3.47a2 2 0 0 0-1.34-2.23z',
	user: 'M6 21v-2a4 4 0 0 1 4-4h4a4 4 0 0 1 4 4v2',
	image: 'M3 5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2zM8.5 10a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3zM21 15l-5-5-9 9',
}

function sectionIcon(name) {
	const children = [h('path', { d: SECTION_ICON_PATHS[name], 'stroke-linecap': 'round', 'stroke-linejoin': 'round' })]
	if (name === 'user') children.push(h('circle', { cx: 12, cy: 8, r: 4 }))

	return h('svg', {
		class: 'header-icon', width: 16, height: 16, fill: 'none',
		stroke: 'currentColor', 'stroke-width': 2, viewBox: '0 0 24 24',
	}, children)
}

const ReportSection = {
	props: {
		title: { type: String, required: true },
		icon: { type: String, default: null },
		summary: { type: Object, default: () => ({}) },
	},
	setup(p) {
		return () => {
			const s = p.summary || {}
			const tagEntries = Object.entries(s.tag_counts || {}).sort((a, b) => b[1] - a[1])
			const maxTagCount = tagEntries.length ? tagEntries[0][1] : 0
			const driverEntries = Object.entries(s.driver_breakdown || {})

			return h('div', { class: 'card' }, [
				h('div', { class: 'card-header' }, [
					p.icon ? sectionIcon(p.icon) : null,
					h('h3', p.title),
					h('span', { class: ['rate-badge', rateColorClass(s.rejection_rate)] },
						`${s.total_rejected ?? 0}/${s.total_reviewed ?? 0} · ${formatRate(s.rejection_rate)}`),
				]),
				h('div', { class: 'card-body' }, [
					tagEntries.length
						? h('div', { class: 'sub-block' }, [
							h('div', { class: 'sub-title' }, 'Ret etiketleri'),
							h('div', { class: 'tag-list' }, tagEntries.map(([tag, count]) =>
								h('span', {
									class: 'tag-chip',
									key: tag,
									style: count === maxTagCount ? { background: 'rgb(var(--color-primary) / .12)', color: 'rgb(var(--color-primary))' } : null,
								}, `${tag} (${count})`))),
						])
						: h('div', { class: 'empty-block' }, 'Ret etiketi yok.'),

					driverEntries.length
						? h('div', { class: 'sub-block' }, [
							h('div', { class: 'sub-title' }, 'AI sürücü kırılımı'),
							h('div', { class: 'driver-rows' }, driverEntries.map(([name, d]) => {
								const rate = d.total > 0 ? d.rejected / d.total : null
								return h('div', { class: 'driver-row', key: name }, [
									h('span', { class: 'driver-name' }, name),
									h('span', { class: 'driver-stat' }, `${d.total} toplam`),
									h('span', { class: ['rate-badge', rateColorClass(rate)] }, `${d.rejected} reddedildi`),
									h('div', { class: 'driver-models' },
										(d.models || []).length
											? d.models.map(m => h('span', { class: 'tag-chip', key: m }, m))
											: h('span', { class: 'driver-models-empty' }, 'model bilgisi yok')),
								])
							})),
						])
						: null,

					(s.sample_notes || []).length
						? h('div', { class: 'sub-block' }, [
							h('div', { class: 'sub-title' }, 'Örnek ret notları'),
							h('div', { class: 'note-cards' }, s.sample_notes.map((n, i) =>
								h('p', { class: 'note-card', key: i }, n))),
						])
						: null,
				]),
			])
		}
	},
}
</script>

<style>
/* Bilinçli olarak scoped değil: "Giydirme"/"Manken" kartları render fonksiyonuyla
   (h()) manuel oluşturulan ReportSection bileşeninden geliyor, bu düğümler bu SFC'nin
   derleyici tarafından eklenen data-v-* scope attribute'unu taşımıyor — dolayısıyla
   `scoped` kullanılırsa bu kartlardaki hiçbir kural eşleşmez (kartlar tamamen
   stilsiz/düz metin görünür). Bunun yerine sayfa kök sınıfı (.page-review-report-show)
   ile manuel namespace'liyoruz; bu, DOM'u hangi bileşenin oluşturduğundan bağımsız
   çalışır ve aynı isimdeki genel sınıfların (.card, .rate-badge, .tag-chip vb.)
   diğer sayfalara (ör. CreativeReviewReports.vue, Atelier/Patterns.vue) sızmasını önler. */
.page-review-report-show .page-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 20px; gap: 16px; }
.page-review-report-show .page-title { font-size: 22px; font-weight: 700; color: #1a1a2e; line-height: 1.2; }
.page-review-report-show .page-subtitle { font-size: 13px; color: #888; margin-top: 4px; }

.page-review-report-show .kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 20px; }
.page-review-report-show .kpi-card { display: flex; flex-direction: column; gap: 6px; background: #fff; border: 1px solid #ebebf0; border-radius: 16px; padding: 18px; box-shadow: 0 1px 4px rgba(0,0,0,.04); }
.page-review-report-show .kpi-label { font-size: 12px; font-weight: 600; color: #888; text-transform: uppercase; letter-spacing: .04em; }
.page-review-report-show .kpi-value { font-size: 24px; font-weight: 800; color: #1a1a2e; }
.page-review-report-show .kpi-hint { font-size: 12px; color: #999; }

.page-review-report-show .card { background: #fff; border-radius: 16px; border: 1px solid #ebebf0; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,.04); margin-bottom: 18px; }
.page-review-report-show .card-header { padding: 14px 18px; display: flex; align-items: center; gap: 12px; border-bottom: 1px solid #f0f0f5; }
.page-review-report-show .header-icon { color: rgb(var(--color-primary)); flex-shrink: 0; }
.page-review-report-show .card-header h3 { font-size: 15px; font-weight: 700; color: #1a1a2e; }
.page-review-report-show .card-header .hint { font-size: 12px; color: #aaa; margin-left: auto; }
.page-review-report-show .card-body { padding: 18px; display: flex; flex-direction: column; gap: 18px; }
.page-review-report-show .empty-block { text-align: center; color: #aaa; padding: 12px 0; font-style: italic; font-size: 13px; }

.page-review-report-show .ai-card { border-color: rgb(var(--color-primary) / .25); background: linear-gradient(180deg, rgb(var(--color-primary) / .04), #fff 60px); }

.page-review-report-show .insight-list { display: flex; flex-direction: column; gap: 8px; }
.page-review-report-show .insight-item {
	position: relative;
	margin: 0;
	padding: 9px 14px 9px 30px;
	font-size: 13px;
	line-height: 1.5;
	color: #1a1a2e;
	background: rgb(var(--color-primary) / .05);
	border-radius: 8px;
}
.page-review-report-show .insight-item::before {
	content: '';
	position: absolute;
	left: 12px;
	top: 15px;
	width: 6px;
	height: 6px;
	border-radius: 50%;
	background: rgb(var(--color-primary));
}

.page-review-report-show .rate-badge { font-size: 11px; font-weight: 700; padding: 3px 9px; border-radius: 20px; color: #fff; white-space: nowrap; margin-left: auto; }
.page-review-report-show .rate-badge.rate-good { background: #16a34a; }
.page-review-report-show .rate-badge.rate-warn { background: #f59e0b; }
.page-review-report-show .rate-badge.rate-bad { background: #dc2626; }
.page-review-report-show .rate-badge.rate-neutral { background: #9ca3af; }

.page-review-report-show .kpi-value.rate-good { color: #16a34a; }
.page-review-report-show .kpi-value.rate-warn { color: #f59e0b; }
.page-review-report-show .kpi-value.rate-bad { color: #dc2626; }
.page-review-report-show .kpi-value.rate-neutral { color: #9ca3af; }

.page-review-report-show .detection-hint { font-size: 12.5px; color: #999; margin: 0; }
.page-review-report-show .detection-hint code { background: #f5f5f8; padding: 1px 5px; border-radius: 4px; font-size: 11.5px; }

.page-review-report-show .sub-block { display: flex; flex-direction: column; gap: 8px; }
.page-review-report-show .sub-title { font-size: 12px; font-weight: 700; color: #555; text-transform: uppercase; letter-spacing: .03em; }
.page-review-report-show .tag-list { display: flex; flex-wrap: wrap; gap: 6px; }
.page-review-report-show .tag-chip { font-size: 12px; font-weight: 600; color: #555; background: #f5f5f8; border-radius: 999px; padding: 4px 10px; }
.page-review-report-show .tag-chip.zeroshot { background: #fef3c7; color: #b45309; }

.page-review-report-show .driver-rows { display: flex; flex-direction: column; gap: 6px; }
.page-review-report-show .driver-row { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; padding: 10px 14px; border: 1px solid #f0f0f5; border-radius: 10px; background: #fafafc; }
.page-review-report-show .driver-name { font-size: 12.5px; font-weight: 700; color: #1a1a2e; text-transform: capitalize; min-width: 80px; }
.page-review-report-show .driver-stat { font-size: 12px; color: #999; }
.page-review-report-show .driver-models { display: flex; flex-wrap: wrap; gap: 6px; width: 100%; margin-top: 2px; }
.page-review-report-show .driver-models-empty { font-size: 11.5px; color: #bbb; font-style: italic; }

.page-review-report-show .note-cards { display: flex; flex-direction: column; gap: 8px; }
.page-review-report-show .note-card {
	position: relative;
	margin: 0;
	padding: 10px 14px 10px 30px;
	font-size: 12.5px;
	line-height: 1.5;
	color: #555;
	font-style: italic;
	background: #fef2f2;
	border-left: 3px solid #fca5a5;
	border-radius: 0 8px 8px 0;
}
.page-review-report-show .note-card::before {
	content: '\201C';
	position: absolute;
	left: 9px;
	top: 4px;
	font-size: 20px;
	font-style: normal;
	font-weight: 800;
	color: #dc2626;
	line-height: 1;
}

@media (max-width: 700px) {
	.page-review-report-show .page-header { flex-wrap: wrap; }
	.page-review-report-show .card-header { flex-wrap: wrap; row-gap: 6px; }
	.page-review-report-show .rate-badge { margin-left: 0; }
}
</style>
