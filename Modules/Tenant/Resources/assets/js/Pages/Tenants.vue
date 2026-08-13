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

		<PageHeader title="Tenant'lar">
			<template #subtitle><strong>{{ tenants.length }}</strong> tenant kayıtlı</template>
			<template v-if="canManage" #actions>
				<Button variant="primary" with-icon @click="openNew">
					<template #leading><Plus :size="13" /></template>
					Yeni Tenant
				</Button>
			</template>
		</PageHeader>

		<Card title="Tenant Listesi" body-class="p-0">
			<template #actions>
				<div class="card-actions-row">
					<div class="card-search">
						<Search :size="13" />
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
			</template>

			<DataTable :columns="columns" :data="filtered" row-key-field="id" empty-title="Kayıt bulunamadı">
				<template #tenant="{ row }">
					<div class="tenant-cell">
						<Avatar :initials="row.name.charAt(0).toUpperCase()" size="md" />
						<div class="tenant-info">
							<span class="tenant-name">{{ row.name }}</span>
							<span class="tenant-code">{{ row.code }}</span>
						</div>
					</div>
				</template>
				<template #type="{ row }">
					<Badge v-if="row.type" color="info" variant="tonal" :label="row.type.name" />
					<span v-else class="dim">—</span>
				</template>
				<template #contact="{ row }">
					<div class="contact-cell">
						<span v-if="row.email" class="contact-line">{{ row.email }}</span>
						<span v-if="row.phone" class="contact-line dim">{{ row.phone }}</span>
					</div>
				</template>
				<template #tax_number="{ value }">
					<span v-if="value" class="mono">{{ value }}</span>
					<span v-else class="dim">—</span>
				</template>
				<template #credit="{ row }">
					<div class="credit-cell">
						<ProgressBar :value="creditUsage(row)" :color="creditColor(row)" />
						<span class="credit-text">{{ formatMoney(row.current_balance) }} / {{ formatMoney(row.credit_limit) }}</span>
					</div>
				</template>
				<template #userCount="{ value }"><span class="user-count">{{ value }}</span></template>
				<template #is_active="{ value }">
					<Badge :color="value ? 'success' : 'danger'" variant="tonal" :label="value ? 'Aktif' : 'Pasif'" />
				</template>
				<template #actions="{ row }">
					<button v-if="canAccess" class="table-action-btn access" @click="openAccess(row)" title="Erişim Yönet"><KeyRound :size="14" /></button>
					<button v-if="canManage" class="table-action-btn view" @click="edit(row)" title="Düzenle"><Pencil :size="14" /></button>
					<button v-if="canManage" class="table-action-btn toggle" @click="toggle(row)" :title="row.is_active ? 'Pasifleştir' : 'Aktifleştir'">
						<component :is="row.is_active ? Pause : Play" :size="14" />
					</button>
					<button v-if="canManage" class="table-action-btn delete" @click="confirmDelete(row)" title="Sil"><Trash2 :size="14" /></button>
				</template>
			</DataTable>
		</Card>

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

				<div v-if="!editing || ownerExists" class="form-section">
					<h4 class="form-section-title">Portal Giriş Hesabı</h4>
					<p class="form-section-hint">
						{{ editing
							? 'Tenant portalına giriş yapan yetkili hesap. Şifreyi yalnızca değiştirmek istiyorsanız doldurun.'
							: 'Tenant subdomain portalına giriş yapacak ilk (yetkili) kullanıcı — sonradan tenant kendi alt kullanıcılarını portaldan ekleyebilir.' }}
					</p>
					<div class="form-row-2">
						<div class="form-row">
							<label class="form-label">Yetkili Adı <span class="req">*</span></label>
							<input v-model="form.owner_name" type="text" class="form-input" placeholder="Ahmet Yılmaz" autocomplete="off" />
							<span v-if="errors.owner_name" class="form-error">{{ errors.owner_name }}</span>
						</div>
						<div class="form-row">
							<label class="form-label">Giriş E-postası <span class="req">*</span></label>
							<input v-model="form.owner_email" type="email" class="form-input" placeholder="yetkili@firma.com" autocomplete="off" />
							<span v-if="errors.owner_email" class="form-error">{{ errors.owner_email }}</span>
						</div>
					</div>
					<div class="form-row-2">
						<div class="form-row">
							<label class="form-label">Şifre <span v-if="!editing" class="req">*</span></label>
							<input v-model="form.owner_password" type="password" class="form-input" autocomplete="new-password" :placeholder="editing ? 'Değiştirmek için doldurun' : 'En az 8 karakter'" />
							<span v-if="errors.owner_password" class="form-error">{{ errors.owner_password }}</span>
						</div>
						<div class="form-row">
							<label class="form-label">Şifre (Tekrar)</label>
							<input v-model="form.owner_password_confirmation" type="password" class="form-input" autocomplete="new-password" placeholder="Şifreyi tekrar girin" />
						</div>
					</div>
				</div>
				<div v-else class="form-section">
					<h4 class="form-section-title">Portal Giriş Hesabı</h4>
					<p class="form-section-hint">Bu tenant için portal giriş hesabı bulunamadı.</p>
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
					<h4 class="form-section-title">Kargo Anlaşması</h4>
					<p class="form-section-hint">
						Bagisto senkronu bu alan boşken tenant'ı aktif edemez — dolu değilse tenant Bagisto'da hiç oluşmaz/güncellenmez.
					</p>
					<div class="form-row-2">
						<div class="form-row">
							<label class="form-label">Kargo Anlaşması Tipi</label>
							<select v-model="form.shipping_agreement_type" class="form-input">
								<option :value="null">Seçilmedi</option>
								<option value="own">Bayi kendi kargo anlaşmasını kullanıyor</option>
								<option value="platform">Platform kargo anlaşması</option>
							</select>
							<span v-if="errors.shipping_agreement_type" class="form-error">{{ errors.shipping_agreement_type }}</span>
						</div>
						<div class="form-row" v-if="form.shipping_agreement_type === 'own'">
							<label class="form-label">Kargo Firması <span class="req">*</span></label>
							<select v-model="form.carrier_id" class="form-input">
								<option :value="null">Seçilmedi</option>
								<option v-for="c in carriers" :key="c.id" :value="c.id">{{ c.name }}</option>
							</select>
							<span v-if="errors.carrier_id" class="form-error">{{ errors.carrier_id }}</span>
						</div>
					</div>
					<div class="form-row form-row-inline" v-if="form.shipping_agreement_type === 'platform'">
						<label class="form-check">
							<input v-model="form.shipping_terms_accepted" type="checkbox" />
							<span>Gönderen bilgisi platforma ait olacaktır, şartları kabul ediyorum</span>
						</label>
						<span v-if="errors.shipping_terms_accepted" class="form-error">{{ errors.shipping_terms_accepted }}</span>
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
import { Plus, Search, KeyRound, Pencil, Pause, Play, Trash2 } from 'lucide-vue-next'
import AppLayout from '@/Layouts/AppLayout.vue'
import Breadcrumb from '@/Components/Breadcrumb.vue'
import PageHeader from '@/Components/PageHeader.vue'
import Card from '@/Components/Card.vue'
import DataTable from '@/Components/DataTable.vue'
import Badge from '@/Components/Badge.vue'
import Avatar from '@/Components/Avatar.vue'
import ProgressBar from '@/Components/ProgressBar.vue'
import Button from '@/Components/Button.vue'
import AppModal from '@/Components/AppModal.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
	tenants: { type: Array, default: () => [] },
	types: { type: Array, default: () => [] },
	carriers: { type: Array, default: () => [] },
	filters: { type: Object, default: () => ({}) },
})

