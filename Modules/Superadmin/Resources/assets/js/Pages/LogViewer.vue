<template>
	<Head title="Loglar" />
	<div class="page-log-viewer">
		<Breadcrumb
			:items="[
				{ label: 'Ana Sayfa', to: '/workflow', icon: 'home' },
				{ label: 'Süper Admin' },
				{ label: 'Sistem Logları' },
			]"
		/>

		<div class="page-header">
			<div>
				<h1 class="page-title">Sistem Logları</h1>
				<p class="page-subtitle">
					Sistem hareketleri ve hata kayıtları
				</p>
			</div>
			<div class="header-meta">
				<span class="session-badge">
					<svg width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
						<rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
						<path d="M7 11V7a5 5 0 0110 0v4" />
					</svg>
					Güvenli oturum aktif
				</span>
			</div>
		</div>

		<!-- Sekmeler -->
		<div class="tabs-bar">
			<button
				class="tab-btn"
				:class="{ active: activeTab === 'activity' }"
				@click="switchTab('activity')"
			>
				<svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
					<polyline points="22 12 18 12 15 21 9 3 6 12 2 12" />
				</svg>
				Sistem Hareketleri
				<span class="tab-count">{{ activity.total }}</span>
			</button>
			<button
				class="tab-btn"
				:class="{ active: activeTab === 'errors' }"
				@click="switchTab('errors')"
			>
				<svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
					<circle cx="12" cy="12" r="10" />
					<line x1="12" y1="8" x2="12" y2="12" />
					<line x1="12" y1="16" x2="12.01" y2="16" />
				</svg>
				Hata Logları
				<span class="tab-count tab-count-error">{{ errors.total }}</span>
			</button>
		</div>

		<!-- Filtre kartı -->
		<div class="card filter-card">
			<div class="filter-row">
				<div class="filter-group">
					<label class="filter-label">Modül</label>
					<select v-model="localFilters.module" class="filter-select">
						<option value="">Tüm Modüller</option>
						<option v-for="m in modules" :key="m" :value="m">{{ m }}</option>
					</select>
				</div>
				<div class="filter-group">
					<label class="filter-label">Seviye</label>
					<select v-model="localFilters.level" class="filter-select">
						<option value="">Tüm Seviyeler</option>
						<template v-if="activeTab === 'activity'">
							<option value="info">info</option>
							<option value="notice">notice</option>
							<option value="warning">warning</option>
						</template>
						<template v-else>
							<option value="warning">warning</option>
							<option value="error">error</option>
							<option value="critical">critical</option>
						</template>
					</select>
				</div>
				<div v-if="activeTab === 'activity'" class="filter-group">
					<label class="filter-label">Aksiyon</label>
					<input
						v-model="localFilters.action"
						type="text"
						class="filter-input"
						placeholder="örn. auth.login"
					/>
				</div>
				<div class="filter-group filter-group-wide">
					<label class="filter-label">Arama</label>
					<input
						v-model="localFilters.q"
						type="text"
						class="filter-input"
						:placeholder="activeTab === 'activity' ? 'Açıklama veya aksiyon...' : 'Mesaj veya sınıf...'"
					/>
				</div>
				<div class="filter-group">
					<label class="filter-label">Başlangıç</label>
					<input v-model="localFilters.date_from" type="date" class="filter-input" />
				</div>
				<div class="filter-group">
					<label class="filter-label">Bitiş</label>
					<input v-model="localFilters.date_to" type="date" class="filter-input" />
				</div>
				<div class="filter-actions">
					<button class="btn btn-primary btn-sm" @click="applyFilters">Filtrele</button>
					<button class="btn btn-ghost btn-sm" @click="clearFilters">Temizle</button>
				</div>
			</div>
		</div>

		<!-- ── Sistem Hareketleri sekmesi ── -->
		<div v-if="activeTab === 'activity'" class="card">
			<table class="data-table">
				<thead>
					<tr>
						<th style="width: 14%">Zaman</th>
						<th style="width: 10%">Modül</th>
						<th style="width: 16%">Aksiyon</th>
						<th>Açıklama</th>
						<th style="width: 14%">Kullanıcı</th>
						<th style="width: 11%">IP</th>
						<th style="width: 4%"></th>
					</tr>
				</thead>
				<tbody>
					<tr v-if="activity.data.length === 0">
						<td colspan="7" class="empty-row">Kayıt bulunamadı.</td>
					</tr>
					<template v-for="row in activity.data" :key="row.id">
						<tr
							class="data-row"
							:class="{ 'row-expanded': expandedActivity === row.id }"
							@click="toggleActivityRow(row.id)"
						>
							<td class="mono dim">{{ row.created_at }}</td>
							<td>
								<span v-if="row.module" class="pill pill-module">{{ row.module }}</span>
								<span v-else class="dim">—</span>
							</td>
							<td>
								<span class="pill" :class="actionPillClass(row.level)">{{ row.action }}</span>
							</td>
							<td class="desc-cell">{{ row.description }}</td>
							<td>
								<span v-if="row.causer">{{ row.causer.name }}</span>
								<span v-else-if="row.causerLabel" class="dim">{{ row.causerLabel }}</span>
								<span v-else class="dim">—</span>
							</td>
							<td class="mono dim">{{ row.ip_address ?? '—' }}</td>
							<td class="expand-cell">
								<span class="expand-icon" :class="{ open: expandedActivity === row.id }">›</span>
							</td>
						</tr>
						<tr v-if="expandedActivity === row.id" class="expand-row">
							<td colspan="7">
								<div class="expand-content">
									<div class="expand-section-title">Özellikler (properties)</div>
									<pre v-if="row.properties && Object.keys(row.properties).length" class="json-block">{{ JSON.stringify(row.properties, null, 2) }}</pre>
									<p v-else class="dim expand-empty">Özellik yok.</p>
								</div>
							</td>
						</tr>
					</template>
				</tbody>
			</table>
			<PaginationLinks :links="activity.links" />
		</div>

		<!-- ── Hata Logları sekmesi ── -->
		<div v-if="activeTab === 'errors'" class="card">
			<table class="data-table">
				<thead>
					<tr>
						<th style="width: 14%">Zaman</th>
						<th style="width: 10%">Modül</th>
						<th style="width: 9%">Seviye</th>
						<th>Mesaj</th>
						<th style="width: 20%">Dosya:Satır</th>
						<th style="width: 4%"></th>
					</tr>
				</thead>
				<tbody>
					<tr v-if="errors.data.length === 0">
						<td colspan="6" class="empty-row">Kayıt bulunamadı.</td>
					</tr>
					<template v-for="row in errors.data" :key="row.id">
						<tr
							class="data-row"
							:class="{ 'row-expanded': expandedError === row.id }"
							@click="toggleErrorRow(row.id)"
						>
							<td class="mono dim">{{ row.occurred_at ?? row.created_at }}</td>
							<td>
								<span v-if="row.module" class="pill pill-module">{{ row.module }}</span>
								<span v-else class="dim">—</span>
							</td>
							<td>
								<span class="pill" :class="levelPillClass(row.level)">{{ row.level }}</span>
							</td>
							<td class="desc-cell">{{ row.message }}</td>
							<td class="mono dim file-cell">
								<span v-if="row.file">{{ shortPath(row.file) }}:{{ row.line }}</span>
								<span v-else>—</span>
							</td>
							<td class="expand-cell">
								<span class="expand-icon" :class="{ open: expandedError === row.id }">›</span>
							</td>
						</tr>
						<tr v-if="expandedError === row.id" class="expand-row">
							<td colspan="6">
								<div class="expand-content">
									<!-- Meta bilgiler -->
									<div class="expand-meta">
										<div v-if="row.exception_class" class="meta-item">
											<span class="meta-key">Exception:</span>
											<span class="mono">{{ row.exception_class }}</span>
										</div>
										<div v-if="row.url" class="meta-item">
											<span class="meta-key">URL:</span>
											<span class="mono">{{ row.method }} {{ row.url }}</span>
										</div>
										<div v-if="row.causer" class="meta-item">
											<span class="meta-key">Kullanıcı:</span>
											<span>{{ row.causer.name }}</span>
										</div>
										<div v-if="row.ip_address" class="meta-item">
											<span class="meta-key">IP:</span>
											<span class="mono">{{ row.ip_address }}</span>
										</div>
									</div>

									<!-- Context -->
									<template v-if="row.context && Object.keys(row.context).length">
										<div class="expand-section-title">Context</div>
										<pre class="json-block">{{ JSON.stringify(row.context, null, 2) }}</pre>
									</template>

									<!-- Trace satır satır -->
									<template v-if="row.trace">
										<div class="expand-section-title">Stack Trace</div>
										<div class="trace-block">
											<div
												v-for="(frame, idx) in traceLines(row.trace)"
												:key="idx"
												class="trace-line"
											>{{ frame }}</div>
										</div>
									</template>
								</div>
							</td>
						</tr>
					</template>
				</tbody>
			</table>
			<PaginationLinks :links="errors.links" />
		</div>
	</div>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Breadcrumb from '@/Components/Breadcrumb.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
	activity:  { type: Object, required: true },
	errors:    { type: Object, required: true },
	modules:   { type: Array, default: () => [] },
	activeTab: { type: String, default: 'activity' },
	filters:   { type: Object, default: () => ({}) },
})

