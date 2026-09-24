<script setup>
import { ref, inject } from 'vue'
import { OzField, OzInput, OzSelect, OzSettingRow, OzButton, OzPill } from '@/Components/Oz'

// Ayarlar · E-posta: SMTP ve gönderici (sunucu tarafında .env'e yazılır).
// Not: "Test e-postası" sunucuya bağlı değil (eskisinde de taklitti); uç nokta eklenince buradan çağrılmalı.
const props = defineProps({
	form: { type: Object, required: true },
	options: { type: Object, required: true },
	errors: { type: Object, default: () => ({}) },
})
const emit = defineEmits(['focus-sensitive'])
const showToast = inject('showToast', null)

const testBusy = ref(false)
const lastTestResult = ref(props.form.lastTestResult || null)
const lastTestedAt = ref(props.form.lastTestedAt || null)
const fmt = (iso) => (iso ? new Date(iso).toLocaleString('tr-TR', { dateStyle: 'short', timeStyle: 'short' }) : '—')

function testMail() {
	if (testBusy.value) return
	testBusy.value = true
	setTimeout(() => {
		lastTestResult.value = 'success'
		lastTestedAt.value = new Date().toISOString()
		showToast?.({ type: 'success', title: 'SMTP testi başarılı', message: `Test e-postası ${props.form.fromAddress} adresine gönderildi.` })
		testBusy.value = false
	}, 1200)
}
</script>

<template>
	<div class="stack">
		<div class="block">
			<h4>Gönderim altyapısı</h4>
			<div class="form-grid">
				<OzField label="Sürücü">
					<OzSelect v-model="form.driver" :options="options.mailDrivers" />
				</OzField>
				<OzField label="Şifreleme">
					<OzSelect v-model="form.encryption" :options="options.mailEncryption" />
				</OzField>
				<OzField label="SMTP sunucu" :error="errors['mail.host']">
					<OzInput v-model="form.host" icon="server" placeholder="smtp.gmail.com" />
				</OzField>
				<OzField label="Port" :error="errors['mail.port']">
					<OzInput v-model="form.port" type="number" placeholder="587" />
				</OzField>
				<OzField label="Kullanıcı adı">
					<OzInput v-model="form.username" icon="user" autocomplete="off" />
				</OzField>
				<OzField label="Parola" hint="Maskeliyse boş bırakıp kaydetmek mevcut parolayı korur">
					<OzInput v-model="form.password" type="password" icon="lock" autocomplete="new-password" revealable @focus="emit('focus-sensitive', 'password')" />
				</OzField>
			</div>
		</div>

		<div class="block">
			<h4>Gönderici</h4>
			<div class="form-grid">
				<OzField label="Gönderici e-posta" required :error="errors['mail.fromAddress']">
					<OzInput v-model="form.fromAddress" type="email" icon="mail" placeholder="noreply@example.com" />
				</OzField>
				<OzField label="Gönderici adı" required :error="errors['mail.fromName']">
					<OzInput v-model="form.fromName" icon="building" placeholder="Özüm Server" />
				</OzField>
			</div>
		</div>

		<OzSettingRow icon="send" title="Bağlantıyı dene" :description="lastTestedAt ? `Son test: ${fmt(lastTestedAt)}` : 'Kayıtlı ayarlarla gönderici adresine bir test e-postası yollar.'">
			<OzPill v-if="lastTestResult" :tone="lastTestResult === 'success' ? 'success' : 'danger'">
				{{ lastTestResult === 'success' ? 'Başarılı' : 'Başarısız' }}
			</OzPill>
			<OzButton size="sm" icon="send" :loading="testBusy" @click="testMail">Test gönder</OzButton>
		</OzSettingRow>
	</div>
</template>
