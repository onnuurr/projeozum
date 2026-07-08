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

		<div class="page-header">
			<div>
				<h1 class="page-title">Banka Hesapları</h1>
				<p class="page-subtitle"><strong>{{ accounts.length }}</strong> hesap kayıtlı</p>
			</div>
		</div>

		<div class="card" style="margin-bottom: 18px;">
			<div class="card-header">
				<h3>{{ editing ? 'Hesabı Düzenle' : 'Yeni Hesap' }}</h3>
			</div>
			<div class="form-section">
				<form class="form-grid" @submit.prevent="submit">
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
						<div class="form-row form-row-actions">
							<label class="form-label">&nbsp;</label>
							<div class="btn-group">
								<button type="submit" class="btn btn-primary" :disabled="form.processing">
									{{ editing ? 'Güncelle' : 'Ekle' }}
								</button>
								<button v-if="editing" type="button" class="btn btn-ghost" @click="reset">Vazgeç</button>
							</div>
						</div>
					</div>
				</form>
			</div>
		</div>

		<div class="card">
			<div class="card-header">
				<h3>Hesap Listesi</h3>
			</div>
			<table class="data-table">
				<thead>
					<tr>
						<th>Banka</th>
						<th>Hesap</th>
						<th>IBAN</th>
						<th>Durum</th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					<tr v-if="accounts.length === 0">
						<td colspan="5" class="empty-row">Kayıt bulunamadı</td>
					</tr>
					<tr v-for="a in accounts" :key="a.id">
						<td>{{ a.bankName }}</td>
						<td>{{ a.accountName }}</td>
						<td class="mono">{{ a.iban }}</td>
						<td>
							<span class="badge" :class="a.isActive ? 'badge-active' : 'badge-passive'">
								{{ a.isActive ? 'Aktif' : 'Pasif' }}
							</span>
						</td>
						<td>
							<div class="table-actions">
								<button class="table-action-btn" title="Düzenle" @click="edit(a)">✏️</button>
								<button class="table-action-btn danger" title="Sil" @click="remove(a)">🗑️</button>
							</div>
						</td>
					</tr>
				</tbody>
			</table>
		</div>
	</div>
</template>

<script setup>
import { ref, inject } from 'vue'
import { Head, Link, useForm, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Breadcrumb from '@/Components/Breadcrumb.vue'
import FinanceNav from '../Components/FinanceNav.vue'

defineOptions({ layout: AppLayout })

const $swal = inject('$swal')
const showToast = inject('showToast')

defineProps({
	accounts: { type: Array, default: () => [] },
})

const editing = ref(false)

function emptyForm() {
	return { id: null, bank_name: '', account_name: '', iban: '', currency: 'TRY', account_number: '', is_active: true, note: '' }
}

const form = useForm(emptyForm())

function edit(a) {
	editing.value = true
	Object.assign(form, {
		id: a.id, bank_name: a.bankName, account_name: a.accountName, iban: a.iban,
		currency: a.currency, account_number: a.accountNumber, is_active: a.isActive, note: a.note,
	})
}

function reset() {
	editing.value = false
	form.reset()
	Object.assign(form, emptyForm())
	form.clearErrors()
}

function submit() {
	const opts = {
		preserveScroll: true,
		onSuccess: () => {
			showToast?.({ type: 'success', title: editing.value ? 'Hesap güncellendi' : 'Hesap eklendi', message: form.bank_name })
			reset()
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
.form-error { font-size: 11.5px; color: #ef4444; }
.btn-group { display: flex; gap: 8px; }

.data-table { width: 100%; border-collapse: separate; border-spacing: 0; }
.data-table thead tr { background: #f8f8fc; }
.data-table th { text-align: left; padding: 12px 16px; font-size: 11px; font-weight: 600; color: #aaa; border-bottom: 1px solid #f0f0f5; text-transform: uppercase; letter-spacing: 0.04em; }
.data-table td { padding: 12px 16px; font-size: 13px; color: #444; border-bottom: 1px solid #f5f5f8; vertical-align: middle; }
.data-table tr:last-child td { border-bottom: none; }
.data-table tr:hover td { background: #fafafe; }
.empty-row { text-align: center !important; color: #aaa; padding: 32px 0 !important; font-style: italic; }
.mono { font-family: 'SF Mono', Menlo, Consolas, monospace; font-size: 12px; }

.badge { display: inline-block; padding: 3px 9px; border-radius: 6px; font-size: 11px; font-weight: 700; }
.badge-active { background: #dcfce7; color: #166534; }
.badge-passive { background: #f0f0f5; color: #888; }

.table-actions { display: flex; gap: 4px; justify-content: flex-end; }
.table-action-btn { background: #f3f4f6; border: none; cursor: pointer; font-size: 13px; padding: 5px 9px; border-radius: 6px; color: #6b7280; transition: all .15s; }
.table-action-btn:hover { background: rgb(var(--color-primary-soft)); color: rgb(var(--color-primary)); }
.table-action-btn.danger:hover { background: #fee2e2; color: #dc2626; }
</style>
