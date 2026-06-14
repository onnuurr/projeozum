<script setup>
import { useForm, router } from '@inertiajs/vue3'
import AtelierNav from '../Components/AtelierNav.vue'

const props = defineProps({ boms: Array, products: Array, materials: Array })

const form = useForm({ product_id: '', name: 'Varsayılan Reçete', lines: [{ material_id: '', quantity_per_unit: 1, waste_pct: 0 }] })

function addLine() { form.lines.push({ material_id: '', quantity_per_unit: 1, waste_pct: 0 }) }
function removeLine(i) { form.lines.splice(i, 1) }
function submit() {
  form.post('/atelier/boms', { onSuccess: () => { form.reset(); form.lines = [{ material_id: '', quantity_per_unit: 1, waste_pct: 0 }] } })
}
function remove(b) { if (confirm('Reçete silinsin mi?')) router.delete(`/atelier/boms/${b.id}`) }
</script>

<template>
  <div>
    <AtelierNav />
    <div class="p-6 space-y-6">
    <h1 class="text-xl font-semibold">Reçeteler (BOM)</h1>

    <form @submit.prevent="submit" class="bg-white p-4 rounded shadow-sm space-y-3">
      <div class="flex gap-3">
        <label class="flex flex-col text-sm flex-1">Ürün
          <select v-model="form.product_id" class="border rounded px-2 py-1">
            <option value="">Seçin…</option>
            <option v-for="p in products" :key="p.id" :value="p.id">{{ p.name }} ({{ p.sku }})</option>
          </select>
        </label>
        <label class="flex flex-col text-sm flex-1">Reçete adı<input v-model="form.name" class="border rounded px-2 py-1" /></label>
      </div>

      <div v-for="(line, i) in form.lines" :key="i" class="flex gap-2 items-end">
        <label class="flex flex-col text-sm flex-1">Malzeme
          <select v-model="line.material_id" class="border rounded px-2 py-1">
            <option value="">Seçin…</option>
            <option v-for="m in materials" :key="m.id" :value="m.id">{{ m.name }} ({{ m.unit }})</option>
          </select>
        </label>
        <label class="flex flex-col text-sm w-32">Birim/adet<input v-model="line.quantity_per_unit" type="number" step="0.0001" class="border rounded px-2 py-1" /></label>
        <label class="flex flex-col text-sm w-24">Fire %<input v-model="line.waste_pct" type="number" step="0.01" class="border rounded px-2 py-1" /></label>
        <button type="button" @click="removeLine(i)" class="text-red-600 pb-1.5">Sil</button>
      </div>

      <div class="flex gap-2">
        <button type="button" @click="addLine" class="px-3 py-1 rounded border">+ Satır</button>
        <button class="bg-indigo-600 text-white px-4 py-1.5 rounded" :disabled="form.processing">Kaydet</button>
      </div>
    </form>

    <div v-for="b in boms" :key="b.id" class="bg-white p-4 rounded shadow-sm">
      <div class="flex justify-between">
        <h3 class="font-medium">{{ b.productName }} — {{ b.name }}</h3>
        <button @click="remove(b)" class="text-red-600 text-sm">Sil</button>
      </div>
      <ul class="text-sm mt-2 space-y-1">
        <li v-for="(l, i) in b.lines" :key="i">{{ l.materialName }}: {{ l.quantityPerUnit }} {{ l.unit }} (fire %{{ l.wastePct }})</li>
      </ul>
    </div>
  </div>
  </div>
</template>
