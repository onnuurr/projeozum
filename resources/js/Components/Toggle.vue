<script setup>
import { computed } from 'vue';

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    label: { type: String, default: '' },
    disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

const checked = computed({
    get: () => props.modelValue,
    set: (v) => emit('update:modelValue', v),
});
</script>

<template>
    <label :class="['inline-flex items-center gap-2 cursor-pointer max-md:min-h-[44px] max-md:py-2', disabled ? 'opacity-50 cursor-not-allowed' : '']">
        <div class="relative inline-flex items-center">
            <input
                type="checkbox"
                :checked="checked"
                :disabled="disabled"
                class="sr-only peer focus:outline-none"
                @change="checked = $event.target.checked"
            />
            <div class="w-7 h-4 bg-line rounded-full peer-checked:bg-primary transition-colors duration-200 peer-focus-visible:ring-2 peer-focus-visible:ring-primary peer-focus-visible:ring-offset-1" />
            <div class="absolute left-0.5 top-0.5 w-3 h-3 bg-white rounded-full shadow-sm transition-transform duration-200 peer-checked:translate-x-3" />
        </div>
        <span v-if="label" class="text-xs text-ink">{{ label }}</span>
        <slot />
    </label>
</template>
