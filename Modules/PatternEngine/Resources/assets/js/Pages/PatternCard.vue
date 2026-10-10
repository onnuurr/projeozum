<script setup>
import { ref, computed, watch, inject } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import {
	OzPageHeader, OzModuleTabs, OzBreadcrumbs, OzCard, OzTabs, OzButton, OzPill, OzField, OzInput, OzSelect, OzEmptyState, OzAlert,
} from '@/Components/Oz'

defineOptions({ layout: AppLayout })

// Kalıp Kartı: üretimin ihtiyaç duyduğu kalıp bilgisi. Ölçü satırları kalıp tipinin şablonundan
// gelir (Kalıp Ayarları), PDF'ten okunan hücreler işaretlidir; elle düzeltilen hücre "elle" olur.
const props = defineProps({
	pattern: { type: Object, required: true },
	measurementTables: { type: Array, default: () => [] },
	types: { type: Array, default: () => [] },
	points: { type: Array, default: () => [] },
	canManage: { type: Boolean, default: false },
})

const showToast = inject('showToast', null)
const clone = (v) => JSON.parse(JSON.stringify(v ?? null))
const BASIS = { body: 'Vücut Ölçüleri', garment: 'Hazır Giysi Ölçüleri' }
const BASIS_HINT = {
	body: 'Bedenin karşılık geldiği vücut ölçüleri (PDF beden tabloları buraya düşer)',
	garment: 'Dikilmiş ürünün kontrol ölçüleri (üretim/kalite kontrol)',
}

/* ── sekmeler ── */
const tabs = computed(() => [
	{ key: 'genel', label: 'Genel', icon: 'file' },
	{ key: 'olculer', label: 'Ölçüler', icon: 'rows', count: filledCells.value || undefined },
	{ key: 'parcalar', label: 'Parçalar', icon: 'scissors', count: prod.value.parts.length || undefined },
	{ key: 'kumas', label: 'Kumaş & Malzeme', icon: 'package' },
])
const initialTab = new URL(window.location.href).searchParams.get('tab')
const activeTab = ref(['genel', 'olculer', 'parcalar', 'kumas'].includes(initialTab) ? initialTab : 'genel')
watch(activeTab, (key) => {
	const url = new URL(window.location.href)
	url.searchParams.set('tab', key)
	window.history.replaceState(window.history.state, '', url)
})

/* ── düzenlenebilir kopyalar (kayıttan sonra sunucu verisiyle yenilenir) ── */
const prod = ref({})
const tables = ref([])
const sizesText = ref('')

function resetProd() {
	const p = props.pattern
	prod.value = {
		pattern_type_id: p.typeId ?? '',
		size_range: p.sizeRange ?? '',
		variants: clone(p.variants) ?? [],
		seam_allowance_cm: p.seamAllowance ?? '',
		hem_allowance_cm: p.hemAllowance ?? '',
		parts: clone(p.parts) ?? [],
		fabric_usage: {
			fabrics: clone(p.fabricUsage?.fabrics) ?? [],
			rows: clone(p.fabricUsage?.rows) ?? [],
		},
		materials: clone(p.materials) ?? [],
	}
	sizesText.value = (p.sizes ?? []).join(', ')
}
watch(() => props.pattern, resetProd, { immediate: true })
watch(() => props.measurementTables, (t) => { tables.value = clone(t) }, { immediate: true })

const sizes = computed(() => sizesText.value.split(/[,;\s]+/).map((s) => s.trim()).filter(Boolean))
// Ölçü ızgarasının kolonları: kalıbın bedenleri + (bedende olmayan ama değeri girilmiş) ek bedenler.
const gridSizes = computed(() => {
	const extra = new Set()
	tables.value.forEach((t) => t.rows.forEach((r) => Object.keys(r.values || {}).forEach((s) => { if (!sizes.value.includes(s)) extra.add(s) })))
	return [...sizes.value, ...extra]
})
const filledCells = computed(() => props.measurementTables.reduce((n, t) => n + t.rows.reduce((m, r) => m + Object.keys(r.values || {}).length, 0), 0))

const typeOptions = computed(() => [{ value: '', label: 'Tip seçin…' }, ...props.types.map((t) => ({ value: t.id, label: t.name }))])
const variantOptions = computed(() => [{ value: '', label: '—' }, ...prod.value.variants.filter((v) => v.code).map((v) => ({ value: v.code, label: v.name ? `${v.code} · ${v.name}` : v.code }))])
const fabricOptions = computed(() => [{ value: '', label: '—' }, ...prod.value.fabric_usage.fabrics.filter((f) => f.code).map((f) => ({ value: f.code, label: f.name ? `${f.code} · ${f.name}` : f.code }))])

