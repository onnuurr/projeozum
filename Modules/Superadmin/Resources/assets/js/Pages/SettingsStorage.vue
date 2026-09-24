<script setup>
import { OzField, OzInput, OzSelect, OzSwitch, OzSettingRow, OzButton } from '@/Components/Oz'

// Ayarlar · Depolama & yedek: dosya depolama, CDN, yükleme sınırları, zamanlanmış yedekleme
defineProps({
	form: { type: Object, required: true },
	options: { type: Object, required: true },
	errors: { type: Object, default: () => ({}) },
	backupBusy: { type: Boolean, default: false },
	canManageBackups: { type: Boolean, default: false },
})
const emit = defineEmits(['run-backup', 'view-history', 'focus-sensitive'])

const fmt = (iso) => (iso ? new Date(iso).toLocaleString('tr-TR', { dateStyle: 'short', timeStyle: 'short' }) : '—')
</script>

<template>
	<div class="stack">
		<div class="block">
			<h4>Dosya depolama</h4>
			<div class="form-grid">
				<OzField label="Sürücü" required>
					<OzSelect v-model="form.driver" :options="options.storageDrivers" />
				</OzField>
				<OzField label="Bucket">
					<OzInput v-model="form.bucket" icon="database" class="mono" placeholder="my-bucket" />
				</OzField>
				<OzField label="Bölge (region)">
					<OzInput v-model="form.region" class="mono" placeholder="eu-west-1" />
				</OzField>
				<OzField label="Access key">
					<OzInput v-model="form.accessKey" icon="key" class="mono" autocomplete="off" @focus="emit('focus-sensitive', 'accessKey')" />
				</OzField>
				<OzField label="Secret key">
					<OzInput v-model="form.secretKey" type="password" icon="lock" class="mono" autocomplete="off" revealable @focus="emit('focus-sensitive', 'secretKey')" />
				</OzField>
				<OzField label="CDN URL" hint="CloudFront, Cloudflare vb. (isteğe bağlı)">
					<OzInput v-model="form.cdnUrl" type="url" icon="globe" placeholder="https://cdn.example.com" />
				</OzField>
				<OzField label="Maks. yükleme boyutu">
					<OzInput v-model="form.maxUploadMB" type="number" min="1" suffix="MB" placeholder="100" />
				</OzField>
				<OzField label="İzin verilen uzantılar" hint="Virgülle ayrılmış">
					<OzInput v-model="form.allowedExtensions" class="mono" placeholder="jpg,png,pdf,doc,xls" />
				</OzField>
			</div>
		</div>

		<div class="block">
			<h4>Otomatik yedekleme</h4>
			<div class="rows">
				<OzSettingRow icon="cloud" title="Yedekleme aktif" description="Veritabanı ve dosyalar zamanlanmış olarak yedeklenir.">
					<OzSwitch v-model="form.backupEnabled" aria-label="Yedekleme aktif" />
					<template v-if="form.backupEnabled" #details>
						<div class="form-grid three">
							<OzField label="Sıklık">
								<OzSelect v-model="form.backupSchedule" :options="options.backupSchedules" />
							</OzField>
							<OzField label="Saat (UTC+3)">
								<OzInput v-model="form.backupTime" type="time" />
							</OzField>
							<OzField label="Saklama süresi" hint="Eski yedekler silinir">
								<OzInput v-model="form.backupRetentionDays" type="number" min="1" suffix="gün" />
							</OzField>
						</div>
					</template>
				</OzSettingRow>
				<OzSettingRow icon="archive" title="Son yedek" :description="`${fmt(form.lastBackupAt)} · ${form.lastBackupSize || '—'}`">
					<OzButton size="sm" icon="clock" @click="emit('view-history')">Geçmiş</OzButton>
					<OzButton v-if="canManageBackups" size="sm" variant="primary" icon="upload" :loading="backupBusy" @click="emit('run-backup')">Şimdi yedekle</OzButton>
				</OzSettingRow>
			</div>
		</div>
	</div>
</template>
