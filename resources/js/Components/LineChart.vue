<script setup>
import Chart from '@/Components/Chart.vue';

defineProps({
    labels: { type: Array, required: true },
    data: { type: Array, required: true },
    height: { type: [Number, String], default: 180 },
    label: { type: String, default: '' },
});
</script>

<template>
    <Chart type="line" :labels="labels" :data="data" :height="height" :label="label" color="primary" />
</template>
