<script setup>
import { computed, provide, useId } from 'vue'

// Form alanı sarmalayıcısı: etiket, yardım metni ve hata mesajı.
// İçindeki Oz form bileşenleri id / aria / hata durumunu buradan otomatik alır.
//   <OzField label="Ad" error="Zorunlu"><OzInput v-model="name" /></OzField>
const props = defineProps({
  label: { type: String, default: '' },
  hint: { type: String, default: '' },
  error: { type: String, default: '' },
  required: { type: Boolean, default: false },
  // Yan yana etiket (ayarlar formları için)
  inline: { type: Boolean, default: false },
})

const id = `oz-field-${useId()}`
const hintId = `${id}-hint`

const describedBy = computed(() => (props.error || props.hint ? hintId : undefined))

provide('ozField', {
  id,
  describedBy,
  invalid: computed(() => !!props.error),
  required: computed(() => props.required),
})
</script>

<template>
  <div class="oz-field" :class="{ 'is-inline': inline }">
    <label v-if="label" :for="id" class="label">
      {{ label }}<span v-if="required" class="req" aria-hidden="true">*</span>
    </label>
    <div class="control">
      <slot :id="id" />
      <p v-if="error" :id="hintId" class="msg error" role="alert">{{ error }}</p>
      <p v-else-if="hint" :id="hintId" class="msg">{{ hint }}</p>
    </div>
  </div>
</template>

<style scoped>
.oz-field { display: flex; flex-direction: column; gap: 6px; min-width: 0; }
.oz-field.is-inline { flex-direction: row; align-items: flex-start; gap: 16px; }
.oz-field.is-inline .label { width: 160px; flex-shrink: 0; padding-top: 9px; }
.oz-field.is-inline .control { flex: 1; }
.control { display: flex; flex-direction: column; gap: 5px; min-width: 0; }
.label { font-size: 12px; font-weight: 600; color: var(--oz-ink-soft); }
.req { color: var(--oz-danger); margin-left: 3px; }
.msg { margin: 0; font-size: 11px; color: var(--oz-muted); }
.msg.error { color: var(--oz-danger); font-weight: 500; }

@media (max-width: 640px) {
  .oz-field.is-inline { flex-direction: column; gap: 6px; }
  .oz-field.is-inline .label { width: auto; padding-top: 0; }
}
</style>
