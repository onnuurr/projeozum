<script setup>
import { Link, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

const tabs = [
  { label: 'Panel', href: '/atelier' },
  { label: 'İş Emirleri', href: '/atelier/production-orders' },
  { label: 'Hammaddeler', href: '/atelier/materials' },
  { label: 'Reçeteler', href: '/atelier/boms' },
  { label: 'Operasyonlar', href: '/atelier/operations' },
  { label: 'Fasoncular', href: '/atelier/fason-suppliers' },
]
const current = computed(() => usePage().url)
function active(href) {
  return href === '/atelier' ? current.value === '/atelier' : current.value.startsWith(href)
}
</script>

<template>
  <nav class="flex gap-1 border-b bg-white px-4">
    <Link v-for="t in tabs" :key="t.href" :href="t.href"
      class="px-3 py-2 text-sm border-b-2"
      :class="active(t.href) ? 'border-indigo-600 text-indigo-600 font-medium' : 'border-transparent text-gray-600 hover:text-gray-900'">
      {{ t.label }}
    </Link>
  </nav>
</template>
