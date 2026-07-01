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

		<div class="page-header">
			<div>
				<h1 class="page-title">Ürün Maliyetleri</h1>
				<p class="page-subtitle"><strong>{{ products.length }}</strong> ürün · kaynak: üretim emri (Atelier) veya alış fiyatı</p>
			</div>
		</div>

		<div class="card">
			<div class="card-header">
				<h3>Maliyet Listesi</h3>
				<div class="card-search">
					<input v-model="searchQuery" type="text" placeholder="Ürün / SKU ara..." />
				</div>
			</div>

			<table class="data-table">
				<thead>
					<tr>
						<th>Ürün</th>
						<th>SKU</th>
						<th>Kaynak</th>
						<th>Birim Maliyet</th>
						<th>Ort. Maliyet</th>
						<th>Satış Fiyatı</th>
						<th>Kar Marjı</th>
					</tr>
				</thead>
				<tbody>
					<tr v-if="filtered.length === 0">
						<td colspan="7" class="empty-row">Kayıt bulunamadı</td>
					</tr>
					<tr v-for="p in filtered" :key="p.id">
						<td>{{ p.name }}</td>
						<td class="dim">{{ p.sku }}</td>
						<td>
							<span class="badge" :class="p.source === 'production' ? 'badge-production' : 'badge-purchase'">
								{{ p.source === 'production' ? 'Üretim' : 'Alış Fiyatı' }}
							</span>
						</td>
						<td>{{ formatMoney(p.unitCost) }}</td>
						<td class="dim">{{ formatMoney(p.avgUnitCost) }}</td>
						<td>{{ formatMoney(p.price) }}</td>
						<td :class="p.margin >= 0 ? 'margin-positive' : 'margin-negative'">
							{{ formatMoney(p.margin) }}
							<span v-if="p.marginRate !== null" class="dim">({{ p.marginRate }}%)</span>
						</td>
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
	products: { type: Array, default: () => [] },
})

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

.badge { display: inline-block; padding: 3px 9px; border-radius: 6px; font-size: 11px; font-weight: 700; }
.badge-production { background: #e0f2fe; color: #0369a1; }
.badge-purchase { background: #f0f0f5; color: #555; }

.margin-positive { color: #059669; font-weight: 600; }
.margin-negative { color: #dc2626; font-weight: 600; }
</style>
