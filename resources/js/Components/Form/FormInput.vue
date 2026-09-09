<script setup>
import { computed, useSlots } from 'vue';

const props = defineProps({
    modelValue: { type: [String, Number], default: '' },
    type: { type: String, default: 'text' },
    placeholder: { type: String, default: '' },
    autocomplete: { type: String, default: 'off' },
    required: { type: Boolean, default: false },
    minlength: { type: [Number, String], default: undefined },
    min: { type: [Number, String], default: undefined },
    max: { type: [Number, String], default: undefined },
    id: { type: String, default: '' },
    name: { type: String, default: '' },
    variant: { type: String, default: 'auth' },
});

const emit = defineEmits(['update:modelValue']);

const slots = useSlots();

const wrapClass = computed(() => {
    if (props.variant === 'auth') return 'input-wrap';
    if (props.variant === 'settings') return 'settings-input-wrap';
    return 'form-input-wrap';
});
const iconClass = computed(() => {
    if (props.variant === 'auth') return 'input-icon';
    if (props.variant === 'settings') return 'settings-input-icon';
    return 'form-input-icon';
});
const inputClass = computed(() => (props.variant === 'settings' ? 'settings-input' : 'form-input'));
const hasIcon = computed(() => !!slots.icon);
const hasTrailing = computed(() => !!slots.trailing);
const needsWrapper = computed(() => hasIcon.value || hasTrailing.value || props.variant === 'auth');

const value = computed({
    get: () => props.modelValue,
    set: (v) => emit('update:modelValue', v),
});
</script>

<template>
    <div v-if="needsWrapper" :class="wrapClass">
        <span v-if="hasIcon" :class="iconClass">
            <slot name="icon" />
        </span>

        <input
            :id="id || undefined"
            :name="name || undefined"
            v-model="value"
            :type="type"
            :class="[inputClass, { 'has-trail': hasTrailing, 'has-icon': hasIcon }]"
            :placeholder="placeholder"
            :autocomplete="autocomplete"
            :required="required"
            :minlength="minlength"
            :min="min"
            :max="max"
        />

        <slot name="trailing" />
    </div>

    <input
        v-else
        :id="id || undefined"
        :name="name || undefined"
        v-model="value"
        :type="type"
        :class="inputClass"
        :placeholder="placeholder"
        :autocomplete="autocomplete"
        :required="required"
        :minlength="minlength"
        :min="min"
        :max="max"
        style="width: 100%"
    />
</template>
