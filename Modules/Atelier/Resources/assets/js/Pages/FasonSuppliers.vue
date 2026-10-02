<script setup>
import { ref, computed, inject, watch } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import {
	OzPageHeader, OzModuleTabs, OzStatStrip, OzCard, OzButton, OzTable, OzPill, OzDropdown, OzAvatar,
	OzDrawer, OzField, OzInput, OzTextarea, OzSwitch, useOzConfirm,
} from '@/Components/Oz'
import { useCan } from '@/composables/useCan'

defineOptions({ layout: AppLayout })

const props = defineProps({ suppliers: { type: Array, default: () => [] } })

const { can } = useCan()
const canManage = computed(() => can('atelier.fason.manage'))
const showToast = inject('showToast', null)
const confirm = useOzConfirm()

const activeCount = computed(() => props.suppliers.filter((s) => s.isActive).length)
const noContact = computed(() => props.suppliers.filter((s) => !s.phone && !s.email).length)
const stats = computed(() => [
	{ key: 'total', label: 'Toplam Fasoncu', value: props.suppliers.length, icon: 'factory', tone: 'accent' },
	{ key: 'active', label: 'Aktif', value: activeCount.value, icon: 'success', tone: 'success' },
	{ key: 'passive', label: 'Pasif', value: props.suppliers.length - activeCount.value, icon: 'eye-off', tone: props.suppliers.length - activeCount.value ? 'warning' : 'success' },
	{ key: 'noContact', label: 'İletişimi Eksik', value: noContact.value, icon: 'phone', tone: noContact.value ? 'warning' : 'success' },
])

const columns = [
	{ key: 'name', label: 'Firma', sortable: true },
	{ key: 'contactName', label: 'Yetkili', sortable: true },
	{ key: 'phone', label: 'Telefon' },
	{ key: 'email', label: 'E-posta' },
	{ key: 'isActive', label: 'Durum', sortable: true, filter: { type: 'select', options: [{ value: true, label: 'Aktif' }, { value: false, label: 'Pasif' }] } },
	{ key: 'actions', label: '', align: 'right', width: '48px', searchable: false },
]

const rowActions = (s) => [
	{ key: 'edit', label: 'Düzenle', icon: 'edit' },
	{ key: 'toggle', label: s.isActive ? 'Pasife Al' : 'Aktif Et', icon: s.isActive ? 'eye-off' : 'eye' },
	{ key: 'remove', label: 'Sil', icon: 'trash', danger: true },
]
function onAction(key, s) {
	if (key === 'edit') openEdit(s)
	else if (key === 'toggle') toggleActive(s)
	else if (key === 'remove') remove(s)
}

const emptyForm = () => ({ id: null, name: '', contact_name: '', phone: '', email: '', address: '', tax_no: '', notes: '', is_active: true })
const form = useForm(emptyForm())
const editing = ref(false)
const formOpen = ref(false)

function resetForm() {
	editing.value = false
	form.defaults(emptyForm())
	form.reset()
	form.clearErrors()
}
watch(formOpen, (open) => { if (!open) setTimeout(resetForm, 250) })

const toFormData = (s) => ({
	id: s.id, name: s.name, contact_name: s.contactName ?? '', phone: s.phone ?? '', email: s.email ?? '',
	address: s.address ?? '', tax_no: s.taxNo ?? '', notes: s.notes ?? '', is_active: !!s.isActive,
})

function openCreate() {
	resetForm()
	formOpen.value = true
}
function openEdit(s) {
	resetForm()
	editing.value = true
	Object.assign(form, toFormData(s))
	formOpen.value = true
}

function submit() {
	if (form.processing) return
	const opts = {
		preserveScroll: true,
		onSuccess: () => { formOpen.value = false },
		onError: (errs) => showToast?.({ type: 'error', title: 'Kayıt başarısız', message: Object.values(errs)[0] || 'Doğrulama hatası.' }),
	}
	editing.value ? form.put(`/atelier/fason-suppliers/${form.id}`, opts) : form.post('/atelier/fason-suppliers', opts)
}

// Güncelleme uç noktası tüm alanları doğruluyor; mevcut kaydın verisiyle yalnızca is_active değişir.
function toggleActive(s) {
	router.put(`/atelier/fason-suppliers/${s.id}`, { ...toFormData(s), is_active: !s.isActive }, {
		preserveScroll: true,
		onError: (errs) => showToast?.({ type: 'error', title: 'Güncellenemedi', message: Object.values(errs)[0] || 'Hata.' }),
	})
}

async function remove(s) {
	const ok = await confirm.danger('Fasoncuyu Sil', `${s.name} fason tedarikçisi kalıcı olarak silinecek.`)
	if (ok) router.delete(`/atelier/fason-suppliers/${s.id}`, { preserveScroll: true })
}
</script>

