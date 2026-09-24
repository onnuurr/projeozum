import { ref, provide, computed, watch, onMounted, onBeforeUnmount } from 'vue'
import { usePage, router } from '@inertiajs/vue3'
import Swal from 'sweetalert2'
import { renderMenuIcon } from '@/menuIcons.js'
import { useToast } from '@/composables/useToast.js'

const STAFF_ROLES = ['designer', 'reviewer', 'superadmin']

/**
 * AppLayout kabuğunun (TopNav/Sidebar/drawer/toast/$swal) tüm state ve
 * provide() sözleşmesini barındırır. AppLayoutClassic bu composable'ı çağırır —
 * böylece sayfaların inject('showToast')/inject('$swal')/inject('drawer')
 * beklentisi karşılanır.
 */
export function useAppShell() {
	const page = usePage()

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

	/* ── Üst menü (header) = aktif kökün child'ları ──
	   Dropdown (child) ikon-only gösterilir; bir alt seviyesi (g) varsa o da
	   kendi ikonuyla taşınır ki TopNav bunu hover yerine click ile açılan bir
	   flyout listesi olarak render edebilsin (bkz. TopNav.vue nav-subdropdown).
	   Daha derin bir seviye (gg) çıkarsa yine firstLink ile tek linke düzleşir. */
	const navItems = computed(() => {
		const root = activeRoot.value
		if (!root) return []
		return (root.children || []).map((child) => ({
			name: child.label,
			icon: renderMenuIcon(child.icon),
			to: child.to || undefined,
			active: urlMatches(child.to),
			children: (child.children || []).map((g) => ({
				label: g.label,
				icon: renderMenuIcon(g.icon),
				to: g.to || undefined,
				children: (g.children || []).map((gg) => ({
					label: gg.label,
					icon: renderMenuIcon(gg.icon),
					to: firstLink(gg) || undefined,
				})),
			})),
		}))
	})

	/* ── Kullanıcı ── */
	// auth.user = User modeli, auth.roles = Spatie rol adları (HandleInertiaRequests).
	// Ön yüzde tek bir rol anahtarına indirgenir: superadmin | tenant | user
	const ROLE_LABELS = { superadmin: 'Süper Admin', tenant: 'İş Ortağı', user: 'Kullanıcı' }
	const role = computed(() => {
		const roles = page.props.auth?.roles ?? []
		if (roles.includes('superadmin')) return 'superadmin'
		if (roles.includes('tenant') || roles.includes('tenant-user') || page.props.auth?.user?.tenant_id) return 'tenant'
		return 'user'
	})
	const initialsOf = (name = '') =>
		name.trim().split(/\s+/).filter(Boolean).slice(0, 2).map((w) => w[0].toLocaleUpperCase('tr')).join('') || '?'

	const currentUser = computed(() => {
		const u = page.props.auth?.user
		if (!u) {
			return { id: 0, name: 'Misafir', shortName: 'Misafir', initials: '?', email: '', role: 'guest', roleLabel: 'Misafir' }
		}
		return {
			...u,
			shortName: u.shortName ?? u.name?.split(' ')[0] ?? '',
			initials: u.initials ?? initialsOf(u.name),
			role: role.value,
			roleLabel: ROLE_LABELS[role.value],
		}
	})
	const isStaff = computed(() => STAFF_ROLES.includes(currentUser.value.role))

	const ICON = (path) => `<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">${path}</svg>`
	const USER_ICONS = {
		profile: ICON('<path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/>'),
		company: ICON('<path d="M3 21h18M5 21V7l8-4v18M19 21V11l-6-4"/><path d="M9 9h.01M9 13h.01M9 17h.01"/>'),
		shield: ICON('<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>'),
		settings: ICON('<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 01-2.83 2.83l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/>'),
		bell: ICON('<path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9M13.73 21a2 2 0 01-3.46 0"/>'),
		keys: ICON('<rect x="2" y="6" width="20" height="12" rx="2"/><path d="M6 10h.01M10 10h.01M14 10h.01M18 10h.01M8 14h8"/>'),
		help: ICON('<circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 015.83 1c0 2-3 3-3 3M12 17h.01"/>'),
		logout: ICON('<path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9"/>'),
	}

	// Kullanıcı menüsü role göre kurulur:
	//   superadmin → Profilim + Süper Admin Paneli
	//   tenant     → Firma Profili (Profilim yerine) + firma ayarları
	//   diğer      → Profilim
	const userMenu = computed(() => {
		const byRole = {
			superadmin: [
				{ label: 'Profilim', icon: USER_ICONS.profile, to: '/profile' },
				{ label: 'Süper Admin Paneli', icon: USER_ICONS.shield, to: '/superadmin/settings' },
			],
			tenant: [
				{ label: 'Firma Profili', icon: USER_ICONS.company, to: '/tenant' },
				{ label: 'Ayarlar', icon: USER_ICONS.settings, to: '/tenant/settings' },
			],
			user: [
				{ label: 'Profilim', icon: USER_ICONS.profile, to: '/profile' },
			],
		}
		return [
			...(byRole[currentUser.value.role] ?? byRole.user),
			{ divider: true },
			{ label: 'Bildirimler', icon: USER_ICONS.bell },
			{ label: 'Kısayollar', icon: USER_ICONS.keys },
			{ label: 'Yardım & Destek', icon: USER_ICONS.help },
			{ divider: true },
			{ label: 'Çıkış Yap', icon: USER_ICONS.logout, action: 'logout', danger: true },
		]
	})

	/* ── Bildirimler ── */
	// Gerçek DB bildirimleri HandleInertiaRequests ile paylaşılır (AppLayout çanını besler).
	const notifications = computed(() => page.props.notifications || [])

	// Rol bazlı görünür bildirimler (forRoles tanımlanmış olanlar filtrelenir).
	const visibleNotifications = computed(() =>
		notifications.value.filter((n) => !n.forRoles || n.forRoles.includes(currentUser.value.role))
	)

	function markAllNotificationsRead() {
		router.post(route('notifications.read-all'), {}, {
			preserveScroll: true, preserveState: true,
			onSuccess: () => showToast({ type: 'success', title: 'Tümü Okundu', message: 'Tüm bildirimler okundu olarak işaretlendi.' }),
		})
	}

	function readNotification(id) {
		router.post(route('notifications.read', id), {}, { preserveScroll: true, preserveState: true })
	}

	// Bildirim paneli açıldığında çağrılır: kullanıcı hiçbir şeye tıklamadan,
	// arka planda sessizce (toast/uyarı YOK) tüm bildirimleri okundu işaretler.
	function markNotificationsReadOnOpen() {
		if (!visibleNotifications.value.some((n) => !n.read)) return
		router.post(route('notifications.read-all'), {}, { preserveScroll: true, preserveState: true })
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

	// searchRecent/searchQuick emoji ikonları eski TopNav'a özeldi — TopBar.vue (new/)
	// UiIcon üzerinden isimli ikon bekliyor, eşleşmeyen isim sessizce boş kalır.
	const SEARCH_ICON_MAP = { '📦': 'package', '👤': 'user', '📋': 'file_text', '➕': 'plus', '📊': 'chart' }

	const searchItems = computed(() => [
		...searchRecent.value.map((item) => ({ key: item.text, label: item.text, icon: SEARCH_ICON_MAP[item.icon] || 'search', group: item.sub })),
		...searchQuick.value.map((item) => ({ key: item.text, label: item.text, icon: SEARCH_ICON_MAP[item.icon] || 'search', group: item.sub })),
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
		}
		// DİKKAT: deep KULLANMA. Her tam Inertia yanıtı flash için YENİ bir nesne üretir,
		// bu yüzden sığ (referans) izleme yeni toast'ları zaten yakalar. deep:true ise,
		// onSuccess'te yapılan router.reload({ only: [...] }) gibi flash'i içermeyen kısmi
		// yenilemelerde page.props yeniden atandığında — flash referansı aynı (bayat toast)
		// kalsa bile — callback'i tekrar tetikleyip AYNI toast'ı ikinci kez gösteriyordu.
	)

	/* ── Anlık bildirim (Reverb) ── */
	// ShouldBroadcast implement eden HERHANGİ bir Notification için otomatik çalışır — sayfa
	// yenilenmeden çana eklenir. Şekil, HandleInertiaRequests::notificationsPayload() ile aynı
	// olacak şekilde normalize edilir (TopNav.vue bu alanları bekler: icon/iconType/title/desc/time/read/link).
	let echoChannel = null
	onMounted(() => {
		const userId = currentUser.value.id
		if (!userId || !window.Echo) return
		echoChannel = window.Echo.private(`App.Models.User.${userId}`)
		echoChannel.notification((notification) => {
			notifications.value.unshift({
				id: notification.id,
				icon: notification.icon ?? '🔔',
				iconType: notification.iconType ?? 'info',
				title: notification.title ?? 'Bildirim',
				desc: notification.desc ?? '',
				time: 'şimdi',
				read: false,
				link: notification.link ?? null,
			})
		})
	})
	onBeforeUnmount(() => {
		if (echoChannel) window.Echo.leave(`App.Models.User.${currentUser.value.id}`)
	})

	/* ── Global Ctrl+K (arama) ── */
	function onKey(e) {
		if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
			e.preventDefault()
			searchOpen.value = true
		}
	}
	onMounted(() => document.addEventListener('keydown', onKey))
	onBeforeUnmount(() => document.removeEventListener('keydown', onKey))

	return {
		page,
		currentUser,
		isStaff,
		userMenu,
		navItems,
		activeRoot,
		sidebarTop,
		sidebarBottom,
		visibleNotifications,
		markAllNotificationsRead,
		markNotificationsReadOnOpen,
		readNotification,
		onNotificationClick,
		handleUserAction,
		drawerOpen,
		onFilterApply,
		searchOpen,
		searchRecent,
		searchQuick,
		searchItems,
		toasts,
		showToast,
		dismissToast,
	}
}
