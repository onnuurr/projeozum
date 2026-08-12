<script setup>
import { COLOR_KEYS } from '@/lib/colorVariants.js';
import Skeleton from '@/Components/Skeleton.vue';

defineProps({
    icon: { type: [Object, Function], required: true },
    title: { type: String, required: true },
    subtitle: { type: String, default: '' },
    color: { type: String, default: 'primary', validator: (v) => COLOR_KEYS.includes(v) },
    loading: { type: Boolean, default: false },
});

const colorClasses = {
    primary: { button: 'bg-primary/5 border-primary/20 hover:bg-primary/10', icon: 'text-primary' },
    success: { button: 'bg-success/5 border-success/20 hover:bg-success/10', icon: 'text-success' },
    warning: { button: 'bg-warning/5 border-warning/20 hover:bg-warning/10', icon: 'text-warning' },
    danger: { button: 'bg-danger/5 border-danger/20 hover:bg-danger/10', icon: 'text-danger' },
    info: { button: 'bg-info/5 border-info/20 hover:bg-info/10', icon: 'text-info' },
    neutral: { button: 'bg-gray-50 border-gray-200 hover:bg-gray-100', icon: 'text-gray-600' },
};
</script>

<template>
    <button
        type="button"
        :class="[
            'p-3 w-full rounded-lg border text-left group',
            loading ? 'bg-canvas border-line cursor-default' : ['transition-all cursor-pointer', colorClasses[color].button],
        ]"
    >
        <div v-if="loading">
            <div><Skeleton variant="circle" width="1.5rem" height="1.5rem" /></div>
            <div class="mt-2"><Skeleton width="70%" height="0.75rem" /></div>
            <div class="mt-1"><Skeleton width="50%" height="0.625rem" /></div>
        </div>
        <div v-else>
            <component :is="icon" :size="20" :class="['mb-1 block group-hover:scale-110 transition-transform', colorClasses[color].icon]" />
            <p class="text-xs font-medium text-ink">{{ title }}</p>
            <p v-if="subtitle" class="text-[0.625rem] text-muted">{{ subtitle }}</p>
        </div>
    </button>
</template>
