<script setup>
import Chart from '@/Components/Chart.vue';

defineProps({
    type: { type: String, default: 'line', validator: (v) => ['line', 'bar'].includes(v) },
    data: { type: Array, required: true },
    color: { type: String, default: '#F96A21' },
    height: { type: [Number, String], default: 32 },
});
</script>

<template>
    <Chart :type="type" :data="data" :height="height" :colors="[color]" :grid="false" />
</template>
