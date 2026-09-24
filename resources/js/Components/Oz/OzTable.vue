<script setup>
import { ref, computed, watch, useId } from 'vue'
import { useDebounceFn } from '@vueuse/core'
import OzIcon from './OzIcon.vue'
import OzInput from './OzInput.vue'
import OzButton from './OzButton.vue'
import OzTableFilters from './OzTableFilters.vue'
import { ozFold } from './ozText'
import { filterDefault, isFilterActive, matchesFilter, normalizeOptions } from './ozTableFilter'
import OzSkeleton from './OzSkeleton.vue'
import OzEmptyState from './OzEmptyState.vue'
import OzPagination from './OzPagination.vue'

// Veri tablosu.
// columns: [{ key, label, align?: 'left'|'right', class?, sortable?, sortValue?: (row) => any, width? }]
// Hücre özelleştirme: <template #cell-{key}="{ row, value }">
// Boş durum: #empty slot'u, yoksa emptyTitle/emptyText ile OzEmptyState
// pageSize > 0 verilirse altta sayfalama çıkar (istemci tarafı)
//
// Arama ve filtre:
//   searchable        → üstte genel arama (sütunun searchValue ?? sortValue ?? row[key] değerinde, TR duyarsız)
//   column.filter     → { type: 'select' | 'text' | 'range' | 'date', ... } (ayrıntı: ozTableFilter.js)
//   v-model:search / v-model:filters → durum dışarıdan okunup yazılabilir (bağlanmazsa tablo kendi tutar)
//   manual            → satırlar süzülmez; sunucu tarafı için 'filter-change' ({ search, filters }) 300 ms gecikmeyle yayılır
//   #toolbar          → araç çubuğunun sağına butonlar
//   #empty            → { filtered, clear } alır: filtre sonucu boşsa filtered = true
const props = defineProps({
  columns: { type: Array, required: true },
  rows: { type: Array, default: () => [] },
  rowKey: { type: String, default: 'id' },
  minWidth: { type: String, default: '580px' },
  loading: { type: Boolean, default: false },
  pageSize: { type: Number, default: 0 },
  emptyTitle: { type: String, default: 'Kayıt bulunamadı' },
  emptyText: { type: String, default: '' },
  // Başlangıç sıralaması: { key, dir: 'asc' | 'desc' }
  defaultSort: { type: Object, default: null },
  searchable: { type: Boolean, default: false },
  searchPlaceholder: { type: String, default: 'Tabloda ara…' },
  manual: { type: Boolean, default: false },
  // Filtre paneli başlangıçta açık mı
  filtersOpen: { type: Boolean, default: false },
})

const emit = defineEmits(['row-click', 'sort', 'filter-change'])

const sort = ref(props.defaultSort ? { ...props.defaultSort } : { key: '', dir: 'asc' })
const page = ref(1)

/* ── arama / filtre ── */
const search = defineModel('search', { type: String, default: '' })
const filters = defineModel('filters', { type: Object, default: () => ({}) })
const panelOpen = ref(props.filtersOpen)
const panelId = `oz-tf-${useId()}`

const filterCols = computed(() => props.columns
  .filter((c) => c.filter)
  .map((c) => ({
    ...c,
    _options: c.filter.type === 'select' ? normalizeOptions(c.filter, props.rows.map((r) => cellOf(c, r))) : [],
  })))

const cellOf = (col, row) => (col.filterValue ? col.filterValue(row) : row[col.key])
const activeCount = computed(() => filterCols.value.filter((c) => isFilterActive(c.filter, filters.value[c.key])).length)
const hasQuery = computed(() => search.value.trim() !== '' || activeCount.value > 0)

const searchCols = computed(() => props.columns.filter((c) => c.searchable !== false && c.key))
const searchText = (row) => searchCols.value
  .map((c) => (c.searchValue ? c.searchValue(row) : c.sortValue ? c.sortValue(row) : row[c.key]))
  .filter((v) => v != null && typeof v !== 'object')
  .join(' ')

const filteredRows = computed(() => {
  if (props.manual) return props.rows
  const q = ozFold(search.value.trim())
  const active = filterCols.value.filter((c) => isFilterActive(c.filter, filters.value[c.key]))
  if (!q && !active.length) return props.rows
  return props.rows.filter((row) =>
    (!q || ozFold(searchText(row)).includes(q))
    && active.every((c) => matchesFilter(c.filter, filters.value[c.key], cellOf(c, row))))
})

function clearAll() {
  search.value = ''
  filters.value = Object.fromEntries(filterCols.value.map((c) => [c.key, filterDefault(c.filter)]))
}

const emitFilterChange = useDebounceFn(() => emit('filter-change', { search: search.value, filters: { ...filters.value } }), 300)
watch([search, filters], () => {
  page.value = 1
  emitFilterChange()
}, { deep: true })

