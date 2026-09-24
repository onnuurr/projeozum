<script setup>
import { ref, computed, inject, reactive, watch } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { OzPageHeader, OzModuleTabs, OzStatTile, OzCard, OzButton, OzTable, OzDropdown, OzDrawer, OzField, OzInput, useOzConfirm } from '@/Components/Oz'

defineOptions({ layout: AppLayout })

const props = defineProps({
	brands: { type: Array, default: () => [] },
})

const showToast = inject('showToast')
const confirm = useOzConfirm()
const page = usePage()

const canManage = computed(() => (page.props.auth?.permissions ?? []).includes('brand.manage'))

const stats = computed(() => {
	const withProducts = props.brands.filter((b) => b.productCount > 0).length
	return [
		{ key: 'total', label: 'Toplam Marka', value: props.brands.length, icon: 'tag', tone: 'accent' },
		{ key: 'products', label: 'Toplam Ürün', value: props.brands.reduce((sum, b) => sum + b.productCount, 0), icon: 'package', tone: 'success' },
		{ key: 'withProducts', label: 'Ürünü Olan', value: withProducts, icon: 'success', tone: 'info' },
		{ key: 'empty', label: 'Ürünsüz Marka', value: props.brands.length - withProducts, icon: 'eye-off', tone: props.brands.length - withProducts ? 'warning' : 'success' },
	]
})

const columns = [
	{ key: 'name', label: 'Marka', sortable: true, sortValue: (r) => r.name },
	{ key: 'productCount', label: 'Ürün Sayısı', align: 'right', sortable: true },
	{ key: 'sort_order', label: 'Sıra', align: 'right', sortable: true },
	{ key: 'updatedAt', label: 'Güncelleme', sortable: true },
	{ key: 'actions', label: '', align: 'right', width: '48px', searchable: false },
]

const rowActions = () => [
	{ key: 'edit', label: 'Düzenle', icon: 'edit' },
	{ key: 'remove', label: 'Sil', icon: 'trash', danger: true },
]
function onAction(key, b) {
	if (key === 'edit') edit(b)
	else if (key === 'remove') confirmDelete(b)
}

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
		await confirm.warning('Silinemez', `${b.name} markasına bağlı ${b.productCount} ürün var. Önce ürünlerin markasını değiştirin.`, { cancelText: 'Kapat', confirmText: 'Tamam' })
		return
	}
	const ok = await confirm.danger('Markayı Sil', `“${b.name}” silinecek. Bu işlem geri alınabilir (soft delete).`)
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

<template>
	<Head title="Markalar" />

	<OzPageHeader title="Markalar">
		<template #subtitle><b>{{ brands.length }}</b> marka kayıtlı</template>
		<template #actions>
			<OzButton v-if="canManage" variant="primary" icon="plus" @click="openNew">Yeni Marka</OzButton>
		</template>
	</OzPageHeader>
	<OzModuleTabs />

	<div class="stats">
		<OzStatTile v-for="s in stats" :key="s.key" :label="s.label" :value="s.value" :icon="s.icon" :tone="s.tone" />
	</div>

	<OzCard title="Marka Listesi">
		<OzTable :columns="columns" :rows="brands" row-key="id" empty-title="Kayıt bulunamadı" searchable search-placeholder="Marka ara…" min-width="640px">
			<template #cell-name="{ row }">
				<div class="brand-cell">
					<div class="brand-logo">{{ row.name.charAt(0) }}</div>
					<div class="brand-info">
						<span class="brand-name">{{ row.name }}</span>
						<span class="brand-slug">{{ row.slug }}</span>
					</div>
				</div>
			</template>
			<template #cell-productCount="{ value }">
				<span class="product-count">{{ value }}</span>
			</template>
			<template v-if="canManage" #cell-actions="{ row }">
				<OzDropdown :items="rowActions()" :label="`${row.name} işlemleri`" @select="(k) => onAction(k, row)" />
			</template>
		</OzTable>
	</OzCard>

	<OzDrawer
		v-model:open="formOpen"
		:title="editing ? 'Markayı Düzenle' : 'Yeni Marka'"
		:subtitle="editing ? editing.name : 'Katalog için yeni bir marka ekleyin'"
		:icon="editing ? 'edit' : 'plus'"
		size="md"
		:persistent="busy"
	>
		<form class="form" @submit.prevent="submit">
			<OzField label="Marka Adı" required :error="errors.name">
				<OzInput v-model="form.name" placeholder="örn. Nike" @input="onNameInput" />
			</OzField>
			<OzField label="Slug" required :error="errors.slug">
				<OzInput v-model="form.slug" class="mono" placeholder="nike" @input="slugTouched = true" />
			</OzField>
			<OzField label="Sıra" :error="errors.sort_order">
				<OzInput v-model.number="form.sort_order" type="number" min="0" placeholder="0" />
			</OzField>
			<button type="submit" hidden />
		</form>
		<template #footer>
			<OzButton :disabled="busy" @click="formOpen = false">İptal</OzButton>
			<OzButton variant="primary" :icon="editing ? 'check' : 'plus'" :loading="busy" @click="submit">{{ editing ? 'Kaydet' : 'Ekle' }}</OzButton>
		</template>
	</OzDrawer>
</template>

<style scoped>
.stats { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; margin-bottom: 14px; }
@media (max-width: 1040px) { .stats { grid-template-columns: repeat(2, minmax(0, 1fr)); } }

.mono { font-family: ui-monospace, monospace; }
.brand-cell { display: flex; align-items: center; gap: 12px; }
.brand-logo {
	width: 32px; height: 32px; border-radius: 9px;
	background: linear-gradient(135deg, var(--oz-accent-soft), var(--oz-accent));
	display: flex; align-items: center; justify-content: center;
	font-size: 14px; font-weight: 800; color: #fff; flex-shrink: 0;
}
.brand-info { display: flex; flex-direction: column; gap: 1px; }
.brand-name { font-weight: 700; color: var(--oz-ink-soft); font-size: 12.5px; }
.brand-slug { font-size: 10.5px; color: var(--oz-muted); font-family: ui-monospace, monospace; }
.product-count { display: inline-block; padding: 2px 9px; background: var(--oz-surface-2); color: var(--oz-muted); border-radius: 6px; font-size: 11.5px; font-weight: 700; font-family: ui-monospace, monospace; }
.form { display: flex; flex-direction: column; gap: 14px; }
</style>
