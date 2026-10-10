<script setup>
import { ref, computed, watch, useId, getCurrentInstance, provide } from 'vue'
import { useDebounceFn, useResizeObserver } from '@vueuse/core'
import OzIcon from './OzIcon.vue'
import OzInput from './OzInput.vue'
import OzButton from './OzButton.vue'
import OzCheckbox from './OzCheckbox.vue'
import OzTableFilters from './OzTableFilters.vue'
import { ozFold } from './ozText'
import { filterDefault, isFilterActive, matchesFilter, normalizeOptions } from './ozTableFilter'
import OzSkeleton from './OzSkeleton.vue'
import OzEmptyState from './OzEmptyState.vue'
import OzPagination from './OzPagination.vue'

// Veri tablosu.
// columns: [{ key, label, align?: 'left'|'right', class?, sortable?, sortValue?: (row) => any, width? }]
// Hücre özelleştirme: <template #cell-{key}="{ row, value, depth }">
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
//
// Seçim (selectable):
//   v-model:selected  → seçili satır anahtarları (rowKey) dizisi; isRowSelectable(row) ile kısıtlanabilir
//   başlıktaki kutu sayfadakileri seçer; sayfa tamamen seçilince "filtrelenen N kaydın tümünü seç" çıkar
//   #bulk="{ selected, clear }" → seçim varken alttaki yüzen çubuğa toplu işlem butonları; itemLabel: "3 ürün seçili"
//
// Ağaç (tree):
//   satırlar parentKey (varsayılan parent_id) ile iç içe çizilir; treeColumn sütununda girinti + aç/kapa.
//   Kardeşler mevcut sıralamaya göre dizilir. Arama/filtrede eşleşenler üst satırlarıyla gösterilir
//   (üstler soluk "bağlam" satırı, seçilemez). Sayfalama kök gruplarıyla: bir ağaç iki sayfaya bölünmez.
//
// Başlık yapışkandır (tablo kartına sığdığında; sığmayıp yatay kaydırma gerektiğinde tarayıcı gereği kapanır).
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
  selectable: { type: Boolean, default: false },
  isRowSelectable: { type: Function, default: null },
  itemLabel: { type: String, default: 'kayıt' },
  tree: { type: Boolean, default: false },
  parentKey: { type: String, default: 'parent_id' },
  treeColumn: { type: String, default: '' },
})

const emit = defineEmits(['row-click', 'sort', 'filter-change'])
// Tablo içindeki OzPill'ler varsayılan olarak küçük boy (yoğun satırlarda göz yormasın)
provide('ozPillSize', 'sm')
// row-click dinleyicisi varsa satırlar tıklanabilir görünür (imleç + vurgu)
const clickable = computed(() => !!getCurrentInstance()?.vnode.props?.onRowClick)

const sort = ref(props.defaultSort ? { ...props.defaultSort } : { key: '', dir: 'asc' })
const page = ref(1)
const keyOf = (row) => row[props.rowKey]

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

/* ── sıralama ── */
function toggleSort(col) {
  if (!col.sortable) return
  if (sort.value.key !== col.key) sort.value = { key: col.key, dir: 'asc' }
  else if (sort.value.dir === 'asc') sort.value = { key: col.key, dir: 'desc' }
  else sort.value = { key: '', dir: 'asc' }
  page.value = 1
  emit('sort', { ...sort.value })
}

function sortRows(list) {
  const { key, dir } = sort.value
  if (!key) return list
  const col = props.columns.find((c) => c.key === key)
  const get = col?.sortValue ?? ((row) => row[key])
  const factor = dir === 'asc' ? 1 : -1
  return [...list].sort((a, b) => {
    const av = get(a)
    const bv = get(b)
    if (av == null && bv == null) return 0
    if (av == null) return 1
    if (bv == null) return -1
    if (typeof av === 'number' && typeof bv === 'number') return (av - bv) * factor
    return String(av).localeCompare(String(bv), 'tr', { numeric: true }) * factor
  })
}

