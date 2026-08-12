<script setup>
import { TrendingUp, TrendingDown, Minus } from 'lucide-vue-next';
import Chart from '@/Components/Chart.vue';
import Badge from '@/Components/Badge.vue';
import { COLOR_KEYS } from '@/lib/colorVariants.js';

defineProps({
    title: { type: String, required: true },
    subtitle: { type: String, default: '' },
    value: { type: String, default: '' },
    change: { type: String, default: '' },
    changeTrend: { type: String, default: 'up', validator: (v) => ['up', 'down', 'neutral'].includes(v) },
    badge: { type: String, default: '' },
    type: { type: String, default: 'line', validator: (v) => ['line', 'bar', 'pie', 'doughnut'].includes(v) },
    labels: { type: Array, default: () => [] },
    data: { type: Array, default: () => [] },
    series: { type: Array, default: () => [] },
    height: { type: [Number, String], default: 200 },
    color: { type: String, default: 'primary', validator: (v) => COLOR_KEYS.includes(v) },
    legend: { type: Boolean, default: false },
    compact: { type: Boolean, default: true },
});

const trendIcon = { up: TrendingUp, down: TrendingDown, neutral: Minus };
const trendClass = { up: 'text-success', down: 'text-danger', neutral: 'text-muted' };
</script>

<template>
    <div class="bg-canvas rounded-lg p-4 border border-line h-full flex flex-col">
        <div class="flex items-start justify-between gap-3 mb-4">
            <div class="min-w-0">
                <div class="flex items-center gap-2">
                    <h4 class="text-sm font-medium text-ink truncate">{{ title }}</h4>
                    <Badge v-if="badge" color="primary" variant="tonal" :label="badge" />
                </div>
                <p v-if="subtitle" class="text-[0.6875rem] text-muted mt-0.5 truncate">{{ subtitle }}</p>
                <p v-if="value" class="text-2xl font-bold text-ink mt-1.5">{{ value }}</p>
                <span v-if="change" class="inline-flex items-center gap-1 mt-1 text-[0.6875rem] font-medium" :class="trendClass[changeTrend]">
                    <component :is="trendIcon[changeTrend]" :size="12" />
                    {{ change }}
                </span>
            </div>
            <slot name="actions" />
        </div>
        <div class="flex-1 min-h-0">
            <Chart :type="type" :labels="labels" :data="data" :series="series" :height="height" :color="color" :legend="legend" :compact="compact" />
        </div>
    </div>
</template>
