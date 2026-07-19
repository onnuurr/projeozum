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
				<Link href="/" class="portal-nav-link" :class="{ active: isActive('/') }">
					<svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 11.5 12 4l9 7.5M5 10v9a1 1 0 0 0 1 1h4v-6h4v6h4a1 1 0 0 0 1-1v-9" /></svg>
					<span>Pano</span>
				</Link>
				<Link href="/catalog" class="portal-nav-link" :class="{ active: page.url.startsWith('/catalog') }">
					<svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7.5 12 4l8 3.5M4 7.5 12 11m-8-3.5v9L12 21m0-10 8-3.5m-8 3.5v10m8-10v9l-8 1" /></svg>
					<span>Katalog</span>
				</Link>
				<Link href="/checkout" class="portal-nav-link" :class="{ active: page.url.startsWith('/checkout') }">
					<svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 4h2l2.4 12.2a2 2 0 0 0 2 1.8h7.2a2 2 0 0 0 2-1.6L20 8H6" /><circle cx="9.5" cy="20" r="1.4" fill="currentColor" stroke="none" /><circle cx="17" cy="20" r="1.4" fill="currentColor" stroke="none" /></svg>
					<span>Sepet</span>
				</Link>
				<Link href="/orders" class="portal-nav-link" :class="{ active: page.url.startsWith('/orders') }">
					<svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M8 4h9a1 1 0 0 1 1 1v15l-3-2-2.5 2-2.5-2-2.5 2V5a1 1 0 0 1 1-1h-1Z" /><path stroke-linecap="round" d="M9 9h6M9 12h6" /></svg>
					<span>Siparişlerim</span>
				</Link>
				<Link href="/invoices" class="portal-nav-link" :class="{ active: page.url.startsWith('/invoices') }">
					<svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M6 3h9l4 4v13a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1Z" /><path stroke-linecap="round" d="M9 12h6M9 15.5h6" /></svg>
					<span>Faturalarım</span>
				</Link>
				<Link href="/credit" class="portal-nav-link" :class="{ active: page.url.startsWith('/credit') }">
					<svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="6" width="18" height="13" rx="2" /><path stroke-linecap="round" d="M3 10h18M7 15h4" /></svg>
					<span>Kredi</span>
				</Link>
				<Link href="/marketplace" class="portal-nav-link" :class="{ active: page.url.startsWith('/marketplace') }">
					<svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="8.5" /><path stroke-linecap="round" d="M3.5 12h17M12 3.5c2.2 2.3 3.4 5.3 3.4 8.5s-1.2 6.2-3.4 8.5c-2.2-2.3-3.4-5.3-3.4-8.5S9.8 5.8 12 3.5Z" /></svg>
					<span>Pazaryerleri</span>
				</Link>
				<Link href="/financials" class="portal-nav-link" :class="{ active: page.url.startsWith('/financials') }">
					<svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 20V4m0 16h16M8 16v-4m4.5 4V8M17 16v-7" /></svg>
					<span>Kâr/Zarar</span>
				</Link>
				<Link href="/profit" class="portal-nav-link" :class="{ active: page.url.startsWith('/profit') }">
					<svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="5" y="3" width="14" height="18" rx="2" /><path stroke-linecap="round" d="M8 7h8M8 11h1.5M12 11h1.5M16 11h0M8 14.5h1.5M12 14.5h1.5M16 14.5h0M8 18h1.5M12 18h1.5M16 18h0" /></svg>
					<span>Kâr Hesabı</span>
				</Link>
				<Link
					v-if="page.props.auth.permissions.includes('portal.users.manage')"
					href="/users"
					class="portal-nav-link"
					:class="{ active: page.url.startsWith('/users') }"
				>
					<svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="9" cy="8" r="3.2" /><path stroke-linecap="round" d="M3.5 19c.6-3.2 2.8-5 5.5-5s4.9 1.8 5.5 5M15.5 8.2a3 3 0 1 1 3.7 2.9M16.5 14c2.2.4 3.6 1.9 4 4.6" /></svg>
					<span>Kullanıcılar</span>
				</Link>
				<a href="#" class="portal-nav-link disabled">
					<svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="2.8" /><path stroke-linecap="round" stroke-linejoin="round" d="m19.4 13.5.9.5a1 1 0 0 1 .4 1.3l-1 1.7a1 1 0 0 1-1.2.4l-1-.4c-.5.4-1 .8-1.6 1l-.2 1.1a1 1 0 0 1-1 .9h-2a1 1 0 0 1-1-.9l-.2-1.1a6 6 0 0 1-1.6-1l-1 .4a1 1 0 0 1-1.2-.4l-1-1.7a1 1 0 0 1 .4-1.3l.9-.5a6 6 0 0 1 0-1.9l-.9-.5a1 1 0 0 1-.4-1.3l1-1.7a1 1 0 0 1 1.2-.4l1 .4c.5-.4 1-.8 1.6-1l.2-1.1a1 1 0 0 1 1-.9h2a1 1 0 0 1 1 .9l.2 1.1c.6.2 1.1.6 1.6 1l1-.4a1 1 0 0 1 1.2.4l1 1.7a1 1 0 0 1-.4 1.3l-.9.5a6 6 0 0 1 0 1.9Z" /></svg>
					<span>Ayarlar</span>
				</a>
			</nav>
			<div class="portal-footer">
				<form method="POST" action="/logout">
					<input type="hidden" name="_token" :value="csrf" />
					<button type="submit" class="portal-logout">
						<svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17.5V19a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h7a2 2 0 0 1 2 2v1.5M9 12h11.5m0 0-3.5-3.5m3.5 3.5-3.5 3.5" /></svg>
						<span>Çıkış</span>
					</button>
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
.portal-shell { display: grid; grid-template-columns: 232px 1fr; min-height: 100vh; background: rgb(var(--portal-bg)); }

