<template>
	<Head title="Banka Hesapları" />
	<div class="page-bank-accounts">
		<Breadcrumb
			:items="[
				{ label: 'Ana Sayfa', to: '/workflow', icon: 'home' },
				{ label: 'Finans', to: '/finance' },
				{ label: 'Banka Hesapları' },
			]"
		/>

		<FinanceNav current="bank" />

		<div class="sub-nav">
			<Link href="/finance/bank-accounts" class="sub-tab active">Hesaplar</Link>
			<Link href="/finance/bank-statements" class="sub-tab">Ekstreler & Mutabakat</Link>
		</div>

		<PageHeader title="Banka Hesapları">
			<template #subtitle><strong>{{ accounts.length }}</strong> hesap kayıtlı</template>
			<template #actions>
				<Button variant="primary" with-icon @click="openCreate">
					<template #leading><Plus :size="13" /></template>
					Yeni Hesap
				</Button>
			</template>
		</PageHeader>

		<Card title="Hesap Listesi" body-class="p-0">
			<DataTable :columns="columns" :data="accounts" row-key-field="id" empty-title="Kayıt bulunamadı">
				<template #iban="{ value }"><span class="mono">{{ value }}</span></template>
				<template #isActive="{ value }">
					<Badge :color="value ? 'success' : 'neutral'" variant="tonal" :label="value ? 'Aktif' : 'Pasif'" />
				</template>
				<template #actions="{ row }">
					<button class="table-action-btn" title="Düzenle" @click="openEdit(row)"><Pencil :size="14" /></button>
					<button class="table-action-btn danger" title="Sil" @click="remove(row)"><Trash2 :size="14" /></button>
				</template>
			</DataTable>
		</Card>

		<AppModal v-model="modalOpen" :title="editing ? 'Hesabı Düzenle' : 'Yeni Hesap'" size="md">
			<form id="bank-account-form" class="form-grid" @submit.prevent="submit">
				<div class="form-row-inline">
					<div class="form-row">
						<label class="form-label">Banka Adı <span class="req">*</span></label>
						<input v-model="form.bank_name" type="text" class="form-input" placeholder="örn. Garanti BBVA" />
						<span v-if="form.errors.bank_name" class="form-error">{{ form.errors.bank_name }}</span>
					</div>
					<div class="form-row">
						<label class="form-label">Hesap Adı <span class="req">*</span></label>
						<input v-model="form.account_name" type="text" class="form-input" placeholder="örn. Şirket TL Hesabı" />
						<span v-if="form.errors.account_name" class="form-error">{{ form.errors.account_name }}</span>
					</div>
					<div class="form-row" style="flex: 2">
						<label class="form-label">IBAN <span class="req">*</span></label>
						<input v-model="form.iban" type="text" class="form-input" placeholder="TR.." />
						<span v-if="form.errors.iban" class="form-error">{{ form.errors.iban }}</span>
					</div>
					<div class="form-row">
						<label class="form-label">Para Birimi</label>
						<input v-model="form.currency" type="text" class="form-input" placeholder="TRY" maxlength="3" />
					</div>
				</div>
				<div class="form-row-inline">
					<div class="form-row">
						<label class="form-label">Hesap No</label>
						<input v-model="form.account_number" type="text" class="form-input" placeholder="isteğe bağlı" />
					</div>
					<div class="form-row" style="flex: 2">
						<label class="form-label">Not</label>
						<input v-model="form.note" type="text" class="form-input" placeholder="isteğe bağlı" />
					</div>
				</div>
			</form>
			<template #footer="{ close }">
				<Button variant="ghost" :disabled="form.processing" @click="close">İptal</Button>
				<Button type="submit" form="bank-account-form" variant="primary" :loading="form.processing">
					{{ editing ? 'Güncelle' : 'Ekle' }}
				</Button>
			</template>
		</AppModal>
	</div>
</template>

<script setup>
import { ref, inject, watch } from 'vue'
import { Head, Link, useForm, router } from '@inertiajs/vue3'
import { Plus, Pencil, Trash2 } from 'lucide-vue-next'
import AppLayout from '@/Layouts/AppLayout.vue'
import Breadcrumb from '@/Components/Breadcrumb.vue'
import PageHeader from '@/Components/PageHeader.vue'
import Card from '@/Components/Card.vue'
import DataTable from '@/Components/DataTable.vue'
import Badge from '@/Components/Badge.vue'
import Button from '@/Components/Button.vue'
import AppModal from '@/Components/AppModal.vue'
import FinanceNav from '../Components/FinanceNav.vue'

