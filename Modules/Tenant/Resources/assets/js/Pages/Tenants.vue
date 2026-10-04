<script setup>
import { ref, computed, inject, onMounted } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import {
	OzPageHeader, OzModuleTabs, OzStatStrip, OzCard, OzTable, OzPill, OzButton, OzAvatar, OzDropdown, OzEmptyState,
	OzProgress, OzDrawer, OzField, OzInput, OzSelect, OzTextarea, OzCheckbox, OzSwitch, OzAlert, OzIcon, useOzConfirm,
} from '@/Components/Oz'
import { useCan } from '@/composables/useCan'
import { isValidTaxNumber } from '../utils/turkishTaxNumber'

// İş ortakları (Oz kit). Liste istemci tarafında süzülür (OzTable); ?q, ?type_id, ?active başlangıç filtresi olur.
//   Ekle / düzenle / aktiflik / sil → Inertia istekleri (TenantController); doğrulama hataları paneldeki alanlara düşer.
//   ?from_application=ID → prefillApplication ile panel başvuru bilgileriyle açılır.
defineOptions({ layout: AppLayout })

const props = defineProps({
	tenants: { type: Array, default: () => [] },
	types: { type: Array, default: () => [] },
	carriers: { type: Array, default: () => [] },
	filters: { type: Object, default: () => ({}) },
	prefillApplication: { type: Object, default: null },
	defaultDiscountRate: { type: Number, default: 0 },
})

const { can } = useCan()
const canManage = computed(() => can('tenant.manage'))
const canAccess = computed(() => can('tenant-access.manage'))
const showToast = inject('showToast', null)
const confirm = useOzConfirm()

const fmtMoney = (v) => new Intl.NumberFormat('tr-TR', { style: 'currency', currency: 'TRY', maximumFractionDigits: 0 }).format(Number(v ?? 0))
const creditUsage = (t) => {
	const limit = Number(t.credit_limit ?? 0)
	return limit > 0 ? Math.round((Number(t.current_balance ?? 0) / limit) * 100) : null
}

/* ── özet ── */
const activeCount = computed(() => props.tenants.filter((t) => t.is_active).length)
const riskCount = computed(() => props.tenants.filter((t) => (creditUsage(t) ?? 0) >= 90).length)
const stats = computed(() => [
	{ key: 'total', label: 'Toplam', value: props.tenants.length, icon: 'building', tone: 'accent' },
	{ key: 'active', label: 'Aktif', value: activeCount.value, icon: 'success', tone: 'success' },
	{ key: 'passive', label: 'Pasif', value: props.tenants.length - activeCount.value, icon: 'eye-off', tone: props.tenants.length - activeCount.value ? 'warning' : 'success' },
	{ key: 'risk', label: 'Kredi limiti %90+', value: riskCount.value, icon: 'wallet', tone: riskCount.value ? 'danger' : 'success' },
])

/* ── tablo ── */
const rows = computed(() => props.tenants.map((t) => ({
	...t,
	type_id: t.type?.id ?? '__none__',
	status: t.is_active ? 'active' : 'passive',
	usage: creditUsage(t),
})))
const typeOptions = computed(() => props.types.map((t) => ({ value: t.id, label: t.name })))
const columns = computed(() => [
	{ key: 'name', label: 'İş ortağı', sortable: true, searchValue: (r) => `${r.name} ${r.code} ${r.legal_name ?? ''} ${r.tax_number ?? ''} ${r.email ?? ''}` },
	{
		key: 'type_id', label: 'Tip', sortable: true, sortValue: (r) => r.type?.name ?? '', searchValue: (r) => r.type?.name ?? '',
		filter: { type: 'select', multiple: true, options: [...typeOptions.value, { value: '__none__', label: 'Tipsiz' }] },
	},
	{ key: 'contact', label: 'İletişim', searchValue: (r) => `${r.phone ?? ''} ${r.contact_person ?? ''}` },
	{ key: 'tax_number', label: 'Vergi no', sortable: true },
	{ key: 'usage', label: 'Kredi (kullanılan / limit)', sortable: true, searchable: false, width: '190px' },
	{ key: 'userCount', label: 'Kullanıcı', sortable: true, align: 'right', searchable: false },
	{
		key: 'status', label: 'Durum', sortable: true, searchable: false,
		filter: { type: 'select', options: [{ value: 'active', label: 'Aktif' }, { value: 'passive', label: 'Pasif' }] },
	},
	{ key: 'actions', label: '', align: 'right', width: '48px', searchable: false },
])

