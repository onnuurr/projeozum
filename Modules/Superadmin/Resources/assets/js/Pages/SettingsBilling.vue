<template>
	<Card title="Ödeme & Faturalama" body-class="p-4">
		<p class="section-subtitle">Abonelik tahsilatı ve fatura ayarları</p>

		<!-- Ödeme Sağlayıcısı -->
		<FormField label="Ödeme Sağlayıcısı" required>
			<select v-model="form.paymentProvider" class="form-input">
				<option value="iyzico">iyzico</option>
				<option value="stripe">Stripe</option>
			</select>
		</FormField>

		<!-- iyzico API Keys -->
		<div v-if="form.paymentProvider === 'iyzico'">
			<h3 class="settings-subhead">iyzico API</h3>
			<div class="settings-grid">
				<FormField label="API Key">
					<input
						v-model="form.iyzicoApiKey"
						type="text"
						class="form-input mono-input"
						@focus="clearMask('iyzicoApiKey')"
					/>
				</FormField>
				<FormField label="Secret Key">
					<input
						v-model="form.iyzicoSecretKey"
						type="password"
						class="form-input mono-input"
						@focus="clearMask('iyzicoSecretKey')"
					/>
				</FormField>
			</div>
		</div>

		<!-- Stripe API Keys -->
		<div v-if="form.paymentProvider === 'stripe'">
			<h3 class="settings-subhead">Stripe API</h3>
			<div class="settings-grid">
				<FormField label="Publishable Key">
					<input
						v-model="form.stripePublicKey"
						type="text"
						class="form-input mono-input"
						placeholder="pk_live_..."
						@focus="clearMask('stripePublicKey')"
					/>
				</FormField>
				<FormField label="Secret Key">
					<input
						v-model="form.stripeSecretKey"
						type="password"
						class="form-input mono-input"
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
			<FormField label="Para Birimi" required>
				<select v-model="form.currency" class="form-input">
					<option value="TRY">TRY (₺)</option>
					<option value="USD">USD ($)</option>
					<option value="EUR">EUR (€)</option>
				</select>
			</FormField>

			<FormField label="KDV Oranı (%)">
				<input v-model.number="form.vatRate" type="number" min="0" max="100" step="0.5" class="form-input" />
			</FormField>

			<FormField label="Deneme Süresi (gün)">
				<input v-model.number="form.trialDays" type="number" min="0" class="form-input" />
			</FormField>
		</div>

		<FormField label="Fatura Numarası Öneki">
			<input v-model="form.invoicePrefix" type="text" class="form-input mono-input" placeholder="INV-" />
			<div class="field-help">Örn: INV- → INV-2026-001</div>
		</FormField>

		<FormField label="Fatura Alt Bilgi">
			<textarea v-model="form.invoiceFooter" class="form-textarea" rows="2" placeholder="Tüm faturalarda görünecek metin..."></textarea>
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
import FormField from '@Modules/Shared/Resources/assets/js/Components/FormField.vue'

const SENSITIVE_MASK = '••••••••'

const props = defineProps({
	form: {
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

.form-input {
	width: 100%;
	height: 36px;
	padding: 0 12px;
	border: 1.5px solid var(--color-outline-variant);
	border-radius: 8px;
	font-family: inherit;
	font-size: 13px;
	color: var(--color-ink);
	background: var(--color-surface-container-lowest);
	outline: none;
	transition: border-color 0.15s;
}

.form-input:focus {
	border-color: var(--color-primary);
	background: var(--color-surface);
	box-shadow: 0 0 0 3px color-mix(in srgb, var(--color-primary) 12%, transparent);
}

.form-input.mono-input {
	font-family: 'Courier New', monospace;
	font-size: 12px;
}

.form-textarea {
	width: 100%;
	padding: 10px 12px;
	border: 1.5px solid var(--color-outline-variant);
	border-radius: 8px;
	font-family: inherit;
	font-size: 13px;
	color: var(--color-ink);
	background: var(--color-surface-container-lowest);
	outline: none;
	resize: vertical;
	min-height: 60px;
	transition: border-color 0.15s;
	line-height: 1.5;
}

.form-textarea:focus {
	border-color: var(--color-primary);
	background: var(--color-surface);
	box-shadow: 0 0 0 3px color-mix(in srgb, var(--color-primary) 12%, transparent);
}

.field-help {
	font-size: 12px;
	color: var(--color-muted);
	margin-top: 4px;
	line-height: 1.4;
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
