<script setup>
import { reactive, ref, watch } from 'vue'
import { OzModal, OzField, OzInput, OzSelect, OzTextarea, OzRadioGroup, OzSwitch, OzButton } from '@/Components/Oz'

// "Yeni İş Emri" formu. Doğrulamadan geçen veriyi `save` ile yayar; kaydetmeyi sayfa yapar.
//   <WorkOrderModal v-model:open="x" :partners="[...]" :workshops="[...]" @save="onSave" />
// onSave(payload, done): iş bitince done() çağrılır, modal kapanır.
const props = defineProps({
  partners: { type: Array, required: true },
  // [{ value, label, disabled? }]
  workshops: { type: Array, required: true },
})

const open = defineModel('open', { type: Boolean, default: false })
const emit = defineEmits(['save'])

const priorityOptions = [
  { value: 'normal', label: 'Normal', description: 'Sıradaki boş kapasiteye' },
  { value: 'high', label: 'Yüksek', description: 'Planın öne alınır' },
  { value: 'urgent', label: 'Acil', description: '24 saat içinde başlar' },
]

const empty = () => ({ partner: '', product: '', qty: '', workshop: '', priority: 'normal', dueDate: '', note: '', notify: true })
const form = reactive(empty())
const errors = reactive({})
const saving = ref(false)
const formId = `work-order-form-${Math.random().toString(36).slice(2, 8)}`

function reset() {
  Object.assign(form, empty())
  Object.keys(errors).forEach((k) => delete errors[k])
  saving.value = false
}

watch(open, (v) => { if (v) reset() })

function validate() {
  Object.keys(errors).forEach((k) => delete errors[k])
  if (!form.partner) errors.partner = 'İş ortağı seçin'
  if (!form.product.trim()) errors.product = 'Ürün adı zorunlu'
  if (!(Number(form.qty) > 0)) errors.qty = 'Adet 0\'dan büyük olmalı'
  if (!form.workshop) errors.workshop = 'Atölye seçin'
  return !Object.keys(errors).length
}

function submit() {
  if (!validate()) return
  saving.value = true
  emit('save', { ...form, product: form.product.trim(), qty: Number(form.qty) }, () => {
    saving.value = false
    open.value = false
  })
}
</script>

<template>
  <OzModal v-model:open="open" title="Yeni İş Emri" subtitle="Üretim planına yeni bir iş emri ekleyin" icon="plus" size="lg" :persistent="saving">
    <form :id="formId" class="wo-grid" novalidate @submit.prevent="submit">
      <OzField label="İş Ortağı" :error="errors.partner" required>
        <OzSelect v-model="form.partner" :options="partners" placeholder="Seçin" />
      </OzField>
      <OzField label="Ürün" :error="errors.product" required>
        <OzInput v-model="form.product" placeholder="ör. Pamuklu Gömlek" />
      </OzField>
      <OzField label="Adet" :error="errors.qty" required>
        <OzInput v-model="form.qty" type="number" min="1" suffix="adet" placeholder="0" />
      </OzField>
      <OzField label="Termin">
        <OzInput v-model="form.dueDate" type="date" />
      </OzField>
      <OzField label="Atölye" :error="errors.workshop" required class="span-2">
        <OzSelect v-model="form.workshop" :options="workshops" placeholder="Atölye seçin" />
      </OzField>
      <OzField label="Öncelik" class="span-2">
        <OzRadioGroup v-model="form.priority" :options="priorityOptions" variant="cards" aria-label="Öncelik" />
      </OzField>
      <OzField label="Not" class="span-2">
        <OzTextarea v-model="form.note" :maxlength="300" placeholder="Kumaş, beden dağılımı vb." />
      </OzField>
      <OzSwitch v-model="form.notify" label="İş ortağına bildirim gönder" class="span-2" />
    </form>
    <template #footer>
      <OzButton :disabled="saving" @click="open = false">Vazgeç</OzButton>
      <OzButton variant="primary" type="submit" :form="formId" :disabled="saving">
        {{ saving ? 'Kaydediliyor…' : 'İş Emrini Oluştur' }}
      </OzButton>
    </template>
  </OzModal>
</template>

<style scoped>
.wo-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px 16px; }
.span-2 { grid-column: 1 / -1; }
@media (max-width: 520px) { .wo-grid { grid-template-columns: 1fr; } }
</style>
