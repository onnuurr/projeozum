<template>
	<Head title="Operasyonlar" />
	<div class="page-operations">
		<Breadcrumb
			:items="[
				{ label: 'Ana Sayfa', to: '/workflow', icon: 'home' },
				{ label: 'Üretim Atölyesi', to: '/atelier' },
				{ label: 'Operasyonlar' },
			]"
		/>

		<AtelierNav current="operations" />

		<div class="page-header">
			<div>
				<h1 class="page-title">Operasyonlar</h1>
				<p class="page-subtitle"><strong>{{ operations.length }}</strong> operasyon tanımı</p>
			</div>
		</div>

		<!-- Form Card -->
		<div class="card" style="margin-bottom: 18px;">
			<div class="card-header">
				<h3>{{ editing ? 'Operasyon Düzenle' : 'Yeni Operasyon' }}</h3>
			</div>
			<div class="form-section">
				<form @submit.prevent="submit" class="form-grid-inline">
					<div class="form-row">
						<label class="form-label">Kod <span class="req">*</span></label>
						<input v-model="form.code" type="text" class="form-input" placeholder="örn. OP-01" />
					</div>
					<div class="form-row" style="flex: 2">
						<label class="form-label">Ad <span class="req">*</span></label>
						<input v-model="form.name" type="text" class="form-input" placeholder="Operasyon adı" />
					</div>
					<div class="form-row">
						<label class="form-label">Varsayılan Yer</label>
						<select v-model="form.default_location" class="form-input">
							<option value="in_house">İç atölye</option>
							<option value="fason">Fason</option>
						</select>
					</div>
					<div class="form-row">
						<label class="form-label">Birim İşçilik</label>
						<input v-model="form.default_unit_cost" type="number" step="0.01" class="form-input" placeholder="0.00" />
					</div>
					<div class="form-row" style="flex: 0 0 80px">
						<label class="form-label">Sıra</label>
						<input v-model="form.sort_order" type="number" class="form-input" placeholder="0" />
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
				<h3>Operasyon Listesi</h3>
			</div>
			<div class="table-scroll">
			<table class="data-table">
				<thead>
					<tr>
						<th style="width: 10%">Kod</th>
						<th>Ad</th>
						<th style="width: 14%">Yer</th>
						<th style="width: 14%; text-align: right;">Birim İşçilik</th>
						<th style="width: 8%; text-align: right;">Sıra</th>
						<th style="width: 12%"></th>
					</tr>
				</thead>
				<tbody>
					<tr v-if="operations.length === 0">
						<td colspan="6" class="empty-row">Operasyon kaydı yok.</td>
					</tr>
					<tr v-for="o in operations" :key="o.id">
						<td><span class="mono-chip">{{ o.code }}</span></td>
						<td><span class="row-name">{{ o.name }}</span></td>
						<td>
							<span class="loc-pill" :class="o.defaultLocation === 'fason' ? 'loc-fason' : 'loc-inhouse'">
								{{ o.defaultLocation === 'fason' ? 'Fason' : 'İç' }}
							</span>
						</td>
						<td class="num">{{ o.defaultUnitCost }}</td>
						<td class="num dim">{{ o.sortOrder }}</td>
						<td>
							<div class="table-actions">
								<button class="table-action-btn" @click="edit(o)" title="Düzenle">✏️</button>
								<button class="table-action-btn danger" @click="remove(o)" title="Sil">🗑️</button>
							</div>
						</td>
					</tr>
				</tbody>
			</table>
			</div>
		</div>
	</div>
</template>

<script setup>
import { ref, inject } from 'vue'
import { Head, useForm, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Breadcrumb from '@/Components/Breadcrumb.vue'
import AtelierNav from '../Components/AtelierNav.vue'

defineOptions({ layout: AppLayout })

const $swal = inject('$swal')

const props = defineProps({ operations: Array })
const form = useForm({ id: null, code: '', name: '', default_location: 'in_house', default_unit_cost: 0, sort_order: 0 })
const editing = ref(false)

function edit(o) {
  editing.value = true
  form.id = o.id; form.code = o.code; form.name = o.name
  form.default_location = o.defaultLocation; form.default_unit_cost = o.defaultUnitCost; form.sort_order = o.sortOrder
}
function reset() { editing.value = false; form.reset(); form.id = null }
function submit() {
  editing.value ? form.put(`/atelier/operations/${form.id}`, { onSuccess: reset }) : form.post('/atelier/operations', { onSuccess: reset })
}
async function remove(o) {
  const ok = await $swal.dangerConfirm({ title: 'Operasyon silinsin mi?', html: 'Bu operasyon kalıcı olarak silinecek.' })
  if (ok) router.delete(`/atelier/operations/${o.id}`)
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
.form-row { display: flex; flex-direction: column; gap: 6px; flex: 1; min-width: 120px; }
.form-row-actions { flex: 0 0 auto; min-width: unset; }
.form-label { font-size: 12px; font-weight: 600; color: #1a1a2e; }
.form-label .req { color: #ef4444; }
.form-input { padding: 9px 12px; border: 1px solid #e8e8f0; border-radius: 8px; font-family: inherit; font-size: 13px; color: #1a1a2e; background: #fff; outline: none; transition: border-color .15s; }
.form-input:focus { border-color: rgb(var(--color-primary)); }
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

.loc-pill { display: inline-block; padding: 2px 9px; border-radius: 6px; font-size: 11px; font-weight: 600; }
.loc-inhouse { background: #dcfce7; color: #16a34a; }
.loc-fason { background: #fef3c7; color: #d97706; }

.table-actions { display: flex; gap: 4px; justify-content: flex-end; }
.table-action-btn { background: #f3f4f6; border: none; cursor: pointer; font-size: 13px; padding: 5px 9px; border-radius: 6px; color: #6b7280; transition: all .15s; }
.table-action-btn:hover { background: rgb(var(--color-primary-soft)); color: rgb(var(--color-primary)); }
.table-action-btn.danger:hover { background: #fee2e2; color: #dc2626; }
</style>
