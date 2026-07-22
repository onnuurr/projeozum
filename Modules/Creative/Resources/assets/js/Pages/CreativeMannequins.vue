<template>
	<Head title="Sanal Manken" />
	<div class="page-mannequins">
		<Breadcrumb
			:items="[
				{ label: 'Ana Sayfa', to: '/workflow', icon: 'home' },
				{ label: 'Creative' },
				{ label: 'Sanal Manken' },
			]"
		/>

		<CreativeNav current="mannequins" />

		<div class="page-header">
			<div>
				<h1 class="page-title">Sanal Manken Stüdyosu</h1>
				<p class="page-subtitle">AI ile manken üret, pozlar oluştur ve ürünlerini giydir</p>
			</div>
			<button type="button" class="btn btn-ghost btn-with-icon" @click="refresh">
				<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
					<path d="M23 4v6h-6M1 20v-6h6" /><path d="M3.5 9a9 9 0 0114.9-3.4L23 10M1 14l4.6 4.4A9 9 0 0020.5 15" />
				</svg>
				Yenile
			</button>
		</div>

		<!-- Yeni manken formu -->
		<div class="card">
			<div class="card-header">
				<h3>Yeni Manken Üret</h3>
				<span class="hint">Tarif girin, AI kimlik görselini üretsin</span>
			</div>
			<div class="card-body">
				<div class="form-grid">
					<label class="field field-wide">
						<span class="field-label">Ad <em>*</em></span>
						<input v-model="form.name" type="text" placeholder="örn. Studio Kadın Manken 1" />
					</label>
					<label class="field">
						<span class="field-label">Cinsiyet</span>
						<select v-model="form.gender">
							<option value="">—</option>
							<option v-for="o in genders" :key="o.value" :value="o.value">{{ o.label }}</option>
						</select>
					</label>
					<label class="field">
						<span class="field-label">Yaş aralığı</span>
						<select v-model="form.age_range">
							<option value="">—</option>
							<option v-for="o in ageRanges" :key="o" :value="o">{{ o }}</option>
						</select>
					</label>
					<label class="field">
						<span class="field-label">Ten tonu</span>
						<select v-model="form.skin_tone">
							<option value="">—</option>
							<option v-for="o in skinTones" :key="o.value" :value="o.value">{{ o.label }}</option>
						</select>
					</label>
					<label class="field">
						<span class="field-label">Vücut tipi</span>
						<select v-model="form.body_type">
							<option value="">—</option>
							<option v-for="o in bodyTypes" :key="o.value" :value="o.value">{{ o.label }}</option>
						</select>
					</label>
					<label class="field">
						<span class="field-label">Saç</span>
						<input v-model="form.hair" type="text" placeholder="örn. uzun düz kahverengi" />
					</label>
					<label class="field field-wide">
						<span class="field-label">Yüz tarifi</span>
						<textarea v-model="form.face" rows="2" placeholder="örn. oval yüz, belirgin elmacık kemikleri, kahverengi gözler, hafif gülümseme"></textarea>
					</label>
					<label class="field">
						<span class="field-label">Boy (cm)</span>
						<input v-model.number="form.height_cm" type="number" min="30" max="260" placeholder="örn. 175" />
					</label>
					<label class="field">
						<span class="field-label">Göğüs (cm)</span>
						<input v-model.number="form.bust_cm" type="number" min="20" max="200" placeholder="örn. 90" />
					</label>
					<label class="field">
						<span class="field-label">Bel (cm)</span>
						<input v-model.number="form.waist_cm" type="number" min="20" max="200" placeholder="örn. 62" />
					</label>
					<label class="field">
						<span class="field-label">Kalça (cm)</span>
						<input v-model.number="form.hips_cm" type="number" min="20" max="200" placeholder="örn. 92" />
					</label>
					<label class="field field-wide">
						<span class="field-label">Ek tarif (opsiyonel)</span>
						<textarea v-model="form.extras" rows="2" placeholder="Makyaj, stil, duruş tonu detayları…"></textarea>
					</label>
				</div>

				<div class="reference-upload">
					<div class="reference-upload-head">
						<strong>Referans fotoğraf (opsiyonel)</strong>
						<span class="hint">Kimliği kopyalamaz — yalnızca ten/ışık/doku gerçekçiliği için AI'a görsel çapa olarak verilir.</span>
					</div>
					<div class="reference-upload-body">
						<label class="upload-drop" v-if="!referencePreview">
							<input type="file" accept="image/*" @change="onReferenceFileChange" hidden />
							<span>Görsel seç…</span>
						</label>
						<div v-else class="reference-preview">
							<img :src="referencePreview" alt="Referans fotoğraf" />
							<button type="button" class="link-btn danger" @click="clearReferenceFile">Kaldır</button>
						</div>
					</div>
				</div>

				<div class="form-actions">
					<button class="btn btn-primary btn-with-icon" :disabled="!canCreate || busy" @click="create">
						<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
							<path d="M12 5v14M5 12h14" />
						</svg>
						{{ busy ? 'Kuyruğa alınıyor…' : 'Manken Üret' }}
					</button>
				</div>
			</div>
		</div>

		<!-- Manken listesi -->
		<div class="card">
			<div class="card-header">
				<h3>Mankenler</h3>
				<span class="hint">{{ mannequins.length }} manken</span>
			</div>
			<div class="card-body">
				<div v-if="mannequins.length === 0" class="empty-block">Henüz manken yok. Yukarıdan bir tane üretin.</div>
				<div v-else class="mannequin-grid">
					<div v-for="m in mannequins" :key="m.id" class="mannequin-card">
						<div class="mannequin-thumb">
							<img v-if="m.reference_url" :src="m.reference_url" :alt="m.name" class="clickable" @click="openPreview(m)" />
							<span v-else class="no-preview">{{ statusLabel(m.status) }}</span>
							<button v-if="m.reference_url" class="zoom-badge" title="Büyük önizleme" @click="openPreview(m)">⤢</button>
							<span class="status-badge" :class="m.status">{{ statusLabel(m.status) }}</span>
							<span v-if="m.review_status" class="review-badge" :class="`r-${m.review_status}`">
								{{ reviewLabel(m.review_status) }}
							</span>
						</div>
						<div class="mannequin-meta">
							<span class="mannequin-name">{{ m.name }}</span>
							<span class="mannequin-traits">{{ traitLine(m) || '—' }}</span>
							<span v-if="measureLine(m)" class="mannequin-measures">{{ measureLine(m) }}</span>
							<span v-if="m.creator_name" class="mannequin-creator">Üreten: {{ m.creator_name }}{{ m.is_own ? ' (siz)' : '' }}</span>
							<div v-if="m.review_tags && m.review_tags.length" class="review-tags">
								<span v-for="t in m.review_tags" :key="t" class="review-tag">{{ t }}</span>
							</div>
							<p v-if="m.error" class="mannequin-error" :title="m.error">⚠ {{ m.error }}</p>
						</div>
						<div v-if="m.can_review" class="mannequin-review-actions">
							<button type="button" class="act-btn approve" :disabled="busyReview === m.id" @click="approve(m)">✓ Onayla</button>
							<button type="button" class="act-btn reject" :disabled="busyReview === m.id" @click="reject(m)">✕ Reddet</button>
						</div>
						<div v-if="can('creative.asset.manage')" class="mannequin-actions">
							<button type="button" class="link-btn" @click="regenerate(m)">Yeniden üret</button>
							<button type="button" class="link-btn danger" @click="destroy(m)">Sil</button>
						</div>
						<Link v-if="m.can_chat" :href="`/creative/mannequins/${m.id}/review-chat`" class="chat-link">
							💬 AI ile Konuş <span v-if="m.review_chats?.length">({{ m.review_chats.length }} mesaj)</span>
						</Link>
					</div>
				</div>
			</div>
		</div>

		<!-- Büyük önizleme (lightbox) -->
		<Teleport to="body">
			<div v-if="preview" class="lightbox" @click.self="closePreview">
				<div class="lb-box">
					<button class="lb-close" @click="closePreview">✕</button>
					<div
						class="lb-img-wrap"
						:class="{ zoomed: zoom.scale > 1, dragging: zoom.dragging }"
						@wheel.prevent="onWheel"
						@pointerdown="onPointerDown"
						@pointermove="onPointerMove"
						@pointerup="onPointerUp"
						@pointercancel="onPointerUp"
						@dblclick="onDblClick"
						@touchstart.passive="onTouchStart"
						@touchmove.prevent="onTouchMove"
					>
						<img
							:src="preview.reference_url"
							:alt="preview.name"
							draggable="false"
							:style="{ transform: `translate(${zoom.x}px, ${zoom.y}px) scale(${zoom.scale})` }"
						/>
					</div>
					<div class="lb-side">
						<h3 class="lb-title">{{ preview.name }}</h3>
						<div class="lb-tags-meta">
							<span v-if="traitLine(preview)" class="lb-chip">{{ traitLine(preview) }}</span>
							<span v-if="measureLine(preview)" class="lb-chip dim">{{ measureLine(preview) }}</span>
							<span class="lb-chip ghost">{{ statusLabel(preview.status) }}</span>
						</div>
						<span class="lb-hint">Yakınlaştırmak için fare tekerleği veya pinch, gezinmek için sürükleyin.</span>
						<a :href="preview.reference_url" target="_blank" :download="`manken-${preview.id}.png`" class="btn btn-primary lb-download">
							<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M7 10l5 5 5-5M12 15V3" /></svg>
							İndir
						</a>
					</div>
				</div>
			</div>
		</Teleport>
	</div>
