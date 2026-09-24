<script setup>
import OzIcon from './OzIcon.vue'

// İkonlu liste satırı (onay kuyruğu vb.). Slot: actions (satır altı butonlar)
// Alt alta dizildiğinde aralarına otomatik ayraç çizilir; kapsayıcıya iç boşluğu sayfa verir.
defineProps({
  title: { type: String, required: true },
  subtitle: { type: String, default: '' },
  meta: { type: String, default: '' },
  icon: { type: String, default: '' },
  tone: { type: String, default: 'warning', validator: (v) => ['accent', 'info', 'warning', 'success', 'danger'].includes(v) },
})
</script>

<template>
  <div class="oz-list-item">
    <div v-if="icon" class="ico" :class="`tone-${tone}`"><OzIcon :name="icon" :size="15" /></div>
    <div class="body">
      <div class="title">{{ title }}</div>
      <div v-if="subtitle" class="sub">{{ subtitle }}</div>
      <div v-if="meta" class="meta">{{ meta }}</div>
      <div v-if="$slots.actions" class="actions"><slot name="actions" /></div>
    </div>
  </div>
</template>

<style scoped>
.oz-list-item { display: flex; gap: 11px; padding: 11px; border-radius: 10px; transition: background .12s, transform .14s ease; }
.oz-list-item:hover { background: var(--oz-surface-2); transform: translateX(2px); }
.oz-list-item + .oz-list-item { border-top: 1px solid var(--oz-border-soft); }
.ico { width: 32px; height: 32px; border-radius: 9px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; box-shadow: inset 0 1px 1px var(--oz-inset-hl), 0 2px 4px rgba(15,23,42,0.05); }
.tone-accent { background: var(--oz-accent-soft); color: var(--oz-accent); }
.tone-info { background: var(--oz-info-soft); color: var(--oz-info); }
.tone-warning { background: var(--oz-warning-soft); color: var(--oz-warning); }
.tone-success { background: var(--oz-success-soft); color: var(--oz-success); }
.tone-danger { background: var(--oz-danger-soft); color: var(--oz-danger); }
.body { flex: 1; min-width: 0; }
.title { font-size: 12.5px; font-weight: 700; color: var(--oz-ink-soft); }
.sub { font-size: 11px; color: var(--oz-muted); margin-top: 2px; }
.meta { font-size: 10px; color: var(--oz-muted-2); margin-top: 4px; }
.actions { display: flex; gap: 6px; margin-top: 8px; }
</style>
