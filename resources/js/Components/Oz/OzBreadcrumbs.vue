<script setup>
import { Link } from '@inertiajs/vue3'
import OzIcon from './OzIcon.vue'

// Konum izi. items: [{ label, to?, href?, icon? }] — son öğe geçerli sayfa sayılır.
//   <OzBreadcrumbs :items="[{ label: 'Üretim', to: '/production' }, { label: '#IE-2292' }]" />
defineProps({
  items: { type: Array, required: true },
})
</script>

<template>
  <nav class="oz-crumbs" aria-label="Konum">
    <ol>
      <li v-for="(item, i) in items" :key="i">
        <OzIcon v-if="i > 0" name="chevron-right" :size="12" class="sep" aria-hidden="true" />
        <span v-if="i === items.length - 1" class="crumb current" aria-current="page">
          <OzIcon v-if="item.icon" :name="item.icon" :size="13" />{{ item.label }}
        </span>
        <Link v-else-if="item.to" :href="item.to" class="crumb">
          <OzIcon v-if="item.icon" :name="item.icon" :size="13" />{{ item.label }}
        </Link>
        <a v-else-if="item.href" :href="item.href" class="crumb">
          <OzIcon v-if="item.icon" :name="item.icon" :size="13" />{{ item.label }}
        </a>
        <span v-else class="crumb plain">
          <OzIcon v-if="item.icon" :name="item.icon" :size="13" />{{ item.label }}
        </span>
      </li>
    </ol>
  </nav>
</template>

<style scoped>
ol { display: flex; flex-wrap: wrap; align-items: center; gap: 4px; margin: 0; padding: 0; list-style: none; }
li { display: inline-flex; align-items: center; gap: 4px; min-width: 0; }
.sep { color: var(--oz-muted-2); flex-shrink: 0; }
.crumb {
  display: inline-flex; align-items: center; gap: 5px; max-width: 220px;
  padding: 3px 6px; border-radius: 6px;
  font-size: 12px; font-weight: 500; color: var(--oz-muted); text-decoration: none;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
a.crumb:hover { color: var(--oz-accent); background: var(--oz-accent-soft); }
a.crumb:focus-visible { outline: 2px solid var(--oz-accent); outline-offset: 1px; }
.current { color: var(--oz-ink-soft); font-weight: 700; }
</style>
