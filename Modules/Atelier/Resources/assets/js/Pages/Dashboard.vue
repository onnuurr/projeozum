<script setup>
import { computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { OzPageHeader, OzModuleTabs, OzStatTile, OzCard, OzTable, OzPill, OzButton } from '@/Components/Oz'
import { ORDER_STATUS, ORDER_TONE } from '../atelierNav'

defineOptions({ layout: AppLayout })

const props = defineProps({
	activeOrders: { type: Array, default: () => [] },
	fasonPending: { type: Number, default: 0 },
	lowStock: { type: Number, default: 0 },
})

const lateCount = computed(() => props.activeOrders.filter((o) => o.isLate).length)

const stats = computed(() => [
	{ key: 'active', label: 'Aktif İş Emri', value: props.activeOrders.length, icon: 'workflow', tone: 'accent' },
	{ key: 'late', label: 'Termini Geçen', value: lateCount.value, icon: 'clock', tone: lateCount.value ? 'danger' : 'success' },
	{ key: 'fason', label: 'Fasonda Bekleyen Adım', value: props.fasonPending, icon: 'factory', tone: 'warning' },
	{ key: 'stock', label: 'Tükenen Hammadde', value: props.lowStock, icon: 'package', tone: props.lowStock ? 'danger' : 'success' },
])

const columns = [
	{ key: 'code', label: 'Kod', sortable: true },
	{ key: 'productName', label: 'Ürün', sortable: true, class: 'oz-cell-strong' },
	{ key: 'status', label: 'Durum', sortable: true },
	{ key: 'dueDate', label: 'Termin', sortable: true },
	{ key: 'actions', label: '', align: 'right', width: '80px', searchable: false },
]
</script>

<template>
	<Head title="Atölye Paneli" />

	<OzPageHeader title="Atölye Paneli" subtitle="Üretim durumu özeti">
		<template #actions>
			<OzButton icon="workflow" @click="router.visit('/atelier/production-orders')">İş Emirleri</OzButton>
		</template>
	</OzPageHeader>
	<OzModuleTabs />

	<div class="stats">
		<OzStatTile v-for="s in stats" :key="s.key" :label="s.label" :value="s.value" :icon="s.icon" :tone="s.tone" />
	</div>

	<OzCard title="Devam Eden İş Emirleri" :meta="`${activeOrders.length} kayıt`">
		<OzTable :columns="columns" :rows="activeOrders" row-key="id" :default-sort="{ key: 'dueDate', dir: 'asc' }" empty-title="Aktif iş emri yok" empty-text="Planlanan ya da üretimdeki iş emirleri burada görünür." min-width="560px">
			<template #cell-code="{ value }"><span class="mono">{{ value }}</span></template>
			<template #cell-status="{ value }">
				<OzPill :tone="ORDER_TONE[value] ?? 'neutral'">{{ ORDER_STATUS[value] ?? value }}</OzPill>
			</template>
			<template #cell-dueDate="{ row }">
				<span class="due">
					{{ row.dueDate || '—' }}
					<OzPill v-if="row.isLate" tone="danger">Gecikti</OzPill>
				</span>
			</template>
			<template #cell-actions="{ row }">
				<Link :href="`/atelier/production-orders/${row.id}`" class="detail-link">Detay</Link>
			</template>
		</OzTable>
	</OzCard>
</template>

<style scoped>
.stats { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; margin-bottom: 14px; }
@media (max-width: 1040px) { .stats { grid-template-columns: repeat(2, minmax(0, 1fr)); } }

.mono { font-family: ui-monospace, monospace; font-size: 12px; }
.due { display: inline-flex; align-items: center; gap: 8px; }
.detail-link { color: var(--oz-accent); text-decoration: none; padding: 4px 10px; border-radius: 6px; font-size: 12px; font-weight: 600; }
.detail-link:hover { background: var(--oz-accent-soft); }
</style>
