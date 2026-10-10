<script setup>
import { ref, computed, inject, watch, onBeforeUnmount } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import {
	OzPageHeader, OzModuleTabs, OzStatStrip, OzCard, OzTable, OzPill, OzDropdown,
	OzButton, OzModal, OzIcon, useOzConfirm,
} from '@/Components/Oz'
import MarketplaceConnectDrawer from '@/Components/MarketplaceConnectDrawer.vue'
import MarketplaceCategoryPickerModal from '@/Components/MarketplaceCategoryPickerModal.vue'
import CategoryFormDrawer from '@/Components/CategoryFormDrawer.vue'
import CategoryAttributesModal from '@/Components/CategoryAttributesModal.vue'
import { useCan } from '@/composables/useCan'
import { ozIconForCategory } from '@/categoryIcons.js'

defineOptions({ layout: AppLayout })

const props = defineProps({
	categories: { type: Array, default: () => [] },
	marketplaces: { type: Array, default: () => [] },
	productAttributes: { type: Array, default: () => [] },
	defaultVatRate: { type: Number, default: 10 },
})

const showToast = inject('showToast')
const confirm = useOzConfirm()
const { can } = useCan()
const canManage = computed(() => can('category.manage'))
const canCreate = computed(() => can('category.create'))
const canEdit = computed(() => can('category.edit'))
// Satır menüsü: Düzenle → category.edit, Özellikler / Sil → category.manage
const hasRowActions = computed(() => canEdit.value || canManage.value)

/* ── Veri ── */
const categories = ref(props.categories.map((c) => ({ ...c })))
const marketplaces = ref(props.marketplaces.map((m) => ({ ...m })))

// Backend'den gelen props yenilenince local state'i tazele.
watch(
	() => props.categories,
	(list) => { categories.value = list.map((c) => ({ ...c })) },
	{ deep: true },
)
watch(
	() => props.marketplaces,
	(list) => { marketplaces.value = list.map((m) => ({ ...m })) },
	{ deep: true },
)

/* ── Kategori formu (drawer) ── */
const formDrawerOpen = ref(false)
const editingCategory = ref(null)
const formBusy = ref(false)
const formErrors = ref({})

watch(formDrawerOpen, (open) => {
	if (!open) {
		setTimeout(() => {
			editingCategory.value = null
			formErrors.value = {}
		}, 250)
	}
})

/* ── Bağlantı drawer state ── */
const connectDrawerOpen = ref(false)
const connectingMarketplace = ref(null)
const connectingCategory = ref(null)

/* ── Özellik seçimi modal state (global özellikler, Ürün Ayarları → Özellikler) ── */
const attributesModalOpen = ref(false)
const attributesCategoryId = ref(null)
const attributesCategory = computed(() => categories.value.find((c) => c.id === attributesCategoryId.value) ?? null)

function openAttributesModal(cat) {
	attributesCategoryId.value = cat.id
	attributesModalOpen.value = true
}

/* ── Eşleştirme modal state ── */
const mapperOpen = ref(false)
const mappingCategory = ref(null)
const mappingMarketplace = ref(null)
const mappingInitialPath = ref('')
const mappingCurrent = ref(null) // edit modunda mevcut mapping objesi

watch(connectDrawerOpen, (open) => {
	document.body.style.overflow = open ? 'hidden' : ''
})
onBeforeUnmount(() => { document.body.style.overflow = '' })

/* ── İstatistikler ── */
const stats = computed(() => {
	const totalCells = categories.value.length * marketplaces.value.length
	const mappedCells = categories.value.reduce((acc, c) => {
		return acc + marketplaces.value.filter((mp) => c.marketplaces[mp.key]?.mapped).length
	}, 0)
	return {
		total: categories.value.length,
		// productCount backend'de henüz hesaplanmıyor (hep 0) — onun yerine otomatik durumdan aktif sayısı
		active: categories.value.filter((c) => c.status === 'active').length,
		activeMarketplaces: marketplaces.value.filter((mp) => mp.connected).length,
		mappingRate: totalCells > 0 ? Math.round((mappedCells / totalCells) * 100) : 0,
	}
})

