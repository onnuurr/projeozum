<script setup>
import { computed } from 'vue'
import OzIcon from './OzIcon.vue'

// Sayfalama. v-model: aktif sayfa (1'den başlar)
const props = defineProps({
  total: { type: Number, required: true },
  pageSize: { type: Number, default: 10 },
  // Aktif sayfanın iki yanında gösterilecek sayfa sayısı
  siblings: { type: Number, default: 1 },
})

const page = defineModel({ type: Number, default: 1 })

const pageCount = computed(() => Math.max(1, Math.ceil(props.total / props.pageSize)))
const from = computed(() => (props.total ? (page.value - 1) * props.pageSize + 1 : 0))
const to = computed(() => Math.min(page.value * props.pageSize, props.total))

// [1, '…', 4, 5, 6, '…', 12]
const pages = computed(() => {
  const n = pageCount.value
  const cur = page.value
  const start = Math.max(2, cur - props.siblings)
  const end = Math.min(n - 1, cur + props.siblings)
  const list = [1]
  if (start > 2) list.push('…')
  for (let i = start; i <= end; i++) list.push(i)
  if (end < n - 1) list.push('…')
  if (n > 1) list.push(n)
  return list
})

function go(p) {
  page.value = Math.min(Math.max(1, p), pageCount.value)
}
</script>

<template>
  <nav class="oz-pagination" aria-label="Sayfalama">
    <span class="range">
      <b>{{ from }}–{{ to }}</b> / {{ total }} kayıt
    </span>
    <div class="pages">
      <button type="button" class="pg" :disabled="page <= 1" aria-label="Önceki sayfa" @click="go(page - 1)">
        <OzIcon name="chevron-left" :size="14" :stroke-width="2.5" />
      </button>
      <template v-for="(p, i) in pages" :key="`${p}-${i}`">
        <span v-if="p === '…'" class="gap" aria-hidden="true">…</span>
        <button
          v-else
          type="button"
          class="pg"
          :class="{ active: p === page }"
          :aria-current="p === page ? 'page' : undefined"
          :aria-label="`Sayfa ${p}`"
          @click="go(p)"
        >{{ p }}</button>
      </template>
      <button type="button" class="pg" :disabled="page >= pageCount" aria-label="Sonraki sayfa" @click="go(page + 1)">
        <OzIcon name="chevron-right" :size="14" :stroke-width="2.5" />
      </button>
    </div>
  </nav>
</template>

<style scoped>
.oz-pagination { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
.range { font-size: 11.5px; color: var(--oz-muted); font-variant-numeric: tabular-nums; }
.range b { color: var(--oz-ink-soft); font-weight: 700; }
.pages { display: flex; align-items: center; gap: 4px; }
.pg {
  min-width: 30px; height: 30px; padding: 0 8px; border-radius: 8px;
  display: inline-flex; align-items: center; justify-content: center;
  font: inherit; font-size: 12px; font-weight: 600; font-variant-numeric: tabular-nums;
  color: var(--oz-ink); background: var(--oz-surface); border: 1px solid var(--oz-border);
  cursor: pointer; transition: background .12s, border-color .12s, color .12s;
}
.pg:hover:not(:disabled):not(.active) { background: var(--oz-surface-2); border-color: var(--oz-muted-2); }
.pg.active { background: var(--oz-accent); border-color: var(--oz-accent); color: #fff; box-shadow: 0 2px 6px rgba(var(--oz-accent-rgb), .3); }
.pg:disabled { opacity: .4; cursor: not-allowed; }
.pg:focus-visible { outline: 2px solid var(--oz-accent); outline-offset: 1px; }
.gap { min-width: 20px; text-align: center; color: var(--oz-muted-2); font-size: 12px; }
</style>
