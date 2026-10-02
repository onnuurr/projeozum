<script setup>
import { ref, computed, inject, watch, onMounted } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import axios from 'axios'
import { useDebounceFn } from '@vueuse/core'
import AppLayout from '@/Layouts/AppLayout.vue'
import {
	OzPageHeader, OzModuleTabs, OzStatStrip, OzCard, OzButton, OzTable, OzPill, OzDrawer,
	OzField, OzInput, OzSelect, OzCombobox, OzTextarea, OzAlert, OzSpinner, OzCheckbox,
} from '@/Components/Oz'
import RouteStepsEditor from '../Components/RouteStepsEditor.vue'
import { ORDER_STATUS, ORDER_TONE, formatMoney, formatNumber } from '../atelierNav'
import { useCan } from '@/composables/useCan'

defineOptions({ layout: AppLayout })

const props = defineProps({
	orders: { type: Array, default: () => [] },
	products: { type: Array, default: () => [] },
	warehouses: { type: Array, default: () => [] },
	operations: { type: Array, default: () => [] },
	fasonSuppliers: { type: Array, default: () => [] },
	preselectProductId: { type: Number, default: null },
	pendingCards: { type: Array, default: () => [] },
})

const { can } = useCan()
const canManage = computed(() => can('atelier.production.manage'))
const canEditRoute = computed(() => can('atelier.bom.manage'))
const showToast = inject('showToast', null)

/* ── özet ── */
const countBy = (...statuses) => props.orders.filter((o) => statuses.includes(o.status)).length
const today = new Date().toISOString().slice(0, 10)
const isLate = (o) => !!o.dueDate && o.dueDate < today && !['completed', 'cancelled'].includes(o.status)
const stats = computed(() => [
	{ key: 'waiting', label: 'Taslak / Planlı', value: countBy('draft', 'planned'), icon: 'clock', tone: 'info' },
	{ key: 'progress', label: 'Üretimde', value: countBy('in_progress'), icon: 'factory', tone: 'accent' },
	{ key: 'late', label: 'Termini Geçen', value: props.orders.filter(isLate).length, icon: 'alert', tone: props.orders.some(isLate) ? 'danger' : 'success' },
	{ key: 'done', label: 'Tamamlanan', value: countBy('completed'), icon: 'success', tone: 'success' },
])

/* ── tablo ── */
const statusOptions = Object.entries(ORDER_STATUS).map(([value, label]) => ({ value, label }))
const columns = [
	{ key: 'code', label: 'Kod', sortable: true },
	{ key: 'productName', label: 'Ürün', sortable: true, class: 'oz-cell-strong' },
	{ key: 'status', label: 'Durum', sortable: true, filter: { type: 'select', options: statusOptions } },
	{ key: 'progress', label: 'Stoğa Alınan / Plan', align: 'right', searchable: false },
	{ key: 'dueDate', label: 'Termin', sortable: true, filter: { type: 'date' } },
	{ key: 'totalCost', label: 'Maliyet', align: 'right', sortable: true, class: 'oz-cell-amount' },
	{ key: 'actions', label: '', align: 'right', width: '80px', searchable: false },
]

/* ── yeni iş emri sihirbazı ── */
const productOptions = computed(() => props.products.map((p) => ({ value: p.id, label: p.name, description: p.sku })))
const warehouseOptions = computed(() => props.warehouses.map((w) => ({ value: w.id, label: `${w.name} (${w.code})` })))
const emptyForm = () => ({
	product_id: '', warehouse_id: props.warehouses.length === 1 ? props.warehouses[0].id : '',
	planned_qty: 0, due_date: '', notes: '', items: [], steps: [], save_as_product_route: false,
})
const form = useForm(emptyForm())
const wizardOpen = ref(false)
const requirements = ref([])
const previewLoading = ref(false)

