<template>
	<aside class="left-sidebar">
		<component
			v-for="btn in topButtons"
			:key="btn.label"
			:is="btn.to ? Link : 'button'"
			:href="btn.to || undefined"
			class="sb-btn"
			:class="{ active: btn.active }"
			:title="btn.label"
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
</template>

<script setup>
import { ref } from 'vue'
import { Link } from '@inertiajs/vue3'

defineProps({
	topButtons: { type: Array, required: true },
	bottomButtons: { type: Array, required: true },
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
.sb-btn:hover { background: rgb(var(--color-primary-soft)); color: rgb(var(--color-primary)); }
.sb-btn.active {
	background: rgb(var(--color-primary));
	color: #fff;
	box-shadow: 0 4px 12px rgb(var(--color-primary) / .35);
}
.sb-btn,
.sb-btn:link,
.sb-btn:visited { text-decoration: none; }
.sb-btn.theme-btn {
	width: 30px;
	height: 30px;
	background: rgb(var(--color-ink));
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
</style>
