<script setup>
import { ref, useId } from 'vue'
import OzIcon from './OzIcon.vue'
import { useOzFocusTrap } from './useOzFocusTrap'

// Kenardan kayan panel (kayıt detayı, filtreler, hızlı düzenleme). v-model:open ile açılır.
// Slots: default (kayan gövde), footer (sabit alt butonlar), header (özel başlık),
//        header-actions (kapat butonunun soluna, ör. OzDropdown), sub-header (başlık altı, ör. OzTabs)
// .oz-root içine teleport edilir ki --oz-* token'ları ve tema geçerli olsun.
const props = defineProps({
  title: { type: String, default: '' },
  subtitle: { type: String, default: '' },
  icon: { type: String, default: '' },
  // Başlık ikonunun rengi: accent | info | warning | success | danger
  tone: { type: String, default: 'accent' },
  side: { type: String, default: 'right', validator: (v) => ['right', 'left'].includes(v) },
  size: { type: String, default: 'md', validator: (v) => ['sm', 'md', 'lg'].includes(v) },
  closeOnBackdrop: { type: Boolean, default: true },
  // Kaydetme gibi işlemler sürerken kapatmayı engellemek için
  persistent: { type: Boolean, default: false },
  // 'container': panelin kendisine odaklan (varsayılan) | 'auto': ilk form alanına
  initialFocus: { type: String, default: 'container', validator: (v) => ['container', 'auto'].includes(v) },
})

const open = defineModel('open', { type: Boolean, default: false })
const emit = defineEmits(['close'])

const titleId = `oz-drawer-title-${useId()}`
const panelEl = ref(null)

function close() {
  if (props.persistent) return
  open.value = false
  emit('close')
}

function onBackdrop() {
  if (props.closeOnBackdrop) close()
}

const { layer } = useOzFocusTrap(open, panelEl, { onEscape: close, initialFocus: props.initialFocus })
</script>

<template>
  <Teleport to=".oz-root" defer>
    <Transition :name="`oz-drawer-${side}`">
      <div v-if="open" class="oz-drawer-backdrop" :style="{ zIndex: layer }" :class="`is-${side}`" @mousedown.self="onBackdrop">
        <aside
          ref="panelEl"
          class="oz-drawer"
          :class="`size-${size}`"
          role="dialog"
          aria-modal="true"
          tabindex="-1"
          :aria-labelledby="title || $slots.header ? titleId : undefined"
        >
          <header class="head">
            <slot name="header">
              <div v-if="icon" class="ico" :class="`tone-${tone}`"><OzIcon :name="icon" :size="16" /></div>
              <div class="titles">
                <h3 :id="titleId">{{ title }}</h3>
                <p v-if="subtitle">{{ subtitle }}</p>
              </div>
            </slot>
            <div class="head-actions">
              <slot name="header-actions" />
              <button v-if="!persistent" type="button" class="close" aria-label="Kapat" @click="close">
                <OzIcon name="x" :size="16" />
              </button>
            </div>
          </header>

          <div v-if="$slots['sub-header']" class="sub-header"><slot name="sub-header" /></div>

          <div class="body"><slot /></div>

          <footer v-if="$slots.footer" class="foot"><slot name="footer" /></footer>
        </aside>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.oz-drawer-backdrop {
  position: fixed; inset: 0; z-index: 100;
  /* --oz-kb: iOS'ta sanal klavyenin yüksekliği (bkz. ozKeyboard.js) — katman klavyenin üstünde biter */
  bottom: var(--oz-kb, 0px);
  background: rgba(15, 23, 42, .4);
  backdrop-filter: blur(1.5px);
  display: flex; justify-content: flex-end;
  padding: 10px;
}
.oz-drawer-backdrop.is-left { justify-content: flex-start; }

.oz-drawer {
  width: 100%; height: 100%;
  display: flex; flex-direction: column;
  background: var(--oz-surface); color: var(--oz-ink);
  border: 1px solid var(--oz-border); border-radius: 16px;
  box-shadow: 0 24px 60px -12px rgba(15, 23, 42, .35);
  overflow: hidden;
  outline: none;
}
.size-sm { max-width: 380px; }
.size-md { max-width: 480px; }
.size-lg { max-width: 680px; }

.head { display: flex; align-items: flex-start; gap: 12px; padding: 18px 18px 14px; border-bottom: 1px solid var(--oz-border-soft); flex-shrink: 0; }
.ico { width: 36px; height: 36px; border-radius: 10px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; box-shadow: inset 0 1px 1px var(--oz-inset-hl), 0 2px 6px -1px rgba(15, 23, 42, 0.06); }
.tone-accent { background: var(--oz-accent-soft); color: var(--oz-accent); }
.tone-info { background: var(--oz-info-soft); color: var(--oz-info); }
.tone-warning { background: var(--oz-warning-soft); color: var(--oz-warning); }
.tone-success { background: var(--oz-success-soft); color: var(--oz-success); }
.tone-danger { background: var(--oz-danger-soft); color: var(--oz-danger); }
.titles { flex: 1; min-width: 0; }
h3 { margin: 0; font-size: 15px; font-weight: 700; color: var(--oz-ink-soft); letter-spacing: -.01em; }
.titles p { margin: 3px 0 0; font-size: 12px; color: var(--oz-muted); }
.head-actions { display: flex; align-items: center; gap: 4px; margin: -4px -4px 0 0; flex-shrink: 0; }
.close {
  width: 30px; height: 30px; border-radius: 8px;
  display: flex; align-items: center; justify-content: center;
  background: none; border: 1px solid transparent; color: var(--oz-muted); cursor: pointer;
  transition: background .12s, color .12s, border-color .12s;
}
.close:hover { background: var(--oz-surface-2); border-color: var(--oz-border); color: var(--oz-ink); }
.close:focus-visible { outline: 2px solid var(--oz-accent); outline-offset: 1px; }

.sub-header { flex-shrink: 0; padding-top: 4px; }
.body { flex: 1; min-height: 0; overflow-y: auto; padding: 18px; }
.foot {
  flex-shrink: 0;
  display: flex; justify-content: flex-end; gap: 8px; flex-wrap: wrap;
  padding: 12px 18px; border-top: 1px solid var(--oz-border-soft); background: var(--oz-surface-2);
}

/* giriş/çıkış: arka plan solar, panel kenardan kayar */
.oz-drawer-right-enter-active, .oz-drawer-right-leave-active,
.oz-drawer-left-enter-active, .oz-drawer-left-leave-active { transition: opacity .2s ease; }
.oz-drawer-right-enter-active .oz-drawer, .oz-drawer-right-leave-active .oz-drawer,
.oz-drawer-left-enter-active .oz-drawer, .oz-drawer-left-leave-active .oz-drawer { transition: transform .28s cubic-bezier(0.16, 1, 0.3, 1); }
.oz-drawer-right-enter-from, .oz-drawer-right-leave-to,
.oz-drawer-left-enter-from, .oz-drawer-left-leave-to { opacity: 0; }
.oz-drawer-right-enter-from .oz-drawer, .oz-drawer-right-leave-to .oz-drawer { transform: translateX(calc(100% + 20px)); }
.oz-drawer-left-enter-from .oz-drawer, .oz-drawer-left-leave-to .oz-drawer { transform: translateX(calc(-100% - 20px)); }

@media (max-width: 640px) {
  .oz-drawer-backdrop { padding: 0; }
  .oz-drawer { max-width: none; border-radius: 0; border: none; }
}
</style>
