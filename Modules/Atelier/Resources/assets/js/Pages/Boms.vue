<template>
	<Head title="Reçeteler" />
	<div class="page-boms">
		<Breadcrumb
			:items="[
				{ label: 'Ana Sayfa', to: '/workflow', icon: 'home' },
				{ label: 'Üretim Atölyesi', to: '/atelier' },
				{ label: 'Reçeteler' },
			]"
		/>

		<AtelierNav current="boms" />

		<div class="page-header">
			<div>
				<h1 class="page-title">Reçeteler (BOM)</h1>
				<p class="page-subtitle"><strong>{{ boms.length }}</strong> reçete tanımı</p>
			</div>
		</div>

		<!-- New BOM form card -->
		<div v-if="can('atelier.bom.manage')" class="card" style="margin-bottom: 18px;">
			<div class="card-header">
				<h3>Yeni Reçete</h3>
			</div>
			<div class="form-section">
				<form @submit.prevent="submit" class="form-grid">
					<div class="form-row-inline">
						<div class="form-row">
							<label class="form-label">Ürün <span class="req">*</span></label>
							<select v-model="form.product_id" class="form-input">
								<option value="">Seçin…</option>
								<option v-for="p in products" :key="p.id" :value="p.id">{{ p.name }} ({{ p.sku }})</option>
							</select>
						</div>
						<div class="form-row" style="flex: 1">
							<label class="form-label">Reçete Adı <span class="req">*</span></label>
							<input v-model="form.name" type="text" class="form-input" placeholder="örn. Varsayılan Reçete" />
						</div>
					</div>

					<!-- Lines -->
					<div class="bom-lines">
						<div class="bom-lines-header">
							<span class="form-label">Malzeme Satırları</span>
						</div>
						<div v-for="(line, i) in form.lines" :key="i" class="bom-line">
							<div class="form-row" style="flex: 2">
								<label class="form-label">Malzeme</label>
								<select v-model="line.material_id" class="form-input">
									<option value="">Seçin…</option>
									<option v-for="m in materials" :key="m.id" :value="m.id">{{ m.name }} ({{ m.unit }})</option>
								</select>
							</div>
							<div class="form-row">
								<label class="form-label">Birim/adet</label>
								<input v-model="line.quantity_per_unit" type="number" step="0.0001" class="form-input" />
							</div>
							<div class="form-row">
								<label class="form-label">Fire %</label>
								<input v-model="line.waste_pct" type="number" step="0.01" class="form-input" />
							</div>
							<div class="form-row form-row-actions">
								<label class="form-label">&nbsp;</label>
								<button type="button" class="btn btn-ghost btn-sm" @click="removeLine(i)">Kaldır</button>
							</div>
						</div>
					</div>

					<div class="form-actions">
						<button type="button" class="btn btn-ghost" @click="addLine">+ Satır Ekle</button>
						<button type="submit" class="btn btn-primary" :disabled="form.processing">Kaydet</button>
					</div>
				</form>
			</div>
		</div>

		<!-- BOM list -->
		<div v-if="boms.length === 0" class="card">
			<div class="empty-state">Henüz reçete tanımlanmamış.</div>
		</div>

		<div v-for="b in boms" :key="b.id" class="card bom-card">
			<div class="card-header">
				<div class="bom-card-title">
					<span class="bom-product">{{ b.productName }}</span>
					<span class="bom-name-badge">{{ b.name }}</span>
				</div>
				<button v-if="can('atelier.bom.manage')" class="btn btn-ghost btn-sm btn-danger-ghost" @click="remove(b)">Sil</button>
			</div>
			<div class="table-scroll">
			<table class="data-table">
				<thead>
					<tr>
						<th>Malzeme</th>
						<th style="width: 15%; text-align: right;">Birim/adet</th>
						<th style="width: 15%; text-align: right;">Fire %</th>
					</tr>
				</thead>
				<tbody>
					<tr v-for="(l, i) in b.lines" :key="i">
						<td>{{ l.materialName }} <span class="unit-tag">{{ l.unit }}</span></td>
						<td class="num">{{ l.quantityPerUnit }}</td>
						<td class="num dim">{{ l.wastePct }}</td>
					</tr>
				</tbody>
			</table>
			</div>
		</div>
	</div>
</template>

