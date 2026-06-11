<template>
	<draggable
		:list="nodes"
		:group="{ name: 'menus' }"
		item-key="id"
		handle=".mt-handle"
		class="mt-list"
		@change="$emit('changed')"
	>
		<template #item="{ element }">
			<div class="mt-node">
				<div class="mt-row" :class="{ inactive: !element.is_active }">
					<span class="mt-handle" title="Sürükle">⋮⋮</span>
					<span class="mt-label">{{ element.label }}</span>
					<span v-if="element.permission" class="mt-badge">{{ element.permission }}</span>
					<span class="mt-actions">
						<button type="button" @click="$emit('edit', element)" title="Düzenle">✎</button>
						<button type="button" @click="$emit('add-child', element)" title="Alt menü ekle">＋</button>
						<button type="button" class="danger" @click="$emit('remove', element)" title="Sil">🗑</button>
					</span>
				</div>
				<MenuTree
					:nodes="element.children"
					class="mt-children"
					@changed="$emit('changed')"
					@edit="$emit('edit', $event)"
					@add-child="$emit('add-child', $event)"
					@remove="$emit('remove', $event)"
				/>
			</div>
		</template>
	</draggable>
</template>

<script setup>
import draggable from 'vuedraggable'

defineProps({
	nodes: { type: Array, required: true },
})

defineEmits(['changed', 'edit', 'add-child', 'remove'])
</script>

<style scoped>
.mt-list { min-height: 12px; }
.mt-children { margin-left: 22px; border-left: 1px dashed #e0e0ea; padding-left: 8px; }
.mt-node { margin: 3px 0; }
.mt-row {
	display: flex; align-items: center; gap: 8px;
	padding: 7px 10px; background: #fff;
	border: 1px solid #ebebf0; border-radius: 8px;
	transition: border-color .12s;
}
.mt-row:hover { border-color: #c8c8d8; }
.mt-row.inactive { opacity: .5; }
.mt-handle { cursor: grab; color: #bbb; user-select: none; font-size: 12px; }
.mt-label { font-size: 13px; font-weight: 500; color: #1a1a2e; }
.mt-badge {
	font-size: 10px; color: #8a6d00; background: #fff8e1;
	border-radius: 4px; padding: 1px 6px;
}
.mt-actions { margin-left: auto; display: flex; gap: 4px; }
.mt-actions button {
	width: 26px; height: 26px; border: 1px solid #ebebf0;
	background: #fff; border-radius: 6px; cursor: pointer; color: #666;
}
.mt-actions button:hover { background: #f5f5fb; color: #1a1a2e; }
.mt-actions button.danger:hover { background: #fef2f2; color: #dc2626; border-color: #fecaca; }
</style>
