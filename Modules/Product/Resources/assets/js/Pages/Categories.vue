<script setup>
import { ref, computed, inject, watch, onBeforeUnmount } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import {
	OzPageHeader, OzModuleTabs, OzStatStrip, OzCard, OzInput, OzSelect, OzPill, OzDropdown,
	OzPagination, OzEmptyState, OzButton, OzModal, OzIcon, useOzConfirm,
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

/* ── Özellik tanımları modal state (Faz 3) ── */
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

/* ── Toplu seçim ── */
const selected = ref(new Set())
const bulkBusy = ref(false)
const selectedCount = computed(() => selected.value.size)

function isSelected(id) { return selected.value.has(id) }
function toggleRow(id) {
	const next = new Set(selected.value)
	next.has(id) ? next.delete(id) : next.add(id)
	selected.value = next
}
const allVisibleSelected = computed(() =>
	paginated.value.length > 0 && paginated.value.every((c) => selected.value.has(c.id)),
)
function toggleSelectAllVisible() {
	const ids = paginated.value.map((c) => c.id)
	const next = new Set(selected.value)
	if (allVisibleSelected.value) ids.forEach((id) => next.delete(id))
	else ids.forEach((id) => next.add(id))
	selected.value = next
}
function clearSelection() { selected.value = new Set() }

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
			clearSelection()
		},
		onError: (errs) => {
			showToast?.({ type: 'error', title: 'Silme Başarısız', message: Object.values(errs)[0] || 'Sunucu hatası.' })
		},
		onFinish: () => { bulkBusy.value = false },
	})
}

/* ── Ağaç ── */
// Düz listeden ağaç satırları: ebeveyn → çocuklar, kardeşler backend sırasında (sort_order, name).
// Ebeveyni listede olmayan (silinmiş) kategori köke düşer; bozuk veride döngüye karşı `seen` kümesi.
const tree = computed(() => {
	const byId = new Map(categories.value.map((c) => [c.id, c]))
	const children = new Map()
	const roots = []
	for (const c of categories.value) {
		const pid = c.parent_id && c.parent_id !== c.id && byId.has(c.parent_id) ? c.parent_id : null
		if (pid === null) roots.push(c)
		else {
			if (!children.has(pid)) children.set(pid, [])
			children.get(pid).push(c)
		}
	}
	const rows = []
	const seen = new Set()
	const walk = (c, depth, ancestors, rootId) => {
		if (seen.has(c.id)) return
		seen.add(c.id)
		const kids = children.get(c.id) ?? []
		rows.push({ cat: c, depth, ancestors, rootId, childCount: kids.length })
		kids.forEach((k) => walk(k, depth + 1, [...ancestors, c.id], rootId))
	}
	roots.forEach((r) => walk(r, 0, [], r.id))
	categories.value.filter((c) => !seen.has(c.id)).forEach((c) => walk(c, 0, [], c.id))
	return rows
})

// Daraltılmış düğümler (varsayılan: hepsi açık)
const collapsed = ref(new Set())
const hasNested = computed(() => tree.value.some((r) => r.childCount > 0))
const isCollapsed = (id) => collapsed.value.has(id)
function toggleNode(id) {
	const next = new Set(collapsed.value)
	next.has(id) ? next.delete(id) : next.add(id)
	collapsed.value = next
}
const allCollapsed = computed(() => tree.value.filter((r) => r.childCount > 0).every((r) => collapsed.value.has(r.cat.id)))
function toggleAll() {
	collapsed.value = allCollapsed.value
		? new Set()
		: new Set(tree.value.filter((r) => r.childCount > 0).map((r) => r.cat.id))
}

/* ── Filtreler ── */
const searchQuery = ref('')
const parentFilter = ref('all')
const statusFilter = ref('all')
const currentPage = ref(1)
const itemsPerPage = 8 // sayfa başına ana kategori grubu (alt kategoriler grubuyla birlikte gelir)

// "Şunun altındakiler": alt kategorisi olan kategoriler, tam yoluyla
const parentFilterOptions = computed(() => {
	const byId = new Map(categories.value.map((c) => [c.id, c]))
	return [
		{ value: 'all', label: 'Tüm Kategoriler' },
		...tree.value
			.filter((r) => r.childCount > 0)
			.map((r) => ({
				value: String(r.cat.id),
				label: [...r.ancestors.map((id) => byId.get(id)?.name), r.cat.name].join(' › '),
			})),
	]
})

