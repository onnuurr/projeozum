<template>
	<div class="h-screen overflow-hidden flex flex-col">
		<header class="w-full flex flex-col shadow-sm flex-shrink-0 relative z-10">
			<TopBar
				:notifications="visibleNotifications"
				:cart-item-count="cartItemCount"
				@open-search="searchOpen = true"
				@open-cart="cartOpen = true"
				@mark-all-read="markAllNotificationsRead"
				@read-notification="readNotification"
				@notification-click="onNotificationClick"
			/>

			<MainNav
				:menu-items="sidebarTop"
				:user="currentUser"
				:user-menu="userMenu"
				@user-action="handleUserAction"
			/>

			<ActionBar :title="activeRoot?.label" :tabs="navItems" />
		</header>

		<main class="flex-1 min-h-0 mx-1 sm:mx-2 my-1 sm:my-2 bg-surface rounded-xl sm:rounded-2xl shadow-sm overflow-hidden">
			<div class="main-content h-full overflow-y-auto p-3 sm:p-4 md:p-6 space-y-4 sm:space-y-5">
				<slot />
			</div>
		</main>

		<FilterDrawer
			v-model="drawerOpen"
			@apply="onFilterApply"
			@reset="showToast({ type: 'info', title: 'Filtreler Sıfırlandı', message: 'Tüm filtreler kaldırıldı.' })"
		/>

		<CartDrawer
			v-model="cartOpen"
			:items="cartItems"
			@update-qty="updateCartQty"
			@remove="removeCartItem"
			@clear="clearCart"
			@checkout="onCheckout"
		/>

		<SearchModal v-model="searchOpen" :recent="searchRecent" :quick="searchQuick" />

		<ToastContainer :toasts="toasts" @dismiss="dismissToast" />
	</div>
</template>

<script setup>
import TopBar from '@/Components/TopBar.vue'
import MainNav from '@/Components/MainNav.vue'
import ActionBar from '@/Components/ActionBar.vue'
import FilterDrawer from '@/Components/FilterDrawer.vue'
import CartDrawer from '@/Components/CartDrawer.vue'
import SearchModal from '@/Components/SearchModal.vue'
import ToastContainer from '@/Components/ToastContainer.vue'
import { useAppShell } from '@/composables/useAppShell.js'

const {
	navItems,
	activeRoot,
	sidebarTop,
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
/* Sayfa (body) arka planı gri kalır (--color-bg), <main> saas-frontend'in kendi
   mx-1/my-1 + bg-surface + shadow-sm kartı olarak onun üzerinde yüzer — bu ayrım
   olmadan içerik sayfayla aynı renkte görünüp "düz beyaz" hissi veriyordu. Sadece ince
   scrollbar burada, geri kalan spacing/renk/gölge tamamen SaasKit/layout/AppLayout.vue'nun
   birebir Tailwind class'ları (bkz. şablon), uydurma piksel değeri yok. */
.main-content::-webkit-scrollbar { width: 4px; }
.main-content::-webkit-scrollbar-track { background: transparent; margin: 14px 0; }
.main-content::-webkit-scrollbar-thumb { background: rgba(0, 0, 0, 0.12); border-radius: 4px; }
.main-content::-webkit-scrollbar-thumb:hover { background: rgba(0, 0, 0, 0.22); }
</style>
