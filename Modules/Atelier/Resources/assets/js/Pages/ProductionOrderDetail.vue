<script setup>
import { ref, reactive, computed, inject } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import {
	OzPageHeader, OzModuleTabs, OzBreadcrumbs, OzStatStrip, OzCard, OzButton, OzPill, OzProgress,
	OzEmptyState, OzInput, OzSelect, OzField, OzTextarea, OzModal, OzAlert, useOzConfirm,
} from '@/Components/Oz'
import { ORDER_STATUS, ORDER_TONE, formatMoney, formatNumber } from '../atelierNav'
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

const STEP_STATUS = { pending: 'Bekliyor', in_progress: 'Sürüyor', done: 'Bitti' }
const STEP_TONE = { pending: 'neutral', in_progress: 'accent', done: 'success' }
const LOCATION = { in_house: 'İç', fason: 'Fason' }
const locationOptions = Object.entries(LOCATION).map(([value, label]) => ({ value, label }))
const fasonOptions = computed(() => props.fasonSuppliers.map((f) => ({ value: f.id, label: f.name })))

const isOpen = computed(() => ['draft', 'planned', 'in_progress'].includes(props.order.status))
const isRunning = computed(() => ['planned', 'in_progress'].includes(props.order.status))
const receivedPct = computed(() => props.order.plannedQty ? Math.min(100, Math.round((props.order.receivedQty / props.order.plannedQty) * 100)) : 0)
const doneSteps = computed(() => props.order.steps.filter((s) => s.status === 'done').length)

// Kapanmamış emirde fason/işçilik aşama maliyetlerinden anlık hesaplanır; kapanınca sunucudaki kesin değer.
const liveCost = computed(() => {
	if (props.order.status === 'completed') {
		return { material: props.order.materialCost, fason: props.order.fasonCost, labor: props.order.laborCost, total: props.order.totalCost }
	}
	const sum = (type) => props.order.steps.filter((s) => s.locationType === type).reduce((t, s) => t + s.stepCost, 0)
	const fason = sum('fason'); const labor = sum('in_house')
	return { material: props.order.materialCost, fason, labor, total: props.order.materialCost + fason + labor }
})

const stats = computed(() => [
	{ key: 'planned', label: 'Planlanan', value: `${props.order.plannedQty} adet`, icon: 'layers', tone: 'info' },
	{ key: 'received', label: 'Stoğa Alınan', value: `${props.order.receivedQty} adet`, icon: 'warehouse', tone: 'success' },
	{ key: 'receivable', label: 'Stoğa Alınabilir', value: `${props.order.receivable} adet`, icon: 'package', tone: props.order.receivable > 0 ? 'warning' : 'info' },
	{ key: 'unit', label: props.order.status === 'completed' ? 'Birim Maliyet' : 'Toplam Maliyet (anlık)', value: formatMoney(props.order.status === 'completed' ? props.order.unitCost : liveCost.value.total), icon: 'wallet', tone: 'accent' },
])

const breadcrumbs = computed(() => [
	{ label: 'Atölye', to: '/atelier' },
	{ label: 'İş Emirleri', to: '/atelier/production-orders' },
	{ label: props.order.code },
])

const busy = ref('')
const errorToast = (title) => (errs) => showToast?.({ type: 'error', title, message: Object.values(errs)[0] || 'Sunucu hatası.' })
const post = (key, url, data, title, onSuccess) => {
	busy.value = key
	router.post(url, data, { preserveScroll: true, onSuccess, onError: errorToast(title), onFinish: () => { busy.value = '' } })
}
const base = computed(() => `/atelier/production-orders/${props.order.id}`)

/* ── aşama partileri ── */
const batchForms = reactive(Object.fromEntries(props.order.steps.map((s) => [s.id, { good_qty: null, scrap_qty: null, note: '' }])))
const openHistory = ref(null)

