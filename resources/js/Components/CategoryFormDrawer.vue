<script setup>
import { reactive, computed, ref, watch } from 'vue'
import { OzDrawer, OzField, OzInput, OzSelect, OzButton } from '@/Components/Oz'

const props = defineProps({
	category: { type: Object, default: null },
	parentCategories: { type: Array, default: () => [] },
	busy: { type: Boolean, default: false },
	errors: { type: Object, default: () => ({}) },
})

const open = defineModel('open', { type: Boolean, default: false })
const emit = defineEmits(['submit'])

const isEdit = computed(() => !!props.category)

const statusOptions = [
	{ value: 'active', label: 'Aktif' },
	{ value: 'passive', label: 'Pasif' },
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

watch(open, (isOpen) => {
	if (isOpen) {
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
})
</script>

<template>
	<OzDrawer
		v-model:open="open"
		:title="isEdit ? 'Kategoriyi Düzenle' : 'Yeni Kategori'"
		:subtitle="isEdit ? category.name : 'Ürün kataloğuna yeni bir kategori ekleyin'"
		:icon="isEdit ? 'edit' : 'plus'"
		size="md"
		:persistent="busy"
	>
		<form class="form" @submit.prevent="submit">
			<OzField label="Kategori Adı" required :error="errors.name">
				<OzInput v-model="form.name" placeholder="örn. Erkek Tişört" @input="onNameInput" />
			</OzField>
			<div class="row-2">
				<OzField label="Slug" required :error="errors.slug">
					<OzInput v-model="form.slug" class="mono" placeholder="erkek-tisort" @input="slugTouched = true" />
				</OzField>
				<OzField label="İkon (emoji)">
					<OzInput v-model="form.icon" maxlength="4" placeholder="📦" />
				</OzField>
			</div>
			<div class="row-2">
				<OzField label="Üst Kategori" :error="errors.parent_id">
					<OzSelect v-model="form.parent_id" :options="parentOptions" />
				</OzField>
				<OzField label="Durum">
					<OzSelect v-model="form.status" :options="statusOptions" />
				</OzField>
			</div>
			<OzField label="Sıra" :error="errors.sort_order">
				<OzInput v-model.number="form.sort_order" type="number" min="0" placeholder="0" />
			</OzField>
			<button type="submit" hidden />
		</form>
		<template #footer>
			<OzButton :disabled="busy" @click="open = false">İptal</OzButton>
			<OzButton variant="primary" :icon="isEdit ? 'check' : 'plus'" :loading="busy" @click="submit">{{ isEdit ? 'Değişiklikleri Kaydet' : 'Kategori Ekle' }}</OzButton>
		</template>
	</OzDrawer>
</template>

<style scoped>
.mono { font-family: ui-monospace, monospace; }
.form { display: flex; flex-direction: column; gap: 14px; }
.row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
@media (max-width: 560px) {
	.row-2 { grid-template-columns: 1fr; }
}
</style>
