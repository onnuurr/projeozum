<!--
	saas-frontend components/layout/MainNav.vue'nun birebir sınıf/yapı kopyası
	(bkz. Components/SaasKit/layout/MainNav.vue — doğrulanmış verbatim referans, AppLogo
	de doğrudan oradan kullanılıyor, yeniden çizilmedi). Sadece veri sözleşmesi değişti:
	menuItems → sidebarTop (icon hâlâ useAppShell.js'in ürettiği <svg> string'i, v-html ile
	aynen render edilir), profil paneli userMenu dizisi üzerinde v-for döner (rol-filtreli,
	demo rol değiştirici dahil — TopNav'ın eski mantığı taşındı, saas-frontend'in sabit 3
	linkli paneli kopyalanmadı, plan notu #3).
-->
<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppLogo from './SaasKit/layout/AppLogo.vue';
import Offcanvas from '@/Components/Offcanvas.vue';

const props = defineProps({
	menuItems: { type: Array, required: true },
	user: { type: Object, required: true },
	userMenu: { type: Array, required: true },
});

const emit = defineEmits(['user-action']);

const activePanel = ref(null);
const containerRef = ref(null);
const mobileMenuOpen = ref(false);

function togglePanel(name) {
	activePanel.value = activePanel.value === name ? null : name;
}

function toggleMobileMenu() {
	mobileMenuOpen.value = !mobileMenuOpen.value;
}

function closePanel() {
	activePanel.value = null;
}

function userMenuHtml(item) {
	return `${item.icon}<span>${item.label}</span>`;
}

function handleUserAction(item) {
	emit('user-action', item);
	closePanel();
}

function onDocumentClick(e) {
	if (containerRef.value && !containerRef.value.contains(e.target)) closePanel();
}

function onEscape(e) {
	if (e.key === 'Escape') {
		closePanel();
		if (document.activeElement instanceof HTMLElement) document.activeElement.blur();
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
				<svg v-if="!mobileMenuOpen" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
				<svg v-else width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
			</button>

			<AppLogo class="mr-6 2xl:mr-10 flex-shrink-0" />

			<div class="hidden lg:flex items-end h-full gap-1 min-w-0">
				<Link
					v-for="item in menuItems"
					:key="item.label"
					:href="item.to || '#'"
					:class="[
						'flex flex-col items-center justify-center px-4 transition-all cursor-pointer',
						item.active
							? 'h-[64px] bg-white text-primary rounded-t-lg'
							: 'h-[64px] text-white opacity-80 hover:opacity-100',
					]"
				>
					<span class="w-[18px] h-[18px] mb-1" v-html="item.icon"></span>
					<span class="font-bold text-2xs leading-tight">{{ item.label }}</span>
				</Link>
			</div>
		</div>

		<!-- Mobile nav drawer -->
		<Offcanvas v-model="mobileMenuOpen" position="left" title="Menü" width="w-72">
			<div class="-m-3">
				<Link
					v-for="item in menuItems"
					:key="item.label"
					:href="item.to || '#'"
					:class="[
						'flex items-center gap-3 px-4 py-3 text-xs font-medium transition-colors cursor-pointer border-b border-line last:border-b-0',
						item.active ? 'bg-primary/10 text-primary' : 'text-ink hover:bg-surface',
					]"
					@click="mobileMenuOpen = false"
				>
					<span class="w-[18px] h-[18px]" v-html="item.icon"></span>
					{{ item.label }}
				</Link>
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
					{{ user.initials || user.shortName?.charAt(0) }}
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
							{{ user.initials }}
						</div>
						<div class="min-w-0">
							<p class="text-xs font-semibold text-ink truncate">{{ user.name }}</p>
							<p class="text-2xs text-muted truncate">{{ user.email }}</p>
						</div>
					</div>
					<div class="p-1.5 max-h-[60vh] overflow-y-auto">
						<template v-for="(item, idx) in userMenu" :key="idx">
							<hr v-if="item.divider" class="border-line my-1" style="border-top-width: 1px;" />
							<p v-else-if="item.heading" class="px-2.5 pt-2 pb-1 text-2xs font-semibold text-muted uppercase tracking-wide">{{ item.heading }}</p>
							<div
								v-else
								class="user-menu-item flex items-center gap-2.5 px-2.5 py-2 rounded-lg hover:bg-surface transition-colors cursor-pointer text-xs text-ink"
								:class="{ 'text-danger hover:bg-red-50': item.danger, 'demo-active': item.activeDemoRole }"
								@click="handleUserAction(item)"
								v-html="userMenuHtml(item)"
							></div>
						</template>
					</div>
				</div>
			</Transition>
		</div>
	</nav>
</template>

<style scoped>
.user-menu-item :deep(svg) { color: rgb(var(--color-muted)); flex-shrink: 0; }
.user-menu-item:hover :deep(svg) { color: rgb(var(--color-primary)); }
.user-menu-item.text-danger :deep(svg) { color: rgb(var(--color-danger)); }
.user-menu-item.demo-active {
	background: rgb(var(--color-primary) / .1);
	color: rgb(var(--color-primary));
	font-weight: 600;
	position: relative;
}
.user-menu-item.demo-active::after {
	content: '✓';
	margin-left: auto;
	color: rgb(var(--color-primary));
	font-weight: 800;
}
</style>
