<template>
	<Head :title="`Erişim · ${tenant.name}`" />
	<div class="page-tenant-access">
		<Breadcrumb
			:items="[
				{ label: 'Ana Sayfa', to: '/workflow', icon: 'home' },
				{ label: 'İlişkiler' },
				{ label: 'Tenant\'lar', to: '/tenants' },
				{ label: tenant.name },
				{ label: 'Erişim' },
			]"
		/>

		<div class="page-header">
			<div>
				<h1 class="page-title">{{ tenant.name }} · Erişim Yönetimi</h1>
				<p class="page-subtitle">
					<span class="mono">{{ tenant.code }}</span>
					<span v-if="tenant.type"> · {{ tenant.type.name }}</span>
					<span class="info-hint">· Varsayılan tüm ürünler açık (blacklist mode)</span>
				</p>
			</div>
		</div>

		<!-- ─── Kurallar ──────────────────────────────────────────────── -->
		<div class="card">
			<div class="card-header">
				<h3>Blok Kuralları</h3>
				<span class="card-sub">{{ rules.length }} kural · marka veya kategori bazlı erişim engeli</span>
				<button v-if="canManage" class="btn btn-primary btn-sm btn-with-icon" @click="openRuleModal">
					<svg width="11" height="11" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
						<path d="M12 5v14M5 12h14" />
					</svg>
					Yeni Kural
				</button>
			</div>

			<div class="table-scroll">
			<table class="data-table">
				<thead>
					<tr>
						<th style="width: 15%">Kapsam Tipi</th>
						<th style="width: 35%">Kapsam</th>
						<th style="width: 15%">Durum</th>
						<th style="width: 25%">Not</th>
						<th style="width: 10%">İşlem</th>
					</tr>
				</thead>
				<tbody>
					<tr v-if="rules.length === 0">
						<td colspan="5" class="empty-row">Kural yok — tüm marka ve kategoriler açık</td>
					</tr>
					<tr v-for="r in rules" :key="r.id">
						<td>
							<span :class="['badge', r.scope_type === 'brand' ? 'badge-brand' : 'badge-category']">
								{{ r.scope_type === 'brand' ? 'Marka' : 'Kategori' }}
							</span>
						</td>
						<td><strong>{{ r.scope_label }}</strong></td>
						<td>
							<span class="status-pill blocked">🚫 Bloklu</span>
						</td>
						<td class="dim">{{ r.notes || '—' }}</td>
						<td>
							<button v-if="canManage" class="table-action-btn delete" @click="confirmDeleteRule(r)" title="Kuralı Sil">🗑️</button>
						</td>
					</tr>
				</tbody>
			</table>
			</div>
		</div>

		<!-- ─── Ürün Override'ları ────────────────────────────────────── -->
		<div class="card" style="margin-top: 20px">
			<div class="card-header">
				<h3>Ürün Override'ları</h3>
				<span class="card-sub">{{ overrides.length }} ürün · kural üzerine özel davranış</span>
				<button v-if="canManage" class="btn btn-primary btn-sm btn-with-icon" @click="openOverrideModal">
					<svg width="11" height="11" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
						<path d="M12 5v14M5 12h14" />
					</svg>
					Yeni Override
				</button>
			</div>

			<div class="table-scroll">
			<table class="data-table">
				<thead>
					<tr>
						<th style="width: 28%">Ürün</th>
						<th style="width: 12%">Marka</th>
						<th style="width: 12%">Kategori</th>
						<th style="width: 12%">Baz Fiyat</th>
						<th style="width: 12%">Özel Fiyat</th>
						<th style="width: 12%">Durum</th>
						<th style="width: 12%">İşlem</th>
					</tr>
				</thead>
				<tbody>
					<tr v-if="overrides.length === 0">
						<td colspan="7" class="empty-row">Override yok — bu tenant kural setine uyuyor</td>
					</tr>
					<tr v-for="o in overrides" :key="o.id">
						<td>
							<div class="product-cell">
								<strong>{{ o.product_name }}</strong>
								<span class="dim mono">{{ o.product_sku }}</span>
							</div>
						</td>
						<td class="dim">{{ o.brand_name || '—' }}</td>
						<td class="dim">{{ o.category_name || '—' }}</td>
						<td><span class="mono">₺{{ formatPrice(o.base_price) }}</span></td>
						<td>
							<span v-if="o.custom_price !== null" class="mono price-custom">₺{{ formatPrice(o.custom_price) }}</span>
							<span v-else class="dim">—</span>
						</td>
						<td>
							<span :class="['status-pill', o.is_blocked ? 'blocked' : 'allowed']">
								{{ o.is_blocked ? '🚫 Gizli' : '✓ Açık' }}
							</span>
						</td>
						<td>
							<div class="table-actions">
								<button v-if="canManage" class="table-action-btn view" @click="editOverride(o)" title="Düzenle">✏️</button>
									<button v-if="canCustomizeCopy" class="table-action-btn view" @click="openCustomCopy(o)" title="Özel Metin (Bu Bayiye Özel)">📝</button>
								<button v-if="canManage" class="table-action-btn delete" @click="confirmDeleteOverride(o)" title="Sil">🗑️</button>
							</div>
						</td>
					</tr>
				</tbody>
			</table>
			</div>
		</div>

		<!-- ─── Kural Modal ───────────────────────────────────────────── -->
		<AppModal v-model="ruleModalOpen" title="Yeni Blok Kuralı" size="md" variant="warning">
			<form class="form-grid" @submit.prevent="submitRule">
				<div class="form-row">
					<label class="form-label">Kapsam Tipi <span class="req">*</span></label>
					<select v-model="ruleForm.scope_type" class="form-input">
						<option value="brand">Marka</option>
						<option value="category">Kategori</option>
					</select>
				</div>
				<div class="form-row">
					<label class="form-label">{{ ruleForm.scope_type === 'brand' ? 'Marka' : 'Kategori' }} <span class="req">*</span></label>
					<select v-model.number="ruleForm.scope_id" class="form-input">
						<option :value="null">Seçilmedi</option>
						<option v-for="opt in scopeOptions" :key="opt.id" :value="opt.id">{{ opt.name }}</option>
					</select>
					<span v-if="ruleErrors.scope_id" class="form-error">{{ ruleErrors.scope_id }}</span>
				</div>
				<div class="form-row">
					<label class="form-label">Not</label>
					<textarea v-model="ruleForm.notes" class="form-input" rows="2" placeholder="Bu kuralın amacı..." />
				</div>
				<div class="info-box">
					Seçilen <strong>{{ ruleForm.scope_type === 'brand' ? 'markaya' : 'kategoriye' }}</strong> ait tüm ürünler bu tenant'a kapatılacak.
					İstisna eklemek için ürün override'ı kullanın.
				</div>
			</form>
			<template #footer="{ close }">
				<button class="btn btn-ghost" @click="close" :disabled="ruleBusy">İptal</button>
				<button class="btn btn-primary" @click="submitRule" :disabled="ruleBusy">Ekle</button>
			</template>
		</AppModal>

		<!-- ─── Override Modal ────────────────────────────────────────── -->
		<AppModal v-model="overrideModalOpen" :title="overrideEditing ? 'Override Düzenle' : 'Yeni Ürün Override'" size="md" variant="info">
			<form class="form-grid" @submit.prevent="submitOverride">
				<div class="form-row" v-if="!overrideEditing">
					<label class="form-label">Ürün Ara <span class="req">*</span></label>
					<div class="search-wrap">
						<input v-model="overrideSearchQuery" type="text" class="form-input" placeholder="Ürün adı veya SKU ile arayın (min 2 karakter)..." @input="searchProducts" />
						<div v-if="searchResults.length > 0" class="search-results">
							<div v-for="p in searchResults" :key="p.id" class="search-result" @click="pickProduct(p)">
								<strong>{{ p.name }}</strong>
								<span class="dim mono">{{ p.sku }}</span>
								<span class="dim">{{ p.brand_name }} · {{ p.category_name }} · ₺{{ formatPrice(p.price) }}</span>
							</div>
						</div>
					</div>
					<span v-if="overrideErrors.product_id" class="form-error">{{ overrideErrors.product_id }}</span>
				</div>
				<div v-if="overrideForm.product_id" class="picked-product">
					<strong>{{ overrideForm.product_label }}</strong>
				</div>
				<div class="form-row">
					<label class="form-check">
						<input v-model="overrideForm.is_blocked" type="checkbox" />
						<span>Bu ürünü tenant'a kapat (override)</span>
					</label>
				</div>
				<div class="form-row" v-if="!overrideForm.is_blocked">
					<label class="form-label">Özel Fiyat (₺)</label>
					<input v-model.number="overrideForm.custom_price" type="number" min="0" step="0.01" class="form-input" placeholder="Boş bırakılırsa varsayılan fiyat" />
					<span v-if="overrideErrors.custom_price" class="form-error">{{ overrideErrors.custom_price }}</span>
				</div>
				<div class="form-row">
					<label class="form-label">Not</label>
					<textarea v-model="overrideForm.notes" class="form-input" rows="2" placeholder="Override sebebi..." />
				</div>
			</form>
			<template #footer="{ close }">
				<button class="btn btn-ghost" @click="close" :disabled="overrideBusy">İptal</button>
				<button class="btn btn-primary" @click="submitOverride" :disabled="overrideBusy">
					{{ overrideEditing ? 'Kaydet' : 'Ekle' }}
				</button>
			</template>
		</AppModal>

		<!-- ─── Özel Metin (Bayiye Özel) Modal ─────────────────────────── -->
		<AppModal v-model="customCopyOpen" title="Bayiye Özel Ürün Metni" size="lg" variant="info">
			<div v-if="customCopy.product_name" class="picked-product">
				<strong>{{ customCopy.product_name }}</strong>
			</div>
			<form class="form-grid" @submit.prevent="submitCustomCopy">
				<div class="form-row">
					<label class="form-label">Özel Ürün Adı</label>
					<input v-model="customCopy.custom_name" type="text" maxlength="255" class="form-input" placeholder="Boş: bu bayi için varsayılan adı gösterir" />
					<span v-if="customCopy.default_public_name" class="form-help">Varsayılan: {{ customCopy.default_public_name }}</span>
				</div>
				<div class="form-row">
					<label class="form-label">Özel Açıklama (Markdown)</label>
					<textarea v-model="customCopy.custom_description" rows="8" class="form-input" placeholder="Boş: default tenant_description'ı gösterir" />
					<button
						v-if="customCopy.default_tenant_description"
						type="button"
						class="btn btn-ghost btn-xs"
						@click="customCopy.custom_description = customCopy.default_tenant_description"
					>↓ Varsayılandan kopyala</button>
				</div>
			</form>
			<template #footer="{ close }">
				<button class="btn btn-ghost" @click="close" :disabled="customCopy.busy">İptal</button>
				<button class="btn btn-primary" @click="submitCustomCopy" :disabled="customCopy.busy">Kaydet</button>
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
	rules: { type: Array, default: () => [] },
	overrides: { type: Array, default: () => [] },
	brands: { type: Array, default: () => [] },
	categories: { type: Array, default: () => [] },
	canCustomizeCopy: { type: Boolean, default: false },
})

