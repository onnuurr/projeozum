<script setup>
import draggable from 'vuedraggable'
import { OzIcon, OzPill, OzButton, OzDropdown } from '@/Components/Oz'
import { ozIconForMenu } from '@/ozMenuIcons.js'

// Menü ağacı (özyinelemeli). Sürükle-bırak: aynı ya da farklı seviyeye taşıma; rota kataloğundan
// bırakılan klon (__route) yeni menü olarak `add-route` yayar. ⋯ menüsü sürüklemeden aynı işleri yapar.
const props = defineProps({
	nodes: { type: Array, required: true },
	parentId: { type: [Number, null], default: null },
	depth: { type: Number, default: 0 },
	disabled: { type: Boolean, default: false },
})
const emit = defineEmits(['changed', 'add-route', 'edit', 'add-child', 'remove', 'toggle-active'])

const hasChildren = (n) => Array.isArray(n.children) && n.children.length > 0
const target = (n) => n.route_name || n.url || ''

function onChange(evt) {
	if (evt.added?.element?.__route) {
		emit('add-route', { route: evt.added.element.__route, parentId: props.parentId, index: evt.added.newIndex })
		return
	}
	emit('changed')
}

function move(node, dir) {
	const i = props.nodes.indexOf(node)
	const j = i + dir
	if (j < 0 || j >= props.nodes.length) return
	props.nodes.splice(i, 1)
	props.nodes.splice(j, 0, node)
	emit('changed')
}

const actions = (node) => [
	{ key: 'edit', label: 'Düzenle', icon: 'edit' },
	{ key: 'child', label: 'Alt menü ekle', icon: 'plus' },
	{ key: 'toggle', label: node.is_active ? 'Pasif yap' : 'Aktif yap', icon: node.is_active ? 'eye-off' : 'eye' },
	{ divider: true },
	{ key: 'up', label: 'Yukarı taşı', icon: 'chevron-up', disabled: props.nodes.indexOf(node) === 0 },
	{ key: 'down', label: 'Aşağı taşı', icon: 'chevron-down', disabled: props.nodes.indexOf(node) === props.nodes.length - 1 },
	{ divider: true },
	{ key: 'remove', label: 'Sil', icon: 'trash', danger: true },
]
function onAction(key, node) {
	if (key === 'edit') emit('edit', node)
	else if (key === 'child') emit('add-child', node)
	else if (key === 'toggle') emit('toggle-active', node)
	else if (key === 'up') move(node, -1)
	else if (key === 'down') move(node, 1)
	else if (key === 'remove') emit('remove', node)
}
</script>

<template>
	<draggable
		:list="nodes"
		:group="{ name: 'menus' }"
		:disabled="disabled"
		item-key="id"
		handle=".grip"
		ghost-class="is-ghost"
		class="mt-list"
		:class="{ 'is-nested': depth > 0 }"
		@change="onChange"
	>
		<template #item="{ element }">
			<div class="mt-node" :class="{ 'is-root': depth === 0 }">
				<div class="mt-row" :class="{ inactive: !element.is_active }">
					<span class="grip" title="Sürükle" aria-hidden="true"><OzIcon name="more-vertical" :size="15" /></span>
					<button
						type="button"
						class="chev"
						:class="{ open: !element.__collapsed, none: !hasChildren(element) }"
						:disabled="!hasChildren(element)"
						:aria-expanded="hasChildren(element) ? !element.__collapsed : undefined"
						:aria-label="`${element.label} alt menüleri`"
						@click="element.__collapsed = !element.__collapsed"
					><OzIcon name="chevron-right" :size="14" /></button>
					<span class="ico" :class="{ sm: depth > 0, empty: !element.icon }">
						<OzIcon v-if="element.icon" :name="ozIconForMenu(element.icon)" :size="depth ? 14 : 16" />
						<span v-else class="dot" />
					</span>
					<div class="text">
						<b>{{ element.label }}</b>
						<span :class="{ soon: !target(element) }">
							{{ hasChildren(element) ? `${element.children.length} alt menü` : '' }}{{ hasChildren(element) && target(element) ? ' · ' : '' }}{{ target(element) || (hasChildren(element) ? '' : 'Bağlantı yok') }}
						</span>
					</div>
					<div class="tags">
						<OzPill v-if="!element.is_active" tone="neutral">Pasif</OzPill>
						<OzPill v-if="element.permission" tone="warning" :title="`İzin: ${element.permission}`">{{ element.permission }}</OzPill>
					</div>
					<OzButton size="sm" icon="plus" class="add-child" :aria-label="`${element.label} altına menü ekle`" :disabled="disabled" @click="emit('add-child', element)">Alt menü</OzButton>
					<OzDropdown :items="actions(element)" :label="`${element.label} işlemleri`" @select="(k) => onAction(k, element)" />
				</div>
				<MenuTree
					v-show="!element.__collapsed"
					:nodes="element.children"
					:parent-id="element.id"
					:depth="depth + 1"
					:disabled="disabled"
					class="mt-children"
					@changed="emit('changed')"
					@add-route="emit('add-route', $event)"
					@edit="emit('edit', $event)"
					@add-child="emit('add-child', $event)"
					@remove="emit('remove', $event)"
					@toggle-active="emit('toggle-active', $event)"
				/>
			</div>
		</template>
	</draggable>
