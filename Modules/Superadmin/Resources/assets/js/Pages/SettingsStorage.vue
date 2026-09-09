<template>
	<div class="settings-storage">
		<!-- Dosya Depolama -->
		<Card title="Dosya Depolama" class="mb-4">
			<p class="section-subtitle">Dosya depolama altyapısı ve CDN ayarları</p>

			<FormField variant="settings" label="Sürücü" required>
				<CustomSelect v-model="form.driver" :options="options.storageDrivers" :show-label="false" />
			</FormField>

			<div class="settings-grid">
				<FormField variant="settings" label="Bucket">
					<FormInput v-model="form.bucket" variant="settings" type="text" class="mono-input" placeholder="my-bucket" />
				</FormField>
				<FormField variant="settings" label="Region">
					<FormInput v-model="form.region" variant="settings" type="text" class="mono-input" placeholder="eu-west-1" />
				</FormField>
			</div>

			<div class="settings-grid">
				<FormField variant="settings" label="Access Key">
					<FormInput
						v-model="form.accessKey"
						variant="settings"
						type="text"
						class="mono-input"
						@focus="clearMask('accessKey')"
					/>
				</FormField>
				<FormField variant="settings" label="Secret Key">
					<FormInput
						v-model="form.secretKey"
						variant="settings"
						type="password"
						class="mono-input"
						@focus="clearMask('secretKey')"
					/>
				</FormField>
			</div>

			<FormField variant="settings" label="CDN URL">
				<FormInput
					v-model="form.cdnUrl"
					variant="settings"
					type="url"
					placeholder="https://cdn.example.com"
				/>
				<div class="field-help">CloudFront, CloudFlare vb. CDN URL'ı (opsiyonel)</div>
			</FormField>

			<div class="settings-grid">
				<FormField variant="settings" label="Max Yükleme Boyutu (MB)">
					<FormInput v-model.number="form.maxUploadMB" variant="settings" type="number" min="1" placeholder="100" />
				</FormField>
				<FormField variant="settings" label="İzin Verilen Uzantılar">
					<FormInput
						v-model="form.allowedExtensions"
						variant="settings"
						type="text"
						class="mono-input"
						placeholder="jpg,png,pdf,doc,xls"
					/>
					<div class="field-help">Virgülle ayrılmış</div>
				</FormField>
			</div>
		</Card>

		<!-- Otomatik Yedekleme -->
		<Card title="Otomatik Yedekleme">
			<p class="section-subtitle">Veritabanı ve dosyaların zamanlanmış yedeklemesi</p>

			<div class="settings-toggle-item">
				<div class="settings-toggle-info">
					<div class="settings-toggle-label">Yedekleme Aktif</div>
					<div class="settings-toggle-sub">Veritabanı + dosyalar zamanlanmış olarak yedeklenir.</div>
				</div>
				<label class="form-toggle">
					<input type="checkbox" v-model="form.backupEnabled" />
					<span class="form-toggle-slider"></span>
				</label>
			</div>

			<!-- Backup Schedule (Conditional) -->
			<template v-if="form.backupEnabled">
				<div class="settings-divider"></div>

				<div class="settings-grid-3col">
					<FormField variant="settings" label="Sıklık">
						<CustomSelect v-model="form.backupSchedule" :options="options.backupSchedules" :show-label="false" />
					</FormField>

					<FormField variant="settings" label="Saat (UTC+3)">
						<FormInput v-model="form.backupTime" variant="settings" type="time" />
						<div class="field-help">Yedekleme saati</div>
					</FormField>

					<FormField variant="settings" label="Saklama Süresi (gün)">
						<FormInput v-model.number="form.backupRetentionDays" variant="settings" type="number" min="1" />
						<div class="field-help">Eski yedekler silinir</div>
					</FormField>
				</div>
			</template>

			<!-- Backup Info -->
			<div class="settings-divider"></div>

			<div class="backup-info">
				<div class="info-block">
					<div class="info-label">Son Yedek</div>
					<div class="info-value">{{ formatBackupDate(form.lastBackupAt) }}</div>
				</div>
				<div class="info-block">
					<div class="info-label">Boyut</div>
					<div class="info-value">{{ form.lastBackupSize || '—' }}</div>
				</div>
			</div>

			<div class="backup-actions">
				<Button variant="secondary" size="sm" @click="$emit('view-history')">Geçmiş</Button>
				<Button
					v-if="canManageBackups"
					variant="primary"
					size="sm"
					:loading="backupBusy"
					@click="$emit('run-backup')"
				>Şimdi Yedekle</Button>
			</div>
		</Card>
	</div>
</template>

<script setup>
import { defineProps } from 'vue'
import Card from '@/Components/Card.vue'
import Button from '@/Components/Button.vue'
import CustomSelect from '@/Components/CustomSelect.vue'
import FormField from '@/Components/Form/FormField.vue'
import FormInput from '@/Components/Form/FormInput.vue'

const SENSITIVE_MASK = '••••••••'

const props = defineProps({
	form: {
		type: Object,
		required: true,
	},
	options: {
		type: Object,
		required: true,
	},
	backupBusy: {
		type: Boolean,
		default: false,
	},
	canManageBackups: {
		type: Boolean,
		default: false,
	},
})

defineEmits(['run-backup', 'view-history'])

function clearMask(key) {
	if (props.form[key] === SENSITIVE_MASK) {
		props.form[key] = ''
	}
}

function formatBackupDate(iso) {
	if (!iso) return '—'
	return new Date(iso).toLocaleString('tr-TR', { dateStyle: 'short', timeStyle: 'short' })
}
</script>

<style scoped>
.settings-storage {
	display: flex;
	flex-direction: column;
	gap: 20px;
}

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

.settings-toggle-info {
	flex: 1;
}

.settings-toggle-label {
	font-size: 13px;
	font-weight: 600;
	color: var(--color-ink);
	margin-bottom: 4px;
}

.settings-toggle-sub {
	font-size: 12px;
	color: var(--color-muted);
	line-height: 1.4;
}

.form-toggle {
	position: relative;
	display: inline-flex;
	width: 44px;
	height: 24px;
	flex-shrink: 0;
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

.backup-info {
	display: grid;
	grid-template-columns: repeat(2, 1fr);
	gap: 16px;
	margin-bottom: 16px;
}

.info-block {
	padding: 12px 14px;
	border-radius: 8px;
	background: var(--color-surface-container-lowest);
	border: 1px solid var(--color-outline-variant);
}

.info-label {
	font-size: 12px;
	font-weight: 600;
	color: var(--color-muted);
	text-transform: uppercase;
	letter-spacing: 0.03em;
	margin-bottom: 4px;
}

.info-value {
	font-size: 14px;
	font-weight: 600;
	color: var(--color-ink);
}

.backup-actions {
	display: flex;
	gap: 10px;
}

.mb-4 {
	margin-bottom: 20px;
}

@media (max-width: 768px) {
	.settings-grid,
	.settings-grid-3col {
		grid-template-columns: 1fr;
	}

	.backup-info {
		grid-template-columns: 1fr;
	}
}
</style>