const columns = [
	{ key: 'tenant', label: 'Tenant', sortable: false },
	{ key: 'type', label: 'Tip' },
	{ key: 'contact', label: 'İletişim', sortable: false },
	{ key: 'tax_number', label: 'Vergi No' },
	{ key: 'credit', label: 'Kredi (kullanılan / limit)', sortable: false },
	{ key: 'userCount', label: 'Kullanıcı' },
	{ key: 'is_active', label: 'Durum' },
]

const showToast = inject('showToast')
const $swal = inject('$swal')
const page = usePage()

const canManage = computed(() => (page.props.auth?.permissions ?? []).includes('tenant.manage'))
const canAccess = computed(() => (page.props.auth?.permissions ?? []).includes('tenant-access.manage'))

function openAccess(t) {
	router.visit(`/tenants/${t.id}/access`)
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
	if (u >= 90) return 'danger'
	if (u >= 70) return 'warning'
	return 'success'
}

/* ── Form state ── */
const formOpen = ref(false)
const editing = ref(null)
const busy = ref(false)
const errors = ref({})
const ownerExists = ref(false)

const emptyForm = () => ({
	code: '',
	name: '',
	legal_name: '',
	owner_name: '',
	owner_email: '',
	owner_password: '',
	owner_password_confirmation: '',
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
	shipping_agreement_type: null,
	carrier_id: null,
	shipping_terms_accepted: false,
})

const form = reactive(emptyForm())

function resetForm() {
	Object.assign(form, emptyForm())
}

watch(formOpen, (open) => {
	if (!open) {
		setTimeout(() => {
			editing.value = null
			ownerExists.value = false
			errors.value = {}
			resetForm()
		}, 250)
	}
})

function openNew() {
	editing.value = null
	ownerExists.value = false
	errors.value = {}
	resetForm()
	formOpen.value = true
}

