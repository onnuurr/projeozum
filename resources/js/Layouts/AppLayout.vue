<template>
	<div class="layout-root">
		<TopNav
			:nav-items="navItems"
			:notifications="visibleNotifications"
			:user="currentUser"
			:user-menu="userMenu"
			:cart-item-count="cartItemCount"
			@open-search="searchOpen = true"
			@open-cart="cartOpen = true"
			@mark-all-read="markAllNotificationsRead"
			@read-notification="readNotification"
			@user-action="handleUserAction"
			@notification-click="onNotificationClick"
		/>

		<LeftSidebar :top-buttons="sidebarTop" :bottom-buttons="sidebarBottom" />

		<main class="main-content">
			<slot />
		</main>

		<FilterDrawer
			v-model="drawerOpen"
			@apply="onFilterApply"
			@reset="showToast({ type: 'info', title: 'Filtreler Sıfırlandı', message: 'Tüm filtreler kaldırıldı.' })"
		/>

		<SearchModal
			v-model="searchOpen"
			:recent="searchRecent"
			:quick="searchQuick"
			@select="showToast({ type: 'info', title: 'Arama Sonucu', message: $event.text })"
		/>

		<CartDrawer
			v-model="cartOpen"
			:items="cartItems"
			@update-qty="updateCartQty"
			@remove="removeCartItem"
			@clear="clearCart"
			@checkout="onCheckout"
		/>

		<ToastContainer :toasts="toasts" @dismiss="dismissToast" />
	</div>
</template>

<script setup>
import { ref, provide, computed, watch, onMounted, onBeforeUnmount } from 'vue'
import { usePage, router } from '@inertiajs/vue3'
import TopNav from '@/Components/TopNav.vue'
import LeftSidebar from '@/Components/LeftSidebar.vue'
import FilterDrawer from '@/Components/FilterDrawer.vue'
import SearchModal from '@/Components/SearchModal.vue'
import CartDrawer from '@/Components/CartDrawer.vue'
import ToastContainer from '@/Components/ToastContainer.vue'
import Swal from 'sweetalert2'
import { renderMenuIcon } from '@/menuIcons.js'
import { useToast } from '@/composables/useToast.js'

const page = usePage()

const STAFF_ROLES = ['designer', 'reviewer', 'superadmin']

/* ── DB menüsü (Inertia paylaşımı) ── */
const menuTree = computed(() => page.props.menu ?? [])

// Bir düğüm ya da en yakın torununun gerçek link'i (kök tıklanınca nereye gitsin).
function firstLink(node) {
	if (node.to) return node.to
	for (const child of node.children || []) {
		const link = firstLink(child)
		if (link) return link
	}
	return null
}

// URL eşleşmesi: tam eşitlik ya da segment sınırında prefix ('/' öncesi).
// Sorgu içeren hedefler (örn. /atelier?status=draft) yalnızca tam eşleşir.
function urlMatches(to) {
	if (!to) return false
	if (page.url === to) return true
	if (to.includes('?')) return false
	const path = page.url.split('?')[0]
	return path === to || path.startsWith(to + '/')
}

// Aktif kök: page.url'i ağaçta en uzun prefix ile eşleştir, kök ataya yürü.
const activeRoot = computed(() => {
	const url = page.url
	let best = { root: null, len: -1 }
	const walk = (node, root) => {
		const r = root || node
		if (node.to && urlMatches(node.to) && node.to.length > best.len) {
			best = { root: r, len: node.to.length }
		}
		;(node.children || []).forEach((c) => walk(c, r))
	}
	menuTree.value.forEach((n) => walk(n, null))
	return best.root || menuTree.value[0] || null
})

/* ── Üst menü (header) = aktif kökün child'ları ── */
const navItems = computed(() => {
	const root = activeRoot.value
	if (!root) return []
	return (root.children || []).map((child) => ({
		name: child.label,
		to: child.to || undefined,
		active: urlMatches(child.to),
		children: (child.children || []).map((g) => ({ label: g.label, to: g.to || undefined })),
	}))
})

/* ── Kullanıcı ── */
// Inertia'nın paylaştığı auth.user'dan oku — HandleInertiaRequests şu an
// session('demo_role') değerine göre 4 farklı mock profil döner.
const currentUser = computed(
	() =>
		page.props.auth?.user || {
			id: 0,
			name: 'Misafir',
			shortName: 'Misafir',
			initials: '?',
			email: '',
			role: 'tenant',
			roleLabel: 'Misafir',
		}
)
const isStaff = computed(() => STAFF_ROLES.includes(currentUser.value.role))

