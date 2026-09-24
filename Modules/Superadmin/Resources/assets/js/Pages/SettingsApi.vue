<script setup>
import { inject } from 'vue'
import { OzField, OzInput, OzTextarea, OzSwitch, OzSettingRow, OzButton, OzPill } from '@/Components/Oz'

// Ayarlar · API & geliştirici: istek sınırı, webhook anahtarı, CORS, Cloudflare, API anahtarı ve Swagger
const props = defineProps({
	form: { type: Object, required: true },
	errors: { type: Object, default: () => ({}) },
})
const emit = defineEmits(['focus-sensitive'])
const showToast = inject('showToast', null)

function copySecret() {
	navigator.clipboard?.writeText(props.form.webhookSecret)
	showToast?.({ type: 'success', title: 'Kopyalandı', message: 'Webhook anahtarı panoya kopyalandı.' })
}
function regenerateSecret() {
	props.form.webhookSecret = 'whsec_' + Array.from({ length: 32 }, () => Math.floor(Math.random() * 16).toString(16)).join('')
	showToast?.({ type: 'warning', title: 'Yeni anahtar üretildi', message: 'Kaydettikten sonra webhook tüketicilerinin güncellenmesi gerekir.' })
}
</script>

<template>
	<div class="stack">
		<div class="block">
			<h4>Genel API</h4>
			<div class="form-grid">
				<OzField label="İstek sınırı (rate limit)" :error="errors['api.rateLimitPerMinute']">
					<OzInput v-model="form.rateLimitPerMinute" type="number" min="1" suffix="istek / dk" />
				</OzField>
				<OzField label="API sürümü">
					<OzInput v-model="form.apiVersion" class="mono" />
				</OzField>
				<OzField label="Webhook imzalama anahtarı" hint="Gelen webhook isteklerinin imzasını doğrulamak için kullanılır" class="span-2">
					<div class="inline">
						<OzInput v-model="form.webhookSecret" icon="key" class="mono grow" readonly />
						<OzButton icon="copy" @click="copySecret">Kopyala</OzButton>
						<OzButton icon="refresh" @click="regenerateSecret">Yenile</OzButton>
					</div>
				</OzField>
				<OzField label="İzinli origin'ler (CORS)" hint="Her satıra bir origin; * tüm origin'lere izin verir" class="span-2">
					<OzTextarea v-model="form.allowedOrigins" :rows="3" class="mono" />
				</OzField>
			</div>
		</div>

		<div class="block">
			<h4>Cloudflare</h4>
			<div class="form-grid">
				<OzField label="Zone ID">
					<OzInput v-model="form.cloudflareZoneId" class="mono" placeholder="a1b2c3d4e5f6…" />
				</OzField>
				<OzField label="API token" hint="“Önbelleği temizle” yetkili bir token gerekir (Performans › Laravel + Cloudflare önbelleğini temizle)">
					<OzInput v-model="form.cloudflareApiToken" type="password" icon="lock" class="mono" autocomplete="off" revealable @focus="emit('focus-sensitive', 'cloudflareApiToken')" />
				</OzField>
			</div>
		</div>

		<div class="rows">
			<OzSettingRow icon="flask" title="Sandbox API" description="Açıkken API gerçek veriyi değiştirmez, sahte yanıt döner.">
				<OzPill v-if="form.sandboxMode" tone="warning">Test</OzPill>
				<OzSwitch v-model="form.sandboxMode" aria-label="Sandbox API" />
			</OzSettingRow>
			<OzSettingRow icon="key" title="API anahtarı zorunlu" description="Tüm API çağrıları için geçerli bir API anahtarı gerekir.">
				<OzSwitch v-model="form.requireApiKey" aria-label="API anahtarı zorunlu" />
			</OzSettingRow>
			<OzSettingRow icon="file" title="Swagger dokümantasyonu" description="/api/docs adresinde otomatik API dokümantasyonu.">
				<OzSwitch v-model="form.enableSwagger" aria-label="Swagger dokümantasyonu" />
			</OzSettingRow>
		</div>
	</div>
</template>

<style scoped>
.inline { display: flex; gap: 8px; flex-wrap: wrap; }
.grow { flex: 1 1 240px; }
</style>
