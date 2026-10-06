<script setup>
import { computed, inject, ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import { OzCard, OzTable, OzSelect, OzButton } from '@/Components/Oz'
import { REGIONS, LENGTHS, toOptions } from '../../support/mockupLabels'

// Mokap · Kategoriler: kategori → bölge (+ varsayılan boy). Bölgesi boş kategori (üst kategorisi de
// eşleşmiyorsa) mokap dışıdır ve eski giydirme hattından geçer. Ürünün "Boy" özelliği varsa o önceliklidir.
const props = defineProps({
	categories: { type: Array, required: true },
})

const showToast = inject('showToast')
const rows = ref([])
const busy = ref(false)
const selected = ref([])
const bulkRegion = ref('')

watch(() => props.categories, (list) => {
	rows.value = list.map((c) => ({ ...c, region: c.region ?? '', length: c.length ?? '', label: c.parent ? `${c.parent} › ${c.name}` : c.name }))
}, { immediate: true })

const dirty = computed(() => rows.value.filter((r) => {
	const o = props.categories.find((c) => c.id === r.id)
	return (o.region ?? '') !== r.region || (o.length ?? '') !== r.length
}))

const columns = [
	{ key: 'label', label: 'Kategori', sortable: true },
	{ key: 'region', label: 'Mokap bölgesi', width: '200px', searchable: false },
	{ key: 'length', label: 'Varsayılan boy', width: '180px', searchable: false },
]
const regionOptions = [{ value: '', label: 'Mokap dışı' }, ...toOptions(REGIONS)]
const lengthOptions = [{ value: '', label: 'Ürün özelliğinden / AI' }, ...toOptions(LENGTHS)]

function save(list = dirty.value) {
	if (busy.value || !list.length) return
	busy.value = true
	router.put('/creative/settings/mockup/categories', {
		rows: list.map((r) => ({ category_id: r.id, region: r.region || null, length: r.region ? (r.length || null) : null })),
	}, {
		preserveScroll: true,
		preserveState: true,
		onSuccess: () => {
			showToast?.({ type: 'success', title: 'Kategori eşleştirmesi kaydedildi', message: `${list.length} kategori güncellendi.` })
			selected.value = []
		},
		onError: (errs) => showToast?.({ type: 'error', title: 'Kayıt başarısız', message: Object.values(errs)[0] || 'Doğrulama hatası.' }),
		onFinish: () => { busy.value = false },
	})
}

function applyBulk() {
	const list = rows.value.filter((r) => selected.value.includes(r.id))
	list.forEach((r) => { r.region = bulkRegion.value; if (!r.region) r.length = '' })
	save(list)
}
</script>

<template>
	<OzCard title="Kategori → mokap bölgesi" subtitle="Hangi kategorideki ürünün mokapta nereye giydirileceği · boş = mokap dışı (dış giyim, aksesuar…)">
		<template #actions>
			<OzButton variant="primary" size="sm" icon="save" :loading="busy" :disabled="!dirty.length" @click="save()">
				Kaydet<template v-if="dirty.length"> ({{ dirty.length }})</template>
			</OzButton>
		</template>
		<OzTable v-model:selected="selected" :columns="columns" :rows="rows" row-key="id" selectable item-label="kategori"
			empty-title="Kategori yok" searchable search-placeholder="Kategori ara…">
			<template #bulk>
				<OzSelect v-model="bulkRegion" :options="regionOptions" class="bulk-sel" aria-label="Seçilenlere bölge" />
				<OzButton size="sm" icon="check" :loading="busy" @click="applyBulk">Seçilenlere uygula ({{ selected.length }})</OzButton>
			</template>
			<template #cell-region="{ row }">
				<OzSelect v-model="row.region" :options="regionOptions" :aria-label="`${row.label} bölgesi`" />
			</template>
			<template #cell-length="{ row }">
				<OzSelect v-model="row.length" :options="lengthOptions" :disabled="!row.region" :aria-label="`${row.label} boyu`" />
			</template>
		</OzTable>
	</OzCard>
</template>

<style scoped>
.bulk-sel { min-width: 150px; }
</style>
