<script setup>
import { useId } from 'vue'

// Aç/kapa anahtarı. v-model: boolean
// Görünür etiket yoksa (ör. OzSettingRow içinde) aria-label verin; iç input'a uygulanır.
defineProps({
  label: { type: String, default: '' },
  description: { type: String, default: '' },
  disabled: { type: Boolean, default: false },
  ariaLabel: { type: String, default: undefined },
})

const model = defineModel({ type: Boolean, default: false })
const id = `oz-switch-${useId()}`
</script>

<template>
  <label class="oz-switch" :class="{ disabled }" :for="id">
    <input :id="id" v-model="model" type="checkbox" role="switch" :aria-checked="model" :aria-label="ariaLabel" :disabled="disabled">
    <span class="track" aria-hidden="true"><span class="thumb" /></span>
    <span v-if="label || description" class="text">
      <span class="label">{{ label }}</span>
      <span v-if="description" class="desc">{{ description }}</span>
    </span>
  </label>
</template>

<style scoped>
.oz-switch { display: inline-flex; align-items: flex-start; gap: 10px; cursor: pointer; user-select: none; }
.oz-switch.disabled { opacity: .5; cursor: not-allowed; }
input { position: absolute; opacity: 0; width: 0; height: 0; }
.track {
  width: 32px; height: 18px; border-radius: 999px; flex-shrink: 0; position: relative;
  background: var(--oz-track); box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.08);
  transition: background .15s, box-shadow .15s;
}
.thumb {
  position: absolute; top: 2px; left: 2px; width: 14px; height: 14px; border-radius: 50%;
  background: #fff; box-shadow: 0 1px 3px rgba(15, 23, 42, .25);
  transition: transform .18s cubic-bezier(0.16, 1, 0.3, 1);
}
input:checked + .track { background: var(--oz-accent); }
input:checked + .track .thumb { transform: translateX(14px); }
input:focus-visible + .track { box-shadow: 0 0 0 3px rgba(var(--oz-accent-rgb), .25); }
.text { display: flex; flex-direction: column; gap: 2px; }
.label { font-size: 12.5px; font-weight: 600; color: var(--oz-ink-soft); line-height: 1.4; }
.desc { font-size: 11px; color: var(--oz-muted); }
</style>
