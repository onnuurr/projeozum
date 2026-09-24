<script setup>
// Sayfa başlığı. Slots: subtitle (başlık altı zengin metin), actions (sağdaki butonlar)
defineProps({
  title: { type: String, required: true },
  subtitle: { type: String, default: '' },
})
</script>

<template>
  <div class="oz-page-head">
    <div>
      <h1>{{ title }}</h1>
      <p v-if="subtitle || $slots.subtitle"><slot name="subtitle">{{ subtitle }}</slot></p>
    </div>
    <div v-if="$slots.actions" class="actions"><slot name="actions" /></div>
  </div>
</template>

<style scoped>
.oz-page-head { display: flex; align-items: flex-end; justify-content: space-between; gap: 16px; flex-wrap: wrap; margin-bottom: 16px; }
h1 { margin: 0; font-size: 22px; font-weight: 800; color: var(--oz-ink-soft); letter-spacing: -.02em; }
p { margin: 4px 0 0; font-size: 12.5px; color: var(--oz-muted); }
p :deep(b) { color: var(--oz-ink); font-weight: 600; }
.actions { display: flex; gap: 8px; flex-wrap: wrap; }
</style>
