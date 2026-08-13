<template>
	<Head title="Süper Admin · Panel" />
	<div class="page-sa-dashboard">
		<Breadcrumb
			:items="[
				{ label: 'Ana Sayfa', to: '/workflow', icon: 'home' },
				{ label: 'Süper Admin' },
			]"
		/>

		<div class="page-header">
			<div>
				<div class="sa-badge">
					<svg width="11" height="11" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
						<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
					</svg>
					Süper Admin
				</div>
				<h1 class="page-title">Sistem Paneli</h1>
				<p class="page-subtitle">
					Platformun canlı durumu, kullanım sayıları ve hızlı erişim.
				</p>
			</div>
			<div class="header-actions">
				<StatusIndicator :status="systemFetchedAt ? 'online' : 'updating'" :label="systemFetchedAt ? 'Canlı' : 'Yükleniyor…'" />
				<Link href="/superadmin/settings" class="btn btn-secondary btn-sm btn-with-icon">
					<svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
						<circle cx="12" cy="12" r="3" />
						<path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 11-2.83 2.83l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 11-2.83-2.83l.06-.06a1.65 1.65 0 00.33-1.82 1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 112.83-2.83l.06.06a1.65 1.65 0 001.82.33H9a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 112.83 2.83l-.06.06a1.65 1.65 0 00-.33 1.82V9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z" />
					</svg>
					Sistem Ayarları
				</Link>
			</div>
		</div>

		<!-- Kullanım sayıları -->
		<div class="counter-grid">
			<StatWidget :icon="Building2" :value="fmt(liveSystem.totalTenants)" title="Kiracı (Tenant)" color="primary" />
			<StatWidget :icon="UsersRound" :value="fmt(liveSystem.totalUsers)" title="Kullanıcı" color="success" />
			<StatWidget :icon="ShoppingCart" :value="fmt(liveSystem.totalOrders)" title="Sipariş" color="primary" />
			<StatWidget :icon="Clock" :value="fmt(liveSystem.queueJobsPending)" title="Bekleyen iş" color="warning" />
			<Link href="/superadmin/failed-jobs" class="counter-link">
				<StatWidget :icon="AlertCircle" :value="fmt(liveSystem.queueJobsFailed)" title="Başarısız iş" :color="liveSystem.queueJobsFailed > 0 ? 'danger' : 'neutral'" />
			</Link>
		</div>

		<div class="sa-grid">
			<!-- Canlı metrikler -->
			<Card title="Kullanım Metrikleri">
				<template #actions>
					<span class="sa-panel-sub" v-if="!liveSystem.liveMetrics && !systemFetchedAt">İlk ölçüm bekleniyor…</span>
					<span class="sa-panel-sub sa-panel-sub-error" v-else-if="systemFetchError">Canlı veri alınamadı, son bilinen değerler gösteriliyor</span>
				</template>
				<div class="metric-grid">
					<div class="metric-card">
						<div class="metric-head">
							<span class="metric-label">CPU</span>
							<strong class="metric-value">%{{ cpuPct }}</strong>
						</div>
						<ProgressBar :value="cpuPct" :color="metricColor(cpuPct)" />
					</div>
					<div class="metric-card">
						<div class="metric-head">
							<span class="metric-label">RAM</span>
							<strong class="metric-value">%{{ ramPct }}</strong>
						</div>
						<ProgressBar :value="ramPct" :color="metricColor(ramPct)" />
						<div class="metric-sub">{{ liveSystem.memoryUsageMB }} / {{ liveSystem.memoryTotalMB }} MB</div>
					</div>
					<div class="metric-card">
						<div class="metric-head">
							<span class="metric-label">Disk</span>
							<strong class="metric-value">%{{ diskPct }}</strong>
						</div>
						<ProgressBar :value="diskPct" :color="metricColor(diskPct)" />
						<div class="metric-sub">{{ liveSystem.diskUsageGB }} / {{ liveSystem.diskTotalGB }} GB</div>
					</div>
					<div class="metric-card">
						<div class="metric-head">
							<span class="metric-label">Çalışma Süresi</span>
							<strong class="metric-value">{{ liveSystem.uptime }}</strong>
						</div>
						<div class="metric-sub">Sunucu: {{ liveSystem.serverOs }}</div>
					</div>
				</div>

				<div class="service-row">
					<span class="service-dot" :class="reverbInfo.running ? 'sd-ok' : 'sd-down'"></span>
					<div class="service-info">
						<div class="service-name">Reverb (WebSocket)</div>
						<div class="service-meta">{{ reverbInfo.host }}:{{ reverbInfo.port }}</div>
					</div>
					<Badge :color="reverbInfo.running ? 'success' : 'danger'" variant="tonal" :label="reverbInfo.running ? 'Çalışıyor' : 'Kapalı'" />
				</div>
			</Card>

			<!-- Yazılım sürümleri -->
			<Card title="Yazılım Sürümleri">
				<div class="versions-grid">
					<div class="version-row"><span class="vr-label">PHP</span><span class="vr-value mono">{{ liveSystem.phpVersion }}</span></div>
					<div class="version-row"><span class="vr-label">Laravel</span><span class="vr-value mono">{{ liveSystem.laravelVersion }}</span></div>
					<div class="version-row"><span class="vr-label">Inertia.js</span><span class="vr-value mono">{{ liveSystem.inertiaVersion }}</span></div>
					<div class="version-row"><span class="vr-label">Vue.js</span><span class="vr-value mono">{{ liveSystem.vueVersion }}</span></div>
					<div class="version-row"><span class="vr-label">Veritabanı</span><span class="vr-value mono">{{ liveSystem.mysqlVersion }}</span></div>
					<div class="version-row"><span class="vr-label">Redis</span><span class="vr-value mono">{{ liveSystem.redisVersion }}</span></div>
					<div class="version-row"><span class="vr-label">Web Sunucu</span><span class="vr-value mono">{{ liveSystem.webServer }}</span></div>
				</div>
			</Card>
		</div>

		<!-- Hızlı erişim -->
		<Card title="Hızlı Erişim">
			<div class="quick-grid">
				<QuickActionWidget
					:icon="Settings"
					title="Sistem Ayarları"
					subtitle="Genel, güvenlik, e-posta, ödeme ve performans"
					color="primary"
					@click="router.visit('/superadmin/settings')"
				/>
				<QuickActionWidget
					:icon="Shield"
					title="Roller & İzinler"
					subtitle="Rol matrisi ve izin yönetimi"
					color="primary"
					@click="router.visit('/superadmin/settings')"
				/>
			</div>
		</Card>
	</div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { Building2, UsersRound, ShoppingCart, Clock, AlertCircle, Settings, Shield } from 'lucide-vue-next'
