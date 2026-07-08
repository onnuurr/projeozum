<template>
	<Head title="Finans" />
	<div class="page-finance-dashboard">
		<Breadcrumb
			:items="[
				{ label: 'Ana Sayfa', to: '/workflow', icon: 'home' },
				{ label: 'Finans' },
			]"
		/>

		<FinanceNav current="dashboard" />

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

		<div class="kpi-grid">
			<Link href="/finance/supplier-invoices" class="kpi-card">
				<span class="kpi-label">Alınan Faturalar</span>
				<span class="kpi-value">{{ formatMoney(supplierInvoices.unpaidAmount) }}</span>
				<span class="kpi-hint">{{ supplierInvoices.invoiceCount }} fatura · ödenmemiş bakiye</span>
			</Link>
			<Link href="/finance/proformas" class="kpi-card">
				<span class="kpi-label">Açık Proformalar</span>
				<span class="kpi-value">{{ proformas.openCount }}</span>
				<span class="kpi-hint">{{ proformas.invoiceCount }} toplam proforma</span>
			</Link>
			<Link href="/finance/outgoing-invoices" class="kpi-card">
				<span class="kpi-label">Düzenlenen Faturalar</span>
				<span class="kpi-value">{{ outgoingInvoices.invoiceCount }}</span>
				<span class="kpi-hint">{{ outgoingInvoices.notSentCount }} e-Fatura gönderilmedi</span>
			</Link>
			<Link href="/finance/bank-accounts" class="kpi-card">
				<span class="kpi-label">Banka Mutabakatı</span>
				<span class="kpi-value">{{ bank.unmatchedCount }}</span>
				<span class="kpi-hint">eşleşmemiş işlem · {{ bank.accountCount }} hesap</span>
			</Link>
		</div>
	</div>
</template>

<script setup>
import { Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Breadcrumb from '@/Components/Breadcrumb.vue'
import FinanceNav from '../Components/FinanceNav.vue'

defineOptions({ layout: AppLayout })

defineProps({
	sales: { type: Object, required: true },
	tenantPurchases: { type: Object, required: true },
	costs: { type: Object, required: true },
	supplierInvoices: { type: Object, required: true },
	proformas: { type: Object, required: true },
	outgoingInvoices: { type: Object, required: true },
	bank: { type: Object, required: true },
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
</style>