const summaryItems = computed(() => [
	{ key: 'total', label: 'kategori', value: stats.value.total, icon: 'layers', tone: 'accent' },
	{ key: 'active', label: 'aktif', value: stats.value.active, icon: 'check', tone: 'success' },
	{ key: 'mp', label: 'pazaryeri bağlı', value: `${stats.value.activeMarketplaces} / ${marketplaces.value.length}`, icon: 'store', tone: 'info' },
	{ key: 'map', label: 'eşleştirme', value: `%${stats.value.mappingRate}`, icon: 'link', tone: 'warning' },
])

/* ── Toplu seçim (OzTable selectable; toplu işlem çubuğu tablonun #bulk slot'unda) ── */
const selected = ref([])
const bulkBusy = ref(false)

async function bulkDelete() {
	const ids = [...selected.value]
	if (!ids.length) return
	const ok = await confirm.danger('Kategorileri Sil', `${ids.length} kategori kalıcı olarak silinecek. İçlerindeki ürünler ve pazaryeri eşleştirmeleri de etkilenir.`)
	if (!ok) return

	bulkBusy.value = true
	router.post('/products/categories/bulk-destroy', { ids }, {
		preserveScroll: true,
		preserveState: false,
		onSuccess: () => {
			showToast?.({ type: 'warning', title: 'Kategoriler Silindi', message: `${ids.length} kategori kaldırıldı.` })
			selected.value = []
		},
		onError: (errs) => {
			showToast?.({ type: 'error', title: 'Silme Başarısız', message: Object.values(errs)[0] || 'Sunucu hatası.' })
		},
		onFinish: () => { bulkBusy.value = false },
	})
}

/* ── Tablo (OzTable tree: ağaç, arama, filtre, kök grubuyla sayfalama) ── */
// Her kategorinin ana (kök) kategorisi — "Ana kategori" filtresi bununla eşleşir (seçilen ağacın tamamı)
const rootNameOf = computed(() => {
	const byId = new Map(categories.value.map((c) => [c.id, c]))
	const out = new Map()
	for (const c of categories.value) {
		let node = c
		const seen = new Set()
		while (node.parent_id && byId.has(node.parent_id) && !seen.has(node.id)) {
			seen.add(node.id)
			node = byId.get(node.parent_id)
		}
		out.set(c.id, node.name)
	}
	return out
})

// Özellik sayısı: kategorinin kendi seçimi + üst kategorilerinden miras (tekrarsız)
const attributeCounts = computed(() => {
	const byId = new Map(categories.value.map((c) => [c.id, c]))
	const out = new Map()
	for (const c of categories.value) {
		const own = new Set((c.productAttributes ?? []).map((a) => a.id))
		const inherited = new Set()
		const seen = new Set([c.id])
		for (let p = byId.get(c.parent_id); p && !seen.has(p.id); p = byId.get(p.parent_id)) {
			seen.add(p.id)
			for (const a of p.productAttributes ?? []) if (!own.has(a.id)) inherited.add(a.id)
		}
		out.set(c.id, { own: own.size, inherited: inherited.size, total: own.size + inherited.size })
	}
	return out
})

// Geçerli KDV oranı: kendi oranı, yoksa en yakın üst kategorinin, o da yoksa varsayılan
const vatOf = computed(() => {
	const byId = new Map(categories.value.map((c) => [c.id, c]))
	const out = new Map()
	for (const c of categories.value) {
		let rate = null
		let inherited = false
		const seen = new Set()
		for (let n = c; n && !seen.has(n.id); n = byId.get(n.parent_id)) {
			seen.add(n.id)
			if (n.vat_rate !== null && n.vat_rate !== undefined) { rate = n.vat_rate; inherited = n.id !== c.id; break }
		}
		out.set(c.id, rate === null ? { rate: props.defaultVatRate, inherited: true, isDefault: true } : { rate, inherited, isDefault: false })
	}
	return out
})

const statusLabel = (c) => (c.status === 'active' ? 'Aktif' : c.manuallyPassive ? 'Pasif (elle)' : 'Pasif · ürün yok')