const showToast = inject('showToast')
const $swal = inject('$swal')
const page = usePage()

const canManage = computed(() => (page.props.auth?.permissions ?? []).includes('tenant-access.manage'))
const canCustomizeCopy = computed(() => props.canCustomizeCopy)

/* ── Bayiye Özel Metin modalı (M3) ── */
const customCopyOpen = ref(false)
const customCopy = reactive({
	access_id: null,
	product_name: '',
	custom_name: '',
	custom_description: '',
	default_public_name: '',
	default_tenant_description: '',
	busy: false,
})
function openCustomCopy(o) {
	Object.assign(customCopy, {
		access_id: o.id,
		product_name: o.product_name,
		custom_name: o.custom_name || '',
		custom_description: o.custom_description || '',
		default_public_name: o.default_public_name || '',
		default_tenant_description: o.default_tenant_description || '',
		busy: false,
	})
	customCopyOpen.value = true
}
function submitCustomCopy() {
	if (!customCopy.access_id || customCopy.busy) return
	customCopy.busy = true
	router.put(
		`/tenants/${props.tenant.id}/access/overrides/${customCopy.access_id}/custom-copy`,
		{ custom_name: customCopy.custom_name || null, custom_description: customCopy.custom_description || null },
		{
			preserveScroll: true,
			onSuccess: () => {
				customCopyOpen.value = false
				showToast?.({ type: 'success', title: 'Özel metin kaydedildi', message: customCopy.product_name })
			},
			onError: (errs) => {
				const first = Object.values(errs)[0]
				showToast?.({ type: 'error', title: 'Kayıt başarısız', message: first || 'Doğrulama hatası' })
			},
			onFinish: () => { customCopy.busy = false },
		},
	)
}

