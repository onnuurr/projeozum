<template>
  <div class="raster-tracer">
    <Head :title="`Sayısallaştır — ${pattern.name}`" />
    <Breadcrumb
      :items="[
        { label: 'Ana Sayfa', to: '/workflow', icon: 'home' },
        { label: 'Üretim Atölyesi', to: '/atelier' },
        { label: 'Kalıplar', to: '/atelier/patterns' },
        { label: 'Sayısallaştır' },
      ]"
    />
    <AtelierNav />

    <div class="rt-head">
      <h1 class="rt-title">Sayısallaştır: {{ pattern.name }}</h1>
      <span class="rt-page">Sayfa {{ page + 1 }} / {{ pageCount || '?' }}</span>
      <button class="btn btn-sm btn-secondary" :disabled="page === 0" @click="changePage(page - 1)">◀ Önceki</button>
      <button class="btn btn-sm btn-secondary" :disabled="pageCount && page >= pageCount - 1" @click="changePage(page + 1)">Sonraki ▶</button>
      <label class="rt-dpi">DPI
        <select v-model.number="dpi" class="form-input rt-select" @change="loadImage">
          <option :value="150">150</option><option :value="200">200</option><option :value="300">300</option>
        </select>
      </label>
    </div>

    <div class="rt-tools">
      <span class="rt-tools-label">Araç:</span>
      <button class="btn btn-sm" :class="tool === 'calibrate' ? 'btn-primary' : 'btn-secondary'" @click="tool = 'calibrate'">📏 Kalibrasyon</button>
      <button class="btn btn-sm" :class="tool === 'trace' ? 'btn-primary' : 'btn-secondary'" @click="tool = 'trace'">✏️ İzle</button>
      <span v-if="pxPerMm" class="rt-scale ok">Ölçek: {{ pxPerMm.toFixed(3) }} px/mm</span>
      <span v-else class="rt-scale warn">Ölçek ayarlanmadı — kalibrasyon aracıyla bilinen bir mesafeyi çizin</span>
    </div>

    <div class="rt-canvas">
      <svg v-if="imgUrl" :width="imgW" :height="imgH" @click="onSvgClick">
        <image :href="imgUrl" :width="imgW" :height="imgH" />
        <line v-if="calib.a && calib.b" :x1="calib.a.x" :y1="calib.a.y" :x2="calib.b.x" :y2="calib.b.y"
              stroke="#dc2626" stroke-width="2" />
        <polyline v-if="current.points.length" :points="ptsStr(current.points)"
                  fill="none" stroke="#2563eb" stroke-width="2" />
        <circle v-for="(p, i) in current.points" :key="i" :cx="p.x" :cy="p.y" r="3" fill="#2563eb" />
        <polygon v-for="(pc, i) in pieces" :key="'pc' + i" :points="pieceStr(pc)"
                 fill="rgba(16,185,129,0.15)" stroke="#059669" stroke-width="2" />
      </svg>
      <div v-else class="rt-loading">Sayfa yükleniyor…</div>
    </div>

    <div v-if="tool === 'trace'" class="rt-panel">
      <label class="rt-field"><span class="form-label">Parça adı</span><input v-model="current.name" class="form-input" /></label>
      <label class="rt-field"><span class="form-label">Adet</span><input v-model.number="current.quantity" type="number" min="1" class="form-input rt-narrow" /></label>
      <label class="rt-field"><span class="form-label">Beden</span><input v-model="current.size" class="form-input rt-mid" /></label>
      <button class="btn btn-sm btn-success" :disabled="current.points.length < 3" @click="closeCurrentPiece">✓ Parçayı bitir</button>
      <button class="btn btn-sm btn-secondary" :disabled="!current.points.length" @click="current.points = []">Temizle</button>
    </div>

    <ul v-if="pieces.length" class="rt-pieces">
      <li v-for="(pc, i) in pieces" :key="'l' + i">
        <span>{{ pc.name }} (×{{ pc.quantity }}{{ pc.size ? ', ' + pc.size : '' }})</span>
        <button class="btn btn-xs btn-outline-danger" @click="pieces.splice(i, 1)">sil</button>
      </li>
    </ul>

    <div class="rt-panel rt-save">
      <label class="rt-field"><span class="form-label">Kalıp adı</span><input v-model="meta.name" class="form-input" /></label>
      <label class="rt-field"><span class="form-label">Ürün tipi</span><input v-model="meta.product_type" class="form-input" /></label>
      <label class="rt-field"><span class="form-label">Beden aralığı</span><input v-model="meta.size_range" class="form-input" /></label>
      <button class="btn btn-success" :disabled="!canSave" @click="save">💾 Kaydet (DXF)</button>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Breadcrumb from '@/Components/Breadcrumb.vue'
import AtelierNav from '../Components/AtelierNav.vue'

defineOptions({ layout: AppLayout })
const props = defineProps({ pattern: Object, imageBase: String })

