<script setup>
import { ref, computed, onMounted } from 'vue'
import { useResizeObserver } from '@vueuse/core'
import { useOzTheme } from '@/composables/useOzTheme'
import OzIcon from './OzIcon.vue'
import OzRailFlyout from './OzRailFlyout.vue'

// Sol modül menüsü (masaüstü dikey ikon şeridi; mobilde OzShell gizler, yerine OzMobileNav gelir).
// children verilen modüle tıklanınca alt menü paneli (OzRailFlyout) açılır;
// panel açıkken başka bir modülün üzerine gelmek paneli o modüle geçirir.
const props = defineProps({
  // [{ key, title, icon, to?, children?: [{ key, title, to?, icon?, description?, count? }] }]
  items: { type: Array, required: true },
  // Alta sabitlenen öğeler (ör. Ayarlar)
  bottomItems: { type: Array, default: () => [] },
  active: { type: String, default: '' },
  // Etkin alt sayfanın key'i (panelde işaretlenir)
  activeChild: { type: String, default: '' },
  themeToggle: { type: Boolean, default: true },
})

// select: yaprak modüle ya da bir alt sayfaya tıklanınca ({ ...item } — `to` taşır)
const emit = defineEmits(['select'])

const { theme, toggleTheme } = useOzTheme()

const hasSub = (item) => !!item.children?.length
const enabled = (c) => !!(c.to || c.run)
// Açılabilen bir alt sayfada uyarı sayısı varsa rail ikonunda nokta gösterilir
const hasCount = (item) => item.children?.some((c) => c.count && enabled(c))

/* ── alt menü paneli ── */
const flyItem = ref(null)
const flyAnchor = ref(null)

function openFly(item, el) {
  flyItem.value = item
  flyAnchor.value = el
}

function closeFly(restoreFocus = false) {
  const el = flyAnchor.value
  flyItem.value = null
  if (restoreFocus) el?.focus()
}

function onItem(item, e) {
  if (hasSub(item)) {
    flyItem.value?.key === item.key ? closeFly() : openFly(item, e.currentTarget)
  } else {
    closeFly()
    emit('select', item)
  }
}

// Dokunmatikte (tablet) dokunma önce hover, sonra click üretir: hover paneli yeni modüle
// geçirir, ardından gelen click "zaten açık" deyip kapatırdı. Hover yalnız gerçek fareyle çalışır.
const isMouse = (e) => e.pointerType === 'mouse'
function onHover(item, e) {
  if (!isMouse(e)) return
  if (flyItem.value && hasSub(item) && flyItem.value.key !== item.key) openFly(item, e.currentTarget)
}

function onItemKey(item, e) {
  if (hasSub(item) && ['ArrowRight', 'ArrowDown'].includes(e.key) && !flyItem.value) {
    e.preventDefault()
    openFly(item, e.currentTarget)
  }
}

function onChild(child) {
  closeFly()
  emit('select', child)
}

/* ── kaydırma: çubuk gizli; ikonlar sığmıyorsa kayar, kaydırılabilir kenarda solma ── */
const scrollEl = ref(null)
const fadeTop = ref(false)
const fadeBottom = ref(false)
function updateFade() {
  const el = scrollEl.value
  if (!el) return
  fadeTop.value = el.scrollTop > 2
  fadeBottom.value = el.scrollTop + el.clientHeight < el.scrollHeight - 2
}
function onScroll() {
  updateFade()
  tip.value = null
  if (flyItem.value) closeFly()
}
onMounted(updateFade)
useResizeObserver(scrollEl, updateFade)

/* ── ipucu: kaydırma alanında kırpılmasın diye ekrana sabit tek balon ── */
const tip = ref(null)
function showTip(text, e) {
  const r = e.currentTarget.getBoundingClientRect()
  tip.value = { text, top: `${Math.round(r.top + r.height / 2)}px`, left: `${Math.round(r.right + 10)}px` }
}

const groups = computed(() => [
  { key: 'main', items: props.items, size: 17 },
  { key: 'bottom', items: props.bottomItems, size: 16 },
])
</script>

