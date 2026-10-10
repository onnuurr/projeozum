<script setup>
import { ref, computed, inject, reactive, watch } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { OzPageHeader, OzModuleTabs, OzTabs, OzCard, OzButton, OzTable, OzDropdown, OzDrawer, OzField, OzInput, OzIcon, useOzConfirm } from '@/Components/Oz'
import { useCan } from '@/composables/useCan'
import AttributeSettingsTab from '../Components/AttributeSettingsTab.vue'

defineOptions({ layout: AppLayout })

// Product modülünün tek ayar sayfası. Her ayar bir sekme; sekme verisi, kullanıcının o sekmeyi
// görme yetkisi yoksa backend'den null gelir ve sekme hiç çizilmez. Yeni ayar = yeni sekme.
const props = defineProps({
	tab: { type: String, default: null },
	sizes: { type: Array, default: null },
	colors: { type: Array, default: null },
	attributes: { type: Array, default: null },
})

const showToast = inject('showToast')
const confirm = useOzConfirm()
const { can } = useCan()

const canCreate = computed(() => can('variant-option.create'))
const canUpdate = computed(() => can('variant-option.update'))
const canDelete = computed(() => can('variant-option.delete'))

const tabs = computed(() => [
	...(props.sizes ? [{ key: 'sizes', label: 'Bedenler', icon: 'shirt', count: props.sizes.length }] : []),
	...(props.colors ? [{ key: 'colors', label: 'Renkler', icon: 'palette', count: props.colors.length }] : []),
	...(props.attributes ? [{ key: 'attributes', label: 'Özellikler', icon: 'sliders', count: props.attributes.length }] : []),
])

const activeTab = ref(tabs.value.some((t) => t.key === props.tab) ? props.tab : tabs.value[0]?.key ?? '')

// Sekme adres çubuğunda kalsın: yenileme / paylaşılan link aynı sekmeyi açar.
watch(activeTab, (key) => {
	const url = new URL(window.location.href)
	url.searchParams.set('tab', key)
	window.history.replaceState(window.history.state, '', url)
})

const sizeColumns = [
	{ key: 'name', label: 'Beden', sortable: true },
	{ key: 'variantCount', label: 'Varyant', align: 'right', sortable: true },
	{ key: 'sort_order', label: 'Sıra', align: 'right', sortable: true },
	{ key: 'actions', label: '', align: 'right', width: '48px', searchable: false },
]
const colorColumns = [
	{ key: 'name', label: 'Renk', sortable: true },
	{ key: 'variantCount', label: 'Varyant', align: 'right', sortable: true },
	{ key: 'sort_order', label: 'Sıra', align: 'right', sortable: true },
	{ key: 'actions', label: '', align: 'right', width: '48px', searchable: false },
]

const rowActions = computed(() => [
	...(canUpdate.value ? [{ key: 'edit', label: 'Düzenle', icon: 'edit' }] : []),
	...(canDelete.value ? [{ key: 'remove', label: 'Sil', icon: 'trash', danger: true }] : []),
])

const TYPES = {
	size: { label: 'Beden', url: '/products/settings/sizes' },
	color: { label: 'Renk', url: '/products/settings/colors' },
}

function onAction(type, key, row) {
	if (key === 'edit') edit(type, row)
	else if (key === 'remove') confirmDelete(type, row)
}

/* ── Form state ── */
const formOpen = ref(false)
const formType = ref('size')
const editing = ref(null)
const busy = ref(false)
const errors = ref({})
const form = reactive({ name: '', hex: '#888888', sort_order: null })
// Yeni kayıt: Özellikler sekmesindeki seçenek girişi gibi virgülle birden çok ad yazılıp listeye
// eklenir, hepsi tek istekte (bulk-store) listenin sonuna kaydedilir. Düzenleme tek kayıt üzerinden.
const DEFAULT_HEX = '#888888'
const items = ref([])
const newNames = ref('')

function resetForm() {
	editing.value = null
	errors.value = {}
	form.name = ''
	form.hex = DEFAULT_HEX
	form.sort_order = null
	items.value = []
	newNames.value = ''
}

function addItems() {
	const names = newNames.value.split(',').map((s) => s.trim()).filter(Boolean)
	for (const name of names) {
		if (!items.value.some((it) => it.name.toLocaleLowerCase('tr') === name.toLocaleLowerCase('tr'))) {
			items.value.push({ name, hex: DEFAULT_HEX })
		}
	}
	newNames.value = ''
}

function itemError(i) {
	return errors.value[`items.${i}.name`] || errors.value[`items.${i}.hex`] || ''
}

watch(formOpen, (open) => {
	if (!open) setTimeout(resetForm, 250)
})

function openNew(type) {
	resetForm()
	formType.value = type
	formOpen.value = true
}

