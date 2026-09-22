<template>
	<aside class="left-sidebar">
		<component
			v-for="btn in topButtons"
			:key="btn.label"
			:is="btn.to && !(btn.active && navItems.length) ? Link : 'button'"
			:href="btn.to && !(btn.active && navItems.length) ? btn.to : undefined"
			class="sb-btn"
			:class="{ active: btn.active }"
			:title="btn.label"
			@click="btn.active && navItems.length ? togglePanel($event) : undefined"
			@mouseenter="showTooltip($event, btn.label)"
			@mouseleave="hideTooltip"
		>
			<span v-html="btn.icon"></span>
		</component>
		<div class="sb-spacer"></div>
		<button
			v-for="btn in bottomButtons"
			:key="btn.label"
			class="sb-btn"
			:class="{ 'theme-btn': btn.theme }"
			:title="btn.label"
			@mouseenter="showTooltip($event, btn.label)"
			@mouseleave="hideTooltip"
		>
			<span v-html="btn.icon"></span>
		</button>

		<Teleport to="body">
			<div class="sb-tooltip" :class="{ visible: tooltip.visible }" :style="tooltip.style">
				{{ tooltip.text }}
			</div>
		</Teleport>
	</aside>

	<!-- Aktif modülün sayfa ağacı (navItems): eskiden header'da ikon-kare flyout'tu
	     (TopNav.vue nav-dropdown/nav-subdropdown), okunabilirlik şikayeti üzerine
	     buraya, sidebar'ın aktif ikonundan açılan etiketli bir panele taşındı. -->
	<Teleport to="body">
		<div class="sb-panel" :class="{ open: panelOpen }" :style="panelStyle" @click.stop>
			<div class="sb-panel-list">
				<div v-for="item in navItems" :key="item.name" class="sb-panel-node">
					<component
						:is="item.to && !item.children?.length ? Link : 'button'"
						:href="item.to || undefined"
						type="button"
						class="sb-panel-row"
						:class="{ active: item.active, open: expandedNode === item.name }"
						@click="item.children?.length ? toggleNode(item.name) : closePanel()"
					>
						<span class="sb-panel-icon" v-html="item.icon"></span>
						<span class="sb-panel-label">{{ item.name }}</span>
						<span v-if="item.children?.length" class="sb-panel-chevron" :class="{ open: expandedNode === item.name }">&#8250;</span>
					</component>
					<div v-if="item.children?.length" class="sb-panel-children" :class="{ open: expandedNode === item.name }">
						<div class="sb-panel-children-inner">
							<template v-for="child in item.children" :key="child.label">
								<component
									:is="child.to && !child.children?.length ? Link : 'button'"
									:href="child.to || undefined"
									type="button"
									class="sb-panel-subrow"
									:class="{ open: expandedSubnode === child.label }"
									@click="child.children?.length ? toggleSubnode(child.label) : closePanel()"
								>
									<span v-html="child.icon"></span>
									<span>{{ child.label }}</span>
									<span v-if="child.children?.length" class="sb-panel-chevron" :class="{ open: expandedSubnode === child.label }">&#8250;</span>
								</component>
								<div v-if="child.children?.length" class="sb-panel-grandchildren" :class="{ open: expandedSubnode === child.label }">
									<div class="sb-panel-grandchildren-inner">
										<Link v-for="sub in child.children" :key="sub.label" :href="sub.to" class="sb-panel-leaf" @click="closePanel()">
											<span v-html="sub.icon"></span>
											<span>{{ sub.label }}</span>
										</Link>
									</div>
								</div>
							</template>
						</div>
					</div>
				</div>
			</div>
		</div>
	</Teleport>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue'
import { Link } from '@inertiajs/vue3'

defineProps({
	topButtons: { type: Array, required: true },
	bottomButtons: { type: Array, required: true },
	navItems: { type: Array, default: () => [] },
})

const tooltip = ref({ visible: false, text: '', style: {} })

function showTooltip(e, label) {
	const r = e.currentTarget.getBoundingClientRect()
	tooltip.value = {
		visible: true,
		text: label,
		style: {
			top: r.top + r.height / 2 + 'px',
			left: r.right + 10 + 'px',
		},
	}
}

