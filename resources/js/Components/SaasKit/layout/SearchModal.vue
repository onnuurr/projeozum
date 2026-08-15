<!--
	saas-frontend components/layout/SearchModal.vue portu. Tek fark: <UiIcon>
	Components/UiIcon.vue'dan açıkça import ediliyor (global registration yok).
-->
<script setup>
import { ref, computed, watch, nextTick, onMounted, onBeforeUnmount } from 'vue';
import UiIcon from '@/Components/UiIcon.vue';

const props = defineProps({
    modelValue: {
        type: Boolean,
        default: false,
    },
    items: {
        type: Array,
        default: () => [
            { key: 'home', label: 'Ana Sayfa', icon: 'home', group: 'Sayfalar' },
            { key: 'orders', label: 'Siparişler', icon: 'shopping_bag', group: 'Sayfalar' },
            { key: 'products', label: 'Ürünler', icon: 'package', group: 'Sayfalar' },
            { key: 'marketplace', label: 'Pazaryeri', icon: 'store', group: 'Sayfalar' },
            { key: 'campaigns', label: 'Kampanyalar', icon: 'percent', group: 'Sayfalar' },
            { key: 'customers', label: 'Müşteriler', icon: 'users', group: 'Sayfalar' },
            { key: 'reports', label: 'Raporlar', icon: 'chart-line', group: 'Sayfalar' },
            { key: 'settings', label: 'Ayarlar', icon: 'settings', group: 'Sayfalar' },
            { key: 'new-order', label: 'Yeni Sipariş Oluştur', icon: 'plus', group: 'Hızlı İşlemler' },
            { key: 'new-product', label: 'Yeni Ürün Ekle', icon: 'add_shopping_cart', group: 'Hızlı İşlemler' },
            { key: 'new-customer', label: 'Yeni Müşteri Ekle', icon: 'user_add', group: 'Hızlı İşlemler' },
        ],
    },
});

const emit = defineEmits(['update:modelValue', 'select']);

const query = ref('');
const inputRef = ref(null);
const activeIndex = ref(0);

const filteredItems = computed(() => {
    if (!query.value.trim()) return props.items;
    const q = query.value.toLowerCase();
    return props.items.filter((item) => item.label.toLowerCase().includes(q));
});

const groupedItems = computed(() => {
    const groups = {};
    for (const item of filteredItems.value) {
        if (!groups[item.group]) groups[item.group] = [];
        groups[item.group].push(item);
    }
    return groups;
});

function close() {
    emit('update:modelValue', false);
}

function select(item) {
    emit('select', item);
    close();
}

function onArrow(dir) {
    const max = filteredItems.value.length - 1;
    if (max < 0) return;
    activeIndex.value = Math.min(max, Math.max(0, activeIndex.value + dir));
}

function onEnter() {
    const item = filteredItems.value[activeIndex.value];
    if (item) select(item);
}

function onEscape(e) {
    if (e.key === 'Escape' && props.modelValue) close();
}

watch(() => props.modelValue, async (open) => {
    document.body.style.overflow = open ? 'hidden' : '';
    if (open) {
        query.value = '';
        activeIndex.value = 0;
        await nextTick();
        inputRef.value?.focus();
    }
});

onMounted(() => {
    document.addEventListener('keydown', onEscape);
});

onBeforeUnmount(() => {
    document.removeEventListener('keydown', onEscape);
    document.body.style.overflow = '';
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
            <div v-if="modelValue" class="fixed inset-0 z-[60] bg-black/50" />
        </Transition>

        <Transition
            enter-active-class="transition ease-out duration-200"
            enter-from-class="opacity-0 scale-95 -translate-y-2"
            enter-to-class="opacity-100 scale-100 translate-y-0"
            leave-active-class="transition ease-in duration-150"
            leave-from-class="opacity-100 scale-100 translate-y-0"
            leave-to-class="opacity-0 scale-95 -translate-y-2"
        >
            <div
                v-if="modelValue"
                class="fixed inset-0 z-[60] flex items-start justify-center px-3 sm:px-4"
                style="padding-top: min(8vh, 60px);"
            >
                <div class="w-full max-w-2xl bg-white rounded-xl sm:rounded-2xl shadow-2xl border border-line overflow-hidden flex flex-col max-h-[80vh] sm:max-h-[70vh]">
                    <!-- Search bar -->
                    <div class="flex items-center gap-2.5 px-4 sm:px-5 py-3 sm:py-4 border-b border-line flex-shrink-0">
                        <UiIcon name="search" :size="20" class="text-muted flex-shrink-0" />
                        <input
                            ref="inputRef"
                            v-model="query"
                            type="text"
                            placeholder="Ara... (sipariş, ürün, müşteri, sayfa)"
                            class="flex-1 bg-transparent border-none text-sm text-ink focus:outline-none focus:ring-0 placeholder-muted/60"
                            @keydown.down.prevent="onArrow(1)"
                            @keydown.up.prevent="onArrow(-1)"
                            @keydown.enter.prevent="onEnter"
                        />
                        <button
                            type="button"
                            class="flex-shrink-0 p-1.5 text-muted hover:text-ink hover:bg-surface rounded-lg transition-colors cursor-pointer"
                            title="Kapat (Esc)"
                            @click="close"
                        >
                            <UiIcon name="close" :size="20" />
                        </button>
                    </div>

                    <!-- Results -->
                    <div class="flex-1 overflow-y-auto p-2">
                        <template v-if="filteredItems.length">
                            <div v-for="(groupItems, groupName) in groupedItems" :key="groupName" class="mb-2 last:mb-0">
                                <p class="px-3 pt-2 pb-1 text-2xs font-semibold text-muted uppercase tracking-wider">
                                    {{ groupName }}
                                </p>
                                <button
                                    v-for="item in groupItems"
                                    :key="item.key"
                                    type="button"
                                    :class="[
                                        'w-full flex items-center gap-3 px-3 py-2.5 rounded-lg text-left text-sm transition-colors cursor-pointer',
                                        filteredItems[activeIndex]?.key === item.key
                                            ? 'bg-primary/10 text-primary'
                                            : 'text-ink hover:bg-surface',
                                    ]"
                                    @click="select(item)"
                                    @mouseenter="activeIndex = filteredItems.indexOf(item)"
                                >
                                    <UiIcon :name="item.icon" :size="18" class="text-muted" />
                                    {{ item.label }}
                                </button>
                            </div>
                        </template>
                        <p v-else class="px-3 py-10 text-sm text-muted text-center">
                            "{{ query }}" için sonuç bulunamadı.
                        </p>
                    </div>

                    <!-- Footer hints -->
                    <div class="flex-shrink-0 flex items-center justify-between px-4 sm:px-5 py-2.5 border-t border-line text-2xs text-muted bg-surface">
                        <div class="flex items-center gap-3">
                            <span class="hidden sm:flex items-center gap-1">
                                <kbd class="px-1.5 py-0.5 bg-white border border-line rounded text-2xs">↑↓</kbd>
                                gezin
                            </span>
                            <span class="flex items-center gap-1">
                                <kbd class="px-1.5 py-0.5 bg-white border border-line rounded text-2xs">↵</kbd>
                                seç
                            </span>
                        </div>
                        <span class="hidden sm:flex items-center gap-1">
                            <kbd class="px-1.5 py-0.5 bg-white border border-line rounded text-2xs">esc</kbd>
                            kapat
                        </span>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
