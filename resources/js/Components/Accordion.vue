<script setup>
import { ref } from 'vue';
import AccordionItem from '@/Components/AccordionItem.vue';

const props = defineProps({
    items: { type: Array, default: () => [] },
    bordered: { type: Boolean, default: true },
    icon: { type: [Object, Function], default: null },
});

const openId = ref(null);

function toggleItem(id) {
    openId.value = openId.value === id ? null : id;
}

function isOpen(id) {
    return openId.value === id;
}
</script>

<template>
    <div :class="bordered ? 'border border-line rounded-lg overflow-hidden divide-y divide-line' : 'divide-y divide-line'">
        <div v-for="item in items" :key="item.id">
            <slot name="item" :item="item" :is-open="isOpen(item.id)" :toggle="() => toggleItem(item.id)">
                <AccordionItem
                    :title="item.title"
                    :icon="item.icon || icon"
                    :bordered="false"
                    :model-value="isOpen(item.id)"
                    @update:model-value="toggleItem(item.id)"
                >
                    <slot name="content" :item="item">{{ item.content }}</slot>
                </AccordionItem>
            </slot>
        </div>

        <slot />
    </div>
</template>
