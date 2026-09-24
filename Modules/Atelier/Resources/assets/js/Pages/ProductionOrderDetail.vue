<script setup>
import { ref, reactive, computed, inject } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import {
	OzPageHeader, OzModuleTabs, OzBreadcrumbs, OzStatTile, OzCard, OzButton, OzPill, OzProgress,
	OzEmptyState, OzInput, OzSelect, useOzConfirm,
} from '@/Components/Oz'
import { ORDER_STATUS, ORDER_TONE, formatMoney } from '../atelierNav'
import { useCan } from '@/composables/useCan'

defineOptions({ layout: AppLayout })

const props = defineProps({
	order: { type: Object, required: true },
	fasonSuppliers: { type: Array, default: () => [] },
})

const { can } = useCan()
const canManage = computed(() => can('atelier.production.manage'))
const showToast = inject('showToast', null)
const confirm = useOzConfirm()

const STEP_STATUS = { pending: 'Bekliyor', in_progress: 'Devam', done: 'Bitti' }
const STEP_TONE = { pending: 'neutral', in_progress: 'accent', done: 'success' }
const stepStatusOptions = Object.entries(STEP_STATUS).map(([value, label]) => ({ value, label }))
const fasonOptions = computed(() => props.fasonSuppliers.map((f) => ({ value: f.id, label: f.name })))

const isOpen = computed(() => !['completed', 'cancelled'].includes(props.order.status))
const producedPct = computed(() => props.order.plannedQty ? Math.round((props.order.producedQty / props.order.plannedQty) * 100) : 0)
const doneSteps = computed(() => props.order.steps.filter((s) => s.status === 'done').length)

const stats = computed(() => [
	{ key: 'material', label: 'Malzeme', value: formatMoney(props.order.materialCost), icon: 'package', tone: 'info' },
	{ key: 'fason', label: 'Fason', value: formatMoney(props.order.fasonCost), icon: 'factory', tone: 'warning' },
	{ key: 'labor', label: 'İşçilik', value: formatMoney(props.order.laborCost), icon: 'scissors', tone: 'success' },
	{ key: 'total', label: 'Toplam Maliyet', value: formatMoney(props.order.totalCost), icon: 'wallet', tone: 'accent' },
])

const breadcrumbs = computed(() => [
	{ label: 'Atölye', to: '/atelier' },
	{ label: 'İş Emirleri', to: '/atelier/production-orders' },
	{ label: props.order.code },
])

/* ── düzenlenebilir satırlar (sunucu yanıtıyla sayfa yeniden kurulur) ── */
// Adımın mevcut fasoncusu yalnızca adıyla geliyor; seçim kutusunda gösterilebilsin diye listeden eşlenir
const supplierIdByName = (name) => props.fasonSuppliers.find((f) => f.name === name)?.id ?? null
const stepForms = reactive(Object.fromEntries(props.order.steps.map((s) => [s.id, {
	status: s.status, input_qty: s.inputQty, output_qty: s.outputQty, scrap_qty: s.scrapQty,
	fason_supplier_id: supplierIdByName(s.fasonSupplier), unit_cost: s.unitCost, note: '',
}])))
const itemForms = reactive(Object.fromEntries(props.order.items.map((i) => [i.id, {
	produced_qty: i.producedQty, scrap_qty: i.scrapQty,
}])))
const itemsTotal = computed(() => Object.values(itemForms).reduce((s, f) => s + (Number(f.produced_qty) || 0), 0))

const busy = ref('')
const errorToast = (title) => (errs) => showToast?.({ type: 'error', title, message: Object.values(errs)[0] || 'Sunucu hatası.' })

function saveStep(id) {
	busy.value = `step-${id}`
	router.put(`/atelier/production-orders/${props.order.id}/steps/${id}`, stepForms[id], {
		preserveScroll: true,
		onError: errorToast('Adım kaydedilemedi'),
		onFinish: () => { busy.value = '' },
	})
}
function saveItems() {
	busy.value = 'items'
	const items = props.order.items.map((i) => ({ id: i.id, produced_qty: itemForms[i.id].produced_qty, scrap_qty: itemForms[i.id].scrap_qty }))
	router.put(`/atelier/production-orders/${props.order.id}/items`, { items }, {
		preserveScroll: true,
		onError: errorToast('Miktarlar kaydedilemedi'),
		onFinish: () => { busy.value = '' },
	})
}

