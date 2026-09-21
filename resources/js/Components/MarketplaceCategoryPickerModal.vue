<template>
	<AppModal
		:model-value="modelValue"
		:title="category && marketplace ? `${category.name} → ${marketplace.name}` : ''"
		:subtitle="stage === 'picking' ? 'Pazaryeri kategorisini kademeli olarak seç' : 'Eşleştirmeyi gözden geçir ve kaydet'"
		size="md"
		@update:model-value="$emit('update:modelValue', $event)"
	>
		<template #icon>
			<div
				v-if="marketplace"
				class="mp-logo"
				:style="{ background: marketplace.color, color: '#fff' }"
			>
				{{ marketplace.logoText }}
			</div>
		</template>

		<template v-if="category && marketplace">
			<!-- ─────────── Stage 1: Kademeli select'ler ─────────── -->
						<div v-if="stage === 'picking'" class="picker-stage">
							<div
								v-for="(level, idx) in levels"
								:key="idx"
								class="picker-level"
							>
								<CustomSelect
									:label="`${ordinalLabel(idx)} Kategori`"
									:model-value="selection[idx]"
									:options="level.options"
									:placeholder="idx === 0 ? 'Üst kategori seçiniz...' : 'Alt kategori seçiniz...'"
									@update:model-value="onPick(idx, $event)"
								/>
							</div>

							<!-- Yol önizleme (canlı) -->
							<div v-if="pathParts.length" class="path-preview">
								<span class="pp-label">Seçili Yol</span>
								<div class="pp-chips">
									<template v-for="(p, i) in pathParts" :key="i">
										<span class="pp-chip">{{ p }}</span>
										<svg
											v-if="i < pathParts.length - 1"
											class="pp-sep"
											width="11" height="11" fill="none" stroke="currentColor"
											stroke-width="2.5" viewBox="0 0 24 24"
										>
											<polyline points="9 18 15 12 9 6" />
										</svg>
									</template>
								</div>
							</div>

							<div v-if="!isLeaf && pathParts.length" class="hint-banner">
								<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
									<circle cx="12" cy="12" r="10" />
									<line x1="12" y1="8" x2="12" y2="12" />
									<line x1="12" y1="16" x2="12.01" y2="16" />
								</svg>
								En alt kategoriye ulaşana kadar seçime devam edin.
							</div>
						</div>

						<!-- ─────────── Stage 2: Textbox önizleme ─────────── -->
						<div v-else class="preview-stage">
							<label class="preview-label">Pazaryeri Kategori Yolu</label>
							<input
								class="preview-input"
								type="text"
								readonly
								:value="fullPath"
								aria-label="Seçilen kategori yolu"
							/>
							<div class="preview-meta">
								<div class="pm-row">
									<span class="pm-key">Pazaryeri</span>
									<span class="pm-val">{{ marketplace.name }}</span>
								</div>
								<div class="pm-row">
									<span class="pm-key">Kendi kategorin</span>
									<span class="pm-val">{{ category.name }}</span>
								</div>
								<div class="pm-row">
									<span class="pm-key">Derinlik</span>
									<span class="pm-val">{{ pathParts.length }} seviye</span>
								</div>
							</div>
			</div>
		</template>

		<template v-if="category && marketplace" #footer>
			<template v-if="stage === 'picking'">
				<Button variant="ghost" @click="close">Vazgeç</Button>
				<Button variant="primary" with-icon :disabled="!isLeaf" @click="confirmPick">
					<template #leading><Check :size="13" /></template>
					Eşleştir
				</Button>
			</template>
			<template v-else>
				<Button variant="ghost" with-icon @click="backToPicker">
					<template #leading><ChevronLeft :size="13" /></template>
					Geri Dön
				</Button>
				<Button variant="primary" with-icon @click="save">
					<template #leading><Save :size="13" /></template>
					Kaydet
				</Button>
			</template>
		</template>
	</AppModal>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { Check, ChevronLeft, Save } from 'lucide-vue-next'
import CustomSelect from './CustomSelect.vue'
import Button from './Button.vue'
import AppModal from './AppModal.vue'

const props = defineProps({
	modelValue: { type: Boolean, default: false },
	category: { type: Object, default: null },
	marketplace: { type: Object, default: null },
	tree: { type: Array, default: () => [] },
	initialPath: { type: String, default: '' },
})

const emit = defineEmits(['update:modelValue', 'submit'])

const stage = ref('picking') // 'picking' | 'preview'
const selection = ref([])    // her seviyedeki seçili node.id
const pickedNodes = ref([])  // seçili node referansları (her seviye için)

const ordinals = ['Ana', '2.', '3.', '4.', '5.', '6.']
function ordinalLabel(i) {
	return ordinals[i] || `${i + 1}.`
}

/** Görünür seviyeleri (selectbox'ları) üret. */
const levels = computed(() => {
	const out = []
	// İlk seviye: kök
	out.push({
		options: (props.tree || []).map((n) => ({ value: n.id, label: n.name })),
	})
	// Sonraki seviyeler: önceki seçimin children'ından beslen
	for (let i = 0; i < pickedNodes.value.length; i++) {
		const node = pickedNodes.value[i]
		if (!node?.children?.length) break
		out.push({
			options: node.children.map((n) => ({ value: n.id, label: n.name })),
		})
	}
	return out
})

const pathParts = computed(() => pickedNodes.value.map((n) => n.name))
const fullPath = computed(() => pathParts.value.join(' > '))

/** Son seçim bir yaprak mı (children boş veya yok)? */
const isLeaf = computed(() => {
	if (!pickedNodes.value.length) return false
	const last = pickedNodes.value[pickedNodes.value.length - 1]
	return !last?.children?.length
})

