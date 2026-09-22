<template>
	<nav class="top-nav">
		<div class="logo">
			<div class="logo-icon">
				<svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="2.5">
					<path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5" />
				</svg>
			</div>
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

			<div class="notif-wrap" ref="notifWrap">
				<button class="nav-icon-btn" title="Bildirimler" @click.stop="toggleNotif">
					<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
						<path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9M13.73 21a2 2 0 01-3.46 0" />
					</svg>
					<span v-if="unreadCount > 0" class="badge pulse">{{ unreadCount }}</span>
				</button>
			</div>

			<div class="nav-user-wrap" ref="userWrap">
				<div class="nav-user" :class="{ open: userOpen }" @click.stop="toggleUser">
					<div class="nav-user-avatar">{{ userInitials }}</div>
					<span class="nav-user-name">{{ user.shortName }}</span>
					<svg class="nav-user-chevron" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
						<path d="M6 9l6 6 6-6" />
					</svg>
				</div>
			</div>
		</div>

		<!-- Bildirim ve kullanıcı panelleri body'ye teleport edilir: böylece .nav-actions
		     mobilde display:none olduğunda (bkz. alt sabit menü) panel DOM'dan kaybolmaz. -->
		<Teleport to="body">
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
		</Teleport>

		<Teleport to="body">
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
		</Teleport>

		<!-- Mobil (telefon): logo dışında header boştu, Ara/Bildirim/Kullanıcı ayrı bir
		     "Menü" sheet'ine gizliydi — kullanışsız bulunduğu için üçü de artık .nav-actions
		     içinde (yukarıda), kompakt haliyle, her zaman görünür. Alt sabit sekme çubuğu
		     sadece modül gezinmesi için: aktif sekmeye TEKRAR dokununca (navigasyon yerine)
		     o modülün sayfa ağacı (.mtb-sheet) açılır — masaüstünde LeftSidebar.vue'nun
		     panelindeki aynı davranış/veri (navItems). Görünürlük CSS media query ile
		     kontrol edilir. -->
		<Teleport to="body">
			<nav class="mobile-tab-bar" @click.stop>
				<div class="mtb-scroll">
					<component
						:is="item.to && !(item.active && navItems.length) ? Link : 'button'"
						v-for="item in sidebarItems"
						:key="item.label"
						:href="item.to && !(item.active && navItems.length) ? item.to : undefined"
						class="mtb-btn"
						:class="{ active: item.active }"
						@click="item.active && navItems.length ? toggleMore() : undefined"
					>
						<span class="mtb-icon-wrap">
							<span v-html="item.icon"></span>
							<span v-if="item.active && navItems.length" class="mtb-expand-hint"></span>
						</span>
						<span>{{ item.label }}</span>
					</component>
				</div>
			</nav>

			<div v-if="moreOpen" class="mtb-sheet-backdrop" @click="moreOpen = false"></div>

			<div class="mtb-sheet" :class="{ open: moreOpen }" @click.stop>
				<div class="mtb-sheet-section-label">Bu Bölüm</div>
				<div class="mtb-nav-list">
					<div v-for="item in navItems" :key="item.name" class="mtb-nav-node">
						<component
							:is="item.to && !item.children?.length ? Link : 'button'"
							:href="item.to || undefined"
							type="button"
							class="mtb-nav-row"
							:class="{ active: item.active, open: mobileNavExpanded === item.name }"
							@click="item.children?.length ? toggleMobileNav(item.name) : closeMobileSheet()"
						>
							<span class="mtb-nav-icon" v-html="item.icon"></span>
							<span class="mtb-nav-label">{{ item.name }}</span>
							<span v-if="item.children?.length" class="mtb-nav-chevron" :class="{ open: mobileNavExpanded === item.name }">&#8250;</span>
						</component>
						<div v-if="item.children?.length" class="mtb-nav-children" :class="{ open: mobileNavExpanded === item.name }">
							<div class="mtb-nav-children-inner">
								<template v-for="child in item.children" :key="child.label">
									<component
										:is="child.to && !child.children?.length ? Link : 'button'"
										:href="child.to || undefined"
										type="button"
										class="mtb-nav-subrow"
										:class="{ open: mobileNavSubExpanded === child.label }"
										@click="child.children?.length ? toggleMobileNavSub(child.label) : closeMobileSheet()"
									>
										<span v-html="child.icon"></span>
										<span>{{ child.label }}</span>
										<span v-if="child.children?.length" class="mtb-nav-chevron" :class="{ open: mobileNavSubExpanded === child.label }">&#8250;</span>
									</component>
									<div v-if="child.children?.length" class="mtb-nav-grandchildren" :class="{ open: mobileNavSubExpanded === child.label }">
										<div class="mtb-nav-grandchildren-inner">
											<Link v-for="sub in child.children" :key="sub.label" :href="sub.to" class="mtb-nav-leaf" @click="closeMobileSheet()">
												<span v-html="sub.icon"></span>
												<span>{{ sub.label }}</span>
											</Link>
										</div>
									</div>
								</template>
							</div>
						</div>
					</div>
				</div>
			</div>
		</Teleport>
	</nav>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { Link } from '@inertiajs/vue3'