function edit(type, row) {
	resetForm()
	formType.value = type
	editing.value = row
	form.name = row.name
	form.hex = row.hex ?? '#888888'
	form.sort_order = row.sort_order
	formOpen.value = true
}

function submit() {
	if (busy.value) return
	if (!editing.value) return submitNew()
	busy.value = true
	errors.value = {}
	const t = TYPES[formType.value]
	const opts = {
		preserveScroll: true,
		preserveState: true,
		onSuccess: () => {
			formOpen.value = false
			showToast?.({ type: 'success', title: editing.value ? `${t.label} güncellendi` : `${t.label} eklendi`, message: form.name })
		},
		onError: (errs) => {
			errors.value = errs
			showToast?.({ type: 'error', title: 'Kayıt başarısız', message: Object.values(errs)[0] || 'Doğrulama hatası.' })
		},
		onFinish: () => { busy.value = false },
	}
	const payload = { name: form.name, sort_order: form.sort_order === '' ? null : form.sort_order }
	if (formType.value === 'color') payload.hex = form.hex
	if (editing.value) router.put(`${t.url}/${editing.value.id}`, payload, opts)
	else router.post(t.url, payload, opts)
}

function submitNew() {
	// Kutuda kalan, "Ekle"ye basılmamış yazı da kaydedilsin.
	if (newNames.value.trim()) addItems()
	const t = TYPES[formType.value]
	if (!items.value.length) {
		errors.value = { items: `En az bir ${t.label.toLocaleLowerCase('tr')} yazın.` }
		return
	}
	busy.value = true
	errors.value = {}
	const isColor = formType.value === 'color'
	const payload = { items: items.value.map((it) => (isColor ? { name: it.name, hex: it.hex } : { name: it.name })) }
	const count = payload.items.length
	router.post(`${t.url}/bulk-store`, payload, {
		preserveScroll: true,
		preserveState: true,
		onSuccess: () => {
			formOpen.value = false
			showToast?.({
				type: 'success',
				title: `${t.label} eklendi`,
				message: count > 1 ? `${count} kayıt eklendi.` : payload.items[0].name,
			})
		},
		onError: (errs) => {
			errors.value = errs
			showToast?.({ type: 'error', title: 'Kayıt başarısız', message: Object.values(errs)[0] || 'Doğrulama hatası.' })
		},
		onFinish: () => { busy.value = false },
	})
}

async function confirmDelete(type, row) {
	const t = TYPES[type]
	const usage = row.variantCount > 0
		? ` ${row.variantCount} varyantta kullanılıyor; o varyantlar etkilenmez, yalnızca yeni ürünlerde listede çıkmaz. Mağazada (Bagisto) ürünlerde kullanıldığı için seçenek orada kalır.`
		: ''
	const ok = await confirm.danger(`${t.label} Sil`, `“${row.name}” seçim listesinden kaldırılacak.${usage}`)
	if (!ok) return
	router.delete(`${t.url}/${row.id}`, {
		preserveScroll: true,
		preserveState: true,
		onSuccess: () => showToast?.({ type: 'warning', title: `${t.label} silindi`, message: row.name }),
		onError: (errs) => showToast?.({ type: 'error', title: 'Silme başarısız', message: Object.values(errs)[0] || 'Sunucu hatası.' }),
	})
}

/* ── Toplu seçim (OzTable selectable; toplu işlem çubuğu tablonun #bulk slot'unda) ── */
const selected = reactive({ size: [], color: [] })
const bulkBusy = ref(false)

// Sekme değişince diğer sekmenin seçimi temizlenir (yüzen çubuk yanlış tabloya ait kalmasın).
watch(activeTab, () => { selected.size = []; selected.color = [] })

async function bulkDelete(type) {
	const t = TYPES[type]
	const rows = (type === 'size' ? props.sizes : props.colors).filter((r) => selected[type].includes(r.id))
	if (!rows.length) return
	const used = rows.filter((r) => r.variantCount > 0).length
	const usage = used ? ` Bunlardan ${used} tanesi varyantlarda kullanılıyor; o varyantlar etkilenmez.` : ''
	const ok = await confirm.danger(`${t.label} Sil`, `${rows.length} ${t.label.toLowerCase()} seçim listesinden kaldırılacak.${usage}`)
	if (!ok) return

	bulkBusy.value = true
	router.post(`${t.url}/bulk-destroy`, { ids: rows.map((r) => r.id) }, {
		preserveScroll: true,
		preserveState: true,
		onSuccess: () => {
			showToast?.({ type: 'warning', title: `${t.label} silindi`, message: `${rows.length} kayıt kaldırıldı.` })
			selected[type] = []
		},
		onError: (errs) => showToast?.({ type: 'error', title: 'Silme başarısız', message: Object.values(errs)[0] || 'Sunucu hatası.' }),
		onFinish: () => { bulkBusy.value = false },
	})
}

