<script setup>
import { ref, nextTick, useId } from 'vue'
import { onClickOutside, useEventListener } from '@vueuse/core'
import OzIcon from './OzIcon.vue'
import { useOzAnchor } from './useOzAnchor'

// Açılır işlem menüsü.
// items: [{ key, label, icon?, danger?, disabled?, hint? } | { divider: true }]
// Tetikleyici: varsayılan ⋯ butonu; özel buton için #trigger slot'u ({ open, toggle, attrs }).
//   <OzDropdown :items="items" @select="onAction" />
const props = defineProps({
  items: { type: Array, required: true },
  placement: { type: String, default: 'bottom-end' },
  label: { type: String, default: 'İşlemler' },
})

const emit = defineEmits(['select'])

const open = ref(false)
const wrapEl = ref(null)
const menuEl = ref(null)
const activeIndex = ref(-1)
const menuId = `oz-menu-${useId()}`
const { style, update } = useOzAnchor({ placement: props.placement })

const selectable = () => props.items.map((it, i) => (it.divider || it.disabled ? null : i)).filter((i) => i !== null)

function triggerEl() {
  return wrapEl.value?.querySelector('[aria-haspopup]')
}

async function show(focusFirst = false) {
  open.value = true
  activeIndex.value = focusFirst ? selectable()[0] ?? -1 : -1
  await nextTick()
  await update(triggerEl(), () => menuEl.value)
  focusActive()
}

function hide(restoreFocus = false) {
  if (!open.value) return
  open.value = false
  if (restoreFocus === true) triggerEl()?.focus()
}

function toggle() {
  open.value ? hide() : show()
}

// Menü açıkken: dışarı tıklama, sayfa kaydırma ve pencere boyutu değişimi menüyü kapatır
onClickOutside(menuEl, () => hide(), { ignore: [wrapEl] })
useEventListener(() => (open.value ? document : null), 'scroll', () => hide(), { capture: true, passive: true })
useEventListener(() => (open.value ? window : null), 'resize', () => hide())

function choose(item) {
  if (item.disabled || item.divider) return
  hide(true)
  emit('select', item.key, item)
}

function focusActive() {
  if (activeIndex.value < 0) { menuEl.value?.focus(); return }
  menuEl.value?.querySelectorAll('[role=menuitem]')[selectable().indexOf(activeIndex.value)]?.focus()
}

function move(delta) {
  const list = selectable()
  if (!list.length) return
  const pos = list.indexOf(activeIndex.value)
  activeIndex.value = list[(pos + delta + list.length) % list.length] ?? list[0]
  focusActive()
}

function onTriggerKey(e) {
  if (['ArrowDown', 'Enter', ' '].includes(e.key)) {
    e.preventDefault()
    show(true)
  }
}

function onMenuKey(e) {
  if (e.key === 'ArrowDown') { e.preventDefault(); move(1) }
  else if (e.key === 'ArrowUp') { e.preventDefault(); move(-1) }
  else if (e.key === 'Home') { e.preventDefault(); activeIndex.value = selectable()[0]; focusActive() }
  else if (e.key === 'End') { e.preventDefault(); activeIndex.value = selectable().at(-1); focusActive() }
  else if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); hide(true) }
  else if (e.key === 'Tab') hide()
}


const triggerAttrs = {
  'aria-haspopup': 'menu',
  'aria-controls': menuId,
}
</script>

<template>
  <div ref="wrapEl" class="oz-dropdown">
    <slot name="trigger" :open="open" :toggle="toggle" :attrs="{ ...triggerAttrs, 'aria-expanded': open, onKeydown: onTriggerKey }">
      <button
        type="button"
        class="dots"
        :class="{ open }"
        :aria-label="label"
        v-bind="triggerAttrs"
        :aria-expanded="open"
        @click.stop="toggle"
        @keydown="onTriggerKey"
      >
        <OzIcon name="more" :size="16" :stroke-width="2.5" />
      </button>
    </slot>

    <Teleport to=".oz-root" defer>
      <Transition name="oz-pop">
        <div
          v-if="open"
          :id="menuId"
          ref="menuEl"
          class="oz-menu"
          role="menu"
          tabindex="-1"
          :aria-label="label"
          :style="style"
          @keydown="onMenuKey"
        >
          <template v-for="(item, i) in items" :key="item.key ?? `d${i}`">
            <div v-if="item.divider" class="divider" role="separator" />
            <button
              v-else
              type="button"
              role="menuitem"
              class="item"
              :class="{ danger: item.danger, active: i === activeIndex }"
              :disabled="item.disabled"
              tabindex="-1"
              @click="choose(item)"
              @mouseenter="activeIndex = i"
            >
              <OzIcon v-if="item.icon" :name="item.icon" :size="14" />
              <span class="text">{{ item.label }}</span>
              <kbd v-if="item.hint">{{ item.hint }}</kbd>
            </button>
          </template>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<style scoped>
.oz-dropdown { display: inline-flex; }
.dots {
  width: 30px; height: 30px; border-radius: 8px;
  display: flex; align-items: center; justify-content: center;
  background: none; border: 1px solid transparent; color: var(--oz-muted); cursor: pointer;
  transition: background .12s, color .12s, border-color .12s;
}
.dots:hover, .dots.open { background: var(--oz-surface-2); border-color: var(--oz-border); color: var(--oz-ink); }
.dots:focus-visible { outline: 2px solid var(--oz-accent); outline-offset: 1px; }

.oz-menu {
  position: fixed; z-index: 110; min-width: 180px; max-width: 280px;
  padding: 5px; border-radius: 11px;
  background: var(--oz-surface); border: 1px solid var(--oz-border);
  box-shadow: var(--oz-shadow);
  outline: none;
}
.item {
  width: 100%; display: flex; align-items: center; gap: 9px;
  padding: 8px 10px; border-radius: 7px; border: none; background: none;
  font: inherit; font-size: 12.5px; font-weight: 500; color: var(--oz-ink); text-align: left;
  cursor: pointer; outline: none;
}
.item :deep(svg) { color: var(--oz-muted); }
.item.active, .item:focus-visible { background: var(--oz-accent-soft); color: var(--oz-accent); }
.item.active :deep(svg), .item:focus-visible :deep(svg) { color: var(--oz-accent); }
.item.danger, .item.danger :deep(svg) { color: var(--oz-danger); }
.item.danger.active, .item.danger:focus-visible { background: var(--oz-danger-soft); }
.item:disabled { opacity: .45; cursor: not-allowed; }
.text { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
kbd { font-size: 10px; color: var(--oz-muted-2); font-family: inherit; font-weight: 600; }
.divider { height: 1px; margin: 5px 4px; background: var(--oz-border-soft); }

.oz-pop-enter-active, .oz-pop-leave-active { transition: opacity .12s ease, transform .12s ease; }
.oz-pop-enter-from, .oz-pop-leave-to { opacity: 0; transform: translateY(-4px) scale(.98); }
</style>
