<template>
	<Head title="Kullanıcılar" />
	<div class="page-users">
		<Breadcrumb
			:items="[
				{ label: 'Ana Sayfa', to: '/workflow', icon: 'home' },
				{ label: 'Süper Admin' },
				{ label: 'Kullanıcılar' },
			]"
		/>

		<div class="page-header">
			<div>
				<h1 class="page-title">Kullanıcılar</h1>
				<p class="page-subtitle"><strong>{{ users.length }}</strong> kullanıcı kayıtlı</p>
			</div>
			<button class="btn btn-primary btn-with-icon" @click="openNew">
				<svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
					<path d="M12 5v14M5 12h14" />
				</svg>
				Yeni Kullanıcı
			</button>
		</div>

		<div class="card">
			<div class="card-header">
				<h3>Kullanıcı Listesi</h3>
				<div class="card-search">
					<input v-model="searchQuery" type="text" placeholder="İsim, e-posta, telefon ara..." />
				</div>
				<select v-model="filterRole" class="filter-select">
					<option value="">Tüm roller</option>
					<option v-for="r in roles" :key="r.id" :value="r.name">{{ r.display_name || r.name }}</option>
				</select>
				<select v-model="filterActive" class="filter-select">
					<option value="">Tüm durumlar</option>
					<option value="1">Aktif</option>
					<option value="0">Pasif</option>
				</select>
			</div>

			<div class="table-scroll">
			<table class="data-table">
				<thead>
					<tr>
						<th>Kullanıcı</th>
						<th>Telefon</th>
						<th>Unvan</th>
						<th>Rol</th>
						<th>Tenant</th>
						<th>Durum</th>
						<th>Kayıt Tarihi</th>
						<th>İşlemler</th>
					</tr>
				</thead>
				<tbody>
					<tr v-if="filtered.length === 0">
						<td colspan="8" class="empty-row">Kayıt bulunamadı</td>
					</tr>
					<tr v-for="u in filtered" :key="u.id">
						<td>
							<div class="user-cell">
								<div class="user-avatar">{{ initials(u.name) }}</div>
								<div class="user-info">
									<span class="user-name">{{ u.name }}</span>
									<span class="user-email">{{ u.email }}</span>
								</div>
							</div>
						</td>
						<td>{{ u.phone || '—' }}</td>
						<td>{{ u.job_title || '—' }}</td>
						<td><span v-if="u.role" class="badge-role">{{ u.role }}</span><span v-else class="dim">—</span></td>
						<td>{{ u.tenant?.name || '—' }}</td>
						<td>
							<span :class="['status-pill', u.is_active ? 'active' : 'inactive']">
								{{ u.is_active ? 'Aktif' : 'Pasif' }}
							</span>
						</td>
						<td>{{ u.created_at }}</td>
						<td>
							<div class="table-actions">
								<button class="table-action-btn view" @click="edit(u)" title="Düzenle">✏️</button>
								<button class="table-action-btn toggle" @click="toggle(u)" :title="u.is_active ? 'Pasifleştir' : 'Aktifleştir'">
									{{ u.is_active ? '⏸️' : '▶️' }}
								</button>
								<button class="table-action-btn delete" @click="confirmDelete(u)" title="Sil">🗑️</button>
							</div>
						</td>
					</tr>
				</tbody>
			</table>
			</div>
		</div>

		<AppModal v-model="formOpen" :title="editing ? 'Kullanıcı Düzenle' : 'Yeni Kullanıcı'" size="md" variant="info">
			<form class="form-grid" @submit.prevent="submit">
				<div class="form-row-2">
					<div class="form-row">
						<label class="form-label">Ad Soyad <span class="req">*</span></label>
						<input v-model="form.name" type="text" class="form-input" />
						<span v-if="errors.name" class="form-error">{{ errors.name }}</span>
					</div>
					<div class="form-row">
						<label class="form-label">E-posta <span class="req">*</span></label>
						<input v-model="form.email" type="email" class="form-input" />
						<span v-if="errors.email" class="form-error">{{ errors.email }}</span>
					</div>
				</div>
				<div class="form-row-2">
					<div class="form-row">
						<label class="form-label">Telefon</label>
						<input v-model="form.phone" type="text" class="form-input" />
					</div>
					<div class="form-row">
						<label class="form-label">Unvan</label>
						<input v-model="form.job_title" type="text" class="form-input" />
					</div>
				</div>
				<div class="form-row-2">
					<div class="form-row">
						<label class="form-label">Şifre <span v-if="!editing" class="req">*</span></label>
						<input v-model="form.password" type="password" class="form-input" :placeholder="editing ? 'Boş bırakılırsa değişmez' : ''" />
						<span v-if="errors.password" class="form-error">{{ errors.password }}</span>
					</div>
					<div class="form-row">
						<label class="form-label">Şifre Tekrar</label>
						<input v-model="form.password_confirmation" type="password" class="form-input" />
					</div>
				</div>
				<div class="form-row-2">
					<div class="form-row">
						<label class="form-label">Rol <span class="req">*</span></label>
						<select v-model="form.role" class="form-input" :disabled="editing?.role === 'superadmin'">
							<option value="">Seçin</option>
							<option v-for="r in roles" :key="r.id" :value="r.name">{{ r.display_name || r.name }}</option>
						</select>
						<span v-if="editing?.role === 'superadmin'" class="form-hint">Superadmin rolü bu ekrandan değiştirilemez.</span>
						<span v-if="errors.role" class="form-error">{{ errors.role }}</span>
					</div>
					<div class="form-row">
						<label class="form-label">Tenant</label>
						<select v-model="form.tenant_id" class="form-input">
							<option :value="null">Yok</option>
							<option v-for="t in tenants" :key="t.id" :value="t.id">{{ t.name }}</option>
						</select>
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
import { ref, reactive, computed, inject, watch } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Breadcrumb from '@/Components/Breadcrumb.vue'
import AppModal from '@/Components/AppModal.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
	users: { type: Array, default: () => [] },
	roles: { type: Array, default: () => [] },
	tenants: { type: Array, default: () => [] },
})

