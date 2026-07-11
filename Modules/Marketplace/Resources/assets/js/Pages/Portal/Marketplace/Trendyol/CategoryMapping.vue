<template>
	<Head title="Trendyol · Kategori Eşleme" />
	<div class="page">
		<Link href="/marketplace/trendyol" class="back-link">← Trendyol Panosu</Link>
		<h1 class="page-title">Kategori Eşleme</h1>
		<p class="page-subtitle">Sistemimizdeki kategorileri Trendyol kategori ağacına bağlayın.</p>

		<div class="card placeholder">
			<p>Bu sayfa Phase 3 sonraki iterasyonda doldurulacak —
			<code>TrendyolCategoryClient</code> ile Trendyol kategori tree'sinden autocomplete +
			<code>category_marketplace_mappings</code> tablosuna upsert.</p>
		</div>
	</div>
</template>

<script setup>
import { Head, Link } from '@inertiajs/vue3'
import TenantPortalLayout from '@/Layouts/TenantPortalLayout.vue'

defineOptions({ layout: TenantPortalLayout })

defineProps({
	tenant: { type: Object, required: true },
})
</script>

<style scoped>
.back-link { font-size: 12px; color: #4338ca; text-decoration: none; }
.page-title { font-size: 22px; font-weight: 700; margin: 8px 0 6px; }
.page-subtitle { font-size: 13px; color: #888; margin-bottom: 16px; }
.card { background: #fff; border-radius: 12px; border: 1px solid #ebebf0; padding: 18px; }
.placeholder p { font-size: 13px; color: #555; line-height: 1.5; }
.placeholder code { background: #f7f7fb; padding: 2px 6px; border-radius: 4px; font-size: 11px; }
</style>
