<script setup>
import { computed } from 'vue';
import { COLOR_KEYS } from '@/lib/colorVariants.js';
import Skeleton from '@/Components/Skeleton.vue';

const props = defineProps({
    title: { type: String, required: true },
    value: { type: [Number, String], required: true },
    total: { type: [Number, String], required: true },
    color: { type: String, default: 'primary', validator: (v) => COLOR_KEYS.includes(v) },
    description: { type: String, default: '' },
    loading: { type: Boolean, default: false },
});

const percentage = computed(() => {
    const total = Number(props.total);
    if (!total) return 0;
    return Math.min((Number(props.value) / total) * 100, 100);
});

const valueClasses = {
    primary: 'text-primary',
    success: 'text-success',
    warning: 'text-warning',
    danger: 'text-danger',
    info: 'text-info',
    neutral: 'text-gray-600',
};

const barClasses = {
    primary: 'bg-primary',
    success: 'bg-success',
    warning: 'bg-warning',
    danger: 'bg-danger',
    info: 'bg-info',
    neutral: 'bg-gray-400',
};
</script>

<template>
    <div class="p-3 pl-[10px] bg-canvas rounded-lg border border-line border-l-[3px] border-l-transparent transition-colors duration-150 hover:border-l-primary hover:bg-surface">
        <div v-if="loading">
            <div class="flex items-center justify-between mb-2">
                <Skeleton width="50%" height="0.75rem" />
                <Skeleton width="25%" height="0.6875rem" />
            </div>
            <Skeleton width="100%" height="0.5rem" />
        </div>
        <div v-else>
            <div class="flex items-center justify-between mb-2">
                <p class="text-xs font-medium text-ink">{{ title }}</p>
                <p :class="['text-[0.6875rem] font-semibold', valueClasses[color]]">{{ value }} / {{ total }}</p>
            </div>
            <div class="w-full h-2 bg-line rounded-full overflow-hidden">
                <div :class="['h-full rounded-full transition-all duration-300', barClasses[color]]" :style="{ width: percentage + '%' }" />
            </div>
            <p v-if="description" class="text-[0.625rem] text-muted mt-1">{{ description }}</p>
        </div>
    </div>
</template>
