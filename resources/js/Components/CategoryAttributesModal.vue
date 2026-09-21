<template>
	<AppModal
		:model-value="modelValue"
		:title="category ? `${category.name} — Özellikler` : ''"
		subtitle="Bu kategorideki ürünlerde görünecek özel alanları tanımla"
		size="md"
		@update:model-value="$emit('update:modelValue', $event)"
	>
		<template #icon><Tag :size="16" /></template>

		<div class="cad-body">
			<div v-if="!definitions.length" class="cad-empty">Henüz özellik tanımı yok.</div>
			<ul v-else class="cad-list">
				<li v-for="def in definitions" :key="def.id" class="cad-row">
					<div class="cad-row-info">
						<span class="cad-key">{{ def.key }}</span>
						<span class="cad-label">{{ def.label }}</span>
						<span class="cad-type">{{ typeLabel(def.type) }}</span>
						<span v-if="def.required" class="cad-required">zorunlu</span>
					</div>
					<button class="cad-remove" @click="remove(def)" title="Sil"><Trash2 :size="13" /></button>
				</li>
			</ul>

			<div class="cad-divider"></div>

			<form class="cad-form" @submit.prevent="submit">
				<div class="cad-form-grid">
					<div class="form-group">
						<label class="form-label">Anahtar</label>
						<input v-model="form.key" class="form-input" type="text" placeholder="örn. yaka" />
					</div>
					<div class="form-group">
						<label class="form-label">Etiket</label>
						<input v-model="form.label" class="form-input" type="text" placeholder="örn. Yaka Tipi" />
					</div>
					<div class="form-group">
						<label class="form-label">Tip</label>
						<CustomSelect v-model="form.type" :options="typeOptions" :show-label="false" />
					</div>
					<div class="form-group">
						<label class="toggle-row">
							<input type="checkbox" v-model="form.required" />
							<span class="toggle-text">Zorunlu</span>
						</label>
					</div>
				</div>
				<div v-if="form.type === 'enum'" class="form-group">
					<label class="form-label">Seçenekler (virgülle ayır)</label>
					<input v-model="optionsText" class="form-input" type="text" placeholder="V yaka, Bisiklet yaka, Polo yaka" />
				</div>
				<span v-if="error" class="form-error">{{ error }}</span>
				<Button variant="primary" with-icon type="submit" :loading="busy">
					<template #leading><Plus :size="13" /></template>
					Ekle
				</Button>
			</form>
		</div>

		<template #footer="{ close: closeModal }">
			<Button variant="ghost" @click="closeModal">Kapat</Button>
		</template>
	</AppModal>
</template>

<script setup>
import { ref, reactive, computed, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import { Tag, Trash2, Plus } from 'lucide-vue-next'
import CustomSelect from './CustomSelect.vue'
import Button from './Button.vue'
import AppModal from './AppModal.vue'

const props = defineProps({
	modelValue: { type: Boolean, default: false },
	category: { type: Object, default: null },
})

const emit = defineEmits(['update:modelValue', 'changed'])

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

watch(
	() => props.modelValue,
	(open) => {
		if (open) {
			definitions.value = [...(props.category?.attributeDefinitions || [])]
			Object.assign(form, defaultFormState())
			optionsText.value = ''
			error.value = ''
		}
	},
)

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

<style scoped>
.cad-body { display: flex; flex-direction: column; gap: 14px; }

.cad-empty { color: rgb(var(--color-muted-rgb)); font-size: 12.5px; font-style: italic; }

.cad-list { display: flex; flex-direction: column; gap: 6px; }
.cad-row {
	display: flex; align-items: center; justify-content: space-between;
	padding: 8px 12px;
	background: rgb(var(--color-bg-rgb) / .5);
	border: 1px solid rgb(var(--color-border-rgb));
	border-radius: 9px;
}
.cad-row-info { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.cad-key {
	font-family: 'SF Mono', Menlo, Consolas, monospace;
	font-size: 11.5px; font-weight: 700; color: rgb(var(--color-ink-rgb));
	background: rgb(var(--color-border-rgb)); padding: 2px 7px; border-radius: 5px;
}
.cad-label { font-size: 12.5px; color: rgb(var(--color-ink-rgb) / .8); }
.cad-type { font-size: 11px; color: rgb(var(--color-muted-rgb)); }
.cad-required { font-size: 10.5px; font-weight: 700; color: rgb(var(--color-danger-rgb)); }
.cad-remove {
	background: none; border: none; cursor: pointer; color: rgb(var(--color-muted-rgb));
	display: inline-flex; align-items: center;
	padding: 4px 6px; border-radius: 6px; flex-shrink: 0;
}
.cad-remove:hover { background: rgb(var(--color-danger-rgb) / .12); color: rgb(var(--color-danger-rgb)); }

.cad-divider { height: 1px; background: rgb(var(--color-border-rgb)); }

.cad-form { display: flex; flex-direction: column; gap: 10px; }
.cad-form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }

.form-group { display: flex; flex-direction: column; gap: 5px; }
.form-label { font-size: 11.5px; font-weight: 600; color: rgb(var(--color-muted-rgb)); }
.form-input {
	height: 38px; padding: 0 12px;
	border: 1px solid rgb(var(--color-border-rgb)); border-radius: 8px;
	font-size: 13px; color: rgb(var(--color-ink-rgb));
}
.form-error { color: rgb(var(--color-danger-rgb)); font-size: 11.5px; }
.toggle-row { display: flex; align-items: center; gap: 7px; font-size: 12.5px; color: rgb(var(--color-ink-rgb) / .8); cursor: pointer; }
</style>
