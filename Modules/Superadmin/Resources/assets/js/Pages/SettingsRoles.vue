<script setup>
import { ref, computed, reactive, inject } from 'vue'
import { router } from '@inertiajs/vue3'
import {
	OzTabs, OzInput, OzButton, OzPill, OzIcon, OzEmptyState, OzCheckbox, OzField, OzDrawer, OzModal, useOzConfirm,
} from '@/Components/Oz'

// Ayarlar · Roller & izinler (Spatie). Değişiklikler anında sunucuya yazılır (sayfanın Kaydet'inden bağımsız):
//   POST /superadmin/roles · PUT/DELETE /superadmin/roles/{id} · POST /superadmin/roles/{id}/permissions
//   POST /superadmin/permissions · PUT/DELETE /superadmin/permissions/{id}
// Süper Admin rolü tüm izinlere sahiptir, düzenlenemez / silinemez.
const props = defineProps({
	roles: { type: Array, required: true },
	modules: { type: Array, required: true },
	canManage: { type: Boolean, default: false },
})

const showToast = inject('showToast', null)
const confirm = useOzConfirm()
const firstError = (errors, fallback) => Object.values(errors ?? {})[0] || fallback

const view = ref('list')
const viewTabs = [
	{ key: 'list', label: 'Roller', icon: 'users' },
	{ key: 'matrix', label: 'İzin matrisi', icon: 'grid' },
	{ key: 'permissions', label: 'İzinler', icon: 'shield' },
]

function reload(onDone) {
	router.reload({ only: ['settings'], preserveScroll: true, preserveState: true, onFinish: () => onDone?.() })
}

const hasPermission = (role, key) => role.permissions?.includes('*') || role.permissions?.includes(key)
const permLabel = (role) => (role.permissions?.includes('*') ? 'Tüm izinler' : `${role.permissions?.length || 0} izin`)
const totalPermissions = computed(() => props.modules.reduce((n, m) => n + m.permissions.length, 0))
const flatPermissions = computed(() => props.modules.flatMap((m) => m.permissions))

/* ── rol listesi ── */
const roleSearch = ref('')
const filteredRoles = computed(() => {
	const q = roleSearch.value.trim().toLocaleLowerCase('tr')
	return q ? props.roles.filter((r) => `${r.name} ${r.key}`.toLocaleLowerCase('tr').includes(q)) : props.roles
})

/* ── matris: hücre anında kaydedilir ── */
const busyCell = ref('')
async function togglePermission(role, key) {
	if (role.key === 'superadmin' || busyCell.value || !props.canManage) return
	const next = hasPermission(role, key) ? role.permissions.filter((k) => k !== key) : [...(role.permissions || []), key]
	busyCell.value = `${role.id}:${key}`
	try {
		await window.axios.post(`/superadmin/roles/${role.id}/permissions`, { permissions: next })
		reload(() => { busyCell.value = '' })
	} catch (e) {
		busyCell.value = ''
		showToast?.({ type: 'error', title: 'İzin güncellenemedi', message: e?.response?.data?.error || 'Sunucu hatası.' })
	}
}

/* ── rol ekle / düzenle ── */
const roleDrawer = ref(false)
const editingRole = ref(null)
const roleBusy = ref(false)

function openAddRole() {
	editingRole.value = reactive({ isNew: true, id: null, key: '', name: '', permissions: [] })
	roleDrawer.value = true
}
function openEditRole(role) {
	editingRole.value = reactive({ isNew: false, id: role.id, key: role.key, name: role.name, userCount: role.userCount, permissions: [...(role.permissions || [])] })
	roleDrawer.value = true
}

const roleKeyError = computed(() => {
	const r = editingRole.value
	if (!r?.key) return ''
	if (!/^[a-z][a-z0-9_]*$/.test(r.key)) return 'Küçük harf ile başlamalı; yalnızca küçük harf, rakam ve alt çizgi'
	if (r.isNew && props.roles.some((x) => x.key === r.key)) return 'Bu anahtar zaten kullanılıyor'
	return ''
})
const roleValid = computed(() => !!editingRole.value?.name?.trim() && !!editingRole.value?.key?.trim() && !roleKeyError.value)

const moduleCount = (m) => m.permissions.filter((p) => editingRole.value?.permissions.includes(p.key)).length
const moduleAll = (m) => m.permissions.length > 0 && moduleCount(m) === m.permissions.length
function toggleModule(m, on) {
	const keys = m.permissions.map((p) => p.key)
	const cur = editingRole.value.permissions
	editingRole.value.permissions = on ? [...new Set([...cur, ...keys])] : cur.filter((k) => !keys.includes(k))
}
function togglePerm(key, on) {
	const cur = editingRole.value.permissions
	editingRole.value.permissions = on ? [...cur, key] : cur.filter((k) => k !== key)
}

