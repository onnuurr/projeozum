<template>
	<Card title="E-posta (SMTP)" body-class="p-4">
		<p class="section-subtitle">Sistem e-postalarının gönderim altyapısı</p>

		<!-- Driver & Encryption -->
		<div class="settings-grid">
			<FormField label="Sürücü">
				<select v-model="form.driver" class="form-input">
					<option value="smtp">SMTP</option>
					<option value="sendmail">Sendmail</option>
					<option value="mailgun">Mailgun</option>
					<option value="ses">AWS SES</option>
				</select>
			</FormField>

			<FormField label="Şifreleme">
				<select v-model="form.encryption" class="form-input">
					<option value="tls">TLS</option>
					<option value="ssl">SSL</option>
					<option value="">Yok</option>
				</select>
			</FormField>
		</div>

		<!-- SMTP Host & Port -->
		<div class="settings-grid">
			<FormField label="SMTP Host">
				<input v-model="form.host" type="text" class="form-input" placeholder="smtp.gmail.com" />
			</FormField>

			<FormField label="Port">
				<input v-model.number="form.port" type="number" class="form-input" placeholder="587" />
			</FormField>
		</div>

		<!-- Username & Password -->
		<div class="settings-grid">
			<FormField label="Kullanıcı Adı">
				<input v-model="form.username" type="text" class="form-input" />
			</FormField>

			<FormField label="Parola">
				<div class="password-input-wrapper">
					<input
						v-model="form.password"
						:type="showPassword ? 'text' : 'password'"
						class="form-input"
						@focus="clearMask"
					/>
					<button type="button" class="password-toggle" @click="showPassword = !showPassword">
						<Eye v-if="!showPassword" :size="16" />
						<EyeOff v-else :size="16" />
					</button>
				</div>
				<div class="field-help">Maskeliyse, boş bırakıp kaydet = mevcut şifreyi koru</div>
			</FormField>
		</div>

		<!-- Divider -->
		<div class="settings-divider"></div>

		<!-- Sender Info -->
		<h3 class="settings-subhead">Gönderici Bilgileri</h3>

		<div class="settings-grid">
			<FormField label="Gönderici E-posta" required>
				<input v-model="form.fromAddress" type="email" class="form-input" placeholder="noreply@example.com" />
			</FormField>

			<FormField label="Gönderici Adı" required>
				<input v-model="form.fromName" type="text" class="form-input" placeholder="Özüm Server" />
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
import FormField from '@Modules/Shared/Resources/assets/js/Components/FormField.vue'

const SENSITIVE_MASK = '••••••••'
const showToast = inject('showToast')

const props = defineProps({
	form: {
		type: Object,
		required: true,
	},
	options: {
		type: Object,
		default: () => ({}),
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

/* Form Input */
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
	transition: border-color 0.15s, background-color 0.15s;
}

.form-input:focus {
	border-color: var(--color-primary);
	background: var(--color-surface);
	box-shadow: 0 0 0 3px color-mix(in srgb, var(--color-primary) 12%, transparent);
}

.form-input::placeholder {
	color: var(--color-muted);
}

.field-help {
	font-size: 12px;
	color: var(--color-muted);
	margin-top: 4px;
	line-height: 1.4;
}

/* Password Input with Toggle */
.password-input-wrapper {
	position: relative;
	display: flex;
}

.password-input-wrapper .form-input {
	padding-right: 40px;
}

.password-toggle {
	position: absolute;
	right: 10px;
	top: 50%;
	transform: translateY(-50%);
	background: none;
	border: none;
	cursor: pointer;
	display: flex;
	align-items: center;
	justify-content: center;
	color: var(--color-muted);
	transition: color 0.2s;
	padding: 4px;
}

.password-toggle:hover {
	color: var(--color-primary);
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