const columns = computed(() => [
	{
		key: 'name', label: 'Kategori', sortable: true,
		searchValue: (c) => `${c.name} ${c.slug}`,
		filterValue: (c) => rootNameOf.value.get(c.id),
		filter: { type: 'select', label: 'Ana kategori', placeholder: 'Tüm ana kategoriler' },
	},
	{ key: 'productCount', label: 'Ürün', sortable: true, searchable: false, width: '90px' },
	{ key: 'vat', label: 'KDV', sortable: true, searchable: false, width: '90px', sortValue: (c) => vatOf.value.get(c.id)?.rate ?? 0 },
	{ key: 'attrs', label: 'Özellikler', sortable: true, searchable: false, width: '130px', sortValue: (c) => attributeCounts.value.get(c.id)?.total ?? 0 },
	{ key: 'marketplaces', label: 'Pazaryeri Entegrasyonları', searchable: false },
	{
		key: 'status', label: 'Durum', sortable: true, searchable: false, width: '150px',
		sortValue: statusLabel, filterValue: statusLabel,
		filter: { type: 'select', label: 'Durum', options: ['Aktif', 'Pasif · ürün yok', 'Pasif (elle)'] },
	},
	{ key: 'updatedAt', label: 'Güncelleme', sortable: true, searchable: false, width: '120px' },
	...(hasRowActions.value ? [{ key: 'actions', label: '', align: 'right', width: '48px', searchable: false }] : []),
])

const rowActions = () => [
	...(canEdit.value ? [{ key: 'edit', label: 'Düzenle', icon: 'edit' }] : []),
	...(canManage.value ? [
		{ key: 'attributes', label: 'Özellikler', icon: 'tag' },
		{ divider: true },
		{ key: 'remove', label: 'Sil', icon: 'trash', danger: true },
	] : []),
]
function onRowAction(key, cat) {
	if (key === 'edit') editCategory(cat)
	else if (key === 'attributes') openAttributesModal(cat)
	else if (key === 'remove') confirmDelete(cat)
}

/* ── Pazaryeri logo durumu ── */
function mpChipClass(cat, mp) {
	if (!mp.connected) return 'mp-disconnected'
	if (cat.marketplaces[mp.key]?.mapped) return 'mp-mapped'
	return 'mp-unmapped'
}

function mpChipStyle(cat, mp) {
	if (mp.connected && cat.marketplaces[mp.key]?.mapped) {
		return { background: mp.color, color: '#fff', borderColor: mp.color }
	}
	return {}
}

function mpTooltip(cat, mp) {
	if (!mp.connected) return `${mp.name} — Bağlantı yok`
	if (cat.marketplaces[mp.key]?.mapped) {
		const m = cat.marketplaces[mp.key]
		return `${mp.name} — Eşleştirildi · ${m.syncedProducts} ürün senkron`
	}
	return `${mp.name} — Eşleştirilmemiş`
}

/* ── Pazaryeri tıklama: bağlı değilse bağlantı drawer, eşleşmişse durum modalı, yoksa eşleştirme onayı ── */
const mpStatusOpen = ref(false)
const mpStatusCategory = ref(null)
const mpStatusMarketplace = ref(null)
const mpStatusMapping = computed(() => mpStatusCategory.value && mpStatusMarketplace.value
	? mpStatusCategory.value.marketplaces[mpStatusMarketplace.value.key]
	: null)

async function openMarketplaceCheck(cat, mp) {
	// Senaryo 1: Pazaryeri bağlı değil → bağlantı drawer'ı aç
	if (!mp.connected) {
		openConnectDrawer(cat, mp)
		return
	}

	const mapping = cat.marketplaces[mp.key]

	// Senaryo 2: Eşleştirme var → durum modalı (düzenle / kaldır / kapat)
	if (mapping?.mapped) {
		mpStatusCategory.value = cat
		mpStatusMarketplace.value = mp
		mpStatusOpen.value = true
		return
	}

	// Senaryo 3: Eşleştirme yok
	const ok = await confirm({
		tone: 'warning',
		title: `${mp.name}'e Eşleştir`,
		message: `“${cat.name}” kategorisi ${mp.name}'te henüz eşleştirilmemiş. Şimdi eşleştirip ${cat.productCount} ürünü bu pazaryerine yollayabilirsin.`,
		confirmText: 'Şimdi Eşleştir',
	})
	if (ok) openMappingEditor(cat, mp, null)
}

