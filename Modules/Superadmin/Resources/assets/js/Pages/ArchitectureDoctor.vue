<script setup>
import { ref, reactive, computed, watch, onBeforeUnmount } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import {
	OzPageHeader, OzModuleTabs, OzStatStrip, OzCard, OzPill, OzButton, OzAlert, OzEmptyState, OzTabs, OzInput, OzIcon,
	useOzConfirm,
} from '@/Components/Oz'
import { useCan } from '@/composables/useCan'

// Süper admin · Mimari Doktor (Oz kit). storage/app/architecture-doctor.json raporunu gösterir.
//   Şimdi tara → POST scan (senkron artisan) · Otomatik düzelt → POST fix (kuyruk işi, ayrı branch'e commit)
//   Kuyrukta/çalışan düzeltme varken rapor + geçmiş 5 sn'de bir yenilenir.
defineOptions({ layout: AppLayout })

const props = defineProps({
	report: { type: Object, default: null },
	runs: { type: Array, default: () => [] },
})

const { can } = useCan()
const confirm = useOzConfirm()
const canManage = computed(() => can('architecture-doctor.manage'))

/* ── rapor ── */
const rules = computed(() => props.report?.results ?? [])
const categories = computed(() => props.report?.category_summaries ?? {})
const passedCount = computed(() => rules.value.filter((r) => r.passed).length)
const failedCount = computed(() => rules.value.length - passedCount.value)
const findingCount = computed(() => rules.value.reduce((t, r) => t + (r.findings?.length ?? 0), 0))

const stats = computed(() => [
	{ key: 'total', label: 'Toplam kural', value: rules.value.length, icon: 'shield', tone: 'accent' },
	{ key: 'passed', label: 'Geçti', value: passedCount.value, icon: 'success', tone: 'success' },
	{ key: 'failed', label: 'Kaldı', value: failedCount.value, icon: 'error', tone: failedCount.value ? 'danger' : 'success' },
	{ key: 'findings', label: 'Bulgu', value: findingCount.value, icon: 'warning', tone: findingCount.value ? 'warning' : 'success' },
])

const SEVERITY = {
	critical: { tone: 'danger', label: 'Kritik', rank: 0 },
	warning: { tone: 'warning', label: 'Uyarı', rank: 1 },
	notice: { tone: 'neutral', label: 'Not', rank: 2 },
}
const sev = (s) => SEVERITY[s] ?? { tone: 'neutral', label: s, rank: 3 }

/* ── süzme ── */
const filter = ref(failedCount.value ? 'failed' : 'all')
const filterTabs = computed(() => [
	{ key: 'all', label: 'Tümü', count: rules.value.length },
	{ key: 'failed', label: 'Kalanlar', count: failedCount.value },
	{ key: 'passed', label: 'Geçenler', count: passedCount.value },
])
const query = ref('')
const q = computed(() => query.value.trim().toLocaleLowerCase('tr'))
const matches = (f) => `${f.message} ${f.file ?? ''} ${f.suggestion ?? ''}`.toLocaleLowerCase('tr').includes(q.value)

// Kategori kartları: kalan kuralı olan kategoriler üstte; kart içinde kalanlar önce, sonra önem sırası
const groups = computed(() => {
	const map = new Map()
	for (const rule of rules.value) {
		if (filter.value === 'failed' && rule.passed) continue
		if (filter.value === 'passed' && !rule.passed) continue
		const findings = q.value ? rule.findings.filter(matches) : rule.findings
		if (q.value && !findings.length && !rule.rule_id.toLocaleLowerCase('tr').includes(q.value)) continue
		if (!map.has(rule.category)) map.set(rule.category, [])
		map.get(rule.category).push({ ...rule, shown: findings })
	}
	return [...map.entries()]
		.map(([name, list]) => ({
			name,
			rules: list.sort((a, b) => a.passed - b.passed || sev(a.severity).rank - sev(b.severity).rank),
			summary: categories.value[name] ?? { passed: list.filter((r) => r.passed).length, total: list.length },
		}))
		.sort((a, b) => (b.summary.total - b.summary.passed > 0) - (a.summary.total - a.summary.passed > 0))
})

const expanded = reactive(new Set())
const toggle = (id) => (expanded.has(id) ? expanded.delete(id) : expanded.add(id))
// Arama yapılınca eşleşen bulgusu olan kurallar açılır
watch(q, (v) => { if (v) for (const g of groups.value) for (const r of g.rules) if (r.shown.length) expanded.add(r.rule_id) })

