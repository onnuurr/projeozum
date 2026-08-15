<template>
	<Head title="Süper Admin · Panel" />
	<div class="page-sa-dashboard">
		<Breadcrumb
			:items="[
				{ label: 'Ana Sayfa', to: '/workflow', icon: 'home' },
				{ label: 'Süper Admin' },
			]"
		/>

		<PageHeader
			badge="Süper Admin"
			title="Sistem Paneli"
			subtitle="Platformun canlı durumu, kullanım sayıları ve hızlı erişim."
		>
			<template #actions>
				<StatusIndicator :status="systemFetchedAt ? 'online' : 'updating'" :label="systemFetchedAt ? 'Canlı' : 'Yükleniyor…'" />
				<Link href="/superadmin/settings" class="btn btn-secondary btn-sm btn-with-icon">
					<Settings :size="13" />
					Sistem Ayarları
				</Link>
			</template>
		</PageHeader>

		<!-- Kullanım sayıları -->
		<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-3 sm:gap-4">
			<StatWidget :icon="Building2" :value="fmt(liveSystem.totalTenants)" title="Kiracı (Tenant)" color="primary" />
			<StatWidget :icon="UsersRound" :value="fmt(liveSystem.totalUsers)" title="Kullanıcı" color="success" />
			<StatWidget :icon="ShoppingCart" :value="fmt(liveSystem.totalOrders)" title="Sipariş" color="primary" />
			<StatWidget :icon="Clock" :value="fmt(liveSystem.queueJobsPending)" title="Bekleyen iş" color="warning" />
			<Link href="/superadmin/failed-jobs" class="block">
				<StatWidget :icon="AlertCircle" :value="fmt(liveSystem.queueJobsFailed)" title="Başarısız iş" :color="liveSystem.queueJobsFailed > 0 ? 'danger' : 'neutral'" />
			</Link>
		</div>

		<div class="grid grid-cols-1 lg:grid-cols-3 gap-3 sm:gap-4">
			<!-- Canlı metrikler -->
			<Card title="Kullanım Metrikleri" class="lg:col-span-2" body-class="p-3 sm:p-4 md:p-6">
				<template #actions>
					<span class="text-xs text-muted" v-if="!liveSystem.liveMetrics && !systemFetchedAt">İlk ölçüm bekleniyor…</span>
					<span class="text-xs text-danger" v-else-if="systemFetchError">Canlı veri alınamadı, son bilinen değerler gösteriliyor</span>
				</template>

				<div class="grid grid-cols-[repeat(auto-fit,minmax(150px,1fr))] gap-3.5">
					<div class="bg-canvas rounded-lg p-3">
						<div class="flex items-baseline justify-between mb-2">
							<span class="text-xs text-muted">CPU</span>
							<strong class="text-[15px] font-bold text-ink">%{{ cpuPct }}</strong>
						</div>
						<ProgressBar :value="cpuPct" :color="metricColor(cpuPct)" />
					</div>
					<div class="bg-canvas rounded-lg p-3">
						<div class="flex items-baseline justify-between mb-2">
							<span class="text-xs text-muted">RAM</span>
							<strong class="text-[15px] font-bold text-ink">%{{ ramPct }}</strong>
						</div>
						<ProgressBar :value="ramPct" :color="metricColor(ramPct)" />
						<div class="text-[11px] text-muted mt-1.5">{{ liveSystem.memoryUsageMB }} / {{ liveSystem.memoryTotalMB }} MB</div>
					</div>
					<div class="bg-canvas rounded-lg p-3">
						<div class="flex items-baseline justify-between mb-2">
							<span class="text-xs text-muted">Disk</span>
							<strong class="text-[15px] font-bold text-ink">%{{ diskPct }}</strong>
						</div>
						<ProgressBar :value="diskPct" :color="metricColor(diskPct)" />
						<div class="text-[11px] text-muted mt-1.5">{{ liveSystem.diskUsageGB }} / {{ liveSystem.diskTotalGB }} GB</div>
					</div>
					<div class="bg-canvas rounded-lg p-3">
						<div class="flex items-baseline justify-between mb-2">
							<span class="text-xs text-muted">Çalışma Süresi</span>
							<strong class="text-[15px] font-bold text-ink">{{ liveSystem.uptime }}</strong>
						</div>
						<div class="text-[11px] text-muted mt-1.5">Sunucu: {{ liveSystem.serverOs }}</div>
					</div>
				</div>

				<div class="flex items-center gap-3 mt-4 p-3 rounded-lg border border-line">
					<span class="w-2 h-2 rounded-full flex-shrink-0" :class="reverbInfo.running ? 'bg-success' : 'bg-danger'"></span>
					<div class="flex-1 min-w-0">
						<div class="text-xs font-semibold text-ink">Reverb (WebSocket)</div>
						<div class="text-[11px] text-muted font-mono">{{ reverbInfo.host }}:{{ reverbInfo.port }}</div>
					</div>
					<Badge :color="reverbInfo.running ? 'success' : 'danger'" variant="tonal" :label="reverbInfo.running ? 'Çalışıyor' : 'Kapalı'" />
				</div>
			</Card>

			<!-- Yazılım sürümleri -->
			<Card title="Yazılım Sürümleri" class="lg:col-span-1" body-class="p-3 sm:p-4 md:p-6 divide-y divide-line">
				<div class="flex justify-between items-center py-2 first:pt-0 last:pb-0">
					<span class="text-xs text-muted">PHP</span>
					<span class="text-xs font-mono text-ink">{{ liveSystem.phpVersion }}</span>
				</div>
				<div class="flex justify-between items-center py-2 first:pt-0 last:pb-0">
					<span class="text-xs text-muted">Laravel</span>
					<span class="text-xs font-mono text-ink">{{ liveSystem.laravelVersion }}</span>
				</div>
				<div class="flex justify-between items-center py-2 first:pt-0 last:pb-0">
					<span class="text-xs text-muted">Inertia.js</span>
					<span class="text-xs font-mono text-ink">{{ liveSystem.inertiaVersion }}</span>
				</div>
				<div class="flex justify-between items-center py-2 first:pt-0 last:pb-0">
					<span class="text-xs text-muted">Vue.js</span>
					<span class="text-xs font-mono text-ink">{{ liveSystem.vueVersion }}</span>
				</div>
				<div class="flex justify-between items-center py-2 first:pt-0 last:pb-0">
					<span class="text-xs text-muted">Veritabanı</span>
					<span class="text-xs font-mono text-ink">{{ liveSystem.mysqlVersion }}</span>
				</div>
				<div class="flex justify-between items-center py-2 first:pt-0 last:pb-0">
					<span class="text-xs text-muted">Redis</span>
					<span class="text-xs font-mono text-ink">{{ liveSystem.redisVersion }}</span>
				</div>
				<div class="flex justify-between items-center py-2 first:pt-0 last:pb-0">
					<span class="text-xs text-muted">Web Sunucu</span>
					<span class="text-xs font-mono text-ink">{{ liveSystem.webServer }}</span>
				</div>
			</Card>
		</div>

		<!-- Hızlı erişim -->
		<Card title="Hızlı Erişim" body-class="p-3 sm:p-4 md:p-6 grid grid-cols-1 sm:grid-cols-2 gap-3">
			<QuickActionWidget
				:icon="Settings"
				title="Sistem Ayarları"
				subtitle="Genel, güvenlik, e-posta, ödeme ve performans"
				color="primary"
				@click="router.visit('/superadmin/settings')"
			/>
			<QuickActionWidget
				:icon="Lock"
				title="Roller & İzinler"
				subtitle="Rol matrisi ve izin yönetimi"
				color="primary"
				@click="router.visit('/superadmin/settings')"
			/>
		</Card>
	</div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { Settings, Building2, UsersRound, ShoppingCart, Clock, AlertCircle, Lock } from 'lucide-vue-next'
import AppLayout from '@/Layouts/AppLayout.vue'
import Breadcrumb from '@/Components/Breadcrumb.vue'
import PageHeader from '@/Components/PageHeader.vue'
import Card from '@/Components/Card.vue'
import StatWidget from '@/Components/StatWidget.vue'
import StatusIndicator from '@/Components/StatusIndicator.vue'
import ProgressBar from '@/Components/ProgressBar.vue'
import Badge from '@/Components/Badge.vue'
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