function recordBatch(step) {
	const f = batchForms[step.id]
	post(`batch-${step.id}`, `${base.value}/steps/${step.id}/batches`, {
		good_qty: Number(f.good_qty) || 0, scrap_qty: Number(f.scrap_qty) || 0, note: f.note || null,
	}, 'Üretim kaydı eklenemedi', () => { f.good_qty = null; f.scrap_qty = null; f.note = '' })
}
const batchTotal = (step) => (Number(batchForms[step.id].good_qty) || 0) + (Number(batchForms[step.id].scrap_qty) || 0)

/* ── fason kararı ── */
const decisionOpen = ref(false)
const decisionStep = ref(null)
const decisionForm = reactive({ location_type: 'in_house', fason_supplier_id: null, unit_cost: 0, note: '' })

function openDecision(step) {
	decisionStep.value = step
	Object.assign(decisionForm, {
		location_type: step.locationType, fason_supplier_id: step.fasonSupplierId, unit_cost: step.unitCost, note: '',
	})
	decisionOpen.value = true
}
function saveDecision() {
	busy.value = 'decision'
	router.put(`${base.value}/steps/${decisionStep.value.id}/decision`, {
		...decisionForm,
		fason_supplier_id: decisionForm.location_type === 'fason' ? decisionForm.fason_supplier_id : null,
	}, {
		preserveScroll: true,
		onSuccess: () => { decisionOpen.value = false },
		onError: errorToast('Karar kaydedilemedi'),
		onFinish: () => { busy.value = '' },
	})
}

/* ── stoğa al ── */
const receiveForm = reactive(Object.fromEntries(props.order.items.map((i) => [i.variantId, 0])))
const receiveTotal = computed(() => Object.values(receiveForm).reduce((t, v) => t + (Number(v) || 0), 0))

// Alınabilir adedi, varyantların kalan (plan − alınan) adedi oranında dağıtır; artan, en çok kalana.
function distribute() {
	const rows = props.order.items.map((i) => ({ id: i.variantId, left: Math.max(0, i.plannedQty - i.receivedQty) }))
	const totalLeft = rows.reduce((t, r) => t + r.left, 0)
	const target = props.order.receivable
	if (!totalLeft) {
		rows.forEach((r, idx) => { receiveForm[r.id] = idx === 0 ? target : 0 })
		return
	}
	let given = 0
	rows.forEach((r) => { receiveForm[r.id] = Math.floor((target * r.left) / totalLeft); given += receiveForm[r.id] })
	rows.sort((a, b) => b.left - a.left)
	for (let i = 0; given < target; i = (i + 1) % rows.length) { receiveForm[rows[i].id]++; given++ }
}

function receive() {
	const items = Object.entries(receiveForm)
		.filter(([, qty]) => Number(qty) > 0)
		.map(([id, qty]) => ({ product_variant_id: Number(id), qty: Number(qty) }))
	post('receive', `${base.value}/receive`, { items }, 'Stoğa alınamadı', () => {
		Object.keys(receiveForm).forEach((k) => { receiveForm[k] = 0 })
	})
}

/* ── durum geçişleri ── */
async function plan() {
	const ok = await confirm({ title: 'İş emri planlansın mı?', message: 'Reçeteye göre gereken hammadde rezerve edilecek; stoktan kesim aşamasında düşülür.', tone: 'warning', confirmText: 'Planla' })
	if (ok) post('plan', `${base.value}/plan`, {}, 'Planlanamadı')
}
async function close() {
	const ok = await confirm({ title: 'İş emri kapatılsın mı?', message: `${props.order.receivedQty} adet stoğa alındı. Kalan hammadde rezervi serbest bırakılacak ve gerçek birim maliyet ürünün alış fiyatına yazılacak.`, tone: 'accent', confirmText: 'Kapat' })
	if (ok) post('close', `${base.value}/close`, {}, 'Kapatılamadı')
}
async function cancel() {
	const ok = await confirm.danger('İş emri iptal edilsin mi?', 'Hammadde rezervi serbest bırakılır; kesilmiş (tüketilmiş) hammadde geri dönmez.', { confirmText: 'İptal Et' })
	if (ok) post('cancel', `${base.value}/cancel`, {}, 'İptal edilemedi')
}
</script>

