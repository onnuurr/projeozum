<script setup>
import { ref, reactive, computed, watch, nextTick, onBeforeUnmount } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { OzPageHeader, OzModuleTabs, OzStatStrip, OzCard, OzPill, OzButton, OzAlert, OzEmptyState, OzIcon } from '@/Components/Oz'
import { useCan } from '@/composables/useCan'

// Süper admin · Yedeklemeler (Oz kit). backup_runs kayıtları (son 50) + elle tetikleme (RunBackupJob, kuyruk).
//   Çalışan yedek varken liste 5 sn'de bir yenilenir.
defineOptions({ layout: AppLayout })

const props = defineProps({
	runs: { type: Array, default: () => [] },
	nextRunAt: { type: String, default: null },
})

const { can } = useCan()

const STATUS = {
	running: { tone: 'info', label: 'Çalışıyor', icon: 'refresh' },
	success: { tone: 'success', label: 'Başarılı', icon: 'success' },
	failed: { tone: 'danger', label: 'Başarısız', icon: 'error' },
}
const status = (s) => STATUS[s] ?? { tone: 'neutral', label: '—', icon: 'help' }

/* ── özet ── */
const latest = computed(() => props.runs[0] ?? null)
const running = computed(() => props.runs.find((r) => r.status === 'running') ?? null)
const lastSuccess = computed(() => props.runs.find((r) => r.status === 'success') ?? null)
// En yeni kayıttan geriye art arda başarısız sayısı
const failStreak = computed(() => {
	let n = 0
	for (const r of props.runs) { if (r.status === 'running') continue; if (r.status !== 'failed') break; n++ }
	return n
})
const recent = computed(() => props.runs.filter((r) => r.status !== 'running').slice(0, 20))
const recentOk = computed(() => recent.value.filter((r) => r.status === 'success').length)
const rate = computed(() => (recent.value.length ? Math.round((recentOk.value / recent.value.length) * 100) : null))

const daysSince = (iso) => (iso ? Math.floor((Date.now() - new Date(iso).getTime()) / 86400000) : null)
const agoText = (iso) => {
	const d = daysSince(iso)
	return d == null ? '—' : d === 0 ? 'Bugün' : d === 1 ? 'Dün' : `${d} gün önce`
}

const stats = computed(() => {
	const l = latest.value
	const okDays = daysSince(lastSuccess.value?.started_at)
	return [
		{
			key: 'latest', label: 'Son yedek', value: l ? status(l.status).label : '—', icon: l ? status(l.status).icon : 'help',
			tone: l ? (l.status === 'failed' ? 'danger' : l.status === 'running' ? 'info' : 'success') : 'info',
			delta: l ? formatShort(l.started_at) : '', trend: l?.status === 'failed' ? 'down' : 'up',
		},
		{
			key: 'ok', label: 'Son başarılı yedek', value: lastSuccess.value ? agoText(lastSuccess.value.started_at) : 'Yok', icon: 'cloud',
			tone: okDays == null || okDays > 1 ? 'danger' : 'success',
			delta: lastSuccess.value ? formatShort(lastSuccess.value.started_at) : '', trend: okDays == null || okDays > 1 ? 'warn' : 'up',
		},
		{
			key: 'rate', label: `Başarı oranı · son ${recent.value.length}`, value: rate.value == null ? '—' : `%${rate.value}`, icon: 'chart',
			tone: rate.value == null ? 'info' : rate.value >= 90 ? 'success' : rate.value >= 70 ? 'warning' : 'danger',
			delta: recent.value.length ? `${recentOk.value}/${recent.value.length} başarılı` : '', trend: rate.value >= 90 ? 'up' : 'warn',
		},
		// Saat etikette: sayaç animasyonu "05:00"daki baştaki sıfırı düşürüyor
		{ key: 'next', label: `Sıradaki çalışma · ${formatTime(props.nextRunAt)}`, value: dayText(props.nextRunAt), icon: 'clock', tone: 'info', delta: '' },
	]
})

