<template>
	<div class="layout-root">
		<TopNav
			:nav-items="navItems"
			:sidebar-items="sidebarTop"
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
import TopNav from '@/Components/TopNav.vue'
import LeftSidebar from '@/Components/LeftSidebar.vue'
import FilterDrawer from '@/Components/FilterDrawer.vue'
import SearchModal from '@/Components/SearchModal.vue'
import CartDrawer from '@/Components/CartDrawer.vue'
import ToastContainer from '@/Components/ToastContainer.vue'
import { useAppShell } from '@/composables/useAppShell.js'

const {
	navItems,
	sidebarTop,
	sidebarBottom,
	visibleNotifications,
	currentUser,
	userMenu,
	cartItemCount,
	markAllNotificationsRead,
	readNotification,
	handleUserAction,
	onNotificationClick,
	drawerOpen,
	onFilterApply,
	searchOpen,
	searchRecent,
	searchQuick,
	cartOpen,
	cartItems,
	updateCartQty,
	removeCartItem,
	clearCart,
	onCheckout,
	toasts,
	showToast,
	dismissToast,
} = useAppShell()
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

/* ── Dar ekran (telefon): iç boşlukları daralt — içerik alanını büyüt.
   Alt boşluk, TopNav.vue'daki sabit mobil sekme çubuğunun (.mobile-tab-bar)
   içeriğin üzerine binmemesi için genişletilir. ── */
@media (max-width: 640px) {
	.main-content { grid-column: 1; padding: 14px 12px calc(64px + env(safe-area-inset-bottom)); border-radius: 10px; }
}
</style>
