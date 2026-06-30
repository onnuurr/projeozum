<template>
	<Head :title="`${tenant.name} · Portal`" />
	<div class="portal-dashboard">
		<header class="page-header">
			<div>
				<h1 class="page-title">Hoş geldiniz, {{ tenant.name }}</h1>
				<p class="page-subtitle">
					<span class="mono">{{ tenant.code }}</span>
					· Bayi portalı (scaffold)
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

		<section class="card placeholder">
			<h2 class="card-title">Geçmiş Siparişler · Top Satanlar · Faturalar</h2>
			<p>Bu paneller Phase 1 ile birlikte aktif olacak.</p>
		</section>
	</div>
</template>

<script setup>
import { Head } from '@inertiajs/vue3'
import TenantPortalLayout from '@/Layouts/TenantPortalLayout.vue'

defineOptions({ layout: TenantPortalLayout })

defineProps({
	tenant: { type: Object, required: true },
	snapshot: { type: Object, required: true },
})

function formatMoney(v) {
	return new Intl.NumberFormat('tr-TR', { style: 'currency', currency: 'TRY', maximumFractionDigits: 0 }).format(Number(v ?? 0))
}
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

.card.placeholder { color: #888; font-size: 13px; }
.card.placeholder p { margin: 0; }
</style>
