<template>
	<Head title="Fasoncular" />
	<div class="page-fason">
		<Breadcrumb
			:items="[
				{ label: 'Ana Sayfa', to: '/workflow', icon: 'home' },
				{ label: 'Üretim Atölyesi', to: '/atelier' },
				{ label: 'Fasoncular' },
			]"
		/>

		<AtelierNav current="fason" />

		<div class="page-header">
			<div>
				<h1 class="page-title">Fasoncular</h1>
				<p class="page-subtitle"><strong>{{ suppliers.length }}</strong> fasoncu kaydı</p>
			</div>
		</div>

		<!-- Form Card -->
		<div class="card" style="margin-bottom: 18px;">
			<div class="card-header">
				<h3>{{ editing ? 'Fasoncu Düzenle' : 'Yeni Fasoncu' }}</h3>
			</div>
			<div class="form-section">
				<form @submit.prevent="submit" class="form-grid">
					<div class="form-row-inline">
						<div class="form-row" style="flex: 2">
							<label class="form-label">Ad <span class="req">*</span></label>
							<input v-model="form.name" type="text" class="form-input" placeholder="Firma adı" />
						</div>
						<div class="form-row">
							<label class="form-label">Yetkili</label>
							<input v-model="form.contact_name" type="text" class="form-input" placeholder="Ad Soyad" />
						</div>
						<div class="form-row">
							<label class="form-label">Telefon</label>
							<input v-model="form.phone" type="text" class="form-input" placeholder="+90 5xx..." />
						</div>
						<div class="form-row">
							<label class="form-label">E-posta</label>
							<input v-model="form.email" type="email" class="form-input" placeholder="ornek@firma.com" />
						</div>
					</div>
					<div class="form-row-inline">
						<div class="form-row" style="flex: 3">
							<label class="form-label">Adres</label>
							<input v-model="form.address" type="text" class="form-input" placeholder="Açık adres" />
						</div>
						<div class="form-row">
							<label class="form-label">Vergi No</label>
							<input v-model="form.tax_no" type="text" class="form-input" placeholder="1234567890" />
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
					</div>
				</form>
			</div>
		</div>

		<!-- Table Card -->
		<div class="card">
			<div class="card-header">
				<h3>Fasoncu Listesi</h3>
			</div>
			<table class="data-table">
				<thead>
					<tr>
						<th style="width: 22%">Ad</th>
						<th style="width: 18%">Yetkili</th>
						<th style="width: 15%">Telefon</th>
						<th style="width: 22%">E-posta</th>
						<th style="width: 8%">Durum</th>
						<th style="width: 10%"></th>
					</tr>
				</thead>
				<tbody>
					<tr v-if="suppliers.length === 0">
						<td colspan="6" class="empty-row">Fasoncu kaydı yok.</td>
					</tr>
					<tr v-for="s in suppliers" :key="s.id">
						<td>
							<div class="supplier-cell">
								<div class="supplier-logo">{{ s.name.charAt(0) }}</div>
								<div class="supplier-info">
									<span class="row-name">{{ s.name }}</span>
									<span v-if="s.taxNo" class="tax-no">{{ s.taxNo }}</span>
								</div>
							</div>
						</td>
						<td>{{ s.contactName }}</td>
						<td><a v-if="s.phone" :href="`tel:${s.phone}`" class="link-dim">{{ s.phone }}</a><span v-else class="dim">—</span></td>
						<td><a v-if="s.email" :href="`mailto:${s.email}`" class="link-dim">{{ s.email }}</a><span v-else class="dim">—</span></td>
						<td>
							<span class="status-pill" :class="s.isActive ? 'status-ok' : 'status-passive'">
								<span class="dot"></span>
								{{ s.isActive ? 'Aktif' : 'Pasif' }}
							</span>
						</td>
						<td>
							<div class="table-actions">
								<button class="table-action-btn" @click="edit(s)" title="Düzenle">✏️</button>
								<button class="table-action-btn danger" @click="remove(s)" title="Sil">🗑️</button>
							</div>
						</td>
					</tr>
				</tbody>
			</table>
		</div>
	</div>
