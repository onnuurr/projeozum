<script setup>
// Noktalı durum rozeti. tone: info | warning | accent | success | danger | neutral
defineProps({
  tone: { type: String, default: 'neutral', validator: (v) => ['info', 'warning', 'accent', 'success', 'danger', 'neutral'].includes(v) },
  label: { type: String, default: '' },
})
</script>

<template>
  <span class="oz-pill" :class="`tone-${tone}`"><slot>{{ label }}</slot></span>
</template>

<style scoped>
.oz-pill { display: inline-flex; align-items: center; gap: 5px; font-size: 11px; font-weight: 700; padding: 3px 9px; border-radius: 999px; white-space: nowrap; border: 1px solid transparent; box-shadow: 0 1px 2px rgba(15, 23, 42, 0.03); }
.oz-pill::before { content: ''; width: 6px; height: 6px; border-radius: 50%; background: currentColor; box-shadow: 0 0 6px currentColor; }
.tone-info { color: var(--oz-info); background: var(--oz-pill-info-bg); border-color: rgba(var(--oz-info-rgb), 0.22); }
.tone-warning { color: var(--oz-warning); background: var(--oz-pill-warning-bg); border-color: rgba(var(--oz-warning-rgb), 0.25); }
.tone-accent { color: var(--oz-accent); background: var(--oz-pill-accent-bg); border-color: rgba(var(--oz-accent-rgb), 0.22); }
.tone-success { color: var(--oz-success); background: var(--oz-pill-success-bg); border-color: rgba(var(--oz-success-rgb), 0.22); }
.tone-danger { color: var(--oz-danger); background: var(--oz-pill-danger-bg); border-color: rgba(var(--oz-danger-rgb), 0.25); }
.tone-neutral { color: var(--oz-muted); background: var(--oz-pill-neutral-bg); border-color: var(--oz-border); }
</style>