// Bulguların hepsi aynı öneriyi taşıyorsa öneri bir kez, listenin üstünde gösterilir
const commonSuggestion = (rule) => {
	const s = rule.findings[0]?.suggestion
	return s && rule.findings.length > 1 && rule.findings.every((f) => f.suggestion === s) ? s : ''
}

/* ── işlemler ── */
const scanning = ref(false)
function scanNow() {
	if (scanning.value) return
	scanning.value = true
	router.post(route('superadmin.architecture-doctor.scan'), {}, { preserveScroll: true, onFinish: () => { scanning.value = false } })
}

const activeRun = computed(() => props.runs.find((r) => r.status === 'queued' || r.status === 'running') ?? null)
const fixing = ref(false)
async function fixNow() {
	if (fixing.value || activeRun.value) return
	const ok = await confirm({
		title: 'Otomatik düzeltme başlatılsın mı?',
		message: 'Agent kalan kuralları düzeltmeye çalışır ve değişiklikleri ayrı bir branch\'e commit eder; ana branch\'e push/merge yapılmaz.',
		confirmText: 'Başlat',
		icon: 'sparkles',
	})
	if (!ok) return
	fixing.value = true
	router.post(route('superadmin.architecture-doctor.fix'), {}, { preserveScroll: true, onFinish: () => { fixing.value = false } })
}

// Düzeltme sürerken sonucu beklemek için yoklama
let poll = null
watch(activeRun, (run) => {
	clearInterval(poll)
	poll = run ? setInterval(() => router.reload({ only: ['runs', 'report'], preserveScroll: true, preserveState: true }), 5000) : null
}, { immediate: true })
onBeforeUnmount(() => clearInterval(poll))

/* ── düzeltme geçmişi ── */
const RUN_STATUS = {
	queued: { tone: 'neutral', label: 'Kuyrukta', icon: 'clock' },
	running: { tone: 'info', label: 'Çalışıyor', icon: 'refresh' },
	success: { tone: 'success', label: 'Başarılı', icon: 'success' },
	failed: { tone: 'danger', label: 'Başarısız', icon: 'error' },
}
const runStatus = (s) => RUN_STATUS[s] ?? { tone: 'neutral', label: '—', icon: 'help' }
const openRuns = reactive(new Set())
const toggleRun = (id) => (openRuns.has(id) ? openRuns.delete(id) : openRuns.add(id))

function formatDate(iso) {
	if (!iso) return '—'
	return new Date(iso).toLocaleString('tr-TR', { dateStyle: 'medium', timeStyle: 'short' })
}
function formatDuration(sec) {
	if (sec == null) return ''
	return sec < 60 ? `${sec} sn` : `${Math.floor(sec / 60)} dk ${sec % 60} sn`
}
</script>

