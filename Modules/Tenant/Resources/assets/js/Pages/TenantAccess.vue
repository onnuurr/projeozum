<script setup>
import { ref, computed, inject, onBeforeUnmount } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import {
	OzPageHeader, OzModuleTabs, OzStatStrip, OzCard, OzTable, OzPill, OzButton, OzDropdown, OzEmptyState, OzIcon, OzSpinner,
	OzDrawer, OzField, OzInput, OzCombobox, OzTextarea, OzSwitch, OzAlert, OzRadioGroup, useOzConfirm,
} from '@/Components/Oz'
import { useCan } from '@/composables/useCan'

// Bir iş ortağının ürün erişimi (Oz kit). Varsayılan: tüm ürünler açık (blacklist).
//   Blok kuralları marka/kategori kapatır; ürün override'ları kuralın ve fiyatın üzerine yazar.
//   Tüm değişiklikler Inertia istekleri (TenantAccessController); ürün araması JSON (/tenants/access/products/search).
defineOptions({ layout: AppLayout })

const props = defineProps({
	tenant: { type: Object, required: true },
	rules: { type: Array, default: () => [] },
	overrides: { type: Array, default: () => [] },
	brands: { type: Array, default: () => [] },
	categories: { type: Array, default: () => [] },
	canCustomizeCopy: { type: Boolean, default: false },
})

const { can } = useCan()
const canManage = computed(() => can('tenant-access.manage'))
const showToast = inject('showToast', null)
const confirm = useOzConfirm()

