<template>
	<div class="bottom-card">
		<div class="bottom-card-header">
			<h3>{{ title }}</h3>
			<div class="bc-actions">
				<button class="wf-action-btn" title="Ekle">+</button>
				<button class="wf-action-btn" title="Paylaş">
					<svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
						<circle cx="18" cy="5" r="3" /><circle cx="6" cy="12" r="3" /><circle cx="18" cy="19" r="3" />
						<path d="M8.59 13.51l6.83 3.98M15.41 6.51l-6.82 3.98" />
					</svg>
				</button>
				<button class="wf-action-btn" title="Takvim">
					<svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
						<rect x="3" y="4" width="18" height="18" rx="2" />
						<line x1="3" y1="10" x2="21" y2="10" />
					</svg>
				</button>
			</div>
		</div>
		<div class="table-scroll">
		<table class="data-table">
			<thead>
				<tr>
					<th></th>
					<th>Konu</th>
					<th>Durum</th>
					<th>Başlangıç</th>
					<th>Bitiş</th>
					<th>Sorumlu</th>
				</tr>
			</thead>
			<tbody>
				<tr v-for="row in rows" :key="row.id">
					<td>
						<button class="star-btn" :class="{ active: row.starred }" @click="toggleStar(row)">
							{{ row.starred ? '★' : '☆' }}
						</button>
					</td>
					<td>{{ row.subject }}</td>
					<td><Badge :color="statusColorMap[row.statusClass]" :label="row.status" variant="tonal" /></td>
					<td class="dim">{{ row.start }}</td>
					<td class="dim">{{ row.end }}</td>
					<td class="cell-name">{{ row.owner }}</td>
				</tr>
			</tbody>
		</table>
		</div>
	</div>
</template>

<script setup>
import Badge from '@/Components/Badge.vue'

const props = defineProps({
	title: { type: String, default: 'Üretim Planı' },
	rows: { type: Array, required: true },
})

const emit = defineEmits(['toggle-star'])

const statusColorMap = {
	done: 'success',
	scheduled: 'warning',
	active: 'danger',
	progress: 'info',
}

function toggleStar(row) {
	emit('toggle-star', row.id)
}
</script>

<style scoped>
.bottom-card {
	background: #fff;
	border-radius: 16px;
	border: 1px solid #ebebf0;
	overflow: hidden;
	box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
}
.bottom-card-header {
	padding: 13px 18px;
	display: flex; align-items: center; justify-content: space-between;
	border-bottom: 1px solid #f0f0f5;
}
.bottom-card-header h3 { font-size: 14px; font-weight: 700; color: #1a1a2e; }
.bc-actions { display: flex; gap: 6px; }

.data-table {
	width: 100%;
	border-collapse: collapse;
}
.data-table thead tr { background: #f8f8fc; }
.data-table th {
	text-align: left;
	padding: 9px 14px;
	font-size: 11px; font-weight: 600;
	color: #aaa;
	border-bottom: 1px solid #f0f0f5;
}
.data-table td {
	padding: 9px 14px;
	font-size: 12px;
	color: #444;
	border-bottom: 1px solid #f5f5f8;
}
.data-table tr:last-child td { border-bottom: none; }
.data-table tr:hover td { background: #fafafe; }

.cell-name { font-size: 12px; }
.dim { font-size: 11px; color: #888; }

.star-btn {
	background: none; border: none; cursor: pointer;
	color: #ddd; font-size: 13px; margin-right: 4px;
}
.star-btn:hover, .star-btn.active { color: #f7b731; }
</style>