<template>
	<Head title="Mimari Doktor · Süper Admin" />

	<OzPageHeader title="Mimari Doktor">
		<template #subtitle>
			<code>architecture:doctor</code> denetim raporu · her pazar otomatik yenilenir<template v-if="report"> · son tarama {{ formatDate(report.generated_at) }}</template>
		</template>
		<template #actions>
			<OzButton icon="refresh" :loading="scanning" :disabled="scanning" @click="scanNow">Şimdi tara</OzButton>
			<OzButton v-if="canManage" variant="primary" icon="sparkles" :loading="fixing" :disabled="!!activeRun || fixing" @click="fixNow">Otomatik düzelt</OzButton>
		</template>
	</OzPageHeader>
	<OzModuleTabs />

	<OzAlert v-if="activeRun" tone="info" :title="activeRun.status === 'running' ? 'Otomatik düzeltme çalışıyor' : 'Otomatik düzeltme kuyrukta'" class="block">
		{{ activeRun.status === 'running' ? `${formatDate(activeRun.started_at)} tarihinde başladı.` : 'Kuyruk işçisi (queue:work) çalıştığında başlayacak.' }}
		Sayfa sonuç gelene kadar kendini yeniler.
	</OzAlert>

	<OzCard v-if="!report" class="block">
		<OzEmptyState icon="shield" title="Henüz rapor üretilmemiş" description="İlk raporu şimdi oluşturun ya da sunucuda php artisan architecture:doctor --json çalıştırın.">
			<template #actions>
				<OzButton variant="primary" icon="refresh" :loading="scanning" @click="scanNow">Şimdi tara</OzButton>
			</template>
		</OzEmptyState>
	</OzCard>

	<template v-else>
		<OzStatStrip :items="stats" />

		<div class="toolbar">
			<OzTabs v-model="filter" :tabs="filterTabs" variant="segment" aria-label="Kural durumu" />
			<OzInput v-model="query" icon="search" type="search" placeholder="Bulgu, dosya ya da kural ara…" aria-label="Bulgu ara" class="search" />
		</div>

		<OzCard v-if="!groups.length" class="block">
			<OzEmptyState
				:icon="q ? 'search' : 'success'"
				:title="q ? 'Eşleşen bulgu yok' : (filter === 'failed' ? 'Kalan kural yok' : 'Kural yok')"
				:description="q ? 'Aramayı değiştirin ya da filtreyi Tümü yapın.' : 'Tüm kurallar geçti.'"
				compact
			/>
		</OzCard>

		<OzCard
			v-for="g in groups"
			:key="g.name"
			:title="g.name"
			:meta="`${g.summary.passed}/${g.summary.total} geçti`"
			class="block"
		>
			<div class="rules">
				<div v-for="rule in g.rules" :key="rule.rule_id" class="rule" :class="{ open: expanded.has(rule.rule_id) }">
					<button
						type="button"
						class="rule-row"
						:disabled="!rule.findings.length"
						:aria-expanded="rule.findings.length ? expanded.has(rule.rule_id) : undefined"
						@click="toggle(rule.rule_id)"
					>
						<span class="status" :class="rule.passed ? 'ok' : 'bad'"><OzIcon :name="rule.passed ? 'success' : 'error'" :size="16" /></span>
						<span class="rule-id">{{ rule.rule_id }}</span>
						<span class="pills">
							<OzPill :tone="sev(rule.severity).tone">{{ sev(rule.severity).label }}</OzPill>
							<OzPill tone="info" :title="`Eklendi: ${rule.lifecycle?.introduced_in ?? '—'}`">{{ rule.lifecycle?.maturity }}</OzPill>
						</span>
						<span v-if="rule.findings.length" class="count">
							{{ q && rule.shown.length !== rule.findings.length ? `${rule.shown.length}/` : '' }}{{ rule.findings.length }} bulgu
						</span>
						<span v-else class="count muted">bulgu yok</span>
						<OzIcon v-if="rule.findings.length" name="chevron-right" :size="15" class="chev" />
					</button>

					<div v-if="expanded.has(rule.rule_id)" class="findings">
						<p v-if="commonSuggestion(rule)" class="common"><OzIcon name="info" :size="14" /> {{ commonSuggestion(rule) }}</p>
						<div v-for="(f, i) in rule.shown" :key="i" class="finding">
							<div class="msg">{{ f.message }}</div>
							<div v-if="f.file" class="file">{{ f.file }}<span v-if="f.line">:{{ f.line }}</span></div>
							<div v-if="f.suggestion && !commonSuggestion(rule)" class="sugg">{{ f.suggestion }}</div>
						</div>
						<p v-if="!rule.shown.length" class="none">Bu kuralda aramayla eşleşen bulgu yok.</p>
					</div>
				</div>
			</div>
		</OzCard>
	</template>

	<OzCard v-if="canManage" title="Düzeltme geçmişi" :meta="runs.length ? `son ${runs.length} çalışma` : ''" class="block">
		<OzEmptyState v-if="!runs.length" icon="sparkles" title="Henüz otomatik düzeltme çalışmadı" description="“Otomatik düzelt” ile başlatılır; değişiklikler ayrı bir branch'e commit edilir." compact />
		<div v-else class="rules">
			<div v-for="r in runs" :key="r.id" class="rule" :class="{ open: openRuns.has(r.id) }">
				<button type="button" class="rule-row" :aria-expanded="openRuns.has(r.id)" @click="toggleRun(r.id)">
					<OzPill :tone="runStatus(r.status).tone">{{ runStatus(r.status).label }}</OzPill>
					<span class="run-date">{{ formatDate(r.started_at ?? r.created_at) }}</span>
					<span v-if="r.branch_name" class="branch">{{ r.branch_name }}</span>
					<span class="count">
						<template v-if="r.rules_before && r.rules_after">{{ r.rules_before.failed }} → {{ r.rules_after.failed }} kalan</template>
						<template v-if="r.duration_seconds != null"> · {{ formatDuration(r.duration_seconds) }}</template>
					</span>
					<OzIcon name="chevron-right" :size="15" class="chev" />
				</button>
				<div v-if="openRuns.has(r.id)" class="findings">
					<OzAlert v-if="r.error_message" tone="danger" title="Hata">{{ r.error_message }}</OzAlert>
					<div v-if="r.files_changed?.length">
						<div class="label">Değişen dosyalar ({{ r.files_changed.length }})</div>
						<div v-for="f in r.files_changed" :key="f" class="file">{{ f }}</div>
					</div>
					<pre v-if="r.log_output" class="log">{{ r.log_output }}</pre>
					<p v-if="r.branch_name && r.files_changed?.length" class="sugg">
						Değişiklikler <code>{{ r.branch_name }}</code> branch'ine commit edildi, ana branch'e push/merge edilmedi —
						incelemek için <code>git log {{ r.branch_name }}</code> ya da PR açın.
					</p>
					<p v-if="!r.error_message && !r.files_changed?.length && !r.log_output && !r.branch_name" class="none">Ayrıntı yok.</p>
				</div>
			</div>
		</div>
	</OzCard>
