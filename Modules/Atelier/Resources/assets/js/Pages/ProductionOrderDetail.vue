<script setup>
import { reactive } from 'vue'
import { router } from '@inertiajs/vue3'
import AtelierNav from '../Components/AtelierNav.vue'

const props = defineProps({ order: Object, fasonSuppliers: Array })

const STATUS = { draft: 'Taslak', planned: 'Planlandı', in_progress: 'Üretimde', completed: 'Tamamlandı', cancelled: 'İptal' }
const STEP_STATUS = { pending: 'Bekliyor', in_progress: 'Devam', done: 'Bitti' }

const stepForms = reactive(Object.fromEntries(props.order.steps.map(s => [s.id, {
  status: s.status, input_qty: s.inputQty, output_qty: s.outputQty, scrap_qty: s.scrapQty,
  fason_supplier_id: null, unit_cost: s.unitCost, note: '',
}])))

const itemForms = reactive(Object.fromEntries(props.order.items.map(i => [i.id, {
  produced_qty: i.producedQty, scrap_qty: i.scrapQty,
}])))

function saveStep(id) {
  router.put(`/atelier/production-orders/${props.order.id}/steps/${id}`, stepForms[id], { preserveScroll: true })
}
function saveItems() {
  const items = props.order.items.map(i => ({ id: i.id, produced_qty: itemForms[i.id].produced_qty, scrap_qty: itemForms[i.id].scrap_qty }))
  router.put(`/atelier/production-orders/${props.order.id}/items`, { items }, { preserveScroll: true })
}
function plan() { router.post(`/atelier/production-orders/${props.order.id}/plan`, {}, { preserveScroll: true }) }
function complete() { router.post(`/atelier/production-orders/${props.order.id}/complete`, {}, { preserveScroll: true }) }
function cancel() { if (confirm('İptal edilsin mi?')) router.post(`/atelier/production-orders/${props.order.id}/cancel`, {}, { preserveScroll: true }) }
</script>

<template>
  <div>
    <AtelierNav />
    <div class="p-6 space-y-6">
    <div class="flex justify-between items-center">
      <div>
        <h1 class="text-xl font-semibold">{{ order.code }} — {{ order.productName }}</h1>
        <p class="text-sm text-gray-500">Durum: {{ STATUS[order.status] }} • Depo: {{ order.warehouse }}</p>
      </div>
      <div class="flex gap-2">
        <button v-if="order.status === 'draft'" @click="plan" class="bg-amber-600 text-white px-4 py-1.5 rounded">Planla (hammadde düş)</button>
        <button v-if="['planned','in_progress'].includes(order.status)" @click="complete" class="bg-emerald-600 text-white px-4 py-1.5 rounded">Tamamla → Stoğa al</button>
        <button v-if="!['completed','cancelled'].includes(order.status)" @click="cancel" class="px-4 py-1.5 rounded border text-red-600">İptal</button>
      </div>
    </div>

    <div class="grid grid-cols-5 gap-3 text-sm">
      <div class="bg-white p-3 rounded shadow-sm">Malzeme<br><b>{{ order.materialCost }} ₺</b></div>
      <div class="bg-white p-3 rounded shadow-sm">Fason<br><b>{{ order.fasonCost }} ₺</b></div>
      <div class="bg-white p-3 rounded shadow-sm">İşçilik<br><b>{{ order.laborCost }} ₺</b></div>
      <div class="bg-white p-3 rounded shadow-sm">Toplam<br><b>{{ order.totalCost }} ₺</b></div>
      <div class="bg-white p-3 rounded shadow-sm">Birim<br><b>{{ order.unitCost }} ₺</b></div>
    </div>

    <div class="bg-white p-4 rounded shadow-sm">
      <h2 class="font-medium mb-2">Varyant üretim</h2>
      <div v-for="i in order.items" :key="i.id" class="flex gap-3 items-center text-sm mb-1">
        <span class="w-40">{{ i.size }} / {{ i.colorName }}</span>
        <span class="text-gray-500">Plan: {{ i.plannedQty }}</span>
        <label>Üretilen <input v-model="itemForms[i.id].produced_qty" type="number" class="border rounded px-2 py-1 w-24" /></label>
        <label>Fire <input v-model="itemForms[i.id].scrap_qty" type="number" class="border rounded px-2 py-1 w-20" /></label>
      </div>
      <button @click="saveItems" class="mt-2 px-3 py-1 rounded border text-sm">Üretim miktarlarını kaydet</button>
    </div>

    <div class="bg-white p-4 rounded shadow-sm">
      <h2 class="font-medium mb-2">Rota / Aşamalar</h2>
      <div v-for="s in order.steps" :key="s.id" class="border-b py-2 text-sm flex flex-wrap gap-2 items-end">
        <span class="w-44">{{ s.sequence }}. {{ s.operationName }} ({{ s.locationType === 'fason' ? 'Fason' : 'İç' }})</span>
        <select v-model="stepForms[s.id].status" class="border rounded px-2 py-1">
          <option value="pending">Bekliyor</option><option value="in_progress">Devam</option><option value="done">Bitti</option>
        </select>
        <label>Giren <input v-model="stepForms[s.id].input_qty" type="number" class="border rounded px-2 py-1 w-20" /></label>
        <label>Çıkan <input v-model="stepForms[s.id].output_qty" type="number" class="border rounded px-2 py-1 w-20" /></label>
        <label>Fire <input v-model="stepForms[s.id].scrap_qty" type="number" class="border rounded px-2 py-1 w-16" /></label>
        <label>Birim ₺ <input v-model="stepForms[s.id].unit_cost" type="number" step="0.01" class="border rounded px-2 py-1 w-20" /></label>
        <select v-if="s.locationType === 'fason'" v-model="stepForms[s.id].fason_supplier_id" class="border rounded px-2 py-1">
          <option :value="null">Fasoncu…</option>
          <option v-for="f in fasonSuppliers" :key="f.id" :value="f.id">{{ f.name }}</option>
        </select>
        <button @click="saveStep(s.id)" class="px-3 py-1 rounded border">Kaydet</button>
      </div>
    </div>
  </div>
  </div>
</template>