</template>

<script setup>
import { ref, reactive, computed, inject, onMounted, onUnmounted } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Breadcrumb from '@/Components/Breadcrumb.vue'
import CreativeNav from '../Components/CreativeNav.vue'
import { openRejectDialog } from '../support/rejectDialog'
import { useCan } from '@/composables/useCan'

defineOptions({ layout: AppLayout })

const props = defineProps({
	mannequins: { type: Array, default: () => [] },
	rejectionReasons: { type: Array, default: () => [] },
})

const showToast = inject('showToast', null)
const $swal = inject('$swal')
const { can } = useCan()

const busy = ref(false)
const busyReview = ref(null)
const preview = ref(null)
const form = reactive({
	name: '', gender: '', age_range: '', skin_tone: '', body_type: '', hair: '',
	face: '', height_cm: null, bust_cm: null, waist_cm: null, hips_cm: null, extras: '',
})

const referenceFile = ref(null)
const referencePreview = ref(null)

function onReferenceFileChange(e) {
	const file = e.target.files[0] || null
	referenceFile.value = file
	if (referencePreview.value) URL.revokeObjectURL(referencePreview.value)
	referencePreview.value = file ? URL.createObjectURL(file) : null
}

function clearReferenceFile() {
	if (referencePreview.value) URL.revokeObjectURL(referencePreview.value)
	referenceFile.value = null
	referencePreview.value = null
}

