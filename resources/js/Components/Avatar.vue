<script setup>
import { computed } from 'vue';
import { colorVariants, COLOR_KEYS } from '@/lib/colorVariants.js';

const props = defineProps({
    src: { type: String, default: '' },
    initials: { type: String, default: '' },
    name: { type: String, default: '' },
    size: { type: String, default: 'md', validator: (v) => ['sm', 'md', 'lg', 'xl'].includes(v) },
    color: { type: String, default: 'primary', validator: (v) => COLOR_KEYS.includes(v) },
    status: { type: String, default: '', validator: (v) => ['', 'online', 'offline', 'away', 'busy'].includes(v) },
    group: { type: Boolean, default: false },
});

const sizeClasses = {
    sm: 'w-7 h-7 text-[0.625rem]',
    md: 'w-8 h-8 text-xs',
    lg: 'w-10 h-10 text-sm',
    xl: 'w-12 h-12 text-lg',
};

const statusColors = { online: 'bg-success', offline: 'bg-gray-400', away: 'bg-warning', busy: 'bg-danger' };

const dotSizeClasses = {
    sm: 'w-2 h-2 border',
    md: 'w-2.5 h-2.5 border',
    lg: 'w-3 h-3 border-2',
    xl: 'w-3.5 h-3.5 border-2',
};

const avatarClasses = computed(() => [
    sizeClasses[props.size],
    colorVariants[props.color].tonal,
    props.group ? 'ring-2 ring-surface' : '',
    'relative inline-flex items-center justify-center rounded-full font-medium overflow-hidden flex-shrink-0 select-none',
]);
</script>

<template>
    <div :class="avatarClasses">
        <img v-if="src" :src="src" alt="" class="w-full h-full object-cover rounded-full" />
        <span v-else>{{ initials || name || '?' }}</span>

        <span
            v-if="status"
            :class="[statusColors[status], dotSizeClasses[size], 'absolute bottom-0 right-0 rounded-full border-white']"
        />
    </div>
</template>
