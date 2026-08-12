<script setup>
import { COLOR_KEYS } from '@/lib/colorVariants.js';
import Skeleton from '@/Components/Skeleton.vue';

defineProps({
    icon: { type: [Object, Function], required: true },
    value: { type: [String, Number], required: true },
    title: { type: String, required: true },
    change: { type: String, default: '' },
    trend: { type: String, default: 'up', validator: (v) => ['up', 'down'].includes(v) },
    color: { type: String, default: 'primary', validator: (v) => COLOR_KEYS.includes(v) },
    loading: { type: Boolean, default: false },
});

const iconColorClasses = {
    primary: 'text-primary',
    success: 'text-success',
    warning: 'text-warning',
    danger: 'text-danger',
    info: 'text-info',
    neutral: 'text-gray-600',
};

const trendClasses = { up: 'text-success bg-success/10', down: 'text-danger bg-danger/10' };
</script>

<template>
    <div class="p-3 pl-[10px] bg-canvas rounded-lg border border-line border-l-[3px] border-l-transparent transition-colors duration-150 hover:border-l-primary hover:bg-surface">
        <div v-if="loading">
            <div class="flex items-center justify-between mb-2">
                <Skeleton variant="circle" width="1.5rem" height="1.5rem" />
                <Skeleton width="2.25rem" height="0.875rem" />
            </div>
            <Skeleton width="65%" height="1.375rem" />
            <Skeleton width="45%" height="0.75rem" class="mt-1.5" />
        </div>
        <div v-else>
            <div class="flex items-center justify-between mb-2">
                <component :is="icon" :size="18" :class="iconColorClasses[color]" />
                <span v-if="change" :class="['text-[0.625rem] font-medium px-1.5 py-0.5 rounded-full', trendClasses[trend]]">{{ change }}</span>
            </div>
            <div class="text-xl font-bold text-ink">{{ value }}</div>
            <div class="text-[0.6875rem] text-muted">{{ title }}</div>
        </div>
    </div>
</template>
