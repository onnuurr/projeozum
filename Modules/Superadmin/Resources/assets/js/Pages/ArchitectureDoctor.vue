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

		<PageHeader title="Mimari Doktor">
			<template #subtitle>
				<code>php artisan architecture:doctor</code> ile üretilen, her hafta pazar günü otomatik
				yenilenen mimari denetim raporu. Hiçbir kural henüz gerçek bir işlemi engellemiyor.
			</template>
			<template #actions>
				<Button variant="ghost" size="sm" :disabled="scanning" :loading="scanning" @click="scanNow">
					Şimdi Tara
				</Button>
				<Button v-if="can('architecture-doctor.manage')" variant="primary" size="sm" :disabled="hasActiveFixRun" :loading="fixing" @click="fixNow">
					Otomatik Düzelt
				</Button>
			</template>
		</PageHeader>

		<EmptyState v-if="!report" :icon="FileQuestion" title="Henüz rapor üretilmemiş.">
			<p class="text-2xs text-muted empty-hint"><code>php artisan architecture:doctor --json</code> ile üretilir.</p>
		</EmptyState>

		<template v-else>
			<div class="kpi-grid">
				<StatWidget :icon="ListChecks" :value="totalRules" title="Toplam Kural" color="primary" />
				<StatWidget :icon="CheckCircle2" :value="passedCount" title="Geçti" color="success" />
				<StatWidget :icon="XCircle" :value="failedCount" title="Kaldı" :color="failedCount > 0 ? 'danger' : 'success'" />
				<StatWidget :icon="CalendarClock" :value="formatDate(report.generated_at)" title="Son Üretim" color="neutral" />
			</div>

			<Card v-for="(rules, category) in rulesByCategory" :key="category" :title="category" class="section-card">
				<template #actions>
					<span class="hint">{{ categories[category]?.passed ?? 0 }}/{{ categories[category]?.total ?? rules.length }} geçti</span>
				</template>
				<div class="rows">
					<div v-for="rule in rules" :key="rule.rule_id" class="rule-block">
						<div
							class="row"
							:class="{ clickable: rule.findings.length > 0 }"
							@click="rule.findings.length > 0 && toggle(rule.rule_id)"
						>
							<Badge :color="rule.passed ? 'success' : 'danger'" :label="rule.passed ? 'PASSED' : 'FAILED'" />
							<span class="row-rule-id">{{ rule.rule_id }}</span>
							<Badge :color="severityColor(rule.severity)" variant="tonal" :label="rule.severity" />
							<Badge color="info" variant="tonal" :label="rule.lifecycle.maturity" />
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
			</Card>
		</template>

		<Card v-if="can('architecture-doctor.manage')" title="Düzeltme Geçmişi">
			<template #actions>
				<span class="hint">Son {{ runs.length }} çalışma</span>
			</template>
			<EmptyState v-if="runs.length === 0" :icon="Wrench" title='Henüz otomatik düzeltme çalışmadı.' hint='"Otomatik Düzelt" butonuyla tetiklenir.' />
			<div v-else class="rows">
				<div v-for="r in runs" :key="r.id" class="rule-block">
					<div class="row clickable" @click="toggleRun(r.id)">
						<Badge :color="fixStatusColor(r.status)" :label="fixStatusLabel(r.status)" />
						<span class="row-date">{{ formatDate(r.started_at) }}</span>
						<span v-if="r.branch_name" class="row-branch">{{ r.branch_name }}</span>
						<span v-if="r.rules_before && r.rules_after" class="row-count">
							{{ r.rules_before.failed }} → {{ r.rules_after.failed }} FAILED
						</span>
						<span class="row-toggle">{{ expandedRuns.has(r.id) ? '▾' : '▸' }}</span>
					</div>
					<div v-if="expandedRuns.has(r.id)" class="findings">
						<Alert v-if="r.error_message" variant="error" :message="r.error_message" />
						<div v-if="r.files_changed && r.files_changed.length" class="finding-file">
							Değişen dosyalar: {{ r.files_changed.join(', ') }}
						</div>
						<pre v-if="r.log_output" class="log-output">{{ r.log_output }}</pre>
						<div v-if="r.branch_name" class="finding-suggestion">
							Bu değişiklikler <code>{{ r.branch_name }}</code> branch'ine commit'lendi, ana branch'e otomatik
							push/merge edilmedi — incelemek için <code>git log {{ r.branch_name }}</code> / PR açın.
						</div>
					</div>
				</div>
			</div>
		</Card>
	</div>
</template>

