<script setup>
import { ref, computed, nextTick, watch, useId } from 'vue'
import { onClickOutside, useEventListener } from '@vueuse/core'
import OzIcon from './OzIcon.vue'
import { useOzField } from './useOzField'
import { useOzAnchor } from './useOzAnchor'
import { ozFold as fold } from './ozText'

// Aranabilir seçim kutusu; multiple ile çoklu seçim (etiketli).
// options: ['A', 'B'] veya [{ value, label, description?, disabled? }]
// v-model: tekli → değer ('' = boş), çoklu → değer dizisi
//   <OzCombobox v-model="partner" :options="partners" placeholder="İş ortağı seçin" />
//   <OzCombobox v-model="tags" :options="tags" multiple />
const props = defineProps({
  options: { type: Array, required: true },
  multiple: { type: Boolean, default: false },
  placeholder: { type: String, default: 'Seçin' },
  searchPlaceholder: { type: String, default: 'Ara…' },
  emptyText: { type: String, default: 'Sonuç bulunamadı' },
  clearable: { type: Boolean, default: false },
  // Çoklu seçimde tetikleyicide gösterilecek en fazla etiket; kalanı "+n"
  maxTags: { type: Number, default: 3 },
  disabled: { type: Boolean, default: false },
  invalid: { type: Boolean, default: false },
  required: { type: Boolean, default: false },
  id: { type: String, default: '' },
})

const model = defineModel({ type: [String, Number, Array], default: '' })
const { inputId, invalid, required, describedBy } = useOzField(props)

const uid = useId()
const listId = `oz-cb-list-${uid}`
const optId = (i) => `oz-cb-opt-${uid}-${i}`

const triggerEl = ref(null)
const popEl = ref(null)
const searchEl = ref(null)
const listEl = ref(null)
const open = ref(false)
const query = ref('')
const active = ref(-1)
const { style: anchorStyle, update } = useOzAnchor({ placement: 'bottom-start' })
const popWidth = ref(0)
const popStyle = computed(() => ({ ...anchorStyle.value, width: `${popWidth.value}px` }))

const normalized = computed(() =>
  props.options.map((o) => (typeof o === 'object' ? o : { value: o, label: String(o) })),
)
const selectedValues = computed(() => {
  if (props.multiple) return Array.isArray(model.value) ? model.value : []
  return model.value === '' || model.value == null ? [] : [model.value]
})
const isSelected = (o) => selectedValues.value.includes(o.value)
const selectedOptions = computed(() =>
  selectedValues.value.map((v) => normalized.value.find((o) => o.value === v) ?? { value: v, label: String(v) }),
)

const filtered = computed(() => {
  const q = fold(query.value.trim())
  if (!q) return normalized.value
  return normalized.value.filter((o) => fold(o.label).includes(q) || (o.description && fold(o.description).includes(q)))
})

watch(filtered, () => { active.value = firstEnabled() })

function firstEnabled(from = 0, dir = 1) {
  const list = filtered.value
  for (let n = 0; n < list.length; n++) {
    const i = (((from + n * dir) % list.length) + list.length) % list.length
    if (!list[i].disabled) return i
  }
  return -1
}

async function show() {
  if (props.disabled || open.value) return
  open.value = true
  query.value = ''
  popWidth.value = Math.max(triggerEl.value.offsetWidth, 220)
  const sel = filtered.value.findIndex(isSelected)
  active.value = sel !== -1 ? sel : firstEnabled()
  await update(triggerEl.value, () => popEl.value)
  searchEl.value?.focus()
  scrollActive()
}

function hide(restoreFocus = false) {
  if (!open.value) return
  open.value = false
  if (restoreFocus) triggerEl.value?.focus()
}

function reposition() { update(triggerEl.value, () => popEl.value) }
// Liste kendi içinde kayarken kapanmasın, sayfa kayınca kapansın
function onScroll(e) { if (!popEl.value?.contains(e.target)) hide() }

onClickOutside(popEl, () => hide(), { ignore: [triggerEl] })
useEventListener(() => (open.value ? document : null), 'scroll', onScroll, { capture: true, passive: true })
useEventListener(() => (open.value ? window : null), 'resize', reposition)

function choose(o) {
  if (!o || o.disabled) return
  if (props.multiple) {
    model.value = isSelected(o) ? selectedValues.value.filter((v) => v !== o.value) : [...selectedValues.value, o.value]
    // Çoklu seçimde popover açık kalır; seçilen etiketler tetikleyicide büyüyebilir
    nextTick(reposition)
  } else {
    model.value = o.value
    hide(true)
  }
}

function removeValue(v) {
  model.value = props.multiple ? selectedValues.value.filter((x) => x !== v) : ''
}

function clear() {
  model.value = props.multiple ? [] : ''
  triggerEl.value?.focus()
}

async function scrollActive() {
  await nextTick()
  listEl.value?.querySelector(`#${CSS.escape(optId(active.value))}`)?.scrollIntoView({ block: 'nearest' })
}

