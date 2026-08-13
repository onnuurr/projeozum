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

		<FinanceNav current="tenant-purchases" />

		<PageHeader title="Bayi Alışverişleri">
			<template #subtitle>
				<strong>{{ summary.invoiceCount }}</strong> fatura ·
				toplam <strong>{{ formatMoney(summary.totalAmount) }}</strong> ·
				bekleyen <strong>{{ formatMoney(summary.pendingAmount) }}</strong>
			</template>
		</PageHeader>

		<Card title="Tenant Fatura Listesi" body-class="p-0">
			<template #actions>
				<div class="card-search">
					<input v-model="searchQuery" type="text" placeholder="Tenant / sipariş no ara..." />
				</div>
			</template>

			<DataTable :columns="columns" :data="filtered" row-key-field="id" empty-title="Kayıt bulunamadı">
				<template #tenant="{ row }">{{ row.tenant }} <span v-if="row.tenantCode" class="dim">({{ row.tenantCode }})</span></template>
				<template #orderNo="{ value }"><span class="mono">{{ value ?? '—' }}</span></template>
				<template #amount="{ row }"><span class="strong">{{ formatMoney(row.amount) }}</span> <span class="dim">{{ row.currency }}</span></template>
				<template #status="{ value }"><Badge :color="statusColor(value)" variant="tonal" :label="value" /></template>
				<template #dueDate="{ value }"><span class="dim">{{ value ?? '—' }}</span></template>
				<template #paidAt="{ value }"><span class="dim">{{ value ?? '—' }}</span></template>
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
	invoices: { type: Array, default: () => [] },
})

const columns = [
	{ key: 'tenant', label: 'Tenant' },
	{ key: 'orderNo', label: 'Sipariş No' },
	{ key: 'amount', label: 'Tutar' },
	{ key: 'status', label: 'Durum' },
	{ key: 'dueDate', label: 'Vade' },
	{ key: 'paidAt', label: 'Ödendi' },
	{ key: 'createdAt', label: 'Oluşturulma' },
]

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

function statusColor(status) {
	return { paid: 'success', pending: 'warning', cancelled: 'danger' }[status] ?? 'neutral'
}
</script>

<style scoped>
.card-search { display: flex; align-items: center; gap: 6px; background: rgb(var(--color-bg)); border: 1px solid rgb(var(--color-border)); border-radius: 8px; padding: 5px 10px; min-width: 220px; }
.card-search input { border: none; background: none; outline: none; font-family: inherit; font-size: 13px; color: rgb(var(--color-ink)); width: 100%; }

.dim { font-size: 12px; color: rgb(var(--color-muted)); }
.mono { font-family: 'SF Mono', Menlo, Consolas, monospace; font-size: 12px; }
.strong { font-weight: 700; color: rgb(var(--color-ink)); }
</style>
