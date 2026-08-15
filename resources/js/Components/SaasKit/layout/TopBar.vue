<!--
	saas-frontend components/layout/TopBar.vue portu. Tek fark: <UiIcon> app.js'te
	global değil, burada Components/UiIcon.vue açıkça import ediliyor
	(bkz. PORTING_GUIDE.md §4.9) ve SearchModal bu kabuğun kendi ./SearchModal.vue'sundan geliyor.
-->
<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue';
import UiIcon from '@/Components/UiIcon.vue';
import SearchModal from './SearchModal.vue';

const props = defineProps({
    version: {
        type: String,
        default: '1.0.0',
    },
    menuItems: {
        type: Array,
        default: () => [
            { key: 'home', label: 'Ana Sayfa', icon: 'home' },
            { key: 'orders', label: 'Siparişler', icon: 'shopping_bag' },
            { key: 'products', label: 'Ürünler', icon: 'package' },
            { key: 'marketplace', label: 'Pazaryeri', icon: 'store' },
            { key: 'campaigns', label: 'Kampanyalar', icon: 'percent' },
            { key: 'customers', label: 'Müşteriler', icon: 'users' },
            { key: 'design', label: 'Tasarım', icon: 'palette' },
            { key: 'reports', label: 'Raporlar', icon: 'chart-line' },
            { key: 'tools', label: 'Araçlar', icon: 'wrench' },
            { key: 'settings', label: 'Ayarlar', icon: 'settings' },
        ],
    },
    notificationCount: {
        type: Number,
        default: 3,
    },
    notifications: {
        type: Array,
        default: () => [
            { id: 1, icon: 'shopping_bag', color: 'text-primary', title: 'Yeni sipariş alındı', message: 'Sipariş #1284 — Ahmet Yılmaz — ₺1.250', time: '2 dakika önce', read: false },
            { id: 2, icon: 'check_circle', color: 'text-success', title: 'Ödeme alındı', message: '₺2.450 ödeme başarıyla alındı', time: '15 dakika önce', read: false },
            { id: 3, icon: 'alert', color: 'text-warning', title: 'Stok uyarısı', message: 'Ürün #456 kritik stok seviyesinde', time: '1 saat önce', read: true },
            { id: 4, icon: 'user_add', color: 'text-primary', title: 'Yeni müşteri', message: 'Müşteri #892 kaydoldu', time: '3 saat önce', read: true },
        ],
    },
});

const quickLinks = [
    { label: 'Siteyi Görüntüle', icon: 'external-link', href: '#' },
    { label: 'Destek Sistemi', icon: 'headphones', href: '#' },
    { label: 'Satış Ortaklığı', icon: 'user_add', href: '#' },
];

const showSearch = ref(false);
const isMac = typeof navigator !== 'undefined' && /Mac|iPhone|iPad|iPod/i.test(navigator.userAgent);
const activePanel = ref(null);
const containerRef = ref(null);

const unreadCount = () => props.notifications.filter((n) => !n.read).length;

const helpLinks = [
    { icon: 'question', label: 'Yardım Merkezi' },
    { icon: 'keyboard', label: 'Klavye Kısayolları' },
    { icon: 'file-text', label: 'Sürüm Notları' },
];

const qrPattern = Array.from({ length: 64 }, (_, i) => {
    const cornerMarkers = [0, 1, 2, 6, 7, 8, 9, 15, 16, 17, 48, 49, 50, 56, 57, 58];
    if (cornerMarkers.includes(i)) return true;
    return ((i * 37 + 11) % 7) < 3;
});

function openSearch() {
    showSearch.value = true;
}

function togglePanel(name) {
    activePanel.value = activePanel.value === name ? null : name;
}

function onGlobalKeydown(e) {
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
        e.preventDefault();
        openSearch();
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
});
</script>

