<template>
	<Card title="Ödeme & Faturalama" body-class="p-4">
		<p class="section-subtitle">Abonelik tahsilatı ve fatura ayarları</p>

		<!-- Ödeme Sağlayıcısı -->
		<FormField variant="settings" label="Ödeme Sağlayıcısı" required>
			<CustomSelect v-model="form.paymentProvider" :options="options.paymentProviders" :show-label="false" />
		</FormField>

		<!-- iyzico API Keys -->
		<div v-if="form.paymentProvider === 'iyzico'">
			<h3 class="settings-subhead">iyzico API</h3>
			<div class="settings-grid">
				<FormField variant="settings" label="API Key">
					<FormInput
						v-model="form.iyzicoApiKey"
						variant="settings"
						type="text"
						class="mono-input"
						@focus="clearMask('iyzicoApiKey')"
					/>
				</FormField>
				<FormField variant="settings" label="Secret Key">
					<FormInput
						v-model="form.iyzicoSecretKey"
						variant="settings"
						type="password"
						class="mono-input"
						@focus="clearMask('iyzicoSecretKey')"
					/>
				</FormField>
			</div>
		</div>

		<!-- Stripe API Keys -->
		<div v-if="form.paymentProvider === 'stripe'">
			<h3 class="settings-subhead">Stripe API</h3>
			<div class="settings-grid">
				<FormField variant="settings" label="Publishable Key">
					<FormInput
						v-model="form.stripePublicKey"
						variant="settings"
						type="text"
						class="mono-input"
						placeholder="pk_live_..."
						@focus="clearMask('stripePublicKey')"
					/>
				</FormField>
				<FormField variant="settings" label="Secret Key">
					<FormInput
						v-model="form.stripeSecretKey"
						variant="settings"
						type="password"
						class="mono-input"
						placeholder="sk_live_..."
						@focus="clearMask('stripeSecretKey')"
					/>
				</FormField>
			</div>
		</div>

		<!-- Divider -->
		<div class="settings-divider"></div>

		<!-- Fatura Ayarları -->
		<h3 class="settings-subhead">Fatura Ayarları</h3>

		<div class="settings-grid-3col">
			<FormField variant="settings" label="Para Birimi" required>
				<CustomSelect v-model="form.currency" :options="options.currencies" :show-label="false" />
			</FormField>

			<FormField variant="settings" label="KDV Oranı (%)">
				<FormInput v-model.number="form.vatRate" variant="settings" type="number" min="0" max="100" step="0.5" />
			</FormField>

			<FormField variant="settings" label="Deneme Süresi (gün)">
				<FormInput v-model.number="form.trialDays" variant="settings" type="number" min="0" />
			</FormField>
		</div>

		<FormField variant="settings" label="Fatura Numarası Öneki">
			<FormInput v-model="form.invoicePrefix" variant="settings" type="text" class="mono-input" placeholder="INV-" />
			<div class="field-help">Örn: INV- → INV-2026-001</div>
		</FormField>

		<FormField variant="settings" label="Fatura Alt Bilgi">
			<FormTextarea v-model="form.invoiceFooter" class="settings-textarea" rows="2" min-height="60px" placeholder="Tüm faturalarda görünecek metin..." />
		</FormField>

		<!-- Divider -->
		<div class="settings-divider"></div>

		<!-- Sandbox Mode -->
		<div class="settings-toggle-item" :class="{ 'warning-bg': form.sandboxMode }">
			<div class="settings-toggle-info">
				<div class="settings-toggle-label">
					Sandbox Modu
					<Badge v-if="form.sandboxMode" color="warning" variant="tonal" label="TEST" />
				</div>
				<div class="settings-toggle-sub">Açıkken gerçek tahsilat yapılmaz, test kartları kabul edilir.</div>
			</div>
			<label class="form-toggle">
				<input type="checkbox" v-model="form.sandboxMode" />
				<span class="form-toggle-slider"></span>
			</label>
		</div>
	</Card>
</template>

<script setup>
import { defineProps } from 'vue'
import Card from '@/Components/Card.vue'
import Badge from '@/Components/Badge.vue'
import CustomSelect from '@/Components/CustomSelect.vue'
import FormField from '@/Components/Form/FormField.vue'
import FormInput from '@/Components/Form/FormInput.vue'
import FormTextarea from '@/Components/Form/FormTextarea.vue'

const SENSITIVE_MASK = '••••••••'

const props = defineProps({
	form: {
		type: Object,
		required: true,
	},
	options: {
		type: Object,
		required: true,
	},
})

function clearMask(key) {
	if (props.form[key] === SENSITIVE_MASK) {
		props.form[key] = ''
	}
}
</script>

<style scoped>
.section-subtitle {
	font-size: 13px;
	color: var(--color-muted);
	margin-bottom: 20px;
	line-height: 1.5;
}

.settings-grid {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
	gap: 20px;
	margin-bottom: 20px;
}

.settings-grid-3col {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
	gap: 16px;
	margin-bottom: 20px;
}

.settings-subhead {
	font-size: 12px;
	font-weight: 600;
	color: var(--color-ink);
	text-transform: uppercase;
	letter-spacing: 0.04em;
	margin-bottom: 16px;
	margin-top: 20px;
}

.settings-divider {
	height: 1px;
	background: var(--color-outline-variant);
	margin: 24px 0;
}

.settings-toggle-item {
	display: flex;
	align-items: center;
	justify-content: space-between;
	padding: 12px 14px;
	border-radius: 8px;
	background: var(--color-surface-container-lowest);
	border: 1px solid var(--color-outline-variant);
	gap: 16px;
}

.settings-toggle-item.warning-bg {
	background: color-mix(in srgb, var(--color-warning) 8%, transparent);
	border-color: color-mix(in srgb, var(--color-warning) 30%, transparent);
}

.settings-toggle-info {
	flex: 1;
}

.settings-toggle-label {
	font-size: 13px;
	font-weight: 600;
	color: var(--color-ink);
	display: flex;
	align-items: center;
	gap: 8px;
	margin-bottom: 4px;
}

.settings-toggle-sub {
	font-size: 12px;
	color: var(--color-muted);
	line-height: 1.4;
}

/* Toggle */
.form-toggle {
	position: relative;
	display: inline-flex;
	width: 44px;
	height: 24px;
	flex-shrink: 0;
	margin-left: 12px;
	cursor: pointer;
}

.form-toggle input {
	opacity: 0;
	width: 0;
	height: 0;
}

.form-toggle-slider {
	position: absolute;
	cursor: pointer;
	top: 0;
	left: 0;
	right: 0;
	bottom: 0;
	background-color: var(--color-outline-variant);
	transition: 0.3s;
	border-radius: 24px;
}

.form-toggle-slider::before {
	position: absolute;
	content: '';
	height: 18px;
	width: 18px;
	left: 3px;
	bottom: 3px;
	background-color: white;
	transition: 0.3s;
	border-radius: 50%;
}

.form-toggle input:checked + .form-toggle-slider {
	background-color: var(--color-primary);
}

.form-toggle input:checked + .form-toggle-slider::before {
	transform: translateX(20px);
}

@media (max-width: 768px) {
	.settings-grid,
	.settings-grid-3col {
		grid-template-columns: 1fr;
	}

	.settings-toggle-item {
		flex-direction: column;
		align-items: flex-start;
	}
}
</style>
