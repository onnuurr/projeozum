<template>
	<div class="specs-grid">
		<OzField label="Kompozisyon">
			<OzInput v-model="local.composition" placeholder="örn. %60 pamuk %40 polyester" />
		</OzField>
		<OzField label="Gramaj">
			<OzInput v-model.number="local.gsm" type="number" min="0" step="1" suffix="gsm" placeholder="180" />
		</OzField>
		<OzField label="Genişlik">
			<OzInput v-model.number="local.width_cm" type="number" min="0" step="0.5" suffix="cm" placeholder="150" />
		</OzField>
		<OzField label="Dokuma / Örgü">
			<OzInput v-model="local.weave" placeholder="örn. jarse, dimi, ribana" />
		</OzField>
		<OzField label="Terbiye / Finish">
			<OzInput v-model="local.finish" placeholder="örn. peach, mercerize" />
		</OzField>
		<OzField label="Lif Menşei">
			<OzInput v-model="local.fiber_origin" placeholder="örn. Ege pamuğu" />
		</OzField>
		<OzField label="Bakım Talimatı" class="full">
			<OzTextarea v-model="local.care_instructions" :rows="2" placeholder="30°C yıkanır, tersten ütülenir…" />
		</OzField>
		<OzField label="Notlar" class="full">
			<OzTextarea v-model="local.notes" :rows="2" placeholder="Tedarikçi, ton uyarısı vb." />
		</OzField>
	</div>
</template>

<script setup>
import { reactive, watch } from 'vue'
import { OzField, OzInput, OzTextarea } from '@/Components/Oz'

const props = defineProps({
	modelValue: { type: Object, default: () => ({}) },
})
const emit = defineEmits(['update:modelValue'])

const emptyLocal = () => ({
	composition: '', gsm: null, width_cm: null, weave: '', finish: '',
	fiber_origin: '', care_instructions: '', notes: '',
})

const local = reactive({ ...emptyLocal(), ...(props.modelValue || {}) })

watch(() => props.modelValue, (val) => {
	Object.assign(local, emptyLocal(), val || {})
}, { deep: true })

// Boş alanlar gönderilmez (UpdateMaterialSpecsRequest yalnızca dolu anahtarları bekler)
watch(local, (val) => {
	const clean = {}
	for (const k of Object.keys(val)) {
		if (val[k] !== '' && val[k] !== null && val[k] !== undefined) clean[k] = val[k]
	}
	emit('update:modelValue', clean)
}, { deep: true })
</script>

<style scoped>
.specs-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px 12px; }
.full { grid-column: 1 / -1; }
@media (max-width: 560px) {
	.specs-grid { grid-template-columns: 1fr; }
}
</style>
