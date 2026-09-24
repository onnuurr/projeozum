<script setup>
import { ref, computed, watch } from 'vue'
import { onClickOutside, useEventListener } from '@vueuse/core'

// Tetikleyici butona sağdan hizalanan açılır panel (bildirimler, kullanıcı menüsü vb.).
// Kullanım: <OzFloatingPanel v-model:open="x" :anchor="buttonEl"> ... </OzFloatingPanel>
const props = defineProps({
  open: { type: Boolean, default: false },
  anchor: { type: Object, default: null },
  width: { type: String, default: 'min(340px, calc(100vw - 24px))' },
})

const emit = defineEmits(['update:open'])

const style = ref({})

function place() {
  const el = props.anchor?.$el ?? props.anchor
  if (!el?.getBoundingClientRect) return
  const r = el.getBoundingClientRect()
  style.value = { top: `${r.bottom + 8}px`, right: `${window.innerWidth - r.right}px`, width: props.width }
}

watch(() => props.open, (v) => { if (v) place() })

const panelEl = ref(null)
const anchorEl = computed(() => props.anchor?.$el ?? props.anchor)

function close() {
  if (props.open) emit('update:open', false)
}

// Tetikleyici butonun kendisi hariç dışarı tıklama ve Esc kapatır
onClickOutside(panelEl, close, { ignore: [anchorEl] })
useEventListener(() => (props.open ? document : null), 'keydown', (e) => { if (e.key === 'Escape') close() })
useEventListener(() => (props.open ? window : null), 'resize', place)
</script>

<template>
  <div ref="panelEl" class="oz-floating-panel" :class="{ open }" :style="style" role="dialog">
    <slot />
  </div>
</template>

<style scoped>
.oz-floating-panel {
  position: fixed; top: 68px; right: 14px;
  background: var(--oz-surface); border: 1px solid var(--oz-border);
  border-radius: 14px; box-shadow: var(--oz-shadow);
  opacity: 0; pointer-events: none; transform: translateY(-6px) scale(.97);
  transition: opacity .15s ease, transform .15s ease;
  z-index: 50; overflow: hidden;
}
.oz-floating-panel.open { opacity: 1; pointer-events: all; transform: translateY(0) scale(1); }
</style>
