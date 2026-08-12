<script setup>
import { computed } from 'vue';
import { ChevronDown } from 'lucide-vue-next';

const props = defineProps({
    title: { type: String, default: '' },
    icon: { type: [Object, Function], default: null },
    bordered: { type: Boolean, default: true },
    modelValue: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

const isOpen = computed({
    get: () => props.modelValue,
    set: (v) => emit('update:modelValue', v),
});
</script>

<template>
    <div :class="bordered ? 'border border-line rounded-lg overflow-hidden' : ''">
        <button
            type="button"
            class="w-full flex items-center justify-between px-3 py-2.5 text-left text-xs font-medium text-ink hover:bg-canvas transition-colors cursor-pointer"
            @click="isOpen = !isOpen"
        >
            <span class="flex items-center gap-2">
                <component :is="icon" v-if="icon" :size="14" class="text-muted" />
                {{ title }}
            </span>
            <ChevronDown :size="14" class="text-muted transition-transform duration-200" :class="isOpen ? 'rotate-180' : ''" />
        </button>

        <div
            class="grid transition-[grid-template-rows] duration-200 ease-out motion-reduce:transition-none"
            :style="{ gridTemplateRows: isOpen ? '1fr' : '0fr' }"
            :inert="!isOpen"
        >
            <div class="overflow-hidden min-h-0">
                <div class="px-3 pb-3 text-xs text-muted">
                    <slot />
                </div>
            </div>
        </div>
    </div>
</template>
