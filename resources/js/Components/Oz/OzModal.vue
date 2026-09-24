<script setup>
import { ref, useId } from 'vue'
import OzIcon from './OzIcon.vue'
import { useOzFocusTrap } from './useOzFocusTrap'

// Modal pencere. v-model:open ile açılır/kapanır.
// Slots: default (gövde), footer (alt butonlar), header (özel başlık)
// .oz-root içine teleport edilir ki --oz-* token'ları ve tema geçerli olsun.
const props = defineProps({
  title: { type: String, default: '' },
  subtitle: { type: String, default: '' },
  icon: { type: String, default: '' },
  // Başlık ikonunun rengi: accent | info | warning | success | danger
  tone: { type: String, default: 'accent' },
  size: { type: String, default: 'md', validator: (v) => ['sm', 'md', 'lg'].includes(v) },
  closeOnBackdrop: { type: Boolean, default: true },
  // Kaydetme gibi işlemler sürerken kapatmayı engellemek için
  persistent: { type: Boolean, default: false },
})

const open = defineModel('open', { type: Boolean, default: false })
const emit = defineEmits(['close'])

const titleId = `oz-modal-title-${useId()}`
const dialogEl = ref(null)

function close() {
  if (props.persistent) return
  open.value = false
  emit('close')
}

function onBackdrop() {
  if (props.closeOnBackdrop) close()
}

const { layer } = useOzFocusTrap(open, dialogEl, { onEscape: close })
</script>

<template>
  <Teleport to=".oz-root" defer>
    <Transition name="oz-modal">
      <div v-if="open" class="oz-modal-backdrop" :style="{ zIndex: layer }" @mousedown.self="onBackdrop">
        <div
          ref="dialogEl"
          class="oz-modal"
          :class="`size-${size}`"
          role="dialog"
          aria-modal="true"
          :aria-labelledby="title || $slots.header ? titleId : undefined"
        >
          <header v-if="title || $slots.header" class="head">
            <slot name="header">
              <div v-if="icon" class="ico" :class="`tone-${tone}`"><OzIcon :name="icon" :size="16" /></div>
              <div class="titles">
                <h3 :id="titleId">{{ title }}</h3>
                <p v-if="subtitle">{{ subtitle }}</p>
              </div>
            </slot>
            <button v-if="!persistent" type="button" class="close" aria-label="Kapat" @click="close">
              <OzIcon name="x" :size="16" />
            </button>
          </header>

          <div class="body"><slot /></div>

          <footer v-if="$slots.footer" class="foot"><slot name="footer" /></footer>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.oz-modal-backdrop {
  position: fixed; inset: 0; z-index: 100;
  background: rgba(15, 23, 42, .45);
  backdrop-filter: blur(2px);
  display: flex; align-items: center; justify-content: center;
  padding: 16px;
}
.oz-modal {
  width: 100%; max-height: calc(100dvh - 32px);
  display: flex; flex-direction: column;
  background: var(--oz-surface); color: var(--oz-ink);
  border: 1px solid var(--oz-border); border-radius: 16px;
  box-shadow: 0 24px 60px -12px rgba(15, 23, 42, .35);
  overflow: hidden;
}
.size-sm { max-width: 400px; }
.size-md { max-width: 560px; }
.size-lg { max-width: 820px; }

.head { display: flex; align-items: flex-start; gap: 12px; padding: 18px 18px 14px; border-bottom: 1px solid var(--oz-border-soft); }
.ico { width: 36px; height: 36px; border-radius: 10px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; box-shadow: inset 0 1px 1px var(--oz-inset-hl), 0 2px 6px -1px rgba(15, 23, 42, 0.06); }
.tone-accent { background: var(--oz-accent-soft); color: var(--oz-accent); }
.tone-info { background: var(--oz-info-soft); color: var(--oz-info); }
.tone-warning { background: var(--oz-warning-soft); color: var(--oz-warning); }
.tone-success { background: var(--oz-success-soft); color: var(--oz-success); }
.tone-danger { background: var(--oz-danger-soft); color: var(--oz-danger); }
.titles { flex: 1; min-width: 0; }
h3 { margin: 0; font-size: 15px; font-weight: 700; color: var(--oz-ink-soft); letter-spacing: -.01em; }
.titles p { margin: 3px 0 0; font-size: 12px; color: var(--oz-muted); }
.close {
  width: 30px; height: 30px; border-radius: 8px; flex-shrink: 0; margin: -4px -4px 0 0;
  display: flex; align-items: center; justify-content: center;
  background: none; border: 1px solid transparent; color: var(--oz-muted); cursor: pointer;
  transition: background .12s, color .12s, border-color .12s;
}
.close:hover { background: var(--oz-surface-2); border-color: var(--oz-border); color: var(--oz-ink); }
.close:focus-visible { outline: 2px solid var(--oz-accent); outline-offset: 1px; }

.body { padding: 18px; overflow-y: auto; }
.foot {
  display: flex; justify-content: flex-end; gap: 8px; flex-wrap: wrap;
  padding: 12px 18px; border-top: 1px solid var(--oz-border-soft); background: var(--oz-surface-2);
}

.oz-modal-enter-active, .oz-modal-leave-active { transition: opacity .18s ease; }
.oz-modal-enter-active .oz-modal, .oz-modal-leave-active .oz-modal { transition: transform .2s cubic-bezier(0.16, 1, 0.3, 1); }
.oz-modal-enter-from, .oz-modal-leave-to { opacity: 0; }
.oz-modal-enter-from .oz-modal, .oz-modal-leave-to .oz-modal { transform: translateY(8px) scale(.97); }

@media (max-width: 640px) {
  .oz-modal-backdrop { align-items: flex-end; padding: 0; }
  .oz-modal { max-width: none; border-radius: 16px 16px 0 0; max-height: 92dvh; }
}
</style>
