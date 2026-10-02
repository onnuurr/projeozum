<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import { OzButton, OzField, OzInput, OzSelect, OzAlert } from '@/Components/Oz'

// Üretim rotası adımları (operasyon + iç/fason + fasoncu + adet fiyatı) düzenleyicisi.
// İş emri sihirbazı, ürün rotası ve Üretilecek Ürün formu aynı şekli kullanır:
// { operation_id, sequence, location_type, fason_supplier_id, unit_cost }.
const props = defineProps({
	operations: { type: Array, default: () => [] },
	fasonSuppliers: { type: Array, default: () => [] },
	// useForm errors nesnesi; alan anahtarları `${errorKey}.${i}.${alan}` biçiminde
	errors: { type: Object, default: () => ({}) },
	errorKey: { type: String, default: 'steps' },
	// false ise son adım da silinebilir (rota opsiyonelse)
	required: { type: Boolean, default: true },
})

const steps = defineModel({ type: Array, default: () => [] })

const operationOptions = computed(() => props.operations.map((o) => ({ value: o.id, label: o.name })))
const fasonOptions = computed(() => props.fasonSuppliers.map((f) => ({ value: f.id, label: f.name })))
const locationOptions = [{ value: 'in_house', label: 'İç atölye' }, { value: 'fason', label: 'Fason' }]
const missingFason = computed(() => steps.value.some((s) => s.location_type === 'fason' && !s.fason_supplier_id))

const renumber = (list) => list.map((s, i) => ({ ...s, sequence: i + 1 }))
const emptyStep = () => ({ operation_id: '', sequence: 0, location_type: 'in_house', fason_supplier_id: null, unit_cost: 0 })

function patch(i, values) {
	steps.value = steps.value.map((s, idx) => (idx === i ? { ...s, ...values } : s))
}
function onOperation(i, id) {
	const op = props.operations.find((o) => o.id === Number(id))
	patch(i, op
		? { operation_id: id, location_type: op.default_location, unit_cost: Number(op.default_unit_cost), fason_supplier_id: null }
		: { operation_id: id })
}
function onLocation(i, value) {
	patch(i, { location_type: value, ...(value !== 'fason' ? { fason_supplier_id: null } : {}) })
}
function move(i, dir) {
	const list = [...steps.value]
	const j = i + dir
	if (j < 0 || j >= list.length) return
	;[list[i], list[j]] = [list[j], list[i]]
	steps.value = renumber(list)
}
function remove(i) { steps.value = renumber(steps.value.filter((_, idx) => idx !== i)) }
function add() { steps.value = renumber([...steps.value, emptyStep()]) }

const err = (i, field) => props.errors[`${props.errorKey}.${i}.${field}`]
</script>

<template>
	<div class="route-editor">
		<p v-if="errors[errorKey]" class="field-error">{{ errors[errorKey] }}</p>
		<OzAlert v-if="!operations.length" tone="warning" title="Tanımlı operasyon yok">
			Rota adımları operasyonlardan seçilir (kesim, dikim, ütü…). Önce
			<Link href="/atelier/operations">Operasyonlar</Link> sayfasından en az bir operasyon ekleyin.
		</OzAlert>

		<div v-for="(s, i) in steps" :key="i" class="step">
			<span class="step-seq">{{ s.sequence }}</span>
			<OzField label="Operasyon" :error="err(i, 'operation_id')">
				<OzSelect :model-value="s.operation_id" :options="operationOptions" placeholder="Operasyon seçin" @update:model-value="(v) => onOperation(i, v)" />
			</OzField>
			<OzField label="Yer" :error="err(i, 'location_type')">
				<OzSelect :model-value="s.location_type" :options="locationOptions" @update:model-value="(v) => onLocation(i, v)" />
			</OzField>
			<OzField label="Adet Fiyatı" :error="err(i, 'unit_cost')">
				<OzInput :model-value="s.unit_cost" type="number" min="0" step="0.01" suffix="₺" @update:model-value="(v) => patch(i, { unit_cost: Number(v) || 0 })" />
			</OzField>
			<div class="step-tools">
				<OzButton size="sm" icon="chevron-up" :disabled="i === 0" :aria-label="`${s.sequence}. adımı yukarı taşı`" @click="move(i, -1)" />
				<OzButton size="sm" icon="chevron-down" :disabled="i === steps.length - 1" :aria-label="`${s.sequence}. adımı aşağı taşı`" @click="move(i, 1)" />
				<OzButton size="sm" icon="trash" :disabled="required && steps.length === 1" :aria-label="`${s.sequence}. adımı kaldır`" @click="remove(i)" />
			</div>
			<OzField v-if="s.location_type === 'fason'" label="Fasoncu" required class="step-fason" :error="err(i, 'fason_supplier_id')">
				<OzSelect :model-value="s.fason_supplier_id" :options="fasonOptions" placeholder="Fasoncu seçin" @update:model-value="(v) => patch(i, { fason_supplier_id: v })" />
			</OzField>
		</div>

		<OzAlert v-if="missingFason && !fasonSuppliers.length" tone="warning" title="Tanımlı fasoncu yok">
			Fason aşama için önce <Link href="/atelier/fason-suppliers">Fasoncular</Link> sayfasından fasoncu ekleyin.
		</OzAlert>
		<OzButton size="sm" icon="plus" class="add" @click="add">Adım Ekle</OzButton>
	</div>
</template>

<style scoped>
.route-editor { display: flex; flex-direction: column; gap: 10px; }
.field-error { margin: 0; font-size: 12px; color: var(--oz-danger); }
.step {
	display: grid; grid-template-columns: 26px minmax(0, 2fr) minmax(0, 1fr) minmax(0, 1fr) auto; gap: 10px; align-items: end;
	padding: 10px 12px; border: 1px solid var(--oz-border-soft); border-radius: var(--oz-radius-sm); background: var(--oz-surface-2);
}
.step-seq {
	align-self: center; width: 24px; height: 24px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center;
	font-size: 11px; font-weight: 700; background: var(--oz-accent-soft); color: var(--oz-accent);
}
.step > * { min-width: 0; }
.step-tools { display: flex; gap: 4px; margin-bottom: 2px; }
.step-fason { grid-column: 2 / -1; }
.add { align-self: flex-start; }

@media (max-width: 640px) {
	.step { grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); }
	.step-seq { grid-column: 1 / -1; }
	.step > :nth-child(2), .step-fason, .step-tools { grid-column: 1 / -1; }
}
</style>
