<script setup>
import Skeleton from '@/Components/Skeleton.vue';
import Badge from '@/Components/Badge.vue';
import Avatar from '@/Components/Avatar.vue';
import { COLOR_KEYS } from '@/lib/colorVariants.js';

const props = defineProps({
    title: { type: String, required: true },
    subtitle: { type: String, default: '' },
    items: { type: Array, default: () => [] },
    loading: { type: Boolean, default: false },
    emptyText: { type: String, default: 'Kayıt bulunamadı.' },
    maxHeight: { type: String, default: '' },
    clickable: { type: Boolean, default: false },
});

defineEmits(['item-action', 'item-click']);

const badgeClasses = {
    primary: 'text-primary bg-primary/10',
    success: 'text-emerald-600 bg-emerald-50',
    warning: 'text-amber-600 bg-amber-50',
    danger: 'text-red-500 bg-red-50',
    info: 'text-blue-600 bg-blue-50',
    neutral: 'text-zinc-600 bg-zinc-100',
};

function itemBadgeClasses(item) {
    const color = item.badgeColor && COLOR_KEYS.includes(item.badgeColor) ? item.badgeColor : 'neutral';
    return badgeClasses[color];
}
</script>

<template>
    <div class="bg-surface rounded-lg border border-line overflow-hidden flex flex-col">
        <div class="px-3 sm:px-4 py-2.5 border-b border-line bg-canvas flex items-center justify-between gap-3">
            <div class="min-w-0">
                <h3 class="text-xs font-semibold text-ink">{{ title }}</h3>
                <p v-if="subtitle" class="text-[0.6875rem] text-muted truncate">{{ subtitle }}</p>
            </div>
            <div v-if="$slots.actions" class="flex items-center gap-2 flex-shrink-0">
                <slot name="actions" />
            </div>
        </div>

        <div v-if="loading" class="p-3 sm:p-4 space-y-3">
            <div v-for="n in 4" :key="n" class="flex items-center gap-3">
                <Skeleton variant="circle" width="2.25rem" height="2.25rem" class="flex-shrink-0" />
                <div class="flex-1 min-w-0 space-y-1.5">
                    <Skeleton width="45%" height="0.6875rem" />
                    <Skeleton width="70%" height="0.625rem" />
                </div>
                <Skeleton width="3rem" height="1.125rem" class="flex-shrink-0" />
            </div>
        </div>

        <div v-else-if="items.length === 0" class="p-4 text-center">
            <span class="text-2xs text-muted">{{ emptyText }}</span>
        </div>

        <ul v-else :class="['divide-y divide-line', maxHeight ? 'overflow-y-auto' : '']" :style="maxHeight ? { maxHeight } : undefined">
            <li
                v-for="item in items"
                :key="item.id"
                class="px-3 sm:px-4 py-2.5 flex items-center gap-3 hover:bg-canvas/70 transition-colors"
                :class="{ 'cursor-pointer': clickable }"
                @click="clickable && $emit('item-click', item)"
            >
                <Avatar
                    v-if="item.avatar"
                    size="md"
                    :src="item.avatar"
                    :name="item.avatarName"
                    :color="item.avatarColor"
                    class="flex-shrink-0"
                />
                <span
                    v-else-if="item.icon"
                    class="w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0"
                    :class="itemBadgeClasses(item)"
                >
                    <component :is="item.icon" :size="16" />
                </span>

                <div class="flex-1 min-w-0">
                    <p class="text-xs font-medium text-ink truncate">{{ item.title }}</p>
                    <p v-if="item.subtitle" class="text-[0.6875rem] text-muted truncate">{{ item.subtitle }}</p>
                </div>

                <div class="flex items-center gap-2 flex-shrink-0">
                    <span
                        v-if="item.badge"
                        class="text-[0.625rem] font-semibold px-2 py-0.5 rounded-full"
                        :class="itemBadgeClasses(item)"
                    >{{ item.badge }}</span>
                    <span v-if="item.meta" class="text-[0.6875rem] text-muted">{{ item.meta }}</span>
                    <button
                        v-if="$slots['item-action']"
                        type="button"
                        class="p-1 text-muted hover:text-primary rounded transition-colors"
                        @click.stop="$emit('item-action', item)"
                    >
                        <slot name="item-action" />
                    </button>
                </div>
            </li>
        </ul>

        <div v-if="$slots.footer" class="px-3 sm:px-4 py-2.5 border-t border-line bg-canvas">
            <slot name="footer" />
        </div>
    </div>
</template>