function formatPrice(v) {
	return Number(v ?? 0).toFixed(2).replace('.', ',')
}

const scopeOptions = computed(() => ruleForm.scope_type === 'brand' ? props.brands : props.categories)

/* ── Rule modal ── */
const ruleModalOpen = ref(false)
const ruleBusy = ref(false)
const ruleErrors = ref({})
const ruleForm = reactive({
	scope_type: 'brand',
	scope_id: null,
	notes: '',
})

watch(ruleModalOpen, (open) => {
	if (!open) setTimeout(() => {
		ruleForm.scope_type = 'brand'
		ruleForm.scope_id = null
		ruleForm.notes = ''
		ruleErrors.value = {}
	}, 250)
})

function openRuleModal() {
	ruleForm.scope_type = 'brand'
	ruleForm.scope_id = null
	ruleForm.notes = ''
	ruleErrors.value = {}
	ruleModalOpen.value = true
}

function submitRule() {
	if (ruleBusy.value) return
	if (!ruleForm.scope_id) {
		ruleErrors.value = { scope_id: 'Bir ' + (ruleForm.scope_type === 'brand' ? 'marka' : 'kategori') + ' seçin.' }
		return
	}
	ruleBusy.value = true
	router.post(`/tenants/${props.tenant.id}/access/rules`, {
		scope_type: ruleForm.scope_type,
		scope_id: ruleForm.scope_id,
		is_blocked: true,
		notes: ruleForm.notes,
	}, {
		preserveScroll: true,
		preserveState: true,
		onSuccess: () => {
			ruleModalOpen.value = false
			showToast?.({ type: 'success', title: 'Kural eklendi', message: 'Erişim güncellendi.' })
		},
		onError: (errs) => {
			ruleErrors.value = errs
			showToast?.({ type: 'error', title: 'Kayıt başarısız', message: Object.values(errs)[0] || 'Hata.' })
		},
		onFinish: () => { ruleBusy.value = false },
	})
}

