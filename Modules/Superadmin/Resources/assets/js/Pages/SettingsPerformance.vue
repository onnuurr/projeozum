<script setup>
import { OzField, OzInput, OzSelect, OzSwitch, OzSettingRow, OzButton, OzPill } from '@/Components/Oz'

// Ayarlar · Performans: önbellek, kuyruk, oturum, loglama ve önbellek temizleme işlemleri
defineProps({
	form: { type: Object, required: true },
	options: { type: Object, required: true },
	errors: { type: Object, default: () => ({}) },
	// Çalışan temizleme işi: 'all' | 'clear' | 'config' | 'route' | 'view' | false
	cacheBusy: { type: [String, Boolean], default: false },
})
const emit = defineEmits(['cache-action'])

const cacheActions = [
	{ type: 'clear', label: 'Cache temizle' },
	{ type: 'config', label: 'Config cache yenile' },
	{ type: 'route', label: 'Route cache yenile' },
	{ type: 'view', label: 'View cache yenile' },
]
</script>

<template>
	<div class="stack">
		<div class="block">
			<h4>Önbellek ve kuyruk</h4>
			<div class="form-grid three">
				<OzField label="Cache sürücüsü">
					<OzSelect v-model="form.cacheDriver" :options="options.cacheDrivers" />
				</OzField>
				<OzField label="Cache süresi (TTL)">
					<OzInput v-model="form.cacheTtlMinutes" type="number" min="1" suffix="dk" />
				</OzField>
				<OzField label="Session sürücüsü">
					<OzSelect v-model="form.sessionDriver" :options="options.cacheDrivers" />
				</OzField>
				<OzField label="Queue sürücüsü">
					<OzSelect v-model="form.queueDriver" :options="options.queueDrivers" />
				</OzField>
				<OzField label="Worker sayısı">
					<OzInput v-model="form.queueWorkers" type="number" min="1" max="32" />
				</OzField>
			</div>
		</div>

		<div class="block">
			<h4>Loglama</h4>
			<div class="form-grid">
				<OzField label="Log seviyesi">
					<OzSelect v-model="form.logLevel" :options="options.logLevels" />
				</OzField>
				<OzField label="Log saklama">
					<OzInput v-model="form.logRetentionDays" type="number" min="1" suffix="gün" />
				</OzField>
			</div>
			<div class="rows">
				<OzSettingRow icon="code" title="Debug bar" description="Geliştirici araç çubuğu — production'da kapalı olmalı." :tone="form.enableDebugBar ? 'danger' : 'neutral'">
					<OzPill v-if="form.enableDebugBar" tone="danger">Dikkat</OzPill>
					<OzSwitch v-model="form.enableDebugBar" aria-label="Debug bar" />
				</OzSettingRow>
				<OzSettingRow icon="database" title="Query log" description="Tüm SQL sorgularını loglar. Yüksek I/O — yalnızca hata ayıklama için.">
					<OzSwitch v-model="form.enableQueryLog" aria-label="Query log" />
				</OzSettingRow>
			</div>
		</div>

		<div class="block">
			<h4>Önbelleği temizle</h4>
			<OzSettingRow icon="eraser" title="Laravel + Cloudflare" description="Uygulama önbelleğini ve (API bölümünde tanımlıysa) Cloudflare önbelleğini birlikte temizler.">
				<OzButton variant="primary" size="sm" icon="eraser" :disabled="!!cacheBusy && cacheBusy !== 'all'" :loading="cacheBusy === 'all'" @click="emit('cache-action', 'all')">Tümünü temizle</OzButton>
			</OzSettingRow>
			<div class="cache-row">
				<OzButton
					v-for="a in cacheActions"
					:key="a.type"
					size="sm"
					:disabled="!!cacheBusy && cacheBusy !== a.type"
					:loading="cacheBusy === a.type"
					@click="emit('cache-action', a.type)"
				>{{ a.label }}</OzButton>
			</div>
		</div>
	</div>
</template>

<style scoped>
.cache-row { display: flex; flex-wrap: wrap; gap: 8px; }
</style>
