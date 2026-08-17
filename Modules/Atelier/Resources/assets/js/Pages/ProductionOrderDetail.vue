<template>
	<Head :title="`${order.code} — İş Emri`" />
	<div class="page-atelier-order-detail">
		<Breadcrumb
			:items="[
				{ label: 'Ana Sayfa', to: '/workflow', icon: 'home' },
				{ label: 'Üretim Atölyesi', to: '/atelier' },
				{ label: 'İş Emirleri', to: '/atelier/production-orders' },
				{ label: order.code },
			]"
		/>

		<AtelierNav current="orders" />

		<!-- Page Header -->
		<PageHeader :title="`${order.code} — ${order.productName}`">
			<template #subtitle>
				<span class="header-subtitle">
					<Badge :color="STATUS_COLORS[order.status]" :label="STATUS[order.status]" variant="tonal" />
					<span class="sep">•</span>
					Depo: {{ order.warehouse }}
				</span>
			</template>
			<template v-if="can('atelier.production.manage')" #actions>
				<Button v-if="order.status === 'draft'" variant="warning" @click="plan">Planla (Hammadde düş)</Button>
				<Button v-if="['planned','in_progress'].includes(order.status)" variant="success" @click="complete">Tamamla → Stoğa al</Button>
				<Button v-if="!['completed','cancelled'].includes(order.status)" variant="outline-danger" @click="cancel">İptal</Button>
			</template>
		</PageHeader>

		<!-- Cost Summary -->
		<div class="stat-row">
			<div class="stat-card">
				<div class="stat-label">Malzeme</div>
				<div class="stat-value">{{ order.materialCost }} <span class="stat-unit">₺</span></div>
			</div>
			<div class="stat-card">
				<div class="stat-label">Fason</div>
				<div class="stat-value">{{ order.fasonCost }} <span class="stat-unit">₺</span></div>
			</div>
			<div class="stat-card">
				<div class="stat-label">İşçilik</div>
				<div class="stat-value">{{ order.laborCost }} <span class="stat-unit">₺</span></div>
			</div>
			<div class="stat-card stat-card-primary">
				<div class="stat-label">Toplam</div>
				<div class="stat-value">{{ order.totalCost }} <span class="stat-unit">₺</span></div>
			</div>
			<div class="stat-card">
				<div class="stat-label">Birim</div>
				<div class="stat-value">{{ order.unitCost }} <span class="stat-unit">₺</span></div>
			</div>
		</div>

		<!-- Variant Production -->
		<Card title="Varyant Üretim Miktarları" body-class="items-body" style="margin-bottom: 16px;">
			<div v-for="i in order.items" :key="i.id" class="item-row">
				<span class="item-label">{{ i.size }} / {{ i.colorName }}</span>
				<span class="item-plan dim">Plan: <strong>{{ i.plannedQty }}</strong></span>
				<div class="form-row item-field">
					<label class="form-label">Üretilen</label>
					<input v-model="itemForms[i.id].produced_qty" type="number" class="form-input" />
				</div>
				<div class="form-row item-field">
					<label class="form-label">Fire</label>
					<input v-model="itemForms[i.id].scrap_qty" type="number" class="form-input" />
				</div>
			</div>
			<div v-if="can('atelier.production.manage')" class="items-footer">
				<Button size="sm" @click="saveItems">Üretim Miktarlarını Kaydet</Button>
			</div>
		</Card>

		<!-- Steps / Route -->
		<Card title="Rota / Üretim Aşamaları" body-class="steps-body">
			<div v-for="s in order.steps" :key="s.id" class="step-row">
				<div class="step-meta">
					<span class="step-seq">{{ s.sequence }}</span>
					<div class="step-info">
						<span class="step-name">{{ s.operationName }}</span>
						<Badge :color="s.locationType === 'fason' ? 'warning' : 'info'" :label="s.locationType === 'fason' ? 'Fason' : 'İç'" />
					</div>
				</div>
				<div class="step-fields">
					<div class="form-row">
						<label class="form-label">Durum</label>
						<select v-model="stepForms[s.id].status" class="form-input">
							<option value="pending">Bekliyor</option>
							<option value="in_progress">Devam</option>
							<option value="done">Bitti</option>
						</select>
					</div>
					<div class="form-row">
						<label class="form-label">Giren</label>
						<input v-model="stepForms[s.id].input_qty" type="number" class="form-input" />
					</div>
					<div class="form-row">
						<label class="form-label">Çıkan</label>
						<input v-model="stepForms[s.id].output_qty" type="number" class="form-input" />
					</div>
					<div class="form-row">
						<label class="form-label">Fire</label>
						<input v-model="stepForms[s.id].scrap_qty" type="number" class="form-input" />
					</div>
					<div class="form-row">
						<label class="form-label">Birim ₺</label>
						<input v-model="stepForms[s.id].unit_cost" type="number" step="0.01" class="form-input" />
					</div>
					<div v-if="s.locationType === 'fason'" class="form-row">
						<label class="form-label">Fasoncu</label>
						<select v-model="stepForms[s.id].fason_supplier_id" class="form-input">
							<option :value="null">Seçin…</option>
							<option v-for="f in fasonSuppliers" :key="f.id" :value="f.id">{{ f.name }}</option>
						</select>
					</div>
				</div>
				<div v-if="can('atelier.production.manage')" class="step-save">
					<Button size="sm" @click="saveStep(s.id)">Kaydet</Button>
				</div>
			</div>
			<div v-if="!order.steps.length" class="empty-steps">Rota adımı tanımlanmamış.</div>
		</Card>
	</div>
</template>

