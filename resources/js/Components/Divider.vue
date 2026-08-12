<script setup>
import { computed } from 'vue';

const props = defineProps({
    vertical: { type: Boolean, default: false },
    label: { type: String, default: '' },
    icon: { type: [Object, Function], default: null },
    variant: { type: String, default: 'solid', validator: (v) => ['solid', 'dashed', 'dotted'].includes(v) },
});

const lineClasses = {
    solid: 'bg-line h-px',
    dashed: 'border-t border-dashed border-line',
    dotted: 'border-t border-dotted border-line',
};

const lineClass = computed(() => lineClasses[props.variant]);
</script>

<template>
    <div
        v-if="vertical"
        :class="[
            'w-px self-stretch',
            variant === 'solid' ? 'bg-line' : variant === 'dashed' ? 'border-l border-dashed border-line' : 'border-l border-dotted border-line',
        ]"
    />

    <div v-else-if="icon" class="flex items-center gap-2">
        <div :class="['flex-1', lineClass]" />
        <span class="w-6 h-6 rounded-full bg-canvas border border-line inline-flex items-center justify-center">
            <component :is="icon" :size="14" class="text-muted" />
        </span>
        <div :class="['flex-1', lineClass]" />
    </div>

    <div v-else-if="label" class="flex items-center gap-2">
        <div :class="['flex-1', lineClass]" />
        <span class="text-[0.6875rem] font-medium text-muted whitespace-nowrap">{{ label }}</span>
        <div :class="['flex-1', lineClass]" />
    </div>

    <div v-else :class="lineClass" />
</template>
