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

		<div class="page-header">
			<div>
				<h1 class="page-title">Ret Analiz Raporları</h1>
				<p class="page-subtitle">
					Haftalık <code>creative:review-report</code> komutunun ürettiği, reddedilen
					manken/giydirme görsellerinin etiket ve AI sürücü kırılımındaki özetleri.
				</p>
			</div>
		</div>

		<div class="card">
			<div class="card-header">
				<h3>Raporlar</h3>
				<span class="hint">{{ reports.length }} rapor</span>
			</div>
			<div class="card-body">
				<div v-if="reports.length === 0" class="empty-block">
					Henüz üretilmiş rapor yok. <code>php artisan creative:review-report</code> ile üretilir.
				</div>
				<div v-else class="rows">
					<Link
						v-for="r in reports"
						:key="r.file"
						:href="`/creative/review-reports/${r.file}`"
						class="row"
					>
						<span class="row-date">{{ formatDate(r.generated_at) }}</span>
						<span class="row-window">Son {{ r.window_days }} gün</span>
						<span class="row-stat">
							{{ r.total_rejected }}/{{ r.total_reviewed }} reddedildi
							<template v-if="r.rejection_rate !== null">(%{{ Math.round(r.rejection_rate * 100) }})</template>
						</span>
						<span class="row-arrow">
							<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
								<path d="M9 6l6 6-6 6" />
							</svg>
						</span>
					</Link>
				</div>
			</div>
		</div>
	</div>
</template>

<script setup>
import { Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Breadcrumb from '@/Components/Breadcrumb.vue'

defineOptions({ layout: AppLayout })

defineProps({
	reports: { type: Array, default: () => [] },
})

function formatDate(iso) {
	if (!iso) return '—'
	return new Date(iso).toLocaleString('tr-TR', { dateStyle: 'medium', timeStyle: 'short' })
}
</script>

<style scoped>
.page-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 20px; gap: 16px; }
.page-title { font-size: 22px; font-weight: 700; color: #1a1a2e; line-height: 1.2; }
.page-subtitle { font-size: 13px; color: #888; margin-top: 4px; max-width: 640px; }
.page-subtitle code { background: #f5f5f8; border-radius: 4px; padding: 1px 5px; font-size: 12px; }

.card { background: #fff; border-radius: 16px; border: 1px solid #ebebf0; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,.04); margin-bottom: 18px; }
.card-header { padding: 14px 18px; display: flex; align-items: center; gap: 12px; border-bottom: 1px solid #f0f0f5; }
.card-header h3 { font-size: 15px; font-weight: 700; color: #1a1a2e; }
.card-header .hint { font-size: 12px; color: #aaa; margin-left: auto; }
.card-body { padding: 18px; }
.empty-block { text-align: center; color: #aaa; padding: 28px 0; font-style: italic; font-size: 13px; }
.empty-block code { background: #f5f5f8; border-radius: 4px; padding: 1px 5px; font-size: 12px; font-style: normal; }

.rows { display: flex; flex-direction: column; gap: 6px; }
.row { display: flex; align-items: center; gap: 16px; padding: 12px 14px; border: 1px solid #f0f0f5; border-radius: 10px; background: #fafafc; text-decoration: none; transition: background .15s, border-color .15s; }
.row:hover { background: #f5f5fa; border-color: #e5e5f0; }
.row-date { font-size: 13px; font-weight: 600; color: #1a1a2e; min-width: 160px; }
.row-window { font-size: 12px; color: #999; }
.row-stat { font-size: 12.5px; color: #555; font-weight: 600; margin-left: auto; }
.row-arrow { display: flex; color: #bbb; }

@media (max-width: 700px) {
	.page-header { flex-wrap: wrap; }
	.row { flex-wrap: wrap; }
	.row-date { min-width: 0; width: 100%; }
	.row-stat { margin-left: 0; }
}
</style>
