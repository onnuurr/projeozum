<script setup>
import { ref, computed, inject, reactive, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import { OzCard, OzButton, OzTable, OzDropdown, OzDrawer, OzField, OzInput, OzSelect, OzSwitch, OzPill, OzIcon, OzIconPicker, OzAlert, useOzConfirm } from '@/Components/Oz'
import { customIconNames, iconPickerOptions, lucideIcon } from '@/lucideIcons.js'
import { useCan } from '@/composables/useCan'
import { ATTRIBUTE_TYPES, TYPE_LABELS, TYPE_TONES, FILTERABLE_TYPES, hasOptions } from '../productAttributeTypes.js'

// Ürün Ayarları → Özellikler sekmesi (product-attribute.*). Global ürün özellikleri: kategoriler
// hangilerinin geçerli olduğunu seçer (Kategoriler → Özellikler), ürün formunda doldurulur ve
// mağazaya (Bagisto) aynı kodla attribute + seçenek olarak gider. Kod ve tip sonradan değişmez.
const props = defineProps({
	attributes: { type: Array, required: true },
})

const showToast = inject('showToast')
const confirm = useOzConfirm()
const { can } = useCan()

const canCreate = computed(() => can('product-attribute.create'))
const canUpdate = computed(() => can('product-attribute.update'))
const canDelete = computed(() => can('product-attribute.delete'))

const BASE = '/products/settings/attributes'

const TYPE_OPTIONS = ATTRIBUTE_TYPES.map((t) => ({ value: t.value, label: t.label }))

const columns = [
	{ key: 'name', label: 'Özellik', sortable: true, searchValue: (r) => `${r.name} ${r.code} ${r.options.map((o) => o.name).join(' ')}` },
	{ key: 'type', label: 'Tip', sortable: true, sortValue: (r) => TYPE_LABELS[r.type], filterValue: (r) => TYPE_LABELS[r.type], filter: { type: 'select', label: 'Tip', options: Object.values(TYPE_LABELS) }, searchable: false },
	{ key: 'options', label: 'Seçenekler', searchable: false },
	{ key: 'categoryCount', label: 'Kategori', align: 'right', sortable: true },
	{ key: 'productCount', label: 'Ürün', align: 'right', sortable: true },
	{ key: 'is_filterable', label: 'Mağaza filtresi', sortable: true, searchable: false, filterValue: (r) => (r.is_filterable ? 'Evet' : 'Hayır'), filter: { type: 'select', label: 'Mağaza filtresi', options: ['Evet', 'Hayır'] } },
	{ key: 'actions', label: '', align: 'right', width: '48px', searchable: false },
]

const rowActions = computed(() => [
	...(canUpdate.value ? [{ key: 'edit', label: 'Düzenle', icon: 'edit' }] : []),
	...(canDelete.value ? [{ key: 'remove', label: 'Sil', icon: 'trash', danger: true }] : []),
])

function onAction(key, row) {
	if (key === 'edit') edit(row)
	else if (key === 'remove') confirmDelete(row)
}

/* ── Form ── */
const formOpen = ref(false)
const editing = ref(null)
const busy = ref(false)
const errors = ref({})
const codeTouched = ref(false)
const newOption = ref('')

// Seçenek ikonu (isteğe bağlı): ortak ikon kaydı, yıkama sembolleri (oz:bakim-*) önde.
// Yalnız özel (oz:) ikonlar mağazaya (Bagisto) seçenek görseli olarak da gider.
const optionIconOptions = iconPickerOptions(customIconNames.filter((n) => n.startsWith('oz:bakim-')))
const resolveIcon = (name) => lucideIcon(name) ?? (() => null)
const iconPickFor = ref(null)
const form = reactive({ name: '', code: '', type: 'select', is_filterable: true, options: [] })

// Bagisto attribute kodu: küçük harf, rakam, alt çizgi; harfle başlar
function toCode(text) {
	const map = { ç: 'c', Ç: 'c', ğ: 'g', Ğ: 'g', ı: 'i', İ: 'i', ö: 'o', Ö: 'o', ş: 's', Ş: 's', ü: 'u', Ü: 'u' }
	return text
		.replace(/[çÇğĞıİöÖşŞüÜ]/g, (ch) => map[ch] || ch)
		.toLowerCase()
		.replace(/[^a-z0-9]+/g, '_')
		.replace(/^[^a-z]+|_+$/g, '')
		.slice(0, 64)
}

function resetForm() {
	editing.value = null
	errors.value = {}
	codeTouched.value = false
	newOption.value = ''
	iconPickFor.value = null
	Object.assign(form, { name: '', code: '', type: 'select', is_filterable: true, options: [] })
}

watch(formOpen, (open) => {
	if (!open) setTimeout(resetForm, 250)
})

function onNameInput() {
	if (!editing.value && !codeTouched.value) form.code = toCode(form.name)
}

function openNew() {
	resetForm()
	formOpen.value = true
}

function edit(row) {
	resetForm()
	editing.value = row
	Object.assign(form, {
		name: row.name,
		code: row.code,
		type: row.type,
		is_filterable: row.is_filterable,
		options: row.options.map((o) => ({ id: o.id, name: o.name, icon: o.icon ?? '', productCount: o.productCount })),
	})
	formOpen.value = true
}

function addOption() {
	const names = newOption.value.split(',').map((s) => s.trim()).filter(Boolean)
	for (const name of names) {
		if (!form.options.some((o) => o.name.toLocaleLowerCase('tr') === name.toLocaleLowerCase('tr'))) {
			form.options.push({ id: null, name, icon: '', productCount: 0 })
		}
	}
	newOption.value = ''
}

function moveOption(i, dir) {
	const j = i + dir
	if (j < 0 || j >= form.options.length) return
	const list = form.options
	;[list[i], list[j]] = [list[j], list[i]]
}

function optionError(i) {
	return errors.value[`options.${i}.name`] || ''
}

const filterableAllowed = computed(() => FILTERABLE_TYPES.includes(form.type))

function submit() {
	if (busy.value) return
	if (newOption.value.trim()) addOption()
	busy.value = true
	errors.value = {}
	const payload = {
		name: form.name,
		is_filterable: filterableAllowed.value && form.is_filterable,
		options: hasOptions(form.type) ? form.options.map((o) => ({ id: o.id, name: o.name, icon: o.icon || null })) : [],
		...(editing.value ? {} : { code: form.code, type: form.type }),
	}
	const opts = {
		preserveScroll: true,
		preserveState: true,
		onSuccess: () => {
			formOpen.value = false
			showToast?.({ type: 'success', title: editing.value ? 'Özellik güncellendi' : 'Özellik eklendi', message: form.name })
		},
		onError: (errs) => {
			errors.value = errs
			showToast?.({ type: 'error', title: 'Kayıt başarısız', message: Object.values(errs)[0] || 'Doğrulama hatası.' })
		},
		onFinish: () => { busy.value = false },
	}
	if (editing.value) router.put(`${BASE}/${editing.value.id}`, payload, opts)
	else router.post(BASE, payload, opts)
}

async function confirmDelete(row) {
	if (row.productCount > 0) {
		await confirm.warning('Silinemez', `“${row.name}” ${row.productCount} üründe kullanılıyor. Önce ürünlerdeki değeri kaldırın.`, { cancelText: 'Kapat', confirmText: 'Tamam' })
		return
	}
	const cats = row.categoryCount > 0 ? ` ${row.categoryCount} kategoriden de kaldırılır.` : ''
	const ok = await confirm.danger('Özelliği Sil', `“${row.name}” silinecek; mağazadaki (Bagisto) aynı özellik de silinir.${cats}`)
	if (!ok) return
	router.delete(`${BASE}/${row.id}`, {
		preserveScroll: true,
		preserveState: true,
		onSuccess: () => showToast?.({ type: 'warning', title: 'Özellik silindi', message: row.name }),
		onError: (errs) => showToast?.({ type: 'error', title: 'Silme başarısız', message: Object.values(errs)[0] || 'Sunucu hatası.' }),
	})
}

/* ── Toplu silme ── */
const selected = ref([])
const bulkBusy = ref(false)

async function bulkDelete() {
	const rows = props.attributes.filter((r) => selected.value.includes(r.id))
	if (!rows.length) return
	const used = rows.filter((r) => r.productCount > 0)
	if (used.length) {
		await confirm.warning('Silinemez', `Ürünlerde kullanılan özellikler silinemez: ${used.map((r) => r.name).join(', ')}.`, { cancelText: 'Kapat', confirmText: 'Tamam' })
		return
	}
	const ok = await confirm.danger('Özellikleri Sil', `${rows.length} özellik silinecek; mağazadaki (Bagisto) karşılıkları da silinir.`)
	if (!ok) return
	bulkBusy.value = true
	router.post(`${BASE}/bulk-destroy`, { ids: rows.map((r) => r.id) }, {
		preserveScroll: true,
		preserveState: true,
		onSuccess: () => {
			showToast?.({ type: 'warning', title: 'Özellikler silindi', message: `${rows.length} kayıt kaldırıldı.` })
			selected.value = []
		},
		onError: (errs) => showToast?.({ type: 'error', title: 'Silme başarısız', message: Object.values(errs)[0] || 'Sunucu hatası.' }),
		onFinish: () => { bulkBusy.value = false },
	})
}
</script>

<template>
	<OzCard title="Özellikler" subtitle="Kumaş, kalıp, yaka tipi gibi ürün özellikleri · kategorilere atanır, ürün formunda doldurulur, mağazada (Bagisto) özellik ve filtre olarak görünür">
		<template v-if="canCreate" #actions>
			<OzButton size="sm" icon="plus" @click="openNew">Özellik Ekle</OzButton>
		</template>
		<OzTable v-model:selected="selected" :columns="columns" :rows="attributes" row-key="id" :selectable="canDelete" item-label="özellik" empty-title="Henüz özellik yok" empty-text="Örn. Kumaş, Kalıp, Yaka Tipi — ekledikten sonra Kategoriler ekranından kategorilere atayın." searchable search-placeholder="Özellik, kod ya da seçenek ara…" min-width="820px">
			<template #bulk>
				<OzButton variant="danger" size="sm" icon="trash" :loading="bulkBusy" @click="bulkDelete">Seçilenleri Sil ({{ selected.length }})</OzButton>
			</template>
			<template #cell-name="{ row }">
				<div class="attr-cell">
					<span class="attr-name">{{ row.name }}</span>
					<span class="attr-code">{{ row.code }}</span>
				</div>
			</template>
			<template #cell-type="{ row }"><OzPill :tone="TYPE_TONES[row.type]" :dot="false">{{ TYPE_LABELS[row.type] }}</OzPill></template>
			<template #cell-options="{ row }">
				<div v-if="row.options.length" class="chips">
					<span v-for="o in row.options.slice(0, 4)" :key="o.id" class="chip"><OzIcon v-if="o.icon" :name="resolveIcon(o.icon)" :size="13" />{{ o.name }}</span>
					<span v-if="row.options.length > 4" class="chip more">+{{ row.options.length - 4 }}</span>
				</div>
				<span v-else class="muted">—</span>
			</template>
			<template #cell-categoryCount="{ value }"><span class="count">{{ value }}</span></template>
			<template #cell-productCount="{ value }"><span class="count">{{ value }}</span></template>
			<template #cell-is_filterable="{ value }">
				<OzIcon v-if="value" name="check" :size="15" class="yes" />
				<span v-else class="muted">—</span>
			</template>
			<template v-if="rowActions.length" #cell-actions="{ row }">
				<OzDropdown :items="rowActions" :label="`${row.name} işlemleri`" @select="(k) => onAction(k, row)" />
			</template>
		</OzTable>
	</OzCard>

	<OzDrawer
		v-model:open="formOpen"
		:title="editing ? 'Özelliği Düzenle' : 'Yeni Özellik'"
		:subtitle="editing ? editing.name : 'Mağazada (Bagisto) aynı kodla bir özellik açılır'"
		:icon="editing ? 'edit' : 'plus'"
		size="md"
		:persistent="busy"
	>
		<form class="form" @submit.prevent="submit">
			<OzField label="Özellik Adı" required :error="errors.name">
				<OzInput v-model="form.name" placeholder="örn. Yaka Tipi" @input="onNameInput" />
			</OzField>
			<div class="grid-2">
				<OzField label="Kod" required :hint="editing ? 'Oluşturulduktan sonra değişmez' : 'Mağazadaki özellik kodu'" :error="errors.code">
					<OzInput v-model="form.code" class="mono" placeholder="yaka_tipi" :disabled="!!editing" @input="codeTouched = true" />
				</OzField>
				<OzField label="Tip" required :hint="editing ? 'Oluşturulduktan sonra değişmez' : ''" :error="errors.type">
					<OzSelect v-model="form.type" :options="TYPE_OPTIONS" :disabled="!!editing" />
				</OzField>
			</div>

			<OzField v-if="hasOptions(form.type)" label="Seçenekler" required :error="errors.options">
				<ul v-if="form.options.length" class="opt-list">
					<li v-for="(o, i) in form.options" :key="o.id ?? `new-${i}`" class="opt-row">
						<div class="opt-move">
							<button type="button" :disabled="i === 0" aria-label="Yukarı taşı" @click="moveOption(i, -1)"><OzIcon name="chevron-up" :size="13" /></button>
							<button type="button" :disabled="i === form.options.length - 1" aria-label="Aşağı taşı" @click="moveOption(i, 1)"><OzIcon name="chevron-down" :size="13" /></button>
						</div>
						<button type="button" class="opt-icon" :class="{ active: iconPickFor === i, empty: !o.icon }" :title="o.icon ? 'İkonu değiştir' : 'İkon ekle (isteğe bağlı)'" @click="iconPickFor = iconPickFor === i ? null : i">
							<OzIcon :name="o.icon ? resolveIcon(o.icon) : 'plus'" :size="o.icon ? 18 : 12" />
						</button>
						<div class="opt-input">
							<OzInput v-model="o.name" :invalid="!!optionError(i)" :aria-label="`Seçenek ${i + 1}`" />
							<span v-if="optionError(i)" class="opt-error">{{ optionError(i) }}</span>
							<div v-if="iconPickFor === i" class="opt-picker">
								<OzIconPicker v-model="o.icon" :options="optionIconOptions" :resolve="resolveIcon" :page-size="96" clearable max-height="180px" @update:model-value="iconPickFor = null" />
							</div>
						</div>
						<span v-if="o.productCount" class="opt-usage" :title="`${o.productCount} üründe kullanılıyor`">{{ o.productCount }} ürün</span>
						<button type="button" class="opt-remove" :disabled="o.productCount > 0" :title="o.productCount ? 'Ürünlerde kullanılıyor, silinemez' : 'Kaldır'" @click="form.options.splice(i, 1)"><OzIcon name="trash" :size="13" /></button>
					</li>
				</ul>
				<div class="opt-add">
					<OzInput v-model="newOption" placeholder="Seçenek yazın (virgülle birden çok)" @keydown.enter.prevent="addOption" />
					<OzButton size="sm" icon="plus" :disabled="!newOption.trim()" @click="addOption">Ekle</OzButton>
				</div>
			</OzField>

			<OzField v-if="filterableAllowed" label="Mağaza filtresi" hint="Açıksa mağazanın kategori sayfalarında bu özellikle filtrelenebilir">
				<OzSwitch v-model="form.is_filterable" :label="form.is_filterable ? 'Filtrelenebilir' : 'Filtrede gösterme'" />
			</OzField>

			<OzAlert v-if="!editing" tone="info">Kaydettikten sonra özelliği hangi kategorilerde kullanacağınızı <b>Kategoriler → satır menüsü → Özellikler</b> ekranından seçin; alt kategoriler üst kategorinin seçimini miras alır.</OzAlert>
			<p v-if="editing && editing.options.some((o) => o.productCount && form.options.find((f) => f.id === o.id)?.name.trim() !== o.name)" class="warn">
				Ürünlerde kullanılan bir seçeneğin adını değiştiriyorsunuz; ürünler seçeneği koruyarak yeni adla görünür (mağazada da yerinde yeniden adlandırılır).
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
.attr-cell { display: flex; flex-direction: column; gap: 1px; }
.attr-name { font-weight: 700; color: var(--oz-ink-soft); font-size: 12.5px; }
.attr-code { font-size: 10.5px; color: var(--oz-muted); font-family: ui-monospace, monospace; }
.chips { display: flex; flex-wrap: wrap; gap: 4px; }
.chip { display: inline-flex; align-items: center; gap: 4px; padding: 2px 8px; border: 1px solid var(--oz-border); border-radius: 6px; font-size: 11px; color: var(--oz-ink-soft); white-space: nowrap; }
.chip.more { color: var(--oz-muted); }
.count { display: inline-block; padding: 2px 9px; background: var(--oz-surface-2); color: var(--oz-muted); border-radius: 6px; font-size: 11.5px; font-weight: 700; font-family: ui-monospace, monospace; }
.muted { color: var(--oz-muted); }
.yes { color: var(--oz-success); }
.form { display: flex; flex-direction: column; gap: 14px; }
.grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.mono { font-family: ui-monospace, monospace; }
.opt-list { list-style: none; margin: 0 0 8px; padding: 0; display: flex; flex-direction: column; gap: 6px; }
.opt-row { display: flex; align-items: flex-start; gap: 8px; }
.opt-move { display: flex; flex-direction: column; }
.opt-move button, .opt-remove { background: none; border: none; cursor: pointer; color: var(--oz-muted); padding: 2px 4px; border-radius: 5px; display: inline-flex; }
.opt-move button:hover:not(:disabled) { background: var(--oz-surface-2); color: var(--oz-ink-soft); }
.opt-move button:disabled, .opt-remove:disabled { opacity: 0.35; cursor: not-allowed; }
.opt-remove { margin-top: 8px; }
.opt-remove:hover:not(:disabled) { background: var(--oz-danger-soft); color: var(--oz-danger); }
.opt-icon { width: 34px; height: 34px; flex-shrink: 0; display: inline-flex; align-items: center; justify-content: center; border: 1px solid var(--oz-border); border-radius: 8px; background: none; color: var(--oz-ink-soft); cursor: pointer; }
.opt-icon.empty { border-style: dashed; color: var(--oz-muted); }
.opt-icon.active, .opt-icon:hover { border-color: var(--oz-accent); color: var(--oz-accent); }
.opt-picker { margin-top: 6px; padding: 8px; border: 1px solid var(--oz-border); border-radius: 9px; }
.opt-input { flex: 1; display: flex; flex-direction: column; gap: 2px; }
.opt-error { color: var(--oz-danger); font-size: 11px; }
.opt-usage { margin-top: 9px; font-size: 10.5px; color: var(--oz-muted); white-space: nowrap; }
.opt-add { display: flex; gap: 8px; align-items: center; }
.opt-add > :first-child { flex: 1; }
.warn { margin: 0; font-size: 12px; color: var(--oz-warning); }
@media (max-width: 560px) { .grid-2 { grid-template-columns: 1fr; } }
</style>
