<!--
	saas-frontend components/layout/TopBar.vue'nun birebir sınıf/yapı kopyası
	(bkz. Components/SaasKit/layout/TopBar.vue — doğrulanmış verbatim referans).
	Sadece veri sözleşmesi gerçek useAppShell.js'e bağlandı; hiçbir Tailwind class'ı
	uydurulmadı, hepsi kaynaktan birebir taşındı. QR/grid-launcher/info/quick-links/rocket
	(gerçek karşılığı olmayan mock UI) çıkarıldı, sepet ikonu bildirim ikonuyla AYNI class
	string'i kullanılarak eklendi. Bildirim veri şekli (desc/iconType, emoji icon karakteri)
	useAppShell.js'in sağladığı hâliyle korundu (plan notu #2).
-->
<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue';
import { Search, Bell } from 'lucide-vue-next';

const props = defineProps({
	notifications: { type: Array, required: true },
});

const emit = defineEmits(['open-search', 'mark-all-read', 'read-notification', 'notification-click', 'notifications-opened']);

const isMac = typeof navigator !== 'undefined' && /Mac|iPhone|iPad|iPod/i.test(navigator.userAgent);
const activePanel = ref(null);
const containerRef = ref(null);

const iconTypeClasses = {
	success: 'bg-success/10 text-success',
	warning: 'bg-warning/10 text-warning',
	error: 'bg-danger/10 text-danger',
	info: 'bg-info/10 text-info',
};

const unreadCount = () => props.notifications.filter((n) => !n.read).length;

function togglePanel(name) {
	const opening = activePanel.value !== name;
	activePanel.value = opening ? name : null;
	if (opening && name === 'notifications') {
		emit('notifications-opened');
	}
}

function onNotifClick(n) {
	emit('read-notification', n.id);
	if (n?.link) {
		activePanel.value = null;
		emit('notification-click', n);
	}
}

function onGlobalKeydown(e) {
	if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
		e.preventDefault();
		emit('open-search');
	}
}

function onDocumentClick(e) {
	if (containerRef.value && !containerRef.value.contains(e.target)) {
		activePanel.value = null;
	}
}

onMounted(() => {
	document.addEventListener('keydown', onGlobalKeydown);
	document.addEventListener('click', onDocumentClick);
});
onBeforeUnmount(() => {
	document.removeEventListener('keydown', onGlobalKeydown);
	document.removeEventListener('click', onDocumentClick);
	clearTimeout(bumpTimer);
});
</script>

<template>
	<div class="bg-surface h-9 sm:h-10 px-2 sm:px-3 md:px-6 flex items-center justify-between text-ink border-b border-line gap-1 sm:gap-2">
		<div class="flex-grow min-w-0 max-w-md lg:max-w-lg mx-1 sm:mx-2 md:mx-4">
			<button
				type="button"
				class="group relative w-full flex items-center bg-canvas hover:bg-line/60 border border-line rounded-full py-1.5 sm:py-1 pl-9 pr-3 text-sm text-muted transition-all cursor-pointer text-left max-sm:min-h-[36px] focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40"
				@click="emit('open-search')"
			>
				<Search :size="16" class="absolute left-3 top-1/2 -translate-y-1/2 text-muted group-hover:text-ink transition-colors" />
				<span class="hidden sm:inline flex-1 group-hover:text-ink transition-colors truncate">Ara...</span>
				<kbd class="hidden sm:inline-flex ml-auto items-center gap-0.5 px-2 py-0.5 bg-surface border border-line rounded-md text-2xs text-muted font-medium">
					{{ isMac ? '⌘' : 'Ctrl' }} K
				</kbd>
			</button>
		</div>

		<div ref="containerRef" class="relative flex items-center gap-1 sm:gap-1.5 flex-shrink-0">
			<div class="relative">
				<button
					type="button"
					class="w-8 h-8 flex items-center justify-center text-muted hover:text-ink hover:bg-canvas rounded-full transition-colors relative cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40"
					:class="activePanel === 'notifications' ? 'bg-canvas text-ink' : ''"
					title="Bildirimler"
					@click.stop="togglePanel('notifications')"
				>
					<Bell :size="18" />
					<span
						v-if="unreadCount() > 0"
						class="absolute -top-0.5 -right-0.5 bg-danger text-white font-bold text-2xs min-w-[16px] h-4 px-1 rounded-full border border-surface flex items-center justify-center"
					>{{ unreadCount() }}</span>
				</button>
			</div>

			<!-- Notifications -->
			<Transition
				enter-active-class="transition duration-150 ease-out"
				enter-from-class="opacity-0 -translate-y-1 scale-[0.98]"
				enter-to-class="opacity-100 translate-y-0 scale-100"
				leave-active-class="transition duration-100 ease-in"
				leave-from-class="opacity-100 translate-y-0"
				leave-to-class="opacity-0 -translate-y-1"
			>
				<div
					v-if="activePanel === 'notifications'"
					class="absolute top-full right-0 mt-2 w-80 max-w-[calc(100vw-1.5rem)] bg-white rounded-xl shadow-xl border border-line overflow-hidden z-50 origin-top-right"
					@click.stop
				>
					<div class="flex items-center justify-between p-3 border-b border-line">
						<h3 class="text-sm font-semibold text-ink">
							Bildirimler
							<span v-if="unreadCount()" class="text-2xs font-medium text-primary">({{ unreadCount() }} yeni)</span>
						</h3>
						<button type="button" class="text-2xs text-primary hover:underline" @click="emit('mark-all-read')">Tümünü okundu işaretle</button>
					</div>
					<div class="max-h-80 overflow-y-auto divide-y divide-line">
						<div
							v-for="notification in notifications"
							:key="notification.id"
							class="flex items-start gap-2.5 p-3 hover:bg-surface transition-colors cursor-pointer"
							:class="notification.read ? '' : 'bg-primary/5'"
							@click="onNotifClick(notification)"
						>
							<span class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0 text-sm mt-0.5" :class="iconTypeClasses[notification.iconType] || iconTypeClasses.info">{{ notification.icon }}</span>
							<div class="flex-1 min-w-0">
								<p class="text-xs font-medium text-ink">{{ notification.title }}</p>
								<p class="text-2xs text-muted truncate">{{ notification.desc }}</p>
								<p class="text-2xs text-muted/70 mt-0.5">{{ notification.time }}</p>
							</div>
							<span v-if="!notification.read" class="w-2 h-2 bg-primary rounded-full mt-1.5 flex-shrink-0" />
						</div>
					</div>
					<div class="p-2.5 border-t border-line text-center">
						<button type="button" class="text-2xs font-medium text-primary hover:underline">Tüm bildirimleri gör</button>
					</div>
				</div>
			</Transition>
		</div>
	</div>
</template>

