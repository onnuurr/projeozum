<script setup>
import { computed } from 'vue';
import { COLOR_KEYS, colorVariants } from '@/lib/colorVariants.js';

const props = defineProps({
    label: { type: String, default: '' },
    color: { type: String, default: 'primary', validator: (v) => COLOR_KEYS.includes(v) },
    variant: { type: String, default: 'filled', validator: (v) => ['filled', 'tonal', 'outlined'].includes(v) },
    rounded: { type: String, default: 'full', validator: (v) => ['md', 'full'].includes(v) },
    dot: { type: Boolean, default: false },
    count: { type: Number, default: undefined },
    icon: { type: [Object, Function], default: null },
    size: { type: String, default: 'sm', validator: (v) => ['sm', 'md'].includes(v) },
});

const sizeClasses = {
    sm: 'px-1.5 min-w-[18px] h-[18px] text-[0.625rem]',
    md: 'px-2 min-w-[22px] h-[22px] text-[0.6875rem]',
};

const roundedClasses = { md: 'rounded-md', full: 'rounded-full' };

const classes = computed(() => [
    colorVariants[props.color][props.variant],
    sizeClasses[props.size],
    roundedClasses[props.rounded],
    'inline-flex items-center justify-center font-medium',
    props.icon ? 'gap-0.5' : '',
]);

const dotClass = computed(() => colorVariants[props.color].dot);
</script>

<template>
    <span
        v-if="dot"
        :class="[dotClass, size === 'sm' ? 'w-2 h-2' : 'w-2.5 h-2.5', 'inline-block rounded-full']"
    />

    <span v-else :class="classes">
        <component :is="icon" v-if="icon" :size="size === 'sm' ? 11 : 12" class="flex-shrink-0" />
        <template v-if="count !== undefined && !icon">{{ count > 99 ? '99+' : count }}</template>
        <span v-else-if="label" class="truncate">{{ label }}</span>
        <slot />
    </span>
</template>