/* ── durum geçişleri ── */
async function plan() {
	const ok = await confirm({ title: 'İş emri planlansın mı?', message: 'Reçeteye göre gereken hammadde stoktan düşülecek.', tone: 'warning', confirmText: 'Planla' })
	if (!ok) return
	busy.value = 'plan'
	router.post(`/atelier/production-orders/${props.order.id}/plan`, {}, { preserveScroll: true, onError: errorToast('Planlanamadı'), onFinish: () => { busy.value = '' } })
}
async function complete() {
	const ok = await confirm({ title: 'İş emri tamamlansın mı?', message: `Üretilen ${props.order.producedQty} adet ${props.order.warehouse ?? 'depoya'} stoğuna alınacak.`, tone: 'accent', confirmText: 'Tamamla' })
	if (!ok) return
	busy.value = 'complete'
	router.post(`/atelier/production-orders/${props.order.id}/complete`, {}, { preserveScroll: true, onError: errorToast('Tamamlanamadı'), onFinish: () => { busy.value = '' } })
}
async function cancel() {
	const ok = await confirm.danger('İş emri iptal edilsin mi?', 'Bu işlem geri alınamaz.', { confirmText: 'İptal Et' })
	if (!ok) return
	busy.value = 'cancel'
	router.post(`/atelier/production-orders/${props.order.id}/cancel`, {}, { preserveScroll: true, onError: errorToast('İptal edilemedi'), onFinish: () => { busy.value = '' } })
}
</script>

<template>
	<Head :title="`${order.code} — İş Emri`" />

	<OzBreadcrumbs :items="breadcrumbs" class="crumbs" />
	<OzPageHeader :title="`${order.code} · ${order.productName ?? '—'}`">
		<template #subtitle>
			<span class="subtitle">
				<OzPill :tone="ORDER_TONE[order.status] ?? 'neutral'">{{ ORDER_STATUS[order.status] ?? order.status }}</OzPill>
				<span>Depo: <b>{{ order.warehouse ?? '—' }}</b></span>
				<span>Birim maliyet: <b class="mono">{{ formatMoney(order.unitCost) }}</b></span>
			</span>
		</template>
		<template v-if="canManage && isOpen" #actions>
			<OzButton v-if="order.status === 'draft'" variant="primary" icon="package" :loading="busy === 'plan'" @click="plan">Planla</OzButton>
			<OzButton v-if="['planned', 'in_progress'].includes(order.status)" variant="primary" icon="check" :loading="busy === 'complete'" @click="complete">Tamamla → Stoğa Al</OzButton>
			<OzButton variant="danger" icon="x" :loading="busy === 'cancel'" @click="cancel">İptal Et</OzButton>
		</template>
	</OzPageHeader>
	<OzModuleTabs />

	<div class="stats">
		<OzStatTile v-for="s in stats" :key="s.key" :label="s.label" :value="s.value" :icon="s.icon" :tone="s.tone" />
	</div>

	<div class="grid">
		<div class="col">
			<OzCard title="Varyant Üretim Miktarları" :meta="`${order.items.length} varyant`">
				<OzEmptyState v-if="!order.items.length" icon="package" title="Varyant satırı yok" compact />
				<div v-else class="table-scroll">
					<table class="grid-table">
						<thead>
							<tr>
								<th>Varyant</th>
								<th class="num">Plan</th>
								<th class="num">Üretilen</th>
								<th class="num">Fire</th>
							</tr>
						</thead>
						<tbody>
							<tr v-for="i in order.items" :key="i.id">
								<td class="strong">{{ i.size }} / {{ i.colorName }}</td>
								<td class="num mono">{{ i.plannedQty }}</td>
								<td class="num"><OzInput v-model.number="itemForms[i.id].produced_qty" type="number" min="0" class="qty" :disabled="!canManage" :aria-label="`${i.size} / ${i.colorName} üretilen`" /></td>
								<td class="num"><OzInput v-model.number="itemForms[i.id].scrap_qty" type="number" min="0" class="qty" :disabled="!canManage" :aria-label="`${i.size} / ${i.colorName} fire`" /></td>
							</tr>
						</tbody>
						<tfoot>
							<tr>
								<td>Toplam</td>
								<td class="num mono">{{ order.plannedQty }}</td>
								<td class="num mono">{{ itemsTotal }}</td>
								<td />
							</tr>
						</tfoot>
					</table>
				</div>
				<div v-if="canManage && order.items.length" class="card-foot">
					<OzButton size="sm" variant="primary" icon="check" :loading="busy === 'items'" @click="saveItems">Miktarları Kaydet</OzButton>
				</div>
			</OzCard>

			<OzCard title="Rota / Üretim Aşamaları" :meta="`${doneSteps} / ${order.steps.length} tamamlandı`">
				<OzEmptyState v-if="!order.steps.length" icon="workflow" title="Rota adımı tanımlanmamış" compact />
				<div v-else class="steps">
					<div v-for="s in order.steps" :key="s.id" class="step">
						<div class="step-head">
							<span class="step-seq" :class="`is-${s.status}`">{{ s.sequence }}</span>
							<span class="step-name">{{ s.operationName }}</span>
							<OzPill :tone="s.locationType === 'fason' ? 'warning' : 'info'">{{ s.locationType === 'fason' ? 'Fason' : 'İç' }}</OzPill>
							<OzPill :tone="STEP_TONE[s.status] ?? 'neutral'">{{ STEP_STATUS[s.status] ?? s.status }}</OzPill>
							<span class="step-cost mono">{{ formatMoney(s.stepCost) }}</span>
						</div>
						<div class="step-fields">
							<label class="f"><span>Durum</span><OzSelect v-model="stepForms[s.id].status" :options="stepStatusOptions" :disabled="!canManage" /></label>
							<label class="f"><span>Giren</span><OzInput v-model.number="stepForms[s.id].input_qty" type="number" min="0" :disabled="!canManage" /></label>
							<label class="f"><span>Çıkan</span><OzInput v-model.number="stepForms[s.id].output_qty" type="number" min="0" :disabled="!canManage" /></label>
							<label class="f"><span>Fire</span><OzInput v-model.number="stepForms[s.id].scrap_qty" type="number" min="0" :disabled="!canManage" /></label>
							<label class="f"><span>Birim</span><OzInput v-model.number="stepForms[s.id].unit_cost" type="number" min="0" step="0.01" suffix="₺" :disabled="!canManage" /></label>
							<label v-if="s.locationType === 'fason'" class="f f-wide"><span>Fasoncu</span><OzSelect v-model="stepForms[s.id].fason_supplier_id" :options="fasonOptions" placeholder="Fasoncu seçin" :disabled="!canManage" /></label>
							<OzButton v-if="canManage" class="step-save" size="sm" icon="check" :loading="busy === `step-${s.id}`" @click="saveStep(s.id)">Kaydet</OzButton>
						</div>
					</div>
				</div>
			</OzCard>
		</div>

		<div class="col side">
			<OzCard title="Üretim Durumu">
				<div class="summary">
					<OzProgress label="Üretilen" :value="producedPct" :tone="producedPct >= 100 ? 'ok' : 'accent'" />
					<div class="kv"><span>Planlanan</span><b class="mono">{{ order.plannedQty }} adet</b></div>
					<div class="kv"><span>Üretilen</span><b class="mono">{{ order.producedQty }} adet</b></div>
					<div class="kv"><span>Rota</span><b>{{ doneSteps }} / {{ order.steps.length }} adım</b></div>
					<div class="kv"><span>Birim maliyet</span><b class="mono">{{ formatMoney(order.unitCost) }}</b></div>
				</div>
			</OzCard>
		</div>
	</div>
