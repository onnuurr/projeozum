<script setup>
import { ref, computed, inject, onMounted, onBeforeUnmount } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import {
	OzPageHeader, OzModuleTabs, OzStatTile, OzCard, OzProgress, OzPill, OzButton, OzAlert, OzSettingRow,
} from '@/Components/Oz'

// Süper admin · sistem paneli (Oz kit).
// İlk render sunucunun staticPayload()'ı ile gelir; canlı metrikler 5 sn'de bir /superadmin/system-info'dan yenilenir.
defineOptions({ layout: AppLayout })

const props = defineProps({
	system: { type: Object, required: true },
})

/* ── canlı sistem metrikleri ── */
const liveSystem = ref({ ...props.system })
const systemFetchedAt = ref(null)
const systemFetchError = ref(false)
let systemTimer = null
const SYSTEM_POLL_MS = 5000

const pct = (used, total) => {
	const t = Number(total) || 0
	return t > 0 ? Math.round(((Number(used) || 0) / t) * 1000) / 10 : 0
}
const cpuPct = computed(() => Number(liveSystem.value.cpuUsagePct) || 0)
const ramPct = computed(() => pct(liveSystem.value.memoryUsageMB, liveSystem.value.memoryTotalMB))
const diskPct = computed(() => pct(liveSystem.value.diskUsageGB, liveSystem.value.diskTotalGB))

// <60 iyi · <85 dikkat · üstü kritik
const toneOf = (p) => (p < 60 ? 'ok' : p < 85 ? 'warn' : 'over')

const metrics = computed(() => [
	{ key: 'cpu', label: 'CPU', value: cpuPct.value, note: '' },
	{ key: 'ram', label: 'RAM', value: ramPct.value, note: `${liveSystem.value.memoryUsageMB} / ${liveSystem.value.memoryTotalMB} MB` },
	{ key: 'disk', label: 'Disk', value: diskPct.value, note: `${liveSystem.value.diskUsageGB} / ${liveSystem.value.diskTotalGB} GB` },
])

const reverb = computed(() => ({
	running: !!liveSystem.value.reverb?.running,
	host: liveSystem.value.reverb?.host ?? '127.0.0.1',
	port: liveSystem.value.reverb?.port ?? 8080,
}))

// Her satırın durumu VersionCheckService'ten (config: superadmin.version_policy) gelir.
const VERSION_TONE = { ok: 'success', warning: 'warning', critical: 'danger', unknown: 'neutral' }
const VERSION_LABEL = { ok: 'Güncel', warning: 'Dikkat', critical: 'Kritik', unknown: 'Bilinmiyor' }
const versionCheck = (key) => liveSystem.value.versionChecks?.components?.[key] ?? null
const versions = computed(() => [
	['php', 'PHP', liveSystem.value.phpVersion],
	['laravel', 'Laravel', liveSystem.value.laravelVersion],
	['inertia', 'Inertia.js', liveSystem.value.inertiaVersion],
	['vue', 'Vue.js', liveSystem.value.vueVersion],
	['database', 'Veritabanı', liveSystem.value.mysqlVersion],
	['redis', 'Redis', liveSystem.value.redisVersion],
	['webServer', 'Web sunucu', liveSystem.value.webServer],
].map(([key, label, value]) => ({ key, label, value, check: versionCheck(key) })))
const fmtDate = (d) => (d ? new Date(d).toLocaleDateString('tr-TR') : null)
const showToast = inject('showToast', null)
// Komut sadece panoya kopyalanır; panel hiçbir güncellemeyi kendisi çalıştırmaz.
function copyCommand(cmd) {
	navigator.clipboard?.writeText(cmd)
	showToast?.({ type: 'success', title: 'Kopyalandı', message: 'Komut panoya kopyalandı; sunucuda çalıştırabilirsiniz.' })
}
const aptListsUpdatedAt = computed(() => fmtDate(liveSystem.value.versionChecks?.aptListsUpdatedAt))
const versionSummary = computed(() => {
	const st = liveSystem.value.versionChecks?.status
	if (!st) return null
	const label = st === 'ok' ? 'Tümü güncel' : st === 'critical' ? 'Kritik güncelleme var' : st === 'warning' ? 'Güncelleme önerilir' : 'Bazı sürümler bilinmiyor'
	// Yayın verisi arka planda (superadmin:check-versions, haftalık) yenilenir; yoksa statik politikaya göre değerlendirilir.
	const at = liveSystem.value.versionChecks?.releasesCheckedAt
	return { tone: VERSION_TONE[st], label: at ? `${label} · son kontrol ${new Date(at).toLocaleDateString('tr-TR')}` : label }
})

