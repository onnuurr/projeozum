<template>
	<Card :title="title" class="section-card">
		<template #actions>
			<Badge
				:color="rateColor(summary.rejection_rate)"
				:label="`${summary.total_rejected ?? 0}/${summary.total_reviewed ?? 0} · ${formatRate(summary.rejection_rate)}`"
			/>
		</template>

		<div class="body-stack">
			<div v-if="tagEntries.length" class="sub-block">
				<div class="sub-title">Ret etiketleri</div>
				<div class="tag-list">
					<Badge
						v-for="[tag, count] in tagEntries"
						:key="tag"
						:color="count === maxTagCount ? 'primary' : 'neutral'"
						variant="tonal"
						:label="`${tag} (${count})`"
					/>
				</div>
			</div>
			<p v-else class="text-2xs text-muted">Ret etiketi yok.</p>

			<div v-if="driverEntries.length" class="sub-block">
				<div class="sub-title">AI sürücü kırılımı</div>
				<div class="driver-rows">
					<div v-for="[name, d] in driverEntries" :key="name" class="driver-row">
						<span class="driver-name">{{ name }}</span>
						<span class="driver-stat">{{ d.total }} toplam</span>
						<Badge :color="rateColor(d.total > 0 ? d.rejected / d.total : null)" :label="`${d.rejected} reddedildi`" />
						<div class="driver-models">
							<Badge v-for="m in (d.models || [])" :key="m" color="neutral" variant="tonal" :label="m" />
							<span v-if="!(d.models || []).length" class="driver-models-empty">model bilgisi yok</span>
						</div>
					</div>
				</div>
			</div>

			<div v-if="(summary.sample_notes || []).length" class="sub-block">
				<div class="sub-title">Örnek ret notları</div>
				<div class="note-cards">
					<p v-for="(n, i) in summary.sample_notes" :key="i" class="note-card">{{ n }}</p>
				</div>
			</div>
		</div>
	</Card>
</template>

<script setup>
import { computed } from 'vue'
import Card from '@/Components/Card.vue'
import Badge from '@/Components/Badge.vue'

const props = defineProps({
	title: { type: String, required: true },
	summary: { type: Object, default: () => ({}) },
})

const tagEntries = computed(() => Object.entries(props.summary.tag_counts || {}).sort((a, b) => b[1] - a[1]))
const maxTagCount = computed(() => (tagEntries.value.length ? tagEntries.value[0][1] : 0))
const driverEntries = computed(() => Object.entries(props.summary.driver_breakdown || {}))

function formatRate(rate) {
	return rate === null || rate === undefined ? '—' : `%${Math.round(rate * 100)}`
}

function rateColor(rate) {
	if (rate === null || rate === undefined) return 'neutral'
	if (rate < 0.15) return 'success'
	if (rate < 0.30) return 'warning'
	return 'danger'
}
</script>

<style scoped>
.section-card { margin-bottom: 18px; }
.body-stack { display: flex; flex-direction: column; gap: 18px; }
.sub-block { display: flex; flex-direction: column; gap: 8px; }
.sub-title { font-size: 12px; font-weight: 700; color: var(--color-muted); text-transform: uppercase; letter-spacing: .03em; }
.tag-list { display: flex; flex-wrap: wrap; gap: 6px; }

.driver-rows { display: flex; flex-direction: column; gap: 6px; }
.driver-row { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; padding: 10px 14px; border: 1px solid var(--color-outline-variant); border-radius: 10px; background: var(--color-surface-container-low); }
.driver-name { font-size: 12.5px; font-weight: 700; color: var(--color-ink); text-transform: capitalize; min-width: 80px; }
.driver-stat { font-size: 12px; color: var(--color-muted); }
.driver-models { display: flex; flex-wrap: wrap; gap: 6px; width: 100%; margin-top: 2px; }
.driver-models-empty { font-size: 11.5px; color: var(--color-muted); font-style: italic; }

.note-cards { display: flex; flex-direction: column; gap: 8px; }
.note-card {
	position: relative;
	margin: 0;
	padding: 10px 14px 10px 30px;
	font-size: 12.5px;
	line-height: 1.5;
	color: var(--color-muted);
	font-style: italic;
	background: color-mix(in srgb, var(--color-danger) 5%, transparent);
	border-left: 3px solid color-mix(in srgb, var(--color-danger) 40%, transparent);
	border-radius: 0 8px 8px 0;
}
.note-card::before {
	content: '\201C';
	position: absolute;
	left: 9px;
	top: 4px;
	font-size: 20px;
	font-style: normal;
	font-weight: 800;
	color: var(--color-danger);
	line-height: 1;
}
</style>
