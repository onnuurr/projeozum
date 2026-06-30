<template>
	<Head title="Tenant'lar" />
	<div class="page-tenants">
		<Breadcrumb
			:items="[
				{ label: 'Ana Sayfa', to: '/workflow', icon: 'home' },
				{ label: 'İlişkiler' },
				{ label: 'Tenant\'lar' },
			]"
		/>

		<div class="page-header">
			<div>
				<h1 class="page-title">Tenant'lar</h1>
				<p class="page-subtitle"><strong>{{ tenants.length }}</strong> tenant kayıtlı</p>
			</div>
			<button v-if="canManage" class="btn btn-primary btn-with-icon" @click="openNew">
				<svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
					<path d="M12 5v14M5 12h14" />
				</svg>
				Yeni Tenant
			</button>
		</div>

		<div class="card">
			<div class="card-header">
				<h3>Tenant Listesi</h3>
				<div class="card-search">
					<svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
						<circle cx="11" cy="11" r="8" /><path d="M21 21l-4.35-4.35" />
					</svg>
					<input v-model="searchQuery" type="text" placeholder="Tenant ara (isim, kod, vergi no, e-posta)..." />
				</div>
				<select v-model="filterType" class="filter-select">
					<option value="">Tüm tipler</option>
					<option v-for="t in types" :key="t.id" :value="t.id">{{ t.name }}</option>
				</select>
				<select v-model="filterActive" class="filter-select">
					<option value="">Tüm durumlar</option>
					<option value="1">Aktif</option>
					<option value="0">Pasif</option>
				</select>
			</div>

			<table class="data-table">
				<thead>
					<tr>
						<th style="width: 25%">Tenant</th>
						<th style="width: 12%">Tip</th>
						<th style="width: 14%">İletişim</th>
						<th style="width: 12%">Vergi No</th>
						<th style="width: 13%">Kredi (kullanılan / limit)</th>
						<th style="width: 8%">Kullanıcı</th>
						<th style="width: 8%">Durum</th>
						<th style="width: 8%">İşlemler</th>
					</tr>
				</thead>
				<tbody>
					<tr v-if="filtered.length === 0">
						<td colspan="8" class="empty-row">Kayıt bulunamadı</td>
					</tr>
					<tr v-for="t in filtered" :key="t.id">
						<td>
							<div class="tenant-cell">
								<div class="tenant-logo">{{ t.name.charAt(0).toUpperCase() }}</div>
								<div class="tenant-info">
									<span class="tenant-name">{{ t.name }}</span>
									<span class="tenant-code">{{ t.code }}</span>
								</div>
							</div>
						</td>
						<td>
							<span v-if="t.type" class="badge badge-type">{{ t.type.name }}</span>
							<span v-else class="dim">—</span>
						</td>
						<td>
							<div class="contact-cell">
								<span v-if="t.email" class="contact-line">{{ t.email }}</span>
								<span v-if="t.phone" class="contact-line dim">{{ t.phone }}</span>
							</div>
						</td>
						<td>
							<span v-if="t.tax_number" class="mono">{{ t.tax_number }}</span>
							<span v-else class="dim">—</span>
						</td>
						<td>
							<div class="credit-cell">
								<div class="credit-bar">
									<div class="credit-bar-fill" :style="{ width: creditUsage(t) + '%', background: creditColor(t) }"></div>
								</div>
								<span class="credit-text">{{ formatMoney(t.current_balance) }} / {{ formatMoney(t.credit_limit) }}</span>
							</div>
						</td>
						<td>
							<span class="user-count">{{ t.userCount }}</span>
						</td>
						<td>
							<span :class="['status-pill', t.is_active ? 'active' : 'inactive']">
								{{ t.is_active ? 'Aktif' : 'Pasif' }}
							</span>
						</td>
						<td>
							<div class="table-actions">
								<button v-if="canAccess" class="table-action-btn access" @click="openAccess(t)" title="Erişim Yönet">🔐</button>
								<button v-if="canMarketplace" class="table-action-btn marketplace" @click="openMarketplace(t)" title="Pazaryeri Bağlantıları">🛍️</button>
								<button v-if="canManage" class="table-action-btn view" @click="edit(t)" title="Düzenle">✏️</button>
								<button v-if="canManage" class="table-action-btn toggle" @click="toggle(t)" :title="t.is_active ? 'Pasifleştir' : 'Aktifleştir'">
									{{ t.is_active ? '⏸️' : '▶️' }}
								</button>
								<button v-if="canManage" class="table-action-btn delete" @click="confirmDelete(t)" title="Sil">🗑️</button>
							</div>
						</td>
					</tr>
				</tbody>
			</table>
		</div>

		<!-- Form modal -->
		<AppModal v-model="formOpen" :title="editing ? 'Tenant Düzenle' : 'Yeni Tenant'" size="lg" variant="info">
			<form class="form-grid" @submit.prevent="submit">
				<div class="form-section">
					<h4 class="form-section-title">Temel Bilgiler</h4>
					<div class="form-row-2">
						<div class="form-row">
							<label class="form-label">Tenant Kodu <span class="req">*</span></label>
							<input v-model="form.code" type="text" class="form-input mono" placeholder="BAYI001" />
							<span v-if="errors.code" class="form-error">{{ errors.code }}</span>
						</div>
						<div class="form-row">
							<label class="form-label">Tenant Tipi</label>
							<select v-model="form.tenant_type_id" class="form-input">
								<option :value="null">Seçilmedi</option>
								<option v-for="t in types" :key="t.id" :value="t.id">{{ t.name }}</option>
							</select>
							<span v-if="errors.tenant_type_id" class="form-error">{{ errors.tenant_type_id }}</span>
						</div>
					</div>
					<div class="form-row-2">
						<div class="form-row">
							<label class="form-label">Ad <span class="req">*</span></label>
							<input v-model="form.name" type="text" class="form-input" placeholder="örn. ABC Tekstil" />
							<span v-if="errors.name" class="form-error">{{ errors.name }}</span>
						</div>
						<div class="form-row">
							<label class="form-label">Yasal Ünvan</label>
							<input v-model="form.legal_name" type="text" class="form-input" placeholder="ABC Tekstil San. Tic. Ltd. Şti." />
							<span v-if="errors.legal_name" class="form-error">{{ errors.legal_name }}</span>
						</div>
					</div>
				</div>

				<div class="form-section">
					<h4 class="form-section-title">Vergi & Yasal</h4>
					<div class="form-row-2">
						<div class="form-row">
							<label class="form-label">Vergi No</label>
							<input v-model="form.tax_number" type="text" class="form-input mono" placeholder="1234567890" />
							<span v-if="errors.tax_number" class="form-error">{{ errors.tax_number }}</span>
						</div>
						<div class="form-row">
							<label class="form-label">Vergi Dairesi</label>
							<input v-model="form.tax_office" type="text" class="form-input" placeholder="Kadıköy" />
							<span v-if="errors.tax_office" class="form-error">{{ errors.tax_office }}</span>
						</div>
					</div>
				</div>

				<div class="form-section">
					<h4 class="form-section-title">İletişim</h4>
					<div class="form-row-2">
						<div class="form-row">
							<label class="form-label">E-posta</label>
							<input v-model="form.email" type="email" class="form-input" placeholder="info@firma.com" />
							<span v-if="errors.email" class="form-error">{{ errors.email }}</span>
						</div>
						<div class="form-row">
							<label class="form-label">Telefon</label>
							<input v-model="form.phone" type="text" class="form-input" placeholder="+90 212 ..." />
							<span v-if="errors.phone" class="form-error">{{ errors.phone }}</span>
						</div>
					</div>
					<div class="form-row-2">
						<div class="form-row">
							<label class="form-label">Yetkili Kişi</label>
							<input v-model="form.contact_person" type="text" class="form-input" placeholder="Ahmet Yılmaz" />
							<span v-if="errors.contact_person" class="form-error">{{ errors.contact_person }}</span>
						</div>
						<div class="form-row">
							<label class="form-label">Yetkili Telefonu</label>
							<input v-model="form.contact_phone" type="text" class="form-input" placeholder="+90 532 ..." />
							<span v-if="errors.contact_phone" class="form-error">{{ errors.contact_phone }}</span>
						</div>
					</div>
				</div>

				<div class="form-section">
					<h4 class="form-section-title">Adres</h4>
					<div class="form-row">
						<label class="form-label">Adres</label>
						<textarea v-model="form.address" class="form-input" rows="2" placeholder="Cadde, sokak, bina no..." />
					</div>
					<div class="form-row-3">
						<div class="form-row">
							<label class="form-label">İl</label>
							<input v-model="form.city" type="text" class="form-input" placeholder="İstanbul" />
						</div>
						<div class="form-row">
							<label class="form-label">İlçe</label>
							<input v-model="form.district" type="text" class="form-input" placeholder="Kadıköy" />
						</div>
						<div class="form-row">
							<label class="form-label">Posta Kodu</label>
							<input v-model="form.postal_code" type="text" class="form-input" placeholder="34000" />
						</div>
					</div>
				</div>

				<div class="form-section">
					<h4 class="form-section-title">Ticari Koşullar</h4>
					<div class="form-row-3">
						<div class="form-row">
							<label class="form-label">Kredi Limiti (₺)</label>
							<input v-model.number="form.credit_limit" type="number" min="0" step="0.01" class="form-input" placeholder="0" />
							<span v-if="errors.credit_limit" class="form-error">{{ errors.credit_limit }}</span>
						</div>
						<div class="form-row">
							<label class="form-label">Vade (gün)</label>
							<input v-model.number="form.payment_term_days" type="number" min="0" max="365" class="form-input" placeholder="0" />
							<span v-if="errors.payment_term_days" class="form-error">{{ errors.payment_term_days }}</span>
						</div>
						<div class="form-row">
							<label class="form-label">İskonto (%)</label>
							<input v-model.number="form.discount_rate" type="number" min="0" max="100" step="0.01" class="form-input" placeholder="0" />
							<span v-if="errors.discount_rate" class="form-error">{{ errors.discount_rate }}</span>
						</div>
					</div>
				</div>

				<div class="form-section">
					<div class="form-row">
						<label class="form-label">Notlar</label>
						<textarea v-model="form.notes" class="form-input" rows="2" placeholder="Tenant hakkında özel notlar..." />
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
				<button class="btn btn-ghost" @click="close" :disabled="busy">İptal</button>
				<button class="btn btn-primary" @click="submit" :disabled="busy">
					{{ editing ? 'Kaydet' : 'Ekle' }}
				</button>
			</template>
		</AppModal>
	</div>