const failed = computed(() => Number(liveSystem.value.queueJobsFailed) || 0)
const stats = computed(() => [
	{ key: 'tenants', label: 'İş ortağı', value: liveSystem.value.totalTenants, icon: 'building', tone: 'accent' },
	{ key: 'users', label: 'Kullanıcı', value: liveSystem.value.totalUsers, icon: 'users', tone: 'success' },
	{ key: 'orders', label: 'Sipariş', value: liveSystem.value.totalOrders, icon: 'cart', tone: 'info' },
	{ key: 'pending', label: 'Bekleyen iş', value: liveSystem.value.queueJobsPending, icon: 'clock', tone: 'warning' },
	{ key: 'failed', label: 'Başarısız iş', value: failed.value, icon: 'alert', tone: failed.value > 0 ? 'danger' : 'info', href: '/superadmin/failed-jobs' },
])

const status = computed(() => {
	if (systemFetchError.value) return { tone: 'danger', label: 'Canlı veri alınamadı' }
	if (!systemFetchedAt.value) return { tone: 'neutral', label: 'Ölçülüyor…' }
	return { tone: 'success', label: 'Sistem aktif' }
})

async function fetchSystemInfo() {
	try {
		const { data } = await window.axios.get('/superadmin/system-info')
		if (data?.system) {
			liveSystem.value = data.system
			systemFetchedAt.value = data.fetchedAt
			systemFetchError.value = false
		}
	} catch (e) {
		// Son bilinen değerler ekranda kalır ama hata açıkça gösterilir (sessizce "her şey yolunda" denmez).
		systemFetchError.value = true
	}
}

onMounted(() => {
	fetchSystemInfo()
	systemTimer = setInterval(fetchSystemInfo, SYSTEM_POLL_MS)
})
onBeforeUnmount(() => {
	if (systemTimer) clearInterval(systemTimer)
	systemTimer = null
})

const quickLinks = [
	{ icon: 'settings', title: 'Sistem ayarları', description: 'Genel, güvenlik, e-posta, ödeme ve performans', to: '/superadmin/settings' },
	{ icon: 'shield', title: 'Roller & izinler', description: 'Rol matrisi ve izin yönetimi', to: '/superadmin/settings?section=roles' },
	{ icon: 'sliders', title: 'Menü yönetimi', description: 'Sol şerit ve alt menüler', to: '/superadmin/menus' },
	{ icon: 'users', title: 'Kullanıcılar', description: 'Hesaplar, roller ve erişim', to: '/superadmin/users' },
]
</script>

<template>
	<Head title="Süper Admin · Panel" />

	<OzPageHeader title="Sistem Paneli" subtitle="Platformun canlı durumu, kullanım sayıları ve hızlı erişim">
		<template #actions>
			<OzPill :tone="status.tone">{{ status.label }}</OzPill>
			<OzButton icon="settings" @click="router.visit('/superadmin/settings')">Sistem ayarları</OzButton>
		</template>
	</OzPageHeader>
	<OzModuleTabs />

	<OzAlert v-if="systemFetchError" tone="danger" title="Canlı veri alınamadı" class="mb">
		Son bilinen değerler gösteriliyor; bağlantı düzelince ölçümler kendiliğinden güncellenir.
	</OzAlert>

	<div class="stats">
		<template v-for="s in stats" :key="s.key">
			<Link v-if="s.href" :href="s.href" class="stat-link" :aria-label="`${s.label} · ayrıntılar`">
				<OzStatTile :label="s.label" :value="s.value ?? 0" :icon="s.icon" :tone="s.tone" />
			</Link>
			<OzStatTile v-else :label="s.label" :value="s.value ?? 0" :icon="s.icon" :tone="s.tone" />
		</template>
	</div>

	<div class="grid">
		<OzCard title="Kullanım metrikleri" :subtitle="systemFetchedAt ? '5 sn’de bir güncellenir' : 'ilk ölçüm bekleniyor'" class="span-2">
			<div class="pad">
				<div class="metrics">
					<div v-for="m in metrics" :key="m.key" class="metric">
						<OzProgress :label="m.label" :value="m.value" :tone="toneOf(m.value)" />
						<span v-if="m.note" class="note">{{ m.note }}</span>
					</div>
					<div class="metric uptime">
						<span class="k">Çalışma süresi</span>
						<b>{{ liveSystem.uptime }}</b>
						<span class="note">Sunucu: {{ liveSystem.serverOs }}</span>
					</div>
				</div>
				<OzSettingRow icon="activity" title="Reverb (WebSocket)" :description="`${reverb.host}:${reverb.port}`">
					<OzPill :tone="reverb.running ? 'success' : 'danger'">{{ reverb.running ? 'Çalışıyor' : 'Kapalı' }}</OzPill>
				</OzSettingRow>
			</div>
		</OzCard>

		<OzCard title="Yazılım sürümleri" :subtitle="versionSummary?.label">
			<ul class="versions">
				<li v-for="v in versions" :key="v.key" class="version-row">
					<div class="version-head">
						<span class="version-name">{{ v.label }}</span>
						<span class="version-value">
							{{ v.value || '—' }}
							<template v-if="v.check?.updateAvailable"> → <b>{{ v.check.latest }}</b></template>
						</span>
						<OzPill v-if="v.check" :tone="VERSION_TONE[v.check.status]">{{ VERSION_LABEL[v.check.status] }}</OzPill>
					</div>
					<p v-if="v.check" class="version-note">
						<span v-if="v.check.packageSource" class="version-source">{{ v.check.packageSource }} · </span>{{ v.check.message }}
						<span v-if="v.check.eol && v.check.status === 'ok'"> Destek bitişi: {{ fmtDate(v.check.eol) }}.</span>
					</p>
					<div v-if="v.check?.updateCommand || v.check?.updateNote" class="version-howto">
						<span class="version-howto-title">Nasıl güncellenir</span>
						<div v-if="v.check.updateCommand" class="version-cmd">
							<code>{{ v.check.updateCommand }}</code>
							<OzButton size="sm" icon="copy" :aria-label="`${v.label} güncelleme komutunu kopyala`" @click="copyCommand(v.check.updateCommand)" />
						</div>
						<p v-if="v.check.updateNote" class="version-howto-note">{{ v.check.updateNote }}</p>
					</div>
				</li>
			</ul>
			<p v-if="aptListsUpdatedAt" class="version-foot">Sistem paketleri apt deposuna göre değerlendirilir · paket listeleri son yenilenme: {{ aptListsUpdatedAt }}</p>
		</OzCard>
	</div>

	<OzCard title="Hızlı erişim">
		<div class="quick">
			<OzSettingRow v-for="q in quickLinks" :key="q.title" :icon="q.icon" :title="q.title" :description="q.description">
				<OzButton size="sm" icon="chevron-right" :aria-label="`${q.title} sayfasına git`" @click="router.visit(q.to)" />
			</OzSettingRow>
		</div>
	</OzCard>
