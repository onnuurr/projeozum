<template>
	<Head title="Kullanıcılar" />
	<div class="portal-users">
		<header class="page-header">
			<div>
				<h1 class="page-title">Kullanıcılar</h1>
				<p class="page-subtitle">Tenant ekibinizi yönetin</p>
			</div>
			<button class="btn-primary" @click="openCreate">Yeni Kullanıcı</button>
		</header>

		<section class="card">
			<div class="table-scroll">
			<table class="data-table">
				<thead>
					<tr><th>Ad</th><th>E-posta</th><th>Rol</th><th>Durum</th><th>İzin</th><th></th></tr>
				</thead>
				<tbody>
					<tr v-for="u in users" :key="u.id">
						<td>{{ u.name }}</td>
						<td class="mono">{{ u.email }}</td>
						<td>{{ u.is_admin ? 'Yönetici' : 'Kullanıcı' }}</td>
						<td>
							<span :class="['badge', u.is_active ? 'ok' : 'off']">
								{{ u.is_active ? 'Aktif' : 'Pasif' }}
							</span>
						</td>
						<td>{{ u.permissions.length }}</td>
						<td class="row-actions">
							<template v-if="!u.is_admin && u.id !== $page.props.auth.user.id">
								<button @click="openEdit(u)">Düzenle</button>
								<button @click="toggleActive(u)">{{ u.is_active ? 'Pasifleştir' : 'Aktifleştir' }}</button>
								<button @click="openReset(u)">Şifre</button>
								<button class="danger" @click="confirmDelete(u)">Sil</button>
							</template>
							<span v-else class="muted">—</span>
						</td>
					</tr>
				</tbody>
			</table>
			</div>
		</section>

		<!-- Oluştur / Düzenle drawer -->
		<div v-if="drawer.open" class="drawer-backdrop" @click.self="drawer.open = false">
			<div class="drawer">
				<h2>{{ drawer.mode === 'create' ? 'Yeni Kullanıcı' : 'Kullanıcıyı Düzenle' }}</h2>
				<form @submit.prevent="submit">
					<label class="field">
						<span>Ad</span>
						<input v-model="form.name" type="text" required />
						<small v-if="form.errors.name" class="err">{{ form.errors.name }}</small>
					</label>

					<template v-if="drawer.mode === 'create'">
						<label class="field">
							<span>E-posta</span>
							<input v-model="form.email" type="email" required />
							<small v-if="form.errors.email" class="err">{{ form.errors.email }}</small>
						</label>
						<label class="field">
							<span>Şifre</span>
							<input v-model="form.password" type="password" required />
							<small v-if="form.errors.password" class="err">{{ form.errors.password }}</small>
						</label>
						<label class="field">
							<span>Şifre (tekrar)</span>
							<input v-model="form.password_confirmation" type="password" required />
						</label>
					</template>

					<fieldset class="perms">
						<legend>Portal İzinleri</legend>
						<div v-for="(items, group) in groupedPermissions" :key="group" class="perm-group">
							<h4>{{ group }}</h4>
							<label v-for="p in items" :key="p.name" class="perm-check">
								<input type="checkbox" :value="p.name" v-model="form.permissions" />
								<span>{{ p.label }}</span>
							</label>
						</div>
					</fieldset>

					<div class="drawer-actions">
						<button type="button" @click="drawer.open = false">Vazgeç</button>
						<button type="submit" class="btn-primary" :disabled="form.processing">Kaydet</button>
					</div>
				</form>
			</div>
		</div>

		<!-- Şifre sıfırla drawer -->
		<div v-if="reset.open" class="drawer-backdrop" @click.self="reset.open = false">
			<div class="drawer">
				<h2>Şifre Sıfırla — {{ reset.user?.name }}</h2>
				<form @submit.prevent="submitReset">
					<label class="field">
						<span>Yeni şifre</span>
						<input v-model="resetForm.password" type="password" required />
						<small v-if="resetForm.errors.password" class="err">{{ resetForm.errors.password }}</small>
					</label>
					<label class="field">
						<span>Yeni şifre (tekrar)</span>
						<input v-model="resetForm.password_confirmation" type="password" required />
					</label>
					<div class="drawer-actions">
						<button type="button" @click="reset.open = false">Vazgeç</button>
						<button type="submit" class="btn-primary" :disabled="resetForm.processing">Güncelle</button>
					</div>
				</form>
			</div>
		</div>
	</div>
