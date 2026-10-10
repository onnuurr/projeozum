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
              <OzIcon v-if="c.level === 1 && c.icon" :name="c.icon" :size="16" class="item-ico" />
              <span v-else-if="c.level === 1" class="item-dot" aria-hidden="true" />
              <span class="item-text">
                <span class="item-title">{{ c.title }}</span>
                <span v-if="c.description" class="item-desc">{{ c.description }}</span>
              </span>
              <span v-if="!enabled(c)" class="soon-tag">Yakında</span>
              <span v-else-if="c.count != null" class="count">{{ c.count }}</span>
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
  position: fixed; width: 244px; pointer-events: auto;
  background: var(--oz-surface); border: 1px solid var(--oz-border-soft); border-radius: 12px;
  box-shadow: var(--oz-shadow); outline: none;
}
/* Minimal: başlık yalnız küçük etiket, satırlar çerçevesiz ikon + metin; seçili satır = yumuşak zemin + sol çizgi */
.head { display: flex; align-items: center; gap: 10px; padding: 12px 16px 4px; }
.head-title {
  flex: 1; min-width: 0; font-size: 10.5px; font-weight: 700; letter-spacing: .07em; text-transform: uppercase;
  color: var(--oz-muted-2); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.list { display: flex; flex-direction: column; gap: 2px; padding: 4px 6px 6px; max-height: min(420px, calc(100dvh - 120px)); overflow-y: auto; overscroll-behavior: contain; }
.item {
  position: relative; display: flex; align-items: center; gap: 10px; width: 100%; min-height: 34px;
  padding: 7px 10px; border-radius: 8px; border: none; background: none; cursor: pointer; text-align: left;
  font: inherit; color: var(--oz-ink-soft); outline: none;
  transition: background .12s, color .12s;
}
.item-ico { color: var(--oz-muted-2); flex-shrink: 0; transition: color .12s; }
.item-text { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 1px; }
.item-title { font-size: 13px; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.item-desc { font-size: 11px; color: var(--oz-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.item:not(.soon):hover, .item:not(.soon):focus-visible { background: var(--oz-surface-2); color: var(--oz-ink); }
.item:not(.soon):hover .item-ico { color: var(--oz-ink-soft); }
.item:focus-visible { box-shadow: inset 0 0 0 2px rgba(var(--oz-accent-rgb), .4); }
.item.active { background: var(--oz-accent-soft); color: var(--oz-accent); }
.item.active .item-title { font-weight: 600; }
.item.active .item-ico { color: var(--oz-accent); }
.item.active::before {
  content: ''; position: absolute; left: -6px; top: 8px; bottom: 8px; width: 3px; border-radius: 0 3px 3px 0;
  background: var(--oz-accent);
}
.item.soon { cursor: default; }
/* 3. seviye: ince dikey kılavuz çizgisiyle girintili */
.item.sub { min-height: 30px; padding: 5px 10px 5px 36px; }
.item.sub::after { content: ''; position: absolute; left: 18px; top: 0; bottom: 0; width: 1px; background: var(--oz-border); }
.item.sub .item-title { font-size: 12.5px; font-weight: 400; color: var(--oz-muted); }
.item.sub:hover .item-title { color: var(--oz-ink); }
.item-dot { width: 5px; height: 5px; margin: 0 5px; border-radius: 50%; background: var(--oz-border-strong); flex-shrink: 0; transition: background .12s; }
.item:hover .item-dot { background: var(--oz-muted); }
.item.active .item-dot { background: var(--oz-accent); }
.item.soon .item-title, .item.soon .item-ico { opacity: .45; }
.soon-tag { font-size: 10.5px; font-weight: 500; color: var(--oz-muted-2); flex-shrink: 0; }
.count {
  min-width: 18px; height: 18px; padding: 0 6px; border-radius: 999px; display: inline-flex; align-items: center; justify-content: center;
  font-size: 10.5px; font-weight: 600; background: var(--oz-danger-soft); color: var(--oz-danger); flex-shrink: 0; font-variant-numeric: tabular-nums;
}
.close { width: 30px; height: 30px; border-radius: 8px; display: flex; align-items: center; justify-content: center; border: none; background: none; color: var(--oz-muted); cursor: pointer; }
.close:hover { background: var(--oz-surface-2); color: var(--oz-ink); }

/* Mobil: alttan açılan sayfa */
.oz-fly-layer.is-sheet { z-index: 100; pointer-events: auto; background: rgba(15, 23, 42, .4); backdrop-filter: blur(1.5px); display: flex; align-items: flex-end; }
.is-sheet .oz-fly { position: relative; width: 100%; border-radius: 18px 18px 0 0; border-bottom: none; padding-bottom: env(safe-area-inset-bottom); }
.is-sheet .head { position: relative; padding: 20px 18px 6px; }
.is-sheet .head::before { content: ''; position: absolute; top: 6px; left: 50%; width: 36px; height: 4px; margin-left: -18px; border-radius: 4px; background: var(--oz-border-strong); }
.is-sheet .list { max-height: 60dvh; padding: 8px; }
.is-sheet .item { min-height: 44px; padding: 10px 12px; }
.is-sheet .item.sub { min-height: 38px; padding-left: 38px; }

.oz-fly-enter-active, .oz-fly-leave-active { transition: opacity .14s ease; }
.oz-fly-enter-active .oz-fly, .oz-fly-leave-active .oz-fly { transition: transform .16s cubic-bezier(0.16, 1, 0.3, 1); }
.oz-fly-enter-from, .oz-fly-leave-to { opacity: 0; }
.oz-fly-enter-from .oz-fly, .oz-fly-leave-to .oz-fly { transform: translateX(-6px); }
.oz-sheet-enter-active, .oz-sheet-leave-active { transition: opacity .18s ease; }
.oz-sheet-enter-active .oz-fly, .oz-sheet-leave-active .oz-fly { transition: transform .24s cubic-bezier(0.16, 1, 0.3, 1); }
.oz-sheet-enter-from, .oz-sheet-leave-to { opacity: 0; }
.oz-sheet-enter-from .oz-fly, .oz-sheet-leave-to .oz-fly { transform: translateY(100%); }
</style>