/* ── rota: ürünün rotasından (yoksa operasyon sırasından önerilen) otomatik dolar ── */
const routeSource = ref('')          // product | suggested
const routeLoadedFor = ref(null)     // rotanın yüklendiği ürün; adet değişikliği rotayı ezmesin
const routeSnapshot = ref('')
const stepsKey = (steps) => JSON.stringify(steps.map(({ operation_id, location_type, fason_supplier_id, unit_cost }) => [operation_id, location_type, fason_supplier_id, Number(unit_cost)]))
const routeChanged = computed(() => routeSource.value === 'product' && stepsKey(form.steps) !== routeSnapshot.value)
const showSaveRoute = computed(() => canEditRoute.value && (routeSource.value === 'suggested' || routeChanged.value))
watch(routeChanged, (changed) => { if (changed) form.save_as_product_route = true })

function applyRoute(route) {
	form.steps = route.steps.map((st) => ({ ...st }))
	routeSource.value = route.source
	routeSnapshot.value = stepsKey(form.steps)
	form.save_as_product_route = route.source === 'suggested' && canEditRoute.value
}

function resetForm() {
	form.defaults(emptyForm())
	form.reset()
	form.clearErrors()
	requirements.value = []
	routeSource.value = ''
	routeLoadedFor.value = null
}
watch(wizardOpen, (open) => { if (!open) setTimeout(resetForm, 250) })

function openWizard() {
	resetForm()
	wizardOpen.value = true
}

function openWizardFor(productId) {
	openWizard()
	form.product_id = productId
}

// "Üretilecek Ürün" kaydından yönlendirildiyse sihirbazı o ürünle aç
onMounted(() => {
	if (props.preselectProductId && canManage.value) openWizardFor(props.preselectProductId)
})

// Ürün ya da adet değişince varyantlar + BOM malzeme gereksinimi sunucudan önizlenir
let previewSeq = 0
const loadPreview = useDebounceFn(async () => {
	// Ürün seçilince rota ve varyantlar hemen gelir; malzeme ihtiyacı yalnız adet girilince anlamlı
	if (!form.product_id) {
		requirements.value = []
		return
	}
	const seq = ++previewSeq
	previewLoading.value = true
	try {
		const { data } = await axios.get('/atelier/production-orders/plan-preview', {
			params: { product_id: form.product_id, planned_qty: form.planned_qty },
		})
		if (seq !== previewSeq) return
		requirements.value = form.planned_qty > 0 ? data.requirements : []
		if (routeLoadedFor.value !== form.product_id) {
			applyRoute(data.route)
			routeLoadedFor.value = form.product_id
		}
		// Varyant listesi yalnızca ürün değişince yeniden kurulur; adet değişikliği girilen dağılımı silmez
		const ids = data.variants.map((v) => v.id).join(',')
		if (ids !== form.items.map((i) => i.product_variant_id).join(',')) {
			form.items = data.variants.map((v) => ({ product_variant_id: v.id, planned_qty: 0, label: `${v.size} / ${v.colorName}`, sku: v.sku }))
		}
	} catch {
		if (seq === previewSeq) showToast?.({ type: 'error', title: 'Önizleme alınamadı', message: 'Ürün varyantları ve malzeme ihtiyacı yüklenemedi.' })
	} finally {
		if (seq === previewSeq) previewLoading.value = false
	}
}, 350)
watch(() => form.product_id, () => { form.items = []; loadPreview() })
watch(() => form.planned_qty, loadPreview)

const itemsTotal = computed(() => form.items.reduce((s, i) => s + (Number(i.planned_qty) || 0), 0))
const itemsMismatch = computed(() => form.items.length > 0 && itemsTotal.value !== Number(form.planned_qty))
const requirementsCost = computed(() => requirements.value.reduce((s, r) => s + Number(r.line_cost || 0), 0))
const isShort = (r) => Number(r.available_qty) < Number(r.required_qty)
const hasShortage = computed(() => requirements.value.some(isShort))
const missingFason = computed(() => form.steps.some((s) => s.location_type === 'fason' && !s.fason_supplier_id))

// Planlanan adedi varyantlara eşit böler, artan adetler ilk varyantlara eklenir
function distributeEvenly() {
	const n = form.items.length
	const total = Number(form.planned_qty) || 0
	if (!n) return
	const base = Math.floor(total / n)
	form.items.forEach((it, i) => { it.planned_qty = base + (i < total % n ? 1 : 0) })
}