const page = ref(0)
const dpi = ref(200)
const pageCount = ref(0)
const imgUrl = ref('')
const imgW = ref(0)
const imgH = ref(0)
const tool = ref('calibrate')
const pxPerMm = ref(null)
const calib = reactive({ a: null, b: null })
const current = reactive({ points: [], name: '', quantity: 1, size: '' })
const pieces = ref([])
const meta = reactive({
  name: props.pattern.name, product_type: props.pattern.productType || '', size_range: props.pattern.sizeRange || '',
})

const canSave = computed(() => pxPerMm.value && pieces.value.length > 0)

function ptsStr(pts) { return pts.map(p => `${p.x},${p.y}`).join(' ') }
function pieceStr(pc) { return pc.points.map(p => `${p.x},${p.y}`).join(' ') }

async function loadImage() {
  imgUrl.value = ''
  const url = `${props.imageBase}?page=${page.value}&dpi=${dpi.value}`
  const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
  if (!res.ok) { pageCount.value = page.value; page.value = Math.max(0, page.value - 1); return }
  pageCount.value = parseInt(res.headers.get('X-Page-Count') || '1', 10)
  imgW.value = parseInt(res.headers.get('X-Width') || '0', 10)
  imgH.value = parseInt(res.headers.get('X-Height') || '0', 10)
  const blob = await res.blob()
  imgUrl.value = URL.createObjectURL(blob)
}

function changePage(p) {
  if (p < 0) return
  page.value = p
  // sayfa başına kalibrasyon ve aktif izleme sıfırlanır
  pxPerMm.value = null; calib.a = null; calib.b = null; current.points = []
  loadImage()
}

function onSvgClick(e) {
  const rect = e.currentTarget.getBoundingClientRect()
  const x = e.clientX - rect.left
  const y = e.clientY - rect.top
  if (tool.value === 'calibrate') {
    if (!calib.a || (calib.a && calib.b)) { calib.a = { x, y }; calib.b = null }
    else {
      calib.b = { x, y }
      const dpx = Math.hypot(calib.b.x - calib.a.x, calib.b.y - calib.a.y)
      const mm = parseFloat(prompt('Bu çizginin gerçek uzunluğu (mm):', '100'))
      if (mm > 0) pxPerMm.value = dpx / mm
    }
  } else if (tool.value === 'trace') {
    current.points.push({ x, y })
  }
}

function closeCurrentPiece() {
  if (current.points.length < 3) return
  pieces.value.push({
    name: current.name || `Parça ${pieces.value.length + 1}`,
    quantity: current.quantity || 1, size: current.size || '',
    points: current.points.slice(),
  })
  current.points = []; current.name = ''; current.size = ''
}

function save() {
  if (!canSave.value) return
  router.post(`/atelier/patterns/${props.pattern.id}/traced`, {
    name: meta.name, product_type: meta.product_type, size_range: meta.size_range,
    calibration: { px_per_mm: pxPerMm.value, image_height_px: imgH.value },
    pieces: pieces.value.map(pc => ({
      name: pc.name, quantity: pc.quantity, size: pc.size,
      polylines: [{ role: 'cut', points: pc.points.map(p => [p.x, p.y]) }],
    })),
  }, { preserveScroll: true })
}

onMounted(loadImage)
</script>

<style scoped>
.raster-tracer { padding: 0 4px 24px; }

.rt-head {
	display: flex; align-items: center; gap: 12px; flex-wrap: wrap;
	margin: 14px 0 10px;
}
.rt-title { font-size: 17px; font-weight: 700; color: rgb(var(--color-ink, 26 26 46)); }
.rt-page { font-size: 12px; color: #888; }
.rt-dpi { display: inline-flex; align-items: center; gap: 6px; font-size: 12px; color: #666; }
.rt-select { height: 30px; width: auto; padding: 0 8px; }

.rt-tools {
	display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
	margin-bottom: 10px; font-size: 13px;
}
.rt-tools-label { font-weight: 600; color: #555; }
.rt-scale { font-size: 12px; font-weight: 600; }
.rt-scale.ok { color: #16a34a; }
.rt-scale.warn { color: #dc2626; }

.rt-canvas {
	border: 1.5px solid #e8e8f0; border-radius: 10px;
	overflow: auto; max-height: 70vh; background: #f4f4f8;
}
.rt-canvas svg { display: block; cursor: crosshair; }
.rt-loading { padding: 40px; text-align: center; color: #999; }

.rt-panel {
	display: flex; align-items: flex-end; gap: 10px; flex-wrap: wrap;
	border-top: 1px solid #ececf2; padding-top: 12px; margin-top: 12px;
}
.rt-field { display: flex; flex-direction: column; gap: 4px; }
.rt-narrow { width: 80px; }
.rt-mid { width: 120px; }
.rt-save { align-items: flex-end; }

.rt-pieces {
	list-style: none; margin: 10px 0 0; padding: 0;
	display: flex; flex-direction: column; gap: 6px;
}
.rt-pieces li {
	display: flex; align-items: center; gap: 10px;
	font-size: 13px; color: #333;
}
</style>
