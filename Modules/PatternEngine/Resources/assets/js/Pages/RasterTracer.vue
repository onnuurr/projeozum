<script setup>
import { ref, reactive, computed, inject, onMounted, onUnmounted } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import {
	OzPageHeader, OzModuleTabs, OzBreadcrumbs, OzCard, OzButton, OzPill, OzTabs, OzModal, OzSelect,
	OzField, OzInput, OzSpinner, OzEmptyState,
} from '@/Components/Oz'

defineOptions({ layout: AppLayout })

const props = defineProps({ pattern: { type: Object, required: true }, imageBase: { type: String, required: true } })

const showToast = inject('showToast', null)

const breadcrumbs = computed(() => [
	{ label: 'Atölye', to: '/atelier' },
	{ label: 'Kalıp Kütüphanesi', to: '/atelier/patterns' },
	{ label: props.pattern.name },
])

/* ── sayfa görüntüsü ── */
const page = ref(0)
const dpi = ref('200')
const dpiOptions = [{ value: '150', label: '150 DPI' }, { value: '200', label: '200 DPI' }, { value: '300', label: '300 DPI' }]
const pageCount = ref(0)
const imgUrl = ref('')
const imgW = ref(0)
const imgH = ref(0)
const loading = ref(false)
const loadError = ref('')

