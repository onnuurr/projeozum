<template>
	<div class="creative-nav-wrap">
		<button
			type="button"
			class="cn-mobile-toggle"
			:class="{ open: mobileNavOpen }"
			@click="mobileNavOpen = !mobileNavOpen"
		>
			<span class="cn-icon" v-html="activeTab.icon"></span>
			<span class="cn-mobile-toggle-label">{{ activeTab.label }}</span>
			<svg class="cn-burger" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
				<line x1="3" y1="6" x2="21" y2="6"/>
				<line x1="3" y1="12" x2="21" y2="12"/>
				<line x1="3" y1="18" x2="21" y2="18"/>
			</svg>
		</button>
		<div v-if="mobileNavOpen" class="cn-mobile-backdrop" @click="mobileNavOpen = false"></div>
		<nav class="creative-nav" :class="{ 'cn-mobile-open': mobileNavOpen }">
			<Link
				v-for="tab in tabs"
				:key="tab.href"
				:href="tab.href"
				class="cn-tab"
				:class="{ active: current === tab.key }"
				@click="mobileNavOpen = false"
			>
				<span class="cn-icon" v-html="tab.icon"></span>
				{{ tab.label }}
			</Link>
		</nav>
	</div>
</template>

<script setup>
import { computed, ref } from 'vue'
import { Link } from '@inertiajs/vue3'

const props = defineProps({
	current: { type: String, required: true },
})

const mobileNavOpen = ref(false)

const tabs = [
	{
		key: 'studio',
		label: 'Stüdyo',
		href: '/creative/studio',
		icon: '<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 3l14 9-14 9V3z"/></svg>',
	},
	{
		key: 'gallery',
		label: 'Galeri',
		href: '/creative/gallery',
		icon: '<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>',
	},
	{
		key: 'mannequins',
		label: 'Sanal Manken',
		href: '/creative/mannequins',
		icon: '<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="6" r="3"/><path d="M12 9v7M8 21l4-5 4 5M6 12h12"/></svg>',
	},
	{
		key: 'poses',
		label: 'Pozlar',
		href: '/creative/poses',
		icon: '<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="4" r="2"/><path d="M12 6v6m0 0l-4 8m4-8l4 8M6 9l6 1 6-1"/></svg>',
	},
	{
		key: 'tryon',
		label: 'Ürün Giydirme',
		href: '/creative/tryon',
		icon: '<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20.4 14.5L16 10 4 22M3 7l4-4 4 4M7 3v12"/></svg>',
	},
	{
		key: 'garment-scans',
		label: 'Parça Tespiti',
		href: '/creative/garment-scans',
		icon: '<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="4" y="4" width="16" height="16" rx="2"/><path d="M9 4v16M4 9h5M4 15h5"/></svg>',
	},
	{
		key: 'templates',
		label: 'Şablonlar',
		href: '/creative/templates',
		icon: '<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/></svg>',
	},
	{
		key: 'brandkits',
		label: 'Marka Kiti',
		href: '/creative/brandkits',
		icon: '<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="13.5" cy="6.5" r="2.5"/><circle cx="17.5" cy="10.5" r="2.5"/><circle cx="8.5" cy="7.5" r="2.5"/><circle cx="6.5" cy="12.5" r="2.5"/><path d="M12 2a10 10 0 100 20c1.1 0 2-.9 2-2 0-.5-.2-1-.5-1.3-.3-.4-.5-.8-.5-1.2 0-1.1.9-2 2-2h2.3c2.6 0 4.7-2.1 4.7-4.7C24 5.8 18.6 2 12 2z"/></svg>',
	},
]

const activeTab = computed(() => tabs.find((tab) => tab.key === props.current) ?? tabs[0])
</script>

<style scoped>
.creative-nav-wrap { position: relative; }
.creative-nav { display: flex; gap: 4px; margin-bottom: 18px; background: #fff; border: 1px solid #ebebf0; border-radius: 12px; padding: 5px; box-shadow: 0 1px 4px rgba(0,0,0,.04); overflow-x: auto; -webkit-overflow-scrolling: touch; }
.cn-tab { display: flex; align-items: center; gap: 7px; padding: 8px 14px; border-radius: 8px; font-size: 13px; font-weight: 600; color: #888; text-decoration: none; transition: all .15s; flex-shrink: 0; white-space: nowrap; }
.cn-tab:hover { background: rgb(var(--color-primary-soft)); color: #555; }
.cn-tab.active { background: rgb(var(--color-primary)); color: #fff; }
.cn-icon { display: inline-flex; flex-shrink: 0; }
.cn-mobile-toggle { display: none; }
.cn-mobile-backdrop { display: none; }

@media (max-width: 640px) {
	.cn-mobile-toggle {
		display: flex;
		align-items: center;
		gap: 8px;
		width: 100%;
		padding: 8px 12px;
		border-radius: 10px;
		border: 1px solid #ebebf0;
		background: #fff;
		font-size: 13px;
		font-weight: 600;
		color: #444;
		box-shadow: 0 1px 4px rgba(0,0,0,.04);
		margin-bottom: 18px;
	}
	.cn-mobile-toggle-label { flex: 1; text-align: left; }
	.cn-burger { flex-shrink: 0; transition: transform .15s; }
	.cn-mobile-toggle.open .cn-burger { transform: rotate(90deg); }

	.cn-mobile-backdrop {
		display: block;
		position: fixed;
		inset: 0;
		background: rgba(0,0,0,.25);
		z-index: 30;
	}

	.creative-nav {
		display: none;
		flex-direction: column;
		flex-wrap: nowrap;
		overflow-x: visible;
		position: absolute;
		top: 100%;
		left: 0;
		right: 0;
		margin-top: -14px;
		z-index: 40;
		box-shadow: 0 8px 24px rgba(0,0,0,.14);
		gap: 3px;
		padding: 4px;
	}
	.creative-nav.cn-mobile-open { display: flex; }
	.cn-tab { padding: 8px 12px; font-size: 13px; gap: 8px; justify-content: flex-start; white-space: normal; }
}
</style>
