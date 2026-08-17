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

		<PageHeader title="Şablonlar" subtitle="SVG şablon yükleyin, slotları sürükleyerek konumlandırın. Değişiklikler SVG'ye işlenir.">
			<template #actions>
				<template v-if="can('creative.template.manage')">
					<Button variant="ghost" with-icon @click="openGenerateModal">
						<template #leading><Star :size="13" /></template>
						Marka Kitinden Üret
					</Button>
					<label class="btn btn-primary btn-with-icon" :class="{ disabled: uploading }">
						<Upload :size="13" />
						{{ uploading ? 'Yükleniyor…' : 'SVG Yükle' }}
						<input type="file" accept=".svg,image/svg+xml" hidden @change="uploadTemplate" />
					</label>
				</template>
			</template>
		</PageHeader>

		<AppModal v-model="genModalOpen" title="Marka Kitinden Şablon Üret" subtitle="Seçilen marka kiti ve formatlara göre otomatik SVG şablon(lar) oluşturulur." size="md">
			<div class="field">
				<label>Marka Kiti</label>
				<select v-model.number="genForm.brand_kit_id">
					<option v-for="k in brandKits" :key="k.id" :value="k.id">{{ k.name }}{{ k.is_default ? ' (varsayılan)' : '' }}</option>
				</select>
			</div>
			<div class="field">
				<label>Tasarım</label>
				<select v-model="genForm.preset">
					<option v-for="(label, key) in presets" :key="key" :value="key">{{ label }}</option>
				</select>
			</div>
			<div class="field">
				<label>Formatlar</label>
				<div class="format-chips">
					<label v-for="f in formats" :key="f.key" class="format-chip" :class="{ on: genForm.formats.includes(f.key) }">
						<input type="checkbox" :value="f.key" v-model="genForm.formats" />
						{{ f.label }}
					</label>
				</div>
			</div>
			<template #footer="{ close }">
				<Button variant="ghost" :disabled="generating" @click="close">İptal</Button>
				<Button
					variant="primary"
					:disabled="!genForm.brand_kit_id || !genForm.preset || genForm.formats.length === 0"
					:loading="generating"
					@click="submitGenerate"
				>
					Üret
				</Button>
			</template>
		</AppModal>

		<div class="tpl-layout">
			<!-- Şablon listesi -->
			<aside class="tpl-list">
				<EmptyState v-if="templates.length === 0" :icon="LayoutTemplate" title="Şablon yok." />
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
					<Badge v-if="!t.is_active" class="tpl-off" color="neutral" label="pasif" size="sm" />
					<Badge
						v-else-if="t.constraints && !t.constraints.passed"
						class="tpl-warn"
						color="warning"
						:icon="AlertTriangle"
						:label="String(t.constraints.violations.length)"
						:title="t.constraints.violations.join('\n')"
					/>
				</button>
			</aside>

			<!-- Tasarımcı -->
			<section v-if="current" class="tpl-designer">
				<div class="card">
					<div class="card-header">
						<input v-model="nameDraft" class="name-input" type="text" :readonly="!can('creative.template.manage')" @blur="saveMeta" />
						<div v-if="can('creative.template.manage')" class="header-actions">
							<label class="active-toggle" :class="{ on: activeDraft }">
								<input v-model="activeDraft" type="checkbox" @change="saveMeta" />
								<span class="at-dot"></span> Aktif
							</label>
							<button class="link-btn danger" @click="removeTemplate">Sil</button>
						</div>
					</div>

					<div v-if="current.constraints && !current.constraints.passed" class="constraint-warnings">
						<strong>Layout uyarıları:</strong>
						<ul>
							<li v-for="(v, i) in current.constraints.violations" :key="i">{{ v }}</li>
						</ul>
					</div>

					<div class="card-body designer-body">
						<!-- Sahne -->
						<div class="stage-wrap">
							<EmptyState v-if="!current.width" :icon="Ruler" title="Boyut çıkarılamadı." hint="SVG'de width/height veya viewBox olduğundan emin olun." />
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
									<button class="row-del" @click="removeSlot(selectedSlot)"><X :size="14" /></button>
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
									<div class="field">
										<label>Azami genişlik (AI metin kırpma için, opsiyonel)</label>
										<input v-model.number="active.w" type="number" min="0" placeholder="örn. 400" />
										<p class="field-hint">Boş/0 bırakılırsa kırpma kontrolü atlanır. Render'ı etkilemez.</p>
									</div>
								</template>
							</template>
						</div>
					</div>

					<div class="designer-footer">
						<span class="hint">Slotlar SVG'ye yazılır ve şablon yeniden incelenir.</span>
						<Button v-if="can('creative.template.manage')" variant="primary" :loading="saving" @click="saveSlots">Slotları Kaydet</Button>
					</div>
				</div>
			</section>

			<section v-else class="tpl-designer">
				<div class="card"><div class="card-body"><EmptyState :icon="LayoutTemplate" title="Düzenlemek için soldan bir şablon seçin." /></div></div>
			</section>
		</div>
	</div>
