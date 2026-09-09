<script setup>
import { computed } from 'vue';

const props = defineProps({
    label: { type: String, default: '' },
    error: { type: String, default: '' },
    link: { type: Object, default: null },
    variant: { type: String, default: 'auth' },
    forId: { type: String, default: '' },
    required: { type: Boolean, default: false },
});

const wrapperClass = computed(() => {
    if (props.variant === 'auth') return 'field';
    if (props.variant === 'settings') return 'settings-field';
    return 'form-group';
});
const labelClass = computed(() => {
    if (props.variant === 'auth') return 'field-label';
    if (props.variant === 'settings') return 'settings-label';
    return 'form-label';
});
</script>

<template>
    <div :class="[wrapperClass, { 'has-error': !!error }]">
        <label v-if="label" :class="labelClass" :for="forId || undefined">
            {{ label }}<span v-if="required" class="field-required-mark"> *</span>
            <a v-if="link" class="field-link" :href="link.href">{{ link.label }}</a>
        </label>

        <slot />

        <div v-if="error && variant === 'auth'" class="field-error">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none">
                <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2" />
                <path d="M12 7v6M12 17h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
            </svg>
            <span class="error-msg">{{ error }}</span>
        </div>
        <p v-else-if="error" class="text-xs text-rose-600 mt-1">{{ error }}</p>
    </div>
</template>
