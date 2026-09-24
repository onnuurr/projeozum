<script setup>
import { ref, computed } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { OzPageHeader, OzModuleTabs, OzStatTile, OzCard, OzPill, OzSwitch, OzIcon, OzEmptyState } from '@/Components/Oz'
import { rateTone, formatRate } from '../reviewReport.js'

// Ret analiz raporları listesi (Oz kit). creative:review-report her gün son N günü özetleyen bir JSON üretir
// (storage/app/creative-review-reports/{tarih}.json). İncelemesi olmayan raporlar varsayılan olarak gizlenir.
defineOptions({ layout: AppLayout })

const props = defineProps({
	reports: { type: Array, default: () => [] },
})

const latest = computed(() => props.reports[0] ?? null)
const lastWithData = computed(() => props.reports.find((r) => r.total_reviewed > 0) ?? null)
const emptyCount = computed(() => props.reports.filter((r) => !r.total_reviewed).length)

const showEmpty = ref(false)
const visible = computed(() => (showEmpty.value ? props.reports : props.reports.filter((r) => r.total_reviewed > 0)))

const stats = computed(() => [
	{ key: 'count', label: 'Rapor', value: props.reports.length, icon: 'file', tone: 'accent' },
	{
		key: 'rate', label: 'Son dolu raporda ret', value: formatRate(lastWithData.value?.rejection_rate),
		icon: 'percent', tone: rateTone(lastWithData.value?.rejection_rate) === 'neutral' ? 'info' : rateTone(lastWithData.value?.rejection_rate),
	},
	{
		key: 'reviewed', label: 'Son rapor · incelenen', value: latest.value?.total_reviewed ?? 0,
		icon: 'shirt', tone: latest.value?.total_reviewed ? 'info' : 'warning',
	},
	{ key: 'date', label: 'Son rapor', value: latest.value ? dayText(latest.value.generated_at) : '—', icon: 'calendar', tone: 'info' },
])

function dayText(iso) {
	if (!iso) return '—'
	const d = new Date(iso)
	const diff = Math.round((new Date().setHours(0, 0, 0, 0) - new Date(d).setHours(0, 0, 0, 0)) / 86400000)
	return diff === 0 ? 'Bugün' : diff === 1 ? 'Dün' : d.toLocaleDateString('tr-TR', { day: 'numeric', month: 'short' })
}
const formatDate = (iso) => (iso ? new Date(iso).toLocaleDateString('tr-TR', { day: 'numeric', month: 'long', year: 'numeric', weekday: 'short' }) : '—')
const formatTime = (iso) => (iso ? new Date(iso).toLocaleTimeString('tr-TR', { hour: '2-digit', minute: '2-digit' }) : '')
</script>

<template>
	<Head title="Ret Analiz Raporları" />

	<OzPageHeader title="Ret analiz raporları">
		<template #subtitle>
			<code>creative:review-report</code> her gün, reddedilen giydirme / manken görsellerini etiket ve AI sürücü kırılımıyla özetler
		</template>
	</OzPageHeader>
	<OzModuleTabs />

	<div class="stats">
		<OzStatTile v-for="s in stats" :key="s.key" :label="s.label" :value="s.value" :icon="s.icon" :tone="s.tone" />
	</div>

	<OzCard title="Raporlar" :meta="`${visible.length} / ${reports.length} rapor`" class="block">
		<template v-if="emptyCount" #actions>
			<label class="toggle">
				<OzSwitch v-model="showEmpty" aria-label="Boş raporları göster" />
				<span>Boş raporları göster ({{ emptyCount }})</span>
			</label>
		</template>

		<OzEmptyState
			v-if="!reports.length"
			icon="file"
			title="Henüz rapor üretilmemiş"
			description="Sunucuda php artisan creative:review-report ile üretilir; her gece otomatik çalışır."
			compact
		/>
		<OzEmptyState
			v-else-if="!visible.length"
			icon="success"
			title="İncelemesi olan rapor yok"
			:description="`${emptyCount} raporun penceresinde hiç inceleme yapılmamış.`"
			compact
		/>

		<div v-else class="rows">
			<Link
				v-for="r in visible"
				:key="r.file"
				:href="route('creative.review-reports.show', r.file)"
				class="row"
				:class="{ empty: !r.total_reviewed }"
			>
				<span class="ico" :class="`tone-${r.total_reviewed ? rateTone(r.rejection_rate) : 'neutral'}`"><OzIcon name="file" :size="16" /></span>
				<span class="when">
					<b>{{ formatDate(r.generated_at) }}</b>
					<span>{{ formatTime(r.generated_at) }} · son {{ r.window_days ?? '—' }} gün</span>
				</span>
				<span class="counts">
					<template v-if="r.total_reviewed">
						<b>{{ r.total_rejected }}</b> / {{ r.total_reviewed }} reddedildi
						<span class="bar" aria-hidden="true"><span :class="`tone-${rateTone(r.rejection_rate)}`" :style="{ width: `${Math.round((r.rejection_rate ?? 0) * 100)}%` }" /></span>
					</template>
					<span v-else class="muted">İnceleme yok</span>
				</span>
				<OzPill :tone="r.total_reviewed ? rateTone(r.rejection_rate) : 'neutral'" class="rate">{{ r.total_reviewed ? formatRate(r.rejection_rate) : '—' }}</OzPill>
				<OzIcon name="chevron-right" :size="15" class="chev" />
			</Link>
		</div>
	</OzCard>
