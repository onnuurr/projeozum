<template>
	<Head :title="`${provider.label} · Yakında`" />
	<div class="coming">
		<Link href="/marketplace" class="back-link">← Pazaryerleri</Link>
		<div class="card">
			<div class="logo">{{ provider.label.charAt(0) }}</div>
			<h1>{{ provider.label }} entegrasyonu yakında</h1>
			<p>
				Bu pazaryeri için live entegrasyon henüz aktif değil. Doc inceleme
				tamamlandığında push/pull/webhook akışları açılacak.
			</p>
			<p class="dim">
				Şimdilik kimlik bilgilerinizi <strong>Pazaryeri Bağlantıları</strong>
				ekranından kaydedebilirsiniz; aktive olduğunda hazır olur.
			</p>
		</div>
	</div>
</template>

<script setup>
import { Head, Link } from '@inertiajs/vue3'
import TenantPortalLayout from '@/Layouts/TenantPortalLayout.vue'

defineOptions({ layout: TenantPortalLayout })

defineProps({
	tenant: { type: Object, required: true },
	provider: { type: Object, required: true },
})
</script>

<style scoped>
.back-link { font-size: 12px; color: rgb(var(--portal-accent)); text-decoration: none; }
.coming { max-width: 640px; }
.card { background: #fff; border: 1px solid rgb(var(--portal-border)); border-radius: 14px; padding: 28px; margin-top: 12px; text-align: center; }
.logo { width: 64px; height: 64px; border-radius: 16px; margin: 0 auto 14px; background: linear-gradient(135deg, rgb(var(--portal-accent)), rgb(var(--portal-accent-hover))); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 28px; }
h1 { font-size: 18px; font-weight: 700; color: rgb(var(--portal-ink)); margin-bottom: 10px; }
p { font-size: 13px; color: rgb(var(--portal-text-secondary)); line-height: 1.5; margin: 6px 0; }
.dim { color: rgb(var(--portal-text-muted)); }
</style>
