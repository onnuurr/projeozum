<script setup>
import { ref, computed, useId } from 'vue'
import { useElementSize } from '@vueuse/core'
import { motion } from 'motion-v'

// Yumuşak eğrili alan grafiği (SVG). Son nokta vurgulanır, üzerine gelince tooltip.
// İlk çizimde çizgi soldan sağa çizilir, alan ardından belirir; veri değişince eğri yeni şekline akar.
const props = defineProps({
  values: { type: Array, required: true },
  labels: { type: Array, required: true },
  unit: { type: String, default: '' },
  // Son etiketin yerine yazılır (ör. "Bugün"); boşsa etiket aynen kalır
  lastLabel: { type: String, default: 'Bugün' },
  // Verilmezse en büyük değerin üstündeki yuvarlak sayı
  yMax: { type: Number, default: null },
  yTickCount: { type: Number, default: 5 },
  // x ekseninde her kaç noktada bir etiket
  labelEvery: { type: Number, default: 2 },
})

// viewBox genişliği kapsayıcının gerçek genişliğini izler: mobilde yazılar küçülmez,
// x etiketleri sığdığı kadar seyreltilir.
const H = 230, ML = 34, MR = 8, MT = 10, MB = 26
const svgEl = ref(null)
const { width: measured } = useElementSize(svgEl)
// ölçülmeden önce (ilk çizim) 720 varsayılır
const W = computed(() => (measured.value > 0 ? Math.max(Math.round(measured.value), 240) : 720))
const plotW = computed(() => W.value - ML - MR)
const plotH = H - MT - MB
const gradId = `oz-area-${useId()}`

// Eksen adımı 1-2-2.5-5 × 10^n'e yuvarlanır ki tick'ler tam sayı olsun
const maxY = computed(() => {
  if (props.yMax) return props.yMax
  const intervals = props.yTickCount - 1
  const raw = (Math.max(...props.values, 1) * 1.05) / intervals
  const mag = 10 ** Math.floor(Math.log10(raw))
  const step = [1, 2, 2.5, 5, 10].find((m) => m * mag >= raw) * mag
  return step * intervals
})

const yTicks = computed(() => {
  const n = props.yTickCount - 1
  return Array.from({ length: n + 1 }, (_, i) => Math.round((maxY.value / n) * i))
})

const xAt = (i) => ML + (plotW.value * i) / Math.max(props.values.length - 1, 1)
const yAt = (v) => MT + plotH - (plotH * v) / maxY.value
const baseline = computed(() => yAt(0))

const lastIndex = computed(() => props.values.length - 1)
const labelFor = (i) => (i === lastIndex.value && props.lastLabel ? props.lastLabel : props.labels[i])
const withUnit = (v) => (props.unit ? `${v} ${props.unit}` : `${v}`)

const points = computed(() => props.values.map((v, i) => ({ x: xAt(i), y: yAt(v), v })))
const lastPoint = computed(() => points.value[lastIndex.value])

const linePath = computed(() => {
  const pts = points.value
  if (!pts.length) return ''
  let d = `M${pts[0].x},${pts[0].y}`
  for (let i = 0; i < pts.length - 1; i++) {
    const p0 = pts[i], p1 = pts[i + 1]
    const mx = (p0.x + p1.x) / 2
    d += ` C${mx},${p0.y} ${mx},${p1.y} ${p1.x},${p1.y}`
  }
  return d
})
const areaPath = computed(() => `${linePath.value} L${lastPoint.value.x},${baseline.value} L${points.value[0].x},${baseline.value} Z`)

const MIN_LABEL_GAP = 44
const effectiveEvery = computed(() => {
  const step = plotW.value / Math.max(props.values.length - 1, 1)
  let every = props.labelEvery
  while (step * every < MIN_LABEL_GAP && every < props.values.length) every++
  return every
})

const xLabels = computed(() =>
  props.labels
    .map((_, i) => ({ i, x: xAt(i), text: labelFor(i), last: i === lastIndex.value }))
    .filter((l) => l.i % effectiveEvery.value === 0 || l.last)
    // son etiketle çakışan komşuyu at
    .filter((l, _, arr) => l.last || arr.at(-1).x - l.x >= MIN_LABEL_GAP),
)

const wrapEl = ref(null)
const hoverIndex = ref(null)
const tooltipPos = ref({ left: '0px', top: '0px' })
const hoverPoint = computed(() => (hoverIndex.value == null ? null : points.value[hoverIndex.value]))

function onMove(evt) {
  const svg = svgEl.value
  const pt = svg.createSVGPoint()
  pt.x = evt.clientX
  pt.y = evt.clientY
  const loc = pt.matrixTransform(svg.getScreenCTM().inverse())
  let idx = 0, best = Infinity
  points.value.forEach((p, i) => {
    const dist = Math.abs(p.x - loc.x)
    if (dist < best) { best = dist; idx = i }
  })
  hoverIndex.value = idx

  const p = points.value[idx]
  const wrapRect = wrapEl.value.getBoundingClientRect()
  const svgRect = svg.getBoundingClientRect()
  tooltipPos.value = {
    left: `${(svgRect.left - wrapRect.left) + (p.x / W.value) * svgRect.width}px`,
    top: `${(svgRect.top - wrapRect.top) + (p.y / H) * svgRect.height - 10}px`,
  }
}
</script>

