<script setup>
import { ref, computed, inject, reactive, watch } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { OzPageHeader, OzModuleTabs, OzStatTile, OzCard, OzButton, OzTable, OzPill, OzDropdown, OzDrawer, OzField, OzInput, OzSwitch, useOzConfirm } from '@/Components/Oz'

defineOptions({ layout: AppLayout })

const props = defineProps({
	carriers: { type: Array, default: () => [] },
})

const showToast = inject('showToast')
const confirm = useOzConfirm()
const page = usePage()

const canManage = computed(() => (page.props.auth?.permissions ?? []).includes('carrier.manage'))

const activeCount = computed(() => props.carriers.filter((c) => c.is_active).length)
const stats = computed(() => [
	{ key: 'total', label: 'Toplam Firma', value: props.carriers.length, icon: 'truck', tone: 'accent' },
	{ key: 'active', label: 'Aktif', value: activeCount.value, icon: 'success', tone: 'success' },
	{ key: 'passive', label: 'Pasif', value: props.carriers.length - activeCount.value, icon: 'eye-off', tone: props.carriers.length - activeCount.value ? 'warning' : 'success' },
	{ key: 'inUse', label: 'Sipariş Bağlı', value: props.carriers.filter((c) => c.orderCount > 0).length, icon: 'package', tone: 'info' },
])

const columns = [
	{ key: 'code', label: 'Kod', sortable: true },
	{ key: 'name', label: 'Ad', sortable: true, class: 'oz-cell-strong' },
	{ key: 'tracking_url_template', label: 'Takip URL Şablonu', sortable: false },
	{ key: 'is_active', label: 'Durum', sortable: true },
	{ key: 'orderCount', label: 'Sipariş', align: 'right', sortable: true },
	{ key: 'sort_order', label: 'Sıra', align: 'right', sortable: true },
	{ key: 'actions', label: '', align: 'right', width: '48px', searchable: false },
]

const rowActions = () => [
	{ key: 'edit', label: 'Düzenle', icon: 'edit' },
	{ key: 'remove', label: 'Sil', icon: 'trash', danger: true },
]
function onAction(key, c) {
	if (key === 'edit') edit(c)
	else if (key === 'remove') confirmDelete(c)
}

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
		await confirm.warning('Silinemez', `${c.name} firmasına bağlı ${c.orderCount} sipariş var. Silmek yerine pasife alın.`, { cancelText: 'Kapat', confirmText: 'Tamam' })
		return
	}
	const ok = await confirm.danger('Firmayı Sil', `${c.name} kargo firmasını silmek istediğinize emin misiniz?`)
	if (!ok) return
	router.delete(`/products/carriers/${c.id}`, {
		preserveScroll: true,
		onSuccess: () => showToast?.({ type: 'success', title: 'Firma silindi', message: c.name }),
		onError: (errs) => showToast?.({ type: 'error', title: 'Silinemedi', message: Object.values(errs)[0] || 'Hata.' }),
	})
}
</script>

<template>
	<Head title="Kargo Firmaları" />

	<OzPageHeader title="Kargo Firmaları">
		<template #subtitle><b>{{ carriers.length }}</b> firma kayıtlı</template>
		<template #actions>
			<OzButton v-if="canManage" variant="primary" icon="plus" @click="openNew">Yeni Firma</OzButton>
		</template>
	</OzPageHeader>
	<OzModuleTabs />

	<div class="stats">
		<OzStatTile v-for="s in stats" :key="s.key" :label="s.label" :value="s.value" :icon="s.icon" :tone="s.tone" />
	</div>

	<OzCard title="Firma Listesi">
		<OzTable :columns="columns" :rows="carriers" row-key="id" empty-title="Kayıt bulunamadı" searchable search-placeholder="Firma ara…">
			<template #cell-code="{ value }"><span class="mono">{{ value }}</span></template>
			<template #cell-tracking_url_template="{ value }"><span class="dim">{{ value || '—' }}</span></template>
			<template #cell-is_active="{ value }">
				<OzPill :tone="value ? 'success' : 'neutral'">{{ value ? 'Aktif' : 'Pasif' }}</OzPill>
			</template>
			<template v-if="canManage" #cell-actions="{ row }">
				<OzDropdown :items="rowActions()" :label="`${row.name} işlemleri`" @select="(k) => onAction(k, row)" />
			</template>
		</OzTable>
	</OzCard>

	<OzDrawer v-model:open="formOpen" :title="editing ? 'Firmayı Düzenle' : 'Yeni Kargo Firması'" subtitle="Kargo firması bilgilerini girin" :icon="editing ? 'edit' : 'plus'" size="md" :persistent="busy">
		<form class="form" @submit.prevent="submit">
			<div class="row-2">
				<OzField label="Kod" required :error="errors.code">
					<OzInput v-model="form.code" class="mono" placeholder="ARAS" />
				</OzField>
				<OzField label="Ad" required :error="errors.name">
					<OzInput v-model="form.name" placeholder="Aras Kargo" />
				</OzField>
			</div>
			<OzField label="Takip URL Şablonu" :error="errors.tracking_url_template">
				<OzInput v-model="form.tracking_url_template" placeholder="https://kargotakip.example/{code}" />
			</OzField>
			<div class="row-2">
				<OzField label="Sıra" :error="errors.sort_order">
					<OzInput v-model.number="form.sort_order" type="number" min="0" placeholder="0" />
				</OzField>
				<OzField label="Durum">
					<OzSwitch v-model="form.is_active" label="Aktif" />
				</OzField>
			</div>
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

.mono { font-family: ui-monospace, monospace; font-size: 12px; }
.dim { color: var(--oz-muted); }
.form { display: flex; flex-direction: column; gap: 14px; }
.row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
@media (max-width: 560px) {
	.row-2 { grid-template-columns: 1fr; }
}
</style>
