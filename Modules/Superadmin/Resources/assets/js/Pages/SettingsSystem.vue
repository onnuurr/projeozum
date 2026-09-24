<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import { OzProgress, OzSettingRow, OzPill, OzStatTile, OzAlert } from '@/Components/Oz'

// Ayarlar · Sistem bilgisi (salt okunur): canlı metrikler, sürümler, servisler, platform sayaçları.
// Yoklamayı sayfa yapar (yalnızca bu bölüm açıkken); burası gelen `system` verisini çizer.
const props = defineProps({
	system: { type: Object, required: true },
	fetchError: { type: Boolean, default: false },
})

const s = computed(() => props.system)
const pct = (used, total) => {
	const t = Number(total) || 0
	return t > 0 ? Math.round(((Number(used) || 0) / t) * 1000) / 10 : 0
}
const toneOf = (p) => (p < 60 ? 'ok' : p < 85 ? 'warn' : 'over')
const metrics = computed(() => [
	{ key: 'cpu', label: 'CPU', value: Number(s.value.cpuUsagePct) || 0, note: '' },
	{ key: 'ram', label: 'RAM', value: pct(s.value.memoryUsageMB, s.value.memoryTotalMB), note: `${s.value.memoryUsageMB} / ${s.value.memoryTotalMB} MB` },
	{ key: 'disk', label: 'Disk', value: pct(s.value.diskUsageGB, s.value.diskTotalGB), note: `${s.value.diskUsageGB} / ${s.value.diskTotalGB} GB` },
])
const versions = computed(() => [
	['PHP', s.value.phpVersion], ['Laravel', s.value.laravelVersion], ['Inertia.js', s.value.inertiaVersion],
	['Vue.js', s.value.vueVersion], ['Veritabanı', s.value.mysqlVersion], ['Redis', s.value.redisVersion],
	['Web sunucu', s.value.webServer], ['İşletim sistemi', s.value.serverOs],
])
const reverb = computed(() => ({
	running: !!s.value.reverb?.running,
	host: s.value.reverb?.host ?? '127.0.0.1',
	port: s.value.reverb?.port ?? 8080,
	error: s.value.reverb?.error ?? null,
}))
const failed = computed(() => Number(s.value.queueJobsFailed) || 0)
</script>

<template>
	<div class="stack">
		<OzAlert v-if="fetchError" tone="danger" title="Canlı veri alınamadı">Son bilinen değerler gösteriliyor.</OzAlert>

		<div class="block">
			<h4>Kullanım metrikleri</h4>
			<div class="metrics">
				<div v-for="m in metrics" :key="m.key" class="metric">
					<OzProgress :label="m.label" :value="m.value" :tone="toneOf(m.value)" />
					<span v-if="m.note" class="note">{{ m.note }}</span>
				</div>
				<div class="metric">
					<span class="k">Çalışma süresi</span>
					<b>{{ s.uptime }}</b>
				</div>
			</div>
		</div>

		<div class="block">
			<h4>Servisler</h4>
			<OzSettingRow icon="activity" title="Reverb (WebSocket)" :description="`${reverb.host}:${reverb.port}`">
				<OzPill :tone="reverb.running ? 'success' : 'danger'">{{ reverb.running ? 'Çalışıyor' : 'Durmuş' }}</OzPill>
				<template #details>
					<p v-if="reverb.running" class="svc-note">Gerçek zamanlı yayın aktif · <code>BROADCAST_CONNECTION=reverb</code></p>
					<p v-else class="svc-note warn">
						Başlatmak için: <code>php artisan reverb:start</code>
						<span v-if="reverb.error"> · {{ reverb.error }}</span>
					</p>
				</template>
			</OzSettingRow>
		</div>

		<div class="block">
			<h4>Yazılım sürümleri</h4>
			<dl class="versions">
				<template v-for="[k, v] in versions" :key="k">
					<dt>{{ k }}</dt>
					<dd>{{ v || '—' }}</dd>
				</template>
			</dl>
		</div>

		<div class="block">
			<h4>Platform sayaçları</h4>
			<div class="counters">
				<OzStatTile label="İş ortağı" :value="s.totalTenants ?? 0" icon="building" tone="accent" />
				<OzStatTile label="Kullanıcı" :value="s.totalUsers ?? 0" icon="users" tone="success" />
				<OzStatTile label="Sipariş" :value="s.totalOrders ?? 0" icon="cart" tone="info" />
				<OzStatTile label="Kuyrukta bekleyen" :value="s.queueJobsPending ?? 0" icon="clock" tone="warning" />
				<Link href="/superadmin/failed-jobs" class="stat-link" aria-label="Başarısız işler · ayrıntılar">
					<OzStatTile label="Başarısız iş" :value="failed" icon="alert" :tone="failed > 0 ? 'danger' : 'info'" />
				</Link>
			</div>
		</div>
	</div>
</template>

<style scoped>
.metrics { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px; }
.metric { display: flex; flex-direction: column; gap: 6px; padding: 12px 14px; border-radius: 12px; background: var(--oz-surface-2); border: 1px solid var(--oz-border-soft); }
.metric .k { font-size: 12px; font-weight: 600; color: var(--oz-ink-soft); }
.metric b { font-size: 15px; font-weight: 800; color: var(--oz-ink-soft); }
.note { font-size: 11px; color: var(--oz-muted); font-variant-numeric: tabular-nums; }
.svc-note { margin: 0; font-size: 12px; color: var(--oz-muted); }
.svc-note.warn { color: var(--oz-warning); }
.svc-note code { font-family: ui-monospace, monospace; font-size: 11.5px; padding: 1px 5px; border-radius: 5px; background: var(--oz-surface-2); border: 1px solid var(--oz-border); color: var(--oz-ink-soft); }
.versions { display: grid; grid-template-columns: auto 1fr; margin: 0; border: 1px solid var(--oz-border-soft); border-radius: 12px; padding: 4px 14px; }
.versions dt, .versions dd { padding: 9px 0; border-top: 1px solid var(--oz-border-soft); font-size: 12.5px; }
.versions dt:first-of-type, .versions dd:first-of-type { border-top: none; }
.versions dt { color: var(--oz-muted); }
.versions dd { margin: 0; text-align: right; font-family: ui-monospace, monospace; font-size: 12px; color: var(--oz-ink-soft); }
.counters { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 12px; }
.stat-link { display: block; color: inherit; text-decoration: none; border-radius: var(--oz-radius); }
</style>
