<template>
	<Head title="Siparişlerim" />
	<div class="portal-orders">
		<h1 class="page-title">Siparişlerim</h1>
		<p class="page-subtitle">{{ orders.total }} sipariş kayıtlı</p>

		<div class="card">
			<div class="table-scroll">
			<table class="data-table">
				<thead>
					<tr>
						<th>Sipariş No</th>
						<th>Tarih</th>
						<th>Ürün Sayısı</th>
						<th>Tutar</th>
						<th>Tip</th>
						<th>Durum</th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					<tr v-if="orders.data.length === 0">
						<td colspan="7" class="empty">Henüz sipariş yok.</td>
					</tr>
					<tr v-for="o in orders.data" :key="o.id">
						<td class="mono">{{ o.order_no }}</td>
						<td>{{ o.created_at?.slice(0, 10) }}</td>
						<td class="mono">{{ o.item_count }}</td>
						<td class="mono">{{ formatMoney(o.total) }}</td>
						<td><span class="badge" :class="`type-${o.order_type}`">{{ o.order_type }}</span></td>
						<td><span class="status-pill" :class="o.status">{{ o.status }}</span></td>
						<td><Link :href="`/orders/${o.id}`" class="btn-ghost">Detay</Link></td>
					</tr>
				</tbody>
			</table>
			</div>
		</div>

		<nav class="pagination" v-if="orders.last_page > 1">
			<Link
				v-for="link in orders.links"
				:key="link.label"
				:href="link.url ?? '#'"
				v-html="link.label"
				:class="['page-link', { active: link.active, disabled: !link.url }]"
			/>
		</nav>
	</div>
</template>

<script setup>
import { Head, Link } from '@inertiajs/vue3'
import TenantPortalLayout from '@/Layouts/TenantPortalLayout.vue'

defineOptions({ layout: TenantPortalLayout })

defineProps({
	tenant: { type: Object, required: true },
	orders: { type: Object, required: true },
})

function formatMoney(v) {
	return new Intl.NumberFormat('tr-TR', { style: 'currency', currency: 'TRY', maximumFractionDigits: 2 }).format(Number(v ?? 0))
}
</script>

<style scoped>
.page-title { font-size: 22px; font-weight: 700; color: #1a1a2e; }
.page-subtitle { font-size: 13px; color: #888; margin: 4px 0 16px; }
.mono { font-family: 'SF Mono', Menlo, Consolas, monospace; font-size: 12px; }
.card { background: #fff; border-radius: 16px; border: 1px solid #ebebf0; overflow: hidden; }
.data-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.data-table th, .data-table td { padding: 10px 14px; text-align: left; border-bottom: 1px solid #f5f5f8; }
.data-table th { font-size: 11px; text-transform: uppercase; color: #888; background: #f8f8fc; }
.empty { text-align: center; color: #aaa; padding: 32px; font-style: italic; }
.badge { display: inline-block; padding: 2px 8px; border-radius: 6px; font-size: 11px; font-weight: 600; }
.badge.type-dropship { background: #ede9fe; color: #5b21b6; }
.badge.type-b2c { background: #f0f0f5; color: #555; }
.status-pill { display: inline-block; padding: 2px 8px; border-radius: 6px; font-size: 11px; font-weight: 600; background: #f0f0f5; color: #555; }
.btn-ghost { padding: 4px 10px; border-radius: 6px; font-size: 12px; color: #4338ca; text-decoration: none; }
.btn-ghost:hover { background: #eef2ff; }
.pagination { display: flex; gap: 4px; margin-top: 12px; }
.page-link { padding: 6px 10px; border-radius: 6px; font-size: 12px; color: #555; text-decoration: none; background: #fff; border: 1px solid #ebebf0; }
.page-link.active { background: #4338ca; color: #fff; border-color: #4338ca; }
.page-link.disabled { color: #ccc; pointer-events: none; }
</style>
