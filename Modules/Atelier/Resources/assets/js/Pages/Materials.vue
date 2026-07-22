<template>
	<Head title="Hammaddeler" />
	<div class="page-materials">
		<Breadcrumb
			:items="[
				{ label: 'Ana Sayfa', to: '/workflow', icon: 'home' },
				{ label: 'Üretim Atölyesi', to: '/atelier' },
				{ label: 'Hammaddeler' },
			]"
		/>

		<AtelierNav current="materials" />

		<div class="page-header">
			<div>
				<h1 class="page-title">Hammaddeler</h1>
				<p class="page-subtitle"><strong>{{ materials.length }}</strong> hammadde tanımı</p>
			</div>
		</div>

		<!-- Form Card -->
		<div v-if="can('atelier.material.manage')" class="card" style="margin-bottom: 18px;">
			<div class="card-header">
				<h3>{{ editing ? 'Hammadde Düzenle' : 'Yeni Hammadde' }}</h3>
			</div>
			<div class="form-section">
				<form @submit.prevent="submit" class="form-grid-inline">
					<div class="form-row">
						<label class="form-label">Kod <span class="req">*</span></label>
						<input v-model="form.code" type="text" class="form-input" placeholder="örn. KM-001" />
					</div>
					<div class="form-row" style="flex: 2">
						<label class="form-label">Ad <span class="req">*</span></label>
						<input v-model="form.name" type="text" class="form-input" placeholder="Hammadde adı" />
					</div>
					<div class="form-row">
						<label class="form-label">Tür</label>
						<select v-model="form.type" class="form-input">
							<option value="kumas">Kumaş</option>
							<option value="aksesuar">Aksesuar</option>
							<option value="etiket">Etiket</option>
						</select>
					</div>
					<div class="form-row">
						<label class="form-label">Birim</label>
						<select v-model="form.unit" class="form-input">
							<option value="metre">Metre</option>
							<option value="adet">Adet</option>
							<option value="kg">Kg</option>
						</select>
					</div>
					<div class="form-row">
						<label class="form-label">Birim Maliyet</label>
						<input v-model="form.unit_cost" type="number" step="0.01" class="form-input" placeholder="0.00" />
					</div>
					<div class="form-row form-row-actions">
						<label class="form-label">&nbsp;</label>
						<div class="btn-group">
							<button type="submit" class="btn btn-primary" :disabled="form.processing">
								{{ editing ? 'Güncelle' : 'Ekle' }}
							</button>
							<button v-if="editing" type="button" class="btn btn-ghost" @click="reset">Vazgeç</button>
						</div>
					</div>
				</form>
			</div>
		</div>

		<!-- Table Card -->
		<div class="card">
			<div class="card-header">
				<h3>Hammadde Listesi</h3>
			</div>
			<div class="table-scroll">
			<table class="data-table">
				<thead>
					<tr>
						<th style="width: 10%">Kod</th>
						<th>Ad</th>
						<th style="width: 12%">Tür</th>
						<th style="width: 10%">Birim</th>
						<th style="width: 10%; text-align: right;">Stok</th>
						<th style="width: 12%; text-align: right;">Maliyet</th>
						<th style="width: 12%"></th>
					</tr>
				</thead>
				<tbody>
					<tr v-if="materials.length === 0">
						<td colspan="7" class="empty-row">Hammadde kaydı yok.</td>
					</tr>
					<tr v-for="m in materials" :key="m.id">
						<td><span class="mono-chip">{{ m.code }}</span></td>
						<td><span class="row-name">{{ m.name }}</span></td>
						<td>
							<span class="type-pill">{{ m.type }}</span>
						</td>
						<td>{{ m.unit }}</td>
						<td class="num">{{ m.currentStock }}</td>
						<td class="num dim">{{ m.unitCost }}</td>
						<td>
							<div v-if="can('atelier.material.manage')" class="table-actions">
								<button class="table-action-btn" @click="openSpecs(m)" title="Özellikler (AI için)">🧵</button>
									<button class="table-action-btn" @click="openMove(m)" title="Stok Hareketi">⇅</button>
								<button class="table-action-btn" @click="edit(m)" title="Düzenle">✏️</button>
								<button class="table-action-btn danger" @click="remove(m)" title="Sil">🗑️</button>
							</div>
						</td>
					</tr>
				</tbody>
			</table>
			</div>
		</div>

		<!-- Materyal Özellikleri Modal (AI için) -->
		<div v-if="specs.material_id" class="modal-overlay" @click.self="specs.material_id = null">
			<div class="modal-box modal-box-wide">
				<div class="modal-head">
					<span class="modal-title">Materyal Özellikleri — <em>{{ specs.material_name }}</em></span>
					<button class="modal-close" @click="specs.material_id = null">✕</button>
				</div>
				<p class="modal-desc">AI ürün açıklaması bu bilgilerden üretilir. Kumaş için kompozisyon ve gramaj yeterli.</p>
				<MaterialSpecsPanel v-model="specs.data" />
				<div class="modal-foot">
					<button type="button" class="btn btn-ghost" @click="specs.material_id = null">Kapat</button>
					<button type="button" class="btn btn-primary" @click="submitSpecs" :disabled="specsBusy">Kaydet</button>
				</div>
			</div>
		</div>

		<!-- Stok Hareketi Modal -->
		<div v-if="move.material_id" class="modal-overlay" @click.self="move.material_id = null">
			<div class="modal-box">
				<div class="modal-head">
					<span class="modal-title">Stok Hareketi</span>
					<button class="modal-close" @click="move.material_id = null">✕</button>
				</div>
				<form @submit.prevent="submitMove" class="form-grid">
					<div class="form-row">
						<label class="form-label">Tip <span class="req">*</span></label>
						<select v-model="move.type" class="form-input">
							<option value="in">Giriş</option>
							<option value="out">Çıkış</option>
							<option value="adjust">Düzeltme</option>
						</select>
					</div>
					<div class="form-row">
						<label class="form-label">Miktar <span class="req">*</span></label>
						<input v-model="move.quantity" type="number" step="0.001" class="form-input" />
						<span v-if="move.errors.quantity" class="form-error">{{ move.errors.quantity }}</span>
					</div>
					<div class="form-row">
						<label class="form-label">Sebep</label>
						<select v-model="move.reason" class="form-input">
							<option value="purchase">Satın alma</option>
							<option value="consume">Tüketim</option>
							<option value="scrap">Fire</option>
							<option value="correction">Düzeltme</option>
						</select>
					</div>
					<div class="modal-foot">
						<button type="button" class="btn btn-ghost" @click="move.material_id = null">Kapat</button>
						<button type="submit" class="btn btn-primary" :disabled="move.processing">Kaydet</button>
					</div>
				</form>
			</div>
		</div>
	</div>
