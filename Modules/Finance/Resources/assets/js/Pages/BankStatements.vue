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

		<div class="page-header">
			<div>
				<h1 class="page-title">Banka Ekstreleri</h1>
				<p class="page-subtitle">Manuel ekstre içe aktarma (MT940 / CSV / Excel) ve mutabakat</p>
			</div>
		</div>

		<div class="card" style="margin-bottom: 18px;">
			<div class="card-header">
				<h3>Ekstre İçe Aktar</h3>
			</div>
			<div class="form-section">
				<form class="form-grid" @submit.prevent="submitImport">
					<div class="form-row-inline">
						<div class="form-row" style="flex: 2">
							<label class="form-label">Banka Hesabı <span class="req">*</span></label>
							<select v-model="importForm.bank_account_id" class="form-input">
								<option :value="null">— Seçiniz —</option>
								<option v-for="a in accounts" :key="a.value" :value="a.value">{{ a.label }}</option>
							</select>
							<span v-if="importForm.errors.bank_account_id" class="form-error">{{ importForm.errors.bank_account_id }}</span>
						</div>
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
						<div class="form-row form-row-actions">
							<label class="form-label">&nbsp;</label>
							<button type="submit" class="btn btn-primary" :disabled="importForm.processing">İçe Aktar</button>
						</div>
					</div>
				</form>
			</div>
		</div>

		<div class="card" style="margin-bottom: 18px;">
			<div class="card-header">
				<h3>İçe Aktarma Geçmişi</h3>
			</div>
			<table class="data-table">
				<thead>
					<tr>
						<th>Hesap</th>
						<th>Dosya</th>
						<th>Format</th>
						<th>Durum</th>
						<th>Satır</th>
						<th>Tarih</th>
					</tr>
				</thead>
				<tbody>
					<tr v-if="imports.length === 0">
						<td colspan="6" class="empty-row">Henüz içe aktarma yapılmadı</td>
					</tr>
					<tr v-for="imp in imports" :key="imp.id">
						<td>{{ imp.bankAccountLabel }}</td>
						<td>{{ imp.originalFilename }}</td>
						<td class="dim">{{ imp.format }}</td>
						<td>
							<span class="badge" :class="'badge-' + imp.status">{{ importStatusLabel(imp.status) }}</span>
							<span v-if="imp.errorMessage" class="dim" :title="imp.errorMessage"> ⚠️</span>
						</td>
						<td>{{ imp.importedRowCount }}</td>
						<td class="dim">{{ imp.createdAt }}</td>
					</tr>
				</tbody>
			</table>
		</div>

		<div class="card">
			<div class="card-header">
				<h3>Mutabakat Bekleyen İşlemler</h3>
			</div>
			<table class="data-table">
				<thead>
					<tr>
						<th>Hesap</th>
						<th>Tarih</th>
						<th>Açıklama</th>
						<th>Tutar</th>
						<th>Aday Eşleşme</th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					<tr v-if="transactions.length === 0">
						<td colspan="6" class="empty-row">Eşleşmemiş işlem yok</td>
					</tr>
					<tr v-for="tx in transactions" :key="tx.id">
						<td class="dim">{{ tx.bankAccountLabel }}</td>
						<td class="dim">{{ tx.transactionDate }}</td>
						<td>{{ tx.description || tx.reference || '—' }}</td>
						<td :class="tx.amount >= 0 ? 'amount-in' : 'amount-out'">{{ formatMoney(tx.amount) }}</td>
						<td>
							<select v-model="selectedCandidate[tx.id]" class="form-input form-input-sm">
								<option :value="null">— Aday yok —</option>
								<option v-for="c in tx.candidates" :key="c.type + '-' + c.id" :value="c">{{ c.label }}</option>
							</select>
						</td>
						<td>
							<div class="table-actions">
								<button
									class="table-action-btn"
									:disabled="!selectedCandidate[tx.id]"
									title="Eşleştir"
									@click="matchTransaction(tx)"
								>✅</button>
								<button class="table-action-btn" title="Yoksay" @click="ignoreTransaction(tx)">🚫</button>
							</div>
						</td>
					</tr>
				</tbody>
			</table>
		</div>
	</div>
</template>

