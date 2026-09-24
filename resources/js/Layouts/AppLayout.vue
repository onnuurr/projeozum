<template>
	<OzAppLayout>
		<slot />
	</OzAppLayout>
</template>

<script setup>
// Uygulama kabuğu: Oz kit (components/Oz). Tüm sayfalar defineOptions({ layout: AppLayout }) ile bunu kullanır.
// Eski TopNav + LeftSidebar kabuğu AppLayoutClassic.vue'da duruyor; geri dönmek için aşağıdaki
// import'u '@/Layouts/AppLayoutClassic.vue' yapmak (ve bileşen adını değiştirmek) yeterli.
import OzAppLayout from '@/Layouts/OzAppLayout.vue'
</script>
