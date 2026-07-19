<template>
	<Head title="Kredi Hareketleri" />
	<div class="portal-credit">
		<h1 class="page-title">Kredi Hareketleri</h1>

		<div class="snapshot-grid">
			<div class="snap-cell"><span class="label">Limit</span><span class="value">{{ formatMoney(snapshot.credit_limit) }}</span></div>
			<div class="snap-cell"><span class="label">Mevcut Borç</span><span class="value">{{ formatMoney(snapshot.current_balance) }}</span></div>
			<div class="snap-cell highlight"><span class="label">Kullanılabilir</span><span class="value">{{ formatMoney(snapshot.available_credit) }}</span></div>
		</div>

		<div class="card">
			<div class="table-scroll">
			<table class="data-table">
				<thead>
					<tr><th>Tarih</th><th>Tip</th><th>Tutar</th><th>Sebep</th><th>Bakiye Sonrası</th><th>İlişkili</th></tr>
				</thead>
				<tbody>
					<tr v-if="ledger.data.length === 0">
						<td colspan="6" class="empty">Henüz hareket yok.</td>
					</tr>
					<tr v-for="l in ledger.data" :key="l.id">
						<td>{{ l.created_at?.slice(0, 16).replace('T', ' ') }}</td>
						<td><span :class="['type-pill', l.type]">{{ l.type === 'debit' ? '+' : '−' }}</span></td>
						<td class="mono">{{ formatMoney(l.amount) }}</td>
						<td>{{ l.reason }}</td>
						<td class="mono">{{ formatMoney(l.balance_after) }}</td>
						<td>
							<Link v-if="l.order_id" :href="`/orders/${l.order_id}`" class="link">Sipariş #{{ l.order_id }}</Link>
							<Link v-else-if="l.invoice_id" :href="`/invoices/${l.invoice_id}`" class="link">Fatura #{{ l.invoice_id }}</Link>
							<span v-else class="dim">—</span>
						</td>
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
	snapshot: { type: Object, required: true },
	ledger: { type: Object, required: true },
})

function formatMoney(v) {
	return new Intl.NumberFormat('tr-TR', { style: 'currency', currency: 'TRY', maximumFractionDigits: 2 }).format(Number(v ?? 0))
}
</script>

<style scoped>
.page-title { font-size: 22px; font-weight: 700; color: rgb(var(--portal-ink)); margin-bottom: 16px; }
.snapshot-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-bottom: 16px; }
.snap-cell { background: #fff; border: 1px solid rgb(var(--portal-border)); border-radius: 12px; padding: 14px 16px; display: flex; flex-direction: column; gap: 4px; }
.snap-cell.highlight { background: linear-gradient(135deg, rgb(var(--portal-accent-soft)), rgb(var(--portal-accent-soft-2))); border-color: rgb(var(--portal-accent-soft-border)); }
.label { font-size: 11px; font-weight: 600; color: rgb(var(--portal-text-muted)); text-transform: uppercase; }
.value { font-size: 18px; font-weight: 700; font-family: 'SF Mono', Menlo, Consolas, monospace; }
.card { background: #fff; border-radius: 16px; border: 1px solid rgb(var(--portal-border)); overflow: hidden; }
.data-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.data-table th, .data-table td { padding: 10px 14px; text-align: left; border-bottom: 1px solid rgb(var(--portal-bg-soft)); }
.data-table th { font-size: 11px; text-transform: uppercase; color: rgb(var(--portal-text-muted)); background: rgb(var(--portal-bg-soft)); }
.empty { text-align: center; color: rgb(var(--portal-text-muted)); padding: 32px; font-style: italic; }
.mono { font-family: 'SF Mono', Menlo, Consolas, monospace; }
.type-pill { display: inline-block; width: 22px; height: 22px; border-radius: 50%; text-align: center; line-height: 22px; font-weight: 700; }
.type-pill.debit { background: rgb(var(--color-danger) / .1); color: rgb(var(--color-danger)); }
.type-pill.credit { background: rgb(var(--color-success) / .12); color: rgb(var(--color-success)); }
.link { color: rgb(var(--portal-accent)); text-decoration: none; }
.link:hover { text-decoration: underline; }
.dim { color: #aaa; }

@media (max-width: 640px) {
	.snapshot-grid { grid-template-columns: repeat(2, 1fr); }
}
</style>