function hideTooltip() {
	tooltip.value.visible = false
}

/* ── Aktif modülün sayfa paneli ── */
const panelOpen = ref(false)
const panelStyle = ref({})
const expandedNode = ref(null)
const expandedSubnode = ref(null)

function togglePanel(e) {
	panelOpen.value = !panelOpen.value
	if (panelOpen.value) {
		const r = e.currentTarget.getBoundingClientRect()
		panelStyle.value = { top: r.top + 'px', left: r.right + 8 + 'px' }
	} else {
		expandedNode.value = null
		expandedSubnode.value = null
	}
}

function closePanel() {
	panelOpen.value = false
	expandedNode.value = null
	expandedSubnode.value = null
}

function toggleNode(name) {
	expandedNode.value = expandedNode.value === name ? null : name
	expandedSubnode.value = null
}

function toggleSubnode(label) {
	expandedSubnode.value = expandedSubnode.value === label ? null : label
}

function handleGlobalClick(e) {
	if (!e.target.closest('.sb-btn') && !e.target.closest('.sb-panel')) closePanel()
}

onMounted(() => document.addEventListener('click', handleGlobalClick))
onBeforeUnmount(() => document.removeEventListener('click', handleGlobalClick))
</script>

<style scoped>
.left-sidebar {
	grid-column: 1;
	grid-row: 2;
	width: 48px;
	background: #fff;
	display: flex;
	flex-direction: column;
	align-items: center;
	padding: 8px 0;
	gap: 2px;
	border-radius: 14px;
	box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
	align-self: stretch;
	overflow-y: auto;
	overflow-x: hidden;
	min-height: 0;
}
.left-sidebar::-webkit-scrollbar { width: 3px; }
.left-sidebar::-webkit-scrollbar-track { background: transparent; }
.left-sidebar::-webkit-scrollbar-thumb { background: #d0d0da; border-radius: 3px; }
.left-sidebar::-webkit-scrollbar-thumb:hover { background: #a0a0b0; }

.sb-btn {
	width: 34px;
	height: 34px;
	background: none;
	border: none;
	cursor: pointer;
	border-radius: 8px;
	color: #aaa;
	display: flex;
	align-items: center;
	justify-content: center;
	font-size: 14px;
	transition: all 0.15s;
	position: relative;
	flex-shrink: 0;
}
.sb-btn:hover { background: rgb(var(--color-primary-soft-rgb)); color: rgb(var(--color-primary-rgb)); }
.sb-btn.active {
	background: rgb(var(--color-primary-rgb));
	color: #fff;
	box-shadow: 0 4px 12px rgb(var(--color-primary-rgb) / .35);
}
.sb-btn,
.sb-btn:link,
.sb-btn:visited { text-decoration: none; }
.sb-btn.theme-btn {
	width: 30px;
	height: 30px;
	background: rgb(var(--color-ink-rgb));
	color: #fff;
	border-radius: 50%;
}
.sb-spacer { flex: 1; }

/* Dar ekran (telefon): sol dikey sidebar kaldırılır — kök menüler artık
   TopNav.vue'daki alt sabit sekme çubuğunda (.mobile-tab-bar) gösterilir. */
@media (max-width: 640px) {
	.left-sidebar { display: none; }
}
</style>

<style>
.sb-tooltip {
	position: fixed;
	background: #1a1a2e;
	color: #fff;
	font-size: 11.5px;
	font-weight: 500;
	font-family: inherit;
	padding: 5px 10px;
	border-radius: 7px;
	white-space: nowrap;
	pointer-events: none;
	opacity: 0;
	z-index: 10000;
	transition: opacity .15s, transform .15s;
	transform: translateY(-50%) translateX(-4px);
}
.sb-tooltip.visible {
	opacity: 1;
	transform: translateY(-50%) translateX(0);
}

/* ── Aktif modül sayfa paneli (sidebar'dan açılır flyout) ── */
.sb-panel {
	position: fixed;
	background: #fff;
	border: 1px solid #e8e8f0;
	border-radius: 14px;
	box-shadow: 0 8px 28px rgba(0, 0, 0, 0.13);
	width: 260px;
	max-height: min(70vh, 520px);
	overflow-y: auto;
	padding: 6px;
	opacity: 0;
	pointer-events: none;
	transform: translateX(-6px);
	transition: opacity .15s, transform .15s;
	z-index: 9999;
}
.sb-panel.open { opacity: 1; pointer-events: all; transform: translateX(0); }
.sb-panel::-webkit-scrollbar { width: 3px; }
.sb-panel::-webkit-scrollbar-track { background: transparent; }
.sb-panel::-webkit-scrollbar-thumb { background: #e0e0ea; border-radius: 3px; }

.sb-panel-list { display: flex; flex-direction: column; }
.sb-panel-node + .sb-panel-node { border-top: 1px solid #f5f5f8; }

.sb-panel-row {
	width: 100%;
	display: flex; align-items: center; gap: 10px;
	background: none; border: none; cursor: pointer;
	padding: 9px 10px; border-radius: 10px;
	font-size: 13px; font-weight: 600; color: #333;
	text-decoration: none;
	transition: background .12s, color .12s;
}
.sb-panel-icon { display: inline-flex; flex-shrink: 0; }
.sb-panel-row :deep(svg) { width: 16px; height: 16px; flex-shrink: 0; color: #aaa; transition: color .12s; }
.sb-panel-row:hover,
.sb-panel-row.open { background: rgb(var(--color-primary-soft-rgb)); color: rgb(var(--color-primary-rgb)); }
.sb-panel-row:hover :deep(svg),
.sb-panel-row.open :deep(svg) { color: rgb(var(--color-primary-rgb)); }
.sb-panel-row.active { color: rgb(var(--color-primary-rgb)); }
.sb-panel-label { flex: 1; text-align: left; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.sb-panel-chevron {
	flex-shrink: 0;
	color: #ccc;
	font-size: 15px;
	line-height: 1;
	transition: transform .15s;
}
.sb-panel-chevron.open { transform: rotate(90deg); }

.sb-panel-children {
	display: grid;
	grid-template-rows: 0fr;
	overflow: hidden;
	transition: grid-template-rows .18s ease;
}
.sb-panel-children.open { grid-template-rows: 1fr; }
.sb-panel-children-inner { min-height: 0; padding-left: 14px; }

.sb-panel-subrow {
	width: 100%;
	display: flex; align-items: center; gap: 9px;
	background: none; border: none; cursor: pointer;
	padding: 8px 10px; border-radius: 9px;
	font-size: 12.5px; font-weight: 550; color: #555;
	text-decoration: none;
	transition: background .12s, color .12s;
}
.sb-panel-subrow :deep(svg) { width: 14px; height: 14px; flex-shrink: 0; color: #bbb; }
.sb-panel-subrow:hover,
.sb-panel-subrow.open { background: rgb(var(--color-primary-soft-rgb)); color: rgb(var(--color-primary-rgb)); }
.sb-panel-subrow:hover :deep(svg),
.sb-panel-subrow.open :deep(svg) { color: rgb(var(--color-primary-rgb)); }

.sb-panel-grandchildren {
	display: grid;
	grid-template-rows: 0fr;
	overflow: hidden;
	transition: grid-template-rows .16s ease;
}
.sb-panel-grandchildren.open { grid-template-rows: 1fr; }
.sb-panel-grandchildren-inner { min-height: 0; padding-left: 14px; }

.sb-panel-leaf {
	display: flex; align-items: center; gap: 9px;
	padding: 7px 10px; border-radius: 8px;
	font-size: 12px; font-weight: 500; color: #777;
	text-decoration: none;
	transition: background .12s, color .12s;
}
.sb-panel-leaf :deep(svg) { width: 13px; height: 13px; flex-shrink: 0; color: #ccc; }
.sb-panel-leaf:hover { background: rgb(var(--color-primary-soft-rgb)); color: rgb(var(--color-primary-rgb)); }
.sb-panel-leaf:hover :deep(svg) { color: rgb(var(--color-primary-rgb)); }
</style>