const props = defineProps({
	navItems: { type: Array, required: true },
	sidebarItems: { type: Array, default: () => [] },
	notifications: { type: Array, required: true },
	user: { type: Object, required: true },
	userMenu: { type: Array, required: true },
})

const emit = defineEmits(['open-search', 'mark-all-read', 'read-notification', 'user-action', 'notification-click'])

function onNotifClick(n) {
	emit('read-notification', n.id)
	if (n?.link) {
		notifOpen.value = false
		emit('notification-click', n)
	}
}

const notifWrap = ref(null)
const userWrap = ref(null)

const notifOpen = ref(false)
const userOpen = ref(false)
const moreOpen = ref(false)
const mobileNavExpanded = ref(null)
const mobileNavSubExpanded = ref(null)
const notifPanelStyle = ref({})
const userDropdownStyle = ref({})

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

function toggleNotif() {
	notifOpen.value = !notifOpen.value
	userOpen.value = false
	moreOpen.value = false
	if (notifOpen.value) positionNotif()
}

function positionNotif() {
	// Mobilde panel CSS ile alt sabit menünün üstünde "bottom sheet" olarak açılır;
	// masaüstündeki (gizli) tetikleyici buton konumu mobilde anlamsız olur.
	if (window.innerWidth <= 640) {
		notifPanelStyle.value = {}
		return
	}
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
	moreOpen.value = false
	if (userOpen.value) positionUser()
}

function toggleMore() {
	moreOpen.value = !moreOpen.value
	notifOpen.value = false
	userOpen.value = false
	if (!moreOpen.value) {
		mobileNavExpanded.value = null
		mobileNavSubExpanded.value = null
	}
}

// Bu bölüm akordeonundaki bir yaprağa (link) dokununca sheet'i kapat ve
// açık kalan akordeon durumunu sıfırla — bir sonraki açılış kapalı başlasın.
function closeMobileSheet() {
	moreOpen.value = false
	mobileNavExpanded.value = null
	mobileNavSubExpanded.value = null
}

function toggleMobileNav(name) {
	mobileNavExpanded.value = mobileNavExpanded.value === name ? null : name
	mobileNavSubExpanded.value = null
}

function toggleMobileNavSub(label) {
	mobileNavSubExpanded.value = mobileNavSubExpanded.value === label ? null : label
}

function positionUser() {
	if (window.innerWidth <= 640) {
		userDropdownStyle.value = {}
		return
	}
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
	// Paneller body'ye teleport edildiği için tetikleyici (.notif-wrap/.nav-user-wrap)
	// ile aynı DOM dalında değiller; panelin kendi sınıfı da ayrıca kontrol edilir.
	if (!e.target.closest('.notif-wrap') && !e.target.closest('.notif-panel')) notifOpen.value = false
	if (!e.target.closest('.nav-user-wrap') && !e.target.closest('.user-dropdown')) userOpen.value = false
	if (!e.target.closest('.mobile-tab-bar') && !e.target.closest('.mtb-sheet')) {
		moreOpen.value = false
		mobileNavExpanded.value = null
		mobileNavSubExpanded.value = null
	}
}