async function confirmDeleteRule(r) {
	const ok = await $swal.dangerConfirm({
		title: 'Kuralı Sil',
		html: `<strong>${r.scope_label}</strong> kuralı silinecek.`,
		confirmText: 'Sil',
		cancelText: 'Vazgeç',
	})
	if (!ok) return
	router.delete(`/tenants/${props.tenant.id}/access/rules/${r.id}`, {
		preserveScroll: true,
		preserveState: true,
		onSuccess: () => showToast?.({ type: 'warning', title: 'Kural silindi', message: r.scope_label }),
		onError: (errs) => showToast?.({ type: 'error', title: 'Silme başarısız', message: Object.values(errs)[0] || 'Hata.' }),
	})
}

/* ── Override modal ── */
const overrideModalOpen = ref(false)
const overrideBusy = ref(false)
const overrideErrors = ref({})
const overrideEditing = ref(null)
const overrideSearchQuery = ref('')
const searchResults = ref([])
let searchTimer = null

const emptyOverride = () => ({
	product_id: null,
	product_label: '',
	is_blocked: false,
	custom_price: null,
	notes: '',
})

const overrideForm = reactive(emptyOverride())

watch(overrideModalOpen, (open) => {
	if (!open) setTimeout(() => {
		Object.assign(overrideForm, emptyOverride())
		overrideEditing.value = null
		overrideErrors.value = {}
		overrideSearchQuery.value = ''
		searchResults.value = []
	}, 250)
})

