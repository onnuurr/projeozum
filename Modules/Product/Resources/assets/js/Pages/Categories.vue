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
				<p class="page-subtitle">Ürün kategorilerini yönet</p>
			</div>
			<button class="btn btn-primary btn-with-icon" @click="openNewCategoryModal">
				<svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
					<path d="M12 5v14M5 12h14" />
				</svg>
				Yeni Kategori
			</button>
		</div>

		<div class="stats-grid">
			<div class="stat-card">
				<div class="stat-icon" style="background: rgb(var(--color-primary-soft))">📂</div>
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
		</div>

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

			<div v-if="selectedCount > 0" class="bulk-bar">
				<span class="bulk-info"><strong>{{ selectedCount }}</strong> kategori seçildi</span>
				<div class="bulk-actions">
					<button class="btn btn-ghost btn-sm" @click="clearSelection">Vazgeç</button>
					<button class="btn btn-danger btn-sm btn-with-icon" :disabled="bulkBusy" @click="bulkDelete">
						🗑️ Seçilenleri Sil
					</button>
				</div>
			</div>

			<table class="data-table">
				<thead>
					<tr>
						<th class="col-check">
							<input type="checkbox" :checked="allVisibleSelected" @change="toggleSelectAllVisible" aria-label="Tümünü seç" />
						</th>
						<th style="width: 32%">Kategori</th>
						<th style="width: 14%">Ürün</th>
						<th style="width: 14%">Durum</th>
						<th style="width: 16%">Güncelleme</th>
						<th style="width: 18%">İşlemler</th>
					</tr>
				</thead>
				<tbody>
					<tr v-if="paginated.length === 0">
						<td colspan="6" class="empty-row">Kayıt bulunamadı</td>
					</tr>
					<tr v-for="cat in paginated" :key="cat.id" :class="{ 'row-selected': isSelected(cat.id) }">
						<td class="col-check">
							<input type="checkbox" :checked="isSelected(cat.id)" @change="toggleRow(cat.id)" :aria-label="`${cat.name} seç`" />
						</td>
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
import { ref, computed, inject, watch } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Breadcrumb from '@/Components/Breadcrumb.vue'
import CustomSelect from '@/Components/CustomSelect.vue'
import CategoryFormDrawer from '@/Components/CategoryFormDrawer.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
	categories: { type: Array, default: () => [] },
})

const showToast = inject('showToast')
const $swal = inject('$swal')

const categories = ref(props.categories.map((c) => ({ ...c })))

watch(
	() => props.categories,
	(list) => { categories.value = list.map((c) => ({ ...c })) },
	{ deep: true },
)

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

