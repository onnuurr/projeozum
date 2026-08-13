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

		<FinanceNav current="sales" />

		<PageHeader title="Satış Geçmişi">
			<template #subtitle>
				<strong>{{ summary.orderCount }}</strong> sipariş ·
				toplam <strong>{{ formatMoney(summary.totalRevenue) }}</strong>
			</template>
		</PageHeader>

		<Card title="Sipariş Listesi" body-class="p-0">
			<template #actions>
				<div class="card-search">
					<input v-model="searchQuery" type="text" placeholder="Sipariş no / müşteri ara..." />
				</div>
			</template>

			<DataTable :columns="columns" :data="filtered" row-key-field="id" empty-title="Kayıt bulunamadı">
				<template #orderNo="{ value }"><span class="mono">{{ value }}</span></template>
				<template #shippingFee="{ value }"><span class="dim">{{ formatMoney(value) }}</span></template>
				<template #subtotal="{ value }">{{ formatMoney(value) }}</template>
				<template #total="{ value }"><span class="strong">{{ formatMoney(value) }}</span></template>
				<template #status="{ value }"><Badge color="neutral" variant="tonal" :label="value" /></template>
				<template #createdAt="{ value }"><span class="dim">{{ value }}</span></template>
			</DataTable>
		</Card>
	</div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Breadcrumb from '@/Components/Breadcrumb.vue'
import PageHeader from '@/Components/PageHeader.vue'
import Card from '@/Components/Card.vue'
import DataTable from '@/Components/DataTable.vue'
import Badge from '@/Components/Badge.vue'
import FinanceNav from '../Components/FinanceNav.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
	summary: { type: Object, required: true },
	orders: { type: Array, default: () => [] },
})

const columns = [
	{ key: 'orderNo', label: 'Sipariş No' },
	{ key: 'customer', label: 'Müşteri' },
	{ key: 'itemCount', label: 'Ürün Sayısı' },
	{ key: 'subtotal', label: 'Ara Toplam' },
	{ key: 'shippingFee', label: 'Kargo' },
	{ key: 'total', label: 'Toplam' },
	{ key: 'status', label: 'Durum' },
	{ key: 'createdAt', label: 'Tarih' },
]

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
.card-search { display: flex; align-items: center; gap: 6px; background: rgb(var(--color-bg)); border: 1px solid rgb(var(--color-border)); border-radius: 8px; padding: 5px 10px; min-width: 220px; }
.card-search input { border: none; background: none; outline: none; font-family: inherit; font-size: 13px; color: rgb(var(--color-ink)); width: 100%; }

.dim { font-size: 12px; color: rgb(var(--color-muted)); }
.mono { font-family: 'SF Mono', Menlo, Consolas, monospace; font-size: 12px; }
.strong { font-weight: 700; color: rgb(var(--color-ink)); }
</style>
