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

		<div class="page-header">
			<div>
				<h1 class="page-title">İş Emirleri</h1>
				<p class="page-subtitle"><strong>{{ orders.length }}</strong> iş emri</p>
			</div>
			<button @click="showWizard = true" class="btn btn-primary">+ Yeni İş Emri</button>
		</div>

		<!-- Orders Table -->
		<div class="card">
			<div class="card-header">
				<h3>İş Emri Listesi</h3>
			</div>
			<div class="table-scroll">
			<table class="data-table">
				<thead>
					<tr>
						<th style="width: 11%">Kod</th>
						<th>Ürün</th>
						<th style="width: 12%">Durum</th>
						<th style="width: 10%; text-align: right;">Planlanan</th>
						<th style="width: 10%; text-align: right;">Üretilen</th>
						<th style="width: 11%">Termin</th>
						<th style="width: 11%; text-align: right;">Maliyet</th>
						<th style="width: 6%"></th>
					</tr>
				</thead>
				<tbody>
					<tr v-if="!orders.length">
						<td colspan="8" class="empty-row">İş emri kaydı yok.</td>
					</tr>
					<tr v-for="o in orders" :key="o.id">
						<td><span class="mono-chip">{{ o.code }}</span></td>
						<td><span class="row-name">{{ o.productName }}</span></td>
						<td>
							<span class="status-pill" :class="`status-${o.status}`">
								<span class="dot"></span>{{ STATUS[o.status] }}
							</span>
						</td>
						<td class="num">{{ o.plannedQty }}</td>
						<td class="num">{{ o.producedQty }}</td>
						<td>{{ o.dueDate || '—' }}</td>
						<td class="num dim">{{ o.totalCost }}</td>
						<td>
							<div class="table-actions">
								<button class="table-action-btn" @click="router.get(`/atelier/production-orders/${o.id}`)">Detay</button>
							</div>
						</td>
					</tr>
				</tbody>
			</table>
			</div>
		</div>

		<!-- Wizard Modal -->
		<div v-if="showWizard" class="modal-overlay" @click.self="showWizard = false">
			<div class="modal-box">
				<div class="modal-head">
					<span class="modal-title">Yeni İş Emri</span>
					<button class="modal-close" @click="showWizard = false">✕</button>
				</div>

				<form @submit.prevent="submit" class="wizard-body">

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
								<button type="button" @click="removeStep(i)" class="btn btn-ghost btn-sm step-remove">Sil</button>
							</div>
						</div>
						<button type="button" @click="addStep" class="btn btn-ghost btn-sm" style="margin-top: 8px;">+ Adım Ekle</button>
					</div>

					<div class="modal-foot">
						<button type="button" class="btn btn-ghost" @click="showWizard = false">Kapat</button>
						<button type="submit" class="btn btn-primary" :disabled="form.processing">Oluştur</button>
					</div>
				</form>
			</div>
		</div>
	</div>
</template>

<script setup>
import { ref } from 'vue'
import { Head, useForm, router } from '@inertiajs/vue3'
import axios from 'axios'
import AppLayout from '@/Layouts/AppLayout.vue'
import Breadcrumb from '@/Components/Breadcrumb.vue'
import AtelierNav from '../Components/AtelierNav.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({ orders: Array, products: Array, warehouses: Array, operations: Array })

