<template>
	<nav class="atelier-nav">
		<Link
			v-for="tab in tabs"
			:key="tab.href"
			:href="tab.href"
			class="an-tab"
			:class="{ active: current === tab.key }"
		>
			<span class="an-icon" v-html="tab.icon"></span>
			{{ tab.label }}
		</Link>
	</nav>
</template>

<script setup>
import { Link } from '@inertiajs/vue3'

defineProps({
	current: { type: String, required: true },
})

const tabs = [
	{
		key: 'dashboard',
		label: 'Panel',
		href: '/atelier',
		icon: '<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/></svg>',
	},
	{
		key: 'conversions',
		label: 'Sayısallaştırma',
		href: '/atelier/conversions',
		icon: '<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><path d="M14 2v6h6"/><path d="M12 18v-6"/><path d="M9 15l3 3 3-3"/></svg>',
	},
	{
		key: 'patterns',
		label: 'Kalıp Kütüphanesi',
		href: '/atelier/patterns',
		icon: '<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>',
	},
	{
		key: 'concepts',
		label: 'Konsept Stüdyosu',
		href: '/atelier/concepts',
		icon: '<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 3l1.5 3.5L10 8 6.5 9.5 5 13 3.5 9.5 0 8l3.5-1.5z" transform="translate(2 1)"/><path d="M18 11l1 2.5 2.5 1-2.5 1L18 18l-1-2.5-2.5-1 2.5-1z"/><path d="M13 3l.8 2 2 .8-2 .8L13 8.6l-.8-2-2-.8 2-.8z"/></svg>',
	},
	{
		key: 'assignments',
		label: 'Atölye İşleri',
		href: '/atelier/assignments',
		icon: '<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 11l3 3 5-5"/><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M8 4V2M16 4V2"/></svg>',
	},
	{
		key: 'orders',
		label: 'İş Emirleri',
		href: '/atelier/production-orders',
		icon: '<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>',
	},
	{
		key: 'materials',
		label: 'Hammaddeler',
		href: '/atelier/materials',
		icon: '<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>',
	},
	{
		key: 'boms',
		label: 'Reçeteler',
		href: '/atelier/boms',
		icon: '<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><path d="M14 2v6h6M9 13h6M9 17h6M9 9h1"/></svg>',
	},
	{
		key: 'operations',
		label: 'Operasyonlar',
		href: '/atelier/operations',
		icon: '<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 11-2.83 2.83l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 11-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 11-2.83-2.83l.06-.06a1.65 1.65 0 00.33-1.82 1.65 1.65 0 00-1.51-1H3a2 2 0 110-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 112.83-2.83l.06.06a1.65 1.65 0 001.82.33H9a1.65 1.65 0 001-1.51V3a2 2 0 114 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 112.83 2.83l-.06.06a1.65 1.65 0 00-.33 1.82V9a1.65 1.65 0 001.51 1H21a2 2 0 110 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg>',
	},
	{
		key: 'fason',
		label: 'Fasoncular',
		href: '/atelier/fason-suppliers',
		icon: '<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>',
	},
]
</script>

<style scoped>
.atelier-nav { display: flex; gap: 4px; margin-bottom: 18px; background: #fff; border: 1px solid #ebebf0; border-radius: 12px; padding: 5px; box-shadow: 0 1px 4px rgba(0,0,0,.04); flex-wrap: wrap; }
.an-tab { display: flex; align-items: center; gap: 7px; padding: 8px 14px; border-radius: 8px; font-size: 13px; font-weight: 600; color: #888; text-decoration: none; transition: all .15s; }
.an-tab:hover { background: rgb(var(--color-primary-soft)); color: #555; }
.an-tab.active { background: rgb(var(--color-primary)); color: #fff; }
.an-icon { display: inline-flex; }
</style>
