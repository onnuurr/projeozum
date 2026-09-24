<script setup>
import { ref, computed, watch, nextTick } from 'vue'
import { useInfiniteScroll } from '@vueuse/core'
import OzIcon from './OzIcon.vue'
import OzInput from './OzInput.vue'
import { ozIcons } from './icons'
import { ozFold } from './ozText'

// İkon seçici: aranabilir ızgara. v-model: ikon adı (ya da options verilirse seçeneğin value'su)
// options: [{ value, icon, label? }] — yalnızca bu ikonlar listelenir, kaydedilen değer `value` olur
//   (ör. DB'de ayrı anahtarla saklanan menü ikonları). Verilmezse kayıttaki (icons.js) tüm ikonlar.
// clearable: seçili ikona tekrar tıklamak seçimi kaldırır (ikonsuz)
// resolve: seçeneğin `icon`unu OzIcon'a verilecek bileşene çevirir (ör. tüm Lucide seti, tembel yükleme)
// pageSize: büyük listelerde önce bu kadarı çizilir, ızgara kaydırıldıkça sonraki sayfa eklenir (0 = hepsi)
const model = defineModel({ type: String, default: '' })
const props = defineProps({
  // Izgara yüksekliği (kaydırılır)
  maxHeight: { type: String, default: '188px' },
  options: { type: Array, default: null },
  clearable: { type: Boolean, default: false },
  resolve: { type: Function, default: null },
  pageSize: { type: Number, default: 0 },
})

const query = ref('')
const all = computed(() => props.options ?? Object.keys(ozIcons).map((n) => ({ value: n, icon: n, label: n })))
const filtered = computed(() => {
  const q = ozFold(query.value.trim())
  return q ? all.value.filter((o) => ozFold(`${o.value} ${o.label ?? ''} ${o.icon}`).includes(q)) : all.value
})
// Seçili ikon ilk sayfada değilse başa alınır, görünür kalsın
const limit = ref(props.pageSize)
const gridEl = ref(null)
watch(query, () => { limit.value = props.pageSize; if (gridEl.value) gridEl.value.scrollTop = 0 })
const visible = computed(() => {
  if (!props.pageSize) return filtered.value
  const list = filtered.value.slice(0, limit.value)
  const sel = filtered.value.find((o) => o.value === model.value)
  return sel && !list.includes(sel) ? [sel, ...list.slice(0, -1)] : list
})
const rest = computed(() => filtered.value.length - visible.value.length)
useInfiniteScroll(gridEl, () => { limit.value += props.pageSize }, {
  distance: 160,
  canLoadMore: () => props.pageSize > 0 && rest.value > 0,
})

function pick(value) {
  model.value = props.clearable && model.value === value ? '' : value
}

function onKey(e, i) {
  const cols = 8
  const map = { ArrowRight: 1, ArrowLeft: -1, ArrowDown: cols, ArrowUp: -cols }
  if (!(e.key in map)) return
  e.preventDefault()
  const grid = e.currentTarget.parentElement
  // son satırda aşağı ok: sonraki sayfayı ekleyip devam et
  if (i + map[e.key] >= visible.value.length && rest.value > 0) {
    limit.value += props.pageSize
    nextTick(() => grid.children[Math.min(visible.value.length - 1, i + map[e.key])]?.focus())
    return
  }
  const next = Math.max(0, Math.min(visible.value.length - 1, i + map[e.key]))
  grid.children[next]?.focus()
}
</script>

<template>
  <div class="oz-icon-picker">
    <OzInput v-model="query" icon="search" type="search" placeholder="İkon ara… (ör. cart, user)" aria-label="İkon ara" />
    <div ref="gridEl" class="grid" role="listbox" aria-label="İkonlar" :style="{ maxHeight }">
      <button
        v-for="(o, i) in visible"
        :key="o.value"
        type="button"
        role="option"
        class="cell"
        :class="{ active: o.value === model }"
        :aria-selected="o.value === model"
        :title="o.label ?? o.value"
        :tabindex="o.value === model || (!visible.some((x) => x.value === model) && i === 0) ? 0 : -1"
        @click="pick(o.value)"
        @keydown="onKey($event, i)"
      >
        <OzIcon :name="resolve ? resolve(o.icon) : o.icon" :size="17" />
      </button>
      <p v-if="rest > 0" class="more" aria-hidden="true">+{{ rest }} ikon · kaydırdıkça yüklenir</p>
      <p v-if="!filtered.length" class="empty">“{{ query }}” ile eşleşen ikon yok</p>
    </div>
  </div>
</template>

<style scoped>
.oz-icon-picker { display: flex; flex-direction: column; gap: 8px; }
.grid {
  display: grid; grid-template-columns: repeat(auto-fill, minmax(38px, 1fr)); gap: 4px;
  overflow-y: auto; padding: 6px; border: 1px solid var(--oz-border); border-radius: 10px; background: var(--oz-surface-2);
}
.cell {
  aspect-ratio: 1; display: flex; align-items: center; justify-content: center;
  border: 1px solid transparent; border-radius: 8px; background: none; color: var(--oz-muted); cursor: pointer;
  transition: background .12s, color .12s, border-color .12s;
}
.cell:hover { background: var(--oz-surface); color: var(--oz-ink); border-color: var(--oz-border); }
.cell.active { background: var(--oz-accent); color: #fff; border-color: transparent; box-shadow: 0 3px 10px rgba(var(--oz-accent-rgb), .3); }
.cell:focus-visible { outline: 2px solid var(--oz-accent); outline-offset: 1px; }
.more { grid-column: 1 / -1; margin: 2px 0 0; padding: 6px; text-align: center; font-size: 11.5px; color: var(--oz-muted-2); }
.empty { grid-column: 1 / -1; margin: 0; padding: 12px; text-align: center; font-size: 12px; color: var(--oz-muted); }
</style>
