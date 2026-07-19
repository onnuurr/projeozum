<template>
	<Head title="Pazaryerleri" />
	<div class="hub">
		<h1 class="page-title">Pazaryeri Bağlantıları</h1>
		<p class="page-subtitle">Ürünlerinizi farklı pazaryerlerine push edip satışlarını çekin. API anahtarları şifreli saklanır ve tam olarak bir daha gösterilmez.</p>

		<div class="grid">
			<div v-for="p in providers" :key="p.code" class="card">
				<Link :href="`/marketplace/${p.code}`" class="card-link">
					<div class="head">
						<span class="logo">{{ p.label.charAt(0) }}</span>
						<div>
							<div class="label">{{ p.label }}</div>
							<div class="state">
								<span v-if="p.connected" :class="['dot', p.active ? 'on' : 'off']"></span>
								<span v-if="p.connected">{{ p.store_name ?? 'Bağlı' }}</span>
								<span v-else class="dim">Bağlı değil</span>
							</div>
						</div>
					</div>
					<div class="badge" :class="p.live_ready ? 'live' : 'soon'">
						{{ p.live_ready ? 'Live' : 'Yakında' }}
					</div>
				</Link>

				<div v-if="p.connected" class="key-row">
					<span class="key-chip">Key: {{ p.credential.api_key_preview ?? '—' }}</span>
					<span class="key-chip">Secret: {{ p.credential.api_secret_preview ?? '—' }}</span>
				</div>

				<div v-if="canManage" class="card-actions">
					<button v-if="!p.connected" class="btn-link" @click="openConnect(p)">Bağlan</button>
					<template v-else>
						<button class="btn-link" @click="openEdit(p)">Düzenle</button>
						<button class="btn-link" @click="toggle(p.credential)">{{ p.credential.is_active ? 'Pasifleştir' : 'Aktifleştir' }}</button>
						<button class="btn-link danger" @click="confirmDelete(p)">Kaldır</button>
					</template>
				</div>
			</div>
		</div>

		<!-- Bağlantı ekle / düzenle drawer -->
		<div v-if="drawer.open" class="drawer-backdrop" @click.self="drawer.open = false">
			<div class="drawer">
				<h2>{{ drawer.mode === 'create' ? `${drawer.provider?.label} Bağlantısı Kur` : `${drawer.provider?.label} Bağlantısını Düzenle` }}</h2>
				<form @submit.prevent="submit">
					<label class="field">
						<span>Mağaza Adı</span>
						<input v-model="form.store_name" type="text" maxlength="191" placeholder="Örn: Ana Mağaza" />
						<small v-if="form.errors.store_name" class="err">{{ form.errors.store_name }}</small>
					</label>
					<label class="field">
						<span>Supplier / Merchant ID</span>
						<input v-model="form.supplier_id" type="text" maxlength="64" placeholder="Pazaryerinin verdiği satıcı kodu" />
						<small v-if="form.errors.supplier_id" class="err">{{ form.errors.supplier_id }}</small>
					</label>
					<label class="field">
						<span>API Key</span>
						<input v-model="form.api_key" type="password" maxlength="500" autocomplete="new-password" :placeholder="drawer.mode === 'edit' ? 'Değiştirmek için yeni anahtar girin' : 'Pazaryeri API anahtarı'" />
						<small v-if="form.errors.api_key" class="err">{{ form.errors.api_key }}</small>
					</label>
					<label class="field">
						<span>API Secret</span>
						<input v-model="form.api_secret" type="password" maxlength="500" autocomplete="new-password" :placeholder="drawer.mode === 'edit' ? 'Değiştirmek için yeni secret girin' : 'Pazaryeri API secret'" />
						<small v-if="form.errors.api_secret" class="err">{{ form.errors.api_secret }}</small>
					</label>
					<label class="field checkbox">
						<input v-model="form.is_active" type="checkbox" />
						<span>Bağlantı aktif</span>
					</label>
					<label class="field">
						<span>Not</span>
						<textarea v-model="form.notes" rows="2" maxlength="500" placeholder="İç notlar..." />
					</label>
					<p class="info-box">API Key ve Secret şifreli saklanır ve ekranda bir daha tam olarak gösterilmez. Düzenlerken boş bırakırsanız mevcut değerler korunur.</p>
					<div class="drawer-actions">
						<button type="button" @click="drawer.open = false">Vazgeç</button>
						<button type="submit" class="btn-primary" :disabled="form.processing">{{ drawer.mode === 'create' ? 'Bağlan' : 'Kaydet' }}</button>
					</div>
				</form>
			</div>
		</div>
	</div>
</template>

<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'
import { reactive, computed } from 'vue'
import TenantPortalLayout from '@/Layouts/TenantPortalLayout.vue'

defineOptions({ layout: TenantPortalLayout })

const props = defineProps({
	tenant: { type: Object, required: true },
	providers: { type: Array, default: () => [] },
})

const page = usePage()
const canManage = computed(() => (page.props.auth?.permissions ?? []).includes('marketplace.manage'))

const drawer = reactive({ open: false, mode: 'create', provider: null, credentialId: null })
const form = useForm({
	marketplace: '',
	store_name: '',
	supplier_id: '',
	api_key: '',
	api_secret: '',
	is_active: true,
	notes: '',
})

