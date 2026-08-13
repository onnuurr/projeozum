<template>
	<Head title="Banka Ekstreleri" />
	<div class="page-bank-statements">
		<Breadcrumb
			:items="[
				{ label: 'Ana Sayfa', to: '/workflow', icon: 'home' },
				{ label: 'Finans', to: '/finance' },
				{ label: 'Banka Ekstreleri' },
			]"
		/>

		<FinanceNav current="bank" />

		<div class="sub-nav">
			<Link href="/finance/bank-accounts" class="sub-tab">Hesaplar</Link>
			<Link href="/finance/bank-statements" class="sub-tab active">Ekstreler & Mutabakat</Link>
		</div>

		<PageHeader title="Banka Ekstreleri" subtitle="Manuel ekstre içe aktarma (MT940 / CSV / Excel) ve mutabakat">
			<template #actions>
				<Button variant="primary" with-icon @click="importModalOpen = true">
					<template #leading><Upload :size="13" /></template>
					Ekstre İçe Aktar
				</Button>
			</template>
		</PageHeader>

		<Card title="İçe Aktarma Geçmişi" body-class="p-0" style="margin-bottom: 18px;">
			<DataTable :columns="importColumns" :data="imports" row-key-field="id" empty-title="Henüz içe aktarma yapılmadı">
				<template #format="{ value }"><span class="dim">{{ value }}</span></template>
				<template #status="{ row }">
					<Badge :color="importStatusColor(row.status)" variant="tonal" :label="importStatusLabel(row.status)" />
					<Tooltip v-if="row.errorMessage" :text="row.errorMessage" position="top">
						<AlertTriangle :size="12" class="inline align-text-bottom text-warning ml-1" />
					</Tooltip>
				</template>
				<template #createdAt="{ value }"><span class="dim">{{ value }}</span></template>
			</DataTable>
		</Card>

		<Card title="Mutabakat Bekleyen İşlemler" body-class="p-0">
			<DataTable :columns="txColumns" :data="transactions" row-key-field="id" empty-title="Eşleşmemiş işlem yok">
				<template #bankAccountLabel="{ value }"><span class="dim">{{ value }}</span></template>
				<template #transactionDate="{ value }"><span class="dim">{{ value }}</span></template>
				<template #description="{ row }">{{ row.description || row.reference || '—' }}</template>
				<template #amount="{ row }">
					<span :class="row.amount >= 0 ? 'amount-in' : 'amount-out'">{{ formatMoney(row.amount) }}</span>
				</template>
				<template #candidate="{ row }">
					<select v-model="selectedCandidate[row.id]" class="form-input form-input-sm">
						<option :value="null">— Aday yok —</option>
						<option v-for="c in row.candidates" :key="c.type + '-' + c.id" :value="c">{{ c.label }}</option>
					</select>
				</template>
				<template #actions="{ row }">
					<button
						class="table-action-btn"
						:disabled="!selectedCandidate[row.id]"
						title="Eşleştir"
						@click="matchTransaction(row)"
					><CheckCircle2 :size="14" /></button>
					<button class="table-action-btn" title="Yoksay" @click="ignoreTransaction(row)"><Ban :size="14" /></button>
				</template>
			</DataTable>
		</Card>

		<AppModal v-model="importModalOpen" title="Ekstre İçe Aktar" size="md">
			<form id="statement-import-form" class="form-grid" @submit.prevent="submitImport">
				<div class="form-row">
					<label class="form-label">Banka Hesabı <span class="req">*</span></label>
					<select v-model="importForm.bank_account_id" class="form-input">
						<option :value="null">— Seçiniz —</option>
						<option v-for="a in accounts" :key="a.value" :value="a.value">{{ a.label }}</option>
					</select>
					<span v-if="importForm.errors.bank_account_id" class="form-error">{{ importForm.errors.bank_account_id }}</span>
				</div>
				<div class="form-row-inline">
					<div class="form-row">
						<label class="form-label">Format <span class="req">*</span></label>
						<select v-model="importForm.format" class="form-input">
							<option value="mt940">MT940</option>
							<option value="csv">CSV</option>
							<option value="xlsx">Excel (XLSX)</option>
						</select>
					</div>
					<div class="form-row" style="flex: 2">
						<label class="form-label">Dosya <span class="req">*</span></label>
						<input type="file" class="form-input" accept=".txt,.sta,.csv,.xlsx,.xls" @change="onFileChange" />
						<span v-if="importForm.errors.file" class="form-error">{{ importForm.errors.file }}</span>
					</div>
				</div>
			</form>
			<template #footer="{ close }">
				<Button variant="ghost" :disabled="importForm.processing" @click="close">İptal</Button>
				<Button type="submit" form="statement-import-form" variant="primary" :loading="importForm.processing">İçe Aktar</Button>
			</template>
		</AppModal>
	</div>
