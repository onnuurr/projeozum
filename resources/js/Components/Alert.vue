<script setup>
import { computed, ref, watch } from 'vue';
import { Info, CheckCircle2, AlertTriangle, AlertCircle, X } from 'lucide-vue-next';

const props = defineProps({
    variant: { type: String, default: 'info', validator: (v) => ['info', 'success', 'warning', 'error'].includes(v) },
    title: { type: String, default: '' },
    message: { type: String, default: '' },
    closable: { type: Boolean, default: false },
});

const emit = defineEmits(['close']);

const visible = ref(true);

function requestClose() {
    visible.value = false;
}

function onAfterLeave() {
    emit('close');
}

watch(
    () => [props.title, props.message, props.variant],
    () => { visible.value = true; },
);

const variantConfig = {
    info: { bg: 'bg-primary/5', border: 'border-primary/20', iconColor: 'text-primary', icon: Info },
    success: { bg: 'bg-success/10', border: 'border-success/30', iconColor: 'text-success', icon: CheckCircle2 },
    warning: { bg: 'bg-warning/10', border: 'border-warning/30', iconColor: 'text-warning', icon: AlertTriangle },
    error: { bg: 'bg-danger/10', border: 'border-danger/30', iconColor: 'text-danger', icon: AlertCircle },
};

const config = computed(() => variantConfig[props.variant]);
</script>

<template>
    <Transition
        appear
        enter-active-class="transition duration-150 ease-out motion-reduce:transition-none"
        enter-from-class="opacity-0 -translate-y-1"
        enter-to-class="opacity-100 translate-y-0"
        leave-active-class="transition duration-150 ease-in motion-reduce:transition-none"
        leave-from-class="opacity-100 translate-y-0"
        leave-to-class="opacity-0 -translate-y-1"
        @after-leave="onAfterLeave"
    >
        <div
            v-if="visible"
            :role="variant === 'error' ? 'alert' : 'status'"
            :aria-live="variant === 'error' ? 'assertive' : 'polite'"
            :class="[config.bg, config.border, 'border rounded-lg p-3 flex items-start gap-3']"
        >
            <component :is="config.icon" :size="18" aria-hidden="true" :class="['flex-shrink-0', config.iconColor]" />
            <div class="flex-1 min-w-0">
                <p v-if="title" class="text-xs font-semibold text-ink mb-0.5">{{ title }}</p>
                <div class="text-xs text-muted">
                    <slot>{{ message }}</slot>
                </div>
            </div>
            <button
                v-if="closable"
                type="button"
                aria-label="Bildirimi kapat"
                class="flex-shrink-0 text-muted hover:text-ink cursor-pointer"
                @click="requestClose"
            >
                <X :size="14" aria-hidden="true" />
            </button>
        </div>
    </Transition>
</template>