const genders = [
	{ value: 'female', label: 'Kadın' },
	{ value: 'male', label: 'Erkek' },
	{ value: 'unisex', label: 'Unisex' },
]
const ageRanges = ['0-2', '3-5', '6-9', '10-13', '14-17', '18-25', '25-35', '35-45', '45+']
const skinTones = [
	{ value: 'fair', label: 'Açık' },
	{ value: 'medium', label: 'Orta' },
	{ value: 'tan', label: 'Bronz' },
	{ value: 'dark', label: 'Koyu' },
]
const bodyTypes = [
	{ value: 'slim', label: 'İnce' },
	{ value: 'athletic', label: 'Atletik' },
	{ value: 'average', label: 'Ortalama' },
	{ value: 'plus-size', label: 'Plus-size' },
]

const STATUS_LABELS = { draft: 'Taslak', generating: 'Üretiliyor', ready: 'Hazır', failed: 'Başarısız' }
const REVIEW_LABELS = { pending: 'Onay Bekliyor', approved: 'Onaylı', rejected: 'Reddedildi' }

const canCreate = computed(() => form.name.trim().length > 0)
const hasPending = computed(() => props.mannequins.some(m => m.status === 'draft' || m.status === 'generating'))

function statusLabel(s) { return STATUS_LABELS[s] || s }
function reviewLabel(r) { return REVIEW_LABELS[r] || r }