</template>

<script setup>
import { reactive, ref, watch, inject } from 'vue'
import { Head, Link, useForm, router } from '@inertiajs/vue3'
import { Upload, AlertTriangle, CheckCircle2, Ban } from 'lucide-vue-next'
import AppLayout from '@/Layouts/AppLayout.vue'
import Breadcrumb from '@/Components/Breadcrumb.vue'
import PageHeader from '@/Components/PageHeader.vue'
import Card from '@/Components/Card.vue'
import DataTable from '@/Components/DataTable.vue'
import Badge from '@/Components/Badge.vue'
import Button from '@/Components/Button.vue'
import AppModal from '@/Components/AppModal.vue'
import Tooltip from '@/Components/Tooltip.vue'
import FinanceNav from '../Components/FinanceNav.vue'

defineOptions({ layout: AppLayout })

const showToast = inject('showToast')

defineProps({
	imports: { type: Array, default: () => [] },
	transactions: { type: Array, default: () => [] },
	accounts: { type: Array, default: () => [] },
})

const importColumns = [
	{ key: 'bankAccountLabel', label: 'Hesap' },
	{ key: 'originalFilename', label: 'Dosya' },
	{ key: 'format', label: 'Format' },
	{ key: 'status', label: 'Durum' },
	{ key: 'importedRowCount', label: 'Satır' },
	{ key: 'createdAt', label: 'Tarih' },
]

const txColumns = [
	{ key: 'bankAccountLabel', label: 'Hesap' },
	{ key: 'transactionDate', label: 'Tarih' },
	{ key: 'description', label: 'Açıklama' },
	{ key: 'amount', label: 'Tutar' },
	{ key: 'candidate', label: 'Aday Eşleşme', sortable: false },
]

const importModalOpen = ref(false)

const importForm = useForm({
	bank_account_id: null,
	format: 'mt940',
	file: null,
})

watch(importModalOpen, (open) => {
	if (!open) setTimeout(() => { importForm.reset(); importForm.clearErrors() }, 250)
})

function onFileChange(e) {
	importForm.file = e.target.files[0] ?? null
}

function submitImport() {
	importForm.post('/finance/bank-statements/import', {
		preserveScroll: true,
		onSuccess: () => {
			showToast?.({ type: 'success', title: 'Ekstre içe aktarıldı' })
			importModalOpen.value = false
		},
		onError: () => {
			showToast?.({ type: 'error', title: 'İçe aktarma başarısız', message: Object.values(importForm.errors)[0] || 'Doğrulama hatası.' })
		},
	})
}

const selectedCandidate = reactive({})

function matchTransaction(tx) {
	const candidate = selectedCandidate[tx.id]
	if (!candidate) return
	router.post(`/finance/bank-statements/transactions/${tx.id}/match`, { type: candidate.type, id: candidate.id }, {
		preserveScroll: true,
		onSuccess: () => showToast?.({ type: 'success', title: 'İşlem eşleştirildi' }),
	})
}

function ignoreTransaction(tx) {
	router.post(`/finance/bank-statements/transactions/${tx.id}/ignore`, {}, {
		preserveScroll: true,
		onSuccess: () => showToast?.({ type: 'warning', title: 'İşlem yoksayıldı' }),
	})
}

function importStatusLabel(status) {
	return { pending: 'Bekliyor', processing: 'İşleniyor', completed: 'Tamamlandı', failed: 'Başarısız' }[status] ?? status
}

function importStatusColor(status) {
	return { pending: 'warning', processing: 'warning', completed: 'success', failed: 'danger' }[status] ?? 'neutral'
}

function formatMoney(value) {
	return new Intl.NumberFormat('tr-TR', { style: 'currency', currency: 'TRY' }).format(value ?? 0)
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
.form-input-sm { padding: 6px 8px; font-size: 12px; min-width: 180px; }
.form-error { font-size: 11.5px; color: rgb(var(--color-danger)); }

.dim { font-size: 12px; color: rgb(var(--color-muted)); }

.amount-in { color: rgb(var(--color-success)); font-weight: 700; }
.amount-out { color: rgb(var(--color-danger)); font-weight: 700; }

.table-action-btn { display: inline-flex; align-items: center; justify-content: center; background: rgb(var(--color-bg)); border: none; cursor: pointer; padding: 6px; border-radius: 6px; color: rgb(var(--color-muted)); transition: all .15s; }
.table-action-btn:hover:not(:disabled) { background: rgb(var(--color-primary-soft)); color: rgb(var(--color-primary)); }
.table-action-btn:disabled { opacity: .4; cursor: not-allowed; }
</style>
