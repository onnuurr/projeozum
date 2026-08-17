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

		<PageHeader title="Reçeteler (BOM)">
			<template #subtitle><strong>{{ boms.length }}</strong> reçete tanımı</template>
			<template v-if="can('atelier.bom.manage')" #actions>
				<Button variant="primary" with-icon @click="openCreate">
					<template #leading><Plus :size="13" /></template>
					Yeni Reçete
				</Button>
			</template>
		</PageHeader>

		<!-- BOM list -->
		<EmptyState v-if="boms.length === 0" :icon="ClipboardList" title="Henüz reçete tanımlanmamış." />

		<Card v-for="b in boms" :key="b.id" :title="b.productName" class="bom-card">
			<template #actions>
				<div class="bom-card-actions">
					<Badge color="info" :label="b.name" variant="tonal" />
					<Button v-if="can('atelier.bom.manage')" variant="danger" size="sm" @click="remove(b)">Sil</Button>
				</div>
			</template>
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
		</Card>

		<AppModal v-model="modalOpen" title="Yeni Reçete" size="lg">
			<form id="bom-form" class="form-grid" @submit.prevent="submit">
				<div class="form-row-inline">
					<div class="form-row">
						<label class="form-label">Ürün <span class="req">*</span></label>
						<select v-model="form.product_id" class="form-input">
							<option value="">Seçin…</option>
							<option v-for="p in products" :key="p.id" :value="p.id">{{ p.name }} ({{ p.sku }})</option>
						</select>
						<span v-if="form.errors.product_id" class="form-error">{{ form.errors.product_id }}</span>
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
							<Button type="button" variant="ghost" size="sm" @click="removeLine(i)">Kaldır</Button>
						</div>
					</div>
					<Button type="button" variant="ghost" with-icon @click="addLine">
						<template #leading><Plus :size="13" /></template>
						Satır Ekle
					</Button>
				</div>
			</form>
			<template #footer="{ close }">
				<Button variant="ghost" :disabled="form.processing" @click="close">İptal</Button>
				<Button type="submit" form="bom-form" variant="primary" :loading="form.processing">Kaydet</Button>
			</template>
		</AppModal>
	</div>
</template>

<script setup>
import { ref, inject, watch } from 'vue'
import { Head, useForm, router } from '@inertiajs/vue3'
import { Plus, ClipboardList } from 'lucide-vue-next'
import AppLayout from '@/Layouts/AppLayout.vue'
import Breadcrumb from '@/Components/Breadcrumb.vue'
import PageHeader from '@/Components/PageHeader.vue'
import Card from '@/Components/Card.vue'
import Badge from '@/Components/Badge.vue'
import Button from '@/Components/Button.vue'
import AppModal from '@/Components/AppModal.vue'
import EmptyState from '@/Components/EmptyState.vue'
import AtelierNav from '../Components/AtelierNav.vue'
import { useCan } from '@/composables/useCan'

defineOptions({ layout: AppLayout })

const { can } = useCan()
const $swal = inject('$swal')
const showToast = inject('showToast', null)

const props = defineProps({ boms: Array, products: Array, materials: Array })

function emptyForm() {
	return { product_id: '', name: 'Varsayılan Reçete', lines: [{ material_id: '', quantity_per_unit: 1, waste_pct: 0 }] }
}

const form = useForm(emptyForm())
const modalOpen = ref(false)

function openCreate() {
	reset()
	modalOpen.value = true
}

function reset() {
	form.reset()
	Object.assign(form, emptyForm())
	form.clearErrors()
}

watch(modalOpen, (open) => {
	if (!open) setTimeout(reset, 250)
})

function addLine() { form.lines.push({ material_id: '', quantity_per_unit: 1, waste_pct: 0 }) }
function removeLine(i) { form.lines.splice(i, 1) }
function submit() {
	form.post('/atelier/boms', {
		onSuccess: () => {
			showToast?.({ type: 'success', title: 'Reçete eklendi', message: form.name })
			modalOpen.value = false
		},
		onError: () => {
			showToast?.({ type: 'error', title: 'Kayıt başarısız', message: Object.values(form.errors)[0] || 'Doğrulama hatası.' })
		},
	})
}
async function remove(b) {
  const ok = await $swal.dangerConfirm({ title: 'Reçete silinsin mi?', html: 'Bu reçete kalıcı olarak silinecek.' })
  if (ok) router.delete(`/atelier/boms/${b.id}`)
}
</script>

<style scoped>
.bom-card { margin-bottom: 14px; }

.form-grid { display: flex; flex-direction: column; gap: 14px; }
.form-row-inline { display: flex; gap: 12px; flex-wrap: wrap; }
.form-row { display: flex; flex-direction: column; gap: 6px; flex: 1; min-width: 150px; }
.form-row-actions { flex: 0 0 auto; min-width: unset; }
.form-label { font-size: 12px; font-weight: 600; color: #1a1a2e; }
.form-label .req { color: #ef4444; }
.form-input { padding: 9px 12px; border: 1px solid #e8e8f0; border-radius: 8px; font-family: inherit; font-size: 13px; color: #1a1a2e; background: #fff; outline: none; transition: border-color .15s; }
.form-input:focus { border-color: var(--color-primary); }
.form-error { font-size: 11.5px; color: var(--color-danger); }

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

.bom-card-actions { display: flex; align-items: center; gap: 10px; }
</style>