const userMenu = computed(() => {
	const role = currentUser.value.role
	return [
		{ label: 'Profilim', icon: '<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>', to: '/profile' },
		{ label: 'Firma Profili', icon: '<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 21h18M5 21V7l8-4v18M19 21V11l-6-4"/><path d="M9 9h.01M9 13h.01M9 17h.01"/></svg>', to: '/tenant' },
		{ divider: true },
		{ label: 'Üretim Atölyesi', icon: '<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="6" cy="6" r="3"/><circle cx="6" cy="18" r="3"/><line x1="20" y1="4" x2="8.12" y2="15.88"/><line x1="14.47" y1="14.48" x2="20" y2="20"/><line x1="8.12" y1="8.12" x2="12" y2="12"/></svg>', to: '/atelier', visible: STAFF_ROLES.includes(role) },
		{ label: 'Süper Admin Paneli', icon: '<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>', to: '/superadmin/settings', visible: role === 'superadmin' },
		{ label: 'Menü Yönetimi', icon: '<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>', to: '/superadmin/menus', visible: role === 'superadmin' },
		{ label: 'Ayarlar', icon: '<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg>', to: '/tenant/settings' },
		{ label: 'Bildirimler', icon: '<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9M13.73 21a2 2 0 01-3.46 0"/></svg>' },
		{ divider: true },
		{ heading: `Rol Değiştir · ${currentUser.value.roleLabel}` },
		{ label: 'Tasarımcı', icon: '🎨', to: '/demo/role/designer', demoRole: 'designer', activeDemoRole: role === 'designer' },
		{ label: 'İnceleyici', icon: '👀', to: '/demo/role/reviewer', demoRole: 'reviewer', activeDemoRole: role === 'reviewer' },
		{ label: 'Süper Admin', icon: '🛡️', to: '/demo/role/superadmin', demoRole: 'superadmin', activeDemoRole: role === 'superadmin' },
		{ label: 'Firma (Tenant)', icon: '🏢', to: '/demo/role/tenant', demoRole: 'tenant', activeDemoRole: role === 'tenant' },
		{ divider: true },
		{ label: 'Kısayollar', icon: '<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="2" y="6" width="20" height="12" rx="2"/><path d="M6 10h.01M10 10h.01M14 10h.01M18 10h.01M8 14h8"/></svg>' },
		{ label: 'Yardım & Destek', icon: '<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 015.83 1c0 2-3 3-3 3M12 17h.01"/></svg>' },
		{ divider: true },
		{ label: 'Çıkış Yap', icon: '<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9"/></svg>', action: 'logout', danger: true },
	].filter((it) => it.visible !== false)
})

/* ── Bildirimler ── */
const notifications = ref([
	{ id: 1, icon: '⚠️', iconType: 'error', title: 'Geciken İş Emri: Kesim & Dikim', desc: "Büşra Çelik'e atanan iş emri teslim tarihini 2 gün geçti.", time: '5 dakika önce', read: false },
	{ id: 2, icon: '📦', iconType: 'info', title: 'Yeni Sipariş: #SIP-1048', desc: 'Mehmet Arslan tarafından 500 metre pamuk kumaş siparişi oluşturuldu.', time: '23 dakika önce', read: false },
	{ id: 3, icon: '✅', iconType: 'success', title: 'Kalite Kontrol Tamamlandı', desc: 'Sipariş #SIP-1041 kalite kontrolden geçti, sevkiyata hazır.', time: '2 saat önce', read: true },
	{ id: 4, icon: '🔔', iconType: 'warning', title: 'Stok Uyarısı: Polyester Kumaş', desc: 'Polyester kumaş stoğu kritik seviyenin altına düştü (12 top kaldı).', time: '5 saat önce', read: true },
	{ id: 5, icon: '🚚', iconType: 'success', title: 'Sevkiyat Tamamlandı', desc: 'Sipariş #SIP-1039 müşteriye teslim edildi.', time: 'Dün, 16:42', read: true },
])

// Rol bazlı görünür bildirimler (forRoles tanımlanmış olanlar filtrelenir).
const visibleNotifications = computed(() =>
	notifications.value.filter((n) => !n.forRoles || n.forRoles.includes(currentUser.value.role))
)

function markAllNotificationsRead() {
	notifications.value.forEach((n) => (n.read = true))
	showToast({ type: 'success', title: 'Tümü Okundu', message: 'Tüm bildirimler okundu olarak işaretlendi.' })
}