</template>

<script setup>
import { ref, inject } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Breadcrumb from '@/Components/Breadcrumb.vue'
import AtelierNav from '../Components/AtelierNav.vue'
import MaterialSpecsPanel from '../Components/MaterialSpecsPanel.vue'
import axios from 'axios'
import { useCan } from '@/composables/useCan'

defineOptions({ layout: AppLayout })

const { can } = useCan()
const $swal = inject('$swal')

const props = defineProps({ materials: Array })

const form = useForm({ id: null, code: '', name: '', type: 'kumas', unit: 'metre', unit_cost: 0, is_active: true })
const editing = ref(false)

function edit(m) {
  editing.value = true
  form.id = m.id; form.code = m.code; form.name = m.name; form.type = m.type
  form.unit = m.unit; form.unit_cost = m.unitCost; form.is_active = m.isActive
}
function reset() {
  editing.value = false
  form.reset(); form.id = null
}
function submit() {
  if (editing.value) {
    form.put(`/atelier/materials/${form.id}`, { onSuccess: reset })
  } else {
    form.post('/atelier/materials', { onSuccess: reset })
  }
}
async function remove(m) {
  const ok = await $swal.dangerConfirm({ title: 'Silinsin mi?', html: `<b>${m.name}</b> malzemesi kalıcı olarak silinecek.` })
  if (ok) router.delete(`/atelier/materials/${m.id}`)
}

const move = useForm({ material_id: null, type: 'in', quantity: 0, reason: 'purchase', note: '' })
function openMove(m) { move.material_id = m.id; move.type = 'in'; move.quantity = 0; move.reason = 'purchase' }
function submitMove() {
  move.post('/atelier/materials/movement', { onSuccess: () => { move.reset(); move.material_id = null } })
}

