<template>
	<Head title="Şablonlar" />
	<div class="page-templates">
		<Breadcrumb
			:items="[
				{ label: 'Ana Sayfa', to: '/workflow', icon: 'home' },
				{ label: 'Creative' },
				{ label: 'Şablonlar' },
			]"
		/>

		<CreativeNav current="templates" />

		<div class="page-header">
			<div>
				<h1 class="page-title">Şablonlar</h1>
				<p class="page-subtitle">SVG şablon yükleyin, slotları sürükleyerek konumlandırın. Değişiklikler SVG'ye işlenir.</p>
			</div>
			<label class="btn btn-primary btn-with-icon" :class="{ disabled: uploading }">
				<svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14" /></svg>
				{{ uploading ? 'Yükleniyor…' : 'SVG Yükle' }}
				<input type="file" accept=".svg,image/svg+xml" hidden @change="uploadTemplate" />
			</label>
		</div>

		<div class="tpl-layout">
			<!-- Şablon listesi -->
			<aside class="tpl-list">
				<div v-if="templates.length === 0" class="empty-block">Şablon yok.</div>
				<button
					v-for="t in templates"
					:key="t.id"
					class="tpl-list-item"
					:class="{ active: selectedId === t.id, inactive: !t.is_active }"
					@click="select(t)"
				>
					<div class="tpl-thumb">
						<img v-if="t.preview_url" :src="t.preview_url" :alt="t.name" />
						<span v-else class="no-preview">—</span>
					</div>
					<div class="tpl-list-meta">
						<span class="tpl-list-name">{{ t.name }}</span>
						<span class="tpl-list-dim">{{ t.width }}×{{ t.height }} · {{ (t.slots || []).length }} slot</span>
					</div>
					<span v-if="!t.is_active" class="tpl-off">pasif</span>
				</button>
			</aside>

			<!-- Tasarımcı -->
			<section v-if="current" class="tpl-designer">
				<div class="card">
					<div class="card-header">
						<input v-model="nameDraft" class="name-input" type="text" @blur="saveMeta" />
						<div class="header-actions">
							<label class="active-toggle" :class="{ on: activeDraft }">
								<input v-model="activeDraft" type="checkbox" @change="saveMeta" />
								<span class="at-dot"></span> Aktif
							</label>
							<button class="link-btn danger" @click="removeTemplate">Sil</button>
						</div>
					</div>

					<div class="card-body designer-body">
						<!-- Sahne -->
						<div class="stage-wrap">
							<div v-if="!current.width" class="empty-block">
								Boyut çıkarılamadı. SVG'de width/height veya viewBox olduğundan emin olun.
							</div>
							<div
								v-else
								ref="stageEl"
								class="stage"
								:style="{ width: displayW + 'px', height: displayH + 'px' }"
								@pointermove="onMove"
								@pointerup="endDrag"
								@pointerleave="endDrag"
							>
								<img v-if="current.svg_url" :src="current.svg_url" class="stage-bg" alt="" draggable="false" />
								<div
									v-for="(s, i) in slots"
									:key="i"
									class="slot-box"
									:class="[s.type, { selected: selectedSlot === i, point: !hasBox(s) }]"
									:style="boxStyle(s)"
									@pointerdown.stop="startDrag($event, i, 'move')"
								>
									<span class="slot-tag">{{ s.key }}</span>
									<span
										v-if="hasBox(s)"
										class="resize-handle"
										@pointerdown.stop="startDrag($event, i, 'resize')"
									></span>
								</div>
							</div>
							<div class="stage-tools">
								<button class="link-btn" @click="addSlot('text')">+ Metin slotu</button>
								<button class="link-btn" @click="addSlot('image')">+ Görsel slotu</button>
								<span class="dirty-flag" v-if="dirty">● kaydedilmedi</span>
							</div>
						</div>

						<!-- Özellik paneli -->
						<div class="props">
							<div v-if="selectedSlot === null" class="props-empty">
								Düzenlemek için bir slot seçin veya yeni slot ekleyin.
							</div>
							<template v-else>
								<div class="props-head">
									<h4>Slot: {{ active.type === 'text' ? 'Metin' : 'Görsel' }}</h4>
									<button class="row-del" @click="removeSlot(selectedSlot)">✕</button>
								</div>
								<div class="field"><label>Anahtar (data-slot)</label>
									<input v-model="active.key" type="text" placeholder="product_image" /></div>
								<div class="grid2">
									<div class="field"><label>X</label><input v-model.number="active.x" type="number" /></div>
									<div class="field"><label>Y</label><input v-model.number="active.y" type="number" /></div>
								</div>
								<template v-if="active.type === 'image'">
									<div class="grid2">
										<div class="field"><label>Genişlik</label><input v-model.number="active.w" type="number" /></div>
										<div class="field"><label>Yükseklik</label><input v-model.number="active.h" type="number" /></div>
									</div>
									<div class="field"><label>Sığdırma</label>
										<select v-model="active.fit"><option value="cover">cover (kırp)</option><option value="contain">contain (sığdır)</option></select>
									</div>
								</template>
								<template v-else>
									<div class="grid2">
										<div class="field"><label>Font boyutu</label><input v-model.number="active.font_size" type="number" /></div>
										<div class="field"><label>Hizalama</label>
											<select v-model="active.align"><option value="left">sol</option><option value="center">orta</option><option value="right">sağ</option></select>
										</div>
									</div>
									<div class="field"><label>Renk (#hex veya token:primary)</label>
										<input v-model="active.fill" type="text" placeholder="token:text" /></div>
									<label class="bold-toggle"><input v-model="active.bold" type="checkbox" /> Kalın (bold)</label>
								</template>
							</template>
						</div>
					</div>

					<div class="designer-footer">
						<span class="hint">Slotlar SVG'ye yazılır ve şablon yeniden incelenir.</span>
						<button class="btn btn-primary" :disabled="saving" @click="saveSlots">
							{{ saving ? 'Kaydediliyor…' : 'Slotları Kaydet' }}
						</button>
					</div>
				</div>
			</section>

			<section v-else class="tpl-designer">
				<div class="card"><div class="card-body"><div class="empty-block">Düzenlemek için soldan bir şablon seçin.</div></div></div>
			</section>
		</div>
	</div>
</template>

<script setup>
import { ref, reactive, computed, watch, inject } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Breadcrumb from '@/Components/Breadcrumb.vue'
import CreativeNav from '../Components/CreativeNav.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
	templates: { type: Array, default: () => [] },
})