</template>

<script setup>
import { Head, useForm } from '@inertiajs/vue3'
import { computed, reactive } from 'vue'
import TenantPortalLayout from '@/Layouts/TenantPortalLayout.vue'

defineOptions({ layout: TenantPortalLayout })

const props = defineProps({
	tenant: Object,
	users: Array,
	assignablePermissions: Array,
})

const groupedPermissions = computed(() => {
	const out = {}
	for (const p of props.assignablePermissions) {
		;(out[p.group] ??= []).push(p)
	}
	return out
})

const drawer = reactive({ open: false, mode: 'create', userId: null })
const form = useForm({ name: '', email: '', password: '', password_confirmation: '', permissions: [] })

function openCreate() {
	drawer.mode = 'create'; drawer.userId = null
	form.reset(); form.clearErrors()
	drawer.open = true
}
function openEdit(u) {
	drawer.mode = 'edit'; drawer.userId = u.id
	form.reset(); form.clearErrors()
	form.name = u.name
	form.permissions = [...u.permissions]
	drawer.open = true
}
function submit() {
	if (drawer.mode === 'create') {
		form.post('/users', { onSuccess: () => (drawer.open = false) })
	} else {
		form.put(`/users/${drawer.userId}`, { onSuccess: () => (drawer.open = false) })
	}
}

function toggleActive(u) {
	useForm({}).post(`/users/${u.id}/toggle-active`)
}
function confirmDelete(u) {
	if (confirm(`${u.name} kullanıcısını silmek istediğinize emin misiniz?`)) {
		useForm({}).delete(`/users/${u.id}`)
	}
}

const reset = reactive({ open: false, user: null })
const resetForm = useForm({ password: '', password_confirmation: '' })
function openReset(u) {
	reset.user = u; resetForm.reset(); resetForm.clearErrors()
	reset.open = true
}
function submitReset() {
	resetForm.post(`/users/${reset.user.id}/reset-password`, { onSuccess: () => (reset.open = false) })
}
</script>

<style scoped>
.page-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px; }
.btn-primary { background: #4338ca; color: #fff; border: 0; padding: 8px 14px; border-radius: 8px; cursor: pointer; }
.btn-primary:disabled { opacity: .5; cursor: not-allowed; }
.data-table { width: 100%; border-collapse: collapse; }
.data-table th, .data-table td { text-align: left; padding: 8px 10px; border-bottom: 1px solid #2a2a44; }
.badge.ok { color: #16a34a; } .badge.off { color: #b91c1c; }
.row-actions { display: flex; gap: 6px; } .row-actions .danger { color: #b91c1c; }
.muted { color: #6b7280; }
.drawer-backdrop { position: fixed; inset: 0; background: rgba(0,0,0,.4); display: flex; justify-content: flex-end; }
.drawer { width: 420px; max-width: 90vw; background: #14142a; height: 100%; padding: 20px; overflow-y: auto; }
.field { display: flex; flex-direction: column; gap: 4px; margin-bottom: 12px; }
.field input { padding: 8px; border-radius: 6px; border: 1px solid #2a2a44; background: #0f0f22; color: #fff; }
.err { color: #f87171; font-size: 12px; }
.perms { border: 1px solid #2a2a44; border-radius: 8px; padding: 10px; margin-bottom: 12px; }
.perm-group h4 { margin: 8px 0 4px; font-size: 12px; color: #9ca3af; }
.perm-check { display: flex; gap: 8px; align-items: center; padding: 3px 0; }
.drawer-actions { display: flex; justify-content: flex-end; gap: 8px; }
</style>
