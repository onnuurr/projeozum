<template>
	<Teleport to="body">
		<aside class="drawer category-form-drawer" :class="{ open: modelValue }" role="dialog" aria-modal="true">
			<div class="drawer-header">
				<div class="drawer-title">
					<div class="drawer-title-icon" :class="isEdit ? 'icon-edit' : 'icon-add'">
						<svg v-if="isEdit" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
							<path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7" />
							<path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z" />
						</svg>
						<svg v-else width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
							<path d="M12 5v14M5 12h14" />
						</svg>
					</div>
					<div>
						<h4>{{ isEdit ? 'Kategoriyi Düzenle' : 'Yeni Kategori Ekle' }}</h4>
						<p>{{ isEdit ? category.name : 'Ürün kataloğuna yeni bir kategori ekleyin' }}</p>
					</div>
				</div>
				<button class="drawer-close" @click="close" aria-label="Kapat">
					<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
						<path d="M18 6L6 18M6 6l12 12" />
					</svg>
				</button>
			</div>

			<div class="drawer-body">
				<div class="form-section form-grid-2">
					<div class="form-group full">
						<label class="form-label">Kategori Adı <span class="required">*</span></label>
						<input
							v-model="form.name"
							class="form-input"
							type="text"
							placeholder="örn. Erkek Tişört"
							@input="onNameInput"
							@keydown.enter="submit"
						/>
						<span v-if="errors.name" class="form-error">{{ errors.name }}</span>
					</div>

					<div class="form-group">
						<label class="form-label">Slug <span class="required">*</span></label>
						<input
							v-model="form.slug"
							class="form-input"
							type="text"
							placeholder="erkek-tisort"
							@input="slugTouched = true"
						/>
						<span v-if="errors.slug" class="form-error">{{ errors.slug }}</span>
					</div>

					<div class="form-group">
						<label class="form-label">İkon (emoji)</label>
						<input
							v-model="form.icon"
							class="form-input"
							type="text"
							maxlength="4"
							placeholder="📦"
						/>
					</div>

					<CustomSelect
						label="Üst Kategori"
						v-model="form.parent_id"
						:options="parentOptions"
						placeholder="— Yok (Ana kategori) —"
					/>

					<CustomSelect
						label="Durum"
						v-model="form.status"
						:options="statusOptions"
					/>

					<div class="form-group">
						<label class="form-label">Sıra</label>
						<input
							v-model.number="form.sort_order"
							class="form-input"
							type="number"
							min="0"
							placeholder="0"
						/>
					</div>
				</div>
			</div>

			<div class="drawer-footer">
				<button class="btn btn-ghost" @click="close" :disabled="busy">İptal</button>
				<button class="btn btn-primary btn-with-icon" @click="submit" :disabled="busy">
					<svg v-if="isEdit" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
						<path d="M20 6L9 17l-5-5" />
					</svg>
					<svg v-else width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
						<path d="M12 5v14M5 12h14" />
					</svg>
					{{ isEdit ? 'Değişiklikleri Kaydet' : 'Kategori Ekle' }}
				</button>
			</div>
		</aside>
	</Teleport>
</template>

<script setup>
import { reactive, computed, ref, watch } from 'vue'
import CustomSelect from './CustomSelect.vue'

const props = defineProps({
	modelValue: { type: Boolean, default: false },
	category: { type: Object, default: null },
	parentCategories: { type: Array, default: () => [] },
	busy: { type: Boolean, default: false },
	errors: { type: Object, default: () => ({}) },
})

const emit = defineEmits(['update:modelValue', 'submit'])

const isEdit = computed(() => !!props.category)

const statusOptions = [
	{ value: 'active', label: 'Aktif', dot: '#16a34a' },
	{ value: 'passive', label: 'Pasif', dot: '#dc2626' },
]

const parentOptions = computed(() => {
	const excludeId = isEdit.value ? props.category?.id : null
	return [
		{ value: null, label: '— Yok (Ana kategori) —' },
		...props.parentCategories
			.filter((c) => c.id !== excludeId)
			.map((c) => ({ value: c.id, label: c.name })),
	]
})

const slugTouched = ref(false)

const defaultForm = () => ({
	name: '',
	slug: '',
	icon: '📦',
	parent_id: null,
	status: 'active',
	sort_order: 0,
})

const form = reactive(defaultForm())

function slugify(str) {
	return String(str || '')
		.toLowerCase()
		.replace(/ı/g, 'i').replace(/ş/g, 's').replace(/ç/g, 'c')
		.replace(/ğ/g, 'g').replace(/ü/g, 'u').replace(/ö/g, 'o')
		.replace(/[^a-z0-9]+/g, '-')
		.replace(/^-+|-+$/g, '')
}

function onNameInput() {
	if (!slugTouched.value) form.slug = slugify(form.name)
}

function close() {
	if (props.busy) return
	emit('update:modelValue', false)
}

function submit() {
	emit('submit', {
		id: props.category?.id ?? null,
		mode: isEdit.value ? 'edit' : 'create',
		payload: {
			name: form.name.trim(),
			slug: form.slug.trim(),
			icon: form.icon?.trim() || null,
			parent_id: form.parent_id || null,
			status: form.status,
			sort_order: Number(form.sort_order) || 0,
		},
	})
}

watch(
	() => props.modelValue,
	(open) => {
		if (open) {
			if (props.category) {
				slugTouched.value = true
				Object.assign(form, {
					name: props.category.name || '',
					slug: props.category.slug || '',
					icon: props.category.icon || '📦',
					parent_id: props.category.parent_id ?? null,
					status: props.category.status || 'active',
					sort_order: props.category.sort_order ?? 0,
				})
			} else {
				slugTouched.value = false
				Object.assign(form, defaultForm())
			}
		} else {
			setTimeout(() => {
				slugTouched.value = false
				Object.assign(form, defaultForm())
			}, 250)
		}
	},
)
</script>

<style scoped>
.category-form-drawer {
	width: 480px;
	max-width: calc(100vw - 24px);
}

.icon-add  { background: rgb(var(--color-primary-soft)) !important; color: rgb(var(--color-primary)) !important; }
.icon-edit { background: #fef3c7 !important; color: #d97706 !important; }

.form-section { display: flex; flex-direction: column; gap: 12px; }
.form-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
.form-group { display: flex; flex-direction: column; gap: 5px; }
.form-group.full { grid-column: 1 / -1; }
.required { color: #ef4444; margin-left: 2px; }
.form-error { color: #dc2626; font-size: 11px; margin-top: 2px; }
</style>