// ── Basit sayfalama bileşeni (inline) ──────────────────────────────────────
const PaginationLinks = {
	props: { links: { type: Array, default: () => [] } },
	template: `
		<div v-if="links && links.length > 3" class="pagination">
			<template v-for="link in links" :key="link.label">
				<a
					v-if="link.url"
					:href="link.url"
					class="page-link"
					:class="{ active: link.active }"
					v-html="link.label"
					@click.prevent="navigate(link.url)"
				/>
				<span v-else class="page-link disabled" v-html="link.label" />
			</template>
		</div>
	`,
	setup(p) {
		function navigate(url) {
			router.visit(url, { preserveState: true, preserveScroll: true })
		}
		return { navigate }
	},
}

// ── Yerel filtre durumu ────────────────────────────────────────────────────
const localFilters = reactive({
	module:    props.filters.module    ?? '',
	level:     props.filters.level     ?? '',
	action:    props.filters.action    ?? '',
	q:         props.filters.q         ?? '',
	date_from: props.filters.date_from ?? '',
	date_to:   props.filters.date_to   ?? '',
})

// ── Genişletme durumu ──────────────────────────────────────────────────────
const expandedActivity = ref(null)
const expandedError    = ref(null)

function toggleActivityRow(id) {
	expandedActivity.value = expandedActivity.value === id ? null : id
}
function toggleErrorRow(id) {
	expandedError.value = expandedError.value === id ? null : id
}