function mpStatusEdit() {
	mpStatusOpen.value = false
	openMappingEditor(mpStatusCategory.value, mpStatusMarketplace.value, mpStatusMapping.value)
}
async function mpStatusUnmap() {
	mpStatusOpen.value = false
	await confirmUnmap(mpStatusCategory.value, mpStatusMarketplace.value)
}

function openMappingEditor(cat, mp, currentMapping) {
	mappingCategory.value = cat
	mappingMarketplace.value = mp
	mappingCurrent.value = currentMapping ?? null
	mappingInitialPath.value = currentMapping?.categoryPath ?? ''
	mapperOpen.value = true
}

function handleMapperSubmit(payload) {
	const cat = categories.value.find((c) => c.id === payload.categoryId)
	const mp = mappingMarketplace.value
	if (!cat || !mp) return

	const wasEmpty = !cat.marketplaces[mp.key]?.mapped

	router.post(
		`/products/categories/${cat.id}/marketplaces/${mp.id}`,
		{ category_path: payload.path },
		{
			preserveScroll: true,
			preserveState: true,
			onSuccess: () => {
				mapperOpen.value = false
				showToast?.({
					type: 'success',
					title: wasEmpty ? 'Eşleştirildi' : 'Güncellendi',
					message: `${cat.name} → ${mp.name} ${wasEmpty ? 'eşleştirildi' : 'yolu güncellendi'}.`,
				})
			},
			onError: (errs) => {
				showToast?.({ type: 'error', title: 'Eşleştirme Başarısız', message: Object.values(errs)[0] || 'Sunucu hatası.' })
			},
		},
	)
}

function openConnectDrawer(cat, mp) {
	connectingCategory.value = cat
	connectingMarketplace.value = mp
	connectDrawerOpen.value = true
}

async function handleConnectSubmit(payload) {
	const mp = marketplaces.value.find((m) => m.key === payload.marketplaceKey)
	if (!mp) return

	router.post(
		`/products/marketplaces/${mp.id}/connect`,
		{},
		{
			preserveScroll: true,
			preserveState: true,
			onSuccess: () => {
				showToast?.({
					type: 'success',
					title: `${mp.name} Bağlandı`,
					message: `${payload.syncScopes?.length ?? 0} veri tipi senkronize edilecek.`,
				})
				connectDrawerOpen.value = false

				if (payload.autoMapAfterConnect && payload.categoryId) {
					const cat = categories.value.find((c) => c.id === payload.categoryId)
					if (cat) setTimeout(() => openMappingEditor(cat, mp, null), 320)
				}
			},
			onError: (errs) => {
				showToast?.({ type: 'error', title: 'Bağlantı Başarısız', message: Object.values(errs)[0] || 'Sunucu hatası.' })
			},
		},
	)
}

async function confirmUnmap(cat, mp) {
	const ok = await confirm.danger('Eşleştirmeyi Kaldır', `“${cat.name}” kategorisinin ${mp.name} eşleştirmesi kaldırılacak. Bu pazaryerindeki ürün senkronizasyonu duracak.`, { confirmText: 'Kaldır' })
	if (!ok) return

	router.delete(`/products/categories/${cat.id}/marketplaces/${mp.id}`, {
		preserveScroll: true,
		preserveState: true,
		onSuccess: () => {
			showToast?.({ type: 'warning', title: 'Eşleştirme Kaldırıldı', message: `${cat.name} → ${mp.name} ayrıldı.` })
		},
		onError: (errs) => {
			showToast?.({ type: 'error', title: 'Kaldırma Başarısız', message: Object.values(errs)[0] || 'Sunucu hatası.' })
		},
	})
}

/* ── CRUD aksiyonları ── */
function openNewCategoryDrawer() {
	editingCategory.value = null
	formErrors.value = {}
	formDrawerOpen.value = true
}

function editCategory(cat) {
	editingCategory.value = { ...cat }
	formErrors.value = {}
	formDrawerOpen.value = true
}

