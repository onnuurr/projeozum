<template>
	<Head title="Mimari Doktor" />
	<div class="page-architecture-doctor">
		<Breadcrumb
			:items="[
				{ label: 'Ana Sayfa', to: '/workflow', icon: 'home' },
				{ label: 'Süper Admin' },
				{ label: 'Mimari Doktor' },
			]"
		/>

		<div class="page-header">
			<div>
				<h1 class="page-title">Mimari Doktor</h1>
				<p class="page-subtitle">
					<code>php artisan architecture:doctor</code> ile üretilen, her hafta pazar günü otomatik
					yenilenen mimari denetim raporu. Salt-okuma — hiçbir kural henüz gerçek bir işlemi engellemiyor.
				</p>
			</div>
		</div>

		<div v-if="!report" class="empty-block">
			Henüz rapor üretilmemiş. <code>php artisan architecture:doctor --json</code> ile üretilir.
		</div>

		<template v-else>
			<div class="kpi-grid">
				<div class="kpi-card">
					<span class="kpi-label">Toplam Kural</span>
					<span class="kpi-value">{{ totalRules }}</span>
					<span class="kpi-hint">{{ Object.keys(categories).length }} kategori</span>
				</div>
				<div class="kpi-card">
					<span class="kpi-label">Geçti</span>
					<span class="kpi-value rate-good">{{ passedCount }}</span>
					<span class="kpi-hint">PASSED</span>
				</div>
				<div class="kpi-card">
					<span class="kpi-label">Kaldı</span>
					<span class="kpi-value" :class="failedCount > 0 ? 'rate-bad' : 'rate-good'">{{ failedCount }}</span>
					<span class="kpi-hint">FAILED</span>
				</div>
				<div class="kpi-card">
					<span class="kpi-label">Son Üretim</span>
					<span class="kpi-value kpi-value-sm">{{ formatDate(report.generated_at) }}</span>
					<span class="kpi-hint">Haftalık zamanlanmış görev</span>
				</div>
			</div>

			<div v-for="(rules, category) in rulesByCategory" :key="category" class="card">
				<div class="card-header">
					<svg class="header-icon" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
						<path d="M9 11l3 3L22 4" />
						<path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11" />
					</svg>
					<h3>{{ category }}</h3>
					<span class="hint">{{ categories[category]?.passed ?? 0 }}/{{ categories[category]?.total ?? rules.length }} geçti</span>
				</div>
				<div class="card-body">
					<div class="rows">
						<div v-for="rule in rules" :key="rule.rule_id" class="rule-block">
							<div
								class="row"
								:class="{ clickable: rule.findings.length > 0 }"
								@click="rule.findings.length > 0 && toggle(rule.rule_id)"
							>
								<span class="status-badge" :class="rule.passed ? 'rate-good' : 'rate-bad'">
									{{ rule.passed ? 'PASSED' : 'FAILED' }}
								</span>
								<span class="row-rule-id">{{ rule.rule_id }}</span>
								<span class="severity-badge" :class="severityClass(rule.severity)">{{ rule.severity }}</span>
								<span class="maturity-badge">{{ rule.lifecycle.maturity }}</span>
								<span v-if="rule.findings.length > 0" class="row-count">{{ rule.findings.length }} bulgu</span>
								<span v-if="rule.findings.length > 0" class="row-toggle">{{ expanded.has(rule.rule_id) ? '▾' : '▸' }}</span>
							</div>

							<div v-if="expanded.has(rule.rule_id)" class="findings">
								<div v-for="(finding, i) in rule.findings" :key="i" class="finding">
									<div class="finding-message">{{ finding.message }}</div>
									<div v-if="finding.file" class="finding-file">
										{{ finding.file }}<span v-if="finding.line">:{{ finding.line }}</span>
									</div>
									<div v-if="finding.suggestion" class="finding-suggestion">{{ finding.suggestion }}</div>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</template>
	</div>
</template>

<script setup>
import { computed, reactive } from 'vue'
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Breadcrumb from '@/Components/Breadcrumb.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
	report: { type: Object, default: null },
})

const expanded = reactive(new Set())

function toggle(ruleId) {
	if (expanded.has(ruleId)) {
		expanded.delete(ruleId)
	} else {
		expanded.add(ruleId)
	}
}

const rules = computed(() => props.report?.results ?? [])
const categories = computed(() => props.report?.category_summaries ?? {})
const totalRules = computed(() => rules.value.length)
const passedCount = computed(() => rules.value.filter(r => r.passed).length)
const failedCount = computed(() => totalRules.value - passedCount.value)

