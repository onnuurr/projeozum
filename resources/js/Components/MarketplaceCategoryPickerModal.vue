<script setup>
import { ref, computed, watch } from 'vue'
import { OzModal, OzSelect, OzField, OzAlert, OzButton, OzIcon } from '@/Components/Oz'

const props = defineProps({
	category: { type: Object, default: null },
	marketplace: { type: Object, default: null },
	tree: { type: Array, default: () => [] },
	initialPath: { type: String, default: '' },
})

const open = defineModel('open', { type: Boolean, default: false })
const emit = defineEmits(['submit'])

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

watch(open, (isOpen) => {
	if (isOpen) {
		reset()
		rehydrateFromPath(props.initialPath)
	}
})
</script>

<template>
	<OzModal v-model:open="open" size="md" :subtitle="stage === 'picking' ? 'Pazaryeri kategorisini kademeli olarak seç' : 'Eşleştirmeyi gözden geçir ve kaydet'">
		<template v-if="category && marketplace" #header>
			<div v-if="marketplace" class="mp-logo" :style="{ background: marketplace.color, color: '#fff' }">{{ marketplace.logoText }}</div>
			<div class="titles">
				<h3>{{ category.name }} → {{ marketplace.name }}</h3>
				<p>{{ stage === 'picking' ? 'Pazaryeri kategorisini kademeli olarak seç' : 'Eşleştirmeyi gözden geçir ve kaydet' }}</p>
			</div>
		</template>

		<template v-if="category && marketplace">
			<!-- ─────────── Stage 1: Kademeli select'ler ─────────── -->
			<div v-if="stage === 'picking'" class="picker-stage">
				<OzField v-for="(level, idx) in levels" :key="idx" :label="`${ordinalLabel(idx)} Kategori`" class="picker-level">
					<OzSelect
						:model-value="selection[idx]"
						:options="level.options"
						:placeholder="idx === 0 ? 'Üst kategori seçiniz…' : 'Alt kategori seçiniz…'"
						@update:model-value="onPick(idx, $event)"
					/>
				</OzField>

				<!-- Yol önizleme (canlı) -->
				<div v-if="pathParts.length" class="path-preview">
					<span class="pp-label">Seçili Yol</span>
					<div class="pp-chips">
						<template v-for="(p, i) in pathParts" :key="i">
							<span class="pp-chip">{{ p }}</span>
							<OzIcon v-if="i < pathParts.length - 1" name="chevron-right" :size="11" :stroke-width="2.5" class="pp-sep" />
						</template>
					</div>
				</div>

				<OzAlert v-if="!isLeaf && pathParts.length" tone="warning">En alt kategoriye ulaşana kadar seçime devam edin.</OzAlert>
			</div>

			<!-- ─────────── Stage 2: Textbox önizleme ─────────── -->
			<div v-else class="preview-stage">
				<label class="preview-label">Pazaryeri Kategori Yolu</label>
				<input class="preview-input mono" type="text" readonly :value="fullPath" aria-label="Seçilen kategori yolu">
				<div class="preview-meta">
					<div class="pm-row"><span class="pm-key">Pazaryeri</span><span class="pm-val">{{ marketplace.name }}</span></div>
					<div class="pm-row"><span class="pm-key">Kendi kategorin</span><span class="pm-val">{{ category.name }}</span></div>
					<div class="pm-row"><span class="pm-key">Derinlik</span><span class="pm-val">{{ pathParts.length }} seviye</span></div>
				</div>
			</div>
		</template>

		<template v-if="category && marketplace" #footer>
			<template v-if="stage === 'picking'">
				<OzButton @click="open = false">Vazgeç</OzButton>
				<OzButton variant="primary" icon="check" :disabled="!isLeaf" @click="confirmPick">Eşleştir</OzButton>
			</template>
			<template v-else>
				<OzButton icon="chevron-left" @click="backToPicker">Geri Dön</OzButton>
				<OzButton variant="primary" icon="save" @click="save">Kaydet</OzButton>
			</template>
		</template>
	</OzModal>
</template>

<style scoped>
.mp-logo { width: 36px; height: 36px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 800; letter-spacing: 0.04em; flex-shrink: 0; }
.titles { flex: 1; min-width: 0; }
.titles h3 { margin: 0; font-size: 15px; font-weight: 700; color: var(--oz-ink-soft); letter-spacing: -.01em; }
.titles p { margin: 3px 0 0; font-size: 12px; color: var(--oz-muted); }

.mono { font-family: ui-monospace, monospace; }

/* ── Stage 1 ── */
.picker-stage { display: flex; flex-direction: column; gap: 12px; }
.picker-level { animation: slideDown .25s ease; }
@keyframes slideDown { from { opacity: 0; transform: translateY(-6px); } to { opacity: 1; transform: translateY(0); } }

.path-preview { margin-top: 4px; padding: 12px 14px; background: var(--oz-surface-2); border: 1px solid var(--oz-border); border-radius: 10px; display: flex; flex-direction: column; gap: 8px; }
.pp-label { font-size: 10.5px; font-weight: 700; color: var(--oz-muted); text-transform: uppercase; letter-spacing: 0.06em; }
.pp-chips { display: flex; align-items: center; flex-wrap: wrap; gap: 4px; }
.pp-chip { display: inline-flex; align-items: center; padding: 4px 10px; background: var(--oz-surface); border: 1px solid var(--oz-border); border-radius: 7px; font-size: 12px; font-weight: 600; color: var(--oz-ink-soft); }
.pp-sep { color: var(--oz-muted-2, var(--oz-muted)); flex-shrink: 0; }

/* ── Stage 2 ── */
.preview-stage { display: flex; flex-direction: column; gap: 14px; animation: fadeIn .25s ease; }
@keyframes fadeIn { from { opacity: 0; transform: scale(.98); } to { opacity: 1; transform: scale(1); } }

.preview-label { font-size: 11.5px; font-weight: 600; color: var(--oz-muted); }
.preview-input {
	width: 100%; height: 44px; padding: 0 14px;
	border: 1.5px solid var(--oz-accent); border-radius: 10px;
	background: var(--oz-accent-soft); color: var(--oz-ink-soft);
	font-size: 13.5px; font-weight: 600; letter-spacing: 0.01em;
	outline: none; box-shadow: 0 0 0 3px rgba(var(--oz-accent-rgb), .1);
	cursor: text; user-select: all;
}
.preview-meta { display: flex; flex-direction: column; gap: 6px; padding: 12px 14px; background: var(--oz-surface-2); border: 1px solid var(--oz-border); border-radius: 10px; }
.pm-row { display: flex; justify-content: space-between; gap: 12px; font-size: 12.5px; }
.pm-key { color: var(--oz-muted); font-weight: 600; }
.pm-val { color: var(--oz-ink-soft); font-weight: 700; text-align: right; }
</style>
