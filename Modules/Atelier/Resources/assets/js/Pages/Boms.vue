<script setup>
import { ref, computed, inject, watch } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import {
	OzPageHeader, OzModuleTabs, OzStatTile, OzCard, OzButton, OzPill, OzDropdown, OzEmptyState,
	OzDrawer, OzField, OzInput, OzCombobox, OzAlert, useOzConfirm,
} from '@/Components/Oz'
import { formatNumber } from '../atelierNav'
import { useCan } from '@/composables/useCan'

defineOptions({ layout: AppLayout })

const props = defineProps({
	boms: { type: Array, default: () => [] },
	products: { type: Array, default: () => [] },
	materials: { type: Array, default: () => [] },
})

const { can } = useCan()
const canManage = computed(() => can('atelier.bom.manage'))
const showToast = inject('showToast', null)
const confirm = useOzConfirm()

/* ── özet ── */
const lineCount = computed(() => props.boms.reduce((s, b) => s + b.lines.length, 0))
const usedMaterials = computed(() => new Set(props.boms.flatMap((b) => b.lines.map((l) => l.materialId))).size)
const productsWithoutBom = computed(() => {
	const withBom = new Set(props.boms.map((b) => b.productId))
	return props.products.filter((p) => !withBom.has(p.id)).length
})
const stats = computed(() => [
	{ key: 'boms', label: 'Aktif Reçete', value: props.boms.length, icon: 'receipt', tone: 'accent' },
	{ key: 'lines', label: 'Malzeme Satırı', value: lineCount.value, icon: 'rows', tone: 'info' },
	{ key: 'materials', label: 'Kullanılan Hammadde', value: usedMaterials.value, icon: 'package', tone: 'success' },
	{ key: 'missing', label: 'Reçetesiz Ürün', value: productsWithoutBom.value, icon: 'alert', tone: productsWithoutBom.value ? 'warning' : 'success' },
])

/* ── liste + arama ── */
const query = ref('')
const fold = (s) => (s ?? '').toLocaleLowerCase('tr')
const visibleBoms = computed(() => {
	const q = fold(query.value.trim())
	if (!q) return props.boms
	return props.boms.filter((b) => fold(b.productName).includes(q) || fold(b.name).includes(q)
		|| b.lines.some((l) => fold(l.materialName).includes(q)))
})

const bomActions = [{ key: 'remove', label: 'Sil', icon: 'trash', danger: true }]
async function remove(b) {
	const ok = await confirm.danger('Reçeteyi Sil', `${b.productName} ürününün "${b.name}" reçetesi kalıcı olarak silinecek.`)
	if (ok) router.delete(`/atelier/boms/${b.id}`, { preserveScroll: true })
}

/* ── yeni reçete ── */
const productOptions = computed(() => props.products.map((p) => ({ value: p.id, label: p.name, description: p.sku })))
const materialOptions = computed(() => props.materials.map((m) => ({ value: m.id, label: m.name, description: m.unit })))
const unitOf = (id) => props.materials.find((m) => m.id === id)?.unit ?? ''
const hasBom = (productId) => props.boms.some((b) => b.productId === productId)

const emptyLine = () => ({ material_id: '', quantity_per_unit: 1, waste_pct: 0 })
const emptyForm = () => ({ product_id: '', name: 'Varsayılan Reçete', lines: [emptyLine()] })
const form = useForm(emptyForm())
const formOpen = ref(false)

function resetForm() {
	form.defaults(emptyForm())
	form.reset()
	form.clearErrors()
}
watch(formOpen, (open) => { if (!open) setTimeout(resetForm, 250) })

function openCreate() {
	resetForm()
	formOpen.value = true
}
function addLine() { form.lines.push(emptyLine()) }
function removeLine(i) { form.lines.splice(i, 1) }
const lineError = (i, field) => form.errors[`lines.${i}.${field}`]

function submit() {
	if (form.processing) return
	form.post('/atelier/boms', {
		preserveScroll: true,
		onSuccess: () => { formOpen.value = false },
		onError: (errs) => showToast?.({ type: 'error', title: 'Kayıt başarısız', message: Object.values(errs)[0] || 'Doğrulama hatası.' }),
	})
}
</script>