function approve(m) {
	if (busyReview.value) return
	busyReview.value = m.id
	router.post(`/creative/mannequins/${m.id}/approve`, {}, {
		preserveScroll: true,
		preserveState: false,
		onError: (errs) => showToast?.({ type: 'error', title: 'Onaylanamadı', message: Object.values(errs)[0] || 'Sunucu hatası.' }),
		onFinish: () => { busyReview.value = null },
	})
}

async function reject(m) {
	const result = await openRejectDialog($swal, props.rejectionReasons)
	if (!result) return

	busyReview.value = m.id
	router.post(`/creative/mannequins/${m.id}/reject`, { reason: result.reason, tags: result.tags }, {
		preserveScroll: true,
		preserveState: false,
		onError: (errs) => showToast?.({ type: 'error', title: 'Reddedilemedi', message: Object.values(errs)[0] || 'Sunucu hatası.' }),
		onFinish: () => { busyReview.value = null },
	})
}

function traitLine(m) {
	return [m.gender, m.age_range, m.skin_tone, m.body_type].filter(Boolean).join(' · ')
}

function measureLine(m) {
	const parts = []
	if (m.height_cm) parts.push(`boy ${m.height_cm}`)
	if (m.bust_cm) parts.push(`G${m.bust_cm}`)
	if (m.waist_cm) parts.push(`B${m.waist_cm}`)
	if (m.hips_cm) parts.push(`K${m.hips_cm}`)
	return parts.join(' · ')
}

function create() {
	if (!canCreate.value || busy.value) return
	busy.value = true
	router.post('/creative/mannequins', { ...form, reference_photo: referenceFile.value }, {
		onSuccess: () => {
			form.name = ''; form.hair = ''; form.face = ''; form.extras = ''
			form.height_cm = null; form.bust_cm = null; form.waist_cm = null; form.hips_cm = null
			clearReferenceFile()
		},
		onError: (errs) => {
			showToast?.({ type: 'error', title: 'Üretilemedi', message: Object.values(errs)[0] || 'Doğrulama hatası.' })
		},
		onFinish: () => { busy.value = false },
	})
}

function regenerate(m) {
	router.post(`/creative/mannequins/${m.id}/regenerate`, {}, {
		preserveScroll: true,
	})
}

async function destroy(m) {
	const ok = await $swal.dangerConfirm({
		title: 'Mankeni Sil',
		html: `<strong>${m.name}</strong> ve ürettiği pozlar silinecek.`,
		confirmText: 'Sil',
		cancelText: 'Vazgeç',
	})
	if (!ok) return
	router.delete(`/creative/mannequins/${m.id}`, {
		preserveScroll: true,
	})
}

// Büyük önizleme (lightbox) zoom/pan durumu — bkz. CreativeTryon.vue (aynı yapı).
const zoom = reactive({ scale: 1, x: 0, y: 0, dragging: false })
const MIN_SCALE = 1
const MAX_SCALE = 5

function resetZoom() {
	zoom.scale = 1
	zoom.x = 0
	zoom.y = 0
}

function openPreview(m) {
	if (!m.reference_url) return
	preview.value = m
	resetZoom()
}

function closePreview() {
	preview.value = null
}

// img, flex ile wrapper'ın merkezine ortalandığı için CSS transform-origin (img'in kendi
// merkezi) de wrapper merkeziyle çakışır — imleç noktasını sabit tutmak için scale
// değişimini bu merkeze göre telafi etmemiz gerekiyor, yoksa zoom her zaman görselin
// ortasına doğru kayar.
function zoomAt(cx, cy, rect, targetScale) {
	const centerX = rect.width / 2
	const centerY = rect.height / 2
	const prev = zoom.scale
	const next = Math.min(MAX_SCALE, Math.max(MIN_SCALE, targetScale))
	const k = next / prev
	zoom.x = (1 - k) * (cx - centerX) + k * zoom.x
	zoom.y = (1 - k) * (cy - centerY) + k * zoom.y
	zoom.scale = next
	if (next === MIN_SCALE) { zoom.x = 0; zoom.y = 0 }
}