function saveRole() {
	if (!roleValid.value || roleBusy.value) return
	const r = editingRole.value
	const displayName = r.name.trim()
	const sysKey = r.key.trim()
	const perms = [...r.permissions]
	roleBusy.value = true

	const afterSaved = async (id) => {
		try {
			if (id) await window.axios.post(`/superadmin/roles/${id}/permissions`, { permissions: perms })
			reload(() => {
				roleBusy.value = false
				roleDrawer.value = false
				showToast?.({ type: 'success', title: r.isNew ? 'Rol oluşturuldu' : 'Rol güncellendi', message: `«${displayName}» kaydedildi.` })
			})
		} catch (e) {
			roleBusy.value = false
			showToast?.({ type: 'error', title: 'İzinler kaydedilemedi', message: e?.response?.data?.error || 'Sunucu hatası.' })
		}
	}
	const onError = (errors) => {
		roleBusy.value = false
		showToast?.({ type: 'error', title: 'Kaydedilemedi', message: firstError(errors, 'Doğrulama hatası.') })
	}

	if (r.isNew) {
		router.post('/superadmin/roles', { role: sysKey, display_name: displayName }, {
			preserveScroll: true, preserveState: true, onError,
			onSuccess: () => afterSaved(props.roles.find((x) => x.key === sysKey)?.id),
		})
	} else {
		router.put(`/superadmin/roles/${r.id}`, { name: sysKey, display_name: displayName }, {
			preserveScroll: true, preserveState: true, onError,
			onSuccess: () => afterSaved(r.id),
		})
	}
}

async function deleteRole(role) {
	if (role.system) return
	const ok = await confirm.danger(
		`«${role.name}» rolü silinsin mi?`,
		`Bu role atanmış ${role.userCount} kullanıcı varsayılan Salt Okunur rolüne taşınacak. Rolün ${role.permissions?.length || 0} izni kaldırılır. Bu işlem geri alınamaz.`,
		{ confirmText: 'Rolü sil' },
	)
	if (!ok) return
	router.delete(`/superadmin/roles/${role.id}`, {
		preserveScroll: true, preserveState: true,
		onSuccess: () => showToast?.({ type: 'warning', title: 'Rol silindi', message: `«${role.name}» kaldırıldı.` }),
		onError: (errors) => showToast?.({ type: 'error', title: 'Silinemedi', message: firstError(errors, 'Sunucu hatası.') }),
	})
}

/* ── izin CRUD ── */
const permSearch = ref('')
const filteredModules = computed(() => {
	const q = permSearch.value.trim().toLocaleLowerCase('tr')
	if (!q) return props.modules
	return props.modules
		.map((m) => ({ ...m, permissions: m.permissions.filter((p) => `${p.key} ${p.name}`.toLocaleLowerCase('tr').includes(q)) }))
		.filter((m) => m.permissions.length)
})

const permModal = ref(false)
const editingPerm = ref(null)
const permBusy = ref(false)
function openAddPerm() {
	editingPerm.value = reactive({ isNew: true, id: null, name: '', display_name: '' })
	permModal.value = true
}
function openEditPerm(p) {
	editingPerm.value = reactive({ isNew: false, id: p.id, name: p.key, display_name: p.name === p.key ? '' : p.name })
	permModal.value = true
}
const permKeyError = computed(() => {
	const p = editingPerm.value
	const n = p?.name?.trim()
	if (!n) return ''
	if (!/^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*)*$/.test(n)) return 'Biçim: modul.islem (küçük harf, rakam, alt çizgi)'
	if (p.isNew && flatPermissions.value.some((x) => x.key === n)) return 'Bu izin zaten var'
	return ''
})
const permValid = computed(() => !!editingPerm.value?.name?.trim() && !permKeyError.value)

function savePerm() {
	if (!permValid.value || permBusy.value) return
	const p = editingPerm.value
	const payload = { name: p.name.trim(), display_name: p.display_name?.trim() || null }
	permBusy.value = true
	router[p.isNew ? 'post' : 'put'](p.isNew ? '/superadmin/permissions' : `/superadmin/permissions/${p.id}`, payload, {
		preserveScroll: true, preserveState: true,
		onSuccess: () => {
			permModal.value = false
			showToast?.({ type: 'success', title: p.isNew ? 'İzin eklendi' : 'İzin güncellendi', message: payload.name })
		},
		onError: (errors) => showToast?.({ type: 'error', title: 'Kaydedilemedi', message: firstError(errors, 'Doğrulama hatası.') }),
		onFinish: () => { permBusy.value = false },
	})
}

