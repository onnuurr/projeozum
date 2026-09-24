<script setup>
import { Link } from '@inertiajs/vue3';
// Oz kabuğunda modülün alt sayfaları başlığın altında sekme olarak gösterilir (saas'taki OzPageHeader + OzModuleTabs deseni).
// Veriyi OzAppLayout sağlar; eski kabukta (AppLayoutClassic) ya da modülün alt sayfası yoksa hiçbir şey çizmez.
// Geri oklu (backHref) ve yapışkan (sticky) form/detay başlıklarında gösterilmez.
import OzModuleTabs from '@/Components/Oz/OzModuleTabs.vue';

defineProps({
	badge: { type: [String, Object], default: null },
	title: { type: String, required: true },
	subtitle: { type: String, default: '' },
	// Tam-sayfa form başlığı: geri oku + üstte yapışkan (sticky) şerit.
	// bkz. docs/page-layout-ui-standard.md §2 "Form Topbar" deseni.
	backHref: { type: String, default: null },
	sticky: { type: Boolean, default: false },
});
</script>

<template>
	<!-- display: contents — sarmalayıcı kutu oluşturmaz, sticky başlık eskisi gibi çalışır -->
	<div class="page-header-wrap">
		<div
			class="flex justify-between gap-4 flex-wrap mb-5"
			:class="sticky
				? 'items-center sticky top-0 z-20 bg-surface/90 backdrop-blur border border-line rounded-2xl px-4 py-3 shadow-sm'
				: 'items-start'"
		>
			<div class="flex items-center gap-3 min-w-0">
				<Link
					v-if="backHref"
					:href="backHref"
					aria-label="Geri"
					class="flex items-center justify-center w-9 h-9 rounded-lg border border-line bg-surface text-muted hover:bg-canvas hover:text-ink transition-colors flex-shrink-0"
				>
					<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
						<path d="M19 12H5M12 19l-7-7 7-7" />
					</svg>
				</Link>
				<div class="min-w-0">
					<span
						v-if="badge || $slots.badge"
						class="inline-flex items-center gap-1 rounded-full bg-primary/10 px-2.5 py-1 text-[0.6875rem] font-medium text-primary mb-2"
					>
						<slot name="badge">{{ typeof badge === 'string' ? badge : badge?.label }}</slot>
					</span>
					<h1 class="text-headline-sm text-on-surface mt-2 truncate">{{ title }}</h1>
					<p v-if="subtitle || $slots.subtitle" class="text-body-sm text-muted mt-1 truncate">
						<slot name="subtitle">{{ subtitle }}</slot>
					</p>
				</div>
			</div>
			<div v-if="$slots.actions" class="flex items-center gap-2.5 flex-wrap w-full sm:w-auto">
				<slot name="actions" />
			</div>
		</div>
		<OzModuleTabs v-if="!sticky && !backHref" class="page-header-tabs" />
	</div>
</template>

<style scoped>
.page-header-wrap { display: contents; }
/* saas'taki gibi başlık ile sekmeler arası 12px (başlık satırının mb-5'i 20px) */
.page-header-tabs { margin-top: -8px; }
</style>
