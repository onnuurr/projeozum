<!--
	saas-frontend components/layout/MainNav.vue portu. İki fark: <UiIcon> Components/UiIcon.vue'dan
	açıkça import ediliyor (global registration yok), Offcanvas bu projenin MEVCUT
	Components/Offcanvas.vue'sundan (Batch 2'de zaten taşınmıştı) geliyor — API birebir uyumlu
	(v-model, position, title, width), yeniden yazmaya gerek yoktu.
-->
<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue';
import UiIcon from '@/Components/UiIcon.vue';
import AppLogo from './AppLogo.vue';
import Offcanvas from '@/Components/Offcanvas.vue';

const props = defineProps({
    menuItems: {
        type: Array,
        default: () => [
            { key: 'orders', label: 'SİPARİŞLER', icon: 'shopping_bag' },
            { key: 'products', label: 'ÜRÜNLER', icon: 'package' },
            { key: 'marketplace', label: 'PAZARYERİ', icon: 'store' },
            { key: 'campaigns', label: 'KAMPANYALAR', icon: 'percent' },
            { key: 'customers', label: 'İŞ ORTAKLARI', icon: 'users' },
            { key: 'design', label: 'STÜDYO', icon: 'palette' },
            { key: 'reports', label: 'RAPORLAR', icon: 'chart-line' },
            { key: 'tools', label: 'ATÖLYE', icon: 'wrench' },
            { key: 'settings', label: 'AYARLAR', icon: 'settings' },
        ],
    },
    activeItem: {
        type: String,
        default: 'products',
    },
    appName: {
        type: String,
        default: 'OzumServer',
    },
    userName: {
        type: String,
        default: 'Admin Kullanıcı',
    },
    userEmail: {
        type: String,
        default: 'admin@ozumserver.com',
    },
    userInitials: {
        type: String,
        default: 'AK',
    },
});

const emit = defineEmits(['navigate', 'profile-click', 'logout']);

const activePanel = ref(null);
const containerRef = ref(null);
const mobileMenuOpen = ref(false);

function togglePanel(name) {
    activePanel.value = activePanel.value === name ? null : name;
    if (name === 'profile') emit('profile-click');
}

function toggleMobileMenu() {
    mobileMenuOpen.value = !mobileMenuOpen.value;
}

function closePanel() {
    activePanel.value = null;
}

function navigate(key) {
    emit('navigate', key);
    closePanel();
    mobileMenuOpen.value = false;
}

function onDocumentClick(e) {
    if (containerRef.value && !containerRef.value.contains(e.target)) {
        closePanel();
    }
}

function onEscape(e) {
    if (e.key === 'Escape') {
        closePanel();
        if (document.activeElement instanceof HTMLElement) {
            document.activeElement.blur();
        }
    }
}

onMounted(() => {
    document.addEventListener('click', onDocumentClick);
    document.addEventListener('keydown', onEscape);
});

onBeforeUnmount(() => {
    document.removeEventListener('click', onDocumentClick);
    document.removeEventListener('keydown', onEscape);
});
</script>

