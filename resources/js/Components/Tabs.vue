<script setup>
import { computed, reactive, ref, nextTick, onMounted, onBeforeUnmount, watch } from 'vue';
import { useSpring, useReducedMotion } from '@vueuse/motion';

const props = defineProps({
    tabs: { type: Array, required: true },
    modelValue: { type: [String, Number], required: true },
    variant: { type: String, default: 'underline', validator: (v) => ['underline', 'pills', 'icons'].includes(v) },
});

const emit = defineEmits(['update:modelValue']);

const activeKey = computed({
    get: () => props.modelValue,
    set: (v) => emit('update:modelValue', v),
});

const underlineClasses = {
    wrapper: 'border-b border-line relative',
    active: 'text-primary font-medium',
    inactive: 'text-muted hover:text-primary',
    item: 'px-3 py-2 text-xs cursor-pointer transition-colors max-md:min-h-[44px] max-md:inline-flex max-md:items-center',
};

const pillsClasses = {
    wrapper: 'relative inline-flex bg-canvas p-1 rounded-lg gap-0.5',
    active: 'text-white',
    inactive: 'text-muted hover:text-primary',
    item: 'relative z-10 px-3 py-1.5 text-xs rounded-md cursor-pointer transition-colors max-md:min-h-[44px] max-md:flex max-md:items-center',
};

const iconsClasses = {
    wrapper: 'flex flex-col items-center',
    active: 'text-primary',
    inactive: 'text-muted hover:text-primary',
    item: 'relative flex flex-col items-center gap-0.5 px-2 py-1 cursor-pointer transition-colors max-md:min-h-[44px] max-md:justify-center',
};

const styles = computed(() => ({ underline: underlineClasses, pills: pillsClasses, icons: iconsClasses }[props.variant]));

const tabRefs = ref([]);
const indicator = useSpring(reactive({ x: 0, width: 0 }), { stiffness: 500, damping: 40 });
const prefersReducedMotion = useReducedMotion();

function setTabRef(el, index) {
    tabRefs.value[index] = el;
}

function updateIndicator(animate) {
    if (props.variant !== 'underline' && props.variant !== 'pills') return;
    const activeIndex = props.tabs.findIndex((t) => t.key === activeKey.value);
    const el = tabRefs.value[activeIndex];
    if (!el) return;
    if (animate && !prefersReducedMotion.value) {
        indicator.set({ x: el.offsetLeft, width: el.offsetWidth });
    } else {
        indicator.values.x = el.offsetLeft;
        indicator.values.width = el.offsetWidth;
    }
}

let resizeObserver = null;

onMounted(() => {
    nextTick(() => updateIndicator(false));
    if ((props.variant === 'underline' || props.variant === 'pills') && typeof ResizeObserver !== 'undefined') {
        resizeObserver = new ResizeObserver(() => updateIndicator(false));
        tabRefs.value.forEach((el) => el && resizeObserver.observe(el));
    }
});

onBeforeUnmount(() => resizeObserver?.disconnect());

watch(activeKey, () => nextTick(() => updateIndicator(true)));
</script>

<template>
    <div :class="styles.wrapper">
        <span
            v-if="variant === 'underline' || variant === 'pills'"
            class="absolute pointer-events-none"
            :class="variant === 'underline' ? 'bottom-0 left-0 h-0.5 bg-primary rounded-full' : 'top-1 bottom-1 left-0 rounded-md bg-primary shadow-sm'"
            :style="{ transform: `translateX(${indicator.values.x}px)`, width: `${indicator.values.width}px` }"
        />
        <button
            v-for="(tab, index) in tabs"
            :key="tab.key"
            :ref="(el) => setTabRef(el, index)"
            type="button"
            :disabled="tab.disabled"
            :class="[
                styles.item,
                tab.disabled ? 'opacity-40 cursor-not-allowed pointer-events-none' : '',
                activeKey === tab.key ? styles.active : styles.inactive,
            ]"
            @click="activeKey = tab.key"
        >
            <component :is="tab.icon" v-if="tab.icon" :size="variant === 'icons' ? 18 : 14" />
            <span v-if="variant === 'icons'" class="text-[0.5625rem]">{{ tab.label }}</span>
            <template v-else>
                {{ tab.label }}
                <span
                    v-if="tab.count !== undefined"
                    class="ml-1 px-1.5 min-w-[16px] h-4 rounded-full text-[0.5625rem] font-bold inline-flex items-center justify-center"
                    :class="activeKey === tab.key ? 'bg-white/20 text-white' : 'bg-muted/10 text-muted'"
                >
                    {{ tab.count > 99 ? '99+' : tab.count }}
                </span>
            </template>
            <span
                v-if="variant === 'icons' && tab.count !== undefined"
                class="absolute -top-1 -right-1 px-1 min-w-[15px] h-[15px] rounded-full text-[0.5rem] font-bold inline-flex items-center justify-center"
                :class="activeKey === tab.key ? 'bg-primary/20 text-primary' : 'bg-muted/10 text-muted'"
            >
                {{ tab.count > 99 ? '99+' : tab.count }}
            </span>
        </button>
    </div>
</template>