function readNotification(id) {
	const n = notifications.value.find((x) => x.id === id)
	if (n) n.read = true
}

function onNotificationClick(notification) {
	if (notification?.id) readNotification(notification.id)
	if (notification?.link) router.visit(notification.link)
}

function handleUserAction(item) {
	if (item.action === 'logout') {
		router.post(route('logout'))
		return
	}
	if (item.to) {
		router.visit(item.to)
		return
	}
	const isDanger = !!item.danger
	showToast({
		type: isDanger ? 'error' : 'info',
		title: item.label,
		message: isDanger ? 'Oturumunuz sonlandırıldı.' : `${item.label} sayfası açılıyor.`,
	})
}

/* ── Sidebar ── */
const SVG = (path, opts = '') => `<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" ${opts}>${path}</svg>`

/* ── Sidebar = kök menüler ── */
// Görünürlük (izin/rol) sunucuda MenuTreeBuilder ile filtrelenir; burada client-side gate yok.
const sidebarTop = computed(() =>
	menuTree.value.map((node) => ({
		label: node.label,
		icon: renderMenuIcon(node.icon),
		to: firstLink(node) || undefined,
		active: node.id === activeRoot.value?.id,
	}))
)

const sidebarBottom = ref([
	{ label: 'Bildirimler', icon: SVG('<path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9M13.73 21a2 2 0 01-3.46 0"/>') },
	{
		label: 'Tema',
		theme: true,
		icon: `<svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>`,
	},
])

/* ── Drawer ── */
const drawerOpen = ref(false)
provide('drawer', { open: () => (drawerOpen.value = true) })

function onFilterApply(payload) {
	showToast({
		type: 'success',
		title: 'Filtreler Uygulandı',
		message: `${payload.activeCount} aktif filtre ile sonuçlar güncellendi.`,
	})
}

/* ── Search ── */
const searchOpen = ref(false)

const searchRecent = ref([
	{ icon: '📦', text: 'Sipariş #1042 – Kumaş Dokuma', sub: 'İş Emirleri' },
	{ icon: '👤', text: 'Ahmet Yıldız', sub: 'Müşteriler' },
	{ icon: '📋', text: 'Boya Süreci Raporu', sub: 'Raporlar' },
])

const searchQuick = ref([
	{ icon: '➕', text: 'Yeni İş Emri Oluştur', sub: 'İş Emirleri' },
	{ icon: '📊', text: 'Üretim Raporu', sub: 'Raporlar' },
])

/* ── Toast (shared composable — singleton across all components) ── */
const { toasts, showToast, dismissToast } = useToast()

provide('showToast', showToast)

/* ── SweetAlert wrapper ── */
const swalDefaults = {
	customClass: {
		popup: 'tek-swal',
		title: 'tek-swal-title',
		htmlContainer: 'tek-swal-body',
		icon: 'tek-swal-icon',
		actions: 'tek-swal-actions',
	},
	buttonsStyling: false,
	reverseButtons: true,
}

const $swal = {
	fire: (opts) => Swal.fire({ ...swalDefaults, ...opts, customClass: { ...swalDefaults.customClass, ...(opts?.customClass || {}) } }),
	async confirm({ icon = 'question', title, html, confirmText = 'Tamam', cancelText = 'Vazgeç' }) {
		const r = await $swal.fire({
			icon, title, html,
			showCancelButton: true,
			confirmButtonText: confirmText,
			cancelButtonText: cancelText,
			customClass: { confirmButton: 'btn btn-primary', cancelButton: 'btn btn-ghost' },
		})
		return r.isConfirmed
	},
	async dangerConfirm({ title, html, confirmText = 'Sil', cancelText = 'Vazgeç' }) {
		const r = await $swal.fire({
			icon: 'warning', title, html,
			showCancelButton: true,
			confirmButtonText: confirmText,
			cancelButtonText: cancelText,
			customClass: { confirmButton: 'btn btn-danger', cancelButton: 'btn btn-ghost' },
		})
		return r.isConfirmed
	},
}

provide('$swal', $swal)

/* ── Flash izleyici: backend HandleInertiaRequests'in paylaştığı flash prop'unu uygular ── */
// flash.toast → { type, title, message } — HandleInertiaRequests her iki schema'yı bu şekle normalize eder.
// flash.notification → topnav bildirim paneline eklenir (rol filtreli).
watch(
	() => page.props.flash,
	(flash) => {
		if (!flash) return
		const toast = flash.toast
		if (toast && toast.type) {
			showToast({
				type: toast.type || 'info',
				title: toast.title || '',
				message: toast.message || '',
				duration: toast.duration,
			})
		}
		const notif = flash.notification
		if (notif && (!notif.forRoles || notif.forRoles.includes(currentUser.value.role))) {
			notifications.value.unshift({ ...notif })
		}
	},
	{ deep: true }
)