<template>
	<Head :title="`${order.code} — İş Emri`" />

	<OzBreadcrumbs :items="breadcrumbs" class="crumbs" />
	<OzPageHeader :title="`${order.code} · ${order.productName ?? '—'}`">
		<template #subtitle>
			<span class="subtitle">
				<OzPill :tone="ORDER_TONE[order.status] ?? 'neutral'">{{ ORDER_STATUS[order.status] ?? order.status }}</OzPill>
				<OzPill v-if="order.productStatus === 'development'" tone="warning">Geçici Kart</OzPill>
				<span>Depo: <b>{{ order.warehouse ?? '—' }}</b></span>
				<span v-if="order.dueDate">Termin: <b class="mono">{{ order.dueDate }}</b></span>
			</span>
		</template>
		<template v-if="canManage && isOpen" #actions>
			<OzButton v-if="order.status === 'draft'" variant="primary" icon="package" :loading="busy === 'plan'" @click="plan">Planla</OzButton>
			<OzButton v-if="isRunning" variant="primary" icon="check" :loading="busy === 'close'" :disabled="!order.receivedQty || order.receivable > 0" @click="close">Kapat</OzButton>
			<OzButton v-if="!order.receivedQty" variant="danger" icon="x" :loading="busy === 'cancel'" @click="cancel">İptal Et</OzButton>
		</template>
	</OzPageHeader>
	<OzModuleTabs />

	<OzStatStrip :items="stats" />

	<OzAlert v-if="order.status === 'draft'" tone="info" title="Taslak iş emri" class="banner">
		Planla ile reçetedeki hammadde rezerve edilir; üretim kayıtları planlamadan sonra açılır. Aşama kararları (iç/fason) şimdi değiştirilebilir.
	</OzAlert>

	<div class="grid">
		<div class="col">
			<OzCard title="Rota / Üretim Aşamaları" :meta="`${doneSteps} / ${order.steps.length} bitti`">
				<OzEmptyState v-if="!order.steps.length" icon="workflow" title="Rota adımı tanımlanmamış" compact />
				<ol v-else class="steps">
					<li v-for="s in order.steps" :key="s.id" class="step">
						<div class="step-head">
							<span class="step-seq" :class="`is-${s.status}`">{{ s.sequence }}</span>
							<span class="step-name">{{ s.operationName }}</span>
							<OzPill :tone="s.locationType === 'fason' ? 'warning' : 'info'">
								{{ LOCATION[s.locationType] }}<template v-if="s.fasonSupplier"> · {{ s.fasonSupplier }}</template>
							</OzPill>
							<OzPill v-if="s.consumesMaterials" tone="neutral">Hammadde düşer</OzPill>
							<OzPill :tone="STEP_TONE[s.status] ?? 'neutral'">{{ STEP_STATUS[s.status] ?? s.status }}</OzPill>
							<OzButton
								v-if="canManage && isOpen && !s.batches.length"
								class="step-edit" size="sm" icon="edit" :aria-label="`${s.operationName} kararını değiştir`"
								@click="openDecision(s)"
							>Karar</OzButton>
						</div>

						<div class="metrics">
							<div class="m"><span>Bekleyen</span><b class="mono" :class="{ hot: s.available > 0 }">{{ s.available }}</b></div>
							<div class="m"><span>Çıkan</span><b class="mono">{{ s.outputQty }}</b></div>
							<div class="m"><span>Fire</span><b class="mono">{{ s.scrapQty }}</b></div>
							<div class="m"><span>Adet fiyatı</span><b class="mono">{{ formatMoney(s.unitCost) }}</b></div>
							<div class="m"><span>Maliyet</span><b class="mono">{{ formatMoney(s.stepCost) }}</b></div>
						</div>

						<div v-if="canManage && isRunning" class="batch">
							<OzInput v-model.number="batchForms[s.id].good_qty" type="number" min="0" placeholder="Sağlam" suffix="adet" :disabled="!s.available" :aria-label="`${s.operationName} sağlam adet`" />
							<OzInput v-model.number="batchForms[s.id].scrap_qty" type="number" min="0" placeholder="Fire" suffix="adet" :disabled="!s.available" :aria-label="`${s.operationName} fire adet`" />
							<OzInput v-model="batchForms[s.id].note" placeholder="Not (isteğe bağlı)" :disabled="!s.available" :aria-label="`${s.operationName} not`" />
							<OzButton
								size="sm" variant="primary" icon="plus"
								:disabled="!s.available || !batchTotal(s) || batchTotal(s) > s.available"
								:loading="busy === `batch-${s.id}`"
								@click="recordBatch(s)"
							>Kaydet</OzButton>
						</div>
						<p v-if="canManage && isRunning && batchTotal(s) > s.available" class="hint danger">Bu aşamada en fazla {{ s.available }} adet işlenebilir.</p>

						<button v-if="s.batches.length" type="button" class="history-toggle" @click="openHistory = openHistory === s.id ? null : s.id">
							{{ openHistory === s.id ? 'Kayıtları gizle' : `${s.batches.length} kayıt` }}
						</button>
						<ul v-if="openHistory === s.id" class="history">
							<li v-for="b in s.batches" :key="b.id">
								<span class="mono">{{ b.at }}</span>
								<b>+{{ b.goodQty }}</b><span v-if="b.scrapQty" class="dim"> / {{ b.scrapQty }} fire</span>
								<span class="dim">{{ b.by ?? '—' }}</span>
								<span v-if="b.note" class="dim note">{{ b.note }}</span>
							</li>
						</ul>
					</li>
				</ol>
			</OzCard>

			<OzCard v-if="order.receivable > 0 || order.receivedQty > 0" title="Stoğa Al" :meta="`${order.receivable} adet alınabilir`">
				<div class="table-scroll">
					<table class="grid-table">
						<thead>
							<tr>
								<th>Varyant</th>
								<th class="num">Plan</th>
								<th class="num">Alınan</th>
								<th v-if="canManage && isRunning && order.receivable > 0" class="num">Bu giriş</th>
							</tr>
						</thead>
						<tbody>
							<tr v-for="i in order.items" :key="i.id">
								<td class="strong">{{ i.size ?? '—' }} / {{ i.colorName ?? '—' }}</td>
								<td class="num mono">{{ i.plannedQty }}</td>
								<td class="num mono">{{ i.receivedQty }}</td>
								<td v-if="canManage && isRunning && order.receivable > 0" class="num">
									<OzInput v-model.number="receiveForm[i.variantId]" type="number" min="0" class="qty" :aria-label="`${i.size} / ${i.colorName} stoğa alınacak`" />
								</td>
							</tr>
						</tbody>
						<tfoot>
							<tr>
								<td>Toplam</td>
								<td class="num mono">{{ order.plannedQty }}</td>
								<td class="num mono">{{ order.receivedQty }}</td>
								<td v-if="canManage && isRunning && order.receivable > 0" class="num mono" :class="{ danger: receiveTotal > order.receivable }">{{ receiveTotal }} / {{ order.receivable }}</td>
							</tr>
						</tfoot>
					</table>
				</div>
				<div v-if="canManage && isRunning && order.receivable > 0" class="card-foot">
					<OzButton size="sm" icon="refresh" @click="distribute">Orantılı Dağıt</OzButton>
					<OzButton
						size="sm" variant="primary" icon="warehouse"
						:disabled="!receiveTotal || receiveTotal > order.receivable"
						:loading="busy === 'receive'"
						@click="receive"
					>{{ order.warehouse ?? 'Depo' }} stoğuna al</OzButton>
				</div>
			</OzCard>
		</div>

		<div class="col side">
			<OzCard title="Üretim Durumu">
				<div class="summary">
					<OzProgress label="Stoğa alınan" :value="receivedPct" :tone="receivedPct >= 100 ? 'ok' : 'accent'" />
					<div class="kv"><span>Planlanan</span><b class="mono">{{ order.plannedQty }} adet</b></div>
					<div class="kv"><span>Stoğa alınan</span><b class="mono">{{ order.receivedQty }} adet</b></div>
					<div class="kv"><span>Rota</span><b>{{ doneSteps }} / {{ order.steps.length }} aşama</b></div>
					<div v-if="order.closedAt" class="kv"><span>Kapanış</span><b class="mono">{{ order.closedAt }}</b></div>
				</div>
			</OzCard>

			<OzCard title="Hammadde" :meta="order.status === 'draft' ? 'planlamada rezerve edilir' : ''">
				<OzEmptyState v-if="!order.materials.length" icon="layers" title="Rezerv yok" :description="order.status === 'draft' ? 'İş emri henüz planlanmadı.' : 'Reçetede hammadde yok.'" compact />
				<ul v-else class="materials">
					<li v-for="m in order.materials" :key="m.id">
						<div class="mat-name">{{ m.name }}</div>
						<div class="mat-row">
							<span>Gerekli <b class="mono">{{ formatNumber(m.requiredQty, 3) }}</b></span>
							<span>Tüketilen <b class="mono">{{ formatNumber(m.consumedQty, 3) }}</b></span>
							<span>{{ m.released ? 'Serbest' : 'Rezerv' }} <b class="mono">{{ formatNumber(m.openQty, 3) }}</b></span>
							<span class="dim">{{ m.unit }}</span>
						</div>
					</li>
				</ul>
			</OzCard>

			<OzCard title="Maliyet" :meta="order.status === 'completed' ? 'kesin' : 'anlık'">
				<div class="summary">
					<div class="kv"><span>Malzeme{{ order.status === 'completed' ? '' : ' (plan)' }}</span><b class="mono">{{ formatMoney(liveCost.material) }}</b></div>
					<div class="kv"><span>Fason</span><b class="mono">{{ formatMoney(liveCost.fason) }}</b></div>
					<div class="kv"><span>İşçilik</span><b class="mono">{{ formatMoney(liveCost.labor) }}</b></div>
					<div class="kv total"><span>Toplam</span><b class="mono">{{ formatMoney(liveCost.total) }}</b></div>
					<div v-if="order.status === 'completed'" class="kv"><span>Birim maliyet</span><b class="mono">{{ formatMoney(order.unitCost) }}</b></div>
				</div>
			</OzCard>
		</div>
	</div>

	<OzModal v-model:open="decisionOpen" :title="`${decisionStep?.operationName ?? ''} — aşama kararı`" subtitle="Kendimiz mi üretelim, fasona mı verelim?" icon="factory" :persistent="busy === 'decision'">
		<div v-if="decisionStep" class="form">
			<OzField label="Nerede yapılacak" required>
				<OzSelect v-model="decisionForm.location_type" :options="locationOptions" />
			</OzField>
			<OzField v-if="decisionForm.location_type === 'fason'" label="Fasoncu" required>
				<OzSelect v-model="decisionForm.fason_supplier_id" :options="fasonOptions" placeholder="Fasoncu seçin" />
			</OzField>
			<OzField label="Adet fiyatı">
				<OzInput v-model.number="decisionForm.unit_cost" type="number" min="0" step="0.01" suffix="₺" />
			</OzField>
			<OzField label="Not">
				<OzTextarea v-model="decisionForm.note" :rows="2" placeholder="Karar gerekçesi…" />
			</OzField>
			<div v-if="decisionStep.decisions.length" class="decisions">
				<h4>Karar geçmişi</h4>
				<ul>
					<li v-for="d in decisionStep.decisions" :key="d.id">
						<span class="mono">{{ d.at }}</span>
						<b>{{ LOCATION[d.locationType] }}<template v-if="d.fasonSupplier"> · {{ d.fasonSupplier }}</template></b>
						<span class="mono">{{ formatMoney(d.unitCost) }}</span>
						<span class="dim">{{ d.by ?? '—' }}</span>
						<span v-if="d.note" class="dim note">{{ d.note }}</span>
					</li>
				</ul>
			</div>
		</div>
		<template #footer>
			<OzButton :disabled="busy === 'decision'" @click="decisionOpen = false">Vazgeç</OzButton>
			<OzButton
				variant="primary" icon="check" :loading="busy === 'decision'"
				:disabled="decisionForm.location_type === 'fason' && !decisionForm.fason_supplier_id"
				@click="saveDecision"
			>Kaydet</OzButton>
		</template>
	</OzModal>
