<script setup>
import { computed } from 'vue';

const props = defineProps({
    modelValue: { type: [Boolean, Array, String, Number], default: false },
    label: { type: String, default: '' },
    id: { type: String, default: '' },
    value: { type: [String, Number, Boolean], default: undefined },
    name: { type: String, default: '' },
    type: { type: String, default: 'checkbox' },
});

const emit = defineEmits(['update:modelValue']);

const checked = computed({
    get: () => props.modelValue,
    set: (v) => emit('update:modelValue', v),
});
</script>

<template>
    <label class="form-check" :class="{ 'form-check-radio': type === 'radio' }">
        <input
            v-model="checked"
            :type="type"
            :id="id || undefined"
            :name="name || undefined"
            :value="value"
        />
        <span class="form-check-box"></span>
        <span v-if="label" class="form-check-label">{{ label }}</span>
        <slot />
    </label>
</template>
