<script setup>
import { computed } from 'vue'
import OzIcon from './OzIcon.vue'
import { useOzField } from './useOzField'
import { useOzSplitAttrs } from './useOzSplitAttrs'

defineOptions({ inheritAttrs: false })

// Yerel <select> (mobilde sistem seçicisi, klavye erişimi hazır).
// options: ['A', 'B'] veya [{ value, label, disabled? }]
const props = defineProps({
  options: { type: Array, required: true },
  placeholder: { type: String, default: '' },
  disabled: { type: Boolean, default: false },
  invalid: { type: Boolean, default: false },
  required: { type: Boolean, default: false },
  id: { type: String, default: '' },
})

const model = defineModel({ type: [String, Number], default: '' })
const { inputId, invalid, required, describedBy } = useOzField(props)
const { rootAttrs, inputAttrs } = useOzSplitAttrs()

const normalized = computed(() =>
  props.options.map((o) => (typeof o === 'object' ? o : { value: o, label: String(o) })),
)
</script>

<template>
  <div class="oz-select" :class="{ invalid, empty: model === '' || model == null }" v-bind="rootAttrs">
    <select
      :id="inputId"
      v-bind="inputAttrs"
      v-model="model"
      :disabled="disabled"
      :required="required"
      :aria-invalid="invalid || undefined"
      :aria-describedby="describedBy"
    >
      <option v-if="placeholder" value="" disabled>{{ placeholder }}</option>
      <option v-for="o in normalized" :key="o.value" :value="o.value" :disabled="o.disabled">{{ o.label }}</option>
    </select>
    <OzIcon name="chevron-down" :size="13" :stroke-width="2.5" class="chev" />
  </div>
</template>

<style scoped>
.oz-select { position: relative; }
select {
  appearance: none; width: 100%; height: 36px; padding: 0 34px 0 12px;
  background: var(--oz-surface); border: 1px solid var(--oz-border); border-radius: 9px;
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
  font: inherit; font-size: 12.5px; color: var(--oz-ink); cursor: pointer; outline: none;
  transition: border-color .15s, box-shadow .15s;
}
select:hover { border-color: var(--oz-border-strong); }
select:focus { border-color: rgba(var(--oz-accent-rgb), .55); box-shadow: 0 0 0 3px rgba(var(--oz-accent-rgb), .14); }
select:disabled { background: var(--oz-surface-2); opacity: .7; cursor: not-allowed; }
.empty select { color: var(--oz-muted-2); }
select option { color: var(--oz-ink); background: var(--oz-surface); }
.invalid select { border-color: rgba(var(--oz-danger-rgb), .6); }
.chev { position: absolute; right: 12px; top: 50%; transform: translateY(-50%); color: var(--oz-muted); pointer-events: none; }
</style>
