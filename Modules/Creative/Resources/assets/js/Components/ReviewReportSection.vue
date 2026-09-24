<script setup>
import { computed } from 'vue'
import { OzCard, OzPill, OzIcon } from '@/Components/Oz'
import { rateTone, formatRate, entriesOf } from '../reviewReport.js'

// Ret raporunun tek aşaması (giydirme / manken / creative studio): ret etiketleri, AI sürücü kırılımı, örnek notlar.
const props = defineProps({
	title: { type: String, required: true },
	icon: { type: String, default: 'layers' },
	summary: { type: Object, default: () => ({}) },
})

const reviewed = computed(() => props.summary?.total_reviewed ?? 0)
const tags = computed(() => entriesOf(props.summary?.tag_counts).sort((a, b) => b[1] - a[1]))
const maxTag = computed(() => tags.value[0]?.[1] ?? 0)
const drivers = computed(() => entriesOf(props.summary?.driver_breakdown))
const notes = computed(() => props.summary?.sample_notes ?? [])
</script>

<template>
	<OzCard class="section">
		<template #title>
			<span class="title"><OzIcon :name="icon" :size="15" /> {{ title }}</span>
		</template>
		<template #actions>
			<OzPill :tone="reviewed ? rateTone(summary.rejection_rate) : 'neutral'">
				{{ summary.total_rejected ?? 0 }}/{{ reviewed }} · {{ formatRate(summary.rejection_rate) }}
			</OzPill>
		</template>

		<div class="body">
			<p v-if="!reviewed" class="none">Bu pencerede inceleme yapılmamış.</p>
			<template v-else>
				<div v-if="tags.length" class="block">
					<div class="label">Ret etiketleri</div>
					<div v-for="[tag, count] in tags" :key="tag" class="tag">
						<span class="tag-name">{{ tag }}</span>
						<span class="tag-bar"><span :class="{ top: count === maxTag }" :style="{ width: `${(count / maxTag) * 100}%` }" /></span>
						<b>{{ count }}</b>
					</div>
				</div>
				<p v-else class="none">Ret etiketi girilmemiş.</p>

				<div v-if="drivers.length" class="block">
					<div class="label">AI sürücü kırılımı</div>
					<div v-for="[name, d] in drivers" :key="name" class="driver">
						<div class="driver-head">
							<b>{{ name }}</b>
							<span class="driver-count">{{ d.rejected }}/{{ d.total }} reddedildi</span>
							<OzPill :tone="rateTone(d.total ? d.rejected / d.total : null)">{{ formatRate(d.total ? d.rejected / d.total : null) }}</OzPill>
						</div>
						<div class="models">
							<code v-for="m in d.models || []" :key="m">{{ m }}</code>
							<span v-if="!(d.models || []).length" class="none">model bilgisi yok</span>
						</div>
					</div>
				</div>

				<div v-if="notes.length" class="block">
					<div class="label">Örnek ret notları</div>
					<blockquote v-for="(n, i) in notes" :key="i">{{ n }}</blockquote>
				</div>
			</template>
		</div>
	</OzCard>
</template>

<style scoped>
.title { display: inline-flex; align-items: center; gap: 6px; }
.title :deep(svg) { color: var(--oz-accent); }
.body { padding: 0 16px 16px; display: flex; flex-direction: column; gap: 16px; }
.block { display: flex; flex-direction: column; gap: 6px; }
.label { font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: var(--oz-muted); }
.none { margin: 0; font-size: 12.5px; color: var(--oz-muted-2); }

.tag { display: grid; grid-template-columns: minmax(0, 140px) 1fr 28px; align-items: center; gap: 10px; font-size: 12.5px; }
.tag-name { color: var(--oz-ink-soft); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.tag-bar { height: 8px; border-radius: 4px; background: var(--oz-surface-2); overflow: hidden; }
.tag-bar > span { display: block; height: 100%; border-radius: 4px; background: var(--oz-border-strong); }
.tag-bar > span.top { background: var(--oz-accent); }
.tag b { text-align: right; color: var(--oz-ink-soft); font-variant-numeric: tabular-nums; }

.driver { padding: 10px 12px; border: 1px solid var(--oz-border-soft); border-radius: 10px; background: var(--oz-surface-2); display: flex; flex-direction: column; gap: 6px; }
.driver-head { display: flex; align-items: center; gap: 10px; font-size: 12.5px; }
.driver-head b { text-transform: capitalize; color: var(--oz-ink-soft); }
.driver-count { flex: 1; color: var(--oz-muted); }
.models { display: flex; flex-wrap: wrap; gap: 4px; }
.models code { font-family: ui-monospace, monospace; font-size: 11px; padding: 2px 6px; border-radius: 6px; background: var(--oz-surface); border: 1px solid var(--oz-border-soft); color: var(--oz-muted); }

blockquote { margin: 0; padding: 8px 12px; border-left: 3px solid var(--oz-danger); border-radius: 0 8px 8px 0; background: var(--oz-danger-soft); font-size: 12.5px; line-height: 1.5; color: var(--oz-ink-soft); }
</style>