</template>

<script setup>
import { ref, reactive, computed, watch, inject } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import { Star, Upload, X, LayoutTemplate, AlertTriangle, Ruler } from 'lucide-vue-next'
import AppLayout from '@/Layouts/AppLayout.vue'
import Breadcrumb from '@/Components/Breadcrumb.vue'
import AppModal from '@/Components/AppModal.vue'
import PageHeader from '@/Components/PageHeader.vue'
import Button from '@/Components/Button.vue'
import Badge from '@/Components/Badge.vue'
import EmptyState from '@/Components/EmptyState.vue'
import CreativeNav from '../Components/CreativeNav.vue'
import { useCan } from '@/composables/useCan'

defineOptions({ layout: AppLayout })

const { can } = useCan()

const props = defineProps({
	templates: { type: Array, default: () => [] },
	brandKits: { type: Array, default: () => [] },
	presets: { type: Object, default: () => ({}) },
	formats: { type: Array, default: () => [] },
})

const showToast = inject('showToast', null)
const $swal = inject('$swal')
const uploading = ref(false)
const saving = ref(false)
const genModalOpen = ref(false)
const generating = ref(false)
const genForm = reactive({ brand_kit_id: null, preset: null, formats: [] })
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
		// Görsel slotu: gerçek kırpma kutusu genişliği. Metin slotu: opsiyonel AI
		// metin kırpma tavanı (CreativeCopyRuleEngine) — render'ı etkilemez.
		w: s.type === 'image' ? s.w : (s.w || null),
		h: s.type === 'image' ? s.h : null,
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

function openGenerateModal() {
	const defaultKit = props.brandKits.find(k => k.is_default) || props.brandKits[0] || null
	genForm.brand_kit_id = defaultKit?.id ?? null
	genForm.preset = Object.keys(props.presets)[0] ?? null
	genForm.formats = props.formats[0] ? [props.formats[0].key] : []
	genModalOpen.value = true
}

