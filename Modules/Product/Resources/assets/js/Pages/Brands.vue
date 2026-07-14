<template>
	<Head title="Markalar" />
	<div class="page-brands">
		<Breadcrumb
			:items="[
				{ label: 'Ana Sayfa', to: '/workflow', icon: 'home' },
				{ label: 'Katalog', to: '/products' },
				{ label: 'Markalar' },
			]"
		/>

		<div class="page-header">
			<div>
				<h1 class="page-title">Markalar</h1>
				<p class="page-subtitle"><strong>{{ brands.length }}</strong> marka kayıtlı</p>
			</div>
			<button v-if="canManage" class="btn btn-primary btn-with-icon" @click="openNew">
				<svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
					<path d="M12 5v14M5 12h14" />
				</svg>
				Yeni Marka
			</button>
		</div>

		<div class="card">
			<div class="card-header">
				<h3>Marka Listesi</h3>
				<div class="card-search">
					<svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
						<circle cx="11" cy="11" r="8" /><path d="M21 21l-4.35-4.35" />
					</svg>
					<input v-model="searchQuery" type="text" placeholder="Marka ara..." />
				</div>
			</div>

			<div class="table-scroll">
			<table class="data-table">
				<thead>
					<tr>
						<th style="width: 50%">Marka</th>
						<th style="width: 15%">Ürün Sayısı</th>
						<th style="width: 15%">Sıra</th>
						<th style="width: 10%">Güncelleme</th>
						<th style="width: 10%">İşlemler</th>
					</tr>
				</thead>
				<tbody>
					<tr v-if="filtered.length === 0">
						<td colspan="5" class="empty-row">Kayıt bulunamadı</td>
					</tr>
					<tr v-for="b in filtered" :key="b.id">
						<td>
							<div class="brand-cell">
								<div class="brand-logo">{{ b.name.charAt(0) }}</div>
								<div class="brand-info">
									<span class="brand-name">{{ b.name }}</span>
									<span class="brand-slug">{{ b.slug }}</span>
								</div>
							</div>
						</td>
						<td>
							<span class="product-count">{{ b.productCount }}</span>
						</td>
						<td>{{ b.sort_order }}</td>
						<td class="dim">{{ b.updatedAt }}</td>
						<td>
							<div class="table-actions">
								<button v-if="canManage" class="table-action-btn view" @click="edit(b)" title="Düzenle">✏️</button>
								<button v-if="canManage" class="table-action-btn delete" @click="confirmDelete(b)" title="Sil">🗑️</button>
							</div>
						</td>
					</tr>
				</tbody>
			</table>
			</div>
		</div>

		<!-- Form modal -->
		<AppModal v-model="formOpen" :title="editing ? 'Markayı Düzenle' : 'Yeni Marka'" size="md" variant="info">
			<form class="form-grid" @submit.prevent="submit">
				<div class="form-row">
					<label class="form-label">Marka Adı <span class="req">*</span></label>
					<input v-model="form.name" type="text" class="form-input" placeholder="örn. Nike" @input="onNameInput" />
					<span v-if="errors.name" class="form-error">{{ errors.name }}</span>
				</div>
				<div class="form-row">
					<label class="form-label">Slug <span class="req">*</span></label>
					<input v-model="form.slug" type="text" class="form-input" placeholder="nike" @input="slugTouched = true" />
					<span v-if="errors.slug" class="form-error">{{ errors.slug }}</span>
				</div>
				<div class="form-row">
					<label class="form-label">Sıra</label>
					<input v-model.number="form.sort_order" type="number" min="0" class="form-input" placeholder="0" />
					<span v-if="errors.sort_order" class="form-error">{{ errors.sort_order }}</span>
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
	brands: { type: Array, default: () => [] },
})

const showToast = inject('showToast')
const $swal = inject('$swal')
const page = usePage()

const canManage = computed(() => (page.props.auth?.permissions ?? []).includes('brand.manage'))

const searchQuery = ref('')
const filtered = computed(() => {
	const q = searchQuery.value.trim().toLowerCase()
	if (!q) return props.brands
	return props.brands.filter(b =>
		b.name.toLowerCase().includes(q) || b.slug.toLowerCase().includes(q),
	)
})

/* ── Form state ── */
const formOpen = ref(false)
const editing = ref(null)
const busy = ref(false)
const errors = ref({})
const slugTouched = ref(false)
const form = reactive({ name: '', slug: '', sort_order: 0 })

function slugify(text) {
	const map = { ç: 'c', Ç: 'c', ğ: 'g', Ğ: 'g', ı: 'i', İ: 'i', ö: 'o', Ö: 'o', ş: 's', Ş: 's', ü: 'u', Ü: 'u' }
	return text
		.replace(/[çÇğĞıİöÖşŞüÜ]/g, ch => map[ch] || ch)
		.toLowerCase()
		.replace(/[^a-z0-9]+/g, '-')
		.replace(/(^-|-$)/g, '')
}

function onNameInput() {
	if (!slugTouched.value) form.slug = slugify(form.name)
}