function openConnect(p) {
	drawer.mode = 'create'
	drawer.provider = p
	drawer.credentialId = null
	form.reset()
	form.clearErrors()
	form.marketplace = p.code
	form.is_active = true
	drawer.open = true
}

function openEdit(p) {
	drawer.mode = 'edit'
	drawer.provider = p
	drawer.credentialId = p.credential.id
	form.reset()
	form.clearErrors()
	form.marketplace = p.code
	form.store_name = p.credential.store_name ?? ''
	form.supplier_id = p.credential.supplier_id ?? ''
	form.is_active = p.credential.is_active
	form.notes = p.credential.notes ?? ''
	drawer.open = true
}

function submit() {
	if (drawer.mode === 'create') {
		form.post('/marketplace', { preserveScroll: true, onSuccess: () => (drawer.open = false) })
	} else {
		form.put(`/marketplace/${drawer.credentialId}`, { preserveScroll: true, onSuccess: () => (drawer.open = false) })
	}
}

function toggle(credential) {
	useForm({}).post(`/marketplace/${credential.id}/toggle`, { preserveScroll: true })
}

function confirmDelete(p) {
	if (confirm(`${p.label} bağlantısını kaldırmak istediğinize emin misiniz? Şifreli anahtarlar geri alınamaz.`)) {
		useForm({}).delete(`/marketplace/${p.credential.id}`, { preserveScroll: true })
	}
}
</script>

<style scoped>
.page-title { font-size: 22px; font-weight: 700; color: rgb(var(--portal-ink)); }
.page-subtitle { font-size: 13px; color: rgb(var(--portal-text-muted)); margin: 4px 0 18px; }
.grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 14px; }
.card { background: #fff; border: 1px solid rgb(var(--portal-border)); border-radius: 14px; padding: 16px 18px; display: flex; flex-direction: column; gap: 10px; }
.card-link { display: flex; align-items: center; justify-content: space-between; gap: 12px; text-decoration: none; color: inherit; }
.head { display: flex; align-items: center; gap: 12px; }
.logo { width: 42px; height: 42px; border-radius: 12px; background: linear-gradient(135deg, rgb(var(--portal-accent)), rgb(var(--portal-accent-hover))); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 18px; flex-shrink: 0; }
.label { font-size: 14px; font-weight: 700; color: rgb(var(--portal-ink)); }
.state { font-size: 11px; color: rgb(var(--portal-text-secondary)); display: flex; align-items: center; gap: 6px; margin-top: 2px; }
.dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; }
.dot.on { background: rgb(var(--color-success)); }
.dot.off { background: rgb(var(--color-warning)); }
.dim { color: #aaa; }
.badge { padding: 3px 10px; border-radius: 8px; font-size: 11px; font-weight: 700; flex-shrink: 0; }
.badge.live { background: rgb(var(--color-success) / .12); color: rgb(var(--color-success)); }
.badge.soon { background: rgb(var(--color-warning) / .14); color: rgb(180 83 9); }

.key-row { display: flex; flex-wrap: wrap; gap: 6px; }
.key-chip { font-family: 'SF Mono', Menlo, Consolas, monospace; font-size: 11px; background: rgb(var(--portal-bg-soft)); color: rgb(var(--portal-text-secondary)); padding: 3px 8px; border-radius: 6px; }

.card-actions { display: flex; gap: 12px; border-top: 1px solid rgb(var(--portal-bg-soft)); padding-top: 10px; }
.btn-link { background: none; border: none; padding: 0; font-size: 12px; font-weight: 600; color: rgb(var(--portal-accent)); cursor: pointer; }
.btn-link:hover { text-decoration: underline; }
.btn-link.danger { color: #dc2626; }

.drawer-backdrop { position: fixed; inset: 0; background: rgba(17,24,39,.4); display: flex; justify-content: flex-end; z-index: 60; }
.drawer { background: #fff; width: 420px; max-width: 100%; height: 100%; overflow-y: auto; padding: 24px; }
.drawer h2 { font-size: 16px; font-weight: 700; color: rgb(var(--portal-ink)); margin-bottom: 16px; }
.field { display: flex; flex-direction: column; gap: 4px; margin-bottom: 12px; font-size: 12px; font-weight: 600; color: rgb(var(--portal-ink)); }
.field input, .field textarea { padding: 8px 10px; border: 1px solid rgb(var(--portal-border)); border-radius: 8px; font-family: inherit; font-size: 13px; font-weight: 400; color: rgb(var(--portal-ink)); }
.field.checkbox { flex-direction: row; align-items: center; gap: 8px; }
.err { color: #ef4444; font-weight: 400; }
.info-box { background: rgb(var(--portal-accent) / .08); color: rgb(var(--portal-ink)); padding: 10px 12px; border-radius: 8px; font-size: 12px; line-height: 1.5; margin-bottom: 12px; }
.drawer-actions { display: flex; justify-content: flex-end; gap: 8px; margin-top: 8px; }
.drawer-actions button { padding: 8px 14px; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; border: 1px solid rgb(var(--portal-border)); background: #fff; }
.btn-primary { background: rgb(var(--portal-accent)); color: #fff; border: 0; }
.btn-primary:hover:not(:disabled) { background: rgb(var(--portal-accent-hover)); }
.btn-primary:disabled { opacity: .5; cursor: not-allowed; }
</style>