function edit(t) {
	editing.value = t
	ownerExists.value = !!t.owner
	errors.value = {}
	Object.assign(form, {
		code: t.code,
		name: t.name,
		legal_name: t.legal_name ?? '',
		owner_name: t.owner?.name ?? '',
		owner_email: t.owner?.email ?? '',
		owner_password: '',
		owner_password_confirmation: '',
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
		shipping_agreement_type: t.shipping_agreement_type ?? null,
		carrier_id: t.carrier?.id ?? null,
		shipping_terms_accepted: !!t.shipping_terms_accepted_at,
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
		const payload = { ...form }
		if (!ownerExists.value) {
			// Bu tenant için owner hesabı yok — alanları hiç gönderme.
			delete payload.owner_name
			delete payload.owner_email
			delete payload.owner_password
			delete payload.owner_password_confirmation
		} else if (!payload.owner_password) {
			// Şifre boş bırakıldıysa mevcut şifreye dokunma.
			delete payload.owner_password
			delete payload.owner_password_confirmation
		}
		router.put(`/tenants/${editing.value.id}`, payload, opts)
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
.card-actions-row { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.card-search { display: flex; align-items: center; gap: 6px; background: rgb(var(--color-bg)); border: 1px solid rgb(var(--color-border)); border-radius: 8px; padding: 5px 10px; min-width: 280px; }
.card-search svg { color: rgb(var(--color-muted)); flex-shrink: 0; }
.card-search input { border: none; background: none; outline: none; font-family: inherit; font-size: 13px; color: rgb(var(--color-ink)); width: 100%; }
.filter-select { border: 1px solid rgb(var(--color-border)); background: rgb(var(--color-bg)); border-radius: 8px; padding: 6px 10px; font-size: 12.5px; color: rgb(var(--color-ink)); font-family: inherit; outline: none; }

.dim { color: rgb(var(--color-muted)); font-size: 12px; }
.mono { font-family: 'SF Mono', Menlo, Consolas, monospace; font-size: 12px; }

.tenant-cell { display: flex; align-items: center; gap: 12px; }
.tenant-info { display: flex; flex-direction: column; gap: 2px; }
.tenant-name { font-weight: 600; color: rgb(var(--color-ink)); font-size: 13px; }
.tenant-code { font-size: 10.5px; color: rgb(var(--color-muted)); font-family: 'SF Mono', Menlo, Consolas, monospace; }

.contact-cell { display: flex; flex-direction: column; gap: 2px; }
.contact-line { font-size: 12px; color: rgb(var(--color-ink)); }

.credit-cell { display: flex; flex-direction: column; gap: 4px; min-width: 130px; }
.credit-text { font-size: 11px; color: rgb(var(--color-muted)); font-family: 'SF Mono', Menlo, Consolas, monospace; }

.user-count { display: inline-block; padding: 2px 9px; background: rgb(var(--color-bg)); color: rgb(var(--color-muted)); border-radius: 6px; font-size: 11.5px; font-weight: 700; font-family: 'SF Mono', Menlo, Consolas, monospace; }

.table-action-btn { display: inline-flex; align-items: center; justify-content: center; background: rgb(var(--color-bg)); border: none; cursor: pointer; padding: 6px; border-radius: 6px; color: rgb(var(--color-muted)); transition: all .15s; }
.table-action-btn.access:hover { background: rgb(var(--color-primary-soft)); color: rgb(var(--color-primary)); }
.table-action-btn.view:hover { background: rgb(var(--color-primary-soft)); color: rgb(var(--color-primary)); }
.table-action-btn.toggle:hover { background: rgb(var(--color-warning) / .15); color: rgb(var(--color-warning)); }
.table-action-btn.delete:hover { background: rgb(var(--color-danger) / .12); color: rgb(var(--color-danger)); }

.form-grid { display: flex; flex-direction: column; gap: 18px; }
.form-section { display: flex; flex-direction: column; gap: 12px; padding-bottom: 12px; border-bottom: 1px solid rgb(var(--color-border)); }
.form-section:last-child { border-bottom: none; padding-bottom: 0; }
.form-section-title { font-size: 12px; font-weight: 700; color: rgb(var(--color-muted)); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 4px; }
.form-section-hint { font-size: 12px; color: rgb(var(--color-muted)); margin: -6px 0 2px; }
.form-row { display: flex; flex-direction: column; gap: 6px; }
.form-row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.form-row-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; }
.form-row-inline { flex-direction: row; align-items: center; gap: 10px; }
.form-label { font-size: 12px; font-weight: 600; color: rgb(var(--color-ink)); }
.form-label .req { color: rgb(var(--color-danger)); }
.form-input { padding: 9px 12px; border: 1px solid rgb(var(--color-border)); border-radius: 8px; font-family: inherit; font-size: 13px; color: rgb(var(--color-ink)); background: rgb(var(--color-surface)); outline: none; transition: border-color .15s; }
.form-input.mono { font-family: 'SF Mono', Menlo, Consolas, monospace; }
.form-input:focus { border-color: rgb(var(--color-primary)); }
.form-error { font-size: 11.5px; color: rgb(var(--color-danger)); }
.form-check { display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 13px; color: rgb(var(--color-ink)); }

@media (max-width: 560px) {
	.form-row-2 { grid-template-columns: 1fr; }
	.form-row-3 { grid-template-columns: 1fr; }
}
</style>