function onPick(levelIdx, value) {
	// Bu seviyedeki seçime göre node'u bul; sonraki seviyeleri sıfırla
	const opts = levels.value[levelIdx]?.options || []
	const opt = opts.find((o) => o.value === value)
	if (!opt) return

	// Hangi node listesinden seçildiğini bul
	const parentChildren =
		levelIdx === 0
			? props.tree
			: (pickedNodes.value[levelIdx - 1]?.children || [])
	const node = parentChildren.find((n) => n.id === value)
	if (!node) return

	// Seçim ve sonrasını sıfırla
	selection.value = [...selection.value.slice(0, levelIdx), value]
	pickedNodes.value = [...pickedNodes.value.slice(0, levelIdx), node]
}

function confirmPick() {
	if (!isLeaf.value) return
	stage.value = 'preview'
}

function backToPicker() {
	stage.value = 'picking'
}

function close() {
	emit('update:modelValue', false)
}

function save() {
	emit('submit', {
		categoryId: props.category?.id ?? null,
		marketplaceKey: props.marketplace?.key ?? null,
		path: fullPath.value,
		parts: [...pathParts.value],
		leafId: pickedNodes.value[pickedNodes.value.length - 1]?.id ?? null,
	})
}

function reset() {
	stage.value = 'picking'
	selection.value = []
	pickedNodes.value = []
}

/** initialPath verilmişse, ağacı isimle eşleştirerek seçimleri rehydrate et. */
function rehydrateFromPath(path) {
	if (!path) return
	const wantedNames = path.split('>').map((s) => s.trim()).filter(Boolean)
	const newSelection = []
	const newPicked = []
	let currentList = props.tree || []
	for (const name of wantedNames) {
		const node = currentList.find((n) => n.name === name)
		if (!node) break
		newSelection.push(node.id)
		newPicked.push(node)
		currentList = node.children || []
	}
	selection.value = newSelection
	pickedNodes.value = newPicked
}

watch(
	() => props.modelValue,
	(open) => {
		if (open) {
			reset()
			rehydrateFromPath(props.initialPath)
		}
	}
)
</script>

<style scoped>
.mp-logo {
	width: 100%;
	height: 100%;
	border-radius: 10px;
	display: flex;
	align-items: center;
	justify-content: center;
	font-size: 11px;
	font-weight: 800;
	letter-spacing: 0.04em;
}

/* ── Stage 1 ── */
.picker-stage {
	display: flex;
	flex-direction: column;
	gap: 12px;
}

.picker-level {
	animation: slideDown .25s ease;
}

@keyframes slideDown {
	from { opacity: 0; transform: translateY(-6px); }
	to   { opacity: 1; transform: translateY(0); }
}

/* CustomSelect içinden gelen .form-group / .form-label tipografisini eşitle */
.picker-stage :deep(.form-label) {
	font-size: 11.5px;
	font-weight: 600;
	color: #555;
	margin-bottom: 5px;
	display: inline-block;
}

.path-preview {
	margin-top: 4px;
	padding: 12px 14px;
	background: #fafafe;
	border: 1px solid #f0f0f5;
	border-radius: 10px;
	display: flex;
	flex-direction: column;
	gap: 8px;
}

.pp-label {
	font-size: 10.5px;
	font-weight: 700;
	color: #888;
	text-transform: uppercase;
	letter-spacing: 0.06em;
}

.pp-chips {
	display: flex;
	align-items: center;
	flex-wrap: wrap;
	gap: 4px;
}

.pp-chip {
	display: inline-flex;
	align-items: center;
	padding: 4px 10px;
	background: #fff;
	border: 1px solid #e8e8f0;
	border-radius: 7px;
	font-size: 12px;
	font-weight: 600;
	color: #1a1a2e;
}

.pp-sep {
	color: #bbb;
	flex-shrink: 0;
}

.hint-banner {
	display: flex;
	align-items: center;
	gap: 8px;
	padding: 9px 12px;
	background: #fef3c7;
	border: 1px solid #fde68a;
	border-radius: 9px;
	font-size: 12px;
	color: #92400e;
}
.hint-banner svg { color: #d97706; flex-shrink: 0; }

/* ── Stage 2 ── */
.preview-stage {
	display: flex;
	flex-direction: column;
	gap: 14px;
	animation: fadeIn .25s ease;
}

@keyframes fadeIn {
	from { opacity: 0; transform: scale(.98); }
	to   { opacity: 1; transform: scale(1); }
}

.preview-label {
	font-size: 11.5px;
	font-weight: 600;
	color: #555;
}

.preview-input {
	width: 100%;
	height: 44px;
	padding: 0 14px;
	border: 1.5px solid rgb(var(--color-primary-rgb));
	border-radius: 10px;
	background: #f5f7ff;
	color: #1a1a2e;
	font-family: 'SF Mono', Menlo, Consolas, monospace;
	font-size: 13.5px;
	font-weight: 600;
	letter-spacing: 0.01em;
	outline: none;
	box-shadow: 0 0 0 3px rgb(var(--color-primary-rgb) / 0.1);
	cursor: text;
	user-select: all;
}

.preview-meta {
	display: flex;
	flex-direction: column;
	gap: 6px;
	padding: 12px 14px;
	background: #fafafe;
	border: 1px solid #f0f0f5;
	border-radius: 10px;
}

.pm-row {
	display: flex;
	justify-content: space-between;
	gap: 12px;
	font-size: 12.5px;
}
.pm-key {
	color: #888;
	font-weight: 600;
}
.pm-val {
	color: #1a1a2e;
	font-weight: 700;
	text-align: right;
}
</style>
