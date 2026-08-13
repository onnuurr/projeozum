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

		<PageHeader :title="`${tenant.name} · Erişim Yönetimi`">
			<template #subtitle>
				<span class="mono">{{ tenant.code }}</span>
				<span v-if="tenant.type"> · {{ tenant.type.name }}</span>
				<span class="info-hint">· Varsayılan tüm ürünler açık (blacklist mode)</span>
			</template>
		</PageHeader>

		<!-- ─── Kurallar ──────────────────────────────────────────────── -->
		<Card title="Blok Kuralları" body-class="p-0">
			<template #actions>
				<div class="card-actions-row">
					<span class="card-sub">{{ rules.length }} kural · marka veya kategori bazlı erişim engeli</span>
					<Button v-if="canManage" variant="primary" size="sm" with-icon @click="openRuleModal">
						<template #leading><Plus :size="11" /></template>
						Yeni Kural
					</Button>
				</div>
			</template>

			<DataTable :columns="ruleColumns" :data="rules" row-key-field="id" empty-title="Kural yok — tüm marka ve kategoriler açık">
				<template #scope_type="{ value }">
					<Badge :color="value === 'brand' ? 'warning' : 'info'" variant="tonal" :label="value === 'brand' ? 'Marka' : 'Kategori'" />
				</template>
				<template #scope_label="{ value }"><strong>{{ value }}</strong></template>
				<template #status="{}"><Badge color="danger" variant="tonal" :icon="Ban" label="Bloklu" /></template>
				<template #notes="{ value }"><span class="dim">{{ value || '—' }}</span></template>
				<template #actions="{ row }">
					<button v-if="canManage" class="table-action-btn delete" @click="confirmDeleteRule(row)" title="Kuralı Sil"><Trash2 :size="14" /></button>
				</template>
			</DataTable>
		</Card>

		<!-- ─── Ürün Override'ları ────────────────────────────────────── -->
		<Card title="Ürün Override'ları" body-class="p-0" style="margin-top: 20px">
			<template #actions>
				<div class="card-actions-row">
					<span class="card-sub">{{ overrides.length }} ürün · kural üzerine özel davranış</span>
					<Button v-if="canManage" variant="primary" size="sm" with-icon @click="openOverrideModal">
						<template #leading><Plus :size="11" /></template>
						Yeni Override
					</Button>
				</div>
			</template>

			<DataTable :columns="overrideColumns" :data="overrides" row-key-field="id" empty-title="Override yok — bu tenant kural setine uyuyor">
				<template #product_name="{ row }">
					<div class="product-cell">
						<strong>{{ row.product_name }}</strong>
						<span class="dim mono">{{ row.product_sku }}</span>
					</div>
				</template>
				<template #brand_name="{ value }"><span class="dim">{{ value || '—' }}</span></template>
				<template #category_name="{ value }"><span class="dim">{{ value || '—' }}</span></template>
				<template #base_price="{ value }"><span class="mono">₺{{ formatPrice(value) }}</span></template>
				<template #custom_price="{ value }">
					<span v-if="value !== null" class="mono price-custom">₺{{ formatPrice(value) }}</span>
					<span v-else class="dim">—</span>
				</template>
				<template #is_blocked="{ value }">
					<Badge v-if="value" color="danger" variant="tonal" :icon="Ban" label="Gizli" />
					<Badge v-else color="success" variant="tonal" :icon="Check" label="Açık" />
				</template>
				<template #actions="{ row }">
					<button v-if="canManage" class="table-action-btn view" @click="editOverride(row)" title="Düzenle"><Pencil :size="14" /></button>
					<button v-if="canCustomizeCopy" class="table-action-btn view" @click="openCustomCopy(row)" title="Özel Metin (Bu Bayiye Özel)"><NotebookPen :size="14" /></button>
					<button v-if="canManage" class="table-action-btn delete" @click="confirmDeleteOverride(row)" title="Sil"><Trash2 :size="14" /></button>
				</template>
			</DataTable>
		</Card>

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
				<Alert variant="warning">
					Seçilen <strong>{{ ruleForm.scope_type === 'brand' ? 'markaya' : 'kategoriye' }}</strong> ait tüm ürünler bu tenant'a kapatılacak.
					İstisna eklemek için ürün override'ı kullanın.
				</Alert>
			</form>
			<template #footer="{ close }">
				<Button variant="ghost" :disabled="ruleBusy" @click="close">İptal</Button>
				<Button variant="primary" :loading="ruleBusy" @click="submitRule">Ekle</Button>
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
				<Button variant="ghost" :disabled="overrideBusy" @click="close">İptal</Button>
				<Button variant="primary" :loading="overrideBusy" @click="submitOverride">
					{{ overrideEditing ? 'Kaydet' : 'Ekle' }}
				</Button>
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
					<Button
						v-if="customCopy.default_tenant_description"
						type="button"
						variant="ghost"
						size="xs"
						with-icon
						@click="customCopy.custom_description = customCopy.default_tenant_description"
					>
						<template #leading><ArrowDown :size="11" /></template>
						Varsayılandan kopyala
					</Button>
				</div>
			</form>
			<template #footer="{ close }">
				<Button variant="ghost" :disabled="customCopy.busy" @click="close">İptal</Button>
				<Button variant="primary" :loading="customCopy.busy" @click="submitCustomCopy">Kaydet</Button>
			</template>
		</AppModal>
	</div>
