<script setup>
import { OzField, OzInput, OzSelect, OzCombobox, OzTextarea, OzSwitch, OzSettingRow, OzPill } from '@/Components/Oz'

// Ayarlar · Genel: sistem kimliği, bölgesel ayarlar, kayıt ve bakım modu
defineProps({
	form: { type: Object, required: true },
	options: { type: Object, required: true },
	errors: { type: Object, default: () => ({}) },
})
</script>

<template>
	<div class="stack">
		<div class="block">
			<h4>Sistem kimliği</h4>
			<div class="form-grid">
				<OzField label="Sistem adı" required hint="Üst menüde, e-postalarda ve faturada görünür" :error="errors['general.systemName']">
					<OzInput v-model="form.systemName" icon="building" placeholder="Ozum Server" />
				</OzField>
				<OzField label="Sistem URL" required :error="errors['general.systemUrl']">
					<OzInput v-model="form.systemUrl" type="url" icon="globe" placeholder="https://ozumserver.com.tr" />
				</OzField>
				<OzField label="Destek e-posta" required :error="errors['general.supportEmail']">
					<OzInput v-model="form.supportEmail" type="email" icon="mail" placeholder="support@ozumserver.com.tr" />
				</OzField>
				<OzField label="Logo URL">
					<OzInput v-model="form.logoUrl" type="url" icon="link" placeholder="https://…" />
				</OzField>
			</div>
		</div>

		<div class="block">
			<h4>Bölgesel ayarlar</h4>
			<div class="form-grid four">
				<OzField label="Varsayılan dil">
					<OzSelect v-model="form.defaultLanguage" :options="options.languages" />
				</OzField>
				<OzField label="Zaman dilimi">
					<OzCombobox v-model="form.defaultTimezone" :options="options.timezones" search-placeholder="Şehir ara…" />
				</OzField>
				<OzField label="Tarih biçimi">
					<OzSelect v-model="form.dateFormat" :options="options.dateFormats" />
				</OzField>
				<OzField label="Para birimi">
					<OzSelect v-model="form.defaultCurrency" :options="options.currencies" />
				</OzField>
			</div>
		</div>

		<div class="rows">
			<OzSettingRow icon="user-plus" title="Yeni kayıt açık" description="Kapatırsan yeni firma kaydı yapılamaz, mevcut iş ortakları etkilenmez.">
				<OzSwitch v-model="form.allowSignup" aria-label="Yeni kayıt açık" />
			</OzSettingRow>
			<OzSettingRow
				icon="warning"
				title="Bakım modu"
				description="Açıkken yalnızca süper admin giriş yapabilir, diğer kullanıcılara bakım sayfası gösterilir."
				:tone="form.maintenanceMode ? 'danger' : 'neutral'"
			>
				<OzPill v-if="form.maintenanceMode" tone="danger">Aktif</OzPill>
				<OzSwitch v-model="form.maintenanceMode" aria-label="Bakım modu" />
				<template v-if="form.maintenanceMode" #details>
					<OzField label="Bakım mesajı">
						<OzTextarea v-model="form.maintenanceMessage" :rows="3" placeholder="Bakım yapılıyor, lütfen daha sonra tekrar deneyin…" />
					</OzField>
				</template>
			</OzSettingRow>
		</div>
	</div>
</template>
