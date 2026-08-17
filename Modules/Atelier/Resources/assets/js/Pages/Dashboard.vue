<template>
	<Head title="Atölye Paneli" />
	<div class="page-atelier-dashboard">
		<Breadcrumb
			:items="[
				{ label: 'Ana Sayfa', to: '/workflow', icon: 'home' },
				{ label: 'Üretim Atölyesi', to: '/atelier' },
				{ label: 'Panel' },
			]"
		/>

		<AtelierNav current="dashboard" />

		<PageHeader title="Atölye Paneli" subtitle="Üretim durumu özeti" />

		<!-- Stat Cards -->
		<div class="stat-row">
			<div class="stat-card">
				<div class="stat-label">Aktif İş Emri</div>
				<div class="stat-value">{{ activeOrders.length }}</div>
			</div>
			<div class="stat-card">
				<div class="stat-label">Fasonda Bekleyen Adım</div>
				<div class="stat-value">{{ fasonPending }}</div>
			</div>
			<div class="stat-card stat-card-danger">
				<div class="stat-label">Tükenen Hammadde</div>
				<div class="stat-value danger">{{ lowStock }}</div>
			</div>
		</div>

		<!-- Active Orders Table -->
		<Card title="Devam Eden İş Emirleri">
			<DataTable :columns="columns" :data="activeOrders" empty-title="Aktif iş emri yok.">
				<template #code="{ row }"><span class="mono-chip">{{ row.code }}</span></template>
				<template #productName="{ row }"><span class="row-name">{{ row.productName }}</span></template>
				<template #status="{ row }">
					<Badge :color="row.status === 'in_progress' ? 'success' : 'info'" :label="STATUS[row.status]" variant="tonal" />
				</template>
				<template #dueDate="{ row }">
					{{ row.dueDate || '—' }}
					<Badge v-if="row.isLate" color="danger" label="Gecikti" />
				</template>
				<template #actions="{ row }">
					<Button variant="secondary" size="sm" @click="router.get(`/atelier/production-orders/${row.id}`)">Detay</Button>
				</template>
			</DataTable>
		</Card>
	</div>
</template>

<script setup>
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Breadcrumb from '@/Components/Breadcrumb.vue'
import PageHeader from '@/Components/PageHeader.vue'
import Card from '@/Components/Card.vue'
import DataTable from '@/Components/DataTable.vue'
import Badge from '@/Components/Badge.vue'
import Button from '@/Components/Button.vue'
import AtelierNav from '../Components/AtelierNav.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({ activeOrders: { type: Array, default: () => [] }, fasonPending: { type: Number, default: 0 }, lowStock: { type: Number, default: 0 } })
const STATUS = { planned: 'Planlandı', in_progress: 'Üretimde' }

const columns = [
	{ key: 'code', label: 'Kod' },
	{ key: 'productName', label: 'Ürün' },
	{ key: 'status', label: 'Durum' },
	{ key: 'dueDate', label: 'Termin' },
]
</script>

<style scoped>
.stat-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; margin-bottom: 20px; }
.stat-card { background: #fff; border-radius: 14px; border: 1px solid #ebebf0; padding: 18px 20px; box-shadow: 0 1px 4px rgba(0,0,0,.04); display: flex; flex-direction: column; gap: 8px; }
.stat-card-danger { border-color: #fee2e2; background: #fff8f8; }
.stat-label { font-size: 12px; font-weight: 600; color: #888; text-transform: uppercase; letter-spacing: 0.04em; }
.stat-value { font-size: 32px; font-weight: 800; color: #1a1a2e; line-height: 1; }
.stat-value.danger { color: #dc2626; }

.row-name { font-weight: 600; color: #1a1a2e; }
.mono-chip { font-family: 'SF Mono', Menlo, Consolas, monospace; font-size: 11.5px; background: #f0f0f5; padding: 2px 7px; border-radius: 5px; color: #555; }

@media (max-width: 640px) {
	.stat-row { grid-template-columns: repeat(2, 1fr); }
}
</style>