// Son 30 çalışma şeridi (eskiden yeniye)
const strip = computed(() => props.runs.slice(0, 30).reverse())

/* ── işlemler ── */
const busy = ref(false)
function runNow() {
	if (busy.value || running.value) return
	busy.value = true
	router.post(route('superadmin.backups.run'), {}, { preserveScroll: true, onFinish: () => { busy.value = false } })
}

let poll = null
watch(running, (r) => {
	clearInterval(poll)
	poll = r ? setInterval(() => router.reload({ only: ['runs'], preserveScroll: true, preserveState: true }), 5000) : null
}, { immediate: true })
onBeforeUnmount(() => clearInterval(poll))

/* ── geçmiş ── */
const open = reactive(new Set())
const toggle = (id) => (open.has(id) ? open.delete(id) : open.add(id))
// Uyarıdaki "Ayrıntı": satırı açıp ona kaydırır
function showRun(id) {
	open.add(id)
	nextTick(() => document.getElementById(`run-${id}`)?.scrollIntoView({ behavior: 'smooth', block: 'start' }))
}
const errorHead = (msg) => (msg ?? '').split('\n')[0]
const errorLines = (msg) => (msg ?? '').split('\n').filter(Boolean).length

function formatDate(iso) {
	if (!iso) return '—'
	return new Date(iso).toLocaleString('tr-TR', { dateStyle: 'medium', timeStyle: 'short' })
}
function formatShort(iso) {
	if (!iso) return ''
	return new Date(iso).toLocaleString('tr-TR', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' })
}
// Bugün / Yarın / "24 Eyl" — tarayıcının saat diliminde
function dayText(iso) {
	if (!iso) return '—'
	const d = new Date(iso)
	const diff = Math.round((new Date(d).setHours(0, 0, 0, 0) - new Date().setHours(0, 0, 0, 0)) / 86400000)
	return diff === 0 ? 'Bugün' : diff === 1 ? 'Yarın' : d.toLocaleDateString('tr-TR', { day: 'numeric', month: 'short' })
}
function formatTime(iso) {
	return iso ? new Date(iso).toLocaleTimeString('tr-TR', { hour: '2-digit', minute: '2-digit' }) : '—'
}
function formatDuration(sec) {
	if (!sec) return '—'
	const m = Math.floor(sec / 60)
	return m ? `${m} dk ${sec % 60} sn` : `${sec} sn`
}
function formatBytes(bytes) {
	if (!bytes) return '—'
	const units = ['B', 'KB', 'MB', 'GB', 'TB']
	const i = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1)
	return `${(bytes / 1024 ** i).toLocaleString('tr-TR', { maximumFractionDigits: 1 })} ${units[i]}`
}
const totalBytes = (r) => (r.db_dump_bytes ?? 0) + (r.files_bytes ?? 0)
</script>

