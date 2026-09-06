<template>
	<div class="settings-security">
		<!-- Parola Politikası -->
		<Card title="Parola Politikası" class="mb-4">
			<p class="section-subtitle">Sistem genelinde parola güvenliği standartları</p>

			<div class="settings-grid-2col">
				<FormField label="Minimum Uzunluk">
					<input v-model.number="form.passwordMinLength" type="number" min="6" max="64" class="form-input" />
					<div class="field-help">Minimum 6 karakter önerilir</div>
				</FormField>

				<FormField label="Geçerlilik Süresi (gün)">
					<input v-model.number="form.passwordExpiryDays" type="number" min="0" max="365" class="form-input" />
					<div class="field-help">0 = süresiz</div>
				</FormField>
			</div>

			<h3 class="settings-subhead">Parola Gereksinimleri</h3>

			<div class="checkbox-group">
				<label class="checkbox-card">
					<input type="checkbox" v-model="form.passwordRequireUppercase" />
					<div class="checkbox-content">
						<div class="checkbox-label">Büyük harf zorunlu</div>
						<div class="checkbox-sub">En az 1 büyük harf (A-Z)</div>
					</div>
				</label>

				<label class="checkbox-card">
					<input type="checkbox" v-model="form.passwordRequireNumbers" />
					<div class="checkbox-content">
						<div class="checkbox-label">Rakam zorunlu</div>
						<div class="checkbox-sub">En az 1 rakam (0-9)</div>
					</div>
				</label>

				<label class="checkbox-card">
					<input type="checkbox" v-model="form.passwordRequireSpecial" />
					<div class="checkbox-content">
						<div class="checkbox-label">Özel karakter</div>
						<div class="checkbox-sub">En az 1 özel karakter (!@#$ vb.)</div>
					</div>
				</label>
			</div>
		</Card>

		<!-- İki Faktörlü Kimlik -->
		<Card title="İki Faktörlü Kimlik (2FA)" class="mb-4">
			<p class="section-subtitle">Hesap güvenliğini artırmak için second factor zorunluluğu</p>

			<FormField label="Zorunluluk Düzeyi">
				<div class="radio-card-group">
					<label class="radio-card" :class="{ active: form.twoFactorRequired === 'optional' }">
						<input type="radio" value="optional" v-model="form.twoFactorRequired" />
						<div class="radio-content">
							<div class="radio-label">İsteğe Bağlı</div>
							<div class="radio-sub">Kullanıcılar kendi isteğine bağlı aktive eder</div>
						</div>
					</label>

					<label class="radio-card" :class="{ active: form.twoFactorRequired === 'admins' }">
						<input type="radio" value="admins" v-model="form.twoFactorRequired" />
						<div class="radio-content">
							<div class="radio-label">Sadece Yöneticiler</div>
							<div class="radio-sub">Tenant yöneticileri için zorunlu</div>
						</div>
					</label>

					<label class="radio-card" :class="{ active: form.twoFactorRequired === 'all' }">
						<input type="radio" value="all" v-model="form.twoFactorRequired" />
						<div class="radio-content">
							<div class="radio-label">Tüm Kullanıcılar</div>
							<div class="radio-sub">Girişte tüm kullanıcılar 2FA yapmak zorunda</div>
						</div>
					</label>
				</div>
			</FormField>
		</Card>

		<!-- Oturum & Giriş -->
		<Card title="Oturum & Giriş Güvenliği">
			<p class="section-subtitle">Oturum ve giriş denemesi limitleri</p>

			<div class="settings-grid-3col">
				<FormField label="Oturum Süresi (dakika)">
					<input v-model.number="form.sessionTimeoutMinutes" type="number" min="5" class="form-input" />
					<div class="field-help">İşlem yapılmazsa logout</div>
				</FormField>

				<FormField label="Max Başarısız Giriş">
					<input v-model.number="form.maxLoginAttempts" type="number" min="1" max="20" class="form-input" />
					<div class="field-help">Kilitlenmeden önceki deneme</div>
				</FormField>

				<FormField label="Kilitlenme Süresi (dakika)">
					<input v-model.number="form.lockoutMinutes" type="number" min="1" class="form-input" />
					<div class="field-help">Başarısız denemelerden sonra</div>
				</FormField>
			</div>

			<FormField label="IP Whitelist (Admin Paneli)">
				<textarea v-model="form.ipWhitelist" class="form-textarea" placeholder="192.168.1.100&#10;10.0.0.0/8&#10;Boş = tüm IP'lere açık" rows="4"></textarea>
				<div class="field-help">Her IP bir satırda, CIDR notasyonu desteklenir</div>
			</FormField>
		</Card>
	</div>
</template>

<script setup>
import { defineProps } from 'vue'
import Card from '@/Components/Card.vue'
import FormField from '../../../../Shared/Resources/assets/js/Components/FormField.vue'

defineProps({
	form: {
		type: Object,
		required: true,
	},
	options: {
		type: Object,
		default: () => ({}),
	},
})
</script>

<style scoped>
.settings-security {
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

/* Grid Layouts */
.settings-grid-2col {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
	gap: 20px;
	margin-bottom: 20px;
}

.settings-grid-3col {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
	gap: 16px;
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

.form-textarea {
	width: 100%;
	padding: 10px 12px;
	border: 1.5px solid var(--color-outline-variant);
	border-radius: 8px;
	font-family: 'Courier New', monospace;
	font-size: 12px;
	color: var(--color-ink);
	background: var(--color-surface-container-lowest);
	outline: none;
	resize: vertical;
	min-height: 100px;
	transition: border-color 0.15s, background-color 0.15s;
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

/* Subheading */
.settings-subhead {
	font-size: 12px;
	font-weight: 600;
	color: var(--color-ink);
	text-transform: uppercase;
	letter-spacing: 0.04em;
	margin-bottom: 12px;
	margin-top: 20px;
}

/* Checkbox Group */
.checkbox-group {
	display: flex;
	flex-direction: column;
	gap: 10px;
}

.checkbox-card {
	display: flex;
	align-items: flex-start;
	gap: 12px;
	padding: 12px 14px;
	border-radius: 8px;
	background: var(--color-surface-container-lowest);
	border: 1px solid var(--color-outline-variant);
	cursor: pointer;
	transition: all 0.2s;
}

.checkbox-card:hover {
	border-color: var(--color-primary);
	background: color-mix(in srgb, var(--color-primary) 5%, transparent);
}

.checkbox-card input[type="checkbox"] {
	width: 18px;
	height: 18px;
	margin-top: 2px;
	cursor: pointer;
	flex-shrink: 0;
	accent-color: var(--color-primary);
}

.checkbox-content {
	flex: 1;
}

.checkbox-label {
	font-size: 13px;
	font-weight: 600;
	color: var(--color-ink);
	margin-bottom: 2px;
}

.checkbox-sub {
	font-size: 12px;
	color: var(--color-muted);
	line-height: 1.4;
}

/* Radio Card Group */
.radio-card-group {
	display: flex;
	flex-direction: column;
	gap: 12px;
}

.radio-card {
	display: flex;
	align-items: flex-start;
	gap: 12px;
	padding: 14px 16px;
	border-radius: 8px;
	background: var(--color-surface-container-lowest);
	border: 1.5px solid var(--color-outline-variant);
	cursor: pointer;
	transition: all 0.2s;
}

.radio-card:hover {
	border-color: var(--color-primary);
	background: color-mix(in srgb, var(--color-primary) 5%, transparent);
}

.radio-card.active {
	border-color: var(--color-primary);
	background: color-mix(in srgb, var(--color-primary) 8%, transparent);
}

.radio-card input[type="radio"] {
	width: 18px;
	height: 18px;
	margin-top: 2px;
	cursor: pointer;
	flex-shrink: 0;
	accent-color: var(--color-primary);
}

.radio-content {
	flex: 1;
}

.radio-label {
	font-size: 13px;
	font-weight: 600;
	color: var(--color-ink);
	margin-bottom: 2px;
}

.radio-sub {
	font-size: 12px;
	color: var(--color-muted);
	line-height: 1.4;
}

/* Card Margin */
.mb-4 {
	margin-bottom: 20px;
}

/* Responsive */
@media (max-width: 768px) {
	.settings-grid-2col,
	.settings-grid-3col {
		grid-template-columns: 1fr;
	}
}
</style>
