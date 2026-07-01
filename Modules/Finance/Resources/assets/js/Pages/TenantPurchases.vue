<template>
	<Head title="Bayi Alışverişleri" />
	<div class="page-tenant-purchases">
		<Breadcrumb
			:items="[
				{ label: 'Ana Sayfa', to: '/workflow', icon: 'home' },
				{ label: 'Finans', to: '/finance' },
				{ label: 'Bayi Alışverişleri' },
			]"
		/>

		<div class="page-header">
			<div>
				<h1 class="page-title">Bayi Alışverişleri</h1>
				<p class="page-subtitle">
					<strong>{{ summary.invoiceCount }}</strong> fatura ·
					toplam <strong>{{ formatMoney(summary.totalAmount) }}</strong> ·
					bekleyen <strong>{{ formatMoney(summary.pendingAmount) }}</strong>
				</p>
			</div>
		</div>

		<div class="card">
			<div class="card-header">
				<h3>Tenant Fatura Listesi</h3>
				<div class="card-search">
					<input v-model="searchQuery" type="text" placeholder="Tenant / sipariş no ara..." />
				</div>
			</div>

			<table class="data-table">
				<thead>
					<tr>
						<th>Tenant</th>
						<th>Sipariş No</th>
						<th>Tutar</th>
						<th>Durum</th>
						<th>Vade</th>
						<th>Ödendi</th>
						<th>Oluşturulma</th>
					</tr>
				</thead>
				<tbody>
					<tr v-if="filtered.length === 0">
						<td colspan="7" class="empty-row">Kayıt bulunamadı</td>
					</tr>
					<tr v-for="inv in filtered" :key="inv.id">
						<td>{{ inv.tenant }} <span v-if="inv.tenantCode" class="dim">({{ inv.tenantCode }})</span></td>
						<td class="mono">{{ inv.orderNo ?? '—' }}</td>
						<td class="strong">{{ formatMoney(inv.amount) }} <span class="dim">{{ inv.currency }}</span></td>
						<td><span class="badge" :class="'badge-' + inv.status">{{ inv.status }}</span></td>
						<td class="dim">{{ inv.dueDate ?? '—' }}</td>
						<td class="dim">{{ inv.paidAt ?? '—' }}</td>
						<td class="dim">{{ inv.createdAt }}</td>
					</tr>
				</tbody>
			</table>
		</div>
	</div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Breadcrumb from '@/Components/Breadcrumb.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
	summary: { type: Object, required: true },
	invoices: { type: Array, default: () => [] },
})

const searchQuery = ref('')
const filtered = computed(() => {
	const q = searchQuery.value.trim().toLowerCase()
	if (!q) return props.invoices
	return props.invoices.filter(inv =>
		inv.tenant.toLowerCase().includes(q) || (inv.orderNo ?? '').toLowerCase().includes(q),
	)
})

function formatMoney(value) {
	return new Intl.NumberFormat('tr-TR', { style: 'currency', currency: 'TRY' }).format(value ?? 0)
}
</script>

<style scoped>
.page-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 20px; gap: 16px; }
.page-title { font-size: 22px; font-weight: 700; color: #1a1a2e; line-height: 1.2; }
.page-subtitle { font-size: 13px; color: #888; margin-top: 4px; }

.card { background: #fff; border-radius: 16px; border: 1px solid #ebebf0; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,.04); }
.card-header { padding: 14px 18px; display: flex; align-items: center; gap: 12px; border-bottom: 1px solid #f0f0f5; }
.card-header h3 { font-size: 15px; font-weight: 700; color: #1a1a2e; }
.card-search { display: flex; align-items: center; gap: 6px; background: #f5f5f8; border: 1px solid #e8e8f0; border-radius: 8px; padding: 5px 10px; margin-left: auto; min-width: 220px; }
.card-search input { border: none; background: none; outline: none; font-family: inherit; font-size: 13px; color: #1a1a2e; width: 100%; }

.data-table { width: 100%; border-collapse: separate; border-spacing: 0; }
.data-table thead tr { background: #f8f8fc; }
.data-table th { text-align: left; padding: 12px 16px; font-size: 11px; font-weight: 600; color: #aaa; border-bottom: 1px solid #f0f0f5; text-transform: uppercase; letter-spacing: 0.04em; }
.data-table td { padding: 12px 16px; font-size: 13px; color: #444; border-bottom: 1px solid #f5f5f8; vertical-align: middle; }
.data-table tr:last-child td { border-bottom: none; }
.data-table tr:hover td { background: #fafafe; }
.empty-row { text-align: center !important; color: #aaa; padding: 32px 0 !important; font-style: italic; }
.dim { font-size: 12px; color: #888; }
.mono { font-family: 'SF Mono', Menlo, Consolas, monospace; font-size: 12px; }
.strong { font-weight: 700; color: #1a1a2e; }

.badge { display: inline-block; padding: 3px 9px; border-radius: 6px; font-size: 11px; font-weight: 700; text-transform: capitalize; background: #f0f0f5; color: #555; }
.badge-paid { background: #dcfce7; color: #166534; }
.badge-pending { background: #fef9c3; color: #854d0e; }
.badge-cancelled { background: #fee2e2; color: #991b1b; }
</style>
