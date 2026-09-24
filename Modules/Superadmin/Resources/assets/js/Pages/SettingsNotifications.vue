<script setup>
import { OzField, OzInput, OzSwitch, OzSettingRow } from '@/Components/Oz'

// Ayarlar · Bildirimler: e-posta olayları, Slack, push / uygulama içi
defineProps({
	form: { type: Object, required: true },
	errors: { type: Object, default: () => ({}) },
})

const emailEvents = [
	{ key: 'emailNewTenant', icon: 'building', title: 'Yeni iş ortağı kaydı', desc: 'Bir firma sisteme kaydolduğunda yöneticilere e-posta gönderilir.' },
	{ key: 'emailNewOrder', icon: 'cart', title: 'Yeni sipariş', desc: 'Her yeni sipariş için müşteriye ve operasyona bilgi.' },
	{ key: 'emailPaymentFailure', icon: 'credit-card', title: 'Ödeme başarısızlığı', desc: 'Abonelik ya da sipariş ödemesi başarısız olduğunda uyarı.' },
	{ key: 'emailSystemError', icon: 'warning', title: 'Sistem hatası', desc: '5xx hatalar ve kritik istisnalar süper admin’e gider.' },
	{ key: 'emailWeeklyReport', icon: 'chart', title: 'Haftalık özet raporu', desc: 'Her pazartesi 09:00’da süper admin’lere haftalık özet.' },
]
const channels = [
	{ key: 'enablePushNotifications', icon: 'smartphone', title: 'Push bildirimleri', desc: 'Mobil/web push üzerinden anlık bildirimler.' },
	{ key: 'enableInAppNotifications', icon: 'bell', title: 'Uygulama içi bildirimler', desc: 'Üst bardaki zil simgesinde görünen bildirimler.' },
]
</script>

<template>
	<div class="stack">
		<div class="block">
			<h4>E-posta bildirimleri</h4>
			<div class="rows">
				<OzSettingRow v-for="e in emailEvents" :key="e.key" :icon="e.icon" :title="e.title" :description="e.desc">
					<OzSwitch v-model="form[e.key]" :aria-label="e.title" />
				</OzSettingRow>
			</div>
		</div>

		<div class="block">
			<h4>Slack entegrasyonu</h4>
			<div class="form-grid">
				<OzField label="Webhook URL" hint="Slack App › Incoming Webhooks" :error="errors['notifications.slackWebhookUrl']">
					<OzInput v-model="form.slackWebhookUrl" type="url" icon="link" class="mono" placeholder="https://hooks.slack.com/services/…" />
				</OzField>
				<OzField label="Kanal" hint="Örn. #alerts, #notifications">
					<OzInput v-model="form.slackChannel" icon="hash" placeholder="#kanal-adı" />
				</OzField>
			</div>
		</div>

		<div class="block">
			<h4>Diğer kanallar</h4>
			<div class="rows">
				<OzSettingRow v-for="c in channels" :key="c.key" :icon="c.icon" :title="c.title" :description="c.desc">
					<OzSwitch v-model="form[c.key]" :aria-label="c.title" />
				</OzSettingRow>
			</div>
		</div>
	</div>
</template>
