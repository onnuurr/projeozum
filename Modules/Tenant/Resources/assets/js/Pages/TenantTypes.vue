<template>
	<Head title="Tenant Tipleri" />
	<div class="page-tenant-types">
		<Breadcrumb
			:items="[
				{ label: 'Ana Sayfa', to: '/workflow', icon: 'home' },
				{ label: 'İlişkiler' },
				{ label: 'Tenant\'lar', to: '/tenants' },
				{ label: 'Tipler' },
			]"
		/>

		<div class="page-header">
			<div>
				<h1 class="page-title">Tenant Tipleri</h1>
				<p class="page-subtitle"><strong>{{ types.length }}</strong> tip kayıtlı</p>
			</div>
			<button v-if="canManage" class="btn btn-primary btn-with-icon" @click="openNew">
				<svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
					<path d="M12 5v14M5 12h14" />
				</svg>
				Yeni Tip
			</button>
		</div>

		<div class="card">
			<div class="card-header">
				<h3>Tip Listesi</h3>
			</div>
			<table class="data-table">
				<thead>
					<tr>
						<th style="width: 15%">Kod</th>
						<th style="width: 20%">Ad</th>
						<th style="width: 25%">Açıklama</th>
						<th style="width: 12%">Fiyat Listesi</th>
						<th style="width: 10%">Tenant</th>
						<th style="width: 8%">Sıra</th>
						<th style="width: 10%">İşlemler</th>
					</tr>
				</thead>
				<tbody>
					<tr v-if="types.length === 0">
						<td colspan="7" class="empty-row">Kayıt bulunamadı</td>
					</tr>
					<tr v-for="t in types" :key="t.id">
						<td><span class="mono">{{ t.code }}</span></td>
						<td><strong>{{ t.name }}</strong></td>
						<td class="dim">{{ t.description || '—' }}</td>
						<td>
							<span v-if="t.price_list_type" class="badge badge-price">{{ priceLabel(t.price_list_type) }}</span>
							<span v-else class="dim">—</span>
						</td>
						<td><span class="user-count">{{ t.tenantCount }}</span></td>
						<td>{{ t.sort_order }}</td>
						<td>
							<div class="table-actions">
								<button v-if="canManage" class="table-action-btn view" @click="edit(t)" title="Düzenle">✏️</button>
								<button v-if="canManage" class="table-action-btn delete" @click="confirmDelete(t)" title="Sil">🗑️</button>
							</div>
						</td>
					</tr>
				</tbody>
			</table>
		</div>

		<AppModal v-model="formOpen" :title="editing ? 'Tipi Düzenle' : 'Yeni Tenant Tipi'" size="md" variant="info">
			<form class="form-grid" @submit.prevent="submit">
				<div class="form-row-2">
					<div class="form-row">
						<label class="form-label">Kod <span class="req">*</span></label>
						<input v-model="form.code" type="text" class="form-input mono" placeholder="DEALER" />
						<span v-if="errors.code" class="form-error">{{ errors.code }}</span>
					</div>
					<div class="form-row">
						<label class="form-label">Ad <span class="req">*</span></label>
						<input v-model="form.name" type="text" class="form-input" placeholder="Bayi" />
						<span v-if="errors.name" class="form-error">{{ errors.name }}</span>
					</div>
				</div>
				<div class="form-row">
					<label class="form-label">Açıklama</label>
					<textarea v-model="form.description" class="form-input" rows="2" placeholder="Bu tipin amacı..." />
					<span v-if="errors.description" class="form-error">{{ errors.description }}</span>
				</div>
				<div class="form-row-2">
					<div class="form-row">
						<label class="form-label">Fiyat Listesi Tipi</label>
						<select v-model="form.price_list_type" class="form-input">
							<option :value="null">Seçilmedi</option>
							<option value="retail">Perakende</option>
							<option value="dealer">Bayi</option>
							<option value="dropship">Dropship</option>
						</select>
						<span v-if="errors.price_list_type" class="form-error">{{ errors.price_list_type }}</span>
					</div>
					<div class="form-row">
						<label class="form-label">Sıra</label>
						<input v-model.number="form.sort_order" type="number" min="0" class="form-input" placeholder="0" />
					</div>
				</div>
				<div class="form-row form-row-inline">
					<label class="form-check">
						<input v-model="form.is_active" type="checkbox" />
						<span>Aktif</span>
					</label>
				</div>
			</form>
			<template #footer="{ close }">
				<button class="btn btn-ghost" @click="close" :disabled="busy">İptal</button>
				<button class="btn btn-primary" @click="submit" :disabled="busy">
					{{ editing ? 'Kaydet' : 'Ekle' }}
				</button>
			</template>
		</AppModal>
	</div>
</template>

<script setup>
import { ref, computed, inject, reactive, watch } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Breadcrumb from '@/Components/Breadcrumb.vue'
import AppModal from '@/Components/AppModal.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
	types: { type: Array, default: () => [] },
})

const showToast = inject('showToast')
const $swal = inject('$swal')
const page = usePage()