import AppLayout from '@/Layouts/AppLayout.vue'
import Breadcrumb from '@/Components/Breadcrumb.vue'
import StatWidget from '@/Components/StatWidget.vue'
import StatusIndicator from '@/Components/StatusIndicator.vue'
import ProgressBar from '@/Components/ProgressBar.vue'
import Badge from '@/Components/Badge.vue'
import Card from '@/Components/Card.vue'
import QuickActionWidget from '@/Components/QuickActionWidget.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
	system: { type: Object, required: true },
})

/* ── Canlı sistem metrikleri ── */
const liveSystem = ref({ ...props.system })
const systemFetchedAt = ref(null)
const systemFetchError = ref(false)
let systemTimer = null
const SYSTEM_POLL_MS = 5000

const cpuPct = computed(() => Number(liveSystem.value.cpuUsagePct) || 0)
const ramPct = computed(() => {
	const total = Number(liveSystem.value.memoryTotalMB) || 0
	const used = Number(liveSystem.value.memoryUsageMB) || 0
	if (total <= 0) return 0
	return Math.round((used / total) * 1000) / 10
})
const diskPct = computed(() => {
	const total = Number(liveSystem.value.diskTotalGB) || 0
	const used = Number(liveSystem.value.diskUsageGB) || 0
	if (total <= 0) return 0
	return Math.round((used / total) * 1000) / 10
})

const reverbInfo = computed(() => ({
	running: !!liveSystem.value.reverb?.running,
	host: liveSystem.value.reverb?.host ?? '127.0.0.1',
	port: liveSystem.value.reverb?.port ?? 8080,
}))

function fmt(n) {
	return Number(n || 0).toLocaleString('tr-TR')
}

function metricColor(pct) {
	if (pct < 60) return 'success'
	if (pct < 85) return 'warning'
	return 'danger'
}

