<script setup>
import { ref, computed, watch, nextTick, useId } from 'vue'
import { onClickOutside, useEventListener, useMediaQuery } from '@vueuse/core'
import OzIcon from './OzIcon.vue'

// OzRail'in alt menü paneli. Masaüstünde rail butonunun sağında havada durur,
// mobilde (yatay şerit) alttan açılan sayfa olur. Doğrudan değil OzRail üzerinden kullanılır.
// module: { key, title, icon, children: [{ key, title, to?, icon?, description?, count? }] }
// `to` verilmeyen alt öğe henüz sayfası olmadığı için "Yakında" olarak pasif çizilir.
// Alt öğenin kendi children'ı varsa (3. seviye) altında girintili liste olarak çizilir.
const props = defineProps({
  module: { type: Object, default: null },
  anchor: { type: Object, default: null },
  activeChild: { type: String, default: '' },
})

const emit = defineEmits(['select', 'close'])

const panelEl = ref(null)
const listEl = ref(null)
const style = ref({})
const mobile = useMediaQuery('(max-width: 768px)')
const titleId = `oz-fly-${useId()}`

const children = computed(() => props.module?.children ?? [])
// Klavye gezinmesi ve odak için 2. ve 3. seviye tek düz liste
const flat = computed(() => children.value.flatMap((c) => [{ ...c, level: 1 }, ...(c.children ?? []).map((g) => ({ ...g, level: 2, parentKey: c.key }))]))
const enabled = (c) => !!c.to || !!c.run

function place() {
  const a = props.anchor
  if (mobile.value) { style.value = {}; return }
  if (!a || !panelEl.value) return
  const r = a.getBoundingClientRect()
  const h = panelEl.value.offsetHeight
  const margin = 12
  // butonun üst hizasından başla, ekrana sığmıyorsa yukarı kaydır
  const top = Math.max(margin, Math.min(r.top - 10, window.innerHeight - h - margin))
  style.value = { top: `${Math.round(top)}px`, left: `${Math.round(r.right + 14)}px` }
}

function items() {
  return [...(panelEl.value?.querySelectorAll('[role=menuitem]:not([aria-disabled=true])') ?? [])]
}

function focusAt(i) {
  const list = items()
  if (list.length) list[(i + list.length) % list.length].focus()
}

function onKey(e) {
  const list = items()
  const i = list.indexOf(document.activeElement)
  if (e.key === 'ArrowDown') { e.preventDefault(); focusAt(i + 1) }
  else if (e.key === 'ArrowUp') { e.preventDefault(); focusAt(i - 1) }
  else if (e.key === 'Home') { e.preventDefault(); focusAt(0) }
  else if (e.key === 'End') { e.preventDefault(); focusAt(-1) }
  else if (e.key === 'Escape' || (e.key === 'ArrowLeft' && !mobile.value)) { e.preventDefault(); e.stopPropagation(); emit('close', true) }
  else if (e.key === 'Tab') emit('close', false)
}

// Panel sabit (fixed) katmanda; tekerlek olayı arkadaki sayfaya geçip onu kaydırmasın.
// Liste kendi içinde kaydırılabiliyorsa o yönde yalnızca liste kayar.
function onWheel(e) {
  const l = listEl.value
  const canScroll = l?.contains(e.target) && (e.deltaY < 0
    ? l.scrollTop > 0
    : l.scrollTop + l.clientHeight < l.scrollHeight - 1)
  if (!canScroll) e.preventDefault()
}

function choose(c) {
  if (enabled(c)) emit('select', c)
}

// Dışarı tıklama kapatır; rail'in kendisine tıklama OzRail'de ele alınır (başka modüle geçiş)
onClickOutside(panelEl, () => emit('close', false), { ignore: [() => props.anchor, '.oz-rail'] })
useEventListener(() => (props.module ? window : null), 'resize', place)

watch(() => props.module, async (m) => {
  if (m) {
    await nextTick()
    place()
    // aktif alt sayfaya, yoksa ilk öğeye odaklan
    const list = items()
    const act = list.find((el) => el.dataset.key === props.activeChild)
    ;(act ?? list[0] ?? panelEl.value)?.focus({ preventScroll: true })
  }
}, { immediate: true })