const showToast = inject('showToast', null)
const $swal = inject('$swal')
const uploading = ref(false)
const saving = ref(false)
const selectedId = ref(null)
const slots = reactive([])
const selectedSlot = ref(null)
const dirty = ref(false)
const nameDraft = ref('')
const activeDraft = ref(true)
const stageEl = ref(null)

const MAX_W = 560

const current = computed(() => props.templates.find(t => t.id === selectedId.value) || null)
const active = computed(() => (selectedSlot.value !== null ? slots[selectedSlot.value] : null))
const displayW = computed(() => Math.min(MAX_W, current.value?.width || MAX_W))
const scale = computed(() => (current.value?.width ? displayW.value / current.value.width : 1))
const displayH = computed(() => Math.round((current.value?.height || 0) * scale.value))

function select(t) {
	selectedId.value = t.id
	nameDraft.value = t.name
	activeDraft.value = !!t.is_active
	selectedSlot.value = null
	dirty.value = false
	loadSlots(t.slots || [])
}

function loadSlots(raw) {
	slots.splice(0, slots.length)
	for (const s of raw) {
		slots.push({
			ref: s.key,
			key: s.key,
			type: s.type === 'text' ? 'text' : 'image',
			x: num(s.x), y: num(s.y),
			w: num(s.w), h: num(s.h),
			fit: s.fit || 'cover',
			font_size: num(s.font_size) || 16,
			bold: !!s.bold,
			align: s.align || 'left',
			fill: s.fill || '#000000',
		})
	}
}

function num(v) { const n = Number(v); return Number.isFinite(n) ? n : 0 }
function hasBox(s) { return s.type === 'image' || (s.w > 0 && s.h > 0) }