<script setup>
import { reactive, inject } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Breadcrumb from '@/Components/Breadcrumb.vue'
import PageHeader from '@/Components/PageHeader.vue'
import Card from '@/Components/Card.vue'
import Badge from '@/Components/Badge.vue'
import Button from '@/Components/Button.vue'
import AtelierNav from '../Components/AtelierNav.vue'
import { useCan } from '@/composables/useCan'

defineOptions({ layout: AppLayout })

const { can } = useCan()
const $swal = inject('$swal')

const props = defineProps({ order: Object, fasonSuppliers: Array })

const STATUS = { draft: 'Taslak', planned: 'Planlandı', in_progress: 'Üretimde', completed: 'Tamamlandı', cancelled: 'İptal' }
const STATUS_COLORS = { draft: 'neutral', planned: 'info', in_progress: 'success', completed: 'success', cancelled: 'danger' }
const STEP_STATUS = { pending: 'Bekliyor', in_progress: 'Devam', done: 'Bitti' }

const stepForms = reactive(Object.fromEntries(props.order.steps.map(s => [s.id, {
  status: s.status, input_qty: s.inputQty, output_qty: s.outputQty, scrap_qty: s.scrapQty,
  fason_supplier_id: null, unit_cost: s.unitCost, note: '',
}])))

const itemForms = reactive(Object.fromEntries(props.order.items.map(i => [i.id, {
  produced_qty: i.producedQty, scrap_qty: i.scrapQty,
}])))

function saveStep(id) {
  router.put(`/atelier/production-orders/${props.order.id}/steps/${id}`, stepForms[id], { preserveScroll: true })
}
function saveItems() {
  const items = props.order.items.map(i => ({ id: i.id, produced_qty: itemForms[i.id].produced_qty, scrap_qty: itemForms[i.id].scrap_qty }))
  router.put(`/atelier/production-orders/${props.order.id}/items`, { items }, { preserveScroll: true })
}
function plan() { router.post(`/atelier/production-orders/${props.order.id}/plan`, {}, { preserveScroll: true }) }
function complete() { router.post(`/atelier/production-orders/${props.order.id}/complete`, {}, { preserveScroll: true }) }
async function cancel() {
  const ok = await $swal.dangerConfirm({ title: 'İş emri iptal edilsin mi?', html: 'Bu işlem geri alınamaz.', confirmText: 'İptal Et' })
  if (ok) router.post(`/atelier/production-orders/${props.order.id}/cancel`, {}, { preserveScroll: true })
}
</script>

<style scoped>
.header-subtitle { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.sep { color: #d0d0db; }

/* Stat row */
.stat-row { display: grid; grid-template-columns: repeat(5, 1fr); gap: 12px; margin-bottom: 20px; }
.stat-card { background: #fff; border-radius: 14px; border: 1px solid #ebebf0; padding: 16px 18px; box-shadow: 0 1px 4px rgba(0,0,0,.04); display: flex; flex-direction: column; gap: 6px; }
.stat-card-primary { border-color: var(--color-primary); background: var(--color-primary-soft); }
.stat-label { font-size: 11px; font-weight: 600; color: #888; text-transform: uppercase; letter-spacing: 0.04em; }
.stat-value { font-size: 24px; font-weight: 800; color: #1a1a2e; line-height: 1; }
.stat-unit { font-size: 14px; font-weight: 500; color: #888; }

/* Form basics */
.form-row { display: flex; flex-direction: column; gap: 5px; }
.form-label { font-size: 11px; font-weight: 600; color: #888; text-transform: uppercase; letter-spacing: 0.03em; }
.form-input { padding: 8px 10px; border: 1px solid #e8e8f0; border-radius: 8px; font-family: inherit; font-size: 13px; color: #1a1a2e; background: #fff; outline: none; transition: border-color .15s; width: 100%; box-sizing: border-box; }
.form-input:focus { border-color: var(--color-primary); }

/* Variant items */
.items-body { padding: 16px 18px; display: flex; flex-direction: column; gap: 10px; }
.item-row { display: flex; align-items: flex-end; gap: 12px; padding: 12px 14px; background: #fafafe; border-radius: 10px; border: 1px solid #f0f0f5; flex-wrap: wrap; }
.item-label { font-size: 13px; font-weight: 600; color: #1a1a2e; min-width: 150px; flex: 1; }
.item-plan { font-size: 12px; min-width: 80px; }
.item-field { min-width: 100px; max-width: 130px; }
.items-footer { display: flex; justify-content: flex-end; padding-top: 4px; }
.dim { color: #888; }

/* Steps */
.steps-body { display: flex; flex-direction: column; }
.step-row { display: flex; align-items: flex-start; gap: 16px; padding: 16px 18px; border-bottom: 1px solid #f5f5f8; flex-wrap: wrap; }
.step-row:last-child { border-bottom: none; }
.step-meta { display: flex; align-items: flex-start; gap: 10px; min-width: 200px; flex: 0 0 auto; }
.step-seq { width: 28px; height: 28px; border-radius: 50%; background: var(--color-primary); color: var(--color-on-primary); display: inline-flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 700; flex-shrink: 0; margin-top: 2px; }
.step-info { display: flex; flex-direction: column; gap: 4px; }
.step-name { font-size: 13px; font-weight: 700; color: #1a1a2e; }
.step-fields { display: flex; gap: 10px; flex: 1; flex-wrap: wrap; }
.step-fields .form-row { min-width: 90px; max-width: 120px; }
.step-save { display: flex; align-items: flex-end; flex-shrink: 0; padding-bottom: 0; }
.empty-steps { padding: 32px; text-align: center; color: #aaa; font-style: italic; font-size: 13px; }

@media (max-width: 640px) {
	.stat-row { grid-template-columns: repeat(2, 1fr); }
}
</style>
