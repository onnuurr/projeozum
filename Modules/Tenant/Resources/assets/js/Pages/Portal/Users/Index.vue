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
.page-title { font-size: 22px; font-weight: 700; color: rgb(var(--portal-ink)); }
.page-subtitle { font-size: 13px; color: rgb(var(--portal-text-muted)); margin-top: 4px; }
.btn-primary { background: rgb(var(--portal-accent)); color: #fff; border: 0; padding: 8px 14px; border-radius: 8px; cursor: pointer; font-size: 13px; font-weight: 600; }
.btn-primary:hover:not(:disabled) { background: rgb(var(--portal-accent-hover)); }
.btn-primary:disabled { opacity: .5; cursor: not-allowed; }
.card { background: #fff; border-radius: 16px; border: 1px solid rgb(var(--portal-border)); overflow: hidden; }
.data-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.data-table th, .data-table td { text-align: left; padding: 10px 14px; border-bottom: 1px solid rgb(var(--portal-bg-soft)); }
.data-table th { font-size: 11px; text-transform: uppercase; color: rgb(var(--portal-text-muted)); background: rgb(var(--portal-bg-soft)); }
.mono { font-family: 'SF Mono', Menlo, Consolas, monospace; }
.badge { display: inline-block; padding: 2px 8px; border-radius: 6px; font-size: 11px; font-weight: 600; }
.badge.ok { background: rgb(var(--color-success) / .12); color: rgb(var(--color-success)); }
.badge.off { background: rgb(var(--color-danger) / .1); color: rgb(var(--color-danger)); }
.row-actions { display: flex; gap: 10px; }
.row-actions button { background: none; border: none; padding: 0; font-size: 12px; color: rgb(var(--portal-accent)); cursor: pointer; }
.row-actions button:hover { text-decoration: underline; }
.row-actions .danger { color: rgb(var(--color-danger)); }
.muted { color: rgb(var(--portal-text-muted)); }
.drawer-backdrop { position: fixed; inset: 0; background: rgba(17,24,39,.4); display: flex; justify-content: flex-end; z-index: 40; }
.drawer { width: 420px; max-width: 90vw; background: #fff; height: 100%; padding: 22px; overflow-y: auto; }
.drawer h2 { font-size: 16px; font-weight: 700; color: rgb(var(--portal-ink)); margin-bottom: 16px; }
.field { display: flex; flex-direction: column; gap: 4px; margin-bottom: 12px; }
.field span { font-size: 11px; font-weight: 600; color: rgb(var(--portal-text-secondary)); }
.field input { padding: 8px 12px; border-radius: 8px; border: 1px solid rgb(var(--portal-border)); background: #fff; color: rgb(var(--portal-ink)); font-size: 13px; }
.err { color: rgb(var(--color-danger)); font-size: 12px; }
.perms { border: 1px solid rgb(var(--portal-border)); border-radius: 8px; padding: 10px; margin-bottom: 12px; }
.perm-group h4 { margin: 8px 0 4px; font-size: 12px; color: rgb(var(--portal-text-muted)); }
.perm-check { display: flex; gap: 8px; align-items: center; padding: 3px 0; font-size: 13px; color: rgb(var(--portal-ink)); }
.drawer-actions { display: flex; justify-content: flex-end; gap: 8px; margin-top: 4px; }
.drawer-actions button[type="button"] { background: rgb(var(--portal-bg)); border: 1px solid rgb(var(--portal-border)); border-radius: 8px; padding: 8px 14px; font-size: 13px; cursor: pointer; color: rgb(var(--portal-text-secondary)); }
</style>
