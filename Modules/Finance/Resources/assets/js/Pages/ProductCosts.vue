<template>
	<Head title="Ürün Maliyetleri" />
	<div class="page-product-costs">
		<Breadcrumb
			:items="[
				{ label: 'Ana Sayfa', to: '/workflow', icon: 'home' },
				{ label: 'Finans', to: '/finance' },
				{ label: 'Ürün Maliyetleri' },
			]"
		/>

		<FinanceNav current="product-costs" />

		<PageHeader title="Ürün Maliyetleri">
			<template #subtitle><strong>{{ products.length }}</strong> ürün · kaynak: üretim emri (Atelier) veya alış fiyatı</template>
		</PageHeader>

		<Card title="Maliyet Listesi" body-class="p-0">
			<template #actions>
				<div class="card-search">
					<input v-model="searchQuery" type="text" placeholder="Ürün / SKU ara..." />
				</div>
			</template>

			<DataTable :columns="columns" :data="filtered" row-key-field="id" empty-title="Kayıt bulunamadı">
				<template #sku="{ value }"><span class="dim">{{ value }}</span></template>
				<template #source="{ value }">
					<Badge :color="value === 'production' ? 'info' : 'neutral'" variant="tonal" :label="value === 'production' ? 'Üretim' : 'Alış Fiyatı'" />
				</template>
				<template #unitCost="{ value }">{{ formatMoney(value) }}</template>
				<template #avgUnitCost="{ value }"><span class="dim">{{ formatMoney(value) }}</span></template>
				<template #price="{ value }">{{ formatMoney(value) }}</template>
				<template #margin="{ row }">
					<span :class="row.margin >= 0 ? 'margin-positive' : 'margin-negative'">
						{{ formatMoney(row.margin) }}
						<span v-if="row.marginRate !== null" class="dim">({{ row.marginRate }}%)</span>
					</span>
				</template>
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
	products: { type: Array, default: () => [] },
})

const columns = [
	{ key: 'name', label: 'Ürün' },
	{ key: 'sku', label: 'SKU' },
	{ key: 'source', label: 'Kaynak' },
	{ key: 'unitCost', label: 'Birim Maliyet' },
	{ key: 'avgUnitCost', label: 'Ort. Maliyet' },
	{ key: 'price', label: 'Satış Fiyatı' },
	{ key: 'margin', label: 'Kar Marjı' },
]

const searchQuery = ref('')
const filtered = computed(() => {
	const q = searchQuery.value.trim().toLowerCase()
	if (!q) return props.products
	return props.products.filter(p =>
		p.name.toLowerCase().includes(q) || (p.sku ?? '').toLowerCase().includes(q),
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

.margin-positive { color: rgb(var(--color-success)); font-weight: 600; }
.margin-negative { color: rgb(var(--color-danger)); font-weight: 600; }
</style>