function boxStyle(s) {
	const sc = scale.value
	const base = { left: s.x * sc + 'px', top: s.y * sc + 'px' }
	if (hasBox(s)) {
		return { ...base, width: Math.max(8, s.w * sc) + 'px', height: Math.max(8, s.h * sc) + 'px' }
	}
	// Metin slotu (kutusuz): yaklaşık font yüksekliğinde işaretçi; üst-sol köşeyi y'ye hizala.
	const h = Math.max(14, s.font_size * sc)
	return { ...base, top: (s.y * sc - h) + 'px', height: h + 'px' }
}

// ── Sürükleme ───────────────────────────────────────────────────────────
let drag = null

function startDrag(e, i, mode) {
	selectedSlot.value = i
	const s = slots[i]
	drag = { i, mode, sx: e.clientX, sy: e.clientY, ox: s.x, oy: s.y, ow: s.w, oh: s.h }
	e.target.setPointerCapture?.(e.pointerId)
}

function onMove(e) {
	if (!drag) return
	const sc = scale.value || 1
	const dx = (e.clientX - drag.sx) / sc
	const dy = (e.clientY - drag.sy) / sc
	const s = slots[drag.i]
	if (drag.mode === 'move') {
		s.x = Math.round(Math.max(0, drag.ox + dx))
		s.y = Math.round(Math.max(0, drag.oy + dy))
	} else {
		s.w = Math.round(Math.max(8, drag.ow + dx))
		s.h = Math.round(Math.max(8, drag.oh + dy))
	}
	dirty.value = true
}

function endDrag() { drag = null }

// ── Slot ekle/sil ───────────────────────────────────────────────────────
function addSlot(type) {
	const base = {
		ref: null, key: type === 'text' ? 'text_' + slots.length : 'image_' + slots.length,
		type, x: 40, y: 40, w: type === 'image' ? 300 : 0, h: type === 'image' ? 300 : 0,
		fit: 'cover', font_size: 32, bold: false, align: 'left', fill: type === 'text' ? 'token:text' : '#000000',
	}
	slots.push(base)
	selectedSlot.value = slots.length - 1
	dirty.value = true
}

function removeSlot(i) {
	slots.splice(i, 1)
	selectedSlot.value = null
	dirty.value = true
}

// ── Kaydetme ────────────────────────────────────────────────────────────
function saveSlots() {
	if (saving.value || !current.value) return
	saving.value = true
	const payload = slots.map(s => ({
		ref: s.ref, key: s.key, type: s.type,
		x: s.x, y: s.y,
		w: s.type === 'image' ? s.w : null, h: s.type === 'image' ? s.h : null,
		fit: s.type === 'image' ? s.fit : null,
		font_size: s.type === 'text' ? s.font_size : null,
		bold: s.type === 'text' ? s.bold : null,
		align: s.type === 'text' ? s.align : null,
		fill: s.fill || null,
	}))
	router.put(`/creative/templates/${current.value.id}/slots`, { slots: payload }, {
		preserveScroll: true,
		preserveState: false,
		onSuccess: () => { dirty.value = false },
		onError: (errs) => showToast?.({ type: 'error', title: 'Kaydedilemedi', message: Object.values(errs)[0] || 'Hata' }),
		onFinish: () => { saving.value = false },
	})
}

function saveMeta() {
	if (!current.value) return
	if (nameDraft.value === current.value.name && activeDraft.value === !!current.value.is_active) return
	router.put(`/creative/templates/${current.value.id}`, {
		name: nameDraft.value, is_active: activeDraft.value,
	}, { preserveScroll: true, preserveState: false })
}

function uploadTemplate(e) {
	const file = e.target.files?.[0]
	if (!file) return
	uploading.value = true
	const fd = new FormData()
	fd.append('svg', file)
	fd.append('name', file.name.replace(/\.svg$/i, ''))
	router.post('/creative/templates', fd, {
		preserveScroll: true,
		preserveState: false,
		onError: (errs) => showToast?.({ type: 'error', title: 'Yüklenemedi', message: Object.values(errs)[0] || 'Hata' }),
		onFinish: () => { uploading.value = false; e.target.value = '' },
	})
}

