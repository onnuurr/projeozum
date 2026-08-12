<script setup>
import { ref, computed, onMounted, onBeforeUnmount, watch, useSlots } from 'vue'
import IMask from 'imask'
import { X, CheckCircle2, AlertCircle } from 'lucide-vue-next'

const props = defineProps({
  modelValue: {
    type: String,
    default: '',
  },
  mask: {
    type: [String, Object],
    required: true,
  },
  label: String,
  placeholder: String,
  prefix: String,
  suffix: String,
  uppercase: Boolean,
  clearable: Boolean,
  hint: String,
  error: String,
})

const emit = defineEmits(['update:modelValue', 'update:unmasked', 'update:complete'])

const inputRef = ref(null)
const slots = useSlots()
let maskInstance = null

const isComplete = ref(false)
const isFocused = ref(false)

function syncComplete() {
  const complete = maskInstance?.masked.isComplete ?? false
  if (complete !== isComplete.value) {
    isComplete.value = complete
    emit('update:complete', complete)
  }
}

onMounted(() => {
  const options = typeof props.mask === 'string' ? { mask: props.mask } : props.mask
  maskInstance = IMask(inputRef.value, options)
  maskInstance.on('accept', () => {
    emit('update:modelValue', maskInstance.value)
    emit('update:unmasked', maskInstance.unmaskedValue)
    syncComplete()
  })
  if (props.modelValue) {
    maskInstance.value = props.modelValue
  }
  syncComplete()
})

onBeforeUnmount(() => {
  maskInstance?.destroy()
})

watch(() => props.modelValue, (val) => {
  if (maskInstance && val !== maskInstance.value) {
    maskInstance.value = val ?? ''
    syncComplete()
  }
})

function clear() {
  emit('update:modelValue', '')
  if (maskInstance) {
    maskInstance.value = ''
    syncComplete()
  }
}

const hasIcon = computed(() => !!slots.icon)
const hasLeftAdornment = computed(() => hasIcon.value || Boolean(props.prefix))

const showClear = computed(() => props.clearable && props.modelValue && !props.error)

const showSuccess = computed(() => isComplete.value && !props.error && !showClear.value)

const stateClasses = computed(() => {
  if (props.error) return 'border-danger focus:border-danger focus:ring-danger/20'
  if (isComplete.value) return 'border-success focus:ring-success/20'
  return 'border-line focus:border-primary focus:ring-primary/20'
})
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
      <span
        v-if="isComplete && !error"
        class="inline-flex items-center gap-0.5 text-2xs font-medium text-success"
      >
        <CheckCircle2 class="w-3 h-3" />
        Tamam
      </span>
    </div>
    <div class="relative">
      <span
        v-if="hasIcon"
        class="absolute left-2.5 top-1/2 -translate-y-1/2 w-6 h-6 rounded-md bg-canvas flex items-center justify-center transition-colors"
        :class="isFocused && !error ? 'text-primary bg-primary/10' : 'text-muted'"
      >
        <slot name="icon" />
      </span>
      <span
        v-else-if="prefix"
        class="absolute left-2.5 top-1/2 -translate-y-1/2 w-6 h-6 rounded-md bg-canvas flex items-center justify-center text-xs font-bold transition-colors"
        :class="isFocused && !error ? 'text-primary bg-primary/10' : 'text-muted'"
      >
        {{ prefix }}
      </span>
      <input
        ref="inputRef"
        type="text"
        :placeholder="placeholder"
        :class="[
          'w-full bg-white border rounded-lg py-1.5 text-xs text-ink transition-[border-color,box-shadow,background-color] duration-150 motion-reduce:transition-none',
          'placeholder:text-muted/60',
          'focus:outline-none focus:ring-2',
          hasLeftAdornment ? 'pl-11' : 'pl-2.5',
          (showClear || showSuccess || suffix) ? 'pr-9' : 'pr-2.5',
          uppercase ? 'uppercase' : '',
          stateClasses,
        ]"
        @focus="isFocused = true"
        @blur="isFocused = false"
      />
      <button
        v-if="showClear"
        type="button"
        class="absolute right-2 top-1/2 -translate-y-1/2 text-muted hover:text-danger transition-colors cursor-pointer"
        @click="clear"
      >
        <X class="w-3.5 h-3.5" />
      </button>
      <span
        v-else-if="showSuccess"
        class="absolute right-2 top-1/2 -translate-y-1/2 text-success pointer-events-none"
      >
        <CheckCircle2 class="w-3.5 h-3.5" />
      </span>
      <span
        v-else-if="suffix"
        class="absolute right-2.5 top-1/2 -translate-y-1/2 text-xs font-bold text-muted pointer-events-none"
      >
        {{ suffix }}
      </span>
    </div>
    <p
      v-if="error"
      class="text-2xs text-danger mt-1 flex items-center gap-1"
    >
      <AlertCircle class="w-3 h-3" />
      {{ error }}
    </p>
    <p
      v-else-if="hint"
      class="text-2xs text-muted/80 mt-1"
    >
      {{ hint }}
    </p>
  </div>
</template>
