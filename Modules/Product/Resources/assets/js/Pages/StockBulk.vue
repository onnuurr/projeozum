<script setup>
import { ref, reactive, computed, watch } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { useDebounceFn } from '@vueuse/core'
import AppLayout from '@/Layouts/AppLayout.vue'
import { OzPageHeader, OzModuleTabs, OzCard, OzButton, OzField, OzSelect, OzInput, OzTextarea, OzRadioGroup, OzEmptyState, OzSpinner, OzIcon, useOzConfirm } from '@/Components/Oz'

// Toplu stok işlemi: ürün(ler) seçilir, bütün varyantları gelir, tek kayıtla seçili depoya
// giriş / çıkış / sayım yapılır. Sunucu tek transaction'da yazar (StockService::bulk) ve her
// değişikliği işleme bağlı bir stok hareketi olarak loglar (Stok › Hareket Geçmişi).
defineOptions({ layout: AppLayout })

const props = defineProps({
	warehouses: { type: Array, default: () => [] },
	warehouseId: { type: Number, default: null },
	preset: { type: Array, default: () => [] },              // stok listesinden seçilip gelen ürünler
	presetVariantIds: { type: Array, default: () => [] },
})

const confirm = useOzConfirm()

const MODES = [
	{ value: 'in', label: 'Giriş', description: 'Girilen miktar stoğa eklenir.' },
	{ value: 'out', label: 'Çıkış', description: 'Girilen miktar stoktan düşülür.' },
	{ value: 'count', label: 'Sayım', description: 'Stok girilen miktara eşitlenir; fark kaydedilir.' },
]
const MODE_LABELS = { in: 'Giriş', out: 'Çıkış', count: 'Sayım' }

const form = reactive({ warehouse_id: props.warehouseId, mode: 'in', note: '' })
const warehouseOptions = computed(() => props.warehouses.map(w => ({ value: w.id, label: `${w.name} (${w.code})` })))

// Ürünler ve varyant başına girilen miktar ('' = bu varyanta dokunma).
const products = ref([])
const qty = reactive({})

function addProducts(rows, onlyVariantIds = null) {
	for (const p of rows) {
		const existing = products.value.find(x => x.id === p.id)
		if (existing) existing.variants = p.variants
		else products.value.push(p)
		for (const v of p.variants) {
			if (!(v.id in qty)) qty[v.id] = form.mode === 'count' && (!onlyVariantIds || onlyVariantIds.includes(v.id)) ? v.quantity : ''
		}
	}
}
addProducts(props.preset, props.presetVariantIds.length ? props.presetVariantIds : null)

function removeProduct(p) {
	products.value = products.value.filter(x => x.id !== p.id)
	p.variants.forEach(v => { delete qty[v.id] })
}

// Sayımda kutular mevcut stokla dolar (yalnız değişeni düzelt); giriş/çıkışta boş başlar.
watch(() => form.mode, (mode) => {
	for (const p of products.value) for (const v of p.variants) qty[v.id] = mode === 'count' ? v.quantity : ''
})

// Depo değişince miktarlar o depodan yeniden okunur.
const reloading = ref(false)
watch(() => form.warehouse_id, async (id) => {
	if (!id || !products.value.length) return
	reloading.value = true
	try {
		const { data } = await window.axios.get('/products/stocks/bulk/products', { params: { warehouse_id: id, product_ids: products.value.map(p => p.id) } })
		addProducts(data?.data || [])
		if (form.mode === 'count') for (const p of products.value) for (const v of p.variants) qty[v.id] = v.quantity
	} finally {
		reloading.value = false
	}
})

/* ── Ürün arama ── */
const search = ref('')
const results = ref([])
const searching = ref(false)
const runSearch = useDebounceFn(async () => {
	const q = search.value.trim()
	if (q.length < 2 || !form.warehouse_id) { results.value = []; return }
	searching.value = true
	try {
		const { data } = await window.axios.get('/products/stocks/bulk/products', { params: { warehouse_id: form.warehouse_id, q } })
		results.value = data?.data || []
	} finally {
		searching.value = false
	}
}, 300)
watch(search, runSearch)

function pick(p) {
	addProducts([p])
	search.value = ''
	results.value = []
}
const isAdded = (p) => products.value.some(x => x.id === p.id)

/* ── Hızlı doldurma ── */
const fillValue = ref('')
function fillAll(p = null) {
	const val = fillValue.value === '' ? '' : Math.max(0, parseInt(fillValue.value, 10) || 0)
	for (const prod of p ? [p] : products.value) for (const v of prod.variants) qty[v.id] = val
}
function clearAll() {
	for (const p of products.value) for (const v of p.variants) qty[v.id] = form.mode === 'count' ? v.quantity : ''
}