const statusFilterOptions = [
	{ value: 'all', label: 'Tüm Durumlar' },
	{ value: 'active', label: 'Aktif' },
	{ value: 'passive', label: 'Pasif' },
]

const isFiltering = computed(() => !!searchQuery.value.trim() || statusFilter.value !== 'all')

// Görünen satırlar. Arama/durum filtresinde eşleşenler üst kategorileriyle birlikte gösterilir
// (üstler soluk "bağlam" satırı); bu modda daraltma yok sayılır.
const visibleRows = computed(() => {
	let rows = tree.value
	if (parentFilter.value !== 'all') {
		const id = Number(parentFilter.value)
		const base = rows.find((r) => r.cat.id === id)?.depth ?? 0
		rows = rows
			.filter((r) => r.cat.id === id || r.ancestors.includes(id))
			.map((r) => ({ ...r, depth: r.depth - base, rootId: id }))
	}
	if (isFiltering.value) {
		const q = searchQuery.value.trim().toLocaleLowerCase('tr')
		const hit = new Set(rows
			.filter(({ cat }) => (!q || cat.name.toLocaleLowerCase('tr').includes(q) || cat.slug?.includes(q))
				&& (statusFilter.value === 'all' || cat.status === statusFilter.value))
			.map((r) => r.cat.id))
		const keep = new Set(hit)
		rows.forEach((r) => { if (hit.has(r.cat.id)) r.ancestors.forEach((a) => keep.add(a)) })
		return rows.filter((r) => keep.has(r.cat.id)).map((r) => ({ ...r, context: !hit.has(r.cat.id) }))
	}
	return rows.filter((r) => !r.ancestors.some((a) => collapsed.value.has(a)))
})

// Sayfalama ana kategori gruplarıyla — bir ağaç iki sayfaya bölünmez
const groups = computed(() => {
	const list = []
	for (const r of visibleRows.value) {
		if (!list.length || list.at(-1).key !== r.rootId) list.push({ key: r.rootId, rows: [] })
		list.at(-1).rows.push(r)
	}
	return list
})
watch([searchQuery, parentFilter, statusFilter], () => { currentPage.value = 1 })

