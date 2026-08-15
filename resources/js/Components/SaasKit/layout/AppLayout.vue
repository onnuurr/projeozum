<!--
	saas-frontend components/layout/AppLayout.vue portu, birebir.
	Bilinçli olarak bu projenin gerçek Layouts/AppLayoutClassic.vue'suna DOKUNMUYOR —
	sayfalara bağlı değil, sadece karşılaştırma/entegrasyon kararı için paralel bir kabuk.
-->
<script setup>
import TopBar from './TopBar.vue';
import MainNav from './MainNav.vue';
import ActionBar from './ActionBar.vue';

// Slots: topbar, navbar, actionbar, default
</script>

<template>
    <div class="h-screen overflow-hidden flex flex-col">
        <header class="w-full flex flex-col shadow-sm flex-shrink-0 relative z-10">
            <slot name="topbar">
                <TopBar />
            </slot>
            <slot name="navbar">
                <MainNav />
            </slot>
            <slot name="actionbar">
                <ActionBar />
            </slot>
        </header>

        <main class="flex-1 min-h-0 mx-1 sm:mx-2 my-1 sm:my-2 bg-surface rounded-xl sm:rounded-2xl shadow-sm overflow-hidden">
            <div class="h-full overflow-y-auto p-3 sm:p-4 md:p-6 space-y-4 sm:space-y-5 main-scrollable">
                <slot />
            </div>
        </main>
    </div>
</template>