<script setup>
import { computed, reactive, ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import { FileQuestion, Wrench, ListChecks, CheckCircle2, XCircle, CalendarClock } from 'lucide-vue-next'
import AppLayout from '@/Layouts/AppLayout.vue'
import Breadcrumb from '@/Components/Breadcrumb.vue'
import Badge from '@/Components/Badge.vue'
import Alert from '@/Components/Alert.vue'
import Card from '@/Components/Card.vue'
import PageHeader from '@/Components/PageHeader.vue'
import EmptyState from '@/Components/EmptyState.vue'
import StatWidget from '@/Components/StatWidget.vue'
import Button from '@/Components/Button.vue'
import { useCan } from '@/composables/useCan'

defineOptions({ layout: AppLayout })

const { can } = useCan()

const props = defineProps({
	report: { type: Object, default: null },
	runs: { type: Array, default: () => [] },
})

const expanded = reactive(new Set())

function toggle(ruleId) {
	if (expanded.has(ruleId)) {
		expanded.delete(ruleId)
	} else {
		expanded.add(ruleId)
	}
}

const expandedRuns = reactive(new Set())

function toggleRun(runId) {
	if (expandedRuns.has(runId)) {
		expandedRuns.delete(runId)
	} else {
		expandedRuns.add(runId)
	}
}

const hasActiveFixRun = computed(() => props.runs.some(r => r.status === 'queued' || r.status === 'running'))

const scanning = ref(false)
function scanNow() {
	if (scanning.value) return
	scanning.value = true
	router.post('/superadmin/architecture-doctor/scan', {}, {
		preserveScroll: true,
		onFinish: () => { scanning.value = false },
	})
}

const fixing = ref(false)
function fixNow() {
	if (fixing.value || hasActiveFixRun.value) return
	fixing.value = true
	router.post('/superadmin/architecture-doctor/fix', {}, {
		preserveScroll: true,
		onFinish: () => { fixing.value = false },
	})
}

function fixStatusLabel(status) {
	return { queued: 'Kuyrukta', running: 'Çalışıyor', success: 'Başarılı', failed: 'Başarısız' }[status] ?? '—'
}

function fixStatusColor(status) {
	return { queued: 'neutral', running: 'warning', success: 'success', failed: 'danger' }[status] ?? 'neutral'
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

function severityColor(severity) {
	return { critical: 'danger', warning: 'warning', notice: 'neutral' }[severity] ?? 'neutral'
}

function formatDate(iso) {
	if (!iso) return '—'
	return new Date(iso).toLocaleString('tr-TR', { dateStyle: 'medium', timeStyle: 'short' })
}
</script>

<style scoped>
.page-subtitle code { background: var(--color-canvas); border-radius: 4px; padding: 1px 5px; font-size: 12px; }

.kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 20px; }

.section-card { margin-bottom: 18px; }
.hint { font-size: 12px; color: var(--color-muted); white-space: nowrap; }
.empty-hint code { background: var(--color-canvas); border-radius: 4px; padding: 1px 5px; font-size: 12px; }

.rows { display: flex; flex-direction: column; gap: 6px; }
.rule-block { display: flex; flex-direction: column; }
.row { display: flex; align-items: center; gap: 12px; padding: 12px 14px; border: 1px solid var(--color-outline-variant); border-radius: 10px; background: var(--color-surface-container-low); flex-wrap: wrap; }
.row.clickable { cursor: pointer; }
.row.clickable:hover { background: color-mix(in srgb, var(--color-outline-variant) 50%, transparent); }
.row-rule-id { font-size: 13px; font-weight: 600; color: var(--color-ink); font-family: monospace; }
.row-count { font-size: 12px; color: var(--color-muted); margin-left: auto; }
.row-toggle { font-size: 12px; color: var(--color-muted); }
.row-date { font-size: 13px; font-weight: 600; color: var(--color-ink); }
.row-branch { font-size: 12px; color: var(--color-muted); font-family: monospace; }

.findings { display: flex; flex-direction: column; gap: 8px; padding: 10px 14px 14px 30px; max-height: 360px; overflow-y: auto; }
.finding { border-left: 2px solid var(--color-outline-variant); padding-left: 10px; }
.finding-message { font-size: 12.5px; color: var(--color-ink); }
.finding-file { font-size: 11.5px; color: var(--color-muted); font-family: monospace; margin-top: 2px; }
.finding-suggestion { font-size: 11.5px; color: var(--color-info); margin-top: 2px; }
.log-output { font-size: 11px; color: var(--color-ink); background: var(--color-canvas); border-radius: 8px; padding: 10px 12px; max-height: 300px; overflow: auto; white-space: pre-wrap; word-break: break-word; }

@media (max-width: 700px) {
	.row { flex-wrap: wrap; }
	.row-count { margin-left: 0; }
}
</style>
