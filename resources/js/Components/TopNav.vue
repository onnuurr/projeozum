<template>
	<nav class="top-nav">
		<div class="logo">
			<div class="logo-icon">
				<svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="2.5">
					<path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5" />
				</svg>
			</div>
		</div>

		<div class="nav-scroll-wrapper" ref="scrollWrapper">
			<button
				class="nav-scroll-btn"
				:class="{ 'nav-btn-hidden': scrollAtStart }"
				@click="scrollNav(-120)"
				@mouseenter="startHoverScroll(-3)"
				@mouseleave="stopHoverScroll"
			>&#8249;</button>
			<div class="nav-links" ref="navLinks" @scroll="updateScrollArrows">
				<div
					v-for="item in navItems"
					:key="item.name"
					class="nav-item"
					ref="navItemEls"
					@mouseenter="openDropdown(item)"
					@mouseleave="scheduleClose"
				>
					<Link
						v-if="item.to"
						:href="item.to"
						class="nav-link"
						:class="{ active: item.active }"
					>{{ item.name }}</Link>
					<a v-else href="#" class="nav-link" :class="{ active: item.active }" @click.prevent>{{ item.name }}</a>
					<div
						class="nav-dropdown"
						:class="{ open: activeDropdown === item.name }"
						:style="dropdownStyle(item)"
						@mouseenter="cancelClose"
						@mouseleave="scheduleClose"
					>
						<template v-for="child in item.children" :key="child.label">
							<Link v-if="child.to" :href="child.to">{{ child.label }}</Link>
							<a v-else href="#" @click.prevent>{{ child.label }}</a>
						</template>
					</div>
				</div>
			</div>
			<button
				class="nav-scroll-btn"
				:class="{ 'nav-btn-hidden': scrollAtEnd }"
				@click="scrollNav(120)"
				@mouseenter="startHoverScroll(3)"
				@mouseleave="stopHoverScroll"
			>&#8250;</button>
		</div>

		<div class="nav-actions">
			<div class="nav-search" @click="$emit('open-search')">
				<svg width="13" height="13" fill="none" stroke="#aaa" stroke-width="2" viewBox="0 0 24 24">
					<circle cx="11" cy="11" r="8" /><path d="M21 21l-4.35-4.35" />
				</svg>
				<span class="nav-search-placeholder">Ara...</span>
				<kbd class="nav-search-kbd">Ctrl K</kbd>
			</div>

			<button class="nav-icon-btn nav-icon-message" title="Mesajlar">
				<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
					<path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z" />
				</svg>
			</button>

			<button
				class="nav-icon-btn cart-btn"
				:class="{ bump: cartBump }"
				title="Sepetim"
				@click.stop="$emit('open-cart')"
			>
				<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
					<circle cx="9" cy="21" r="1" />
					<circle cx="20" cy="21" r="1" />
					<path d="M1 1h4l2.68 13.39a2 2 0 002 1.61h9.72a2 2 0 002-1.61L23 6H6" />
				</svg>
				<span v-if="cartItemCount > 0" class="badge cart-badge">{{ cartItemCount > 99 ? '99+' : cartItemCount }}</span>
			</button>

			<div class="notif-wrap" ref="notifWrap">
				<button class="nav-icon-btn" title="Bildirimler" @click.stop="toggleNotif">
					<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
						<path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9M13.73 21a2 2 0 01-3.46 0" />
					</svg>
					<span v-if="unreadCount > 0" class="badge">{{ unreadCount }}</span>
				</button>

				<div class="notif-panel" :class="{ open: notifOpen }" :style="notifPanelStyle">
					<div class="notif-panel-header">
						<h4>Bildirimler</h4>
						<button class="notif-mark-read" @click="$emit('mark-all-read')">Tümünü okundu işaretle</button>
					</div>
					<div class="notif-list">
						<template v-for="(group, gIdx) in groupedNotifications" :key="group.label">
							<div class="notif-section-label">{{ group.label }}</div>
							<template v-for="(n, idx) in group.items" :key="n.id">
								<div class="notif-item" :class="{ unread: !n.read }" @click="onNotifClick(n)">
									<div class="notif-icon" :class="`notif-icon-${n.iconType}`">{{ n.icon }}</div>
									<div class="notif-body">
										<div class="notif-title">{{ n.title }}</div>
										<div class="notif-desc">{{ n.desc }}</div>
										<div class="notif-time">{{ n.time }}</div>
									</div>
									<div v-if="!n.read" class="notif-unread-dot"></div>
								</div>
								<hr v-if="idx < group.items.length - 1 || gIdx < groupedNotifications.length - 1" class="notif-divider" />
							</template>
						</template>
					</div>
					<div class="notif-panel-footer">
						<button>Tüm bildirimleri gör</button>
					</div>
				</div>
			</div>

			<div class="nav-user-wrap" ref="userWrap">
				<div class="nav-user" :class="{ open: userOpen }" @click.stop="toggleUser">
					<div class="nav-user-avatar">{{ userInitials }}</div>
					<span class="nav-user-name">{{ user.shortName }}</span>
					<svg class="nav-user-chevron" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
						<path d="M6 9l6 6 6-6" />
					</svg>
				</div>

				<div class="user-dropdown" :class="{ open: userOpen }" :style="userDropdownStyle">
					<div class="ud-header">
						<div class="ud-avatar-lg">{{ userInitials }}</div>
						<div>
							<div class="ud-name">{{ user.name }}</div>
							<div class="ud-email">{{ user.email }}</div>
						</div>
					</div>
					<template
						v-for="(item, idx) in userMenu"
						:key="idx"
					>
						<hr v-if="item.divider" class="ud-divider" />
						<div v-else-if="item.heading" class="ud-heading">{{ item.heading }}</div>
						<div
							v-else
							class="ud-item"
							:class="{ danger: item.danger, 'demo-active': item.activeDemoRole }"
							@click="handleUserAction(item)"
							v-html="userMenuHtml(item)"
						></div>
					</template>
				</div>
			</div>
		</div>
	</nav>