/* ── Önizleme ── */
function newQuantity(v) {
	const raw = qty[v.id]
	if (raw === '' || raw === null || raw === undefined) return null
	const n = Math.max(0, parseInt(raw, 10) || 0)
	return form.mode === 'in' ? v.quantity + n : form.mode === 'out' ? v.quantity - n : n
}
const lines = computed(() => {
	const out = []
	for (const p of products.value) {
		for (const v of p.variants) {
			const next = newQuantity(v)
			if (next === null || next === v.quantity) continue
			const n = Math.max(0, parseInt(qty[v.id], 10) || 0)
			out.push({ variant: v, product: p, quantity: n, next, delta: next - v.quantity })
		}
	}
	return out
})
// Çıkış/sayım stoğu eksiye ya da rezerve miktarın altına indiremez (sunucu da aynı kuralı uygular).
function isBad(v) {
	const n = newQuantity(v)
	return n !== null && n < v.quantity && (n < 0 || n < v.reserved)
}
const invalid = computed(() => lines.value.filter(l => isBad(l.variant)))
const totals = computed(() => ({
	in: lines.value.reduce((s, l) => s + Math.max(0, l.delta), 0),
	out: lines.value.reduce((s, l) => s + Math.max(0, -l.delta), 0),
}))

/* ── Kaydet ── */
const busy = ref(false)
const errors = ref({})
async function submit() {
	if (busy.value || !lines.value.length || invalid.value.length || !form.warehouse_id) return
	const wh = props.warehouses.find(w => w.id === form.warehouse_id)
	const ok = await confirm({
		title: `Toplu ${MODE_LABELS[form.mode].toLowerCase()} kaydedilsin mi?`,
		message: `${wh?.name ?? 'Depo'}: ${lines.value.length} varyant değişecek (+${totals.value.in} / −${totals.value.out} adet). İşlem hareket geçmişine tek kayıt olarak yazılır.`,
		confirmText: 'Kaydet',
		tone: form.mode === 'out' ? 'warning' : 'accent',
	})
	if (!ok) return
	busy.value = true
	errors.value = {}
	router.post('/products/stocks/bulk', {
		warehouse_id: form.warehouse_id,
		mode: form.mode,
		note: form.note || null,
		lines: lines.value.map(l => ({ variant_id: l.variant.id, quantity: l.quantity })),
	}, {
		onError: (errs) => { errors.value = errs },
		onFinish: () => { busy.value = false },
	})
}
const firstError = computed(() => Object.values(errors.value)[0] ?? null)
</script>

