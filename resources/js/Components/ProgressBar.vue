<script setup>
import { computed } from 'vue';
import { COLOR_KEYS, colorVariants } from '@/lib/colorVariants.js';

const props = defineProps({
    value: { type: Number, default: 0 },
    max: { type: Number, default: 100 },
    color: { type: String, default: 'primary', validator: (v) => COLOR_KEYS.includes(v) },
    label: { type: String, default: '' },
    showValue: { type: Boolean, default: false },
    indeterminate: { type: Boolean, default: false },
    height: { type: String, default: 'sm', validator: (v) => ['sm', 'md'].includes(v) },
    striped: { type: Boolean, default: false },
});

const heightClasses = { sm: 'h-1.5', md: 'h-3.5' };

const percentage = computed(() => Math.min(100, Math.max(0, (props.value / props.max) * 100)));

const barStyle = computed(() => ({ width: props.indeterminate ? '40%' : `${percentage.value}%` }));

const barClass = computed(() => [
    colorVariants[props.color].solid,
    'relative h-full rounded-full overflow-hidden transition-all duration-300 ease-out',
    'shadow-[inset_0_1px_0_rgba(255,255,255,0.25)]',
    props.indeterminate ? 'progress-bar-indeterminate' : '',
    props.striped && !props.indeterminate ? 'progress-bar-striped' : '',
]);

const valueTone = computed(() => {
    if (percentage.value >= 90) return 'text-danger';
    if (percentage.value >= 75) return 'text-warning';
    return 'text-primary';
});
</script>

<template>
    <div class="w-full">
        <div v-if="label || showValue" class="flex items-center justify-between mb-1">
            <span v-if="label" class="text-[0.6875rem] font-medium text-muted">{{ label }}</span>
            <span v-if="showValue && !indeterminate" class="text-[0.6875rem] font-medium tabular-nums" :class="valueTone">
                {{ Math.round(percentage) }}%
            </span>
        </div>
        <div :class="[heightClasses[height], 'relative w-full bg-canvas rounded-full overflow-hidden']">
            <div :class="barClass" :style="barStyle" />
        </div>
    </div>
</template>

<style scoped>
.progress-bar-striped {
    background-image: linear-gradient(45deg, rgba(255, 255, 255, 0.15) 25%, transparent 25%, transparent 50%, rgba(255, 255, 255, 0.15) 50%, rgba(255, 255, 255, 0.15) 75%, transparent 75%, transparent);
    background-size: 1rem 1rem;
}

.progress-bar-indeterminate {
    animation: progress-bar-indeterminate 1.2s ease-in-out infinite;
}

@keyframes progress-bar-indeterminate {
    from { transform: translateX(-100%); }
    to { transform: translateX(250%); }
}
</style>
