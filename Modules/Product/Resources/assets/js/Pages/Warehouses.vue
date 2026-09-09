<template>
	<Head title="Depolar" />
	<div class="page-warehouses">
		<Breadcrumb
			:items="[
				{ label: 'Ana Sayfa', to: '/workflow', icon: 'home' },
				{ label: 'Katalog', to: '/products' },
				{ label: 'Depolar' },
			]"
		/>

		<PageHeader title="Depolar">
			<template #subtitle><strong>{{ warehouses.length }}</strong> depo kayıtlı</template>
			<template #actions>
				<Button v-if="canManage" variant="primary" with-icon @click="openNew">
					<template #leading><Plus :size="13" /></template>
					Yeni Depo
				</Button>
			</template>
		</PageHeader>

		<div class="cards-grid">
			<div v-if="warehouses.length === 0" class="empty-state">
				<div class="empty-icon"><Warehouse :size="36" /></div>
				<div class="empty-text">Henüz depo eklenmemiş.</div>
			</div>
			<div v-for="w in warehouses" :key="w.id" class="warehouse-card">
				<div class="card-top">
					<div class="warehouse-icon"><Warehouse :size="20" /></div>
					<div class="card-actions">
						<button v-if="canManage" class="icon-btn" @click="edit(w)" title="Düzenle"><Pencil :size="13" /></button>
						<button v-if="canManage" class="icon-btn delete" @click="confirmDelete(w)" title="Sil"><Trash2 :size="13" /></button>
					</div>
				</div>
				<div class="warehouse-info">
					<div class="warehouse-name">{{ w.name }}</div>
					<div class="warehouse-code">{{ w.code }}</div>
					<div v-if="w.city" class="warehouse-city"><MapPin :size="11" /> {{ w.city }}</div>
				</div>
				<div class="card-stats">
					<div class="stat">
						<div class="stat-value">{{ w.totalStock.toLocaleString('tr-TR') }}</div>
						<div class="stat-label">Toplam Stok</div>
					</div>
					<div class="stat">
						<div class="stat-value">{{ w.stockCount }}</div>
						<div class="stat-label">Varyant</div>
					</div>
				</div>
				<div class="card-footer">
					<span class="status-pill" :class="w.is_active ? 'status-active' : 'status-passive'">
						<span class="dot"></span>
						{{ w.is_active ? 'Aktif' : 'Pasif' }}
					</span>
				</div>
			</div>
		</div>

		<AppDrawer v-model="formOpen" :title="editing ? 'Depoyu Düzenle' : 'Yeni Depo'" subtitle="Depo bilgilerini girin" variant="info" width="480px">
			<form class="form-grid" @submit.prevent="submit">
				<div class="form-row">
					<label class="form-label">Depo Adı <span class="req">*</span></label>
					<input v-model="form.name" type="text" class="form-input" placeholder="örn. Ana Depo" />
					<span v-if="errors.name" class="form-error">{{ errors.name }}</span>
				</div>
				<div class="form-row">
					<label class="form-label">Kod <span class="req">*</span></label>
					<input v-model="form.code" type="text" class="form-input mono-input" placeholder="ANA01" @input="form.code = form.code.toUpperCase()" />
					<span class="form-hint">Yalnızca büyük harf, rakam, tire ve alt çizgi.</span>
					<span v-if="errors.code" class="form-error">{{ errors.code }}</span>
				</div>
				<div class="form-row">
					<label class="form-label">Şehir</label>
					<input v-model="form.city" type="text" class="form-input" placeholder="örn. İstanbul" />
					<span v-if="errors.city" class="form-error">{{ errors.city }}</span>
				</div>
				<div class="form-row">
					<label class="form-label">Adres</label>
					<textarea v-model="form.address" class="form-input" rows="3" placeholder="Açık adres..." />
					<span v-if="errors.address" class="form-error">{{ errors.address }}</span>
				</div>
				<div class="form-row">
					<label class="form-check">
						<input v-model="form.is_active" type="checkbox" />
						<span>Aktif</span>
					</label>
				</div>
			</form>
			<template #footer="{ close }">
				<Button variant="ghost" :disabled="busy" @click="close">İptal</Button>
				<Button variant="primary" :loading="busy" @click="submit">
					{{ editing ? 'Kaydet' : 'Ekle' }}
				</Button>
			</template>
		</AppDrawer>
	</div>
</template>

<script setup>
import { ref, computed, inject, reactive, watch } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import { Plus, Pencil, Trash2, Warehouse, MapPin } from 'lucide-vue-next'
import AppLayout from '@/Layouts/AppLayout.vue'
import Breadcrumb from '@/Components/Breadcrumb.vue'
import PageHeader from '@/Components/PageHeader.vue'
import Button from '@/Components/Button.vue'
import AppDrawer from '@/Components/AppDrawer.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
	warehouses: { type: Array, default: () => [] },
})

const showToast = inject('showToast')
const $swal = inject('$swal')
const page = usePage()

const canManage = computed(() => (page.props.auth?.permissions ?? []).includes('warehouse.manage'))

const formOpen = ref(false)
const editing = ref(null)
const busy = ref(false)
const errors = ref({})
const form = reactive({ name: '', code: '', city: '', address: '', is_active: true })

watch(formOpen, (open) => {
	if (!open) {
		setTimeout(() => {
			editing.value = null
			errors.value = {}
			Object.assign(form, { name: '', code: '', city: '', address: '', is_active: true })
		}, 250)
	}
})

function openNew() {
	editing.value = null
	errors.value = {}
	Object.assign(form, { name: '', code: '', city: '', address: '', is_active: true })
	formOpen.value = true
}

