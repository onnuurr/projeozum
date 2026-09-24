<script setup>
import { OzField, OzInput, OzTextarea, OzCheckbox, OzRadioGroup } from '@/Components/Oz'

// Ayarlar · Güvenlik: parola politikası, 2FA zorunluluğu, oturum/giriş limitleri, log erişim şifresi
defineProps({
	form: { type: Object, required: true },
	errors: { type: Object, default: () => ({}) },
	logAccessPasswordSet: { type: Boolean, default: false },
})

const twoFactorOptions = [
	{ value: 'optional', label: 'İsteğe bağlı', description: 'Kullanıcılar kendi isteğiyle açar' },
	{ value: 'admins', label: 'Yalnızca yöneticiler', description: 'İş ortağı yöneticileri için zorunlu' },
	{ value: 'all', label: 'Tüm kullanıcılar', description: 'Girişte herkes 2FA yapmak zorunda' },
]
</script>

<template>
	<div class="stack">
		<div class="block">
			<h4>Parola politikası</h4>
			<div class="form-grid">
				<OzField label="Minimum uzunluk" hint="En az 6 karakter önerilir">
					<OzInput v-model="form.passwordMinLength" type="number" min="6" max="64" suffix="karakter" />
				</OzField>
				<OzField label="Geçerlilik süresi" hint="0 = süresiz">
					<OzInput v-model="form.passwordExpiryDays" type="number" min="0" max="365" suffix="gün" />
				</OzField>
			</div>
			<div class="check-grid">
				<OzCheckbox v-model="form.passwordRequireUppercase" label="Büyük harf zorunlu" description="En az 1 büyük harf (A-Z)" />
				<OzCheckbox v-model="form.passwordRequireNumbers" label="Rakam zorunlu" description="En az 1 rakam (0-9)" />
				<OzCheckbox v-model="form.passwordRequireSpecial" label="Özel karakter" description="En az 1 özel karakter (!@#$ vb.)" />
			</div>
		</div>

		<div class="block">
			<h4>İki faktörlü kimlik doğrulama (2FA)</h4>
			<OzRadioGroup v-model="form.twoFactorRequired" :options="twoFactorOptions" variant="cards" aria-label="2FA zorunluluğu" />
		</div>

		<div class="block">
			<h4>Oturum ve giriş güvenliği</h4>
			<div class="form-grid three">
				<OzField label="Oturum süresi" hint="İşlem yapılmazsa oturum kapanır">
					<OzInput v-model="form.sessionTimeoutMinutes" type="number" min="5" suffix="dk" />
				</OzField>
				<OzField label="Maks. başarısız giriş" hint="Kilitlenmeden önceki deneme">
					<OzInput v-model="form.maxLoginAttempts" type="number" min="1" max="20" />
				</OzField>
				<OzField label="Kilitlenme süresi" hint="Başarısız denemelerden sonra">
					<OzInput v-model="form.lockoutMinutes" type="number" min="1" suffix="dk" />
				</OzField>
				<OzField label="IP izin listesi (admin paneli)" hint="Her satıra bir IP; CIDR desteklenir. Boş = tüm IP'lere açık" class="span-all">
					<OzTextarea v-model="form.ipWhitelist" :rows="4" class="mono" placeholder="192.168.1.100&#10;10.0.0.0/8" />
				</OzField>
			</div>
		</div>

		<div class="block">
			<h4>Log erişim şifresi</h4>
			<OzField
				label="Log görüntüleyici şifresi (belirle / değiştir)"
				:hint="logAccessPasswordSet ? '✓ Şifre belirlenmiş. Değiştirmek için yenisini girin; boş bırakırsan mevcut şifre korunur.' : 'Henüz şifre yok. Log görüntüleyiciye erişim için bir şifre belirleyin.'"
				:error="errors['security.logAccessPassword']"
			>
				<OzInput v-model="form.logAccessPassword" type="password" icon="lock" autocomplete="new-password" placeholder="Boş bırakırsan mevcut şifre korunur" revealable />
			</OzField>
		</div>
	</div>
</template>
