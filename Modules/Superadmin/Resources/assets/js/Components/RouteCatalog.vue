<template>
	<aside class="rc">
		<div class="rc-head">
			<h2>Route Kataloğu</h2>
			<p>Bir route'u sola, menü ağacına sürükle.</p>
		</div>

		<input
			v-model="search"
			type="search"
			class="rc-search"
			placeholder="Route ara…"
		/>

		<div v-if="!groups.length" class="rc-empty">
			Eşleşen route yok.
		</div>

		<div v-for="group in groups" :key="group.module" class="rc-group">
			<div class="rc-group-title">{{ group.module }}</div>

			<draggable
				:list="group.items"
				:group="{ name: 'menus', pull: 'clone', put: false }"
				:clone="cloneRoute"
				:sort="false"
				item-key="name"
				class="rc-items"
			>
				<template #item="{ element }">
					<div class="rc-item" :title="element.name">
						<span class="rc-grip">⋮⋮</span>
						<span class="rc-label">{{ element.label }}</span>
						<span class="rc-uri">{{ element.uri }}</span>
					</div>
				</template>
			</draggable>
		</div>
	</aside>
</template>

<script setup>
import { computed, ref } from 'vue'
import draggable from 'vuedraggable'

const props = defineProps({
	routes: { type: Array, default: () => [] },
})

const search = ref('')

// Arama filtresi + modüle göre gruplama.
const groups = computed(() => {
	const q = search.value.trim().toLowerCase()
	const filtered = q
		? props.routes.filter((r) =>
			`${r.name} ${r.label} ${r.uri}`.toLowerCase().includes(q))
		: props.routes

	const byModule = new Map()
	for (const r of filtered) {
		if (!byModule.has(r.module)) byModule.set(r.module, [])
		byModule.get(r.module).push(r)
	}
	return [...byModule.entries()].map(([module, items]) => ({ module, items }))
})

// Ağaca düşecek taslak düğüm: id yok, __route ile MenuTree'ye taşınır.
function cloneRoute(route) {
	return {
		id: null,
		label: route.label,
		route_name: route.name,
		url: '',
		permission: null,
		is_active: true,
		children: [],
		__route: route,
	}
}
</script>

<style scoped>
.rc {
	border: 1px solid #ebebf0; border-radius: 12px;
	background: #fafafe; padding: 14px;
	max-height: calc(100vh - 160px); overflow-y: auto;
	align-self: start;
}
.rc-head h2 { font-size: 15px; font-weight: 700; color: #1a1a2e; }
.rc-head p { font-size: 12px; color: #999; margin-top: 2px; }
.rc-search {
	width: 100%; margin: 12px 0; box-sizing: border-box;
	border: 1px solid #e0e0ea; border-radius: 8px; padding: 8px 10px; font-size: 13px;
}
.rc-empty { font-size: 12.5px; color: #aaa; padding: 12px 4px; }
.rc-group { margin-bottom: 14px; }
.rc-group-title {
	font-size: 11px; font-weight: 700; text-transform: uppercase;
	letter-spacing: .04em; color: #8a8aa0; margin-bottom: 6px;
}
.rc-items { display: flex; flex-direction: column; gap: 4px; min-height: 4px; }
.rc-item {
	display: flex; align-items: center; gap: 8px;
	padding: 6px 9px; background: #fff;
	border: 1px solid #ebebf0; border-radius: 8px;
	cursor: grab; user-select: none;
}
.rc-item:hover { border-color: #c8c8d8; }
.rc-grip { color: #c4c4d0; font-size: 11px; }
.rc-label { font-size: 12.5px; font-weight: 500; color: #1a1a2e; }
.rc-uri {
	margin-left: auto; font-size: 10.5px; color: #aaa;
	font-family: ui-monospace, monospace;
	max-width: 45%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}

@media (max-width: 640px) {
	.rc { padding: 10px; }
	.rc-uri { display: none; }
}
</style>