/* ── kayıt ── */
const busy = ref(false)
const errorToast = (errs) => showToast?.({ type: 'error', title: 'Kayıt başarısız', message: Object.values(errs)[0] || 'Doğrulama hatası.' })
const num = (v) => (v === '' || v === null || v === undefined ? null : Number(String(v).replace(',', '.')))

function saveProduction() {
	if (busy.value) return
	busy.value = true
	const p = prod.value
	router.put(`/atelier/patterns/${props.pattern.id}/production`, {
		pattern_type_id: p.pattern_type_id || null,
		size_range: p.size_range || null,
		sizes: sizes.value,
		variants: p.variants.filter((v) => v.code?.trim()),
		seam_allowance_cm: num(p.seam_allowance_cm),
		hem_allowance_cm: num(p.hem_allowance_cm),
		parts: p.parts.filter((pt) => pt.part_name?.trim()),
		fabric_usage: {
			fabrics: p.fabric_usage.fabrics.filter((f) => f.code?.trim()),
			rows: p.fabric_usage.rows.map((r) => ({ ...r, width_cm: num(r.width_cm), values: Object.fromEntries(Object.entries(r.values || {}).filter(([, v]) => v !== '' && v !== null).map(([k, v]) => [k, num(v)])) })),
		},
		materials: p.materials.filter((m) => m.name?.trim()).map((m) => ({ ...m, quantity: num(m.quantity) })),
	}, {
		preserveScroll: true,
		onError: errorToast,
		onFinish: () => { busy.value = false },
	})
}

function saveMeasurements() {
	if (busy.value) return
	busy.value = true
	router.put(`/atelier/patterns/${props.pattern.id}/measurements`, {
		tables: tables.value.map((t) => ({
			basis: t.basis,
			group_label: t.group_label,
			rows: t.rows.map((r) => ({
				point_id: r.point_id,
				custom_label: r.custom_label,
				values: Object.fromEntries(Object.entries(r.values || {}).filter(([, v]) => v !== '' && v !== null).map(([k, v]) => [k, num(v)])),
			})),
		})),
	}, {
		preserveScroll: true,
		onError: errorToast,
		onFinish: () => { busy.value = false },
	})
}

/* ── ölçü tablosu satır işlemleri ── */
const addRowChoice = ref({})
function pointOptionsFor(table) {
	const used = new Set(table.rows.map((r) => r.point_id).filter(Boolean))
	return [
		{ value: '', label: 'Satır ekle…' },
		...props.points.filter((p) => p.basis === table.basis && !used.has(p.id)).map((p) => ({ value: p.id, label: p.name })),
		{ value: 'custom', label: 'Özel ölçü (serbest ad)…' },
	]
}
function addRow(table, idx) {
	const choice = addRowChoice.value[idx]
	if (!choice) return
	if (choice === 'custom') {
		table.rows.push({ point_id: null, custom_label: '', label: '', values: {}, sources: {}, isNew: true })
	} else {
		const p = props.points.find((x) => x.id === Number(choice))
		table.rows.push({ point_id: p.id, custom_label: null, label: p.name, hint: p.hint, values: {}, sources: {} })
	}
	addRowChoice.value[idx] = ''
}
function setCell(row, size, value) {
	row.values = { ...row.values, [size]: value }
	if (row.sources?.[size] === 'pdf') row.sources = { ...row.sources, [size]: 'edited' }
}
function addGroupTable(basis) {
	tables.value.push({ basis, group_label: 'Yeni grup', rows: tables.value.find((t) => t.basis === basis)?.rows.map((r) => ({ ...r, values: {}, sources: {} })) ?? [] })
}

/* ── liste satırları ── */
const addVariant = () => prod.value.variants.push({ code: '', name: '' })
const addPart = () => prod.value.parts.push({ part_no: '', part_name: '', quantity: 1, variants: '', fabric_code: '', notes: '', size_range: '' })
const addFabric = () => prod.value.fabric_usage.fabrics.push({ code: '', name: '' })
const addUsageRow = () => prod.value.fabric_usage.rows.push({ variant: '', fabric: '', width_cm: 140, values: {} })
const addMaterial = () => prod.value.materials.push({ name: '', quantity: '', unit: 'adet', variants: '' })