</template>

<style scoped>
.mb { margin-bottom: 14px; }
.stats { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 12px; margin-bottom: 14px; }
.stat-link { display: block; color: inherit; text-decoration: none; border-radius: var(--oz-radius); }
.stat-link:focus-visible { outline: 2px solid var(--oz-accent); outline-offset: 2px; }

.grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 14px; margin-bottom: 14px; align-items: start; }
.span-2 { grid-column: span 2; }
.pad { display: flex; flex-direction: column; gap: 16px; padding: 4px 18px 18px; }

.metrics { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 12px; }
.metric { display: flex; flex-direction: column; gap: 6px; padding: 12px 14px; border-radius: 12px; background: var(--oz-surface-2); border: 1px solid var(--oz-border-soft); }
.note { font-size: 11px; color: var(--oz-muted); font-variant-numeric: tabular-nums; }
.uptime .k { font-size: 12px; font-weight: 600; color: var(--oz-ink-soft); }
.uptime b { font-size: 15px; font-weight: 800; color: var(--oz-ink-soft); }

.versions { list-style: none; margin: 0; padding: 0 18px 12px; }
.version-row { padding: 9px 0; border-top: 1px solid var(--oz-border-soft); }
.version-row:first-child { border-top: none; }
.version-head { display: flex; align-items: center; gap: 8px; font-size: 12.5px; }
.version-name { color: var(--oz-muted); }
.version-value { margin-left: auto; font-family: ui-monospace, monospace; font-size: 12px; color: var(--oz-ink-soft); text-align: right; }
.version-value b { font-weight: 700; color: var(--oz-ink); }
.version-note { margin: 4px 0 0; font-size: 11px; line-height: 1.4; color: var(--oz-muted); }
.version-howto { margin-top: 6px; padding: 8px 10px; border-radius: 10px; background: var(--oz-surface-2); border: 1px solid var(--oz-border-soft); }
.version-howto-title { display: block; margin-bottom: 4px; font-size: 10.5px; font-weight: 700; letter-spacing: .02em; text-transform: uppercase; color: var(--oz-muted); }
.version-cmd { display: flex; align-items: center; gap: 6px; }
.version-cmd code { flex: 1; min-width: 0; overflow-x: auto; white-space: nowrap; font-family: ui-monospace, monospace; font-size: 11px; color: var(--oz-ink-soft); }
.version-howto-note { margin: 4px 0 0; font-size: 11px; line-height: 1.4; color: var(--oz-muted); }
.version-source { font-weight: 600; color: var(--oz-ink-soft); }
.version-foot { margin: 0; padding: 0 18px 14px; font-size: 11px; color: var(--oz-muted); }

.quick { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; padding: 0 18px 18px; }

@media (max-width: 1280px) { .stats { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
@media (max-width: 1040px) { .grid { grid-template-columns: minmax(0, 1fr); } .span-2 { grid-column: auto; } }
@media (max-width: 640px) {
	.stats { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
	.quick { grid-template-columns: minmax(0, 1fr); }
}
</style>
