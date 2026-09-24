<script setup>
import { computed } from 'vue'
import { ozIcons } from './icons'

// Lucide ikonu. name: kayıttaki ad ('bell', 'chevron-down' …) veya doğrudan bir Lucide bileşeni.
const props = defineProps({
  name: { type: [String, Object, Function], required: true },
  size: { type: [Number, String], default: 16 },
  strokeWidth: { type: [Number, String], default: 2 },
})

const component = computed(() => {
  if (typeof props.name !== 'string') return props.name
  const found = ozIcons[props.name]
  if (!found && import.meta.env.DEV) console.warn(`[OzIcon] "${props.name}" kayıtlı değil (components/oz/icons.js)`)
  return found ?? null
})
</script>

<template>
  <component
    :is="component"
    v-if="component"
    class="oz-icon"
    :size="size"
    :stroke-width="strokeWidth"
    aria-hidden="true"
  />
</template>

<style scoped>
.oz-icon { display: block; flex-shrink: 0; }
</style>