const showToast = inject('showToast')
const specs = ref({ material_id: null, material_name: '', data: {} })
const specsBusy = ref(false)
function openSpecs(m) {
  specs.value = { material_id: m.id, material_name: m.name, data: { ...(m.specs || {}) } }
}
async function submitSpecs() {
  if (!specs.value.material_id || specsBusy.value) return
  specsBusy.value = true
  try {
    await axios.put(`/atelier/materials/${specs.value.material_id}/specs`, { specs: specs.value.data })
    showToast?.({ type: 'success', title: 'Özellikler kaydedildi' })
    specs.value.material_id = null
    router.reload({ only: ['materials'], preserveScroll: true })
  } catch (e) {
    const msg = e?.response?.data?.errors?.specs?.[0] || 'Kayıt başarısız.'
    showToast?.({ type: 'error', title: 'Hata', message: msg })
  } finally {
    specsBusy.value = false
  }
}
</script>

<style scoped>
.page-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 20px; gap: 16px; }
.page-title { font-size: 22px; font-weight: 700; color: #1a1a2e; }
.page-subtitle { font-size: 13px; color: #888; margin-top: 4px; }

.card { background: #fff; border-radius: 16px; border: 1px solid #ebebf0; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,.04); }
.card-header { padding: 14px 18px; display: flex; align-items: center; gap: 12px; border-bottom: 1px solid #f0f0f5; }
.card-header h3 { font-size: 15px; font-weight: 700; color: #1a1a2e; }

.form-section { padding: 16px 18px; }
.form-grid-inline { display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end; }
.form-grid { display: flex; flex-direction: column; gap: 14px; }
.form-row { display: flex; flex-direction: column; gap: 6px; flex: 1; min-width: 120px; }
.form-row-actions { flex: 0 0 auto; min-width: unset; }
.form-label { font-size: 12px; font-weight: 600; color: #1a1a2e; }
.form-label .req { color: #ef4444; }
.form-input { padding: 9px 12px; border: 1px solid #e8e8f0; border-radius: 8px; font-family: inherit; font-size: 13px; color: #1a1a2e; background: #fff; outline: none; transition: border-color .15s; }
.form-input:focus { border-color: rgb(var(--color-primary)); }
.form-error { font-size: 11.5px; color: #ef4444; }
.btn-group { display: flex; gap: 8px; }

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
.type-pill { display: inline-block; padding: 2px 9px; background: #eff6ff; color: #3b82f6; border-radius: 6px; font-size: 11px; font-weight: 600; text-transform: capitalize; }

.table-actions { display: flex; gap: 4px; justify-content: flex-end; }
.table-action-btn { background: #f3f4f6; border: none; cursor: pointer; font-size: 13px; padding: 5px 9px; border-radius: 6px; color: #6b7280; transition: all .15s; }
.table-action-btn:hover { background: rgb(var(--color-primary-soft)); color: rgb(var(--color-primary)); }
.table-action-btn.danger:hover { background: #fee2e2; color: #dc2626; }

/* Modal */
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,.4); display: flex; align-items: center; justify-content: center; z-index: 9000; }
.modal-box { background: #fff; border-radius: 16px; padding: 24px; width: 360px; max-width: calc(100vw - 32px); display: flex; flex-direction: column; gap: 16px; box-shadow: 0 8px 40px rgba(0,0,0,.15); }
.modal-box-wide { width: 560px; }
.modal-desc { font-size: 12.5px; color: #888; margin: -6px 0 2px; }
.modal-title em { color: rgb(var(--color-primary)); font-style: normal; font-weight: 700; }
.modal-head { display: flex; align-items: center; justify-content: space-between; }
.modal-title { font-size: 16px; font-weight: 700; color: #1a1a2e; }
.modal-close { background: none; border: none; cursor: pointer; font-size: 15px; color: #888; padding: 2px 6px; border-radius: 6px; transition: all .15s; }
.modal-close:hover { background: #f0f0f5; color: #1a1a2e; }
.modal-foot { display: flex; gap: 8px; justify-content: flex-end; padding-top: 4px; }
</style>
