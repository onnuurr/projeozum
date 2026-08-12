<script setup>
import { computed } from 'vue';
import { COLOR_KEYS, colorHex } from '@/lib/colorVariants.js';
import MiniChart from '@/Components/MiniChart.vue';
import Skeleton from '@/Components/Skeleton.vue';

const props = defineProps({
    title: { type: String, required: true },
    value: { type: String, required: true },
    change: { type: String, default: '' },
    type: { type: String, default: 'line', validator: (v) => ['line', 'bar'].includes(v) },
    data: { type: Array, required: true },
    color: { type: String, default: 'primary', validator: (v) => COLOR_KEYS.includes(v) },
    loading: { type: Boolean, default: false },
});

const chartColor = computed(() => colorHex[props.color]);
</script>

<template>
    <div class="p-3 pl-[10px] bg-canvas rounded-lg border border-line border-l-[3px] border-l-transparent transition-colors duration-150 hover:border-l-primary hover:bg-surface">
        <div v-if="loading">
            <Skeleton width="60%" height="0.6875rem" />
            <Skeleton width="45%" height="1.125rem" class="mt-1.5" />
            <Skeleton width="100%" height="2rem" class="mt-2" />
        </div>
        <div v-else>
            <p class="text-[0.6875rem] text-muted mb-1">{{ title }}</p>
            <p class="text-lg font-bold text-ink">{{ value }}</p>
            <p v-if="change" class="text-[0.625rem] text-muted mt-0.5">{{ change }}</p>
            <div class="h-8 mt-1">
                <MiniChart :type="type" :data="data" :color="chartColor" />
            </div>
        </div>
    </div>
</template>
