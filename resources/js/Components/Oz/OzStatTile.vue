<script setup>
import { computed } from 'vue'
import OzIcon from './OzIcon.vue'
import { useOzCountUp } from './useOzCountUp'

// tone: ikon rengi (accent | info | warning | success | danger)
// trend: değişim rozeti (up | down | warn)
// value sayı ya da "₺482.600" / "%4,2" gibi metin olabilir; sayısal kısmı 0'dan sayarak gelir
const props = defineProps({
  label: { type: String, required: true },
  value: { type: [String, Number], required: true },
  icon: { type: String, required: true },
  tone: { type: String, default: 'accent', validator: (v) => ['accent', 'info', 'warning', 'success', 'danger'].includes(v) },
  delta: { type: String, default: '' },
  context: { type: String, default: '' },
  trend: { type: String, default: 'up', validator: (v) => ['up', 'down', 'warn'].includes(v) },
})

const { text: valueText } = useOzCountUp(() => props.value)

const trendIcon = computed(() => ({ up: 'arrow-up-right', down: 'arrow-down-right', warn: 'alert' })[props.trend])
</script>

<template>
  <!-- Dış sarmalayıcı container query kapsayıcısıdır (kutu kendi genişliğine göre sıkışır) -->
  <div class="oz-stat-wrap">
    <div class="oz-stat">
      <div class="top">
        <span class="label">{{ label }}</span>
        <div class="ico" :class="`tone-${tone}`"><OzIcon :name="icon" /></div>
      </div>
      <div class="value">{{ valueText }}</div>
      <div v-if="delta" class="delta" :class="trend">
        <OzIcon :name="trendIcon" :size="10" :stroke-width="3" />
        {{ delta }} <span v-if="context" class="ctx">{{ context }}</span>
      </div>
    </div>
  </div>
</template>

<style scoped>
.oz-stat {
  height: 100%; padding: 16px 18px; display: flex; flex-direction: column; gap: 8px;
  background: var(--oz-stat-bg);
  border: 1px solid var(--oz-border);
  border-radius: var(--oz-radius); box-shadow: var(--oz-shadow-sm);
  transition: transform .15s, box-shadow .15s, border-color .15s;
}
.oz-stat:hover { transform: translateY(-2px); box-shadow: var(--oz-shadow); border-color: var(--oz-border-strong); }
.top { display: flex; align-items: center; justify-content: space-between; }
.label { font-size: 12px; font-weight: 600; color: var(--oz-muted); }
.ico { width: 34px; height: 34px; border-radius: 9px; display: flex; align-items: center; justify-content: center; box-shadow: inset 0 1px 1px var(--oz-inset-hl), 0 2px 6px -1px rgba(15, 23, 42, 0.06); }
.tone-accent { background: var(--oz-accent-soft); color: var(--oz-accent); }
.tone-info { background: var(--oz-info-soft); color: var(--oz-info); }
.tone-warning { background: var(--oz-warning-soft); color: var(--oz-warning); }
.tone-success { background: var(--oz-success-soft); color: var(--oz-success); }
.tone-danger { background: var(--oz-danger-soft); color: var(--oz-danger); }
.value { font-size: 28px; font-weight: 800; color: var(--oz-ink-soft); font-variant-numeric: tabular-nums; letter-spacing: -.03em; margin-top: 2px; }
.delta { font-size: 11px; font-weight: 700; display: inline-flex; align-items: center; gap: 4px; width: fit-content; padding: 3px 8px; border-radius: 999px; }
.delta.up { color: var(--oz-success); background: var(--oz-success-soft); border: 1px solid rgba(var(--oz-success-rgb), 0.2); }
.delta.down { color: var(--oz-danger); background: var(--oz-danger-soft); border: 1px solid rgba(var(--oz-danger-rgb), 0.2); }
.delta.warn { color: var(--oz-warning); background: var(--oz-warning-soft); border: 1px solid rgba(var(--oz-warning-rgb), 0.2); }
.ctx { color: var(--oz-muted); font-weight: 500; margin-left: 2px; }

/* Dar kutu (telefonda 2 sütun): ölçü kutunun kendi genişliğine göre küçülür */
.oz-stat-wrap { container-type: inline-size; min-width: 0; }
@container (max-width: 210px) {
  .oz-stat { padding: 12px 13px; gap: 6px; }
  .label { font-size: 11px; line-height: 1.3; }
  .top { align-items: flex-start; gap: 6px; }
  .ico { width: 28px; height: 28px; border-radius: 8px; flex-shrink: 0; }
  .ico :deep(svg) { width: 14px; height: 14px; }
  .value { font-size: 21px; }
  .delta { padding: 2px 7px; font-size: 10.5px; }
  .ctx { display: none; }
}
</style>
