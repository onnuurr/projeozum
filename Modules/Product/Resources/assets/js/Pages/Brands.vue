<template>
	<Head title="Markalar" />
	<div class="page-brands">
		<Breadcrumb
			:items="[
				{ label: 'Ana Sayfa', to: '/workflow', icon: 'home' },
				{ label: 'Katalog', to: '/products' },
				{ label: 'Markalar' },
			]"
		/>

		<PageHeader title="Markalar">
			<template #subtitle><strong>{{ brands.length }}</strong> marka kayıtlı</template>
			<template #actions>
				<Button v-if="canManage" variant="primary" with-icon @click="openNew">
					<template #leading><Plus :size="13" /></template>
					Yeni Marka
				</Button>
			</template>
		</PageHeader>

		<Card title="Marka Listesi" body-class="p-2.5">
			<div class="card-search">
				<FormField variant="default">
					<FormInput v-model="searchQuery" type="text" placeholder="Marka ara..." variant="default">
						<template #icon><Search :size="13" /></template>
					</FormInput>
				</FormField>
			</div>

			<DataTable :columns="columns" :data="filtered" empty-title="Kayıt bulunamadı">
				<template #brand="{ row }">
					<div class="brand-cell">
						<div class="brand-logo">{{ row.name.charAt(0) }}</div>
						<div class="brand-info">
							<span class="brand-name">{{ row.name }}</span>
							<span class="brand-slug">{{ row.slug }}</span>
						</div>
					</div>
				</template>
				<template #productCount="{ value }">
					<span class="product-count">{{ value }}</span>
				</template>
				<template #updatedAt="{ value }">{{ value }}</template>
				<template v-if="canManage" #actions="{ row }">
					<button class="table-action-btn view" @click="edit(row)" title="Düzenle"><Pencil :size="14" /></button>
					<button class="table-action-btn delete" @click="confirmDelete(row)" title="Sil"><Trash2 :size="14" /></button>
				</template>
			</DataTable>
		</Card>

		<!-- Form modal -->
		<AppModal v-model="formOpen" :title="editing ? 'Markayı Düzenle' : 'Yeni Marka'" size="md" variant="info">
			<form class="form-grid" @submit.prevent="submit">
				<div class="form-row">
					<label class="form-label">Marka Adı <span class="req">*</span></label>
					<input v-model="form.name" type="text" class="form-input" placeholder="örn. Nike" @input="onNameInput" />
					<span v-if="errors.name" class="form-error">{{ errors.name }}</span>
				</div>
				<div class="form-row">
					<label class="form-label">Slug <span class="req">*</span></label>
					<input v-model="form.slug" type="text" class="form-input" placeholder="nike" @input="slugTouched = true" />
					<span v-if="errors.slug" class="form-error">{{ errors.slug }}</span>
				</div>
				<div class="form-row">
					<label class="form-label">Sıra</label>
					<input v-model.number="form.sort_order" type="number" min="0" class="form-input" placeholder="0" />
					<span v-if="errors.sort_order" class="form-error">{{ errors.sort_order }}</span>
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
import { Plus, Search, Pencil, Trash2 } from 'lucide-vue-next'
import AppLayout from '@/Layouts/AppLayout.vue'
import Breadcrumb from '@/Components/Breadcrumb.vue'
import AppModal from '@/Components/AppModal.vue'
import PageHeader from '@/Components/PageHeader.vue'
import Button from '@/Components/Button.vue'
import Card from '@/Components/Card.vue'
import DataTable from '@/Components/DataTable.vue'
import FormField from '@/Components/Form/FormField.vue'
import FormInput from '@/Components/Form/FormInput.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
	brands: { type: Array, default: () => [] },
})

const showToast = inject('showToast')
const $swal = inject('$swal')
const page = usePage()

const canManage = computed(() => (page.props.auth?.permissions ?? []).includes('brand.manage'))

const columns = [
	{ key: 'brand', label: 'Marka', sortable: false },
	{ key: 'productCount', label: 'Ürün Sayısı' },
	{ key: 'sort_order', label: 'Sıra' },
	{ key: 'updatedAt', label: 'Güncelleme' },
]

const searchQuery = ref('')
const filtered = computed(() => {
	const q = searchQuery.value.trim().toLowerCase()
	if (!q) return props.brands
	return props.brands.filter(b =>
		b.name.toLowerCase().includes(q) || b.slug.toLowerCase().includes(q),
	)
})

/* ── Form state ── */
const formOpen = ref(false)
const editing = ref(null)
const busy = ref(false)
const errors = ref({})
const slugTouched = ref(false)
const form = reactive({ name: '', slug: '', sort_order: 0 })

function slugify(text) {
	const map = { ç: 'c', Ç: 'c', ğ: 'g', Ğ: 'g', ı: 'i', İ: 'i', ö: 'o', Ö: 'o', ş: 's', Ş: 's', ü: 'u', Ü: 'u' }
	return text
		.replace(/[çÇğĞıİöÖşŞüÜ]/g, ch => map[ch] || ch)
		.toLowerCase()
		.replace(/[^a-z0-9]+/g, '-')
		.replace(/(^-|-$)/g, '')
}