async function deletePerm(p) {
	if (!(await confirm.danger('İzin silinsin mi?', `${p.name} (${p.key}) kalıcı olarak silinecek.`))) return
	router.delete(`/superadmin/permissions/${p.id}`, {
		preserveScroll: true, preserveState: true,
		onSuccess: () => showToast?.({ type: 'warning', title: 'İzin silindi', message: p.name }),
		onError: (errors) => showToast?.({ type: 'error', title: 'Silinemedi', message: firstError(errors, 'Sunucu hatası.') }),
	})
}
</script>

<template>
	<div class="stack">
		<div class="toolbar">
			<OzTabs v-model="view" :tabs="viewTabs" variant="segment" aria-label="Görünüm" />
			<template v-if="view === 'list'">
				<OzInput v-model="roleSearch" icon="search" type="search" placeholder="Rol ara…" aria-label="Rol ara" class="search" />
				<OzButton v-if="canManage" variant="primary" icon="plus" @click="openAddRole">Yeni rol</OzButton>
			</template>
			<template v-else-if="view === 'permissions'">
				<OzInput v-model="permSearch" icon="search" type="search" placeholder="İzin ara…" aria-label="İzin ara" class="search" />
				<OzButton v-if="canManage" variant="primary" icon="plus" @click="openAddPerm">Yeni izin</OzButton>
			</template>
		</div>

		<!-- Roller -->
		<template v-if="view === 'list'">
			<OzEmptyState v-if="!filteredRoles.length" icon="search" :title="`“${roleSearch}” ile eşleşen rol yok`" compact />
			<div v-else class="role-grid">
				<div v-for="r in filteredRoles" :key="r.key" class="role-card">
					<span class="role-avatar" :style="{ background: r.color }">{{ r.name.charAt(0).toLocaleUpperCase('tr') }}</span>
					<div class="role-body">
						<div class="role-title">
							<b>{{ r.name }}</b>
							<OzPill v-if="r.system" tone="accent">Sistem</OzPill>
						</div>
						<code class="role-key">{{ r.key }}</code>
						<div class="role-meta">
							<span><OzIcon name="users" :size="12" /> {{ r.userCount }} kullanıcı</span>
							<span><OzIcon name="shield" :size="12" /> {{ permLabel(r) }}</span>
						</div>
					</div>
					<div v-if="canManage" class="role-actions">
						<OzButton size="sm" icon="edit" :disabled="r.key === 'superadmin'" :aria-label="`${r.name} düzenle`" :title="r.key === 'superadmin' ? 'Süper Admin rolü düzenlenemez' : 'Düzenle'" @click="openEditRole(r)" />
						<OzButton size="sm" icon="trash" :disabled="r.system" :aria-label="`${r.name} sil`" :title="r.system ? 'Sistem rolü silinemez' : 'Sil'" @click="deleteRole(r)" />
					</div>
				</div>
			</div>
		</template>

		<!-- İzin matrisi -->
		<template v-else-if="view === 'matrix'">
			<p class="lead">Hücreye tıklayınca izin anında kaydedilir. Süper Admin tüm izinlere sahiptir, değiştirilemez.</p>
			<div class="matrix-scroll">
				<table class="matrix">
					<thead>
						<tr>
							<th class="perm-col">İzin</th>
							<th v-for="r in roles" :key="r.key">
								<span class="role-dot" :style="{ background: r.color }" />{{ r.name }}
							</th>
						</tr>
					</thead>
					<tbody>
						<template v-for="m in modules" :key="m.key">
							<tr class="module-row">
								<td :colspan="roles.length + 1"><b>{{ m.name }}</b> <span>{{ m.permissions.length }} izin</span></td>
							</tr>
							<tr v-for="p in m.permissions" :key="p.key">
								<td class="perm-col">
									<div class="perm-name">{{ p.name }}</div>
									<code class="perm-key">{{ p.key }}</code>
								</td>
								<td v-for="r in roles" :key="r.key" class="cell">
									<OzCheckbox
										:model-value="hasPermission(r, p.key)"
										:disabled="r.key === 'superadmin' || !canManage || busyCell === `${r.id}:${p.key}`"
										:aria-label="`${r.name} · ${p.name}`"
										@update:model-value="togglePermission(r, p.key)"
									/>
								</td>
							</tr>
						</template>
					</tbody>
				</table>
			</div>
		</template>

		<!-- İzinler -->
		<template v-else>
			<OzEmptyState v-if="!flatPermissions.length" icon="shield" title="Henüz izin tanımlanmamış" description="“Yeni izin” ile başlayın." compact />
			<OzEmptyState v-else-if="!filteredModules.length" icon="search" :title="`“${permSearch}” ile eşleşen izin yok`" compact />
			<div v-for="m in filteredModules" :key="m.key" class="perm-group">
				<div class="perm-group-head"><b>{{ m.name }}</b><span>{{ m.permissions.length }} izin</span></div>
				<div v-for="p in m.permissions" :key="p.id" class="perm-row">
					<div class="perm-info">
						<div class="perm-name">{{ p.name }}</div>
						<code class="perm-key">{{ p.key }}</code>
					</div>
					<div v-if="canManage" class="role-actions">
						<OzButton size="sm" icon="edit" :aria-label="`${p.name} düzenle`" @click="openEditPerm(p)" />
						<OzButton size="sm" icon="trash" :aria-label="`${p.name} sil`" @click="deletePerm(p)" />
					</div>
				</div>
			</div>
		</template>

		<!-- Rol paneli -->
		<OzDrawer
			v-model:open="roleDrawer"
			:title="editingRole?.isNew ? 'Yeni rol' : 'Rolü düzenle'"
			:subtitle="editingRole?.isNew ? 'Ad, sistem anahtarı ve izinleri belirleyin' : `${editingRole?.userCount ?? 0} kullanıcı bu role sahip`"
			icon="users"
			size="lg"
			:persistent="roleBusy"
			initial-focus="auto"
		>
			<div v-if="editingRole" class="drawer-body">
				<div class="form-grid">
					<OzField label="Rol adı" required>
						<OzInput v-model="editingRole.name" placeholder="Örn. Depo Sorumlusu" maxlength="40" />
					</OzField>
					<OzField label="Sistem anahtarı" required :hint="editingRole.isNew ? 'küçük harf ve alt çizgi, ör. warehouse_manager' : 'Oluşturulduktan sonra değiştirilemez'" :error="roleKeyError">
						<OzInput v-model="editingRole.key" class="mono" placeholder="warehouse_manager" :disabled="!editingRole.isNew" />
					</OzField>
				</div>
				<div class="perm-head">
					<h4>İzinler</h4>
					<span><b>{{ editingRole.permissions.length }}</b> / {{ totalPermissions }} seçili</span>
				</div>
				<div v-for="m in modules" :key="m.key" class="perm-module">
					<div class="perm-module-head">
						<b>{{ m.name }}</b>
						<span class="count">{{ moduleCount(m) }} / {{ m.permissions.length }}</span>
						<OzCheckbox :model-value="moduleAll(m)" label="Tümü" @update:model-value="toggleModule(m, $event)" />
					</div>
					<div class="perm-checks">
						<OzCheckbox
							v-for="p in m.permissions"
							:key="p.key"
							:model-value="editingRole.permissions.includes(p.key)"
							:label="p.name"
							:description="p.key"
							@update:model-value="togglePerm(p.key, $event)"
						/>
					</div>
				</div>
			</div>
			<template #footer>
				<OzButton :disabled="roleBusy" @click="roleDrawer = false">Vazgeç</OzButton>
				<OzButton variant="primary" icon="check" :disabled="!roleValid" :loading="roleBusy" @click="saveRole">
					{{ editingRole?.isNew ? 'Rolü oluştur' : 'Kaydet' }}
				</OzButton>
			</template>
		</OzDrawer>

		<!-- İzin penceresi -->
		<OzModal v-model:open="permModal" :title="editingPerm?.isNew ? 'Yeni izin' : 'İzni düzenle'" subtitle="Sistem anahtarı ve görünen ad" icon="shield" size="sm" :persistent="permBusy">
			<form v-if="editingPerm" class="modal-form" @submit.prevent="savePerm">
				<OzField label="Sistem anahtarı" required hint="Modül öneki + nokta: users.view, orders.create" :error="permKeyError">
					<OzInput v-model="editingPerm.name" class="mono" placeholder="users.view" />
				</OzField>
				<OzField label="Görünen ad">
					<OzInput v-model="editingPerm.display_name" placeholder="Örn. Kullanıcıları görüntüle" />
				</OzField>
				<button type="submit" hidden />
			</form>
			<template #footer>
				<OzButton :disabled="permBusy" @click="permModal = false">Vazgeç</OzButton>
				<OzButton variant="primary" icon="check" :disabled="!permValid" :loading="permBusy" @click="savePerm">{{ editingPerm?.isNew ? 'Oluştur' : 'Güncelle' }}</OzButton>
			</template>
		</OzModal>
	</div>
