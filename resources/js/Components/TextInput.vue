<script setup>
import { onMounted, ref } from 'vue';
import { isTouchPrimary } from '@/Components/Oz/ozTouch';

const model = defineModel({
    type: String,
    required: true,
});

const input = ref(null);

onMounted(() => {
    // Dokunmatikte otomatik odak sanal klavyeyi açıp ekranı kapatır (bkz. ozTouch.js).
    if (input.value.hasAttribute('autofocus') && !isTouchPrimary()) {
        input.value.focus();
    }
});

defineExpose({ focus: () => input.value.focus() });
</script>

<template>
    <input
        class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
        v-model="model"
        ref="input"
    />
</template>
