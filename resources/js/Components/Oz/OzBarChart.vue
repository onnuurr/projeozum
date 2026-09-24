<script setup>
import { computed, ref } from 'vue'
import { motion } from 'motion-v'

// Dikey sütun grafiği. En yüksek sütun vurgulanır; sütunlar soldan sağa kademeli, yaylı büyür.
// values: sayılar  labels: x etiketleri
// tone: accent | info | success | warning | danger
const props = defineProps({
  values: { type: Array, required: true },
  labels: { type: Array, required: true },
  unit: { type: String, default: '' },
  tone: { type: String, default: 'accent' },
  height: { type: Number, default: 180 },
  // Değerleri sütunların üstünde sürekli göster
  showValues: { type: Boolean, default: false },
  highlightMax: { type: Boolean, default: true },
  format: { type: Function, default: (v) => v.toLocaleString('tr-TR') },
})

const hover = ref(null)
const max = computed(() => Math.max(...props.values, 1))
const maxIndex = computed(() => props.values.indexOf(Math.max(...props.values)))
const bars = computed(() => props.values.map((v, i) => ({
  v, i, label: props.labels[i], pct: (v / max.value) * 100,
  strong: (props.highlightMax && i === maxIndex.value) || hover.value === i,
})))
const text = (v) => `${props.format(v)}${props.unit ? ` ${props.unit}` : ''}`
const summary = computed(() => bars.value.map((b) => `${b.label}: ${text(b.v)}`).join(', '))
</script>

<template>
  <div class="oz-bars" :class="`tone-${tone}`" role="img" :aria-label="summary" :style="{ '--h': `${height}px` }">
    <div class="plot" @mouseleave="hover = null">
      <div v-for="b in bars" :key="b.i" class="col" @mouseenter="hover = b.i">
        <motion.div
          class="bar"
          :class="{ strong: b.strong }"
          :initial="{ height: '0%' }"
          :animate="{ height: `${Math.max(b.pct, 1.5)}%` }"
          :transition="{ type: 'spring', stiffness: 110, damping: 17, delay: b.i * 0.05 }"
        >
          <span class="val" :class="{ show: showValues || hover === b.i }">{{ text(b.v) }}</span>
        </motion.div>
      </div>
    </div>
    <div class="labels" aria-hidden="true">
      <span v-for="b in bars" :key="b.i" :class="{ strong: hover === b.i }">{{ b.label }}</span>
    </div>
  </div>
</template>

<style scoped>
.oz-bars { --c: var(--oz-accent); --rgb: var(--oz-accent-rgb); }
.tone-info { --c: var(--oz-info); --rgb: var(--oz-info-rgb); }
.tone-success { --c: var(--oz-success); --rgb: var(--oz-success-rgb); }
.tone-warning { --c: var(--oz-warning); --rgb: var(--oz-warning-rgb); }
.tone-danger { --c: var(--oz-danger); --rgb: var(--oz-danger-rgb); }
.plot {
  height: var(--h); display: flex; align-items: flex-end; gap: 8px;
  padding-top: 22px; border-bottom: 1px solid var(--oz-border);
  background: repeating-linear-gradient(to top, transparent 0, transparent calc(25% - 1px), var(--oz-border-soft) calc(25% - 1px), var(--oz-border-soft) 25%);
  background-origin: content-box; background-clip: content-box;
}
.col { flex: 1; height: 100%; display: flex; flex-direction: column; justify-content: flex-end; align-items: center; min-width: 0; position: relative; cursor: default; }
.bar {
  position: relative; width: 100%; max-width: 36px; border-radius: 6px 6px 2px 2px;
  background: linear-gradient(180deg, rgba(var(--rgb), .35) 0%, rgba(var(--rgb), .18) 100%);
  transition: background .15s, box-shadow .15s;
}
.bar.strong { background: linear-gradient(180deg, var(--c) 0%, rgba(var(--rgb), .75) 100%); box-shadow: 0 4px 12px rgba(var(--rgb), .3); }
.val {
  position: absolute; bottom: calc(100% + 4px); left: 50%; translate: -50% 0; white-space: nowrap;
  font-size: 10.5px; font-weight: 700; color: var(--oz-ink-soft); font-variant-numeric: tabular-nums;
  opacity: 0; transform: translateY(2px); transition: opacity .12s, transform .12s; pointer-events: none;
}
.val.show { opacity: 1; transform: none; }
.labels { display: flex; gap: 8px; padding-top: 7px; }
.labels span { flex: 1; min-width: 0; text-align: center; font-size: 10.5px; font-weight: 600; color: var(--oz-muted-2); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.labels span.strong { color: var(--oz-ink-soft); }
</style>
