<template>
	<div class="settings-storage">
		<!-- Dosya Depolama -->
		<Card title="Dosya Depolama" class="mb-4">
			<p class="section-subtitle">Dosya depolama altyapısı ve CDN ayarları</p>

			<FormField label="Sürücü" required>
				<select v-model="form.driver" class="form-input">
					<option value="s3">AWS S3</option>
					<option value="local">Yerel (Disk)</option>
					<option value="azure">Azure Blob</option>
				</select>
			</FormField>

			<div class="settings-grid">
				<FormField label="Bucket">
					<input v-model="form.bucket" type="text" class="form-input mono-input" placeholder="my-bucket" />
				</FormField>
				<FormField label="Region">
					<input v-model="form.region" type="text" class="form-input mono-input" placeholder="eu-west-1" />
				</FormField>
			</div>

			<div class="settings-grid">
				<FormField label="Access Key">
					<input
						v-model="form.accessKey"
						type="text"
						class="form-input mono-input"
						@focus="clearMask('accessKey')"
					/>
				</FormField>
				<FormField label="Secret Key">
					<input
						v-model="form.secretKey"
						type="password"
						class="form-input mono-input"
						@focus="clearMask('secretKey')"
					/>
				</FormField>
			</div>

			<FormField label="CDN URL">
				<input
					v-model="form.cdnUrl"
					type="url"
					class="form-input"
					placeholder="https://cdn.example.com"
				/>
				<div class="field-help">CloudFront, CloudFlare vb. CDN URL'ı (opsiyonel)</div>
			</FormField>

			<div class="settings-grid">
				<FormField label="Max Yükleme Boyutu (MB)">
					<input v-model.number="form.maxUploadMB" type="number" min="1" class="form-input" placeholder="100" />
				</FormField>
				<FormField label="İzin Verilen Uzantılar">
					<input
						v-model="form.allowedExtensions"
						type="text"
						class="form-input mono-input"
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
					<FormField label="Sıklık">
						<select v-model="form.backupSchedule" class="form-input">
							<option value="daily">Günde 1 kez</option>
							<option value="twice-daily">Günde 2 kez</option>
							<option value="weekly">Haftada 1 kez</option>
						</select>
					</FormField>

					<FormField label="Saat (UTC+3)">
						<input v-model="form.backupTime" type="time" class="form-input" />
						<div class="field-help">Yedekleme saati</div>
					</FormField>

					<FormField label="Saklama Süresi (gün)">
						<input v-model.number="form.backupRetentionDays" type="number" min="1" class="form-input" />
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
				<Button variant="secondary" size="sm">Geçmiş</Button>
				<Button variant="primary" size="sm" @click="runBackup">Şimdi Yedekle</Button>
			</div>
		</Card>
	</div>
</template>

<script setup>
import { defineProps } from 'vue'
import Card from '@/Components/Card.vue'
import Button from '@/Components/Button.vue'
import FormField from '@Modules/Shared/Resources/assets/js/Components/FormField.vue'

const SENSITIVE_MASK = '••••••••'

const props = defineProps({
	form: {
		type: Object,
		required: true,
	},
})

function clearMask(key) {
	if (props.form[key] === SENSITIVE_MASK) {
		props.form[key] = ''
	}
}

function formatBackupDate(iso) {
	if (!iso) return '—'
	return new Date(iso).toLocaleString('tr-TR', { dateStyle: 'short', timeStyle: 'short' })
}

function runBackup() {
	console.log('Backup triggered')
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
	transition: border-color 0.15s;
}

.form-input:focus {
	border-color: var(--color-primary);
	background: var(--color-surface);
	box-shadow: 0 0 0 3px color-mix(in srgb, var(--color-primary) 12%, transparent);
}

.form-input.mono-input {
	font-family: 'Courier New', monospace;
	font-size: 12px;
}

.field-help {
	font-size: 12px;
	color: var(--color-muted);
	margin-top: 4px;
	line-height: 1.4;
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