// Sunucudan gelen başlangıç filtreleri (?q, ?type_id, ?active)
const search = ref(props.filters.q ?? '')
const tableFilters = ref({
	type_id: props.filters.type_id ? [Number(props.filters.type_id)] : [],
	status: props.filters.active === '' || props.filters.active == null ? '' : (['1', 'true', 1, true].includes(props.filters.active) ? 'active' : 'passive'),
})

const rowActions = (t) => [
	...(canAccess.value ? [{ key: 'access', label: 'Erişim yönet', icon: 'key' }] : []),
	...(canManage.value ? [
		{ key: 'edit', label: 'Düzenle', icon: 'edit' },
		{ key: 'toggle', label: t.is_active ? 'Pasifleştir' : 'Aktifleştir', icon: t.is_active ? 'eye-off' : 'eye' },
		{ divider: true },
		{ key: 'remove', label: 'Sil', icon: 'trash', danger: true, disabled: t.userCount > 0, hint: t.userCount > 0 ? `${t.userCount} bağlı kullanıcı var` : '' },
	] : []),
]
function onAction(key, t) {
	if (key === 'access') router.visit(`/tenants/${t.id}/access`)
	else if (key === 'edit') openEdit(t)
	else if (key === 'toggle') toggle(t)
	else if (key === 'remove') remove(t)
}

/* ── ekle / düzenle ── */
const drawerOpen = ref(false)
const editing = ref(null)
const busy = ref(false)
const errors = ref({})
const ownerExists = ref(false)
const taxCheck = ref(null)
const prefillDocuments = ref([])

/* ── adresler: fatura / sevk / iade (TenantAddress) ── */
const ADDRESS_TYPES = [
	{ key: 'billing', label: 'Fatura adresi', hint: 'Faturalar ve sözleşmelerde kullanılır; Bagisto\'da varsayılan adres.' },
	{ key: 'shipping', label: 'Sevk adresi', hint: 'Siparişlerin gönderileceği adres.' },
	{ key: 'return', label: 'İade adresi', hint: 'İade/geri gönderimlerin yapılacağı adres.' },
]
const ADDRESS_FIELDS = ['contact_name', 'phone', 'address', 'district', 'city', 'postal_code', 'country']
const blankAddress = () => ({ contact_name: '', phone: '', address: '', district: '', city: '', postal_code: '', country: 'TR' })
const toAddress = (a) => Object.fromEntries(ADDRESS_FIELDS.map((f) => [f, a?.[f] ?? (f === 'country' ? 'TR' : '')]))
const sameAddress = (a, b) => ADDRESS_FIELDS.every((f) => String(a[f] ?? '').trim() === String(b[f] ?? '').trim())

// Sunucudaki adreslerden form durumu: sevk/iade, fatura adresiyle birebir aynıysa (ya da hiç yoksa) "aynı" işaretli gelir.
function addressState(addresses = {}) {
	const billing = toAddress(addresses.billing)
	const state = { addresses: { billing }, sameAsBilling: {} }
	for (const type of ['shipping', 'return']) {
		const own = addresses[type] ? toAddress(addresses[type]) : null
		state.sameAsBilling[type] = !own || sameAddress(own, billing)
		state.addresses[type] = own && !state.sameAsBilling[type] ? own : blankAddress()
	}
	return state
}

const blank = () => ({
	code: '', name: '', legal_name: '', tenant_type_id: '',
	owner_name: '', owner_email: '', owner_password: '', owner_password_confirmation: '',
	tax_number: '', tax_office: '', tenant_application_id: null,
	email: '', phone: '', contact_person: '', contact_phone: '',
	...addressState(),
	credit_limit: 0, discount_rate: props.defaultDiscountRate,
	is_active: true, notes: '',
	shipping_agreement_type: '', carrier_id: '', shipping_terms_accepted: false,
})
const form = ref(blank())

