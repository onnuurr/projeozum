<script setup>
import { ref, computed, inject } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import {
	OzPageHeader, OzModuleTabs, OzStatTile, OzCard, OzTable, OzPill, OzButton, OzAvatar, OzDropdown, OzEmptyState,
	OzDrawer, OzField, OzInput, OzSelect, OzCombobox, useOzConfirm,
} from '@/Components/Oz'
import { useCan } from '@/composables/useCan'

// Süper admin · Kullanıcılar (Oz kit). Liste istemci tarafında süzülür (OzTable).
//   Ekle / düzenle / aktiflik / sil → Inertia istekleri (UserController); doğrulama hataları paneldeki alanlara düşer.
defineOptions({ layout: AppLayout })

const props = defineProps({
	users: { type: Array, default: () => [] },
	roles: { type: Array, default: () => [] },
	tenants: { type: Array, default: () => [] },
})

const page = usePage()
const { can } = useCan()
const canManage = computed(() => can('users.manage'))
const showToast = inject('showToast', null)
const confirm = useOzConfirm()
const meId = computed(() => page.props.auth?.user?.id)

/* ── roller ── */
const ROLE_LABELS = { superadmin: 'Süper admin', tenant: 'İş ortağı', 'tenant-user': 'İş ortağı kullanıcısı' }
const ROLE_TONES = { superadmin: 'accent', tenant: 'info', 'tenant-user': 'neutral' }
const roleLabel = (name) => props.roles.find((r) => r.name === name)?.display_name || ROLE_LABELS[name] || name
const roleOptions = computed(() => props.roles.map((r) => ({ value: r.name, label: roleLabel(r.name) })))

/* ── özet ── */
const activeCount = computed(() => props.users.filter((u) => u.is_active).length)
const stats = computed(() => [
	{ key: 'total', label: 'Toplam', value: props.users.length, icon: 'users', tone: 'accent' },
	{ key: 'active', label: 'Aktif', value: activeCount.value, icon: 'success', tone: 'success' },
	{ key: 'passive', label: 'Pasif', value: props.users.length - activeCount.value, icon: 'eye-off', tone: props.users.length - activeCount.value ? 'warning' : 'success' },
	{ key: 'tenant', label: 'İş ortağına bağlı', value: props.users.filter((u) => u.tenant).length, icon: 'building', tone: 'info' },
])

/* ── tablo ── */
const rows = computed(() => props.users.map((u) => ({
	...u,
	status: u.is_active ? 'active' : 'passive',
	tenant_name: u.tenant?.name ?? '',
	created_day: u.created_at?.slice(0, 10) ?? '',
})))
const columns = computed(() => [
	{ key: 'name', label: 'Kullanıcı', sortable: true, searchValue: (r) => `${r.name} ${r.email} ${r.phone ?? ''} ${r.job_title ?? ''}` },
	{
		key: 'role', label: 'Rol', sortable: true, sortValue: (r) => roleLabel(r.role ?? ''), filterValue: (r) => r.role ?? '__none__',
		filter: { type: 'select', multiple: true, options: [...roleOptions.value, { value: '__none__', label: 'Rolsüz' }] },
	},
	{
		key: 'tenant_name', label: 'İş ortağı', sortable: true,
		filter: { type: 'select', multiple: true, options: [...new Set(rows.value.map((r) => r.tenant_name).filter(Boolean))].map((n) => ({ value: n, label: n })) },
	},
	{ key: 'job_title', label: 'Unvan', sortable: true },
	{ key: 'phone', label: 'Telefon' },
	{
		key: 'status', label: 'Durum', sortable: true,
		filter: { type: 'select', options: [{ value: 'active', label: 'Aktif' }, { value: 'passive', label: 'Pasif' }] },
	},
	{ key: 'created_day', label: 'Kayıt', sortable: true, filter: { type: 'date' } },
	{ key: 'actions', label: '', align: 'right', width: '48px', searchable: false },
])

const rowActions = (u) => {
	const self = u.id === meId.value
	return [
		{ key: 'edit', label: 'Düzenle', icon: 'edit' },
		{ key: 'toggle', label: u.is_active ? 'Pasifleştir' : 'Aktifleştir', icon: u.is_active ? 'eye-off' : 'eye', disabled: self, hint: self ? 'Kendi hesabınız' : '' },
		{ divider: true },
		{ key: 'remove', label: 'Sil', icon: 'trash', danger: true, disabled: self, hint: self ? 'Kendi hesabınız' : '' },
	]
}
function onAction(key, u) {
	if (key === 'edit') openEdit(u)
	else if (key === 'toggle') toggle(u)
	else if (key === 'remove') remove(u)
}

/* ── ekle / düzenle ── */
const drawerOpen = ref(false)
const editing = ref(null)
const busy = ref(false)
const errors = ref({})
const blank = () => ({ name: '', email: '', phone: '', job_title: '', password: '', password_confirmation: '', role: '', tenant_id: '' })
const form = ref(blank())
const lockedRole = computed(() => editing.value?.role === 'superadmin')
const tenantOptions = computed(() => props.tenants.map((t) => ({ value: t.id, label: t.name })))