</template>

<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount, nextTick } from 'vue'
import { Link } from '@inertiajs/vue3'

const props = defineProps({
	navItems: { type: Array, required: true },
	notifications: { type: Array, required: true },
	user: { type: Object, required: true },
	userMenu: { type: Array, required: true },
	cartItemCount: { type: Number, default: 0 },
})

const emit = defineEmits(['open-search', 'mark-all-read', 'read-notification', 'user-action', 'open-cart', 'notification-click'])

function onNotifClick(n) {
	emit('read-notification', n.id)
	if (n?.link) {
		notifOpen.value = false
		emit('notification-click', n)
	}
}

// Sepet sayısı değişince sepet ikonunda küçük bump animasyonu
const cartBump = ref(false)
let bumpTimer = null
watch(() => props.cartItemCount, (newVal, oldVal) => {
	if (newVal > oldVal) {
		cartBump.value = true
		clearTimeout(bumpTimer)
		bumpTimer = setTimeout(() => { cartBump.value = false }, 400)
	}
})

const navLinks = ref(null)
const notifWrap = ref(null)
const userWrap = ref(null)
const navItemEls = ref([])

const scrollAtStart = ref(true)
const scrollAtEnd = ref(false)
const notifOpen = ref(false)
const userOpen = ref(false)
const activeDropdown = ref(null)
const dropdownPositions = ref({})
const notifPanelStyle = ref({})
const userDropdownStyle = ref({})

let closeTimer = null
let hoverScrollTimer = null

const unreadCount = computed(() => props.notifications.filter((n) => !n.read).length)

const userInitials = computed(() => {
	return props.user.name
		.split(' ')
		.map((n) => n[0])
		.slice(0, 2)
		.join('')
		.toUpperCase()
})

const groupedNotifications = computed(() => {
	const unread = props.notifications.filter((n) => !n.read)
	const read = props.notifications.filter((n) => n.read)
	const groups = []
	if (unread.length) groups.push({ label: 'Yeni', items: unread })
	if (read.length) groups.push({ label: 'Daha Önce', items: read })
	return groups
})

function userMenuHtml(item) {
	return `${item.icon}<span>${item.label}</span>`
}