const STATUS = { draft: ['Taslak', 'neutral'], approved: ['Onaylı', 'success'], rejected: ['Reddedildi', 'danger'] }
</script>

<template>
	<Head :title="`Kalıp Kartı · ${pattern.name}`" />

	<OzBreadcrumbs :items="[
		{ label: 'Kalıp Kütüphanesi', to: '/atelier/patterns', icon: 'layers' },
		{ label: pattern.name },
	]" />

	<OzPageHeader :title="pattern.name">
		<template #subtitle>
			<span v-if="pattern.code" class="mono">{{ pattern.code }} · </span>
			<span>{{ pattern.productType }}</span>
			<span v-if="pattern.sizeRange"> · {{ pattern.sizeRange }}</span>
			<span v-if="pattern.vendor"> · {{ pattern.vendor }}</span>
			&nbsp;<OzPill :tone="STATUS[pattern.status]?.[1] ?? 'neutral'">{{ STATUS[pattern.status]?.[0] ?? pattern.status }}</OzPill>
		</template>
		<template #actions>
			<a v-if="pattern.pdfUrl" :href="pattern.pdfUrl" target="_blank" rel="noopener"><OzButton icon="file">PDF</OzButton></a>
			<a v-if="pattern.dxfUrl" :href="pattern.dxfUrl" download><OzButton icon="download">DXF</OzButton></a>
		</template>
	</OzPageHeader>
	<OzModuleTabs />

	<OzAlert v-if="pattern.extractionStatus === 'processing'" tone="info" title="PDF inceleniyor" class="block">
		Çıkarım bitince PDF'ten okunan ölçü, parça ve sarfiyat bilgileri bu karta otomatik dolar.
	</OzAlert>

	<OzTabs v-model="activeTab" :tabs="tabs" aria-label="Kalıp kartı bölümleri" class="block" />

	<!-- ══════════ GENEL ══════════ -->
	<template v-if="activeTab === 'genel'">
		<OzCard title="Kalıp bilgileri" subtitle="Kalıp tipi ölçü tablosunun satırlarını belirler" class="block">
			<template v-if="canManage" #actions>
				<OzButton variant="primary" size="sm" icon="save" :loading="busy" @click="saveProduction">Kaydet</OzButton>
			</template>
			<div class="form-grid">
				<OzField label="Kalıp tipi" hint="Tipler ve ölçü şablonları: Kalıp Ayarları">
					<OzSelect v-model="prod.pattern_type_id" :options="typeOptions" :disabled="!canManage" />
				</OzField>
				<OzField label="Beden aralığı" hint="Görünen etiket, ör. 122-164">
					<OzInput v-model="prod.size_range" :disabled="!canManage" placeholder="122-164" />
				</OzField>
				<OzField label="Bedenler" hint="Ölçü tablosu kolonları · virgülle ayırın">
					<OzInput v-model="sizesText" :disabled="!canManage" placeholder="122, 128, 134, 140" />
				</OzField>
				<OzField label="Dikiş payı">
					<OzInput v-model="prod.seam_allowance_cm" type="number" suffix="cm" :disabled="!canManage" placeholder="1,5" />
				</OzField>
				<OzField label="Etek/paça payı">
					<OzInput v-model="prod.hem_allowance_cm" type="number" suffix="cm" :disabled="!canManage" />
				</OzField>
			</div>
		</OzCard>

		<OzCard title="Model varyantları" subtitle="Tek kalıpta birden fazla model varsa (ör. Burda A = anorak, B = sweatshirt)" class="block">
			<template v-if="canManage" #actions>
				<OzButton size="sm" icon="plus" @click="addVariant">Varyant</OzButton>
				<OzButton variant="primary" size="sm" icon="save" :loading="busy" @click="saveProduction">Kaydet</OzButton>
			</template>
			<div v-if="prod.variants.length" class="list-rows">
				<div v-for="(v, i) in prod.variants" :key="i" class="list-row">
					<OzInput v-model="v.code" class="w-code" placeholder="A" :disabled="!canManage" aria-label="Varyant kodu" />
					<OzInput v-model="v.name" placeholder="Model adı (anorak…)" :disabled="!canManage" aria-label="Varyant adı" />
					<OzButton v-if="canManage" size="sm" icon="trash" :aria-label="`${v.code || i + 1} varyantını kaldır`" @click="prod.variants.splice(i, 1)" />
				</div>
			</div>
			<p v-else class="dim pad">Varyant yok — kalıp tek model.</p>
		</OzCard>
	</template>

	<!-- ══════════ ÖLÇÜLER ══════════ -->
	<template v-else-if="activeTab === 'olculer'">
		<OzAlert v-if="!pattern.typeId" tone="warning" title="Kalıp tipi seçilmemiş" class="block">
			Ölçü satırları kalıp tipinin şablonundan gelir. Genel sekmesinden tip seçin ya da aşağıdan satır ekleyin.
		</OzAlert>
		<OzAlert v-if="!gridSizes.length" tone="warning" title="Beden tanımlı değil" class="block">
			Ölçü girmek için Genel sekmesinde kalıbın bedenlerini yazıp kaydedin.
		</OzAlert>

		<OzCard v-for="(table, ti) in tables" :key="`${table.basis}-${ti}`" :title="BASIS[table.basis] + (table.group_label ? ` · ${table.group_label}` : '')"
			:subtitle="BASIS_HINT[table.basis]" class="block">
			<template v-if="canManage" #actions>
				<OzSelect v-model="addRowChoice[ti]" :options="pointOptionsFor(table)" class="add-sel" aria-label="Satır ekle" @update:model-value="addRow(table, ti)" />
				<OzButton variant="primary" size="sm" icon="save" :loading="busy" @click="saveMeasurements">Kaydet</OzButton>
			</template>
			<div v-if="canManage && table.group_label !== null" class="group-edit">
				<OzField label="Grup adı" inline><OzInput v-model="table.group_label" placeholder="ör. Kız / Erkek" /></OzField>
			</div>
			<div v-if="table.rows.length && gridSizes.length" class="table-scroll">
				<table class="matrix-table">
					<thead>
						<tr>
							<th class="col-label">Ölçü (cm)</th>
							<th v-for="s in gridSizes" :key="s" class="col-size">{{ s }}</th>
							<th v-if="canManage" class="col-act"></th>
						</tr>
					</thead>
					<tbody>
						<tr v-for="(row, ri) in table.rows" :key="ri">
							<td class="col-label">
								<OzInput v-if="!row.point_id && canManage" v-model="row.custom_label" placeholder="Ölçü adı" aria-label="Özel ölçü adı" />
								<span v-else :title="row.hint || ''">{{ row.label }}</span>
							</td>
							<td v-for="s in gridSizes" :key="s" class="col-size" :class="{ 'from-pdf': row.sources?.[s] === 'pdf' }">
								<input class="cell-input" type="number" step="0.1" :value="row.values?.[s] ?? ''" :disabled="!canManage"
									:aria-label="`${row.label || row.custom_label} · ${s}`"
									@input="setCell(row, s, $event.target.value)" />
							</td>
							<td v-if="canManage" class="col-act">
								<OzButton size="sm" icon="trash" :aria-label="`${row.label || row.custom_label} satırını kaldır`" @click="table.rows.splice(ri, 1)" />
							</td>
						</tr>
					</tbody>
				</table>
			</div>
			<OzEmptyState v-else-if="!table.rows.length" icon="rows" title="Ölçü satırı yok" description="Kalıp tipi seçin ya da sağ üstten satır ekleyin." />
			<p class="legend dim"><span class="pdf-dot"></span> PDF'ten okundu · elle değiştirilen hücreler yeniden çıkarımda korunur</p>
		</OzCard>

		<div v-if="canManage" class="group-actions">
			<OzButton size="sm" icon="plus" @click="addGroupTable('body')">Vücut tablosu grubu ekle</OzButton>
			<OzButton size="sm" icon="plus" @click="addGroupTable('garment')">Hazır giysi tablosu grubu ekle</OzButton>
		</div>
	</template>

	<!-- ══════════ PARÇALAR ══════════ -->
	<template v-else-if="activeTab === 'parcalar'">
		<OzCard title="Parça listesi" subtitle="Kesim adetleri — varyant ve kumaş kodu sarfiyatla eşleşir" class="block">
			<template v-if="canManage" #actions>
				<OzButton size="sm" icon="plus" @click="addPart">Parça</OzButton>
				<OzButton variant="primary" size="sm" icon="save" :loading="busy" @click="saveProduction">Kaydet</OzButton>
			</template>
			<div v-if="prod.parts.length" class="table-scroll">
				<table class="matrix-table">
					<thead>
						<tr><th class="w-no">No</th><th>Parça</th><th class="w-qty">Adet</th><th class="w-sel">Varyant</th><th class="w-sel">Kumaş</th><th>Not</th><th v-if="canManage" class="col-act"></th></tr>
					</thead>
					<tbody>
						<tr v-for="(pt, i) in prod.parts" :key="i">
							<td><OzInput v-model="pt.part_no" :disabled="!canManage" aria-label="Parça no" /></td>
							<td><OzInput v-model="pt.part_name" :disabled="!canManage" placeholder="Kol, ön, kapüşon…" aria-label="Parça adı" /></td>
							<td><OzInput v-model="pt.quantity" type="number" :disabled="!canManage" aria-label="Kesim adedi" /></td>
							<td><OzInput v-model="pt.variants" :disabled="!canManage" placeholder="A,B" aria-label="Varyantlar" /></td>
							<td><OzSelect v-model="pt.fabric_code" :options="fabricOptions" :disabled="!canManage" aria-label="Kumaş" /></td>
							<td><OzInput v-model="pt.notes" :disabled="!canManage" aria-label="Not" /></td>
							<td v-if="canManage" class="col-act"><OzButton size="sm" icon="trash" :aria-label="`${pt.part_name || i + 1} parçasını kaldır`" @click="prod.parts.splice(i, 1)" /></td>
						</tr>
					</tbody>
				</table>
			</div>
			<OzEmptyState v-else icon="scissors" title="Parça yok" description="Kol, ön, arka, kapüşon… kesim adetleriyle ekleyin." />
		</OzCard>
	</template>

	<!-- ══════════ KUMAŞ & MALZEME ══════════ -->
	<template v-else-if="activeTab === 'kumas'">
		<OzCard title="Kumaşlar" subtitle="Kalıpta geçen kumaş kodları (ör. I = ana kumaş, II = astar/ikinci renk)" class="block">
			<template v-if="canManage" #actions>
				<OzButton size="sm" icon="plus" @click="addFabric">Kumaş</OzButton>
				<OzButton variant="primary" size="sm" icon="save" :loading="busy" @click="saveProduction">Kaydet</OzButton>
			</template>
			<div v-if="prod.fabric_usage.fabrics.length" class="list-rows">
				<div v-for="(f, i) in prod.fabric_usage.fabrics" :key="i" class="list-row">
					<OzInput v-model="f.code" class="w-code" placeholder="I" :disabled="!canManage" aria-label="Kumaş kodu" />
					<OzInput v-model="f.name" placeholder="Kumaş adı / türü" :disabled="!canManage" aria-label="Kumaş adı" />
					<OzButton v-if="canManage" size="sm" icon="trash" :aria-label="`${f.code || i + 1} kumaşını kaldır`" @click="prod.fabric_usage.fabrics.splice(i, 1)" />
				</div>
			</div>
			<p v-else class="dim pad">Kumaş tanımlı değil.</p>
		</OzCard>

		<OzCard title="Kumaş sarfiyatı (metre)" subtitle="Varyant × kumaş × kumaş eni için beden başına metre" class="block">
			<template v-if="canManage" #actions>
				<OzButton size="sm" icon="plus" @click="addUsageRow">Satır</OzButton>
				<OzButton variant="primary" size="sm" icon="save" :loading="busy" @click="saveProduction">Kaydet</OzButton>
			</template>
			<div v-if="prod.fabric_usage.rows.length && sizes.length" class="table-scroll">
				<table class="matrix-table">
					<thead>
						<tr>
							<th class="w-sel">Varyant</th><th class="w-sel">Kumaş</th><th class="w-qty">En (cm)</th>
							<th v-for="s in sizes" :key="s" class="col-size">{{ s }}</th>
							<th v-if="canManage" class="col-act"></th>
						</tr>
					</thead>
					<tbody>
						<tr v-for="(r, i) in prod.fabric_usage.rows" :key="i">
							<td><OzSelect v-model="r.variant" :options="variantOptions" :disabled="!canManage" aria-label="Varyant" /></td>
							<td><OzSelect v-model="r.fabric" :options="fabricOptions" :disabled="!canManage" aria-label="Kumaş" /></td>
							<td><OzInput v-model="r.width_cm" type="number" :disabled="!canManage" aria-label="Kumaş eni" /></td>
							<td v-for="s in sizes" :key="s" class="col-size">
								<input class="cell-input" type="number" step="0.05" :value="r.values?.[s] ?? ''" :disabled="!canManage"
									:aria-label="`Sarfiyat ${s}`" @input="r.values = { ...r.values, [s]: $event.target.value }" />
							</td>
							<td v-if="canManage" class="col-act"><OzButton size="sm" icon="trash" aria-label="Sarfiyat satırını kaldır" @click="prod.fabric_usage.rows.splice(i, 1)" /></td>
						</tr>
					</tbody>
				</table>
			</div>
			<OzEmptyState v-else-if="!sizes.length" icon="rows" title="Beden tanımlı değil" description="Genel sekmesinde bedenleri girin." />
			<OzEmptyState v-else icon="package" title="Sarfiyat girilmedi" description="Her varyant/kumaş/en için bir satır ekleyin." />
		</OzCard>

		<OzCard title="Malzeme" subtitle="Fermuar, kuşgözü, ribana, lastik…" class="block">
			<template v-if="canManage" #actions>
				<OzButton size="sm" icon="plus" @click="addMaterial">Malzeme</OzButton>
				<OzButton variant="primary" size="sm" icon="save" :loading="busy" @click="saveProduction">Kaydet</OzButton>
			</template>
			<div v-if="prod.materials.length" class="table-scroll">
				<table class="matrix-table">
					<thead><tr><th>Malzeme</th><th class="w-qty">Miktar</th><th class="w-qty">Birim</th><th class="w-sel">Varyant</th><th v-if="canManage" class="col-act"></th></tr></thead>
					<tbody>
						<tr v-for="(m, i) in prod.materials" :key="i">
							<td><OzInput v-model="m.name" :disabled="!canManage" aria-label="Malzeme adı" /></td>
							<td><OzInput v-model="m.quantity" type="number" :disabled="!canManage" aria-label="Miktar" /></td>
							<td><OzInput v-model="m.unit" :disabled="!canManage" aria-label="Birim" /></td>
							<td><OzInput v-model="m.variants" :disabled="!canManage" placeholder="A,B" aria-label="Varyantlar" /></td>
							<td v-if="canManage" class="col-act"><OzButton size="sm" icon="trash" :aria-label="`${m.name || i + 1} malzemesini kaldır`" @click="prod.materials.splice(i, 1)" /></td>
						</tr>
					</tbody>
				</table>
			</div>
			<OzEmptyState v-else icon="package" title="Malzeme girilmedi" />
		</OzCard>
	</template>