<template>
	<Head title="Toplu Stok İşlemi" />

	<OzPageHeader title="Toplu Stok İşlemi" subtitle="Bir veya daha fazla ürünün bütün varyantlarına tek seferde giriş, çıkış ya da sayım">
		<template #actions>
			<Link href="/products/stocks/history" class="oz-link-btn"><OzIcon name="clock" :size="14" /> Hareket Geçmişi</Link>
			<Link href="/products/stocks" class="oz-link-btn"><OzIcon name="chevron-left" :size="14" /> Stok Listesi</Link>
		</template>
	</OzPageHeader>
	<OzModuleTabs />

	<div class="bulk-grid">
		<OzCard title="İşlem">
			<div class="form">
				<OzField label="Depo" required :error="errors.warehouse_id">
					<OzSelect v-model="form.warehouse_id" :options="warehouseOptions" placeholder="Depo seçin" />
				</OzField>
				<OzField label="İşlem tipi" required>
					<OzRadioGroup v-model="form.mode" :options="MODES" aria-label="İşlem tipi" />
				</OzField>
				<OzField label="Not" hint="Hareket geçmişinde işlemle birlikte görünür (ör. irsaliye no, sayım tarihi).">
					<OzTextarea v-model="form.note" :rows="2" placeholder="Açıklama (opsiyonel)…" />
				</OzField>
			</div>
		</OzCard>

		<OzCard title="Ürünler">
			<template #actions>
				<OzSpinner v-if="reloading" :size="14" />
			</template>
			<div class="pad">
			<div class="search-box">
				<OzInput v-model="search" icon="search" placeholder="Ürün adı veya SKU ile ara (en az 2 harf)…" :disabled="!form.warehouse_id" />
				<div v-if="search.trim().length >= 2" class="search-results">
					<div v-if="searching" class="search-empty"><OzSpinner :size="14" /> Aranıyor…</div>
					<div v-else-if="!results.length" class="search-empty">Eşleşen ürün yok.</div>
					<button v-for="p in results" v-else :key="p.id" type="button" class="search-row" :disabled="isAdded(p)" @click="pick(p)">
						<span class="sr-name">{{ p.name }}</span>
						<span class="sr-meta">{{ p.sku }} · {{ p.variants.length }} varyant</span>
						<span class="sr-add">{{ isAdded(p) ? 'Eklendi' : 'Ekle' }}</span>
					</button>
				</div>
			</div>

			<div v-if="products.length" class="fill-row">
				<span class="fill-label">{{ form.mode === 'count' ? 'Bütün varyantları şu miktara eşitle:' : 'Bütün varyantlara uygula:' }}</span>
				<OzInput v-model="fillValue" type="number" min="0" class="fill-input" />
				<OzButton size="sm" @click="fillAll()">Uygula</OzButton>
				<OzButton size="sm" @click="clearAll">Sıfırla</OzButton>
			</div>

			<OzEmptyState v-if="!products.length" icon="package" title="Henüz ürün eklenmedi" description="Yukarıdan ürün arayıp ekleyin; ürünün bütün varyantları listeye gelir." />

			<div v-for="p in products" :key="p.id" class="prod">
				<div class="prod-head">
					<div class="prod-title">
						<b>{{ p.name }}</b>
						<span class="mono">{{ p.sku }}</span>
					</div>
					<button type="button" class="link" @click="fillAll(p)" :title="`Bu ürünün varyantlarına ${fillValue || '…'} uygula`">Bu ürüne uygula</button>
					<button type="button" class="link danger" @click="removeProduct(p)">Kaldır</button>
				</div>
				<table class="var-table">
					<thead>
						<tr>
							<th>Varyant</th>
							<th class="num">Mevcut</th>
							<th class="num">{{ form.mode === 'count' ? 'Sayılan' : form.mode === 'in' ? 'Giriş' : 'Çıkış' }}</th>
							<th class="num">Yeni stok</th>
						</tr>
					</thead>
					<tbody>
						<tr v-for="v in p.variants" :key="v.id" :class="{ changed: newQuantity(v) !== null && newQuantity(v) !== v.quantity, bad: isBad(v) }">
							<td>
								<span v-if="v.size" class="chip">{{ v.size }}</span>
								<span v-if="v.color_name" class="chip"><span class="dot" :style="{ background: v.color_hex || 'transparent' }"></span>{{ v.color_name }}</span>
								<span class="mono">{{ v.sku }}</span>
							</td>
							<td class="num">{{ v.quantity }}<small v-if="v.reserved" class="dim"> ({{ v.reserved }} rezerve)</small></td>
							<td class="num"><input v-model="qty[v.id]" type="number" min="0" class="qty-input" :placeholder="form.mode === 'count' ? 'sayılmadı' : '0'" /></td>
							<td class="num">
								<template v-if="newQuantity(v) !== null && newQuantity(v) !== v.quantity">
									<b>{{ newQuantity(v) }}</b>
									<small :class="newQuantity(v) > v.quantity ? 'up' : 'down'"> ({{ newQuantity(v) > v.quantity ? '+' : '' }}{{ newQuantity(v) - v.quantity }})</small>
								</template>
								<span v-else class="dim">—</span>
							</td>
						</tr>
					</tbody>
				</table>
			</div>
			</div>
		</OzCard>
	</div>

	<div v-if="products.length" class="summary-bar">
		<div class="summary-text">
			<b>{{ lines.length }}</b> varyant değişecek
			<span class="up">+{{ totals.in }}</span> / <span class="down">−{{ totals.out }}</span> adet
			<span v-if="invalid.length" class="err"> · {{ invalid.length }} varyantta stok yetersiz</span>
			<span v-else-if="firstError" class="err"> · {{ firstError }}</span>
		</div>
		<OzButton variant="primary" icon="save" :loading="busy" :disabled="!lines.length || invalid.length > 0 || !form.warehouse_id" @click="submit">Kaydet</OzButton>
	</div>
</template>