</template>

<script setup>
import { ref, computed, inject, reactive, watch } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import { Plus, Ban, Check, Pencil, NotebookPen, Trash2, ArrowDown } from 'lucide-vue-next'
import AppLayout from '@/Layouts/AppLayout.vue'
import Breadcrumb from '@/Components/Breadcrumb.vue'
import PageHeader from '@/Components/PageHeader.vue'
import Card from '@/Components/Card.vue'
import DataTable from '@/Components/DataTable.vue'
import Badge from '@/Components/Badge.vue'
import Alert from '@/Components/Alert.vue'
import Button from '@/Components/Button.vue'
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

const ruleColumns = [
	{ key: 'scope_type', label: 'Kapsam Tipi' },
	{ key: 'scope_label', label: 'Kapsam' },
	{ key: 'status', label: 'Durum', sortable: false },
	{ key: 'notes', label: 'Not' },
]

const overrideColumns = [
	{ key: 'product_name', label: 'Ürün' },
	{ key: 'brand_name', label: 'Marka' },
	{ key: 'category_name', label: 'Kategori' },
	{ key: 'base_price', label: 'Baz Fiyat' },
	{ key: 'custom_price', label: 'Özel Fiyat' },
	{ key: 'is_blocked', label: 'Durum' },
]

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
.info-hint { color: rgb(var(--color-muted)); font-size: 12px; }

.card-actions-row { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
.card-sub { font-size: 12px; color: rgb(var(--color-muted)); }

.dim { color: rgb(var(--color-muted)); font-size: 12px; }
.mono { font-family: 'SF Mono', Menlo, Consolas, monospace; font-size: 12px; }

.product-cell { display: flex; flex-direction: column; gap: 2px; }
.price-custom { color: rgb(var(--color-primary)); font-weight: 700; }

.table-action-btn { display: inline-flex; align-items: center; justify-content: center; background: rgb(var(--color-bg)); border: none; cursor: pointer; padding: 6px; border-radius: 6px; color: rgb(var(--color-muted)); transition: all .15s; }
.table-action-btn.view:hover { background: rgb(var(--color-primary-soft)); color: rgb(var(--color-primary)); }
.table-action-btn.delete:hover { background: rgb(var(--color-danger) / .12); color: rgb(var(--color-danger)); }

.form-grid { display: flex; flex-direction: column; gap: 14px; }
.form-row { display: flex; flex-direction: column; gap: 6px; }
.form-label { font-size: 12px; font-weight: 600; color: rgb(var(--color-ink)); }
.form-label .req { color: rgb(var(--color-danger)); }
.form-input { padding: 9px 12px; border: 1px solid rgb(var(--color-border)); border-radius: 8px; font-family: inherit; font-size: 13px; color: rgb(var(--color-ink)); background: rgb(var(--color-surface)); outline: none; transition: border-color .15s; }
.form-input:focus { border-color: rgb(var(--color-primary)); }
.form-error { font-size: 11.5px; color: rgb(var(--color-danger)); }
.form-check { display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 13px; color: rgb(var(--color-ink)); }

.search-wrap { position: relative; }
.search-results { position: absolute; top: 100%; left: 0; right: 0; max-height: 280px; overflow-y: auto; background: rgb(var(--color-surface)); border: 1px solid rgb(var(--color-border)); border-radius: 8px; margin-top: 4px; z-index: 100; box-shadow: 0 4px 16px rgba(0,0,0,.08); }
.search-result { padding: 10px 12px; cursor: pointer; display: flex; flex-direction: column; gap: 2px; border-bottom: 1px solid rgb(var(--color-border)); }
.search-result:hover { background: rgb(var(--color-bg)); }
.search-result:last-child { border-bottom: none; }
.search-result strong { font-size: 13px; color: rgb(var(--color-ink)); }
.search-result .dim { font-size: 11px; }

.picked-product { padding: 10px 12px; background: rgb(var(--color-info) / .08); border: 1px solid rgb(var(--color-info) / .25); border-radius: 8px; font-size: 13px; color: rgb(var(--color-info)); }
</style>
