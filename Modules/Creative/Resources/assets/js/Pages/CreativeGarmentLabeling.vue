<template>
	<Head title="Giysi Parça Etiketleme" />
	<div class="page-labeling">
		<Breadcrumb
			:items="[
				{ label: 'Ana Sayfa', to: '/workflow', icon: 'home' },
				{ label: 'Creative' },
				{ label: 'Parça Tespiti', to: '/creative/garment-scans' },
				{ label: 'Etiketle' },
			]"
		/>

		<div class="page-header">
			<div>
				<h1 class="page-title">Parçaları İşaretle</h1>
				<p class="page-subtitle">
					Görsel üzerinde bir dikdörtgen çizip parçayı adlandırın (yaka, cep, etek, kol ucu...).
					İşaretlediğiniz kutular tespit modelinin eğitim verisidir.
				</p>
				<p v-if="pendingSuggestionCount > 0" class="page-subtitle suggestion-hint">
					{{ pendingSuggestionCount }} AI önerisi onay bekliyor — kesikli amber kutuları
					<strong>Onayla</strong> ya da <strong>Reddet</strong> ile hızlıca gözden geçirin.
				</p>
			</div>
			<span v-if="scan.model_version && scan.model_version !== 'null'" class="model-badge">{{ scan.model_version }}</span>
		</div>

		<div class="card">
			<div class="card-body">
				<div v-if="!scan.image_url" class="empty-block">Bu tarama için bir görsel bulunamadı.</div>
				<div
					v-else
					class="labeling-frame"
					ref="frameRef"
					@mousedown="onMouseDown"
					@mousemove="onMouseMove"
					@mouseup="onMouseUp"
					@mouseleave="onMouseUp"
					@touchstart.prevent="onMouseDown"
					@touchmove.prevent="onMouseMove"
					@touchend.prevent="onMouseUp"
				>
					<img :src="scan.image_url" alt="Giysi görseli" class="labeling-image" draggable="false" />

					<div class="boxes-layer">
						<div
							v-for="d in detections"
							:key="d.id"
							class="existing-box"
							:class="{ manual: d.source === 'manual', zeroshot: d.source === 'zeroshot' }"
							:style="boxStyle(d.bbox)"
						>
							<span class="existing-tag">
								{{ d.label_display }}
								<template v-if="d.source === 'zeroshot'"> · %{{ Math.round(d.confidence * 100) }}</template>
							</span>
							<div v-if="d.source === 'zeroshot'" class="suggestion-actions" @mousedown.stop @touchstart.stop>
								<button type="button" class="suggestion-btn confirm" @click.stop="confirmSuggestion(d)">✓ Onayla</button>
								<button type="button" class="suggestion-btn reject" @click.stop="rejectSuggestion(d)">✕ Reddet</button>
							</div>
						</div>
						<div v-if="draftBox" class="draft-box" :style="boxStyle(draftBox)"></div>
					</div>
				</div>

				<form v-if="pendingBox" class="pending-form" @submit.prevent="submitPending">
					<input
						v-model="pendingLabel"
						type="text"
						list="garment-label-options"
						placeholder="Parça adı (ör. Yaka)"
						maxlength="60"
						autofocus
					/>
					<datalist id="garment-label-options">
						<option v-for="l in labels" :key="l.key" :value="l.display" />
					</datalist>
					<button type="submit" class="btn btn-primary" :disabled="!pendingLabel.trim() || saving">Kaydet</button>
					<button type="button" class="btn btn-ghost" @click="cancelPending">Vazgeç</button>
				</form>
			</div>
		</div>

		<div class="card">
			<div class="card-header"><h3>İşaretli Parçalar</h3><span class="hint">{{ detections.length }}</span></div>
			<div class="card-body">
				<div v-if="detections.length === 0" class="empty-block">Henüz hiç parça işaretlenmedi.</div>
				<div v-else class="detection-list">
					<div v-for="d in detections" :key="d.id" class="detection-row">
						<span class="row-label">{{ d.label_display }}</span>
						<span class="row-source" :class="d.source">{{ sourceLabel(d.source) }}</span>
						<span class="row-confidence">%{{ Math.round(d.confidence * 100) }}</span>
						<button type="button" class="link-btn danger" @click="removeDetection(d)">Sil</button>
					</div>
				</div>
			</div>
		</div>

		<Link href="/creative/garment-scans" class="btn btn-ghost back-link">← Taramalar listesine dön</Link>
	</div>
</template>

