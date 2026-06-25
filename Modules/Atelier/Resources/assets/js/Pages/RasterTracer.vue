<template>
  <div>
    <Head :title="`Sayısallaştır — ${pattern.name}`" />
    <AtelierNav />
    <div class="p-4 space-y-3">
      <div class="flex items-center gap-3 flex-wrap">
        <h1 class="text-lg font-semibold">Sayısallaştır: {{ pattern.name }}</h1>
        <span class="text-sm text-gray-500">Sayfa {{ page + 1 }} / {{ pageCount || '?' }}</span>
        <button class="px-2 py-1 border rounded" :disabled="page === 0" @click="changePage(page - 1)">◀ Önceki</button>
        <button class="px-2 py-1 border rounded" :disabled="pageCount && page >= pageCount - 1" @click="changePage(page + 1)">Sonraki ▶</button>
        <label class="text-sm">DPI
          <select v-model.number="dpi" @change="loadImage" class="border rounded px-1">
            <option :value="150">150</option><option :value="200">200</option><option :value="300">300</option>
          </select>
        </label>
      </div>

      <div class="flex gap-2 items-center text-sm flex-wrap">
        <span class="font-medium">Araç:</span>
        <button class="px-2 py-1 border rounded" :class="{ 'bg-blue-600 text-white': tool === 'calibrate' }" @click="tool = 'calibrate'">📏 Kalibrasyon</button>
        <button class="px-2 py-1 border rounded" :class="{ 'bg-blue-600 text-white': tool === 'trace' }" @click="tool = 'trace'">✏️ İzle</button>
        <span v-if="pxPerMm" class="text-green-700">Ölçek: {{ pxPerMm.toFixed(3) }} px/mm</span>
        <span v-else class="text-red-600">Ölçek henüz ayarlanmadı (kalibrasyon aracıyla bilinen bir mesafeyi çizin)</span>
      </div>

      <div class="border rounded overflow-auto bg-gray-100" style="max-height:70vh">
        <svg v-if="imgUrl" :width="imgW" :height="imgH" @click="onSvgClick" style="display:block">
          <image :href="imgUrl" :width="imgW" :height="imgH" />
          <!-- kalibrasyon çizgisi -->
          <line v-if="calib.a && calib.b" :x1="calib.a.x" :y1="calib.a.y" :x2="calib.b.x" :y2="calib.b.y"
                stroke="red" stroke-width="2" />
          <!-- aktif izleme -->
          <polyline v-if="current.points.length" :points="ptsStr(current.points)"
                    fill="none" stroke="#2563eb" stroke-width="2" />
          <circle v-for="(p, i) in current.points" :key="i" :cx="p.x" :cy="p.y" r="3" fill="#2563eb" />
          <!-- kaydedilmiş parçalar -->
          <polygon v-for="(pc, i) in pieces" :key="'pc' + i" :points="pieceStr(pc)"
                   fill="rgba(16,185,129,0.15)" stroke="#059669" stroke-width="2" />
        </svg>
        <div v-else class="p-8 text-center text-gray-500">Sayfa yükleniyor…</div>
      </div>

      <!-- aktif parça paneli -->
      <div v-if="tool === 'trace'" class="flex gap-2 items-end flex-wrap border-t pt-2">
        <label class="text-sm">Parça adı<input v-model="current.name" class="border rounded px-2 py-1 block" /></label>
        <label class="text-sm">Adet<input v-model.number="current.quantity" type="number" min="1" class="border rounded px-2 py-1 block w-20" /></label>
        <label class="text-sm">Beden<input v-model="current.size" class="border rounded px-2 py-1 block w-28" /></label>
        <button class="px-2 py-1 border rounded" @click="closeCurrentPiece" :disabled="current.points.length < 3">✓ Parçayı bitir</button>
        <button class="px-2 py-1 border rounded" @click="current.points = []" :disabled="!current.points.length">Temizle</button>
      </div>

      <ul class="text-sm list-disc pl-5">
        <li v-for="(pc, i) in pieces" :key="'l' + i">{{ pc.name }} ({{ pc.quantity }}x{{ pc.size ? ', ' + pc.size : '' }})
          <button class="text-red-600 ml-2" @click="pieces.splice(i, 1)">sil</button></li>
      </ul>

      <div class="flex gap-2 items-end border-t pt-2 flex-wrap">
        <label class="text-sm">Kalıp adı<input v-model="meta.name" class="border rounded px-2 py-1 block" /></label>
        <label class="text-sm">Ürün tipi<input v-model="meta.product_type" class="border rounded px-2 py-1 block" /></label>
        <label class="text-sm">Beden aralığı<input v-model="meta.size_range" class="border rounded px-2 py-1 block" /></label>
        <button class="px-3 py-1 rounded bg-green-600 text-white disabled:opacity-50" :disabled="!canSave" @click="save">💾 Kaydet (DXF)</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
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
