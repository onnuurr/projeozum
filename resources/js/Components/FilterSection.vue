<script setup>
import { computed, useSlots } from 'vue';
import { Filter, ChevronDown, Search } from 'lucide-vue-next';

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    search: { type: String, default: '' },
    searchPlaceholder: { type: String, default: 'Hızlı ara...' },
});

const emit = defineEmits(['update:modelValue', 'update:search', 'apply', 'clear']);

const open = computed({
    get: () => props.modelValue,
    set: (v) => emit('update:modelValue', v),
});

const searchValue = computed({
    get: () => props.search,
    set: (v) => emit('update:search', v),
});

const slots = useSlots();
</script>

<template>
    <div class="bg-surface rounded-lg border border-line overflow-hidden">
        <div class="p-2.5 flex items-center justify-between border-b border-line bg-canvas gap-2">
            <div class="flex items-center gap-2 flex-1 min-w-0">
                <button
                    type="button"
                    class="flex items-center gap-1 px-2.5 py-1.5 bg-white border border-line rounded text-xs font-medium flex-shrink-0"
                    @click="open = !open"
                >
                    <Filter :size="14" />
                    <span class="hidden sm:inline">Filtreler</span>
                    <ChevronDown :size="14" class="transition-transform duration-200" :class="{ 'rotate-180': open }" />
                </button>
                <div class="relative flex-1 max-w-xs min-w-0">
                    <Search :size="14" class="absolute left-2 top-1/2 -translate-y-1/2 text-muted" />
                    <input
                        v-model="searchValue"
                        type="text"
                        :placeholder="searchPlaceholder"
                        class="w-full pl-8 pr-2 py-1.5 text-xs border border-line rounded focus:outline-none focus:border-primary bg-white"
                    />
                </div>
            </div>
            <div v-if="slots.actions" class="flex items-center gap-2 flex-shrink-0">
                <slot name="actions" />
            </div>
        </div>

        <div class="transition-all duration-300 overflow-hidden" :class="open ? 'max-h-[600px] opacity-100 p-3' : 'max-h-0 opacity-0 p-0'">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                <slot />
            </div>

            <div class="flex items-center justify-end gap-2 mt-3">
                <button type="button" class="px-3 py-1.5 text-xs font-medium text-muted hover:text-primary transition-colors" @click="$emit('clear')">
                    Temizle
                </button>
                <button type="button" class="bg-primary hover:bg-primary-hover text-white px-3 py-1.5 rounded text-xs font-medium transition-colors" @click="$emit('apply')">
                    Filtrele
                </button>
            </div>
        </div>
    </div>
</template>
