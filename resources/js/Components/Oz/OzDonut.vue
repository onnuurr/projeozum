<script setup>
import { computed, ref } from 'vue'
import { motion } from 'motion-v'
import { useOzCountUp } from './useOzCountUp'

// Halka grafik + açıklama. segments: [{ label, value, tone? }]
// tone: accent | info | success | warning | danger | neutral (verilmezse sırayla atanır)
// Ortada toplam (veya üzerine gelinen dilim) yazar. Dilimler sırayla çizilerek açılır, toplam sayarak gelir.
const props = defineProps({
  segments: { type: Array, required: true },
  centerLabel: { type: String, default: 'Toplam' },
  unit: { type: String, default: '' },
  size: { type: Number, default: 150 },
  thickness: { type: Number, default: 16 },
  format: { type: Function, default: (v) => v.toLocaleString('tr-TR') },
  // Açıklamayı alta al (dar kartlar için)
  stacked: { type: Boolean, default: false },
})

const autoTones = ['accent', 'info', 'success', 'warning', 'danger', 'neutral']
const hover = ref(null)

const total = computed(() => props.segments.reduce((s, x) => s + x.value, 0))
const R = 50
const C = 2 * Math.PI * R
// Dilimler arasında küçük boşluk; tek dilimde boşluk yok
const GAP = computed(() => (props.segments.filter((s) => s.value > 0).length > 1 ? 1.2 : 0))

const arcs = computed(() => {
  let offset = 0
  return props.segments.map((s, i) => {
    const frac = total.value ? s.value / total.value : 0
    const len = Math.max(frac * C - GAP.value, 0)
    const arc = { ...s, i, tone: s.tone || autoTones[i % autoTones.length], pct: frac * 100, dash: `${len} ${C - len}`, offset: -offset }
    offset += frac * C
    return arc
  })
})

const { value: totalAnimated } = useOzCountUp(() => total.value)
const center = computed(() => {
  const a = hover.value == null ? null : arcs.value[hover.value]
  return a
    ? { value: `%${a.pct.toFixed(a.pct < 10 ? 1 : 0)}`, label: a.label }
    : { value: props.format(Math.round(totalAnimated.value)), label: props.centerLabel }
})
const text = (v) => `${props.format(v)}${props.unit ? ` ${props.unit}` : ''}`
const summary = computed(() => arcs.value.map((a) => `${a.label}: ${text(a.value)} (%${a.pct.toFixed(0)})`).join(', '))
</script>

<template>
  <div class="oz-donut" :class="{ 'is-stacked': stacked }">
    <div class="donut-ring" :style="{ width: `${size}px`, height: `${size}px` }" role="img" :aria-label="summary">
      <svg viewBox="0 0 120 120" @mouseleave="hover = null">
        <circle cx="60" cy="60" :r="R" class="track" :stroke-width="thickness * 120 / size" />
        <motion.circle
          v-for="a in arcs"
          :key="a.i"
          cx="60" cy="60" :r="R"
          class="arc"
          :class="[`tone-${a.tone}`, { dim: hover != null && hover !== a.i }]"
          :stroke-width="(hover === a.i ? thickness + 4 : thickness) * 120 / size"
          :initial="{ strokeDasharray: `0 ${C}`, strokeDashoffset: a.offset }"
          :animate="{ strokeDasharray: a.dash, strokeDashoffset: a.offset }"
          :transition="{ duration: 0.7, ease: [0.16, 1, 0.3, 1], delay: a.i * 0.08 }"
          @mouseenter="hover = a.i"
        />
      </svg>
      <div class="center" aria-hidden="true">
        <b>{{ center.value }}</b>
        <span>{{ center.label }}</span>
      </div>
    </div>
    <ul class="legend">
      <li v-for="a in arcs" :key="a.i" :class="{ dim: hover != null && hover !== a.i }" @mouseenter="hover = a.i" @mouseleave="hover = null">
        <i :class="`tone-${a.tone}`" />
        <span class="l-label">{{ a.label }}</span>
        <span class="l-val">{{ text(a.value) }}</span>
        <span class="l-pct">%{{ a.pct.toFixed(0) }}</span>
      </li>
    </ul>
  </div>
</template>

<style scoped>
.oz-donut { display: flex; align-items: center; gap: 22px; }
.oz-donut.is-stacked { flex-direction: column; align-items: stretch; }
.is-stacked .donut-ring { align-self: center; }
.donut-ring { position: relative; flex-shrink: 0; }
svg { width: 100%; height: 100%; transform: rotate(-90deg); overflow: visible; }
circle { fill: none; }
.track { stroke: var(--oz-track); }
.arc { stroke: var(--c); transition: stroke-width .15s, opacity .15s; cursor: pointer; }
.arc.dim { opacity: .35; }
.center { position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 2px; text-align: center; pointer-events: none; }
.center b { font-size: 20px; font-weight: 800; color: var(--oz-ink-soft); letter-spacing: -.02em; font-variant-numeric: tabular-nums; }
.center span { font-size: 10.5px; font-weight: 600; color: var(--oz-muted); max-width: 70%; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

.tone-accent { --c: var(--oz-accent); }
.tone-info { --c: var(--oz-info); }
.tone-success { --c: var(--oz-success); }
.tone-warning { --c: var(--oz-warning); }
.tone-danger { --c: var(--oz-danger); }
.tone-neutral { --c: var(--oz-muted-2); }

.legend { flex: 1; min-width: 0; margin: 0; padding: 0; list-style: none; display: flex; flex-direction: column; gap: 4px; }
.legend li { display: flex; align-items: center; gap: 9px; padding: 6px 8px; border-radius: 8px; font-size: 12px; cursor: default; transition: background .12s, opacity .12s; }
.legend li:hover { background: var(--oz-surface-2); }
.legend li.dim { opacity: .5; }
.legend i { width: 9px; height: 9px; border-radius: 3px; background: var(--c); flex-shrink: 0; }
.l-label { flex: 1; min-width: 0; color: var(--oz-ink-soft); font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.l-val { color: var(--oz-muted); font-variant-numeric: tabular-nums; }
.l-pct { width: 38px; text-align: right; font-weight: 700; color: var(--oz-ink-soft); font-variant-numeric: tabular-nums; }

@media (max-width: 420px) { .oz-donut { flex-direction: column; align-items: stretch; } .donut-ring { align-self: center; } }
</style>
