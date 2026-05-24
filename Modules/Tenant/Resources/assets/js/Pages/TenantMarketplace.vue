<template>
	<Head :title="`Pazaryeri · ${tenant.name}`" />
	<div class="page-tenant-marketplace">
		<Breadcrumb
			:items="[
				{ label: 'Ana Sayfa', to: '/workflow', icon: 'home' },
				{ label: 'İlişkiler' },
				{ label: 'Tenant\'lar', to: '/tenants' },
				{ label: tenant.name },
				{ label: 'Pazaryeri' },
			]"
		/>

		<div class="page-header">
			<div>
				<h1 class="page-title">{{ tenant.name }} · Pazaryeri Bağlantıları</h1>
				<p class="page-subtitle">
					<span class="mono">{{ tenant.code }}</span>
					<span v-if="tenant.type"> · {{ tenant.type.name }}</span>
					<span class="info-hint">· API anahtarları şifreli saklanır; UI'da gösterilmez</span>
				</p>
			</div>
			<button v-if="canManage" class="btn btn-primary btn-with-icon" :disabled="availableMarketplaces.length === 0" @click="openCreate">
				<svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
					<path d="M12 5v14M5 12h14" />
				</svg>
				Yeni Bağlantı
			</button>
		</div>

		<div class="card">
			<div class="card-header">
				<h3>Bağlantılar</h3>
				<span class="card-sub">{{ credentials.length }} / {{ marketplaces.length }} pazaryeri tanımlı</span>
			</div>

			<table class="data-table">
				<thead>
					<tr>
						<th style="width: 18%">Pazaryeri</th>
						<th style="width: 18%">Mağaza</th>
						<th style="width: 14%">Supplier ID</th>
						<th style="width: 12%">Durum</th>
						<th style="width: 12%">Anahtarlar</th>
						<th style="width: 14%">Son Senkron</th>
						<th style="width: 12%">İşlem</th>
					</tr>
				</thead>
				<tbody>
					<tr v-if="credentials.length === 0">
						<td colspan="7" class="empty-row">Henüz pazaryeri bağlantısı eklenmedi.</td>
					</tr>
					<tr v-for="c in credentials" :key="c.id">
						<td>
							<div class="marketplace-cell">
								<span :class="['marketplace-pill', `mp-${c.marketplace}`]">{{ c.marketplace_label }}</span>
							</div>
						</td>
						<td>
							<strong>{{ c.store_name || '—' }}</strong>
						</td>
						<td class="mono dim">{{ c.supplier_id || '—' }}</td>
						<td>
							<span :class="['status-pill', c.is_active ? 'active' : 'inactive']">
								{{ c.is_active ? '● Aktif' : '○ Pasif' }}
							</span>
						</td>
						<td>
							<span class="key-indicators">
								<span :class="['key-dot', c.has_api_key ? 'on' : 'off']" :title="c.has_api_key ? 'API Key ayarlı' : 'API Key boş'">K</span>
								<span :class="['key-dot', c.has_api_secret ? 'on' : 'off']" :title="c.has_api_secret ? 'Secret ayarlı' : 'Secret boş'">S</span>
							</span>
						</td>
						<td class="dim">{{ c.last_sync_at || 'Hiç' }}</td>
						<td>
							<div class="table-actions">
								<button v-if="canManage" class="table-action-btn view" @click="openEdit(c)" title="Düzenle">✏️</button>
								<button v-if="canManage" class="table-action-btn toggle" @click="toggle(c)" :title="c.is_active ? 'Pasifleştir' : 'Aktifleştir'">
									{{ c.is_active ? '⏸️' : '▶️' }}
								</button>
								<button v-if="canManage" class="table-action-btn delete" @click="confirmDelete(c)" title="Sil">🗑️</button>
							</div>
						</td>
					</tr>
				</tbody>
			</table>
		</div>

		<div v-if="anyError" class="error-list">
			<div v-for="c in credentialsWithError" :key="`e-${c.id}`" class="error-item">
				<strong>{{ c.marketplace_label }}</strong>
				<span>{{ c.last_error }}</span>
			</div>
		</div>

		<!-- ─── Credential Modal ─────────────────────────────────────── -->
		<AppModal v-model="modalOpen" :title="editing ? 'Bağlantıyı Düzenle' : 'Yeni Pazaryeri Bağlantısı'" size="md" variant="info">
			<form class="form-grid" @submit.prevent="submit">
				<div class="form-row">
					<label class="form-label">Pazaryeri <span class="req">*</span></label>
					<select v-model="form.marketplace" class="form-input" :disabled="!!editing">
						<option v-for="mp in marketplaceOptions" :key="mp.code" :value="mp.code">{{ mp.label }}</option>
					</select>
					<span v-if="errors.marketplace" class="form-error">{{ errors.marketplace }}</span>
				</div>
				<div class="form-row">
					<label class="form-label">Mağaza Adı</label>
					<input v-model="form.store_name" type="text" class="form-input" maxlength="191" placeholder="Örn: Ana Mağaza" />
					<span v-if="errors.store_name" class="form-error">{{ errors.store_name }}</span>
				</div>
				<div class="form-row">
					<label class="form-label">Supplier / Merchant ID</label>
					<input v-model="form.supplier_id" type="text" class="form-input" maxlength="64" placeholder="Pazaryerinin verdiği satıcı kodu" />
					<span v-if="errors.supplier_id" class="form-error">{{ errors.supplier_id }}</span>
				</div>
				<div class="form-row">
					<label class="form-label">API Key {{ editing ? '' : '*' }}</label>
					<input v-model="form.api_key" type="password" class="form-input" maxlength="500" autocomplete="new-password" :placeholder="editing ? 'Değiştirmek için yeni anahtar girin' : 'Pazaryeri API anahtarı'" />
					<span v-if="errors.api_key" class="form-error">{{ errors.api_key }}</span>
				</div>
				<div class="form-row">
					<label class="form-label">API Secret</label>
					<input v-model="form.api_secret" type="password" class="form-input" maxlength="500" autocomplete="new-password" :placeholder="editing ? 'Değiştirmek için yeni secret girin' : 'Pazaryeri API secret'" />
					<span v-if="errors.api_secret" class="form-error">{{ errors.api_secret }}</span>
				</div>
				<div class="form-row">
					<label class="form-check">
						<input v-model="form.is_active" type="checkbox" />
						<span>Bağlantı aktif</span>
					</label>
				</div>
				<div class="form-row">
					<label class="form-label">Not</label>
					<textarea v-model="form.notes" class="form-input" rows="2" maxlength="500" placeholder="İç notlar..." />
				</div>
				<div class="info-box">
					API Key ve Secret <strong>şifreli</strong> saklanır ve UI'da bir daha gösterilmez. Düzenlerken boş bırakırsanız mevcut değerler korunur.
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
	tenant: { type: Object, required: true },
	credentials: { type: Array, default: () => [] },
	marketplaces: { type: Array, default: () => [] },
})