<template>
	<Head title="Fasoncular" />

	<OzPageHeader title="Fasoncular">
		<template #subtitle><b>{{ suppliers.length }}</b> fason tedarikçi kaydı</template>
		<template #actions>
			<OzButton v-if="canManage" variant="primary" icon="plus" @click="openCreate">Yeni Fasoncu</OzButton>
		</template>
	</OzPageHeader>
	<OzModuleTabs />

	<OzStatStrip :items="stats" />

	<OzCard title="Fasoncu Listesi">
		<OzTable :columns="columns" :rows="suppliers" row-key="id" searchable search-placeholder="Firma, yetkili ya da iletişim ara…" empty-title="Fasoncu kaydı yok" min-width="700px">
			<template #cell-name="{ row }">
				<div class="supplier">
					<OzAvatar :name="row.name" tone="auto" :size="30" />
					<div class="supplier-text">
						<span class="supplier-name">{{ row.name }}</span>
						<span v-if="row.taxNo" class="mono dim">VKN {{ row.taxNo }}</span>
					</div>
				</div>
			</template>
			<template #cell-contactName="{ value }">{{ value || '—' }}</template>
			<template #cell-phone="{ value }">
				<a v-if="value" :href="`tel:${value}`" class="contact">{{ value }}</a><span v-else class="dim">—</span>
			</template>
			<template #cell-email="{ value }">
				<a v-if="value" :href="`mailto:${value}`" class="contact">{{ value }}</a><span v-else class="dim">—</span>
			</template>
			<template #cell-isActive="{ value }">
				<OzPill :tone="value ? 'success' : 'neutral'">{{ value ? 'Aktif' : 'Pasif' }}</OzPill>
			</template>
			<template v-if="canManage" #cell-actions="{ row }">
				<OzDropdown :items="rowActions(row)" :label="`${row.name} işlemleri`" @select="(k) => onAction(k, row)" />
			</template>
		</OzTable>
	</OzCard>

	<OzDrawer v-model:open="formOpen" :title="editing ? 'Fasoncuyu Düzenle' : 'Yeni Fasoncu'" subtitle="Firma ve iletişim bilgileri" :icon="editing ? 'edit' : 'plus'" size="md" :persistent="form.processing">
		<form class="form" @submit.prevent="submit">
			<OzField label="Firma Adı" required :error="form.errors.name">
				<OzInput v-model="form.name" placeholder="Firma adı" />
			</OzField>
			<div class="row-2">
				<OzField label="Yetkili" :error="form.errors.contact_name">
					<OzInput v-model="form.contact_name" icon="user" placeholder="Ad Soyad" />
				</OzField>
				<OzField label="Vergi No" :error="form.errors.tax_no">
					<OzInput v-model="form.tax_no" class="mono" placeholder="1234567890" />
				</OzField>
			</div>
			<div class="row-2">
				<OzField label="Telefon" :error="form.errors.phone">
					<OzInput v-model="form.phone" type="tel" icon="phone" placeholder="+90 5xx…" />
				</OzField>
				<OzField label="E-posta" :error="form.errors.email">
					<OzInput v-model="form.email" type="email" icon="mail" placeholder="ornek@firma.com" />
				</OzField>
			</div>
			<OzField label="Adres" :error="form.errors.address">
				<OzTextarea v-model="form.address" :rows="2" placeholder="Açık adres" />
			</OzField>
			<OzField label="Notlar" :error="form.errors.notes">
				<OzTextarea v-model="form.notes" :rows="2" placeholder="Uzmanlık, kapasite, ödeme koşulu…" />
			</OzField>
			<OzSwitch v-model="form.is_active" label="Aktif" description="Pasif fasoncular iş emri adımlarında seçilemez." />
			<button type="submit" hidden />
		</form>
		<template #footer>
			<OzButton :disabled="form.processing" @click="formOpen = false">İptal</OzButton>
			<OzButton variant="primary" :icon="editing ? 'check' : 'plus'" :loading="form.processing" @click="submit">{{ editing ? 'Kaydet' : 'Ekle' }}</OzButton>
		</template>
	</OzDrawer>
</template>

<style scoped>

.mono { font-family: ui-monospace, monospace; font-size: 11.5px; }
.dim { color: var(--oz-muted); }
.supplier { display: flex; align-items: center; gap: 10px; }
.supplier-text { display: flex; flex-direction: column; gap: 1px; min-width: 0; }
.supplier-name { font-weight: 600; color: var(--oz-ink-soft); }
.contact { color: var(--oz-ink); text-decoration: none; }
.contact:hover { color: var(--oz-accent); text-decoration: underline; }

.form { display: flex; flex-direction: column; gap: 14px; }
.row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
@media (max-width: 560px) {
	.row-2 { grid-template-columns: 1fr; }
}
</style>