const showToolbar = computed(() => props.searchable || filterCols.value.length > 0)


function toggleSort(col) {
  if (!col.sortable) return
  if (sort.value.key !== col.key) sort.value = { key: col.key, dir: 'asc' }
  else if (sort.value.dir === 'asc') sort.value = { key: col.key, dir: 'desc' }
  else sort.value = { key: '', dir: 'asc' }
  page.value = 1
  emit('sort', { ...sort.value })
}

const sortedRows = computed(() => {
  const { key, dir } = sort.value
  if (!key) return filteredRows.value
  const col = props.columns.find((c) => c.key === key)
  const get = col?.sortValue ?? ((row) => row[key])
  const factor = dir === 'asc' ? 1 : -1
  return [...filteredRows.value].sort((a, b) => {
    const av = get(a)
    const bv = get(b)
    if (av == null && bv == null) return 0
    if (av == null) return 1
    if (bv == null) return -1
    if (typeof av === 'number' && typeof bv === 'number') return (av - bv) * factor
    return String(av).localeCompare(String(bv), 'tr', { numeric: true }) * factor
  })
})

const visibleRows = computed(() => {
  if (!props.pageSize) return sortedRows.value
  const start = (page.value - 1) * props.pageSize
  return sortedRows.value.slice(start, start + props.pageSize)
})

// Veri değişip mevcut sayfa boşa düşerse son sayfaya çek
watch(() => filteredRows.value.length, (n) => {
  if (!props.pageSize) return
  const last = Math.max(1, Math.ceil(n / props.pageSize))
  if (page.value > last) page.value = last
})

const skeletonRows = computed(() => Math.min(props.pageSize || 5, 8))

function ariaSort(col) {
  if (!col.sortable) return undefined
  if (sort.value.key !== col.key) return 'none'
  return sort.value.dir === 'asc' ? 'ascending' : 'descending'
}
</script>

<template>
  <div class="oz-table-wrap">
    <div v-if="showToolbar || $slots.toolbar" class="oz-table-toolbar">
      <OzInput
        v-if="searchable"
        v-model="search"
        class="tb-search"
        icon="search"
        type="search"
        :placeholder="searchPlaceholder"
        aria-label="Tabloda ara"
      />
      <OzButton
        v-if="filterCols.length"
        icon="filter"
        :class="{ 'tb-filter-on': panelOpen || activeCount }"
        :aria-expanded="panelOpen"
        :aria-controls="panelId"
        @click="panelOpen = !panelOpen"
      >
        Filtreler
        <span v-if="activeCount" class="tb-count">{{ activeCount }}</span>
      </OzButton>
      <span v-if="hasQuery && !loading" class="tb-result" aria-live="polite">
        <b>{{ filteredRows.length }}</b> / {{ rows.length }} kayıt
      </span>
      <div v-if="$slots.toolbar" class="tb-end"><slot name="toolbar" /></div>
    </div>
    <OzTableFilters
      v-if="filterCols.length"
      v-model:filters="filters"
      v-model:open="panelOpen"
      :columns="filterCols"
      :panel-id="panelId"
      :result-count="filteredRows.length"
      @clear-all="clearAll"
    />

    <div class="oz-table-scroll">
      <table class="oz-table" :style="{ minWidth }" :aria-busy="loading || undefined">
        <thead>
          <tr>
            <th
              v-for="col in columns"
              :key="col.key"
              :style="{ textAlign: col.align || 'left', width: col.width }"
              :aria-sort="ariaSort(col)"
            >
              <button
                v-if="col.sortable"
                type="button"
                class="sort-btn"
                :class="{ active: sort.key === col.key }"
                @click="toggleSort(col)"
              >
                {{ col.label }}
                <span class="sort-ico" :class="sort.key === col.key ? sort.dir : ''" aria-hidden="true">
                  <OzIcon name="chevron-up" :size="11" :stroke-width="3" class="up" />
                  <OzIcon name="chevron-down" :size="11" :stroke-width="3" class="down" />
                </span>
              </button>
              <template v-else>{{ col.label }}</template>
            </th>
          </tr>
        </thead>
        <tbody>
          <template v-if="loading">
            <tr v-for="n in skeletonRows" :key="`sk-${n}`" class="skeleton-row">
              <td v-for="(col, ci) in columns" :key="col.key" :style="{ textAlign: col.align || 'left' }">
                <OzSkeleton :width="ci === 0 ? '70%' : `${45 + ((n * 17 + ci * 23) % 40)}%`" :class="{ 'push-right': col.align === 'right' }" />
              </td>
            </tr>
          </template>
          <template v-else>
            <tr v-for="(row, i) in visibleRows" :key="row[rowKey] ?? i" @click="emit('row-click', row)">
              <td
                v-for="col in columns"
                :key="col.key"
                :class="col.class"
                :style="{ textAlign: col.align || 'left' }"
              >
                <slot :name="`cell-${col.key}`" :row="row" :value="row[col.key]">{{ row[col.key] }}</slot>
              </td>
            </tr>
            <tr v-if="!filteredRows.length" class="empty-row">
              <td :colspan="columns.length">
                <slot name="empty" :filtered="rows.length > 0 && hasQuery" :clear="clearAll">
                  <OzEmptyState
                    v-if="rows.length && hasQuery"
                    icon="filter"
                    title="Filtrelere uyan kayıt yok"
                    description="Aramayı değiştirin ya da bazı filtreleri kaldırın."
                    compact
                  >
                    <template #actions><OzButton size="sm" icon="x" @click="clearAll">Filtreleri temizle</OzButton></template>
                  </OzEmptyState>
                  <OzEmptyState v-else :title="emptyTitle" :description="emptyText" compact />
                </slot>
              </td>
            </tr>
          </template>
        </tbody>
      </table>
    </div>
    <div v-if="pageSize && !loading && filteredRows.length > pageSize" class="foot">
      <OzPagination v-model="page" :total="filteredRows.length" :page-size="pageSize" />
    </div>
  </div>
