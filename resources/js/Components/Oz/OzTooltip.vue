<script setup>
import { ref, useId } from 'vue'
import { useTimeoutFn } from '@vueuse/core'
import { useOzAnchor } from './useOzAnchor'

// İki kök düğüm var (tetikleyici + teleport); class/style gibi attribute'lar tetikleyiciye gider
defineOptions({ inheritAttrs: false })

// İpucu balonu. Fareyle üzerine gelince veya klavyeyle odaklanınca görünür.
//   <OzTooltip text="Dışa aktar"><button>…</button></OzTooltip>
const props = defineProps({
  text: { type: String, required: true },
  placement: { type: String, default: 'top', validator: (v) => ['top', 'bottom'].includes(v) },
  delay: { type: Number, default: 250 },
})

const visible = ref(false)
const wrapEl = ref(null)
const tipEl = ref(null)
const tipId = `oz-tip-${useId()}`
const { style, side, update } = useOzAnchor({ placement: props.placement, offset: 8 })
const { start: show, stop } = useTimeoutFn(async () => {
  visible.value = true
  // tetikleyici: sarmalanan ilk öğe
  await update(wrapEl.value?.firstElementChild ?? wrapEl.value, () => tipEl.value)
}, () => props.delay, { immediate: false })

function hide() {
  stop()
  visible.value = false
}

function onKey(e) {
  if (e.key === 'Escape') hide()
}

</script>

<template>
  <span
    ref="wrapEl"
    class="oz-tooltip-trigger"
    v-bind="$attrs"
    :aria-describedby="visible ? tipId : undefined"
    @mouseenter="show"
    @mouseleave="hide"
    @focusin="show"
    @focusout="hide"
    @keydown="onKey"
  >
    <slot />
  </span>
  <Teleport to=".oz-root" defer>
    <Transition name="oz-tip">
      <div v-if="visible" :id="tipId" ref="tipEl" role="tooltip" class="oz-tooltip" :class="`side-${side}`" :style="style">
        {{ text }}
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.oz-tooltip-trigger { display: inline-flex; }
.oz-tooltip {
  position: fixed; z-index: 130; pointer-events: none;
  max-width: 240px; padding: 5px 9px; border-radius: 7px;
  background: var(--oz-ink-soft); color: var(--oz-surface);
  font-size: 11px; font-weight: 600; line-height: 1.4;
  box-shadow: 0 4px 12px rgba(0, 0, 0, .15);
}
.oz-tip-enter-active, .oz-tip-leave-active { transition: opacity .1s ease, transform .1s ease; }
.oz-tip-enter-from, .oz-tip-leave-to { opacity: 0; }
.side-top.oz-tip-enter-from { transform: translateY(3px); }
.side-bottom.oz-tip-enter-from { transform: translateY(-3px); }
</style>
