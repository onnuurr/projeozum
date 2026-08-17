<template>
	<div class="creative-nav-wrap">
		<button
			type="button"
			class="cn-mobile-toggle"
			:class="{ open: mobileNavOpen }"
			@click="mobileNavOpen = !mobileNavOpen"
		>
			<span class="cn-icon"><component :is="activeTab.icon" :size="14" /></span>
			<span class="cn-mobile-toggle-label">{{ activeTab.label }}</span>
			<Menu class="cn-burger" :size="16" />
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
				<span class="cn-icon"><component :is="tab.icon" :size="14" /></span>
				{{ tab.label }}
			</Link>
		</nav>
	</div>
</template>

<script setup>
import { computed, ref } from 'vue'
import { Link } from '@inertiajs/vue3'
import { Menu, Play, LayoutGrid, PersonStanding, Footprints, Shirt, ScanSearch, LayoutTemplate, Palette } from 'lucide-vue-next'

const props = defineProps({
	current: { type: String, required: true },
})

const mobileNavOpen = ref(false)

const tabs = [
	{ key: 'studio',        label: 'Stüdyo',        href: '/creative/studio',        icon: Play },
	{ key: 'gallery',       label: 'Galeri',         href: '/creative/gallery',       icon: LayoutGrid },
	{ key: 'mannequins',    label: 'Sanal Manken',   href: '/creative/mannequins',    icon: PersonStanding },
	{ key: 'poses',         label: 'Pozlar',         href: '/creative/poses',         icon: Footprints },
	{ key: 'tryon',         label: 'Ürün Giydirme',  href: '/creative/tryon',         icon: Shirt },
	{ key: 'garment-scans', label: 'Parça Tespiti',  href: '/creative/garment-scans', icon: ScanSearch },
	{ key: 'templates',     label: 'Şablonlar',      href: '/creative/templates',     icon: LayoutTemplate },
	{ key: 'brandkits',     label: 'Marka Kiti',     href: '/creative/brandkits',     icon: Palette },
]

const activeTab = computed(() => tabs.find((tab) => tab.key === props.current) ?? tabs[0])
</script>

<style scoped>
.creative-nav-wrap { position: relative; }
.creative-nav { display: flex; gap: 4px; margin-bottom: 18px; background: var(--color-surface); border: 1px solid var(--color-outline-variant); border-radius: 12px; padding: 5px; box-shadow: 0 1px 4px rgba(0,0,0,.04); overflow-x: auto; -webkit-overflow-scrolling: touch; }
.cn-tab { display: flex; align-items: center; gap: 7px; padding: 8px 14px; border-radius: 8px; font-size: 13px; font-weight: 600; color: var(--color-muted); text-decoration: none; transition: all .15s; flex-shrink: 0; white-space: nowrap; }
.cn-tab:hover { background: var(--color-primary-soft); color: var(--color-ink); }
.cn-tab.active { background: var(--color-primary); color: var(--color-on-primary); }
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
		border: 1px solid var(--color-outline-variant);
		background: var(--color-surface);
		font-size: 13px;
		font-weight: 600;
		color: var(--color-ink);
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
