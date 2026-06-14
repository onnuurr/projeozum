<script setup>
import { ref } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import AtelierNav from '../Components/AtelierNav.vue'

const props = defineProps({ materials: Array })

const form = useForm({ id: null, code: '', name: '', type: 'kumas', unit: 'metre', unit_cost: 0, is_active: true })
const editing = ref(false)

function edit(m) {
  editing.value = true
  form.id = m.id; form.code = m.code; form.name = m.name; form.type = m.type
  form.unit = m.unit; form.unit_cost = m.unitCost; form.is_active = m.isActive
}
function reset() {
  editing.value = false
  form.reset(); form.id = null
}
function submit() {
  if (editing.value) {
    form.put(`/atelier/materials/${form.id}`, { onSuccess: reset })
  } else {
    form.post('/atelier/materials', { onSuccess: reset })
  }
}
function remove(m) {
  if (confirm(`${m.name} silinsin mi?`)) router.delete(`/atelier/materials/${m.id}`)
}

const move = useForm({ material_id: null, type: 'in', quantity: 0, reason: 'purchase', note: '' })
function openMove(m) { move.material_id = m.id; move.type = 'in'; move.quantity = 0; move.reason = 'purchase' }
function submitMove() {
  move.post('/atelier/materials/movement', { onSuccess: () => { move.reset(); move.material_id = null } })
}
</script>

<template>
  <div>
    <AtelierNav />
    <div class="p-6 space-y-6">
    <h1 class="text-xl font-semibold">Hammaddeler</h1>

    <form @submit.prevent="submit" class="grid grid-cols-6 gap-2 items-end bg-white p-4 rounded shadow-sm">
      <label class="flex flex-col text-sm">Kod<input v-model="form.code" class="border rounded px-2 py-1" /></label>
      <label class="flex flex-col text-sm col-span-2">Ad<input v-model="form.name" class="border rounded px-2 py-1" /></label>
      <label class="flex flex-col text-sm">Tür
        <select v-model="form.type" class="border rounded px-2 py-1">
          <option value="kumas">Kumaş</option><option value="aksesuar">Aksesuar</option><option value="etiket">Etiket</option>
        </select>
      </label>
      <label class="flex flex-col text-sm">Birim
        <select v-model="form.unit" class="border rounded px-2 py-1">
          <option value="metre">Metre</option><option value="adet">Adet</option><option value="kg">Kg</option>
        </select>
      </label>
      <label class="flex flex-col text-sm">Birim Maliyet<input v-model="form.unit_cost" type="number" step="0.01" class="border rounded px-2 py-1" /></label>
      <div class="col-span-6 flex gap-2">
        <button class="bg-indigo-600 text-white px-4 py-1.5 rounded" :disabled="form.processing">{{ editing ? 'Güncelle' : 'Ekle' }}</button>
        <button v-if="editing" type="button" @click="reset" class="px-4 py-1.5 rounded border">Vazgeç</button>
      </div>
    </form>

    <table class="w-full text-sm bg-white rounded shadow-sm">
      <thead class="text-left border-b">
        <tr><th class="p-2">Kod</th><th class="p-2">Ad</th><th class="p-2">Tür</th><th class="p-2">Birim</th><th class="p-2 text-right">Stok</th><th class="p-2 text-right">Maliyet</th><th class="p-2"></th></tr>
      </thead>
      <tbody>
        <tr v-for="m in materials" :key="m.id" class="border-b">
          <td class="p-2">{{ m.code }}</td>
          <td class="p-2">{{ m.name }}</td>
          <td class="p-2">{{ m.type }}</td>
          <td class="p-2">{{ m.unit }}</td>
          <td class="p-2 text-right">{{ m.currentStock }}</td>
          <td class="p-2 text-right">{{ m.unitCost }}</td>
          <td class="p-2 text-right space-x-2">
            <button @click="openMove(m)" class="text-emerald-600">Hareket</button>
            <button @click="edit(m)" class="text-indigo-600">Düzenle</button>
            <button @click="remove(m)" class="text-red-600">Sil</button>
          </td>
        </tr>
      </tbody>
    </table>

    <div v-if="move.material_id" class="fixed inset-0 bg-black/40 flex items-center justify-center" @click.self="move.material_id = null">
      <form @submit.prevent="submitMove" class="bg-white p-6 rounded space-y-3 w-80">
        <h2 class="font-semibold">Stok Hareketi</h2>
        <label class="flex flex-col text-sm">Tip
          <select v-model="move.type" class="border rounded px-2 py-1">
            <option value="in">Giriş</option><option value="out">Çıkış</option><option value="adjust">Düzeltme</option>
          </select>
        </label>
        <label class="flex flex-col text-sm">Miktar<input v-model="move.quantity" type="number" step="0.001" class="border rounded px-2 py-1" /></label>
        <label class="flex flex-col text-sm">Sebep
          <select v-model="move.reason" class="border rounded px-2 py-1">
            <option value="purchase">Satın alma</option><option value="consume">Tüketim</option><option value="scrap">Fire</option><option value="correction">Düzeltme</option>
          </select>
        </label>
        <div v-if="move.errors.quantity" class="text-red-600 text-sm">{{ move.errors.quantity }}</div>
        <div class="flex gap-2">
          <button class="bg-indigo-600 text-white px-4 py-1.5 rounded">Kaydet</button>
          <button type="button" @click="move.material_id = null" class="px-4 py-1.5 rounded border">Kapat</button>
        </div>
      </form>
    </div>
  </div>
  </div>
</template>
