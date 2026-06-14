<script setup>
import { router } from '@inertiajs/vue3'
import AtelierNav from '../Components/AtelierNav.vue'

const props = defineProps({ activeOrders: { type: Array, default: () => [] }, fasonPending: { type: Number, default: 0 }, lowStock: { type: Number, default: 0 } })
const STATUS = { planned: 'Planlandı', in_progress: 'Üretimde' }
</script>

<template>
  <div>
    <AtelierNav />
    <div class="p-6 space-y-6">
      <h1 class="text-xl font-semibold">Atölye Paneli</h1>

      <div class="grid grid-cols-3 gap-3 text-sm">
        <div class="bg-white p-4 rounded shadow-sm">Aktif iş emri<br><b class="text-2xl">{{ activeOrders.length }}</b></div>
        <div class="bg-white p-4 rounded shadow-sm">Fasonda bekleyen adım<br><b class="text-2xl">{{ fasonPending }}</b></div>
        <div class="bg-white p-4 rounded shadow-sm">Tükenen hammadde<br><b class="text-2xl text-red-600">{{ lowStock }}</b></div>
      </div>

      <div class="bg-white p-4 rounded shadow-sm">
        <h2 class="font-medium mb-2">Devam eden iş emirleri</h2>
        <table class="w-full text-sm">
          <thead class="text-left border-b"><tr><th class="p-2">Kod</th><th class="p-2">Ürün</th><th class="p-2">Durum</th><th class="p-2">Termin</th><th class="p-2"></th></tr></thead>
          <tbody>
            <tr v-for="o in activeOrders" :key="o.id" class="border-b" :class="o.isLate ? 'bg-red-50' : ''">
              <td class="p-2">{{ o.code }}</td><td class="p-2">{{ o.productName }}</td>
              <td class="p-2">{{ STATUS[o.status] }}</td>
              <td class="p-2">{{ o.dueDate || '—' }} <span v-if="o.isLate" class="text-red-600">(gecikti)</span></td>
              <td class="p-2 text-right"><button @click="router.get(`/atelier/production-orders/${o.id}`)" class="text-indigo-600">Detay</button></td>
            </tr>
            <tr v-if="!activeOrders.length"><td colspan="5" class="p-4 text-center text-gray-400">Aktif iş emri yok.</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>