function handleFormSubmit({ mode, id, payload }) {
	if (formBusy.value) return
	formBusy.value = true
	formErrors.value = {}

	const opts = {
		preserveScroll: true,
		preserveState: true,
		onSuccess: () => {
			formDrawerOpen.value = false
			showToast?.({
				type: 'success',
				title: mode === 'edit' ? 'Kategori Güncellendi' : 'Kategori Eklendi',
				message: payload.name,
			})
		},
		onError: (errs) => {
			formErrors.value = errs
			const first = Object.values(errs)[0]
			showToast?.({ type: 'error', title: 'Kayıt Başarısız', message: first || 'Doğrulama hatası.' })
		},
		onFinish: () => { formBusy.value = false },
	}

	if (mode === 'edit') {
		router.put(`/products/categories/${id}`, payload, opts)
	} else {
		router.post('/products/categories', payload, opts)
	}
}

async function confirmDelete(cat) {
	const mappedCount = marketplaces.value.filter((mp) => cat.marketplaces[mp.key]?.mapped).length
	const ok = await confirm.danger('Kategoriyi Sil', `“${cat.name}” silinecek. Bu kategoride ${cat.productCount} ürün ve ${mappedCount} pazaryeri eşleştirmesi var.`)
	if (!ok) return

	router.delete(`/products/categories/${cat.id}`, {
		preserveScroll: true,
		preserveState: true,
		onSuccess: () => {
			showToast?.({ type: 'warning', title: 'Kategori Silindi', message: `${cat.name} kalıcı olarak silindi.` })
		},
		onError: (errs) => {
			showToast?.({ type: 'error', title: 'Silme Başarısız', message: Object.values(errs)[0] || 'Sunucu hatası.' })
		},
	})
}
</script>

