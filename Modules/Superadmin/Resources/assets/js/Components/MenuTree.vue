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
                    <span class="mt-handle" title="Sürükle"><GripVertical :size="14" /></span>
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
                            <Pencil :size="13" />
                        </button>
                        <button
                            type="button"
                            @click="$emit('add-child', element)"
                            title="Alt menü ekle"
                        >
                            <Plus :size="13" />
                        </button>
                        <button
                            type="button"
                            class="danger"
                            @click="$emit('remove', element)"
                            title="Sil"
                        >
                            <Trash2 :size="13" />
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
import { GripVertical, Pencil, Plus, Trash2 } from "lucide-vue-next";

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

// Sağ panelden gelen route klonu bu listeye düşerse (added, __route işaretli)
// yeni öğe yarat; mevcut bir menü öğesinin başka bir üst menüye taşınması da
// "added" olarak gelir ama __route yok — bu durumda diğer taşımalar gibi
// sadece "dirty" işaretlenip normal reorder/autosave akışına bırakılır.
function onChange(evt) {
    if (evt.added?.element?.__route) {
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
    border-left: 1px dashed var(--color-outline-variant);
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
    background: var(--color-surface);
    border: 1px solid var(--color-outline-variant);
    border-radius: 8px;
    transition: border-color 0.12s;
}
.mt-row:hover {
    border-color: var(--color-muted);
}
.mt-row.inactive {
    opacity: 0.5;
}
.mt-handle {
    cursor: grab;
    color: var(--color-muted);
    user-select: none;
    display: inline-flex;
    align-items: center;
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
    color: var(--color-muted);
    font-size: 10px;
    line-height: 1;
    padding: 0;
}
.mt-toggle:hover {
    color: var(--color-ink);
}
.mt-toggle-spacer {
    width: 18px;
    flex-shrink: 0;
}
.mt-label {
    font-size: 13px;
    font-weight: 500;
    color: var(--color-ink);
}
.mt-count {
    font-size: 10px;
    color: var(--color-muted);
    background: var(--color-canvas);
    border-radius: 999px;
    padding: 1px 7px;
    font-weight: 600;
}
.mt-badge {
    font-size: 10px;
    color: var(--color-warning);
    background: color-mix(in srgb, var(--color-warning) 12%, transparent);
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
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: 1px solid var(--color-outline-variant);
    background: var(--color-surface);
    border-radius: 6px;
    cursor: pointer;
    color: var(--color-muted);
}
.mt-actions button:hover {
    background: var(--color-canvas);
    color: var(--color-ink);
}
.mt-actions button.danger:hover {
    background: color-mix(in srgb, var(--color-danger) 10%, transparent);
    color: var(--color-danger);
    border-color: color-mix(in srgb, var(--color-danger) 30%, transparent);
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