function openOverrideModal() {
	Object.assign(overrideForm, emptyOverride())
	overrideEditing.value = null
	overrideErrors.value = {}
	overrideSearchQuery.value = ''
	searchResults.value = []
	overrideModalOpen.value = true
}

function editOverride(o) {
	overrideEditing.value = o
	overrideErrors.value = {}
	Object.assign(overrideForm, {
		product_id: o.product_id,
		product_label: o.product_name,
		is_blocked: o.is_blocked,
		custom_price: o.custom_price,
		notes: o.notes ?? '',
	})
	overrideModalOpen.value = true
}

function searchProducts() {
	clearTimeout(searchTimer)
	const q = overrideSearchQuery.value.trim()
	if (q.length < 2) {
		searchResults.value = []
		return
	}
	searchTimer = setTimeout(async () => {
		try {
			const res = await fetch(`/tenants/access/products/search?q=${encodeURIComponent(q)}`, {
				headers: { 'Accept': 'application/json' },
			})
			const data = await res.json()
			searchResults.value = data.products ?? []
		} catch (e) {
			searchResults.value = []
		}
	}, 250)
}

function pickProduct(p) {
	overrideForm.product_id = p.id
	overrideForm.product_label = `${p.name} (${p.sku})`
	overrideSearchQuery.value = ''
	searchResults.value = []
}

function submitOverride() {
	if (overrideBusy.value) return
	if (!overrideEditing.value && !overrideForm.product_id) {
		overrideErrors.value = { product_id: 'Bir ürün seçin.' }
		return
	}
	overrideBusy.value = true
	const payload = {
		is_blocked: overrideForm.is_blocked,
		custom_price: overrideForm.is_blocked ? null : (overrideForm.custom_price ?? null),
		notes: overrideForm.notes,
	}
	const opts = {
		preserveScroll: true,
		preserveState: true,
		onSuccess: () => {
			overrideModalOpen.value = false
			showToast?.({ type: 'success', title: overrideEditing.value ? 'Override güncellendi' : 'Override eklendi', message: overrideForm.product_label })
		},
		onError: (errs) => {
			overrideErrors.value = errs
			showToast?.({ type: 'error', title: 'Kayıt başarısız', message: Object.values(errs)[0] || 'Hata.' })
		},
		onFinish: () => { overrideBusy.value = false },
	}
	if (overrideEditing.value) {
		router.put(`/tenants/${props.tenant.id}/access/overrides/${overrideEditing.value.id}`, payload, opts)
	} else {
		router.post(`/tenants/${props.tenant.id}/access/overrides`, { product_id: overrideForm.product_id, ...payload }, opts)
	}
}