</template>

<style scoped>
code { font-family: ui-monospace, monospace; font-size: .92em; padding: 1px 5px; border-radius: 5px; background: var(--oz-surface-2); border: 1px solid var(--oz-border-soft); }
.block { margin-bottom: 14px; }
.toolbar { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 14px; flex-wrap: wrap; }
.search { width: 320px; max-width: 100%; }

.rules { display: flex; flex-direction: column; gap: 8px; padding: 0 16px 16px; }
.rule { border: 1px solid var(--oz-border-soft); border-radius: 12px; background: var(--oz-surface); overflow: hidden; }
.rule.open { border-color: var(--oz-border); box-shadow: var(--oz-shadow-sm); }
.rule-row {
	width: 100%; display: flex; align-items: center; gap: 10px; padding: 10px 12px; border: none; background: none;
	text-align: left; font: inherit; color: inherit; cursor: pointer;
}
.rule-row:hover:not(:disabled) { background: var(--oz-surface-2); }
.rule-row:disabled { cursor: default; }
.rule-row:focus-visible { outline: 2px solid var(--oz-accent); outline-offset: -2px; border-radius: 12px; }
.status { width: 28px; height: 28px; border-radius: 8px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.status.ok { background: var(--oz-success-soft); color: var(--oz-success); }
.status.bad { background: var(--oz-danger-soft); color: var(--oz-danger); }
.rule-id { font-family: ui-monospace, monospace; font-size: 12.5px; font-weight: 700; color: var(--oz-ink-soft); min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.pills { display: flex; gap: 4px; flex-shrink: 0; }
.count { margin-left: auto; font-size: 12px; color: var(--oz-muted); white-space: nowrap; font-variant-numeric: tabular-nums; }
.count.muted { color: var(--oz-muted-2); }
.chev { color: var(--oz-muted); transition: transform .18s; flex-shrink: 0; }
.open .chev { transform: rotate(90deg); }
.run-date { font-size: 13px; font-weight: 700; color: var(--oz-ink-soft); white-space: nowrap; }
.branch { font-family: ui-monospace, monospace; font-size: 11.5px; color: var(--oz-muted); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; min-width: 0; }

.findings { display: flex; flex-direction: column; gap: 8px; padding: 4px 14px 14px 50px; max-height: 420px; overflow-y: auto; border-top: 1px solid var(--oz-border-soft); padding-top: 12px; }
.common { display: flex; gap: 6px; align-items: flex-start; margin: 0 0 2px; padding: 8px 10px; border-radius: 8px; background: var(--oz-info-soft); color: var(--oz-info); font-size: 12px; line-height: 1.45; }
.common :deep(svg) { flex-shrink: 0; margin-top: 1px; }
.finding { border-left: 2px solid var(--oz-border); padding-left: 10px; }
.msg { font-size: 12.5px; color: var(--oz-ink-soft); line-height: 1.45; }
.file { font-family: ui-monospace, monospace; font-size: 11.5px; color: var(--oz-muted); margin-top: 2px; word-break: break-all; }
.sugg { font-size: 12px; color: var(--oz-info); margin: 2px 0 0; line-height: 1.45; }
.none { margin: 0; font-size: 12px; color: var(--oz-muted); }
.label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: var(--oz-muted); margin-bottom: 4px; }
.log { margin: 0; font-family: ui-monospace, monospace; font-size: 11px; line-height: 1.5; color: var(--oz-ink-soft); background: var(--oz-surface-2); border: 1px solid var(--oz-border-soft); border-radius: 8px; padding: 10px 12px; max-height: 300px; overflow: auto; white-space: pre-wrap; word-break: break-word; }

@media (max-width: 640px) {
	.search { width: 100%; }
	.rules { padding: 0 10px 12px; }
	.rule-row { flex-wrap: wrap; row-gap: 6px; }
	.rule-id { flex: 1 1 calc(100% - 80px); }
	.pills { order: 3; }
	.count { order: 4; }
	.findings { padding-left: 14px; }
	.run-date { flex: 1; }
	.branch { order: 5; flex-basis: 100%; }
}
</style>