const showToast = inject('showToast')
const $swal = inject('$swal')

const searchQuery = ref('')
const filterRole = ref('')
const filterActive = ref('')

const filtered = computed(() => {
	const q = searchQuery.value.trim().toLowerCase()
	return props.users.filter(u => {
		if (filterRole.value && u.role !== filterRole.value) return false
		if (filterActive.value !== '' && (u.is_active ? '1' : '0') !== String(filterActive.value)) return false
		if (q) {
			const hay = [u.name, u.email, u.phone].filter(Boolean).join(' ').toLowerCase()
			if (!hay.includes(q)) return false
		}
		return true
	})
})

function initials(name) {
	return (name || '?').split(' ').filter(Boolean).slice(0, 2).map(w => w[0]?.toUpperCase()).join('')
}

const formOpen = ref(false)
const editing = ref(null)
const busy = ref(false)
const errors = ref({})

const emptyForm = () => ({
	name: '', email: '', phone: '', job_title: '',
	password: '', password_confirmation: '', role: '', tenant_id: null,
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

function edit(u) {
	editing.value = u
	errors.value = {}
	Object.assign(form, {
		name: u.name, email: u.email, phone: u.phone ?? '', job_title: u.job_title ?? '',
		password: '', password_confirmation: '', role: u.role ?? '', tenant_id: u.tenant?.id ?? null,
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
			showToast?.({ type: 'success', title: editing.value ? 'Kullanıcı güncellendi' : 'Kullanıcı eklendi', message: form.name })
		},
		onError: (errs) => {
			errors.value = errs
			showToast?.({ type: 'error', title: 'Kayıt başarısız', message: Object.values(errs)[0] || 'Doğrulama hatası.' })
		},
		onFinish: () => { busy.value = false },
	}
	if (editing.value) {
		router.put(`/superadmin/users/${editing.value.id}`, { ...form }, opts)
	} else {
		router.post('/superadmin/users', { ...form }, opts)
	}
}

function toggle(u) {
	router.post(`/superadmin/users/${u.id}/toggle`, {}, {
		preserveScroll: true, preserveState: true,
		onSuccess: () => showToast?.({ type: 'success', title: u.is_active ? 'Kullanıcı pasifleştirildi' : 'Kullanıcı aktifleştirildi', message: u.name }),
		onError: (errs) => showToast?.({ type: 'error', title: 'İşlem başarısız', message: Object.values(errs)[0] || 'Sunucu hatası.' }),
	})
}

async function confirmDelete(u) {
	const ok = await $swal.dangerConfirm({
		title: 'Kullanıcıyı Sil',
		html: `<strong>${u.name}</strong> silinecek.`,
		confirmText: 'Sil',
		cancelText: 'Vazgeç',
	})
	if (!ok) return
	router.delete(`/superadmin/users/${u.id}`, {
		preserveScroll: true, preserveState: true,
		onSuccess: () => showToast?.({ type: 'warning', title: 'Kullanıcı silindi', message: u.name }),
		onError: (errs) => showToast?.({ type: 'error', title: 'Silme başarısız', message: Object.values(errs)[0] || 'Sunucu hatası.' }),
	})
}
</script>

<style scoped>
.page-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 20px; gap: 16px; flex-wrap: wrap; }
.page-title { font-size: 22px; font-weight: 700; color: #1a1a2e; line-height: 1.2; }
.page-subtitle { font-size: 13px; color: #888; margin-top: 4px; }

.card { background: #fff; border-radius: 16px; border: 1px solid #ebebf0; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,.04); }
.card-header { padding: 14px 18px; display: flex; align-items: center; gap: 12px; border-bottom: 1px solid #f0f0f5; flex-wrap: wrap; }
.card-header h3 { font-size: 15px; font-weight: 700; color: #1a1a2e; }
.card-search { display: flex; align-items: center; gap: 6px; background: #f5f5f8; border: 1px solid #e8e8f0; border-radius: 8px; padding: 5px 10px; margin-left: auto; min-width: 240px; }
.card-search input { border: none; background: none; outline: none; font-family: inherit; font-size: 13px; width: 100%; }
.filter-select { border: 1px solid #e8e8f0; background: #f5f5f8; border-radius: 8px; padding: 6px 10px; font-size: 12.5px; font-family: inherit; outline: none; }

.data-table { width: 100%; border-collapse: separate; border-spacing: 0; }
.data-table thead tr { background: #f8f8fc; }
.data-table th { text-align: left; padding: 12px 16px; font-size: 11px; font-weight: 600; color: #aaa; border-bottom: 1px solid #f0f0f5; text-transform: uppercase; letter-spacing: 0.04em; }
.data-table td { padding: 12px 16px; font-size: 13px; color: #444; border-bottom: 1px solid #f5f5f8; vertical-align: middle; }
.data-table tr:last-child td { border-bottom: none; }
.empty-row { text-align: center !important; color: #aaa; padding: 32px 0 !important; font-style: italic; }
.dim { color: #aaa; font-size: 12px; }

.user-cell { display: flex; align-items: center; gap: 12px; }
.user-avatar { width: 36px; height: 36px; border-radius: 50%; background: linear-gradient(135deg, #dbeafe, #bfdbfe); display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 800; color: #2563eb; flex-shrink: 0; }
.user-info { display: flex; flex-direction: column; gap: 2px; }
.user-name { font-weight: 600; color: #1a1a2e; font-size: 13px; }
.user-email { font-size: 11px; color: #888; }

.badge-role { display: inline-block; padding: 3px 9px; background: rgb(var(--color-primary-soft)); color: #4338ca; border-radius: 6px; font-size: 11px; font-weight: 600; }

.status-pill { display: inline-block; padding: 3px 9px; border-radius: 6px; font-size: 11px; font-weight: 600; }
.status-pill.active { background: #dcfce7; color: #15803d; }
.status-pill.inactive { background: #fee2e2; color: #b91c1c; }

.table-actions { display: flex; gap: 4px; }
.table-action-btn { background: #f3f4f6; border: none; cursor: pointer; font-size: 13px; padding: 5px 9px; border-radius: 6px; color: #6b7280; transition: all .15s; }
.table-action-btn.view:hover { background: rgb(var(--color-primary-soft)); color: rgb(var(--color-primary)); }
.table-action-btn.toggle:hover { background: #fef3c7; color: #b45309; }
.table-action-btn.delete:hover { background: #fee2e2; color: #dc2626; }

.form-grid { display: flex; flex-direction: column; gap: 14px; }
.form-row { display: flex; flex-direction: column; gap: 6px; }
.form-row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.form-label { font-size: 12px; font-weight: 600; color: #1a1a2e; }
.form-label .req { color: #ef4444; }
.form-input { padding: 9px 12px; border: 1px solid #e8e8f0; border-radius: 8px; font-family: inherit; font-size: 13px; color: #1a1a2e; background: #fff; outline: none; }
.form-input:focus { border-color: rgb(var(--color-primary)); }
.form-input:disabled { background: #f5f5f8; color: #999; }
.form-error { font-size: 11.5px; color: #ef4444; }
.form-hint { font-size: 11px; color: #aaa; }

@media (max-width: 560px) {
	.form-row-2 { grid-template-columns: 1fr; }
}
</style>