const shippingOptions = [
	{ value: 'own', label: 'İş ortağının kendi kargo anlaşması' },
	{ value: 'platform', label: 'Platform kargo anlaşması' },
]
const carrierOptions = computed(() => props.carriers.map((c) => ({ value: c.id, label: c.name })))
const showOwner = computed(() => !editing.value || ownerExists.value)

function reset(extra = {}) {
	errors.value = {}
	taxCheck.value = null
	ownerExists.value = false
	prefillDocuments.value = []
	form.value = { ...blank(), ...extra }
}

function openCreate() {
	editing.value = null
	reset()
	drawerOpen.value = true
}

function openFromApplication(app) {
	editing.value = null
	reset({
		name: app.name ?? '', legal_name: app.legal_name ?? '',
		tax_number: app.tax_number ?? '', tax_office: app.tax_office ?? '',
		email: app.email ?? '', phone: app.phone ?? '',
		contact_person: app.contact_person ?? '', contact_phone: app.contact_phone ?? '',
		...addressState(app.addresses),
		tenant_application_id: app.id,
	})
	prefillDocuments.value = app.documents ?? []
	drawerOpen.value = true
}
onMounted(() => { if (props.prefillApplication) openFromApplication(props.prefillApplication) })

function openEdit(t) {
	if (!canManage.value) return
	editing.value = t
	reset({
		code: t.code, name: t.name, legal_name: t.legal_name ?? '', tenant_type_id: t.type?.id ?? '',
		owner_name: t.owner?.name ?? '', owner_email: t.owner?.email ?? '',
		tax_number: t.tax_number ?? '', tax_office: t.tax_office ?? '',
		email: t.email ?? '', phone: t.phone ?? '', contact_person: t.contact_person ?? '', contact_phone: t.contact_phone ?? '',
		...addressState(t.addresses),
		credit_limit: Number(t.credit_limit ?? 0), discount_rate: Number(t.discount_rate ?? 0),
		is_active: !!t.is_active, notes: t.notes ?? '',
		shipping_agreement_type: t.shipping_agreement_type ?? '', carrier_id: t.carrier?.id ?? '',
		shipping_terms_accepted: !!t.shipping_terms_accepted_at,
	})
	ownerExists.value = !!t.owner
	drawerOpen.value = true
}

function checkTaxNumber() {
	taxCheck.value = isValidTaxNumber(form.value.tax_number)
}
const taxHint = computed(() => (taxCheck.value === true ? '✓ Biçim geçerli (VKN/TCKN)' : ''))
const taxError = computed(() => errors.value.tax_number || (taxCheck.value === false ? 'Geçersiz VKN/TCKN' : ''))

const FIELD_KEYS = [
	'code', 'name', 'legal_name', 'tenant_type_id', 'owner_name', 'owner_email', 'owner_password', 'tax_number', 'tax_office',
	'email', 'phone', 'contact_person', 'contact_phone',
	'credit_limit', 'discount_rate', 'notes', 'shipping_agreement_type', 'carrier_id', 'shipping_terms_accepted',
]

function submit() {
	if (busy.value) return
	busy.value = true
	errors.value = {}
	const { sameAsBilling, ...rest } = form.value
	const payload = {
		...rest,
		addresses: Object.fromEntries(ADDRESS_TYPES.map(({ key }) => [
			key,
			key !== 'billing' && sameAsBilling[key] ? { ...rest.addresses.billing } : { ...rest.addresses[key] },
		])),
		tenant_type_id: form.value.tenant_type_id || null,
		shipping_agreement_type: form.value.shipping_agreement_type || null,
		carrier_id: form.value.shipping_agreement_type === 'own' ? (form.value.carrier_id || null) : null,
	}
	if (editing.value) {
		delete payload.tenant_application_id
		if (!ownerExists.value) {
			// Bu iş ortağının yetkili hesabı yok — alanları hiç gönderme.
			delete payload.owner_name
			delete payload.owner_email
			delete payload.owner_password
			delete payload.owner_password_confirmation
		} else if (!payload.owner_password) {
			// Şifre boş bırakıldıysa mevcut şifreye dokunma.
			delete payload.owner_password
			delete payload.owner_password_confirmation
		}
	}
	const title = editing.value ? 'İş ortağı güncellendi' : 'İş ortağı eklendi'
	const opts = {
		preserveScroll: true,
		preserveState: true,
		onSuccess: () => {
			drawerOpen.value = false
			showToast?.({ type: 'success', title, message: payload.name })
		},
		// Alan hataları panelde; toast yalnızca alana bağlanamayanlar için
		onError: (errs) => {
			errors.value = errs
			const other = Object.entries(errs).find(([k]) => !FIELD_KEYS.includes(k) && !k.startsWith('addresses.'))
			if (other) showToast?.({ type: 'error', title: 'Kaydedilemedi', message: other[1] })
		},
		onFinish: () => { busy.value = false },
	}
	if (editing.value) router.put(`/tenants/${editing.value.id}`, payload, opts)
	else router.post('/tenants', payload, opts)
}

