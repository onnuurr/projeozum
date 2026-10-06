<script setup>
import { ref, computed, watch } from 'vue'
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { OzPageHeader, OzModuleTabs, OzTabs } from '@/Components/Oz'
import GeneralTab from '../Components/MockupSettings/GeneralTab.vue'
import CategoriesTab from '../Components/MockupSettings/CategoriesTab.vue'
import OptionsTab from '../Components/MockupSettings/OptionsTab.vue'
import PosesTab from '../Components/MockupSettings/PosesTab.vue'

defineOptions({ layout: AppLayout })

// Creative modülünün tek ayar sayfası (çark ikonu). Mokap sekmeleri mockup-settings.manage ile
// kilitli; yetki yoksa backend 'mockup' prop'unu null gönderir ve sekmeler hiç çizilmez.
const props = defineProps({
	tab: { type: String, default: null },
	mockup: { type: Object, default: null },
})

const tabs = computed(() => (props.mockup ? [
	{ key: 'general', label: 'Mokap · Genel', icon: 'settings' },
	{ key: 'categories', label: 'Kategoriler', icon: 'grid', count: props.mockup.categories.filter((c) => c.region).length },
	{ key: 'options', label: 'Stilist Seçenekleri', icon: 'shirt', count: props.mockup.options.length },
	{ key: 'poses', label: 'Pozlar', icon: 'user', count: props.mockup.poses.filter((p) => p.mockup_regions.length).length },
] : []))

const activeTab = ref(tabs.value.some((t) => t.key === props.tab) ? props.tab : tabs.value[0]?.key ?? '')

// Sekme adres çubuğunda kalsın: yenileme / paylaşılan link aynı sekmeyi açar.
watch(activeTab, (key) => {
	const url = new URL(window.location.href)
	url.searchParams.set('tab', key)
	window.history.replaceState(window.history.state, '', url)
})
</script>

<template>
	<Head title="Creative Ayarları" />

	<OzPageHeader title="Creative Ayarları">
		<template #subtitle>Mokap giydirme: otomatik başlatma, kategori eşleştirme, stilist seçenekleri ve pozlar</template>
	</OzPageHeader>
	<OzModuleTabs />

	<template v-if="tabs.length">
		<OzTabs v-model="activeTab" :tabs="tabs" aria-label="Creative ayarları" class="settings-tabs" />

		<GeneralTab v-if="activeTab === 'general'" :general="mockup.general" :mannequins="mockup.mannequins" />
		<CategoriesTab v-else-if="activeTab === 'categories'" :categories="mockup.categories" />
		<OptionsTab v-else-if="activeTab === 'options'" :options="mockup.options" />
		<PosesTab v-else-if="activeTab === 'poses'" :poses="mockup.poses" :features="mockup.features" />
	</template>
</template>

<style scoped>
.settings-tabs { margin-bottom: 14px; }
</style>
