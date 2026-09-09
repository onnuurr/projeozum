<template>
	<Card title="Genel Ayarlar" body-class="p-4">
		<p class="section-subtitle">Sistem adı, dil, zaman dilimi gibi temel platform ayarları</p>

		<!-- Sistem Bilgileri: Ad & URL -->
		<div class="settings-grid">
			<FormField variant="settings" label="Sistem Adı" required>
				<FormInput v-model="form.systemName" variant="settings" type="text" placeholder="Ozum Server" />
				<div class="field-help">Üst menüde, e-postalarda ve faturada görünür</div>
			</FormField>

			<FormField variant="settings" label="Sistem URL" required>
				<FormInput v-model="form.systemUrl" variant="settings" type="url" placeholder="https://ozumserver.com.tr" />
			</FormField>
		</div>

		<!-- İletişim Bilgileri -->
		<div class="settings-grid">
			<FormField variant="settings" label="Destek E-posta" required>
				<FormInput v-model="form.supportEmail" variant="settings" type="email" placeholder="support@ozumserver.com.tr" />
			</FormField>

			<FormField variant="settings" label="Logo URL">
				<FormInput v-model="form.logoUrl" variant="settings" type="url" placeholder="https://..." />
			</FormField>
		</div>

		<!-- Bölgesel Ayarlar: Dil, Zaman Dilimi, Format, Para -->
		<div class="settings-grid-4col">
			<FormField variant="settings" label="Varsayılan Dil">
				<CustomSelect v-model="form.defaultLanguage" :options="options.languages" :show-label="false" />
			</FormField>

			<FormField variant="settings" label="Zaman Dilimi">
				<CustomSelect v-model="form.defaultTimezone" :options="options.timezones" :show-label="false" />
			</FormField>

			<FormField variant="settings" label="Tarih Formatı">
				<CustomSelect v-model="form.dateFormat" :options="options.dateFormats" :show-label="false" />
			</FormField>

			<FormField variant="settings" label="Para Birimi">
				<CustomSelect v-model="form.defaultCurrency" :options="options.currencies" :show-label="false" />
			</FormField>
		</div>

		<!-- Divider -->
		<div class="settings-divider"></div>

		<!-- Toggles: Yeni Kayıt & Bakım Modu -->
		<div class="settings-toggle-group">
			<div class="settings-toggle-item">
				<div class="settings-toggle-info">
					<div class="settings-toggle-label">Yeni Kayıt Açık</div>
					<div class="settings-toggle-sub">Kapatırsan yeni firma kaydı yapılamaz, mevcut tenant'lar etkilenmez.</div>
				</div>
				<label class="form-toggle">
					<input type="checkbox" v-model="form.allowSignup" />
					<span class="form-toggle-slider"></span>
				</label>
			</div>

			<div class="settings-toggle-item" :class="{ 'danger-bg': form.maintenanceMode }">
				<div class="settings-toggle-info">
					<div class="settings-toggle-label">
						Bakım Modu
						<Badge v-if="form.maintenanceMode" color="danger" variant="tonal" label="AKTİF" />
					</div>
					<div class="settings-toggle-sub">Açıkken sadece süper admin giriş yapabilir, diğer kullanıcılara bakım sayfası gösterilir.</div>
				</div>
				<label class="form-toggle">
					<input type="checkbox" v-model="form.maintenanceMode" />
					<span class="form-toggle-slider"></span>
				</label>
			</div>
		</div>

		<!-- Conditional: Bakım Mesajı -->
		<transition name="fade">
			<FormField v-if="form.maintenanceMode" variant="settings" label="Bakım Mesajı">
				<FormTextarea v-model="form.maintenanceMessage" class="settings-textarea" rows="3" placeholder="Bakım yapılıyor, lütfen daha sonra tekrar deneyin..." />
			</FormField>
		</transition>
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

defineProps({
	form: {
		type: Object,
		required: true,
	},
	options: {
		type: Object,
		required: true,
	},
})
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
	grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
	gap: 20px;
	margin-bottom: 20px;
}

.settings-grid-4col {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
	gap: 16px;
	margin-bottom: 20px;
}

/* Divider */
.settings-divider {
	height: 1px;
	background: var(--color-outline-variant);
	margin: 24px 0;
}

/* Toggle Group */
.settings-toggle-group {
	display: flex;
	flex-direction: column;
	gap: 12px;
}

.settings-toggle-item {
	display: flex;
	align-items: center;
	justify-content: space-between;
	padding: 12px 14px;
	border-radius: 8px;
	background: var(--color-surface-container-lowest);
	border: 1px solid var(--color-outline-variant);
	transition: all 0.2s;
}

.settings-toggle-item.danger-bg {
	background: color-mix(in srgb, var(--color-danger) 8%, transparent);
	border-color: color-mix(in srgb, var(--color-danger) 30%, transparent);
}

.settings-toggle-info {
	flex: 1;
	min-width: 0;
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

/* Toggle Switch */
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

.form-toggle input:disabled + .form-toggle-slider {
	opacity: 0.5;
	cursor: not-allowed;
}

/* Transitions */
.fade-enter-active,
.fade-leave-active {
	transition: opacity 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
	opacity: 0;
}

/* Responsive */
@media (max-width: 768px) {
	.settings-grid,
	.settings-grid-4col {
		grid-template-columns: 1fr;
	}

	.settings-toggle-item {
		flex-direction: column;
		align-items: flex-start;
		gap: 12px;
	}

	.form-toggle {
		margin-left: 0;
		align-self: flex-end;
	}
}
</style>
