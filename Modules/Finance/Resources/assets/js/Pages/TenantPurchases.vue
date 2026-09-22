<template>
	<Head title="Bayi Siparişleri" />
	<div class="page-tenant-purchases">
		<Breadcrumb
			:items="[
				{ label: 'Ana Sayfa', to: '/workflow', icon: 'home' },
				{ label: 'Finans', to: '/finance' },
				{ label: 'Bayi Siparişleri' },
			]"
		/>

		<FinanceNav current="tenant-purchases" />

		<PageHeader title="Bayi Siparişleri">
			<template #subtitle>
				<strong>{{ summary.orderCount }}</strong> sipariş ·
				toplam <strong>{{ formatMoney(summary.totalAmount) }}</strong> ·
				<strong>{{ summary.statusCounts.pending ?? 0 }}</strong> beklemede
			</template>
		</PageHeader>

		<Card title="Ürün Bazlı Kırılım" body-class="p-0">
			<template #actions>
				<span class="hint">Hangi üründen ne kadarlık sipariş geldi</span>
			</template>
			<EmptyState v-if="byProduct.length === 0" title="Henüz Bagisto siparişi yok." />
			<DataTable v-else :columns="productColumns" :data="byProduct" row-key-field="productName" empty-title="Kayıt bulunamadı">
				<template #totalAmount="{ value }"><span class="strong">{{ formatMoney(value) }}</span></template>
			</DataTable>
		</Card>

		<Card title="Sipariş Listesi" body-class="p-0">
			<template #actions>
				<div class="card-search">
					<input v-model="searchQuery" type="text" placeholder="İş ortağı / sipariş no ara..." />
				</div>
			</template>

			<DataTable :columns="orderColumns" :data="filtered" row-key-field="id" empty-title="Kayıt bulunamadı">
				<template #tenant="{ row }">{{ row.tenant }} <span v-if="row.tenantCode" class="dim">({{ row.tenantCode }})</span></template>
				<template #orderNo="{ value }"><span class="mono">{{ value ?? '—' }}</span></template>
				<template #amount="{ row }"><span class="strong">{{ formatMoney(row.amount) }}</span></template>
				<template #status="{ value }"><Badge :color="statusColor(value)" variant="tonal" :label="statusLabels[value] ?? value" /></template>
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
import EmptyState from '@/Components/EmptyState.vue'
import FinanceNav from '../Components/FinanceNav.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
	summary: { type: Object, required: true },
	orders: { type: Array, default: () => [] },
	byProduct: { type: Array, default: () => [] },
	statusLabels: { type: Object, default: () => ({}) },
})

const productColumns = [
	{ key: 'productName', label: 'Ürün' },
	{ key: 'qty', label: 'Adet' },
	{ key: 'orderCount', label: 'Sipariş Sayısı' },
	{ key: 'totalAmount', label: 'Toplam Tutar' },
]

const orderColumns = [
	{ key: 'tenant', label: 'İş Ortağı' },
	{ key: 'orderNo', label: 'Sipariş No' },
	{ key: 'amount', label: 'Tutar' },
	{ key: 'status', label: 'Durum' },
	{ key: 'itemCount', label: 'Kalem' },
	{ key: 'createdAt', label: 'Oluşturulma' },
]

const searchQuery = ref('')
const filtered = computed(() => {
	const q = searchQuery.value.trim().toLowerCase()
	if (!q) return props.orders
	return props.orders.filter(o =>
		o.tenant.toLowerCase().includes(q) || (o.orderNo ?? '').toLowerCase().includes(q),
	)
})

function formatMoney(value) {
	return new Intl.NumberFormat('tr-TR', { style: 'currency', currency: 'TRY' }).format(value ?? 0)
}

function statusColor(status) {
	return {
		pending: 'warning',
		confirmed: 'info',
		preparing: 'info',
		shipped: 'info',
		delivered: 'success',
		cancelled: 'danger',
	}[status] ?? 'neutral'
}
</script>

<style scoped>
.page-tenant-purchases { display: flex; flex-direction: column; gap: 16px; }
.hint { font-size: 12px; color: rgb(var(--color-muted-rgb)); white-space: nowrap; }

.card-search { display: flex; align-items: center; gap: 6px; background: rgb(var(--color-bg-rgb)); border: 1px solid rgb(var(--color-border-rgb)); border-radius: 8px; padding: 5px 10px; min-width: 220px; }
.card-search input { border: none; background: none; outline: none; font-family: inherit; font-size: 13px; color: rgb(var(--color-ink-rgb)); width: 100%; }

.dim { font-size: 12px; color: rgb(var(--color-muted-rgb)); }
.mono { font-family: 'SF Mono', Menlo, Consolas, monospace; font-size: 12px; }
.strong { font-weight: 700; color: rgb(var(--color-ink-rgb)); }
</style>
