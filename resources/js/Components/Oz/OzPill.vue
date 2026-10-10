<script setup>
import { computed, inject } from 'vue'
import OzIcon from './OzIcon.vue'

// Durum rozeti. tone: info | warning | accent | success | danger | neutral
// dot: soldaki durum noktası (varsayılan açık) · icon: nokta yerine ikon (OzIcon adı)
// size: md | sm — verilmezse bulunduğu yere göre: OzTable içinde sm (tablo yoğunluğu), başka yerde md
// pulse: canlı/süren durumlarda nokta nabız atar ("İşleniyor")
const props = defineProps({
  tone: { type: String, default: 'neutral', validator: (v) => ['info', 'warning', 'accent', 'success', 'danger', 'neutral'].includes(v) },
  label: { type: String, default: '' },
  dot: { type: Boolean, default: true },
  icon: { type: String, default: '' },
  size: { type: String, default: null, validator: (v) => v == null || ['md', 'sm'].includes(v) },
  pulse: { type: Boolean, default: false },
})

const contextSize = inject('ozPillSize', 'md')
const resolvedSize = computed(() => props.size ?? contextSize)
</script>

<template>
  <span class="oz-pill" :class="[`tone-${tone}`, `size-${resolvedSize}`]">
    <OzIcon v-if="icon" :name="icon" :size="resolvedSize === 'sm' ? 11 : 12" :stroke-width="2.4" class="pill-ico" />
    <span v-else-if="dot" class="pill-dot" :class="{ pulse }" aria-hidden="true" />
    <slot>{{ label }}</slot>
  </span>
</template>

<style scoped>
.oz-pill {
  --pill-rgb: 100, 116, 139;
  display: inline-flex; align-items: center; gap: 6px;
  height: 22px; padding: 0 9px; border-radius: 999px;
  font-size: 11.5px; font-weight: 600; line-height: 1; letter-spacing: .005em; white-space: nowrap;
  font-variant-numeric: tabular-nums;
  /* İnce iç çerçeve + üstte temanın iç parlaması (kenarlık/gölge yerine; daha sakin) */
  box-shadow: inset 0 0 0 1px rgba(var(--pill-rgb), .2), inset 0 1px 0 var(--oz-inset-hl);
}
.size-sm { height: 18px; padding: 0 7px; gap: 5px; font-size: 10.5px; font-weight: 600; }
.size-sm:not(:has(.pill-dot, .pill-ico)) { padding: 0 6px; }
.oz-pill:has(.pill-ico) { padding-left: 7px; }

.pill-dot {
  position: relative; width: 6px; height: 6px; border-radius: 50%; flex-shrink: 0;
  background: currentColor;
  box-shadow: 0 0 0 2.5px rgba(var(--pill-rgb), .18); /* yumuşak hale (eski neon parlama yerine) */
}
.size-sm .pill-dot { width: 5px; height: 5px; box-shadow: 0 0 0 2px rgba(var(--pill-rgb), .18); }
.pill-dot.pulse::after {
  content: ''; position: absolute; inset: 0; border-radius: 50%; background: currentColor;
  animation: oz-pill-pulse 1.6s ease-out infinite;
}
@keyframes oz-pill-pulse {
  0% { transform: scale(1); opacity: .55; }
  100% { transform: scale(2.8); opacity: 0; }
}
@media (prefers-reduced-motion: reduce) { .pill-dot.pulse::after { animation: none; } }
.pill-ico { flex-shrink: 0; }

.tone-info { --pill-rgb: var(--oz-info-rgb); color: var(--oz-pill-info-fg); background: var(--oz-pill-info-bg); }
.tone-warning { --pill-rgb: var(--oz-warning-rgb); color: var(--oz-pill-warning-fg); background: var(--oz-pill-warning-bg); }
.tone-accent { --pill-rgb: var(--oz-accent-rgb); color: var(--oz-pill-accent-fg); background: var(--oz-pill-accent-bg); }
.tone-success { --pill-rgb: var(--oz-success-rgb); color: var(--oz-pill-success-fg); background: var(--oz-pill-success-bg); }
.tone-danger { --pill-rgb: var(--oz-danger-rgb); color: var(--oz-pill-danger-fg); background: var(--oz-pill-danger-bg); }
.tone-neutral { color: var(--oz-pill-neutral-fg); background: var(--oz-pill-neutral-bg); }
/* nötr noktası metinden bir ton açık: "pasif" hissi */
.tone-neutral .pill-dot { background: var(--oz-muted-2); }
</style>