.portal-sidebar { background: rgb(var(--portal-sidebar-bg)); color: rgb(var(--portal-sidebar-text)); padding: 20px 14px; display: flex; flex-direction: column; gap: 22px; }

.portal-brand { display: flex; align-items: center; gap: 12px; padding: 0 6px 18px; border-bottom: 1px solid rgb(var(--portal-sidebar-border)); }
.portal-brand-mark { flex-shrink: 0; width: 38px; height: 38px; border-radius: 10px; background: rgb(var(--portal-accent)); display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 16px; color: #fff; }
.portal-brand-info { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
.portal-brand-name { font-weight: 700; font-size: 14px; color: #fff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.portal-brand-code { font-size: 11px; color: rgb(var(--portal-sidebar-text-dim)); font-family: 'SF Mono', Menlo, Consolas, monospace; }

.portal-nav { display: flex; flex-direction: column; gap: 2px; }
.portal-nav-link { display: flex; align-items: center; gap: 10px; padding: 8px 10px; border-radius: 8px; font-size: 13.5px; font-weight: 500; color: rgb(var(--portal-sidebar-text)); text-decoration: none; transition: background .15s, color .15s; }
.portal-nav-link:hover { background: rgb(var(--portal-sidebar-hover)); color: #fff; }
.portal-nav-link.active { background: rgb(var(--portal-accent)); color: #fff; font-weight: 600; }
.portal-nav-link.disabled { color: rgb(var(--portal-sidebar-text-dim)); cursor: not-allowed; pointer-events: none; }
.nav-icon { width: 17px; height: 17px; flex-shrink: 0; }

.portal-footer { margin-top: auto; padding-top: 14px; border-top: 1px solid rgb(var(--portal-sidebar-border)); }
.portal-logout { width: 100%; display: flex; align-items: center; gap: 10px; padding: 8px 10px; border-radius: 8px; background: transparent; border: none; color: rgb(var(--portal-sidebar-text-dim)); font-size: 13.5px; font-weight: 500; cursor: pointer; }
.portal-logout:hover { background: rgb(var(--portal-sidebar-hover)); color: #fff; }

.portal-main { padding: 26px 32px; min-width: 0; }

@media (max-width: 768px) {
	.portal-shell { grid-template-columns: 1fr; }
	.portal-sidebar { flex-direction: row; flex-wrap: wrap; align-items: center; padding: 10px 12px; gap: 10px; }
	.portal-brand { padding: 0 6px 0 0; border-bottom: none; border-right: 1px solid rgb(var(--portal-sidebar-border)); }
	.portal-nav { flex-direction: row; flex-wrap: wrap; gap: 4px; }
	.portal-nav-link span { display: none; }
	.portal-nav-link { padding: 8px; }
	.portal-footer { margin-top: 0; padding-top: 0; border-top: none; }
	.portal-logout span { display: none; }
	.portal-main { padding: 16px; }
}
</style>