<template>
	<Head title="Kategoriler" />

	<OzPageHeader title="Kategoriler" subtitle="Ürün kategorilerini yönet, pazaryeri entegrasyonlarıyla eşleştir">
		<template #actions>
			<OzButton v-if="canCreate" variant="primary" icon="plus" @click="openNewCategoryDrawer">Yeni Kategori</OzButton>
		</template>
	</OzPageHeader>
	<OzModuleTabs />

	<OzStatStrip :items="summaryItems" label="Kategori özeti" />

	<OzCard title="Kategori Listesi">
		<OzTable
			v-model:selected="selected"
			:columns="columns"
			:rows="categories"
			tree
			parent-key="parent_id"
			:page-size="8"
			:selectable="canManage"
			item-label="kategori"
			searchable
			search-placeholder="Kategori adı ya da slug ara…"
			min-width="900px"
			empty-title="Henüz kategori yok"
		>
			<template #bulk>
				<OzButton variant="danger" size="sm" icon="trash" :loading="bulkBusy" @click="bulkDelete">Seçilenleri Sil ({{ selected.length }})</OzButton>
			</template>

			<template #cell-name="{ row: cat, depth, childCount }">
				<div class="cat-cell">
					<div class="cat-icon" :class="{ 'is-sub': depth > 0 }"><OzIcon :name="ozIconForCategory(cat.icon)" :size="depth > 0 ? 15 : 17" /></div>
					<div class="cat-info">
						<span class="cat-name" :class="{ 'is-sub': depth > 0 }">{{ cat.name }}</span>
						<span v-if="childCount" class="cat-parent">{{ childCount }} alt kategori</span>
					</div>
				</div>
			</template>
			<template #cell-productCount="{ value }"><span class="product-count" :class="{ 'is-zero': !value }">{{ value }}</span></template>
			<template #cell-marketplaces="{ row: cat }">
				<div class="mp-logos">
					<button
						v-for="mp in marketplaces"
						:key="mp.key"
						class="mp-chip"
						:class="mpChipClass(cat, mp)"
						:style="mpChipStyle(cat, mp)"
						:disabled="!canManage"
						:title="canManage ? mpTooltip(cat, mp) : `${mp.name} — görüntüleme yetkiniz var, düzenlemek için yetkiniz yok`"
						@click="canManage && openMarketplaceCheck(cat, mp)"
					>
						{{ mp.logoText }}
						<span v-if="cat.marketplaces[mp.key]?.mapped && mp.connected" class="mp-dot mp-dot-success"></span>
						<span v-else-if="!mp.connected" class="mp-dot mp-dot-error"></span>
					</button>
				</div>
			</template>
			<template #cell-status="{ row: cat }">
				<OzPill
					:tone="cat.status === 'active' ? 'success' : 'neutral'"
					:title="cat.status === 'active' ? null : cat.manuallyPassive ? 'Elle pasife alındı — ürün gelse de pasif kalır' : 'Kendisinde ve alt kategorilerinde yayında ürün yok — ürün yayına alınınca otomatik aktif olur'"
				>{{ statusLabel(cat) }}</OzPill>
			</template>
			<template #cell-vat="{ row: cat }">
				<span :class="{ dim: vatOf.get(cat.id)?.inherited }" :title="vatOf.get(cat.id)?.isDefault ? 'Varsayılan oran' : vatOf.get(cat.id)?.inherited ? 'Üst kategoriden' : 'Bu kategoride tanımlı'">%{{ vatOf.get(cat.id)?.rate }}</span>
			</template>
			<template #cell-attrs="{ row: cat }">
				<button v-if="canManage" type="button" class="attr-count" :title="`${cat.name} özelliklerini düzenle`" @click.stop="openAttributesModal(cat)">
					<OzIcon name="sliders" :size="13" />
					<span v-if="attributeCounts.get(cat.id)?.total">{{ attributeCounts.get(cat.id).total }}</span>
					<span v-else class="dim">Ekle</span>
					<span v-if="attributeCounts.get(cat.id)?.inherited" class="dim">({{ attributeCounts.get(cat.id).inherited }} miras)</span>
				</button>
				<span v-else-if="attributeCounts.get(cat.id)?.total">{{ attributeCounts.get(cat.id).total }}</span>
				<span v-else class="dim">—</span>
			</template>
			<template #cell-updatedAt="{ value }"><span class="dim">{{ value }}</span></template>
			<template #cell-actions="{ row: cat }">
				<OzDropdown :items="rowActions()" :label="`${cat.name} işlemleri`" @select="(k) => onRowAction(k, cat)" />
			</template>
		</OzTable>
	</OzCard>

	<MarketplaceConnectDrawer
		v-model:open="connectDrawerOpen"
		:marketplace="connectingMarketplace"
		:category="connectingCategory"
		@submit="handleConnectSubmit"
	/>

	<MarketplaceCategoryPickerModal
		v-model:open="mapperOpen"
		:category="mappingCategory"
		:marketplace="mappingMarketplace"
		:tree="mappingMarketplace?.categoryTree || []"
		:initial-path="mappingInitialPath"
		@submit="handleMapperSubmit"
	/>

	<CategoryFormDrawer
		v-model:open="formDrawerOpen"
		:default-vat-rate="defaultVatRate"
		:category="editingCategory"
		:parent-categories="categories"
		:busy="formBusy"
		:errors="formErrors"
		@submit="handleFormSubmit"
	/>

	<CategoryAttributesModal
		v-model:open="attributesModalOpen"
		:category="attributesCategory"
		:categories="categories"
		:product-attributes="productAttributes"
		@changed="() => showToast?.({ type: 'success', title: 'Özellikler kaydedildi', message: attributesCategory?.name })"
	/>

	<OzModal v-model:open="mpStatusOpen" :title="mpStatusCategory && mpStatusMarketplace ? `${mpStatusCategory.name} ↔ ${mpStatusMarketplace.name}` : ''" icon="success" tone="success" size="sm">
		<div v-if="mpStatusMapping" class="mp-info">
			<div class="mp-info-row"><span class="mp-info-label">Pazaryeri kategorisi</span><span class="mp-info-value">{{ mpStatusMapping.categoryPath }}</span></div>
			<div class="mp-info-row"><span class="mp-info-label">Harici ID</span><span class="mp-info-value mono">{{ mpStatusMapping.externalId }}</span></div>
			<div class="mp-info-row"><span class="mp-info-label">Senkron ürün</span><span class="mp-info-value"><b>{{ mpStatusMapping.syncedProducts }}</b> / {{ mpStatusCategory?.productCount }}</span></div>
			<div class="mp-info-row"><span class="mp-info-label">Son senkron</span><span class="mp-info-value">{{ mpStatusMapping.lastSync }}</span></div>
		</div>
		<template #footer>
			<OzButton variant="danger" @click="mpStatusUnmap">Eşleştirmeyi Kaldır</OzButton>
			<OzButton @click="mpStatusOpen = false">Kapat</OzButton>
			<OzButton variant="primary" @click="mpStatusEdit">Düzenle</OzButton>
		</template>
	</OzModal>
