<template>
	<section class="workflow-section">
		<div class="workflow-header">
			<h2>{{ title }}</h2>
			<div class="team-avatars">
				<div v-for="m in team" :key="m.id" class="av-badge">
					<div class="team-av" :style="{ background: m.color, color: m.textColor || '#fff' }">{{ m.initials }}</div>
					<span v-if="m.count" class="badge-count" :style="{ background: m.countColor || '#f05a5a' }">{{ m.count }}</span>
				</div>
			</div>
			<div class="wf-actions">
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
						<line x1="16" y1="2" x2="16" y2="6" />
						<line x1="8" y1="2" x2="8" y2="6" />
						<line x1="3" y1="10" x2="21" y2="10" />
					</svg>
				</button>
			</div>
		</div>

		<div class="workflow-body">
			<div class="wf-columns-wrapper">
				<template v-for="(col, idx) in columns" :key="col.label">
					<div class="wf-col">
						<!-- Sütun 1: task-card -->
						<template v-if="col.variant === 'card'">
							<div v-for="task in col.items" :key="task.id" class="task-card">
								<div class="task-card-header">
									<div class="task-av" :style="{ background: task.color }">{{ task.initials }}</div>
									<div class="task-icon-btns">
										<button class="task-icon-btn" title="Tamamla">✓</button>
										<button class="task-icon-btn" title="Takvim">
											<svg width="10" height="10" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
												<rect x="3" y="4" width="18" height="18" rx="2" />
												<line x1="3" y1="10" x2="21" y2="10" />
											</svg>
										</button>
									</div>
								</div>
								<p>{{ task.text }}</p>
							</div>
						</template>

						<!-- Sütun 2-3: task-list-item -->
						<template v-else-if="col.variant === 'list'">
							<div
								v-for="task in col.items"
								:key="task.id"
								class="task-list-item"
								:class="{ bold: task.bold, highlight: task.highlight }"
							>
								<div
									v-if="task.color"
									class="task-av"
									:style="{ background: task.color, color: task.textColor || '#fff' }"
								>{{ task.initials }}</div>
								<div v-else class="task-empty-circle">+</div>
								<span>{{ task.text }}</span>
								<div class="tli-actions">
									<button v-if="task.alert" style="color: #f05a5a">●</button>
									<button v-else-if="task.menu">···</button>
									<template v-else>
										<button>✓</button>
										<button>
											<svg width="10" height="10" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
												<rect x="3" y="4" width="18" height="18" rx="2" />
												<line x1="3" y1="10" x2="21" y2="10" />
											</svg>
										</button>
									</template>
								</div>
							</div>
						</template>

						<!-- Sütun 4: active card + mini grid -->
						<template v-else-if="col.variant === 'cta'">
							<div class="active-task-card">
								<span v-html="col.cta.text"></span>
								<button class="arrow-btn">›</button>
							</div>
							<div class="task-mini-grid">
								<div v-for="m in col.mini" :key="m" class="mini-task" v-html="m"></div>
							</div>
						</template>
					</div>
					<div v-if="idx < columns.length - 1" class="wf-connector">
						<div class="conn-arrow">›</div>
					</div>
				</template>
			</div>

			<div class="col-labels">
				<template v-for="(col, idx) in columns" :key="col.label">
					<div class="col-label">{{ col.label }}</div>
					<div v-if="idx < columns.length - 1" class="col-spacer"></div>
				</template>
			</div>
		</div>
	</section>
</template>

<script setup>
defineProps({
	title: { type: String, default: 'Yeni Sipariş Yönetimi' },
	team: { type: Array, required: true },
	columns: { type: Array, required: true },
})
</script>

<style scoped>
.workflow-section {
	background: #fff;
	border-radius: 16px;
	border: 1px solid #ebebf0;
	overflow: hidden;
	margin-bottom: 14px;
	box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
}
.workflow-header {
	padding: 14px 18px;
	display: flex;
	align-items: center;
	gap: 14px;
	border-bottom: 1px solid #f0f0f5;
}
.workflow-header h2 {
	font-size: 15px;
	font-weight: 700;
	color: #1a1a2e;
	white-space: nowrap;
}

.team-avatars { display: flex; margin-left: 8px; }
.team-av {
	width: 30px; height: 30px; border-radius: 50%;
	border: 2px solid #fff;
	margin-left: -8px;
	display: flex; align-items: center; justify-content: center;
	font-size: 9px; font-weight: 700; color: #fff;
	cursor: pointer; flex-shrink: 0;
}
.av-badge { position: relative; margin-left: -8px; }
.av-badge .badge-count {
	position: absolute; bottom: -2px; right: -2px;
	width: 14px; height: 14px; border-radius: 50%;
	font-size: 7.5px; font-weight: 800;
	border: 1.5px solid #fff;
	display: flex; align-items: center; justify-content: center;
	color: #fff;
}