async function loadImage() {
	if (imgUrl.value) URL.revokeObjectURL(imgUrl.value)
	imgUrl.value = ''
	loadError.value = ''
	loading.value = true
	try {
		const res = await fetch(`${props.imageBase}?page=${page.value}&dpi=${dpi.value}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
		if (!res.ok) {
			// son sayfanın ötesine geçildi → bir önceki sayfaya dön
			if (page.value > 0) {
				pageCount.value = page.value
				page.value -= 1
				return loadImage()
			}
			loadError.value = 'Sayfa görüntüsü alınamadı.'
			return
		}
		pageCount.value = parseInt(res.headers.get('X-Page-Count') || '1', 10)
		imgW.value = parseInt(res.headers.get('X-Width') || '0', 10)
		imgH.value = parseInt(res.headers.get('X-Height') || '0', 10)
		imgUrl.value = URL.createObjectURL(await res.blob())
	} catch {
		loadError.value = 'Sayfa görüntüsü alınamadı.'
	} finally {
		loading.value = false
	}
}
function changePage(p) {
	if (p < 0 || (pageCount.value && p >= pageCount.value)) return
	page.value = p
	// sayfa başına kalibrasyon ve aktif izleme sıfırlanır
	pxPerMm.value = null
	calib.a = null
	calib.b = null
	current.points = []
	loadImage()
}
function onDpiChange() { changePage(page.value) }
onMounted(loadImage)
onUnmounted(() => { if (imgUrl.value) URL.revokeObjectURL(imgUrl.value) })

/* ── araçlar ── */
const tool = ref('calibrate')
const toolTabs = [
	{ key: 'calibrate', label: 'Kalibrasyon', icon: 'map' },
	{ key: 'trace', label: 'İzle', icon: 'edit' },
]
const pxPerMm = ref(null)
const calib = reactive({ a: null, b: null })
const current = reactive({ points: [], name: '', quantity: 1, size: '' })
const pieces = ref([])

// Kalibrasyon: iki nokta → bilinen gerçek uzunluk (mm) sorulur
const calibOpen = ref(false)
const calibMm = ref(100)
const calibPx = computed(() => (calib.a && calib.b ? Math.hypot(calib.b.x - calib.a.x, calib.b.y - calib.a.y) : 0))
function applyCalibration() {
	const mm = Number(calibMm.value)
	if (mm > 0 && calibPx.value > 0) pxPerMm.value = calibPx.value / mm
	calibOpen.value = false
}

function onSvgClick(e) {
	const rect = e.currentTarget.getBoundingClientRect()
	const x = e.clientX - rect.left
	const y = e.clientY - rect.top
	if (tool.value === 'calibrate') {
		if (!calib.a || calib.b) {
			calib.a = { x, y }
			calib.b = null
		} else {
			calib.b = { x, y }
			calibOpen.value = true
		}
	} else {
		current.points.push({ x, y })
	}
}

function closeCurrentPiece() {
	if (current.points.length < 3) return
	pieces.value.push({
		name: current.name || `Parça ${pieces.value.length + 1}`,
		quantity: current.quantity || 1,
		size: current.size || '',
		points: current.points.slice(),
	})
	current.points = []
	current.name = ''
	current.size = ''
}
const ptsStr = (pts) => pts.map((p) => `${p.x},${p.y}`).join(' ')

/* ── kaydet ── */
const meta = reactive({ name: props.pattern.name, product_type: props.pattern.productType || '', size_range: props.pattern.sizeRange || '' })
const saving = ref(false)
const canSave = computed(() => !!pxPerMm.value && pieces.value.length > 0 && !!meta.name.trim())
function save() {
	if (!canSave.value || saving.value) return
	saving.value = true
	router.post(`/atelier/patterns/${props.pattern.id}/traced`, {
		name: meta.name,
		product_type: meta.product_type,
		size_range: meta.size_range,
		calibration: { px_per_mm: pxPerMm.value, image_height_px: imgH.value },
		pieces: pieces.value.map((pc) => ({
			name: pc.name,
			quantity: pc.quantity,
			size: pc.size,
			polylines: [{ role: 'cut', points: pc.points.map((p) => [p.x, p.y]) }],
		})),
	}, {
		preserveScroll: true,
		onError: (errs) => showToast?.({ type: 'error', title: 'Kaydedilemedi', message: Object.values(errs)[0] || 'Sunucu hatası.' }),
		onFinish: () => { saving.value = false },
	})
}
</script>

<template>
	<Head :title="`Sayısallaştır — ${pattern.name}`" />

	<OzBreadcrumbs :items="breadcrumbs" class="crumbs" />
	<OzPageHeader :title="`Sayısallaştır: ${pattern.name}`">
		<template #subtitle>Sayfa <b>{{ page + 1 }}</b> / {{ pageCount || '?' }} · taranmış kalıbı elle izleyip DXF üret</template>
		<template #actions>
			<OzButton icon="chevron-left" :disabled="page === 0 || loading" @click="changePage(page - 1)">Önceki</OzButton>
			<OzButton icon="chevron-right" :disabled="!!pageCount && page >= pageCount - 1 || loading" @click="changePage(page + 1)">Sonraki</OzButton>
			<OzSelect v-model="dpi" :options="dpiOptions" class="dpi" aria-label="Çözünürlük" @update:model-value="onDpiChange" />
		</template>
	</OzPageHeader>
	<OzModuleTabs />

	<div class="layout">
		<OzCard class="canvas-card">
			<template #title>
				<OzTabs v-model="tool" :tabs="toolTabs" variant="segment" aria-label="Araç" />
			</template>
			<template #actions>
				<OzPill v-if="pxPerMm" tone="success">Ölçek {{ pxPerMm.toFixed(3) }} px/mm</OzPill>
				<OzPill v-else tone="warning">Ölçek ayarlanmadı</OzPill>
			</template>
			<p class="tool-hint">
				<template v-if="tool === 'calibrate'">Görsel üzerinde uzunluğunu bildiğiniz bir mesafenin iki ucuna tıklayın (ör. test karesi kenarı).</template>
				<template v-else>Parçanın kesim çizgisi boyunca köşelere tıklayın; en az 3 nokta sonra "Parçayı Bitir".</template>
			</p>
			<div class="canvas">
				<svg v-if="imgUrl" :width="imgW" :height="imgH" :class="`tool-${tool}`" @click="onSvgClick">
					<image :href="imgUrl" :width="imgW" :height="imgH" />
					<polygon v-for="(pc, i) in pieces" :key="'pc' + i" :points="ptsStr(pc.points)" class="piece" />
					<line v-if="calib.a && calib.b" :x1="calib.a.x" :y1="calib.a.y" :x2="calib.b.x" :y2="calib.b.y" class="calib" />
					<circle v-if="calib.a" :cx="calib.a.x" :cy="calib.a.y" r="4" class="calib-pt" />
					<circle v-if="calib.b" :cx="calib.b.x" :cy="calib.b.y" r="4" class="calib-pt" />
					<polyline v-if="current.points.length" :points="ptsStr(current.points)" class="trace" />
					<circle v-for="(p, i) in current.points" :key="i" :cx="p.x" :cy="p.y" r="3" class="trace-pt" />
				</svg>
				<div v-else-if="loading" class="canvas-state"><OzSpinner :size="20" /> Sayfa yükleniyor…</div>
				<OzEmptyState v-else icon="alert" :title="loadError || 'Görüntü yok'" compact>
					<template #actions><OzButton size="sm" icon="refresh" @click="loadImage">Tekrar Dene</OzButton></template>
				</OzEmptyState>
			</div>
		</OzCard>

		<div class="side">
			<OzCard title="Aktif Parça" :meta="`${current.points.length} nokta`">
				<div class="panel">
					<OzField label="Parça adı">
						<OzInput v-model="current.name" :placeholder="`Parça ${pieces.length + 1}`" />
					</OzField>
					<div class="row-2">
						<OzField label="Adet">
							<OzInput v-model.number="current.quantity" type="number" min="1" />
						</OzField>
						<OzField label="Beden">
							<OzInput v-model="current.size" placeholder="ops." />
						</OzField>
					</div>
					<div class="btns">
						<OzButton size="sm" :disabled="!current.points.length" @click="current.points = []">Temizle</OzButton>
						<OzButton size="sm" variant="primary" icon="check" :disabled="current.points.length < 3" @click="closeCurrentPiece">Parçayı Bitir</OzButton>
					</div>
				</div>
			</OzCard>

			<OzCard title="Parçalar" :meta="`${pieces.length} parça`">
				<p v-if="!pieces.length" class="empty">Henüz bitmiş parça yok.</p>
				<ul v-else class="pieces">
					<li v-for="(pc, i) in pieces" :key="'l' + i">
						<span class="piece-name">{{ pc.name }}</span>
						<span class="dim">×{{ pc.quantity }}<template v-if="pc.size"> · {{ pc.size }}</template></span>
						<OzButton size="sm" icon="trash" :aria-label="`${pc.name} parçasını sil`" @click="pieces.splice(i, 1)" />
					</li>
				</ul>
			</OzCard>

			<OzCard title="Kaydet (DXF)">
				<div class="panel">
					<OzField label="Kalıp adı" required>
						<OzInput v-model="meta.name" />
					</OzField>
					<div class="row-2">
						<OzField label="Ürün tipi">
							<OzInput v-model="meta.product_type" />
						</OzField>
						<OzField label="Beden aralığı">
							<OzInput v-model="meta.size_range" />
						</OzField>
					</div>
					<p v-if="!canSave" class="dim small">
						{{ !pxPerMm ? 'Önce kalibrasyon yapın.' : !pieces.length ? 'En az bir parça bitirin.' : 'Kalıp adı gerekli.' }}
					</p>
					<OzButton variant="primary" icon="save" :disabled="!canSave" :loading="saving" class="save" @click="save">Kaydet</OzButton>
				</div>
			</OzCard>
		</div>
	</div>

	<OzModal v-model:open="calibOpen" title="Kalibrasyon" subtitle="Çizdiğiniz çizginin gerçek uzunluğu" icon="map" size="sm">
		<OzField label="Gerçek uzunluk" :hint="`Ekranda ${Math.round(calibPx)} px`">
			<OzInput v-model.number="calibMm" type="number" min="1" step="0.5" suffix="mm" @keydown.enter.prevent="applyCalibration" />
		</OzField>
		<template #footer>
			<OzButton @click="calibOpen = false">Vazgeç</OzButton>
			<OzButton variant="primary" icon="check" :disabled="!(calibMm > 0)" @click="applyCalibration">Uygula</OzButton>
		</template>
	</OzModal>
</template>

<style scoped>
.crumbs { margin-bottom: 8px; }
.dpi { width: 120px; }
.dim { color: var(--oz-muted); font-size: 12px; }
.small { margin: 0; }

.layout { display: grid; grid-template-columns: minmax(0, 1fr) 300px; gap: 16px; align-items: start; }
.side { display: flex; flex-direction: column; gap: 16px; }
@media (max-width: 1100px) { .layout { grid-template-columns: 1fr; } }

.tool-hint { margin: 0; padding: 0 18px 10px; font-size: 12px; color: var(--oz-muted); }
.canvas { margin: 0 18px 18px; border: 1px solid var(--oz-border); border-radius: var(--oz-radius-sm); background: var(--oz-surface-2); overflow: auto; max-height: 70vh; min-height: 240px; }
.canvas svg { display: block; }
.canvas svg.tool-calibrate { cursor: crosshair; }
.canvas svg.tool-trace { cursor: cell; }
.canvas-state { display: flex; align-items: center; justify-content: center; gap: 10px; min-height: 240px; color: var(--oz-muted); font-size: 13px; }
.piece { fill: rgba(var(--oz-success-rgb), .15); stroke: var(--oz-success); stroke-width: 2; }
.calib { stroke: var(--oz-danger); stroke-width: 2; stroke-dasharray: 6 4; }
.calib-pt { fill: var(--oz-danger); }
.trace { fill: none; stroke: var(--oz-accent); stroke-width: 2; }
.trace-pt { fill: var(--oz-accent); }

.panel { display: flex; flex-direction: column; gap: 12px; padding: 0 18px 16px; }
.row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
.btns { display: flex; justify-content: flex-end; gap: 8px; }
.save { width: 100%; justify-content: center; }
.empty { margin: 0; padding: 0 18px 16px; font-size: 12.5px; color: var(--oz-muted); }
.pieces { list-style: none; margin: 0; padding: 0 18px 12px; display: flex; flex-direction: column; }
.pieces li { display: flex; align-items: center; gap: 8px; padding: 6px 0; border-bottom: 1px solid var(--oz-border-soft); font-size: 13px; }
.pieces li:last-child { border-bottom: none; }
.piece-name { flex: 1; min-width: 0; font-weight: 600; color: var(--oz-ink-soft); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
</style>