</template>

<style scoped>
.crumbs { margin-bottom: 8px; }
.stats { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; margin-bottom: 14px; }
@media (max-width: 1040px) { .stats { grid-template-columns: repeat(2, minmax(0, 1fr)); } }

.subtitle { display: inline-flex; align-items: center; gap: 12px; flex-wrap: wrap; }
.mono { font-family: ui-monospace, monospace; font-size: 12px; }

.grid { display: grid; grid-template-columns: minmax(0, 2fr) minmax(260px, 1fr); gap: 16px; align-items: start; }
.col { display: flex; flex-direction: column; gap: 16px; min-width: 0; }
@media (max-width: 1100px) { .grid { grid-template-columns: 1fr; } .side { order: -1; } }

.table-scroll { overflow-x: auto; }
.grid-table { width: 100%; min-width: 420px; border-collapse: collapse; font-size: 13px; }
.grid-table th { text-align: left; padding: 8px 18px; font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: var(--oz-muted); border-bottom: 1px solid var(--oz-border); }
.grid-table td { padding: 7px 18px; border-bottom: 1px solid var(--oz-border-soft); color: var(--oz-ink); }
.grid-table tfoot td { border-bottom: none; background: var(--oz-surface-2); font-weight: 600; color: var(--oz-muted); }
.grid-table .num { text-align: right; }
.grid-table .strong { font-weight: 600; color: var(--oz-ink-soft); }
.qty { width: 110px; margin-left: auto; }
.card-foot { display: flex; justify-content: flex-end; padding: 12px 18px 16px; }

.steps { display: flex; flex-direction: column; }
.step { padding: 14px 18px; display: flex; flex-direction: column; gap: 10px; }
.step + .step { border-top: 1px solid var(--oz-border-soft); }
.step-head { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.step-seq {
	width: 24px; height: 24px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center;
	font-size: 11px; font-weight: 700; background: var(--oz-surface-2); color: var(--oz-muted); border: 1px solid var(--oz-border);
}
.step-seq.is-in_progress { background: var(--oz-accent-soft); color: var(--oz-accent); border-color: transparent; }
.step-seq.is-done { background: var(--oz-success); color: #fff; border-color: transparent; }
.step-name { font-weight: 700; font-size: 13px; color: var(--oz-ink-soft); }
.step-cost { margin-left: auto; color: var(--oz-muted); }
.step-fields { display: grid; grid-template-columns: repeat(auto-fill, minmax(100px, 1fr)); gap: 10px; align-items: end; }
.f { display: flex; flex-direction: column; gap: 4px; min-width: 0; }
.f > span { font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: var(--oz-muted); }
.f-wide { grid-column: span 2; }
.step-save { justify-self: start; }

.summary { display: flex; flex-direction: column; gap: 10px; padding: 0 18px 16px; }
.kv { display: flex; justify-content: space-between; gap: 10px; font-size: 13px; color: var(--oz-muted); }
.kv b { color: var(--oz-ink-soft); }
</style>