<template>
    <nav class="bg-primary h-14 sm:h-[72px] px-3 sm:px-6 flex items-center justify-between relative">
        <div class="flex items-center h-full min-w-0">
            <button
                type="button"
                class="lg:hidden flex-shrink-0 mr-2 sm:mr-4 w-9 h-9 flex items-center justify-center text-white hover:bg-white/10 rounded-full transition-colors cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-white/50"
                :class="mobileMenuOpen ? 'bg-white/10' : ''"
                title="Menü"
                @click.stop="toggleMobileMenu"
            >
                <UiIcon :name="mobileMenuOpen ? 'close' : 'menu'" :size="24" />
            </button>

            <AppLogo :app-name="appName" class="mr-6 2xl:mr-10 flex-shrink-0" />

            <div class="hidden lg:flex items-end h-full gap-1 min-w-0">
                <a
                    v-for="item in menuItems"
                    :key="item.key"
                    :class="[
                        'flex flex-col items-center justify-center px-4 transition-all cursor-pointer',
                        item.key === activeItem
                            ? 'nav-item-active h-[64px] bg-white text-primary rounded-t-lg'
                            : 'h-[64px] text-white opacity-80 hover:opacity-100',
                    ]"
                    @click.prevent="navigate(item.key)"
                >
                    <UiIcon :name="item.icon" :size="18" class="mb-1" />
                    <span class="font-bold text-2xs leading-tight">{{ item.label }}</span>
                </a>
            </div>
        </div>

        <!-- Mobile nav drawer -->
        <Offcanvas
            v-model="mobileMenuOpen"
            position="left"
            title="Menü"
            width="w-72"
        >
            <div class="-m-3">
                <a
                    v-for="item in menuItems"
                    :key="item.key"
                    :class="[
                        'flex items-center gap-3 px-4 py-3 text-xs font-medium transition-colors cursor-pointer border-b border-line last:border-b-0',
                        item.key === activeItem
                            ? 'bg-primary/10 text-primary'
                            : 'text-ink hover:bg-surface',
                    ]"
                    @click.prevent="navigate(item.key)"
                >
                    <UiIcon :name="item.icon" :size="18" />
                    {{ item.label }}
                </a>
            </div>
        </Offcanvas>

        <div ref="containerRef" class="relative z-20 flex items-center flex-shrink-0">
            <!-- Profile -->
            <div class="relative">
                <button
                    class="w-10 h-10 rounded-full bg-white/15 ring-1 ring-white/30 text-white text-sm font-bold flex items-center justify-center hover:bg-white/25 transition-all cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-white/50"
                    :class="activePanel === 'profile' ? 'bg-white/25 ring-white/40' : ''"
                    title="Profil"
                    @click.stop="togglePanel('profile')"
                >
                    {{ userInitials }}
                </button>
            </div>

            <!-- ===== Profile panel ===== -->
            <Transition
                enter-active-class="transition duration-150 ease-out"
                enter-from-class="opacity-0 -translate-y-1 scale-[0.98]"
                enter-to-class="opacity-100 translate-y-0 scale-100"
                leave-active-class="transition duration-100 ease-in"
                leave-from-class="opacity-100 translate-y-0"
                leave-to-class="opacity-0 -translate-y-1"
            >
                <div
                    v-if="activePanel === 'profile'"
                    class="absolute top-full right-0 mt-2 w-64 max-w-[calc(100vw-1.5rem)] bg-white rounded-xl shadow-xl border border-line overflow-hidden z-50 origin-top-right"
                    @click.stop
                >
                    <div class="flex items-center gap-3 p-3 border-b border-line">
                        <div class="w-10 h-10 rounded-full bg-primary text-white flex items-center justify-center text-sm font-bold flex-shrink-0">
                            {{ userInitials }}
                        </div>
                        <div class="min-w-0">
                            <p class="text-xs font-semibold text-ink truncate">{{ userName }}</p>
                            <p class="text-2xs text-muted truncate">{{ userEmail }}</p>
                        </div>
                    </div>
                    <div class="p-1.5">
                        <a class="flex items-center gap-2.5 px-2.5 py-2 rounded-lg hover:bg-surface transition-colors cursor-pointer text-xs text-ink">
                            <UiIcon name="user" :size="16" class="text-muted" />
                            Profilim
                        </a>
                        <a class="flex items-center gap-2.5 px-2.5 py-2 rounded-lg hover:bg-surface transition-colors cursor-pointer text-xs text-ink">
                            <UiIcon name="settings" :size="16" class="text-muted" />
                            Hesap Ayarları
                        </a>
                        <a class="flex items-center gap-2.5 px-2.5 py-2 rounded-lg hover:bg-surface transition-colors cursor-pointer text-xs text-ink">
                            <UiIcon name="bell" :size="16" class="text-muted" />
                            Bildirim Tercihleri
                        </a>
                    </div>
                    <div class="p-1.5 border-t border-line">
                        <a
                            class="flex items-center gap-2.5 px-2.5 py-2 rounded-lg hover:bg-red-50 transition-colors cursor-pointer text-xs text-danger font-medium"
                            @click="emit('logout')"
                        >
                            <UiIcon name="log-out" :size="16" />
                            Çıkış Yap
                        </a>
                    </div>
                </div>
            </Transition>
        </div>
    </nav>
</template>
