<template>
	<Head :title="`Sipariş ${order.order_no}`" />
	<div class="order-detail">
		<Link href="/orders" class="back-link">← Siparişler</Link>
		<h1 class="page-title">{{ order.order_no }}</h1>
		<div class="meta">
			<span><strong>Tarih:</strong> {{ order.created_at?.slice(0, 10) }}</span>
			<span><strong>Tip:</strong> {{ order.order_type }}</span>
			<span><strong>Durum:</strong> {{ order.status }}</span>
			<span><strong>Ödeme:</strong> {{ order.payment_method ?? '—' }}</span>
		</div>

		<div class="card">
			<h2 class="card-title">Ürünler</h2>
			<table class="data-table">
				<thead>
					<tr><th>Ürün</th><th>Beden / Renk</th><th>Adet</th><th>Birim Fiyat</th><th>Toplam</th></tr>
				</thead>
				<tbody>
					<tr v-for="i in order.items" :key="i.id">
						<td>
							<strong>{{ i.product_name }}</strong>
							<div v-if="i.product_brand" class="dim">{{ i.product_brand }}</div>
						</td>
						<td>{{ [i.size, i.color].filter(Boolean).join(' / ') || '—' }}</td>
						<td class="mono">{{ i.qty }}</td>
						<td class="mono">{{ formatMoney(i.unit_price) }}</td>
						<td class="mono">{{ formatMoney(i.total_price) }}</td>
					</tr>
				</tbody>
			</table>
		</div>

		<div class="card totals">
			<div><span>Ara Toplam:</span><span class="mono">{{ formatMoney(order.subtotal) }}</span></div>
			<div><span>Kargo:</span><span class="mono">{{ formatMoney(order.shipping_fee) }}</span></div>
			<div class="grand"><span>Toplam:</span><span class="mono">{{ formatMoney(order.total) }}</span></div>
		</div>

		<div class="card" v-if="order.shipping_info">
			<h2 class="card-title">Teslimat</h2>
			<pre class="json-view">{{ JSON.stringify(order.shipping_info, null, 2) }}</pre>
		</div>
	</div>
</template>

<script setup>
import { Head, Link } from '@inertiajs/vue3'
import TenantPortalLayout from '@/Layouts/TenantPortalLayout.vue'

defineOptions({ layout: TenantPortalLayout })

defineProps({
	tenant: { type: Object, required: true },
	order: { type: Object, required: true },
})

function formatMoney(v) {
	return new Intl.NumberFormat('tr-TR', { style: 'currency', currency: 'TRY', maximumFractionDigits: 2 }).format(Number(v ?? 0))
}
</script>

<style scoped>
.back-link { font-size: 12px; color: #4338ca; text-decoration: none; }
.back-link:hover { text-decoration: underline; }
.page-title { font-size: 22px; font-weight: 700; color: #1a1a2e; margin-top: 8px; }
.meta { display: flex; gap: 18px; margin: 8px 0 16px; font-size: 13px; color: #555; flex-wrap: wrap; }
.card { background: #fff; border-radius: 16px; padding: 18px 20px; border: 1px solid #ebebf0; margin-bottom: 14px; }
.card-title { font-size: 14px; font-weight: 700; margin-bottom: 12px; }
.data-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.data-table th, .data-table td { padding: 8px 10px; text-align: left; border-bottom: 1px solid #f5f5f8; }
.data-table th { color: #888; font-size: 11px; text-transform: uppercase; }
.dim { color: #888; font-size: 11px; }
.mono { font-family: 'SF Mono', Menlo, Consolas, monospace; }
.totals { display: flex; flex-direction: column; gap: 6px; max-width: 320px; margin-left: auto; }
.totals div { display: flex; justify-content: space-between; font-size: 13px; }
.totals .grand { font-weight: 700; font-size: 15px; padding-top: 8px; border-top: 1px solid #ebebf0; }
.json-view { background: #f7f7fb; padding: 12px; border-radius: 8px; font-size: 11px; overflow-x: auto; }
</style>