<script setup>
import { inject } from 'vue'
import { Head, useForm, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Breadcrumb from '@/Components/Breadcrumb.vue'
import AtelierNav from '../Components/AtelierNav.vue'
import { useCan } from '@/composables/useCan'

defineOptions({ layout: AppLayout })

const { can } = useCan()
const $swal = inject('$swal')

const props = defineProps({ boms: Array, products: Array, materials: Array })

const form = useForm({ product_id: '', name: 'Varsayılan Reçete', lines: [{ material_id: '', quantity_per_unit: 1, waste_pct: 0 }] })

function addLine() { form.lines.push({ material_id: '', quantity_per_unit: 1, waste_pct: 0 }) }
function removeLine(i) { form.lines.splice(i, 1) }
function submit() {
  form.post('/atelier/boms', { onSuccess: () => { form.reset(); form.lines = [{ material_id: '', quantity_per_unit: 1, waste_pct: 0 }] } })
}
async function remove(b) {
  const ok = await $swal.dangerConfirm({ title: 'Reçete silinsin mi?', html: 'Bu reçete kalıcı olarak silinecek.' })
  if (ok) router.delete(`/atelier/boms/${b.id}`)
}
</script>

<style scoped>
.page-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 20px; gap: 16px; }
.page-title { font-size: 22px; font-weight: 700; color: #1a1a2e; }
.page-subtitle { font-size: 13px; color: #888; margin-top: 4px; }

.card { background: #fff; border-radius: 16px; border: 1px solid #ebebf0; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,.04); margin-bottom: 14px; }
.card-header { padding: 14px 18px; display: flex; align-items: center; gap: 12px; border-bottom: 1px solid #f0f0f5; }
.card-header h3 { font-size: 15px; font-weight: 700; color: #1a1a2e; }

.form-section { padding: 16px 18px; }
.form-grid { display: flex; flex-direction: column; gap: 14px; }
.form-row-inline { display: flex; gap: 12px; flex-wrap: wrap; }
.form-row { display: flex; flex-direction: column; gap: 6px; flex: 1; min-width: 150px; }
.form-row-actions { flex: 0 0 auto; min-width: unset; }
.form-label { font-size: 12px; font-weight: 600; color: #1a1a2e; }
.form-label .req { color: #ef4444; }
.form-input { padding: 9px 12px; border: 1px solid #e8e8f0; border-radius: 8px; font-family: inherit; font-size: 13px; color: #1a1a2e; background: #fff; outline: none; transition: border-color .15s; }
.form-input:focus { border-color: rgb(var(--color-primary)); }
.form-actions { display: flex; gap: 10px; justify-content: flex-end; padding-top: 4px; }

.bom-lines { display: flex; flex-direction: column; gap: 10px; }
.bom-lines-header { margin-bottom: 2px; }
.bom-line { display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap; padding: 12px 14px; background: #fafafe; border: 1px solid #f0f0f5; border-radius: 10px; }

.data-table { width: 100%; border-collapse: separate; border-spacing: 0; }
.data-table thead tr { background: #f8f8fc; }
.data-table th { text-align: left; padding: 12px 16px; font-size: 11px; font-weight: 600; color: #aaa; border-bottom: 1px solid #f0f0f5; text-transform: uppercase; letter-spacing: 0.04em; }
.data-table td { padding: 12px 16px; font-size: 13px; color: #444; border-bottom: 1px solid #f5f5f8; vertical-align: middle; }
.data-table tr:last-child td { border-bottom: none; }
.data-table tr:hover td { background: #fafafe; }
.num { text-align: right; font-family: 'SF Mono', Menlo, Consolas, monospace; font-weight: 600; }
.dim { color: #888; font-weight: 500; }
.unit-tag { font-size: 10.5px; color: #888; background: #f0f0f5; padding: 1px 6px; border-radius: 4px; margin-left: 5px; }

.empty-state { text-align: center; color: #aaa; padding: 32px; font-style: italic; font-size: 13px; }

.bom-card-title { display: flex; align-items: center; gap: 10px; flex: 1; }
.bom-product { font-weight: 700; color: #1a1a2e; font-size: 14px; }
.bom-name-badge { font-size: 11px; font-weight: 600; background: #eff6ff; color: #3b82f6; padding: 2px 9px; border-radius: 6px; }

.btn-danger-ghost { color: #dc2626 !important; }
.btn-danger-ghost:hover { background: #fee2e2 !important; }
</style>
