<script setup>
import { ozFold } from './ozText'
import { ref, computed, watch, nextTick, useId } from 'vue'
import OzIcon from './OzIcon.vue'
import OzEmptyState from './OzEmptyState.vue'

// Arama / komut paleti (Ctrl K).
// groups: [{ label, items: [{ key, label, icon?, hint?, keywords? }] }]
// Seçilen öğe `select` ile yayılır; ne yapılacağına sayfa/layout karar verir.
const props = defineProps({
  groups: { type: Array, required: true },
  placeholder: { type: String, default: 'Sayfa, işlem veya kayıt ara…' },
  // Verilirse ({ title, description? }) arama boşken liste yerine bu ipucu gösterilir;
  // verilmezse (varsayılan) boş aramada tüm öğeler listelenir.
  idleHint: { type: Object, default: null },
})

const open = defineModel('open', { type: Boolean, default: false })
const emit = defineEmits(['select'])

const query = ref('')
const activeIndex = ref(0)
const inputEl = ref(null)
const listEl = ref(null)
const listId = `oz-cmd-${useId()}`
let lastFocused = null

// Türkçe karakter ve büyük/küçük harf duyarsız eşleştirme ("urun" → "Ürünler")
const fold = ozFold

const filtered = computed(() => {
  const q = fold(query.value.trim())
  if (!q && props.idleHint) return []
  return props.groups
    .map((g) => ({
      ...g,
      items: q ? g.items.filter((it) => fold(`${it.label} ${it.keywords ?? ''} ${it.hint ?? ''}`).includes(q)) : g.items,
    }))
    .filter((g) => g.items.length)
})

const flat = computed(() => filtered.value.flatMap((g) => g.items))
const optionId = (i) => `${listId}-opt-${i}`
const indexOf = (item) => flat.value.indexOf(item)

watch(query, () => { activeIndex.value = 0 })

watch(open, async (v) => {
  if (v) {
    lastFocused = document.activeElement
    query.value = ''
    activeIndex.value = 0
    await nextTick()
    inputEl.value?.focus()
  } else {
    lastFocused?.focus?.()
  }
})

function scrollActive() {
  nextTick(() => listEl.value?.querySelector(`#${CSS.escape(optionId(activeIndex.value))}`)?.scrollIntoView({ block: 'nearest' }))
}

function onKey(e) {
  const n = flat.value.length
  if (e.key === 'ArrowDown') { e.preventDefault(); if (n) activeIndex.value = (activeIndex.value + 1) % n; scrollActive() }
  else if (e.key === 'ArrowUp') { e.preventDefault(); if (n) activeIndex.value = (activeIndex.value - 1 + n) % n; scrollActive() }
  else if (e.key === 'Enter') { e.preventDefault(); const item = flat.value[activeIndex.value]; if (item) choose(item) }
  else if (e.key === 'Escape') { e.preventDefault(); open.value = false }
  else if (e.key === 'Tab') e.preventDefault()
}

function choose(item) {
  open.value = false
  emit('select', item)
}
</script>

<template>
  <Teleport to=".oz-root" defer>
    <Transition name="oz-cmd">
      <div v-if="open" class="oz-cmd-backdrop" @mousedown.self="open = false">
        <div class="oz-cmd" role="dialog" aria-modal="true" aria-label="Arama">
          <div class="search">
            <OzIcon name="search" :size="16" />
            <input
              ref="inputEl"
              v-model="query"
              type="text"
              role="combobox"
              aria-autocomplete="list"
              :aria-expanded="true"
              :aria-controls="listId"
              :aria-activedescendant="flat.length ? optionId(activeIndex) : undefined"
              :placeholder="placeholder"
              autocomplete="off"
              spellcheck="false"
              @keydown="onKey"
            >
            <kbd>Esc</kbd>
          </div>

          <div :id="listId" ref="listEl" class="results" role="listbox" aria-label="Sonuçlar">
            <template v-if="flat.length">
              <div v-for="g in filtered" :key="g.label" class="group" role="group" :aria-label="g.label">
                <div class="group-label">{{ g.label }}</div>
                <div
                  v-for="item in g.items"
                  :id="optionId(indexOf(item))"
                  :key="item.key"
                  class="option"
                  :class="{ active: indexOf(item) === activeIndex }"
                  role="option"
                  :aria-selected="indexOf(item) === activeIndex"
                  @mousemove="activeIndex = indexOf(item)"
                  @click="choose(item)"
                >
                  <span class="opt-ico"><OzIcon :name="item.icon || 'arrow-up-right'" :size="15" /></span>
                  <span class="opt-label">{{ item.label }}</span>
                  <span v-if="item.hint" class="opt-hint">{{ item.hint }}</span>
                </div>
              </div>
            </template>
            <OzEmptyState v-else-if="idleHint && !query.trim()" icon="search" :title="idleHint.title" :description="idleHint.description || ''" compact />
            <OzEmptyState v-else icon="search" title="Sonuç yok" :description="`“${query}” için eşleşen sayfa veya işlem bulunamadı.`" compact />
          </div>

          <div class="foot">
            <span><kbd>↑</kbd><kbd>↓</kbd> gezin</span>
            <span><kbd>Enter</kbd> seç</span>
            <span><kbd>Esc</kbd> kapat</span>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.oz-cmd-backdrop {
  position: fixed; inset: 0; z-index: 105;
  background: rgba(15, 23, 42, .45); backdrop-filter: blur(2px);
  display: flex; justify-content: center; align-items: flex-start;
  padding: 12vh 16px 16px;
}
.oz-cmd {
  width: 100%; max-width: 580px; max-height: 70dvh;
  display: flex; flex-direction: column; overflow: hidden;
  background: var(--oz-surface); border: 1px solid var(--oz-border); border-radius: 16px;
  box-shadow: 0 24px 60px -12px rgba(15, 23, 42, .35);
}
/* Arama satırı ve alt bilgi küçülmez; taşan kısım yalnızca .results'ta kayar (min-height: 0 olmadan liste
   uzunken flex arama satırını ~20px'e eziyordu). */
