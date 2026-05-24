<template>
	<Head title="Kategoriler" />
	<div class="page-categories">
		<Breadcrumb
			:items="[
				{ label: 'Ana Sayfa', to: '/workflow', icon: 'home' },
				{ label: 'Katalog', to: '/products' },
				{ label: 'Kategoriler' },
			]"
		/>

		<div class="page-header">
			<div>
				<h1 class="page-title">Kategoriler</h1>
				<p class="page-subtitle">Ürün kategorilerini yönet, pazaryeri entegrasyonlarıyla eşleştir</p>
			</div>
			<button class="btn btn-primary btn-with-icon" @click="openNewCategoryModal">
				<svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
					<path d="M12 5v14M5 12h14" />
				</svg>
				Yeni Kategori
			</button>
		</div>

		<!-- İstatistik kartları -->
		<div class="stats-grid">
			<div class="stat-card">
				<div class="stat-icon" style="background: #eff6ff">📂</div>
				<div class="stat-content">
					<div class="stat-label">Toplam Kategori</div>
					<div class="stat-value">{{ stats.total }}</div>
				</div>
			</div>
			<div class="stat-card">
				<div class="stat-icon" style="background: #f0fdf4">📦</div>
				<div class="stat-content">
					<div class="stat-label">Toplam Ürün</div>
					<div class="stat-value">{{ stats.products.toLocaleString('tr-TR') }}</div>
				</div>
			</div>
			<div class="stat-card">
				<div class="stat-icon" style="background: #f5f3ff">🏪</div>
				<div class="stat-content">
					<div class="stat-label">Aktif Pazaryeri</div>
					<div class="stat-value">{{ stats.activeMarketplaces }} / {{ marketplaces.length }}</div>
				</div>
			</div>
			<div class="stat-card">
				<div class="stat-icon" style="background: #fff7ed">🔗</div>
				<div class="stat-content">
					<div class="stat-label">Ortalama Eşleştirme</div>
					<div class="stat-value">{{ stats.mappingRate }}%</div>
				</div>
			</div>
		</div>

		<!-- Kategori tablosu -->
		<div class="card">
			<div class="card-header">
				<h3>Kategori Listesi</h3>
				<div class="card-search">
					<svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
						<circle cx="11" cy="11" r="8" /><path d="M21 21l-4.35-4.35" />
					</svg>
					<input v-model="searchQuery" type="text" placeholder="Kategori ara..." />
				</div>
				<CustomSelect
					v-model="parentFilter"
					:options="parentFilterOptions"
					:show-label="false"
					style="width: 160px"
				/>
				<CustomSelect
					v-model="statusFilter"
					:options="statusFilterOptions"
					:show-label="false"
					style="width: 130px"
				/>
			</div>

			<table class="data-table">
				<thead>
					<tr>
						<th style="width: 26%">Kategori</th>
						<th style="width: 10%">Ürün</th>
						<th style="width: 28%">Pazaryeri Entegrasyonları</th>
						<th style="width: 10%">Durum</th>
						<th style="width: 12%">Güncelleme</th>
						<th style="width: 14%">İşlemler</th>
					</tr>
				</thead>
				<tbody>
					<tr v-if="paginated.length === 0">
						<td colspan="6" class="empty-row">Kayıt bulunamadı</td>
					</tr>
					<tr v-for="cat in paginated" :key="cat.id">
						<td>
							<div class="cat-cell">
								<div class="cat-icon">{{ cat.icon }}</div>
								<div class="cat-info">
									<span class="cat-name">{{ cat.name }}</span>
									<span class="cat-parent">{{ cat.parent }}</span>
								</div>
							</div>
						</td>
						<td>
							<span class="product-count">{{ cat.productCount }}</span>
						</td>
						<td>
							<div class="mp-logos">
								<button
									v-for="mp in marketplaces"
									:key="mp.key"
									class="mp-chip"
									:class="mpChipClass(cat, mp)"
									:style="mpChipStyle(cat, mp)"
									@click="openMarketplaceCheck(cat, mp)"
									:title="mpTooltip(cat, mp)"
								>
									{{ mp.logoText }}
									<span
										v-if="cat.marketplaces[mp.key]?.mapped && mp.connected"
										class="mp-dot mp-dot-success"
									></span>
									<span
										v-else-if="!mp.connected"
										class="mp-dot mp-dot-error"
									></span>
								</button>
							</div>
						</td>
						<td>
							<span class="status-pill" :class="`status-${cat.status}`">
								<span class="dot"></span>
								{{ cat.status === 'active' ? 'Aktif' : 'Pasif' }}
							</span>
						</td>
						<td class="dim">{{ cat.updatedAt }}</td>
						<td>
							<div class="table-actions">
								<button class="table-action-btn view" @click="editCategory(cat)" title="Düzenle">✏️ Düzenle</button>
								<button class="table-action-btn delete" @click="confirmDelete(cat)" title="Sil">🗑️</button>
							</div>
						</td>
					</tr>
				</tbody>
			</table>

			<div class="pagination">
				<div class="pagination-info">
					Toplam <span>{{ filtered.length }}</span> kategori
					(<span>{{ startIndex }}</span>-<span>{{ endIndex }}</span> arası)
				</div>
				<div class="pagination-controls">
					<button class="pagination-btn" :disabled="currentPage === 1" @click="currentPage--">‹</button>
					<button
						v-for="page in visiblePages"
						:key="page"
						class="pagination-btn"
						:class="{ active: currentPage === page }"
						@click="currentPage = page"
					>{{ page }}</button>
					<button class="pagination-btn" :disabled="currentPage === totalPages || totalPages === 0" @click="currentPage++">›</button>
				</div>
			</div>
		</div>

		<MarketplaceConnectDrawer
			v-model="connectDrawerOpen"
			:marketplace="connectingMarketplace"
			:category="connectingCategory"
			@submit="handleConnectSubmit"
		/>

		<MarketplaceCategoryPickerModal
			v-model="mapperOpen"
			:category="mappingCategory"
			:marketplace="mappingMarketplace"
			:tree="mappingMarketplace?.categoryTree || []"
			:initial-path="mappingInitialPath"
			@submit="handleMapperSubmit"
		/>

		<CategoryFormDrawer
			v-model="formDrawerOpen"
			:category="editingCategory"
			:parent-categories="categories"
			:busy="formBusy"
			:errors="formErrors"
			@submit="handleFormSubmit"
		/>
	</div>