watch(formOpen, (open) => {
	if (!open) {
		setTimeout(() => {
			editing.value = null
			errors.value = {}
			form.name = ''
			form.slug = ''
			form.sort_order = 0
			slugTouched.value = false
		}, 250)
	}
})

function openNew() {
	editing.value = null
	errors.value = {}
	form.name = ''
	form.slug = ''
	form.sort_order = 0
	slugTouched.value = false
	formOpen.value = true
}

function edit(b) {
	editing.value = b
	errors.value = {}
	form.name = b.name
	form.slug = b.slug
	form.sort_order = b.sort_order
	slugTouched.value = true
	formOpen.value = true
}

function submit() {
	if (busy.value) return
	busy.value = true
	errors.value = {}
	const opts = {
		preserveScroll: true,
		preserveState: true,
		onSuccess: () => {
			formOpen.value = false
			showToast?.({
				type: 'success',
				title: editing.value ? 'Marka güncellendi' : 'Marka eklendi',
				message: form.name,
			})
		},
		onError: (errs) => {
			errors.value = errs
			showToast?.({
				type: 'error',
				title: 'Kayıt başarısız',
				message: Object.values(errs)[0] || 'Doğrulama hatası.',
			})
		},
		onFinish: () => { busy.value = false },
	}
	const payload = { name: form.name, slug: form.slug, sort_order: form.sort_order }
	if (editing.value) {
		router.put(`/products/brands/${editing.value.id}`, payload, opts)
	} else {
		router.post('/products/brands', payload, opts)
	}
}

async function confirmDelete(b) {
	if (b.productCount > 0) {
		await $swal.fire({
			icon: 'warning',
			title: 'Silinemez',
			text: `${b.name} markasına bağlı ${b.productCount} ürün var. Önce ürünlerin markasını değiştirin.`,
		})
		return
	}
	const ok = await $swal.dangerConfirm({
		title: 'Markayı Sil',
		html: `<strong>${b.name}</strong> silinecek. Bu işlem geri alınabilir (soft delete).`,
		confirmText: 'Sil',
		cancelText: 'Vazgeç',
	})
	if (!ok) return
	router.delete(`/products/brands/${b.id}`, {
		preserveScroll: true,
		preserveState: true,
		onSuccess: () => {
			showToast?.({ type: 'warning', title: 'Marka silindi', message: b.name })
		},
		onError: (errs) => {
			showToast?.({ type: 'error', title: 'Silme başarısız', message: Object.values(errs)[0] || 'Sunucu hatası.' })
		},
	})
}
</script>

<style scoped>
.page-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 20px; gap: 16px; }
.page-title { font-size: 22px; font-weight: 700; color: #1a1a2e; line-height: 1.2; }
.page-subtitle { font-size: 13px; color: #888; margin-top: 4px; }

.card { background: #fff; border-radius: 16px; border: 1px solid #ebebf0; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,.04); }
.card-header { padding: 14px 18px; display: flex; align-items: center; gap: 12px; border-bottom: 1px solid #f0f0f5; }
.card-header h3 { font-size: 15px; font-weight: 700; color: #1a1a2e; }
.card-search { display: flex; align-items: center; gap: 6px; background: #f5f5f8; border: 1px solid #e8e8f0; border-radius: 8px; padding: 5px 10px; margin-left: auto; min-width: 220px; }
.card-search svg { color: #aaa; flex-shrink: 0; }
.card-search input { border: none; background: none; outline: none; font-family: inherit; font-size: 13px; color: #1a1a2e; width: 100%; }
.card-search input::placeholder { color: #bbb; }

.data-table { width: 100%; border-collapse: separate; border-spacing: 0; }
.data-table thead tr { background: #f8f8fc; }
.data-table th { text-align: left; padding: 12px 16px; font-size: 11px; font-weight: 600; color: #aaa; border-bottom: 1px solid #f0f0f5; text-transform: uppercase; letter-spacing: 0.04em; }
.data-table td { padding: 12px 16px; font-size: 13px; color: #444; border-bottom: 1px solid #f5f5f8; vertical-align: middle; }
.data-table tr:last-child td { border-bottom: none; }
.data-table tr:hover td { background: #fafafe; }
.empty-row { text-align: center !important; color: #aaa; padding: 32px 0 !important; font-style: italic; }
.dim { font-size: 12px; color: #888; }

.brand-cell { display: flex; align-items: center; gap: 12px; }
.brand-logo {
	width: 36px; height: 36px; border-radius: 10px;
	background: linear-gradient(135deg, rgb(var(--color-primary-soft)), #ddd6fe);
	display: flex; align-items: center; justify-content: center;
	font-size: 16px; font-weight: 800; color: rgb(var(--color-primary)); flex-shrink: 0;
}
.brand-info { display: flex; flex-direction: column; gap: 2px; }
.brand-name { font-weight: 600; color: #1a1a2e; font-size: 13px; }
.brand-slug { font-size: 10.5px; color: #888; font-family: 'SF Mono', Menlo, Consolas, monospace; }

.product-count { display: inline-block; padding: 2px 9px; background: #f0f0f5; color: #555; border-radius: 6px; font-size: 11.5px; font-weight: 700; font-family: 'SF Mono', Menlo, Consolas, monospace; }

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
</style>
