<script setup>
import { motion } from 'motion-v'

// Dönen yükleme göstergesi. tone: accent | current (bulunduğu metnin rengi) | muted
// label ekran okuyucular için; görünür metin gerekiyorsa yanına yazılır.
defineProps({
  size: { type: Number, default: 18 },
  tone: { type: String, default: 'accent', validator: (v) => ['accent', 'current', 'muted'].includes(v) },
  label: { type: String, default: 'Yükleniyor' },
})
</script>

<template>
  <span class="oz-spinner" :class="`tone-${tone}`" role="status" :aria-label="label" :style="{ width: `${size}px`, height: `${size}px` }">
    <motion.svg
      viewBox="0 0 24 24"
      :animate="{ rotate: 360 }"
      :transition="{ duration: 0.8, ease: 'linear', repeat: Infinity }"
    >
      <circle class="track" cx="12" cy="12" r="9.5" />
      <motion.circle
        class="arc"
        cx="12" cy="12" r="9.5"
        :animate="{ strokeDasharray: ['6 60', '34 60', '6 60'], strokeDashoffset: [0, -12, -54] }"
        :transition="{ duration: 1.4, ease: 'easeInOut', repeat: Infinity }"
      />
    </motion.svg>
  </span>
</template>

<style scoped>
.oz-spinner { display: inline-flex; flex-shrink: 0; }
.oz-spinner svg { width: 100%; height: 100%; }
circle { fill: none; stroke-width: 3; }
.track { stroke: currentColor; opacity: .18; }
.arc { stroke: currentColor; stroke-linecap: round; stroke-dasharray: 20 60; }
.tone-accent { color: var(--oz-accent); }
.tone-current { color: currentColor; }
.tone-muted { color: var(--oz-muted-2); }
</style>
