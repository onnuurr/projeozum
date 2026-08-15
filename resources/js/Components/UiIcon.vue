<script setup>
import { computed } from 'vue';
import { icons, highlightIcons } from '@/lib/icons.js';

const props = defineProps({
    name: { type: String, default: '' },
    size: { type: [String, Number], default: 17 },
    strokeWidth: { type: [String, Number], default: 2 },
    highlight: { type: Boolean, default: false },
    weight: { type: String, default: 'duotone' },
});

const resolved = computed(() => {
    if (!props.name) return null;
    return highlightIcons[props.name] || icons[props.name] || null;
});

const isPhosphor = computed(() => {
    const c = resolved.value;
    if (!c) return false;
    return Object.prototype.hasOwnProperty.call(highlightIcons, props.name);
});
</script>

<template>
    <component
        :is="resolved"
        v-if="resolved"
        aria-hidden="true"
        :size="size"
        :stroke-width="isPhosphor ? undefined : strokeWidth"
        :weight="isPhosphor ? weight : undefined"
        class="shrink-0"
    />
</template>