const paginatedRows = computed(() => {
	const start = (currentPage.value - 1) * itemsPerPage
	return groups.value.slice(start, start + itemsPerPage).flatMap((g) => g.rows)
})
// Seçim / toplu işlem için: bağlam satırları hariç kategoriler
const paginated = computed(() => paginatedRows.value.filter((r) => !r.context).map((r) => r.cat))

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
		<template #actions>
			<div class="toolbar">
				<OzInput v-model="searchQuery" icon="search" placeholder="Kategori ara…" class="tb-search" />
				<OzSelect v-model="parentFilter" :options="parentFilterOptions" class="tb-select" />
				<OzSelect v-model="statusFilter" :options="statusFilterOptions" class="tb-select-sm" />
				<OzButton v-if="hasNested && !isFiltering" size="sm" :icon="allCollapsed ? 'chevron-right' : 'chevron-down'" @click="toggleAll">
					{{ allCollapsed ? 'Tümünü Aç' : 'Tümünü Kapat' }}
				</OzButton>
			</div>
		</template>

		<div class="table-scroll">
			<table class="cat-table">
				<thead>
					<tr>
						<th v-if="canManage" class="col-check">
							<input type="checkbox" :checked="allVisibleSelected" aria-label="Tümünü seç" @change="toggleSelectAllVisible">
						</th>
						<th style="width: 26%">Kategori</th>
						<th style="width: 10%">Ürün</th>
						<th style="width: 28%">Pazaryeri Entegrasyonları</th>
						<th style="width: 10%">Durum</th>
						<th style="width: 12%">Güncelleme</th>
						<th v-if="hasRowActions" style="width: 48px"></th>
					</tr>
				</thead>
				<tbody>
					<tr
						v-for="{ cat, depth, childCount, context } in paginatedRows"
						:key="cat.id"
						:class="{ 'row-selected': isSelected(cat.id), 'row-context': context, 'row-child': depth > 0 }"
					>
						<td v-if="canManage" class="col-check">
							<input v-if="!context" type="checkbox" :checked="isSelected(cat.id)" :aria-label="`${cat.name} seç`" @change="toggleRow(cat.id)">
						</td>
						<td>
							<div class="cat-cell" :style="{ paddingLeft: `${depth * 26}px` }">
								<button
									v-if="childCount && !isFiltering"
									type="button"
									class="tree-toggle"
									:aria-expanded="!isCollapsed(cat.id)"
									:aria-label="`${cat.name} alt kategorilerini ${isCollapsed(cat.id) ? 'göster' : 'gizle'}`"
									@click="toggleNode(cat.id)"
								>
									<OzIcon :name="isCollapsed(cat.id) ? 'chevron-right' : 'chevron-down'" :size="14" />
								</button>
								<span v-else-if="hasNested" class="tree-spacer" :class="{ 'is-leaf': depth > 0 }" aria-hidden="true" />
								<div class="cat-icon" :class="{ 'is-sub': depth > 0 }"><OzIcon :name="ozIconForCategory(cat.icon)" :size="depth > 0 ? 15 : 17" /></div>
								<div class="cat-info">
									<span class="cat-name">{{ cat.name }}</span>
									<span v-if="childCount" class="cat-parent">{{ childCount }} alt kategori</span>
								</div>
							</div>
						</td>
						<td><span class="product-count">{{ cat.productCount }}</span></td>
						<td>
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
						</td>
						<td>
							<OzPill
								:tone="cat.status === 'active' ? 'success' : 'neutral'"
								:title="cat.status === 'active' ? null : cat.manuallyPassive ? 'Elle pasife alındı — ürün gelse de pasif kalır' : 'Kendisinde ve alt kategorilerinde yayında ürün yok — ürün yayına alınınca otomatik aktif olur'"
							>{{ cat.status === 'active' ? 'Aktif' : cat.manuallyPassive ? 'Pasif (elle)' : 'Pasif · ürün yok' }}</OzPill>
						</td>
						<td class="dim">{{ cat.updatedAt }}</td>
						<td v-if="hasRowActions" @click.stop>
							<OzDropdown :items="rowActions()" :label="`${cat.name} işlemleri`" @select="(k) => onRowAction(k, cat)" />
						</td>
					</tr>
				</tbody>
			</table>
			<OzEmptyState v-if="!paginatedRows.length" icon="layers" title="Kayıt bulunamadı" compact />
		</div>

		<div v-if="groups.length > itemsPerPage" class="pagination-wrap">
			<OzPagination v-model="currentPage" :total="groups.length" :page-size="itemsPerPage" />
		</div>
	</OzCard>

	<!-- Toplu seçim çubuğu (yüzen) -->
	<div class="bulk-dock" :class="{ 'is-active': canManage && selectedCount > 0 }">
		<Transition name="bulk">
			<div v-if="canManage && selectedCount > 0" class="bulk-bar" role="toolbar" aria-label="Toplu işlemler">
				<span class="bulk-info"><b>{{ selectedCount }}</b> kategori seçildi</span>
				<div class="bulk-actions">
					<OzButton size="sm" @click="clearSelection">Vazgeç</OzButton>
					<OzButton variant="danger" size="sm" icon="trash" :loading="bulkBusy" @click="bulkDelete">Seçilenleri Sil</OzButton>
				</div>
			</div>
		</Transition>
	</div>

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
		:category="editingCategory"
		:parent-categories="categories"
		:busy="formBusy"
		:errors="formErrors"
		@submit="handleFormSubmit"
	/>

	<CategoryAttributesModal
		v-model:open="attributesModalOpen"
		:category="attributesCategory"
		@changed="() => router.reload({ only: ['categories'], preserveScroll: true })"
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
.toolbar { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.tb-search { width: 220px; }
.tb-select { width: 170px; }
.tb-select-sm { width: 140px; }

/* Toplu seçim çubuğu: akışta yer kaplamaz (tabloyu itmez), kaydırma alanının altında yüzer.
   Dock, .oz-shell-content içinde sticky; seçim varken sona boşluk açar ki son satırları örtmesin. */
.bulk-dock { position: sticky; bottom: 0; height: 0; z-index: 5; pointer-events: none; transition: height .2s ease; }
.bulk-dock.is-active { height: 76px; }
.bulk-bar {
	position: absolute; left: 50%; bottom: 16px; transform: translateX(-50%);
	display: flex; align-items: center; gap: 14px; width: max-content; max-width: calc(100% - 24px);
	padding: 8px 8px 8px 16px; border-radius: 999px; pointer-events: auto;
	background: var(--oz-surface); border: 1px solid var(--oz-border); box-shadow: var(--oz-shadow);
}
.bulk-info { font-size: 12.5px; color: var(--oz-ink-soft); white-space: nowrap; }
.bulk-info b { color: var(--oz-accent); }
.bulk-actions { display: flex; gap: 8px; }
.bulk-enter-active, .bulk-leave-active { transition: opacity .18s ease, transform .22s cubic-bezier(.2, .8, .2, 1); }
.bulk-enter-from, .bulk-leave-to { opacity: 0; transform: translate(-50%, 14px); }
@media (max-width: 768px) {
	.bulk-bar { bottom: 10px; gap: 10px; padding-left: 14px; }
}

.col-check { width: 40px; text-align: center; padding-left: 16px !important; padding-right: 0 !important; }
.col-check input { width: 15px; height: 15px; accent-color: var(--oz-accent); cursor: pointer; }

.table-scroll { overflow-x: auto; padding: 0 4px; }
.cat-table { width: 100%; border-collapse: collapse; }
.cat-table th { text-align: left; padding: 10px 14px; font-size: 10.5px; font-weight: 700; color: var(--oz-muted); text-transform: uppercase; letter-spacing: .04em; border-bottom: 1px solid var(--oz-border); background: var(--oz-th-bg); }
.cat-table td { padding: 12px 14px; font-size: 12.5px; border-top: 1px solid var(--oz-border-soft); vertical-align: middle; }
.cat-table tr:hover td { background: var(--oz-surface-2); }
.cat-table tr.row-selected td { background: var(--oz-accent-soft); }

.dim { font-size: 12px; color: var(--oz-muted); }

.cat-cell { display: flex; align-items: center; gap: 12px; }
.cat-icon { width: 34px; height: 34px; border-radius: 10px; background: var(--oz-accent-soft); color: var(--oz-accent); display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.cat-info { display: flex; flex-direction: column; gap: 2px; }
.cat-name { font-weight: 700; color: var(--oz-ink-soft); font-size: 12.5px; }
.cat-parent { font-size: 10.5px; color: var(--oz-muted); font-weight: 500; }

/* Ağaç */
.tree-toggle { width: 22px; height: 22px; margin-right: -6px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; border: none; border-radius: 6px; background: none; color: var(--oz-muted); cursor: pointer; }
.tree-toggle:hover { background: var(--oz-surface-2); color: var(--oz-ink); }
.tree-toggle:focus-visible { outline: 2px solid var(--oz-accent); outline-offset: 1px; }
.tree-spacer { width: 22px; margin-right: -6px; flex-shrink: 0; align-self: stretch; position: relative; }
/* alt kategori: üst kategoriye bağlayan "└" çizgisi */
.tree-spacer.is-leaf::before { content: ''; position: absolute; left: 10px; top: -12px; height: calc(50% + 12px); width: 9px; border-left: 1.5px solid var(--oz-border); border-bottom: 1.5px solid var(--oz-border); border-bottom-left-radius: 6px; }
.cat-icon.is-sub { width: 30px; height: 30px; border-radius: 9px; }
.row-child .cat-name { font-weight: 600; }
.row-context td { opacity: .55; }

.product-count { display: inline-block; padding: 2px 9px; background: var(--oz-surface-2); color: var(--oz-muted); border-radius: 6px; font-size: 11.5px; font-weight: 700; font-family: ui-monospace, monospace; }

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

.pagination-wrap { padding: 14px 18px 16px; border-top: 1px solid var(--oz-border-soft); }

.mono { font-family: ui-monospace, monospace; font-size: 11.5px; }
.mp-info { display: flex; flex-direction: column; gap: 8px; padding: 12px 14px; background: var(--oz-surface-2); border: 1px solid var(--oz-border); border-radius: 10px; }
.mp-info-row { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; font-size: 12.5px; }
.mp-info-label { font-size: 11px; font-weight: 700; color: var(--oz-muted); text-transform: uppercase; letter-spacing: .04em; flex-shrink: 0; }
.mp-info-value { color: var(--oz-ink-soft); text-align: right; font-weight: 500; }
</style>