function submit() {
	if (form.processing) return
	form.transform((d) => ({
		...d,
		items: d.items.map(({ product_variant_id, planned_qty }) => ({ product_variant_id, planned_qty })),
		steps: d.steps.map((s) => ({ ...s, fason_supplier_id: s.location_type === 'fason' ? s.fason_supplier_id : null })),
		save_as_product_route: showSaveRoute.value && d.save_as_product_route,
	})).post('/atelier/production-orders', {
		preserveScroll: true,
		onSuccess: () => { wizardOpen.value = false },
		onError: (errs) => showToast?.({ type: 'error', title: 'İş emri oluşturulamadı', message: Object.values(errs)[0] || 'Doğrulama hatası.' }),
	})
}
</script>

<template>
	<Head title="İş Emirleri" />

	<OzPageHeader title="İş Emirleri">
		<template #subtitle><b>{{ orders.length }}</b> üretim iş emri</template>
		<template #actions>
			<OzButton v-if="canManage" variant="primary" icon="plus" @click="openWizard">Yeni İş Emri</OzButton>
		</template>
	</OzPageHeader>
	<OzModuleTabs />

	<OzStatStrip :items="stats" />

	<OzCard v-if="pendingCards.length" title="Üretim Bekleyen Geçici Kartlar" subtitle="henüz iş emri verilmedi" class="pending">
		<div class="pending-list">
			<div v-for="c in pendingCards" :key="c.id" class="pending-row">
				<div class="pending-label">
					<Link :href="`/products/${c.id}/card`" class="pending-name">{{ c.name }}</Link>
					<span class="mono dim">{{ c.sku }} · {{ c.createdAt }}</span>
				</div>
				<OzPill tone="warning">Geçici Kart</OzPill>
				<OzButton v-if="canManage" variant="primary" size="sm" icon="workflow" @click="openWizardFor(c.id)">İş Emri Ver</OzButton>
			</div>
		</div>
	</OzCard>

	<OzCard title="İş Emri Listesi">
		<OzTable :columns="columns" :rows="orders" row-key="id" searchable search-placeholder="Kod ya da ürün ara…" empty-title="İş emri kaydı yok" min-width="820px">
			<template #cell-code="{ value }"><span class="mono">{{ value }}</span></template>
			<template #cell-status="{ value }">
				<OzPill :tone="ORDER_TONE[value] ?? 'neutral'">{{ ORDER_STATUS[value] ?? value }}</OzPill>
			</template>
			<template #cell-progress="{ row }">
				<span class="mono"><b>{{ row.receivedQty }}</b> <span class="dim">/ {{ row.plannedQty }}</span></span>
			</template>
			<template #cell-dueDate="{ row }">
				<span class="due">
					{{ row.dueDate || '—' }}
					<OzPill v-if="isLate(row)" tone="danger">Gecikti</OzPill>
				</span>
			</template>
			<template #cell-totalCost="{ value }"><span class="mono">{{ formatMoney(value) }}</span></template>
			<template #cell-actions="{ row }">
				<Link :href="`/atelier/production-orders/${row.id}`" class="detail-link">Detay</Link>
			</template>
		</OzTable>
	</OzCard>

	<OzDrawer v-model:open="wizardOpen" title="Yeni İş Emri" subtitle="Ürün, varyant dağılımı ve üretim rotası" icon="workflow" size="lg" :persistent="form.processing">
		<form class="form" @submit.prevent="submit">
			<section class="section">
				<h3 class="section-title">Temel Bilgiler</h3>
				<OzField label="Ürün" required :error="form.errors.product_id">
					<OzCombobox v-model="form.product_id" :options="productOptions" placeholder="Ürün seçin" search-placeholder="Ürün ya da SKU ara…" />
				</OzField>
				<div class="row-3">
					<OzField label="Planlanan Adet" required :error="form.errors.planned_qty">
						<OzInput v-model.number="form.planned_qty" type="number" min="1" step="1" suffix="adet" />
					</OzField>
					<OzField label="Depo" required :error="form.errors.warehouse_id" hint="Bitmiş ürün bu depoya alınır">
						<OzSelect v-model="form.warehouse_id" :options="warehouseOptions" placeholder="Depo seçin" />
					</OzField>
					<OzField label="Termin" :error="form.errors.due_date">
						<OzInput v-model="form.due_date" type="date" />
					</OzField>
				</div>
			</section>

			<section v-if="form.product_id" class="section">
				<div class="section-head">
					<h3 class="section-title">Varyant Dağılımı</h3>
					<OzSpinner v-if="previewLoading" :size="14" />
					<OzButton v-else-if="form.items.length" size="sm" icon="refresh" :disabled="!(form.planned_qty > 0)" @click="distributeEvenly">Eşit Dağıt</OzButton>
				</div>
				<p v-if="form.errors.items" class="field-error">{{ form.errors.items }}</p>
				<p v-if="!previewLoading && !form.items.length" class="dim small">
					Bu ürünün varyantı yok.
				</p>
				<div v-else class="variants">
					<div v-for="it in form.items" :key="it.product_variant_id" class="variant">
						<div class="variant-label">
							<span>{{ it.label }}</span>
							<span v-if="it.sku" class="mono dim">{{ it.sku }}</span>
						</div>
						<OzInput v-model.number="it.planned_qty" type="number" min="0" step="1" class="variant-qty" :aria-label="`${it.label} adedi`" />
					</div>
					<div class="variant-total" :class="{ 'is-off': itemsMismatch }">
						<span>Toplam</span>
						<span class="mono"><b>{{ itemsTotal }}</b> / {{ form.planned_qty || 0 }}</span>
					</div>
				</div>
			</section>

			<section v-if="requirements.length" class="section">
				<h3 class="section-title">Malzeme Gereksinimi <span class="dim">(planlamada rezerve edilir)</span></h3>
				<div class="reqs">
					<div class="req req-head">
						<span>Hammadde</span>
						<span>Gerekli</span>
						<span>Kullanılabilir</span>
						<span>Tutar</span>
					</div>
					<div v-for="(r, i) in requirements" :key="i" class="req" :class="{ 'is-short': isShort(r) }">
						<span class="req-name">{{ r.material_name }}</span>
						<span class="mono">{{ formatNumber(r.required_qty, 3) }} {{ r.unit }}</span>
						<span class="mono" :title="`Stok − diğer iş emirlerinin rezervi (${formatNumber(r.reserved_qty, 3)} ${r.unit})`">{{ formatNumber(r.available_qty, 3) }} {{ r.unit }}</span>
						<span class="mono dim">≈ {{ formatMoney(r.line_cost) }}</span>
					</div>
					<div class="req req-total">
						<span class="req-name">Tahmini malzeme maliyeti</span>
						<span />
						<span />
						<span class="mono">{{ formatMoney(requirementsCost) }}</span>
					</div>
				</div>
				<OzAlert v-if="hasShortage" tone="warning" title="Hammadde yetersiz">
					İş emri taslak olarak oluşturulabilir, ancak eksik hammadde alınmadan planlanamaz.
				</OzAlert>
			</section>
			<OzAlert v-else-if="form.product_id && form.planned_qty > 0 && !previewLoading" tone="warning" title="Reçete bulunamadı">
				Bu ürünün aktif reçetesi yok; iş emri planlanırken hammadde rezerve edilmez.
			</OzAlert>

			<section class="section">
				<div class="section-head">
					<h3 class="section-title">Üretim Rotası</h3>
					<OzPill v-if="routeSource === 'product'" :tone="routeChanged ? 'warning' : 'success'">
						{{ routeChanged ? 'Ürün rotası · bu iş emrinde değiştirildi' : 'Ürün rotası' }}
					</OzPill>
					<OzPill v-else-if="routeSource === 'suggested'" tone="info">Önerilen · operasyon sırası</OzPill>
				</div>
				<p v-if="!form.product_id" class="dim small">Ürün seçilince rota ürünün rotasından otomatik dolar.</p>
				<p v-else-if="routeSource === 'suggested'" class="dim small">
					Bu ürünün kayıtlı rotası yok; tüm operasyonlar sırasıyla önerildi. Gerekmeyenleri silin, sırayı düzenleyin.
				</p>
				<RouteStepsEditor
					v-if="form.product_id && routeSource"
					v-model="form.steps"
					:operations="operations"
					:fason-suppliers="fasonSuppliers"
					:errors="form.errors"
				/>
				<OzCheckbox
					v-if="showSaveRoute"
					v-model="form.save_as_product_route"
					label="Bu rotayı ürünün rotası olarak kaydet"
					description="Sonraki iş emirlerinde bu ürün için rota otomatik gelir."
				/>
			</section>

			<section class="section">
				<OzField label="Not" :error="form.errors.notes">
					<OzTextarea v-model="form.notes" :rows="2" placeholder="Üretime dair açıklama…" />
				</OzField>
			</section>
			<button type="submit" hidden />
		</form>
		<template #footer>
			<OzButton :disabled="form.processing" @click="wizardOpen = false">İptal</OzButton>
			<OzButton variant="primary" icon="check" :loading="form.processing" :disabled="itemsMismatch || missingFason || !form.steps.length" @click="submit">Oluştur</OzButton>
		</template>
	</OzDrawer>