<template>
  <div ref="wrapEl" class="oz-line-chart">
    <svg
      ref="svgEl"
      class="chart-svg"
      preserveAspectRatio="none"
      :viewBox="`0 0 ${W} ${H}`"
      role="img"
      :aria-label="`Son değer ${withUnit(lastPoint?.v)}`"
      @pointermove="onMove"
      @pointerleave="hoverIndex = null"
    >
      <defs>
        <linearGradient :id="gradId" x1="0" x2="0" y1="0" y2="1">
          <stop offset="0%" stop-color="var(--oz-accent)" stop-opacity="0.18" />
          <stop offset="100%" stop-color="var(--oz-accent)" stop-opacity="0.01" />
        </linearGradient>
      </defs>
      <g>
        <template v-for="t in yTicks" :key="t">
          <line class="grid-line" :x1="ML" :x2="W - MR" :y1="yAt(t)" :y2="yAt(t)" />
          <text class="tick-label" text-anchor="end" :x="ML - 8" :y="yAt(t) + 3">{{ t }}</text>
        </template>
      </g>
      <motion.path
        :fill="`url(#${gradId})`"
        :initial="{ opacity: 0, d: areaPath }"
        :animate="{ opacity: 1, d: areaPath }"
        :transition="{ opacity: { duration: 0.6, delay: 0.5 }, d: { duration: 0.6, ease: [0.16, 1, 0.3, 1] } }"
      />
      <motion.path
        class="line-path"
        :initial="{ pathLength: 0, d: linePath }"
        :animate="{ pathLength: 1, d: linePath }"
        :transition="{ pathLength: { duration: 1.1, ease: [0.65, 0, 0.35, 1] }, d: { duration: 0.6, ease: [0.16, 1, 0.3, 1] } }"
      />
      <line class="crosshair" :style="{ opacity: hoverPoint ? 1 : 0 }" :x1="hoverPoint?.x ?? 0" :x2="hoverPoint?.x ?? 0" :y1="MT" :y2="baseline" />
      <circle class="dot" r="4.5" :style="{ opacity: hoverPoint ? 1 : 0 }" :cx="hoverPoint?.x ?? 0" :cy="hoverPoint?.y ?? 0" />
      <template v-if="lastPoint">
        <motion.circle
          class="dot"
          r="4.5"
          :initial="{ scale: 0, cx: lastPoint.x, cy: lastPoint.y }"
          :animate="{ scale: 1, cx: lastPoint.x, cy: lastPoint.y }"
          :transition="{ scale: { type: 'spring', stiffness: 300, damping: 15, delay: 1 }, default: { duration: 0.6, ease: [0.16, 1, 0.3, 1] } }"
        />
        <motion.text
          class="end-label"
          text-anchor="end"
          :x="lastPoint.x - 10"
          :y="lastPoint.y - 12"
          :initial="{ opacity: 0 }"
          :animate="{ opacity: 1 }"
          :transition="{ duration: 0.3, delay: 1 }"
        >{{ withUnit(lastPoint.v) }}</motion.text>
      </template>
      <g>
        <text
          v-for="l in xLabels"
          :key="l.i"
          class="x-label"
          :class="{ today: l.last && lastLabel }"
          :text-anchor="l.last ? 'end' : 'middle'"
          :x="l.x"
          :y="H - 6"
        >{{ l.text }}</text>
      </g>
    </svg>
    <div class="tooltip" :style="{ ...tooltipPos, opacity: hoverPoint ? 1 : 0 }">
      <template v-if="hoverPoint">
        <b>{{ withUnit(hoverPoint.v) }}</b>
        <div class="tt-sub">{{ labelFor(hoverIndex) }}</div>
      </template>
    </div>
  </div>
</template>

<style scoped>
.oz-line-chart { position: relative; padding: 4px 18px 16px; }
.chart-svg { display: block; width: 100%; height: auto; overflow: visible; }
.grid-line { stroke: var(--oz-border); stroke-width: 1; }
.tick-label { fill: var(--oz-muted); font-size: 9.5px; font-family: 'Inter', sans-serif; font-variant-numeric: tabular-nums; }
.x-label { fill: var(--oz-muted); font-size: 9.5px; font-family: 'Inter', sans-serif; font-weight: 500; }
.x-label.today { fill: var(--oz-accent); font-weight: 700; }
.line-path { fill: none; stroke: var(--oz-accent); stroke-width: 2.25; stroke-linecap: round; stroke-linejoin: round; }
.dot { fill: var(--oz-accent); stroke: var(--oz-surface); stroke-width: 2.5; }
.crosshair { stroke: var(--oz-muted-2); stroke-width: 1; stroke-dasharray: 3 3; }
.end-label { font-size: 10.5px; font-weight: 700; fill: var(--oz-ink-soft); font-variant-numeric: tabular-nums; }
.tooltip {
  position: absolute; pointer-events: none; background: var(--oz-ink-soft); color: var(--oz-surface);
  font-size: 11px; padding: 6px 10px; border-radius: 8px; white-space: nowrap;
  transform: translate(-50%, -100%); transition: opacity .1s;
  z-index: 10; font-variant-numeric: tabular-nums; box-shadow: 0 4px 14px rgba(15,23,42,0.18);
}
.tooltip b { font-weight: 700; }
.tt-sub { opacity: .75; font-size: 10px; margin-top: 2px; }
</style>
