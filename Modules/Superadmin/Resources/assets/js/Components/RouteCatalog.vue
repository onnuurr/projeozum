<script setup>
import { computed, ref } from 'vue'
import draggable from 'vuedraggable'
import { OzCard, OzInput, OzIcon, OzButton, OzEmptyState } from '@/Components/Oz'

// Rota kataloğu: henüz menüde olmayan rotalar (RouteCatalog::forMenu), modüle göre gruplu.
// Satırı menü ağacına sürüklemek ya da + ile eklemek yeni menü öğesi oluşturur.
const props = defineProps({
	routes: { type: Array, default: () => [] },
	disabled: { type: Boolean, default: false },
})
const emit = defineEmits(['add'])

const search = ref('')
const groups = computed(() => {
	const q = search.value.trim().toLocaleLowerCase('tr')
	const list = q ? props.routes.filter((r) => `${r.name} ${r.label} ${r.uri}`.toLocaleLowerCase('tr').includes(q)) : props.routes
	const byModule = new Map()
	for (const r of list) {
		if (!byModule.has(r.module)) byModule.set(r.module, [])
		byModule.get(r.module).push(r)
	}
	return [...byModule.entries()].map(([module, items]) => ({ module, items }))
})

// Ağaca düşecek taslak düğüm: id yok, __route ile MenuTree'ye taşınır
const cloneRoute = (route) => ({ id: null, label: route.label, route_name: route.name, url: '', permission: null, is_active: true, children: [], __route: route })
</script>

<template>
	<OzCard title="Rota kataloğu" :meta="`${routes.length} rota`" class="catalog">
		<div class="cat-search">
			<OzInput v-model="search" icon="search" type="search" placeholder="Rota ya da sayfa ara…" aria-label="Rota ara" />
			<p class="hint"><OzIcon name="more-vertical" :size="12" /> ile ağaca sürükleyin ya da + ile kök menü olarak ekleyin</p>
		</div>
		<div class="cat-list">
			<OzEmptyState v-if="!groups.length" icon="search" :title="search ? 'Eşleşen rota yok' : 'Tüm rotalar menüde'" compact />
			<div v-for="g in groups" :key="g.module" class="cat-group">
				<div class="cat-module">{{ g.module }}</div>
				<draggable
					:list="g.items"
					:group="{ name: 'menus', pull: 'clone', put: false }"
					:clone="cloneRoute"
					:sort="false"
					:disabled="disabled"
					item-key="name"
					handle=".grip"
					class="cat-items"
				>
					<template #item="{ element }">
						<div class="cat-row" :title="element.name">
							<span class="grip" aria-hidden="true"><OzIcon name="more-vertical" :size="14" /></span>
							<div class="text">
								<b>{{ element.label }}</b>
								<span>{{ element.uri }}</span>
							</div>
							<OzButton size="sm" icon="plus" :disabled="disabled" :aria-label="`${element.label} menüye ekle`" @click="emit('add', element)" />
						</div>
					</template>
				</draggable>
			</div>
		</div>
	</OzCard>
</template>

<style scoped>
.cat-search { padding: 0 16px 8px; }
.hint { display: flex; align-items: center; gap: 4px; margin: 8px 0 0; font-size: 11px; color: var(--oz-muted); }
.cat-list { max-height: calc(100dvh - 330px); min-height: 200px; overflow-y: auto; padding: 0 8px 10px; }
.cat-group + .cat-group { margin-top: 6px; }
.cat-module { position: sticky; top: 0; z-index: 1; padding: 8px 8px 4px; background: var(--oz-surface); font-size: 10.5px; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; color: var(--oz-muted-2); }
.cat-row { display: flex; align-items: center; gap: 8px; padding: 7px 8px; border-radius: 9px; }
.cat-row:hover { background: var(--oz-surface-2); }
.grip { display: flex; color: var(--oz-muted-2); cursor: grab; }
.text { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 1px; }
.text b { font-size: 12.5px; font-weight: 600; color: var(--oz-ink-soft); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.text span { font-size: 11px; color: var(--oz-muted); font-family: ui-monospace, monospace; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
</style>
