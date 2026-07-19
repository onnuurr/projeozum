<template>
	<div class="finance-nav-wrap">
		<button
			type="button"
			class="fn-mobile-toggle"
			:class="{ open: mobileNavOpen }"
			@click="mobileNavOpen = !mobileNavOpen"
		>
			<span class="fn-mobile-toggle-label">{{ activeTab.label }}</span>
			<svg class="fn-burger" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
				<line x1="3" y1="6" x2="21" y2="6"/>
				<line x1="3" y1="12" x2="21" y2="12"/>
				<line x1="3" y1="18" x2="21" y2="18"/>
			</svg>
		</button>
		<div v-if="mobileNavOpen" class="fn-mobile-backdrop" @click="mobileNavOpen = false"></div>
		<nav class="finance-nav" :class="{ 'fn-mobile-open': mobileNavOpen }">
			<Link
				v-for="tab in tabs"
				:key="tab.href"
				:href="tab.href"
				class="fn-tab"
				:class="{ active: current === tab.key }"
				@click="mobileNavOpen = false"
			>
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
	{ key: 'dashboard', label: 'Genel Bakış', href: '/finance' },
	{ key: 'product-costs', label: 'Ürün Maliyetleri', href: '/finance/product-costs' },
	{ key: 'sales', label: 'Satış Geçmişi', href: '/finance/sales' },
	{ key: 'tenant-purchases', label: 'Bayi Alışverişleri', href: '/finance/tenant-purchases' },
	{ key: 'supplier-invoices', label: 'Alınan Faturalar', href: '/finance/supplier-invoices' },
	{ key: 'proformas', label: 'Proforma Faturalar', href: '/finance/proformas' },
	{ key: 'outgoing-invoices', label: 'Düzenlenen Faturalar', href: '/finance/outgoing-invoices' },
	{ key: 'bank', label: 'Banka', href: '/finance/bank-accounts' },
]

const activeTab = computed(() => tabs.find((tab) => tab.key === props.current) ?? tabs[0])
</script>

<style scoped>
.finance-nav-wrap { position: relative; }
.finance-nav { display: flex; gap: 4px; margin-bottom: 18px; background: #fff; border: 1px solid #ebebf0; border-radius: 12px; padding: 5px; box-shadow: 0 1px 4px rgba(0,0,0,.04); flex-wrap: wrap; }
.fn-tab { display: flex; align-items: center; padding: 8px 14px; border-radius: 8px; font-size: 13px; font-weight: 600; color: #888; text-decoration: none; transition: all .15s; }
.fn-tab:hover { background: rgb(var(--color-primary-soft)); color: #555; }
.fn-tab.active { background: rgb(var(--color-primary)); color: #fff; }
.fn-mobile-toggle { display: none; }
.fn-mobile-backdrop { display: none; }

/* ── Dar ekran (telefon): üst menü hamburger'a dönüşür ── */
@media (max-width: 640px) {
	.fn-mobile-toggle {
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
		margin-bottom: 12px;
	}
	.fn-mobile-toggle-label { flex: 1; text-align: left; }
	.fn-burger { flex-shrink: 0; transition: transform .15s; }
	.fn-mobile-toggle.open .fn-burger { transform: rotate(90deg); }

	.fn-mobile-backdrop {
		display: block;
		position: fixed;
		inset: 0;
		background: rgba(0,0,0,.25);
		z-index: 30;
	}

	.finance-nav {
		display: none;
		flex-direction: column;
		flex-wrap: nowrap;
		position: absolute;
		top: 100%;
		left: 0;
		right: 0;
		margin-top: -8px;
		z-index: 40;
		box-shadow: 0 8px 24px rgba(0,0,0,.14);
		gap: 2px;
		padding: 4px;
	}
	.finance-nav.fn-mobile-open { display: flex; }
	.fn-tab { padding: 8px 12px; font-size: 13px; justify-content: flex-start; }
}
</style>
