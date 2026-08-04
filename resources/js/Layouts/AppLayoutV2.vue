<!--
	Faz 1 placeholder kabuk. Gerçek görsel tasarım kaynağı netleşene kadar
	AppLayoutClassic ile aynı bileşenleri (TopNav/LeftSidebar/drawer'lar) kullanır —
	amaç şu an "yeni görünüm" değil, flag + katman ayrımının uçtan uca çalıştığını
	kanıtlamak. Token'lar [data-theme="v2"] altında ayrışık (bkz. resources/css/app.css);
	tasarım netleşince yalnızca bu dosya + o CSS bloğu doldurulacak, iş mantığına
	(useAppShell) dokunulmayacak.
-->
<template>
	<div class="layout-root layout-root-v2">
		<div class="v2-badge">V2 · Beta</div>

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

		<main class="main-content-v2">
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
.layout-root-v2 {
	position: relative;
}

.v2-badge {
	position: fixed;
	top: 6px;
	right: 8px;
	z-index: 200;
	font-size: 10px;
	font-weight: 700;
	letter-spacing: 0.04em;
	padding: 2px 8px;
	border-radius: 999px;
	background: rgb(var(--color-primary) / 0.15);
	color: rgb(var(--color-primary-hover));
	pointer-events: none;
}

.main-content-v2 {
	grid-column: 2;
	grid-row: 2;
	padding: 22px;
	overflow-x: hidden;
	overflow-y: auto;
	background: rgb(var(--color-surface));
	border: 1px solid rgb(var(--color-border));
	border-radius: 16px;
	box-shadow: 0 8px 32px rgba(0, 0, 0, 0.08);
}
.main-content-v2::-webkit-scrollbar { width: 4px; }
.main-content-v2::-webkit-scrollbar-track { background: transparent; margin: 14px 0; }
.main-content-v2::-webkit-scrollbar-thumb { background: rgba(0, 0, 0, 0.12); border-radius: 4px; }
.main-content-v2::-webkit-scrollbar-thumb:hover { background: rgba(0, 0, 0, 0.22); }

@media (max-width: 640px) {
	.main-content-v2 { grid-column: 1; padding: 14px 12px calc(64px + env(safe-area-inset-bottom)); border-radius: 12px; }
}
</style>
