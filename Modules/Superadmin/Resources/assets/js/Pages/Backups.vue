<template>
	<Head title="Yedeklemeler" />
	<div class="page-backups">
		<Breadcrumb
			:items="[
				{ label: 'Ana Sayfa', to: '/workflow', icon: 'home' },
				{ label: 'Süper Admin' },
				{ label: 'Yedeklemeler' },
			]"
		/>

		<div class="page-header">
			<div>
				<h1 class="page-title">Yedeklemeler</h1>
				<p class="page-subtitle">
					Veritabanı + proje dosyaları her gece <strong>02:00</strong>'de <code>backup:run</code>
					komutuyla yedeklenip rclone ile Google Drive'a kopyalanır.
				</p>
			</div>
			<button v-if="can('backups.manage')" class="btn btn-primary btn-sm" :disabled="busy || hasRunning" @click="runNow">
				{{ busy ? 'Kuyruğa alınıyor…' : 'Şimdi Çalıştır' }}
			</button>
		</div>

		<div class="kpi-grid">
			<div class="kpi-card">
				<span class="kpi-label">Son Durum</span>
				<span class="kpi-value" :class="statusColorClass(latest?.status)">{{ statusLabel(latest?.status) }}</span>
				<span class="kpi-hint">{{ latest ? formatDate(latest.started_at) : 'Henüz çalışmadı' }}</span>
			</div>
			<div class="kpi-card">
				<span class="kpi-label">Sıradaki Çalışma</span>
				<span class="kpi-value kpi-value-sm">{{ formatDate(nextRunAt) }}</span>
				<span class="kpi-hint">Zamanlanmış (dailyAt 02:00)</span>
			</div>
			<div class="kpi-card">
				<span class="kpi-label">Son 20 Çalışma Başarı Oranı</span>
				<span class="kpi-value" :class="rateColorClass(successRate)">{{ successRate === null ? '—' : `%${Math.round(successRate * 100)}` }}</span>
				<span class="kpi-hint">{{ recentSuccessCount }}/{{ recentWindow.length }} başarılı</span>
			</div>
		</div>

		<div class="card">
			<div class="card-header">
				<svg class="header-icon" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
					<ellipse cx="12" cy="5" rx="9" ry="3" />
					<path d="M3 5v6c0 1.66 4 3 9 3s9-1.34 9-3V5M3 11v6c0 1.66 4 3 9 3s9-1.34 9-3v-6" />
				</svg>
				<h3>Geçmiş</h3>
				<span class="hint">Son {{ runs.length }} çalışma</span>
			</div>
			<div class="card-body">
				<div v-if="runs.length === 0" class="empty-block">
					Henüz yedekleme çalışmadı. <code>php artisan backup:run</code> ile üretilir.
				</div>
				<div v-else class="rows">
					<div v-for="r in runs" :key="r.id" class="row">
						<span class="status-badge" :class="statusColorClass(r.status)">{{ statusLabel(r.status) }}</span>
						<span class="row-date">{{ formatDate(r.started_at) }}</span>
						<span class="row-trigger">{{ r.triggered_by === 'manual' ? 'Elle' : 'Zamanlanmış' }}</span>
						<span class="row-duration">{{ formatDuration(r.duration_seconds) }}</span>
						<span class="row-size">{{ formatBytes((r.db_dump_bytes ?? 0) + (r.files_bytes ?? 0)) }}</span>
						<span v-if="r.error_message" class="row-error" :title="r.error_message">{{ r.error_message }}</span>
					</div>
				</div>
			</div>
		</div>
	</div>
</template>

<script setup>
import { computed, ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Breadcrumb from '@/Components/Breadcrumb.vue'
import { useCan } from '@/composables/useCan'

defineOptions({ layout: AppLayout })

const { can } = useCan()

const props = defineProps({
	runs: { type: Array, default: () => [] },
	nextRunAt: { type: String, default: null },
})

const busy = ref(false)

const latest = computed(() => props.runs[0] ?? null)
const hasRunning = computed(() => props.runs.some(r => r.status === 'running'))
const recentWindow = computed(() => props.runs.slice(0, 20))
const recentSuccessCount = computed(() => recentWindow.value.filter(r => r.status === 'success').length)
const successRate = computed(() => recentWindow.value.length ? recentSuccessCount.value / recentWindow.value.length : null)

function runNow() {
	if (busy.value || hasRunning.value) return
	busy.value = true
	router.post('/superadmin/backups/run', {}, {
		preserveScroll: true,
		onFinish: () => { busy.value = false },
	})
}

function formatDate(iso) {
	if (!iso) return '—'
	return new Date(iso).toLocaleString('tr-TR', { dateStyle: 'medium', timeStyle: 'short' })
}

function formatDuration(seconds) {
	if (!seconds) return '—'
	const m = Math.floor(seconds / 60)
	const s = seconds % 60
	return m > 0 ? `${m}dk ${s}sn` : `${s}sn`
}

function formatBytes(bytes) {
	if (!bytes) return '—'
	const units = ['B', 'KB', 'MB', 'GB']
	const i = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1)
	return `${(bytes / 1024 ** i).toFixed(1)} ${units[i]}`
}

