<script setup>
// Yükleniyor yer tutucusu. variant: text (satır) | rect | circle
defineProps({
  variant: { type: String, default: 'text', validator: (v) => ['text', 'rect', 'circle'].includes(v) },
  width: { type: String, default: '100%' },
  height: { type: String, default: '' },
})
</script>

<template>
  <span
    class="oz-skeleton"
    :class="`is-${variant}`"
    :style="{ width: variant === 'circle' ? (height || width) : width, height: height || undefined }"
    aria-hidden="true"
  />
</template>

<style scoped>
.oz-skeleton {
  display: block; position: relative; overflow: hidden;
  background: var(--oz-track);
}
.is-text { height: 10px; border-radius: 5px; }
.is-rect { height: 80px; border-radius: 10px; }
.is-circle { aspect-ratio: 1; border-radius: 50%; height: auto; }
.oz-skeleton::after {
  content: ''; position: absolute; inset: 0;
  background: linear-gradient(90deg, transparent 0%, var(--oz-surface) 50%, transparent 100%);
  opacity: .55;
  transform: translateX(-100%);
  animation: oz-shimmer 1.3s ease-in-out infinite;
}
@keyframes oz-shimmer { to { transform: translateX(100%); } }
@media (prefers-reduced-motion: reduce) { .oz-skeleton::after { animation: none; } }
</style>
