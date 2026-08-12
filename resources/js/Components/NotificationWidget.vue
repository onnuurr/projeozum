<script setup>
import { ref, watch } from 'vue';
import { X } from 'lucide-vue-next';
import Skeleton from '@/Components/Skeleton.vue';

const props = defineProps({
    notifications: { type: Array, default: () => [] },
    loading: { type: Boolean, default: false },
});

const emit = defineEmits(['dismiss']);

const items = ref([...props.notifications]);

watch(() => props.notifications, (val) => { items.value = [...val]; });

function dismiss(id) {
    items.value = items.value.filter((n) => n.id !== id);
    emit('dismiss', id);
}
</script>

<template>
    <div class="space-y-2">
        <div v-if="loading" class="space-y-2">
            <div v-for="n in 3" :key="n" class="p-3 rounded-lg border border-line bg-canvas">
                <div class="flex items-start gap-3">
                    <Skeleton variant="circle" width="0.375rem" height="0.375rem" class="mt-1.5" />
                    <div class="flex-1 min-w-0">
                        <Skeleton width="70%" height="0.75rem" />
                        <Skeleton width="90%" height="0.6875rem" class="mt-1.5" />
                        <Skeleton width="35%" height="0.625rem" class="mt-1.5" />
                    </div>
                </div>
            </div>
        </div>
        <div v-else class="space-y-2">
            <div
                v-for="notification in items"
                :key="notification.id"
                :class="['relative p-3 rounded-lg border', notification.read ? 'bg-canvas border-line' : 'bg-primary/5 border-primary/20']"
            >
                <button class="absolute top-2 right-2 p-1 text-muted hover:text-primary rounded transition-colors" @click="dismiss(notification.id)">
                    <X :size="16" />
                </button>
                <div class="flex items-start gap-3 pr-6">
                    <span class="w-1.5 h-1.5 rounded-full mt-1.5 flex-shrink-0" :class="notification.read ? 'bg-transparent' : 'bg-primary'" />
                    <div class="flex-1 min-w-0">
                        <p class="text-xs font-medium text-ink">{{ notification.title }}</p>
                        <p v-if="notification.message" class="text-[0.6875rem] text-muted mt-0.5">{{ notification.message }}</p>
                        <p v-if="notification.time" class="text-[0.625rem] text-muted/70 mt-1">{{ notification.time }}</p>
                    </div>
                </div>
            </div>

            <p v-if="items.length === 0" class="text-xs text-muted text-center py-4">Bildirim yok.</p>
        </div>
    </div>
</template>
