<script setup>
import { computed } from 'vue'
import { motion, AnimatePresence } from 'motion-v'
import { useMediaQuery } from '@vueuse/core'
import OzModal from './OzModal.vue'
import OzIcon from './OzIcon.vue'
import OzField from './OzField.vue'
import OzTableFilterField from './OzTableFilterField.vue'
import OzButton from './OzButton.vue'
import { filterDefault, isFilterActive } from './ozTableFilter'

// OzTable'ın filtre paneli ve etkin filtre etiketleri. Doğrudan değil OzTable üzerinden kullanılır.
// Masaüstünde tablonun üstünde açılır kapanır; telefonda alttan açılan sayfa olur (tabloyu aşağı itmez).
// columns: filtre tanımı olan sütunlar ({ key, label, filter: { type, ... }, _options })
const props = defineProps({
  columns: { type: Array, required: true },
  panelId: { type: String, required: true },
  // Telefondaki "N kaydı göster" düğmesi için
  resultCount: { type: Number, default: 0 },
})

const open = defineModel('open', { type: Boolean, default: false })
const mobile = useMediaQuery('(max-width: 640px)')

const filters = defineModel('filters', { type: Object, required: true })
const emit = defineEmits(['clear-all'])

const val = (col) => filters.value[col.key] ?? filterDefault(col.filter)
function set(col, v) {
  filters.value = { ...filters.value, [col.key]: v }
}
function clear(col) {
  set(col, filterDefault(col.filter))
}

const fmt = (col, v) => (col.filter.format ? col.filter.format(v) : String(v))
const optLabel = (col, v) => col._options.find((o) => o.value === v)?.label ?? String(v)

// Etkin filtreler → "Durum: Üretimde, Gecikti" etiketleri
const chips = computed(() => props.columns
  .filter((c) => isFilterActive(c.filter, filters.value[c.key]))
  .map((c) => {
    const v = filters.value[c.key]
    const t = c.filter.type
    let text
    if (t === 'select') {
      const list = Array.isArray(v) ? v : [v]
      const labels = list.map((x) => optLabel(c, x))
      text = labels.length > 2 ? `${labels.slice(0, 2).join(', ')} +${labels.length - 2}` : labels.join(', ')
    } else if (t === 'text') text = `“${v}”`
    else if (t === 'range') {
      if (v.min !== '' && v.max !== '') text = `${fmt(c, v.min)} – ${fmt(c, v.max)}`
      else text = v.min !== '' ? `≥ ${fmt(c, v.min)}` : `≤ ${fmt(c, v.max)}`
    } else if (t === 'date') {
      const d = (x) => new Date(x).toLocaleDateString('tr-TR', { day: 'numeric', month: 'short' })
      if (v.from && v.to) text = `${d(v.from)} – ${d(v.to)}`
      else text = v.from ? `${d(v.from)} sonrası` : `${d(v.to)} öncesi`
    }
    return { col: c, label: c.filter.label || c.label, text }
  }))

defineExpose({ chips })
</script>

<template>
  <OzModal v-if="mobile" v-model:open="open" title="Filtreler" icon="filter" size="sm">
    <div class="sheet-fields">
      <OzField v-for="col in columns" :key="col.key" :label="col.filter.label || col.label">
        <OzTableFilterField :col="col" :model-value="val(col)" @update:model-value="set(col, $event)" />
      </OzField>
    </div>
    <template #footer>
      <OzButton icon="x" @click="emit('clear-all')">Temizle</OzButton>
      <OzButton variant="primary" @click="open = false">{{ resultCount }} kaydı göster</OzButton>
    </template>
  </OzModal>

  <AnimatePresence v-else :initial="false">
    <motion.div
      v-if="open"
      :id="panelId"
      key="panel"
      class="oz-tf-panel"
      role="region"
      aria-label="Filtreler"
      :initial="{ height: 0, opacity: 0 }"
      :animate="{ height: 'auto', opacity: 1 }"
      :exit="{ height: 0, opacity: 0 }"
      :transition="{ duration: 0.22, ease: [0.16, 1, 0.3, 1] }"
    >
      <div class="grid">
        <OzField v-for="col in columns" :key="col.key" :label="col.filter.label || col.label">
          <OzTableFilterField :col="col" :model-value="val(col)" @update:model-value="set(col, $event)" />
        </OzField>
      </div>
    </motion.div>
  </AnimatePresence>

  <div v-if="chips.length" class="oz-tf-chips">
    <TransitionGroup name="oz-chip">
      <span v-for="c in chips" :key="c.col.key" class="chip">
        <span class="chip-label">{{ c.label }}:</span>
        <span class="chip-text">{{ c.text }}</span>
        <button type="button" :aria-label="`${c.label} filtresini kaldır`" @click="clear(c.col)">
          <OzIcon name="x" :size="12" :stroke-width="2.5" />
        </button>
      </span>
    </TransitionGroup>
    <OzButton size="sm" @click="emit('clear-all')">Tümünü temizle</OzButton>
  </div>
</template>

<style scoped>
.oz-tf-panel { overflow: hidden; }
.grid {
  display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 12px 14px;
  margin: 0 18px 12px; padding: 14px; border-radius: 12px;
  background: var(--oz-surface-2); border: 1px solid var(--oz-border-soft);
}
.sheet-fields { display: flex; flex-direction: column; gap: 14px; }

.oz-tf-chips { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; padding: 0 18px 12px; }
.chip {
  display: inline-flex; align-items: center; gap: 5px; height: 28px; padding: 0 4px 0 10px; max-width: 100%;
  border-radius: 999px; background: var(--oz-accent-soft); border: 1px solid rgba(var(--oz-accent-rgb), .22);
  font-size: 11.5px; color: var(--oz-accent);
}
.chip-label { font-weight: 600; opacity: .75; white-space: nowrap; }
.chip-text { font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.chip button { display: flex; padding: 3px; border: none; border-radius: 50%; background: none; color: inherit; cursor: pointer; }
.chip button:hover { background: rgba(var(--oz-accent-rgb), .15); }
.chip button:focus-visible { outline: 2px solid var(--oz-accent); outline-offset: 1px; }
.oz-chip-enter-active, .oz-chip-leave-active { transition: opacity .15s, transform .15s; }
.oz-chip-enter-from, .oz-chip-leave-to { opacity: 0; transform: scale(.9); }

@media (max-width: 640px) {
  .grid { grid-template-columns: 1fr; margin: 0 12px 12px; }
  .oz-tf-chips { padding: 0 12px 12px; }
}
</style>