// ── Sekme geçişi ───────────────────────────────────────────────────────────
function switchTab(tab) {
	router.get('/superadmin/logs', { tab, ...buildParams() }, { preserveState: true })
}

// ── Filtre eylemleri ───────────────────────────────────────────────────────
function buildParams() {
	const p = {}
	if (localFilters.module)    p.module    = localFilters.module
	if (localFilters.level)     p.level     = localFilters.level
	if (localFilters.action)    p.action    = localFilters.action
	if (localFilters.q)         p.q         = localFilters.q
	if (localFilters.date_from) p.date_from = localFilters.date_from
	if (localFilters.date_to)   p.date_to   = localFilters.date_to
	return p
}

function applyFilters() {
	router.get('/superadmin/logs', { tab: props.activeTab, ...buildParams() }, { preserveState: true })
}

function clearFilters() {
	Object.assign(localFilters, { module: '', level: '', action: '', q: '', date_from: '', date_to: '' })
	router.get('/superadmin/logs', { tab: props.activeTab }, { preserveState: true })
}

// ── Yardımcılar ────────────────────────────────────────────────────────────
function traceLines(trace) {
	if (!trace) return []
	return trace.split('\n').filter(l => l.trim())
}

function shortPath(file) {
	if (!file) return ''
	// Proje kök yolunu kısalt (son 3 segment)
	const parts = file.replace(/\\/g, '/').split('/')
	return parts.length > 3 ? '…/' + parts.slice(-3).join('/') : file
}

