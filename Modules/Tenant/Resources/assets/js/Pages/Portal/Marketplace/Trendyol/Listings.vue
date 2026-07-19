<template>
	<Head title="Trendyol · Satışlar" />
	<div class="page">
		<Link href="/marketplace/trendyol" class="back-link">← Trendyol Panosu</Link>
		<h1 class="page-title">Satışlar</h1>

		<div class="card">
			<div class="table-scroll">
			<table class="data-table">
				<thead>
					<tr><th>Tarih</th><th>Sipariş No</th><th>Ürün</th><th>Adet</th><th>Birim</th><th>Komisyon</th><th>Net</th><th>Durum</th></tr>
				</thead>
				<tbody>
					<tr v-if="sales.data.length === 0">
						<td colspan="8" class="empty">Henüz satış yok.</td>
					</tr>
					<tr v-for="s in sales.data" :key="s.id">
						<td>{{ s.sold_at?.slice(0, 10) }}</td>
						<td class="mono">{{ s.external_order_id }}</td>
						<td>—</td>
						<td class="mono">{{ s.qty }}</td>
						<td class="mono">{{ formatMoney(s.sold_price) }}</td>
						<td class="mono dim">{{ formatMoney(s.commission) }}</td>
						<td class="mono">{{ formatMoney(s.net_revenue) }}</td>
						<td><span :class="['pill', s.status]">{{ s.status }}</span></td>
					</tr>
				</tbody>
			</table>
			</div>
		</div>
	</div>
</template>

<script setup>
import { Head, Link } from '@inertiajs/vue3'
import TenantPortalLayout from '@/Layouts/TenantPortalLayout.vue'

defineOptions({ layout: TenantPortalLayout })

defineProps({
	tenant: { type: Object, required: true },
	sales: { type: Object, required: true },
})

function formatMoney(v) {
	return new Intl.NumberFormat('tr-TR', { style: 'currency', currency: 'TRY', maximumFractionDigits: 2 }).format(Number(v ?? 0))
}
</script>

<style scoped>
.back-link { font-size: 12px; color: rgb(var(--portal-accent)); text-decoration: none; }
.page-title { font-size: 22px; font-weight: 700; margin: 8px 0 16px; color: rgb(var(--portal-ink)); }
.card { background: #fff; border-radius: 12px; border: 1px solid rgb(var(--portal-border)); overflow: hidden; }
.data-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.data-table th, .data-table td { padding: 9px 12px; text-align: left; border-bottom: 1px solid rgb(var(--portal-bg-soft)); }
.data-table th { font-size: 11px; text-transform: uppercase; color: rgb(var(--portal-text-muted)); background: rgb(var(--portal-bg-soft)); }
.empty { text-align: center; color: rgb(var(--portal-text-muted)); padding: 32px; font-style: italic; }
.mono { font-family: 'SF Mono', Menlo, Consolas, monospace; font-size: 12px; }
.dim { color: rgb(var(--portal-text-muted)); }
.pill { padding: 2px 8px; border-radius: 6px; font-size: 11px; font-weight: 600; background: rgb(var(--portal-accent-soft-2)); color: rgb(var(--portal-accent)); }
.pill.delivered { background: rgb(var(--color-success) / .12); color: rgb(var(--color-success)); }
.pill.cancelled, .pill.returned { background: rgb(var(--color-danger) / .1); color: rgb(var(--color-danger)); }
</style>
