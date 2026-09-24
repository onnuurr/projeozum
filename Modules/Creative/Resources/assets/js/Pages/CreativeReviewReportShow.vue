<script setup>
import { computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { OzPageHeader, OzModuleTabs, OzStatTile, OzCard, OzPill, OzButton, OzAlert, OzIcon } from '@/Components/Oz'
import ReviewReportSection from '../Components/ReviewReportSection.vue'
import { rateTone, formatRate, entriesOf } from '../reviewReport.js'

// Tek ret analiz raporu (Oz kit): creative-review-reports/{file} JSON'u.
// ai_insight, alerts, detection_summary, color_audit_summary eski raporlarda olmayabilir → ilgili kart gizlenir.
defineOptions({ layout: AppLayout })

const props = defineProps({
	file: { type: String, required: true },
	report: { type: Object, required: true },
})

const r = computed(() => props.report ?? {})
const statTone = (rate) => (rateTone(rate) === 'neutral' ? 'info' : rateTone(rate))
const stats = computed(() => [
	{ key: 'tryon', label: 'Giydirme · incelenen', value: r.value.tryon_results?.total_reviewed ?? 0, icon: 'shirt', tone: 'info' },
	{ key: 'rate', label: 'Giydirme ret oranı', value: formatRate(r.value.tryon_results?.rejection_rate), icon: 'percent', tone: statTone(r.value.tryon_results?.rejection_rate) },
	{ key: 'mannequin', label: 'Manken · incelenen', value: r.value.mannequins?.total_reviewed ?? 0, icon: 'user', tone: 'info' },
	{ key: 'creative', label: 'Creative Studio · incelenen', value: r.value.creative_assets?.total_reviewed ?? 0, icon: 'sparkles', tone: 'info' },
])

const sections = computed(() => [
	{ key: 'tryon', title: 'Giydirme', icon: 'shirt', summary: r.value.tryon_results },
	{ key: 'mannequin', title: 'Manken', icon: 'user', summary: r.value.mannequins },
	{ key: 'creative', title: 'Creative Studio', icon: 'sparkles', summary: r.value.creative_assets },
].filter((s) => s.summary))

// AI önerisi: istem "•" ister ama Gemini çoğu zaman markdown döner ("*   metin", `kod`, **kalın**) → temizlenir
const insights = computed(() => (r.value.ai_insight ?? '')
	.split('\n')
	.map((l) => l.replace(/^\s*[•*-]\s+/, '').replace(/^\s*\d+[.)]\s+/, '').replace(/\*\*(.+?)\*\*/g, '$1').replace(/`([^`]+)`/g, '$1').trim())
	.filter(Boolean))

const alerts = computed(() => r.value.alerts ?? [])
const det = computed(() => r.value.detection_summary ?? null)
const labels = computed(() => entriesOf(det.value?.label_frequency).sort((a, b) => b[1] - a[1]))
const color = computed(() => r.value.color_audit_summary ?? null)

const pct = (v) => (v === null || v === undefined ? '—' : `%${Math.round(v * 100)}`)
const formatDate = (iso) => (iso ? new Date(iso).toLocaleString('tr-TR', { dateStyle: 'long', timeStyle: 'short' }) : '—')
const formatDay = (iso) => (iso ? new Date(iso).toLocaleDateString('tr-TR', { day: 'numeric', month: 'long' }) : '—')
</script>

<template>
	<Head :title="`Ret Analiz Raporu · ${formatDay(r.generated_at)}`" />

	<OzPageHeader :title="`Ret analiz raporu · ${formatDay(r.generated_at)}`">
		<template #subtitle>
			{{ formatDate(r.generated_at) }} · son {{ r.window_days ?? '—' }} gün<template v-if="r.window_since"> ({{ formatDay(r.window_since) }} – {{ formatDay(r.generated_at) }})</template>
		</template>
		<template #actions>
			<OzButton icon="chevron-left" @click="router.visit(route('creative.review-reports.index'))">Tüm raporlar</OzButton>
		</template>
	</OzPageHeader>
	<OzModuleTabs />

	<div class="stats">
		<OzStatTile v-for="s in stats" :key="s.key" :label="s.label" :value="s.value" :icon="s.icon" :tone="s.tone" />
	</div>

	<OzAlert v-for="(a, i) in alerts" :key="i" tone="danger" :title="`${a.subject_label} → ${a.label}: %${Math.round(a.rate * 100)}`" class="block">
		Eşik %{{ Math.round(a.threshold * 100) }} · {{ a.streak }} gündür üstünde
	</OzAlert>

	<OzCard v-if="insights.length" class="block ai">
		<template #title><span class="title"><OzIcon name="sparkles" :size="15" /> Ne yapılabilir?</span></template>
		<template #actions><OzPill tone="accent">AI önerisi</OzPill></template>
		<ul class="insights">
			<li v-for="(line, i) in insights" :key="i">{{ line }}</li>
		</ul>
	</OzCard>

	<div class="sections">
		<ReviewReportSection v-for="s in sections" :key="s.key" :title="s.title" :icon="s.icon" :summary="s.summary" />
	</div>

	<div class="sections two">
		<OzCard v-if="det" class="block">
			<template #title><span class="title"><OzIcon name="scissors" :size="15" /> Giysi parça tespiti</span></template>
			<template #actions>
				<OzPill :tone="det.total_scans ? rateTone(det.zero_detection_rate) : 'neutral'">
					{{ det.scans_with_zero_detections }}/{{ det.total_scans }} sıfır tespit · {{ pct(det.zero_detection_rate) }}
				</OzPill>
			</template>
			<div class="body">
				<p class="hint">Sıfır tespit oranı yüksekse model henüz eğitilmemiş ya da eğitim verisi yetersiz demektir — <code>creative:train-garment-detector</code>.</p>
				<dl class="kv">
					<div><dt>Manuel</dt><dd>{{ det.by_source?.manual ?? 0 }}</dd></div>
					<div><dt>Otomatik</dt><dd>{{ det.by_source?.auto ?? 0 }}</dd></div>
					<div><dt>AI önerisi (onaysız)</dt><dd :class="{ warn: det.by_source?.zeroshot }">{{ det.by_source?.zeroshot ?? 0 }}</dd></div>
					<div><dt>Ort. güven</dt><dd>{{ pct(det.avg_confidence) }}</dd></div>
				</dl>
				<div v-if="labels.length">
					<div class="label">Parça frekansı</div>
					<div class="chips">
						<span v-for="[label, count] in labels" :key="label" class="chip">{{ label }} <b>{{ count }}</b></span>
					</div>
				</div>
				<p v-else class="none">Bu pencerede parça tespiti yok.</p>
			</div>
		</OzCard>

		<OzCard v-if="color" class="block">
			<template #title><span class="title"><OzIcon name="palette" :size="15" /> Renk sadakati</span></template>
			<template #actions>
				<OzPill :tone="color.total_measured ? rateTone(color.over_warn_rate) : 'neutral'">
					ΔE &gt; {{ color.warn_delta_e }}: {{ color.over_warn_threshold }} · {{ pct(color.over_warn_rate) }}
				</OzPill>
			</template>
			<div class="body">
				<p class="hint">Giysi bölgesi giydirme öncesi/sonrası farkından bulunur. ΔE &lt; 5 gözle ayırt edilemez, &gt; 10 belirgin sapmadır.</p>
				<dl v-if="color.total_measured" class="kv">
					<div><dt>Ölçülen</dt><dd>{{ color.total_measured }}</dd></div>
					<div><dt>Güvenli ölçüm</dt><dd>{{ color.high_confidence }}</dd></div>
					<div><dt>Ort. ΔE</dt><dd :class="{ warn: color.avg_delta_e > color.warn_delta_e }">{{ color.avg_delta_e ?? '—' }}</dd></div>
					<div><dt>Renk kilidi</dt><dd>{{ color.locked_count }}</dd></div>
				</dl>
				<p v-else class="none">Bu pencerede renk ölçümü yok.</p>
			</div>
		</OzCard>
	</div>
</template>

<style scoped>
code { font-family: ui-monospace, monospace; font-size: .92em; padding: 1px 5px; border-radius: 5px; background: var(--oz-surface-2); border: 1px solid var(--oz-border-soft); }
.block { margin-bottom: 14px; }
.stats { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; margin-bottom: 14px; }
.title { display: inline-flex; align-items: center; gap: 6px; }
.title :deep(svg) { color: var(--oz-accent); }

.ai { border-color: rgba(var(--oz-accent-rgb), .3); }
.insights { margin: 0; padding: 0 16px 16px; list-style: none; display: flex; flex-direction: column; gap: 8px; }
.insights li { position: relative; padding: 9px 12px 9px 28px; border-radius: 10px; background: var(--oz-accent-soft); font-size: 13px; line-height: 1.55; color: var(--oz-ink-soft); }
.insights li::before { content: ''; position: absolute; left: 12px; top: 16px; width: 6px; height: 6px; border-radius: 50%; background: var(--oz-accent); }

.sections { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 14px; align-items: start; margin-bottom: 14px; }
.sections.two { grid-template-columns: repeat(2, minmax(0, 1fr)); }
.sections > .block { margin-bottom: 0; }
.body { padding: 0 16px 16px; display: flex; flex-direction: column; gap: 14px; }
.hint { margin: 0; font-size: 12px; line-height: 1.5; color: var(--oz-muted); }
.none { margin: 0; font-size: 12.5px; color: var(--oz-muted-2); }
.label { font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: var(--oz-muted); margin-bottom: 6px; }
.kv { margin: 0; display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 8px; }
.kv > div { padding: 8px 10px; border-radius: 10px; background: var(--oz-surface-2); border: 1px solid var(--oz-border-soft); }
dt { font-size: 10.5px; font-weight: 700; color: var(--oz-muted); }
dd { margin: 2px 0 0; font-size: 16px; font-weight: 800; color: var(--oz-ink-soft); font-variant-numeric: tabular-nums; }
dd.warn { color: var(--oz-warning); }
.chips { display: flex; flex-wrap: wrap; gap: 6px; }
.chip { font-size: 12px; padding: 3px 8px; border-radius: 999px; background: var(--oz-surface-2); border: 1px solid var(--oz-border-soft); color: var(--oz-ink-soft); }
.chip b { margin-left: 2px; color: var(--oz-accent); }

@media (max-width: 1180px) { .sections { grid-template-columns: minmax(0, 1fr); } .sections.two { grid-template-columns: minmax(0, 1fr); } }
@media (max-width: 1040px) { .stats { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
@media (max-width: 640px) { .kv { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
</style>