</template>

<script setup>
import { ref, computed, inject, reactive, watch } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Breadcrumb from '@/Components/Breadcrumb.vue'
import AppModal from '@/Components/AppModal.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
	tenants: { type: Array, default: () => [] },
	types: { type: Array, default: () => [] },
	filters: { type: Object, default: () => ({}) },
})

const showToast = inject('showToast')
const $swal = inject('$swal')
const page = usePage()

const canManage = computed(() => (page.props.auth?.permissions ?? []).includes('tenant.manage'))
const canAccess = computed(() => (page.props.auth?.permissions ?? []).includes('tenant-access.manage'))
const canMarketplace = computed(() => (page.props.auth?.permissions ?? []).includes('marketplace.manage'))

function openAccess(t) {
	router.visit(`/tenants/${t.id}/access`)
}

function openMarketplace(t) {
	router.visit(`/tenants/${t.id}/marketplace`)
}

const searchQuery = ref(props.filters.q ?? '')
const filterType = ref(props.filters.type_id ?? '')
const filterActive = ref(props.filters.active ?? '')

const filtered = computed(() => {
	const q = searchQuery.value.trim().toLowerCase()
	return props.tenants.filter(t => {
		if (filterType.value !== '' && Number(t.type?.id ?? 0) !== Number(filterType.value)) return false
		if (filterActive.value !== '' && (t.is_active ? '1' : '0') !== String(filterActive.value)) return false
		if (q) {
			const hay = [t.name, t.code, t.legal_name, t.tax_number, t.email].filter(Boolean).join(' ').toLowerCase()
			if (!hay.includes(q)) return false
		}
		return true
	})
})

