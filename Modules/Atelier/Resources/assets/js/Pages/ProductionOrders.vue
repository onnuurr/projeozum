<script setup>
import { ref } from 'vue'
import { useForm, router } from '@inertiajs/vue3'
import axios from 'axios'
import AtelierNav from '../Components/AtelierNav.vue'

const props = defineProps({ orders: Array, products: Array, warehouses: Array, operations: Array })

const STATUS = {
  draft: 'Taslak', planned: 'Planlandı', in_progress: 'Üretimde', completed: 'Tamamlandı', cancelled: 'İptal',
}

const showWizard = ref(false)
const variants = ref([])
const requirements = ref([])

const form = useForm({
  product_id: '', warehouse_id: '', planned_qty: 0, due_date: '', notes: '',
  items: [], steps: [],
})

async function onProductOrQty() {
  if (!form.product_id || !form.planned_qty) return
  const { data } = await axios.get('/atelier/production-orders/plan-preview', {
    params: { product_id: form.product_id, planned_qty: form.planned_qty },
  })
  variants.value = data.variants
  requirements.value = data.requirements
  // varyant satırlarını eşit dağıtma yapmadan 0 ile başlat
  form.items = data.variants.map(v => ({ product_variant_id: v.id, planned_qty: 0, label: `${v.size} / ${v.colorName}` }))
}

function addStep() {
  form.steps.push({ operation_id: '', sequence: form.steps.length + 1, location_type: 'in_house', fason_supplier_id: null, unit_cost: 0 })
}
function removeStep(i) { form.steps.splice(i, 1); form.steps.forEach((s, idx) => s.sequence = idx + 1) }

function onOperationChange(step) {
  const op = props.operations.find(o => o.id === Number(step.operation_id))
  if (op) { step.location_type = op.default_location; step.unit_cost = op.default_unit_cost }
}

function submit() {
  form.transform(d => ({
    ...d,
    items: d.items.map(({ product_variant_id, planned_qty }) => ({ product_variant_id, planned_qty })),
  })).post('/atelier/production-orders', {
    onSuccess: () => { showWizard.value = false; form.reset(); variants.value = []; requirements.value = [] },
  })
}
</script>

<template>
  <div>
    <AtelierNav />
    <div class="p-6 space-y-6">
    <div class="flex justify-between items-center">
      <h1 class="text-xl font-semibold">İş Emirleri</h1>
      <button @click="showWizard = true" class="bg-indigo-600 text-white px-4 py-1.5 rounded">+ Yeni İş Emri</button>
    </div>

    <table class="w-full text-sm bg-white rounded shadow-sm">
      <thead class="text-left border-b"><tr><th class="p-2">Kod</th><th class="p-2">Ürün</th><th class="p-2">Durum</th><th class="p-2 text-right">Planlanan</th><th class="p-2 text-right">Üretilen</th><th class="p-2">Termin</th><th class="p-2 text-right">Maliyet</th><th class="p-2"></th></tr></thead>
      <tbody>
        <tr v-for="o in orders" :key="o.id" class="border-b">
          <td class="p-2">{{ o.code }}</td>
          <td class="p-2">{{ o.productName }}</td>
          <td class="p-2">{{ STATUS[o.status] }}</td>
          <td class="p-2 text-right">{{ o.plannedQty }}</td>
          <td class="p-2 text-right">{{ o.producedQty }}</td>
          <td class="p-2">{{ o.dueDate || '—' }}</td>
          <td class="p-2 text-right">{{ o.totalCost }}</td>
          <td class="p-2 text-right"><button @click="router.get(`/atelier/production-orders/${o.id}`)" class="text-indigo-600">Detay</button></td>
        </tr>
      </tbody>
    </table>

    <div v-if="showWizard" class="fixed inset-0 bg-black/40 flex items-start justify-center overflow-auto py-10" @click.self="showWizard = false">
      <form @submit.prevent="submit" class="bg-white p-6 rounded space-y-4 w-[900px]">
        <h2 class="font-semibold text-lg">Yeni İş Emri</h2>

        <div class="grid grid-cols-4 gap-3">
          <label class="flex flex-col text-sm">Ürün
            <select v-model="form.product_id" @change="onProductOrQty" class="border rounded px-2 py-1">
              <option value="">Seçin…</option>
              <option v-for="p in products" :key="p.id" :value="p.id">{{ p.name }}</option>
            </select>
          </label>
          <label class="flex flex-col text-sm">Adet<input v-model="form.planned_qty" @change="onProductOrQty" type="number" class="border rounded px-2 py-1" /></label>
          <label class="flex flex-col text-sm">Depo
            <select v-model="form.warehouse_id" class="border rounded px-2 py-1">
              <option value="">Seçin…</option>
              <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
            </select>
          </label>
          <label class="flex flex-col text-sm">Termin<input v-model="form.due_date" type="date" class="border rounded px-2 py-1" /></label>
        </div>

        <div v-if="form.items.length">
          <h3 class="font-medium text-sm mb-1">Varyant adetleri</h3>
          <div v-for="(it, i) in form.items" :key="i" class="flex gap-2 items-center text-sm mb-1">
            <span class="w-40">{{ it.label }}</span>
            <input v-model="it.planned_qty" type="number" class="border rounded px-2 py-1 w-28" />
          </div>
        </div>

        <div v-if="requirements.length" class="bg-amber-50 p-3 rounded text-sm">
          <h3 class="font-medium mb-1">Malzeme gereksinimi (önizleme)</h3>
          <div v-for="(r, i) in requirements" :key="i">{{ r.material_name }}: {{ r.required_qty }} {{ r.unit }} (≈{{ r.line_cost }} ₺)</div>
        </div>

        <div>
          <h3 class="font-medium text-sm mb-1">Rota</h3>
          <div v-for="(s, i) in form.steps" :key="i" class="flex gap-2 items-end text-sm mb-1">
            <span class="w-6">{{ s.sequence }}.</span>
            <select v-model="s.operation_id" @change="onOperationChange(s)" class="border rounded px-2 py-1">
              <option value="">Operasyon…</option>
              <option v-for="op in operations" :key="op.id" :value="op.id">{{ op.name }}</option>
            </select>
            <select v-model="s.location_type" class="border rounded px-2 py-1">
              <option value="in_house">İç</option><option value="fason">Fason</option>
            </select>
            <input v-model="s.unit_cost" type="number" step="0.01" class="border rounded px-2 py-1 w-24" placeholder="Birim ₺" />
            <button type="button" @click="removeStep(i)" class="text-red-600">Sil</button>
          </div>
          <button type="button" @click="addStep" class="px-3 py-1 rounded border text-sm">+ Adım</button>
        </div>

        <div class="flex gap-2">
          <button class="bg-indigo-600 text-white px-4 py-1.5 rounded" :disabled="form.processing">Oluştur</button>
          <button type="button" @click="showWizard = false" class="px-4 py-1.5 rounded border">Kapat</button>
        </div>
      </form>
    </div>
  </div>
  </div>
</template>