function toggle(t) {
	router.post(`/tenants/${t.id}/toggle`, {}, {
		preserveScroll: true,
		preserveState: true,
		onSuccess: () => showToast?.({ type: 'success', title: t.is_active ? 'İş ortağı pasifleştirildi' : 'İş ortağı aktifleştirildi', message: t.name }),
		onError: (errs) => showToast?.({ type: 'error', title: 'İşlem başarısız', message: Object.values(errs)[0] || 'Sunucu hatası.' }),
	})
}

async function remove(t) {
	const ok = await confirm.danger('İş ortağı silinsin mi?', `“${t.name}” silinecek. Kayıt arşivlenir (soft delete), gerekirse geri alınabilir.`)
	if (!ok) return
	router.delete(`/tenants/${t.id}`, {
		preserveScroll: true,
		preserveState: true,
		onSuccess: () => showToast?.({ type: 'warning', title: 'İş ortağı silindi', message: t.name }),
		onError: (errs) => showToast?.({ type: 'error', title: 'Silinemedi', message: Object.values(errs)[0] || 'Sunucu hatası.' }),
	})
}
</script>

<template>
	<Head title="İş Ortakları" />

	<OzPageHeader title="İş Ortakları" :subtitle="`${tenants.length} iş ortağı · ${activeCount} aktif`">
		<template v-if="canManage" #actions>
			<OzButton icon="file" @click="router.visit('/tenants/applications')">Başvurular</OzButton>
			<OzButton variant="primary" icon="plus" @click="openCreate">Yeni iş ortağı</OzButton>
		</template>
	</OzPageHeader>
	<OzModuleTabs />

	<OzStatStrip :items="stats" />

	<OzCard title="İş ortağı listesi" :meta="`${tenants.length} kayıt`" class="block">
		<OzTable
			v-model:search="search"
			v-model:filters="tableFilters"
			:columns="columns"
			:rows="rows"
			:page-size="20"
			:default-sort="{ key: 'name', dir: 'asc' }"
			searchable
			search-placeholder="İsim, kod, vergi no, e-posta ara…"
			min-width="980px"
			class="tenants"
			:class="{ clickable: canManage }"
			@row-click="openEdit"
		>
			<template #cell-name="{ row }">
				<div class="who">
					<OzAvatar :name="row.name" :size="30" tone="auto" shape="square" :status="row.is_active ? '' : 'offline'" />
					<div class="who-text">
						<b>{{ row.name }}</b>
						<span class="mono">{{ row.code }}</span>
					</div>
				</div>
			</template>
			<template #cell-type_id="{ row }">
				<OzPill v-if="row.type" tone="info">{{ row.type.name }}</OzPill>
				<span v-else class="dim">—</span>
			</template>
			<template #cell-contact="{ row }">
				<div class="stack">
					<span v-if="row.email">{{ row.email }}</span>
					<span v-if="row.phone" class="dim">{{ row.phone }}</span>
					<span v-if="!row.email && !row.phone" class="dim">—</span>
				</div>
			</template>
			<template #cell-tax_number="{ value }">
				<span v-if="value" class="mono">{{ value }}</span>
				<span v-else class="dim">—</span>
			</template>
			<template #cell-usage="{ row }">
				<OzProgress v-if="row.usage !== null" :label="`${fmtMoney(row.current_balance)} / ${fmtMoney(row.credit_limit)}`" :value="row.usage" :warn-at="70" class="credit" />
				<span v-else class="dim">Limit tanımsız</span>
			</template>
			<template #cell-userCount="{ value }"><span class="num">{{ value }}</span></template>
			<template #cell-status="{ row }">
				<OzPill :tone="row.is_active ? 'success' : 'neutral'">{{ row.is_active ? 'Aktif' : 'Pasif' }}</OzPill>
			</template>
			<template #cell-actions="{ row }">
				<div v-if="canManage || canAccess" @click.stop>
					<OzDropdown :items="rowActions(row)" :label="`${row.name} işlemleri`" @select="(k) => onAction(k, row)" />
				</div>
			</template>
			<template #empty="{ filtered, clear }">
				<OzEmptyState v-if="filtered" icon="filter" title="Eşleşen iş ortağı yok" description="Aramayı değiştirin ya da bazı filtreleri kaldırın." compact>
					<template #actions><OzButton size="sm" icon="x" @click="clear">Filtreleri temizle</OzButton></template>
				</OzEmptyState>
				<OzEmptyState v-else icon="building" title="Henüz iş ortağı yok" compact>
					<template v-if="canManage" #actions><OzButton size="sm" variant="primary" icon="plus" @click="openCreate">Yeni iş ortağı</OzButton></template>
				</OzEmptyState>
			</template>
		</OzTable>
	</OzCard>

	<OzDrawer
		v-model:open="drawerOpen"
		:title="editing ? `“${editing.name}” düzenle` : 'Yeni iş ortağı'"
		:subtitle="editing ? editing.code : (form.tenant_application_id ? 'Onaylı başvurudan oluşturuluyor' : 'Yetkili hesabıyla birlikte oluşturulur')"
		:icon="editing ? 'edit' : 'building'"
		size="lg"
		:persistent="busy"
		initial-focus="auto"
	>
		<form class="form" @submit.prevent="submit">
			<OzAlert v-if="prefillDocuments.length" tone="info" title="Başvuru belgeleri">
				Unvan / VKN eşleşmesini onaylamadan önce kontrol edin:
				<span class="docs">
					<a v-for="d in prefillDocuments" :key="d.id" :href="d.url" target="_blank" rel="noopener" class="doc"><OzIcon name="file" :size="12" />{{ d.name }}</a>
				</span>
			</OzAlert>

			<div class="grid-2">
				<OzField label="İş ortağı kodu" required :error="errors.code" hint="Büyük harf, rakam, - ve _">
					<OzInput v-model="form.code" class="mono" placeholder="BAYI001" maxlength="32" autocomplete="off" />
				</OzField>
				<OzField label="İş ortağı tipi" :error="errors.tenant_type_id">
					<OzSelect v-model="form.tenant_type_id" :options="[{ value: '', label: 'Seçilmedi' }, ...typeOptions]" />
				</OzField>
				<OzField label="Ad" required :error="errors.name">
					<OzInput v-model="form.name" placeholder="Örn. ABC Tekstil" maxlength="191" />
				</OzField>
				<OzField label="Yasal unvan" :error="errors.legal_name">
					<OzInput v-model="form.legal_name" placeholder="ABC Tekstil San. Tic. Ltd. Şti." maxlength="191" />
				</OzField>
			</div>

			<div class="section">Yetkili giriş hesabı</div>
			<template v-if="showOwner">
				<p class="section-hint">{{ editing ? 'Birincil yetkili hesap. Şifreyi yalnızca değiştirmek istiyorsanız doldurun.' : 'İş ortağının birincil yetkili hesabı; bu bilgilerle giriş yapar.' }}</p>
				<div class="grid-2">
					<OzField label="Yetkili adı" required :error="errors.owner_name">
						<OzInput v-model="form.owner_name" icon="user" placeholder="Ahmet Yılmaz" autocomplete="off" />
					</OzField>
					<OzField label="Giriş e-postası" required :error="errors.owner_email">
						<OzInput v-model="form.owner_email" type="email" icon="mail" placeholder="yetkili@firma.com" autocomplete="off" />
					</OzField>
					<OzField label="Şifre" :required="!editing" :error="errors.owner_password" :hint="editing ? 'Boş bırakılırsa değişmez' : 'En az 8 karakter'">
						<OzInput v-model="form.owner_password" type="password" revealable autocomplete="new-password" />
					</OzField>
					<OzField label="Şifre tekrar" :required="!editing">
						<OzInput v-model="form.owner_password_confirmation" type="password" revealable autocomplete="new-password" />
					</OzField>
				</div>
			</template>
			<p v-else class="section-hint">Bu iş ortağı için yetkili hesap bulunamadı.</p>

			<div class="section">Vergi ve yasal</div>
			<div class="grid-2">
				<OzField label="Vergi no" :error="taxError" :hint="taxHint">
					<div class="inline">
						<OzInput v-model="form.tax_number" class="mono" placeholder="1234567890" maxlength="32" @update:model-value="taxCheck = null" />
						<OzButton icon="check" @click="checkTaxNumber">Sorgula</OzButton>
					</div>
				</OzField>
				<OzField label="Vergi dairesi" :error="errors.tax_office">
					<OzInput v-model="form.tax_office" placeholder="Kadıköy" maxlength="191" />
				</OzField>
			</div>

			<div class="section">İletişim</div>
			<div class="grid-2">
				<OzField label="E-posta" :error="errors.email">
					<OzInput v-model="form.email" type="email" icon="mail" placeholder="info@firma.com" maxlength="191" />
				</OzField>
				<OzField label="Telefon" :error="errors.phone">
					<OzInput v-model="form.phone" type="tel" icon="phone" placeholder="+90 212 …" maxlength="32" />
				</OzField>
				<OzField label="Yetkili kişi" :error="errors.contact_person">
					<OzInput v-model="form.contact_person" placeholder="Ahmet Yılmaz" maxlength="191" />
				</OzField>
				<OzField label="Yetkili telefonu" :error="errors.contact_phone">
					<OzInput v-model="form.contact_phone" type="tel" icon="phone" placeholder="+90 532 …" maxlength="32" />
				</OzField>
			</div>

			<div class="section">Adresler</div>
			<p class="section-hint">Bagisto'da bayi hesabına salt okunur olarak gider; bayi mağazadan değiştiremez.</p>
			<div v-for="type in ADDRESS_TYPES" :key="type.key" class="address">
				<div class="address-head">
					<b>{{ type.label }}</b>
					<span>{{ type.hint }}</span>
				</div>
				<OzCheckbox v-if="type.key !== 'billing'" v-model="form.sameAsBilling[type.key]" label="Fatura adresiyle aynı" />
				<template v-if="type.key === 'billing' || !form.sameAsBilling[type.key]">
					<OzField label="Adres" :error="errors[`addresses.${type.key}.address`]">
						<OzTextarea v-model="form.addresses[type.key].address" :rows="2" :maxlength="1000" placeholder="Cadde, sokak, bina no…" />
					</OzField>
					<div class="grid-3">
						<OzField label="İl" :error="errors[`addresses.${type.key}.city`]"><OzInput v-model="form.addresses[type.key].city" placeholder="İstanbul" maxlength="100" /></OzField>
						<OzField label="İlçe" :error="errors[`addresses.${type.key}.district`]"><OzInput v-model="form.addresses[type.key].district" placeholder="Kadıköy" maxlength="100" /></OzField>
						<OzField label="Posta kodu" :error="errors[`addresses.${type.key}.postal_code`]"><OzInput v-model="form.addresses[type.key].postal_code" placeholder="34000" maxlength="16" /></OzField>
					</div>
					<div class="grid-2">
						<OzField label="Teslim alan / yetkili" :error="errors[`addresses.${type.key}.contact_name`]" hint="Boşsa yetkili hesabın adı kullanılır">
							<OzInput v-model="form.addresses[type.key].contact_name" placeholder="Ahmet Yılmaz" maxlength="191" />
						</OzField>
						<OzField label="Telefon" :error="errors[`addresses.${type.key}.phone`]">
							<OzInput v-model="form.addresses[type.key].phone" type="tel" icon="phone" placeholder="+90 532 …" maxlength="32" />
						</OzField>
					</div>
				</template>
			</div>

			<div class="section">Ticari koşullar</div>
			<div class="grid-2">
				<OzField label="Kredi limiti" :error="errors.credit_limit">
					<OzInput v-model="form.credit_limit" type="number" min="0" step="0.01" suffix="₺" />
				</OzField>
				<OzField label="İskonto" :error="errors.discount_rate">
					<OzInput v-model="form.discount_rate" type="number" min="0" max="100" step="0.01" suffix="%" />
				</OzField>
			</div>

			<div class="section">Kargo anlaşması</div>
			<p class="section-hint">Bu alan boşken Bagisto senkronu iş ortağını aktif edemez; iş ortağı Bagisto'da oluşmaz / güncellenmez.</p>
			<div class="grid-2">
				<OzField label="Anlaşma tipi" :error="errors.shipping_agreement_type">
					<OzSelect v-model="form.shipping_agreement_type" :options="[{ value: '', label: 'Seçilmedi' }, ...shippingOptions]" />
				</OzField>
				<OzField v-if="form.shipping_agreement_type === 'own'" label="Kargo firması" required :error="errors.carrier_id">
					<OzSelect v-model="form.carrier_id" :options="carrierOptions" placeholder="Seçin" />
				</OzField>
			</div>
			<OzField v-if="form.shipping_agreement_type === 'platform'" :error="errors.shipping_terms_accepted">
				<OzCheckbox v-model="form.shipping_terms_accepted" label="Gönderen bilgisi platforma ait olacaktır, şartları kabul ediyorum" />
			</OzField>

			<div class="section">Diğer</div>
			<OzField label="Notlar" :error="errors.notes">
				<OzTextarea v-model="form.notes" :rows="2" :maxlength="2000" placeholder="İş ortağı hakkında özel notlar…" />
			</OzField>
			<OzSwitch v-model="form.is_active" label="Aktif" description="Pasif iş ortağının kullanıcıları sisteme giriş yapamaz" />
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
.tenants.clickable :deep(tbody tr) { cursor: pointer; }
.who { display: flex; align-items: center; gap: 10px; min-width: 0; }
.who-text { display: flex; flex-direction: column; gap: 1px; min-width: 0; }
.who-text b { font-size: 13px; color: var(--oz-ink-soft); white-space: nowrap; }
.who-text span { font-size: 11px; color: var(--oz-muted); }
.stack { display: flex; flex-direction: column; gap: 1px; font-size: 12.5px; white-space: nowrap; }
.mono { font-family: ui-monospace, 'SF Mono', Menlo, Consolas, monospace; font-size: 12px; }
.dim { color: var(--oz-muted-2); }
.num { font-variant-numeric: tabular-nums; font-weight: 600; color: var(--oz-ink-soft); }
.credit { min-width: 160px; }
.credit :deep(.name) { font-size: 11.5px; font-variant-numeric: tabular-nums; color: var(--oz-muted); }

.form { display: flex; flex-direction: column; gap: 14px; }
.grid-2 { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
.grid-3 { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 14px; }
.section { margin-top: 4px; padding-top: 12px; border-top: 1px solid var(--oz-border-soft); font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: var(--oz-muted); }
.section-hint { margin: -6px 0 0; font-size: 12px; color: var(--oz-muted); }
.inline { display: flex; gap: 8px; }
.inline > :first-child { flex: 1; min-width: 0; }
.address { display: flex; flex-direction: column; gap: 12px; padding: 14px; border: 1px solid var(--oz-border-soft); border-radius: 10px; }
.address-head { display: flex; flex-direction: column; gap: 2px; }
.address-head b { font-size: 13px; color: var(--oz-ink-soft); }
.address-head span { font-size: 12px; color: var(--oz-muted); }
.docs { display: flex; flex-wrap: wrap; gap: 6px 12px; margin-top: 6px; }
.doc { display: inline-flex; align-items: center; gap: 4px; font-weight: 600; color: inherit; }

@media (max-width: 560px) { .grid-2, .grid-3 { grid-template-columns: minmax(0, 1fr); } }
</style>
