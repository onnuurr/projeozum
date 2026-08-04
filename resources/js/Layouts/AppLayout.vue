<!--
	İnce dispatcher: hangi admin kabuğunun (Classic/V2) render olacağını
	Superadmin → Ayarlar → Arayüz sekmesinden (ui.adminTheme, bkz.
	Modules/Superadmin/Http/Controllers/SettingsController.php) gelen paylaşılan
	prop'a göre seçer. Sayfalar bu dosyayı import ediyor (58+ kullanım) — burada
	karar vermek, tek bir sayfa dosyasına dokunmadan tüm paneli tema arasında
	geçirmeyi sağlar.

	<html data-theme="..."> de burada set edilir: TopNav/CartDrawer/SearchModal
	gibi bileşenlerin çoğu <Teleport to="body"> kullanıyor, yani içerik DOM'da
	.layout-root'un DIŞINA çıkıyor — token override'ının onlara da ulaşması için
	scope'u kök div yerine <html>'e taşımak gerekiyor (bkz. resources/css/app.css
	[data-theme="v2"] bloğu).
-->
<script setup>
import { computed, watch, onBeforeUnmount } from 'vue'
import { usePage } from '@inertiajs/vue3'
import AppLayoutClassic from './AppLayoutClassic.vue'
import AppLayoutV2 from './AppLayoutV2.vue'

const theme = computed(() => (usePage().props.ui?.adminTheme === 'v2' ? 'v2' : 'classic'))

watch(
	theme,
	(value) => {
		if (value === 'v2') {
			document.documentElement.setAttribute('data-theme', 'v2')
		} else {
			document.documentElement.removeAttribute('data-theme')
		}
	},
	{ immediate: true }
)

onBeforeUnmount(() => document.documentElement.removeAttribute('data-theme'))
</script>

<template>
	<AppLayoutV2 v-if="theme === 'v2'"><slot /></AppLayoutV2>
	<AppLayoutClassic v-else><slot /></AppLayoutClassic>
</template>
