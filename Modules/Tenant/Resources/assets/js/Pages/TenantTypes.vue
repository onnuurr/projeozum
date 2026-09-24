<script setup>
import { ref, computed, inject } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import {
	OzPageHeader, OzModuleTabs, OzCard, OzTable, OzPill, OzButton, OzDropdown, OzEmptyState,
	OzDrawer, OzField, OzInput, OzSelect, OzTextarea, OzSwitch, useOzConfirm,
} from '@/Components/Oz'
import { useCan } from '@/composables/useCan'

// İş ortağı tipleri (Oz kit). Ekle / düzenle / sil → Inertia istekleri (TenantTypeController).
defineOptions({ layout: AppLayout })

const props = defineProps({
	types: { type: Array, default: () => [] },
})

const { can } = useCan()
const canManage = computed(() => can('tenant-type.manage'))
const showToast = inject('showToast', null)
const confirm = useOzConfirm()

const PRICE_LABELS = { retail: 'Perakende', dealer: 'Bayi', dropship: 'Dropship' }
const priceOptions = Object.entries(PRICE_LABELS).map(([value, label]) => ({ value, label }))

const columns = [
	{ key: 'name', label: 'Tip', sortable: true, searchValue: (r) => `${r.name} ${r.code} ${r.description ?? ''}` },
	{ key: 'description', label: 'Açıklama' },
	{
		key: 'price_list_type', label: 'Fiyat listesi', sortable: true, sortValue: (r) => PRICE_LABELS[r.price_list_type] ?? '',
		filterValue: (r) => r.price_list_type ?? '__none__',
		filter: { type: 'select', multiple: true, options: [...priceOptions, { value: '__none__', label: 'Tanımsız' }] },
	},
	{ key: 'tenantCount', label: 'İş ortağı', sortable: true, align: 'right', searchable: false },
	{ key: 'sort_order', label: 'Sıra', sortable: true, align: 'right', searchable: false },
	{
		key: 'is_active', label: 'Durum', sortable: true, searchable: false, filterValue: (r) => (r.is_active ? 'active' : 'passive'),
		filter: { type: 'select', options: [{ value: 'active', label: 'Aktif' }, { value: 'passive', label: 'Pasif' }] },
	},
	{ key: 'actions', label: '', align: 'right', width: '48px', searchable: false },
]

const rowActions = (t) => [
	{ key: 'edit', label: 'Düzenle', icon: 'edit' },
	{ divider: true },
	{ key: 'remove', label: 'Sil', icon: 'trash', danger: true, disabled: t.tenantCount > 0, hint: t.tenantCount > 0 ? `${t.tenantCount} bağlı iş ortağı var` : '' },
]
function onAction(key, t) {
	if (key === 'edit') openEdit(t)
	else if (key === 'remove') remove(t)
}

/* ── ekle / düzenle ── */
const drawerOpen = ref(false)
const editing = ref(null)
const busy = ref(false)
const errors = ref({})
const blank = () => ({ code: '', name: '', description: '', price_list_type: '', is_active: true, sort_order: 0 })
const form = ref(blank())

function openCreate() {
	editing.value = null
	errors.value = {}
	form.value = blank()
	drawerOpen.value = true
}
function openEdit(t) {
	if (!canManage.value) return
	editing.value = t
	errors.value = {}
	form.value = {
		code: t.code, name: t.name, description: t.description ?? '', price_list_type: t.price_list_type ?? '',
		is_active: !!t.is_active, sort_order: t.sort_order ?? 0,
	}
	drawerOpen.value = true
}

function submit() {
	if (busy.value) return
	busy.value = true
	errors.value = {}
	const payload = { ...form.value, price_list_type: form.value.price_list_type || null }
	const title = editing.value ? 'Tip güncellendi' : 'Tip eklendi'
	const opts = {
		preserveScroll: true,
		preserveState: true,
		onSuccess: () => {
			drawerOpen.value = false
			showToast?.({ type: 'success', title, message: payload.name })
		},
		onError: (errs) => {
			errors.value = errs
			const other = Object.entries(errs).find(([k]) => !Object.keys(blank()).includes(k))
			if (other) showToast?.({ type: 'error', title: 'Kaydedilemedi', message: other[1] })
		},
		onFinish: () => { busy.value = false },
	}
	if (editing.value) router.put(`/tenants/types/${editing.value.id}`, payload, opts)
	else router.post('/tenants/types', payload, opts)
}

async function remove(t) {
	const ok = await confirm.danger('Tip silinsin mi?', `“${t.name}” tipi silinecek.`)
	if (!ok) return
	router.delete(`/tenants/types/${t.id}`, {
		preserveScroll: true,
		preserveState: true,
		onSuccess: () => showToast?.({ type: 'warning', title: 'Tip silindi', message: t.name }),
		onError: (errs) => showToast?.({ type: 'error', title: 'Silinemedi', message: Object.values(errs)[0] || 'Sunucu hatası.' }),
	})
}
</script>