</template>

<script setup>
import { ref, computed, inject, watch, onBeforeUnmount } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Breadcrumb from '@/Components/Breadcrumb.vue'
import CustomSelect from '@/Components/CustomSelect.vue'
import MarketplaceConnectDrawer from '@/Components/MarketplaceConnectDrawer.vue'
import MarketplaceCategoryPickerModal from '@/Components/MarketplaceCategoryPickerModal.vue'
import CategoryFormDrawer from '@/Components/CategoryFormDrawer.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
	categories: { type: Array, default: () => [] },
	marketplaces: { type: Array, default: () => [] },
})

const showToast = inject('showToast')
const $swal = inject('$swal')

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
		products: categories.value.reduce((acc, c) => acc + c.productCount, 0),
		activeMarketplaces: marketplaces.value.filter((mp) => mp.connected).length,
		mappingRate: totalCells > 0 ? Math.round((mappedCells / totalCells) * 100) : 0,
	}
})

/* ── Filtreler ── */
const searchQuery = ref('')
const parentFilter = ref('all')
const statusFilter = ref('all')
const currentPage = ref(1)
const itemsPerPage = 8

const parentFilterOptions = computed(() => {
	const uniqueParents = [...new Set(categories.value.map((c) => c.parent))]
	return [
		{ value: 'all', label: 'Tüm Üst Kategoriler' },
		...uniqueParents.map((p) => ({ value: p, label: p })),
	]
})

const statusFilterOptions = [
	{ value: 'all', label: 'Tüm Durumlar' },
	{ value: 'active', label: 'Aktif' },
	{ value: 'passive', label: 'Pasif' },
]

const filtered = computed(() => {
	const q = searchQuery.value.trim().toLowerCase()
	return categories.value.filter((c) => {
		const matchesSearch = !q || c.name.toLowerCase().includes(q) || c.parent.toLowerCase().includes(q)
		const matchesParent = parentFilter.value === 'all' || c.parent === parentFilter.value
		const matchesStatus = statusFilter.value === 'all' || c.status === statusFilter.value
		return matchesSearch && matchesParent && matchesStatus
	})
})

const totalPages = computed(() => Math.max(1, Math.ceil(filtered.value.length / itemsPerPage)))
const startIndex = computed(() => filtered.value.length ? (currentPage.value - 1) * itemsPerPage + 1 : 0)
const endIndex = computed(() => Math.min(currentPage.value * itemsPerPage, filtered.value.length))