/* ── ağaç ── */
const treeCol = computed(() => props.treeColumn || props.columns[0]?.key)
// Tüm satırlar ağaç sırasıyla: { row, depth, ancestors, childCount, root }. Ebeveyni listede olmayan
// satır köke düşer; bozuk veride döngüye karşı `seen`.
const treeAll = computed(() => {
  if (!props.tree) return []
  const ordered = sortRows(props.rows)
  const byKey = new Map(ordered.map((r) => [keyOf(r), r]))
  const children = new Map()
  const roots = []
  for (const r of ordered) {
    const pk = r[props.parentKey]
    if (pk != null && pk !== keyOf(r) && byKey.has(pk)) {
      if (!children.has(pk)) children.set(pk, [])
      children.get(pk).push(r)
    } else roots.push(r)
  }
  const out = []
  const seen = new Set()
  const walk = (r, depth, ancestors, root) => {
    if (seen.has(keyOf(r))) return
    seen.add(keyOf(r))
    const kids = children.get(keyOf(r)) ?? []
    out.push({ row: r, depth, ancestors, childCount: kids.length, root })
    kids.forEach((k) => walk(k, depth + 1, [...ancestors, keyOf(r)], root))
  }
  roots.forEach((r) => walk(r, 0, [], keyOf(r)))
  ordered.filter((r) => !seen.has(keyOf(r))).forEach((r) => walk(r, 0, [], keyOf(r)))
  return out
})

const collapsed = ref(new Set())
const hasNested = computed(() => treeAll.value.some((n) => n.childCount > 0))
const parentKeys = computed(() => treeAll.value.filter((n) => n.childCount > 0).map((n) => keyOf(n.row)))
const allCollapsed = computed(() => parentKeys.value.length > 0 && parentKeys.value.every((k) => collapsed.value.has(k)))
function toggleNode(key) {
  const next = new Set(collapsed.value)
  next.has(key) ? next.delete(key) : next.add(key)
  collapsed.value = next
}
function toggleAllNodes() {
  collapsed.value = allCollapsed.value ? new Set() : new Set(parentKeys.value)
}

// Çizilecek satırlar (sayfalamadan önce)
const displayRows = computed(() => {
  if (!props.tree) {
    return sortRows(filteredRows.value).map((row) => ({ row, depth: 0, childCount: 0, context: false, root: keyOf(row) }))
  }
  if (hasQuery.value && !props.manual) {
    const hits = new Set(filteredRows.value.map(keyOf))
    const keep = new Set(hits)
    treeAll.value.forEach((n) => { if (hits.has(keyOf(n.row))) n.ancestors.forEach((a) => keep.add(a)) })
    return treeAll.value.filter((n) => keep.has(keyOf(n.row))).map((n) => ({ ...n, context: !hits.has(keyOf(n.row)) }))
  }
  return treeAll.value.filter((n) => !n.ancestors.some((a) => collapsed.value.has(a))).map((n) => ({ ...n, context: false }))
})

// Sayfalama birimi: ağaçta kök grubu, düz tabloda satır
const groups = computed(() => {
  if (!props.tree) return displayRows.value.map((n) => [n])
  const list = []
  let last
  for (const n of displayRows.value) {
    if (n.root !== last) { list.push([]); last = n.root }
    list.at(-1).push(n)
  }
  return list
})

const visibleNodes = computed(() => {
  if (!props.pageSize) return displayRows.value
  const start = (page.value - 1) * props.pageSize
  return groups.value.slice(start, start + props.pageSize).flat()
})

// Veri değişip mevcut sayfa boşa düşerse son sayfaya çek
watch(() => groups.value.length, (n) => {
  if (!props.pageSize) return
  const last = Math.max(1, Math.ceil(n / props.pageSize))
  if (page.value > last) page.value = last
})

/* ── seçim ── */
const selected = defineModel('selected', { type: Array, default: () => [] })
const selectedSet = computed(() => new Set(selected.value))
const canSelect = (node) => !node.context && (!props.isRowSelectable || props.isRowSelectable(node.row))
const selectableOnPage = computed(() => visibleNodes.value.filter(canSelect).map((n) => keyOf(n.row)))
const selectableFiltered = computed(() => filteredRows.value
  .filter((r) => !props.isRowSelectable || props.isRowSelectable(r))
  .map(keyOf))
const pageAllSelected = computed(() => selectableOnPage.value.length > 0 && selectableOnPage.value.every((k) => selectedSet.value.has(k)))
const filteredAllSelected = computed(() => selectableFiltered.value.length > 0 && selectableFiltered.value.every((k) => selectedSet.value.has(k)))
// Sayfa tamamen seçili ama filtrelenenlerin hepsi değil → "tümünü seç" önerisi
const offerSelectAll = computed(() => props.selectable && pageAllSelected.value && !filteredAllSelected.value
  && selectableFiltered.value.length > selectableOnPage.value.length)