function onWheel(e) {
	const rect = e.currentTarget.getBoundingClientRect()
	const cx = e.clientX - rect.left
	const cy = e.clientY - rect.top
	zoomAt(cx, cy, rect, zoom.scale * (e.deltaY < 0 ? 1.15 : 1 / 1.15))
}

let dragStart = null
function onPointerDown(e) {
	if (zoom.scale <= 1) return
	e.preventDefault()
	zoom.dragging = true
	dragStart = { x: e.clientX - zoom.x, y: e.clientY - zoom.y }
	e.currentTarget.setPointerCapture(e.pointerId)
}
function onPointerMove(e) {
	if (!zoom.dragging || !dragStart) return
	zoom.x = e.clientX - dragStart.x
	zoom.y = e.clientY - dragStart.y
}
function onPointerUp(e) {
	zoom.dragging = false
	dragStart = null
	if (e.currentTarget?.hasPointerCapture?.(e.pointerId)) e.currentTarget.releasePointerCapture(e.pointerId)
}

function onDblClick(e) {
	if (zoom.scale > 1) {
		resetZoom()
		return
	}
	const rect = e.currentTarget.getBoundingClientRect()
	zoomAt(e.clientX - rect.left, e.clientY - rect.top, rect, 2)
}

// Mobil pinch-zoom / tek parmak sürükleme
let touchStartDist = null
let touchStartScale = 1
let touchStartPos = null
function touchDist(touches) {
	const [a, b] = touches
	return Math.hypot(b.clientX - a.clientX, b.clientY - a.clientY)
}
function onTouchStart(e) {
	if (e.touches.length === 2) {
		touchStartDist = touchDist(e.touches)
		touchStartScale = zoom.scale
	} else if (e.touches.length === 1 && zoom.scale > 1) {
		touchStartPos = { x: e.touches[0].clientX - zoom.x, y: e.touches[0].clientY - zoom.y }
	}
}
function onTouchMove(e) {
	if (e.touches.length === 2 && touchStartDist) {
		const next = Math.min(MAX_SCALE, Math.max(MIN_SCALE, touchStartScale * (touchDist(e.touches) / touchStartDist)))
		zoom.scale = next
		if (next === MIN_SCALE) { zoom.x = 0; zoom.y = 0 }
	} else if (e.touches.length === 1 && touchStartPos) {
		zoom.x = e.touches[0].clientX - touchStartPos.x
		zoom.y = e.touches[0].clientY - touchStartPos.y
	}
}

function onKeydown(e) {
	if (e.key === 'Escape' && preview.value) closePreview()
}

function refresh() {
	router.reload({ only: ['mannequins'] })
}

// Üretim sürerken listeyi periyodik tazele (queue worker arkaplanda işliyor).
let timer = null
onMounted(() => {
	timer = setInterval(() => { if (hasPending.value) refresh() }, 5000)
	window.addEventListener('keydown', onKeydown)
})
onUnmounted(() => {
	if (timer) clearInterval(timer)
	window.removeEventListener('keydown', onKeydown)
})
</script>

