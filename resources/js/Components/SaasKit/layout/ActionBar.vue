<!--
	saas-frontend components/layout/ActionBar.vue portu. Tek fark: saas-frontend'de
	<UiIcon> app.js'te global component olarak kayıtlı, bu projede öyle bir mekanizma
	yok — burada Components/UiIcon.vue açıkça import ediliyor (bkz. PORTING_GUIDE.md §4.9).
-->
<script setup>
import { ref } from 'vue';
import UiIcon from '@/Components/UiIcon.vue';

const props = defineProps({
    title: {
        type: String,
        default: 'Sipariş Yönetimi',
    },
    icon: {
        type: String,
        default: '',
    },
    tabs: {
        type: Array,
        default: () => [
            { key: 'all', label: 'Tümü', count: null, active: true },
            { key: 'pending', label: 'Bekleyen', count: 12, active: false },
            { key: 'processing', label: 'İşleniyor', count: 5, active: false },
            { key: 'shipped', label: 'Kargoda', count: 8, active: false },
            { key: 'completed', label: 'Tamamlanan', count: null, active: false },
            { key: 'cancelled', label: 'İptal', count: 2, active: false },
        ],
    },
    modelValue: {
        type: String,
        default: 'all',
    },
});

const emit = defineEmits(['update:modelValue']);

function selectTab(key) {
    emit('update:modelValue', key);
}

const scrollRef = ref(null);
const dragging = ref(false);
let startX = 0;
let startScrollLeft = 0;
let didDrag = false;

function onMouseDown(e) {
    const el = scrollRef.value;
    if (!el || e.button !== 0) return;
    dragging.value = true;
    didDrag = false;
    startX = e.clientX;
    startScrollLeft = el.scrollLeft;
    document.body.classList.add('select-none');
    window.addEventListener('mousemove', onMouseMove);
    window.addEventListener('mouseup', stopDrag);
}

function onMouseMove(e) {
    if (!dragging.value) return;
    const el = scrollRef.value;
    if (!el) return;
    el.scrollLeft = startScrollLeft - (e.clientX - startX);
    if (Math.abs(e.clientX - startX) > 5) {
        didDrag = true;
    }
}

function stopDrag() {
    if (!dragging.value) return;
    dragging.value = false;
    document.body.classList.remove('select-none');
    window.removeEventListener('mousemove', onMouseMove);
    window.removeEventListener('mouseup', stopDrag);
}

function onClickCapture(e) {
    if (didDrag) {
        e.preventDefault();
        e.stopPropagation();
        didDrag = false;
    }
}

function onWheel(e) {
    const el = e.currentTarget;
    if (el.scrollWidth <= el.clientWidth) return;
    if (Math.abs(e.deltaY) <= Math.abs(e.deltaX)) return;
    e.preventDefault();
    el.scrollLeft += e.deltaY;
}
</script>

<template>
    <div class="bg-surface h-11 sm:h-14 px-2 sm:px-3 md:px-6 flex items-center justify-between gap-2 sm:gap-6 border-b border-line">
        <div class="flex items-center gap-2 flex-shrink-0">
            <div
                v-if="icon"
                class="w-6 h-6 rounded-md bg-primary/10 flex items-center justify-center flex-shrink-0"
            >
                <UiIcon :name="icon" :size="14" class="text-primary" />
            </div>
            <h1 class="hidden sm:block text-md font-bold text-ink tracking-tight leading-none whitespace-nowrap">
                {{ title }}
            </h1>
        </div>

        <div
            ref="scrollRef"
            :class="dragging ? 'cursor-grabbing' : 'cursor-grab'"
            :style="dragging ? { scrollSnapType: 'none', scrollBehavior: 'auto' } : undefined"
            class="flex items-center gap-1 flex-1 min-w-0 overflow-x-auto no-scrollbar scroll-snap-x whitespace-nowrap overscroll-x-contain select-none"
            @wheel="onWheel"
            @mousedown="onMouseDown"
            @mousemove="onMouseMove"
            @mouseup="stopDrag"
            @mouseleave="stopDrag"
            @click.capture="onClickCapture"
        >
            <a
                v-for="tab in tabs"
                :key="tab.key"
                :class="[
                    'flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold cursor-pointer transition-all duration-200 flex-shrink-0',
                    tab.key === modelValue
                        ? 'bg-primary/10 text-primary'
                        : 'text-muted hover:bg-canvas hover:text-ink',
                ]"
                @click.prevent="selectTab(tab.key)"
            >
                {{ tab.label }}
                <span
                    v-if="tab.count != null"
                    class="px-1.5 py-0.5 rounded-full text-2xs font-medium"
                    :class="tab.key === modelValue ? 'bg-primary text-white' : 'bg-canvas text-muted'"
                >
                    {{ tab.count }}
                </span>
            </a>
        </div>

        <slot name="actions" />
    </div>
</template>
