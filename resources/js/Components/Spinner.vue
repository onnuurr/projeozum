<script setup>
import { computed } from 'vue';
import { COLOR_KEYS } from '@/lib/colorVariants.js';

const props = defineProps({
    size: { type: String, default: 'md', validator: (v) => ['sm', 'md', 'lg'].includes(v) },
    variant: { type: String, default: 'spinner', validator: (v) => ['spinner', 'dots', 'gradient'].includes(v) },
    color: { type: String, default: 'primary', validator: (v) => COLOR_KEYS.includes(v) },
});

const sizeClasses = { sm: 'w-3 h-3', md: 'w-4 h-4', lg: 'w-6 h-6' };
const ringWidths = { sm: 2, md: 2.5, lg: 3.5 };

const colorHex = {
    primary: '#F96A21',
    success: '#16A34A',
    warning: '#F59E0B',
    danger: '#DC2626',
    info: '#2563EB',
    neutral: '#9CA3AF',
};

const gradientStyle = computed(() => {
    const ring = ringWidths[props.size] ?? 2.5;
    return {
        background: `conic-gradient(from 0deg, transparent 0deg 255deg, ${colorHex[props.color]} 360deg)`,
        WebkitMask: `radial-gradient(farthest-side, transparent calc(100% - ${ring}px), #000 calc(100% - ${ring}px))`,
        mask: `radial-gradient(farthest-side, transparent calc(100% - ${ring}px), #000 calc(100% - ${ring}px))`,
    };
});

const borderColorClasses = {
    primary: 'border-primary',
    success: 'border-success',
    warning: 'border-warning',
    danger: 'border-danger',
    info: 'border-info',
    neutral: 'border-gray-400',
};

const bgColorClasses = {
    primary: 'bg-primary',
    success: 'bg-success',
    warning: 'bg-warning',
    danger: 'bg-danger',
    info: 'bg-info',
    neutral: 'bg-gray-400',
};
</script>

<template>
    <div
        v-if="variant === 'gradient'"
        :class="[sizeClasses[size], 'inline-block rounded-full animate-spin']"
        :style="gradientStyle"
    />

    <div
        v-else-if="variant === 'spinner'"
        :class="[sizeClasses[size], borderColorClasses[color], 'border-2 border-t-transparent rounded-full animate-spin']"
    />

    <div v-else class="flex items-center gap-1">
        <span
            v-for="i in 3"
            :key="i"
            :class="[
                size === 'sm' ? 'w-1 h-1' : size === 'md' ? 'w-1.5 h-1.5' : 'w-2 h-2',
                bgColorClasses[color],
                'rounded-full animate-bounce',
            ]"
            :style="{ animationDelay: `${(i - 1) * 0.15}s` }"
        />
    </div>
</template>