function toggleRowSelect(key) {
  const next = new Set(selected.value)
  next.has(key) ? next.delete(key) : next.add(key)
  selected.value = [...next]
}
function togglePageSelect() {
  const next = new Set(selected.value)
  if (pageAllSelected.value) selectableOnPage.value.forEach((k) => next.delete(k))
  else selectableOnPage.value.forEach((k) => next.add(k))
  selected.value = [...next]
}
function selectAllFiltered() {
  selected.value = [...new Set([...selected.value, ...selectableFiltered.value])]
}
function clearSelection() { selected.value = [] }

// Silinen / artık listede olmayan satırlar seçimden düşer
watch(() => props.rows, (rows) => {
  if (!selected.value.length) return
  const keys = new Set(rows.map(keyOf))
  const kept = selected.value.filter((k) => keys.has(k))
  if (kept.length !== selected.value.length) selected.value = kept
})

defineExpose({ filteredRows, clearSelection })

/* ── düzen ── */
const showToolbar = computed(() => props.searchable || filterCols.value.length > 0 || (props.tree && hasNested.value))
const colCount = computed(() => props.columns.length + (props.selectable ? 1 : 0))
const skeletonRows = computed(() => Math.min(props.pageSize || 5, 8))

// Yapışkan başlık yalnızca yatay kaydırma kabı yokken çalışır: tablo sığıyorsa kap "clip" olur
// (başlık sayfa kaydırmasına yapışır), sığmıyorsa "auto" (yatay kaydırma; başlık yapışmaz).
const scrollEl = ref(null)
const tableEl = ref(null)
const overflowing = ref(false)
useResizeObserver(scrollEl, () => {
  if (scrollEl.value && tableEl.value) overflowing.value = tableEl.value.offsetWidth > scrollEl.value.clientWidth + 1
})

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
      <OzButton
        v-if="tree && hasNested && !hasQuery"
        :icon="allCollapsed ? 'chevron-right' : 'chevron-down'"
        @click="toggleAllNodes"
      >
        {{ allCollapsed ? 'Tümünü Aç' : 'Tümünü Kapat' }}
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

    <div v-if="offerSelectAll" class="select-all-bar" role="status">
      Bu sayfadaki <b>{{ selectableOnPage.length }}</b> {{ itemLabel }} seçildi.
      <button type="button" class="link-btn" @click="selectAllFiltered">
        {{ hasQuery ? 'Filtrelenen' : 'Tüm' }} {{ selectableFiltered.length }} {{ itemLabel }} seç
      </button>
    </div>

    <div ref="scrollEl" class="oz-table-scroll" :class="{ overflowing }">
      <table ref="tableEl" class="oz-table" :style="{ minWidth }" :aria-busy="loading || undefined">
        <thead>
          <tr>
            <th v-if="selectable" class="col-select">
              <OzCheckbox
                :model-value="pageAllSelected"
                :disabled="!selectableOnPage.length"
                aria-label="Bu sayfadakilerin tümünü seç"
                @update:model-value="togglePageSelect"
              />
            </th>
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
                :class="{ active: sort.key === col.key, right: col.align === 'right' }"
                :title="`${col.label} sütununa göre sırala`"
                @click="toggleSort(col)"
              >
                {{ col.label }}
                <OzIcon
                  class="sort-ico"
                  :name="sort.key === col.key && sort.dir === 'desc' ? 'chevron-down' : 'chevron-up'"
                  :size="12"
                  :stroke-width="2.6"
                  aria-hidden="true"
                />
              </button>
              <template v-else>{{ col.label }}</template>
            </th>
          </tr>
        </thead>
        <tbody>
          <template v-if="loading">
            <tr v-for="n in skeletonRows" :key="`sk-${n}`" class="skeleton-row">
              <td v-if="selectable" class="col-select" />
              <td v-for="(col, ci) in columns" :key="col.key" :style="{ textAlign: col.align || 'left' }">
                <OzSkeleton :width="ci === 0 ? '70%' : `${45 + ((n * 17 + ci * 23) % 40)}%`" :class="{ 'push-right': col.align === 'right' }" />
              </td>
            </tr>
          </template>
          <template v-else>
            <tr
              v-for="(node, i) in visibleNodes"
              :key="keyOf(node.row) ?? i"
              :class="{
                clickable,
                selected: selectable && selectedSet.has(keyOf(node.row)),
                'is-context': node.context,
                'is-child': node.depth > 0,
              }"
              @click="emit('row-click', node.row)"
            >
              <td v-if="selectable" class="col-select" @click.stop>
                <OzCheckbox
                  v-if="canSelect(node)"
                  :model-value="selectedSet.has(keyOf(node.row))"
                  aria-label="Satırı seç"
                  @update:model-value="toggleRowSelect(keyOf(node.row))"
                />
              </td>
              <td
                v-for="col in columns"
                :key="col.key"
                :class="col.class"
                :style="{ textAlign: col.align || 'left' }"
              >
                <div v-if="tree && col.key === treeCol" class="tree-cell" :style="{ paddingLeft: `${node.depth * 26}px` }">
                  <button
                    v-if="node.childCount && !hasQuery"
                    type="button"
                    class="tree-toggle"
                    :aria-expanded="!collapsed.has(keyOf(node.row))"
                    :aria-label="collapsed.has(keyOf(node.row)) ? 'Alt satırları göster' : 'Alt satırları gizle'"
                    @click.stop="toggleNode(keyOf(node.row))"
                  >
                    <OzIcon :name="collapsed.has(keyOf(node.row)) ? 'chevron-right' : 'chevron-down'" :size="14" />
                  </button>
                  <span v-else-if="hasNested" class="tree-spacer" :class="{ 'is-leaf': node.depth > 0 }" aria-hidden="true" />
                  <div class="tree-content">
                    <slot :name="`cell-${col.key}`" :row="node.row" :value="node.row[col.key]" :depth="node.depth" :child-count="node.childCount">{{ node.row[col.key] }}</slot>
                  </div>
                </div>
                <slot v-else :name="`cell-${col.key}`" :row="node.row" :value="node.row[col.key]" :depth="node.depth" :child-count="node.childCount">{{ node.row[col.key] }}</slot>
              </td>
            </tr>
            <tr v-if="!filteredRows.length" class="empty-row">
              <td :colspan="colCount">
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
    <div v-if="pageSize && !loading && groups.length > pageSize" class="foot">
      <OzPagination v-model="page" :total="groups.length" :page-size="pageSize" />
    </div>

    <!-- Toplu işlem çubuğu: seçim varken sayfanın altında yüzer -->
    <div v-if="selectable && $slots.bulk" class="bulk-dock" :class="{ 'is-active': selected.length > 0 }">
      <Transition name="oz-bulk">
        <div v-if="selected.length" class="bulk-bar" role="toolbar" aria-label="Toplu işlemler">
          <span class="bulk-info"><b>{{ selected.length }}</b> {{ itemLabel }} seçili</span>
          <div class="bulk-actions">
            <OzButton size="sm" @click="clearSelection">Temizle</OzButton>
            <slot name="bulk" :selected="selected" :clear="clearSelection" />
          </div>
        </div>
      </Transition>
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

