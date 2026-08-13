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

		<PageHeader title="Finans" subtitle="Şirket içi finansal özet — tenant'lara açık değildir" />

		<div class="kpi-grid">
			<Link href="/finance/sales" class="kpi-link">
				<Card title="Toplam Satış" body-class="p-3.5">
					<p class="kpi-value">{{ formatMoney(sales.totalRevenue) }}</p>
					<p class="kpi-hint">{{ sales.orderCount }} sipariş</p>
				</Card>
			</Link>
			<Link href="/finance/tenant-purchases" class="kpi-link">
				<Card title="Bayi Alışverişleri" body-class="p-3.5">
					<p class="kpi-value">{{ formatMoney(tenantPurchases.totalAmount) }}</p>
					<p class="kpi-hint">{{ tenantPurchases.invoiceCount }} fatura · {{ formatMoney(tenantPurchases.pendingAmount) }} bekliyor</p>
				</Card>
			</Link>
			<Link href="/finance/product-costs" class="kpi-link">
				<Card title="Ortalama Kar Marjı" body-class="p-3.5">
					<p class="kpi-value">{{ costs.avgMarginRate ?? '—' }}%</p>
					<p class="kpi-hint">{{ costs.productsFromProduction }}/{{ costs.productCount }} ürün üretim maliyetinden</p>
				</Card>
			</Link>
		</div>

		<div class="kpi-grid">
			<Link href="/finance/supplier-invoices" class="kpi-link">
				<Card title="Alınan Faturalar" body-class="p-3.5">
					<p class="kpi-value">{{ formatMoney(supplierInvoices.unpaidAmount) }}</p>
					<p class="kpi-hint">{{ supplierInvoices.invoiceCount }} fatura · ödenmemiş bakiye</p>
				</Card>
			</Link>
			<Link href="/finance/proformas" class="kpi-link">
				<Card title="Açık Proformalar" body-class="p-3.5">
					<p class="kpi-value">{{ proformas.openCount }}</p>
					<p class="kpi-hint">{{ proformas.invoiceCount }} toplam proforma</p>
				</Card>
			</Link>
			<Link href="/finance/outgoing-invoices" class="kpi-link">
				<Card title="Düzenlenen Faturalar" body-class="p-3.5">
					<p class="kpi-value">{{ outgoingInvoices.invoiceCount }}</p>
					<p class="kpi-hint">{{ outgoingInvoices.notSentCount }} e-Fatura gönderilmedi</p>
				</Card>
			</Link>
			<Link href="/finance/bank-accounts" class="kpi-link">
				<Card title="Banka Mutabakatı" body-class="p-3.5">
					<p class="kpi-value">{{ bank.unmatchedCount }}</p>
					<p class="kpi-hint">eşleşmemiş işlem · {{ bank.accountCount }} hesap</p>
				</Card>
			</Link>
		</div>
	</div>
</template>

<script setup>
import { Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Breadcrumb from '@/Components/Breadcrumb.vue'
import PageHeader from '@/Components/PageHeader.vue'
import Card from '@/Components/Card.vue'
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
.kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 20px; }
.kpi-link { text-decoration: none; color: inherit; display: block; transition: box-shadow .15s; border-radius: 12px; }
.kpi-link:hover { box-shadow: 0 4px 12px rgba(0,0,0,.08); }
.kpi-value { font-size: 22px; font-weight: 800; color: rgb(var(--color-ink)); margin: 0; }
.kpi-hint { font-size: 11.5px; color: rgb(var(--color-muted)); margin: 4px 0 0; }
</style>
