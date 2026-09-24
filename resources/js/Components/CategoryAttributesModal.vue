<script setup>
import { ref, reactive, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import { OzModal, OzField, OzInput, OzSelect, OzSwitch, OzButton, OzIcon } from '@/Components/Oz'

const props = defineProps({
	category: { type: Object, default: null },
})

const open = defineModel('open', { type: Boolean, default: false })
const emit = defineEmits(['changed'])

const definitions = ref([])
const busy = ref(false)
const error = ref('')
const optionsText = ref('')

const typeOptions = [
	{ value: 'string', label: 'Metin' },
	{ value: 'enum', label: 'Seçenekli (enum)' },
	{ value: 'number', label: 'Sayı' },
	{ value: 'boolean', label: 'Evet/Hayır' },
]

function typeLabel(type) {
	return typeOptions.find((o) => o.value === type)?.label ?? type
}

function defaultFormState() {
	return { key: '', label: '', type: 'string', required: false }
}

const form = reactive(defaultFormState())

watch(open, (isOpen) => {
	if (isOpen) {
		definitions.value = [...(props.category?.attributeDefinitions || [])]
		Object.assign(form, defaultFormState())
		optionsText.value = ''
		error.value = ''
	}
})

function submit() {
	if (busy.value) return
	if (!form.key.trim() || !form.label.trim()) {
		error.value = 'Anahtar ve etiket zorunludur.'
		return
	}

	busy.value = true
	error.value = ''

	const payload = {
		key: form.key.trim(),
		label: form.label.trim(),
		type: form.type,
		required: form.required,
		options: form.type === 'enum'
			? optionsText.value.split(',').map((s) => s.trim()).filter(Boolean)
			: [],
	}

	router.post(`/products/categories/${props.category.id}/attribute-definitions`, payload, {
		preserveScroll: true,
		preserveState: true,
		onSuccess: () => {
			Object.assign(form, defaultFormState())
			optionsText.value = ''
			emit('changed')
		},
		onError: (errs) => {
			error.value = Object.values(errs)[0] || 'Doğrulama hatası.'
		},
		onFinish: () => { busy.value = false },
	})
}

function remove(def) {
	router.delete(`/products/categories/${props.category.id}/attribute-definitions/${def.id}`, {
		preserveScroll: true,
		preserveState: true,
		onSuccess: () => {
			definitions.value = definitions.value.filter((d) => d.id !== def.id)
			emit('changed')
		},
	})
}
</script>

<template>
	<OzModal v-model:open="open" :title="category ? `${category.name} — Özellikler` : ''" subtitle="Bu kategorideki ürünlerde görünecek özel alanları tanımla" icon="tag" size="md">
		<div class="cad-body">
			<p v-if="!definitions.length" class="cad-empty">Henüz özellik tanımı yok.</p>
			<ul v-else class="cad-list">
				<li v-for="def in definitions" :key="def.id" class="cad-row">
					<div class="cad-row-info">
						<span class="cad-key">{{ def.key }}</span>
						<span class="cad-label">{{ def.label }}</span>
						<span class="cad-type">{{ typeLabel(def.type) }}</span>
						<span v-if="def.required" class="cad-required">zorunlu</span>
					</div>
					<button class="cad-remove" type="button" title="Sil" @click="remove(def)"><OzIcon name="trash" :size="13" /></button>
				</li>
			</ul>

			<div class="cad-divider"></div>

			<form class="cad-form" @submit.prevent="submit">
				<div class="cad-form-grid">
					<OzField label="Anahtar">
						<OzInput v-model="form.key" placeholder="örn. yaka" />
					</OzField>
					<OzField label="Etiket">
						<OzInput v-model="form.label" placeholder="örn. Yaka Tipi" />
					</OzField>
					<OzField label="Tip">
						<OzSelect v-model="form.type" :options="typeOptions" />
					</OzField>
					<div class="switch-cell">
						<OzSwitch v-model="form.required" label="Zorunlu" />
					</div>
				</div>
				<OzField v-if="form.type === 'enum'" label="Seçenekler (virgülle ayır)">
					<OzInput v-model="optionsText" placeholder="V yaka, Bisiklet yaka, Polo yaka" />
				</OzField>
				<span v-if="error" class="form-error">{{ error }}</span>
				<OzButton variant="primary" icon="plus" type="submit" :loading="busy">Ekle</OzButton>
			</form>
		</div>

		<template #footer>
			<OzButton @click="open = false">Kapat</OzButton>
		</template>
	</OzModal>
</template>

<style scoped>
.cad-body { display: flex; flex-direction: column; gap: 14px; }

.cad-empty { color: var(--oz-muted); font-size: 12.5px; font-style: italic; margin: 0; }

.cad-list { display: flex; flex-direction: column; gap: 6px; list-style: none; margin: 0; padding: 0; }
.cad-row {
	display: flex; align-items: center; justify-content: space-between;
	padding: 8px 12px;
	background: var(--oz-surface-2);
	border: 1px solid var(--oz-border);
	border-radius: 9px;
}
.cad-row-info { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.cad-key {
	font-family: ui-monospace, monospace;
	font-size: 11.5px; font-weight: 700; color: var(--oz-ink-soft);
	background: var(--oz-border); padding: 2px 7px; border-radius: 5px;
}
.cad-label { font-size: 12.5px; color: var(--oz-ink-soft); }
.cad-type { font-size: 11px; color: var(--oz-muted); }
.cad-required { font-size: 10.5px; font-weight: 700; color: var(--oz-danger); }
.cad-remove {
	background: none; border: none; cursor: pointer; color: var(--oz-muted);
	display: inline-flex; align-items: center;
	padding: 4px 6px; border-radius: 6px; flex-shrink: 0;
}
.cad-remove:hover { background: var(--oz-danger-soft); color: var(--oz-danger); }

.cad-divider { height: 1px; background: var(--oz-border); }

.cad-form { display: flex; flex-direction: column; gap: 10px; align-items: flex-start; }
.cad-form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; width: 100%; }

.form-error { color: var(--oz-danger); font-size: 11.5px; }
.switch-cell { display: flex; align-items: center; height: 100%; padding-top: 20px; }
</style>
