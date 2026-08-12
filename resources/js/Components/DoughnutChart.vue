<script setup>
import Chart from '@/Components/Chart.vue';

defineProps({
    labels: { type: Array, required: true },
    data: { type: Array, required: true },
    height: { type: [Number, String], default: 180 },
});
</script>

<template>
    <Chart type="doughnut" :labels="labels" :data="data" :height="height" legend cutout="62%" />
</template>