async function confirmDeleteOverride(o) {
	const ok = await $swal.dangerConfirm({
		title: 'Override\'ı Sil',
		html: `<strong>${o.product_name}</strong> override\'ı silinecek. Ürün varsayılan kurallara dönecek.`,
		confirmText: 'Sil',
		cancelText: 'Vazgeç',
	})
	if (!ok) return
	router.delete(`/tenants/${props.tenant.id}/access/overrides/${o.id}`, {
		preserveScroll: true,
		preserveState: true,
		onSuccess: () => showToast?.({ type: 'warning', title: 'Override silindi', message: o.product_name }),
		onError: (errs) => showToast?.({ type: 'error', title: 'Silme başarısız', message: Object.values(errs)[0] || 'Hata.' }),
	})
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
.card-header .btn { margin-left: auto; }

.btn-sm { padding: 6px 12px; font-size: 12px; }

.data-table { width: 100%; border-collapse: separate; border-spacing: 0; }
.data-table thead tr { background: #f8f8fc; }
.data-table th { text-align: left; padding: 12px 16px; font-size: 11px; font-weight: 600; color: #aaa; border-bottom: 1px solid #f0f0f5; text-transform: uppercase; letter-spacing: 0.04em; }
.data-table td { padding: 12px 16px; font-size: 13px; color: #444; border-bottom: 1px solid #f5f5f8; vertical-align: middle; }
.data-table tr:last-child td { border-bottom: none; }
.data-table tr:hover td { background: #fafafe; }
.empty-row { text-align: center !important; color: #aaa; padding: 32px 0 !important; font-style: italic; }
.dim { color: #aaa; font-size: 12px; }
.mono { font-family: 'SF Mono', Menlo, Consolas, monospace; font-size: 12px; }

.badge { display: inline-block; padding: 3px 9px; border-radius: 6px; font-size: 11px; font-weight: 600; }
.badge-brand { background: #fef3c7; color: #92400e; }
.badge-category { background: #ddd6fe; color: rgb(var(--color-primary-hover)); }

.status-pill { display: inline-block; padding: 3px 9px; border-radius: 6px; font-size: 11px; font-weight: 600; }
.status-pill.allowed { background: #dcfce7; color: #15803d; }
.status-pill.blocked { background: #fee2e2; color: #b91c1c; }

.product-cell { display: flex; flex-direction: column; gap: 2px; }
.price-custom { color: rgb(var(--color-primary)); font-weight: 700; }

.table-actions { display: flex; gap: 4px; }
.table-action-btn { background: #f3f4f6; border: none; cursor: pointer; font-size: 13px; padding: 5px 9px; border-radius: 6px; color: #6b7280; transition: all .15s; }
.table-action-btn.view:hover { background: rgb(var(--color-primary-soft)); color: rgb(var(--color-primary)); }
.table-action-btn.delete:hover { background: #fee2e2; color: #dc2626; }

.form-grid { display: flex; flex-direction: column; gap: 14px; }
.form-row { display: flex; flex-direction: column; gap: 6px; }
.form-label { font-size: 12px; font-weight: 600; color: #1a1a2e; }
.form-label .req { color: #ef4444; }
.form-input { padding: 9px 12px; border: 1px solid #e8e8f0; border-radius: 8px; font-family: inherit; font-size: 13px; color: #1a1a2e; background: #fff; outline: none; transition: border-color .15s; }
.form-input:focus { border-color: rgb(var(--color-primary)); }
.form-error { font-size: 11.5px; color: #ef4444; }
.form-check { display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 13px; color: #1a1a2e; }

.info-box { background: #fef3c7; color: #92400e; padding: 10px 12px; border-radius: 8px; font-size: 12px; line-height: 1.5; }

.search-wrap { position: relative; }
.search-results { position: absolute; top: 100%; left: 0; right: 0; max-height: 280px; overflow-y: auto; background: #fff; border: 1px solid #e8e8f0; border-radius: 8px; margin-top: 4px; z-index: 100; box-shadow: 0 4px 16px rgba(0,0,0,.08); }
.search-result { padding: 10px 12px; cursor: pointer; display: flex; flex-direction: column; gap: 2px; border-bottom: 1px solid #f5f5f8; }
.search-result:hover { background: #f5f5f8; }
.search-result:last-child { border-bottom: none; }
.search-result strong { font-size: 13px; color: #1a1a2e; }
.search-result .dim { font-size: 11px; }

.picked-product { padding: 10px 12px; background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 8px; font-size: 13px; color: #0c4a6e; }
</style>