</template>

<script setup>
import { ref } from 'vue'
import { Head, useForm, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Breadcrumb from '@/Components/Breadcrumb.vue'
import AtelierNav from '../Components/AtelierNav.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({ suppliers: Array })
const form = useForm({ id: null, name: '', contact_name: '', phone: '', email: '', address: '', tax_no: '', notes: '', is_active: true })
const editing = ref(false)

function edit(s) {
  editing.value = true
  Object.assign(form, { id: s.id, name: s.name, contact_name: s.contactName, phone: s.phone, email: s.email, address: s.address, tax_no: s.taxNo, notes: s.notes, is_active: s.isActive })
}
function reset() { editing.value = false; form.reset(); form.id = null }
function submit() {
  editing.value ? form.put(`/atelier/fason-suppliers/${form.id}`, { onSuccess: reset }) : form.post('/atelier/fason-suppliers', { onSuccess: reset })
}
function remove(s) { if (confirm('Fasoncu silinsin mi?')) router.delete(`/atelier/fason-suppliers/${s.id}`) }
</script>

<style scoped>
.page-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 20px; gap: 16px; }
.page-title { font-size: 22px; font-weight: 700; color: #1a1a2e; }
.page-subtitle { font-size: 13px; color: #888; margin-top: 4px; }

.card { background: #fff; border-radius: 16px; border: 1px solid #ebebf0; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,.04); }
.card-header { padding: 14px 18px; display: flex; align-items: center; gap: 12px; border-bottom: 1px solid #f0f0f5; }
.card-header h3 { font-size: 15px; font-weight: 700; color: #1a1a2e; }

.form-section { padding: 16px 18px; }
.form-grid { display: flex; flex-direction: column; gap: 12px; }
.form-row-inline { display: flex; gap: 12px; flex-wrap: wrap; align-items: flex-end; }
.form-row { display: flex; flex-direction: column; gap: 6px; flex: 1; min-width: 140px; }
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
.dim { color: #888; font-size: 12px; }
.row-name { font-weight: 600; color: #1a1a2e; }

.supplier-cell { display: flex; align-items: center; gap: 10px; }
.supplier-logo { width: 34px; height: 34px; border-radius: 9px; background: linear-gradient(135deg, rgb(var(--color-primary-soft)), #ddd6fe); display: flex; align-items: center; justify-content: center; font-size: 15px; font-weight: 800; color: rgb(var(--color-primary)); flex-shrink: 0; text-transform: uppercase; }
.supplier-info { display: flex; flex-direction: column; gap: 2px; }
.tax-no { font-size: 10.5px; color: #888; font-family: 'SF Mono', Menlo, Consolas, monospace; }

.link-dim { color: #555; text-decoration: none; font-size: 13px; }
.link-dim:hover { color: rgb(var(--color-primary)); text-decoration: underline; }

.status-pill { display: inline-flex; align-items: center; gap: 5px; padding: 3px 10px; border-radius: 999px; font-size: 11px; font-weight: 600; }
.status-pill .dot { width: 5px; height: 5px; border-radius: 50%; background: currentColor; }
.status-ok { background: #dcfce7; color: #16a34a; }
.status-passive { background: #f0f0f5; color: #888; }

.table-actions { display: flex; gap: 4px; justify-content: flex-end; }
.table-action-btn { background: #f3f4f6; border: none; cursor: pointer; font-size: 13px; padding: 5px 9px; border-radius: 6px; color: #6b7280; transition: all .15s; }
.table-action-btn:hover { background: rgb(var(--color-primary-soft)); color: rgb(var(--color-primary)); }
.table-action-btn.danger:hover { background: #fee2e2; color: #dc2626; }
</style>
