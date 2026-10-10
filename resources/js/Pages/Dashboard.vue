<script setup>
import { ref, computed } from 'vue'
import { Head, Link, usePage, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import {
	OzPageHeader, OzStatTile, OzCard, OzButton, OzPill, OzTabs, OzTable, OzLineChart, OzDonut, OzEmptyState,
	OzListItem, OzProgress, OzIcon, useOzPalette,
} from '@/Components/Oz'
import ExchangeRateTicker from '@/Components/ExchangeRateTicker.vue'

defineOptions({ layout: AppLayout })

// summary: HomeDashboardService::build() — yetkisi olmayan bölüm null gelir, o kart çizilmez.
const props = defineProps({
	summary: { type: Object, required: true },
	generatedAt: { type: String, default: '' },
	// TCMB son kurlar + önceki güne göre değişim (ExchangeRateService::latestWithChange)
	exchangeRates: { type: Array, default: () => [] },
})

const page = usePage()
const { openPalette } = useOzPalette()

const orders = computed(() => props.summary.orders)
const tenants = computed(() => props.summary.tenants)
const stock = computed(() => props.summary.stock)
const atelier = computed(() => props.summary.atelier)
const patterns = computed(() => props.summary.patterns)
const attention = computed(() => props.summary.attention ?? [])
const catalog = computed(() => props.summary.catalog ?? [])

const money = (v, digits = 0) => new Intl.NumberFormat('tr-TR', { style: 'currency', currency: 'TRY', maximumFractionDigits: digits }).format(Number(v ?? 0))

/* ── karşılama ── */
const firstName = computed(() => (page.props.auth?.user?.name ?? '').split(' ')[0])
const greeting = computed(() => {
	const h = new Date().getHours()
	if (h < 6) return 'İyi geceler'
	if (h < 12) return 'Günaydın'
	if (h < 18) return 'İyi günler'
	return 'İyi akşamlar'
})
const today = new Intl.DateTimeFormat('tr-TR', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }).format(new Date())

/* ── üst istatistikler: yetkiye göre ilk 4 uygun kutu ── */
const stats = computed(() => {
	const list = []
	if (orders.value) {
		list.push({ key: 'open', label: 'Açık Sipariş', value: orders.value.open, icon: 'cart', tone: 'accent' })
		list.push({ key: 'revenue', label: 'Bu Ay Ciro', value: money(orders.value.month_revenue), icon: 'wallet', tone: 'success' })
	}
	if (tenants.value) list.push({ key: 'tenants', label: 'Aktif İş Ortağı', value: tenants.value.active, icon: 'users', tone: 'info' })
	if (stock.value) list.push({ key: 'stock', label: 'Kritik Stok', value: stock.value.critical, icon: 'warehouse', tone: stock.value.critical ? 'danger' : 'success' })
	if (atelier.value) list.push({ key: 'atelier', label: 'Aktif İş Emri', value: atelier.value.active, icon: 'workflow', tone: 'warning' })
	if (patterns.value) list.push({ key: 'patterns', label: 'Kalıp Kütüphanesi', value: patterns.value.total, icon: 'layers', tone: 'accent' })
	return list.slice(0, 4)
})

/* ── 30 günlük sipariş eğilimi ── */
const trendMetric = ref('orders')
const trendTabs = [{ key: 'orders', label: 'Sipariş' }, { key: 'revenue', label: 'Ciro' }]
const trend = computed(() => orders.value?.trend ?? [])
const trendValues = computed(() => trend.value.map((d) => d[trendMetric.value]))
const trendLabels = computed(() => trend.value.map((d) => new Intl.DateTimeFormat('tr-TR', { day: 'numeric', month: 'short' }).format(new Date(d.date))))
const trendTotal = computed(() => trendValues.value.reduce((a, b) => a + b, 0))
const trendEmpty = computed(() => trendTotal.value === 0)

/* ── durum dağılımı ── */
const STATUS_TONE = { pending: 'warning', confirmed: 'info', preparing: 'accent', shipped: 'info', delivered: 'success', cancelled: 'danger' }
const statusSegments = computed(() => {
	if (!orders.value) return []
	const counts = orders.value.status_counts ?? {}
	return Object.entries(orders.value.statuses ?? {})
		.map(([key, label]) => ({ label, value: Number(counts[key] ?? 0), tone: STATUS_TONE[key] }))
		.filter((s) => s.value > 0)
})

