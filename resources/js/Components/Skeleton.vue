<script setup>
defineProps({
    variant: { type: String, default: 'block', validator: (v) => ['block', 'circle'].includes(v) },
    width: { type: String, default: '100%' },
    height: { type: String, default: '1rem' },
});
</script>

<template>
    <span class="skeleton inline-block" :class="variant === 'circle' ? 'rounded-full' : 'rounded-md'" :style="{ width, height }" />
</template>

<style scoped>
.skeleton {
    background: linear-gradient(90deg, rgb(var(--color-border)) 25%, rgb(var(--color-bg)) 50%, rgb(var(--color-border)) 75%);
    background-size: 200% 100%;
    animation: skeleton-shimmer 1.4s ease-in-out infinite;
}

@keyframes skeleton-shimmer {
    0% { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}
</style>
