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

		<PageHeader title="Yedeklemeler">
			<template #subtitle>
				Veritabanı + proje dosyaları her gece <strong>02:00</strong>'de <code>backup:run</code>
				komutuyla yedeklenip rclone ile Google Drive'a kopyalanır.
			</template>
			<template v-if="can('backups.manage')" #actions>
				<Button variant="primary" size="sm" :disabled="hasRunning" :loading="busy" @click="runNow">
					Şimdi Çalıştır
				</Button>
			</template>
		</PageHeader>

		<div class="kpi-grid">
			<Card title="Son Durum" body-class="p-3.5">
				<Badge v-if="latest" :color="statusColor(latest.status)" :label="statusLabel(latest.status)" />
				<span v-else class="text-sm font-semibold text-ink">—</span>
				<p class="text-2xs text-muted mt-1.5">{{ latest ? formatDate(latest.started_at) : 'Henüz çalışmadı' }}</p>
			</Card>
			<Card title="Sıradaki Çalışma" body-class="p-3.5">
				<p class="text-sm font-semibold text-ink">{{ formatDate(nextRunAt) }}</p>
				<p class="text-2xs text-muted mt-1.5">Zamanlanmış (dailyAt 02:00)</p>
			</Card>
			<Card title="Son 20 Çalışma Başarı Oranı" body-class="p-3.5">
				<ProgressBar
					v-if="successRate !== null"
					:value="Math.round(successRate * 100)"
					:color="rateColor(successRate)"
					show-value
				/>
				<span v-else class="text-sm font-semibold text-ink">—</span>
				<p class="text-2xs text-muted mt-1.5">{{ recentSuccessCount }}/{{ recentWindow.length }} başarılı</p>
			</Card>
		</div>

		<Card title="Geçmiş">
			<template #actions>
				<span class="hint">Son {{ runs.length }} çalışma</span>
			</template>
			<EmptyState v-if="runs.length === 0" :icon="Archive" title="Henüz yedekleme çalışmadı.">
				<p class="text-2xs text-muted empty-hint"><code>php artisan backup:run</code> ile üretilir.</p>
			</EmptyState>
			<div v-else class="rows">
				<div v-for="r in runs" :key="r.id" class="row">
					<Badge :color="statusColor(r.status)" :label="statusLabel(r.status)" />
					<span class="row-date">{{ formatDate(r.started_at) }}</span>
					<span class="row-trigger">{{ r.triggered_by === 'manual' ? 'Elle' : 'Zamanlanmış' }}</span>
					<span class="row-duration">{{ formatDuration(r.duration_seconds) }}</span>
					<span class="row-size">{{ formatBytes((r.db_dump_bytes ?? 0) + (r.files_bytes ?? 0)) }}</span>
					<Tooltip v-if="r.error_message" :text="r.error_message" position="top">
						<span class="row-error">{{ r.error_message }}</span>
					</Tooltip>
				</div>
			</div>
		</Card>
	</div>
</template>

<script setup>
import { computed, ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import { Archive } from 'lucide-vue-next'
import AppLayout from '@/Layouts/AppLayout.vue'
import Breadcrumb from '@/Components/Breadcrumb.vue'
import Badge from '@/Components/Badge.vue'
import ProgressBar from '@/Components/ProgressBar.vue'
import Tooltip from '@/Components/Tooltip.vue'
import Card from '@/Components/Card.vue'
import PageHeader from '@/Components/PageHeader.vue'
import EmptyState from '@/Components/EmptyState.vue'
import Button from '@/Components/Button.vue'
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

function statusColor(status) {
	return { running: 'warning', success: 'success', failed: 'danger' }[status] ?? 'neutral'
}

function rateColor(rate) {
	if (rate === null || rate === undefined) return 'neutral'
	if (rate >= 0.9) return 'success'
	if (rate >= 0.7) return 'warning'
	return 'danger'
}
</script>

<style scoped>
.page-subtitle code { background: rgb(var(--color-bg)); border-radius: 4px; padding: 1px 5px; font-size: 12px; }

.kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 20px; }

.hint { font-size: 12px; color: rgb(var(--color-muted)); white-space: nowrap; }
.empty-hint code { background: rgb(var(--color-bg)); border-radius: 4px; padding: 1px 5px; font-size: 12px; }

.rows { display: flex; flex-direction: column; gap: 6px; }
.row { display: flex; align-items: center; gap: 16px; padding: 12px 14px; border: 1px solid rgb(var(--color-border)); border-radius: 10px; background: rgb(var(--color-bg) / .5); flex-wrap: wrap; }
.row-date { font-size: 13px; font-weight: 600; color: rgb(var(--color-ink)); min-width: 150px; }
.row-trigger { font-size: 12px; color: rgb(var(--color-muted)); }
.row-duration { font-size: 12.5px; color: rgb(var(--color-muted)); }
.row-size { font-size: 12.5px; color: rgb(var(--color-muted)); font-weight: 600; margin-left: auto; }
.row-error { font-size: 12px; color: rgb(var(--color-danger)); max-width: 260px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

@media (max-width: 700px) {
	.row { flex-wrap: wrap; }
	.row-date { min-width: 0; width: 100%; }
	.row-size { margin-left: 0; }
}
</style>