const rulesByCategory = computed(() => {
	const map = {}
	for (const rule of rules.value) {
		if (!map[rule.category]) map[rule.category] = []
		map[rule.category].push(rule)
	}
	return map
})

function severityClass(severity) {
	return { critical: 'rate-bad', warning: 'rate-warn', notice: 'rate-neutral' }[severity] ?? 'rate-neutral'
}

function formatDate(iso) {
	if (!iso) return '—'
	return new Date(iso).toLocaleString('tr-TR', { dateStyle: 'medium', timeStyle: 'short' })
}
</script>

<style scoped>
.page-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 20px; gap: 16px; }
.page-title { font-size: 22px; font-weight: 700; color: #1a1a2e; line-height: 1.2; }
.page-subtitle { font-size: 13px; color: #888; margin-top: 4px; max-width: 640px; }
.page-subtitle code { background: #f5f5f8; border-radius: 4px; padding: 1px 5px; font-size: 12px; }

.kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 20px; }
.kpi-card { display: flex; flex-direction: column; gap: 6px; background: #fff; border: 1px solid #ebebf0; border-radius: 16px; padding: 18px; box-shadow: 0 1px 4px rgba(0,0,0,.04); }
.kpi-label { font-size: 12px; font-weight: 600; color: #888; text-transform: uppercase; letter-spacing: .04em; }
.kpi-value { font-size: 24px; font-weight: 800; color: #1a1a2e; }
.kpi-value-sm { font-size: 15px; font-weight: 700; }
.kpi-hint { font-size: 12px; color: #999; }

.card { background: #fff; border-radius: 16px; border: 1px solid #ebebf0; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,.04); margin-bottom: 18px; }
.card-header { padding: 14px 18px; display: flex; align-items: center; gap: 10px; border-bottom: 1px solid #f0f0f5; }
.header-icon { color: rgb(var(--color-primary)); flex-shrink: 0; }
.card-header h3 { font-size: 15px; font-weight: 700; color: #1a1a2e; }
.card-header .hint { font-size: 12px; color: #aaa; margin-left: auto; }
.card-body { padding: 18px; }
.empty-block { text-align: center; color: #aaa; padding: 28px 0; font-style: italic; font-size: 13px; }
.empty-block code { background: #f5f5f8; border-radius: 4px; padding: 1px 5px; font-size: 12px; font-style: normal; }

.rows { display: flex; flex-direction: column; gap: 6px; }
.rule-block { display: flex; flex-direction: column; }
.row { display: flex; align-items: center; gap: 12px; padding: 12px 14px; border: 1px solid #f0f0f5; border-radius: 10px; background: #fafafc; flex-wrap: wrap; }
.row.clickable { cursor: pointer; }
.row.clickable:hover { background: #f2f2f7; }
.row-rule-id { font-size: 13px; font-weight: 600; color: #1a1a2e; font-family: monospace; }
.row-count { font-size: 12px; color: #999; margin-left: auto; }
.row-toggle { font-size: 12px; color: #999; }

.status-badge { font-size: 11px; font-weight: 700; padding: 3px 9px; border-radius: 20px; color: #fff; white-space: nowrap; }
.status-badge.rate-good { background: #16a34a; }
.status-badge.rate-bad { background: #dc2626; }

.severity-badge, .maturity-badge { font-size: 10.5px; font-weight: 700; padding: 2px 8px; border-radius: 20px; text-transform: uppercase; letter-spacing: .03em; white-space: nowrap; }
.severity-badge.rate-bad { background: #fee2e2; color: #b91c1c; }
.severity-badge.rate-warn { background: #fef3c7; color: #b45309; }
.severity-badge.rate-neutral { background: #f1f1f5; color: #777; }
.maturity-badge { background: #eef2ff; color: #4338ca; }

.findings { display: flex; flex-direction: column; gap: 8px; padding: 10px 14px 14px 30px; max-height: 360px; overflow-y: auto; }
.finding { border-left: 2px solid #ebebf0; padding-left: 10px; }
.finding-message { font-size: 12.5px; color: #333; }
.finding-file { font-size: 11.5px; color: #888; font-family: monospace; margin-top: 2px; }
.finding-suggestion { font-size: 11.5px; color: #4338ca; margin-top: 2px; }

.kpi-value.rate-good { color: #16a34a; }
.kpi-value.rate-bad { color: #dc2626; }

@media (max-width: 700px) {
	.page-header { flex-wrap: wrap; }
	.row { flex-wrap: wrap; }
	.row-count { margin-left: 0; }
}
</style>