const showToast = inject('showToast')
const $swal = inject('$swal')
const page = usePage()

const canManage = computed(() => (page.props.auth?.permissions ?? []).includes('marketplace.manage'))

const availableMarketplaces = computed(() => {
	const used = new Set(props.credentials.map(c => c.marketplace))
	return props.marketplaces.filter(mp => !used.has(mp.code))
})

const marketplaceOptions = computed(() => {
	if (editing.value) {
		return props.marketplaces.filter(mp => mp.code === form.marketplace)
	}
	return availableMarketplaces.value
})

const credentialsWithError = computed(() => props.credentials.filter(c => c.last_error))
const anyError = computed(() => credentialsWithError.value.length > 0)

/* ── Modal ── */
const modalOpen = ref(false)
const busy = ref(false)
const errors = ref({})
const editing = ref(null)

const emptyForm = () => ({
	marketplace: availableMarketplaces.value[0]?.code ?? props.marketplaces[0]?.code ?? '',
	store_name: '',
	supplier_id: '',
	api_key: '',
	api_secret: '',
	is_active: true,
	notes: '',
})

const form = reactive(emptyForm())

watch(modalOpen, (open) => {
	if (!open) setTimeout(() => {
		Object.assign(form, emptyForm())
		editing.value = null
		errors.value = {}
	}, 250)
})

