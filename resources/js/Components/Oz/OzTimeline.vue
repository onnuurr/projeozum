<script setup>
// Dikey aktivite akışı. items: [{ id?, text, sub }]
defineProps({
  items: { type: Array, required: true },
})
</script>

<template>
  <ol class="oz-timeline">
    <li v-for="item in items" :key="item.id ?? item.text" class="item">
      <div class="rail"><div class="dot" /><div class="line" /></div>
      <div>
        <div class="text">{{ item.text }}</div>
        <div v-if="item.sub" class="sub">{{ item.sub }}</div>
      </div>
    </li>
  </ol>
</template>

<style scoped>
.oz-timeline { list-style: none; margin: 0; padding: 0; }
.item { display: flex; gap: 12px; padding: 10px 0; }
.rail { display: flex; flex-direction: column; align-items: center; }
.dot { width: 8px; height: 8px; border-radius: 50%; background: var(--oz-accent); margin-top: 4px; flex-shrink: 0; box-shadow: 0 0 0 3px var(--oz-accent-soft); }
.line { width: 1.5px; flex: 1; background: var(--oz-border); margin-top: 3px; }
.item:last-child .line { display: none; }
.text { font-size: 12px; font-weight: 600; color: var(--oz-ink-soft); line-height: 1.4; }
.sub { font-size: 11px; color: var(--oz-muted); margin-top: 2px; }
</style>
