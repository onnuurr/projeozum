<script setup>
import { computed, ref, onMounted, onUnmounted, watch } from 'vue';
import Chart from 'chart.js/auto';
import { COLOR_KEYS, colorHex, hexToRgba } from '@/lib/colorVariants.js';

const props = defineProps({
    type: { type: String, default: 'line', validator: (v) => ['line', 'bar', 'pie', 'doughnut'].includes(v) },
    labels: { type: Array, default: () => [] },
    data: { type: Array, default: () => [] },
    series: { type: Array, default: () => [] },
    label: { type: String, default: '' },
    height: { type: [Number, String], default: 180 },
    color: { type: String, default: 'primary', validator: (v) => COLOR_KEYS.includes(v) },
    colors: { type: Array, default: () => [] },
    grid: { type: Boolean, default: true },
    legend: { type: Boolean, default: false },
    legendPosition: { type: String, default: 'bottom' },
    compact: { type: Boolean, default: false },
    tooltipValuePrefix: { type: String, default: '' },
    cutout: { type: [String, Number], default: '62%' },
    tooltip: { type: Boolean, default: true },
});

const INK = '#1A1A1E';
const MUTED = '#8A8A94';
const LINE = '#ECECEF';

const chartRef = ref(null);
let chartInstance = null;
let resizeObserver = null;

const isCircular = computed(() => ['pie', 'doughnut'].includes(props.type));
const isMulti = computed(() => props.series.length > 0);

const datasetsData = computed(() => {
    if (isMulti.value) {
        return props.series.map((s, i) => ({
            label: s.label || '',
            data: s.data || [],
            color: s.color || (props.colors.length ? props.colors[i % props.colors.length] : colorHex[props.color]),
        }));
    }
    return [{ label: props.label, data: props.data || [], color: props.colors.length ? props.colors[0] : colorHex[props.color] }];
});

const effectiveLabels = computed(() => {
    if (props.labels.length) return props.labels;
    const first = datasetsData.value[0];
    return first ? first.data.map((_, i) => i + 1) : [];
});

function buildGradient(ctx, area, hex, vertical = true) {
    if (!area) return hexToRgba(hex, 0.16);
    const g = vertical ? ctx.createLinearGradient(0, area.top, 0, area.bottom) : ctx.createLinearGradient(area.left, 0, area.right, 0);
    g.addColorStop(0, hexToRgba(hex, 0.28));
    g.addColorStop(1, hexToRgba(hex, 0.04));
    return g;
}

const formatCompact = (n) => {
    const abs = Math.abs(n);
    if (abs >= 1_000_000) return `${(n / 1_000_000).toLocaleString('tr-TR', { maximumFractionDigits: 1 })}M`;
    if (abs >= 1_000) return `${(n / 1_000).toLocaleString('tr-TR', { maximumFractionDigits: 1 })}K`;
    return String(Math.round(n));
};

function createDataset(ds) {
    if (isCircular.value) {
        const palette = props.colors.length ? props.colors : Object.values(colorHex);
        return {
            data: ds.data,
            backgroundColor: palette.slice(0, ds.data.length),
            borderWidth: 0,
            borderColor: '#fff',
            hoverOffset: 8,
            spacing: 2,
            borderRadius: 6,
        };
    }

    if (props.type === 'bar') {
        return {
            label: ds.label,
            data: ds.data,
            backgroundColor: (context) => {
                const { ctx: c, chartArea } = context.chart;
                return buildGradient(c, chartArea, ds.color, true);
            },
            borderRadius: 6,
            borderSkipped: false,
            maxBarThickness: 32,
            hoverBackgroundColor: hexToRgba(ds.color, 0.92),
        };
    }

    return {
        label: ds.label,
        data: ds.data,
        borderColor: ds.color,
        borderWidth: 2.5,
        backgroundColor: (context) => {
            const { ctx: c, chartArea } = context.chart;
            return buildGradient(c, chartArea, ds.color, true);
        },
        fill: true,
        tension: 0.4,
        pointBackgroundColor: '#fff',
        pointBorderColor: ds.color,
        pointBorderWidth: 2,
        pointRadius: 2.5,
        pointHoverRadius: 5,
    };
}

function buildOptions() {
    const scales = isCircular.value ? {} : {
        x: { grid: { display: false }, ticks: { color: MUTED, font: { size: 10 } }, border: { display: false } },
        y: {
            beginAtZero: true,
            grid: props.grid ? { color: LINE, drawTicks: false } : { display: false },
            border: { display: false },
            ticks: { color: MUTED, font: { size: 10 }, padding: 6, callback: (v) => (props.compact ? formatCompact(v) : v) },
        },
    };

    return {
        responsive: true,
        maintainAspectRatio: false,
        interaction: isCircular.value ? { mode: 'nearest', intersect: true } : { mode: 'index', intersect: false },
        animation: { duration: 500, easing: 'easeOutQuart' },
        plugins: {
            legend: props.legend
                ? { position: props.legendPosition, labels: { color: MUTED, font: { size: 10 }, padding: 12, usePointStyle: true, pointStyle: 'circle' } }
                : { display: false },
            tooltip: props.tooltip
                ? {
                    backgroundColor: INK,
                    titleColor: 'rgba(255,255,255,0.7)',
                    bodyColor: '#fff',
                    padding: 9,
                    cornerRadius: 8,
                    displayColors: isMulti.value,
                    callbacks: {
                        label: (ctx) => {
                            const value = ctx.parsed?.y ?? ctx.parsed;
                            return props.tooltipValuePrefix + value.toLocaleString('tr-TR');
                        },
                    },
                }
                : { enabled: false },
        },
        scales,
        cutout: props.type === 'doughnut' ? props.cutout : undefined,
    };
}

function createChart() {
    if (chartInstance) {
        chartInstance.destroy();
        chartInstance = null;
    }
    if (!chartRef.value || !effectiveLabels.value.length) return;

    chartInstance = new Chart(chartRef.value.getContext('2d'), {
        type: props.type,
        data: { labels: effectiveLabels.value, datasets: datasetsData.value.map(createDataset) },
        options: buildOptions(),
    });
}

function updateChart() {
    if (!chartInstance) return;
    chartInstance.data.labels = effectiveLabels.value;
    chartInstance.data.datasets = datasetsData.value.map(createDataset);
    chartInstance.update();
}

onMounted(() => {
    createChart();
    if (chartRef.value && typeof ResizeObserver !== 'undefined') {
        resizeObserver = new ResizeObserver(() => chartInstance?.resize());
        resizeObserver.observe(chartRef.value);
    }
});

onUnmounted(() => {
    chartInstance?.destroy();
    chartInstance = null;
    resizeObserver?.disconnect();
});

watch(() => [props.labels, props.data, props.series], updateChart, { deep: true });
watch(() => [props.type, props.color, props.colors, props.grid, props.legend, props.compact], createChart);
</script>

<template>
    <div class="relative" :style="{ height: typeof height === 'number' ? height + 'px' : height }">
        <canvas ref="chartRef"></canvas>
    </div>
</template>