<script setup>
import { reactive, inject } from 'vue'
import { Head, Link, useForm, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Breadcrumb from '@/Components/Breadcrumb.vue'
import FinanceNav from '../Components/FinanceNav.vue'

defineOptions({ layout: AppLayout })

const showToast = inject('showToast')

defineProps({
	imports: { type: Array, default: () => [] },
	transactions: { type: Array, default: () => [] },
	accounts: { type: Array, default: () => [] },
})

const importForm = useForm({
	bank_account_id: null,
	format: 'mt940',
	file: null,
})

function onFileChange(e) {
	importForm.file = e.target.files[0] ?? null
}

function submitImport() {
	importForm.post('/finance/bank-statements/import', {
		preserveScroll: true,
		onSuccess: () => {
			showToast?.({ type: 'success', title: 'Ekstre içe aktarıldı' })
			importForm.reset()
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

function formatMoney(value) {
	return new Intl.NumberFormat('tr-TR', { style: 'currency', currency: 'TRY' }).format(value ?? 0)
}
</script>

<style scoped>
.sub-nav { display: flex; gap: 4px; margin-bottom: 18px; }
.sub-tab { padding: 7px 14px; border-radius: 8px; font-size: 12.5px; font-weight: 600; color: #888; text-decoration: none; background: #f0f0f5; }
.sub-tab.active { background: rgb(var(--color-primary-soft)); color: rgb(var(--color-primary)); }

.page-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 20px; gap: 16px; }
.page-title { font-size: 22px; font-weight: 700; color: #1a1a2e; line-height: 1.2; }
.page-subtitle { font-size: 13px; color: #888; margin-top: 4px; }

.card { background: #fff; border-radius: 16px; border: 1px solid #ebebf0; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,.04); }
.card-header { padding: 14px 18px; display: flex; align-items: center; gap: 12px; border-bottom: 1px solid #f0f0f5; }
.card-header h3 { font-size: 15px; font-weight: 700; color: #1a1a2e; }

.form-section { padding: 16px 18px; }
.form-grid { display: flex; flex-direction: column; gap: 12px; }
.form-row-inline { display: flex; gap: 12px; flex-wrap: wrap; align-items: flex-end; }
.form-row { display: flex; flex-direction: column; gap: 6px; flex: 1; min-width: 140px; }
.form-row-actions { flex: 0 0 auto; min-width: unset; }
.form-label { font-size: 12px; font-weight: 600; color: #1a1a2e; }
.form-label .req { color: #ef4444; }
.form-input { padding: 9px 12px; border: 1px solid #e8e8f0; border-radius: 8px; font-family: inherit; font-size: 13px; color: #1a1a2e; background: #fff; outline: none; transition: border-color .15s; }
.form-input:focus { border-color: rgb(var(--color-primary)); }
.form-input-sm { padding: 6px 8px; font-size: 12px; min-width: 180px; }
.form-error { font-size: 11.5px; color: #ef4444; }

.data-table { width: 100%; border-collapse: separate; border-spacing: 0; }
.data-table thead tr { background: #f8f8fc; }
.data-table th { text-align: left; padding: 12px 16px; font-size: 11px; font-weight: 600; color: #aaa; border-bottom: 1px solid #f0f0f5; text-transform: uppercase; letter-spacing: 0.04em; }
.data-table td { padding: 12px 16px; font-size: 13px; color: #444; border-bottom: 1px solid #f5f5f8; vertical-align: middle; }
.data-table tr:last-child td { border-bottom: none; }
.data-table tr:hover td { background: #fafafe; }
.empty-row { text-align: center !important; color: #aaa; padding: 32px 0 !important; font-style: italic; }
.dim { font-size: 12px; color: #888; }

.amount-in { color: #059669; font-weight: 700; }
.amount-out { color: #dc2626; font-weight: 700; }

.badge { display: inline-block; padding: 3px 9px; border-radius: 6px; font-size: 11px; font-weight: 700; background: #f0f0f5; color: #555; }
.badge-completed { background: #dcfce7; color: #166534; }
.badge-pending, .badge-processing { background: #fef9c3; color: #854d0e; }
.badge-failed { background: #fee2e2; color: #991b1b; }

.table-actions { display: flex; gap: 4px; justify-content: flex-end; }
.table-action-btn { background: #f3f4f6; border: none; cursor: pointer; font-size: 13px; padding: 5px 9px; border-radius: 6px; color: #6b7280; transition: all .15s; }
.table-action-btn:hover:not(:disabled) { background: rgb(var(--color-primary-soft)); color: rgb(var(--color-primary)); }
.table-action-btn:disabled { opacity: .4; cursor: not-allowed; }
</style>