<style scoped>
.bulk-grid { display: grid; grid-template-columns: 320px minmax(0, 1fr); gap: 16px; align-items: start; }
@media (max-width: 900px) { .bulk-grid { grid-template-columns: 1fr; } }
.form { display: flex; flex-direction: column; gap: 14px; padding: 4px 18px 18px; }
.pad { padding: 4px 18px 18px; }
.pad > .prod:last-child { margin-bottom: 0; }
.oz-link-btn { display: inline-flex; align-items: center; gap: 5px; font-size: 13px; font-weight: 600; color: var(--oz-ink-soft); text-decoration: none; padding: 6px 10px; border-radius: 8px; }
.oz-link-btn:hover { background: var(--oz-surface-2); }

.search-box { position: relative; margin-bottom: 12px; }
.search-results { position: absolute; left: 0; right: 0; top: calc(100% + 4px); z-index: 20; background: var(--oz-surface); border: 1px solid var(--oz-border); border-radius: 10px; box-shadow: 0 10px 28px rgba(0,0,0,.12); max-height: 320px; overflow: auto; }
.search-empty { padding: 10px 12px; font-size: 12.5px; color: var(--oz-muted); display: flex; gap: 6px; align-items: center; }
.search-row { display: grid; grid-template-columns: 1fr auto; grid-template-rows: auto auto; column-gap: 10px; width: 100%; text-align: left; border: none; border-bottom: 1px solid var(--oz-border); background: none; padding: 8px 12px; cursor: pointer; font-family: inherit; }
.search-row:hover:not(:disabled) { background: var(--oz-surface-2); }
.search-row:disabled { cursor: default; opacity: .6; }
.sr-name { font-size: 13px; font-weight: 600; color: var(--oz-ink-soft); }
.sr-meta { grid-row: 2; font-size: 11px; color: var(--oz-muted); }
.sr-add { grid-row: 1 / span 2; grid-column: 2; align-self: center; font-size: 12px; font-weight: 700; color: var(--oz-accent); }

.fill-row { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-bottom: 12px; font-size: 12.5px; color: var(--oz-muted); }
.fill-input { width: 90px; }

.prod { border: 1px solid var(--oz-border); border-radius: 10px; margin-bottom: 12px; overflow: hidden; }
.prod-head { display: flex; align-items: center; gap: 12px; padding: 8px 12px; background: var(--oz-surface-2); }
.prod-title { flex: 1; min-width: 0; display: flex; align-items: baseline; gap: 8px; }
.prod-title b { font-size: 13px; color: var(--oz-ink-soft); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.link { border: none; background: none; font-family: inherit; font-size: 12px; font-weight: 600; color: var(--oz-accent); cursor: pointer; padding: 0; }
.link.danger { color: var(--oz-danger, #c0392b); }

.var-table { width: 100%; border-collapse: collapse; font-size: 12.5px; }
.var-table th { text-align: left; font-size: 11px; font-weight: 700; color: var(--oz-muted); padding: 6px 12px; border-bottom: 1px solid var(--oz-border); }
.var-table td { padding: 6px 12px; border-bottom: 1px solid var(--oz-border); vertical-align: middle; }
.var-table tr:last-child td { border-bottom: none; }
.var-table .num { text-align: right; white-space: nowrap; }
.var-table tr.changed td { background: color-mix(in srgb, var(--oz-accent) 6%, transparent); }
.var-table tr.bad td { background: color-mix(in srgb, var(--oz-danger, #c0392b) 10%, transparent); }
.qty-input { width: 90px; text-align: right; padding: 5px 8px; border: 1px solid var(--oz-border-strong, var(--oz-border)); border-radius: 7px; background: var(--oz-surface); color: var(--oz-ink-soft); font-family: inherit; font-size: 12.5px; }
.chip { display: inline-flex; align-items: center; gap: 4px; padding: 2px 8px; margin-right: 4px; background: var(--oz-surface-2); color: var(--oz-muted); border-radius: 6px; font-size: 11px; font-weight: 600; }
.chip .dot { width: 8px; height: 8px; border-radius: 50%; border: 1px solid var(--oz-border-strong, var(--oz-border)); }
.mono { font-size: 11px; color: var(--oz-muted); font-family: ui-monospace, monospace; }
.dim { color: var(--oz-muted); font-weight: 500; }
.up { color: var(--oz-success, #2e7d32); }
.down { color: var(--oz-danger, #c0392b); }
.err { color: var(--oz-danger, #c0392b); font-weight: 600; }

.summary-bar { position: sticky; bottom: 12px; z-index: 10; margin-top: 16px; display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 10px 16px; border-radius: 12px; background: var(--oz-surface); border: 1px solid var(--oz-border); box-shadow: 0 8px 24px rgba(0,0,0,.10); font-size: 13px; }
</style>