<script setup>
import { computed, ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Breadcrumb from '@/Components/Breadcrumb.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
	scan: { type: Object, required: true },
	labels: { type: Array, default: () => [] },
})

const detections = ref(props.scan.detections ?? [])
const pendingSuggestionCount = computed(() => detections.value.filter((d) => d.source === 'zeroshot').length)

const frameRef = ref(null)
const dragging = ref(false)
const dragStart = ref(null)
const draftBox = ref(null)
const pendingBox = ref(null)
const pendingLabel = ref('')
const saving = ref(false)

function clamp01(v) {
	return Math.min(1, Math.max(0, v))
}

function pointFromEvent(e) {
	const point = e.touches?.[0] ?? e.changedTouches?.[0] ?? e
	const rect = frameRef.value.getBoundingClientRect()
	return { x: clamp01((point.clientX - rect.left) / rect.width), y: clamp01((point.clientY - rect.top) / rect.height) }
}

function onMouseDown(e) {
	if (pendingBox.value) return
	dragging.value = true
	dragStart.value = pointFromEvent(e)
	draftBox.value = { x: dragStart.value.x, y: dragStart.value.y, w: 0, h: 0 }
}

function onMouseMove(e) {
	if (!dragging.value) return
	const p = pointFromEvent(e)
	const x0 = Math.min(dragStart.value.x, p.x)
	const y0 = Math.min(dragStart.value.y, p.y)
	draftBox.value = { x: x0, y: y0, w: Math.abs(p.x - dragStart.value.x), h: Math.abs(p.y - dragStart.value.y) }
}

function onMouseUp() {
	if (!dragging.value) return
	dragging.value = false
	if (draftBox.value && draftBox.value.w > 0.015 && draftBox.value.h > 0.015) {
		pendingBox.value = draftBox.value
	} else {
		draftBox.value = null
	}
}

function cancelPending() {
	pendingBox.value = null
	draftBox.value = null
	pendingLabel.value = ''
}

function submitPending() {
	if (!pendingLabel.value.trim() || !pendingBox.value) return
	saving.value = true
	router.post(
		`/creative/garment-scans/${props.scan.id}/annotations`,
		{ bbox: pendingBox.value, label: pendingLabel.value.trim() },
		{
			preserveScroll: true,
			onSuccess: () => {
				router.reload({ only: ['scan'], onSuccess: (page) => { detections.value = page.props.scan.detections ?? [] } })
				cancelPending()
			},
			onFinish: () => { saving.value = false },
		},
	)
}

function removeDetection(d) {
	if (!confirm(`"${d.label_display}" parçasını silmek istediğinize emin misiniz?`)) return
	router.delete(`/creative/garment-scans/${props.scan.id}/annotations/${d.id}`, {
		preserveScroll: true,
		onSuccess: () => {
			router.reload({ only: ['scan'], onSuccess: (page) => { detections.value = page.props.scan.detections ?? [] } })
		},
	})
}

// AI önerisini (source='zeroshot') aynı bbox/etiketle mevcut updateAnnotation
// endpoint'ine göndermek yeterli — backend zaten bunu 'manual'a çeviriyor
// (GarmentScanService::updateAnnotation), ayrı bir "onayla" endpoint'i gerekmez.
function confirmSuggestion(d) {
	router.put(
		`/creative/garment-scans/${props.scan.id}/annotations/${d.id}`,
		{ bbox: d.bbox, label: d.label_display },
		{
			preserveScroll: true,
			onSuccess: () => {
				router.reload({ only: ['scan'], onSuccess: (page) => { detections.value = page.props.scan.detections ?? [] } })
			},
		},
	)
}

// Reddetme, manuel bir kutuyu silmekten farklı: bir AI önerisini geri çevirmek
// düşük riskli/tersinir olduğu için removeDetection'daki onay diyaloğu atlanır
// — gözden geçirmeyi hızlandırmak bu özelliğin asıl amacı.
function rejectSuggestion(d) {
	router.delete(`/creative/garment-scans/${props.scan.id}/annotations/${d.id}`, {
		preserveScroll: true,
		onSuccess: () => {
			router.reload({ only: ['scan'], onSuccess: (page) => { detections.value = page.props.scan.detections ?? [] } })
		},
	})
}

const SOURCE_LABELS = { manual: 'Manuel', zeroshot: 'AI Önerisi', auto: 'Otomatik' }
function sourceLabel(source) {
	return SOURCE_LABELS[source] || 'Otomatik'
}