.select-all-bar {
  margin: 0 18px 10px; padding: 8px 12px; border-radius: 10px; font-size: 12.5px; color: var(--oz-ink-soft);
  background: var(--oz-accent-soft); text-align: center;
}
.link-btn { margin-left: 6px; padding: 0; border: none; background: none; font: inherit; font-weight: 700; color: var(--oz-accent); cursor: pointer; }
.link-btn:hover { text-decoration: underline; }
.link-btn:focus-visible { outline: 2px solid var(--oz-accent); outline-offset: 2px; border-radius: 3px; }

/* Sığıyorsa clip: kaydırma kabı oluşturmaz → başlık sayfa kaydırmasına yapışabilir */
.oz-table-scroll { overflow-x: clip; padding: 0 4px 8px; }
.oz-table-scroll.overflowing { overflow-x: auto; }
.oz-table { width: 100%; border-collapse: separate; border-spacing: 0; }

/* Başlık: büyük harf yerine okunur küçük-başlık, yapışkan, opak zemin */
th {
  position: sticky; top: 0; z-index: 2;
  font-size: 11.5px; font-weight: 600; color: var(--oz-muted); letter-spacing: .01em;
  padding: 11px 14px; white-space: nowrap; text-align: left;
  /* --oz-th-bg opak düz renk (iki temada) — yapışkan başlığın altından satırlar görünmez */
  background: var(--oz-th-bg);
  border-bottom: 1px solid var(--oz-border);
}
th:first-child { border-top-left-radius: 8px; }
th:last-child { border-top-right-radius: 8px; }
td { padding: 12px 14px; font-size: 12.5px; border-bottom: 1px solid var(--oz-border-soft); vertical-align: middle; transition: background 0.15s ease; }
tbody tr:last-child td { border-bottom: none; }
tbody tr:not(.empty-row):not(.skeleton-row):hover td { background: var(--oz-surface-2); }
tbody tr.clickable { cursor: pointer; }
tbody tr.clickable:hover td:first-child { box-shadow: inset 2px 0 0 var(--oz-accent); }
tbody tr.selected td { background: var(--oz-accent-soft); }
tbody tr.selected:hover td { background: var(--oz-accent-soft); }
tbody tr.is-context td { opacity: .55; }
.empty-row td { padding: 0; }
.skeleton-row td { padding: 16px 14px; }
.push-right { margin-left: auto; }

