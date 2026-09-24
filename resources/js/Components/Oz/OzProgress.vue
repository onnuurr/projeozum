<script setup>
import { computed } from 'vue'
import { motion } from 'motion-v'
import { useOzCountUp } from './useOzCountUp'

// Etiketli doluluk çubuğu. tone verilmezse değere göre: >100 over, >=warnAt warn, aksi ok.
// Çubuk ilk çizimde 0'dan yaylı dolar, değer değişince yeni değere akar; yüzde de sayarak gelir.
// value verilmezse (null) süresi belirsiz yükleme çubuğu olur.
const props = defineProps({
  label: { type: String, default: '' },
  value: { type: Number, default: null },
  tone: { type: String, default: '', validator: (v) => ['', 'ok', 'warn', 'over', 'accent'].includes(v) },
  warnAt: { type: Number, default: 85 },
  // Çubuklar alt alta dizildiğinde kademeli dolması için (saniye)
  delay: { type: Number, default: 0 },
})

const indeterminate = computed(() => props.value == null)

const resolvedTone = computed(() => {
  if (props.tone) return props.tone
  if (indeterminate.value) return 'accent'
  if (props.value > 100) return 'over'
  if (props.value >= props.warnAt) return 'warn'
  return 'ok'
})

const width = computed(() => `${Math.max(0, Math.min(props.value ?? 0, 100))}%`)
const { text: pctText } = useOzCountUp(() => props.value ?? 0)
</script>

<template>
  <div class="oz-progress">
    <div v-if="label" class="row-top">
      <span class="name">{{ label }}</span>
      <span v-if="!indeterminate" class="pct" :class="resolvedTone">%{{ pctText }}</span>
    </div>
    <div
      class="track"
      role="progressbar"
      :aria-valuenow="indeterminate ? undefined : value"
      aria-valuemin="0"
      aria-valuemax="100"
      :aria-label="label || 'Yükleniyor'"
      :aria-busy="indeterminate || undefined"
    >
      <motion.div
        v-if="indeterminate"
        class="fill runner"
        :class="resolvedTone"
        :initial="{ x: '-100%' }"
        :animate="{ x: '250%' }"
        :transition="{ duration: 1.3, ease: [0.45, 0, 0.3, 1], repeat: Infinity }"
      />
      <motion.div
        v-else
        class="fill"
        :class="resolvedTone"
        :initial="{ width: '0%' }"
        :animate="{ width }"
        :transition="{ type: 'spring', stiffness: 70, damping: 16, delay }"
      />
    </div>
  </div>
</template>

<style scoped>
.row-top { display: flex; align-items: baseline; justify-content: space-between; margin-bottom: 6px; }
.name { font-size: 12px; font-weight: 600; color: var(--oz-ink-soft); }
.pct { font-size: 11.5px; font-weight: 700; font-variant-numeric: tabular-nums; }
.pct.ok { color: var(--oz-success); }
.pct.warn { color: var(--oz-warning); }
.pct.over { color: var(--oz-danger); font-weight: 800; }
.pct.accent { color: var(--oz-accent); }
.track { height: 8px; border-radius: 4px; background: var(--oz-track); box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.06); overflow: hidden; }
.fill { height: 100%; border-radius: 4px; position: relative; }
.fill::after {
  content: '';
  position: absolute; inset: 0;
  background: linear-gradient(90deg, transparent 0%, rgba(255, 255, 255, 0.35) 50%, transparent 100%);
  opacity: 0.7;
}
.runner { width: 40%; }
.fill.ok { background: linear-gradient(90deg, #22c55e 0%, #16a34a 100%); }
.fill.warn { background: linear-gradient(90deg, #f59e0b 0%, #d97706 100%); }
.fill.over { background: linear-gradient(90deg, #ef4444 0%, #dc2626 100%); box-shadow: 0 0 10px rgba(220, 38, 38, 0.45); }
.fill.accent { background: linear-gradient(90deg, var(--oz-accent) 0%, var(--oz-accent-hover) 100%); }
</style>