</template>

<style scoped>
.toolbar { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
.search { flex: 1 1 200px; max-width: 280px; margin-left: auto; }

.role-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 10px; }
.role-card { display: flex; align-items: flex-start; gap: 12px; padding: 14px; border: 1px solid var(--oz-border); border-radius: 12px; background: var(--oz-surface); }
.role-avatar { width: 38px; height: 38px; border-radius: 11px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 800; font-size: 15px; }
.role-body { flex: 1; min-width: 0; }
.role-title { display: flex; align-items: center; gap: 8px; }
.role-title b { font-size: 13.5px; color: var(--oz-ink-soft); }
.role-key, .perm-key { font-family: ui-monospace, monospace; font-size: 11px; color: var(--oz-muted); }
.role-meta { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 8px; font-size: 11.5px; color: var(--oz-muted); }
.role-meta span { display: inline-flex; align-items: center; gap: 4px; }
.role-actions { display: flex; gap: 6px; flex-shrink: 0; }

.matrix-scroll { overflow: auto; max-height: 60vh; border: 1px solid var(--oz-border); border-radius: 12px; }
.matrix { width: 100%; border-collapse: collapse; font-size: 12.5px; }
.matrix th { position: sticky; top: 0; z-index: 2; background: var(--oz-surface-2); padding: 10px 12px; font-size: 11px; font-weight: 700; color: var(--oz-muted); text-transform: uppercase; letter-spacing: .03em; white-space: nowrap; border-bottom: 1px solid var(--oz-border); }
.matrix th:not(.perm-col) { text-transform: none; letter-spacing: 0; font-size: 11.5px; } /* rol adları (anahtar olabilir) olduğu gibi */
.matrix td { padding: 8px 12px; border-top: 1px solid var(--oz-border-soft); }
.matrix .perm-col { position: sticky; left: 0; z-index: 1; background: var(--oz-surface); text-align: left; min-width: 220px; }
.matrix th.perm-col { z-index: 3; background: var(--oz-surface-2); }
.matrix .cell { text-align: center; }
.role-dot { display: inline-block; width: 8px; height: 8px; border-radius: 50%; margin-right: 6px; vertical-align: 1px; }
.module-row td { background: var(--oz-accent-soft); color: var(--oz-accent); }
.module-row span { font-size: 11px; opacity: .8; margin-left: 6px; }
.perm-name { font-weight: 600; color: var(--oz-ink-soft); }