function actionPillClass(level) {
	const map = {
		warning: 'pill-warning',
		notice:  'pill-notice',
		info:    'pill-info',
	}
	return map[level] ?? 'pill-info'
}

function levelPillClass(level) {
	const map = {
		critical: 'pill-critical',
		error:    'pill-error',
		warning:  'pill-warning',
	}
	return map[level] ?? 'pill-error'
}
</script>

<style scoped>
/* ── Layout ── */
.page-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 18px; gap: 16px; }
.page-title  { font-size: 22px; font-weight: 700; color: #1a1a2e; }
.page-subtitle { font-size: 13px; color: #888; margin-top: 4px; }

.session-badge {
	display: inline-flex; align-items: center; gap: 5px;
	padding: 5px 12px; border-radius: 999px;
	background: #dcfce7; color: #16a34a;
	font-size: 11.5px; font-weight: 600;
}

/* ── Sekmeler ── */
.tabs-bar { display: flex; gap: 4px; margin-bottom: 14px; }
.tab-btn {
	display: inline-flex; align-items: center; gap: 6px;
	padding: 8px 16px; border-radius: 10px;
	background: transparent; border: 1.5px solid #e8e8f0;
	font-size: 13px; font-weight: 600; color: #888;
	cursor: pointer; transition: all .15s;
}
.tab-btn:hover { border-color: #c0c0d8; color: #1a1a2e; }
.tab-btn.active { border-color: rgb(var(--color-primary)); background: rgb(var(--color-primary-soft)); color: rgb(var(--color-primary-hover)); }
.tab-count { display: inline-flex; align-items: center; justify-content: center; min-width: 20px; padding: 1px 6px; background: #f0f0f5; color: #666; border-radius: 999px; font-size: 11px; }
.tab-count-error { background: #fee2e2; color: #dc2626; }

/* ── Filtre kartı ── */
.filter-card { padding: 14px 16px; margin-bottom: 14px; }
.filter-row  { display: flex; flex-wrap: wrap; gap: 10px; align-items: flex-end; }
.filter-group { display: flex; flex-direction: column; gap: 4px; min-width: 140px; }
.filter-group-wide { flex: 1; min-width: 200px; }
.filter-label  { font-size: 11px; font-weight: 600; color: #888; text-transform: uppercase; letter-spacing: .04em; }
.filter-select, .filter-input {
	padding: 7px 10px; border: 1.5px solid #e8e8f0; border-radius: 8px;
	font-family: inherit; font-size: 13px; color: #1a1a2e; background: #fff;
	outline: none; transition: border-color .15s;
}
.filter-select:focus, .filter-input:focus { border-color: rgb(var(--color-primary)); }
.filter-actions { display: flex; gap: 6px; align-items: flex-end; padding-bottom: 1px; }
.btn-sm { padding: 7px 14px; font-size: 12.5px; }

/* ── Kart + tablo ── */
.card { background: #fff; border-radius: 16px; border: 1px solid #ebebf0; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,.04); margin-bottom: 14px; }

.data-table { width: 100%; border-collapse: separate; border-spacing: 0; }
.data-table thead tr { background: #f8f8fc; }
.data-table th { text-align: left; padding: 12px 14px; font-size: 11px; font-weight: 600; color: #aaa; border-bottom: 1px solid #f0f0f5; text-transform: uppercase; letter-spacing: .04em; }
.data-table td { padding: 10px 14px; font-size: 13px; color: #444; border-bottom: 1px solid #f5f5f8; vertical-align: middle; }
.data-table tr:last-child td { border-bottom: none; }

.data-row { cursor: pointer; transition: background .1s; }
.data-row:hover td { background: #fafafe; }
.data-row.row-expanded td { background: #f8f8fc; }
.empty-row { text-align: center !important; color: #aaa; padding: 32px 0 !important; font-style: italic; }

.mono { font-family: 'SF Mono', Menlo, Consolas, monospace; font-size: 12px; }
.dim  { color: #9ca3af; }
.desc-cell { max-width: 260px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.file-cell  { max-width: 220px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 11.5px; }

.expand-cell { text-align: center; width: 32px; }
.expand-icon { font-size: 16px; color: #aaa; display: inline-block; transition: transform .2s; user-select: none; }
.expand-icon.open { transform: rotate(90deg); color: rgb(var(--color-primary)); }

/* ── Genişletme satırı ── */
.expand-row td { padding: 0; border-bottom: 1px solid #ebebf0; background: #fafafe; }
.expand-content { padding: 16px 18px; display: flex; flex-direction: column; gap: 12px; }

.expand-section-title { font-size: 11px; font-weight: 700; color: #888; text-transform: uppercase; letter-spacing: .05em; }

.expand-meta { display: flex; flex-direction: column; gap: 6px; }
.meta-item   { display: flex; align-items: baseline; gap: 8px; font-size: 12.5px; }
.meta-key    { font-size: 11px; font-weight: 700; color: #aaa; min-width: 80px; }

.json-block {
	margin: 0; padding: 12px 14px;
	background: #1a1a2e; color: #a8d8a8;
	border-radius: 8px; font-size: 11.5px;
	font-family: 'SF Mono', Menlo, Consolas, monospace;
	overflow-x: auto; max-height: 280px; overflow-y: auto;
	white-space: pre;
}

.trace-block {
	background: #1a1a2e; border-radius: 8px;
	padding: 12px 14px; overflow-x: auto;
	max-height: 360px; overflow-y: auto;
}
.trace-line {
	font-size: 11.5px; font-family: 'SF Mono', Menlo, Consolas, monospace;
	color: #d0d0e0; padding: 2px 0; white-space: pre;
	border-bottom: 1px solid rgba(255,255,255,.04);
}
.trace-line:last-child { border-bottom: none; }

.expand-empty { font-size: 12px; color: #aaa; font-style: italic; margin: 0; }

/* ── Pills ── */
.pill { display: inline-flex; align-items: center; padding: 2px 9px; border-radius: 999px; font-size: 11px; font-weight: 600; white-space: nowrap; }
.pill-module   { background: #f0f0f5; color: #555; }
.pill-info     { background: #dbeafe; color: #1d4ed8; }
.pill-notice   { background: #e0f2fe; color: #0369a1; }
.pill-warning  { background: #fef3c7; color: #d97706; }
.pill-error    { background: #fee2e2; color: #dc2626; }
.pill-critical { background: #fce7f3; color: #be185d; }

/* ── Sayfalama ── */
.pagination { display: flex; flex-wrap: wrap; gap: 4px; padding: 12px 14px; border-top: 1px solid #f0f0f5; }
.page-link {
	display: inline-flex; align-items: center; justify-content: center;
	min-width: 32px; height: 32px; padding: 0 10px;
	border: 1.5px solid #e8e8f0; border-radius: 8px;
	font-size: 12.5px; color: #555; text-decoration: none;
	cursor: pointer; transition: all .15s; background: #fff;
}
.page-link:hover:not(.disabled) { border-color: rgb(var(--color-primary)); color: rgb(var(--color-primary)); }
.page-link.active { background: rgb(var(--color-primary)); border-color: rgb(var(--color-primary)); color: #fff; }
.page-link.disabled { color: #ccc; cursor: default; }
</style>
