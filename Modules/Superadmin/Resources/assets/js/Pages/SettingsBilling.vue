<script setup>
import { OzField, OzInput, OzSelect, OzTextarea, OzSwitch, OzSettingRow, OzPill } from '@/Components/Oz'

// Ayarlar · Ödeme & faturalama: sağlayıcı anahtarları (maskeli), fatura ayarları, sandbox
defineProps({
	form: { type: Object, required: true },
	options: { type: Object, required: true },
	errors: { type: Object, default: () => ({}) },
})
const emit = defineEmits(['focus-sensitive'])
</script>

<template>
	<div class="stack">
		<div class="block">
			<h4>Ödeme sağlayıcısı</h4>
			<div class="form-grid">
				<OzField label="Sağlayıcı" required>
					<OzSelect v-model="form.paymentProvider" :options="options.paymentProviders" />
				</OzField>
			</div>
			<div v-if="form.paymentProvider === 'iyzico'" class="form-grid">
				<OzField label="iyzico API key">
					<OzInput v-model="form.iyzicoApiKey" icon="key" class="mono" autocomplete="off" @focus="emit('focus-sensitive', 'iyzicoApiKey')" />
				</OzField>
				<OzField label="iyzico secret key">
					<OzInput v-model="form.iyzicoSecretKey" type="password" icon="lock" class="mono" autocomplete="off" revealable @focus="emit('focus-sensitive', 'iyzicoSecretKey')" />
				</OzField>
			</div>
			<div v-if="form.paymentProvider === 'stripe'" class="form-grid">
				<OzField label="Stripe publishable key">
					<OzInput v-model="form.stripePublicKey" icon="key" class="mono" placeholder="pk_live_…" autocomplete="off" @focus="emit('focus-sensitive', 'stripePublicKey')" />
				</OzField>
				<OzField label="Stripe secret key">
					<OzInput v-model="form.stripeSecretKey" type="password" icon="lock" class="mono" placeholder="sk_live_…" autocomplete="off" revealable @focus="emit('focus-sensitive', 'stripeSecretKey')" />
				</OzField>
			</div>
		</div>

		<div class="block">
			<h4>Fatura ayarları</h4>
			<div class="form-grid three">
				<OzField label="Para birimi" required>
					<OzSelect v-model="form.currency" :options="options.currencies" />
				</OzField>
				<OzField label="KDV oranı" :error="errors['billing.vatRate']">
					<OzInput v-model="form.vatRate" type="number" min="0" max="100" step="0.5" suffix="%" />
				</OzField>
				<OzField label="Deneme süresi">
					<OzInput v-model="form.trialDays" type="number" min="0" suffix="gün" />
				</OzField>
				<OzField label="Fatura numarası öneki" hint="Örn. INV- → INV-2026-001">
					<OzInput v-model="form.invoicePrefix" class="mono" placeholder="INV-" />
				</OzField>
				<OzField label="Fatura alt bilgisi" class="span-2">
					<OzTextarea v-model="form.invoiceFooter" :rows="2" placeholder="Tüm faturalarda görünecek metin…" />
				</OzField>
			</div>
		</div>

		<OzSettingRow icon="flask" title="Sandbox modu" description="Açıkken gerçek tahsilat yapılmaz, test kartları kabul edilir.">
			<OzPill v-if="form.sandboxMode" tone="warning">Test</OzPill>
			<OzSwitch v-model="form.sandboxMode" aria-label="Sandbox modu" />
		</OzSettingRow>
	</div>
</template>
