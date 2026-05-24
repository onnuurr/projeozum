<script setup>
import { computed } from 'vue';

const props = defineProps({
    modelValue: { type: String, default: '' },
    placeholder: { type: String, default: '' },
    rows: { type: [Number, String], default: 3 },
    id: { type: String, default: '' },
    name: { type: String, default: '' },
    required: { type: Boolean, default: false },
    minHeight: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue']);

const value = computed({
    get: () => props.modelValue,
    set: (v) => emit('update:modelValue', v),
});

const inlineStyle = computed(() => (props.minHeight ? { minHeight: props.minHeight } : null));
</script>

<template>
    <textarea
        :id="id || undefined"
        :name="name || undefined"
        v-model="value"
        class="form-textarea"
        :placeholder="placeholder"
        :rows="rows"
        :required="required"
        :style="inlineStyle"
    />
</template>
