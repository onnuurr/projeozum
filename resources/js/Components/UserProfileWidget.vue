<script setup>
import { BadgeCheck, MoreVertical } from 'lucide-vue-next';
import Skeleton from '@/Components/Skeleton.vue';

defineProps({
    name: { type: String, required: true },
    email: { type: String, default: '' },
    initials: { type: String, default: '' },
    role: { type: String, default: '' },
    online: { type: Boolean, default: false },
    loading: { type: Boolean, default: false },
});

defineEmits(['menu']);
</script>

<template>
    <div class="p-3 pl-[10px] bg-canvas rounded-lg border border-line border-l-[3px] border-l-transparent transition-colors duration-150 hover:border-l-primary hover:bg-surface">
        <div v-if="loading" class="flex items-center gap-4">
            <Skeleton variant="circle" width="3rem" height="3rem" />
            <div class="flex-1 min-w-0">
                <Skeleton width="55%" height="0.875rem" />
                <Skeleton width="70%" height="0.6875rem" class="mt-1.5" />
                <Skeleton width="45%" height="0.75rem" class="mt-2" />
            </div>
        </div>
        <div v-else class="flex items-center gap-4">
            <div class="relative w-12 h-12 rounded-full bg-primary text-white flex items-center justify-center text-lg font-bold flex-shrink-0">
                {{ initials }}
                <span v-if="online" class="absolute bottom-0 right-0 w-3 h-3 bg-success border-2 border-white rounded-full" />
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-semibold text-ink truncate">{{ name }}</p>
                <p v-if="email" class="text-[0.6875rem] text-muted truncate">{{ email }}</p>
                <div class="flex items-center gap-2 mt-1">
                    <span v-if="role" class="inline-flex items-center gap-0.5 px-1.5 py-0.5 bg-primary/10 text-primary text-[0.625rem] rounded">
                        <BadgeCheck :size="10" />
                        {{ role }}
                    </span>
                    <span class="inline-flex items-center gap-0.5 px-1.5 py-0.5 bg-success/10 text-success text-[0.625rem] rounded">
                        <span class="w-1 h-1 bg-success rounded-full" />
                        {{ online ? 'Çevrimiçi' : 'Çevrimdışı' }}
                    </span>
                </div>
            </div>
            <button type="button" class="p-2 text-muted hover:text-primary hover:bg-primary/5 rounded-lg transition-colors" @click="$emit('menu')">
                <MoreVertical :size="20" />
            </button>
        </div>
    </div>
</template>
