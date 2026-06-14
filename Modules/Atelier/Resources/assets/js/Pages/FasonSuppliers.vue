<script setup>
import { ref } from 'vue'
import { useForm, router } from '@inertiajs/vue3'
import AtelierNav from '../Components/AtelierNav.vue'

const props = defineProps({ suppliers: Array })
const form = useForm({ id: null, name: '', contact_name: '', phone: '', email: '', address: '', tax_no: '', notes: '', is_active: true })
const editing = ref(false)

function edit(s) {
  editing.value = true
  Object.assign(form, { id: s.id, name: s.name, contact_name: s.contactName, phone: s.phone, email: s.email, address: s.address, tax_no: s.taxNo, notes: s.notes, is_active: s.isActive })
}
function reset() { editing.value = false; form.reset(); form.id = null }
function submit() {
  editing.value ? form.put(`/atelier/fason-suppliers/${form.id}`, { onSuccess: reset }) : form.post('/atelier/fason-suppliers', { onSuccess: reset })
}
function remove(s) { if (confirm('Fasoncu silinsin mi?')) router.delete(`/atelier/fason-suppliers/${s.id}`) }
</script>

<template>
  <div>
    <AtelierNav />
    <div class="p-6 space-y-6">
    <h1 class="text-xl font-semibold">Fasoncular</h1>
    <form @submit.prevent="submit" class="grid grid-cols-4 gap-2 items-end bg-white p-4 rounded shadow-sm">
      <label class="flex flex-col text-sm">Ad<input v-model="form.name" class="border rounded px-2 py-1" /></label>
      <label class="flex flex-col text-sm">Yetkili<input v-model="form.contact_name" class="border rounded px-2 py-1" /></label>
      <label class="flex flex-col text-sm">Telefon<input v-model="form.phone" class="border rounded px-2 py-1" /></label>
      <label class="flex flex-col text-sm">E-posta<input v-model="form.email" class="border rounded px-2 py-1" /></label>
      <label class="flex flex-col text-sm col-span-2">Adres<input v-model="form.address" class="border rounded px-2 py-1" /></label>
      <label class="flex flex-col text-sm">Vergi No<input v-model="form.tax_no" class="border rounded px-2 py-1" /></label>
      <div class="flex gap-2 items-end">
        <button class="bg-indigo-600 text-white px-4 py-1.5 rounded">{{ editing ? 'Güncelle' : 'Ekle' }}</button>
        <button v-if="editing" type="button" @click="reset" class="px-4 py-1.5 rounded border">Vazgeç</button>
      </div>
    </form>
    <table class="w-full text-sm bg-white rounded shadow-sm">
      <thead class="text-left border-b"><tr><th class="p-2">Ad</th><th class="p-2">Yetkili</th><th class="p-2">Telefon</th><th class="p-2">E-posta</th><th class="p-2"></th></tr></thead>
      <tbody>
        <tr v-for="s in suppliers" :key="s.id" class="border-b">
          <td class="p-2">{{ s.name }}</td><td class="p-2">{{ s.contactName }}</td><td class="p-2">{{ s.phone }}</td><td class="p-2">{{ s.email }}</td>
          <td class="p-2 text-right space-x-2"><button @click="edit(s)" class="text-indigo-600">Düzenle</button><button @click="remove(s)" class="text-red-600">Sil</button></td>
        </tr>
      </tbody>
    </table>
  </div>
  </div>
</template>
