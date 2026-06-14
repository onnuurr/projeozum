<script setup>
import { ref } from 'vue'
import { useForm, router } from '@inertiajs/vue3'

const props = defineProps({ operations: Array })
const form = useForm({ id: null, code: '', name: '', default_location: 'in_house', default_unit_cost: 0, sort_order: 0 })
const editing = ref(false)

function edit(o) {
  editing.value = true
  form.id = o.id; form.code = o.code; form.name = o.name
  form.default_location = o.defaultLocation; form.default_unit_cost = o.defaultUnitCost; form.sort_order = o.sortOrder
}
function reset() { editing.value = false; form.reset(); form.id = null }
function submit() {
  editing.value ? form.put(`/atelier/operations/${form.id}`, { onSuccess: reset }) : form.post('/atelier/operations', { onSuccess: reset })
}
function remove(o) { if (confirm('Operasyon silinsin mi?')) router.delete(`/atelier/operations/${o.id}`) }
</script>

<template>
  <div class="p-6 space-y-6">
    <h1 class="text-xl font-semibold">Operasyonlar</h1>
    <form @submit.prevent="submit" class="grid grid-cols-6 gap-2 items-end bg-white p-4 rounded shadow-sm">
      <label class="flex flex-col text-sm">Kod<input v-model="form.code" class="border rounded px-2 py-1" /></label>
      <label class="flex flex-col text-sm col-span-2">Ad<input v-model="form.name" class="border rounded px-2 py-1" /></label>
      <label class="flex flex-col text-sm">Varsayılan yer
        <select v-model="form.default_location" class="border rounded px-2 py-1">
          <option value="in_house">İç atölye</option><option value="fason">Fason</option>
        </select>
      </label>
      <label class="flex flex-col text-sm">Birim işçilik<input v-model="form.default_unit_cost" type="number" step="0.01" class="border rounded px-2 py-1" /></label>
      <label class="flex flex-col text-sm">Sıra<input v-model="form.sort_order" type="number" class="border rounded px-2 py-1" /></label>
      <div class="col-span-6 flex gap-2">
        <button class="bg-indigo-600 text-white px-4 py-1.5 rounded">{{ editing ? 'Güncelle' : 'Ekle' }}</button>
        <button v-if="editing" type="button" @click="reset" class="px-4 py-1.5 rounded border">Vazgeç</button>
      </div>
    </form>
    <table class="w-full text-sm bg-white rounded shadow-sm">
      <thead class="text-left border-b"><tr><th class="p-2">Kod</th><th class="p-2">Ad</th><th class="p-2">Yer</th><th class="p-2 text-right">İşçilik</th><th class="p-2 text-right">Sıra</th><th class="p-2"></th></tr></thead>
      <tbody>
        <tr v-for="o in operations" :key="o.id" class="border-b">
          <td class="p-2">{{ o.code }}</td><td class="p-2">{{ o.name }}</td>
          <td class="p-2">{{ o.defaultLocation === 'fason' ? 'Fason' : 'İç' }}</td>
          <td class="p-2 text-right">{{ o.defaultUnitCost }}</td><td class="p-2 text-right">{{ o.sortOrder }}</td>
          <td class="p-2 text-right space-x-2"><button @click="edit(o)" class="text-indigo-600">Düzenle</button><button @click="remove(o)" class="text-red-600">Sil</button></td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