<template>
  <nav class="oz-rail" aria-label="Modüller">
    <div
      ref="scrollEl"
      class="rail-scroll"
      :class="{ 'fade-top': fadeTop, 'fade-bottom': fadeBottom }"
      @scroll.passive="onScroll"
    >
    <template v-for="g in groups" :key="g.key">
      <div v-if="g.key === 'bottom'" class="rail-sep" />
      <button
        v-for="item in g.items"
        :key="item.key"
        type="button"
        class="rail-btn"
        :class="{ active: item.key === active, 'has-sub': hasSub(item), expanded: flyItem?.key === item.key }"
        :aria-label="item.title"
        :aria-current="item.key === active ? 'page' : undefined"
        :aria-haspopup="hasSub(item) ? 'menu' : undefined"
        :aria-expanded="hasSub(item) ? flyItem?.key === item.key : undefined"
        @click="onItem(item, $event)"
        @pointerenter="onHover(item, $event); isMouse($event) && flyItem?.key !== item.key && showTip(item.title, $event)"
        @pointerleave="tip = null"
        @keydown="onItemKey(item, $event)"
      >
        <OzIcon :name="item.icon" :size="g.size" />
        <span v-if="hasSub(item)" class="sub-mark" :class="{ alert: hasCount(item) }" aria-hidden="true" />
      </button>
    </template>

    <button
      v-if="themeToggle"
      type="button"
      class="rail-btn"
      aria-label="Tema Değiştir"
      @click="toggleTheme"
      @pointerenter="isMouse($event) && showTip(theme === 'dark' ? 'Açık Tema' : 'Koyu Tema', $event)"
      @pointerleave="tip = null"
    >
      <OzIcon :name="theme === 'dark' ? 'moon' : 'sun'" :size="15" />
    </button>
    </div>

    <div v-if="tip" class="rail-tip" :style="{ top: tip.top, left: tip.left }" aria-hidden="true">{{ tip.text }}</div>

    <OzRailFlyout
      :module="flyItem"
      :anchor="flyAnchor"
      :active-child="flyItem?.key === active ? activeChild : ''"
      @select="onChild"
      @close="closeFly"
    />
  </nav>
</template>

<style scoped>
.oz-rail {
  height: 100%;
  background: var(--oz-surface); border: 1px solid var(--oz-border);
  border-radius: var(--oz-radius); box-shadow: var(--oz-shadow-sm);
  display: flex; flex-direction: column;
  padding: 10px 0;
  overflow: hidden; /* kaydırma .rail-scroll'da; ipucu balonu ekrana sabit (kırpılmaz) */
}
/* Kaydırma çubuğu gizli; ikonlar sığmazsa tekerlek/dokunmayla kayar. Sığıyorsa .rail-sep alt grubu en alta iter.
   Yer daralınca butonlar önce 38px → 30px küçülür (flex-shrink); kaydırma ancak bu da yetmezse başlar —
   birkaç piksellik taşmada tekerlekle şeridin aşağı yukarı oynaması önlenir. */
.rail-scroll {
  flex: 1; min-height: 0;
  display: flex; flex-direction: column; align-items: center; gap: 5px;
  padding: 2px 0;
  overflow-y: auto; overflow-x: hidden; overscroll-behavior: contain;
  scrollbar-width: none;
}
.rail-scroll::-webkit-scrollbar { display: none; }
/* kaydırılabilir kenarda solma (çubuk gizli olduğu için ipucu) */
.rail-scroll.fade-top { mask-image: linear-gradient(to bottom, transparent 0, #000 22px); }
.rail-scroll.fade-bottom { mask-image: linear-gradient(to top, transparent 0, #000 22px); }
.rail-scroll.fade-top.fade-bottom { mask-image: linear-gradient(to bottom, transparent 0, #000 22px, #000 calc(100% - 22px), transparent 100%); }
.rail-btn {
  width: 38px; height: 38px; min-height: 30px; border-radius: 9px; flex-shrink: 1;
  background: none; border: none; color: var(--oz-muted-2); cursor: pointer;
  display: flex; align-items: center; justify-content: center;
  transition: background .15s, color .15s, transform .1s;
  position: relative;
}
.rail-btn:hover { background: var(--oz-accent-soft); color: var(--oz-accent); transform: scale(1.05); }
.rail-btn.active { background: var(--oz-accent); color: #fff; box-shadow: 0 4px 12px rgba(var(--oz-accent-rgb), .35); }
.rail-btn.expanded:not(.active) { background: var(--oz-accent-soft); color: var(--oz-accent); box-shadow: inset 0 0 0 1px rgba(var(--oz-accent-rgb), .25); }
.rail-btn:focus-visible { outline: 2px solid var(--oz-accent); outline-offset: 2px; }

/* Alt menü işareti: sağ alt köşede küçük üçgen; uyarı sayısı varsa kırmızı nokta */
.sub-mark {
  position: absolute; right: 4px; bottom: 4px; width: 0; height: 0;
  border-left: 4px solid transparent; border-bottom: 4px solid currentColor; opacity: .55;
}
.rail-btn.active .sub-mark { opacity: .8; }
.sub-mark.alert { right: 5px; top: 5px; bottom: auto; width: 6px; height: 6px; border: none; border-radius: 50%; background: var(--oz-danger); opacity: 1; box-shadow: 0 0 0 2px var(--oz-surface); }
.rail-btn.active .sub-mark.alert { box-shadow: 0 0 0 2px var(--oz-accent); }

.rail-sep { flex: 1 0 12px; }
.rail-tip {
  position: fixed; transform: translateY(-50%);
  background: var(--oz-ink-soft); color: var(--oz-surface); font-size: 11px; font-weight: 600;
  padding: 5px 9px; border-radius: 7px; white-space: nowrap;
  pointer-events: none; z-index: 40; animation: oz-rail-tip .12s ease;
  box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}
@keyframes oz-rail-tip { from { opacity: 0; } }
</style>