const paginated = computed(() => {
	const start = (currentPage.value - 1) * itemsPerPage
	return filtered.value.slice(start, start + itemsPerPage)
})

const visiblePages = computed(() => {
	const total = totalPages.value
	const current = currentPage.value
	if (total <= 7) return Array.from({ length: total }, (_, i) => i + 1)
	if (current <= 4) return [1, 2, 3, 4, 5, total]
	if (current >= total - 3) return [1, total - 4, total - 3, total - 2, total - 1, total]
	return [1, current - 1, current, current + 1, total]
})

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

/* ── Pazaryeri tıklama: SweetAlert ile durum gösterimi ── */
async function openMarketplaceCheck(cat, mp) {
	// Senaryo 1: Pazaryeri bağlı değil → bağlantı drawer'ı aç
	if (!mp.connected) {
		openConnectDrawer(cat, mp)
		return
	}

	const mapping = cat.marketplaces[mp.key]

	// Senaryo 2: Eşleştirme var
	if (mapping?.mapped) {
		const result = await $swal.fire({
			icon: 'success',
			title: `${cat.name} ↔ ${mp.name}`,
			html: `
				<div class="mp-info">
					<div class="mp-info-row">
						<span class="mp-info-label">Pazaryeri kategorisi</span>
						<span class="mp-info-value">${mapping.categoryPath}</span>
					</div>
					<div class="mp-info-row">
						<span class="mp-info-label">Harici ID</span>
						<span class="mp-info-value mono">${mapping.externalId}</span>
					</div>
					<div class="mp-info-row">
						<span class="mp-info-label">Senkron ürün</span>
						<span class="mp-info-value"><strong>${mapping.syncedProducts}</strong> / ${cat.productCount}</span>
					</div>
					<div class="mp-info-row">
						<span class="mp-info-label">Son senkron</span>
						<span class="mp-info-value">${mapping.lastSync}</span>
					</div>
				</div>
			`,
			showCancelButton: true,
			showDenyButton: true,
			confirmButtonText: '✏️ Düzenle',
			denyButtonText: 'Eşleştirmeyi Kaldır',
			cancelButtonText: 'Kapat',
			customClass: {
				popup: 'tek-swal',
				title: 'tek-swal-title',
				htmlContainer: 'tek-swal-body',
				icon: 'tek-swal-icon',
				actions: 'tek-swal-actions',
				confirmButton: 'btn btn-secondary',
				denyButton: 'btn btn-outline-danger',
				cancelButton: 'btn btn-ghost',
			},
		})

		if (result.isConfirmed) {
			openMappingEditor(cat, mp, mapping)
		} else if (result.isDenied) {
			await confirmUnmap(cat, mp)
		}
		return
	}

	// Senaryo 3: Eşleştirme yok
	const ok = await $swal.confirm({
		icon: 'warning',
		title: `${mp.name}'e Eşleştir`,
		html: `<strong>${cat.name}</strong> kategorisi <strong>${mp.name}</strong>'te henüz eşleştirilmemiş.<br>Şimdi eşleştirip <strong>${cat.productCount}</strong> ürünü bu pazaryerine yollayabilirsin.`,
		confirmText: 'Şimdi Eşleştir',
		cancelText: 'Vazgeç',
	})
	if (ok) openMappingEditor(cat, mp, null)
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
	const ok = await $swal.dangerConfirm({
		title: 'Eşleştirmeyi Kaldır',
		html: `<strong>${cat.name}</strong> kategorisinin <strong>${mp.name}</strong> eşleştirmesi kaldırılacak. Bu pazaryerindeki ürün senkronizasyonu duracak.`,
		confirmText: 'Kaldır',
		cancelText: 'Vazgeç',
	})
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
function openNewCategoryModal() {
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
	const ok = await $swal.dangerConfirm({
		title: 'Kategoriyi Sil',
		html: `<strong>${cat.name}</strong> silinecek.<br>Bu kategoride <strong>${cat.productCount}</strong> ürün ve <strong>${mappedCount}</strong> pazaryeri eşleştirmesi var.`,
		confirmText: 'Sil',
		cancelText: 'Vazgeç',
	})
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

<style scoped>
.page-header {
	display: flex;
	align-items: flex-start;
	justify-content: space-between;
	margin-bottom: 20px;
	gap: 16px;
}

.page-title { font-size: 22px; font-weight: 700; color: #1a1a2e; line-height: 1.2; }
.page-subtitle { font-size: 13px; color: #888; margin-top: 4px; }

/* ── Stats ── */
.stats-grid {
	display: grid;
	grid-template-columns: repeat(4, 1fr);
	gap: 16px;
	margin-bottom: 20px;
}
@media (max-width: 900px) { .stats-grid { grid-template-columns: repeat(2, 1fr); } }

.stat-card {
	background: #fff;
	border-radius: 14px;
	border: 1px solid #ebebf0;
	padding: 18px 20px;
	display: flex;
	align-items: center;
	gap: 14px;
	min-height: 80px;
	box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
}
.stat-icon {
	width: 48px; height: 48px;
	border-radius: 12px;
	display: flex; align-items: center; justify-content: center;
	font-size: 20px; flex-shrink: 0;
}
.stat-content { flex: 1; }
.stat-label { font-size: 12px; color: #888; font-weight: 500; }
.stat-value { font-size: 24px; font-weight: 700; color: #1a1a2e; }

/* ── Kart ── */
.card {
	background: #fff;
	border-radius: 16px;
	border: 1px solid #ebebf0;
	overflow: hidden;
	box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
}

.card-header {
	padding: 14px 18px;
	display: flex;
	align-items: center;
	gap: 12px;
	border-bottom: 1px solid #f0f0f5;
}
.card-header h3 { font-size: 15px; font-weight: 700; color: #1a1a2e; }

.card-search {
	display: flex; align-items: center; gap: 6px;
	background: #f5f5f8; border: 1px solid #e8e8f0;
	border-radius: 8px; padding: 5px 10px;
	margin-left: auto;
	min-width: 220px;
}
.card-search svg { color: #aaa; flex-shrink: 0; }
.card-search input {
	border: none; background: none; outline: none;
	font-family: inherit; font-size: 13px; color: #1a1a2e;
	width: 100%;
}
.card-search input::placeholder { color: #bbb; }

/* ── Tablo ── */
.data-table {
	width: 100%;
	border-collapse: separate;
	border-spacing: 0;
}
.data-table thead tr { background: #f8f8fc; }
.data-table th {
	text-align: left;
	padding: 12px 16px;
	font-size: 11px;
	font-weight: 600;
	color: #aaa;
	border-bottom: 1px solid #f0f0f5;
	text-transform: uppercase;
	letter-spacing: 0.04em;
}
.data-table td {
	padding: 12px 16px;
	font-size: 13px;
	color: #444;
	border-bottom: 1px solid #f5f5f8;
	vertical-align: middle;
}
.data-table tr:last-child td { border-bottom: none; }
.data-table tr:hover td { background: #fafafe; }

.empty-row {
	text-align: center !important;
	color: #aaa;
	padding: 32px 0 !important;
	font-style: italic;
}

.dim { font-size: 12px; color: #888; }

/* ── Kategori hücresi ── */
.cat-cell { display: flex; align-items: center; gap: 12px; }
.cat-icon {
	width: 36px; height: 36px;
	border-radius: 10px;
	background: #f5f3ff;
	display: flex; align-items: center; justify-content: center;
	font-size: 18px;
	flex-shrink: 0;
}
.cat-info { display: flex; flex-direction: column; gap: 2px; }
.cat-name { font-weight: 600; color: #1a1a2e; font-size: 13px; }
.cat-parent {
	font-size: 10.5px;
	color: #888;
	font-weight: 500;
}

.product-count {
	display: inline-block;
	padding: 2px 9px;
	background: #f0f0f5;
	color: #555;
	border-radius: 6px;
	font-size: 11.5px;
	font-weight: 700;
	font-family: 'SF Mono', Menlo, Consolas, monospace;
}

/* ── Pazaryeri logoları ── */
.mp-logos {
	display: flex;
	gap: 5px;
	flex-wrap: wrap;
}

.mp-chip {
	position: relative;
	width: 32px;
	height: 32px;
	border-radius: 9px;
	background: #fff;
	border: 1.5px solid #e8e8f0;
	color: #aaa;
	font-family: inherit;
	font-size: 10px;
	font-weight: 800;
	letter-spacing: 0.04em;
	cursor: pointer;
	display: inline-flex;
	align-items: center;
	justify-content: center;
	transition: transform .12s, box-shadow .12s, border-color .12s;
	padding: 0;
}
.mp-chip:hover {
	transform: translateY(-1px);
	box-shadow: 0 4px 10px rgba(0, 0, 0, 0.08);
}

.mp-chip.mp-mapped {
	border-color: transparent;
}
.mp-chip.mp-mapped::after {
	content: '';
	position: absolute;
	inset: 0;
	border-radius: 9px;
	box-shadow: inset 0 0 0 1.5px rgba(255, 255, 255, 0.25);
	pointer-events: none;
}

.mp-chip.mp-unmapped {
	background: #fafafe;
	color: #c4c4d0;
	border-style: dashed;
	border-color: #e0e0ea;
}
.mp-chip.mp-unmapped:hover { border-color: #c0c0d8; color: #888; }

.mp-chip.mp-disconnected {
	background: #fef2f2;
	color: #dc2626;
	border-color: #fecaca;
}
.mp-chip.mp-disconnected:hover { border-color: #fca5a5; }

.mp-dot {
	position: absolute;
	top: -3px;
	right: -3px;
	width: 10px;
	height: 10px;
	border-radius: 50%;
	border: 2px solid #fff;
}
.mp-dot-success { background: #16a34a; }
.mp-dot-error { background: #dc2626; }

/* ── Status pill ── */
.status-pill {
	display: inline-flex;
	align-items: center;
	gap: 5px;
	padding: 3px 10px;
	border-radius: 999px;
	font-size: 11px;
	font-weight: 600;
}
.status-pill .dot {
	width: 5px; height: 5px;
	border-radius: 50%;
	background: currentColor;
}
.status-active { background: #dcfce7; color: #16a34a; }
.status-passive { background: #fee2e2; color: #dc2626; }

/* ── İşlem butonları ── */
.table-actions { display: flex; gap: 4px; }
.table-action-btn {
	background: #f3f4f6;
	border: none; cursor: pointer;
	font-size: 11.5px;
	padding: 5px 9px;
	border-radius: 6px;
	font-weight: 500;
	color: #6b7280;
	transition: all .15s;
}
.table-action-btn.view:hover { background: #e0e7ff; color: #4f46e5; }
.table-action-btn.delete:hover { background: #fee2e2; color: #dc2626; }

/* ── Pagination ── */
.pagination {
	display: flex;
	align-items: center;
	justify-content: space-between;
	padding: 16px 20px;
	border-top: 1px solid #f0f0f5;
}
.pagination-info { font-size: 13px; color: #888; }
.pagination-info span { color: #1a1a2e; font-weight: 600; }
.pagination-controls { display: flex; align-items: center; gap: 4px; }
.pagination-btn {
	min-width: 32px; height: 32px;
	padding: 0 8px;
	border: 1px solid #e8e8f0;
	border-radius: 6px;
	background: #fff; color: #666;
	font-size: 13px; font-weight: 500;
	cursor: pointer;
	display: flex; align-items: center; justify-content: center;
	transition: all .15s;
}
.pagination-btn:hover:not(:disabled) { border-color: #ccc; color: #333; background: #f9f9fb; }
.pagination-btn.active { background: #1a1a2e; color: #fff; border-color: #1a1a2e; }
.pagination-btn:disabled { opacity: 0.4; cursor: not-allowed; }
</style>

<style>
/* SweetAlert içinde kullanılan dahili stiller (scoped olmamalı) */
.mp-info {
	text-align: left;
	display: flex;
	flex-direction: column;
	gap: 8px;
	margin-top: 8px;
	padding: 12px 14px;
	background: #fafafe;
	border: 1px solid #f0f0f5;
	border-radius: 10px;
}
.mp-info-row {
	display: flex;
	justify-content: space-between;
	align-items: flex-start;
	gap: 12px;
	font-size: 12.5px;
}
.mp-info-label {
	font-size: 11px;
	font-weight: 700;
	color: #888;
	text-transform: uppercase;
	letter-spacing: 0.04em;
	flex-shrink: 0;
}
.mp-info-value {
	color: #1a1a2e;
	text-align: right;
	font-weight: 500;
}
.mp-info-value strong { color: #1a1a2e; font-weight: 800; }
.mp-info-value.mono {
	font-family: 'SF Mono', Menlo, Consolas, monospace;
	font-size: 11.5px;
}
.tek-swal-body .muted { color: #888; font-size: 11.5px; }
</style>
