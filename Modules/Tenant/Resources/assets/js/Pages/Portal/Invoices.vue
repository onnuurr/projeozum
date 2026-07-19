<template>
	<Head title="Faturalarım" />
	<div class="portal-invoices">
		<h1 class="page-title">Faturalarım</h1>
		<p class="page-subtitle">{{ invoices.total }} fatura kayıtlı</p>

		<div class="card">
			<div class="table-scroll">
			<table class="data-table">
				<thead>
					<tr>
						<th>Fatura No</th>
						<th>Tarih</th>
						<th>Vade</th>
						<th>Tutar</th>
						<th>Durum</th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					<tr v-if="invoices.data.length === 0">
						<td colspan="6" class="empty">Henüz fatura yok.</td>
					</tr>
					<tr v-for="inv in invoices.data" :key="inv.id">
						<td class="mono">#{{ inv.id }}</td>
						<td>{{ inv.created_at?.slice(0, 10) }}</td>
						<td>{{ inv.due_date ?? '—' }}</td>
						<td class="mono">{{ formatMoney(inv.amount) }} {{ inv.currency }}</td>
						<td><span class="status-pill" :class="inv.status">{{ inv.status }}</span></td>
						<td><Link :href="`/invoices/${inv.id}`" class="btn-ghost">Detay</Link></td>
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
	invoices: { type: Object, required: true },
})

function formatMoney(v) {
	return new Intl.NumberFormat('tr-TR', { maximumFractionDigits: 2 }).format(Number(v ?? 0))
}
</script>

<style scoped>
.page-title { font-size: 22px; font-weight: 700; color: rgb(var(--portal-ink)); }
.page-subtitle { font-size: 13px; color: rgb(var(--portal-text-muted)); margin: 4px 0 16px; }
.mono { font-family: 'SF Mono', Menlo, Consolas, monospace; font-size: 12px; }
.card { background: #fff; border-radius: 16px; border: 1px solid rgb(var(--portal-border)); overflow: hidden; }
.data-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.data-table th, .data-table td { padding: 10px 14px; text-align: left; border-bottom: 1px solid rgb(var(--portal-bg-soft)); }
.data-table th { font-size: 11px; text-transform: uppercase; color: rgb(var(--portal-text-muted)); background: rgb(var(--portal-bg-soft)); }
.empty { text-align: center; color: rgb(var(--portal-text-muted)); padding: 32px; font-style: italic; }
.status-pill { display: inline-block; padding: 2px 8px; border-radius: 6px; font-size: 11px; font-weight: 600; }
.status-pill.paid { background: rgb(var(--color-success) / .12); color: rgb(var(--color-success)); }
.status-pill.pending { background: rgb(var(--color-warning) / .14); color: rgb(180 83 9); }
.status-pill.cancelled { background: rgb(var(--color-danger) / .1); color: rgb(var(--color-danger)); }
.btn-ghost { padding: 4px 10px; border-radius: 6px; font-size: 12px; color: rgb(var(--portal-accent)); text-decoration: none; }
.btn-ghost:hover { background: rgb(var(--portal-accent-soft)); }
</style>
