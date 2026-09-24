<script setup>
import { useOzField } from './useOzField'
import { useOzSplitAttrs } from './useOzSplitAttrs'

defineOptions({ inheritAttrs: false })

const props = defineProps({
  placeholder: { type: String, default: '' },
  rows: { type: Number, default: 3 },
  maxlength: { type: Number, default: null },
  disabled: { type: Boolean, default: false },
  invalid: { type: Boolean, default: false },
  required: { type: Boolean, default: false },
  id: { type: String, default: '' },
})

const model = defineModel({ type: String, default: '' })
const { inputId, invalid, required, describedBy } = useOzField(props)
const { rootAttrs, inputAttrs } = useOzSplitAttrs()
</script>

<template>
  <div class="oz-textarea" :class="{ invalid }" v-bind="rootAttrs">
    <textarea
      :id="inputId"
      v-bind="inputAttrs"
      v-model="model"
      :rows="rows"
      :placeholder="placeholder"
      :maxlength="maxlength ?? undefined"
      :disabled="disabled"
      :required="required"
      :aria-invalid="invalid || undefined"
      :aria-describedby="describedBy"
    />
    <span v-if="maxlength" class="count">{{ model.length }}/{{ maxlength }}</span>
  </div>
</template>

<style scoped>
.oz-textarea { position: relative; }
textarea {
  display: block; width: 100%; resize: vertical; min-height: 72px;
  padding: 9px 12px;
  background: var(--oz-surface); border: 1px solid var(--oz-border); border-radius: 9px;
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
  font: inherit; font-size: 12.5px; line-height: 1.5; color: var(--oz-ink);
  outline: none;
  transition: border-color .15s, box-shadow .15s;
}
textarea::placeholder { color: var(--oz-muted-2); }
textarea:hover { border-color: var(--oz-border-strong); }
textarea:focus { border-color: rgba(var(--oz-accent-rgb), .55); box-shadow: 0 0 0 3px rgba(var(--oz-accent-rgb), .14); }
textarea:disabled { background: var(--oz-surface-2); opacity: .7; cursor: not-allowed; }
.invalid textarea { border-color: rgba(var(--oz-danger-rgb), .6); }
.invalid textarea:focus { box-shadow: 0 0 0 3px rgba(var(--oz-danger-rgb), .14); }
.count { position: absolute; right: 10px; bottom: 7px; font-size: 10px; color: var(--oz-muted-2); pointer-events: none; font-variant-numeric: tabular-nums; }
</style>
