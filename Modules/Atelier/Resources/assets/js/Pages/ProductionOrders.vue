<script setup>
import { ref, computed, inject, watch, onMounted } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import axios from 'axios'
import { useDebounceFn } from '@vueuse/core'
import AppLayout from '@/Layouts/AppLayout.vue'
import {
	OzPageHeader, OzModuleTabs, OzStatTile, OzCard, OzButton, OzTable, OzPill, OzDrawer,
	OzField, OzInput, OzSelect, OzCombobox, OzTextarea, OzAlert, OzSpinner,
} from '@/Components/Oz'
import { ORDER_STATUS, ORDER_TONE, formatMoney, formatNumber } from '../atelierNav'
import { useCan } from '@/composables/useCan'

defineOptions({ layout: AppLayout })

const props = defineProps({
	orders: { type: Array, default: () => [] },
	products: { type: Array, default: () => [] },
	warehouses: { type: Array, default: () => [] },
	operations: { type: Array, default: () => [] },
	preselectProductId: { type: Number, default: null },
})

const { can } = useCan()
const canManage = computed(() => can('atelier.production.manage'))
const showToast = inject('showToast', null)

/* ── özet ── */
const countBy = (...statuses) => props.orders.filter((o) => statuses.includes(o.status)).length
const today = new Date().toISOString().slice(0, 10)
const isLate = (o) => !!o.dueDate && o.dueDate < today && !['completed', 'cancelled'].includes(o.status)
const stats = computed(() => [
	{ key: 'waiting', label: 'Taslak / Planlı', value: countBy('draft', 'planned'), icon: 'clock', tone: 'info' },
	{ key: 'progress', label: 'Üretimde', value: countBy('in_progress'), icon: 'factory', tone: 'accent' },
	{ key: 'late', label: 'Termini Geçen', value: props.orders.filter(isLate).length, icon: 'alert', tone: props.orders.some(isLate) ? 'danger' : 'success' },
	{ key: 'done', label: 'Tamamlanan', value: countBy('completed'), icon: 'success', tone: 'success' },
])

/* ── tablo ── */
const statusOptions = Object.entries(ORDER_STATUS).map(([value, label]) => ({ value, label }))
const columns = [
	{ key: 'code', label: 'Kod', sortable: true },
	{ key: 'productName', label: 'Ürün', sortable: true, class: 'oz-cell-strong' },
	{ key: 'status', label: 'Durum', sortable: true, filter: { type: 'select', options: statusOptions } },
	{ key: 'progress', label: 'Üretilen / Plan', align: 'right', searchable: false },
	{ key: 'dueDate', label: 'Termin', sortable: true, filter: { type: 'date' } },
	{ key: 'totalCost', label: 'Maliyet', align: 'right', sortable: true, class: 'oz-cell-amount' },
	{ key: 'actions', label: '', align: 'right', width: '80px', searchable: false },
]

/* ── yeni iş emri sihirbazı ── */
const productOptions = computed(() => props.products.map((p) => ({ value: p.id, label: p.name, description: p.sku })))
const warehouseOptions = computed(() => props.warehouses.map((w) => ({ value: w.id, label: `${w.name} (${w.code})` })))
const operationOptions = computed(() => props.operations.map((o) => ({ value: o.id, label: o.name })))
const locationOptions = [{ value: 'in_house', label: 'İç atölye' }, { value: 'fason', label: 'Fason' }]

const emptyStep = (sequence) => ({ operation_id: '', sequence, location_type: 'in_house', fason_supplier_id: null, unit_cost: 0 })
const emptyForm = () => ({
	product_id: '', warehouse_id: props.warehouses.length === 1 ? props.warehouses[0].id : '',
	planned_qty: 0, due_date: '', notes: '', items: [], steps: [emptyStep(1)],
})
const form = useForm(emptyForm())
const wizardOpen = ref(false)
const requirements = ref([])
const previewLoading = ref(false)

function resetForm() {
	form.defaults(emptyForm())
	form.reset()
	form.clearErrors()
	requirements.value = []
}
watch(wizardOpen, (open) => { if (!open) setTimeout(resetForm, 250) })

function openWizard() {
	resetForm()
	wizardOpen.value = true
}

// "Üretilecek Ürün" kaydından yönlendirildiyse sihirbazı o ürünle aç
onMounted(() => {
	if (props.preselectProductId && canManage.value) {
		openWizard()
		form.product_id = props.preselectProductId
	}
})

