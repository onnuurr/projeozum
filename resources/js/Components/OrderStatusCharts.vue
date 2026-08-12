<template>
	<div class="bottom-card">
		<div class="bottom-card-header">
			<h3>{{ title }}</h3>
			<div class="bc-actions">
				<button class="wf-action-btn">+</button>
				<button class="wf-action-btn">
					<svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
						<circle cx="18" cy="5" r="3" /><circle cx="6" cy="12" r="3" /><circle cx="18" cy="19" r="3" />
						<path d="M8.59 13.51l6.83 3.98M15.41 6.51l-6.82 3.98" />
					</svg>
				</button>
				<button class="wf-action-btn">
					<svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
						<rect x="3" y="4" width="18" height="18" rx="2" />
						<line x1="3" y1="10" x2="21" y2="10" />
					</svg>
				</button>
			</div>
		</div>
		<div class="chart-area">
			<div v-for="item in items" :key="item.label" class="chart-item">
				<div class="chart-num">{{ item.value }}</div>
				<div class="chart-canvas-wrap">
					<Chart
						type="doughnut"
						:data="[item.value, Math.max(total - item.value, 0)]"
						:colors="[item.color, '#f0f0f6']"
						cutout="72%"
						:legend="false"
						:tooltip="false"
						height="100px"
					/>
					<div class="chart-label-over">{{ item.label }}</div>
				</div>
			</div>
		</div>
	</div>
</template>

<script setup>
import Chart from '@/Components/Chart.vue'

defineProps({
	title: { type: String, default: 'Sipariş Takibi' },
	items: { type: Array, required: true },
	total: { type: Number, default: 16 },
})
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

.chart-area {
	padding: 16px;
	display: flex;
	gap: 20px;
	align-items: center;
	justify-content: center;
}
.chart-item {
	display: flex;
	flex-direction: column;
	align-items: center;
	gap: 6px;
}
.chart-num {
	font-size: 26px;
	font-weight: 800;
	color: #1a1a2e;
	line-height: 1;
}
.chart-canvas-wrap {
	position: relative;
	width: 100px;
	height: 100px;
}
.chart-label-over {
	position: absolute;
	top: 50%; left: 50%;
	transform: translate(-50%, -50%);
	font-size: 10px;
	font-weight: 700;
	color: #555;
	text-align: center;
	line-height: 1.3;
}

@media (max-width: 640px) {
	.chart-area { flex-wrap: wrap; gap: 14px; }
}
</style>
