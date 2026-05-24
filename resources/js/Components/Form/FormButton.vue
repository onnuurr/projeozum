<script setup>
import { computed, useSlots } from 'vue';

const props = defineProps({
    type: { type: String, default: 'button' },
    variant: { type: String, default: 'primary' },
    size: { type: String, default: '' },
    loading: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    pill: { type: Boolean, default: false },
    iconOnly: { type: Boolean, default: false },
    withIcon: { type: Boolean, default: false },
    block: { type: Boolean, default: false },
});

const emit = defineEmits(['click']);
const slots = useSlots();

const hasLeading = computed(() => !!slots.leading);
const hasDefault = computed(() => !!slots.default);

const classes = computed(() => ({
    [`btn-${props.variant}`]: !!props.variant,
    [`btn-${props.size}`]: !!props.size,
    'btn-loading': props.loading,
    'is-loading': props.loading,
    'btn-with-icon': props.withIcon || hasLeading.value,
    'btn-icon': props.iconOnly,
    'btn-pill': props.pill,
    'w-full': props.block,
}));
</script>

<template>
    <button
        :type="type"
        class="btn"
        :class="classes"
        :disabled="disabled || loading"
        @click="emit('click', $event)"
    >
        <span v-if="loading" class="btn-spinner" aria-hidden="true"></span>

        <slot v-if="!loading" name="leading" />

        <span v-if="hasDefault && !iconOnly" class="btn-label">
            <slot />
        </span>

        <slot v-if="!loading" name="trailing" />
    </button>
</template>
