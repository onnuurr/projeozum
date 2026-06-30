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
				<span class="sa-live" :class="{ 'sa-live-stale': !systemFetchedAt }">
					<span class="sa-live-dot"></span>
					{{ systemFetchedAt ? 'Canlı' : 'Yükleniyor…' }}
				</span>
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
			<div class="counter-card">
				<div class="counter-icon ci-blue">
					<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
						<path d="M3 21h18M5 21V7l8-4v18M19 21V11l-6-4" />
					</svg>
				</div>
				<div>
					<div class="counter-value">{{ fmt(liveSystem.totalTenants) }}</div>
					<div class="counter-label">Kiracı (Tenant)</div>
				</div>
			</div>
			<div class="counter-card">
				<div class="counter-icon ci-green">
					<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
						<path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2" /><circle cx="9" cy="7" r="4" />
						<path d="M23 21v-2a4 4 0 00-3-3.87" /><path d="M16 3.13a4 4 0 010 7.75" />
					</svg>
				</div>
				<div>
					<div class="counter-value">{{ fmt(liveSystem.totalUsers) }}</div>
					<div class="counter-label">Kullanıcı</div>
				</div>
			</div>
			<div class="counter-card">
				<div class="counter-icon ci-purple">
					<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
						<circle cx="9" cy="21" r="1" /><circle cx="20" cy="21" r="1" />
						<path d="M1 1h4l2.68 13.39a2 2 0 002 1.61h9.72a2 2 0 002-1.61L23 6H6" />
					</svg>
				</div>
				<div>
					<div class="counter-value">{{ fmt(liveSystem.totalOrders) }}</div>
					<div class="counter-label">Sipariş</div>
				</div>
			</div>
			<div class="counter-card">
				<div class="counter-icon ci-amber">
					<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
						<circle cx="12" cy="12" r="10" /><polyline points="12 6 12 12 16 14" />
					</svg>
				</div>
				<div>
					<div class="counter-value">{{ fmt(liveSystem.queueJobsPending) }}</div>
					<div class="counter-label">Bekleyen iş</div>
				</div>
			</div>
			<div class="counter-card" :class="{ 'counter-danger': liveSystem.queueJobsFailed > 0 }">
				<div class="counter-icon ci-red">
					<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
						<circle cx="12" cy="12" r="10" /><line x1="12" y1="8" x2="12" y2="12" /><line x1="12" y1="16" x2="12.01" y2="16" />
					</svg>
				</div>
				<div>
					<div class="counter-value">{{ fmt(liveSystem.queueJobsFailed) }}</div>
					<div class="counter-label">Başarısız iş</div>
				</div>
			</div>
		</div>

		<div class="sa-grid">
			<!-- Canlı metrikler -->
			<section class="sa-panel">
				<header class="sa-panel-head">
					<h2>Kullanım Metrikleri</h2>
					<span class="sa-panel-sub" v-if="!liveSystem.liveMetrics && !systemFetchedAt">İlk ölçüm bekleniyor…</span>
				</header>
				<div class="metric-grid">
					<div class="metric-card">
						<div class="metric-head">
							<span class="metric-label">CPU</span>
							<strong class="metric-value">%{{ cpuPct }}</strong>
						</div>
						<div class="metric-bar">
							<div class="metric-fill" :class="metricColor(cpuPct)" :style="{ width: cpuPct + '%' }"></div>
						</div>
					</div>
					<div class="metric-card">
						<div class="metric-head">
							<span class="metric-label">RAM</span>
							<strong class="metric-value">%{{ ramPct }}</strong>
						</div>
						<div class="metric-bar">
							<div class="metric-fill" :class="metricColor(ramPct)" :style="{ width: ramPct + '%' }"></div>
						</div>
						<div class="metric-sub">{{ liveSystem.memoryUsageMB }} / {{ liveSystem.memoryTotalMB }} MB</div>
					</div>
					<div class="metric-card">
						<div class="metric-head">
							<span class="metric-label">Disk</span>
							<strong class="metric-value">%{{ diskPct }}</strong>
						</div>
						<div class="metric-bar">
							<div class="metric-fill" :class="metricColor(diskPct)" :style="{ width: diskPct + '%' }"></div>
						</div>
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

				<div class="service-row" :class="reverbInfo.running ? 'service-ok' : 'service-down'">
					<span class="service-dot" :class="reverbInfo.running ? 'sd-ok' : 'sd-down'"></span>
					<div class="service-info">
						<div class="service-name">Reverb (WebSocket)</div>
						<div class="service-meta">{{ reverbInfo.host }}:{{ reverbInfo.port }}</div>
					</div>
					<span class="service-badge" :class="reverbInfo.running ? 'service-badge-ok' : 'service-badge-down'">
						{{ reverbInfo.running ? 'Çalışıyor' : 'Kapalı' }}
					</span>
				</div>
			</section>

			<!-- Yazılım sürümleri -->
			<section class="sa-panel">
				<header class="sa-panel-head">
					<h2>Yazılım Sürümleri</h2>
				</header>
				<div class="versions-grid">
					<div class="version-row"><span class="vr-label">PHP</span><span class="vr-value mono">{{ liveSystem.phpVersion }}</span></div>
					<div class="version-row"><span class="vr-label">Laravel</span><span class="vr-value mono">{{ liveSystem.laravelVersion }}</span></div>
					<div class="version-row"><span class="vr-label">Inertia.js</span><span class="vr-value mono">{{ liveSystem.inertiaVersion }}</span></div>
					<div class="version-row"><span class="vr-label">Vue.js</span><span class="vr-value mono">{{ liveSystem.vueVersion }}</span></div>
					<div class="version-row"><span class="vr-label">Veritabanı</span><span class="vr-value mono">{{ liveSystem.mysqlVersion }}</span></div>
					<div class="version-row"><span class="vr-label">Redis</span><span class="vr-value mono">{{ liveSystem.redisVersion }}</span></div>
					<div class="version-row"><span class="vr-label">Web Sunucu</span><span class="vr-value mono">{{ liveSystem.webServer }}</span></div>
				</div>
			</section>
		</div>

		<!-- Hızlı erişim -->
		<section class="sa-panel">
			<header class="sa-panel-head">
				<h2>Hızlı Erişim</h2>
			</header>
			<div class="quick-grid">
				<Link href="/superadmin/settings" class="quick-card">
					<div class="quick-icon ci-blue">
						<svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3" /><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 11-2.83 2.83l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 11-2.83-2.83l.06-.06a1.65 1.65 0 00.33-1.82 1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 112.83-2.83l.06.06a1.65 1.65 0 001.82.33H9a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 112.83 2.83l-.06.06a1.65 1.65 0 00-.33 1.82V9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z" /></svg>
					</div>
					<div class="quick-text">
						<div class="quick-title">Sistem Ayarları</div>
						<div class="quick-sub">Genel, güvenlik, e-posta, ödeme ve performans</div>
					</div>
				</Link>
				<Link href="/superadmin/settings" class="quick-card">
					<div class="quick-icon ci-purple">
						<svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" /></svg>
					</div>
					<div class="quick-text">
						<div class="quick-title">Roller & İzinler</div>
						<div class="quick-sub">Rol matrisi ve izin yönetimi</div>
					</div>
				</Link>
			</div>
		</section>
	</div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Breadcrumb from '@/Components/Breadcrumb.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
	system: { type: Object, required: true },
})