<template>
	<Head title="Reçeteler" />

	<OzPageHeader title="Reçeteler (BOM)">
		<template #subtitle><b>{{ boms.length }}</b> aktif reçete · ürün başına birim malzeme tüketimi</template>
		<template #actions>
			<OzButton v-if="canManage" variant="primary" icon="plus" @click="openCreate">Yeni Reçete</OzButton>
		</template>
	</OzPageHeader>
	<OzModuleTabs />

	<div class="stats">
		<OzStatTile v-for="s in stats" :key="s.key" :label="s.label" :value="s.value" :icon="s.icon" :tone="s.tone" />
	</div>

	<OzCard title="Reçete Listesi" :meta="query ? `${visibleBoms.length} / ${boms.length}` : `${boms.length} kayıt`">
		<div v-if="boms.length" class="toolbar">
			<OzInput v-model="query" icon="search" placeholder="Ürün, reçete ya da hammadde ara…" class="search" />
		</div>

		<OzEmptyState v-if="!boms.length" icon="receipt" title="Henüz reçete tanımlanmamış" description="Bir ürünün birim başına tükettiği hammaddeleri tanımlayın; iş emri açarken malzeme ihtiyacı buradan hesaplanır.">
			<template v-if="canManage" #actions><OzButton size="sm" variant="primary" icon="plus" @click="openCreate">Yeni Reçete</OzButton></template>
		</OzEmptyState>
		<OzEmptyState v-else-if="!visibleBoms.length" icon="search" title="Eşleşen reçete yok" compact>
			<template #actions><OzButton size="sm" icon="x" @click="query = ''">Aramayı Temizle</OzButton></template>
		</OzEmptyState>

		<div v-else class="bom-grid">
			<article v-for="b in visibleBoms" :key="b.id" class="bom">
				<header class="bom-head">
					<div class="bom-title">
						<span class="bom-product">{{ b.productName }}</span>
						<OzPill tone="info">{{ b.name }}</OzPill>
					</div>
					<OzDropdown v-if="canManage" :items="bomActions" :label="`${b.productName} reçetesi işlemleri`" @select="remove(b)" />
				</header>
				<table class="lines">
					<thead>
						<tr>
							<th>Hammadde</th>
							<th class="num">Birim / adet</th>
							<th class="num">Fire</th>
						</tr>
					</thead>
					<tbody>
						<tr v-for="(l, i) in b.lines" :key="i">
							<td>{{ l.materialName }} <span class="unit">{{ l.unit }}</span></td>
							<td class="num mono">{{ formatNumber(l.quantityPerUnit, 4) }}</td>
							<td class="num mono dim">%{{ formatNumber(l.wastePct) }}</td>
						</tr>
					</tbody>
				</table>
			</article>
		</div>
	</OzCard>

	<OzDrawer v-model:open="formOpen" title="Yeni Reçete" subtitle="Ürünün bir adedi için gereken hammaddeler" icon="receipt" size="lg" :persistent="form.processing">
		<form class="form" @submit.prevent="submit">
			<div class="row-2">
				<OzField label="Ürün" required :error="form.errors.product_id">
					<OzCombobox v-model="form.product_id" :options="productOptions" placeholder="Ürün seçin" search-placeholder="Ürün ya da SKU ara…" />
				</OzField>
				<OzField label="Reçete Adı" required :error="form.errors.name">
					<OzInput v-model="form.name" placeholder="Varsayılan Reçete" />
				</OzField>
			</div>
			<OzAlert v-if="form.product_id && hasBom(form.product_id)" tone="warning" title="Bu ürünün aktif bir reçetesi var">
				Kaydettiğinizde yeni reçete aktif olur, mevcut reçete pasife alınır.
			</OzAlert>

			<section class="lines-edit">
				<div class="lines-edit-head">
					<span class="section-title">Malzeme Satırları</span>
					<span class="dim">{{ form.lines.length }} satır</span>
				</div>
				<p v-if="form.errors.lines" class="line-error">{{ form.errors.lines }}</p>
				<div v-for="(line, i) in form.lines" :key="i" class="line">
					<span class="line-no">{{ i + 1 }}</span>
					<OzField label="Hammadde" :error="lineError(i, 'material_id')" class="line-material">
						<OzCombobox v-model="line.material_id" :options="materialOptions" placeholder="Hammadde seçin" />
					</OzField>
					<OzField label="Birim / adet" :error="lineError(i, 'quantity_per_unit')">
						<OzInput v-model.number="line.quantity_per_unit" type="number" min="0" step="0.0001" :suffix="unitOf(line.material_id)" />
					</OzField>
					<OzField label="Fire" :error="lineError(i, 'waste_pct')">
						<OzInput v-model.number="line.waste_pct" type="number" min="0" step="0.01" suffix="%" />
					</OzField>
					<OzButton class="line-remove" size="sm" icon="trash" :disabled="form.lines.length === 1" :aria-label="`${i + 1}. satırı kaldır`" @click="removeLine(i)" />
				</div>
				<OzButton size="sm" icon="plus" @click="addLine">Satır Ekle</OzButton>
			</section>
			<button type="submit" hidden />
		</form>
		<template #footer>
			<OzButton :disabled="form.processing" @click="formOpen = false">İptal</OzButton>
			<OzButton variant="primary" icon="check" :loading="form.processing" @click="submit">Kaydet</OzButton>
		</template>
	</OzDrawer>