function onNameInput() {
	if (!slugTouched.value) form.slug = slugify(form.name)
}

watch(formOpen, (open) => {
	if (!open) {
		setTimeout(() => {
			editing.value = null
			errors.value = {}
			form.name = ''
			form.slug = ''
			form.sort_order = 0
			slugTouched.value = false
		}, 250)
	}
})

function openNew() {
	editing.value = null
	errors.value = {}
	form.name = ''
	form.slug = ''
	form.sort_order = 0
	slugTouched.value = false
	formOpen.value = true
}

function edit(b) {
	editing.value = b
	errors.value = {}
	form.name = b.name
	form.slug = b.slug
	form.sort_order = b.sort_order
	slugTouched.value = true
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
				title: editing.value ? 'Marka güncellendi' : 'Marka eklendi',
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
	const payload = { name: form.name, slug: form.slug, sort_order: form.sort_order }
	if (editing.value) {
		router.put(`/products/brands/${editing.value.id}`, payload, opts)
	} else {
		router.post('/products/brands', payload, opts)
	}
}

async function confirmDelete(b) {
	if (b.productCount > 0) {
		await $swal.fire({
			icon: 'warning',
			title: 'Silinemez',
			text: `${b.name} markasına bağlı ${b.productCount} ürün var. Önce ürünlerin markasını değiştirin.`,
		})
		return
	}
	const ok = await $swal.dangerConfirm({
		title: 'Markayı Sil',
		html: `<strong>${b.name}</strong> silinecek. Bu işlem geri alınabilir (soft delete).`,
		confirmText: 'Sil',
		cancelText: 'Vazgeç',
	})
	if (!ok) return
	router.delete(`/products/brands/${b.id}`, {
		preserveScroll: true,
		preserveState: true,
		onSuccess: () => {
			showToast?.({ type: 'warning', title: 'Marka silindi', message: b.name })
		},
		onError: (errs) => {
			showToast?.({ type: 'error', title: 'Silme başarısız', message: Object.values(errs)[0] || 'Sunucu hatası.' })
		},
	})
}
</script>

<style scoped>
.card-search { display: flex; align-items: center; gap: 6px; background: rgb(var(--color-bg-rgb) / .5); border: 1px solid rgb(var(--color-border-rgb)); border-radius: 8px; padding: 5px 10px; margin-bottom: 10px; max-width: 280px; }

.brand-cell { display: flex; align-items: center; gap: 12px; }
.brand-logo {
	width: 36px; height: 36px; border-radius: 10px;
	background: linear-gradient(135deg, rgb(var(--color-primary-soft-rgb)), #ddd6fe);
	display: flex; align-items: center; justify-content: center;
	font-size: 16px; font-weight: 800; color: rgb(var(--color-primary-rgb)); flex-shrink: 0;
}
.brand-info { display: flex; flex-direction: column; gap: 2px; }
.brand-name { font-weight: 600; color: rgb(var(--color-ink-rgb)); font-size: 13px; }
.brand-slug { font-size: 10.5px; color: rgb(var(--color-muted-rgb)); font-family: 'SF Mono', Menlo, Consolas, monospace; }

.product-count { display: inline-block; padding: 2px 9px; background: rgb(var(--color-border-rgb) / .5); color: rgb(var(--color-muted-rgb)); border-radius: 6px; font-size: 11.5px; font-weight: 700; font-family: 'SF Mono', Menlo, Consolas, monospace; }

.table-action-btn { background: rgb(var(--color-border-rgb) / .4); border: none; cursor: pointer; padding: 5px 9px; border-radius: 6px; color: rgb(var(--color-muted-rgb)); transition: all .15s; display: inline-flex; align-items: center; }
.table-action-btn.view:hover { background: rgb(var(--color-primary-soft-rgb)); color: rgb(var(--color-primary-rgb)); }
.table-action-btn.delete:hover { background: rgb(var(--color-danger-rgb) / .12); color: rgb(var(--color-danger-rgb)); }

.form-grid { display: flex; flex-direction: column; gap: 14px; }
.form-row { display: flex; flex-direction: column; gap: 6px; }
.form-label { font-size: 12px; font-weight: 600; color: rgb(var(--color-ink-rgb)); }
.form-label .req { color: rgb(var(--color-danger-rgb)); }
.form-input { padding: 9px 12px; border: 1px solid rgb(var(--color-border-rgb)); border-radius: 8px; font-family: inherit; font-size: 13px; color: rgb(var(--color-ink-rgb)); background: var(--color-surface); outline: none; transition: border-color .15s; }
.form-input:focus { border-color: rgb(var(--color-primary-rgb)); }
.form-error { font-size: 11.5px; color: rgb(var(--color-danger-rgb)); }
</style>