.search { flex-shrink: 0; display: flex; align-items: center; gap: 10px; padding: 0 16px; height: 54px; border-bottom: 1px solid var(--oz-border-soft); color: var(--oz-muted); }
.search input { flex: 1; min-width: 0; height: 100%; border: none; outline: none; background: none; font: inherit; font-size: 14px; color: var(--oz-ink); }
.search input::placeholder { color: var(--oz-muted-2); }
/* Ana projedeki @tailwindcss/forms (base) tüm input/textarea'lara kenarlık, padding ve odak halkası veriyor;
   Oz kutusu çerçeveyi kendisi çizdiği için iç alanda bunlar sıfırlanır. */
.oz-cmd .search input, .oz-cmd .search input:focus { border: 0; padding: 0; box-shadow: none; outline: none; background-color: transparent; }
kbd {
  font-family: inherit; font-size: 10px; font-weight: 600; color: var(--oz-muted);
  background: var(--oz-surface-2); border: 1px solid var(--oz-border); border-radius: 5px;
  padding: 2px 6px; min-width: 20px; text-align: center; display: inline-block;
}
.results { flex: 1; min-height: 0; overflow-y: auto; overscroll-behavior: contain; padding: 6px; }
.group + .group { margin-top: 4px; }
.group-label { padding: 8px 10px 5px; font-size: 10.5px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: var(--oz-muted-2); }
.option { display: flex; align-items: center; gap: 10px; padding: 8px 10px; border-radius: 9px; cursor: pointer; }
.option.active { background: var(--oz-accent-soft); }
.opt-ico { width: 28px; height: 28px; border-radius: 8px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; background: var(--oz-surface-2); border: 1px solid var(--oz-border); color: var(--oz-muted); }
.option.active .opt-ico { background: var(--oz-accent); border-color: var(--oz-accent); color: #fff; }
.opt-label { flex: 1; min-width: 0; font-size: 13px; font-weight: 600; color: var(--oz-ink-soft); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.option.active .opt-label { color: var(--oz-accent); }
.opt-hint { font-size: 11px; color: var(--oz-muted); white-space: nowrap; }
.foot { flex-shrink: 0; display: flex; gap: 16px; padding: 9px 16px; border-top: 1px solid var(--oz-border-soft); background: var(--oz-surface-2); font-size: 11px; color: var(--oz-muted); }
.foot kbd { margin-right: 3px; }

.oz-cmd-enter-active, .oz-cmd-leave-active { transition: opacity .15s ease; }
.oz-cmd-enter-active .oz-cmd, .oz-cmd-leave-active .oz-cmd { transition: transform .18s cubic-bezier(0.16, 1, 0.3, 1); }
.oz-cmd-enter-from, .oz-cmd-leave-to { opacity: 0; }
.oz-cmd-enter-from .oz-cmd, .oz-cmd-leave-to .oz-cmd { transform: translateY(-8px) scale(.98); }

@media (max-width: 640px) {
  .oz-cmd-backdrop { padding: 10px; }
  .foot { display: none; }
}
</style>