// Ürün ya da adet değişince varyantlar + BOM malzeme gereksinimi sunucudan önizlenir
let previewSeq = 0
const loadPreview = useDebounceFn(async () => {
	if (!form.product_id || !(form.planned_qty > 0)) {
		requirements.value = []
		return
	}
	const seq = ++previewSeq
	previewLoading.value = true
	try {
		const { data } = await axios.get('/atelier/production-orders/plan-preview', {
			params: { product_id: form.product_id, planned_qty: form.planned_qty },
		})
		if (seq !== previewSeq) return
		requirements.value = data.requirements
		// Varyant listesi yalnızca ürün değişince yeniden kurulur; adet değişikliği girilen dağılımı silmez
		const ids = data.variants.map((v) => v.id).join(',')
		if (ids !== form.items.map((i) => i.product_variant_id).join(',')) {
			form.items = data.variants.map((v) => ({ product_variant_id: v.id, planned_qty: 0, label: `${v.size} / ${v.colorName}`, sku: v.sku }))
		}
	} catch {
		if (seq === previewSeq) showToast?.({ type: 'error', title: 'Önizleme alınamadı', message: 'Ürün varyantları ve malzeme ihtiyacı yüklenemedi.' })
	} finally {
		if (seq === previewSeq) previewLoading.value = false
	}
}, 350)
watch(() => form.product_id, () => { form.items = []; loadPreview() })
watch(() => form.planned_qty, loadPreview)

const itemsTotal = computed(() => form.items.reduce((s, i) => s + (Number(i.planned_qty) || 0), 0))
const itemsMismatch = computed(() => form.items.length > 0 && itemsTotal.value !== Number(form.planned_qty))
const requirementsCost = computed(() => requirements.value.reduce((s, r) => s + Number(r.line_cost || 0), 0))

// Planlanan adedi varyantlara eşit böler, artan adetler ilk varyantlara eklenir
function distributeEvenly() {
	const n = form.items.length
	const total = Number(form.planned_qty) || 0
	if (!n) return
	const base = Math.floor(total / n)
	form.items.forEach((it, i) => { it.planned_qty = base + (i < total % n ? 1 : 0) })
}

function addStep() { form.steps.push(emptyStep(form.steps.length + 1)) }
function removeStep(i) {
	form.steps.splice(i, 1)
	form.steps.forEach((s, idx) => { s.sequence = idx + 1 })
}
function onOperationChange(step) {
	const op = props.operations.find((o) => o.id === Number(step.operation_id))
	if (op) {
		step.location_type = op.default_location
		step.unit_cost = Number(op.default_unit_cost)
	}
}
const stepError = (i, field) => form.errors[`steps.${i}.${field}`]

function submit() {
	if (form.processing) return
	form.transform((d) => ({
		...d,
		items: d.items.map(({ product_variant_id, planned_qty }) => ({ product_variant_id, planned_qty })),
	})).post('/atelier/production-orders', {
		preserveScroll: true,
		onSuccess: () => { wizardOpen.value = false },
		onError: (errs) => showToast?.({ type: 'error', title: 'İş emri oluşturulamadı', message: Object.values(errs)[0] || 'Doğrulama hatası.' }),
	})
}
</script>

