<template>
	<div class="settings-notifications">
		<!-- E-posta Bildirimleri -->
		<Card title="E-posta Bildirimleri" class="mb-4">
			<p class="section-subtitle">Sistem olaylarına bağlı e-posta gönder</p>

			<div class="toggle-group">
				<label class="toggle-card">
					<div class="toggle-content">
						<div class="toggle-label">Yeni Tenant Kaydı</div>
						<div class="toggle-sub">Bir firma sisteme kaydolduğunda admin'lere e-posta gönder.</div>
					</div>
					<input type="checkbox" v-model="form.emailNewTenant" />
					<span class="toggle-switch"></span>
				</label>

				<label class="toggle-card">
					<div class="toggle-content">
						<div class="toggle-label">Yeni Sipariş</div>
						<div class="toggle-sub">Her yeni sipariş için müşteriye ve operasyona bilgi.</div>
					</div>
					<input type="checkbox" v-model="form.emailNewOrder" />
					<span class="toggle-switch"></span>
				</label>

				<label class="toggle-card">
					<div class="toggle-content">
						<div class="toggle-label">Ödeme Başarısızlığı</div>
						<div class="toggle-sub">Abonelik veya sipariş ödemesi başarısız olduğunda uyarı.</div>
					</div>
					<input type="checkbox" v-model="form.emailPaymentFailure" />
					<span class="toggle-switch"></span>
				</label>

				<label class="toggle-card">
					<div class="toggle-content">
						<div class="toggle-label">Sistem Hatası</div>
						<div class="toggle-sub">5xx hatalar ve kritik exception'lar süper admin'e gider.</div>
					</div>
					<input type="checkbox" v-model="form.emailSystemError" />
					<span class="toggle-switch"></span>
				</label>

				<label class="toggle-card">
					<div class="toggle-content">
						<div class="toggle-label">Haftalık Özet Raporu</div>
						<div class="toggle-sub">Her Pazartesi 09:00'da süper admin'lere haftalık özet.</div>
					</div>
					<input type="checkbox" v-model="form.emailWeeklyReport" />
					<span class="toggle-switch"></span>
				</label>
			</div>
		</Card>

		<!-- Slack Entegrasyonu -->
		<Card title="Slack Entegrasyonu" class="mb-4">
			<p class="section-subtitle">Sistem olaylarından Slack kanalına bildirim gönder</p>

			<div class="settings-grid">
				<FormField variant="settings" label="Webhook URL">
					<FormInput
						v-model="form.slackWebhookUrl"
						variant="settings"
						type="url"
						class="mono-input"
						placeholder="https://hooks.slack.com/services/..."
					/>
					<div class="field-help">Slack App > Incoming Webhooks'tan alabilirsiniz</div>
				</FormField>

				<FormField variant="settings" label="Kanal">
					<FormInput v-model="form.slackChannel" variant="settings" type="text" placeholder="#kanal-adı" />
					<div class="field-help">Örn: #alerts, #notifications</div>
				</FormField>
			</div>
		</Card>

		<!-- Diğer Kanallar -->
		<Card title="Diğer Notification Kanalları">
			<p class="section-subtitle">Mobil push ve uygulama içi bildirimler</p>

			<div class="toggle-group">
				<label class="toggle-card">
					<div class="toggle-content">
						<div class="toggle-label">Push Bildirimleri</div>
						<div class="toggle-sub">Mobil/web push üzerinden anlık bildirimler.</div>
					</div>
					<input type="checkbox" v-model="form.enablePushNotifications" />
					<span class="toggle-switch"></span>
				</label>

				<label class="toggle-card">
					<div class="toggle-content">
						<div class="toggle-label">Uygulama İçi Bildirimler</div>
						<div class="toggle-sub">Üst menüde çan ikonunda görünen bildirimler.</div>
					</div>
					<input type="checkbox" v-model="form.enableInAppNotifications" />
					<span class="toggle-switch"></span>
				</label>
			</div>
		</Card>
	</div>
</template>

<script setup>
import { defineProps } from 'vue'
import Card from '@/Components/Card.vue'
import FormField from '@/Components/Form/FormField.vue'
import FormInput from '@/Components/Form/FormInput.vue'

defineProps({
	form: {
		type: Object,
		required: true,
	},
})
</script>

<style scoped>
.settings-notifications {
	display: flex;
	flex-direction: column;
	gap: 20px;
}

.section-subtitle {
	font-size: 13px;
	color: var(--color-muted);
	margin-bottom: 16px;
	line-height: 1.5;
}

.settings-grid {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
	gap: 20px;
	margin-bottom: 20px;
}

/* Toggle Group */
.toggle-group {
	display: flex;
	flex-direction: column;
	gap: 10px;
}

.toggle-card {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 16px;
	padding: 12px 14px;
	border-radius: 8px;
	background: var(--color-surface-container-lowest);
	border: 1px solid var(--color-outline-variant);
	cursor: pointer;
	transition: all 0.2s;
}

.toggle-card:hover {
	border-color: var(--color-primary);
	background: color-mix(in srgb, var(--color-primary) 5%, transparent);
}

.toggle-card input[type="checkbox"] {
	appearance: none;
	width: 0;
	height: 0;
	margin: 0;
	padding: 0;
}

.toggle-content {
	flex: 1;
	min-width: 0;
}

.toggle-label {
	font-size: 13px;
	font-weight: 600;
	color: var(--color-ink);
	margin-bottom: 2px;
}

.toggle-sub {
	font-size: 12px;
	color: var(--color-muted);
	line-height: 1.4;
}

/* Toggle Switch */
.toggle-switch {
	position: relative;
	display: inline-block;
	width: 44px;
	height: 24px;
	flex-shrink: 0;
	background-color: var(--color-outline-variant);
	border-radius: 24px;
	cursor: pointer;
	transition: background-color 0.3s;
}

.toggle-switch::before {
	content: '';
	position: absolute;
	height: 18px;
	width: 18px;
	left: 3px;
	bottom: 3px;
	background-color: white;
	border-radius: 50%;
	transition: transform 0.3s;
}

.toggle-card input:checked + .toggle-switch {
	background-color: var(--color-primary);
}

.toggle-card input:checked + .toggle-switch::before {
	transform: translateX(20px);
}

.mb-4 {
	margin-bottom: 20px;
}

@media (max-width: 768px) {
	.settings-grid {
		grid-template-columns: 1fr;
	}

	.toggle-card {
		flex-direction: column;
		align-items: flex-start;
	}
}
</style>