function move(dir) {
  const list = filtered.value
  if (!list.length) return
  let i = active.value
  for (let n = 0; n < list.length; n++) {
    i = (i + dir + list.length) % list.length
    if (!list[i].disabled) break
  }
  active.value = i
  scrollActive()
}

function onTriggerKey(e) {
  if (['ArrowDown', 'ArrowUp', 'Enter', ' '].includes(e.key)) {
    e.preventDefault()
    show()
  } else if (e.key === 'Backspace' && props.multiple && selectedValues.value.length) {
    removeValue(selectedValues.value.at(-1))
  }
}

function onSearchKey(e) {
  if (e.key === 'ArrowDown') { e.preventDefault(); move(1) }
  else if (e.key === 'ArrowUp') { e.preventDefault(); move(-1) }
  else if (e.key === 'Home') { e.preventDefault(); active.value = firstEnabled(); scrollActive() }
  else if (e.key === 'End') { e.preventDefault(); active.value = firstEnabled(filtered.value.length - 1, -1); scrollActive() }
  else if (e.key === 'Enter') { e.preventDefault(); choose(filtered.value[active.value]) }
  else if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); hide(true) }
  else if (e.key === 'Tab') hide()
}


const visibleTags = computed(() => selectedOptions.value.slice(0, props.maxTags))
const hiddenCount = computed(() => Math.max(selectedOptions.value.length - props.maxTags, 0))
</script>

<template>
  <div class="oz-combobox" :class="{ invalid, disabled, open }">
    <div
      :id="inputId"
      ref="triggerEl"
      class="trigger"
      role="combobox"
      :tabindex="disabled ? -1 : 0"
      aria-haspopup="listbox"
      :aria-expanded="open"
      :aria-controls="listId"
      :aria-invalid="invalid || undefined"
      :aria-required="required || undefined"
      :aria-describedby="describedBy"
      :aria-disabled="disabled || undefined"
      @click="open ? hide() : show()"
      @keydown="onTriggerKey"
    >
      <div class="value">
        <span v-if="!selectedOptions.length" class="placeholder">{{ placeholder }}</span>
        <template v-else-if="multiple">
          <span v-for="o in visibleTags" :key="o.value" class="tag">
            {{ o.label }}
            <button type="button" tabindex="-1" :aria-label="`${o.label} kaldır`" @click.stop="removeValue(o.value)">
              <OzIcon name="x" :size="11" :stroke-width="2.5" />
            </button>
          </span>
          <span v-if="hiddenCount" class="tag more">+{{ hiddenCount }}</span>
        </template>
        <span v-else class="single">{{ selectedOptions[0].label }}</span>
      </div>
      <button v-if="clearable && selectedOptions.length && !disabled" type="button" class="clear" aria-label="Temizle" @click.stop="clear">
        <OzIcon name="x" :size="13" />
      </button>
      <OzIcon name="chevron-down" :size="13" :stroke-width="2.5" class="chev" />
    </div>

    <Teleport to=".oz-root" defer>
      <Transition name="oz-pop">
        <div v-if="open" ref="popEl" class="oz-cb-pop" :style="popStyle">
          <div class="search">
            <OzIcon name="search" :size="13" />
            <input
              ref="searchEl"
              v-model="query"
              type="text"
              :placeholder="searchPlaceholder"
              aria-autocomplete="list"
              :aria-controls="listId"
              :aria-activedescendant="active >= 0 ? optId(active) : undefined"
              @keydown="onSearchKey"
            >
          </div>
          <ul :id="listId" ref="listEl" class="list" role="listbox" :aria-multiselectable="multiple || undefined">
            <li
              v-for="(o, i) in filtered"
              :id="optId(i)"
              :key="o.value"
              role="option"
              class="opt"
              :class="{ active: i === active, selected: isSelected(o), disabled: o.disabled }"
              :aria-selected="isSelected(o)"
              :aria-disabled="o.disabled || undefined"
              @mousedown.prevent
              @click="choose(o)"
              @mousemove="!o.disabled && (active = i)"
            >
              <span v-if="multiple" class="box"><OzIcon name="check" :size="10" :stroke-width="3.5" /></span>
              <span class="opt-text">
                <span class="opt-label">{{ o.label }}</span>
                <span v-if="o.description" class="opt-desc">{{ o.description }}</span>
              </span>
              <OzIcon v-if="!multiple && isSelected(o)" name="check" :size="14" :stroke-width="2.5" class="tick" />
            </li>
            <li v-if="!filtered.length" class="empty" role="presentation">{{ emptyText }}</li>
          </ul>
          <div v-if="multiple && selectedValues.length" class="foot">
            <span>{{ selectedValues.length }} seçili</span>
            <button type="button" @click="model = []">Temizle</button>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<style scoped>
