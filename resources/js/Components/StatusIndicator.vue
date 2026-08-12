<script setup>
defineProps({
    status: { type: String, default: 'online', validator: (v) => ['online', 'offline', 'away', 'busy', 'active', 'maintenance', 'error', 'updating'].includes(v) },
    label: { type: String, default: '' },
});

const statusConfig = {
    online: { dot: 'bg-success', defaultLabel: 'Çevrimiçi' },
    offline: { dot: 'bg-gray-400', defaultLabel: 'Çevrimdışı' },
    away: { dot: 'bg-warning', defaultLabel: 'Uzakta' },
    busy: { dot: 'bg-danger', defaultLabel: 'Meşgul' },
    active: { dot: 'bg-success', defaultLabel: 'Sistem Aktif' },
    maintenance: { dot: 'bg-warning', defaultLabel: 'Bakımda' },
    error: { dot: 'bg-danger', defaultLabel: 'Hata' },
    updating: { dot: 'bg-info', defaultLabel: 'Güncelleniyor' },
};
</script>

<template>
    <div class="inline-flex items-center gap-2 p-2 bg-canvas rounded-lg">
        <span class="relative flex h-2.5 w-2.5">
            <span :class="['animate-ping absolute inline-flex h-full w-full rounded-full opacity-75', statusConfig[status].dot]" />
            <span :class="['relative inline-flex rounded-full h-2.5 w-2.5', statusConfig[status].dot]" />
        </span>
        <span class="text-xs text-ink">{{ label || statusConfig[status].defaultLabel }}</span>
    </div>
</template>
