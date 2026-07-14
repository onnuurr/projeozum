<template>
	<div class="material-picker">
		<div v-if="!rows.length" class="mp-empty">
			Henüz materyal seçilmedi. Aşağıdan arayıp ekleyin.
		</div>

		<div v-for="(row, idx) in rows" :key="row.material_id + ':' + row.role" class="mp-row">
			<div class="mp-role">
				<select v-model="row.role" class="form-input">
					<option v-for="opt in roleOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
				</select>
			</div>
			<div class="mp-name" :title="materialTitle(row)">
				<strong>{{ row.material?.name || '#' + row.material_id }}</strong>
				<span v-if="row.material?.code" class="mp-code">{{ row.material.code }}</span>
				<span v-if="specSummary(row.material)" class="mp-spec">{{ specSummary(row.material) }}</span>
			</div>
			<button type="button" class="mp-remove" @click="removeRow(idx)" aria-label="Kaldır">✕</button>
		</div>

		<div class="mp-search">
			<input
				v-model="query"
				type="text"
				class="form-input"
				placeholder="Malzeme ara (isim veya kod)…"
				@focus="loadIfEmpty"
				@input="onSearchInput"
			/>
			<div v-if="showResults && results.length" class="mp-results">
				<button
					v-for="m in results"
					:key="m.id"
					type="button"
					class="mp-result"
					@click="pick(m)"
					:disabled="isPicked(m)"
				>
					<strong>{{ m.name }}</strong>
					<span class="mp-code">{{ m.code }}</span>
					<span class="mp-type">{{ typeLabel(m.type) }}</span>
					<span v-if="isPicked(m)" class="mp-picked">eklendi</span>
				</button>
			</div>
			<div v-else-if="showResults && !results.length && !loading" class="mp-noresult">
				Eşleşen materyal yok.
			</div>
		</div>
	</div>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import axios from 'axios'

const props = defineProps({
	modelValue: { type: Array, default: () => [] },
	endpoint: { type: String, default: '/atelier/materials-lookup' },
	defaultRole: { type: String, default: 'primary_fabric' },
})
const emit = defineEmits(['update:modelValue'])

const roleOptions = [
	{ value: 'primary_fabric', label: 'Ana Kumaş' },
	{ value: 'secondary',      label: 'Ek Kumaş' },
	{ value: 'trim',           label: 'Aksesuar/Tela' },
	{ value: 'accessory',      label: 'Süsleme' },
	{ value: 'label',          label: 'Etiket' },
]

const rows = computed({
	get() { return props.modelValue || [] },
	set(val) { emit('update:modelValue', val) },
})

const query = ref('')
const results = ref([])
const loading = ref(false)
const showResults = ref(false)
let searchTimer = null

async function fetchResults() {
	loading.value = true
	try {
		const { data } = await axios.get(props.endpoint, { params: { q: query.value } })
		results.value = data.data || []
	} catch (e) {
		results.value = []
	} finally {
		loading.value = false
	}
}

function onSearchInput() {
	showResults.value = true
	clearTimeout(searchTimer)
	searchTimer = setTimeout(fetchResults, 200)
}

function loadIfEmpty() {
	showResults.value = true
	if (!results.value.length) fetchResults()
}

function pick(material) {
	if (isPicked(material)) return
	const next = [...rows.value, {
		material_id: material.id,
		material,
		role: props.defaultRole,
		sort_order: rows.value.length,
		notes: null,
	}]
	rows.value = next
	query.value = ''
	showResults.value = false
}

function removeRow(idx) {
	const next = [...rows.value]
	next.splice(idx, 1)
	rows.value = next.map((r, i) => ({ ...r, sort_order: i }))
}

function isPicked(material) {
	return rows.value.some((r) => r.material_id === material.id)
}

function typeLabel(t) {
	return { kumas: 'Kumaş', aksesuar: 'Aksesuar', etiket: 'Etiket' }[t] || t
}

function specSummary(material) {
	const s = material?.specs || {}
	const parts = []
	if (s.composition) parts.push(s.composition)
	if (s.gsm) parts.push(s.gsm + ' gsm')
	if (s.weave) parts.push(s.weave)
	return parts.join(' · ')
}

function materialTitle(row) {
	const m = row.material
	if (!m) return ''
	return `${m.name} (${m.code}) — ${specSummary(m) || 'özellik yok'}`
}

watch(rows, (val) => {
	// sort_order senkron kalsın.
	val.forEach((r, i) => { if (r.sort_order !== i) r.sort_order = i })
}, { deep: true })
</script>

<style scoped>
.material-picker { display: flex; flex-direction: column; gap: 8px; }
.mp-empty {
	font-size: 12.5px; color: #888;
	padding: 14px; border: 1px dashed #d8d8e8; border-radius: 10px;
	text-align: center;
}
.mp-row {
	display: grid;
	grid-template-columns: 160px 1fr 28px;
	gap: 8px; align-items: center;
	padding: 8px 10px;
	background: #fafafe; border: 1px solid #f0f0f6; border-radius: 8px;
}
.mp-role select { height: 32px; font-size: 12px; padding: 0 8px; }
.mp-name { display: flex; flex-direction: column; gap: 2px; min-width: 0; font-size: 12.5px; }
.mp-code { font-family: 'SF Mono', Consolas, monospace; font-size: 10.5px; color: #888; }
.mp-spec { font-size: 11px; color: #666; }
.mp-remove {
	background: #fff; border: 1px solid #fecaca; color: #dc2626;
	width: 24px; height: 24px; border-radius: 6px;
	display: flex; align-items: center; justify-content: center;
	cursor: pointer;
}
.mp-remove:hover { background: #fef2f2; }
.mp-search { position: relative; margin-top: 4px; }
.mp-results {
	position: absolute; top: calc(100% + 4px); left: 0; right: 0;
	background: #fff; border: 1px solid #ebebf0; border-radius: 10px;
	max-height: 260px; overflow-y: auto;
	box-shadow: 0 6px 20px rgba(0, 0, 0, 0.08);
	z-index: 30;
	padding: 4px;
}
.mp-result {
	display: grid; grid-template-columns: 1fr 100px 90px auto;
	gap: 8px; align-items: center;
	padding: 8px 10px; width: 100%;
	background: none; border: none; text-align: left;
	font-family: inherit; font-size: 12px; cursor: pointer;
	border-radius: 6px;
}
.mp-result:hover:not(:disabled) { background: #f5f5f8; }
.mp-result:disabled { opacity: .55; cursor: not-allowed; }
.mp-result strong { color: #1a1a2e; }
.mp-type { font-size: 10.5px; color: #666; }
.mp-picked { font-size: 10.5px; color: #16a34a; font-weight: 600; }
.mp-noresult { padding: 10px; text-align: center; font-size: 12px; color: #888; }

@media (max-width: 640px) {
	.mp-row { grid-template-columns: 100px 1fr 28px; gap: 6px; }
	.mp-result { grid-template-columns: 1fr auto; row-gap: 2px; }
	.mp-result .mp-type { grid-column: 2; justify-self: end; }
	.mp-result .mp-picked { grid-column: 1 / -1; }
}
</style>