.wf-actions { margin-left: auto; display: flex; gap: 6px; }

.workflow-body { padding: 14px; }
.wf-columns-wrapper {
	display: flex;
	align-items: stretch;
	gap: 0;
	position: relative;
}
.wf-col {
	flex: 1;
	background: #f8f8fc;
	border-radius: 12px;
	border: 1.5px dashed #d8d8ea;
	padding: 10px;
	display: flex;
	flex-direction: column;
	gap: 8px;
	min-width: 0;
}
.wf-connector {
	display: flex; flex-direction: column;
	align-items: center; justify-content: center;
	width: 28px; flex-shrink: 0;
	position: relative; gap: 4px;
}
.wf-connector::before {
	content: '';
	position: absolute;
	top: 50%; left: 50%;
	width: 2px; height: 60%;
	background: transparent;
	border-left: 2px dashed #c8c8d8;
	transform: translate(-50%, -50%);
}
.conn-arrow {
	width: 20px; height: 20px;
	background: #fff;
	border: 1.5px solid #d0d0e0;
	border-radius: 50%;
	display: flex; align-items: center; justify-content: center;
	font-size: 10px; color: #888;
	z-index: 1; position: relative;
}

.task-card {
	background: #fff;
	border-radius: 10px;
	border: 1px solid #e8e8f0;
	padding: 10px;
	box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
}
.task-card-header {
	display: flex; align-items: center; justify-content: space-between;
	margin-bottom: 8px;
}
.task-av {
	width: 28px; height: 28px;
	border-radius: 50%;
	display: flex; align-items: center; justify-content: center;
	font-size: 9px; font-weight: 700; color: #fff;
	flex-shrink: 0;
}
.task-icon-btns { display: flex; gap: 4px; }
.task-icon-btn {
	width: 24px; height: 24px;
	background: none; border: 1px solid #e8e8f0;
	border-radius: 6px; cursor: pointer;
	display: flex; align-items: center; justify-content: center;
	color: #aaa; font-size: 10px;
}
.task-card p { font-size: 11px; color: #444; line-height: 1.4; }

.task-list-item {
	background: #fff;
	border-radius: 9px;
	border: 1px solid #e8e8f0;
	padding: 8px 9px;
	display: flex; align-items: center; gap: 7px;
	box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
}
.task-list-item .task-av {
	width: 24px; height: 24px; font-size: 8px;
}
.task-list-item span {
	font-size: 10.5px;
	color: #333;
	font-weight: 500;
	flex: 1;
	line-height: 1.3;
}
.task-list-item.bold span { font-weight: 700; font-size: 11px; }
.task-list-item.highlight { border-color: #c8d8ff; background: #f4f7ff; }
.task-list-item.highlight span { color: #2a4fb5; font-weight: 700; }
.task-empty-circle {
	width: 18px; height: 18px;
	background: none;
	border: 1.5px dashed #ccc;
	border-radius: 50%;
	display: flex; align-items: center; justify-content: center;
	flex-shrink: 0; cursor: pointer;
	font-size: 9px; color: #aaa;
}
.tli-actions { display: flex; gap: 3px; }
.tli-actions button {
	background: none; border: none; cursor: pointer;
	color: #bbb; font-size: 10px;
}

.active-task-card {
	background: #1a1a2e;
	border-radius: 10px;
	padding: 10px 12px;
	display: flex; align-items: center; justify-content: space-between;
}
.active-task-card span {
	font-size: 11.5px;
	font-weight: 700;
	color: #fff;
	line-height: 1.3;
}
.arrow-btn {
	width: 22px; height: 22px;
	background: rgba(255, 255, 255, 0.15);
	border: none; border-radius: 50%;
	cursor: pointer; color: #fff;
	font-size: 10px;
	display: flex; align-items: center; justify-content: center;
}
.task-mini-grid {
	display: grid;
	grid-template-columns: 1fr 1fr;
	gap: 6px;
}
.mini-task {
	background: #fff;
	border: 1px solid #e8e8f0;
	border-radius: 9px;
	padding: 9px 8px;
	font-size: 10px;
	font-weight: 600;
	color: #444;
	line-height: 1.4;
	box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
}

.col-labels { display: flex; margin-top: 10px; padding: 0 2px; }
.col-label {
	flex: 1; text-align: center;
	font-size: 11.5px; font-weight: 600; color: #888;
}
.col-spacer { width: 28px; flex-shrink: 0; }
</style>
