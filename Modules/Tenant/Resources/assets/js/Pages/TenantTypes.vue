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

		<PageHeader title="Tenant Tipleri">
			<template #subtitle><strong>{{ types.length }}</strong> tip kayıtlı</template>
			<template v-if="canManage" #actions>
				<Button variant="primary" with-icon @click="openNew">
					<template #leading><Plus :size="13" /></template>
					Yeni Tip
				</Button>
			</template>
		</PageHeader>

		<Card title="Tip Listesi" body-class="p-0">
			<DataTable :columns="columns" :data="types" row-key-field="id" empty-title="Kayıt bulunamadı">
				<template #code="{ value }"><span class="mono">{{ value }}</span></template>
				<template #name="{ value }"><strong>{{ value }}</strong></template>
				<template #description="{ value }"><span class="dim">{{ value || '—' }}</span></template>
				<template #price_list_type="{ value }">
					<Badge v-if="value" color="warning" variant="tonal" :label="priceLabel(value)" />
					<span v-else class="dim">—</span>
				</template>
				<template #tenantCount="{ value }"><span class="user-count">{{ value }}</span></template>
				<template #actions="{ row }">
					<button v-if="canManage" class="table-action-btn view" @click="edit(row)" title="Düzenle"><Pencil :size="14" /></button>
					<button v-if="canManage" class="table-action-btn delete" @click="confirmDelete(row)" title="Sil"><Trash2 :size="14" /></button>
				</template>
			</DataTable>
		</Card>

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
				<Button variant="ghost" :disabled="busy" @click="close">İptal</Button>
				<Button variant="primary" :loading="busy" @click="submit">
					{{ editing ? 'Kaydet' : 'Ekle' }}
				</Button>
			</template>
		</AppModal>
	</div>
</template>

<script setup>
import { ref, computed, inject, reactive, watch } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import { Plus, Pencil, Trash2 } from 'lucide-vue-next'
import AppLayout from '@/Layouts/AppLayout.vue'
import Breadcrumb from '@/Components/Breadcrumb.vue'
import PageHeader from '@/Components/PageHeader.vue'
import Card from '@/Components/Card.vue'
import DataTable from '@/Components/DataTable.vue'
import Badge from '@/Components/Badge.vue'
import Button from '@/Components/Button.vue'
import AppModal from '@/Components/AppModal.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
	types: { type: Array, default: () => [] },
})

const columns = [
	{ key: 'code', label: 'Kod' },
	{ key: 'name', label: 'Ad' },
	{ key: 'description', label: 'Açıklama' },
	{ key: 'price_list_type', label: 'Fiyat Listesi' },
	{ key: 'tenantCount', label: 'Tenant' },
	{ key: 'sort_order', label: 'Sıra' },
]

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
.dim { color: rgb(var(--color-muted)); font-size: 12px; }
.mono { font-family: 'SF Mono', Menlo, Consolas, monospace; font-size: 12px; color: rgb(var(--color-muted)); }
.user-count { display: inline-block; padding: 2px 9px; background: rgb(var(--color-bg)); color: rgb(var(--color-muted)); border-radius: 6px; font-size: 11.5px; font-weight: 700; font-family: 'SF Mono', Menlo, Consolas, monospace; }

.table-action-btn { display: inline-flex; align-items: center; justify-content: center; background: rgb(var(--color-bg)); border: none; cursor: pointer; padding: 6px; border-radius: 6px; color: rgb(var(--color-muted)); transition: all .15s; }
.table-action-btn.view:hover { background: rgb(var(--color-primary-soft)); color: rgb(var(--color-primary)); }
.table-action-btn.delete:hover { background: rgb(var(--color-danger) / .12); color: rgb(var(--color-danger)); }

.form-grid { display: flex; flex-direction: column; gap: 14px; }
.form-row { display: flex; flex-direction: column; gap: 6px; }
.form-row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.form-row-inline { flex-direction: row; align-items: center; gap: 10px; }
.form-label { font-size: 12px; font-weight: 600; color: rgb(var(--color-ink)); }
.form-label .req { color: rgb(var(--color-danger)); }
.form-input { padding: 9px 12px; border: 1px solid rgb(var(--color-border)); border-radius: 8px; font-family: inherit; font-size: 13px; color: rgb(var(--color-ink)); background: rgb(var(--color-surface)); outline: none; transition: border-color .15s; }
.form-input.mono { font-family: 'SF Mono', Menlo, Consolas, monospace; }
.form-input:focus { border-color: rgb(var(--color-primary)); }
.form-error { font-size: 11.5px; color: rgb(var(--color-danger)); }
.form-check { display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 13px; color: rgb(var(--color-ink)); }

@media (max-width: 560px) {
	.form-row-2 { grid-template-columns: 1fr; }
}
</style>
