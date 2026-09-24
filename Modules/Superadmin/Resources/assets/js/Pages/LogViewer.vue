<script setup>
import { ref, reactive, computed, watch } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import {
	OzPageHeader, OzModuleTabs, OzCard, OzTabs, OzInput, OzSelect, OzButton, OzPill, OzIcon, OzEmptyState, OzPagination,
} from '@/Components/Oz'

// Süper admin · Sistem logları (Oz kit). Sunucu tarafı süzme + sayfalama (LogViewerController, 50'şer).
//   Filtreler değişince kendiliğinden uygulanır (arama 400 ms gecikmeli); sekme değişince sayfa 1'e döner.
//   Tarihler sunucuda UTC ('Y-m-d H:i:s') → tarayıcının saat diliminde gösterilir.
defineOptions({ layout: AppLayout })

const props = defineProps({
	activity: { type: Object, required: true },
	errors: { type: Object, required: true },
	modules: { type: Array, default: () => [] },
	activeTab: { type: String, default: 'activity' },
	filters: { type: Object, default: () => ({}) },
})

const isErrors = computed(() => props.activeTab === 'errors')
const list = computed(() => (isErrors.value ? props.errors : props.activity))

/* ── sekmeler ── */
const tab = computed({
	get: () => props.activeTab,
	set: (t) => visit({ tab: t, page: undefined }),
})
const tabs = computed(() => [
	{ key: 'activity', label: 'Sistem hareketleri', icon: 'activity', count: props.activity.total },
	{ key: 'errors', label: 'Hata logları', icon: 'alert', count: props.errors.total },
])

/* ── filtreler ── */
const f = reactive({
	module: props.filters.module ?? '',
	level: props.filters.level ?? '',
	action: props.filters.action ?? '',
	q: props.filters.q ?? '',
	date_from: props.filters.date_from ?? '',
	date_to: props.filters.date_to ?? '',
})
const moduleOptions = computed(() => [{ value: '', label: 'Tüm modüller' }, ...props.modules.map((m) => ({ value: m, label: m }))])
const LEVELS = {
	activity: [['info', 'Bilgi'], ['notice', 'Not'], ['warning', 'Uyarı']],
	errors: [['warning', 'Uyarı'], ['error', 'Hata'], ['critical', 'Kritik']],
}
const levelOptions = computed(() => [{ value: '', label: 'Tüm seviyeler' }, ...LEVELS[props.activeTab].map(([value, label]) => ({ value, label }))])
const activeFilterCount = computed(() => Object.entries(f).filter(([k, v]) => v && (k !== 'action' || !isErrors.value)).length)

function params(extra = {}) {
	const p = { tab: props.activeTab }
	for (const [k, v] of Object.entries(f)) if (v && !(k === 'action' && isErrors.value)) p[k] = v
	return { ...p, ...extra }
}
const loading = ref(false)
// Telefonda arama dışındaki filtreler bu düğmeyle açılır
const filtersOpen = ref(false)
function visit(extra = {}) {
	const p = params(extra)
	for (const k of Object.keys(p)) if (p[k] === undefined) delete p[k]
	router.get(route('superadmin.logs'), p, {
		preserveState: true,
		preserveScroll: true,
		replace: true,
		only: ['activity', 'errors', 'activeTab', 'filters'],
		onStart: () => { loading.value = true },
		onFinish: () => { loading.value = false },
	})
}
let timer = null
watch(() => [f.q, f.action], () => { clearTimeout(timer); timer = setTimeout(() => visit({ page: undefined }), 400) })
watch(() => [f.module, f.level, f.date_from, f.date_to], () => { clearTimeout(timer); visit({ page: undefined }) })
// Seviye listesi sekmeye göre değişir; diğer sekmede olmayan seviye seçiliyse temizle
watch(() => props.activeTab, (t) => { if (f.level && !LEVELS[t].some(([v]) => v === f.level)) f.level = '' })

