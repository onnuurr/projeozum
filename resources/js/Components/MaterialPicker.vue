<template>
	<div class="material-picker">
		<div v-if="!rows.length" class="mp-empty">
			<OzIcon name="layers" :size="16" />
			Henüz materyal seçilmedi. Aşağıdan arayıp ekleyin.
		</div>

		<div v-for="(row, idx) in rows" :key="row.material_id + ':' + row.role" class="mp-row">
			<OzSelect v-model="row.role" :options="roleOptions" class="mp-role" :aria-label="`${row.material?.name ?? 'Materyal'} rolü`" />
			<div class="mp-name" :title="materialTitle(row)">
				<span class="mp-title">
					<strong>{{ row.material?.name || '#' + row.material_id }}</strong>
					<span v-if="row.material?.code" class="mp-code">{{ row.material.code }}</span>
				</span>
				<span v-if="specSummary(row.material)" class="mp-spec">{{ specSummary(row.material) }}</span>
			</div>
			<button type="button" class="mp-remove" :aria-label="`${row.material?.name ?? 'Materyali'} kaldır`" @click="removeRow(idx)">
				<OzIcon name="x" :size="14" />
			</button>
		</div>

		<div ref="searchEl" class="mp-search">
			<OzInput
				v-model="query"
				icon="search"
				placeholder="Malzeme ara (isim veya kod)…"
				autocomplete="off"
				@focus="loadIfEmpty"
				@input="onSearchInput"
				@keydown.esc="showResults = false"
			/>
			<div v-if="showResults && (results.length || !loading)" class="mp-results">
				<template v-if="results.length">
					<button
						v-for="m in results"
						:key="m.id"
						type="button"
						class="mp-result"
						:disabled="isPicked(m)"
						@click="pick(m)"
					>
						<span class="mp-result-name">{{ m.name }}</span>
						<span class="mp-code">{{ m.code }}</span>
						<span class="mp-type">{{ typeLabel(m.type) }}</span>
						<span v-if="isPicked(m)" class="mp-picked"><OzIcon name="check" :size="12" /> eklendi</span>
					</button>
				</template>
				<div v-else class="mp-noresult">Eşleşen materyal yok.</div>
			</div>
		</div>
	</div>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import axios from 'axios'
import { onClickOutside } from '@vueuse/core'
import OzIcon from '@/Components/Oz/OzIcon.vue'
import OzInput from '@/Components/Oz/OzInput.vue'
import OzSelect from '@/Components/Oz/OzSelect.vue'

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
const searchEl = ref(null)
let searchTimer = null

onClickOutside(searchEl, () => { showResults.value = false })

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
	display: flex; align-items: center; justify-content: center; gap: 8px;
	padding: 14px; border: 1px dashed var(--oz-border-strong); border-radius: var(--oz-radius-sm);
	font-size: 12.5px; color: var(--oz-muted);
}
.mp-row {
	display: grid; grid-template-columns: 170px minmax(0, 1fr) 32px; gap: 12px; align-items: center;
	padding: 8px 8px 8px 8px;
	background: var(--oz-surface-2); border: 1px solid var(--oz-border-soft); border-radius: var(--oz-radius-sm);
}
.mp-name { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
.mp-title { display: flex; align-items: baseline; gap: 8px; min-width: 0; }
.mp-title strong { font-size: 13px; font-weight: 600; color: var(--oz-ink); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.mp-code { font-family: ui-monospace, SFMono-Regular, Consolas, monospace; font-size: 11px; color: var(--oz-muted); flex-shrink: 0; }
.mp-spec { font-size: 11.5px; color: var(--oz-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.mp-remove {
	width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center;
	border: none; background: none; color: var(--oz-muted); cursor: pointer; transition: background .12s, color .12s;
}
.mp-remove:hover { background: var(--oz-danger-soft); color: var(--oz-danger); }
.mp-remove:focus-visible { outline: 2px solid var(--oz-accent); outline-offset: 1px; }
.mp-search { position: relative; }
.mp-results {
	position: absolute; top: calc(100% + 4px); left: 0; right: 0; z-index: 30;
	max-height: 260px; overflow-y: auto; padding: 4px;
	background: var(--oz-surface); border: 1px solid var(--oz-border); border-radius: var(--oz-radius-sm);
	box-shadow: var(--oz-shadow);
}
.mp-result {
	display: grid; grid-template-columns: minmax(0, 1fr) 110px 80px 70px; gap: 10px; align-items: center;
	width: 100%; padding: 8px 10px; border: none; border-radius: 6px; background: none;
	font: inherit; font-size: 12.5px; text-align: left; color: var(--oz-ink); cursor: pointer;
}
.mp-result:hover:not(:disabled), .mp-result:focus-visible { background: var(--oz-surface-2); outline: none; }
.mp-result:disabled { cursor: default; }
.mp-result:disabled .mp-result-name { color: var(--oz-muted); }
.mp-result-name { font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.mp-type { font-size: 11px; color: var(--oz-muted); }
.mp-picked { display: inline-flex; align-items: center; gap: 3px; justify-self: end; font-size: 11px; font-weight: 600; color: var(--oz-success); }
.mp-noresult { padding: 12px; text-align: center; font-size: 12px; color: var(--oz-muted); }

@media (max-width: 640px) {
	.mp-row { grid-template-columns: minmax(0, 1fr) 32px; row-gap: 6px; }
	.mp-row .mp-role { grid-column: 1 / -1; grid-row: 2; }
	.mp-name { grid-column: 1; grid-row: 1; }
	.mp-remove { grid-column: 2; grid-row: 1; }
	.mp-result { grid-template-columns: minmax(0, 1fr) auto; row-gap: 2px; }
	.mp-result .mp-code { grid-column: 1; grid-row: 2; }
	.mp-result .mp-type { grid-column: 2; grid-row: 1; justify-self: end; }
	.mp-result .mp-picked { grid-column: 2; grid-row: 2; }
}
</style>