function openCreate() {
	if (availableMarketplaces.value.length === 0) return
	Object.assign(form, emptyForm())
	editing.value = null
	errors.value = {}
	modalOpen.value = true
}

function openEdit(c) {
	editing.value = c
	errors.value = {}
	Object.assign(form, {
		marketplace: c.marketplace,
		store_name: c.store_name ?? '',
		supplier_id: c.supplier_id ?? '',
		api_key: '',
		api_secret: '',
		is_active: c.is_active,
		notes: c.notes ?? '',
	})
	modalOpen.value = true
}

function submit() {
	if (busy.value) return
	busy.value = true
	const opts = {
		preserveScroll: true,
		preserveState: true,
		onSuccess: () => {
			modalOpen.value = false
			showToast?.({
				type: 'success',
				title: editing.value ? 'Bağlantı güncellendi' : 'Bağlantı eklendi',
				message: marketplaceLabel(form.marketplace),
			})
		},
		onError: (errs) => {
			errors.value = errs
			showToast?.({ type: 'error', title: 'Kayıt başarısız', message: Object.values(errs)[0] || 'Hata.' })
		},
		onFinish: () => { busy.value = false },
	}
	if (editing.value) {
		router.put(`/tenants/${props.tenant.id}/marketplace/${editing.value.id}`, form, opts)
	} else {
		router.post(`/tenants/${props.tenant.id}/marketplace`, form, opts)
	}
}

function toggle(c) {
	router.post(`/tenants/${props.tenant.id}/marketplace/${c.id}/toggle`, {}, {
		preserveScroll: true,
		preserveState: true,
		onSuccess: () => showToast?.({
			type: c.is_active ? 'warning' : 'success',
			title: c.is_active ? 'Pasifleştirildi' : 'Aktifleştirildi',
			message: c.marketplace_label,
		}),
		onError: (errs) => showToast?.({ type: 'error', title: 'İşlem başarısız', message: Object.values(errs)[0] || 'Hata.' }),
	})
}

async function confirmDelete(c) {
	const ok = await $swal.dangerConfirm({
		title: 'Bağlantıyı Sil',
		html: `<strong>${c.marketplace_label}</strong> bağlantısı silinecek. Şifreli anahtarlar geri alınamaz.`,
		confirmText: 'Sil',
		cancelText: 'Vazgeç',
	})
	if (!ok) return
	router.delete(`/tenants/${props.tenant.id}/marketplace/${c.id}`, {
		preserveScroll: true,
		preserveState: true,
		onSuccess: () => showToast?.({ type: 'warning', title: 'Bağlantı silindi', message: c.marketplace_label }),
		onError: (errs) => showToast?.({ type: 'error', title: 'Silme başarısız', message: Object.values(errs)[0] || 'Hata.' }),
	})
}

function marketplaceLabel(code) {
	return props.marketplaces.find(m => m.code === code)?.label ?? code
}
</script>