function clearFilters() {
	clearTimeout(timer)
	Object.assign(f, { module: '', level: '', action: '', q: '', date_from: '', date_to: '' })
}

const page = computed({
	get: () => list.value.current_page ?? 1,
	set: (n) => visit({ page: n > 1 ? n : undefined }),
})

/* ── satırlar: güne göre gruplu ── */
const parse = (s) => (s ? new Date(`${s.replace(' ', 'T')}Z`) : null)
const dayKey = (d) => d.toLocaleDateString('tr-TR', { year: 'numeric', month: '2-digit', day: '2-digit' })
function dayLabel(d) {
	const today = new Date()
	const y = new Date(); y.setDate(today.getDate() - 1)
	if (dayKey(d) === dayKey(today)) return 'Bugün'
	if (dayKey(d) === dayKey(y)) return 'Dün'
	return d.toLocaleDateString('tr-TR', { day: 'numeric', month: 'long', year: 'numeric', weekday: 'long' })
}
const groups = computed(() => {
	const out = []
	for (const row of list.value.data ?? []) {
		const d = parse(row.occurred_at ?? row.created_at)
		const key = d ? dayKey(d) : '—'
		if (!out.length || out.at(-1).key !== key) out.push({ key, label: d ? dayLabel(d) : '—', rows: [] })
		out.at(-1).rows.push({ ...row, _date: d })
	}
	return out
})
const time = (d) => (d ? d.toLocaleTimeString('tr-TR', { hour: '2-digit', minute: '2-digit', second: '2-digit' }) : '—')
const full = (d) => (d ? d.toLocaleString('tr-TR', { dateStyle: 'full', timeStyle: 'medium' }) : '—')

const open = reactive(new Set())
const toggle = (id) => (open.has(id) ? open.delete(id) : open.add(id))
watch(() => [props.activeTab, list.value.current_page], () => open.clear())

