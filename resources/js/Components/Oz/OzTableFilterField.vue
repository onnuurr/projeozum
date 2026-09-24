<script setup>
import OzInput from './OzInput.vue'
import OzCombobox from './OzCombobox.vue'

// OzTable filtre panelindeki tek alan (sütunun filter.type'ına göre). OzTableFilters kullanır.
const props = defineProps({
  col: { type: Object, required: true },
  modelValue: { type: [String, Number, Array, Object], required: true },
})

const emit = defineEmits(['update:modelValue'])

function setPart(part, v) {
  emit('update:modelValue', { ...props.modelValue, [part]: v })
}
</script>

<template>
  <OzCombobox
    v-if="col.filter.type === 'select'"
    :model-value="modelValue"
    :options="col._options"
    :multiple="col.filter.multiple"
    :placeholder="col.filter.placeholder || 'Tümü'"
    :max-tags="2"
    clearable
    @update:model-value="emit('update:modelValue', $event)"
  />
  <OzInput
    v-else-if="col.filter.type === 'text'"
    :model-value="modelValue"
    :placeholder="col.filter.placeholder || 'İçerir…'"
    icon="search"
    @update:model-value="emit('update:modelValue', $event)"
  />
  <div v-else-if="col.filter.type === 'range'" class="pair">
    <OzInput :model-value="modelValue.min" type="number" placeholder="En az" :suffix="col.filter.suffix" aria-label="En az" @update:model-value="setPart('min', $event)" />
    <span class="dash" aria-hidden="true">–</span>
    <OzInput :model-value="modelValue.max" type="number" placeholder="En çok" :suffix="col.filter.suffix" aria-label="En çok" @update:model-value="setPart('max', $event)" />
  </div>
  <div v-else-if="col.filter.type === 'date'" class="pair">
    <OzInput :model-value="modelValue.from" type="date" aria-label="Başlangıç" @update:model-value="setPart('from', $event)" />
    <span class="dash" aria-hidden="true">–</span>
    <OzInput :model-value="modelValue.to" type="date" aria-label="Bitiş" @update:model-value="setPart('to', $event)" />
  </div>
</template>

<style scoped>
.pair { display: flex; align-items: center; gap: 6px; }
.pair > * { flex: 1; min-width: 0; }
.pair .dash { flex: 0 0 auto; color: var(--oz-muted-2); }
/* dar kutuda sayı ok düğmeleri yer kaplamasın */
.pair :deep(input[type=number]) { -moz-appearance: textfield; appearance: textfield; }
.pair :deep(input[type=number])::-webkit-inner-spin-button,
.pair :deep(input[type=number])::-webkit-outer-spin-button { -webkit-appearance: none; margin: 0; }
</style>