function handleUserAction(item) {
	emit('user-action', item)
	userOpen.value = false
}

function scrollNav(amount) {
	navLinks.value?.scrollBy({ left: amount, behavior: 'smooth' })
}

function startHoverScroll(step) {
	hoverScrollTimer = setInterval(() => navLinks.value?.scrollBy({ left: step }), 16)
}

function stopHoverScroll() {
	clearInterval(hoverScrollTimer)
}

function updateScrollArrows() {
	const el = navLinks.value
	if (!el) return
	scrollAtStart.value = el.scrollLeft <= 0
	scrollAtEnd.value = el.scrollLeft + el.clientWidth >= el.scrollWidth - 1
}

function openDropdown(item) {
	cancelClose()
	activeDropdown.value = item.name
	nextTick(() => positionDropdowns())
}

function positionDropdowns() {
	const positions = {}
	navItemEls.value.forEach((el, idx) => {
		if (!el) return
		const rect = el.getBoundingClientRect()
		const item = props.navItems[idx]
		if (item) {
			positions[item.name] = {
				top: rect.bottom + 6 + 'px',
				left: rect.left + 'px',
			}
		}
	})
	dropdownPositions.value = positions
}

function dropdownStyle(item) {
	const pos = dropdownPositions.value[item.name]
	return pos || {}
}

function scheduleClose() {
	closeTimer = setTimeout(() => {
		activeDropdown.value = null
	}, 120)
}

function cancelClose() {
	clearTimeout(closeTimer)
}

function toggleNotif() {
	notifOpen.value = !notifOpen.value
	userOpen.value = false
	if (notifOpen.value) positionNotif()
}

function positionNotif() {
	const trigger = notifWrap.value?.querySelector('.nav-icon-btn')
	if (!trigger) return
	const r = trigger.getBoundingClientRect()
	notifPanelStyle.value = {
		top: r.bottom + 6 + 'px',
		right: window.innerWidth - r.right + 'px',
		left: 'auto',
	}
}

function toggleUser() {
	userOpen.value = !userOpen.value
	notifOpen.value = false
	if (userOpen.value) positionUser()
}

function positionUser() {
	const trigger = userWrap.value?.querySelector('.nav-user')
	if (!trigger) return
	const r = trigger.getBoundingClientRect()
	userDropdownStyle.value = {
		top: r.bottom + 6 + 'px',
		right: window.innerWidth - r.right + 'px',
		left: 'auto',
	}
}

function handleGlobalClick(e) {
	if (!e.target.closest('.notif-wrap')) notifOpen.value = false
	if (!e.target.closest('.nav-user-wrap')) userOpen.value = false
}

onMounted(() => {
	updateScrollArrows()
	window.addEventListener('resize', updateScrollArrows)
	document.addEventListener('click', handleGlobalClick)
})

onBeforeUnmount(() => {
	window.removeEventListener('resize', updateScrollArrows)
	document.removeEventListener('click', handleGlobalClick)
	clearTimeout(closeTimer)
	clearInterval(hoverScrollTimer)
})
</script>

<style scoped>
.top-nav {
	grid-column: 1 / -1;
	grid-row: 1;
	background: #fff;
	border-bottom: 1px solid #ebebf0;
	padding: 0 24px;
	display: flex;
	align-items: center;
	gap: 0;
	height: 56px;
	border-radius: 14px;
	box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
}

.logo {
	display: flex;
	align-items: center;
	gap: 8px;
	font-size: 17px;
	font-weight: 600;
	color: #1a1a2e;
	margin-right: 32px;
	white-space: nowrap;
}

.logo-icon {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	width: 30px;
	height: 30px;
	background: linear-gradient(135deg, rgb(var(--color-primary)), rgb(var(--color-primary-hover)));
	border-radius: 8px;
	color: #fff;
	font-size: 14px;
	box-shadow: 0 4px 12px rgb(var(--color-primary) / .3);
}

.nav-scroll-wrapper {
	flex: 1;
	min-width: 0;
	display: flex;
	align-items: center;
	gap: 4px;
}

