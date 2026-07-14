<template>
	<nav class="finance-nav">
		<Link
			v-for="tab in tabs"
			:key="tab.href"
			:href="tab.href"
			class="fn-tab"
			:class="{ active: current === tab.key }"
		>
			{{ tab.label }}
		</Link>
	</nav>
</template>

<script setup>
import { Link } from '@inertiajs/vue3'

defineProps({
	current: { type: String, required: true },
})

const tabs = [
	{ key: 'dashboard', label: 'Genel Bakış', href: '/finance' },
	{ key: 'product-costs', label: 'Ürün Maliyetleri', href: '/finance/product-costs' },
	{ key: 'sales', label: 'Satış Geçmişi', href: '/finance/sales' },
	{ key: 'tenant-purchases', label: 'Bayi Alışverişleri', href: '/finance/tenant-purchases' },
	{ key: 'supplier-invoices', label: 'Alınan Faturalar', href: '/finance/supplier-invoices' },
	{ key: 'proformas', label: 'Proforma Faturalar', href: '/finance/proformas' },
	{ key: 'outgoing-invoices', label: 'Düzenlenen Faturalar', href: '/finance/outgoing-invoices' },
	{ key: 'bank', label: 'Banka', href: '/finance/bank-accounts' },
]
</script>

<style scoped>
.finance-nav { display: flex; gap: 4px; margin-bottom: 18px; background: #fff; border: 1px solid #ebebf0; border-radius: 12px; padding: 5px; box-shadow: 0 1px 4px rgba(0,0,0,.04); flex-wrap: wrap; }
.fn-tab { display: flex; align-items: center; padding: 8px 14px; border-radius: 8px; font-size: 13px; font-weight: 600; color: #888; text-decoration: none; transition: all .15s; }
.fn-tab:hover { background: rgb(var(--color-primary-soft)); color: #555; }
.fn-tab.active { background: rgb(var(--color-primary)); color: #fff; }

/* ── Dar ekran (telefon): uzun sekme etiketleri çok satıra taşmasın diye sıkılaştır ── */
@media (max-width: 640px) {
	.finance-nav { gap: 2px; margin-bottom: 12px; padding: 4px; }
	.fn-tab { padding: 6px 9px; font-size: 12px; }
}
</style>
