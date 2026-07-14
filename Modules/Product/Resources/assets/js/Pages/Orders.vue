<template>
	<Head title="Siparişler" />
	<div class="page-orders">
		<Breadcrumb
			:items="[
				{ label: 'Ana Sayfa', to: '/workflow', icon: 'home' },
				{ label: 'Siparişler' },
			]"
		/>

		<div class="page-header">
			<div>
				<h1 class="page-title">Siparişler</h1>
				<p class="page-subtitle"><strong>{{ orders.total }}</strong> sipariş kayıtlı</p>
			</div>
		</div>

		<!-- Durum filtre chip'leri -->
		<div class="status-chips">
			<button
				class="chip"
				:class="{ active: !localFilters.status }"
				@click="setStatus('')"
			>Tümü</button>
			<button
				v-for="(label, key) in statuses"
				:key="key"
				class="chip"
				:class="[{ active: localFilters.status === key }, `chip-${key}`]"
				@click="setStatus(key)"
			>{{ label }}</button>
		</div>

		<div class="filters-row">
			<input
				v-model="localFilters.order_no"
				type="text"
				class="filter-input"
				placeholder="Sipariş no ara…"
				@keyup.enter="applyFilters"
			/>
			<select v-model="localFilters.tenant_id" class="filter-input" @change="applyFilters">
				<option value="">Tüm Bayiler</option>
				<option v-for="t in tenants" :key="t.id" :value="t.id">{{ t.name }} ({{ t.code }})</option>
			</select>
			<input v-model="localFilters.date_from" type="date" class="filter-input" @change="applyFilters" />
			<input v-model="localFilters.date_to" type="date" class="filter-input" @change="applyFilters" />
			<button class="btn btn-ghost" @click="applyFilters">Filtrele</button>
		</div>

		<div class="card">
			<div class="table-scroll">
			<table class="data-table">
				<thead>
					<tr>
						<th>Sipariş No</th>
						<th>Bayi</th>
						<th>Tarih</th>
						<th>Ürün</th>
						<th>Tutar</th>
						<th>Durum</th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					<tr v-if="orders.data.length === 0">
						<td colspan="7" class="empty">Sipariş bulunamadı.</td>
					</tr>
					<tr v-for="o in orders.data" :key="o.id">
						<td class="mono">{{ o.order_no }}</td>
						<td>{{ o.tenant?.name ?? '—' }}</td>
						<td>{{ o.created_at?.slice(0, 10) }}</td>
						<td class="mono">{{ o.item_count }}</td>
						<td class="mono">{{ formatMoney(o.total) }}</td>
						<td><span class="status-pill" :class="`status-${o.status}`">{{ statuses[o.status] ?? o.status }}</span></td>
						<td><Link :href="`/orders/${o.id}`" class="btn-ghost">Detay</Link></td>
					</tr>
				</tbody>
			</table>
			</div>
		</div>

		<nav class="pagination" v-if="orders.last_page > 1">
			<Link
				v-for="link in orders.links"
				:key="link.label"
				:href="link.url ?? '#'"
				v-html="link.label"
				:class="['page-link', { active: link.active, disabled: !link.url }]"
			/>
		</nav>
	</div>
</template>

<script setup>
import { reactive } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Breadcrumb from '@/Components/Breadcrumb.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
	orders: { type: Object, required: true },
	statuses: { type: Object, default: () => ({}) },
	tenants: { type: Array, default: () => [] },
	filters: { type: Object, default: () => ({}) },
})

const localFilters = reactive({
	status: props.filters.status ?? '',
	tenant_id: props.filters.tenant_id ?? '',
	order_no: props.filters.order_no ?? '',
	date_from: props.filters.date_from ?? '',
	date_to: props.filters.date_to ?? '',
})

function setStatus(key) {
	localFilters.status = key
	applyFilters()
}

function applyFilters() {
	const params = {}
	Object.entries(localFilters).forEach(([k, v]) => {
		if (v) params[k] = v
	})
	router.get('/orders', params, { preserveState: true, preserveScroll: true, replace: true })
}

function formatMoney(v) {
	return new Intl.NumberFormat('tr-TR', { style: 'currency', currency: 'TRY', maximumFractionDigits: 2 }).format(Number(v ?? 0))
}
</script>

<style scoped>
.page-title { font-size: 22px; font-weight: 700; color: #1a1a2e; }
.page-subtitle { font-size: 13px; color: #888; margin: 4px 0 16px; }
.page-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px; }
.status-chips { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 12px; }
.chip { padding: 6px 12px; border-radius: 999px; border: 1px solid #ebebf0; background: #fff; font-size: 12px; font-weight: 600; color: #555; cursor: pointer; }
.chip.active { background: #4338ca; color: #fff; border-color: #4338ca; }
.filters-row { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 16px; }
.filter-input { padding: 7px 10px; border-radius: 8px; border: 1px solid #ebebf0; font-size: 13px; background: #fff; }
.btn { padding: 7px 14px; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; border: 1px solid #ebebf0; background: #fff; }
.btn-ghost { color: #4338ca; text-decoration: none; padding: 4px 10px; border-radius: 6px; font-size: 12px; }
.btn-ghost:hover { background: #eef2ff; }
.mono { font-family: 'SF Mono', Menlo, Consolas, monospace; font-size: 12px; }
.card { background: #fff; border-radius: 16px; border: 1px solid #ebebf0; overflow: hidden; }
.data-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.data-table th, .data-table td { padding: 10px 14px; text-align: left; border-bottom: 1px solid #f5f5f8; }
.data-table th { font-size: 11px; text-transform: uppercase; color: #888; background: #f8f8fc; }
.empty { text-align: center; color: #aaa; padding: 32px; font-style: italic; }
.status-pill { display: inline-block; padding: 2px 8px; border-radius: 6px; font-size: 11px; font-weight: 600; background: #f0f0f5; color: #555; }
.status-pending { background: #fef3c7; color: #92400e; }
.status-confirmed { background: #dbeafe; color: #1e40af; }
.status-preparing { background: #ede9fe; color: #5b21b6; }
.status-shipped { background: #cffafe; color: #155e75; }
.status-delivered { background: #dcfce7; color: #166534; }
.status-cancelled { background: #fee2e2; color: #991b1b; }
.pagination { display: flex; gap: 4px; margin-top: 12px; }
.page-link { padding: 6px 10px; border-radius: 6px; font-size: 12px; color: #555; text-decoration: none; background: #fff; border: 1px solid #ebebf0; }
.page-link.active { background: #4338ca; color: #fff; border-color: #4338ca; }
.page-link.disabled { color: #ccc; pointer-events: none; }
</style>
