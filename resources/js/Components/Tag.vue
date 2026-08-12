<script setup>
import { computed } from 'vue';
import { X } from 'lucide-vue-next';
import { COLOR_KEYS, colorVariants } from '@/lib/colorVariants.js';

const props = defineProps({
    label: { type: String, default: '' },
    variant: { type: String, default: 'filled', validator: (v) => ['filled', 'outlined'].includes(v) },
    color: { type: String, default: 'primary', validator: (v) => COLOR_KEYS.includes(v) },
    closable: { type: Boolean, default: false },
    icon: { type: [Object, Function], default: null },
    count: { type: Number, default: undefined },
    status: { type: Boolean, default: false },
});

const emit = defineEmits(['close']);

const tagClasses = computed(() => [
    props.variant === 'outlined' ? colorVariants[props.color].outlined : colorVariants[props.color].tonal,
    'inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[0.6875rem] font-medium',
]);

const dotClass = computed(() => colorVariants[props.color].dot);
const countClasses = computed(() => [
    colorVariants[props.color].tonal,
    'px-1.5 min-w-[16px] h-4 rounded-full text-[0.5625rem] font-bold inline-flex items-center justify-center',
]);
</script>

<template>
    <span :class="tagClasses">
        <span v-if="status" :class="[dotClass, 'w-1.5 h-1.5 rounded-full flex-shrink-0']" />

        <component :is="icon" v-if="icon" :size="12" class="flex-shrink-0" />

        <template v-if="label">{{ label }}</template>
        <slot />

        <span v-if="count !== undefined" :class="countClasses">{{ count > 99 ? '99+' : count }}</span>

        <button
            v-if="closable"
            type="button"
            class="ml-0.5 -mr-0.5 hover:opacity-70 cursor-pointer"
            @click.stop="emit('close')"
        >
            <X :size="12" />
        </button>
    </span>
</template>