.perm-group { border: 1px solid var(--oz-border); border-radius: 12px; overflow: hidden; }
.perm-group-head { display: flex; justify-content: space-between; padding: 10px 14px; background: var(--oz-surface-2); font-size: 12.5px; color: var(--oz-ink-soft); }
.perm-group-head span { font-size: 11.5px; color: var(--oz-muted); }
.perm-row { display: flex; align-items: center; gap: 12px; padding: 10px 14px; border-top: 1px solid var(--oz-border-soft); }
.perm-info { flex: 1; min-width: 0; font-size: 12.5px; }

.drawer-body { display: flex; flex-direction: column; gap: 16px; }
.form-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px 16px; }
.perm-head { display: flex; align-items: baseline; justify-content: space-between; border-top: 1px solid var(--oz-border-soft); padding-top: 14px; }
.perm-head h4 { margin: 0; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: var(--oz-ink-soft); }
.perm-head span { font-size: 12px; color: var(--oz-muted); }
.perm-module { border: 1px solid var(--oz-border); border-radius: 12px; padding: 12px 14px; display: flex; flex-direction: column; gap: 10px; }
.perm-module-head { display: flex; align-items: center; gap: 8px; font-size: 13px; color: var(--oz-ink-soft); }
.perm-module-head .count { margin-right: auto; font-size: 11.5px; color: var(--oz-muted); }
.perm-checks { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 10px; }
.modal-form { display: flex; flex-direction: column; gap: 14px; }
.mono :deep(input) { font-family: ui-monospace, monospace; }

@media (max-width: 640px) {
	.search { max-width: none; margin-left: 0; }
	.form-grid { grid-template-columns: minmax(0, 1fr); }
}
</style>