</template>

<style scoped>
code { font-family: ui-monospace, monospace; font-size: .92em; padding: 1px 5px; border-radius: 5px; background: var(--oz-surface-2); border: 1px solid var(--oz-border-soft); }
.block { margin-bottom: 14px; }
.stats { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; margin-bottom: 14px; }
.toggle { display: inline-flex; align-items: center; gap: 8px; font-size: 12px; color: var(--oz-muted); cursor: pointer; }

.rows { display: flex; flex-direction: column; gap: 8px; padding: 0 16px 16px; }
.row { display: flex; align-items: center; gap: 12px; padding: 10px 12px; border: 1px solid var(--oz-border-soft); border-radius: 12px; background: var(--oz-surface); color: inherit; text-decoration: none; transition: background .12s, border-color .12s; }
.row:hover { background: var(--oz-surface-2); border-color: var(--oz-border); }
.row:focus-visible { outline: 2px solid var(--oz-accent); outline-offset: -2px; }
.row.empty { opacity: .7; }
.ico { width: 32px; height: 32px; border-radius: 9px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; }
.tone-success { background: var(--oz-success-soft); color: var(--oz-success); }
.tone-warning { background: var(--oz-warning-soft); color: var(--oz-warning); }
.tone-danger { background: var(--oz-danger-soft); color: var(--oz-danger); }
.tone-neutral { background: var(--oz-surface-2); color: var(--oz-muted); }
.when { display: flex; flex-direction: column; gap: 1px; width: 250px; flex-shrink: 0; }
.when b { font-size: 13px; color: var(--oz-ink-soft); }
.when span { font-size: 11.5px; color: var(--oz-muted); }
.counts { flex: 1; min-width: 0; display: flex; align-items: center; gap: 6px; font-size: 12.5px; color: var(--oz-muted); font-variant-numeric: tabular-nums; }
.counts b { color: var(--oz-ink-soft); }
.bar { flex: 0 1 160px; height: 6px; margin-left: 8px; border-radius: 3px; background: var(--oz-surface-2); overflow: hidden; }
.bar > span { display: block; height: 100%; border-radius: 3px; }
.bar .tone-success { background: var(--oz-success); }
.bar .tone-warning { background: var(--oz-warning); }
.bar .tone-danger { background: var(--oz-danger); }
.muted { color: var(--oz-muted-2); }
.rate { flex-shrink: 0; }
.chev { color: var(--oz-muted); flex-shrink: 0; }

@media (max-width: 1040px) { .stats { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
@media (max-width: 640px) {
	.rows { padding: 0 10px 12px; }
	.row { flex-wrap: wrap; row-gap: 6px; }
	.when { flex: 1; width: auto; }
	.counts { order: 5; flex-basis: 100%; padding-left: 44px; }
	.bar { flex-basis: 90px; }
	.toggle span { display: none; }
}
</style>