</template>

<style scoped>
.stats { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; margin-bottom: 14px; }
@media (max-width: 1040px) { .stats { grid-template-columns: repeat(2, minmax(0, 1fr)); } }

.mono { font-family: ui-monospace, monospace; font-size: 12px; }
.dim { color: var(--oz-muted); }
.toolbar { padding: 0 18px 12px; }
.search { max-width: 360px; }

.bom-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(min(360px, 100%), 1fr)); gap: 12px; padding: 0 18px 18px; }
.bom { border: 1px solid var(--oz-border); border-radius: var(--oz-radius-sm); background: var(--oz-surface); overflow: hidden; }
.bom-head { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 10px 12px 10px 14px; border-bottom: 1px solid var(--oz-border-soft); background: var(--oz-surface-2); }
.bom-title { display: flex; align-items: center; gap: 8px; min-width: 0; flex-wrap: wrap; }
.bom-product { font-weight: 700; font-size: 13px; color: var(--oz-ink-soft); }
.lines { width: 100%; border-collapse: collapse; font-size: 12.5px; }
.lines th { text-align: left; padding: 8px 14px; font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: var(--oz-muted); }
.lines td { padding: 8px 14px; border-top: 1px solid var(--oz-border-soft); color: var(--oz-ink); }
.lines .num { text-align: right; }
.unit { font-size: 10.5px; color: var(--oz-muted); background: var(--oz-surface-2); border: 1px solid var(--oz-border-soft); padding: 0 6px; border-radius: 4px; margin-left: 4px; }

.form { display: flex; flex-direction: column; gap: 14px; }
.row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.lines-edit { display: flex; flex-direction: column; gap: 10px; }
.lines-edit-head { display: flex; align-items: baseline; justify-content: space-between; font-size: 12px; }
.section-title { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: var(--oz-muted); }
.line-error { margin: 0; font-size: 12px; color: var(--oz-danger); }
.line {
	display: grid; grid-template-columns: 22px minmax(0, 2fr) minmax(0, 1fr) minmax(0, 1fr) auto; gap: 10px; align-items: end;
	padding: 10px 12px; border: 1px solid var(--oz-border-soft); border-radius: var(--oz-radius-sm); background: var(--oz-surface-2);
}
.line-no { align-self: center; font-size: 12px; font-weight: 700; color: var(--oz-muted); }
.line-remove { margin-bottom: 2px; }
@media (max-width: 560px) {
	.row-2 { grid-template-columns: 1fr; }
	.line { grid-template-columns: 1fr 1fr; }
	.line-no { display: none; }
	.line-material { grid-column: 1 / -1; }
}
</style>
