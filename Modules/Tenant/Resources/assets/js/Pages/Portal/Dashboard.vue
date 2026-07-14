<template>
	<Head :title="`${tenant.name} · Portal`" />
	<div class="portal-dashboard">
		<header class="page-header">
			<div>
				<h1 class="page-title">Hoş geldiniz, {{ tenant.name }}</h1>
				<p class="page-subtitle">
					<span class="mono">{{ tenant.code }}</span>
					· Bayi portalı
				</p>
			</div>
		</header>

		<section class="card">
			<h2 class="card-title">Kredi Özeti</h2>
			<div class="credit-grid">
				<div class="credit-cell">
					<span class="credit-label">Limit</span>
					<span class="credit-value">{{ formatMoney(snapshot.credit_limit) }}</span>
				</div>
				<div class="credit-cell">
					<span class="credit-label">Mevcut Borç</span>
					<span class="credit-value">{{ formatMoney(snapshot.current_balance) }}</span>
				</div>
				<div class="credit-cell highlight">
					<span class="credit-label">Kullanılabilir</span>
					<span class="credit-value">{{ formatMoney(snapshot.available_credit) }}</span>
				</div>
			</div>
		</section>

		<section class="card">
			<h2 class="card-title">Son 12 Ay · Alım Trendi</h2>
			<canvas ref="trendCanvas" height="80"></canvas>
		</section>

		<div class="two-col">
			<section class="card">
				<h2 class="card-title">En Çok Aldığım Ürünler (Son 6 Ay)</h2>
				<div class="table-scroll" v-if="topProducts.length">
					<table class="data-table">
						<thead><tr><th>Ürün</th><th>Adet</th><th>Tutar</th></tr></thead>
						<tbody>
							<tr v-for="p in topProducts" :key="p.product_id ?? p.product_name">
								<td>{{ p.product_name }}</td>
								<td class="mono">{{ p.total_qty }}</td>
								<td class="mono">{{ formatMoney(p.total_revenue) }}</td>
							</tr>
						</tbody>
					</table>
				</div>
				<p v-else class="empty">Henüz sipariş yok.</p>
			</section>

			<section class="card">
				<h2 class="card-title">Son Faturalar</h2>
				<ul class="invoice-list" v-if="recentInvoices.length">
					<li v-for="inv in recentInvoices" :key="inv.id">
						<Link :href="`/invoices/${inv.id}`" class="invoice-link">
							<span class="invoice-amount">{{ formatMoney(inv.amount) }}</span>
							<span :class="['invoice-status', inv.status]">{{ inv.status }}</span>
							<span class="invoice-date">{{ inv.due_date ?? '—' }}</span>
						</Link>
					</li>
				</ul>
				<p v-else class="empty">Henüz fatura yok.</p>
			</section>
		</div>
	</div>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import { Chart, registerables } from 'chart.js'
import TenantPortalLayout from '@/Layouts/TenantPortalLayout.vue'

Chart.register(...registerables)

defineOptions({ layout: TenantPortalLayout })

const props = defineProps({
	tenant: { type: Object, required: true },
	snapshot: { type: Object, required: true },
	topProducts: { type: Array, default: () => [] },
	monthlyTrend: { type: Array, default: () => [] },
	recentInvoices: { type: Array, default: () => [] },
})

const trendCanvas = ref(null)
let chartInstance = null

function formatMoney(v) {
	return new Intl.NumberFormat('tr-TR', { style: 'currency', currency: 'TRY', maximumFractionDigits: 0 }).format(Number(v ?? 0))
}

function renderChart() {
	if (!trendCanvas.value) return
	if (chartInstance) chartInstance.destroy()
	chartInstance = new Chart(trendCanvas.value, {
		type: 'bar',
		data: {
			labels: props.monthlyTrend.map(m => m.month),
			datasets: [{
				label: 'Toplam (₺)',
				data: props.monthlyTrend.map(m => m.total),
				backgroundColor: 'rgba(67, 56, 202, 0.8)',
			}],
		},
		options: {
			responsive: true,
			maintainAspectRatio: false,
			plugins: { legend: { display: false } },
			scales: { y: { beginAtZero: true } },
		},
	})
}

onMounted(renderChart)
watch(() => props.monthlyTrend, renderChart, { deep: true })
</script>

<style scoped>
.portal-dashboard { display: flex; flex-direction: column; gap: 18px; }
.page-header { display: flex; align-items: flex-start; justify-content: space-between; }
.page-title { font-size: 22px; font-weight: 700; color: #1a1a2e; }
.page-subtitle { font-size: 13px; color: #888; margin-top: 4px; }
.mono { font-family: 'SF Mono', Menlo, Consolas, monospace; font-size: 12px; }
.card { background: #fff; border-radius: 16px; padding: 18px 20px; border: 1px solid #ebebf0; box-shadow: 0 1px 4px rgba(0,0,0,.04); }
.card-title { font-size: 14px; font-weight: 700; color: #1a1a2e; margin-bottom: 12px; }
.credit-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; }
.credit-cell { background: #f7f7fb; border: 1px solid #ebebf0; border-radius: 12px; padding: 14px 16px; display: flex; flex-direction: column; gap: 4px; }
.credit-cell.highlight { background: linear-gradient(135deg, #eef2ff, #e0e7ff); border-color: #c7d2fe; }
.credit-label { font-size: 11px; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.04em; }
.credit-value { font-size: 20px; font-weight: 700; color: #1a1a2e; font-family: 'SF Mono', Menlo, Consolas, monospace; }
.two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.data-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.data-table th, .data-table td { padding: 8px 10px; text-align: left; border-bottom: 1px solid #f0f0f5; }
.data-table th { color: #888; font-size: 11px; text-transform: uppercase; }
.empty { color: #aaa; font-size: 13px; font-style: italic; }
.invoice-list { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 6px; }
.invoice-link { display: grid; grid-template-columns: 1fr auto auto; gap: 12px; padding: 8px 10px; border-radius: 8px; text-decoration: none; color: #1a1a2e; align-items: center; }
.invoice-link:hover { background: #f7f7fb; }
.invoice-amount { font-weight: 600; font-family: 'SF Mono', Menlo, Consolas, monospace; }
.invoice-status { font-size: 11px; padding: 2px 8px; border-radius: 6px; font-weight: 600; }
.invoice-status.paid { background: #dcfce7; color: #15803d; }
.invoice-status.pending { background: #fef3c7; color: #b45309; }
.invoice-status.cancelled { background: #fee2e2; color: #b91c1c; }
.invoice-date { font-size: 11px; color: #888; }

@media (max-width: 700px) {
	.two-col { grid-template-columns: 1fr; }
}
@media (max-width: 640px) {
	.credit-grid { grid-template-columns: repeat(2, 1fr); }
}
</style>
