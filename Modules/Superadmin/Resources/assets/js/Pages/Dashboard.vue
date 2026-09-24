<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
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

const versions = computed(() => [
	['PHP', liveSystem.value.phpVersion],
	['Laravel', liveSystem.value.laravelVersion],
	['Inertia.js', liveSystem.value.inertiaVersion],
	['Vue.js', liveSystem.value.vueVersion],
	['Veritabanı', liveSystem.value.mysqlVersion],
	['Redis', liveSystem.value.redisVersion],
	['Web sunucu', liveSystem.value.webServer],
])

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

		<OzCard title="Yazılım sürümleri">
			<dl class="versions">
				<template v-for="[k, v] in versions" :key="k">
					<dt>{{ k }}</dt>
					<dd>{{ v || '—' }}</dd>
				</template>
			</dl>
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

.versions { display: grid; grid-template-columns: auto 1fr; margin: 0; padding: 0 18px 12px; }
.versions dt, .versions dd { padding: 9px 0; border-top: 1px solid var(--oz-border-soft); font-size: 12.5px; }
.versions dt:first-of-type, .versions dd:first-of-type { border-top: none; }
.versions dt { color: var(--oz-muted); }
.versions dd { margin: 0; text-align: right; font-family: ui-monospace, monospace; font-size: 12px; color: var(--oz-ink-soft); }

.quick { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; padding: 0 18px 18px; }

@media (max-width: 1280px) { .stats { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
@media (max-width: 1040px) { .grid { grid-template-columns: minmax(0, 1fr); } .span-2 { grid-column: auto; } }
@media (max-width: 640px) {
	.stats { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
	.quick { grid-template-columns: minmax(0, 1fr); }
}
</style>
