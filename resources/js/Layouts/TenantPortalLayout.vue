<template>
	<div class="portal-shell">
		<aside class="portal-sidebar">
			<div class="portal-brand">
				<span class="portal-brand-mark">{{ tenantInitial }}</span>
				<div class="portal-brand-info">
					<div class="portal-brand-name">{{ tenant?.name ?? 'Portal' }}</div>
					<div class="portal-brand-code">{{ tenant?.code }}</div>
				</div>
			</div>
			<nav class="portal-nav">
				<Link href="/" class="portal-nav-link" :class="{ active: isActive('/') }">Pano</Link>
				<Link href="/catalog" class="portal-nav-link" :class="{ active: page.url.startsWith('/catalog') }">Katalog</Link>
				<Link href="/checkout" class="portal-nav-link" :class="{ active: page.url.startsWith('/checkout') }">Sepet</Link>
				<Link href="/orders" class="portal-nav-link" :class="{ active: page.url.startsWith('/orders') }">Siparişlerim</Link>
				<Link href="/invoices" class="portal-nav-link" :class="{ active: page.url.startsWith('/invoices') }">Faturalarım</Link>
				<Link href="/credit" class="portal-nav-link" :class="{ active: page.url.startsWith('/credit') }">Kredi</Link>
				<Link href="/marketplace" class="portal-nav-link" :class="{ active: page.url.startsWith('/marketplace') }">Pazaryerleri</Link>
				<!-- Phase 4 ile aktive edilecek -->
				<a href="#" class="portal-nav-link disabled">Kâr Hesabı</a>
				<a href="#" class="portal-nav-link disabled">Ayarlar</a>
			</nav>
			<div class="portal-footer">
				<form method="POST" action="/logout">
					<input type="hidden" name="_token" :value="csrf" />
					<button type="submit" class="portal-logout">Çıkış</button>
				</form>
			</div>
		</aside>
		<main class="portal-main">
			<slot />
		</main>
	</div>
</template>

<script setup>
import { computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'

const page = usePage()

const tenant = computed(() => page.props.tenant ?? page.props.auth?.tenant ?? null)
const tenantInitial = computed(() => (tenant.value?.name ?? '?').charAt(0).toUpperCase())
const csrf = computed(() => page.props.csrf_token ?? '')

function isActive(path) {
	return page.url === path
}
</script>

<style scoped>
.portal-shell { display: grid; grid-template-columns: 240px 1fr; min-height: 100vh; background: #f7f7fb; }
.portal-sidebar { background: #1a1a2e; color: #f0f0f5; padding: 20px 16px; display: flex; flex-direction: column; gap: 24px; }
.portal-brand { display: flex; align-items: center; gap: 12px; padding-bottom: 16px; border-bottom: 1px solid #2a2a44; }
.portal-brand-mark { width: 40px; height: 40px; border-radius: 12px; background: linear-gradient(135deg, #4338ca, #6366f1); display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 18px; color: #fff; }
.portal-brand-info { display: flex; flex-direction: column; gap: 2px; }
.portal-brand-name { font-weight: 700; font-size: 14px; color: #fff; }
.portal-brand-code { font-size: 11px; color: #a0a0c0; font-family: 'SF Mono', Menlo, Consolas, monospace; }
.portal-nav { display: flex; flex-direction: column; gap: 4px; }
.portal-nav-link { padding: 9px 12px; border-radius: 8px; font-size: 13px; color: #c0c0d8; text-decoration: none; transition: background .15s; }
.portal-nav-link:hover { background: #2a2a44; color: #fff; }
.portal-nav-link.active { background: #4338ca; color: #fff; font-weight: 600; }
.portal-nav-link.disabled { color: #5a5a78; cursor: not-allowed; pointer-events: none; }
.portal-footer { margin-top: auto; }
.portal-logout { width: 100%; padding: 9px 12px; border-radius: 8px; background: transparent; border: 1px solid #2a2a44; color: #c0c0d8; font-size: 13px; cursor: pointer; }
.portal-logout:hover { background: #2a2a44; color: #fff; }
.portal-main { padding: 24px 32px; }
</style>
