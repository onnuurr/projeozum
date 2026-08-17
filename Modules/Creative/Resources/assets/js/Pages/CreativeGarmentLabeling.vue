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

		<PageHeader title="Parçaları İşaretle">
			<template #subtitle>
				Görsel üzerinde bir dikdörtgen çizip parçayı adlandırın (yaka, cep, etek, kol ucu...).
				İşaretlediğiniz kutular tespit modelinin eğitim verisidir.
				<template v-if="pendingSuggestionCount > 0">
					<br />
					<span class="suggestion-hint">
						{{ pendingSuggestionCount }} AI önerisi onay bekliyor — kesikli amber kutuları
						<strong>Onayla</strong> ya da <strong>Reddet</strong> ile hızlıca gözden geçirin.
					</span>
				</template>
			</template>
			<template #actions>
				<Badge v-if="scan.model_version && scan.model_version !== 'null'" color="primary" variant="tonal" :label="scan.model_version" />
			</template>
		</PageHeader>

		<Card>
			<EmptyState v-if="!scan.image_url" :icon="ImageOff" title="Bu tarama için bir görsel bulunamadı." />
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
							<button type="button" class="suggestion-btn confirm" @click.stop="confirmSuggestion(d)"><Check :size="10" /> Onayla</button>
							<button type="button" class="suggestion-btn reject" @click.stop="rejectSuggestion(d)"><X :size="10" /> Reddet</button>
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
				<Button variant="primary" type="submit" :disabled="!pendingLabel.trim()" :loading="saving">Kaydet</Button>
				<Button variant="ghost" type="button" @click="cancelPending">Vazgeç</Button>
			</form>
		</Card>

		<Card title="İşaretli Parçalar">
			<template #actions>
				<span class="hint">{{ detections.length }}</span>
			</template>

			<EmptyState v-if="detections.length === 0" :icon="Tags" title="Henüz hiç parça işaretlenmedi." />
			<div v-else class="detection-list">
				<div v-for="d in detections" :key="d.id" class="detection-row">
					<span class="row-label">{{ d.label_display }}</span>
					<Badge :color="sourceColor(d.source)" variant="tonal" :label="sourceLabel(d.source)" />
					<span class="row-confidence">%{{ Math.round(d.confidence * 100) }}</span>
					<button type="button" class="link-btn danger" @click="removeDetection(d)">Sil</button>
				</div>
			</div>
		</Card>

		<Button variant="ghost" class="back-link" @click="router.visit('/creative/garment-scans')">← Taramalar listesine dön</Button>
	</div>
</template>

<script setup>
import { computed, ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import { Check, X, ImageOff, Tags } from 'lucide-vue-next'
import AppLayout from '@/Layouts/AppLayout.vue'
import Breadcrumb from '@/Components/Breadcrumb.vue'
import PageHeader from '@/Components/PageHeader.vue'
import Card from '@/Components/Card.vue'
import Button from '@/Components/Button.vue'
import Badge from '@/Components/Badge.vue'
import EmptyState from '@/Components/EmptyState.vue'

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

const SOURCE_COLORS = { manual: 'success', zeroshot: 'warning', auto: 'info' }
function sourceColor(source) {
	return SOURCE_COLORS[source] ?? 'info'
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
.hint { font-size: 12px; color: var(--color-muted); white-space: nowrap; }

.labeling-frame { position: relative; max-width: 620px; margin: 0 auto; cursor: crosshair; user-select: none; }
.labeling-image { display: block; width: 100%; height: auto; border-radius: 12px; pointer-events: none; }
.boxes-layer { position: absolute; inset: 0; }

.existing-box { position: absolute; border: 2px solid var(--color-primary); background: color-mix(in srgb, var(--color-primary) 10%, transparent); border-radius: 2px; }
.existing-box.manual { border-color: var(--color-success); background: color-mix(in srgb, var(--color-success) 10%, transparent); }
.existing-box.zeroshot { border-style: dashed; border-color: var(--color-warning); background: color-mix(in srgb, var(--color-warning) 10%, transparent); }
.existing-tag {
	position: absolute; top: -20px; left: 0; background: var(--color-primary);
	color: var(--color-on-primary); font-size: 10px; font-weight: 700; padding: 1px 6px; border-radius: 4px 4px 4px 0; white-space: nowrap;
}
.existing-box.manual .existing-tag { background: var(--color-success); }
.existing-box.zeroshot .existing-tag { background: var(--color-warning); }

.suggestion-actions { position: absolute; bottom: -26px; left: 0; display: flex; gap: 4px; pointer-events: auto; }
.suggestion-btn {
	display: inline-flex; align-items: center; gap: 2px;
	font-size: 10px; font-weight: 700; padding: 2px 7px; border-radius: 5px; border: none; cursor: pointer;
	white-space: nowrap; color: var(--color-on-primary);
}
.suggestion-btn.confirm { background: var(--color-success); }
.suggestion-btn.reject { background: var(--color-danger); }

.suggestion-hint { color: var(--color-warning); font-weight: 600; }

.draft-box { position: absolute; border: 2px dashed var(--color-warning); background: color-mix(in srgb, var(--color-warning) 12%, transparent); border-radius: 2px; }

.pending-form { display: flex; gap: 8px; align-items: center; margin-top: 14px; max-width: 620px; margin-left: auto; margin-right: auto; flex-wrap: wrap; }
.pending-form input { flex: 1; min-width: 160px; padding: 8px 12px; border: 1px solid var(--color-outline-variant); border-radius: 8px; font-size: 13px; background: var(--color-surface); color: var(--color-ink); }

.detection-list { display: flex; flex-direction: column; gap: 6px; }
.detection-row { display: grid; grid-template-columns: 1fr auto auto auto; align-items: center; gap: 10px; padding: 8px 10px; border-radius: 8px; background: var(--color-surface-container-low); }
.row-label { font-size: 13px; font-weight: 600; color: var(--color-ink); }
.row-confidence { font-size: 12px; color: var(--color-muted); }
.link-btn { background: none; border: none; cursor: pointer; font-size: 12px; font-weight: 600; }
.link-btn.danger { color: var(--color-danger); }

.back-link { display: inline-block; margin-top: 4px; }

@media (max-width: 700px) {
	.detection-row { grid-template-columns: 1fr auto; row-gap: 4px; }
}
</style>
