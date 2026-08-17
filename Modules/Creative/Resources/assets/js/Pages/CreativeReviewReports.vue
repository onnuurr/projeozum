<template>
	<Head title="Ret Analiz Raporları" />
	<div class="page-review-reports">
		<Breadcrumb
			:items="[
				{ label: 'Ana Sayfa', to: '/workflow', icon: 'home' },
				{ label: 'Süper Admin' },
				{ label: 'Ret Analiz Raporları' },
			]"
		/>

		<PageHeader title="Ret Analiz Raporları">
			<template #subtitle>
				Haftalık <code>creative:review-report</code> komutunun ürettiği, reddedilen
				manken/giydirme görsellerinin etiket ve AI sürücü kırılımındaki özetleri.
			</template>
		</PageHeader>

		<div v-if="reports.length" class="kpi-grid">
			<StatWidget :icon="ClipboardList" :value="reports.length" title="Toplam Rapor" color="primary" />
			<StatWidget :icon="Percent" :value="formatRate(latest.rejection_rate)" title="Son Rapor Ret Oranı" :color="rateColor(latest.rejection_rate)" />
			<StatWidget :icon="CalendarClock" :value="formatDate(latest.generated_at)" title="Son Rapor Tarihi" color="neutral" />
		</div>

		<ListWidget
			title="Raporlar"
			:subtitle="`${reports.length} rapor`"
			:items="listItems"
			empty-text="Henüz üretilmiş rapor yok. 'php artisan creative:review-report' ile üretilir."
			clickable
			@item-click="(item) => router.visit(`/creative/review-reports/${item.file}`)"
			@item-action="(item) => router.visit(`/creative/review-reports/${item.file}`)"
		>
			<template #item-action>
				<ChevronRight :size="14" />
			</template>
		</ListWidget>
	</div>
</template>

<script setup>
import { computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import { ClipboardList, Percent, CalendarClock, FileBarChart2, ChevronRight } from 'lucide-vue-next'
import AppLayout from '@/Layouts/AppLayout.vue'
import Breadcrumb from '@/Components/Breadcrumb.vue'
import PageHeader from '@/Components/PageHeader.vue'
import StatWidget from '@/Components/StatWidget.vue'
import ListWidget from '@/Components/ListWidget.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
	reports: { type: Array, default: () => [] },
})

const latest = computed(() => props.reports[0] ?? {
	rejection_rate: null, total_rejected: 0, total_reviewed: 0, generated_at: null, window_days: null,
})

const listItems = computed(() => props.reports.map((r) => ({
	id: r.file,
	file: r.file,
	title: formatDate(r.generated_at),
	subtitle: `Son ${r.window_days} gün · ${r.total_rejected}/${r.total_reviewed} reddedildi`,
	badge: formatRate(r.rejection_rate),
	badgeColor: rateColor(r.rejection_rate),
	icon: FileBarChart2,
})))

function formatDate(iso) {
	if (!iso) return '—'
	return new Date(iso).toLocaleString('tr-TR', { dateStyle: 'medium', timeStyle: 'short' })
}

function formatRate(rate) {
	return rate === null || rate === undefined ? 'Veri yok' : `%${Math.round(rate * 100)}`
}

function rateColor(rate) {
	if (rate === null || rate === undefined) return 'neutral'
	if (rate < 0.15) return 'success'
	if (rate < 0.30) return 'warning'
	return 'danger'
}
</script>

<style scoped>
.kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 20px; }
</style>