function formatMoney(v) {
	const n = Number(v ?? 0)
	return new Intl.NumberFormat('tr-TR', { style: 'currency', currency: 'TRY', maximumFractionDigits: 0 }).format(n)
}

function creditUsage(t) {
	const limit = Number(t.credit_limit ?? 0)
	if (limit <= 0) return 0
	return Math.min(100, Math.round((Number(t.current_balance ?? 0) / limit) * 100))
}
function creditColor(t) {
	const u = creditUsage(t)
	if (u >= 90) return '#ef4444'
	if (u >= 70) return '#f59e0b'
	return '#10b981'
}

/* ── Form state ── */
const formOpen = ref(false)
const editing = ref(null)
const busy = ref(false)
const errors = ref({})

const emptyForm = () => ({
	code: '',
	name: '',
	legal_name: '',
	tenant_type_id: null,
	tax_number: '',
	tax_office: '',
	email: '',
	phone: '',
	contact_person: '',
	contact_phone: '',
	address: '',
	city: '',
	district: '',
	country: 'TR',
	postal_code: '',
	credit_limit: 0,
	payment_term_days: 0,
	discount_rate: 0,
	is_active: true,
	notes: '',
})

const form = reactive(emptyForm())

function resetForm() {
	Object.assign(form, emptyForm())
}