const canManage = computed(() => (page.props.auth?.permissions ?? []).includes('tenant-type.manage'))

const PRICE_LABELS = { retail: 'Perakende', dealer: 'Bayi', dropship: 'Dropship' }
function priceLabel(v) { return PRICE_LABELS[v] ?? v }

const formOpen = ref(false)
const editing = ref(null)
const busy = ref(false)
const errors = ref({})

const emptyForm = () => ({
	code: '',
	name: '',
	description: '',
	price_list_type: null,
	is_active: true,
	sort_order: 0,
})

const form = reactive(emptyForm())

watch(formOpen, (open) => {
	if (!open) {
		setTimeout(() => {
			editing.value = null
			errors.value = {}
			Object.assign(form, emptyForm())
		}, 250)
	}
})

function openNew() {
	editing.value = null
	errors.value = {}
	Object.assign(form, emptyForm())
	formOpen.value = true
}

function edit(t) {
	editing.value = t
	errors.value = {}
	Object.assign(form, {
		code: t.code,
		name: t.name,
		description: t.description ?? '',
		price_list_type: t.price_list_type ?? null,
		is_active: !!t.is_active,
		sort_order: t.sort_order ?? 0,
	})
	formOpen.value = true
}

function submit() {
	if (busy.value) return
	busy.value = true
	errors.value = {}
	const opts = {
		preserveScroll: true,
		preserveState: true,
		onSuccess: () => {
			formOpen.value = false
			showToast?.({
				type: 'success',
				title: editing.value ? 'Tip güncellendi' : 'Tip eklendi',
				message: form.name,
			})
		},
		onError: (errs) => {
			errors.value = errs
			showToast?.({
				type: 'error',
				title: 'Kayıt başarısız',
				message: Object.values(errs)[0] || 'Doğrulama hatası.',
			})
		},
		onFinish: () => { busy.value = false },
	}
	if (editing.value) {
		router.put(`/tenants/types/${editing.value.id}`, { ...form }, opts)
	} else {
		router.post('/tenants/types', { ...form }, opts)
	}
}

async function confirmDelete(t) {
	if (t.tenantCount > 0) {
		await $swal.fire({
			icon: 'warning',
			title: 'Silinemez',
			text: `${t.name} tipine bağlı ${t.tenantCount} tenant var. Önce tenant'ların tipini değiştirin.`,
		})
		return
	}
	const ok = await $swal.dangerConfirm({
		title: 'Tipi Sil',
		html: `<strong>${t.name}</strong> silinecek.`,
		confirmText: 'Sil',
		cancelText: 'Vazgeç',
	})
	if (!ok) return
	router.delete(`/tenants/types/${t.id}`, {
		preserveScroll: true,
		preserveState: true,
		onSuccess: () => {
			showToast?.({ type: 'warning', title: 'Tip silindi', message: t.name })
		},
		onError: (errs) => {
			showToast?.({ type: 'error', title: 'Silme başarısız', message: Object.values(errs)[0] || 'Sunucu hatası.' })
		},
	})
}
</script>

<style scoped>
.page-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 20px; gap: 16px; }
.page-title { font-size: 22px; font-weight: 700; color: #1a1a2e; line-height: 1.2; }
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
.dim { color: #aaa; font-size: 12px; }
.mono { font-family: 'SF Mono', Menlo, Consolas, monospace; font-size: 12px; color: #555; }
.badge-price { display: inline-block; padding: 3px 9px; background: #fef3c7; color: #92400e; border-radius: 6px; font-size: 11px; font-weight: 600; }
.user-count { display: inline-block; padding: 2px 9px; background: #f0f0f5; color: #555; border-radius: 6px; font-size: 11.5px; font-weight: 700; font-family: 'SF Mono', Menlo, Consolas, monospace; }

.table-actions { display: flex; gap: 4px; }
.table-action-btn { background: #f3f4f6; border: none; cursor: pointer; font-size: 13px; padding: 5px 9px; border-radius: 6px; color: #6b7280; transition: all .15s; }
.table-action-btn.view:hover { background: #e0e7ff; color: #4f46e5; }
.table-action-btn.delete:hover { background: #fee2e2; color: #dc2626; }

.form-grid { display: flex; flex-direction: column; gap: 14px; }
.form-row { display: flex; flex-direction: column; gap: 6px; }
.form-row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.form-row-inline { flex-direction: row; align-items: center; gap: 10px; }
.form-label { font-size: 12px; font-weight: 600; color: #1a1a2e; }
.form-label .req { color: #ef4444; }
.form-input { padding: 9px 12px; border: 1px solid #e8e8f0; border-radius: 8px; font-family: inherit; font-size: 13px; color: #1a1a2e; background: #fff; outline: none; transition: border-color .15s; }
.form-input.mono { font-family: 'SF Mono', Menlo, Consolas, monospace; }
.form-input:focus { border-color: #7c3aed; }
.form-error { font-size: 11.5px; color: #ef4444; }
.form-check { display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 13px; color: #1a1a2e; }
</style>
