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

		<PageHeader title="Kargo Firmaları">
			<template #subtitle><strong>{{ carriers.length }}</strong> firma kayıtlı</template>
			<template #actions>
				<Button v-if="canManage" variant="primary" with-icon @click="openNew">
					<template #leading><Plus :size="13" /></template>
					Yeni Firma
				</Button>
			</template>
		</PageHeader>

		<Card title="Firma Listesi" body-class="p-2.5">
			<DataTable :columns="columns" :data="carriers" empty-title="Kayıt bulunamadı">
				<template #code="{ value }"><span class="mono">{{ value }}</span></template>
				<template #name="{ value }"><strong>{{ value }}</strong></template>
				<template #tracking_url_template="{ value }"><span class="dim">{{ value || '—' }}</span></template>
				<template #is_active="{ value }">
					<Badge :color="value ? 'success' : 'neutral'" variant="tonal" :label="value ? 'Aktif' : 'Pasif'" />
				</template>
				<template #orderCount="{ value }">{{ value }}</template>
				<template #sort_order="{ value }">{{ value }}</template>
				<template v-if="canManage" #actions="{ row }">
					<button class="table-action-btn" @click="edit(row)" title="Düzenle"><Pencil :size="14" /></button>
					<button class="table-action-btn" @click="confirmDelete(row)" title="Sil"><Trash2 :size="14" /></button>
				</template>
			</DataTable>
		</Card>

		<AppDrawer v-model="formOpen" :title="editing ? 'Firmayı Düzenle' : 'Yeni Kargo Firması'" subtitle="Kargo firması bilgilerini girin" variant="info" width="480px">
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
				<Button variant="ghost" :disabled="busy" @click="close">İptal</Button>
				<Button variant="primary" :loading="busy" @click="submit">
					{{ editing ? 'Kaydet' : 'Ekle' }}
				</Button>
			</template>
		</AppDrawer>
	</div>
</template>

<script setup>
import { ref, computed, inject, reactive, watch } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import { Plus, Pencil, Trash2 } from 'lucide-vue-next'
import AppLayout from '@/Layouts/AppLayout.vue'
import Breadcrumb from '@/Components/Breadcrumb.vue'
import AppDrawer from '@/Components/AppDrawer.vue'
import PageHeader from '@/Components/PageHeader.vue'
import Button from '@/Components/Button.vue'
import Card from '@/Components/Card.vue'
import DataTable from '@/Components/DataTable.vue'
import Badge from '@/Components/Badge.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
	carriers: { type: Array, default: () => [] },
})

const showToast = inject('showToast')
const $swal = inject('$swal')
const page = usePage()

const canManage = computed(() => (page.props.auth?.permissions ?? []).includes('carrier.manage'))

const columns = [
	{ key: 'code', label: 'Kod' },
	{ key: 'name', label: 'Ad' },
	{ key: 'tracking_url_template', label: 'Takip URL Şablonu', sortable: false },
	{ key: 'is_active', label: 'Durum' },
	{ key: 'orderCount', label: 'Sipariş' },
	{ key: 'sort_order', label: 'Sıra' },
]

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
.mono { font-family: ui-monospace, monospace; font-size: 12px; }
.dim { color: rgb(var(--color-muted-rgb)); }
.table-action-btn { border: none; background: rgb(var(--color-border-rgb) / .4); border-radius: 6px; padding: 5px 9px; cursor: pointer; color: rgb(var(--color-muted-rgb)); display: inline-flex; align-items: center; transition: background-color .15s; }
.table-action-btn:hover { background: rgb(var(--color-border-rgb) / .7); }
.req { color: rgb(var(--color-danger-rgb)); }
.form-grid { display: flex; flex-direction: column; gap: 12px; }
.form-row { display: flex; flex-direction: column; gap: 4px; }
.form-row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.form-row-inline { justify-content: center; }
.form-label { font-size: 12px; font-weight: 600; color: rgb(var(--color-ink-rgb)); }
.form-input { border: 1px solid rgb(var(--color-border-rgb)); border-radius: 8px; padding: 8px 10px; font-size: 13px; }
.form-input:focus { outline: none; border-color: rgb(var(--color-primary-rgb)); }
.form-error { font-size: 11px; color: rgb(var(--color-danger-rgb)); }
.form-check { display: flex; align-items: center; gap: 6px; font-size: 13px; color: rgb(var(--color-ink-rgb)); }

@media (max-width: 560px) {
	.form-row-2 { grid-template-columns: 1fr; }
}
</style>
