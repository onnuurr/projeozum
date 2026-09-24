<script setup>
import OzIcon from './OzIcon.vue'

// Veri yokken gösterilen blok. Slot: actions (ör. "Filtreyi temizle" butonu)
defineProps({
  icon: { type: String, default: 'search' },
  title: { type: String, required: true },
  description: { type: String, default: '' },
  compact: { type: Boolean, default: false },
})
</script>

<template>
  <div class="oz-empty" :class="{ 'is-compact': compact }">
    <div class="ico"><OzIcon :name="icon" :size="compact ? 18 : 22" /></div>
    <div class="title">{{ title }}</div>
    <p v-if="description" class="desc">{{ description }}</p>
    <div v-if="$slots.actions" class="actions"><slot name="actions" /></div>
  </div>
</template>

<style scoped>
.oz-empty { display: flex; flex-direction: column; align-items: center; text-align: center; padding: 36px 20px; }
.oz-empty.is-compact { padding: 22px 16px; }
.ico {
  width: 48px; height: 48px; border-radius: 14px; margin-bottom: 12px;
  display: flex; align-items: center; justify-content: center;
  background: var(--oz-accent-soft); color: var(--oz-accent);
  box-shadow: inset 0 1px 1px var(--oz-inset-hl), 0 0 0 6px rgba(var(--oz-accent-rgb), .06);
}
.is-compact .ico { width: 38px; height: 38px; border-radius: 11px; margin-bottom: 9px; }
.title { font-size: 13.5px; font-weight: 700; color: var(--oz-ink-soft); }
.desc { margin: 4px 0 0; max-width: 340px; font-size: 12px; line-height: 1.5; color: var(--oz-muted); }
.actions { display: flex; gap: 8px; margin-top: 14px; flex-wrap: wrap; justify-content: center; }
</style>
