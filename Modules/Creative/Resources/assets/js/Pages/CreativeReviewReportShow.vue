<template>
	<Head title="Ret Analiz Raporu" />
	<div class="page-review-report-show">
		<Breadcrumb
			:items="[
				{ label: 'Ana Sayfa', to: '/workflow', icon: 'home' },
				{ label: 'Süper Admin' },
				{ label: 'Ret Analiz Raporları', to: '/creative/review-reports' },
				{ label: file },
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

		<ReportSection title="Giydirme (TryonResult)" :summary="report.tryon_results" />
		<ReportSection title="Manken" :summary="report.mannequins" />
	</div>
</template>

<script setup>
import { Head } from '@inertiajs/vue3'
import { h } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import Breadcrumb from '@/Components/Breadcrumb.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
	file: { type: String, required: true },
	report: { type: Object, required: true },
})

function formatDate(iso) {
	if (!iso) return '—'
	return new Date(iso).toLocaleString('tr-TR', { dateStyle: 'medium', timeStyle: 'short' })
}

const ReportSection = {
	props: {
		title: { type: String, required: true },
		summary: { type: Object, default: () => ({}) },
	},
	setup(p) {
		return () => {
			const s = p.summary || {}
			const tagEntries = Object.entries(s.tag_counts || {})
			const driverEntries = Object.entries(s.driver_breakdown || {})

			return h('div', { class: 'card' }, [
				h('div', { class: 'card-header' }, [
					h('h3', p.title),
					h('span', { class: 'hint' },
						`${s.total_rejected ?? 0}/${s.total_reviewed ?? 0} reddedildi` +
						(s.rejection_rate != null ? ` (%${Math.round(s.rejection_rate * 100)})` : '')),
				]),
				h('div', { class: 'card-body' }, [
					tagEntries.length
						? h('div', { class: 'sub-block' }, [
							h('div', { class: 'sub-title' }, 'Ret etiketleri'),
							h('div', { class: 'tag-list' }, tagEntries.map(([tag, count]) =>
								h('span', { class: 'tag-chip', key: tag }, `${tag} (${count})`))),
						])
						: h('div', { class: 'empty-block' }, 'Ret etiketi yok.'),

					driverEntries.length
						? h('div', { class: 'sub-block' }, [
							h('div', { class: 'sub-title' }, 'AI sürücü kırılımı'),
							h('table', { class: 'driver-table' }, [
								h('thead', h('tr', [
									h('th', 'Sürücü'), h('th', 'Toplam'), h('th', 'Reddedilen'), h('th', 'Modeller'),
								])),
								h('tbody', driverEntries.map(([name, d]) =>
									h('tr', { key: name }, [
										h('td', name),
										h('td', d.total),
										h('td', d.rejected),
										h('td', (d.models || []).join(', ') || '—'),
									]))),
							]),
						])
						: null,

					(s.sample_notes || []).length
						? h('div', { class: 'sub-block' }, [
							h('div', { class: 'sub-title' }, 'Örnek ret notları'),
							h('ul', { class: 'note-list' }, s.sample_notes.map((n, i) =>
								h('li', { key: i }, n))),
						])
						: null,
				]),
			])
		}
	},
}
</script>

<style scoped>
.page-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 20px; gap: 16px; }
.page-title { font-size: 22px; font-weight: 700; color: #1a1a2e; line-height: 1.2; }
.page-subtitle { font-size: 13px; color: #888; margin-top: 4px; }

.card { background: #fff; border-radius: 16px; border: 1px solid #ebebf0; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,.04); margin-bottom: 18px; }
.card-header { padding: 14px 18px; display: flex; align-items: center; gap: 12px; border-bottom: 1px solid #f0f0f5; }
.card-header h3 { font-size: 15px; font-weight: 700; color: #1a1a2e; }
.card-header .hint { font-size: 12px; color: #aaa; margin-left: auto; }
.card-body { padding: 18px; display: flex; flex-direction: column; gap: 18px; }
.empty-block { text-align: center; color: #aaa; padding: 12px 0; font-style: italic; font-size: 13px; }

.sub-block { display: flex; flex-direction: column; gap: 8px; }
.sub-title { font-size: 12px; font-weight: 700; color: #555; text-transform: uppercase; letter-spacing: .03em; }
.tag-list { display: flex; flex-wrap: wrap; gap: 6px; }
.tag-chip { font-size: 12px; font-weight: 600; color: #555; background: #f5f5f8; border-radius: 999px; padding: 4px 10px; }

.driver-table { width: 100%; border-collapse: collapse; font-size: 12.5px; }
.driver-table th { text-align: left; color: #999; font-weight: 600; padding: 6px 10px; border-bottom: 1px solid #f0f0f5; }
.driver-table td { padding: 8px 10px; border-bottom: 1px solid #f7f7fa; color: #1a1a2e; }

.note-list { display: flex; flex-direction: column; gap: 6px; padding-left: 18px; margin: 0; }
.note-list li { font-size: 12.5px; color: #555; }

@media (max-width: 700px) {
	.page-header { flex-wrap: wrap; }
	.driver-table { display: block; overflow-x: auto; }
}
</style>
