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

		<PageHeader title="Kullanıcılar">
			<template #subtitle><strong>{{ users.length }}</strong> kullanıcı kayıtlı</template>
			<template #actions>
				<Button variant="primary" with-icon @click="openNew">
					<template #leading><Plus :size="13" /></template>
					Yeni Kullanıcı
				</Button>
			</template>
		</PageHeader>

		<Card title="Kullanıcı Listesi" body-class="p-2.5" class="filter-card">
			<div class="filter-row">

				<div class="card-search">
					<FormField variant="default">
						<FormInput v-model="searchQuery" type="text" placeholder="İsim, e-posta, telefon ara..." variant="default">
							<template #icon>
								<svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
									<circle cx="11" cy="11" r="8" /><path d="M21 21l-4.35-4.35" />
								</svg>
							</template>
						</FormInput>
					</FormField>
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

			<DataTable :columns="columns" :data="filtered" empty-title="Kayıt bulunamadı" empty-hint="Filtreyi değiştirip tekrar deneyin.">
			<template #user="{ row }">
				<div class="user-cell">
					<Avatar :initials="initials(row.name)" size="md" />
					<div class="user-info">
						<span class="user-name">{{ row.name }}</span>
						<span class="user-email">{{ row.email }}</span>
					</div>
				</div>
			</template>
			<template #phone="{ value }">{{ value || '—' }}</template>
			<template #job_title="{ value }">{{ value || '—' }}</template>
			<template #role="{ value }">
				<Badge v-if="value" color="primary" variant="tonal" :label="value" />
				<span v-else class="dim">—</span>
			</template>
			<template #tenant="{ row }">{{ row.tenant?.name || '—' }}</template>
			<template #is_active="{ value }">
				<Badge :color="value ? 'success' : 'danger'" variant="tonal" :label="value ? 'Aktif' : 'Pasif'" />
			</template>
			<template #actions="{ row }">
				<button class="table-action-btn view" @click="edit(row)" title="Düzenle"><Pencil :size="14" /></button>
				<button class="table-action-btn toggle" @click="toggle(row)" :title="row.is_active ? 'Pasifleştir' : 'Aktifleştir'">
					<component :is="row.is_active ? Pause : Play" :size="14" />
				</button>
				<button class="table-action-btn delete" @click="confirmDelete(row)" title="Sil"><Trash2 :size="14" /></button>
			</template>
		</DataTable>
			
		</Card>

		

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
				<Button variant="ghost" @click="close" :disabled="busy">İptal</Button>
				<Button variant="primary" :loading="busy" @click="submit">
					{{ editing ? 'Kaydet' : 'Ekle' }}
				</Button>
			</template>
		</AppModal>
	</div>
</template>

<script setup>
import { ref, reactive, computed, inject, watch } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import { Plus, Pencil, Pause, Play, Trash2 } from 'lucide-vue-next'
import AppLayout from '@/Layouts/AppLayout.vue'
import Breadcrumb from '@/Components/Breadcrumb.vue'
import AppModal from '@/Components/AppModal.vue'
import Avatar from '@/Components/Avatar.vue'
import Badge from '@/Components/Badge.vue'
import DataTable from '@/Components/DataTable.vue'
import Card from '@/Components/Card.vue'
import PageHeader from '@/Components/PageHeader.vue'
import Button from '@/Components/Button.vue'
import FormField from '@/Components/Form/FormField.vue'
import FormInput from '@/Components/Form/FormInput.vue'
const columns = [
	{ key: 'user', label: 'Kullanıcı', sortable: false },
	{ key: 'phone', label: 'Telefon' },
	{ key: 'job_title', label: 'Unvan' },
	{ key: 'role', label: 'Rol' },
	{ key: 'tenant', label: 'Tenant', sortable: false },
	{ key: 'is_active', label: 'Durum' },
	{ key: 'created_at', label: 'Kayıt Tarihi' },
]

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
.filter-card { margin-bottom: 14px; }
.filter-row { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
.card-search { display: flex; align-items: center; gap: 6px; background: var(--color-canvas); border: 1px solid var(--color-outline-variant); border-radius: 8px; padding: 5px 10px; margin-left: auto; min-width: 240px; }
.card-search :deep(.form-group) { width: 100%; }
.card-search :deep(.form-input-wrap) { display: flex; align-items: center; gap: 6px; }
.card-search :deep(.form-input-icon) { color: var(--color-muted); flex-shrink: 0; display: flex; }
.card-search :deep(.form-input) { border: none; background: none; outline: none; box-shadow: none; height: auto; padding: 0; font-family: inherit; font-size: 13px; width: 100%; }
.card-search :deep(.form-input)::placeholder { color: var(--color-muted); }
.filter-select { border: 1px solid var(--color-outline-variant); background: var(--color-canvas); border-radius: 8px; padding: 6px 10px; font-size: 12.5px; font-family: inherit; outline: none; }

.dim { color: var(--color-muted); font-size: 12px; }

.user-cell { display: flex; align-items: center; gap: 12px; }
.user-info { display: flex; flex-direction: column; gap: 2px; }
.user-name { font-weight: 600; color: var(--color-ink); font-size: 13px; }
.user-email { font-size: 11px; color: var(--color-muted); }

.table-action-btn { display: inline-flex; align-items: center; justify-content: center; background: var(--color-canvas); border: none; cursor: pointer; padding: 6px; border-radius: 6px; color: var(--color-muted); transition: all .15s; }
.table-action-btn.view:hover { background: var(--color-primary-soft); color: var(--color-primary); }
.table-action-btn.toggle:hover { background: color-mix(in srgb, var(--color-warning) 15%, transparent); color: var(--color-warning); }
.table-action-btn.delete:hover { background: color-mix(in srgb, var(--color-danger) 12%, transparent); color: var(--color-danger); }

.form-grid { display: flex; flex-direction: column; gap: 14px; }
.form-row { display: flex; flex-direction: column; gap: 6px; }
.form-row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.form-label { font-size: 12px; font-weight: 600; color: var(--color-ink); }
.form-label .req { color: var(--color-danger); }
.form-input { padding: 9px 12px; border: 1px solid var(--color-outline-variant); border-radius: 8px; font-family: inherit; font-size: 13px; color: var(--color-ink); background: var(--color-surface); outline: none; }
.form-input:focus { border-color: var(--color-primary); }
.form-input:disabled { background: var(--color-canvas); color: var(--color-muted); }
.form-error { font-size: 11.5px; color: var(--color-danger); }
.form-hint { font-size: 11px; color: var(--color-muted); }

@media (max-width: 560px) {
	.form-row-2 { grid-template-columns: 1fr; }
}
</style>