function openCreate() {
	editing.value = null
	errors.value = {}
	form.value = blank()
	drawerOpen.value = true
}
function openEdit(u) {
	if (!canManage.value) return
	editing.value = u
	errors.value = {}
	form.value = {
		name: u.name, email: u.email, phone: u.phone ?? '', job_title: u.job_title ?? '',
		password: '', password_confirmation: '', role: u.role ?? '', tenant_id: u.tenant?.id ?? '',
	}
	drawerOpen.value = true
}

function submit() {
	if (busy.value) return
	busy.value = true
	errors.value = {}
	const payload = { ...form.value, tenant_id: form.value.tenant_id || null }
	const title = editing.value ? 'Kullanıcı güncellendi' : 'Kullanıcı eklendi'
	const opts = {
		preserveScroll: true,
		preserveState: true,
		onSuccess: () => {
			drawerOpen.value = false
			showToast?.({ type: 'success', title, message: payload.name })
		},
		// Alan hataları panelde; toast yalnızca alana bağlanamayanlar için (panel altbilgisini örtmesin)
		onError: (errs) => {
			errors.value = errs
			const shown = ['name', 'email', 'password', 'phone', 'job_title', 'role', 'tenant_id']
			const other = Object.entries(errs).find(([k]) => !shown.includes(k))
			if (other) showToast?.({ type: 'error', title: 'Kaydedilemedi', message: other[1] })
		},
		onFinish: () => { busy.value = false },
	}
	if (editing.value) router.put(route('superadmin.users.update', editing.value.id), payload, opts)
	else router.post(route('superadmin.users.store'), payload, opts)
}

function toggle(u) {
	router.post(route('superadmin.users.toggle', u.id), {}, {
		preserveScroll: true,
		preserveState: true,
		onSuccess: () => showToast?.({ type: 'success', title: u.is_active ? 'Kullanıcı pasifleştirildi' : 'Kullanıcı aktifleştirildi', message: u.name }),
		onError: (errs) => showToast?.({ type: 'error', title: 'İşlem başarısız', message: Object.values(errs)[0] || 'Sunucu hatası.' }),
	})
}

async function remove(u) {
	const ok = await confirm.danger('Kullanıcı silinsin mi?', `“${u.name}” (${u.email}) kalıcı olarak silinecek.`)
	if (!ok) return
	router.delete(route('superadmin.users.destroy', u.id), {
		preserveScroll: true,
		preserveState: true,
		onSuccess: () => showToast?.({ type: 'warning', title: 'Kullanıcı silindi', message: u.name }),
		onError: (errs) => showToast?.({ type: 'error', title: 'Silinemedi', message: Object.values(errs)[0] || 'Sunucu hatası.' }),
	})
}

const fmtDate = (s) => (s ? new Date(s.replace(' ', 'T')).toLocaleDateString('tr-TR', { day: 'numeric', month: 'short', year: 'numeric' }) : '—')
</script>