</template>

<style scoped>
.oz-table-toolbar { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; padding: 0 18px 12px; }
.tb-search { flex: 1 1 220px; max-width: 320px; }
.tb-count {
  min-width: 18px; height: 18px; padding: 0 5px; margin-left: 2px; border-radius: 999px;
  display: inline-flex; align-items: center; justify-content: center;
  font-size: 10px; font-weight: 700; background: var(--oz-accent); color: #fff;
}
.oz-table-toolbar .tb-filter-on { border-color: rgba(var(--oz-accent-rgb), .4); color: var(--oz-accent); background: var(--oz-accent-soft); }
.tb-result { font-size: 12px; color: var(--oz-muted); font-variant-numeric: tabular-nums; }
.tb-result b { color: var(--oz-ink-soft); }
.tb-end { margin-left: auto; display: flex; gap: 8px; align-items: center; }
.oz-table-scroll { overflow-x: auto; padding: 0 4px 8px; }
.oz-table { width: 100%; border-collapse: collapse; }
th {
  font-size: 10.5px; font-weight: 700; color: var(--oz-muted);
  text-transform: uppercase; letter-spacing: .04em; padding: 12px 14px 10px; white-space: nowrap;
  background: var(--oz-th-bg);
  border-bottom: 1px solid var(--oz-border);
}
td { padding: 12px 14px; font-size: 12.5px; border-top: 1px solid var(--oz-border-soft); vertical-align: middle; transition: background 0.18s ease; }
tbody tr:not(.empty-row):not(.skeleton-row):hover td { background: var(--oz-surface-2); }
.empty-row td { padding: 0; }
.skeleton-row td { padding: 16px 14px; }
.push-right { margin-left: auto; }

.sort-btn {
  display: inline-flex; align-items: center; gap: 5px;
  padding: 0; background: none; border: none; cursor: pointer;
  font: inherit; color: inherit; text-transform: inherit; letter-spacing: inherit;
}
.sort-btn:hover, .sort-btn.active { color: var(--oz-ink-soft); }
.sort-btn:focus-visible { outline: 2px solid var(--oz-accent); outline-offset: 2px; border-radius: 3px; }
.sort-ico { display: inline-flex; flex-direction: column; line-height: 0; color: var(--oz-muted-2); }
.sort-ico .up { margin-bottom: -4px; }
.sort-ico.asc .up, .sort-ico.desc .down { color: var(--oz-accent); }
.sort-ico.asc .down, .sort-ico.desc .up { opacity: .35; }

.foot { padding: 10px 18px 14px; border-top: 1px solid var(--oz-border-soft); }

/* Hazır hücre stilleri: column.class ile kullanılır */
td.oz-cell-id { white-space: nowrap; font-weight: 700; color: var(--oz-accent); font-variant-numeric: tabular-nums; letter-spacing: -0.01em; }
td.oz-cell-strong { font-weight: 700; color: var(--oz-ink-soft); }
td.oz-cell-amount { white-space: nowrap; font-weight: 700; font-variant-numeric: tabular-nums; color: var(--oz-ink-soft); }
@media (max-width: 640px) {
  .oz-table-toolbar { padding: 0 12px 12px; }
  .tb-search { max-width: none; flex-basis: 100%; }
  th { padding: 10px 10px 8px; }
  td { padding: 11px 10px; }
  td.oz-cell-strong { min-width: 120px; }
}
td :deep(.oz-cell-sub) { color: var(--oz-muted); font-size: 11px; margin-top: 2px; }
</style>
