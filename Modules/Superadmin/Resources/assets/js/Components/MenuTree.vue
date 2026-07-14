<template>
    <draggable
        :list="nodes"
        :group="{ name: 'menus' }"
        :disabled="disabled"
        item-key="id"
        handle=".mt-handle"
        class="mt-list"
        @change="onChange"
    >
        <template #item="{ element }">
            <div class="mt-node">
                <div class="mt-row" :class="{ inactive: !element.is_active }">
                    <span class="mt-handle" title="Sürükle">⋮⋮</span>
                    <button
                        v-if="hasChildren(element)"
                        type="button"
                        class="mt-toggle"
                        :title="element.__collapsed ? 'Genişlet' : 'Daralt'"
                        @click="toggle(element)"
                    >
                        {{ element.__collapsed ? "▸" : "▾" }}
                    </button>
                    <span v-else class="mt-toggle-spacer"></span>
                    <span class="mt-label">{{ element.label }}</span>
                    <span v-if="hasChildren(element)" class="mt-count">{{
                        element.children.length
                    }}</span>
                    <span v-if="element.permission" class="mt-badge">{{
                        element.permission
                    }}</span>
                    <span class="mt-actions">
                        <button
                            type="button"
                            @click="$emit('edit', element)"
                            title="Düzenle"
                        >
                            ✎
                        </button>
                        <button
                            type="button"
                            @click="$emit('add-child', element)"
                            title="Alt menü ekle"
                        >
                            ＋
                        </button>
                        <button
                            type="button"
                            class="danger"
                            @click="$emit('remove', element)"
                            title="Sil"
                        >
                            🗑
                        </button>
                    </span>
                </div>
                <MenuTree
                    v-show="!element.__collapsed"
                    :nodes="element.children"
                    :parent-id="element.id"
                    :disabled="disabled"
                    class="mt-children"
                    @changed="$emit('changed')"
                    @add-route="$emit('add-route', $event)"
                    @edit="$emit('edit', $event)"
                    @add-child="$emit('add-child', $event)"
                    @remove="$emit('remove', $event)"
                />
            </div>
        </template>
    </draggable>
</template>

<script setup>
import draggable from "vuedraggable";

const props = defineProps({
    nodes: { type: Array, required: true },
    parentId: { type: [Number, null], default: null },
    disabled: { type: Boolean, default: false },
});

const emit = defineEmits([
    "changed",
    "add-route",
    "edit",
    "add-child",
    "remove",
]);

function hasChildren(node) {
    return Array.isArray(node.children) && node.children.length > 0;
}

// Daralt/genişlet durumunu düğümün üzerinde tut (recursive yapıda her seviyede çalışır).
function toggle(node) {
    node.__collapsed = !node.__collapsed;
}

// Sağ panelden gelen route klonu bu listeye düşerse (added) yeni öğe yarat;
// mevcut öğelerin taşınması (moved) sadece "dirty" işaretler.
function onChange(evt) {
    if (evt.added) {
        emit("add-route", {
            route: evt.added.element.__route,
            parentId: props.parentId,
            index: evt.added.newIndex,
        });
        return;
    }
    emit("changed");
}
</script>

<style scoped>
.mt-list {
    min-height: 1px;
}
.mt-children {
    margin-left: 22px;
    border-left: 1px dashed #e0e0ea;
    padding-left: 8px;
}
.mt-node {
    margin: 3px 0;
}
.mt-row {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 7px 10px;
    background: #fff;
    border: 1px solid #ebebf0;
    border-radius: 8px;
    transition: border-color 0.12s;
}
.mt-row:hover {
    border-color: #c8c8d8;
}
.mt-row.inactive {
    opacity: 0.5;
}
.mt-handle {
    cursor: grab;
    color: #bbb;
    user-select: none;
    font-size: 12px;
}
.mt-toggle {
    width: 18px;
    height: 18px;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    border: none;
    background: transparent;
    cursor: pointer;
    color: #888;
    font-size: 10px;
    line-height: 1;
    padding: 0;
}
.mt-toggle:hover {
    color: #1a1a2e;
}
.mt-toggle-spacer {
    width: 18px;
    flex-shrink: 0;
}
.mt-label {
    font-size: 13px;
    font-weight: 500;
    color: #1a1a2e;
}
.mt-count {
    font-size: 10px;
    color: #6b7280;
    background: #f1f1f7;
    border-radius: 999px;
    padding: 1px 7px;
    font-weight: 600;
}
.mt-badge {
    font-size: 10px;
    color: #8a6d00;
    background: #fff8e1;
    border-radius: 4px;
    padding: 1px 6px;
}
.mt-actions {
    margin-left: auto;
    display: flex;
    gap: 4px;
}
.mt-actions button {
    width: 26px;
    height: 26px;
    border: 1px solid #ebebf0;
    background: #fff;
    border-radius: 6px;
    cursor: pointer;
    color: #666;
}
.mt-actions button:hover {
    background: #f5f5fb;
    color: #1a1a2e;
}
.mt-actions button.danger:hover {
    background: #fef2f2;
    color: #dc2626;
    border-color: #fecaca;
}

@media (max-width: 640px) {
    .mt-children {
        margin-left: 12px;
        padding-left: 6px;
    }
    .mt-row {
        gap: 6px;
        padding: 6px 8px;
    }
    .mt-badge {
        display: none;
    }
    .mt-actions {
        gap: 2px;
    }
    .mt-actions button {
        width: 24px;
        height: 24px;
    }
}
</style>
