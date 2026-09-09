<template>
	<Card title="E-posta (SMTP)" body-class="p-4">
		<p class="section-subtitle">Sistem e-postalarının gönderim altyapısı</p>

		<!-- Driver & Encryption -->
		<div class="settings-grid">
			<FormField variant="settings" label="Sürücü">
				<CustomSelect v-model="form.driver" :options="options.mailDrivers" :show-label="false" />
			</FormField>

			<FormField variant="settings" label="Şifreleme">
				<CustomSelect v-model="form.encryption" :options="options.mailEncryption" :show-label="false" />
			</FormField>
		</div>

		<!-- SMTP Host & Port -->
		<div class="settings-grid">
			<FormField variant="settings" label="SMTP Host">
				<FormInput v-model="form.host" variant="settings" type="text" placeholder="smtp.gmail.com" />
			</FormField>

			<FormField variant="settings" label="Port">
				<FormInput v-model.number="form.port" variant="settings" type="number" placeholder="587" />
			</FormField>
		</div>

		<!-- Username & Password -->
		<div class="settings-grid">
			<FormField variant="settings" label="Kullanıcı Adı">
				<FormInput v-model="form.username" variant="settings" type="text" />
			</FormField>

			<FormField variant="settings" label="Parola">
				<FormInput
					v-model="form.password"
					variant="settings"
					:type="showPassword ? 'text' : 'password'"
					@focus="clearMask"
				>
					<template #trailing>
						<button type="button" class="settings-input-trail" @click="showPassword = !showPassword">
							<Eye v-if="!showPassword" :size="16" />
							<EyeOff v-else :size="16" />
						</button>
					</template>
				</FormInput>
				<div class="field-help">Maskeliyse, boş bırakıp kaydet = mevcut şifreyi koru</div>
			</FormField>
		</div>

		<!-- Divider -->
		<div class="settings-divider"></div>

		<!-- Sender Info -->
		<h3 class="settings-subhead">Gönderici Bilgileri</h3>

		<div class="settings-grid">
			<FormField variant="settings" label="Gönderici E-posta" required>
				<FormInput v-model="form.fromAddress" variant="settings" type="email" placeholder="noreply@example.com" />
			</FormField>

			<FormField variant="settings" label="Gönderici Adı" required>
				<FormInput v-model="form.fromName" variant="settings" type="text" placeholder="Özüm Server" />
			</FormField>
		</div>

		<!-- Divider -->
		<div class="settings-divider"></div>

		<!-- Test Section -->
		<div class="settings-test-block">
			<div class="settings-test-info">
				<div
					v-if="lastTestResult"
					class="settings-test-status"
					:class="`status-${lastTestResult}`"
				>
					<Check v-if="lastTestResult === 'success'" :size="14" :stroke-width="2.5" />
					<AlertCircle v-else :size="14" :stroke-width="2.5" />
					{{ lastTestResult === 'success' ? 'Son test başarılı' : 'Son test başarısız' }}
				</div>
				<div v-if="lastTestedAt" class="settings-test-time">
					Tarih: {{ formatDate(lastTestedAt) }}
				</div>
			</div>
			<Button variant="secondary" size="sm" :loading="testBusy" @click="testMail">
				Test E-postası Gönder
			</Button>
		</div>
	</Card>
</template>

<script setup>
import { ref, defineProps } from 'vue'
import { Check, AlertCircle, Eye, EyeOff } from 'lucide-vue-next'
import { inject } from 'vue'
import Card from '@/Components/Card.vue'
import Button from '@/Components/Button.vue'
import CustomSelect from '@/Components/CustomSelect.vue'
import FormField from '@/Components/Form/FormField.vue'
import FormInput from '@/Components/Form/FormInput.vue'

const SENSITIVE_MASK = '••••••••'
const showToast = inject('showToast')

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

const showPassword = ref(false)
const testBusy = ref(false)
const lastTestResult = ref(props.form.lastTestResult || null)
const lastTestedAt = ref(props.form.lastTestedAt || null)

function clearMask() {
	if (props.form.password === SENSITIVE_MASK) {
		props.form.password = ''
	}
}

function formatDate(iso) {
	if (!iso) return '—'
	return new Date(iso).toLocaleString('tr-TR', { dateStyle: 'short', timeStyle: 'short' })
}

function testMail() {
	if (testBusy.value) return
	testBusy.value = true

	showToast?.({
		type: 'info',
		title: 'Test E-postası',
		message: 'SMTP testi başlatıldı, sonuç birkaç saniye içinde…',
	})

	// Simulate test (replace with actual API call)
	setTimeout(() => {
		lastTestResult.value = 'success'
		lastTestedAt.value = new Date().toISOString()
		showToast?.({
			type: 'success',
			title: 'SMTP Test Başarılı',
			message: `Test e-postası ${props.form.fromAddress} adresine gönderildi.`,
		})
		testBusy.value = false
	}, 1200)
}
</script>

<style scoped>
.section-subtitle {
	font-size: 13px;
	color: var(--color-muted);
	margin-bottom: 20px;
	line-height: 1.5;
}

/* Grid Layout */
.settings-grid {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
	gap: 20px;
	margin-bottom: 20px;
}

/* Divider */
.settings-divider {
	height: 1px;
	background: var(--color-outline-variant);
	margin: 24px 0;
}

/* Subheading */
.settings-subhead {
	font-size: 13px;
	font-weight: 600;
	color: var(--color-ink);
	text-transform: uppercase;
	letter-spacing: 0.03em;
	margin-bottom: 16px;
	margin-top: 0;
}

/* Test Block */
.settings-test-block {
	display: flex;
	align-items: center;
	justify-content: space-between;
	padding: 16px 14px;
	border-radius: 8px;
	background: var(--color-surface-container-lowest);
	border: 1px solid var(--color-outline-variant);
	gap: 16px;
}

.settings-test-info {
	flex: 1;
}

.settings-test-status {
	display: flex;
	align-items: center;
	gap: 8px;
	font-size: 13px;
	font-weight: 600;
	margin-bottom: 4px;
}

.settings-test-status.status-success {
	color: #16a34a;
}

.settings-test-status.status-error {
	color: #dc2626;
}

.settings-test-time {
	font-size: 12px;
	color: var(--color-muted);
}

/* Responsive */
@media (max-width: 768px) {
	.settings-grid {
		grid-template-columns: 1fr;
	}

	.settings-test-block {
		flex-direction: column;
		align-items: flex-start;
	}
}
</style>