.col-select { width: 44px; padding-right: 0; }

/* Sıralama: oku yalnızca aktif sütunda ya da üzerine gelince göster (gürültü azalır) */
.sort-btn {
  display: inline-flex; align-items: center; gap: 4px;
  padding: 0; background: none; border: none; cursor: pointer;
  font: inherit; color: inherit; letter-spacing: inherit;
}
.sort-btn.right { flex-direction: row-reverse; }
.sort-btn:hover, .sort-btn.active { color: var(--oz-ink-soft); }
.sort-btn:focus-visible { outline: 2px solid var(--oz-accent); outline-offset: 2px; border-radius: 3px; }
.sort-ico { opacity: 0; transition: opacity .12s; color: var(--oz-muted); }
.sort-btn:hover .sort-ico, .sort-btn:focus-visible .sort-ico { opacity: .7; }
.sort-btn.active .sort-ico { opacity: 1; color: var(--oz-accent); }

/* Ağaç */
.tree-cell { display: flex; align-items: center; gap: 6px; }
.tree-content { min-width: 0; flex: 1; }
.tree-toggle {
  width: 22px; height: 22px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;
  border: none; border-radius: 6px; background: none; color: var(--oz-muted); cursor: pointer;
}
.tree-toggle:hover { background: var(--oz-surface-2); color: var(--oz-ink); }
.tree-toggle:focus-visible { outline: 2px solid var(--oz-accent); outline-offset: 1px; }
.tree-spacer { width: 22px; flex-shrink: 0; align-self: stretch; position: relative; }
/* alt satır: üst satıra bağlayan "└" çizgisi */
.tree-spacer.is-leaf::before {
  content: ''; position: absolute; left: 10px; top: -12px; height: calc(50% + 12px); width: 9px;
  border-left: 1.5px solid var(--oz-border); border-bottom: 1.5px solid var(--oz-border); border-bottom-left-radius: 6px;
}

.foot { padding: 10px 18px 14px; border-top: 1px solid var(--oz-border-soft); }

/* Toplu işlem çubuğu */
.bulk-dock { position: sticky; bottom: 0; height: 0; z-index: 5; pointer-events: none; transition: height .2s ease; }
.bulk-dock.is-active { height: 76px; }
.bulk-bar {
  position: absolute; left: 50%; bottom: 16px; transform: translateX(-50%);
  display: flex; align-items: center; gap: 14px; width: max-content; max-width: calc(100% - 24px);
  padding: 8px 8px 8px 16px; border-radius: 999px; pointer-events: auto;
  background: var(--oz-surface); border: 1px solid var(--oz-border); box-shadow: var(--oz-shadow);
}
.bulk-info { font-size: 12.5px; color: var(--oz-ink-soft); white-space: nowrap; }
.bulk-info b { color: var(--oz-accent); }
.bulk-actions { display: flex; gap: 8px; }
.oz-bulk-enter-active, .oz-bulk-leave-active { transition: opacity .18s ease, transform .22s cubic-bezier(.2, .8, .2, 1); }
.oz-bulk-enter-from, .oz-bulk-leave-to { opacity: 0; transform: translate(-50%, 14px); }

/* Hazır hücre stilleri: column.class ile kullanılır */
td.oz-cell-id { white-space: nowrap; font-weight: 700; color: var(--oz-accent); font-variant-numeric: tabular-nums; letter-spacing: -0.01em; }
td.oz-cell-strong { font-weight: 700; color: var(--oz-ink-soft); }
td.oz-cell-amount { white-space: nowrap; font-weight: 700; font-variant-numeric: tabular-nums; color: var(--oz-ink-soft); }
@media (max-width: 640px) {
  .oz-table-toolbar { padding: 0 12px 12px; }
  .tb-search { max-width: none; flex-basis: 100%; }
  th { padding: 10px 10px 9px; }
  td { padding: 11px 10px; }
  td.oz-cell-strong { min-width: 120px; }
  .bulk-bar { bottom: 10px; gap: 10px; padding-left: 14px; }
}
td :deep(.oz-cell-sub) { color: var(--oz-muted); font-size: 11px; margin-top: 2px; }
</style>
