<script setup>
import OzIcon from './OzIcon.vue'

// Kompakt özet şeridi: liste/rapor sayfalarının üstünde tek ince satır — ikon + değer + etiket.
// OzStatTile kart sırası listeyi çok aşağı itiyordu; dashboard'larda (trend kartları) OzStatTile kalır.
// items: [{ key, label, value, icon, tone?: accent|info|warning|success|danger, delta?, trend?: up|down|warn, context? }]
// interactive: öğeler buton olur, tıklanınca `select` (item.key) yayılır (ör. duruma göre filtre); active = seçili key
const props = defineProps({
  items: { type: Array, required: true },
  interactive: { type: Boolean, default: false },
  active: { type: [String, Number], default: null },
  label: { type: String, default: 'Özet' },
})
const emit = defineEmits(['select'])

const format = (v) => (typeof v === 'number' ? v.toLocaleString('tr-TR') : v)
</script>

<template>
  <div class="oz-stat-strip" :role="interactive ? 'group' : 'list'" :aria-label="label">
    <component
      :is="interactive ? 'button' : 'div'"
      v-for="s in items"
      :key="s.key ?? s.label"
      :type="interactive ? 'button' : undefined"
      :role="interactive ? undefined : 'listitem'"
      class="item"
      :class="{ interactive, active: interactive && active != null && active === s.key }"
      :aria-pressed="interactive ? active === s.key : undefined"
      @click="interactive && emit('select', s.key)"
    >
      <span class="ico" :class="`tone-${s.tone ?? 'accent'}`"><OzIcon :name="s.icon" :size="13" /></span>
      <b class="value">{{ format(s.value) }}</b>
      <span class="label">{{ s.label }}</span>
      <span v-if="s.delta" class="delta" :class="s.trend ?? 'up'">{{ s.delta }}</span>
      <span v-if="s.context" class="context">{{ s.context }}</span>
    </component>
  </div>
</template>

<style scoped>
.oz-stat-strip {
  display: flex; flex-wrap: wrap; align-items: center; gap: 6px 0; margin-bottom: 12px; padding: 8px 6px;
  background: var(--oz-surface); border: 1px solid var(--oz-border); border-radius: var(--oz-radius);
}
.item {
  display: flex; align-items: center; gap: 7px; padding: 2px 16px; min-width: 0;
  font: inherit; font-size: 12.5px; color: var(--oz-muted); background: none; border: none;
}
.item + .item { border-left: 1px solid var(--oz-border); }
.item.interactive { cursor: pointer; border-radius: 8px; padding-top: 4px; padding-bottom: 4px; transition: background .12s; }
.item.interactive:hover { background: var(--oz-surface-2); }
.item.interactive.active { background: var(--oz-accent-soft); }
.item.interactive.active .label { color: var(--oz-ink-soft); }
.item.interactive:focus-visible { outline: 2px solid var(--oz-accent); outline-offset: 1px; }
.value { font-weight: 700; color: var(--oz-ink-soft); font-variant-numeric: tabular-nums; white-space: nowrap; }
.label { white-space: nowrap; }
.ico { width: 24px; height: 24px; border-radius: 7px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.tone-accent { background: var(--oz-accent-soft); color: var(--oz-accent); }
.tone-info { background: var(--oz-info-soft); color: var(--oz-info); }
.tone-warning { background: var(--oz-warning-soft); color: var(--oz-warning); }
.tone-success { background: var(--oz-success-soft); color: var(--oz-success); }
.tone-danger { background: var(--oz-danger-soft); color: var(--oz-danger); }
.delta { font-size: 11px; font-weight: 700; padding: 1px 6px; border-radius: 999px; }
.delta.up { color: var(--oz-success); background: var(--oz-success-soft); }
.delta.down { color: var(--oz-danger); background: var(--oz-danger-soft); }
.delta.warn { color: var(--oz-warning); background: var(--oz-warning-soft); }
.context { font-size: 11px; color: var(--oz-muted-2, var(--oz-muted)); }
@media (max-width: 640px) {
  .item { flex: 1 1 50%; padding: 4px 10px; }
  .item + .item { border-left: none; }
}
</style>
