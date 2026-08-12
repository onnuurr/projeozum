<script setup>
import { computed, ref } from 'vue';
import { Star } from 'lucide-vue-next';

const props = defineProps({
    modelValue: { type: Number, default: 0 },
    max: { type: Number, default: 5 },
    size: { type: String, default: 'md', validator: (v) => ['xs', 'sm', 'md', 'lg', 'xl'].includes(v) },
    readonly: { type: Boolean, default: false },
    color: { type: String, default: '#F59E0B' },
});

const emit = defineEmits(['update:modelValue']);

const rating = computed({
    get: () => props.modelValue,
    set: (v) => emit('update:modelValue', v),
});

const hoverValue = ref(0);

const sizeClasses = { xs: 12, sm: 14, md: 18, lg: 24, xl: 30 };

function setRating(value) {
    if (!props.readonly) rating.value = value;
}

function onHover(value) {
    if (!props.readonly) hoverValue.value = value;
}

function onLeave() {
    hoverValue.value = 0;
}

function isFilled(index) {
    const compareValue = hoverValue.value || rating.value;
    return compareValue - (index - 1) >= 1;
}
</script>

<template>
    <div class="inline-flex items-center gap-0.5" :class="readonly ? '' : 'cursor-pointer'" @mouseleave="onLeave">
        <button
            v-for="i in max"
            :key="i"
            type="button"
            :disabled="readonly"
            :class="['transition-transform', readonly ? 'cursor-default' : 'hover:scale-110']"
            @click="setRating(i)"
            @mouseenter="onHover(i)"
        >
            <Star
                :size="sizeClasses[size]"
                class="transition-colors"
                :style="{ color: isFilled(i) ? color : '#d1d5db', fill: isFilled(i) ? color : 'none' }"
            />
        </button>
    </div>
</template>
