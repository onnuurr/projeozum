<script setup>
import { computed } from 'vue';

const props = defineProps({
    text: { type: String, default: '' },
    position: { type: String, default: 'top', validator: (v) => ['top', 'bottom', 'left', 'right'].includes(v) },
    variant: { type: String, default: 'dark', validator: (v) => ['dark', 'light'].includes(v) },
    size: { type: String, default: 'sm', validator: (v) => ['sm', 'md'].includes(v) },
    arrow: { type: Boolean, default: true },
    delay: { type: Number, default: 0 },
    disabled: { type: Boolean, default: false },
});

const positionClasses = {
    top: 'bottom-full left-1/2 -translate-x-1/2 mb-2',
    bottom: 'top-full left-1/2 -translate-x-1/2 mt-2',
    left: 'right-full top-1/2 -translate-y-1/2 mr-2',
    right: 'left-full top-1/2 -translate-y-1/2 ml-2',
};

const hiddenTransformClasses = {
    top: 'translate-y-1',
    bottom: '-translate-y-1',
    left: 'translate-x-1',
    right: '-translate-x-1',
};

const visibleTransformClasses = {
    top: 'group-hover/tooltip:translate-y-0',
    bottom: 'group-hover/tooltip:translate-y-0',
    left: 'group-hover/tooltip:translate-x-0',
    right: 'group-hover/tooltip:translate-x-0',
};

const arrowPositionClasses = {
    top: 'left-1/2 -translate-x-1/2 -bottom-1',
    bottom: 'left-1/2 -translate-x-1/2 -top-1',
    left: 'top-1/2 -translate-y-1/2 -right-1',
    right: 'top-1/2 -translate-y-1/2 -left-1',
};

const variantClasses = {
    dark: 'bg-ink text-white',
    light: 'bg-surface text-ink shadow-lg ring-1 ring-line',
};

const sizeClasses = {
    sm: 'px-2.5 py-1 text-[0.6875rem]',
    md: 'px-3 py-1.5 text-xs',
};

const tooltipClasses = computed(() => [
    positionClasses[props.position],
    hiddenTransformClasses[props.position],
    visibleTransformClasses[props.position],
    variantClasses[props.variant],
    sizeClasses[props.size],
    'absolute z-50 rounded-lg whitespace-nowrap',
    'opacity-0 scale-95 group-hover/tooltip:opacity-100 group-hover/tooltip:scale-100',
    'transition duration-150 motion-reduce:transition-[opacity] pointer-events-none',
]);

const arrowClasses = computed(() => [
    arrowPositionClasses[props.position],
    props.variant === 'dark' ? 'bg-ink' : 'bg-surface ring-1 ring-line',
    'absolute w-2 h-2 rotate-45',
]);

const delayStyle = computed(() => (props.delay ? { transitionDelay: `${props.delay}ms` } : null));
</script>

<template>
    <div class="relative inline-flex group/tooltip">
        <slot />
        <div v-if="!disabled && text" :class="tooltipClasses" :style="delayStyle">
            <span v-if="arrow" :class="arrowClasses" />
            <span class="relative z-10">{{ text }}</span>
        </div>
    </div>
</template>
