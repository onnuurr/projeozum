<template>
	<Head title="Finans" />
	<div class="page-finance-dashboard">
		<Breadcrumb
			:items="[
				{ label: 'Ana Sayfa', to: '/workflow', icon: 'home' },
				{ label: 'Finans' },
			]"
		/>

		<div class="page-header">
			<div>
				<h1 class="page-title">Finans</h1>
				<p class="page-subtitle">Şirket içi finansal özet — tenant'lara açık değildir</p>
			</div>
		</div>

		<div class="kpi-grid">
			<Link href="/finance/sales" class="kpi-card">
				<span class="kpi-label">Toplam Satış</span>
				<span class="kpi-value">{{ formatMoney(sales.totalRevenue) }}</span>
				<span class="kpi-hint">{{ sales.orderCount }} sipariş</span>
			</Link>
			<Link href="/finance/tenant-purchases" class="kpi-card">
				<span class="kpi-label">Bayi Alışverişleri</span>
				<span class="kpi-value">{{ formatMoney(tenantPurchases.totalAmount) }}</span>
				<span class="kpi-hint">{{ tenantPurchases.invoiceCount }} fatura · {{ formatMoney(tenantPurchases.pendingAmount) }} bekliyor</span>
			</Link>
			<Link href="/finance/product-costs" class="kpi-card">
				<span class="kpi-label">Ortalama Kar Marjı</span>
				<span class="kpi-value">{{ costs.avgMarginRate ?? '—' }}%</span>
				<span class="kpi-hint">{{ costs.productsFromProduction }}/{{ costs.productCount }} ürün üretim maliyetinden</span>
			</Link>
		</div>

		<div class="card links-card">
			<h3>Modüller</h3>
			<div class="links-grid">
				<Link href="/finance/product-costs">Ürün Maliyetleri</Link>
				<Link href="/finance/sales">Satış Geçmişi</Link>
				<Link href="/finance/tenant-purchases">Bayi Alışverişleri</Link>
			</div>
			<p class="dim">
				Alınan/düzenlenen faturalar, proforma ve banka mutabakatı sonraki fazlarda eklenecek.
			</p>
		</div>
	</div>
</template>

<script setup>
import { Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Breadcrumb from '@/Components/Breadcrumb.vue'

defineOptions({ layout: AppLayout })

defineProps({
	sales: { type: Object, required: true },
	tenantPurchases: { type: Object, required: true },
	costs: { type: Object, required: true },
})

function formatMoney(value) {
	return new Intl.NumberFormat('tr-TR', { style: 'currency', currency: 'TRY' }).format(value ?? 0)
}
</script>

<style scoped>
.page-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 20px; gap: 16px; }
.page-title { font-size: 22px; font-weight: 700; color: #1a1a2e; line-height: 1.2; }
.page-subtitle { font-size: 13px; color: #888; margin-top: 4px; }

.kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 20px; }
.kpi-card {
	display: flex; flex-direction: column; gap: 6px;
	background: #fff; border: 1px solid #ebebf0; border-radius: 16px; padding: 18px;
	box-shadow: 0 1px 4px rgba(0,0,0,.04); text-decoration: none; transition: box-shadow .15s;
}
.kpi-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,.08); }
.kpi-label { font-size: 12px; font-weight: 600; color: #888; text-transform: uppercase; letter-spacing: .04em; }
.kpi-value { font-size: 24px; font-weight: 800; color: #1a1a2e; }
.kpi-hint { font-size: 12px; color: #999; }

.card { background: #fff; border-radius: 16px; border: 1px solid #ebebf0; padding: 18px; box-shadow: 0 1px 4px rgba(0,0,0,.04); }
.links-card h3 { font-size: 15px; font-weight: 700; color: #1a1a2e; margin-bottom: 12px; }
.links-grid { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 12px; }
.links-grid a {
	padding: 8px 14px; background: #f5f5f8; border-radius: 8px; font-size: 13px; font-weight: 600;
	color: rgb(var(--color-primary)); text-decoration: none;
}
.links-grid a:hover { background: rgb(var(--color-primary-soft)); }
.dim { font-size: 12px; color: #999; }
</style>