<template>
	<Head title="Kullanıcılar · Süper Admin" />

	<OzPageHeader title="Kullanıcılar" :subtitle="`${users.length} kullanıcı · ${activeCount} aktif`">
		<template v-if="canManage" #actions>
			<OzButton variant="primary" icon="user-plus" @click="openCreate">Yeni kullanıcı</OzButton>
		</template>
	</OzPageHeader>
	<OzModuleTabs />

	<div class="stats">
		<OzStatTile v-for="s in stats" :key="s.key" :label="s.label" :value="s.value" :icon="s.icon" :tone="s.tone" />
	</div>

	<OzCard title="Kullanıcı listesi" :meta="`${users.length} kayıt`" class="block">
		<OzTable
			:columns="columns"
			:rows="rows"
			:page-size="20"
			:default-sort="{ key: 'name', dir: 'asc' }"
			searchable
			search-placeholder="İsim, e-posta, telefon ara…"
			min-width="860px"
			class="users"
			:class="{ clickable: canManage }"
			@row-click="openEdit"
		>
			<template #cell-name="{ row }">
				<div class="who">
					<OzAvatar :name="row.name" :size="30" tone="auto" :status="row.is_active ? '' : 'offline'" />
					<div class="who-text">
						<b>{{ row.name }} <OzPill v-if="row.id === meId" tone="accent" class="me">Siz</OzPill></b>
						<span>{{ row.email }}</span>
					</div>
				</div>
			</template>
			<template #cell-role="{ row }">
				<OzPill v-if="row.role" :tone="ROLE_TONES[row.role] ?? 'neutral'">{{ roleLabel(row.role) }}</OzPill>
				<span v-else class="dim">Rolsüz</span>
			</template>
			<template #cell-tenant_name="{ value }"><span :class="{ dim: !value }">{{ value || '—' }}</span></template>
			<template #cell-job_title="{ value }"><span :class="{ dim: !value }">{{ value || '—' }}</span></template>
			<template #cell-phone="{ value }"><span :class="{ dim: !value }">{{ value || '—' }}</span></template>
			<template #cell-status="{ row }">
				<OzPill :tone="row.is_active ? 'success' : 'neutral'">{{ row.is_active ? 'Aktif' : 'Pasif' }}</OzPill>
			</template>
			<template #cell-created_day="{ row }"><span class="date">{{ fmtDate(row.created_at) }}</span></template>
			<template #cell-actions="{ row }">
				<!-- satır tıklaması (düzenle) menüye taşmasın -->
				<div v-if="canManage" @click.stop>
					<OzDropdown :items="rowActions(row)" :label="`${row.name} işlemleri`" @select="(k) => onAction(k, row)" />
				</div>
			</template>
			<template #empty="{ filtered, clear }">
				<OzEmptyState v-if="filtered" icon="filter" title="Eşleşen kullanıcı yok" description="Aramayı değiştirin ya da bazı filtreleri kaldırın." compact>
					<template #actions><OzButton size="sm" icon="x" @click="clear">Filtreleri temizle</OzButton></template>
				</OzEmptyState>
				<OzEmptyState v-else icon="users" title="Henüz kullanıcı yok" compact />
			</template>
		</OzTable>
	</OzCard>

	<OzDrawer
		v-model:open="drawerOpen"
		:title="editing ? `“${editing.name}” düzenle` : 'Yeni kullanıcı'"
		:subtitle="editing ? editing.email : 'Hesap oluşturulur ve hemen aktif olur'"
		:icon="editing ? 'edit' : 'user-plus'"
		size="md"
		:persistent="busy"
		initial-focus="auto"
	>
		<form class="form" @submit.prevent="submit">
			<div class="grid-2">
				<OzField label="Ad soyad" required :error="errors.name">
					<OzInput v-model="form.name" maxlength="150" autocomplete="off" />
				</OzField>
				<OzField label="E-posta" required :error="errors.email">
					<OzInput v-model="form.email" type="email" icon="mail" maxlength="255" autocomplete="off" />
				</OzField>
				<OzField label="Telefon" :error="errors.phone">
					<OzInput v-model="form.phone" type="tel" icon="phone" maxlength="30" />
				</OzField>
				<OzField label="Unvan" :error="errors.job_title">
					<OzInput v-model="form.job_title" maxlength="100" placeholder="Örn. Üretim sorumlusu" />
				</OzField>
			</div>

			<div class="section">Yetki</div>
			<div class="grid-2">
				<OzField label="Rol" required :error="errors.role" :hint="lockedRole ? 'Süper admin rolü bu ekrandan değiştirilemez' : ''">
					<OzSelect v-model="form.role" :options="roleOptions" placeholder="Rol seçin" :disabled="lockedRole" />
				</OzField>
				<OzField label="İş ortağı" :error="errors.tenant_id" :hint="tenants.length ? '' : 'Aktif iş ortağı yok'">
					<OzCombobox v-model="form.tenant_id" :options="tenantOptions" placeholder="Yok" search-placeholder="İş ortağı ara…" clearable :disabled="!tenants.length" />
				</OzField>
			</div>

			<div class="section">{{ editing ? 'Şifre değiştir' : 'Şifre' }}</div>
			<div class="grid-2">
				<OzField label="Şifre" :required="!editing" :error="errors.password" :hint="editing ? 'Boş bırakılırsa değişmez' : 'En az 8 karakter'">
					<OzInput v-model="form.password" type="password" revealable autocomplete="new-password" />
				</OzField>
				<OzField label="Şifre tekrar" :required="!editing">
					<OzInput v-model="form.password_confirmation" type="password" revealable autocomplete="new-password" />
				</OzField>
			</div>
			<button type="submit" hidden />
		</form>

		<template #footer>
			<OzButton :disabled="busy" @click="drawerOpen = false">Vazgeç</OzButton>
			<OzButton variant="primary" :icon="editing ? 'check' : 'plus'" :loading="busy" @click="submit">{{ editing ? 'Kaydet' : 'Ekle' }}</OzButton>
		</template>
	</OzDrawer>
</template>

<style scoped>
.block { margin-bottom: 14px; }
.stats { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; margin-bottom: 14px; }
.users.clickable :deep(tbody tr) { cursor: pointer; }
.who { display: flex; align-items: center; gap: 10px; min-width: 0; }
.who-text { display: flex; flex-direction: column; gap: 1px; min-width: 0; }
.who-text b { display: flex; align-items: center; gap: 6px; font-size: 13px; color: var(--oz-ink-soft); white-space: nowrap; }
.who-text span { font-size: 11.5px; color: var(--oz-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.me { font-size: 10px; }
.dim { color: var(--oz-muted-2); }
.date { white-space: nowrap; color: var(--oz-muted); font-variant-numeric: tabular-nums; }

.form { display: flex; flex-direction: column; gap: 14px; }
.grid-2 { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
.section { margin-top: 4px; padding-top: 12px; border-top: 1px solid var(--oz-border-soft); font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: var(--oz-muted); }

@media (max-width: 1040px) { .stats { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
@media (max-width: 560px) { .grid-2 { grid-template-columns: minmax(0, 1fr); } }
</style>