watch(() => props.anchor, () => nextTick(place))
</script>

<template>
  <Teleport to=".oz-root" defer>
    <Transition :name="mobile ? 'oz-sheet' : 'oz-fly'">
      <div v-if="module" class="oz-fly-layer" :class="{ 'is-sheet': mobile }" @mousedown.self="emit('close', false)">
        <div
          ref="panelEl"
          class="oz-fly"
          :style="style"
          role="menu"
          tabindex="-1"
          :aria-labelledby="titleId"
          @keydown="onKey"
          @wheel="onWheel"
        >
          <div class="head">
            <span class="head-ico"><OzIcon :name="module.icon" :size="15" /></span>
            <span :id="titleId" class="head-title">{{ module.title }}</span>
            <button v-if="mobile" type="button" class="close" aria-label="Kapat" @click="emit('close', true)">
              <OzIcon name="x" :size="15" />
            </button>
          </div>
          <div ref="listEl" class="list">
            <button
              v-for="c in flat"
              :key="`${c.parentKey ?? ''}/${c.key}`"
              type="button"
              role="menuitem"
              class="item"
              :class="{ active: c.level === 1 && c.key === activeChild, soon: !enabled(c), sub: c.level === 2 }"
              :data-key="c.key"
              :aria-disabled="!enabled(c) || undefined"
              :aria-current="c.key === activeChild ? 'page' : undefined"
              tabindex="-1"
              @click="choose(c)"
            >
              <span v-if="c.level === 1 && c.icon" class="item-ico"><OzIcon :name="c.icon" :size="14" /></span>
              <span v-else-if="c.level === 1" class="item-dot" aria-hidden="true" />
              <OzIcon v-else name="chevron-right" :size="12" class="sub-ico" />
              <span class="item-text">
                <span class="item-title">{{ c.title }}</span>
                <span v-if="c.description" class="item-desc">{{ c.description }}</span>
              </span>
              <span v-if="!enabled(c)" class="soon-tag">Yakında</span>
              <span v-else-if="c.count != null" class="count">{{ c.count }}</span>
              <OzIcon v-else-if="c.key === activeChild" name="check" :size="14" :stroke-width="2.5" class="tick" />
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
/* Masaüstü: katman tıklamaları geçirir, yalnızca panel yakalar */
.oz-fly-layer { position: fixed; inset: 0; z-index: 60; pointer-events: none; }
.oz-fly {
  position: fixed; width: 284px; pointer-events: auto;
  background: var(--oz-surface); border: 1px solid var(--oz-border); border-radius: 14px;
  box-shadow: var(--oz-shadow); outline: none;
}
/* rail butonuna bakan küçük çentik */
.oz-fly::before {
  content: ''; position: absolute; left: -6px; top: 22px; width: 10px; height: 10px;
  background: var(--oz-surface); border-left: 1px solid var(--oz-border); border-bottom: 1px solid var(--oz-border);
  transform: rotate(45deg);
}
.head { display: flex; align-items: center; gap: 10px; padding: 12px 14px 10px; border-bottom: 1px solid var(--oz-border-soft); }
.head-ico { width: 28px; height: 28px; border-radius: 8px; display: flex; align-items: center; justify-content: center; background: var(--oz-accent-soft); color: var(--oz-accent); flex-shrink: 0; }
.head-title { flex: 1; min-width: 0; font-size: 13px; font-weight: 700; color: var(--oz-ink-soft); }
.list { display: flex; flex-direction: column; gap: 1px; padding: 6px; max-height: min(420px, calc(100dvh - 120px)); overflow-y: auto; overscroll-behavior: contain; }
.item {
  display: flex; align-items: center; gap: 10px; width: 100%;
  padding: 8px 9px; border-radius: 9px; border: none; background: none; cursor: pointer; text-align: left;
  font: inherit; color: var(--oz-ink); outline: none;
  transition: background .12s, color .12s;
}
.item-ico { width: 26px; height: 26px; border-radius: 7px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; color: var(--oz-muted); background: var(--oz-surface-2); border: 1px solid var(--oz-border-soft); transition: inherit; }
.item-text { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 1px; }
.item-title { font-size: 12.5px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.item-desc { font-size: 11px; color: var(--oz-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.item:not(.soon):hover, .item:not(.soon):focus-visible { background: var(--oz-surface-2); }
.item:focus-visible { box-shadow: inset 0 0 0 2px rgba(var(--oz-accent-rgb), .45); }
.item.active { background: var(--oz-accent-soft); }
.item.active .item-title { color: var(--oz-accent); }
.item.active .item-ico { background: var(--oz-accent); color: #fff; border-color: transparent; }
.item.soon { cursor: default; }
.item.sub { padding: 6px 9px 6px 20px; }
.item.sub .item-title { font-size: 12px; font-weight: 500; color: var(--oz-muted); }
.item.sub:hover .item-title { color: var(--oz-ink); }
.sub-ico { color: var(--oz-muted-2); flex-shrink: 0; }
.item-dot { width: 6px; height: 6px; margin: 0 4px 0 6px; border-radius: 50%; background: var(--oz-border-strong); flex-shrink: 0; transition: background .12s, transform .12s; }
.item:hover .item-dot { background: var(--oz-muted); }
.item.active .item-dot { background: var(--oz-accent); transform: scale(1.3); }
.item.soon .item-title, .item.soon .item-ico { opacity: .5; }
.soon-tag { font-size: 9.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: var(--oz-muted-2); padding: 2px 6px; border-radius: 5px; border: 1px dashed var(--oz-border-strong); flex-shrink: 0; }
.count { min-width: 20px; height: 20px; padding: 0 6px; border-radius: 999px; display: inline-flex; align-items: center; justify-content: center; font-size: 10.5px; font-weight: 700; background: var(--oz-danger); color: #fff; flex-shrink: 0; font-variant-numeric: tabular-nums; }
.tick { color: var(--oz-accent); flex-shrink: 0; }
.close { width: 30px; height: 30px; border-radius: 8px; display: flex; align-items: center; justify-content: center; border: none; background: none; color: var(--oz-muted); cursor: pointer; }
.close:hover { background: var(--oz-surface-2); color: var(--oz-ink); }

/* Mobil: alttan açılan sayfa */
.oz-fly-layer.is-sheet { z-index: 100; pointer-events: auto; background: rgba(15, 23, 42, .4); backdrop-filter: blur(1.5px); display: flex; align-items: flex-end; }
.is-sheet .oz-fly { position: relative; width: 100%; border-radius: 18px 18px 0 0; border-bottom: none; padding-bottom: env(safe-area-inset-bottom); }
.is-sheet .oz-fly::before { display: none; }
.is-sheet .head { position: relative; padding: 18px 14px 12px; }
.is-sheet .head::before { content: ''; position: absolute; top: 6px; left: 50%; width: 36px; height: 4px; margin-left: -18px; border-radius: 4px; background: var(--oz-border-strong); }
.is-sheet .list { max-height: 60dvh; padding: 8px; }
.is-sheet .item { padding: 11px 10px; }

.oz-fly-enter-active, .oz-fly-leave-active { transition: opacity .14s ease; }
.oz-fly-enter-active .oz-fly, .oz-fly-leave-active .oz-fly { transition: transform .16s cubic-bezier(0.16, 1, 0.3, 1); }
.oz-fly-enter-from, .oz-fly-leave-to { opacity: 0; }
.oz-fly-enter-from .oz-fly, .oz-fly-leave-to .oz-fly { transform: translateX(-6px); }
.oz-sheet-enter-active, .oz-sheet-leave-active { transition: opacity .18s ease; }
.oz-sheet-enter-active .oz-fly, .oz-sheet-leave-active .oz-fly { transition: transform .24s cubic-bezier(0.16, 1, 0.3, 1); }
.oz-sheet-enter-from, .oz-sheet-leave-to { opacity: 0; }
.oz-sheet-enter-from .oz-fly, .oz-sheet-leave-to .oz-fly { transform: translateY(100%); }
</style>