</template>

<style scoped>
.mt-list { display: flex; flex-direction: column; gap: 8px; min-height: 8px; }
/* alt listeler boşken de bırakma hedefi olsun */
.mt-list.is-nested { min-height: 4px; gap: 6px; padding: 0 0 0 26px; }
.mt-children:empty { padding-top: 0; }
.mt-node.is-root { border: 1px solid var(--oz-border); border-radius: 12px; background: var(--oz-surface); }
.mt-node.is-root > .mt-children { padding: 0 10px 10px 36px; }
.mt-list.is-nested .mt-row { border: 1px solid var(--oz-border-soft); border-radius: 10px; background: var(--oz-surface-2); }
.mt-row { display: flex; align-items: center; gap: 10px; padding: 9px 10px 9px 6px; }
.grip { display: flex; color: var(--oz-muted-2); cursor: grab; padding: 2px; }
.grip:active { cursor: grabbing; }
.chev { width: 24px; height: 24px; display: flex; align-items: center; justify-content: center; border: none; border-radius: 6px; background: none; color: var(--oz-muted); cursor: pointer; transition: transform .18s, background .12s; flex-shrink: 0; }
.chev:hover:not(:disabled) { background: var(--oz-surface-2); }
.chev.open { transform: rotate(90deg); }
.chev.none { visibility: hidden; }
.ico { width: 34px; height: 34px; border-radius: 10px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; background: var(--oz-accent-soft); color: var(--oz-accent); }
.ico.sm { width: 28px; height: 28px; border-radius: 8px; background: var(--oz-surface); color: var(--oz-muted); border: 1px solid var(--oz-border-soft); }
.ico.empty { background: transparent; border-color: transparent; }
.dot { width: 6px; height: 6px; border-radius: 50%; background: var(--oz-border-strong); }
.inactive .ico, .inactive .text { opacity: .5; }
.text { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 1px; }
.text b { font-size: 13px; font-weight: 700; color: var(--oz-ink-soft); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.text span { font-size: 11.5px; color: var(--oz-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-family: ui-monospace, monospace; }
.text span.soon { font-family: inherit; color: var(--oz-muted-2); }
.tags { display: flex; gap: 4px; flex-shrink: 0; max-width: 40%; overflow: hidden; }
.is-ghost { opacity: .45; }
.is-ghost > .mt-row { border: 1px dashed var(--oz-accent) !important; background: var(--oz-accent-soft) !important; }

@media (max-width: 640px) {
	.tags, .add-child { display: none; }
	.mt-list.is-nested { padding-left: 12px; }
	.mt-node.is-root > .mt-children { padding-left: 14px; }
}
</style>