const renameWarning = computed(() =>
	editing.value && editing.value.variantCount > 0 && form.name.trim() !== editing.value.name,
)
</script>

<template>
	<Head title="Ürün Ayarları" />

	<OzPageHeader title="Ürün Ayarları">
		<template #subtitle>Ürün modülüne ait ayarlar ve seçim listeleri</template>
	</OzPageHeader>
	<OzModuleTabs />

	<template v-if="tabs.length">
		<OzTabs v-model="activeTab" :tabs="tabs" aria-label="Ürün ayarları" class="settings-tabs" />

		<OzCard v-if="activeTab === 'sizes'" title="Bedenler" subtitle="Ürün formunda varyant oluştururken seçilen bedenler · mağazadaki (Bagisto) beden seçenekleri buradan yönetilir">
			<template v-if="canCreate" #actions>
				<OzButton size="sm" icon="plus" @click="openNew('size')">Beden Ekle</OzButton>
			</template>
			<OzTable v-model:selected="selected.size" :columns="sizeColumns" :rows="sizes" row-key="id" :selectable="canDelete" item-label="beden" empty-title="Beden yok" searchable search-placeholder="Beden ara…">
				<template #bulk>
					<OzButton variant="danger" size="sm" icon="trash" :loading="bulkBusy" @click="bulkDelete('size')">Seçilenleri Sil ({{ selected.size.length }})</OzButton>
				</template>
				<template #cell-name="{ row }"><span class="size-chip">{{ row.name }}</span></template>
				<template #cell-variantCount="{ value }"><span class="count">{{ value }}</span></template>
				<template v-if="rowActions.length" #cell-actions="{ row }">
					<OzDropdown :items="rowActions" :label="`${row.name} işlemleri`" @select="(k) => onAction('size', k, row)" />
				</template>
			</OzTable>
		</OzCard>

		<OzCard v-else-if="activeTab === 'colors'" title="Renkler" subtitle="Ürün formunda varyant oluştururken seçilen renkler · mağazadaki (Bagisto) renk seçenekleri buradan yönetilir">
			<template v-if="canCreate" #actions>
				<OzButton size="sm" icon="plus" @click="openNew('color')">Renk Ekle</OzButton>
			</template>
			<OzTable v-model:selected="selected.color" :columns="colorColumns" :rows="colors" row-key="id" :selectable="canDelete" item-label="renk" empty-title="Renk yok" searchable search-placeholder="Renk ara…">
				<template #bulk>
					<OzButton variant="danger" size="sm" icon="trash" :loading="bulkBusy" @click="bulkDelete('color')">Seçilenleri Sil ({{ selected.color.length }})</OzButton>
				</template>
				<template #cell-name="{ row }">
					<div class="color-cell">
						<span class="swatch" :style="{ background: row.hex }"></span>
						<span class="color-name">{{ row.name }}</span>
						<span class="hex">{{ row.hex }}</span>
					</div>
				</template>
				<template #cell-variantCount="{ value }"><span class="count">{{ value }}</span></template>
				<template v-if="rowActions.length" #cell-actions="{ row }">
					<OzDropdown :items="rowActions" :label="`${row.name} işlemleri`" @select="(k) => onAction('color', k, row)" />
				</template>
			</OzTable>
		</OzCard>

		<AttributeSettingsTab v-else-if="activeTab === 'attributes'" :attributes="attributes" />
	</template>

	<OzDrawer
		v-model:open="formOpen"
		:title="(editing ? `${TYPES[formType].label} Düzenle` : `Yeni ${TYPES[formType].label}`)"
		:subtitle="editing ? editing.name : 'Ürün formundaki seçim listesine eklenir'"
		:icon="editing ? 'edit' : 'plus'"
		size="md"
		:persistent="busy"
	>
		<form class="form" @submit.prevent="submit">
			<OzField v-if="!editing" :label="formType === 'color' ? 'Renkler' : 'Bedenler'" required :error="errors.items" :hint="formType === 'color' ? 'Her rengin kodunu listeden seçin' : 'Girdiğiniz sırayla listenin sonuna eklenir'">
				<ul v-if="items.length" class="item-list">
					<li v-for="(it, i) in items" :key="i" class="item-row">
						<template v-if="formType === 'color'">
							<OzInput v-model="it.hex" type="color" class="hex-picker" :aria-label="`${it.name} rengi`" />
							<div class="item-input">
								<OzInput v-model="it.name" :invalid="!!itemError(i)" :aria-label="`Renk ${i + 1}`" />
								<span v-if="itemError(i)" class="item-error">{{ itemError(i) }}</span>
							</div>
							<OzInput v-model="it.hex" class="mono item-hex" :aria-label="`${it.name} renk kodu`" />
						</template>
						<div v-else class="item-input">
							<OzInput v-model="it.name" :invalid="!!itemError(i)" :aria-label="`Beden ${i + 1}`" />
							<span v-if="itemError(i)" class="item-error">{{ itemError(i) }}</span>
						</div>
						<button type="button" class="item-remove" title="Kaldır" @click="items.splice(i, 1)"><OzIcon name="trash" :size="13" /></button>
					</li>
				</ul>
				<div class="item-add">
					<OzInput v-model="newNames" :placeholder="formType === 'color' ? 'örn. Haki, Bordo, Lacivert (virgülle birden çok)' : 'örn. S, M, L, XL (virgülle birden çok)'" @keydown.enter.prevent="addItems" />
					<OzButton size="sm" icon="plus" :disabled="!newNames.trim()" @click="addItems">Listeye Ekle</OzButton>
				</div>
			</OzField>

			<OzField v-if="editing" :label="`${TYPES[formType].label} Adı`" required :error="errors.name">
				<OzInput v-model="form.name" :placeholder="formType === 'color' ? 'örn. Haki' : 'örn. 3XL'" />
			</OzField>
			<OzField v-if="editing && formType === 'color'" label="Renk Kodu" required :error="errors.hex">
				<div class="hex-row">
					<OzInput v-model="form.hex" type="color" class="hex-picker" aria-label="Renk seç" />
					<OzInput v-model="form.hex" class="mono grow" placeholder="#1e3a8a" />
				</div>
			</OzField>
			<OzField v-if="editing" label="Sıra" :error="errors.sort_order">
				<OzInput v-model.number="form.sort_order" type="number" min="0" placeholder="Otomatik" />
			</OzField>
			<p v-if="renameWarning" class="warn">
				Bu {{ TYPES[formType].label.toLowerCase() }} {{ editing.variantCount }} varyantta “{{ editing.name }}” olarak kayıtlı. Ad değişikliği bu varyantlara da uygulanır ve ürünler mağazaya (Bagisto) yeniden gönderilir.
			</p>
			<button type="submit" hidden />
		</form>
		<template #footer>
			<OzButton :disabled="busy" @click="formOpen = false">İptal</OzButton>
			<OzButton variant="primary" :icon="editing ? 'check' : 'plus'" :loading="busy" @click="submit">{{ editing ? 'Kaydet' : 'Ekle' }}</OzButton>
		</template>
	</OzDrawer>