</template>

<style scoped>
.crumbs { margin-bottom: 8px; }
.banner { margin-bottom: 14px; }

.subtitle { display: inline-flex; align-items: center; gap: 12px; flex-wrap: wrap; }
.mono { font-family: ui-monospace, monospace; font-size: 12px; }
.dim { color: var(--oz-muted); }
.danger { color: var(--oz-danger); }

.grid { display: grid; grid-template-columns: minmax(0, 2fr) minmax(260px, 1fr); gap: 16px; align-items: start; }
.col { display: flex; flex-direction: column; gap: 16px; min-width: 0; }
@media (max-width: 1100px) { .grid { grid-template-columns: 1fr; } .side { order: -1; } }

.steps { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; }
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
.step-edit { margin-left: auto; }
.step { min-width: 0; }

.metrics { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 10px; }
@media (max-width: 640px) { .metrics { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
.m { display: flex; flex-direction: column; gap: 2px; }
.m > span { font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: var(--oz-muted); }
.m > b { color: var(--oz-ink-soft); }
.m > b.hot { color: var(--oz-accent); }

.batch { display: grid; grid-template-columns: 140px 140px minmax(0, 1fr) auto; gap: 8px; align-items: center; }
/* grid öğeleri varsayılan min-width:auto ile input'un doğal genişliğinin altına inemiyor → kart dışına taşıyordu */
.batch > * { min-width: 0; }
@media (max-width: 640px) {
	.batch { grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); }
	.batch > :nth-child(3), .batch > :nth-child(4) { grid-column: 1 / -1; }
}
.hint { margin: 0; font-size: 12px; }

.history-toggle { align-self: flex-start; background: none; border: 0; padding: 0; font-size: 12px; color: var(--oz-accent); cursor: pointer; }
.history, .decisions ul { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 6px; font-size: 12.5px; }
.history li, .decisions li { display: flex; gap: 10px; flex-wrap: wrap; align-items: baseline; }
.note { flex-basis: 100%; }

.table-scroll { overflow-x: auto; }
.grid-table { width: 100%; min-width: 420px; border-collapse: collapse; font-size: 13px; }
.grid-table th { text-align: left; padding: 8px 18px; font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: var(--oz-muted); border-bottom: 1px solid var(--oz-border); }
.grid-table td { padding: 7px 18px; border-bottom: 1px solid var(--oz-border-soft); color: var(--oz-ink); }
.grid-table tfoot td { border-bottom: none; background: var(--oz-surface-2); font-weight: 600; color: var(--oz-muted); }
.grid-table .num { text-align: right; }
.grid-table .strong { font-weight: 600; color: var(--oz-ink-soft); }
.qty { width: 110px; margin-left: auto; }
.card-foot { display: flex; justify-content: flex-end; gap: 8px; padding: 12px 18px 16px; }

.summary { display: flex; flex-direction: column; gap: 10px; padding: 0 18px 16px; }
.kv { display: flex; justify-content: space-between; gap: 10px; font-size: 13px; color: var(--oz-muted); }
.kv b { color: var(--oz-ink-soft); }
.kv.total { border-top: 1px solid var(--oz-border-soft); padding-top: 10px; }

.materials { list-style: none; margin: 0; padding: 0 18px 16px; display: flex; flex-direction: column; gap: 10px; }
.mat-name { font-weight: 600; font-size: 13px; color: var(--oz-ink-soft); }
.mat-row { display: flex; gap: 12px; flex-wrap: wrap; font-size: 12px; color: var(--oz-muted); }
.mat-row b { color: var(--oz-ink-soft); }

.form { display: flex; flex-direction: column; gap: 12px; }
.decisions h4 { margin: 4px 0 8px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: var(--oz-muted); }
</style>