</template>

<style scoped>
.block { margin-bottom: 14px; }
.mono { font-family: var(--oz-font-mono, ui-monospace, monospace); }
.dim { color: var(--oz-muted); font-size: 12.5px; }
.pad { padding: 14px 18px; margin: 0; }

.form-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 12px 18px; padding: 16px 18px; }
.list-rows { display: flex; flex-direction: column; gap: 8px; padding: 14px 18px; }
.list-row { display: flex; gap: 8px; align-items: center; }
.w-code { max-width: 90px; }
.add-sel { min-width: 190px; }
.group-edit { padding: 12px 18px 0; max-width: 360px; }
.group-actions { display: flex; gap: 8px; flex-wrap: wrap; }

.table-scroll { overflow-x: auto; }
.matrix-table { width: 100%; border-collapse: separate; border-spacing: 0; }
.matrix-table thead tr { background: var(--oz-surface-2); }
.matrix-table th {
	text-align: left; padding: 10px 12px; font-size: 10.5px; font-weight: 700; color: var(--oz-muted);
	border-bottom: 1px solid var(--oz-border); text-transform: uppercase; letter-spacing: .04em; white-space: nowrap;
}
.matrix-table td { padding: 6px 8px; font-size: 13px; color: var(--oz-ink); border-bottom: 1px solid var(--oz-border-soft); vertical-align: middle; }
.matrix-table tr:last-child td { border-bottom: none; }
.col-label { min-width: 180px; font-weight: 600; }
.col-size { text-align: center; min-width: 70px; }
.col-act { width: 44px; text-align: right; }
.w-no { width: 70px; }
.w-qty { width: 90px; }
.w-sel { width: 150px; }

.cell-input {
	width: 64px; padding: 5px 6px; text-align: center; font-size: 13px; color: var(--oz-ink);
	background: var(--oz-surface); border: 1px solid var(--oz-border); border-radius: 7px;
}
.cell-input:focus { outline: none; border-color: var(--oz-accent); }
.cell-input:disabled { background: transparent; border-color: transparent; }
.from-pdf .cell-input { background: var(--oz-accent-soft); border-color: transparent; }

.legend { display: flex; align-items: center; gap: 6px; padding: 10px 18px 14px; margin: 0; font-size: 11.5px; }
.pdf-dot { display: inline-block; width: 10px; height: 10px; border-radius: 3px; background: var(--oz-accent-soft); }
</style>