<template>
	<Head title="Yedeklemeler · Süper Admin" />

	<OzPageHeader title="Yedeklemeler">
		<template #subtitle>
			Veritabanı + proje dosyaları her gece <b>{{ formatTime(nextRunAt) }}</b>'de <code>backup:run</code> ile yedeklenip rclone ile Google Drive'a kopyalanır
		</template>
		<template v-if="can('backups.manage')" #actions>
			<OzButton variant="primary" icon="cloud" :loading="busy" :disabled="!!running || busy" @click="runNow">Şimdi yedekle</OzButton>
		</template>
	</OzPageHeader>
	<OzModuleTabs />

	<OzAlert v-if="running" tone="info" title="Yedekleme çalışıyor" class="block">
		{{ formatDate(running.started_at) }} tarihinde başladı ({{ running.triggered_by === 'manual' ? 'elle' : 'zamanlanmış' }}). Sayfa bitene kadar kendini yeniler.
	</OzAlert>
	<OzAlert
		v-else-if="failStreak"
		tone="danger"
		:title="failStreak > 1 ? `Son ${failStreak} yedekleme başarısız` : 'Son yedekleme başarısız'"
		class="block"
	>
		<template v-if="lastSuccess">Son başarılı yedek {{ formatDate(lastSuccess.started_at) }} ({{ agoText(lastSuccess.started_at).toLocaleLowerCase('tr') }}).</template>
		<template v-else>Kayıtlarda başarılı yedek yok.</template>
		<span class="alert-err">{{ errorHead(latest.error_message) }}</span>
		<template v-if="latest" #actions>
			<OzButton size="sm" icon="chevron-down" @click="showRun(latest.id)">Ayrıntı</OzButton>
		</template>
	</OzAlert>

	<OzStatStrip :items="stats" />

	<OzCard title="Geçmiş" :meta="runs.length ? `son ${runs.length} çalışma` : ''" class="block">
		<OzEmptyState v-if="!runs.length" icon="cloud" title="Henüz yedekleme çalışmadı" description="Her gece 02:00'de otomatik çalışır ya da “Şimdi yedekle” ile başlatılır." compact />
		<template v-else>
			<div class="strip" role="img" :aria-label="`Son ${strip.length} çalışma: ${strip.filter((r) => r.status === 'success').length} başarılı`">
				<span
					v-for="r in strip"
					:key="r.id"
					class="bar"
					:class="`is-${r.status}`"
					:title="`${formatDate(r.started_at)} · ${status(r.status).label}`"
				/>
				<span class="strip-legend">son {{ strip.length }} · eskiden yeniye</span>
			</div>

			<div class="runs">
				<div v-for="r in runs" :id="`run-${r.id}`" :key="r.id" class="run" :class="{ open: open.has(r.id) }">
					<button type="button" class="run-row" :aria-expanded="open.has(r.id)" @click="toggle(r.id)">
						<span class="status" :class="`is-${r.status}`"><OzIcon :name="status(r.status).icon" :size="16" /></span>
						<span class="when">
							<b>{{ formatDate(r.started_at) }}</b>
							<span>{{ r.triggered_by === 'manual' ? 'Elle' : 'Zamanlanmış' }} · {{ formatDuration(r.duration_seconds) }}</span>
						</span>
						<span v-if="r.status === 'failed'" class="err">{{ errorHead(r.error_message) }}</span>
						<span v-else class="sizes">
							<span title="Veritabanı"><OzIcon name="database" :size="13" /> {{ formatBytes(r.db_dump_bytes) }}</span>
							<span title="Dosyalar"><OzIcon name="hard-drive" :size="13" /> {{ formatBytes(r.files_bytes) }}</span>
						</span>
						<OzPill :tone="status(r.status).tone" class="pill">{{ status(r.status).label }}</OzPill>
						<OzIcon name="chevron-right" :size="15" class="chev" />
					</button>
					<div v-if="open.has(r.id)" class="detail">
						<dl>
							<div><dt>Başladı</dt><dd>{{ formatDate(r.started_at) }}</dd></div>
							<div><dt>Bitti</dt><dd>{{ formatDate(r.finished_at) }}</dd></div>
							<div><dt>Veritabanı</dt><dd>{{ formatBytes(r.db_dump_bytes) }}</dd></div>
							<div><dt>Dosyalar</dt><dd>{{ formatBytes(r.files_bytes) }}</dd></div>
							<div><dt>Toplam</dt><dd>{{ formatBytes(totalBytes(r)) }}</dd></div>
							<div><dt>Hedef</dt><dd class="mono">{{ r.remote_path || '—' }}</dd></div>
						</dl>
						<template v-if="r.error_message">
							<div class="label">Hata çıktısı · {{ errorLines(r.error_message) }} satır</div>
							<pre class="log">{{ r.error_message }}</pre>
						</template>
					</div>
				</div>
			</div>
		</template>
	</OzCard>
</template>