function boxStyle(bbox) {
	return {
		left: `${bbox.x * 100}%`,
		top: `${bbox.y * 100}%`,
		width: `${bbox.w * 100}%`,
		height: `${bbox.h * 100}%`,
	}
}
</script>

<style scoped>
.page-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 20px; gap: 16px; }
.page-title { font-size: 22px; font-weight: 700; color: #1a1a2e; line-height: 1.2; }
.page-subtitle { font-size: 13px; color: #888; margin-top: 4px; max-width: 620px; }
.model-badge { font-size: 11px; font-weight: 700; padding: 4px 12px; border-radius: 20px; background: rgb(var(--color-primary-soft)); color: rgb(var(--color-primary)); white-space: nowrap; }

.card { background: #fff; border-radius: 16px; border: 1px solid #ebebf0; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,.04); margin-bottom: 18px; }
.card-header { padding: 14px 18px; display: flex; align-items: center; gap: 12px; border-bottom: 1px solid #f0f0f5; }
.card-header h3 { font-size: 15px; font-weight: 700; color: #1a1a2e; }
.card-header .hint { font-size: 12px; color: #aaa; margin-left: auto; }
.card-body { padding: 18px; }
.empty-block { text-align: center; color: #aaa; padding: 30px 0; font-style: italic; font-size: 13px; }

.labeling-frame { position: relative; max-width: 620px; margin: 0 auto; cursor: crosshair; user-select: none; }
.labeling-image { display: block; width: 100%; height: auto; border-radius: 12px; pointer-events: none; }
.boxes-layer { position: absolute; inset: 0; }

.existing-box { position: absolute; border: 2px solid rgb(var(--color-primary)); background: rgba(37, 99, 235, .1); border-radius: 2px; }
.existing-box.manual { border-color: #16a34a; background: rgba(22, 163, 74, .1); }
.existing-box.zeroshot { border-style: dashed; border-color: #f59e0b; background: rgba(245, 158, 11, .1); }
.existing-tag {
	position: absolute; top: -20px; left: 0; background: inherit; background: rgb(var(--color-primary));
	color: #fff; font-size: 10px; font-weight: 700; padding: 1px 6px; border-radius: 4px 4px 4px 0; white-space: nowrap;
}
.existing-box.manual .existing-tag { background: #16a34a; }
.existing-box.zeroshot .existing-tag { background: #f59e0b; }

.suggestion-actions { position: absolute; bottom: -26px; left: 0; display: flex; gap: 4px; pointer-events: auto; }
.suggestion-btn {
	font-size: 10px; font-weight: 700; padding: 2px 7px; border-radius: 5px; border: none; cursor: pointer;
	white-space: nowrap; color: #fff;
}
.suggestion-btn.confirm { background: #16a34a; }
.suggestion-btn.reject { background: #dc2626; }

.suggestion-hint { color: #b45309; font-weight: 600; }

.draft-box { position: absolute; border: 2px dashed #f59e0b; background: rgba(245, 158, 11, .12); border-radius: 2px; }

.pending-form { display: flex; gap: 8px; align-items: center; margin-top: 14px; max-width: 620px; margin-left: auto; margin-right: auto; flex-wrap: wrap; }
.pending-form input { flex: 1; min-width: 160px; padding: 8px 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 13px; }

.detection-list { display: flex; flex-direction: column; gap: 6px; }
.detection-row { display: grid; grid-template-columns: 1fr auto auto auto; align-items: center; gap: 10px; padding: 8px 10px; border-radius: 8px; background: #fafafc; }
.row-label { font-size: 13px; font-weight: 600; color: #1a1a2e; }
.row-source { font-size: 10.5px; font-weight: 700; padding: 2px 8px; border-radius: 10px; text-transform: uppercase; letter-spacing: .02em; }
.row-source.manual { background: #dcfce7; color: #16a34a; }
.row-source.auto { background: #dbeafe; color: rgb(var(--color-primary)); }
.row-source.zeroshot { background: #fef3c7; color: #b45309; }
.row-confidence { font-size: 12px; color: #999; }
.link-btn { background: none; border: none; cursor: pointer; font-size: 12px; font-weight: 600; }
.link-btn.danger { color: #dc2626; }

.back-link { display: inline-block; margin-top: 4px; }

@media (max-width: 700px) {
	.page-header { flex-wrap: wrap; }
	.detection-row { grid-template-columns: 1fr auto; row-gap: 4px; }
}
</style>
