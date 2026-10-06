<script setup>
import { computed, inject, ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import { OzCard, OzTable, OzSelect, OzButton, OzCombobox, OzPill } from '@/Components/Oz'
import { REGIONS, VIEWS, FEATURES, toOptions } from '../../support/mockupLabels'

// Mokap · Pozlar: hangi poz hangi ürün bölgesinde kullanılır (boş = mokap dışı: oturma, çömelme, kollar
// kavuşmuş gibi maskesi ölçülmemiş ya da ürünü gizleyen pozlar), ürünün sahip olması gereken özellik
// (ör. el cepte → cep) ve kamera yönü (sadakat kontrolü görünmeyen detayı eksik saymasın). Değişiklik anında kaydedilir.
const props = defineProps({
	poses: { type: Array, required: true },
	features: { type: Array, default: () => [] },
})

const showToast = inject('showToast')
const rows = ref([])
const selected = ref([])
const bulkBusy = ref(false)

watch(() => props.poses, (list) => { rows.value = list.map((p) => ({ ...p, mockup_regions: [...p.mockup_regions], requires: [...p.requires] })) }, { immediate: true })

const columns = [
	{ key: 'label', label: 'Poz', sortable: true },
	{ key: 'mockup_regions', label: 'Kullanıldığı bölgeler', width: '260px', searchable: false },
	{ key: 'requires', label: 'Üründe gerekli', width: '200px', searchable: false },
	{ key: 'view', label: 'Kamera yönü', width: '150px', searchable: false },
]
const featureOptions = computed(() => props.features.map((f) => ({ value: f, label: FEATURES[f] ?? f })))

function save(row, done) {
	router.put(`/creative/settings/mockup/poses/${row.id}`, { mockup_regions: row.mockup_regions, requires: row.requires, view: row.view }, {
		preserveScroll: true,
		preserveState: true,
		onSuccess: () => (done ? done() : showToast?.({ type: 'success', title: 'Poz kaydedildi', message: row.label })),
		onError: (errs) => showToast?.({ type: 'error', title: 'Kayıt başarısız', message: Object.values(errs)[0] || 'Doğrulama hatası.' }),
		onFinish: () => { bulkBusy.value = false },
	})
}

function excludeSelected() {
	const list = rows.value.filter((r) => selected.value.includes(r.id) && !r.is_master)
	if (!list.length) return
	bulkBusy.value = true
	let left = list.length
	for (const r of list) {
		r.mockup_regions = []
		save(r, () => {
			left -= 1
			if (!left) {
				showToast?.({ type: 'warning', title: 'Pozlar mokap dışı', message: `${list.length} poz artık mokapta kullanılmayacak.` })
				selected.value = []
			}
		})
	}
}
</script>

<template>
	<OzCard title="Mokap pozları" subtitle="Ana kare her üründe kullanılır; stilist diğer pozları buradaki etiketlere uyanlardan seçer">
		<OzTable v-model:selected="selected" :columns="columns" :rows="rows" row-key="id" selectable :is-row-selectable="(r) => !r.is_master"
			item-label="poz" empty-title="Hazır poz yok" searchable search-placeholder="Poz ara…">
			<template #bulk>
				<OzButton variant="danger" size="sm" icon="eye-off" :loading="bulkBusy" @click="excludeSelected">Mokap dışı yap ({{ selected.length }})</OzButton>
			</template>
			<template #cell-label="{ row }">
				<div class="pose">
					<img v-if="row.preview_url" :src="row.preview_url" alt="" class="thumb" loading="lazy">
					<div class="name">
						<b>{{ row.label }}</b>
						<span class="key">{{ row.pose_key }}</span>
					</div>
					<OzPill v-if="row.is_master" size="sm" tone="accent" :dot="false">Ana kare</OzPill>
					<OzPill v-else-if="!row.mockup_regions.length" size="sm" :dot="false">Mokap dışı</OzPill>
				</div>
			</template>
			<template #cell-mockup_regions="{ row }">
				<OzCombobox v-model="row.mockup_regions" :options="toOptions(REGIONS)" multiple placeholder="Mokap dışı" :disabled="row.is_master"
					:aria-label="`${row.label} bölgeleri`" @update:model-value="save(row)" />
			</template>
			<template #cell-requires="{ row }">
				<OzCombobox v-model="row.requires" :options="featureOptions" multiple placeholder="—" :aria-label="`${row.label} gereksinimi`" @update:model-value="save(row)" />
			</template>
			<template #cell-view="{ row }">
				<OzSelect v-model="row.view" :options="toOptions(VIEWS)" :aria-label="`${row.label} kamera yönü`" @update:model-value="save(row)" />
			</template>
		</OzTable>
	</OzCard>
</template>

<style scoped>
.pose { display: flex; align-items: center; gap: 10px; }
.thumb { width: 34px; height: 46px; object-fit: cover; border-radius: 6px; border: 1px solid var(--oz-border); background: var(--oz-surface-2); }
.name { display: flex; flex-direction: column; gap: 1px; }
.key { font-size: 10.5px; color: var(--oz-muted); font-family: ui-monospace, monospace; }
</style>
