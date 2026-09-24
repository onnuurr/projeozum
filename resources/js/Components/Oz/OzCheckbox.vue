<script setup>
import { useId } from 'vue'
import OzIcon from './OzIcon.vue'

// Onay kutusu. v-model: boolean
// Görünür etiket yoksa (ör. tablo hücresi) aria-label verin; iç input'a uygulanır.
defineProps({
  label: { type: String, default: '' },
  description: { type: String, default: '' },
  disabled: { type: Boolean, default: false },
  ariaLabel: { type: String, default: undefined },
})

const model = defineModel({ type: Boolean, default: false })
const id = `oz-check-${useId()}`
</script>

<template>
  <label class="oz-check" :class="{ disabled }" :for="id">
    <input :id="id" v-model="model" type="checkbox" :aria-label="ariaLabel" :disabled="disabled">
    <span class="box" aria-hidden="true"><OzIcon name="check" :size="11" :stroke-width="3.5" /></span>
    <span v-if="label || description || $slots.default" class="text">
      <span class="label"><slot>{{ label }}</slot></span>
      <span v-if="description" class="desc">{{ description }}</span>
    </span>
  </label>
</template>

<style scoped>
.oz-check { display: inline-flex; align-items: flex-start; gap: 9px; cursor: pointer; user-select: none; }
.oz-check.disabled { opacity: .5; cursor: not-allowed; }
input { position: absolute; opacity: 0; width: 0; height: 0; }
.box {
  width: 17px; height: 17px; border-radius: 5px; flex-shrink: 0; margin-top: 1px;
  border: 1.5px solid var(--oz-border-strong); background: var(--oz-surface);
  display: flex; align-items: center; justify-content: center; color: transparent;
  transition: background .12s, border-color .12s, color .12s, box-shadow .12s;
}
.oz-check:hover .box { border-color: var(--oz-accent); }
input:checked + .box { background: var(--oz-accent); border-color: var(--oz-accent); color: #fff; box-shadow: 0 2px 6px rgba(var(--oz-accent-rgb), .3); }
input:focus-visible + .box { box-shadow: 0 0 0 3px rgba(var(--oz-accent-rgb), .25); }
.text { display: flex; flex-direction: column; gap: 2px; }
.label { font-size: 12.5px; font-weight: 600; color: var(--oz-ink-soft); line-height: 1.4; }
.desc { font-size: 11px; color: var(--oz-muted); }
</style>