function edit(w) {
	editing.value = w
	errors.value = {}
	Object.assign(form, {
		name: w.name,
		code: w.code,
		city: w.city ?? '',
		address: w.address ?? '',
		is_active: !!w.is_active,
	})
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
				title: editing.value ? 'Depo güncellendi' : 'Depo eklendi',
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
	const payload = { ...form }
	if (editing.value) {
		router.put(`/products/warehouses/${editing.value.id}`, payload, opts)
	} else {
		router.post('/products/warehouses', payload, opts)
	}
}

async function confirmDelete(w) {
	if (w.totalStock > 0) {
		await $swal.fire({
			icon: 'warning',
			title: 'Silinemez',
			text: `${w.name} deposunda ${w.totalStock} adet stok var. Önce stokları başka depoya transfer edin.`,
		})
		return
	}
	const ok = await $swal.dangerConfirm({
		title: 'Depoyu Sil',
		html: `<strong>${w.name}</strong> (${w.code}) silinecek.`,
		confirmText: 'Sil',
		cancelText: 'Vazgeç',
	})
	if (!ok) return
	router.delete(`/products/warehouses/${w.id}`, {
		preserveScroll: true,
		preserveState: true,
		onSuccess: () => {
			showToast?.({ type: 'warning', title: 'Depo silindi', message: w.name })
		},
		onError: (errs) => {
			showToast?.({ type: 'error', title: 'Silme başarısız', message: Object.values(errs)[0] || 'Sunucu hatası.' })
		},
	})
}
</script>

<style scoped>
.cards-grid {
	display: grid;
	grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
	gap: 16px;
}

.empty-state {
	grid-column: 1 / -1;
	background: #fff;
	border: 1px dashed #e0e0ea;
	border-radius: 16px;
	padding: 60px 20px;
	text-align: center;
}
.empty-icon { display: flex; justify-content: center; color: #c0c0d8; margin-bottom: 8px; }
.empty-text { color: #888; font-size: 13px; }

.warehouse-card {
	background: #fff;
	border-radius: 14px;
	border: 1px solid #ebebf0;
	padding: 18px;
	box-shadow: 0 1px 3px rgba(0,0,0,.04);
	display: flex;
	flex-direction: column;
	gap: 14px;
	transition: transform .15s, box-shadow .15s;
}
.warehouse-card:hover {
	transform: translateY(-1px);
	box-shadow: 0 4px 12px rgba(0,0,0,.06);
}

.card-top { display: flex; justify-content: space-between; align-items: flex-start; }
.warehouse-icon {
	width: 44px; height: 44px;
	border-radius: 12px;
	background: #fff7ed;
	color: #c2410c;
	display: flex; align-items: center; justify-content: center;
}
.card-actions { display: flex; gap: 4px; }
.icon-btn {
	display: inline-flex; align-items: center; justify-content: center;
	background: #f3f4f6; border: none; cursor: pointer;
	padding: 6px; border-radius: 6px;
	color: #6b7280; transition: all .15s;
}
.icon-btn:hover { background: rgb(var(--color-primary-soft-rgb)); color: rgb(var(--color-primary-rgb)); }
.icon-btn.delete:hover { background: #fee2e2; color: #dc2626; }

.warehouse-info { display: flex; flex-direction: column; gap: 4px; }
.warehouse-name { font-size: 15px; font-weight: 700; color: #1a1a2e; }
.warehouse-code {
	font-size: 11px; color: #888;
	font-family: 'SF Mono', Menlo, Consolas, monospace;
	letter-spacing: 0.04em;
}
.warehouse-city { display: flex; align-items: center; gap: 4px; font-size: 12px; color: #888; margin-top: 2px; }

.card-stats {
	display: grid;
	grid-template-columns: 1fr 1fr;
	gap: 12px;
	padding: 12px;
	background: #fafafe;
	border-radius: 10px;
}
.stat { text-align: center; }
.stat-value { font-size: 18px; font-weight: 700; color: #1a1a2e; }
.stat-label { font-size: 10.5px; color: #888; margin-top: 2px; text-transform: uppercase; letter-spacing: 0.04em; }

.card-footer { display: flex; justify-content: space-between; align-items: center; }
.status-pill {
	display: inline-flex; align-items: center; gap: 5px;
	padding: 3px 10px; border-radius: 999px;
	font-size: 11px; font-weight: 600;
}
.status-pill .dot { width: 5px; height: 5px; border-radius: 50%; background: currentColor; }
.status-active { background: #dcfce7; color: #16a34a; }
.status-passive { background: #fee2e2; color: #dc2626; }

.form-grid { display: flex; flex-direction: column; gap: 14px; }
.form-row { display: flex; flex-direction: column; gap: 6px; }
.form-label { font-size: 12px; font-weight: 600; color: #1a1a2e; }
.form-label .req { color: #ef4444; }
.form-input { padding: 9px 12px; border: 1px solid #e8e8f0; border-radius: 8px; font-family: inherit; font-size: 13px; color: #1a1a2e; background: #fff; outline: none; transition: border-color .15s; }
.form-input:focus { border-color: rgb(var(--color-primary-rgb)); }
.mono-input { font-family: 'SF Mono', Menlo, Consolas, monospace; letter-spacing: 0.04em; }
.form-hint { font-size: 11px; color: #888; }
.form-error { font-size: 11.5px; color: #ef4444; }
.form-check { display: inline-flex; align-items: center; gap: 8px; cursor: pointer; font-size: 13px; color: #1a1a2e; }
</style>
