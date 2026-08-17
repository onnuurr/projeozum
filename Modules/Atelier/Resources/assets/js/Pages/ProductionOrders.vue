<template>
	<Head title="İş Emirleri" />
	<div class="page-atelier-orders">
		<Breadcrumb
			:items="[
				{ label: 'Ana Sayfa', to: '/workflow', icon: 'home' },
				{ label: 'Üretim Atölyesi', to: '/atelier' },
				{ label: 'İş Emirleri' },
			]"
		/>

		<AtelierNav current="orders" />

		<PageHeader title="İş Emirleri">
			<template #subtitle><strong>{{ orders.length }}</strong> iş emri</template>
			<template v-if="can('atelier.production.manage')" #actions>
				<Button variant="primary" with-icon @click="showWizard = true">
					<template #leading><Plus :size="13" /></template>
					Yeni İş Emri
				</Button>
			</template>
		</PageHeader>

		<!-- Orders Table -->
		<Card title="İş Emri Listesi" body-class="p-0">
			<DataTable :columns="columns" :data="orders" row-key-field="id" empty-title="İş emri kaydı yok.">
				<template #code="{ value }"><span class="mono-chip">{{ value }}</span></template>
				<template #productName="{ value }"><span class="row-name">{{ value }}</span></template>
				<template #status="{ value }">
					<Badge :color="STATUS_COLORS[value]" :label="STATUS[value]" variant="tonal" />
				</template>
				<template #plannedQty="{ value }"><span class="num">{{ value }}</span></template>
				<template #producedQty="{ value }"><span class="num">{{ value }}</span></template>
				<template #totalCost="{ value }"><span class="num dim">{{ value }}</span></template>
				<template #actions="{ row }">
					<Button variant="secondary" size="sm" @click="router.get(`/atelier/production-orders/${row.id}`)">Detay</Button>
				</template>
			</DataTable>
		</Card>

		<!-- Wizard Modal -->
		<AppModal v-model="showWizard" title="Yeni İş Emri" size="lg">
			<form id="production-order-form" class="wizard-body" @submit.prevent="submit">

				<!-- Temel Bilgiler -->
				<div class="wizard-section">
					<div class="wizard-section-title">Temel Bilgiler</div>
					<div class="wizard-grid">
						<div class="form-row">
							<label class="form-label">Ürün <span class="req">*</span></label>
							<select v-model="form.product_id" @change="onProductOrQty" class="form-input">
								<option value="">Seçin…</option>
								<option v-for="p in products" :key="p.id" :value="p.id">{{ p.name }}</option>
							</select>
						</div>
						<div class="form-row">
							<label class="form-label">Adet <span class="req">*</span></label>
							<input v-model="form.planned_qty" @change="onProductOrQty" type="number" class="form-input" />
						</div>
						<div class="form-row">
							<label class="form-label">Depo</label>
							<select v-model="form.warehouse_id" class="form-input">
								<option value="">Seçin…</option>
								<option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
							</select>
						</div>
						<div class="form-row">
							<label class="form-label">Termin</label>
							<input v-model="form.due_date" type="date" class="form-input" />
						</div>
					</div>
				</div>

				<!-- Varyant Adetleri -->
				<div v-if="form.items.length" class="wizard-section">
					<div class="wizard-section-title">Varyant Adetleri</div>
					<div class="variant-rows">
						<div v-for="(it, i) in form.items" :key="i" class="variant-row">
							<span class="variant-label">{{ it.label }}</span>
							<input v-model="it.planned_qty" type="number" class="form-input variant-qty" />
						</div>
					</div>
				</div>

				<!-- Malzeme Gereksinimi -->
				<div v-if="requirements.length" class="wizard-section">
					<div class="wizard-section-title">Malzeme Gereksinimi (Önizleme)</div>
					<div class="requirements-box">
						<div v-for="(r, i) in requirements" :key="i" class="req-row">
							<span class="req-name">{{ r.material_name }}</span>
							<span class="req-qty">{{ r.required_qty }} {{ r.unit }}</span>
							<span class="req-cost dim">≈{{ r.line_cost }} ₺</span>
						</div>
					</div>
				</div>

				<!-- Rota -->
				<div class="wizard-section">
					<div class="wizard-section-title">Üretim Rotası</div>
					<div class="step-rows">
						<div v-for="(s, i) in form.steps" :key="i" class="step-row">
							<span class="step-seq">{{ s.sequence }}.</span>
							<select v-model="s.operation_id" @change="onOperationChange(s)" class="form-input step-op">
								<option value="">Operasyon…</option>
								<option v-for="op in operations" :key="op.id" :value="op.id">{{ op.name }}</option>
							</select>
							<select v-model="s.location_type" class="form-input step-loc">
								<option value="in_house">İç</option>
								<option value="fason">Fason</option>
							</select>
							<input v-model="s.unit_cost" type="number" step="0.01" class="form-input step-cost" placeholder="Birim ₺" />
							<Button type="button" variant="ghost" size="sm" class="step-remove" @click="removeStep(i)">Sil</Button>
						</div>
					</div>
					<Button type="button" variant="ghost" size="sm" with-icon style="margin-top: 8px;" @click="addStep">
						<template #leading><Plus :size="12" /></template>
						Adım Ekle
					</Button>
				</div>
			</form>
			<template #footer="{ close }">
				<Button variant="ghost" :disabled="form.processing" @click="close">Kapat</Button>
				<Button type="submit" form="production-order-form" variant="primary" :loading="form.processing">Oluştur</Button>
			</template>
		</AppModal>
	</div>
</template>

