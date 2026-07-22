<template>
	<Teleport to="body">
		<div class="modal-overlay" :class="{ open: modelValue }" @click.self="close">
			<div class="modal cad-modal" role="dialog" aria-modal="true" aria-labelledby="cad-title">
				<template v-if="category">
					<div class="modal-header">
						<div class="modal-title">
							<div class="modal-title-icon" style="background: rgb(var(--color-primary-soft))">🏷️</div>
							<div>
								<h4 id="cad-title">{{ category.name }} — Özellikler</h4>
								<p>Bu kategorideki ürünlerde görünecek özel alanları tanımla</p>
							</div>
						</div>
						<button class="modal-close" @click="close" aria-label="Kapat">
							<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
								<path d="M18 6L6 18M6 6l12 12" />
							</svg>
						</button>
					</div>

					<div class="modal-body cad-body">
						<div v-if="!definitions.length" class="cad-empty">Henüz özellik tanımı yok.</div>
						<ul v-else class="cad-list">
							<li v-for="def in definitions" :key="def.id" class="cad-row">
								<div class="cad-row-info">
									<span class="cad-key">{{ def.key }}</span>
									<span class="cad-label">{{ def.label }}</span>
									<span class="cad-type">{{ typeLabel(def.type) }}</span>
									<span v-if="def.required" class="cad-required">zorunlu</span>
								</div>
								<button class="cad-remove" @click="remove(def)" title="Sil">🗑️</button>
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
							<button class="btn btn-primary btn-with-icon" type="submit" :disabled="busy">
								<svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
									<path d="M12 5v14M5 12h14" />
								</svg>
								Ekle
							</button>
						</form>
					</div>

					<div class="modal-divider"></div>

					<div class="modal-footer">
						<button class="btn btn-ghost" @click="close">Kapat</button>
					</div>
				</template>
			</div>
		</div>
	</Teleport>
</template>

<script setup>
import { ref, reactive, computed, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import CustomSelect from './CustomSelect.vue'

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

function close() {
	emit('update:modelValue', false)
}

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
.cad-modal { max-width: 560px; width: 100%; }
.cad-body { display: flex; flex-direction: column; gap: 14px; }

.cad-empty { color: #aaa; font-size: 12.5px; font-style: italic; }

.cad-list { display: flex; flex-direction: column; gap: 6px; }
.cad-row {
	display: flex; align-items: center; justify-content: space-between;
	padding: 8px 12px;
	background: #fafafe;
	border: 1px solid #f0f0f5;
	border-radius: 9px;
}
.cad-row-info { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.cad-key {
	font-family: 'SF Mono', Menlo, Consolas, monospace;
	font-size: 11.5px; font-weight: 700; color: #1a1a2e;
	background: #eee; padding: 2px 7px; border-radius: 5px;
}
.cad-label { font-size: 12.5px; color: #444; }
.cad-type { font-size: 11px; color: #888; }
.cad-required { font-size: 10.5px; font-weight: 700; color: #dc2626; }
.cad-remove {
	background: none; border: none; cursor: pointer; font-size: 13px;
	padding: 4px 6px; border-radius: 6px; flex-shrink: 0;
}
.cad-remove:hover { background: #fee2e2; }

.cad-divider { height: 1px; background: #f0f0f5; }

.cad-form { display: flex; flex-direction: column; gap: 10px; }
.cad-form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }

.form-group { display: flex; flex-direction: column; gap: 5px; }
.form-label { font-size: 11.5px; font-weight: 600; color: #555; }
.form-input {
	height: 38px; padding: 0 12px;
	border: 1px solid #e0e0ea; border-radius: 8px;
	font-size: 13px; color: #1a1a2e;
}
.form-error { color: #dc2626; font-size: 11.5px; }
.toggle-row { display: flex; align-items: center; gap: 7px; font-size: 12.5px; color: #444; cursor: pointer; }
</style>
