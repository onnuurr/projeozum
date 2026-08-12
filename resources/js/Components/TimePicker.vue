<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { Clock, ChevronDown } from 'lucide-vue-next'
import Button from './Button.vue'

const props = defineProps({
  modelValue: {
    type: [String, null],
    default: null,
  },
  label: String,
  placeholder: {
    type: String,
    default: 'Saat seçin...',
  },
  minuteStep: {
    type: Number,
    default: 5,
  },
  disabled: Boolean,
  error: String,
})

const emit = defineEmits(['update:modelValue'])

const isOpen = ref(false)
const containerRef = ref(null)

const parsedValue = computed(() => {
  if (!props.modelValue) return null
  const [h, m] = props.modelValue.split(':').map(Number)
  if (h == null || Number.isNaN(h) || Number.isNaN(m)) return null
  return { hour: h, minute: m }
})

const displayText = computed(() => props.modelValue || '')

const hours = computed(() => Array.from({ length: 24 }, (_, i) => String(i).padStart(2, '0')))

const minutes = computed(() => {
  const step = Math.max(1, Math.min(60, props.minuteStep))
  const out = []
  for (let m = 0; m < 60; m += step) out.push(String(m).padStart(2, '0'))
  return out
})

function selectHour(hour) {
  const current = parsedValue.value || { hour: 0, minute: 0 }
  emit('update:modelValue', `${hour}:${String(current.minute).padStart(2, '0')}`)
}

function selectMinute(minute) {
  const current = parsedValue.value || { hour: 0, minute: 0 }
  emit('update:modelValue', `${String(current.hour).padStart(2, '0')}:${minute}`)
}

function toggle() {
  if (!props.disabled) isOpen.value = !isOpen.value
}

function onClickOutside(e) {
  if (containerRef.value && !containerRef.value.contains(e.target)) {
    isOpen.value = false
  }
}

onMounted(() => document.addEventListener('click', onClickOutside))
onBeforeUnmount(() => document.removeEventListener('click', onClickOutside))
</script>

<template>
  <div class="w-full">
    <div class="flex items-center justify-between mb-1">
      <label
        v-if="label"
        class="block text-2xs text-muted font-medium"
      >
        {{ label }}
      </label>
    </div>
    <div ref="containerRef" class="relative w-full">
      <button
        type="button"
        :disabled="disabled"
        :class="[
          'w-full flex items-center justify-between bg-white border rounded-lg py-1.5 pl-2.5 text-xs text-left transition-[border-color,box-shadow] duration-150 motion-reduce:transition-none max-md:min-h-[44px] max-md:py-2.5',
          'focus:outline-none focus:ring-2',
          error ? 'border-danger focus:border-danger focus:ring-danger/20'
            : parsedValue ? 'border-success'
              : 'border-line focus:border-primary focus:ring-primary/20',
          disabled ? 'opacity-50 cursor-not-allowed bg-canvas' : 'cursor-pointer',
        ]"
        @click="toggle"
      >
        <span class="flex items-center gap-2 min-w-0">
          <span class="w-6 h-6 rounded-md bg-canvas flex items-center justify-center shrink-0">
            <Clock class="w-3.5 h-3.5 text-muted" />
          </span>
          <span :class="displayText ? 'text-ink' : 'text-muted/70 truncate'">
            {{ displayText || placeholder }}
          </span>
        </span>
        <ChevronDown
          class="w-3.5 h-3.5 text-muted transition-transform duration-200 shrink-0"
          :class="isOpen ? 'rotate-180' : ''"
        />
      </button>

      <Transition
        enter-active-class="transition duration-150 ease-out motion-reduce:transition-none"
        enter-from-class="opacity-0 -translate-y-1"
        enter-to-class="opacity-100 translate-y-0"
        leave-active-class="transition duration-150 ease-in motion-reduce:transition-none"
        leave-from-class="opacity-100 translate-y-0"
        leave-to-class="opacity-0 -translate-y-1"
      >
        <div
          v-if="isOpen"
          class="absolute z-50 mt-1 w-[17rem] bg-white border border-line rounded-lg shadow-lg overflow-hidden p-3"
        >
          <div class="grid grid-cols-2 gap-2 mb-2">
            <div>
              <p class="text-2xs text-muted mb-1 font-medium">Saat</p>
              <div class="max-h-32 overflow-y-auto border border-line rounded-lg">
                <button
                  v-for="h in hours"
                  :key="h"
                  type="button"
                  :class="[
                    'block w-full text-center py-1 text-2xs transition-colors',
                    parsedValue && parsedValue.hour === Number(h)
                      ? 'bg-primary text-white font-medium'
                      : 'text-ink hover:bg-canvas',
                  ]"
                  @click="selectHour(h)"
                >
                  {{ h }}
                </button>
              </div>
            </div>
            <div>
              <p class="text-2xs text-muted mb-1 font-medium">Dakika</p>
              <div class="max-h-32 overflow-y-auto border border-line rounded-lg">
                <button
                  v-for="m in minutes"
                  :key="m"
                  type="button"
                  :class="[
                    'block w-full text-center py-1 text-2xs transition-colors',
                    parsedValue && parsedValue.minute === Number(m)
                      ? 'bg-primary text-white font-medium'
                      : 'text-ink hover:bg-canvas',
                  ]"
                  @click="selectMinute(m)"
                >
                  {{ m }}
                </button>
              </div>
            </div>
          </div>
          <div class="flex items-center justify-center gap-1 pt-2 border-t border-line">
            <Button size="sm" variant="secondary" :disabled="!modelValue" @click="emit('update:modelValue', null)">Temizle</Button>
            <Button size="sm" variant="primary" @click="isOpen = false">Tamam</Button>
          </div>
        </div>
      </Transition>

      <p v-if="error" class="text-2xs text-danger mt-1">
        {{ error }}
      </p>
    </div>
  </div>
</template>