<script setup>
import { ref } from 'vue'
import { Head, useForm, router } from '@inertiajs/vue3'
import { Plus } from 'lucide-vue-next'
import axios from 'axios'
import AppLayout from '@/Layouts/AppLayout.vue'
import Breadcrumb from '@/Components/Breadcrumb.vue'
import PageHeader from '@/Components/PageHeader.vue'
import Card from '@/Components/Card.vue'
import DataTable from '@/Components/DataTable.vue'
import Badge from '@/Components/Badge.vue'
import Button from '@/Components/Button.vue'
import AppModal from '@/Components/AppModal.vue'
import AtelierNav from '../Components/AtelierNav.vue'
import { useCan } from '@/composables/useCan'

defineOptions({ layout: AppLayout })

const { can } = useCan()

const props = defineProps({ orders: Array, products: Array, warehouses: Array, operations: Array })

const columns = [
	{ key: 'code', label: 'Kod' },
	{ key: 'productName', label: 'Ürün' },
	{ key: 'status', label: 'Durum' },
	{ key: 'plannedQty', label: 'Planlanan', align: 'right' },
	{ key: 'producedQty', label: 'Üretilen', align: 'right' },
	{ key: 'dueDate', label: 'Termin' },
	{ key: 'totalCost', label: 'Maliyet', align: 'right' },
]

const STATUS = {
  draft: 'Taslak', planned: 'Planlandı', in_progress: 'Üretimde', completed: 'Tamamlandı', cancelled: 'İptal',
}
const STATUS_COLORS = { draft: 'neutral', planned: 'info', in_progress: 'success', completed: 'success', cancelled: 'danger' }

const showWizard = ref(false)
const variants = ref([])
const requirements = ref([])

const form = useForm({
  product_id: '', warehouse_id: '', planned_qty: 0, due_date: '', notes: '',
  items: [], steps: [],
})

async function onProductOrQty() {
  if (!form.product_id || !form.planned_qty) return
  const { data } = await axios.get('/atelier/production-orders/plan-preview', {
    params: { product_id: form.product_id, planned_qty: form.planned_qty },
  })
  variants.value = data.variants
  requirements.value = data.requirements
  // varyant satırlarını eşit dağıtma yapmadan 0 ile başlat
  form.items = data.variants.map(v => ({ product_variant_id: v.id, planned_qty: 0, label: `${v.size} / ${v.colorName}` }))
}

function addStep() {
  form.steps.push({ operation_id: '', sequence: form.steps.length + 1, location_type: 'in_house', fason_supplier_id: null, unit_cost: 0 })
}
function removeStep(i) { form.steps.splice(i, 1); form.steps.forEach((s, idx) => s.sequence = idx + 1) }

function onOperationChange(step) {
  const op = props.operations.find(o => o.id === Number(step.operation_id))
  if (op) { step.location_type = op.default_location; step.unit_cost = op.default_unit_cost }
}

function submit() {
  form.transform(d => ({
    ...d,
    items: d.items.map(({ product_variant_id, planned_qty }) => ({ product_variant_id, planned_qty })),
  })).post('/atelier/production-orders', {
    onSuccess: () => { showWizard.value = false; form.reset(); variants.value = []; requirements.value = [] },
  })
}
</script>

<style scoped>
.num { text-align: right; font-family: 'SF Mono', Menlo, Consolas, monospace; font-weight: 600; }
.dim { color: #888; font-weight: 500; }
.row-name { font-weight: 600; color: #1a1a2e; }
.mono-chip { font-family: 'SF Mono', Menlo, Consolas, monospace; font-size: 11.5px; background: #f0f0f5; padding: 2px 7px; border-radius: 5px; color: #555; }

.wizard-body { display: flex; flex-direction: column; gap: 0; }
.wizard-section { padding: 18px 24px; border-bottom: 1px solid #f5f5f8; }
.wizard-section:last-of-type { border-bottom: none; }
.wizard-section-title { font-size: 12px; font-weight: 700; color: #888; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 12px; }
.wizard-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; }

.form-row { display: flex; flex-direction: column; gap: 6px; }
.form-label { font-size: 12px; font-weight: 600; color: #1a1a2e; }
.form-label .req { color: #ef4444; }
.form-input { padding: 9px 12px; border: 1px solid #e8e8f0; border-radius: 8px; font-family: inherit; font-size: 13px; color: #1a1a2e; background: #fff; outline: none; transition: border-color .15s; width: 100%; box-sizing: border-box; }
.form-input:focus { border-color: var(--color-primary); }

.variant-rows { display: flex; flex-direction: column; gap: 8px; }
.variant-row { display: flex; align-items: center; gap: 12px; }
.variant-label { font-size: 13px; color: #444; font-weight: 500; min-width: 160px; }
.variant-qty { max-width: 120px; }

.requirements-box { background: #fffbeb; border: 1px solid #fde68a; border-radius: 10px; padding: 12px 14px; display: flex; flex-direction: column; gap: 6px; }
.req-row { display: flex; align-items: center; gap: 12px; font-size: 13px; }
.req-name { font-weight: 600; color: #1a1a2e; flex: 1; }
.req-qty { font-family: 'SF Mono', Menlo, Consolas, monospace; font-size: 12px; color: #444; }
.req-cost { font-size: 12px; }

.step-rows { display: flex; flex-direction: column; gap: 8px; }
.step-row { display: flex; align-items: center; gap: 8px; }
.step-seq { font-size: 13px; font-weight: 700; color: #888; min-width: 18px; }
.step-op { flex: 2; }
.step-loc { flex: 1; }
.step-cost { flex: 1; }
.step-remove { flex-shrink: 0; }

@media (max-width: 640px) {
	.wizard-grid { grid-template-columns: repeat(2, 1fr); }
}
</style>