</template>

<style scoped>
.attr-count { display: inline-flex; align-items: center; gap: 5px; padding: 3px 8px; border: 1px solid var(--oz-border); border-radius: 7px; background: none; font: inherit; font-size: 11.5px; font-weight: 600; color: var(--oz-ink-soft); cursor: pointer; white-space: nowrap; }
.attr-count:hover { border-color: var(--oz-accent); color: var(--oz-accent); }




.dim { font-size: 12px; color: var(--oz-muted); }

.cat-cell { display: flex; align-items: center; gap: 12px; }
.cat-icon { width: 34px; height: 34px; border-radius: 10px; background: var(--oz-accent-soft); color: var(--oz-accent); display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.cat-info { display: flex; flex-direction: column; gap: 2px; }
.cat-name { font-weight: 700; color: var(--oz-ink-soft); font-size: 12.5px; }
.cat-name.is-sub { font-weight: 600; }
.cat-parent { font-size: 10.5px; color: var(--oz-muted); font-weight: 500; }

.cat-icon.is-sub { width: 30px; height: 30px; border-radius: 9px; }

.product-count { font-weight: 600; font-variant-numeric: tabular-nums; color: var(--oz-ink-soft); }
.product-count.is-zero { color: var(--oz-muted-2); font-weight: 500; }

.mp-logos { display: flex; gap: 5px; flex-wrap: wrap; }
.mp-chip {
	position: relative; width: 32px; height: 32px; border-radius: 9px;
	background: var(--oz-surface); border: 1.5px solid var(--oz-border); color: var(--oz-muted-2, var(--oz-muted));
	font-family: inherit; font-size: 10px; font-weight: 800; letter-spacing: .04em; cursor: pointer;
	display: inline-flex; align-items: center; justify-content: center;
	transition: transform .12s, box-shadow .12s, border-color .12s; padding: 0;
}
.mp-chip:disabled { cursor: default; opacity: .6; pointer-events: none; }
.mp-chip:hover { transform: translateY(-1px); box-shadow: var(--oz-shadow); }
.mp-chip.mp-mapped { border-color: transparent; }
.mp-chip.mp-mapped::after { content: ''; position: absolute; inset: 0; border-radius: 9px; box-shadow: inset 0 0 0 1.5px rgba(255,255,255,.25); pointer-events: none; }
.mp-chip.mp-unmapped { background: var(--oz-surface-2); color: var(--oz-muted); border-style: dashed; }
.mp-chip.mp-unmapped:hover { border-color: var(--oz-border-strong); color: var(--oz-ink-soft); }
.mp-chip.mp-disconnected { background: var(--oz-danger-soft); color: var(--oz-danger); border-color: rgba(var(--oz-danger-rgb), .3); }
.mp-dot { position: absolute; top: -3px; right: -3px; width: 10px; height: 10px; border-radius: 50%; border: 2px solid var(--oz-surface); }
.mp-dot-success { background: var(--oz-success); }
.mp-dot-error { background: var(--oz-danger); }


.mono { font-family: ui-monospace, monospace; font-size: 11.5px; }
.mp-info { display: flex; flex-direction: column; gap: 8px; padding: 12px 14px; background: var(--oz-surface-2); border: 1px solid var(--oz-border); border-radius: 10px; }
.mp-info-row { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; font-size: 12.5px; }
.mp-info-label { font-size: 11px; font-weight: 700; color: var(--oz-muted); text-transform: uppercase; letter-spacing: .04em; flex-shrink: 0; }
.mp-info-value { color: var(--oz-ink-soft); text-align: right; font-weight: 500; }
</style>