</template>

<style scoped>

.mono { font-family: ui-monospace, monospace; font-size: 12px; }
.dim { color: var(--oz-muted); font-weight: 400; }
.small { font-size: 12.5px; margin: 0; }
.due { display: inline-flex; align-items: center; gap: 8px; }
.detail-link { color: var(--oz-accent); text-decoration: none; padding: 4px 10px; border-radius: 6px; font-size: 12px; font-weight: 600; }
.detail-link:hover { background: var(--oz-accent-soft); }

.pending { margin-bottom: 14px; }
.pending-list { display: flex; flex-direction: column; }
.pending-row { display: grid; grid-template-columns: minmax(0, 1fr) auto auto; align-items: center; gap: 12px; padding: 10px 16px; }
.pending-row + .pending-row { border-top: 1px solid var(--oz-border-soft); }
.pending-label { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
.pending-name { color: var(--oz-ink); font-weight: 600; font-size: 13.5px; text-decoration: none; }
.pending-name:hover { color: var(--oz-accent); }

.form { display: flex; flex-direction: column; gap: 18px; }
.section { display: flex; flex-direction: column; gap: 12px; }
.section + .section { padding-top: 18px; border-top: 1px solid var(--oz-border-soft); }
.section-head { display: flex; align-items: center; justify-content: space-between; gap: 10px; }
.section-title { margin: 0; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: var(--oz-muted); }
.field-error { margin: 0; font-size: 12px; color: var(--oz-danger); }
.row-3 { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; }

.variants { display: flex; flex-direction: column; border: 1px solid var(--oz-border-soft); border-radius: var(--oz-radius-sm); overflow: hidden; }
.variant { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 7px 12px; }
.variant + .variant { border-top: 1px solid var(--oz-border-soft); }
.variant-label { display: flex; flex-direction: column; font-size: 13px; color: var(--oz-ink); }
.variant-qty { width: 120px; }
.variant-total { display: flex; justify-content: space-between; padding: 8px 12px; font-size: 12.5px; background: var(--oz-surface-2); border-top: 1px solid var(--oz-border-soft); color: var(--oz-muted); }
.variant-total.is-off { color: var(--oz-warning); }

.reqs { display: flex; flex-direction: column; border: 1px solid var(--oz-border-soft); border-radius: var(--oz-radius-sm); overflow: hidden; }
.req { display: grid; grid-template-columns: minmax(0, 1fr) 110px 110px 100px; gap: 12px; align-items: center; padding: 7px 12px; font-size: 13px; }
.req > :not(:first-child) { text-align: right; }
.req-head { font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: var(--oz-muted); }
.req.is-short { background: rgba(var(--oz-danger-rgb), 0.06); }
.req.is-short > :nth-child(3) { color: var(--oz-danger); font-weight: 700; }
.req + .req { border-top: 1px solid var(--oz-border-soft); }
.req-name { font-weight: 600; color: var(--oz-ink-soft); }
.req-total { background: var(--oz-surface-2); }


@media (max-width: 640px) {
	.row-3 { grid-template-columns: 1fr; }
	.req { grid-template-columns: minmax(0, 1fr) 90px 90px; }
	.req > :nth-child(4) { display: none; }
}
</style>