<template>
	<Head title="İş Ortağı Tipleri" />

	<OzPageHeader title="İş Ortağı Tipleri" :subtitle="`${types.length} tip kayıtlı`">
		<template v-if="canManage" #actions>
			<OzButton variant="primary" icon="plus" @click="openCreate">Yeni tip</OzButton>
		</template>
	</OzPageHeader>
	<OzModuleTabs />

	<OzCard title="Tip listesi" :meta="`${types.length} kayıt`">
		<OzTable
			:columns="columns"
			:rows="types"
			:default-sort="{ key: 'sort_order', dir: 'asc' }"
			searchable
			search-placeholder="Ad, kod, açıklama ara…"
			min-width="760px"
			class="types"
			:class="{ clickable: canManage }"
			@row-click="openEdit"
		>
			<template #cell-name="{ row }">
				<div class="who-text">
					<b>{{ row.name }}</b>
					<span class="mono">{{ row.code }}</span>
				</div>
			</template>
			<template #cell-description="{ value }"><span class="desc" :class="{ dim: !value }">{{ value || '—' }}</span></template>
			<template #cell-price_list_type="{ value }">
				<OzPill v-if="value" tone="warning">{{ PRICE_LABELS[value] ?? value }}</OzPill>
				<span v-else class="dim">—</span>
			</template>
			<template #cell-tenantCount="{ value }"><span class="num">{{ value }}</span></template>
			<template #cell-sort_order="{ value }"><span class="num dim">{{ value }}</span></template>
			<template #cell-is_active="{ value }">
				<OzPill :tone="value ? 'success' : 'neutral'">{{ value ? 'Aktif' : 'Pasif' }}</OzPill>
			</template>
			<template #cell-actions="{ row }">
				<div v-if="canManage" @click.stop>
					<OzDropdown :items="rowActions(row)" :label="`${row.name} işlemleri`" @select="(k) => onAction(k, row)" />
				</div>
			</template>
			<template #empty="{ filtered, clear }">
				<OzEmptyState v-if="filtered" icon="filter" title="Eşleşen tip yok" compact>
					<template #actions><OzButton size="sm" icon="x" @click="clear">Filtreleri temizle</OzButton></template>
				</OzEmptyState>
				<OzEmptyState v-else icon="tag" title="Henüz tip yok" description="Bayi, dropship gibi tipler fiyat listesini belirler." compact>
					<template v-if="canManage" #actions><OzButton size="sm" variant="primary" icon="plus" @click="openCreate">Yeni tip</OzButton></template>
				</OzEmptyState>
			</template>
		</OzTable>
	</OzCard>

	<OzDrawer
		v-model:open="drawerOpen"
		:title="editing ? `“${editing.name}” düzenle` : 'Yeni iş ortağı tipi'"
		:subtitle="editing ? editing.code : 'İş ortağı tipi bilgilerini girin'"
		:icon="editing ? 'edit' : 'tag'"
		size="sm"
		:persistent="busy"
		initial-focus="auto"
	>
		<form class="form" @submit.prevent="submit">
			<div class="grid-2">
				<OzField label="Kod" required :error="errors.code">
					<OzInput v-model="form.code" class="mono" placeholder="DEALER" autocomplete="off" />
				</OzField>
				<OzField label="Ad" required :error="errors.name">
					<OzInput v-model="form.name" placeholder="Bayi" />
				</OzField>
			</div>
			<OzField label="Açıklama" :error="errors.description">
				<OzTextarea v-model="form.description" :rows="2" placeholder="Bu tipin amacı…" />
			</OzField>
			<div class="grid-2">
				<OzField label="Fiyat listesi" :error="errors.price_list_type">
					<OzSelect v-model="form.price_list_type" :options="[{ value: '', label: 'Seçilmedi' }, ...priceOptions]" />
				</OzField>
				<OzField label="Sıra" :error="errors.sort_order">
					<OzInput v-model="form.sort_order" type="number" min="0" />
				</OzField>
			</div>
			<OzSwitch v-model="form.is_active" label="Aktif" description="Pasif tipler yeni iş ortağı formunda listelenmez" />
			<button type="submit" hidden />
		</form>

		<template #footer>
			<OzButton :disabled="busy" @click="drawerOpen = false">Vazgeç</OzButton>
			<OzButton variant="primary" :icon="editing ? 'check' : 'plus'" :loading="busy" @click="submit">{{ editing ? 'Kaydet' : 'Ekle' }}</OzButton>
		</template>
	</OzDrawer>
</template>

<style scoped>
.types.clickable :deep(tbody tr) { cursor: pointer; }
.who-text { display: flex; flex-direction: column; gap: 1px; }
.who-text b { font-size: 13px; color: var(--oz-ink-soft); white-space: nowrap; }
.who-text span { font-size: 11px; color: var(--oz-muted); }
.mono { font-family: ui-monospace, 'SF Mono', Menlo, Consolas, monospace; font-size: 12px; }
.desc { display: block; max-width: 340px; font-size: 12.5px; color: var(--oz-muted); }
.dim { color: var(--oz-muted-2); }
.num { font-variant-numeric: tabular-nums; font-weight: 600; color: var(--oz-ink-soft); }
.num.dim { font-weight: 400; color: var(--oz-muted-2); }

.form { display: flex; flex-direction: column; gap: 14px; }
.grid-2 { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
@media (max-width: 560px) { .grid-2 { grid-template-columns: minmax(0, 1fr); } }
</style>