async function removeTemplate() {
	if (!current.value) return
	const ok = await $swal.dangerConfirm({ title: 'Şablon silinsin mi?', html: `<b>${current.value.name}</b> kalıcı olarak silinecek.` })
	if (!ok) return
	router.delete(`/creative/templates/${current.value.id}`, {
		preserveScroll: true,
		preserveState: false,
		onSuccess: () => { selectedId.value = null },
	})
}

// Props (Inertia reload sonrası) değişince seçili şablonun slotlarını tazele.
watch(() => props.templates, () => {
	if (selectedId.value && current.value) {
		nameDraft.value = current.value.name
		activeDraft.value = !!current.value.is_active
		if (!dirty.value) loadSlots(current.value.slots || [])
	}
})
</script>

<style scoped>
.page-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 20px; gap: 16px; }
.page-title { font-size: 22px; font-weight: 700; color: #1a1a2e; line-height: 1.2; }
.page-subtitle { font-size: 13px; color: #888; margin-top: 4px; max-width: 560px; }
.btn.disabled { opacity: .6; pointer-events: none; }

.tpl-layout { display: grid; grid-template-columns: 240px 1fr; gap: 18px; align-items: start; }
.tpl-list { display: flex; flex-direction: column; gap: 8px; }
.tpl-list-item { position: relative; display: flex; gap: 10px; text-align: left; background: #fff; border: 2px solid #ebebf0; border-radius: 12px; padding: 9px; cursor: pointer; font-family: inherit; transition: border-color .15s; }
.tpl-list-item:hover { border-color: rgb(var(--color-primary) / .35); }
.tpl-list-item.active { border-color: rgb(var(--color-primary)); box-shadow: 0 0 0 3px rgb(var(--color-primary) / .1); }
.tpl-list-item.inactive { opacity: .6; }
.tpl-thumb { width: 46px; height: 46px; border-radius: 8px; background: #f5f5f8; flex-shrink: 0; overflow: hidden; display: flex; align-items: center; justify-content: center; }
.tpl-thumb img { width: 100%; height: 100%; object-fit: cover; }
.no-preview { color: #ccc; font-weight: 700; }
.tpl-list-meta { display: flex; flex-direction: column; gap: 3px; min-width: 0; }
.tpl-list-name { font-size: 13px; font-weight: 600; color: #1a1a2e; }
.tpl-list-dim { font-size: 11px; color: #999; font-family: 'SF Mono', Menlo, Consolas, monospace; }
.tpl-off { position: absolute; top: 7px; right: 8px; font-size: 9.5px; font-weight: 700; background: #fee2e2; color: #b91c1c; padding: 1px 5px; border-radius: 4px; text-transform: uppercase; }

.card { background: #fff; border-radius: 16px; border: 1px solid #ebebf0; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,.04); }
.card-header { padding: 12px 16px; display: flex; align-items: center; justify-content: space-between; gap: 12px; border-bottom: 1px solid #f0f0f5; }
.name-input { font-size: 15px; font-weight: 700; color: #1a1a2e; border: 1px solid transparent; border-radius: 7px; padding: 4px 8px; font-family: inherit; flex: 1; }
.name-input:hover { border-color: #ebebf0; }
.name-input:focus { outline: none; border-color: rgb(var(--color-primary) / .35); background: rgb(var(--color-primary-soft)); }
.header-actions { display: flex; align-items: center; gap: 14px; flex-shrink: 0; }
.active-toggle { display: flex; align-items: center; gap: 6px; font-size: 12px; color: #888; cursor: pointer; }
.active-toggle.on { color: rgb(var(--color-primary-hover)); }
.active-toggle input { display: none; }
.at-dot { width: 28px; height: 16px; border-radius: 9px; background: rgb(var(--color-primary) / .35); position: relative; transition: background .15s; }
.at-dot::after { content: ''; position: absolute; top: 2px; left: 2px; width: 12px; height: 12px; border-radius: 50%; background: #fff; transition: transform .15s; }
.active-toggle.on .at-dot { background: rgb(var(--color-primary)); }
.active-toggle.on .at-dot::after { transform: translateX(12px); }
.link-btn { background: none; border: none; color: rgb(var(--color-primary)); font-size: 12px; font-weight: 600; cursor: pointer; font-family: inherit; }
.link-btn.danger { color: #dc2626; }

.designer-body { display: grid; grid-template-columns: 1fr 240px; gap: 18px; padding: 18px; }
.empty-block { text-align: center; color: #aaa; padding: 28px 0; font-style: italic; font-size: 13px; }

.stage-wrap { min-width: 0; overflow-x: auto; -webkit-overflow-scrolling: touch; }
.stage { position: relative; background: #f5f5f8 repeating-conic-gradient(#eee 0% 25%, #f8f8fb 0% 50%) 0 / 20px 20px; border: 1px solid #e8e8f0; border-radius: 10px; overflow: hidden; touch-action: none; user-select: none; }
.stage-bg { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: contain; pointer-events: none; }
.slot-box { position: absolute; border: 2px solid rgb(var(--color-primary) / .7); background: rgb(var(--color-primary) / .08); cursor: move; box-sizing: border-box; }
.slot-box.text { border-style: dashed; border-color: rgba(37,99,235,.8); background: rgba(37,99,235,.1); display: flex; align-items: center; }
.slot-box.point { padding: 0 4px; }
.slot-box.selected { border-color: rgb(var(--color-primary)); background: rgb(var(--color-primary) / .18); box-shadow: 0 0 0 2px rgb(var(--color-primary) / .25); z-index: 2; }
.slot-tag { position: absolute; top: -16px; left: -2px; font-size: 10px; font-weight: 700; color: #fff; background: rgb(var(--color-primary)); padding: 1px 5px; border-radius: 4px 4px 4px 0; white-space: nowrap; }
.slot-box.text .slot-tag { position: static; background: #2563eb; border-radius: 4px; }
.resize-handle { position: absolute; right: -5px; bottom: -5px; width: 12px; height: 12px; background: rgb(var(--color-primary)); border: 2px solid #fff; border-radius: 50%; cursor: nwse-resize; }

.stage-tools { display: flex; align-items: center; gap: 14px; margin-top: 10px; }
.dirty-flag { font-size: 11.5px; color: #d97706; font-weight: 600; margin-left: auto; }

.props { border-left: 1px solid #f0f0f5; padding-left: 18px; }
.props-empty { font-size: 12.5px; color: #aaa; font-style: italic; padding-top: 8px; }
.props-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; }
.props-head h4 { font-size: 13px; font-weight: 700; color: #1a1a2e; }
.field { margin-bottom: 11px; }
.field label { display: block; font-size: 11.5px; font-weight: 600; color: #666; margin-bottom: 4px; }
.field input, .field select { width: 100%; border: 1px solid #e8e8f0; border-radius: 7px; padding: 7px 9px; font-size: 12.5px; font-family: inherit; color: #1a1a2e; background: #fff; }
.field input:focus, .field select:focus { outline: none; border-color: rgb(var(--color-primary) / .35); background: rgb(var(--color-primary-soft)); }
.grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
.bold-toggle { display: flex; align-items: center; gap: 6px; font-size: 12.5px; color: #555; cursor: pointer; }
.row-del { width: 26px; height: 26px; border: none; background: #f5f5f8; color: #999; border-radius: 6px; cursor: pointer; }
.row-del:hover { background: #fee2e2; color: #dc2626; }

.designer-footer { display: flex; align-items: center; justify-content: space-between; gap: 12px; border-top: 1px solid #f0f0f5; padding: 14px 18px; }
.hint { font-size: 11.5px; color: #aaa; }

@media (max-width: 900px) {
	.tpl-layout { grid-template-columns: 1fr; }
	.designer-body { grid-template-columns: 1fr; }
	.props { border-left: none; padding-left: 0; border-top: 1px solid #f0f0f5; padding-top: 16px; }
}
@media (max-width: 640px) {
	.page-header { flex-wrap: wrap; }
	.page-header .btn { width: 100%; justify-content: center; }
	.card-header { flex-wrap: wrap; row-gap: 8px; }
	.header-actions { width: 100%; justify-content: space-between; }
	.designer-footer { flex-wrap: wrap; }
	.designer-footer .btn { width: 100%; justify-content: center; }
}
@media (max-width: 480px) {
	.grid2 { grid-template-columns: 1fr; }
}
</style>
