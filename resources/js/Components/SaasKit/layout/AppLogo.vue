<!--
	saas-frontend components/layout/AppLogo.vue portu — logic değişmedi, sadece
	SVG gradient/accent renkleri saas-frontend'in turuncu kimliğinden bu projenin
	teal marka renklerine (--color-primary #1E7787, --color-primary-soft #E6F2F4)
	çevrildi (Tailwind class değil, ham SVG stop-color/fill olduğu için elle).
-->
<script setup>
import { computed } from 'vue';

const props = defineProps({
    appName: {
        type: String,
        default: 'OzumServer',
    },
});

const nameParts = computed(() => {
    const idx = props.appName.indexOf('Server');
    if (idx > 0) {
        return { first: props.appName.slice(0, idx), rest: props.appName.slice(idx) };
    }
    return { first: props.appName, rest: '' };
});
</script>

<template>
    <div class="flex items-center gap-2 sm:gap-2.5 select-none">
        <svg width="30" height="30" viewBox="0 0 34 34" class="flex-shrink-0 sm:w-[34px] sm:h-[34px]" aria-hidden="true">
            <rect width="34" height="34" rx="9" fill="url(#saaskit-logo-gradient)" />
            <circle cx="17" cy="17" r="7" fill="none" stroke="white" stroke-width="3" stroke-linecap="round" />
            <circle cx="24.5" cy="10" r="2.5" fill="#E6F2F4" />
            <defs>
                <linearGradient id="saaskit-logo-gradient" x1="0" y1="34" x2="34" y2="0" gradientUnits="userSpaceOnUse">
                    <stop stop-color="#155A66" />
                    <stop offset="1" stop-color="#8FD6DE" />
                </linearGradient>
            </defs>
        </svg>
        <span class="text-lg sm:text-xl font-black tracking-tight leading-none">
            <span class="text-white">{{ nameParts.first }}</span><span v-if="nameParts.rest" class="text-white/55 font-semibold">{{ nameParts.rest }}</span>
        </span>
    </div>
</template>
