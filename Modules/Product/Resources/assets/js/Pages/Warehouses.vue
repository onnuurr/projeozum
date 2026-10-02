<script setup>
import { ref, computed, inject, reactive, watch } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import { MapPin } from 'lucide-vue-next'
import AppLayout from '@/Layouts/AppLayout.vue'
import { OzPageHeader, OzModuleTabs, OzStatStrip, OzIcon, OzPill, OzDropdown, OzEmptyState, OzButton, OzDrawer, OzField, OzInput, OzTextarea, OzSwitch, useOzConfirm } from '@/Components/Oz'

defineOptions({ layout: AppLayout })

const props = defineProps({
	warehouses: { type: Array, default: () => [] },
})

const showToast = inject('showToast')
const confirm = useOzConfirm()
const page = usePage()

const canManage = computed(() => (page.props.auth?.permissions ?? []).includes('warehouse.manage'))

const activeCount = computed(() => props.warehouses.filter((w) => w.is_active).length)
const stats = computed(() => [
	{ key: 'total', label: 'Toplam Depo', value: props.warehouses.length, icon: 'warehouse', tone: 'accent' },
	{ key: 'active', label: 'Aktif', value: activeCount.value, icon: 'success', tone: 'success' },
	{ key: 'stock', label: 'Toplam Stok', value: props.warehouses.reduce((sum, w) => sum + w.totalStock, 0), icon: 'package', tone: 'info' },
	{ key: 'variants', label: 'Toplam Varyant', value: props.warehouses.reduce((sum, w) => sum + w.stockCount, 0), icon: 'tag', tone: 'warning' },
])

const rowActions = () => [
	{ key: 'edit', label: 'Düzenle', icon: 'edit' },
	{ key: 'remove', label: 'Sil', icon: 'trash', danger: true },
]
function onAction(key, w) {
	if (key === 'edit') edit(w)
	else if (key === 'remove') confirmDelete(w)
}

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
		await confirm.warning('Silinemez', `${w.name} deposunda ${w.totalStock} adet stok var. Önce stokları başka depoya transfer edin.`, { cancelText: 'Kapat', confirmText: 'Tamam' })
		return
	}
	const ok = await confirm.danger('Depoyu Sil', `“${w.name}” (${w.code}) silinecek.`)
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

<template>
	<Head title="Depolar" />

	<OzPageHeader title="Depolar">
		<template #subtitle><b>{{ warehouses.length }}</b> depo kayıtlı</template>
		<template #actions>
			<OzButton v-if="canManage" variant="primary" icon="plus" @click="openNew">Yeni Depo</OzButton>
		</template>
	</OzPageHeader>
	<OzModuleTabs />

	<OzStatStrip v-if="warehouses.length" :items="stats" />

	<OzEmptyState v-if="!warehouses.length" icon="warehouse" title="Henüz depo eklenmemiş" />
	<div v-else class="cards-grid">
		<div v-for="w in warehouses" :key="w.id" class="warehouse-card">
			<div class="card-top">
				<div class="warehouse-icon"><OzIcon name="warehouse" :size="20" /></div>
				<div v-if="canManage" @click.stop>
					<OzDropdown :items="rowActions()" :label="`${w.name} işlemleri`" @select="(k) => onAction(k, w)" />
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
				<OzPill :tone="w.is_active ? 'success' : 'neutral'">{{ w.is_active ? 'Aktif' : 'Pasif' }}</OzPill>
			</div>
		</div>
	</div>

	<OzDrawer v-model:open="formOpen" :title="editing ? 'Depoyu Düzenle' : 'Yeni Depo'" subtitle="Depo bilgilerini girin" :icon="editing ? 'edit' : 'plus'" size="md" :persistent="busy">
		<form class="form" @submit.prevent="submit">
			<OzField label="Depo Adı" required :error="errors.name">
				<OzInput v-model="form.name" placeholder="örn. Ana Depo" />
			</OzField>
			<OzField label="Kod" required hint="Yalnızca büyük harf, rakam, tire ve alt çizgi." :error="errors.code">
				<OzInput v-model="form.code" class="mono" placeholder="ANA01" @input="form.code = form.code.toUpperCase()" />
			</OzField>
			<OzField label="Şehir" :error="errors.city">
				<OzInput v-model="form.city" placeholder="örn. İstanbul" />
			</OzField>
			<OzField label="Adres" :error="errors.address">
				<OzTextarea v-model="form.address" :rows="3" placeholder="Açık adres…" />
			</OzField>
			<OzField label="Durum">
				<OzSwitch v-model="form.is_active" label="Aktif" />
			</OzField>
			<button type="submit" hidden />
		</form>
		<template #footer>
			<OzButton :disabled="busy" @click="formOpen = false">İptal</OzButton>
			<OzButton variant="primary" :icon="editing ? 'check' : 'plus'" :loading="busy" @click="submit">{{ editing ? 'Kaydet' : 'Ekle' }}</OzButton>
		</template>
	</OzDrawer>
</template>

<style scoped>

.mono { font-family: ui-monospace, monospace; }
.cards-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 14px; }

.warehouse-card {
	background: var(--oz-surface);
	border-radius: var(--oz-radius);
	border: 1px solid var(--oz-border);
	padding: 16px;
	box-shadow: var(--oz-shadow-sm);
	display: flex;
	flex-direction: column;
	gap: 12px;
	transition: transform .15s, box-shadow .15s;
}
.warehouse-card:hover { transform: translateY(-1px); box-shadow: var(--oz-shadow); }

.card-top { display: flex; justify-content: space-between; align-items: flex-start; }
.warehouse-icon {
	width: 40px; height: 40px; border-radius: 11px;
	background: var(--oz-accent-soft); color: var(--oz-accent);
	display: flex; align-items: center; justify-content: center;
}

.warehouse-info { display: flex; flex-direction: column; gap: 3px; }
.warehouse-name { font-size: 14px; font-weight: 700; color: var(--oz-ink-soft); }
.warehouse-code { font-size: 11px; color: var(--oz-muted); font-family: ui-monospace, monospace; letter-spacing: 0.04em; }
.warehouse-city { display: flex; align-items: center; gap: 4px; font-size: 12px; color: var(--oz-muted); margin-top: 2px; }

.card-stats { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; padding: 12px; background: var(--oz-surface-2); border-radius: 10px; }
.stat { text-align: center; }
.stat-value { font-size: 17px; font-weight: 800; color: var(--oz-ink-soft); font-variant-numeric: tabular-nums; }
.stat-label { font-size: 10.5px; color: var(--oz-muted); margin-top: 2px; text-transform: uppercase; letter-spacing: 0.04em; }

.card-footer { display: flex; justify-content: flex-end; }
.form { display: flex; flex-direction: column; gap: 14px; }
</style>