.nav-scroll-btn {
	width: 22px;
	height: 22px;
	flex-shrink: 0;
	background: none;
	border: 1px solid #e8e8f0;
	border-radius: 6px;
	cursor: pointer;
	display: flex;
	align-items: center;
	justify-content: center;
	color: #aaa;
	font-size: 14px;
	line-height: 1;
	transition: opacity 0.15s;
	user-select: none;
	opacity: 0;
	pointer-events: none;
}
.nav-scroll-wrapper:hover .nav-scroll-btn:not(.nav-btn-hidden) {
	opacity: 1;
	pointer-events: all;
}
.nav-scroll-btn:hover {
	background: #f0f0f5;
	color: #333;
	border-color: #ccc;
}

.nav-links {
	flex: 1;
	min-width: 0;
	display: flex;
	gap: 2px;
	overflow-x: auto;
	scrollbar-width: none;
	scroll-behavior: smooth;
}
.nav-links::-webkit-scrollbar { display: none; }

.nav-item { position: relative; flex-shrink: 0; }

.nav-link {
	display: block;
	text-decoration: none;
	color: #666;
	font-size: 13.5px;
	font-weight: 500;
	padding: 6px 14px;
	border-radius: 20px;
	transition: all 0.15s;
	white-space: nowrap;
}
.nav-link:hover { color: rgb(var(--color-primary)); background: rgb(var(--color-primary-soft)); }
.nav-link.active { background: rgb(var(--color-primary)); color: #fff; font-weight: 600; }

.nav-dropdown {
	position: fixed;
	background: #fff;
	border-radius: 10px;
	box-shadow: 0 8px 28px rgba(0, 0, 0, 0.13);
	border: 1px solid #ebebf0;
	min-width: 168px;
	padding: 5px;
	opacity: 0;
	pointer-events: none;
	transform: translateY(-6px);
	transition: opacity 0.15s, transform 0.15s;
	z-index: 9999;
}
.nav-dropdown.open {
	opacity: 1;
	pointer-events: all;
	transform: translateY(0);
}
.nav-dropdown a {
	display: block;
	text-decoration: none;
	color: #444;
	font-size: 12.5px;
	font-weight: 500;
	padding: 7px 12px;
	border-radius: 7px;
	transition: background 0.12s, color 0.12s;
	white-space: nowrap;
}
.nav-dropdown a:hover { background: rgb(var(--color-primary-soft)); color: rgb(var(--color-primary)); }

.nav-actions {
	display: flex;
	align-items: center;
	gap: 8px;
	margin-left: auto;
}

.nav-search {
	display: flex; align-items: center; gap: 6px;
	background: #f5f5f8; border: 1px solid #e8e8f0;
	border-radius: 8px; padding: 5px 10px;
	cursor: pointer; user-select: none;
	transition: border-color .15s, background .15s;
}
.nav-search:hover { background: #efefef; border-color: #d0d0e0; }
.nav-search-placeholder { font-size: 13px; color: #bbb; flex: 1; }
.nav-search-kbd {
	font-size: 10px; color: #aaa;
	background: #fff; border: 1px solid #ddd;
	border-radius: 4px; padding: 1px 5px;
	font-family: inherit;
}

.nav-icon-btn {
	width: 34px; height: 34px;
	background: none; border: 1px solid #e8e8f0;
	border-radius: 8px; cursor: pointer;
	display: flex; align-items: center; justify-content: center;
	color: #666; position: relative;
	transition: border-color .15s, color .15s, background .15s;
}
.nav-icon-btn:hover {
	border-color: #c8c8d8;
	color: #1a1a2e;
	background: #fafafe;
}
.nav-icon-btn .badge {
	position: absolute;
	top: -3px; right: -3px;
	background: #f05a5a; color: #fff;
	font-size: 8px; font-weight: 700;
	width: 14px; height: 14px;
	border-radius: 50%;
	display: flex; align-items: center; justify-content: center;
	border: 2px solid #fff;
}

.cart-btn .cart-badge {
	background: rgb(var(--color-primary));
	min-width: 16px;
	height: 16px;
	width: auto;
	padding: 0 4px;
	font-size: 9px;
	border-radius: 999px;
	top: -4px; right: -4px;
}

@keyframes cart-bump {
	0% { transform: scale(1); }
	35% { transform: scale(1.18) rotate(-6deg); }
	70% { transform: scale(0.96) rotate(3deg); }
	100% { transform: scale(1); }
}
.cart-btn.bump { animation: cart-bump .42s ease-out; }

.nav-user-wrap { position: relative; }
.nav-user {
	display: flex; align-items: center; gap: 8px;
	padding: 4px 10px 4px 4px;
	border: 1.5px solid #e8e8f0; border-radius: 999px;
	background: #fff; cursor: pointer;
	transition: border-color .15s, box-shadow .15s;
	user-select: none;
}
.nav-user:hover { border-color: #c8c8d8; box-shadow: 0 2px 8px rgba(0, 0, 0, .06); }
.nav-user.open { border-color: rgb(var(--color-primary) / .5); box-shadow: 0 0 0 3px rgb(var(--color-primary) / .12); }
.nav-user-avatar {
	width: 26px; height: 26px; border-radius: 50%;
	background: linear-gradient(135deg, rgb(var(--color-primary)), rgb(var(--color-primary-hover)));
	display: flex; align-items: center; justify-content: center;
	font-size: 10px; font-weight: 700; color: #fff; flex-shrink: 0;
}
.nav-user-name { font-size: 12.5px; font-weight: 600; color: #1a1a2e; }
.nav-user-chevron { color: #aaa; transition: transform .15s; }
.nav-user.open .nav-user-chevron { transform: rotate(180deg); }

.user-dropdown {
	position: fixed;
	background: #fff;
	border: 1px solid #e8e8f0;
	border-radius: 14px;
	box-shadow: 0 4px 16px rgba(0, 0, 0, .07), 0 16px 40px rgba(0, 0, 0, .08);
	width: min(220px, calc(100vw - 16px)); padding: 6px;
	opacity: 0; pointer-events: none;
	transform: translateY(-6px) scale(.97);
	transition: opacity .15s, transform .15s;
	z-index: 9998;
}
.user-dropdown.open { opacity: 1; pointer-events: all; transform: translateY(0) scale(1); }

.ud-header {
	padding: 10px;
	border-bottom: 1px solid #f0f0f5;
	margin-bottom: 4px;
	display: flex; align-items: center; gap: 10px;
}
.ud-avatar-lg {
	width: 38px; height: 38px; border-radius: 50%; flex-shrink: 0;
	background: linear-gradient(135deg, rgb(var(--color-primary)), rgb(var(--color-primary-hover)));
	display: flex; align-items: center; justify-content: center;
	font-size: 14px; font-weight: 700; color: #fff;
}
.ud-name { font-size: 13px; font-weight: 700; color: #1a1a2e; }
.ud-email { font-size: 11px; color: #aaa; margin-top: 1px; }

.ud-item {
	display: flex; align-items: center; gap: 9px;
	padding: 8px 10px; border-radius: 9px; cursor: pointer;
	font-size: 12.5px; font-weight: 500; color: #444;
	transition: background .12s, color .12s;
}
.ud-item :deep(svg) { color: #aaa; flex-shrink: 0; transition: color .12s; }
.ud-item:hover { background: rgb(var(--color-primary-soft)); color: rgb(var(--color-primary)); }
.ud-item:hover :deep(svg) { color: rgb(var(--color-primary)); }
.ud-item.danger { color: #ef4444; }
.ud-item.danger :deep(svg) { color: #ef4444; }
.ud-item.danger:hover { background: #fef2f2; color: #dc2626; }
.ud-divider { border: none; border-top: 1px solid #f0f0f5; margin: 4px 0; }
.ud-heading {
	font-size: 10.5px;
	font-weight: 700;
	color: #aaa;
	text-transform: uppercase;
	letter-spacing: 0.04em;
	padding: 8px 14px 4px;
}
.ud-item.demo-active {
	background: rgb(var(--color-primary-soft));
	color: rgb(var(--color-primary));
	font-weight: 600;
	position: relative;
}
.ud-item.demo-active::after {
	content: '✓';
	margin-left: auto;
	color: rgb(var(--color-primary));
	font-weight: 800;
}

.notif-wrap { position: relative; }
.notif-panel {
	position: fixed;
	background: #fff;
	border: 1px solid #e8e8f0;
	border-radius: 16px;
	box-shadow: 0 4px 16px rgba(0, 0, 0, .07), 0 16px 40px rgba(0, 0, 0, .08);
	width: min(340px, calc(100vw - 16px));
	opacity: 0; pointer-events: none;
	transform: translateY(-6px) scale(.97);
	transition: opacity .15s, transform .15s;
	z-index: 9998;
	overflow: hidden;
}
.notif-panel.open { opacity: 1; pointer-events: all; transform: translateY(0) scale(1); }

.notif-panel-header {
	padding: 14px 16px 10px;
	display: flex; align-items: center; justify-content: space-between;
	border-bottom: 1px solid #f0f0f5;
}
.notif-panel-header h4 { font-size: 14px; font-weight: 700; color: #1a1a2e; }
.notif-mark-read {
	font-size: 11.5px; font-weight: 600; color: rgb(var(--color-primary));
	background: none; border: none; cursor: pointer; padding: 0;
	transition: color .15s;
}
.notif-mark-read:hover { color: rgb(var(--color-primary-hover)); }

.notif-list { max-height: 360px; overflow-y: auto; }
.notif-list::-webkit-scrollbar { width: 3px; }
.notif-list::-webkit-scrollbar-track { background: transparent; }
.notif-list::-webkit-scrollbar-thumb { background: #e0e0ea; border-radius: 3px; }

.notif-item {
	display: flex; align-items: flex-start; gap: 11px;
	padding: 11px 16px; cursor: pointer;
	transition: background .12s; position: relative;
}
.notif-item:hover { background: #fafafe; }
.notif-item.unread { background: rgb(var(--color-primary-soft)); }
.notif-item.unread:hover { background: rgb(var(--color-primary-soft)); }
.notif-unread-dot {
	position: absolute; top: 14px; right: 14px;
	width: 7px; height: 7px; border-radius: 50%;
	background: rgb(var(--color-primary));
}
.notif-icon {
	width: 36px; height: 36px; border-radius: 10px;
	display: flex; align-items: center; justify-content: center;
	flex-shrink: 0; font-size: 16px;
}
.notif-icon-success { background: #f0fdf4; }
.notif-icon-warning { background: #fffbeb; }
.notif-icon-error { background: #fef2f2; }
.notif-icon-info { background: rgb(var(--color-primary-soft)); }
.notif-body { flex: 1; min-width: 0; padding-right: 14px; }
.notif-title { font-size: 12.5px; font-weight: 600; color: #1a1a2e; margin-bottom: 2px; line-height: 1.3; }
.notif-desc { font-size: 11.5px; color: #888; line-height: 1.4; }
.notif-time { font-size: 10.5px; color: #bbb; margin-top: 4px; }

.notif-section-label {
	font-size: 10.5px; font-weight: 700; color: #bbb;
	text-transform: uppercase; letter-spacing: .06em;
	padding: 10px 16px 4px;
}
.notif-divider { border: none; border-top: 1px solid #f5f5f8; margin: 0; }

.notif-panel-footer {
	padding: 10px 16px;
	border-top: 1px solid #f0f0f5; text-align: center;
}
.notif-panel-footer button {
	font-size: 12.5px; font-weight: 600; color: rgb(var(--color-primary));
	background: none; border: none; cursor: pointer;
	transition: color .15s;
}
.notif-panel-footer button:hover { color: rgb(var(--color-primary-hover)); }

/* ── Dar ekran (telefon): sağ taraftaki ikon/arama/kullanıcı grubu ekrandan taşmasın ── */
@media (max-width: 640px) {
	.top-nav { padding: 0 10px; }
	.logo { margin-right: 10px; }
	.nav-actions { gap: 4px; }
	.nav-search { padding: 5px; }
	.nav-search-placeholder,
	.nav-search-kbd { display: none; }
	.nav-user-name,
	.nav-user-chevron { display: none; }
	.nav-user { padding-right: 6px; }
}

@media (max-width: 400px) {
	.nav-icon-message { display: none; }
}
</style>
