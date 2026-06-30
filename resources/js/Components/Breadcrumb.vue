<template>
	<nav class="breadcrumb" aria-label="Sayfa konumu">
		<template v-for="(item, idx) in items" :key="item.label">
			<!-- Tıklanabilir ara öğeler -->
			<Link
				v-if="item.to && idx < items.length - 1"
				:href="item.to"
				class="breadcrumb-item"
			>
				<svg v-if="item.icon === 'home'" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
					<path d="M3 9l9-7 9 7v11a2 2 0 01-2 2h-4M9 22V12h6v10" />
				</svg>
				<span>{{ item.label }}</span>
			</Link>

			<!-- Tıklanamaz ara öğeler -->
			<span
				v-else-if="idx < items.length - 1"
				class="breadcrumb-item breadcrumb-static"
			>
				<svg v-if="item.icon === 'home'" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
					<path d="M3 9l9-7 9 7v11a2 2 0 01-2 2h-4M9 22V12h6v10" />
				</svg>
				<span>{{ item.label }}</span>
			</span>

			<!-- Aktif (son) öğe -->
			<span v-else class="breadcrumb-item breadcrumb-active" aria-current="page">
				<svg v-if="item.icon === 'home'" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
					<path d="M3 9l9-7 9 7v11a2 2 0 01-2 2h-4M9 22V12h6v10" />
				</svg>
				<span>{{ item.label }}</span>
			</span>

			<!-- Ayraç -->
			<svg
				v-if="idx < items.length - 1"
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
import { Link } from '@inertiajs/vue3'

defineProps({
	items: { type: Array, required: true },
})
</script>

<style scoped>
.breadcrumb {
	display: inline-flex;
	align-items: center;
	gap: 4px;
	padding: 6px 12px;
	background: #fff;
	border: 1px solid #ebebf0;
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
	color: #888;
	text-decoration: none;
	line-height: 1;
	transition: background .12s, color .12s;
}

.breadcrumb-item span {
	line-height: 1;
}

.breadcrumb-item svg {
	flex-shrink: 0;
	opacity: .75;
	transition: opacity .12s;
}

/* Linklere hover */
a.breadcrumb-item:hover {
	background: rgb(var(--color-primary-soft));
	color: rgb(var(--color-primary));
}
a.breadcrumb-item:hover svg {
	opacity: 1;
}

/* Statik (tıklanamaz) öğeler */
.breadcrumb-static {
	color: #888;
	cursor: default;
}

/* Aktif öğe */
.breadcrumb-active {
	color: #1a1a2e;
	font-weight: 600;
	background: #f0f0f5;
	cursor: default;
}
.breadcrumb-active svg {
	opacity: 1;
}

.breadcrumb-separator {
	color: #ccc;
	flex-shrink: 0;
	margin: 0 1px;
}
</style>