/* ── son siparişler ── */
const recentColumns = [
	{ key: 'order_no', label: 'Sipariş' },
	{ key: 'tenant', label: 'İş Ortağı' },
	{ key: 'status', label: 'Durum' },
	{ key: 'total', label: 'Tutar', align: 'right', class: 'oz-cell-amount' },
]
const shortDate = (v) => (v ? new Intl.DateTimeFormat('tr-TR', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' }).format(new Date(v)) : '')

const patternApprovedPct = computed(() => (patterns.value?.total ? Math.round((patterns.value.approved / patterns.value.total) * 100) : 0))
const hasModuleCards = computed(() => !!(atelier.value || patterns.value))
</script>

<template>
	<Head title="Genel Bakış" />

	<OzPageHeader :title="`${greeting}${firstName ? `, ${firstName}` : ''}`">
		<template #subtitle>{{ today }} · işletmenin genel görünümü</template>
		<template #actions>
			<ExchangeRateTicker v-if="exchangeRates.length" :rates="exchangeRates" />
			<OzButton icon="search" class="quick-search" @click="openPalette">Hızlı Ara <kbd class="kbd">Ctrl K</kbd></OzButton>
		</template>
	</OzPageHeader>

	<div v-if="stats.length" class="stats">
		<OzStatTile v-for="s in stats" :key="s.key" :label="s.label" :value="s.value" :icon="s.icon" :tone="s.tone" />
	</div>

	<!-- Sipariş eğilimi + durum dağılımı -->
	<div v-if="orders" class="row row-trend">
		<OzCard title="Son 30 Gün" :subtitle="trendMetric === 'orders' ? `${trendTotal} sipariş` : money(trendTotal)">
			<template #actions>
				<OzTabs v-model="trendMetric" :tabs="trendTabs" variant="segment" aria-label="Grafik ölçüsü" />
			</template>
			<div class="chart">
				<OzEmptyState v-if="trendEmpty" icon="chart-line" title="Son 30 günde sipariş yok" description="Siparişler geldikçe günlük eğilim burada görünür." compact />
				<OzLineChart v-else :values="trendValues" :labels="trendLabels" :unit="trendMetric === 'revenue' ? '₺' : ''" :label-every="5" />
			</div>
		</OzCard>

		<OzCard title="Sipariş Durumları" link-text="Tümü" @link-click="router.visit('/orders')">
			<div class="donut">
				<OzEmptyState v-if="!statusSegments.length" icon="cart" title="Henüz sipariş yok" compact />
				<OzDonut v-else :segments="statusSegments" center-label="Sipariş" stacked />
			</div>
		</OzCard>
	</div>

	<!-- Son siparişler + dikkat gerektirenler -->
	<div class="row row-main" :class="{ 'is-single': !orders }">
		<OzCard v-if="orders" title="Son Siparişler" link-text="Tüm siparişler" @link-click="router.visit('/orders')">
			<OzTable :columns="recentColumns" :rows="orders.recent" row-key="id" empty-title="Henüz sipariş yok" empty-text="Bayi mağazasından ya da panelden gelen siparişler burada listelenir." min-width="460px">
				<template #cell-order_no="{ row }">
					<Link :href="`/orders/${row.id}`" class="order-link">
						<span class="mono">{{ row.order_no }}</span>
						<span class="dim small">{{ shortDate(row.created_at) }}</span>
					</Link>
				</template>
				<template #cell-tenant="{ value }">{{ value || '—' }}</template>
				<template #cell-status="{ value }">
					<OzPill :tone="STATUS_TONE[value] ?? 'neutral'">{{ orders.statuses?.[value] ?? value }}</OzPill>
				</template>
				<template #cell-total="{ value }"><span class="mono">{{ money(value, 2) }}</span></template>
			</OzTable>
		</OzCard>

		<OzCard title="Dikkat Gerektirenler" :meta="attention.length ? `${attention.length} konu` : ''">
			<OzEmptyState v-if="!attention.length" icon="success" title="Her şey yolunda" description="Bekleyen onay, kritik stok ya da geciken iş yok." compact />
			<div v-else class="attention">
				<Link v-for="a in attention" :key="a.key" :href="a.to" class="att">
					<OzListItem :title="a.title" :icon="a.icon" :tone="a.tone" />
					<span class="att-count" :class="`tone-${a.tone}`">{{ a.count }}</span>
					<OzIcon name="chevron-right" :size="14" class="att-go" />
				</Link>
			</div>
		</OzCard>
	</div>

	<!-- Modül özetleri + katalog -->
	<div class="row row-bottom">
		<OzCard v-if="atelier" title="Atölye" link-text="İş emirleri" @link-click="router.visit('/atelier/production-orders')">
			<div class="kv">
				<div class="kv-row"><span>Planlı / üretimde</span><b>{{ atelier.active }}</b></div>
				<div class="kv-row"><span>Taslak iş emri</span><b>{{ atelier.draft }}</b></div>
				<div class="kv-row" :class="{ 'is-bad': atelier.late }"><span>Termini geçen</span><b>{{ atelier.late }}</b></div>
			</div>
		</OzCard>

		<OzCard v-if="patterns" title="Kalıp Kütüphanesi" link-text="Kalıplar" @link-click="router.visit('/atelier/patterns')">
			<div class="kv">
				<OzProgress label="Onaylı kalıp oranı" :value="patternApprovedPct" tone="accent" />
				<div class="kv-row"><span>Onaylı / toplam</span><b>{{ patterns.approved }} / {{ patterns.total }}</b></div>
				<div class="kv-row" :class="{ 'is-warn': patterns.needs_work }"><span>Çıkarım bekleyen</span><b>{{ patterns.needs_work }}</b></div>
				<div class="kv-row"><span>İncelemede sayısallaştırma</span><b>{{ patterns.conversions_review }}</b></div>
			</div>
		</OzCard>

		<OzCard v-if="catalog.length" title="Sistemde" :class="{ 'is-wide': !hasModuleCards }">
			<div class="catalog">
				<Link v-for="c in catalog" :key="c.key" :href="c.to" class="cat">
					<span class="cat-ico"><OzIcon :name="c.icon" :size="15" /></span>
					<span class="cat-val">{{ c.value.toLocaleString('tr-TR') }}</span>
					<span class="cat-label">{{ c.label }}</span>
				</Link>
			</div>
		</OzCard>
	</div>

	<OzEmptyState
		v-if="!stats.length && !attention.length && !catalog.length"
		icon="lock"
		title="Görüntülenecek özet yok"
		description="Hesabınızın modül yetkileri tanımlandığında ilgili özetler burada görünür."
	/>
</template>

<style scoped>
.stats { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; margin-bottom: 14px; }
@media (max-width: 1040px) { .stats { grid-template-columns: repeat(2, minmax(0, 1fr)); } }

/* mobilde üst barın kendi arama düğmesi var */
@media (max-width: 768px) { .quick-search { display: none; } }
.kbd { margin-left: 6px; font-family: inherit; font-size: 10px; font-weight: 600; color: var(--oz-muted); background: var(--oz-surface-2); border: 1px solid var(--oz-border); border-radius: 5px; padding: 1px 5px; }
.mono { font-family: ui-monospace, monospace; font-size: 12px; }
.dim { color: var(--oz-muted); }
.small { font-size: 11px; }

.row { display: grid; gap: 16px; margin-bottom: 16px; align-items: start; }
.row-trend { grid-template-columns: minmax(0, 2fr) minmax(280px, 1fr); align-items: stretch; }
.row-main { grid-template-columns: minmax(0, 3fr) minmax(300px, 2fr); }
.row-main.is-single { grid-template-columns: 1fr; }
.row-bottom { grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); }
@media (max-width: 1100px) { .row-trend, .row-main { grid-template-columns: 1fr; } }

.row-trend :deep(.oz-card) { display: flex; flex-direction: column; }
.chart { padding: 4px 18px 18px; min-height: 220px; display: flex; flex-direction: column; justify-content: center; flex: 1; }
.donut { display: flex; justify-content: center; padding: 4px 18px 18px; min-height: 220px; }

.order-link { display: inline-flex; flex-direction: column; gap: 1px; color: var(--oz-ink-soft); text-decoration: none; font-weight: 600; }
.order-link:hover .mono { color: var(--oz-accent); }

.attention { display: flex; flex-direction: column; padding: 0 10px 10px; }
.att { position: relative; display: flex; align-items: center; gap: 8px; padding: 2px 8px; border-radius: var(--oz-radius-sm); text-decoration: none; color: inherit; transition: background .15s; }
.att:hover { background: var(--oz-surface-2); }
.att + .att { border-top: 1px solid var(--oz-border-soft); }
.att :deep(.oz-list-item) { flex: 1; min-width: 0; }
.att-count { min-width: 26px; padding: 1px 8px; border-radius: 999px; font-size: 12px; font-weight: 700; text-align: center; }
.att-count.tone-warning { background: var(--oz-warning-soft); color: var(--oz-warning); }
.att-count.tone-danger { background: var(--oz-danger-soft); color: var(--oz-danger); }
.att-count.tone-info { background: var(--oz-info-soft); color: var(--oz-info); }
.att-go { color: var(--oz-muted); flex-shrink: 0; }

.kv { display: flex; flex-direction: column; gap: 10px; padding: 0 18px 16px; }
.kv-row { display: flex; justify-content: space-between; gap: 10px; font-size: 13px; color: var(--oz-muted); }
.kv b { color: var(--oz-ink-soft); font-variant-numeric: tabular-nums; }
.kv-row.is-bad b { color: var(--oz-danger); }
.kv-row.is-warn b { color: var(--oz-warning); }

.catalog { display: grid; grid-template-columns: repeat(auto-fill, minmax(110px, 1fr)); gap: 10px; padding: 0 18px 16px; }
.cat { display: flex; flex-direction: column; gap: 2px; padding: 10px 12px; border: 1px solid var(--oz-border-soft); border-radius: var(--oz-radius-sm); background: var(--oz-surface-2); text-decoration: none; transition: border-color .15s, background .15s; }
.cat:hover { border-color: rgba(var(--oz-accent-rgb), .35); background: var(--oz-accent-soft); }
.cat-ico { color: var(--oz-accent); margin-bottom: 4px; }
.cat-val { font-size: 20px; font-weight: 800; line-height: 1.1; color: var(--oz-ink-soft); font-variant-numeric: tabular-nums; }
.cat-label { font-size: 11.5px; color: var(--oz-muted); }
</style>
