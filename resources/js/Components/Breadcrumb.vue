<template>
	<nav class="flex items-center flex-wrap gap-1.5" aria-label="Sayfa konumu">
		<template v-for="(item, idx) in displayItems" :key="idx">
			<!-- Daraltılmış ara öğeler ("…") -->
			<span v-if="item.collapsed" class="breadcrumb-item breadcrumb-static select-none">
				{{ item.label }}
			</span>

			<!-- Tıklanabilir ara öğeler -->
			<Link
				v-else-if="item.to && idx < displayItems.length - 1"
				:href="item.to"
				class="breadcrumb-item"
			>
				<UiIcon v-if="item.icon" :name="item.icon" :size="13" />
				<span>{{ item.label }}</span>
			</Link>

			<!-- Tıklanamaz ara öğeler -->
			<span
				v-else-if="idx < displayItems.length - 1"
				class="breadcrumb-item breadcrumb-static"
			>
				<UiIcon v-if="item.icon" :name="item.icon" :size="13" />
				<span>{{ item.label }}</span>
			</span>

			<!-- Aktif (son) öğe -->
			<span v-else class="breadcrumb-item breadcrumb-active" aria-current="page">
				<UiIcon v-if="item.icon" :name="item.icon" :size="13" />
				<span>{{ item.label }}</span>
			</span>

			<!-- Ayraç -->
			<svg
				v-if="idx < displayItems.length - 1"
				class="breadcrumb-separator"
				width="12"
				height="12"
				fill="none"
				stroke="currentColor"
				stroke-width="2"
				viewBox="0 0 24 24"
			>
				<path d="M9 18l6-6-6-6" />
			</svg>
		</template>
	</nav>
</template>

<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import UiIcon from '@/Components/UiIcon.vue'

const props = defineProps({
	items: { type: Array, required: true },
	// 0 = sınırsız (varsayılan). Verilirse baş + "…" + kuyruk şeklinde daraltılır.
	maxVisible: { type: Number, default: 0 },
})

const displayItems = computed(() => {
	if (!props.maxVisible || props.items.length <= props.maxVisible) {
		return props.items
	}

	const tailCount = Math.max(1, props.maxVisible - 1)
	const tail = props.items.slice(-tailCount)

	return [props.items[0], { label: '…', collapsed: true }, ...tail]
})
</script>

<style scoped>
.breadcrumb {
	display: inline-flex;
	align-items: center;
	gap: 4px;
	padding: 6px 12px;
	background: #fff;
	border: 1px solid var(--color-line);
	border-radius: 999px;
	box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
	margin-bottom: 14px;
}

.breadcrumb-item {
	display: inline-flex;
	align-items: center;
	gap: 5px;
	padding: 4px 8px;
	border-radius: 7px;
	font-size: 12px;
	font-weight: 500;
	color: var(--color-muted);
	text-decoration: none;
	line-height: 1;
	transition: background .12s, color .12s;
}

.breadcrumb-item span {
	line-height: 1;
}

.breadcrumb-item :deep(svg) {
	flex-shrink: 0;
	opacity: .75;
	transition: opacity .12s;
}

/* Linklere hover */
a.breadcrumb-item:hover {
	background: var(--color-primary-soft);
	color: var(--color-primary);
}
a.breadcrumb-item:hover :deep(svg) {
	opacity: 1;
}

/* Statik (tıklanamaz) öğeler */
.breadcrumb-static {
	color: var(--color-muted);
	cursor: default;
}

/* Aktif öğe */
.breadcrumb-active {
	color: var(--color-ink);
	font-weight: 600;
	background: var(--color-canvas);
	cursor: default;
}
.breadcrumb-active :deep(svg) {
	opacity: 1;
}

.breadcrumb-separator {
	color: var(--color-line);
	flex-shrink: 0;
	margin: 0 1px;
}

/* ── Dar ekran (telefon): uzun breadcrumb zincirini sarmak yerine kaydır ── */
@media (max-width: 640px) {
	.breadcrumb {
		max-width: 100%;
		overflow-x: auto;
		-webkit-overflow-scrolling: touch;
	}
}
</style>