function submitGenerate() {
	if (generating.value || !genForm.brand_kit_id || !genForm.preset || genForm.formats.length === 0) return
	generating.value = true
	router.post('/creative/templates/generate', {
		brand_kit_id: genForm.brand_kit_id,
		preset: genForm.preset,
		formats: genForm.formats,
	}, {
		preserveScroll: true,
		preserveState: false,
		onSuccess: () => { genModalOpen.value = false },
		onError: (errs) => showToast?.({ type: 'error', title: 'Üretilemedi', message: Object.values(errs)[0] || 'Hata' }),
		onFinish: () => { generating.value = false },
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
.btn.disabled { opacity: .6; pointer-events: none; }

.format-chips { display: flex; flex-wrap: wrap; gap: 8px; }
.format-chip { display: flex; align-items: center; gap: 6px; font-size: 12.5px; font-weight: 600; color: var(--color-on-surface-variant); background: var(--color-surface-container-low); border: 1px solid var(--color-outline-variant); border-radius: 20px; padding: 6px 12px; cursor: pointer; transition: all .15s; }
.format-chip.on { color: var(--color-primary-hover); background: var(--color-primary-soft); border-color: color-mix(in srgb, var(--color-primary) 35%, transparent); }
.format-chip input { display: none; }

.tpl-layout { display: grid; grid-template-columns: 240px 1fr; gap: 18px; align-items: start; }
.tpl-list { display: flex; flex-direction: column; gap: 8px; }
.tpl-list-item { position: relative; display: flex; gap: 10px; text-align: left; background: var(--color-surface); border: 2px solid var(--color-outline-variant); border-radius: 12px; padding: 9px; cursor: pointer; font-family: inherit; transition: border-color .15s; }
.tpl-list-item:hover { border-color: color-mix(in srgb, var(--color-primary) 35%, transparent); }
.tpl-list-item.active { border-color: var(--color-primary); box-shadow: 0 0 0 3px color-mix(in srgb, var(--color-primary) 10%, transparent); }
.tpl-list-item.inactive { opacity: .6; }
.tpl-thumb { width: 46px; height: 46px; border-radius: 8px; background: var(--color-surface-container-low); flex-shrink: 0; overflow: hidden; display: flex; align-items: center; justify-content: center; }
.tpl-thumb img { width: 100%; height: 100%; object-fit: cover; }
.no-preview { color: var(--color-muted); font-weight: 700; }
.tpl-list-meta { display: flex; flex-direction: column; gap: 3px; min-width: 0; }
.tpl-list-name { font-size: 13px; font-weight: 600; color: var(--color-ink); }
.tpl-list-dim { font-size: 11px; color: var(--color-muted); font-family: 'SF Mono', Menlo, Consolas, monospace; }
.tpl-off, .tpl-warn { position: absolute; top: 7px; right: 8px; }

.constraint-warnings { margin: 0 16px 14px; padding: 10px 14px; background: color-mix(in srgb, var(--color-warning) 10%, transparent); border: 1px solid color-mix(in srgb, var(--color-warning) 35%, transparent); border-radius: 10px; font-size: 12px; color: var(--color-warning); }
.constraint-warnings strong { font-size: 11.5px; }
.constraint-warnings ul { margin: 4px 0 0; padding-left: 18px; }
.constraint-warnings li { margin-bottom: 2px; }

.card { background: var(--color-surface); border-radius: 16px; border: 1px solid var(--color-outline-variant); overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,.04); }
.card-header { padding: 12px 16px; display: flex; align-items: center; justify-content: space-between; gap: 12px; border-bottom: 1px solid var(--color-outline-variant); }
.name-input { font-size: 15px; font-weight: 700; color: var(--color-ink); border: 1px solid transparent; border-radius: 7px; padding: 4px 8px; font-family: inherit; flex: 1; background: transparent; }
.name-input:hover { border-color: var(--color-outline-variant); }
.name-input:focus { outline: none; border-color: color-mix(in srgb, var(--color-primary) 35%, transparent); background: var(--color-primary-soft); }
.header-actions { display: flex; align-items: center; gap: 14px; flex-shrink: 0; }
.active-toggle { display: flex; align-items: center; gap: 6px; font-size: 12px; color: var(--color-muted); cursor: pointer; }
.active-toggle.on { color: var(--color-primary-hover); }
.active-toggle input { display: none; }
.at-dot { width: 28px; height: 16px; border-radius: 9px; background: color-mix(in srgb, var(--color-primary) 35%, transparent); position: relative; transition: background .15s; }
.at-dot::after { content: ''; position: absolute; top: 2px; left: 2px; width: 12px; height: 12px; border-radius: 50%; background: var(--color-surface); transition: transform .15s; }
.active-toggle.on .at-dot { background: var(--color-primary); }
.active-toggle.on .at-dot::after { transform: translateX(12px); }
.link-btn { background: none; border: none; color: var(--color-primary); font-size: 12px; font-weight: 600; cursor: pointer; font-family: inherit; }
.link-btn.danger { color: var(--color-danger); }

.designer-body { display: grid; grid-template-columns: 1fr 240px; gap: 18px; padding: 18px; }

.stage-wrap { min-width: 0; overflow-x: auto; -webkit-overflow-scrolling: touch; }
.stage { position: relative; background: var(--color-surface-container-low) repeating-conic-gradient(var(--color-surface-container-high) 0% 25%, var(--color-surface-container-low) 0% 50%) 0 / 20px 20px; border: 1px solid var(--color-outline-variant); border-radius: 10px; overflow: hidden; touch-action: none; user-select: none; }
.stage-bg { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: contain; pointer-events: none; }
.slot-box { position: absolute; border: 2px solid color-mix(in srgb, var(--color-primary) 70%, transparent); background: color-mix(in srgb, var(--color-primary) 8%, transparent); cursor: move; box-sizing: border-box; }
.slot-box.text { border-style: dashed; border-color: color-mix(in srgb, var(--color-info) 80%, transparent); background: color-mix(in srgb, var(--color-info) 10%, transparent); display: flex; align-items: center; }
.slot-box.point { padding: 0 4px; }
.slot-box.selected { border-color: var(--color-primary); background: color-mix(in srgb, var(--color-primary) 18%, transparent); box-shadow: 0 0 0 2px color-mix(in srgb, var(--color-primary) 25%, transparent); z-index: 2; }
.slot-tag { position: absolute; top: -16px; left: -2px; font-size: 10px; font-weight: 700; color: var(--color-on-primary); background: var(--color-primary); padding: 1px 5px; border-radius: 4px 4px 4px 0; white-space: nowrap; }
.slot-box.text .slot-tag { position: static; background: var(--color-info); border-radius: 4px; }
.resize-handle { position: absolute; right: -5px; bottom: -5px; width: 12px; height: 12px; background: var(--color-primary); border: 2px solid var(--color-surface); border-radius: 50%; cursor: nwse-resize; }

.stage-tools { display: flex; align-items: center; gap: 14px; margin-top: 10px; }
.dirty-flag { font-size: 11.5px; color: var(--color-warning); font-weight: 600; margin-left: auto; }

.props { border-left: 1px solid var(--color-outline-variant); padding-left: 18px; }
.props-empty { font-size: 12.5px; color: var(--color-muted); font-style: italic; padding-top: 8px; }
.props-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; }
.props-head h4 { font-size: 13px; font-weight: 700; color: var(--color-ink); }
.field { margin-bottom: 11px; }
.field label { display: block; font-size: 11.5px; font-weight: 600; color: var(--color-on-surface-variant); margin-bottom: 4px; }
.field input, .field select { width: 100%; border: 1px solid var(--color-outline-variant); border-radius: 7px; padding: 7px 9px; font-size: 12.5px; font-family: inherit; color: var(--color-ink); background: var(--color-surface); }
.field input:focus, .field select:focus { outline: none; border-color: color-mix(in srgb, var(--color-primary) 35%, transparent); background: var(--color-primary-soft); }
.grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
.bold-toggle { display: flex; align-items: center; gap: 6px; font-size: 12.5px; color: var(--color-on-surface-variant); cursor: pointer; }
.row-del { display: flex; align-items: center; justify-content: center; width: 26px; height: 26px; border: none; background: var(--color-surface-container-low); color: var(--color-muted); border-radius: 6px; cursor: pointer; }
.row-del:hover { background: color-mix(in srgb, var(--color-danger) 12%, transparent); color: var(--color-danger); }

.designer-footer { display: flex; align-items: center; justify-content: space-between; gap: 12px; border-top: 1px solid var(--color-outline-variant); padding: 14px 18px; }
.hint { font-size: 11.5px; color: var(--color-muted); }
.field-hint { font-size: 11px; color: var(--color-muted); margin-top: 4px; }

@media (max-width: 900px) {
	.tpl-layout { grid-template-columns: 1fr; }
	.designer-body { grid-template-columns: 1fr; }
	.props { border-left: none; padding-left: 0; border-top: 1px solid var(--color-outline-variant); padding-top: 16px; }
}
@media (max-width: 640px) {
	.card-header { flex-wrap: wrap; row-gap: 8px; }
	.header-actions { width: 100%; justify-content: space-between; }
	.designer-footer { flex-wrap: wrap; }
	.designer-footer .btn { width: 100%; justify-content: center; }
}
@media (max-width: 480px) {
	.grid2 { grid-template-columns: 1fr; }
}
</style>