<template>
    <div class="bg-ink h-9 sm:h-10 px-2 sm:px-3 md:px-6 flex items-center justify-between text-white/90 gap-1 sm:gap-2">
        <span class="hidden md:inline font-bold text-xs tracking-wider opacity-70 flex-shrink-0">
            Versiyon {{ version }}
        </span>

        <div class="flex-grow min-w-0 max-w-md lg:max-w-lg mx-1 sm:mx-2 md:mx-4">
            <button
                type="button"
                class="group relative w-full flex items-center bg-white/10 hover:bg-white/20 border border-white/10 rounded-full py-1.5 sm:py-1 pl-9 pr-3 text-sm text-white/60 transition-all cursor-pointer text-left max-sm:min-h-[36px] focus:outline-none focus-visible:ring-2 focus-visible:ring-white/40"
                @click="openSearch"
            >
                <UiIcon name="search" :size="16" class="absolute left-3 top-1/2 -translate-y-1/2 text-white/50 group-hover:text-white/80 transition-colors" />
                <span class="hidden sm:inline flex-1 group-hover:text-white/80 transition-colors truncate">Ara...</span>
                <kbd class="hidden sm:inline-flex ml-auto items-center gap-0.5 px-2 py-0.5 bg-white/10 border border-white/20 rounded-md text-2xs text-white/60 font-medium">
                    {{ isMac ? '⌘' : 'Ctrl' }} K
                </kbd>
            </button>
        </div>

        <div ref="containerRef" class="relative flex items-center gap-1 sm:gap-1.5 flex-shrink-0">
            <!-- Quick links (lg+) -->
            <div class="hidden lg:flex items-center gap-0.5 flex-shrink-0">
                <a
                    v-for="link in quickLinks"
                    :key="link.label"
                    :href="link.href"
                    class="hidden xl:flex items-center gap-1.5 px-2 py-1 hover:bg-white/10 rounded transition-colors font-bold text-xs"
                    :title="link.label"
                >
                    <UiIcon :name="link.icon" :size="16" />
                    <span class="hidden 2xl:inline">{{ link.label }}</span>
                </a>
            </div>

            <!-- QR (xl+) -->
            <div class="relative hidden xl:block">
                <button
                    type="button"
                    class="w-8 h-8 flex items-center justify-center text-white/80 hover:text-white hover:bg-white/10 rounded-full transition-colors cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-white/40"
                    :class="activePanel === 'qr' ? 'bg-white/10 text-white' : ''"
                    title="QR Kodu"
                    @click.stop="togglePanel('qr')"
                >
                    <UiIcon name="qr-code" :size="18" />
                </button>
            </div>

            <div class="relative">
                <button
                    type="button"
                    class="w-8 h-8 flex items-center justify-center text-white/80 hover:text-white hover:bg-white/10 rounded-full transition-colors relative cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-white/40"
                    :class="activePanel === 'notifications' ? 'bg-white/10 text-white' : ''"
                    title="Bildirimler"
                    @click.stop="togglePanel('notifications')"
                >
                    <UiIcon name="bell" :size="18" />
                    <span
                        v-if="notificationCount > 0"
                        class="absolute -top-0.5 -right-0.5 bg-danger text-white font-bold text-2xs min-w-[16px] h-4 px-1 rounded-full border border-ink flex items-center justify-center"
                    >
                        {{ notificationCount }}
                    </span>
                </button>
            </div>

            <!-- Grid launcher (xl+) -->
            <div class="relative hidden xl:block">
                <button
                    type="button"
                    class="w-8 h-8 flex items-center justify-center text-white/80 hover:text-white hover:bg-white/10 rounded-full transition-colors cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-white/40"
                    :class="activePanel === 'grid' ? 'bg-white/10 text-white' : ''"
                    title="Grid Görünümü"
                    @click.stop="togglePanel('grid')"
                >
                    <UiIcon name="layout-grid" :size="18" />
                </button>
            </div>

            <!-- Info (xl+) -->
            <div class="relative hidden xl:block">
                <button
                    type="button"
                    class="w-8 h-8 flex items-center justify-center text-white/80 hover:text-white hover:bg-white/10 rounded-full transition-colors cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-white/40"
                    :class="activePanel === 'info' ? 'bg-white/10 text-white' : ''"
                    title="Bilgi"
                    @click.stop="togglePanel('info')"
                >
                    <UiIcon name="info" :size="18" />
                </button>
            </div>

            <!-- More / overflow (below xl) -->
            <div class="relative xl:hidden">
                <button
                    type="button"
                    class="w-8 h-8 flex items-center justify-center text-white/80 hover:text-white hover:bg-white/10 rounded-full transition-colors cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-white/40"
                    :class="activePanel === 'more' ? 'bg-white/10 text-white' : ''"
                    title="Daha Fazla"
                    @click="togglePanel('more')"
                >
                    <UiIcon name="more-2" :size="18" />
                </button>
            </div>

            <button
                class="bg-primary/10 text-primary h-8 w-8 sm:h-9 sm:w-9 rounded-full flex items-center justify-center hover:brightness-110 active:scale-95 transition-all cursor-pointer flex-shrink-0"
                title="Hızlı Erişim"
            >
                <UiIcon name="rocket" :size="18" />
            </button>

            <!-- ===== Panels ===== -->
            <!-- QR -->
            <Transition
                enter-active-class="transition duration-150 ease-out"
                enter-from-class="opacity-0 -translate-y-1 scale-[0.98]"
                enter-to-class="opacity-100 translate-y-0 scale-100"
                leave-active-class="transition duration-100 ease-in"
                leave-from-class="opacity-100 translate-y-0"
                leave-to-class="opacity-0 -translate-y-1"
            >
                <div
                    v-if="activePanel === 'qr'"
                    class="absolute top-full right-0 mt-2 w-64 max-w-[calc(100vw-1.5rem)] bg-white rounded-xl shadow-xl border border-line overflow-hidden z-50 origin-top-right text-ink"
                    @click.stop
                >
                    <div class="p-4 flex flex-col items-center gap-3">
                        <div class="grid grid-cols-8 gap-0.5 p-2 bg-white border border-line rounded">
                            <span
                                v-for="(filled, i) in qrPattern"
                                :key="i"
                                class="w-2.5 h-2.5"
                                :class="filled ? 'bg-ink' : 'bg-transparent'"
                            />
                        </div>
                        <p class="text-xs font-medium text-ink text-center">Mobil Uygulama</p>
                        <p class="text-2xs text-muted text-center">Hızlı erişim için bu kodu telefonunuzla tarayın</p>
                    </div>
                </div>
            </Transition>

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
                        <button type="button" class="text-2xs text-primary hover:underline">Tümünü okundu işaretle</button>
                    </div>
                    <div class="max-h-80 overflow-y-auto divide-y divide-line">
                        <div
                            v-for="notification in notifications"
                            :key="notification.id"
                            class="flex items-start gap-2.5 p-3 hover:bg-surface transition-colors cursor-pointer"
                            :class="notification.read ? '' : 'bg-primary/5'"
                        >
                            <UiIcon :name="notification.icon" :size="18" :class="[notification.color, 'mt-0.5']" />
                            <div class="flex-1 min-w-0">
                                <p class="text-xs font-medium text-ink">{{ notification.title }}</p>
                                <p class="text-2xs text-muted truncate">{{ notification.message }}</p>
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

            <!-- Grid launcher -->
            <Transition
                enter-active-class="transition duration-150 ease-out"
                enter-from-class="opacity-0 -translate-y-1 scale-[0.98]"
                enter-to-class="opacity-100 translate-y-0 scale-100"
                leave-active-class="transition duration-100 ease-in"
                leave-from-class="opacity-100 translate-y-0"
                leave-to-class="opacity-0 -translate-y-1"
            >
                <div
                    v-if="activePanel === 'grid'"
                    class="absolute top-full right-0 mt-2 w-72 max-w-[calc(100vw-1.5rem)] bg-white rounded-xl shadow-xl border border-line overflow-hidden z-50 origin-top-right"
                    @click.stop
                >
                    <div class="p-3 border-b border-line">
                        <h3 class="text-sm font-semibold text-ink">Hızlı Erişim</h3>
                    </div>
                    <div class="p-2 grid grid-cols-3 gap-1">
                        <a
                            v-for="item in menuItems"
                            :key="item.key"
                            class="flex flex-col items-center justify-center gap-1.5 p-3 rounded-lg hover:bg-surface transition-colors cursor-pointer"
                            @click.prevent="togglePanel(null)"
                        >
                            <UiIcon :name="item.icon" :size="20" class="text-primary" />
                            <span class="text-2xs font-medium text-muted text-center leading-tight">{{ item.label }}</span>
                        </a>
                    </div>
                </div>
            </Transition>

            <!-- Info -->
            <Transition
                enter-active-class="transition duration-150 ease-out"
                enter-from-class="opacity-0 -translate-y-1 scale-[0.98]"
                enter-to-class="opacity-100 translate-y-0 scale-100"
                leave-active-class="transition duration-100 ease-in"
                leave-from-class="opacity-100 translate-y-0"
                leave-to-class="opacity-0 -translate-y-1"
            >
                <div
                    v-if="activePanel === 'info'"
                    class="absolute top-full right-0 mt-2 w-64 max-w-[calc(100vw-1.5rem)] bg-white rounded-xl shadow-xl border border-line overflow-hidden z-50 origin-top-right"
                    @click.stop
                >
                    <div class="p-3 border-b border-line">
                        <h3 class="text-sm font-semibold text-ink">Bilgi</h3>
                        <p class="text-2xs text-muted">Versiyon {{ version }}</p>
                    </div>
                    <div class="p-1.5">
                        <a
                            v-for="link in helpLinks"
                            :key="link.label"
                            class="flex items-center gap-2.5 px-2.5 py-2 rounded-lg hover:bg-surface transition-colors cursor-pointer text-xs text-ink"
                        >
                            <UiIcon :name="link.icon" :size="16" class="text-muted" />
                            {{ link.label }}
                        </a>
                    </div>
                </div>
            </Transition>

            <!-- More / overflow -->
            <Transition
                enter-active-class="transition duration-150 ease-out"
                enter-from-class="opacity-0 -translate-y-1 scale-[0.98]"
                enter-to-class="opacity-100 translate-y-0 scale-100"
                leave-active-class="transition duration-100 ease-in"
                leave-from-class="opacity-100 translate-y-0"
                leave-to-class="opacity-0 -translate-y-1"
            >
                <div
                    v-if="activePanel === 'more'"
                    class="absolute top-full right-0 mt-2 w-64 max-w-[calc(100vw-1.5rem)] bg-white rounded-xl shadow-xl border border-line overflow-hidden z-50 origin-top-right"
                    @click.stop
                >
                    <div class="p-3 border-b border-line">
                        <h3 class="text-sm font-semibold text-ink">OzumServer</h3>
                        <p class="text-2xs text-muted">Versiyon {{ version }}</p>
                    </div>
                    <div class="p-1.5">
                        <button
                            type="button"
                            class="w-full flex items-center gap-2.5 px-2.5 py-2 rounded-lg hover:bg-surface transition-colors cursor-pointer text-xs text-ink"
                            @click="togglePanel('qr')"
                        >
                            <UiIcon name="qr-code" :size="16" class="text-muted" />
                            QR Kodu
                        </button>
                        <button
                            type="button"
                            class="w-full flex items-center gap-2.5 px-2.5 py-2 rounded-lg hover:bg-surface transition-colors cursor-pointer text-xs text-ink"
                            @click="togglePanel('grid')"
                        >
                            <UiIcon name="layout-grid" :size="16" class="text-muted" />
                            Hızlı Erişim
                        </button>
                        <button
                            type="button"
                            class="w-full flex items-center gap-2.5 px-2.5 py-2 rounded-lg hover:bg-surface transition-colors cursor-pointer text-xs text-ink"
                            @click="togglePanel('info')"
                        >
                            <UiIcon name="info" :size="16" class="text-muted" />
                            Bilgi
                        </button>
                    </div>
                    <div class="p-1.5 border-t border-line">
                        <a
                            v-for="link in quickLinks"
                            :key="link.label"
                            :href="link.href"
                            class="flex items-center gap-2.5 px-2.5 py-2 rounded-lg hover:bg-surface transition-colors cursor-pointer text-xs text-ink"
                        >
                            <UiIcon :name="link.icon" :size="16" class="text-muted" />
                            {{ link.label }}
                        </a>
                    </div>
                    <div class="p-1.5 border-t border-line">
                        <a
                            v-for="link in helpLinks"
                            :key="link.label"
                            class="flex items-center gap-2.5 px-2.5 py-2 rounded-lg hover:bg-surface transition-colors cursor-pointer text-xs text-ink"
                        >
                            <UiIcon :name="link.icon" :size="16" class="text-muted" />
                            {{ link.label }}
                        </a>
                    </div>
                </div>
            </Transition>
        </div>

        <SearchModal v-model="showSearch" />
    </div>
</template>
