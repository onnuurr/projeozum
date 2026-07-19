<template>
	<Head title="Kâr / Zarar" />
	<div class="financials">
		<h1 class="page-title">Kâr / Zarar (Son 30 Gün)</h1>

		<div class="summary-grid">
			<div class="kpi">
				<span class="label">Satış</span>
				<span class="value">{{ formatMoney(summary.sales_total) }}</span>
			</div>
			<div class="kpi">
				<span class="label">Gider</span>
				<span class="value loss">{{ formatMoney(summary.expenses_total) }}</span>
			</div>
			<div class="kpi">
				<span class="label">Bize Fatura</span>
				<span class="value loss">{{ formatMoney(summary.invoiced_total) }}</span>
			</div>
			<div class="kpi highlight">
				<span class="label">Net</span>
				<span class="value" :class="{ profit: summary.net > 0, loss: summary.net < 0 }">
					{{ formatMoney(summary.net) }}
				</span>
			</div>
		</div>

		<section class="card">
			<h2 class="card-title">Pazaryeri Bazında</h2>
			<canvas ref="byMpCanvas" height="120"></canvas>
		</section>

		<section class="card">
			<h2 class="card-title">12 Aylık Trend</h2>
			<canvas ref="byMonthCanvas" height="100"></canvas>
		</section>
	</div>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue'
import { Head } from '@inertiajs/vue3'
import { Chart, registerables } from 'chart.js'
import TenantPortalLayout from '@/Layouts/TenantPortalLayout.vue'

Chart.register(...registerables)

defineOptions({ layout: TenantPortalLayout })

const props = defineProps({
	tenant: { type: Object, required: true },
	summary: { type: Object, required: true },
	byMarketplace: { type: Array, default: () => [] },
	byMonth: { type: Array, default: () => [] },
})

const byMpCanvas = ref(null)
const byMonthCanvas = ref(null)
let chart1 = null
let chart2 = null

function formatMoney(v) {
	return new Intl.NumberFormat('tr-TR', { style: 'currency', currency: 'TRY', maximumFractionDigits: 0 }).format(Number(v ?? 0))
}

function renderCharts() {
	if (chart1) chart1.destroy()
	if (chart2) chart2.destroy()

	if (byMpCanvas.value) {
		chart1 = new Chart(byMpCanvas.value, {
			type: 'bar',
			data: {
				labels: props.byMarketplace.map(m => m.marketplace),
				datasets: [
					{ label: 'Gelir', data: props.byMarketplace.map(m => m.revenue), backgroundColor: '#4f46e5' },
					{ label: 'Net', data: props.byMarketplace.map(m => m.net), backgroundColor: '#10b981' },
				],
			},
			options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } },
		})
	}

	if (byMonthCanvas.value) {
		chart2 = new Chart(byMonthCanvas.value, {
			type: 'line',
			data: {
				labels: props.byMonth.map(m => m.month),
				datasets: [
					{ label: 'Gelir', data: props.byMonth.map(m => m.revenue), borderColor: '#4f46e5', tension: 0.3 },
					{ label: 'Net', data: props.byMonth.map(m => m.net), borderColor: '#10b981', tension: 0.3 },
				],
			},
			options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } },
		})
	}
}

onMounted(renderCharts)
watch([() => props.byMarketplace, () => props.byMonth], renderCharts, { deep: true })
</script>

<style scoped>
.page-title { font-size: 22px; font-weight: 700; color: rgb(var(--portal-ink)); margin-bottom: 16px; }
.summary-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 16px; }
.kpi { background: #fff; border: 1px solid rgb(var(--portal-border)); border-radius: 12px; padding: 14px 18px; display: flex; flex-direction: column; gap: 4px; }
.kpi.highlight { background: linear-gradient(135deg, rgb(var(--portal-accent-soft)), rgb(var(--portal-accent-soft-2))); border-color: rgb(var(--portal-accent-soft-border)); }
.label { font-size: 11px; color: rgb(var(--portal-text-muted)); text-transform: uppercase; letter-spacing: 0.04em; font-weight: 600; }
.value { font-size: 22px; font-weight: 700; font-family: 'SF Mono', Menlo, Consolas, monospace; color: rgb(var(--portal-ink)); }
.value.profit { color: rgb(var(--color-success)); }
.value.loss { color: rgb(var(--color-danger)); }
.card { background: #fff; border-radius: 12px; border: 1px solid rgb(var(--portal-border)); padding: 18px 20px; margin-bottom: 14px; }
.card-title { font-size: 13px; font-weight: 700; color: rgb(var(--portal-text-secondary)); text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 12px; }

@media (max-width: 640px) {
	.summary-grid { grid-template-columns: repeat(2, 1fr); }
}
</style>