const fmtPrice = (v) => `₺${Number(v ?? 0).toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
const firstError = (errs) => Object.values(errs)[0] || 'Sunucu hatası.'

/* ── özet ── */
const blockedCount = computed(() => props.overrides.filter((o) => o.is_blocked).length)
const pricedCount = computed(() => props.overrides.filter((o) => !o.is_blocked && o.custom_price !== null).length)
const stats = computed(() => [
	{ key: 'rules', label: 'Blok kuralı', value: props.rules.length, icon: 'shield', tone: props.rules.length ? 'warning' : 'success' },
	{ key: 'overrides', label: 'Ürün override', value: props.overrides.length, icon: 'layers', tone: 'accent' },
	{ key: 'blocked', label: 'Gizlenen ürün', value: blockedCount.value, icon: 'eye-off', tone: blockedCount.value ? 'danger' : 'success' },
	{ key: 'priced', label: 'Özel fiyatlı', value: pricedCount.value, icon: 'tag', tone: 'info' },
])

/* ── kurallar ── */
const ruleColumns = [
	{ key: 'scope_label', label: 'Kapsam', sortable: true },
	{
		key: 'scope_type', label: 'Tip', sortable: true, searchable: false,
		filter: { type: 'select', options: [{ value: 'brand', label: 'Marka' }, { value: 'category', label: 'Kategori' }] },
	},
	{ key: 'notes', label: 'Not' },
	{ key: 'actions', label: '', align: 'right', width: '48px', searchable: false },
]

const ruleOpen = ref(false)
const ruleBusy = ref(false)
const ruleErrors = ref({})
const ruleForm = ref({ scope_type: 'brand', scope_id: '', notes: '' })
const scopeTypeOptions = [
	{ value: 'brand', label: 'Marka', description: 'Markanın tüm ürünleri kapanır' },
	{ value: 'category', label: 'Kategori', description: 'Kategorinin tüm ürünleri kapanır' },
]
// Zaten kural olan kapsamlar seçilemez
const scopeOptions = computed(() => {
	const type = ruleForm.value.scope_type
	const taken = new Set(props.rules.filter((r) => r.scope_type === type).map((r) => r.scope_id))
	return (type === 'brand' ? props.brands : props.categories)
		.map((o) => ({ value: o.id, label: o.name, disabled: taken.has(o.id), description: taken.has(o.id) ? 'Zaten kural var' : '' }))
})
const scopeWord = computed(() => (ruleForm.value.scope_type === 'brand' ? 'marka' : 'kategori'))

function openRule() {
	ruleForm.value = { scope_type: 'brand', scope_id: '', notes: '' }
	ruleErrors.value = {}
	ruleOpen.value = true
}
function onScopeTypeChange() {
	ruleForm.value.scope_id = ''
	ruleErrors.value = {}
}

function submitRule() {
	if (ruleBusy.value) return
	if (!ruleForm.value.scope_id) {
		ruleErrors.value = { scope_id: `Bir ${scopeWord.value} seçin.` }
		return
	}
	ruleBusy.value = true
	ruleErrors.value = {}
	router.post(`/tenants/${props.tenant.id}/access/rules`, { ...ruleForm.value, is_blocked: true }, {
		preserveScroll: true,
		preserveState: true,
		onSuccess: () => {
			ruleOpen.value = false
			showToast?.({ type: 'success', title: 'Kural eklendi', message: 'Erişim güncellendi.' })
		},
		onError: (errs) => {
			ruleErrors.value = errs
			if (!errs.scope_id && !errs.notes) showToast?.({ type: 'error', title: 'Kaydedilemedi', message: firstError(errs) })
		},
		onFinish: () => { ruleBusy.value = false },
	})
}

async function removeRule(r) {
	const ok = await confirm.danger('Kural silinsin mi?', `“${r.scope_label}” kuralı silinecek; bu kapsamdaki ürünler iş ortağına yeniden açılır.`)
	if (!ok) return
	router.delete(`/tenants/${props.tenant.id}/access/rules/${r.id}`, {
		preserveScroll: true,
		preserveState: true,
		onSuccess: () => showToast?.({ type: 'warning', title: 'Kural silindi', message: r.scope_label }),
		onError: (errs) => showToast?.({ type: 'error', title: 'Silinemedi', message: firstError(errs) }),
	})
}

/* ── ürün override'ları ── */
const overrideColumns = [
	{ key: 'product_name', label: 'Ürün', sortable: true, searchValue: (r) => `${r.product_name ?? ''} ${r.product_sku ?? ''}` },
	{ key: 'brand_name', label: 'Marka', sortable: true, filter: { type: 'select', multiple: true } },
	{ key: 'category_name', label: 'Kategori', sortable: true, filter: { type: 'select', multiple: true } },
	{ key: 'base_price', label: 'Baz fiyat', sortable: true, align: 'right', searchable: false },
	{ key: 'custom_price', label: 'Özel fiyat', sortable: true, align: 'right', searchable: false },
	{
		key: 'is_blocked', label: 'Durum', sortable: true, searchable: false, filterValue: (r) => (r.is_blocked ? 'blocked' : 'open'),
		filter: { type: 'select', options: [{ value: 'open', label: 'Açık' }, { value: 'blocked', label: 'Gizli' }] },
	},
	{ key: 'actions', label: '', align: 'right', width: '48px', searchable: false },
]

const overrideActions = () => [
	...(canManage.value ? [{ key: 'edit', label: 'Düzenle', icon: 'edit' }] : []),
	...(props.canCustomizeCopy ? [{ key: 'copy', label: 'Özel metin', icon: 'pen' }] : []),
	...(canManage.value ? [{ divider: true }, { key: 'remove', label: 'Sil', icon: 'trash', danger: true }] : []),
]
function onOverrideAction(key, o) {
	if (key === 'edit') editOverride(o)
	else if (key === 'copy') openCustomCopy(o)
	else if (key === 'remove') removeOverride(o)
}

const ovOpen = ref(false)
const ovBusy = ref(false)
const ovErrors = ref({})
const ovEditing = ref(null)
const blankOverride = () => ({ product_id: null, product: null, is_blocked: false, custom_price: '', notes: '' })
const ovForm = ref(blankOverride())

function openOverride() {
	ovEditing.value = null
	ovErrors.value = {}
	ovForm.value = blankOverride()
	resetSearch()
	ovOpen.value = true
}
function editOverride(o) {
	if (!canManage.value) return
	ovEditing.value = o
	ovErrors.value = {}
	ovForm.value = {
		product_id: o.product_id,
		product: { name: o.product_name, sku: o.product_sku, brand_name: o.brand_name, category_name: o.category_name, price: o.base_price },
		is_blocked: !!o.is_blocked,
		custom_price: o.custom_price ?? '',
		notes: o.notes ?? '',
	}
	ovOpen.value = true
}

// Ürün araması (en az 2 karakter, 250 ms gecikme)
const query = ref('')
const results = ref([])
const searching = ref(false)
const searched = ref(false)
let timer = null
let controller = null
const existingIds = computed(() => new Set(props.overrides.map((o) => o.product_id)))

function resetSearch() {
	clearTimeout(timer)
	controller?.abort()
	query.value = ''
	results.value = []
	searched.value = false
	searching.value = false
}
function onSearch(v) {
	clearTimeout(timer)
	const q = String(v ?? '').trim()
	if (q.length < 2) {
		controller?.abort()
		results.value = []
		searched.value = false
		searching.value = false
		return
	}
	searching.value = true
	timer = setTimeout(async () => {
		controller?.abort()
		controller = new AbortController()
		try {
			const res = await fetch(`/tenants/access/products/search?q=${encodeURIComponent(q)}`, {
				headers: { Accept: 'application/json' },
				signal: controller.signal,
			})
			const data = await res.json()
			results.value = data.products ?? []
			searched.value = true
			searching.value = false
		} catch (e) {
			if (e.name === 'AbortError') return
			results.value = []
			searched.value = true
			searching.value = false
		}
	}, 250)
}
onBeforeUnmount(() => { clearTimeout(timer); controller?.abort() })

function pickProduct(p) {
	ovForm.value.product_id = p.id
	ovForm.value.product = p
	ovErrors.value = {}
	resetSearch()
}
function clearProduct() {
	ovForm.value.product_id = null
	ovForm.value.product = null
}

function submitOverride() {
	if (ovBusy.value) return
	if (!ovEditing.value && !ovForm.value.product_id) {
		ovErrors.value = { product_id: 'Bir ürün seçin.' }
		return
	}
	ovBusy.value = true
	ovErrors.value = {}
	const f = ovForm.value
	const payload = {
		is_blocked: f.is_blocked,
		custom_price: f.is_blocked || f.custom_price === '' || f.custom_price == null ? null : Number(f.custom_price),
		notes: f.notes,
	}
	const label = f.product?.name ?? ''
	const opts = {
		preserveScroll: true,
		preserveState: true,
		onSuccess: () => {
			ovOpen.value = false
			showToast?.({ type: 'success', title: ovEditing.value ? 'Override güncellendi' : 'Override eklendi', message: label })
		},
		onError: (errs) => {
			ovErrors.value = errs
			if (!errs.product_id && !errs.custom_price && !errs.notes) showToast?.({ type: 'error', title: 'Kaydedilemedi', message: firstError(errs) })
		},
		onFinish: () => { ovBusy.value = false },
	}
	if (ovEditing.value) router.put(`/tenants/${props.tenant.id}/access/overrides/${ovEditing.value.id}`, payload, opts)
	else router.post(`/tenants/${props.tenant.id}/access/overrides`, { product_id: f.product_id, ...payload }, opts)
}

async function removeOverride(o) {
	const ok = await confirm.danger('Override silinsin mi?', `“${o.product_name}” için override silinecek; ürün varsayılan kurallara döner.`)
	if (!ok) return
	router.delete(`/tenants/${props.tenant.id}/access/overrides/${o.id}`, {
		preserveScroll: true,
		preserveState: true,
		onSuccess: () => showToast?.({ type: 'warning', title: 'Override silindi', message: o.product_name }),
		onError: (errs) => showToast?.({ type: 'error', title: 'Silinemedi', message: firstError(errs) }),
	})
}

/* ── iş ortağına özel ürün metni ── */
const copyOpen = ref(false)
const copyBusy = ref(false)
const copyErrors = ref({})
const copy = ref({})

function openCustomCopy(o) {
	copyErrors.value = {}
	copy.value = {
		access_id: o.id,
		product_name: o.product_name,
		custom_name: o.custom_name || '',
		custom_description: o.custom_description || '',
		default_public_name: o.default_public_name || '',
		default_tenant_description: o.default_tenant_description || '',
	}
	copyOpen.value = true
}
function submitCustomCopy() {
	if (!copy.value.access_id || copyBusy.value) return
	copyBusy.value = true
	copyErrors.value = {}
	router.put(
		`/tenants/${props.tenant.id}/access/overrides/${copy.value.access_id}/custom-copy`,
		{ custom_name: copy.value.custom_name || null, custom_description: copy.value.custom_description || null },
		{
			preserveScroll: true,
			preserveState: true,
			onSuccess: () => {
				copyOpen.value = false
				showToast?.({ type: 'success', title: 'Özel metin kaydedildi', message: copy.value.product_name })
			},
			onError: (errs) => {
				copyErrors.value = errs
				if (!errs.custom_name && !errs.custom_description) showToast?.({ type: 'error', title: 'Kaydedilemedi', message: firstError(errs) })
			},
			onFinish: () => { copyBusy.value = false },
		},
	)
}
</script>

<template>
	<Head :title="`Erişim · ${tenant.name}`" />

	<OzPageHeader :title="`${tenant.name} · Erişim`">
		<template #subtitle>
			<span class="mono">{{ tenant.code }}</span>
			<template v-if="tenant.type"> · {{ tenant.type.name }}</template>
			· varsayılan olarak tüm ürünler açık
		</template>
		<template #actions>
			<OzButton icon="chevron-left" @click="router.visit('/tenants')">İş ortakları</OzButton>
		</template>
	</OzPageHeader>
	<OzModuleTabs />

	<OzStatStrip :items="stats" />

	<div class="cols">
		<OzCard title="Blok kuralları" subtitle="marka / kategori" class="block">
			<template #actions>
				<OzButton v-if="canManage" size="sm" variant="primary" icon="plus" @click="openRule">Yeni kural</OzButton>
				<span v-else class="meta">{{ rules.length }} kural</span>
			</template>
			<OzTable :columns="ruleColumns" :rows="rules" :default-sort="{ key: 'scope_label', dir: 'asc' }" min-width="420px">
				<template #cell-scope_label="{ row }">
					<span class="scope"><OzIcon name="eye-off" :size="13" class="blocked-ico" /><b>{{ row.scope_label }}</b></span>
				</template>
				<template #cell-scope_type="{ value }">
					<OzPill :tone="value === 'brand' ? 'warning' : 'info'">{{ value === 'brand' ? 'Marka' : 'Kategori' }}</OzPill>
				</template>
				<template #cell-notes="{ value }"><span class="note" :class="{ dim: !value }">{{ value || '—' }}</span></template>
				<template #cell-actions="{ row }">
					<OzDropdown v-if="canManage" :items="[{ key: 'remove', label: 'Kuralı sil', icon: 'trash', danger: true }]" :label="`${row.scope_label} işlemleri`" @select="removeRule(row)" />
				</template>
				<template #empty>
					<OzEmptyState icon="shield" title="Kural yok" description="Tüm marka ve kategoriler bu iş ortağına açık." compact>
						<template v-if="canManage" #actions><OzButton size="sm" icon="plus" @click="openRule">Kural ekle</OzButton></template>
					</OzEmptyState>
				</template>
			</OzTable>
		</OzCard>

		<OzCard title="Ürün override'ları" subtitle="kuralın ve fiyatın üzerine yazar" class="block">
			<template #actions>
				<OzButton v-if="canManage" size="sm" variant="primary" icon="plus" @click="openOverride">Yeni override</OzButton>
				<span v-else class="meta">{{ overrides.length }} ürün</span>
			</template>
			<OzTable
				:columns="overrideColumns"
				:rows="overrides"
				:page-size="15"
				:default-sort="{ key: 'product_name', dir: 'asc' }"
				:searchable="overrides.length > 5"
				search-placeholder="Ürün adı veya SKU ara…"
				min-width="760px"
				class="ov"
				:class="{ clickable: canManage }"
				@row-click="editOverride"
			>
				<template #cell-product_name="{ row }">
					<div class="who-text">
						<b>{{ row.product_name }}</b>
						<span class="mono">{{ row.product_sku }}<template v-if="row.custom_name"> · <em>özel ad: {{ row.custom_name }}</em></template></span>
					</div>
				</template>
				<template #cell-brand_name="{ value }"><span :class="{ dim: !value }">{{ value || '—' }}</span></template>
				<template #cell-category_name="{ value }"><span :class="{ dim: !value }">{{ value || '—' }}</span></template>
				<template #cell-base_price="{ value }"><span class="num">{{ fmtPrice(value) }}</span></template>
				<template #cell-custom_price="{ row }">
					<span v-if="!row.is_blocked && row.custom_price !== null" class="num accent">{{ fmtPrice(row.custom_price) }}</span>
					<span v-else class="dim">—</span>
				</template>
				<template #cell-is_blocked="{ value }">
					<OzPill :tone="value ? 'danger' : 'success'">{{ value ? 'Gizli' : 'Açık' }}</OzPill>
				</template>
				<template #cell-actions="{ row }">
					<div v-if="canManage || canCustomizeCopy" @click.stop>
						<OzDropdown :items="overrideActions()" :label="`${row.product_name} işlemleri`" @select="(k) => onOverrideAction(k, row)" />
					</div>
				</template>
				<template #empty="{ filtered, clear }">
					<OzEmptyState v-if="filtered" icon="filter" title="Eşleşen ürün yok" compact>
						<template #actions><OzButton size="sm" icon="x" @click="clear">Filtreleri temizle</OzButton></template>
					</OzEmptyState>
					<OzEmptyState v-else icon="layers" title="Override yok" description="Bu iş ortağı yalnızca kural setine uyuyor." compact>
						<template v-if="canManage" #actions><OzButton size="sm" icon="plus" @click="openOverride">Override ekle</OzButton></template>
					</OzEmptyState>
				</template>
			</OzTable>
		</OzCard>
	</div>

	<!-- Kural -->
	<OzDrawer v-model:open="ruleOpen" title="Yeni blok kuralı" subtitle="Marka ya da kategori bazında erişimi kapatır" icon="shield" tone="warning" size="sm" :persistent="ruleBusy">
		<form class="form" @submit.prevent="submitRule">
			<OzField label="Kapsam tipi" required>
				<OzRadioGroup v-model="ruleForm.scope_type" :options="scopeTypeOptions" variant="cards" aria-label="Kapsam tipi" @update:model-value="onScopeTypeChange" />
			</OzField>
			<OzField :label="ruleForm.scope_type === 'brand' ? 'Marka' : 'Kategori'" required :error="ruleErrors.scope_id">
				<OzCombobox
					:key="ruleForm.scope_type"
					v-model="ruleForm.scope_id"
					:options="scopeOptions"
					:placeholder="`${ruleForm.scope_type === 'brand' ? 'Marka' : 'Kategori'} seçin`"
					:search-placeholder="`${ruleForm.scope_type === 'brand' ? 'Marka' : 'Kategori'} ara…`"
				/>
			</OzField>
			<OzField label="Not" :error="ruleErrors.notes">
				<OzTextarea v-model="ruleForm.notes" :rows="2" :maxlength="500" placeholder="Bu kuralın amacı…" />
			</OzField>
			<OzAlert tone="warning">
				Seçilen {{ ruleForm.scope_type === 'brand' ? 'markaya' : 'kategoriye' }} ait tüm ürünler bu iş ortağına kapatılır. İstisna için ürün override'ı kullanın.
			</OzAlert>
			<button type="submit" hidden />
		</form>
		<template #footer>
			<OzButton :disabled="ruleBusy" @click="ruleOpen = false">Vazgeç</OzButton>
			<OzButton variant="primary" icon="plus" :loading="ruleBusy" @click="submitRule">Ekle</OzButton>
		</template>
	</OzDrawer>

	<!-- Override -->
	<OzDrawer
		v-model:open="ovOpen"
		:title="ovEditing ? 'Override düzenle' : 'Yeni ürün override'"
		subtitle="Ürün bazında görünürlük ve fiyat"
		:icon="ovEditing ? 'edit' : 'layers'"
		size="md"
		:persistent="ovBusy"
		initial-focus="auto"
	>
		<form class="form" @submit.prevent="submitOverride">
			<div v-if="ovForm.product" class="picked">
				<OzIcon name="package" :size="18" />
				<div class="picked-text">
					<b>{{ ovForm.product.name }}</b>
					<span><span class="mono">{{ ovForm.product.sku }}</span> · {{ [ovForm.product.brand_name, ovForm.product.category_name].filter(Boolean).join(' · ') }} · {{ fmtPrice(ovForm.product.price) }}</span>
				</div>
				<OzButton v-if="!ovEditing" size="sm" icon="x" aria-label="Ürünü değiştir" @click="clearProduct" />
			</div>
			<OzField v-else label="Ürün" required :error="ovErrors.product_id" hint="Ürün adı veya SKU; en az 2 karakter">
				<OzInput v-model="query" icon="search" placeholder="Ürün ara…" autocomplete="off" @update:model-value="onSearch" />
			</OzField>
			<div v-if="!ovForm.product && (searching || searched)" class="results">
				<div v-if="searching" class="results-state"><OzSpinner :size="16" /> Aranıyor…</div>
				<div v-else-if="!results.length" class="results-state">Sonuç bulunamadı</div>
				<button
					v-for="p in (searching ? [] : results)"
					:key="p.id"
					type="button"
					class="result"
					@click="pickProduct(p)"
				>
					<span class="result-text">
						<b>{{ p.name }}</b>
						<span><span class="mono">{{ p.sku }}</span> · {{ [p.brand_name, p.category_name].filter(Boolean).join(' · ') }}</span>
					</span>
					<OzPill v-if="existingIds.has(p.id)" tone="neutral">Override var</OzPill>
					<span class="num">{{ fmtPrice(p.price) }}</span>
				</button>
			</div>

			<OzSwitch v-model="ovForm.is_blocked" label="Bu ürünü iş ortağına kapat" description="Açıksa ürün kural gereği kapalı olsa bile görünür" />
			<OzField v-if="!ovForm.is_blocked" label="Özel fiyat" :error="ovErrors.custom_price" hint="Boş bırakılırsa varsayılan fiyat uygulanır">
				<OzInput v-model="ovForm.custom_price" type="number" min="0" step="0.01" suffix="₺" :placeholder="ovForm.product ? String(ovForm.product.price ?? '') : ''" />
			</OzField>
			<OzField label="Not" :error="ovErrors.notes">
				<OzTextarea v-model="ovForm.notes" :rows="2" :maxlength="500" placeholder="Override sebebi…" />
			</OzField>
			<button type="submit" hidden />
		</form>
		<template #footer>
			<OzButton :disabled="ovBusy" @click="ovOpen = false">Vazgeç</OzButton>
			<OzButton variant="primary" :icon="ovEditing ? 'check' : 'plus'" :loading="ovBusy" @click="submitOverride">{{ ovEditing ? 'Kaydet' : 'Ekle' }}</OzButton>
		</template>
	</OzDrawer>

	<!-- Özel metin -->
	<OzDrawer v-model:open="copyOpen" title="İş ortağına özel ürün metni" :subtitle="copy.product_name ?? ''" icon="pen" size="lg" :persistent="copyBusy" initial-focus="auto">
		<form class="form" @submit.prevent="submitCustomCopy">
			<OzField label="Özel ürün adı" :error="copyErrors.custom_name" :hint="copy.default_public_name ? `Varsayılan: ${copy.default_public_name}` : 'Boş: varsayılan ad gösterilir'">
				<OzInput v-model="copy.custom_name" maxlength="255" :placeholder="copy.default_public_name || 'Varsayılan ad'" />
			</OzField>
			<OzField label="Özel açıklama (Markdown)" :error="copyErrors.custom_description" hint="Boş: varsayılan iş ortağı açıklaması gösterilir">
				<OzTextarea v-model="copy.custom_description" :rows="12" :maxlength="10000" class="md" placeholder="Ürün açıklaması…" />
			</OzField>
			<div v-if="copy.default_tenant_description">
				<OzButton size="sm" icon="copy" @click="copy.custom_description = copy.default_tenant_description">Varsayılandan kopyala</OzButton>
			</div>
			<button type="submit" hidden />
		</form>
		<template #footer>
			<OzButton :disabled="copyBusy" @click="copyOpen = false">Vazgeç</OzButton>
			<OzButton variant="primary" icon="check" :loading="copyBusy" @click="submitCustomCopy">Kaydet</OzButton>
		</template>
	</OzDrawer>
</template>

<style scoped>
.block { margin-bottom: 14px; }
.cols { display: grid; grid-template-columns: minmax(0, 2fr) minmax(0, 3fr); gap: 14px; align-items: start; }
.cols > .block { margin-bottom: 0; }
.meta { font-size: 12px; color: var(--oz-muted); }
.mono { font-family: ui-monospace, 'SF Mono', Menlo, Consolas, monospace; font-size: 12px; }
.dim { color: var(--oz-muted-2); }
.num { font-variant-numeric: tabular-nums; white-space: nowrap; color: var(--oz-ink-soft); }
.num.accent { color: var(--oz-accent); font-weight: 700; }
.scope { display: inline-flex; align-items: center; gap: 7px; white-space: nowrap; }
.scope b { font-size: 13px; color: var(--oz-ink-soft); }
.blocked-ico { color: var(--oz-danger); }
.note { display: block; max-width: 260px; font-size: 12.5px; color: var(--oz-muted); }
.ov.clickable :deep(tbody tr) { cursor: pointer; }
.who-text { display: flex; flex-direction: column; gap: 1px; min-width: 0; }
.who-text b { font-size: 13px; color: var(--oz-ink-soft); }
.who-text span { font-size: 11px; color: var(--oz-muted); }
.who-text em { font-style: normal; font-family: inherit; color: var(--oz-accent); }

.form { display: flex; flex-direction: column; gap: 14px; }
.picked { display: flex; align-items: center; gap: 12px; padding: 12px 14px; border: 1px solid var(--oz-accent); border-radius: 12px; background: var(--oz-accent-soft); color: var(--oz-accent); }
.picked-text { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 2px; }
.picked-text b { font-size: 13.5px; color: var(--oz-ink); }
.picked-text > span { font-size: 12px; color: var(--oz-muted); }
.results { display: flex; flex-direction: column; margin-top: -8px; max-height: 300px; overflow-y: auto; border: 1px solid var(--oz-border); border-radius: 12px; }
.results-state { display: flex; align-items: center; gap: 8px; padding: 14px; font-size: 13px; color: var(--oz-muted); }
.result { display: flex; align-items: center; gap: 10px; width: 100%; padding: 10px 14px; border: 0; border-bottom: 1px solid var(--oz-border-soft); background: none; text-align: left; cursor: pointer; }
.result:last-child { border-bottom: 0; }
.result:hover, .result:focus-visible { background: var(--oz-accent-soft); outline: none; }
.result-text { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 1px; }
.result-text b { font-size: 13px; color: var(--oz-ink-soft); font-weight: 600; }
.result-text > span { font-size: 11.5px; color: var(--oz-muted); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.md :deep(textarea) { font-family: ui-monospace, 'SF Mono', Menlo, Consolas, monospace; font-size: 12.5px; }

@media (max-width: 1280px) { .cols { grid-template-columns: minmax(0, 1fr); } .cols > .block { margin-bottom: 0; } }
</style>
