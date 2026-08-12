<script setup>
import { computed, useSlots, watch } from 'vue';
import { X } from 'lucide-vue-next';

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    position: { type: String, default: 'right', validator: (v) => ['left', 'right', 'top', 'bottom'].includes(v) },
    title: { type: String, default: '' },
    width: { type: String, default: 'w-80 max-sm:w-[85vw]' },
});

const emit = defineEmits(['update:modelValue', 'close']);

const open = computed({
    get: () => props.modelValue,
    set: (v) => emit('update:modelValue', v),
});

const slots = useSlots();

const isHorizontal = computed(() => ['left', 'right'].includes(props.position));

const panelPosition = computed(() => ({
    left: 'left-0 top-0 h-full',
    right: 'right-0 top-0 h-full',
    top: 'top-0 left-0 right-0 max-h-[60vh]',
    bottom: 'bottom-0 left-0 right-0 max-h-[70vh] rounded-t-2xl',
}[props.position]));

const slideClass = computed(() => ({
    left: 'slide-left',
    right: 'slide-right',
    top: 'slide-top',
    bottom: 'slide-bottom',
}[props.position]));

function close() {
    open.value = false;
    emit('close');
}

watch(open, (val) => {
    document.body.style.overflow = val ? 'hidden' : '';
});
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition ease-out duration-200"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition ease-in duration-150"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="open" class="fixed inset-0 z-50">
                <div class="absolute inset-0 bg-black/40" @click="close" />

                <div :class="['absolute flex flex-col bg-white shadow-xl border border-line', panelPosition, isHorizontal ? width : '', slideClass]">
                    <div class="flex items-center justify-between p-3 border-b border-line">
                        <h3 v-if="title" class="text-sm font-semibold text-ink">{{ title }}</h3>
                        <button type="button" class="ml-auto text-muted hover:text-ink cursor-pointer" @click="close">
                            <X :size="18" />
                        </button>
                    </div>

                    <div class="flex-1 overflow-y-auto p-3">
                        <slot />
                    </div>

                    <div v-if="slots.footer" class="p-3 border-t border-line">
                        <slot name="footer" />
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.slide-left { animation: slide-left 200ms ease-out; }
.slide-right { animation: slide-right 200ms ease-out; }
.slide-top { animation: slide-top 200ms ease-out; }
.slide-bottom { animation: slide-bottom 200ms ease-out; }

@keyframes slide-left { from { transform: translateX(-100%); } to { transform: translateX(0); } }
@keyframes slide-right { from { transform: translateX(100%); } to { transform: translateX(0); } }
@keyframes slide-top { from { transform: translateY(-100%); } to { transform: translateY(0); } }
@keyframes slide-bottom { from { transform: translateY(100%); } to { transform: translateY(0); } }
</style>