function statusLabel(status) {
	return { running: 'Çalışıyor', success: 'Başarılı', failed: 'Başarısız' }[status] ?? '—'
}

function statusColorClass(status) {
	return { running: 'rate-warn', success: 'rate-good', failed: 'rate-bad' }[status] ?? 'rate-neutral'
}

function rateColorClass(rate) {
	if (rate === null || rate === undefined) return 'rate-neutral'
	if (rate >= 0.9) return 'rate-good'
	if (rate >= 0.7) return 'rate-warn'
	return 'rate-bad'
}
</script>

<style scoped>
.page-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 20px; gap: 16px; }
.page-title { font-size: 22px; font-weight: 700; color: #1a1a2e; line-height: 1.2; }
.page-subtitle { font-size: 13px; color: #888; margin-top: 4px; max-width: 640px; }
.page-subtitle code { background: #f5f5f8; border-radius: 4px; padding: 1px 5px; font-size: 12px; }

.kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 20px; }
.kpi-card { display: flex; flex-direction: column; gap: 6px; background: #fff; border: 1px solid #ebebf0; border-radius: 16px; padding: 18px; box-shadow: 0 1px 4px rgba(0,0,0,.04); }
.kpi-label { font-size: 12px; font-weight: 600; color: #888; text-transform: uppercase; letter-spacing: .04em; }
.kpi-value { font-size: 24px; font-weight: 800; color: #1a1a2e; }
.kpi-value-sm { font-size: 15px; font-weight: 700; }
.kpi-hint { font-size: 12px; color: #999; }

.card { background: #fff; border-radius: 16px; border: 1px solid #ebebf0; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,.04); margin-bottom: 18px; }
.card-header { padding: 14px 18px; display: flex; align-items: center; gap: 10px; border-bottom: 1px solid #f0f0f5; }
.header-icon { color: rgb(var(--color-primary)); flex-shrink: 0; }
.card-header h3 { font-size: 15px; font-weight: 700; color: #1a1a2e; }
.card-header .hint { font-size: 12px; color: #aaa; margin-left: auto; }
.card-body { padding: 18px; }
.empty-block { text-align: center; color: #aaa; padding: 28px 0; font-style: italic; font-size: 13px; }
.empty-block code { background: #f5f5f8; border-radius: 4px; padding: 1px 5px; font-size: 12px; font-style: normal; }

.rows { display: flex; flex-direction: column; gap: 6px; }
.row { display: flex; align-items: center; gap: 16px; padding: 12px 14px; border: 1px solid #f0f0f5; border-radius: 10px; background: #fafafc; flex-wrap: wrap; }
.row-date { font-size: 13px; font-weight: 600; color: #1a1a2e; min-width: 150px; }
.row-trigger { font-size: 12px; color: #999; }
.row-duration { font-size: 12.5px; color: #555; }
.row-size { font-size: 12.5px; color: #555; font-weight: 600; margin-left: auto; }
.row-error { font-size: 12px; color: #dc2626; max-width: 260px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

.status-badge { font-size: 11px; font-weight: 700; padding: 3px 9px; border-radius: 20px; color: #fff; white-space: nowrap; }
.status-badge.rate-good { background: #16a34a; }
.status-badge.rate-warn { background: #f59e0b; }
.status-badge.rate-bad { background: #dc2626; }
.status-badge.rate-neutral { background: #9ca3af; }

.kpi-value.rate-good { color: #16a34a; }
.kpi-value.rate-warn { color: #f59e0b; }
.kpi-value.rate-bad { color: #dc2626; }
.kpi-value.rate-neutral { color: #9ca3af; }

.btn { border: none; border-radius: 8px; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; }
.btn-primary { background: rgb(var(--color-primary)); color: #fff; }
.btn-primary:disabled { opacity: .6; cursor: not-allowed; }
.btn-sm { padding: 8px 14px; font-size: 12.5px; }

@media (max-width: 700px) {
	.page-header { flex-wrap: wrap; }
	.row { flex-wrap: wrap; }
	.row-date { min-width: 0; width: 100%; }
	.row-size { margin-left: 0; }
}
</style>