/* ── Canlı sistem metrikleri ── */
const liveSystem = ref({ ...props.system })
const systemFetchedAt = ref(null)
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
	if (pct < 60) return 'mb-good'
	if (pct < 85) return 'mb-meh'
	return 'mb-bad'
}

async function fetchSystemInfo() {
	try {
		const { data } = await window.axios.get('/superadmin/system-info')
		if (data?.system) {
			liveSystem.value = data.system
			systemFetchedAt.value = data.fetchedAt
		}
	} catch (e) {
		/* sessiz geç — bir sonraki polling yine dener */
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
.sa-live {
	display: inline-flex;
	align-items: center;
	gap: 6px;
	font-size: 12px;
	font-weight: 600;
	color: #16a34a;
}
.sa-live-stale { color: #9ca3af; }
.sa-live-dot {
	width: 8px;
	height: 8px;
	border-radius: 50%;
	background: currentColor;
	box-shadow: 0 0 0 3px rgba(22, 163, 74, 0.15);
}

/* Sayım kartları */
.counter-grid {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
	gap: 12px;
	margin-bottom: 18px;
}
.counter-card {
	display: flex;
	align-items: center;
	gap: 12px;
	background: var(--surface, #fff);
	border: 1px solid var(--border, #e5e7eb);
	border-radius: 12px;
	padding: 14px 16px;
}
.counter-card.counter-danger {
	border-color: #fca5a5;
	background: rgba(220, 38, 38, 0.04);
}
.counter-icon {
	width: 38px;
	height: 38px;
	border-radius: 10px;
	display: grid;
	place-items: center;
	flex-shrink: 0;
}
.ci-blue { background: rgb(var(--color-primary) / 0.12); color: rgb(var(--color-primary)); }
.ci-green { background: rgba(22, 163, 74, 0.12); color: #16a34a; }
.ci-purple { background: rgb(var(--color-primary) / 0.12); color: rgb(var(--color-primary)); }
.ci-amber { background: rgba(202, 138, 4, 0.14); color: #ca8a04; }
.ci-red { background: rgba(220, 38, 38, 0.12); color: #dc2626; }
.counter-value {
	font-size: 20px;
	font-weight: 700;
	line-height: 1.1;
	color: var(--text-strong, #1a1a2e);
}
.counter-label {
	font-size: 12px;
	color: var(--text-muted, #6b7280);
	margin-top: 2px;
}

/* Paneller */
.sa-grid {
	display: grid;
	grid-template-columns: 1.4fr 1fr;
	gap: 16px;
	margin-bottom: 16px;
}
@media (max-width: 900px) { .sa-grid { grid-template-columns: 1fr; } }
.sa-panel {
	background: var(--surface, #fff);
	border: 1px solid var(--border, #e5e7eb);
	border-radius: 12px;
	padding: 18px 20px;
	margin-bottom: 16px;
}
.sa-panel-head {
	display: flex;
	align-items: baseline;
	justify-content: space-between;
	margin-bottom: 14px;
}
.sa-panel-head h2 {
	font-size: 15px;
	font-weight: 600;
	margin: 0;
	color: var(--text-strong, #1a1a2e);
}
.sa-panel-sub {
	font-size: 12px;
	color: var(--text-muted, #9ca3af);
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
.metric-bar {
	height: 6px;
	border-radius: 999px;
	background: var(--border, #e5e7eb);
	overflow: hidden;
}
.metric-fill { height: 100%; border-radius: 999px; transition: width 0.4s ease; }
.mb-good { background: #16a34a; }
.mb-meh { background: #ca8a04; }
.mb-bad { background: #dc2626; }
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
.service-row.service-ok { border-color: rgba(22, 163, 74, 0.3); background: rgba(22, 163, 74, 0.04); }
.service-row.service-down { border-color: rgba(220, 38, 38, 0.3); background: rgba(220, 38, 38, 0.04); }
.service-dot { width: 9px; height: 9px; border-radius: 50%; flex-shrink: 0; }
.sd-ok { background: #16a34a; }
.sd-down { background: #dc2626; }
.service-info { flex: 1; }
.service-name { font-size: 13px; font-weight: 600; color: var(--text-strong, #1a1a2e); }
.service-meta { font-size: 11px; color: var(--text-muted, #9ca3af); font-family: ui-monospace, monospace; }
.service-badge { font-size: 11px; font-weight: 600; padding: 3px 9px; border-radius: 999px; }
.service-badge-ok { color: #16a34a; background: rgba(22, 163, 74, 0.12); }
.service-badge-down { color: #dc2626; background: rgba(220, 38, 38, 0.12); }

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
.quick-card {
	display: flex;
	align-items: center;
	gap: 12px;
	padding: 14px 16px;
	border: 1px solid var(--border, #e5e7eb);
	border-radius: 10px;
	text-decoration: none;
	transition: border-color 0.15s ease, transform 0.15s ease;
}
.quick-card:hover { border-color: rgb(var(--color-primary)); transform: translateY(-1px); }
.quick-icon {
	width: 40px;
	height: 40px;
	border-radius: 10px;
	display: grid;
	place-items: center;
	flex-shrink: 0;
}
.quick-title { font-size: 14px; font-weight: 600; color: var(--text-strong, #1a1a2e); }
.quick-sub { font-size: 12px; color: var(--text-muted, #6b7280); margin-top: 2px; }
</style>
