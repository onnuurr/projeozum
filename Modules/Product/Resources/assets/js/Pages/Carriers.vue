<template>
	<Head title="Kargo Firmaları" />
	<div class="page-carriers">
		<Breadcrumb
			:items="[
				{ label: 'Ana Sayfa', to: '/workflow', icon: 'home' },
				{ label: 'Ürünler', to: '/products' },
				{ label: 'Kargo Firmaları' },
			]"
		/>

		<div class="page-header">
			<div>
				<h1 class="page-title">Kargo Firmaları</h1>
				<p class="page-subtitle"><strong>{{ carriers.length }}</strong> firma kayıtlı</p>
			</div>
			<button v-if="canManage" class="btn btn-primary btn-with-icon" @click="openNew">
				<svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
					<path d="M12 5v14M5 12h14" />
				</svg>
				Yeni Firma
			</button>
		</div>

		<div class="card">
			<div class="card-header">
				<h3>Firma Listesi</h3>
			</div>
			<div class="table-scroll">
			<table class="data-table">
				<thead>
					<tr>
						<th style="width: 14%">Kod</th>
						<th style="width: 28%">Ad</th>
						<th style="width: 26%">Takip URL Şablonu</th>
						<th style="width: 10%">Durum</th>
						<th style="width: 8%">Sipariş</th>
						<th style="width: 6%">Sıra</th>
						<th style="width: 8%">İşlemler</th>
					</tr>
				</thead>
				<tbody>
					<tr v-if="carriers.length === 0">
						<td colspan="7" class="empty-row">Kayıt bulunamadı</td>
					</tr>
					<tr v-for="c in carriers" :key="c.id">
						<td><span class="mono">{{ c.code }}</span></td>
						<td><strong>{{ c.name }}</strong></td>
						<td class="dim">{{ c.tracking_url_template || '—' }}</td>
						<td>
							<span class="badge" :class="c.is_active ? 'badge-active' : 'badge-passive'">
								{{ c.is_active ? 'Aktif' : 'Pasif' }}
							</span>
						</td>
						<td><span class="user-count">{{ c.orderCount }}</span></td>
						<td>{{ c.sort_order }}</td>
						<td>
							<div class="table-actions">
								<button v-if="canManage" class="table-action-btn view" @click="edit(c)" title="Düzenle">✏️</button>
								<button v-if="canManage" class="table-action-btn delete" @click="confirmDelete(c)" title="Sil">🗑️</button>
							</div>
						</td>
					</tr>
				</tbody>
			</table>
			</div>
		</div>

		<AppModal v-model="formOpen" :title="editing ? 'Firmayı Düzenle' : 'Yeni Kargo Firması'" size="md" variant="info">
			<form class="form-grid" @submit.prevent="submit">
				<div class="form-row-2">
					<div class="form-row">
						<label class="form-label">Kod <span class="req">*</span></label>
						<input v-model="form.code" type="text" class="form-input mono" placeholder="ARAS" />
						<span v-if="errors.code" class="form-error">{{ errors.code }}</span>
					</div>
					<div class="form-row">
						<label class="form-label">Ad <span class="req">*</span></label>
						<input v-model="form.name" type="text" class="form-input" placeholder="Aras Kargo" />
						<span v-if="errors.name" class="form-error">{{ errors.name }}</span>
					</div>
				</div>
				<div class="form-row">
					<label class="form-label">Takip URL Şablonu</label>
					<input v-model="form.tracking_url_template" type="text" class="form-input" placeholder="https://kargotakip.example/{code}" />
					<span v-if="errors.tracking_url_template" class="form-error">{{ errors.tracking_url_template }}</span>
				</div>
				<div class="form-row-2">
					<div class="form-row">
						<label class="form-label">Sıra</label>
						<input v-model.number="form.sort_order" type="number" min="0" class="form-input" placeholder="0" />
					</div>
					<div class="form-row form-row-inline">
						<label class="form-check">
							<input v-model="form.is_active" type="checkbox" />
							<span>Aktif</span>
						</label>
					</div>
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
	carriers: { type: Array, default: () => [] },
})

const showToast = inject('showToast')
const $swal = inject('$swal')
const page = usePage()

const canManage = computed(() => (page.props.auth?.permissions ?? []).includes('carrier.manage'))

const formOpen = ref(false)
const editing = ref(null)
const busy = ref(false)
const errors = ref({})