/* ── görünüm yardımcıları ── */
const LEVEL = {
	info: { tone: 'info', label: 'Bilgi', icon: 'info' },
	notice: { tone: 'neutral', label: 'Not', icon: 'info' },
	warning: { tone: 'warning', label: 'Uyarı', icon: 'warning' },
	error: { tone: 'danger', label: 'Hata', icon: 'error' },
	critical: { tone: 'danger', label: 'Kritik', icon: 'alert' },
}
const level = (l) => LEVEL[l] ?? { tone: 'neutral', label: l ?? '—', icon: 'info' }
const shortClass = (c) => (c ? c.split('\\').pop() : '')
// Mutlak proje yolunu at: /home/…/ozumserver.com.tr/vendor/x.php → vendor/x.php
const ROOT_PATH = /(?<!\S)\/\S*?\/(?=(?:app|Modules|vendor|routes|config|database|resources|bootstrap|public)\/)/g
const shortPath = (p) => (p ? p.replace(ROOT_PATH, '') : '')
const hasProps = (o) => o && typeof o === 'object' && Object.keys(o).length > 0
const json = (o) => JSON.stringify(o, null, 2)
// Yığın izi: uygulama kareleri öne, vendor kareleri sönük
const frames = (trace) => (trace ?? '').split('\n').filter((l) => l.trim()).map((l) => ({ text: shortPath(l), vendor: /vendor\/|\{main\}|\[internal/.test(l) }))

const rangeText = computed(() => (list.value.total ? `${list.value.from}–${list.value.to} / ${list.value.total.toLocaleString('tr-TR')} kayıt` : '0 kayıt'))
</script>

<template>
	<Head title="Sistem Logları · Süper Admin" />

	<OzPageHeader title="Sistem logları" subtitle="Sistem hareketleri ve hata kayıtları">
		<template #actions>
			<OzPill tone="success" title="Log erişim kapısı açık (30 dk)"><OzIcon name="lock" :size="11" /> Güvenli oturum</OzPill>
			<OzButton icon="refresh" :loading="loading" @click="visit({ page: list.current_page > 1 ? list.current_page : undefined })">Yenile</OzButton>
		</template>
	</OzPageHeader>
	<OzModuleTabs />

	<OzTabs v-model="tab" :tabs="tabs" aria-label="Log türü" class="block" />

	<OzCard class="block">
		<div class="filters">
			<OzInput v-model="f.q" icon="search" type="search" :placeholder="isErrors ? 'Mesaj ya da exception sınıfı…' : 'Açıklama ya da aksiyon…'" aria-label="Ara" class="q" />
			<OzButton icon="filter" class="more-btn" :aria-expanded="filtersOpen" aria-controls="log-filters" @click="filtersOpen = !filtersOpen">
				Filtreler<span v-if="activeFilterCount" class="count">{{ activeFilterCount }}</span>
			</OzButton>
			<div id="log-filters" class="more" :class="{ 'is-open': filtersOpen }">
				<OzSelect v-model="f.module" :options="moduleOptions" aria-label="Modül" class="sel" />
				<OzSelect v-model="f.level" :options="levelOptions" aria-label="Seviye" class="sel" />
				<OzInput v-if="!isErrors" v-model="f.action" icon="hash" placeholder="Aksiyon, ör. auth.login" aria-label="Aksiyon" class="mono-in" />
				<div class="dates">
					<OzInput v-model="f.date_from" type="date" aria-label="Başlangıç tarihi" :max="f.date_to || undefined" />
					<span class="dash">–</span>
					<OzInput v-model="f.date_to" type="date" aria-label="Bitiş tarihi" :min="f.date_from || undefined" />
				</div>
				<OzButton v-if="activeFilterCount" icon="x" @click="clearFilters">Temizle ({{ activeFilterCount }})</OzButton>
			</div>
		</div>
	</OzCard>

	<OzCard :title="isErrors ? 'Hata logları' : 'Sistem hareketleri'" :meta="rangeText" class="block" :class="{ busy: loading }">
		<OzEmptyState
			v-if="!list.data?.length"
			:icon="activeFilterCount ? 'filter' : (isErrors ? 'success' : 'activity')"
			:title="activeFilterCount ? 'Filtrelere uyan kayıt yok' : (isErrors ? 'Hata kaydı yok' : 'Kayıt yok')"
			:description="activeFilterCount ? 'Filtreleri değiştirin ya da temizleyin.' : ''"
			compact
		>
			<template v-if="activeFilterCount" #actions><OzButton size="sm" icon="x" @click="clearFilters">Filtreleri temizle</OzButton></template>
		</OzEmptyState>

		<div v-else class="log">
			<section v-for="g in groups" :key="g.key" class="day">
				<h3 class="day-head">{{ g.label }} <span>{{ g.rows.length }}</span></h3>

				<div v-for="row in g.rows" :key="row.id" class="entry" :class="[`lv-${row.level}`, { open: open.has(row.id) }]">
					<button type="button" class="entry-row" :aria-expanded="open.has(row.id)" @click="toggle(row.id)">
						<span class="lv" :title="level(row.level).label"><OzIcon :name="level(row.level).icon" :size="14" /></span>
						<span class="t" :title="full(row._date)">{{ time(row._date) }}</span>

						<template v-if="!isErrors">
							<code class="act">{{ row.action }}</code>
							<span class="desc">{{ row.description }}</span>
							<span class="who">{{ row.causer?.name ?? row.causerLabel ?? 'Sistem' }}</span>
						</template>
						<template v-else>
							<code class="act" :title="row.exception_class">{{ shortClass(row.exception_class) || '—' }}</code>
							<span class="desc">{{ row.message }}</span>
							<span class="who mono" :title="row.file">{{ row.file ? `${shortPath(row.file).split('/').pop()}:${row.line}` : '' }}</span>
						</template>

						<OzPill v-if="row.module" tone="neutral" class="mod">{{ row.module }}</OzPill>
						<OzIcon name="chevron-right" :size="15" class="chev" />
					</button>

					<div v-if="open.has(row.id)" class="detail">
						<dl>
							<div><dt>Zaman</dt><dd>{{ full(row._date) }}</dd></div>
							<div><dt>Seviye</dt><dd><OzPill :tone="level(row.level).tone">{{ level(row.level).label }}</OzPill></dd></div>
							<div v-if="row.module"><dt>Modül</dt><dd>{{ row.module }}</dd></div>
							<template v-if="!isErrors">
								<div><dt>Aksiyon</dt><dd class="mono">{{ row.action }}</dd></div>
								<div><dt>Kullanıcı</dt><dd>{{ row.causer?.name ?? row.causerLabel ?? 'Sistem' }}</dd></div>
							</template>
							<template v-else>
								<div v-if="row.exception_class" class="wide"><dt>Exception</dt><dd class="mono">{{ row.exception_class }}</dd></div>
								<div v-if="row.file" class="wide"><dt>Dosya</dt><dd class="mono">{{ shortPath(row.file) }}:{{ row.line }}</dd></div>
								<div v-if="row.url" class="wide"><dt>İstek</dt><dd class="mono">{{ row.method }} {{ row.url }}</dd></div>
								<div v-if="row.causer"><dt>Kullanıcı</dt><dd>{{ row.causer.name }}</dd></div>
							</template>
							<div v-if="row.ip_address"><dt>IP</dt><dd class="mono">{{ row.ip_address }}</dd></div>
						</dl>

						<template v-if="!isErrors">
							<p class="full-desc">{{ row.description }}</p>
							<template v-if="hasProps(row.properties)">
								<div class="label">Özellikler</div>
								<pre class="code">{{ json(row.properties) }}</pre>
							</template>
						</template>
						<template v-else>
							<p class="full-desc">{{ row.message }}</p>
							<template v-if="hasProps(row.context)">
								<div class="label">Context</div>
								<pre class="code">{{ json(row.context) }}</pre>
							</template>
							<template v-if="row.trace">
								<div class="label">Stack trace · {{ frames(row.trace).length }} kare</div>
								<div class="code trace">
									<div v-for="(fr, i) in frames(row.trace)" :key="i" :class="{ vendor: fr.vendor }">{{ fr.text }}</div>
								</div>
							</template>
						</template>
					</div>
				</div>
			</section>
		</div>

		<div v-if="list.last_page > 1" class="pager">
			<span class="range">{{ rangeText }}</span>
			<OzPagination v-model="page" :total="list.total" :page-size="list.per_page" />
		</div>
	</OzCard>
</template>

<style scoped>
.block { margin-bottom: 14px; }
.busy { opacity: .6; transition: opacity .15s; }
.mono, code { font-family: ui-monospace, monospace; }

.filters { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; padding: 14px 16px; }
.q { flex: 1 1 260px; }
.more { display: contents; }
.more-btn { display: none; }
.count { margin-left: 6px; min-width: 18px; height: 18px; padding: 0 5px; border-radius: 9px; background: var(--oz-accent); color: #fff; font-size: 10.5px; font-weight: 800; display: inline-flex; align-items: center; justify-content: center; }
.sel { width: 170px; }
.mono-in { width: 210px; }
.mono-in :deep(input) { font-family: ui-monospace, monospace; font-size: 12px; }
.dates { display: flex; align-items: center; gap: 6px; }
.dates > * { width: 150px; }
.dash { width: auto; color: var(--oz-muted); }

.log { padding: 0 16px 8px; }
.day + .day { margin-top: 6px; }
.day-head { position: sticky; top: 0; z-index: 1; margin: 0; padding: 8px 2px 6px; background: var(--oz-surface); font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: .05em; color: var(--oz-muted); display: flex; gap: 6px; }
.day-head span { color: var(--oz-muted-2); font-weight: 700; }

.entry { border-bottom: 1px solid var(--oz-border-soft); }
.entry:last-child { border-bottom: none; }
.entry.open { background: var(--oz-surface-2); border-radius: 10px; border-bottom-color: transparent; margin: 4px 0; }
.entry-row { width: 100%; display: flex; align-items: center; gap: 10px; padding: 8px 8px; border: none; background: none; font: inherit; color: inherit; text-align: left; cursor: pointer; border-radius: 10px; }
.entry-row:hover { background: var(--oz-surface-2); }
.entry-row:focus-visible { outline: 2px solid var(--oz-accent); outline-offset: -2px; }
.lv { width: 24px; height: 24px; border-radius: 7px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; background: var(--oz-info-soft); color: var(--oz-info); }
.lv-notice .lv { background: var(--oz-surface-2); color: var(--oz-muted); }
.lv-warning .lv { background: var(--oz-warning-soft); color: var(--oz-warning); }
.lv-error .lv, .lv-critical .lv { background: var(--oz-danger-soft); color: var(--oz-danger); }
.t { width: 64px; flex-shrink: 0; font-size: 12px; color: var(--oz-muted); font-variant-numeric: tabular-nums; }
.act { flex-shrink: 0; max-width: 220px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 11.5px; padding: 2px 7px; border-radius: 6px; background: var(--oz-surface-2); border: 1px solid var(--oz-border-soft); color: var(--oz-ink-soft); }
.lv-error .act, .lv-critical .act { color: var(--oz-danger); }
.desc { flex: 1; min-width: 0; font-size: 12.5px; color: var(--oz-ink-soft); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.who { flex-shrink: 0; max-width: 200px; font-size: 12px; color: var(--oz-muted); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.who.mono { font-size: 11.5px; }
.mod { flex-shrink: 0; }
.chev { color: var(--oz-muted); transition: transform .18s; flex-shrink: 0; }
.open .chev { transform: rotate(90deg); }

.detail { padding: 4px 12px 14px 42px; display: flex; flex-direction: column; gap: 10px; }
dl { margin: 0; display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 8px 16px; }
dl .wide { grid-column: 1 / -1; }
dt { font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: var(--oz-muted); }
dd { margin: 2px 0 0; font-size: 12.5px; color: var(--oz-ink-soft); word-break: break-word; }
dd.mono { font-size: 11.5px; }
.full-desc { margin: 0; font-size: 13px; line-height: 1.5; color: var(--oz-ink-soft); word-break: break-word; }
.label { font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: var(--oz-muted); }
.code { margin: 0; font-family: ui-monospace, monospace; font-size: 11px; line-height: 1.55; color: var(--oz-ink-soft); background: var(--oz-surface); border: 1px solid var(--oz-border-soft); border-radius: 8px; padding: 10px 12px; max-height: 320px; overflow: auto; white-space: pre; }
.trace > div { white-space: pre; }
.trace .vendor { color: var(--oz-muted-2); }

.pager { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 12px 16px; border-top: 1px solid var(--oz-border-soft); flex-wrap: wrap; }
.range { font-size: 12px; color: var(--oz-muted); }

@media (max-width: 900px) {
	.who, .mod { display: none; }
}
@media (max-width: 640px) {
	.filters { padding: 12px; }
	.q { flex: 1 1 0; min-width: 0; }
	.more-btn { display: inline-flex; flex-shrink: 0; }
	.more { display: none; flex-basis: 100%; flex-direction: column; gap: 10px; }
	.more.is-open { display: flex; }
	.more > * { width: 100% !important; }
	.dates > * { flex: 1; width: auto; }
	.log { padding: 0 8px 6px; }
	.entry-row { flex-wrap: wrap; row-gap: 4px; }
	.desc { flex-basis: calc(100% - 34px); order: 5; margin-left: 34px; white-space: normal; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
	.act { max-width: calc(100% - 140px); }
	.chev { margin-left: auto; }
	.detail { padding-left: 12px; }
	dl { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
</style>
