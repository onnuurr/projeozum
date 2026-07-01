<template>
	<Head title="Satış Geçmişi" />
	<div class="page-sales-history">
		<Breadcrumb
			:items="[
				{ label: 'Ana Sayfa', to: '/workflow', icon: 'home' },
				{ label: 'Finans', to: '/finance' },
				{ label: 'Satış Geçmişi' },
			]"
		/>

		<div class="page-header">
			<div>
				<h1 class="page-title">Satış Geçmişi</h1>
				<p class="page-subtitle">
					<strong>{{ summary.orderCount }}</strong> sipariş ·
					toplam <strong>{{ formatMoney(summary.totalRevenue) }}</strong>
				</p>
			</div>
		</div>

		<div class="card">
			<div class="card-header">
				<h3>Sipariş Listesi</h3>
				<div class="card-search">
					<input v-model="searchQuery" type="text" placeholder="Sipariş no / müşteri ara..." />
				</div>
			</div>

			<table class="data-table">
				<thead>
					<tr>
						<th>Sipariş No</th>
						<th>Müşteri</th>
						<th>Ürün Sayısı</th>
						<th>Ara Toplam</th>
						<th>Kargo</th>
						<th>Toplam</th>
						<th>Durum</th>
						<th>Tarih</th>
					</tr>
				</thead>
				<tbody>
					<tr v-if="filtered.length === 0">
						<td colspan="8" class="empty-row">Kayıt bulunamadı</td>
					</tr>
					<tr v-for="o in filtered" :key="o.id">
						<td class="mono">{{ o.orderNo }}</td>
						<td>{{ o.customer }}</td>
						<td>{{ o.itemCount }}</td>
						<td>{{ formatMoney(o.subtotal) }}</td>
						<td class="dim">{{ formatMoney(o.shippingFee) }}</td>
						<td class="strong">{{ formatMoney(o.total) }}</td>
						<td><span class="badge">{{ o.status }}</span></td>
						<td class="dim">{{ o.createdAt }}</td>
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
	orders: { type: Array, default: () => [] },
})

const searchQuery = ref('')
const filtered = computed(() => {
	const q = searchQuery.value.trim().toLowerCase()
	if (!q) return props.orders
	return props.orders.filter(o =>
		o.orderNo.toLowerCase().includes(q) || o.customer.toLowerCase().includes(q),
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

.badge { display: inline-block; padding: 3px 9px; border-radius: 6px; font-size: 11px; font-weight: 700; background: #f0f0f5; color: #555; text-transform: capitalize; }
</style>
