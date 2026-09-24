<script setup>
import { computed, useId } from 'vue'

// Seçenek grubu. options: ['A', 'B'] veya [{ value, label, description? }]
// variant: list (alt alta radyo) | cards (seçilebilir kutular)
const props = defineProps({
  options: { type: Array, required: true },
  variant: { type: String, default: 'list', validator: (v) => ['list', 'cards'].includes(v) },
  inline: { type: Boolean, default: false },
  ariaLabel: { type: String, default: '' },
})

const model = defineModel({ type: [String, Number], default: '' })
const name = `oz-radio-${useId()}`

const normalized = computed(() =>
  props.options.map((o) => (typeof o === 'object' ? o : { value: o, label: String(o) })),
)
</script>

<template>
  <div class="oz-radio-group" :class="[`is-${variant}`, { 'is-inline': inline }]" role="radiogroup" :aria-label="ariaLabel || undefined">
    <label v-for="o in normalized" :key="o.value" class="opt" :class="{ checked: model === o.value }">
      <input v-model="model" type="radio" :name="name" :value="o.value" :disabled="o.disabled">
      <span class="dot" aria-hidden="true" />
      <span class="text">
        <span class="label">{{ o.label }}</span>
        <span v-if="o.description" class="desc">{{ o.description }}</span>
      </span>
    </label>
  </div>
</template>

<style scoped>
.oz-radio-group { display: flex; flex-direction: column; gap: 10px; }
.oz-radio-group.is-inline { flex-direction: row; flex-wrap: wrap; gap: 18px; }
.oz-radio-group.is-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 8px; }

.opt { display: inline-flex; align-items: flex-start; gap: 9px; cursor: pointer; user-select: none; }
input { position: absolute; opacity: 0; width: 0; height: 0; }
.dot {
  width: 17px; height: 17px; border-radius: 50%; flex-shrink: 0; margin-top: 1px;
  border: 1.5px solid var(--oz-border-strong); background: var(--oz-surface);
  transition: border-color .12s, box-shadow .12s, border-width .12s;
}
.opt:hover .dot { border-color: var(--oz-accent); }
input:checked + .dot { border: 5px solid var(--oz-accent); }
input:focus-visible + .dot { box-shadow: 0 0 0 3px rgba(var(--oz-accent-rgb), .25); }
input:disabled + .dot, input:disabled ~ .text { opacity: .5; }
.text { display: flex; flex-direction: column; gap: 2px; }
.label { font-size: 12.5px; font-weight: 600; color: var(--oz-ink-soft); line-height: 1.4; }
.desc { font-size: 11px; color: var(--oz-muted); }

.is-cards .opt {
  padding: 11px 12px; border-radius: 10px;
  border: 1px solid var(--oz-border); background: var(--oz-surface);
  transition: border-color .12s, background .12s, box-shadow .12s;
}
.is-cards .opt:hover { border-color: var(--oz-border-strong); }
.is-cards .opt.checked { border-color: rgba(var(--oz-accent-rgb), .55); background: var(--oz-accent-soft); box-shadow: 0 0 0 3px rgba(var(--oz-accent-rgb), .1); }
</style>
