<template>
	<div class="settings-security">
		<!-- Parola Politikası -->
		<Card title="Parola Politikası" class="mb-4">
			<p class="section-subtitle">Sistem genelinde parola güvenliği standartları</p>

			<div class="settings-grid-2col">
				<FormField variant="settings" label="Minimum Uzunluk">
					<FormInput v-model.number="form.passwordMinLength" variant="settings" type="number" min="6" max="64" />
					<div class="field-help">Minimum 6 karakter önerilir</div>
				</FormField>

				<FormField variant="settings" label="Geçerlilik Süresi (gün)">
					<FormInput v-model.number="form.passwordExpiryDays" variant="settings" type="number" min="0" max="365" />
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
							<div class="radio-sub">İş ortağı yöneticileri için zorunlu</div>
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
				<FormField variant="settings" label="Oturum Süresi (dakika)">
					<FormInput v-model.number="form.sessionTimeoutMinutes" variant="settings" type="number" min="5" />
					<div class="field-help">İşlem yapılmazsa logout</div>
				</FormField>

				<FormField variant="settings" label="Max Başarısız Giriş">
					<FormInput v-model.number="form.maxLoginAttempts" variant="settings" type="number" min="1" max="20" />
					<div class="field-help">Kilitlenmeden önceki deneme</div>
				</FormField>

				<FormField variant="settings" label="Kilitlenme Süresi (dakika)">
					<FormInput v-model.number="form.lockoutMinutes" variant="settings" type="number" min="1" />
					<div class="field-help">Başarısız denemelerden sonra</div>
				</FormField>
			</div>

			<FormField variant="settings" label="IP Whitelist (Admin Paneli)">
				<FormTextarea v-model="form.ipWhitelist" class="settings-textarea mono-input" min-height="100px" placeholder="192.168.1.100&#10;10.0.0.0/8&#10;Boş = tüm IP'lere açık" rows="4" />
				<div class="field-help">Her IP bir satırda, CIDR notasyonu desteklenir</div>
			</FormField>
		</Card>

		<!-- Log Erişim Şifresi -->
		<Card title="Log Erişim Şifresi">
			<p class="section-subtitle">Log görüntüleyiciye erişim için ayrı bir şifre</p>

			<FormField variant="settings" label="Log erişim şifresi (belirle / değiştir)">
				<FormInput
					v-model="form.logAccessPassword"
					variant="settings"
					:type="showLogAccessPassword ? 'text' : 'password'"
					placeholder="Boş bırakırsan mevcut şifre korunur"
					autocomplete="new-password"
				>
					<template #trailing>
						<button type="button" class="settings-input-trail" @click="showLogAccessPassword = !showLogAccessPassword">
							<Eye v-if="!showLogAccessPassword" :size="16" />
							<EyeOff v-else :size="16" />
						</button>
					</template>
				</FormInput>
				<div v-if="logAccessPasswordSet" class="field-help" style="color: var(--color-success);">
					<Check :size="11" style="vertical-align: -1px;" />
					Log erişim şifresi belirlenmiş. Değiştirmek için yeni şifreyi girin.
				</div>
				<div v-else class="field-help">
					Henüz şifre belirlenmemiş. Log görüntüleyiciye erişim için buradan bir şifre belirleyin.
				</div>
			</FormField>
		</Card>
	</div>
</template>

<script setup>
import { defineProps, ref } from 'vue'
import { Eye, EyeOff, Check } from 'lucide-vue-next'
import Card from '@/Components/Card.vue'
import FormField from '@/Components/Form/FormField.vue'
import FormInput from '@/Components/Form/FormInput.vue'
import FormTextarea from '@/Components/Form/FormTextarea.vue'

defineProps({
	form: {
		type: Object,
		required: true,
	},
	options: {
		type: Object,
		default: () => ({}),
	},
	logAccessPasswordSet: {
		type: Boolean,
		default: false,
	},
})

const showLogAccessPassword = ref(false)
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
