<script setup>
import { CircleHelp } from 'lucide-vue-next';
import Tooltip from '@/Components/Tooltip.vue';

defineProps({
    text: { type: String, required: true },
    position: { type: String, default: 'top' },
});
</script>

<template>
    <Tooltip :text="text" wrap :position="position" size="sm" class="hint-icon">
        <button type="button" class="hint-icon-trigger" aria-label="Yardım" tabindex="0">
            <CircleHelp :size="13" />
        </button>
    </Tooltip>
</template>

<style scoped>
.hint-icon { vertical-align: middle; margin-left: 4px; }
.hint-icon-trigger {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0;
    border: none;
    background: none;
    color: var(--color-muted);
    cursor: help;
    line-height: 0;
    transition: color .15s;
}
.hint-icon-trigger:hover,
.hint-icon-trigger:focus-visible {
    color: var(--color-primary);
    outline: none;
}
</style>