async function fetchSystemInfo() {
	try {
		const { data } = await window.axios.get('/superadmin/system-info')
		if (data?.system) {
			liveSystem.value = data.system
			systemFetchedAt.value = data.fetchedAt
			systemFetchError.value = false
		}
	} catch (e) {
		// Son bilinen değerler ekranda kalır — ama artık bu bir hata olarak işaretlenir,
		// sessizce "sanki her şey yolunda" gösterilmez (bkz. sa-panel-sub-error).
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
</script>

<style scoped>
.page-sa-dashboard {
	padding: 4px 2px;
}

.page-header {
	display: flex;
	align-items: flex-start;
	justify-content: space-between;
	gap: 16px;
	margin: 14px 0 20px;
	flex-wrap: wrap;
}
.sa-badge {
	display: inline-flex;
	align-items: center;
	gap: 5px;
	font-size: 11px;
	font-weight: 600;
	color: rgb(var(--color-primary));
	background: rgb(var(--color-primary) / 0.1);
	padding: 3px 9px;
	border-radius: 999px;
	margin-bottom: 8px;
}
.page-title {
	font-size: 22px;
	font-weight: 700;
	margin: 0 0 4px;
	color: var(--text-strong, #1a1a2e);
}
.page-subtitle {
	font-size: 13px;
	color: var(--text-muted, #6b7280);
	margin: 0;
}
.header-actions {
	display: flex;
	align-items: center;
	gap: 10px;
}

/* Sayım kartları */
.counter-grid {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
	gap: 12px;
	margin-bottom: 18px;
}
.counter-link {
	text-decoration: none;
	color: inherit;
	display: block;
}

/* Paneller */
.sa-grid {
	display: grid;
	grid-template-columns: 1.4fr 1fr;
	gap: 16px;
	margin-bottom: 16px;
}
@media (max-width: 900px) { .sa-grid { grid-template-columns: 1fr; } }
.sa-panel-sub {
	font-size: 12px;
	color: var(--text-muted, #9ca3af);
}
.sa-panel-sub-error {
	color: var(--color-danger, #dc2626);
}

/* Metrikler */
.metric-grid {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
	gap: 14px;
}
.metric-card {
	background: var(--surface-2, #f9fafb);
	border-radius: 10px;
	padding: 12px 14px;
}
.metric-head {
	display: flex;
	justify-content: space-between;
	align-items: baseline;
	margin-bottom: 8px;
}
.metric-label { font-size: 12px; color: var(--text-muted, #6b7280); }
.metric-value { font-size: 15px; font-weight: 700; color: var(--text-strong, #1a1a2e); }
.metric-sub { font-size: 11px; color: var(--text-muted, #9ca3af); margin-top: 6px; }

/* Servis satırı */
.service-row {
	display: flex;
	align-items: center;
	gap: 12px;
	margin-top: 16px;
	padding: 12px 14px;
	border-radius: 10px;
	border: 1px solid var(--border, #e5e7eb);
}
.service-dot { width: 9px; height: 9px; border-radius: 50%; flex-shrink: 0; }
.sd-ok { background: #16a34a; }
.sd-down { background: #dc2626; }
.service-info { flex: 1; }
.service-name { font-size: 13px; font-weight: 600; color: var(--text-strong, #1a1a2e); }
.service-meta { font-size: 11px; color: var(--text-muted, #9ca3af); font-family: ui-monospace, monospace; }

/* Sürümler */
.versions-grid { display: flex; flex-direction: column; gap: 2px; }
.version-row {
	display: flex;
	justify-content: space-between;
	align-items: center;
	padding: 8px 2px;
	border-bottom: 1px solid var(--border, #f1f1f4);
}
.version-row:last-child { border-bottom: none; }
.vr-label { font-size: 13px; color: var(--text-muted, #6b7280); }
.vr-value { font-size: 13px; color: var(--text-strong, #1a1a2e); }
.mono { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 12px; }

/* Hızlı erişim */
.quick-grid {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
	gap: 12px;
}

@media (max-width: 640px) {
	.header-actions { width: 100%; flex-wrap: wrap; }
	.counter-grid { grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); }
	.metric-grid { grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); }
	.quick-grid { grid-template-columns: 1fr; }
}
</style>