<style scoped>
.page-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 20px; gap: 16px; }
.page-title { font-size: 22px; font-weight: 700; color: #1a1a2e; line-height: 1.2; }
.page-subtitle { font-size: 13px; color: #888; margin-top: 4px; display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
.info-hint { color: #aaa; font-size: 12px; }

.card { background: #fff; border-radius: 16px; border: 1px solid #ebebf0; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,.04); }
.card-header { padding: 14px 18px; display: flex; align-items: center; gap: 12px; border-bottom: 1px solid #f0f0f5; }
.card-header h3 { font-size: 15px; font-weight: 700; color: #1a1a2e; }
.card-sub { font-size: 12px; color: #888; }

.data-table { width: 100%; border-collapse: separate; border-spacing: 0; }
.data-table thead tr { background: #f8f8fc; }
.data-table th { text-align: left; padding: 12px 16px; font-size: 11px; font-weight: 600; color: #aaa; border-bottom: 1px solid #f0f0f5; text-transform: uppercase; letter-spacing: 0.04em; }
.data-table td { padding: 12px 16px; font-size: 13px; color: #444; border-bottom: 1px solid #f5f5f8; vertical-align: middle; }
.data-table tr:last-child td { border-bottom: none; }
.data-table tr:hover td { background: #fafafe; }
.empty-row { text-align: center !important; color: #aaa; padding: 32px 0 !important; font-style: italic; }
.dim { color: #aaa; font-size: 12px; }
.mono { font-family: 'SF Mono', Menlo, Consolas, monospace; font-size: 12px; }

.marketplace-pill { display: inline-block; padding: 4px 10px; border-radius: 6px; font-size: 12px; font-weight: 700; }
.mp-trendyol    { background: #fff6e6; color: #d97706; }
.mp-hepsiburada { background: #ffe6ef; color: #be185d; }
.mp-n11         { background: #ffe6e6; color: #b91c1c; }
.mp-ciceksepeti { background: #e6ffe6; color: #15803d; }

.status-pill { display: inline-block; padding: 3px 9px; border-radius: 6px; font-size: 11px; font-weight: 600; }
.status-pill.active { background: #dcfce7; color: #15803d; }
.status-pill.inactive { background: #f3f4f6; color: #6b7280; }

.key-indicators { display: inline-flex; gap: 4px; }
.key-dot { display: inline-flex; align-items: center; justify-content: center; width: 22px; height: 22px; border-radius: 6px; font-size: 10px; font-weight: 700; }
.key-dot.on  { background: #dcfce7; color: #15803d; }
.key-dot.off { background: #fee2e2; color: #b91c1c; }

.table-actions { display: flex; gap: 4px; }
.table-action-btn { background: #f3f4f6; border: none; cursor: pointer; font-size: 13px; padding: 5px 9px; border-radius: 6px; color: #6b7280; transition: all .15s; }
.table-action-btn.view:hover { background: #e0e7ff; color: #4f46e5; }
.table-action-btn.toggle:hover { background: #fef3c7; color: #92400e; }
.table-action-btn.delete:hover { background: #fee2e2; color: #dc2626; }

.form-grid { display: flex; flex-direction: column; gap: 14px; }
.form-row { display: flex; flex-direction: column; gap: 6px; }
.form-label { font-size: 12px; font-weight: 600; color: #1a1a2e; }
.form-label .req { color: #ef4444; }
.form-input { padding: 9px 12px; border: 1px solid #e8e8f0; border-radius: 8px; font-family: inherit; font-size: 13px; color: #1a1a2e; background: #fff; outline: none; transition: border-color .15s; }
.form-input:focus { border-color: #7c3aed; }
.form-input:disabled { background: #f5f5f8; color: #888; cursor: not-allowed; }
.form-error { font-size: 11.5px; color: #ef4444; }
.form-check { display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 13px; color: #1a1a2e; }

.info-box { background: #f0f9ff; color: #0c4a6e; padding: 10px 12px; border-radius: 8px; font-size: 12px; line-height: 1.5; border: 1px solid #bae6fd; }

.error-list { margin-top: 14px; display: flex; flex-direction: column; gap: 8px; }
.error-item { background: #fee2e2; color: #991b1b; padding: 10px 14px; border-radius: 10px; font-size: 12.5px; display: flex; gap: 10px; align-items: center; }
.error-item strong { color: #7f1d1d; }
</style>
