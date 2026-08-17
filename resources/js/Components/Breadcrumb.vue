<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import UiIcon from '@/Components/UiIcon.vue'

const props = defineProps({
  items: { type: Array, required: true },
  variant: {
    type: String,
    default: 'default',
    validator: (v) => ['default', 'pills', 'dots', 'arrow'].includes(v),
  },
  // 0 = sınırsız (varsayılan). Verilirse baş + "…" + kuyruk şeklinde daraltılır.
  maxVisible: { type: Number, default: 0 },
})

const displayItems = computed(() => {
  if (!props.maxVisible || props.items.length <= props.maxVisible) {
    return props.items
  }

  const tailCount = Math.max(1, props.maxVisible - 1)
  const tail = props.items.slice(-tailCount)

  return [props.items[0], { label: '…', collapsed: true }, ...tail]
})

const separatorIcon = {
  default: 'chevron_right',
  pills: 'chevron_right',
  arrow: 'arrow_forward',
  dots: null,
}
</script>

<template>
  <nav class="flex items-center flex-wrap gap-1.5 mb-2" aria-label="Sayfa konumu">
    <template v-for="(item, index) in displayItems" :key="index">
      <!-- Daraltılmış ara öğeler ("…") -->
      <span v-if="item.collapsed" class="text-xs text-muted px-0.5 select-none">
        {{ item.label }}
      </span>

      <!-- Tıklanabilir ara öğeler -->
      <Link
        v-else-if="item.to && index < displayItems.length - 1"
        :href="item.to"
        :class="[
          'flex items-center gap-1 transition-colors',
          variant === 'pills'
            ? 'px-2 py-0.5 rounded-full text-[0.6875rem] bg-canvas text-muted hover:bg-line'
            : variant === 'dots'
              ? 'text-[0.6875rem] text-muted hover:text-primary'
              : 'text-xs text-muted hover:text-primary',
        ]"
      >
        <UiIcon v-if="item.icon" :name="item.icon" :size="14" />
        {{ item.label }}
      </Link>

      <!-- Tıklanamaz ara öğeler -->
      <span
        v-else-if="index < displayItems.length - 1"
        :class="[
          'flex items-center gap-1',
          variant === 'pills'
            ? 'px-2 py-0.5 rounded-full text-[0.6875rem] bg-canvas text-muted'
            : variant === 'dots'
              ? 'text-[0.6875rem] text-muted'
              : 'text-xs text-muted',
        ]"
      >
        <UiIcon v-if="item.icon" :name="item.icon" :size="14" />
        {{ item.label }}
      </span>

      <!-- Aktif (son) öğe -->
      <span
        v-else
        aria-current="page"
        :class="[
          'flex items-center gap-1 font-semibold',
          variant === 'pills'
            ? 'px-2 py-0.5 rounded-full text-[0.6875rem] bg-primary text-white'
            : variant === 'dots'
              ? 'text-[0.6875rem] text-primary'
              : 'text-xs text-primary',
        ]"
      >
        <UiIcon v-if="item.icon" :name="item.icon" :size="14" />
        {{ item.label }}
      </span>

      <!-- Ayraç -->
      <span v-if="index < displayItems.length - 1" class="text-muted flex items-center">
        <span v-if="variant === 'dots'" class="text-[0.8125rem] leading-none">·</span>
        <UiIcon v-else :name="separatorIcon[variant]" :size="12" />
      </span>
    </template>
  </nav>
</template>