const emptyForm = () => ({
	code: '',
	name: '',
	tracking_url_template: '',
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

function edit(c) {
	editing.value = c
	errors.value = {}
	Object.assign(form, {
		code: c.code,
		name: c.name,
		tracking_url_template: c.tracking_url_template ?? '',
		is_active: !!c.is_active,
		sort_order: c.sort_order ?? 0,
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
				title: editing.value ? 'Firma güncellendi' : 'Firma eklendi',
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
		router.put(`/products/carriers/${editing.value.id}`, { ...form }, opts)
	} else {
		router.post('/products/carriers', { ...form }, opts)
	}
}

async function confirmDelete(c) {
	if (c.orderCount > 0) {
		await $swal.fire({
			icon: 'warning',
			title: 'Silinemez',
			text: `${c.name} firmasına bağlı ${c.orderCount} sipariş var. Silmek yerine pasife alın.`,
		})
		return
	}
	const ok = await $swal.dangerConfirm({
		title: 'Firmayı Sil',
		text: `${c.name} kargo firmasını silmek istediğinize emin misiniz?`,
	})
	if (!ok) return
	router.delete(`/products/carriers/${c.id}`, {
		preserveScroll: true,
		onSuccess: () => showToast?.({ type: 'success', title: 'Firma silindi', message: c.name }),
		onError: (errs) => showToast?.({ type: 'error', title: 'Silinemedi', message: Object.values(errs)[0] || 'Hata.' }),
	})
}
</script>

<style scoped>
.page-header { display: flex; align-items: center; justify-content: space-between; margin: 12px 0 16px; }
.page-title { font-size: 22px; font-weight: 700; color: #1a1a2e; }
.page-subtitle { font-size: 13px; color: #6b7280; margin-top: 2px; }
.card { background: #fff; border-radius: 12px; border: 1px solid #ebebf0; overflow: hidden; }
.card-header { padding: 14px 18px; border-bottom: 1px solid #f0f0f4; }
.card-header h3 { font-size: 14px; font-weight: 700; color: #1a1a2e; }
.data-table { width: 100%; border-collapse: collapse; }
.data-table th { text-align: left; font-size: 11px; text-transform: uppercase; letter-spacing: 0.04em; color: #9ca3af; padding: 10px 18px; border-bottom: 1px solid #f0f0f4; }
.data-table td { padding: 12px 18px; border-bottom: 1px solid #f6f6f9; font-size: 13px; color: #374151; }
.data-table tr:last-child td { border-bottom: none; }
.empty-row { text-align: center; color: #9ca3af; padding: 28px; }
.mono { font-family: ui-monospace, monospace; font-size: 12px; }
.dim { color: #9ca3af; }
.badge { display: inline-block; padding: 2px 10px; border-radius: 999px; font-size: 11px; font-weight: 600; }
.badge-active { background: #e7f7ee; color: #12805c; }
.badge-passive { background: #f3f4f6; color: #6b7280; }
.user-count { display: inline-block; min-width: 22px; text-align: center; }
.table-actions { display: flex; gap: 6px; }
.table-action-btn { border: none; background: #f6f6f9; border-radius: 6px; padding: 4px 8px; cursor: pointer; font-size: 13px; }
.table-action-btn:hover { background: #ececf2; }
.req { color: #e5484d; }
.form-grid { display: flex; flex-direction: column; gap: 12px; }
.form-row { display: flex; flex-direction: column; gap: 4px; }
.form-row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.form-row-inline { justify-content: center; }
.form-label { font-size: 12px; font-weight: 600; color: #374151; }
.form-input { border: 1px solid #d7d7e0; border-radius: 8px; padding: 8px 10px; font-size: 13px; }
.form-input:focus { outline: none; border-color: #6366f1; }
.form-error { font-size: 11px; color: #e5484d; }
.form-check { display: flex; align-items: center; gap: 6px; font-size: 13px; color: #374151; }
.btn { border: none; border-radius: 8px; padding: 8px 14px; font-size: 13px; font-weight: 600; cursor: pointer; }
.btn-primary { background: #6366f1; color: #fff; }
.btn-primary:hover { background: #4f46e5; }
.btn-ghost { background: #f3f4f6; color: #374151; }
.btn-with-icon { display: inline-flex; align-items: center; gap: 6px; }

@media (max-width: 560px) {
	.form-row-2 { grid-template-columns: 1fr; }
}
</style>