<template>
	<Head title="İş Emirleri" />

	<OzPageHeader title="İş Emirleri">
		<template #subtitle><b>{{ orders.length }}</b> üretim iş emri</template>
		<template #actions>
			<OzButton v-if="canManage" variant="primary" icon="plus" @click="openWizard">Yeni İş Emri</OzButton>
		</template>
	</OzPageHeader>
	<OzModuleTabs />

	<div class="stats">
		<OzStatTile v-for="s in stats" :key="s.key" :label="s.label" :value="s.value" :icon="s.icon" :tone="s.tone" />
	</div>

	<OzCard title="İş Emri Listesi">
		<OzTable :columns="columns" :rows="orders" row-key="id" searchable search-placeholder="Kod ya da ürün ara…" empty-title="İş emri kaydı yok" min-width="820px">
			<template #cell-code="{ value }"><span class="mono">{{ value }}</span></template>
			<template #cell-status="{ value }">
				<OzPill :tone="ORDER_TONE[value] ?? 'neutral'">{{ ORDER_STATUS[value] ?? value }}</OzPill>
			</template>
			<template #cell-progress="{ row }">
				<span class="mono"><b>{{ row.producedQty }}</b> <span class="dim">/ {{ row.plannedQty }}</span></span>
			</template>
			<template #cell-dueDate="{ row }">
				<span class="due">
					{{ row.dueDate || '—' }}
					<OzPill v-if="isLate(row)" tone="danger">Gecikti</OzPill>
				</span>
			</template>
			<template #cell-totalCost="{ value }"><span class="mono">{{ formatMoney(value) }}</span></template>
			<template #cell-actions="{ row }">
				<Link :href="`/atelier/production-orders/${row.id}`" class="detail-link">Detay</Link>
			</template>
		</OzTable>
	</OzCard>

	<OzDrawer v-model:open="wizardOpen" title="Yeni İş Emri" subtitle="Ürün, varyant dağılımı ve üretim rotası" icon="workflow" size="lg" :persistent="form.processing">
		<form class="form" @submit.prevent="submit">
			<section class="section">
				<h3 class="section-title">Temel Bilgiler</h3>
				<OzField label="Ürün" required :error="form.errors.product_id">
					<OzCombobox v-model="form.product_id" :options="productOptions" placeholder="Ürün seçin" search-placeholder="Ürün ya da SKU ara…" />
				</OzField>
				<div class="row-3">
					<OzField label="Planlanan Adet" required :error="form.errors.planned_qty">
						<OzInput v-model.number="form.planned_qty" type="number" min="1" step="1" suffix="adet" />
					</OzField>
					<OzField label="Depo" required :error="form.errors.warehouse_id" hint="Bitmiş ürün bu depoya alınır">
						<OzSelect v-model="form.warehouse_id" :options="warehouseOptions" placeholder="Depo seçin" />
					</OzField>
					<OzField label="Termin" :error="form.errors.due_date">
						<OzInput v-model="form.due_date" type="date" />
					</OzField>
				</div>
			</section>

			<section v-if="form.product_id" class="section">
				<div class="section-head">
					<h3 class="section-title">Varyant Dağılımı</h3>
					<OzSpinner v-if="previewLoading" :size="14" />
					<OzButton v-else-if="form.items.length" size="sm" icon="refresh" :disabled="!(form.planned_qty > 0)" @click="distributeEvenly">Eşit Dağıt</OzButton>
				</div>
				<p v-if="form.errors.items" class="field-error">{{ form.errors.items }}</p>
				<p v-if="!previewLoading && !form.items.length" class="dim small">
					{{ form.planned_qty > 0 ? 'Bu ürünün varyantı yok.' : 'Varyantları görmek için planlanan adedi girin.' }}
				</p>
				<div v-else class="variants">
					<div v-for="it in form.items" :key="it.product_variant_id" class="variant">
						<div class="variant-label">
							<span>{{ it.label }}</span>
							<span v-if="it.sku" class="mono dim">{{ it.sku }}</span>
						</div>
						<OzInput v-model.number="it.planned_qty" type="number" min="0" step="1" class="variant-qty" :aria-label="`${it.label} adedi`" />
					</div>
					<div class="variant-total" :class="{ 'is-off': itemsMismatch }">
						<span>Toplam</span>
						<span class="mono"><b>{{ itemsTotal }}</b> / {{ form.planned_qty || 0 }}</span>
					</div>
				</div>
			</section>

			<section v-if="requirements.length" class="section">
				<h3 class="section-title">Malzeme Gereksinimi <span class="dim">(önizleme)</span></h3>
				<div class="reqs">
					<div v-for="(r, i) in requirements" :key="i" class="req">
						<span class="req-name">{{ r.material_name }}</span>
						<span class="mono">{{ formatNumber(r.required_qty, 3) }} {{ r.unit }}</span>
						<span class="mono dim">≈ {{ formatMoney(r.line_cost) }}</span>
					</div>
					<div class="req req-total">
						<span class="req-name">Tahmini malzeme maliyeti</span>
						<span />
						<span class="mono">{{ formatMoney(requirementsCost) }}</span>
					</div>
				</div>
			</section>
			<OzAlert v-else-if="form.product_id && form.planned_qty > 0 && !previewLoading" tone="warning" title="Reçete bulunamadı">
				Bu ürünün aktif reçetesi yok; iş emri planlanırken hammadde düşülmez.
			</OzAlert>

			<section class="section">
				<h3 class="section-title">Üretim Rotası</h3>
				<p v-if="form.errors.steps" class="field-error">{{ form.errors.steps }}</p>
				<div v-for="(s, i) in form.steps" :key="i" class="step">
					<span class="step-seq">{{ s.sequence }}</span>
					<OzField label="Operasyon" :error="stepError(i, 'operation_id')">
						<OzSelect v-model="s.operation_id" :options="operationOptions" placeholder="Operasyon seçin" @update:model-value="onOperationChange(s)" />
					</OzField>
					<OzField label="Yer" :error="stepError(i, 'location_type')">
						<OzSelect v-model="s.location_type" :options="locationOptions" />
					</OzField>
					<OzField label="Birim Maliyet" :error="stepError(i, 'unit_cost')">
						<OzInput v-model.number="s.unit_cost" type="number" min="0" step="0.01" suffix="₺" />
					</OzField>
					<OzButton class="step-remove" size="sm" icon="trash" :disabled="form.steps.length === 1" :aria-label="`${s.sequence}. adımı kaldır`" @click="removeStep(i)" />
				</div>
				<OzButton size="sm" icon="plus" @click="addStep">Adım Ekle</OzButton>
			</section>

			<section class="section">
				<OzField label="Not" :error="form.errors.notes">
					<OzTextarea v-model="form.notes" :rows="2" placeholder="Üretime dair açıklama…" />
				</OzField>
			</section>
			<button type="submit" hidden />
		</form>
		<template #footer>
			<OzButton :disabled="form.processing" @click="wizardOpen = false">İptal</OzButton>
			<OzButton variant="primary" icon="check" :loading="form.processing" @click="submit">Oluştur</OzButton>
		</template>
	</OzDrawer>
