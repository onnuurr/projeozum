<script setup>
defineProps({
    provider: { type: String, default: 'google' },
});

const emit = defineEmits(['click']);
</script>

<template>
    <button class="btn-social" type="button" @click="emit('click', $event)">
        <svg v-if="provider === 'google'" width="16" height="16" viewBox="0 0 48 48">
            <path fill="#FFC107" d="M43.6 20.5H42V20H24v8h11.3C33.6 32.4 29.2 35 24 35c-6.1 0-11-4.9-11-11s4.9-11 11-11c2.8 0 5.4 1 7.4 2.7l5.7-5.7C33.5 6.6 28.9 5 24 5 13.5 5 5 13.5 5 24s8.5 19 19 19c10.5 0 19-8.5 19-19 0-1.2-.1-2.3-.4-3.5z" />
            <path fill="#FF3D00" d="M6.3 14.7l6.6 4.8C14.7 15.7 19 13 24 13c2.8 0 5.4 1 7.4 2.7l5.7-5.7C33.5 6.6 28.9 5 24 5 17 5 10.8 8.8 6.3 14.7z" />
            <path fill="#4CAF50" d="M24 43c4.8 0 9.2-1.6 12.6-4.4l-5.8-4.9C28.9 35 26.5 36 24 36c-5.2 0-9.6-3.4-11.2-8l-6.5 5C9.6 39.1 16.2 43 24 43z" />
            <path fill="#1976D2" d="M43.6 20.5H42V20H24v8h11.3c-.7 2.1-2 3.9-3.7 5.3l5.8 4.9C40.9 34 43 29.4 43 24c0-1.2-.1-2.3-.4-3.5z" />
        </svg>
        <svg v-else-if="provider === 'microsoft'" width="16" height="16" viewBox="0 0 24 24">
            <path fill="#F25022" d="M2 2h9.5v9.5H2z" />
            <path fill="#7FBA00" d="M12.5 2H22v9.5h-9.5z" />
            <path fill="#00A4EF" d="M2 12.5h9.5V22H2z" />
            <path fill="#FFB900" d="M12.5 12.5H22V22h-9.5z" />
        </svg>
        <slot>{{ provider.charAt(0).toUpperCase() + provider.slice(1) }}</slot>
    </button>
</template>