<style scoped>
code { font-family: ui-monospace, monospace; font-size: .92em; padding: 1px 5px; border-radius: 5px; background: var(--oz-surface-2); border: 1px solid var(--oz-border-soft); }
.block { margin-bottom: 14px; }
.alert-err { display: block; margin-top: 4px; font-family: ui-monospace, monospace; font-size: 11.5px; opacity: .85; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

.strip { display: flex; align-items: flex-end; gap: 3px; padding: 0 16px 14px; }
.bar { flex: 1; max-width: 18px; height: 26px; border-radius: 4px; background: var(--oz-border); }
.bar.is-success { background: var(--oz-success); }
.bar.is-failed { background: var(--oz-danger); }
.bar.is-running { background: var(--oz-info); }
.bar:hover { opacity: .75; }
.strip-legend { margin-left: auto; padding-left: 10px; font-size: 11px; color: var(--oz-muted-2); white-space: nowrap; align-self: center; }

.runs { display: flex; flex-direction: column; gap: 8px; padding: 0 16px 16px; }
.run { scroll-margin-top: 16px; border: 1px solid var(--oz-border-soft); border-radius: 12px; background: var(--oz-surface); overflow: hidden; }
.run.open { border-color: var(--oz-border); box-shadow: var(--oz-shadow-sm); }
.run-row { width: 100%; display: flex; align-items: center; gap: 12px; padding: 10px 12px; border: none; background: none; text-align: left; font: inherit; color: inherit; cursor: pointer; }
.run-row:hover { background: var(--oz-surface-2); }
.run-row:focus-visible { outline: 2px solid var(--oz-accent); outline-offset: -2px; border-radius: 12px; }
.status { width: 30px; height: 30px; border-radius: 8px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.status.is-success { background: var(--oz-success-soft); color: var(--oz-success); }
.status.is-failed { background: var(--oz-danger-soft); color: var(--oz-danger); }
.status.is-running { background: var(--oz-info-soft); color: var(--oz-info); }
.when { display: flex; flex-direction: column; gap: 1px; width: 190px; flex-shrink: 0; }
.when b { font-size: 13px; color: var(--oz-ink-soft); }
.when span { font-size: 11.5px; color: var(--oz-muted); }
.err { flex: 1; min-width: 0; font-family: ui-monospace, monospace; font-size: 11.5px; color: var(--oz-danger); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.sizes { flex: 1; display: flex; gap: 14px; font-size: 12px; color: var(--oz-muted); font-variant-numeric: tabular-nums; }
.sizes span { display: inline-flex; align-items: center; gap: 4px; }
.pill { flex-shrink: 0; }
.chev { color: var(--oz-muted); transition: transform .18s; flex-shrink: 0; }
.open .chev { transform: rotate(90deg); }

.detail { padding: 12px 14px 14px 54px; border-top: 1px solid var(--oz-border-soft); display: flex; flex-direction: column; gap: 10px; }
dl { margin: 0; display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 8px 16px; }
dt { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: var(--oz-muted); }
dd { margin: 2px 0 0; font-size: 12.5px; color: var(--oz-ink-soft); font-variant-numeric: tabular-nums; }
dd.mono { font-family: ui-monospace, monospace; font-size: 11.5px; word-break: break-all; }
.label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: var(--oz-muted); }
.log { margin: 0; font-family: ui-monospace, monospace; font-size: 11px; line-height: 1.5; color: var(--oz-ink-soft); background: var(--oz-surface-2); border: 1px solid var(--oz-border-soft); border-radius: 8px; padding: 10px 12px; max-height: 260px; overflow: auto; white-space: pre-wrap; word-break: break-word; }

@media (max-width: 640px) {
	.strip, .runs { padding-left: 10px; padding-right: 10px; }
	.strip-legend { display: none; }
	.run-row { flex-wrap: wrap; row-gap: 6px; }
	.when { flex: 1; width: auto; }
	.err, .sizes { order: 5; flex-basis: 100%; padding-left: 42px; }
	.detail { padding-left: 14px; }
	dl { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
</style>
