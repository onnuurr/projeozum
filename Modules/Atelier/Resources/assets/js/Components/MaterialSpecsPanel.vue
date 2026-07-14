<template>
	<div class="specs-panel">
		<div class="specs-grid">
			<label class="form-row">
				<span class="form-label">Kompozisyon</span>
				<input v-model="local.composition" type="text" class="form-input" placeholder="örn. %60 pamuk %40 polyester" />
			</label>
			<label class="form-row">
				<span class="form-label">Gramaj (gsm)</span>
				<input v-model.number="local.gsm" type="number" min="0" step="1" class="form-input" placeholder="180" />
			</label>
			<label class="form-row">
				<span class="form-label">Genişlik (cm)</span>
				<input v-model.number="local.width_cm" type="number" min="0" step="0.5" class="form-input" placeholder="150" />
			</label>
			<label class="form-row">
				<span class="form-label">Dokuma/Örgü</span>
				<input v-model="local.weave" type="text" class="form-input" placeholder="örn. jarse, dimi, ribana" />
			</label>
			<label class="form-row">
				<span class="form-label">Terbiye/Finish</span>
				<input v-model="local.finish" type="text" class="form-input" placeholder="örn. peach, mercerize" />
			</label>
			<label class="form-row">
				<span class="form-label">Lif Menşei</span>
				<input v-model="local.fiber_origin" type="text" class="form-input" placeholder="örn. Ege pamuğu" />
			</label>
			<label class="form-row form-row-full">
				<span class="form-label">Bakım Talimatı</span>
				<textarea v-model="local.care_instructions" rows="2" class="form-input" placeholder="30°C yıkanır, tersten ütülenir…" />
			</label>
			<label class="form-row form-row-full">
				<span class="form-label">Notlar</span>
				<textarea v-model="local.notes" rows="2" class="form-input" placeholder="Tedarikçi, ton uyarısı vb." />
			</label>
		</div>
	</div>
</template>

<script setup>
import { reactive, watch } from 'vue'

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

watch(local, (val) => {
	const clean = {}
	for (const k of Object.keys(val)) {
		if (val[k] !== '' && val[k] !== null && val[k] !== undefined) clean[k] = val[k]
	}
	emit('update:modelValue', clean)
}, { deep: true })
</script>

<style scoped>
.specs-panel { display: flex; flex-direction: column; gap: 10px; }
.specs-grid {
	display: grid;
	grid-template-columns: 1fr 1fr;
	gap: 10px 12px;
}
.form-row { display: flex; flex-direction: column; gap: 4px; }
.form-row-full { grid-column: 1 / -1; }
.form-label { font-size: 12px; font-weight: 600; color: #444; }
.form-input {
	padding: 8px 11px; border: 1px solid #e8e8f0; border-radius: 8px;
	font-family: inherit; font-size: 12.5px; color: #1a1a2e;
	background: #fff; outline: none; transition: border-color .15s;
}
.form-input:focus { border-color: rgb(var(--color-primary)); }

@media (max-width: 560px) {
	.specs-grid { grid-template-columns: 1fr; }
}
</style>