</template>

<style scoped>
.stats { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; margin-bottom: 14px; }
@media (max-width: 1040px) { .stats { grid-template-columns: repeat(2, minmax(0, 1fr)); } }

.mono { font-family: ui-monospace, monospace; font-size: 12px; }
.dim { color: var(--oz-muted); font-weight: 400; }
.small { font-size: 12.5px; margin: 0; }
.due { display: inline-flex; align-items: center; gap: 8px; }
.detail-link { color: var(--oz-accent); text-decoration: none; padding: 4px 10px; border-radius: 6px; font-size: 12px; font-weight: 600; }
.detail-link:hover { background: var(--oz-accent-soft); }

.form { display: flex; flex-direction: column; gap: 18px; }
.section { display: flex; flex-direction: column; gap: 12px; }
.section + .section { padding-top: 18px; border-top: 1px solid var(--oz-border-soft); }
.section-head { display: flex; align-items: center; justify-content: space-between; gap: 10px; }
.section-title { margin: 0; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: var(--oz-muted); }
.field-error { margin: 0; font-size: 12px; color: var(--oz-danger); }
.row-3 { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; }

.variants { display: flex; flex-direction: column; border: 1px solid var(--oz-border-soft); border-radius: var(--oz-radius-sm); overflow: hidden; }
.variant { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 7px 12px; }
.variant + .variant { border-top: 1px solid var(--oz-border-soft); }
.variant-label { display: flex; flex-direction: column; font-size: 13px; color: var(--oz-ink); }
.variant-qty { width: 120px; }
.variant-total { display: flex; justify-content: space-between; padding: 8px 12px; font-size: 12.5px; background: var(--oz-surface-2); border-top: 1px solid var(--oz-border-soft); color: var(--oz-muted); }
.variant-total.is-off { color: var(--oz-warning); }

.reqs { display: flex; flex-direction: column; border: 1px solid var(--oz-border-soft); border-radius: var(--oz-radius-sm); overflow: hidden; }
.req { display: grid; grid-template-columns: minmax(0, 1fr) auto 110px; gap: 12px; align-items: center; padding: 7px 12px; font-size: 13px; }
.req + .req { border-top: 1px solid var(--oz-border-soft); }
.req > :last-child { text-align: right; }
.req-name { font-weight: 600; color: var(--oz-ink-soft); }
.req-total { background: var(--oz-surface-2); }

.step {
	display: grid; grid-template-columns: 26px minmax(0, 2fr) minmax(0, 1fr) minmax(0, 1fr) auto; gap: 10px; align-items: end;
	padding: 10px 12px; border: 1px solid var(--oz-border-soft); border-radius: var(--oz-radius-sm); background: var(--oz-surface-2);
}
.step-seq {
	align-self: center; width: 24px; height: 24px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center;
	font-size: 11px; font-weight: 700; background: var(--oz-accent-soft); color: var(--oz-accent);
}
.step-remove { margin-bottom: 2px; }

@media (max-width: 640px) {
	.row-3 { grid-template-columns: 1fr; }
	.step { grid-template-columns: 1fr 1fr; }
	.step-seq { grid-column: 1 / -1; }
	.step > :nth-child(2) { grid-column: 1 / -1; }
}
</style>