const stats = computed(() => ({
	total: categories.value.length,
	products: categories.value.reduce((acc, c) => acc + c.productCount, 0),
}))

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
	const ok = await $swal.dangerConfirm({
		title: 'Kategorileri Sil',
		html: `<strong>${ids.length}</strong> kategori kalıcı olarak silinecek.`,
		confirmText: 'Sil',
		cancelText: 'Vazgeç',
	})
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
	const ok = await $swal.dangerConfirm({
		title: 'Kategoriyi Sil',
		html: `<strong>${cat.name}</strong> silinecek.<br>Bu kategoride <strong>${cat.productCount}</strong> ürün var.`,
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
.page-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 20px; gap: 16px; }
.page-title { font-size: 22px; font-weight: 700; color: #1a1a2e; line-height: 1.2; }
.page-subtitle { font-size: 13px; color: #888; margin-top: 4px; }

.stats-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; margin-bottom: 20px; }
@media (max-width: 620px) { .stats-grid { grid-template-columns: 1fr; } }

.stat-card {
	background: #fff; border-radius: 14px; border: 1px solid #ebebf0;
	padding: 18px 20px; display: flex; align-items: center; gap: 14px;
	min-height: 80px; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
}
.stat-icon { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 20px; flex-shrink: 0; }
.stat-content { flex: 1; }
.stat-label { font-size: 12px; color: #888; font-weight: 500; }
.stat-value { font-size: 24px; font-weight: 700; color: #1a1a2e; }

.card { background: #fff; border-radius: 16px; border: 1px solid #ebebf0; overflow: hidden; box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04); }
.card-header { padding: 14px 18px; display: flex; align-items: center; gap: 12px; border-bottom: 1px solid #f0f0f5; }
.card-header h3 { font-size: 15px; font-weight: 700; color: #1a1a2e; }

.card-search {
	display: flex; align-items: center; gap: 6px;
	background: #f5f5f8; border: 1px solid #e8e8f0;
	border-radius: 8px; padding: 5px 10px; margin-left: auto; min-width: 220px;
}
.card-search svg { color: #aaa; flex-shrink: 0; }
.card-search input { border: none; background: none; outline: none; font-family: inherit; font-size: 13px; color: #1a1a2e; width: 100%; }
.card-search input::placeholder { color: #bbb; }

.bulk-bar {
	display: flex; align-items: center; justify-content: space-between; gap: 12px;
	padding: 10px 18px; background: #faf5ff; border-bottom: 1px solid #f0e9fb;
}
.bulk-info { font-size: 13px; color: rgb(var(--color-primary-hover)); }
.bulk-info strong { font-weight: 800; }
.bulk-actions { display: flex; gap: 8px; }
.btn-danger { background: #dc2626; color: #fff; border: none; }
.btn-danger:hover:not(:disabled) { background: #b91c1c; }
.btn-danger:disabled { opacity: .55; cursor: not-allowed; }

.col-check { width: 40px; text-align: center; padding-left: 16px; padding-right: 0; }
.col-check input { width: 15px; height: 15px; accent-color: rgb(var(--color-primary)); cursor: pointer; }
.data-table tr.row-selected td { background: #faf5ff; }

.data-table { width: 100%; border-collapse: separate; border-spacing: 0; }
.data-table thead tr { background: #f8f8fc; }
.data-table th {
	text-align: left; padding: 12px 16px; font-size: 11px; font-weight: 600; color: #aaa;
	border-bottom: 1px solid #f0f0f5; text-transform: uppercase; letter-spacing: 0.04em;
}
.data-table td { padding: 12px 16px; font-size: 13px; color: #444; border-bottom: 1px solid #f5f5f8; vertical-align: middle; }
.data-table tr:last-child td { border-bottom: none; }
.data-table tr:hover td { background: #fafafe; }

.empty-row { text-align: center !important; color: #aaa; padding: 32px 0 !important; font-style: italic; }
.dim { font-size: 12px; color: #888; }

.cat-cell { display: flex; align-items: center; gap: 12px; }
.cat-icon {
	width: 36px; height: 36px; border-radius: 10px; background: rgb(var(--color-primary-soft));
	display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0;
}
.cat-info { display: flex; flex-direction: column; gap: 2px; }
.cat-name { font-weight: 600; color: #1a1a2e; font-size: 13px; }
.cat-parent { font-size: 10.5px; color: #888; font-weight: 500; }

.product-count {
	display: inline-block; padding: 2px 9px; background: #f0f0f5; color: #555;
	border-radius: 6px; font-size: 11.5px; font-weight: 700;
	font-family: 'SF Mono', Menlo, Consolas, monospace;
}

.status-pill { display: inline-flex; align-items: center; gap: 5px; padding: 3px 10px; border-radius: 999px; font-size: 11px; font-weight: 600; }
.status-pill .dot { width: 5px; height: 5px; border-radius: 50%; background: currentColor; }
.status-active { background: #dcfce7; color: #16a34a; }
.status-passive { background: #fee2e2; color: #dc2626; }

.table-actions { display: flex; gap: 4px; }
.table-action-btn {
	background: #f3f4f6; border: none; cursor: pointer; font-size: 11.5px;
	padding: 5px 9px; border-radius: 6px; font-weight: 500; color: #6b7280; transition: all .15s;
}
.table-action-btn.view:hover { background: rgb(var(--color-primary-soft)); color: rgb(var(--color-primary)); }
.table-action-btn.delete:hover { background: #fee2e2; color: #dc2626; }

.pagination { display: flex; align-items: center; justify-content: space-between; padding: 16px 20px; border-top: 1px solid #f0f0f5; }
.pagination-info { font-size: 13px; color: #888; }
.pagination-info span { color: #1a1a2e; font-weight: 600; }
.pagination-controls { display: flex; align-items: center; gap: 4px; }
.pagination-btn {
	min-width: 32px; height: 32px; padding: 0 8px; border: 1px solid #e8e8f0; border-radius: 6px;
	background: #fff; color: #666; font-size: 13px; font-weight: 500; cursor: pointer;
	display: flex; align-items: center; justify-content: center; transition: all .15s;
}
.pagination-btn:hover:not(:disabled) { border-color: #ccc; color: #333; background: #f9f9fb; }
.pagination-btn.active { background: #1a1a2e; color: #fff; border-color: #1a1a2e; }
.pagination-btn:disabled { opacity: 0.4; cursor: not-allowed; }
</style>