</template>

<style scoped>
.settings-tabs { margin-bottom: 14px; }
.size-chip { display: inline-block; min-width: 34px; padding: 3px 9px; text-align: center; border: 1px solid var(--oz-border); border-radius: 7px; font-weight: 700; font-size: 12px; color: var(--oz-ink-soft); }
.color-cell { display: flex; align-items: center; gap: 10px; }
.swatch { width: 20px; height: 20px; border-radius: 6px; border: 1px solid var(--oz-border); flex-shrink: 0; }
.color-name { font-weight: 700; color: var(--oz-ink-soft); font-size: 12.5px; }
.hex { font-size: 10.5px; color: var(--oz-muted); font-family: ui-monospace, monospace; }
.count { display: inline-block; padding: 2px 9px; background: var(--oz-surface-2); color: var(--oz-muted); border-radius: 6px; font-size: 11.5px; font-weight: 700; font-family: ui-monospace, monospace; }
.form { display: flex; flex-direction: column; gap: 14px; }
.hex-row { display: flex; gap: 8px; align-items: center; }
.hex-picker { width: 56px; flex-shrink: 0; }
.grow { flex: 1; }
.mono { font-family: ui-monospace, monospace; }
.item-list { list-style: none; margin: 0 0 8px; padding: 0; display: flex; flex-direction: column; gap: 6px; }
.item-row { display: flex; align-items: flex-start; gap: 8px; }
.item-input { flex: 1; display: flex; flex-direction: column; gap: 2px; }
.item-error { color: var(--oz-danger); font-size: 11px; }
.item-hex { width: 96px; flex-shrink: 0; }
.item-remove { margin-top: 8px; background: none; border: none; cursor: pointer; color: var(--oz-muted); padding: 2px 4px; border-radius: 5px; display: inline-flex; }
.item-remove:hover { background: var(--oz-danger-soft); color: var(--oz-danger); }
.item-add { display: flex; gap: 8px; align-items: center; }
.item-add > :first-child { flex: 1; }
.warn { margin: 0; padding: 10px 12px; border-radius: 9px; background: var(--oz-warning-soft, var(--oz-surface-2)); color: var(--oz-ink-soft); font-size: 12px; }
</style>
