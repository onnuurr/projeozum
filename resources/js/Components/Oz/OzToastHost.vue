<script setup>
import OzIcon from './OzIcon.vue'
import { ozToastInternals } from './useOzToast'

// Bildirim yığını. OzShell tarafından bir kez render edilir; sayfaların eklemesine gerek yok.
const { state, remove, pause, resume } = ozToastInternals

const icons = { success: 'check', info: 'bell', warning: 'alert', danger: 'alert' }

function runAction(t) {
  t.action.onClick?.()
  remove(t.id)
}
</script>

<template>
  <div class="oz-toasts" role="region" aria-label="Bildirimler" aria-live="polite">
    <TransitionGroup name="oz-toast">
      <div
        v-for="t in state.items"
        :key="t.id"
        class="oz-toast"
        :class="`tone-${t.tone}`"
        :role="t.tone === 'danger' ? 'alert' : 'status'"
        @mouseenter="pause(t.id)"
        @mouseleave="resume(t.id)"
      >
        <div class="ico"><OzIcon :name="icons[t.tone]" :size="15" :stroke-width="2.5" /></div>
        <div class="body">
          <div class="title">{{ t.title }}</div>
          <div v-if="t.description" class="desc">{{ t.description }}</div>
          <button v-if="t.action" type="button" class="action" @click="runAction(t)">{{ t.action.label }}</button>
        </div>
        <button type="button" class="close" aria-label="Bildirimi kapat" @click="remove(t.id)">
          <OzIcon name="x" :size="14" />
        </button>
        <div v-if="t.duration" class="bar" :style="{ animationDuration: t.duration + 'ms' }" />
      </div>
    </TransitionGroup>
  </div>
</template>

<style scoped>
.oz-toasts {
  position: fixed; right: 18px; bottom: 18px; z-index: 120;
  display: flex; flex-direction: column; gap: 10px; align-items: flex-end;
  width: min(360px, calc(100vw - 24px));
  pointer-events: none;
}
.oz-toast {
  position: relative; overflow: hidden; pointer-events: all;
  width: 100%; display: flex; align-items: flex-start; gap: 11px;
  padding: 12px 12px 13px 14px;
  background: var(--oz-surface); border: 1px solid var(--oz-border); border-radius: 12px;
  box-shadow: var(--oz-shadow);
}
.ico { width: 28px; height: 28px; border-radius: 8px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; }
.tone-success .ico { background: var(--oz-success-soft); color: var(--oz-success); }
.tone-info .ico { background: var(--oz-accent-soft); color: var(--oz-accent); }
.tone-warning .ico { background: var(--oz-warning-soft); color: var(--oz-warning); }
.tone-danger .ico { background: var(--oz-danger-soft); color: var(--oz-danger); }
.body { flex: 1; min-width: 0; padding-top: 1px; }
.title { font-size: 12.5px; font-weight: 700; color: var(--oz-ink-soft); }
.desc { font-size: 11.5px; color: var(--oz-muted); margin-top: 2px; line-height: 1.45; }
.action { margin-top: 7px; padding: 0; background: none; border: none; font: inherit; font-size: 11.5px; font-weight: 700; color: var(--oz-accent); cursor: pointer; }
.action:hover { text-decoration: underline; }
.close {
  width: 24px; height: 24px; border-radius: 6px; flex-shrink: 0;
  display: flex; align-items: center; justify-content: center;
  background: none; border: none; color: var(--oz-muted-2); cursor: pointer;
}
.close:hover { background: var(--oz-surface-2); color: var(--oz-ink); }
.close:focus-visible, .action:focus-visible { outline: 2px solid var(--oz-accent); outline-offset: 1px; }

/* kalan süre çubuğu; fare üstündeyken durur */
.bar { position: absolute; left: 0; bottom: 0; height: 2px; width: 100%; transform-origin: left; animation: oz-toast-bar linear forwards; opacity: .55; }
.oz-toast:hover .bar { animation-play-state: paused; }
.tone-success .bar { background: var(--oz-success); }
.tone-info .bar { background: var(--oz-accent); }
.tone-warning .bar { background: var(--oz-warning); }
.tone-danger .bar { background: var(--oz-danger); }
@keyframes oz-toast-bar { from { transform: scaleX(1); } to { transform: scaleX(0); } }

.oz-toast-enter-active, .oz-toast-leave-active { transition: opacity .2s ease, transform .25s cubic-bezier(0.16, 1, 0.3, 1); }
.oz-toast-enter-from { opacity: 0; transform: translateY(10px) scale(.97); }
.oz-toast-leave-to { opacity: 0; transform: translateX(24px); }
.oz-toast-move { transition: transform .25s cubic-bezier(0.16, 1, 0.3, 1); }

@media (max-width: 640px) {
  .oz-toasts { right: 12px; left: 12px; bottom: 12px; width: auto; }
}
</style>