watch(formOpen, (open) => {
	if (!open) {
		setTimeout(() => {
			editing.value = null
			errors.value = {}
			resetForm()
		}, 250)
	}
})

function openNew() {
	editing.value = null
	errors.value = {}
	resetForm()
	formOpen.value = true
}

function edit(t) {
	editing.value = t
	errors.value = {}
	Object.assign(form, {
		code: t.code,
		name: t.name,
		legal_name: t.legal_name ?? '',
		tenant_type_id: t.type?.id ?? null,
		tax_number: t.tax_number ?? '',
		tax_office: t.tax_office ?? '',
		email: t.email ?? '',
		phone: t.phone ?? '',
		contact_person: t.contact_person ?? '',
		contact_phone: t.contact_phone ?? '',
		address: t.address ?? '',
		city: t.city ?? '',
		district: t.district ?? '',
		country: t.country ?? 'TR',
		postal_code: t.postal_code ?? '',
		credit_limit: Number(t.credit_limit ?? 0),
		payment_term_days: Number(t.payment_term_days ?? 0),
		discount_rate: Number(t.discount_rate ?? 0),
		is_active: !!t.is_active,
		notes: t.notes ?? '',
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
				title: editing.value ? 'Tenant güncellendi' : 'Tenant eklendi',
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
		router.put(`/tenants/${editing.value.id}`, { ...form }, opts)
	} else {
		router.post('/tenants', { ...form }, opts)
	}
}

function toggle(t) {
	router.post(`/tenants/${t.id}/toggle`, {}, {
		preserveScroll: true,
		preserveState: true,
		onSuccess: () => {
			showToast?.({
				type: 'success',
				title: t.is_active ? 'Tenant pasifleştirildi' : 'Tenant aktifleştirildi',
				message: t.name,
			})
		},
		onError: (errs) => {
			showToast?.({
				type: 'error',
				title: 'İşlem başarısız',
				message: Object.values(errs)[0] || 'Sunucu hatası.',
			})
		},
	})
}

async function confirmDelete(t) {
	if (t.userCount > 0) {
		await $swal.fire({
			icon: 'warning',
			title: 'Silinemez',
			text: `${t.name} tenant'ına bağlı ${t.userCount} kullanıcı var. Önce kullanıcıları başka tenant'a taşıyın.`,
		})
		return
	}
	const ok = await $swal.dangerConfirm({
		title: 'Tenant\'ı Sil',
		html: `<strong>${t.name}</strong> silinecek. Bu işlem geri alınabilir (soft delete).`,
		confirmText: 'Sil',
		cancelText: 'Vazgeç',
	})
	if (!ok) return
	router.delete(`/tenants/${t.id}`, {
		preserveScroll: true,
		preserveState: true,
		onSuccess: () => {
			showToast?.({ type: 'warning', title: 'Tenant silindi', message: t.name })
		},
		onError: (errs) => {
			showToast?.({ type: 'error', title: 'Silme başarısız', message: Object.values(errs)[0] || 'Sunucu hatası.' })
		},
	})
}
</script>

<style scoped>
.page-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 20px; gap: 16px; }
.page-title { font-size: 22px; font-weight: 700; color: #1a1a2e; line-height: 1.2; }
.page-subtitle { font-size: 13px; color: #888; margin-top: 4px; }

.card { background: #fff; border-radius: 16px; border: 1px solid #ebebf0; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,.04); }
.card-header { padding: 14px 18px; display: flex; align-items: center; gap: 12px; border-bottom: 1px solid #f0f0f5; flex-wrap: wrap; }
.card-header h3 { font-size: 15px; font-weight: 700; color: #1a1a2e; }
.card-search { display: flex; align-items: center; gap: 6px; background: #f5f5f8; border: 1px solid #e8e8f0; border-radius: 8px; padding: 5px 10px; margin-left: auto; min-width: 280px; }
.card-search svg { color: #aaa; flex-shrink: 0; }
.card-search input { border: none; background: none; outline: none; font-family: inherit; font-size: 13px; color: #1a1a2e; width: 100%; }
.card-search input::placeholder { color: #bbb; }
.filter-select { border: 1px solid #e8e8f0; background: #f5f5f8; border-radius: 8px; padding: 6px 10px; font-size: 12.5px; color: #1a1a2e; font-family: inherit; outline: none; }

.data-table { width: 100%; border-collapse: separate; border-spacing: 0; }
.data-table thead tr { background: #f8f8fc; }
.data-table th { text-align: left; padding: 12px 16px; font-size: 11px; font-weight: 600; color: #aaa; border-bottom: 1px solid #f0f0f5; text-transform: uppercase; letter-spacing: 0.04em; }
.data-table td { padding: 12px 16px; font-size: 13px; color: #444; border-bottom: 1px solid #f5f5f8; vertical-align: middle; }
.data-table tr:last-child td { border-bottom: none; }
.data-table tr:hover td { background: #fafafe; }
.empty-row { text-align: center !important; color: #aaa; padding: 32px 0 !important; font-style: italic; }
.dim { color: #aaa; font-size: 12px; }
.mono { font-family: 'SF Mono', Menlo, Consolas, monospace; font-size: 12px; }

.tenant-cell { display: flex; align-items: center; gap: 12px; }
.tenant-logo {
	width: 36px; height: 36px; border-radius: 10px;
	background: linear-gradient(135deg, #dbeafe, #bfdbfe);
	display: flex; align-items: center; justify-content: center;
	font-size: 16px; font-weight: 800; color: #2563eb; flex-shrink: 0;
}
.tenant-info { display: flex; flex-direction: column; gap: 2px; }
.tenant-name { font-weight: 600; color: #1a1a2e; font-size: 13px; }
.tenant-code { font-size: 10.5px; color: #888; font-family: 'SF Mono', Menlo, Consolas, monospace; }

.badge-type { display: inline-block; padding: 3px 9px; background: rgb(var(--color-primary-soft)); color: #4338ca; border-radius: 6px; font-size: 11px; font-weight: 600; }

.contact-cell { display: flex; flex-direction: column; gap: 2px; }
.contact-line { font-size: 12px; color: #444; }

.credit-cell { display: flex; flex-direction: column; gap: 4px; min-width: 130px; }
.credit-bar { height: 5px; background: #f0f0f5; border-radius: 4px; overflow: hidden; }
.credit-bar-fill { height: 100%; transition: width .3s; }
.credit-text { font-size: 11px; color: #666; font-family: 'SF Mono', Menlo, Consolas, monospace; }

.user-count { display: inline-block; padding: 2px 9px; background: #f0f0f5; color: #555; border-radius: 6px; font-size: 11.5px; font-weight: 700; font-family: 'SF Mono', Menlo, Consolas, monospace; }

.status-pill { display: inline-block; padding: 3px 9px; border-radius: 6px; font-size: 11px; font-weight: 600; }
.status-pill.active { background: #dcfce7; color: #15803d; }
.status-pill.inactive { background: #fee2e2; color: #b91c1c; }

.table-actions { display: flex; gap: 4px; }
.table-action-btn { background: #f3f4f6; border: none; cursor: pointer; font-size: 13px; padding: 5px 9px; border-radius: 6px; color: #6b7280; transition: all .15s; }
.table-action-btn.access:hover { background: rgb(var(--color-primary-soft)); color: rgb(var(--color-primary)); }
.table-action-btn.marketplace:hover { background: #fff6e6; color: #d97706; }
.table-action-btn.view:hover { background: rgb(var(--color-primary-soft)); color: rgb(var(--color-primary)); }
.table-action-btn.toggle:hover { background: #fef3c7; color: #b45309; }
.table-action-btn.delete:hover { background: #fee2e2; color: #dc2626; }

.form-grid { display: flex; flex-direction: column; gap: 18px; }
.form-section { display: flex; flex-direction: column; gap: 12px; padding-bottom: 12px; border-bottom: 1px solid #f5f5f8; }
.form-section:last-child { border-bottom: none; padding-bottom: 0; }
.form-section-title { font-size: 12px; font-weight: 700; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 4px; }
.form-row { display: flex; flex-direction: column; gap: 6px; }
.form-row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.form-row-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; }
.form-row-inline { flex-direction: row; align-items: center; gap: 10px; }
.form-label { font-size: 12px; font-weight: 600; color: #1a1a2e; }
.form-label .req { color: #ef4444; }
.form-input { padding: 9px 12px; border: 1px solid #e8e8f0; border-radius: 8px; font-family: inherit; font-size: 13px; color: #1a1a2e; background: #fff; outline: none; transition: border-color .15s; }
.form-input.mono { font-family: 'SF Mono', Menlo, Consolas, monospace; }
.form-input:focus { border-color: rgb(var(--color-primary)); }
.form-error { font-size: 11.5px; color: #ef4444; }
.form-check { display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 13px; color: #1a1a2e; }
</style>