const STATUS = {
  draft: 'Taslak', planned: 'Planlandı', in_progress: 'Üretimde', completed: 'Tamamlandı', cancelled: 'İptal',
}

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
.page-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 20px; gap: 16px; }
.page-title { font-size: 22px; font-weight: 700; color: #1a1a2e; }
.page-subtitle { font-size: 13px; color: #888; margin-top: 4px; }

.card { background: #fff; border-radius: 16px; border: 1px solid #ebebf0; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,.04); }
.card-header { padding: 14px 18px; display: flex; align-items: center; gap: 12px; border-bottom: 1px solid #f0f0f5; }
.card-header h3 { font-size: 15px; font-weight: 700; color: #1a1a2e; }

.data-table { width: 100%; border-collapse: separate; border-spacing: 0; }
.data-table thead tr { background: #f8f8fc; }
.data-table th { text-align: left; padding: 12px 16px; font-size: 11px; font-weight: 600; color: #aaa; border-bottom: 1px solid #f0f0f5; text-transform: uppercase; letter-spacing: 0.04em; }
.data-table td { padding: 12px 16px; font-size: 13px; color: #444; border-bottom: 1px solid #f5f5f8; vertical-align: middle; }
.data-table tr:last-child td { border-bottom: none; }
.data-table tr:hover td { background: #fafafe; }
.empty-row { text-align: center !important; color: #aaa; padding: 32px 0 !important; font-style: italic; }
.num { text-align: right; font-family: 'SF Mono', Menlo, Consolas, monospace; font-weight: 600; }
.dim { color: #888; font-weight: 500; }
.row-name { font-weight: 600; color: #1a1a2e; }
.mono-chip { font-family: 'SF Mono', Menlo, Consolas, monospace; font-size: 11.5px; background: #f0f0f5; padding: 2px 7px; border-radius: 5px; color: #555; }

.status-pill { display: inline-flex; align-items: center; gap: 5px; padding: 3px 10px; border-radius: 999px; font-size: 11px; font-weight: 600; }
.status-pill .dot { width: 5px; height: 5px; border-radius: 50%; background: currentColor; }
.status-draft { background: #f0f0f5; color: #888; }
.status-planned { background: #eff6ff; color: #3b82f6; }
.status-in_progress { background: #dcfce7; color: #16a34a; }
.status-completed { background: #f0fdf4; color: #15803d; }
.status-cancelled { background: #fee2e2; color: #dc2626; }

.table-actions { display: flex; gap: 4px; justify-content: flex-end; }
.table-action-btn { background: #f3f4f6; border: none; cursor: pointer; font-size: 12px; padding: 5px 12px; border-radius: 6px; color: #6b7280; font-weight: 600; transition: all .15s; }
.table-action-btn:hover { background: rgb(var(--color-primary-soft)); color: rgb(var(--color-primary)); }

/* Modal */
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,.4); display: flex; align-items: flex-start; justify-content: center; overflow: auto; padding: 40px 16px; z-index: 9000; }
.modal-box { background: #fff; border-radius: 16px; width: 900px; max-width: calc(100vw - 32px); display: flex; flex-direction: column; box-shadow: 0 8px 40px rgba(0,0,0,.15); overflow: hidden; }
.modal-head { display: flex; align-items: center; justify-content: space-between; padding: 18px 24px; border-bottom: 1px solid #f0f0f5; flex-shrink: 0; }
.modal-title { font-size: 17px; font-weight: 700; color: #1a1a2e; }
.modal-close { background: none; border: none; cursor: pointer; font-size: 15px; color: #888; padding: 2px 6px; border-radius: 6px; transition: all .15s; }
.modal-close:hover { background: #f0f0f5; color: #1a1a2e; }
.modal-foot { display: flex; gap: 8px; justify-content: flex-end; padding: 16px 24px; border-top: 1px solid #f0f0f5; }

.wizard-body { display: flex; flex-direction: column; gap: 0; }
.wizard-section { padding: 18px 24px; border-bottom: 1px solid #f5f5f8; }
.wizard-section:last-of-type { border-bottom: none; }
.wizard-section-title { font-size: 12px; font-weight: 700; color: #888; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 12px; }
.wizard-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; }

.form-row { display: flex; flex-direction: column; gap: 6px; }
.form-label { font-size: 12px; font-weight: 600; color: #1a1a2e; }
.form-label .req { color: #ef4444; }
.form-input { padding: 9px 12px; border: 1px solid #e8e8f0; border-radius: 8px; font-family: inherit; font-size: 13px; color: #1a1a2e; background: #fff; outline: none; transition: border-color .15s; width: 100%; box-sizing: border-box; }
.form-input:focus { border-color: rgb(var(--color-primary)); }

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