onMounted(() => {
	document.addEventListener('click', handleGlobalClick)
})

onBeforeUnmount(() => {
	document.removeEventListener('click', handleGlobalClick)
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
	background: linear-gradient(135deg, rgb(var(--color-primary-rgb)), rgb(var(--color-primary-hover-rgb)));
	border-radius: 8px;
	color: #fff;
	font-size: 14px;
	box-shadow: 0 4px 12px rgb(var(--color-primary-rgb) / .3);
}

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

/* Okunmamış bildirim rozeti: dikkat çekmesi için sürekli yumuşak nabız.
   Kullanıcı okundu olarak işaretleyince (unreadCount 0'a düşünce) v-if kaldırıp durur. */
@keyframes notif-pulse {
	0%, 100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(240, 90, 90, .55); }
	50%      { transform: scale(1.18); box-shadow: 0 0 0 3px rgba(240, 90, 90, 0); }
}
.badge.pulse {
	animation: notif-pulse 1.7s ease-in-out infinite;
}

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
.nav-user.open { border-color: rgb(var(--color-primary-rgb) / .5); box-shadow: 0 0 0 3px rgb(var(--color-primary-rgb) / .12); }
.nav-user-avatar {
	width: 26px; height: 26px; border-radius: 50%;
	background: linear-gradient(135deg, rgb(var(--color-primary-rgb)), rgb(var(--color-primary-hover-rgb)));
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
	background: linear-gradient(135deg, rgb(var(--color-primary-rgb)), rgb(var(--color-primary-hover-rgb)));
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
.ud-item:hover { background: rgb(var(--color-primary-soft-rgb)); color: rgb(var(--color-primary-rgb)); }
.ud-item:hover :deep(svg) { color: rgb(var(--color-primary-rgb)); }
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
	background: rgb(var(--color-primary-soft-rgb));
	color: rgb(var(--color-primary-rgb));
	font-weight: 600;
	position: relative;
}
.ud-item.demo-active::after {
	content: '✓';
	margin-left: auto;
	color: rgb(var(--color-primary-rgb));
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
	font-size: 11.5px; font-weight: 600; color: rgb(var(--color-primary-rgb));
	background: none; border: none; cursor: pointer; padding: 0;
	transition: color .15s;
}
.notif-mark-read:hover { color: rgb(var(--color-primary-hover-rgb)); }

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
.notif-item.unread { background: rgb(var(--color-primary-soft-rgb)); }
.notif-item.unread:hover { background: rgb(var(--color-primary-soft-rgb)); }
.notif-unread-dot {
	position: absolute; top: 14px; right: 14px;
	width: 7px; height: 7px; border-radius: 50%;
	background: rgb(var(--color-primary-rgb));
}
.notif-icon {
	width: 36px; height: 36px; border-radius: 10px;
	display: flex; align-items: center; justify-content: center;
	flex-shrink: 0; font-size: 16px;
}
.notif-icon-success { background: #f0fdf4; }
.notif-icon-warning { background: #fffbeb; }
.notif-icon-error { background: #fef2f2; }
.notif-icon-info { background: rgb(var(--color-primary-soft-rgb)); }
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
	font-size: 12.5px; font-weight: 600; color: rgb(var(--color-primary-rgb));
	background: none; border: none; cursor: pointer;
	transition: color .15s;
}
.notif-panel-footer button:hover { color: rgb(var(--color-primary-hover-rgb)); }

/* ── Mobil alt sabit sekme çubuğu (uygulama tarzı) ── */
.mobile-tab-bar {
	display: none;
}
.mtb-scroll {
	flex: 1;
	min-width: 0;
	display: flex;
	gap: 2px;
	overflow-x: auto;
	scrollbar-width: none;
}
.mtb-scroll::-webkit-scrollbar { display: none; }
.mtb-btn {
	/* flex:0 0 auto — buton içeriği kadar geniş olur, elastik daraltma metni
	   komşu butona taşırıp "iç içe" görünmesine yol açıyordu. */
	flex: 0 0 auto;
	min-width: 52px;
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	gap: 2px;
	background: none;
	border: none;
	cursor: pointer;
	color: #888;
	font-size: 9.5px;
	font-weight: 600;
	padding: 6px 8px 4px;
	border-radius: 10px;
	transition: color 0.15s, background 0.15s;
	position: relative;
	text-decoration: none;
	white-space: nowrap;
}
.mtb-btn svg { flex-shrink: 0; width: 18px; height: 18px; }
.mtb-btn.active,
.mtb-btn:active { color: rgb(var(--color-primary-rgb)); background: rgb(var(--color-primary-soft-rgb)); }
.mtb-btn span:last-child {
	max-width: 68px;
	overflow: hidden;
	text-overflow: ellipsis;
}
.mtb-icon-wrap { position: relative; display: inline-flex; }

/* Aktif sekmenin alt sayfaları varsa (navItems.length): tekrar dokununca
   sheet açılacağını ima eden küçük nokta — TopNav'daki bildirim rozetiyle
   karışmasın diye alarm rengi (#f05a5a) değil, marka rengi kullanılır. */
.mtb-expand-hint {
	position: absolute;
	top: -2px; right: -2px;
	width: 6px; height: 6px; border-radius: 50%;
	background: rgb(var(--color-primary-rgb));
	border: 2px solid #fff;
}

/* Hamburger "Menü" panelinin açtığı alt panel: eski mobil sekme çubuğunda
   duran Ara/Bildirim/Hesabım artık burada. */
.mtb-sheet-backdrop {
	display: none;
	position: fixed;
	inset: 0;
	background: rgba(0, 0, 0, 0.25);
	z-index: 9989;
}
.mtb-sheet {
	display: none;
	position: fixed;
	left: 8px; right: 8px;
	bottom: calc(56px + env(safe-area-inset-bottom) + 8px);
	max-height: min(72vh, 520px);
	overflow-y: auto;
	background: #fff;
	border: 1px solid #e8e8f0;
	border-radius: 16px;
	box-shadow: 0 4px 16px rgba(0, 0, 0, .07), 0 16px 40px rgba(0, 0, 0, .08);
	padding: 6px;
	z-index: 9990;
	opacity: 0;
	pointer-events: none;
	transform: translateY(8px) scale(.98);
	transition: opacity .15s, transform .15s;
}
.mtb-sheet.open { opacity: 1; pointer-events: all; transform: translateY(0) scale(1); }

.mtb-sheet-section-label {
	font-size: 10.5px;
	font-weight: 700;
	color: #bbb;
	text-transform: uppercase;
	letter-spacing: .06em;
	padding: 10px 10px 4px;
}

/* Bu bölüm akordeonu: aktif modülün 2./3. seviye sayfaları (navItems), masaüstünde
   LeftSidebar.vue'nun panelinde görünen aynı veri — mobilde satır içi açılır/kapanır
   liste olarak (dokunma hedefleri için daha uygun, sidebar panel mobilde erişilemez). */
.mtb-nav-list { display: flex; flex-direction: column; }
.mtb-nav-node + .mtb-nav-node { border-top: 1px solid #f5f5f8; }

.mtb-nav-row {
	width: 100%;
	display: flex; align-items: center; gap: 10px;
	background: none; border: none; cursor: pointer;
	padding: 10px 10px; border-radius: 10px;
	font-size: 13px; font-weight: 600; color: #333;
	text-decoration: none;
	transition: background .12s, color .12s;
}
.mtb-nav-row :deep(svg) { width: 16px; height: 16px; flex-shrink: 0; color: #aaa; transition: color .12s; }
.mtb-nav-row:hover,
.mtb-nav-row.open { background: rgb(var(--color-primary-soft-rgb)); color: rgb(var(--color-primary-rgb)); }
.mtb-nav-row:hover :deep(svg),
.mtb-nav-row.open :deep(svg) { color: rgb(var(--color-primary-rgb)); }
.mtb-nav-row.active { color: rgb(var(--color-primary-rgb)); }
.mtb-nav-label { flex: 1; text-align: left; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.mtb-nav-chevron {
	flex-shrink: 0;
	color: #ccc;
	font-size: 15px;
	line-height: 1;
	transition: transform .15s;
}
.mtb-nav-chevron.open { transform: rotate(90deg); }

.mtb-nav-children {
	display: grid;
	grid-template-rows: 0fr;
	overflow: hidden;
	transition: grid-template-rows .18s ease;
}
.mtb-nav-children.open { grid-template-rows: 1fr; }
.mtb-nav-children-inner { min-height: 0; padding-left: 14px; }

.mtb-nav-subrow {
	width: 100%;
	display: flex; align-items: center; gap: 9px;
	background: none; border: none; cursor: pointer;
	padding: 8px 10px; border-radius: 9px;
	font-size: 12.5px; font-weight: 550; color: #555;
	text-decoration: none;
	transition: background .12s, color .12s;
}
.mtb-nav-subrow :deep(svg) { width: 14px; height: 14px; flex-shrink: 0; color: #bbb; }
.mtb-nav-subrow:hover,
.mtb-nav-subrow.open { background: rgb(var(--color-primary-soft-rgb)); color: rgb(var(--color-primary-rgb)); }
.mtb-nav-subrow:hover :deep(svg),
.mtb-nav-subrow.open :deep(svg) { color: rgb(var(--color-primary-rgb)); }

.mtb-nav-grandchildren {
	display: grid;
	grid-template-rows: 0fr;
	overflow: hidden;
	transition: grid-template-rows .16s ease;
}
.mtb-nav-grandchildren.open { grid-template-rows: 1fr; }
.mtb-nav-grandchildren-inner { min-height: 0; padding-left: 14px; }

.mtb-nav-leaf {
	display: flex; align-items: center; gap: 9px;
	padding: 7px 10px; border-radius: 8px;
	font-size: 12px; font-weight: 500; color: #777;
	text-decoration: none;
	transition: background .12s, color .12s;
}
.mtb-nav-leaf :deep(svg) { width: 13px; height: 13px; flex-shrink: 0; color: #ccc; }
.mtb-nav-leaf:hover { background: rgb(var(--color-primary-soft-rgb)); color: rgb(var(--color-primary-rgb)); }
.mtb-nav-leaf:hover :deep(svg) { color: rgb(var(--color-primary-rgb)); }

/* ── Dar ekran (telefon): header'da logo + kompakt Ara/Bildirim/Kullanıcı kalır
     (metin/kbd ipucu ve kullanılmayan Mesajlar ikonu gizlenir, yer kazanmak için) —
     alt sabit sekme çubuğu sadece modül gezinmesi içindir, "Menü" katman-üstü-katman
     kullanışsız bulunduğu için kaldırıldı (bkz. yukarıdaki mobile-tab-bar yorumu). ── */
@media (max-width: 640px) {
	.top-nav { padding: 0 10px; gap: 8px; }
	.logo { margin-right: 0; }
	.nav-actions { gap: 6px; }
	.nav-search { padding: 7px; }
	.nav-search-placeholder,
	.nav-search-kbd { display: none; }
	.nav-icon-message { display: none; }
	.nav-icon-btn { width: 32px; height: 32px; }
	.nav-user { padding: 3px; border: none; }
	.nav-user-name { display: none; }
	.nav-user-chevron { display: none; }

	.mobile-tab-bar {
		display: flex;
		position: fixed;
		left: 0;
		right: 0;
		bottom: 0;
		background: #fff;
		border-top: 1px solid #ebebf0;
		box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.08);
		padding: 4px 4px calc(4px + env(safe-area-inset-bottom));
		z-index: 9990;
		justify-content: space-around;
	}

	.mtb-sheet-backdrop { display: block; }
	.mtb-sheet { display: block; }

	/* Bildirim/kullanıcı panelleri mobilde alt sekme çubuğunun üstünde tam genişlik
	   "bottom sheet" olarak açılır; masaüstü konumlandırması (top/right) geçersiz kılınır. */
	.notif-panel,
	.user-dropdown {
		left: 8px !important;
		right: 8px !important;
		bottom: calc(56px + env(safe-area-inset-bottom)) !important;
		top: auto !important;
		width: auto !important;
		transform: translateY(8px) scale(0.98) !important;
	}
	.notif-panel.open,
	.user-dropdown.open {
		transform: translateY(0) scale(1) !important;
	}
}
</style>
