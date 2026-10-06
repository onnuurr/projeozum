<script setup>
import { computed, inject, reactive, ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import { OzCard, OzTable, OzSelect, OzButton, OzDrawer, OzField, OzInput, OzCombobox, OzCheckbox, OzSwitch, OzPill, OzDropdown, useOzConfirm } from '@/Components/Oz'
import { KINDS, REGIONS, GENDERS, LENGTHS, STYLES, SEASONS, PLACEMENTS, toOptions } from '../../support/mockupLabels'

// Mokap · Stilist seçenekleri: stilist (AI) yalnız bu listeden anahtar seçer; kombin cümlesini kural motoru
// seçeneğin İngilizce metninden kurar. Etiketler (bölge/cinsiyet/boy/aksesuar yeri) uyumsuz seçimi eler.
const props = defineProps({
	options: { type: Array, required: true },
})

const showToast = inject('showToast')
const confirm = useOzConfirm()
const kindFilter = ref('')
const selected = ref([])
const bulkBusy = ref(false)

const rows = computed(() => props.options.filter((o) => !kindFilter.value || o.kind === kindFilter.value))
watch(kindFilter, () => { selected.value = [] })

const columns = [
	{ key: 'kind', label: 'Tür', width: '110px', sortable: true },
	{ key: 'label_tr', label: 'Seçenek', sortable: true },
	{ key: 'prompt_en', label: 'Prompt (İngilizce)' },
	{ key: 'tags', label: 'Kısıtlar', searchable: false },
	{ key: 'is_active', label: 'Aktif', width: '70px', align: 'center', searchable: false },
	{ key: 'actions', label: '', align: 'right', width: '48px', searchable: false },
]
const kindOptions = [{ value: '', label: 'Tüm türler' }, ...toOptions(KINDS)]
const rowActions = [{ key: 'edit', label: 'Düzenle', icon: 'edit' }, { key: 'remove', label: 'Sil', icon: 'trash', danger: true }]

/** Etiketleri kısa Türkçe özet çipleri olarak gösterir (boş etiket = kısıt yok). */
function tagChips(tags = {}) {
	const out = []
	const list = (key, map) => (tags[key]?.length ? out.push(tags[key].map((v) => map[v] ?? v).join(', ')) : null)
	list('regions', REGIONS)
	list('genders', GENDERS)
	list('lengths', LENGTHS)
	list('style', STYLES)
	list('season', SEASONS)
	if (tags.placement) out.push(`Yer: ${PLACEMENTS[tags.placement] ?? tags.placement}`)
	if (tags.colorable) out.push('Renk uygulanır')
	return out
}

/* ── Form ── */
const formOpen = ref(false)
const editing = ref(null)
const busy = ref(false)
const errors = ref({})
const blank = () => ({ kind: kindFilter.value || 'top', key: '', label_tr: '', prompt_en: '', is_active: true, sort_order: 0,
	tags: { regions: [], genders: [], lengths: [], style: [], season: [], colorable: false, placement: '' } })
const form = reactive(blank())

function openNew() {
	Object.assign(form, blank())
	editing.value = null
	errors.value = {}
	formOpen.value = true
}

function edit(row) {
	Object.assign(form, blank(), JSON.parse(JSON.stringify({ ...row, tags: { ...blank().tags, ...(row.tags ?? {}) } })))
	editing.value = row
	errors.value = {}
	formOpen.value = true
}

function payload(src) {
	const t = src.tags
	return {
		kind: src.kind, key: src.key, label_tr: src.label_tr, prompt_en: src.prompt_en ?? '', is_active: !!src.is_active,
		sort_order: Number(src.sort_order) || 0,
		tags: { regions: t.regions ?? [], genders: t.genders ?? [], lengths: t.lengths ?? [], style: t.style ?? [], season: t.season ?? [],
			colorable: !!t.colorable, placement: t.placement || null },
	}
}

const opts = (onSuccess) => ({
	preserveScroll: true,
	preserveState: true,
	onSuccess,
	onError: (errs) => {
		errors.value = errs
		showToast?.({ type: 'error', title: 'Kayıt başarısız', message: Object.values(errs)[0] || 'Doğrulama hatası.' })
	},
	onFinish: () => { busy.value = false; bulkBusy.value = false },
})

function submit() {
	if (busy.value) return
	busy.value = true
	errors.value = {}
	const done = () => {
		formOpen.value = false
		showToast?.({ type: 'success', title: editing.value ? 'Seçenek güncellendi' : 'Seçenek eklendi', message: form.label_tr })
	}
	if (editing.value) router.put(`/creative/settings/mockup/options/${editing.value.id}`, payload(form), opts(done))
	else router.post('/creative/settings/mockup/options', payload(form), opts(done))
}

function toggleActive(row, value) {
	router.put(`/creative/settings/mockup/options/${row.id}`, payload({ ...row, is_active: value, tags: { ...blank().tags, ...(row.tags ?? {}) } }),
		opts(() => showToast?.({ type: 'success', title: value ? 'Seçenek açıldı' : 'Seçenek kapatıldı', message: row.label_tr })))
}

async function remove(ids, label) {
	const ok = await confirm.danger('Seçenek Sil', `${label} stilist listesinden kaldırılacak. Daha önce üretilmiş kombinler etkilenmez.`)
	if (!ok) return
	bulkBusy.value = true
	router.delete('/creative/settings/mockup/options', {
		data: { ids },
		...opts(() => {
			showToast?.({ type: 'warning', title: 'Seçenek silindi', message: `${ids.length} kayıt kaldırıldı.` })
			selected.value = []
		}),
	})
}

function onAction(key, row) {
	if (key === 'edit') edit(row)
	else remove([row.id], `“${row.label_tr}”`)
}

const isAccessory = computed(() => form.kind === 'accessory')
const showColorable = computed(() => ['top', 'bottom'].includes(form.kind))
</script>

<template>
	<OzCard title="Stilist seçenekleri" subtitle="Stilist kombini yalnız bu listeden kurar · prompt metni görsel üretimine aynen girer">
		<template #actions>
			<OzButton size="sm" icon="plus" @click="openNew">Seçenek Ekle</OzButton>
		</template>
		<OzTable v-model:selected="selected" :columns="columns" :rows="rows" row-key="id" selectable item-label="seçenek"
			empty-title="Seçenek yok" searchable search-placeholder="Seçenek ara…" :page-size="25">
			<template #toolbar>
				<OzSelect v-model="kindFilter" :options="kindOptions" class="kind-filter" aria-label="Tür filtresi" />
			</template>
			<template #bulk>
				<OzButton variant="danger" size="sm" icon="trash" :loading="bulkBusy" @click="remove(selected, `${selected.length} seçenek`)">Seçilenleri Sil ({{ selected.length }})</OzButton>
			</template>
			<template #cell-kind="{ value }"><OzPill size="sm" :dot="false">{{ KINDS[value] ?? value }}</OzPill></template>
			<template #cell-label_tr="{ row }">
				<div class="name"><b>{{ row.label_tr }}</b><span class="key">{{ row.key }}</span></div>
			</template>
			<template #cell-prompt_en="{ value }"><span class="prompt">{{ value || '— (yok)' }}</span></template>
			<template #cell-tags="{ row }">
				<div class="chips">
					<span v-for="(c, i) in tagChips(row.tags)" :key="i" class="chip">{{ c }}</span>
					<span v-if="!tagChips(row.tags).length" class="muted">Kısıt yok</span>
				</div>
			</template>
			<template #cell-is_active="{ row }">
				<OzSwitch :model-value="row.is_active" :aria-label="`${row.label_tr} aktif`" @update:model-value="(v) => toggleActive(row, v)" />
			</template>
			<template #cell-actions="{ row }">
				<OzDropdown :items="rowActions" :label="`${row.label_tr} işlemleri`" @select="(k) => onAction(k, row)" />
			</template>
		</OzTable>
	</OzCard>

	<OzDrawer v-model:open="formOpen" :title="editing ? 'Seçeneği Düzenle' : 'Yeni Seçenek'" :subtitle="editing ? editing.label_tr : 'Stilistin seçebileceği kombin parçası / renk'"
		:icon="editing ? 'edit' : 'plus'" size="md" :persistent="busy">
		<form class="form" @submit.prevent="submit">
			<div class="two">
				<OzField label="Tür" required :error="errors.kind">
					<OzSelect v-model="form.kind" :options="toOptions(KINDS)" />
				</OzField>
				<OzField label="Anahtar" required :error="errors.key" hint="küçük harf, rakam, alt çizgi">
					<OzInput v-model="form.key" placeholder="örn. rain_boots" class="mono" />
				</OzField>
			</div>
			<OzField label="Türkçe ad" required :error="errors.label_tr">
				<OzInput v-model="form.label_tr" placeholder="örn. Yağmur botu" />
			</OzField>
			<OzField label="Prompt (İngilizce)" :error="errors.prompt_en" hint="Kısa ve somut; boş = görsel prompt'una girmez (örn. “Çorap görünmesin”)">
				<OzInput v-model="form.prompt_en" placeholder="örn. rubber rain boots" />
			</OzField>
			<OzField label="Kullanılabileceği ürün bölgeleri" hint="Boş = hepsi" :error="errors['tags.regions']">
				<OzCombobox v-model="form.tags.regions" :options="toOptions(REGIONS)" multiple placeholder="Hepsi" />
			</OzField>
			<div class="two">
				<OzField label="Cinsiyet" hint="Boş = ikisi">
					<OzCombobox v-model="form.tags.genders" :options="toOptions(GENDERS)" multiple placeholder="İkisi" />
				</OzField>
				<OzField label="Ürün boyu" hint="Boş = hepsi">
					<OzCombobox v-model="form.tags.lengths" :options="toOptions(LENGTHS)" multiple placeholder="Hepsi" />
				</OzField>
			</div>
			<div class="two">
				<OzField label="Üslup" hint="Stiliste ipucu">
					<OzCombobox v-model="form.tags.style" :options="toOptions(STYLES)" multiple placeholder="—" />
				</OzField>
				<OzField label="Mevsim" hint="Boş = her mevsim">
					<OzCombobox v-model="form.tags.season" :options="toOptions(SEASONS)" multiple placeholder="Her mevsim" />
				</OzField>
			</div>
			<OzField v-if="isAccessory" label="Aksesuarın yeri" hint="Üst/takım ürünlerinde göğüste aksesuar kullanılmaz">
				<OzSelect v-model="form.tags.placement" :options="[{ value: '', label: '—' }, ...toOptions(PLACEMENTS)]" />
			</OzField>
			<OzCheckbox v-if="showColorable" v-model="form.tags.colorable" label="Renk seçeneği uygulanır" description="Stilistin seçtiği renk bu parçaya uygulanır (kot gibi kendi rengi olanlarda kapalı bırakın)" />
			<div class="two">
				<OzField label="Sıra" :error="errors.sort_order"><OzInput v-model.number="form.sort_order" type="number" min="0" /></OzField>
				<OzField label="Durum"><OzSwitch v-model="form.is_active" label="Aktif" /></OzField>
			</div>
			<button type="submit" hidden />
		</form>
		<template #footer>
			<OzButton :disabled="busy" @click="formOpen = false">İptal</OzButton>
			<OzButton variant="primary" :icon="editing ? 'check' : 'plus'" :loading="busy" @click="submit">{{ editing ? 'Kaydet' : 'Ekle' }}</OzButton>
		</template>
	</OzDrawer>
</template>

<style scoped>
.kind-filter { min-width: 140px; }
.name { display: flex; flex-direction: column; gap: 1px; }
.key, .mono { font-family: ui-monospace, monospace; }
.key { font-size: 10.5px; color: var(--oz-muted); }
.prompt { font-size: 12px; color: var(--oz-ink-soft); }
.chips { display: flex; flex-wrap: wrap; gap: 4px; }
.chip { padding: 2px 7px; border-radius: 6px; background: var(--oz-surface-2); color: var(--oz-ink-soft); font-size: 11px; }
.muted { color: var(--oz-muted); font-size: 11.5px; }
.form { display: flex; flex-direction: column; gap: 14px; }
.two { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
</style>
