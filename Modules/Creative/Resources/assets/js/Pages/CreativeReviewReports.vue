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

		<div v-if="reports.length" class="kpi-grid">
			<div class="kpi-card">
				<span class="kpi-label">Toplam Rapor</span>
				<span class="kpi-value">{{ reports.length }}</span>
				<span class="kpi-hint">Haftalık üretilen tüm raporlar</span>
			</div>
			<div class="kpi-card">
				<span class="kpi-label">Son Rapor Ret Oranı</span>
				<span class="kpi-value" :class="rateColorClass(latest.rejection_rate)">{{ formatRate(latest.rejection_rate) }}</span>
				<span class="kpi-hint">{{ latest.total_rejected }}/{{ latest.total_reviewed }} reddedildi</span>
			</div>
			<div class="kpi-card">
				<span class="kpi-label">Son Rapor Tarihi</span>
				<span class="kpi-value kpi-value-sm">{{ formatDate(latest.generated_at) }}</span>
				<span class="kpi-hint">Son {{ latest.window_days }} gün penceresi</span>
			</div>
		</div>

		<div class="card">
			<div class="card-header">
				<svg class="header-icon" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
					<path d="M3 3v18h18M8 17V10M13 17V6M18 17v-4" stroke-linecap="round" stroke-linejoin="round" />
				</svg>
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
						<span class="row-stat">{{ r.total_rejected }}/{{ r.total_reviewed }} reddedildi</span>
						<span class="rate-badge" :class="rateColorClass(r.rejection_rate)">{{ formatRate(r.rejection_rate) }}</span>
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
import { computed } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Breadcrumb from '@/Components/Breadcrumb.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
	reports: { type: Array, default: () => [] },
})

const latest = computed(() => props.reports[0] ?? {
	rejection_rate: null, total_rejected: 0, total_reviewed: 0, generated_at: null, window_days: null,
})

function formatDate(iso) {
	if (!iso) return '—'
	return new Date(iso).toLocaleString('tr-TR', { dateStyle: 'medium', timeStyle: 'short' })
}

function formatRate(rate) {
	return rate === null || rate === undefined ? 'Veri yok' : `%${Math.round(rate * 100)}`
}

function rateColorClass(rate) {
	if (rate === null || rate === undefined) return 'rate-neutral'
	if (rate < 0.15) return 'rate-good'
	if (rate < 0.30) return 'rate-warn'
	return 'rate-bad'
}
</script>

<style scoped>
.page-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 20px; gap: 16px; }
.page-title { font-size: 22px; font-weight: 700; color: #1a1a2e; line-height: 1.2; }
.page-subtitle { font-size: 13px; color: #888; margin-top: 4px; max-width: 640px; }
.page-subtitle code { background: #f5f5f8; border-radius: 4px; padding: 1px 5px; font-size: 12px; }

.kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 20px; }
.kpi-card { display: flex; flex-direction: column; gap: 6px; background: #fff; border: 1px solid #ebebf0; border-radius: 16px; padding: 18px; box-shadow: 0 1px 4px rgba(0,0,0,.04); }
.kpi-label { font-size: 12px; font-weight: 600; color: #888; text-transform: uppercase; letter-spacing: .04em; }
.kpi-value { font-size: 24px; font-weight: 800; color: #1a1a2e; }
.kpi-value-sm { font-size: 15px; font-weight: 700; }
.kpi-hint { font-size: 12px; color: #999; }

.card { background: #fff; border-radius: 16px; border: 1px solid #ebebf0; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,.04); margin-bottom: 18px; }
.card-header { padding: 14px 18px; display: flex; align-items: center; gap: 10px; border-bottom: 1px solid #f0f0f5; }
.header-icon { color: rgb(var(--color-primary)); flex-shrink: 0; }
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

.rate-badge { font-size: 11px; font-weight: 700; padding: 3px 9px; border-radius: 20px; color: #fff; white-space: nowrap; }
.rate-badge.rate-good { background: #16a34a; }
.rate-badge.rate-warn { background: #f59e0b; }
.rate-badge.rate-bad { background: #dc2626; }
.rate-badge.rate-neutral { background: #9ca3af; }

.kpi-value.rate-good { color: #16a34a; }
.kpi-value.rate-warn { color: #f59e0b; }
.kpi-value.rate-bad { color: #dc2626; }
.kpi-value.rate-neutral { color: #9ca3af; }

@media (max-width: 700px) {
	.page-header { flex-wrap: wrap; }
	.row { flex-wrap: wrap; }
	.row-date { min-width: 0; width: 100%; }
	.row-stat { margin-left: 0; }
}
</style>
