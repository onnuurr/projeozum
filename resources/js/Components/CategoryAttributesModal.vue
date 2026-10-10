<script setup>
import { ref, computed, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import { OzModal, OzField, OzCombobox, OzSwitch, OzButton, OzIcon } from '@/Components/Oz'
import { useCan } from '@/composables/useCan'

// Kategorinin global ürün özellikleri seçimi (Ürün Ayarları → Özellikler'de tanımlanır).
// Alt kategoriler üst kategorilerin seçimini miras alır; burada yalnızca kategorinin kendi seçimi
// düzenlenir. Kaydet bütün listeyi yazar (PUT /products/categories/{id}/attributes).
const props = defineProps({
	category: { type: Object, default: null },
	categories: { type: Array, default: () => [] },
	productAttributes: { type: Array, default: () => [] },
})

const open = defineModel('open', { type: Boolean, default: false })
const emit = defineEmits(['changed'])
const { can } = useCan()

const own = ref([])
const pick = ref('')
const busy = ref(false)
const error = ref('')

const TYPE_LABELS = { select: 'Seçim', multiselect: 'Çoklu seçim', checkbox: 'Onay kutuları', text: 'Metin', textarea: 'Uzun metin', number: 'Sayı', price: 'Fiyat', boolean: 'Evet / Hayır', date: 'Tarih', datetime: 'Tarih-saat', image: 'Görsel', file: 'Dosya' }
const byId = computed(() => Object.fromEntries(props.productAttributes.map((a) => [a.id, a])))

// Üst kategorilerden gelenler: [{ attribute, from, required }]
const inherited = computed(() => {
	const list = []
	const seen = new Set([props.category?.id])
	let parentId = props.category?.parent_id
	while (parentId && !seen.has(parentId)) {
		seen.add(parentId)
		const parent = props.categories.find((c) => c.id === parentId)
		if (!parent) break
		for (const a of parent.productAttributes ?? []) {
			if (byId.value[a.id]) list.push({ attribute: byId.value[a.id], from: parent.name, required: a.required })
		}
		parentId = parent.parent_id
	}
	return list
})

const addOptions = computed(() => props.productAttributes
	.filter((a) => !own.value.some((o) => o.id === a.id))
	.map((a) => {
		const from = inherited.value.find((i) => i.attribute.id === a.id)?.from
		return { value: a.id, label: a.name, description: from ? `${TYPE_LABELS[a.type]} · ${from} kategorisinden zaten geliyor` : TYPE_LABELS[a.type] }
	}))

watch(open, (isOpen) => {
	if (isOpen) {
		own.value = (props.category?.productAttributes ?? []).filter((a) => byId.value[a.id]).map((a) => ({ id: a.id, required: a.required }))
		pick.value = ''
		error.value = ''
	}
})

watch(pick, (id) => {
	if (!id) return
	own.value.push({ id, required: false })
	pick.value = ''
})

function save() {
	if (busy.value) return
	busy.value = true
	error.value = ''
	router.put(`/products/categories/${props.category.id}/attributes`, { attributes: own.value }, {
		preserveScroll: true,
		preserveState: true,
		onSuccess: () => {
			open.value = false
			emit('changed')
		},
		onError: (errs) => { error.value = Object.values(errs)[0] || 'Doğrulama hatası.' },
		onFinish: () => { busy.value = false },
	})
}
</script>

<template>
	<OzModal v-model:open="open" :title="category ? `${category.name} — Özellikler` : ''" subtitle="Bu kategorideki (ve alt kategorilerindeki) ürünlerin formunda görünecek özellikler" icon="tag" size="md">
		<div class="cad-body">
			<p v-if="!productAttributes.length" class="cad-empty">
				Henüz tanımlı özellik yok.
				<a v-if="can('product-settings.view') && can('product-attribute.view')" href="/products/settings?tab=attributes">Ürün Ayarları → Özellikler</a>
				<template v-else>Ürün Ayarları → Özellikler ekranında tanımlanır.</template>
			</p>

			<template v-else>
				<div v-if="inherited.length" class="cad-section">
					<span class="cad-heading">Üst kategoriden gelenler</span>
					<ul class="cad-list">
						<li v-for="i in inherited" :key="`${i.from}-${i.attribute.id}`" class="cad-row is-inherited">
							<div class="cad-row-info">
								<span class="cad-label">{{ i.attribute.name }}</span>
								<span class="cad-type">{{ TYPE_LABELS[i.attribute.type] }}</span>
								<span v-if="i.required" class="cad-required">zorunlu</span>
							</div>
							<span class="cad-from">{{ i.from }}</span>
						</li>
					</ul>
				</div>

				<div class="cad-section">
					<span class="cad-heading">Bu kategoride</span>
					<p v-if="!own.length" class="cad-empty">Bu kategoriye özel özellik seçilmedi.</p>
					<ul v-else class="cad-list">
						<li v-for="(o, idx) in own" :key="o.id" class="cad-row">
							<div class="cad-row-info">
								<span class="cad-label">{{ byId[o.id]?.name }}</span>
								<span class="cad-type">{{ TYPE_LABELS[byId[o.id]?.type] }}</span>
							</div>
							<div class="cad-row-actions">
								<OzSwitch v-if="byId[o.id]?.type !== 'boolean'" v-model="o.required" label="Zorunlu" />
								<button class="cad-remove" type="button" title="Kaldır" @click="own.splice(idx, 1)"><OzIcon name="trash" :size="13" /></button>
							</div>
						</li>
					</ul>
				</div>

				<OzField v-if="addOptions.length" label="Özellik ekle">
					<OzCombobox v-model="pick" :options="addOptions" placeholder="Özellik seçin" search-placeholder="Özellik ara…" />
				</OzField>
				<span v-if="error" class="form-error">{{ error }}</span>
			</template>
		</div>

		<template #footer>
			<OzButton :disabled="busy" @click="open = false">İptal</OzButton>
			<OzButton v-if="productAttributes.length" variant="primary" icon="check" :loading="busy" @click="save">Kaydet</OzButton>
		</template>
	</OzModal>
</template>

<style scoped>
.cad-body { display: flex; flex-direction: column; gap: 16px; }
.cad-section { display: flex; flex-direction: column; gap: 8px; }
.cad-heading { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: var(--oz-muted); }
.cad-empty { color: var(--oz-muted); font-size: 12.5px; margin: 0; }
.cad-empty a { color: var(--oz-accent); font-weight: 600; }
.cad-list { display: flex; flex-direction: column; gap: 6px; list-style: none; margin: 0; padding: 0; }
.cad-row {
	display: flex; align-items: center; justify-content: space-between; gap: 10px;
	padding: 8px 12px;
	background: var(--oz-surface-2);
	border: 1px solid var(--oz-border);
	border-radius: 9px;
}
.cad-row.is-inherited { background: transparent; border-style: dashed; }
.cad-row-info { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.cad-row-actions { display: flex; align-items: center; gap: 10px; }
.cad-label { font-size: 12.5px; font-weight: 600; color: var(--oz-ink-soft); }
.cad-type { font-size: 11px; color: var(--oz-muted); }
.cad-from { font-size: 11px; color: var(--oz-muted); white-space: nowrap; }
.cad-required { font-size: 10.5px; font-weight: 700; color: var(--oz-danger); }
.cad-remove {
	background: none; border: none; cursor: pointer; color: var(--oz-muted);
	display: inline-flex; align-items: center;
	padding: 4px 6px; border-radius: 6px; flex-shrink: 0;
}
.cad-remove:hover { background: var(--oz-danger-soft); color: var(--oz-danger); }
.form-error { color: var(--oz-danger); font-size: 11.5px; }
</style>