<style scoped>
.page-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 20px; gap: 16px; }
.page-title { font-size: 22px; font-weight: 700; color: #1a1a2e; line-height: 1.2; }
.page-subtitle { font-size: 13px; color: #888; margin-top: 4px; }

.card { background: #fff; border-radius: 16px; border: 1px solid #ebebf0; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,.04); margin-bottom: 18px; }
.card-header { padding: 14px 18px; display: flex; align-items: center; gap: 12px; border-bottom: 1px solid #f0f0f5; }
.card-header h3 { font-size: 15px; font-weight: 700; color: #1a1a2e; }
.card-header .hint { font-size: 12px; color: #aaa; margin-left: auto; }
.card-body { padding: 18px; }
.empty-block { text-align: center; color: #aaa; padding: 28px 0; font-style: italic; font-size: 13px; }

/* Form */
.form-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 14px; }
.field { display: flex; flex-direction: column; gap: 5px; }
.field-wide { grid-column: 1 / -1; }
.field-label { font-size: 12px; font-weight: 600; color: #555; }
.field-label em { color: rgb(var(--color-primary)); font-style: normal; }
.field input, .field select, .field textarea { border: 1px solid #e8e8f0; border-radius: 8px; padding: 8px 10px; font-family: inherit; font-size: 13px; color: #1a1a2e; outline: none; background: #fff; }
.field input:focus, .field select:focus, .field textarea:focus { border-color: rgb(var(--color-primary)); }
.field textarea { resize: vertical; }
.form-actions { margin-top: 16px; display: flex; justify-content: flex-end; }

.reference-upload { margin-top: 16px; padding-top: 16px; border-top: 1px solid #f0f0f5; }
.reference-upload-head { display: flex; flex-wrap: wrap; align-items: baseline; gap: 8px; margin-bottom: 10px; font-size: 13px; color: #1a1a2e; }
.reference-upload-head .hint { color: #888; font-weight: 400; }
.upload-drop { display: inline-flex; align-items: center; justify-content: center; width: 120px; height: 120px; border: 2px dashed #d5d5e0; border-radius: 12px; color: #888; font-size: 12px; font-weight: 600; cursor: pointer; }
.upload-drop:hover { border-color: rgb(var(--color-primary)); color: rgb(var(--color-primary)); }
.reference-preview { display: flex; align-items: center; gap: 12px; }
.reference-preview img { width: 90px; height: 90px; border-radius: 10px; object-fit: cover; border: 1px solid #ebebf0; }

/* Liste */
.mannequin-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(190px, 1fr)); gap: 16px; }
.mannequin-card { border: 1px solid #ebebf0; border-radius: 12px; overflow: hidden; background: #fff; display: flex; flex-direction: column; }
.mannequin-thumb { position: relative; aspect-ratio: 3 / 4; background: #f5f5f8; display: flex; align-items: center; justify-content: center; }
.mannequin-thumb img { width: 100%; height: 100%; object-fit: cover; }
.mannequin-thumb img.clickable { cursor: zoom-in; }
.zoom-badge { position: absolute; bottom: 8px; right: 8px; width: 28px; height: 28px; border: none; border-radius: 8px; background: rgba(15,15,25,.55); color: #fff; font-size: 14px; cursor: pointer; display: flex; align-items: center; justify-content: center; opacity: 0; transition: opacity .15s; }
.mannequin-thumb:hover .zoom-badge { opacity: 1; }
.no-preview { font-size: 12px; color: #bbb; font-weight: 700; }
.status-badge { position: absolute; top: 8px; left: 8px; font-size: 10px; font-weight: 700; padding: 3px 8px; border-radius: 20px; color: #fff; text-transform: uppercase; letter-spacing: .03em; }
.status-badge.draft { background: #9ca3af; }
.status-badge.generating { background: #f59e0b; }
.status-badge.ready { background: #16a34a; }
.status-badge.failed { background: #dc2626; }
.review-badge { position: absolute; top: 8px; right: 8px; padding: 3px 8px; border-radius: 6px; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .02em; }
.review-badge.r-pending { background: #f59e0b; color: #fff; }
.review-badge.r-approved { background: #16a34a; color: #fff; }
.review-badge.r-rejected { background: #dc2626; color: #fff; }
.mannequin-meta { padding: 10px 12px; display: flex; flex-direction: column; gap: 3px; flex: 1; }
.mannequin-name { font-size: 13px; font-weight: 700; color: #1a1a2e; }
.mannequin-traits { font-size: 11px; color: #888; }
.mannequin-measures { font-size: 11px; color: rgb(var(--color-primary)); font-weight: 600; margin-top: 2px; }
.mannequin-creator { font-size: 10.5px; color: #aaa; }
.review-tags { display: flex; flex-wrap: wrap; gap: 4px; margin-top: 4px; }
.review-tag { font-size: 10px; font-weight: 600; padding: 2px 7px; border-radius: 10px; background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
.mannequin-error { font-size: 11px; color: #dc2626; margin-top: 4px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.mannequin-review-actions { display: flex; gap: 8px; padding: 0 12px 10px; }
.act-btn { flex: 1; border: none; cursor: pointer; font-size: 12px; font-weight: 600; padding: 7px 6px; border-radius: 8px; font-family: inherit; transition: all .15s; }
.act-btn:disabled { opacity: .45; cursor: not-allowed; }
.act-btn.approve { background: #dcfce7; color: #15803d; }
.act-btn.approve:hover:not(:disabled) { background: #bbf7d0; }
.act-btn.reject { background: #fee2e2; color: #b91c1c; }
.act-btn.reject:hover:not(:disabled) { background: #fecaca; }
.mannequin-actions { display: flex; gap: 12px; padding: 10px 12px; border-top: 1px solid #f0f0f5; }
.link-btn { background: none; border: none; color: rgb(var(--color-primary)); font-size: 12px; font-weight: 600; cursor: pointer; font-family: inherit; padding: 0; }
.link-btn.danger { color: #dc2626; margin-left: auto; }

/* Lightbox */
.lightbox { position: fixed; inset: 0; z-index: 9999; background: rgba(15,15,25,.82); display: flex; align-items: center; justify-content: center; padding: 32px; backdrop-filter: blur(3px); }
.lb-box { display: flex; gap: 0; max-width: 1100px; max-height: 90vh; background: #fff; border-radius: 16px; overflow: hidden; box-shadow: 0 24px 80px rgba(0,0,0,.4); position: relative; }
.lb-close { position: absolute; top: 12px; right: 12px; z-index: 2; width: 34px; height: 34px; border: none; border-radius: 50%; background: rgba(255,255,255,.9); color: #1a1a2e; font-size: 16px; cursor: pointer; box-shadow: 0 2px 8px rgba(0,0,0,.2); }
.lb-close:hover { background: #fff; }
.lb-img-wrap { background: #11111b repeating-conic-gradient(#1a1a26 0% 25%, #15151f 0% 50%) 0 / 24px 24px; display: flex; align-items: center; justify-content: center; min-width: 0; overflow: hidden; cursor: zoom-in; touch-action: none; }
.lb-img-wrap.zoomed { cursor: grab; }
.lb-img-wrap.dragging { cursor: grabbing; }
.lb-img-wrap img { max-width: 62vw; max-height: 90vh; object-fit: contain; display: block; transition: transform .08s; will-change: transform; -webkit-user-drag: none; user-select: none; }
.lb-img-wrap.dragging img { transition: none; }
.lb-side { width: 300px; flex-shrink: 0; padding: 22px; display: flex; flex-direction: column; gap: 14px; overflow-y: auto; }
.lb-title { font-size: 17px; font-weight: 700; color: #1a1a2e; }
.lb-tags-meta { display: flex; flex-wrap: wrap; gap: 6px; }
.lb-chip { font-size: 11px; font-weight: 700; padding: 3px 9px; border-radius: 6px; background: rgb(var(--color-primary-soft)); color: rgb(var(--color-primary-hover)); }
.lb-chip.dim { background: #f0f0f5; color: #555; font-family: 'SF Mono', Menlo, Consolas, monospace; }
.lb-chip.ghost { background: #f5f5f8; color: #888; }
.lb-hint { font-size: 11px; color: #aaa; line-height: 1.4; }
.lb-download { margin-top: auto; justify-content: center; }
@media (max-width: 820px) { .lb-box { flex-direction: column; } .lb-img-wrap img { max-width: 86vw; max-height: 50vh; } .lb-side { width: auto; } }
.chat-link { display: block; text-align: center; padding: 8px 12px; margin: 0 12px 12px; background: #eef2ff; color: #4338ca; border-radius: 8px; font-size: 12px; font-weight: 600; text-decoration: none; }
.chat-link:hover { background: #e0e7ff; }

/* ── Dar ekran (telefon) ── */
@media (max-width: 640px) {
	.page-header { flex-wrap: wrap; }
	.page-header .btn { width: 100%; justify-content: center; }
	.card-header { flex-wrap: wrap; row-gap: 6px; }
	.card-header .hint { margin-left: 0; }
	.form-grid { grid-template-columns: 1fr; }
	.mannequin-grid { grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 10px; }
}
</style>
