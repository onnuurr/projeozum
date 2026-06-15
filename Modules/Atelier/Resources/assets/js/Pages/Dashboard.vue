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

		<div class="page-header">
			<div>
				<h1 class="page-title">Atölye Paneli</h1>
				<p class="page-subtitle">Üretim durumu özeti</p>
			</div>
		</div>

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
		<div class="card">
			<div class="card-header">
				<h3>Devam Eden İş Emirleri</h3>
			</div>
			<table class="data-table">
				<thead>
					<tr>
						<th style="width: 12%">Kod</th>
						<th>Ürün</th>
						<th style="width: 14%">Durum</th>
						<th style="width: 16%">Termin</th>
						<th style="width: 8%"></th>
					</tr>
				</thead>
				<tbody>
					<tr v-if="!activeOrders.length">
						<td colspan="5" class="empty-row">Aktif iş emri yok.</td>
					</tr>
					<tr v-for="o in activeOrders" :key="o.id" :class="{ 'row-late': o.isLate }">
						<td><span class="mono-chip">{{ o.code }}</span></td>
						<td><span class="row-name">{{ o.productName }}</span></td>
						<td>
							<span class="status-pill" :class="o.status === 'in_progress' ? 'status-active' : 'status-planned'">
								<span class="dot"></span>{{ STATUS[o.status] }}
							</span>
						</td>
						<td>
							{{ o.dueDate || '—' }}
							<span v-if="o.isLate" class="late-badge">Gecikti</span>
						</td>
						<td>
							<button class="table-action-btn" @click="router.get(`/atelier/production-orders/${o.id}`)">Detay</button>
						</td>
					</tr>
				</tbody>
			</table>
		</div>
	</div>
</template>

<script setup>
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Breadcrumb from '@/Components/Breadcrumb.vue'
import AtelierNav from '../Components/AtelierNav.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({ activeOrders: { type: Array, default: () => [] }, fasonPending: { type: Number, default: 0 }, lowStock: { type: Number, default: 0 } })
const STATUS = { planned: 'Planlandı', in_progress: 'Üretimde' }
</script>

<style scoped>
.page-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 20px; gap: 16px; }
.page-title { font-size: 22px; font-weight: 700; color: #1a1a2e; }
.page-subtitle { font-size: 13px; color: #888; margin-top: 4px; }

.stat-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; margin-bottom: 20px; }
.stat-card { background: #fff; border-radius: 14px; border: 1px solid #ebebf0; padding: 18px 20px; box-shadow: 0 1px 4px rgba(0,0,0,.04); display: flex; flex-direction: column; gap: 8px; }
.stat-card-danger { border-color: #fee2e2; background: #fff8f8; }
.stat-label { font-size: 12px; font-weight: 600; color: #888; text-transform: uppercase; letter-spacing: 0.04em; }
.stat-value { font-size: 32px; font-weight: 800; color: #1a1a2e; line-height: 1; }
.stat-value.danger { color: #dc2626; }

.card { background: #fff; border-radius: 16px; border: 1px solid #ebebf0; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,.04); }
.card-header { padding: 14px 18px; display: flex; align-items: center; gap: 12px; border-bottom: 1px solid #f0f0f5; }
.card-header h3 { font-size: 15px; font-weight: 700; color: #1a1a2e; }

.data-table { width: 100%; border-collapse: separate; border-spacing: 0; }
.data-table thead tr { background: #f8f8fc; }
.data-table th { text-align: left; padding: 12px 16px; font-size: 11px; font-weight: 600; color: #aaa; border-bottom: 1px solid #f0f0f5; text-transform: uppercase; letter-spacing: 0.04em; }
.data-table td { padding: 12px 16px; font-size: 13px; color: #444; border-bottom: 1px solid #f5f5f8; vertical-align: middle; }
.data-table tr:last-child td { border-bottom: none; }
.data-table tr:hover td { background: #fafafe; }
.data-table tr.row-late td { background: #fff8f8; }
.data-table tr.row-late:hover td { background: #fff0f0; }
.empty-row { text-align: center !important; color: #aaa; padding: 32px 0 !important; font-style: italic; }
.row-name { font-weight: 600; color: #1a1a2e; }
.mono-chip { font-family: 'SF Mono', Menlo, Consolas, monospace; font-size: 11.5px; background: #f0f0f5; padding: 2px 7px; border-radius: 5px; color: #555; }

.status-pill { display: inline-flex; align-items: center; gap: 5px; padding: 3px 10px; border-radius: 999px; font-size: 11px; font-weight: 600; }
.status-pill .dot { width: 5px; height: 5px; border-radius: 50%; background: currentColor; }
.status-planned { background: #eff6ff; color: #3b82f6; }
.status-active { background: #dcfce7; color: #16a34a; }

.late-badge { display: inline-block; margin-left: 6px; padding: 2px 7px; background: #fee2e2; color: #dc2626; border-radius: 999px; font-size: 10px; font-weight: 700; }

.table-action-btn { background: #f3f4f6; border: none; cursor: pointer; font-size: 12px; padding: 5px 12px; border-radius: 6px; color: #6b7280; font-weight: 600; transition: all .15s; }
.table-action-btn:hover { background: rgb(var(--color-primary-soft)); color: rgb(var(--color-primary)); }
</style>