defineOptions({ layout: AppLayout })

const $swal = inject('$swal')
const showToast = inject('showToast')

defineProps({
	accounts: { type: Array, default: () => [] },
})

const columns = [
	{ key: 'bankName', label: 'Banka' },
	{ key: 'accountName', label: 'Hesap' },
	{ key: 'iban', label: 'IBAN' },
	{ key: 'isActive', label: 'Durum' },
]

const editing = ref(false)
const modalOpen = ref(false)

function emptyForm() {
	return { id: null, bank_name: '', account_name: '', iban: '', currency: 'TRY', account_number: '', is_active: true, note: '' }
}

const form = useForm(emptyForm())

function openCreate() {
	reset()
	modalOpen.value = true
}

function openEdit(a) {
	editing.value = true
	Object.assign(form, {
		id: a.id, bank_name: a.bankName, account_name: a.accountName, iban: a.iban,
		currency: a.currency, account_number: a.accountNumber, is_active: a.isActive, note: a.note,
	})
	modalOpen.value = true
}

function reset() {
	editing.value = false
	form.reset()
	Object.assign(form, emptyForm())
	form.clearErrors()
}

watch(modalOpen, (open) => {
	if (!open) setTimeout(reset, 250)
})

function submit() {
	const opts = {
		preserveScroll: true,
		onSuccess: () => {
			showToast?.({ type: 'success', title: editing.value ? 'Hesap güncellendi' : 'Hesap eklendi', message: form.bank_name })
			modalOpen.value = false
		},
		onError: () => {
			showToast?.({ type: 'error', title: 'Kayıt başarısız', message: Object.values(form.errors)[0] || 'Doğrulama hatası.' })
		},
	}
	if (editing.value) {
		form.put(`/finance/bank-accounts/${form.id}`, opts)
	} else {
		form.post('/finance/bank-accounts', opts)
	}
}

async function remove(a) {
	const ok = await $swal.dangerConfirm({ title: 'Hesap silinsin mi?', html: `<b>${a.bankName} — ${a.accountName}</b> kalıcı olarak silinecek.` })
	if (!ok) return
	router.delete(`/finance/bank-accounts/${a.id}`, {
		preserveScroll: true,
		onSuccess: () => showToast?.({ type: 'warning', title: 'Hesap silindi', message: a.bankName }),
	})
}
</script>

<style scoped>
.sub-nav { display: flex; gap: 4px; margin-bottom: 18px; }
.sub-tab { padding: 7px 14px; border-radius: 8px; font-size: 12.5px; font-weight: 600; color: rgb(var(--color-muted)); text-decoration: none; background: rgb(var(--color-bg)); }
.sub-tab.active { background: rgb(var(--color-primary-soft)); color: rgb(var(--color-primary)); }

.form-grid { display: flex; flex-direction: column; gap: 12px; }
.form-row-inline { display: flex; gap: 12px; flex-wrap: wrap; align-items: flex-end; }
.form-row { display: flex; flex-direction: column; gap: 6px; flex: 1; min-width: 140px; }
.form-label { font-size: 12px; font-weight: 600; color: rgb(var(--color-ink)); }
.form-label .req { color: rgb(var(--color-danger)); }
.form-input { padding: 9px 12px; border: 1px solid rgb(var(--color-border)); border-radius: 8px; font-family: inherit; font-size: 13px; color: rgb(var(--color-ink)); background: rgb(var(--color-surface)); outline: none; transition: border-color .15s; }
.form-input:focus { border-color: rgb(var(--color-primary)); }
.form-error { font-size: 11.5px; color: rgb(var(--color-danger)); }

.mono { font-family: 'SF Mono', Menlo, Consolas, monospace; font-size: 12px; }

.table-action-btn { display: inline-flex; align-items: center; justify-content: center; background: rgb(var(--color-bg)); border: none; cursor: pointer; padding: 6px; border-radius: 6px; color: rgb(var(--color-muted)); transition: all .15s; }
.table-action-btn:hover { background: rgb(var(--color-primary-soft)); color: rgb(var(--color-primary)); }
.table-action-btn.danger:hover { background: rgb(var(--color-danger) / .12); color: rgb(var(--color-danger)); }
</style>