.trigger {
  display: flex; align-items: center; gap: 6px;
  min-height: 36px; padding: 3px 12px 3px 12px;
  background: var(--oz-surface); border: 1px solid var(--oz-border); border-radius: 9px;
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
  cursor: pointer; outline: none; user-select: none;
  transition: border-color .15s, box-shadow .15s;
}
.trigger:hover { border-color: var(--oz-border-strong); }
.trigger:focus-visible, .open .trigger { border-color: rgba(var(--oz-accent-rgb), .55); box-shadow: 0 0 0 3px rgba(var(--oz-accent-rgb), .14); }
.invalid .trigger { border-color: rgba(var(--oz-danger-rgb), .6); }
.disabled .trigger { background: var(--oz-surface-2); opacity: .7; cursor: not-allowed; }
.value { flex: 1; min-width: 0; display: flex; flex-wrap: wrap; gap: 4px; align-items: center; }
.placeholder { font-size: 12.5px; color: var(--oz-muted-2); }
.single { font-size: 12.5px; color: var(--oz-ink); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.tag {
  display: inline-flex; align-items: center; gap: 3px; height: 24px; padding: 0 4px 0 8px; max-width: 160px;
  border-radius: 6px; font-size: 11.5px; font-weight: 600;
  background: var(--oz-accent-soft); color: var(--oz-accent); border: 1px solid rgba(var(--oz-accent-rgb), .2);
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.tag button { display: flex; padding: 2px; border: none; background: none; color: inherit; cursor: pointer; border-radius: 4px; opacity: .7; }
.tag button:hover { opacity: 1; background: rgba(var(--oz-accent-rgb), .15); }
.tag.more { padding: 0 8px; background: var(--oz-surface-2); color: var(--oz-muted); border-color: var(--oz-border); }
.clear { display: flex; padding: 3px; border: none; background: none; color: var(--oz-muted-2); cursor: pointer; border-radius: 5px; }
.clear:hover { color: var(--oz-ink); background: var(--oz-surface-2); }
.chev { color: var(--oz-muted); flex-shrink: 0; transition: transform .15s; }
.open .chev { transform: rotate(180deg); }

.oz-cb-pop {
  position: fixed; z-index: 110; display: flex; flex-direction: column;
  background: var(--oz-surface); border: 1px solid var(--oz-border); border-radius: 11px;
  box-shadow: var(--oz-shadow); overflow: hidden;
}
.search { display: flex; align-items: center; gap: 8px; padding: 0 12px; height: 38px; border-bottom: 1px solid var(--oz-border-soft); color: var(--oz-muted-2); }
.search input { flex: 1; min-width: 0; border: none; outline: none; background: none; font: inherit; font-size: 12.5px; color: var(--oz-ink); }
.search input::placeholder { color: var(--oz-muted-2); }
/* Ana projedeki @tailwindcss/forms (base) tüm input/textarea'lara kenarlık, padding ve odak halkası veriyor;
   Oz kutusu çerçeveyi kendisi çizdiği için iç alanda bunlar sıfırlanır. */
.oz-cb-pop .search input, .oz-cb-pop .search input:focus { border: 0; padding: 0; box-shadow: none; outline: none; background-color: transparent; }
.list { margin: 0; padding: 5px; list-style: none; max-height: 240px; overflow-y: auto; }
.opt {
  display: flex; align-items: center; gap: 9px; padding: 8px 10px; border-radius: 7px;
  font-size: 12.5px; color: var(--oz-ink); cursor: pointer;
}
.opt.active { background: var(--oz-accent-soft); }
.opt.selected .opt-label { color: var(--oz-accent); font-weight: 600; }
.opt.disabled { opacity: .45; cursor: not-allowed; }
.opt-text { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 1px; }
.opt-label { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.opt-desc { font-size: 11px; color: var(--oz-muted); }
.tick { color: var(--oz-accent); flex-shrink: 0; }
.box {
  width: 15px; height: 15px; border-radius: 4px; flex-shrink: 0;
  border: 1.5px solid var(--oz-border-strong); background: var(--oz-surface);
  display: flex; align-items: center; justify-content: center; color: transparent;
}
.opt.selected .box { background: var(--oz-accent); border-color: var(--oz-accent); color: #fff; }
.empty { padding: 14px 10px; text-align: center; font-size: 12px; color: var(--oz-muted); }
.foot { display: flex; align-items: center; justify-content: space-between; padding: 7px 12px; border-top: 1px solid var(--oz-border-soft); background: var(--oz-surface-2); font-size: 11px; color: var(--oz-muted); }
.foot button { border: none; background: none; font: inherit; font-weight: 600; color: var(--oz-accent); cursor: pointer; padding: 2px 4px; border-radius: 5px; }
.foot button:hover { text-decoration: underline; }

.oz-pop-enter-active, .oz-pop-leave-active { transition: opacity .12s ease, transform .12s ease; }
.oz-pop-enter-from, .oz-pop-leave-to { opacity: 0; transform: translateY(-4px) scale(.98); }
</style>