/* ── Sepet (DB destekli) ── */
const cartOpen = ref(false)
const cartItems = ref(Array.isArray(page.props.cart?.items) ? [...page.props.cart.items] : [])

const cartItemCount = computed(() => cartItems.value.reduce((acc, i) => acc + i.qty, 0))

// Backend her Inertia response'unda 'cart' prop'unu paylaşır; UI bunu daima
// otoritatif kabul eder, böylece sayfa yenilense bile veri kaybolmaz.
watch(
	() => page.props.cart?.items,
	(items) => {
		if (Array.isArray(items)) cartItems.value = [...items]
	},
	{ deep: true }
)

function addToCart({ product, variant = null, color = null, size = null, qty = 1, openDrawer = true } = {}) {
	if (!product) return

	// `color` çağırandan obje ({name, hex}), string veya null gelebilir; backend string istiyor.
	const colorStr = color && typeof color === 'object'
		? (color.name ?? color.hex ?? null)
		: (color ?? null)

	router.post(
		'/cart',
		{
			product_id: product.id,
			variant_id: variant?.id ?? null,
			color: colorStr,
			size: size ?? null,
			qty,
		},
		{
			preserveScroll: true,
			preserveState: true,
			only: ['cart', 'flash', 'products', 'product'],
			onSuccess: () => {
				if (openDrawer) cartOpen.value = true
			},
			onError: (errs) => {
				showToast({
					type: 'error',
					title: 'Sepete eklenemedi',
					message: Object.values(errs)[0] || 'Sunucu hatası.',
				})
			},
		}
	)
}

function updateCartQty(key, qty) {
	if (qty <= 0) {
		removeCartItem(key)
		return
	}
	router.put(
		`/cart/${key}`,
		{ qty },
		{
			preserveScroll: true,
			preserveState: true,
			only: ['cart', 'products', 'product'],
			onError: (errs) => {
				showToast({
					type: 'error',
					title: 'Adet güncellenemedi',
					message: Object.values(errs)[0] || 'Sunucu hatası.',
				})
			},
		}
	)
}

function removeCartItem(key) {
	const removed = cartItems.value.find((i) => i.key === key)
	router.delete(`/cart/${key}`, {
		preserveScroll: true,
		preserveState: true,
		only: ['cart', 'products', 'product'],
		onSuccess: () => {
			if (removed) {
				showToast({ type: 'info', title: 'Sepetten kaldırıldı', message: removed.name })
			}
		},
	})
}

function clearCart({ silent = false } = {}) {
	router.delete('/cart', {
		preserveScroll: true,
		preserveState: true,
		only: ['cart', 'products', 'product'],
		onSuccess: () => {
			if (!silent) {
				showToast({ type: 'info', title: 'Sepet boşaltıldı', message: 'Tüm ürünler kaldırıldı.' })
			}
		},
	})
}

function onCheckout() {
	cartOpen.value = false
	router.visit('/checkout')
}

provide('cart', {
	add: addToCart,
	remove: removeCartItem,
	updateQty: updateCartQty,
	clear: clearCart,
	open: () => { cartOpen.value = true },
	items: cartItems,
	itemCount: cartItemCount,
})

/* ── Global Ctrl+K ── */
function onKey(e) {
	if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
		e.preventDefault()
		searchOpen.value = true
	}
}

onMounted(() => document.addEventListener('keydown', onKey))
onBeforeUnmount(() => document.removeEventListener('keydown', onKey))
</script>

<style scoped>
.main-content {
	grid-column: 2;
	grid-row: 2;
	padding: 20px 20px 20px 18px;
	overflow-x: hidden;
	overflow-y: auto;
	background: #f7f7fb;
	border-radius: 14px;
	box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
}
.main-content::-webkit-scrollbar { width: 4px; }
.main-content::-webkit-scrollbar-track { background: transparent; margin: 14px 0; }
.main-content::-webkit-scrollbar-thumb { background: rgba(0, 0, 0, 0.12); border-radius: 4px; }
.main-content::-webkit-scrollbar-thumb:hover { background: rgba(0, 0, 0, 0.22); }
</style>
