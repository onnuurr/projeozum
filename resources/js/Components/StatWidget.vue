<script setup>
import { COLOR_KEYS, colorVariants } from '@/lib/colorVariants.js';
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

/* Kaynak: saas-frontend/resources/js/components/widgets/StatWidget.vue */
const leftBorderClasses = {
    primary: 'border-l-primary',
    success: 'border-l-success',
    warning: 'border-l-warning',
    danger: 'border-l-danger',
    info: 'border-l-info',
    neutral: 'border-l-gray-400',
};

const decorativeColorClasses = {
    primary: 'text-primary',
    success: 'text-success',
    warning: 'text-warning',
    danger: 'text-danger',
    info: 'text-info',
    neutral: 'text-gray-500',
};

const trendClasses = { up: 'text-success bg-success/10', down: 'text-danger bg-danger/10' };
</script>

<template>
    <div
        class="group relative p-3 sm:p-4 bg-surface-container-lowest rounded-lg border border-outline-variant border-l-[3px] transition-all duration-200 hover:shadow-md hover:-translate-y-0.5"
        :class="leftBorderClasses[color]"
    >
        <div v-if="loading">
            <div class="flex items-center justify-between mb-2">
                <Skeleton variant="circle" width="1.5rem" height="1.5rem" />
                <Skeleton width="2.25rem" height="0.875rem" />
            </div>
            <Skeleton width="65%" height="1.375rem" />
            <Skeleton width="45%" height="0.75rem" class="mt-1.5" />
        </div>
        <div v-else class="relative">
            <component
                :is="icon"
                :size="60"
                :class="['absolute -bottom-1 -right-1 opacity-[0.08] pointer-events-none select-none', decorativeColorClasses[color]]"
            />

            <div class="relative flex items-center justify-between mb-2">
                <span
                    class="w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0 transition-transform duration-200 group-hover:scale-105"
                    :class="colorVariants[color].tonal"
                >
                    <component :is="icon" :size="18" />
                </span>
                <span v-if="change" :class="['text-[0.625rem] font-medium px-1.5 py-0.5 rounded-full', trendClasses[trend]]">{{ change }}</span>
            </div>
            <div class="relative text-xl sm:text-2xl font-bold text-ink leading-tight">{{ value }}</div>
            <div class="relative text-[0.6875rem] text-muted mt-1">{{ title }}</div>
        </div>
    </div>
</template>
