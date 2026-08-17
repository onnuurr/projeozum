<template>
	<Head title="Creative Stüdyo" />
	<div class="page-studio">
		<Breadcrumb
			:items="[
				{ label: 'Ana Sayfa', to: '/workflow', icon: 'home' },
				{ label: 'Creative' },
				{ label: 'Stüdyo' },
			]"
		/>

		<CreativeNav current="studio" />

		<PageHeader title="Creative Stüdyo" subtitle="Bir şablon ve ürün seçip sosyal medya görseli üretin">
			<template #actions>
				<Button variant="ghost" with-icon @click="router.visit('/creative/gallery')">
					<template #leading><LayoutGrid :size="14" /></template>
					Galeri
				</Button>
			</template>
		</PageHeader>

		<!-- Adım 1: Şablon -->
		<Card title="1. Şablon Seç">
			<template #actions>
				<span class="hint">{{ templates.length }} aktif şablon</span>
			</template>

			<EmptyState v-if="templates.length === 0" :icon="LayoutTemplate" title="Aktif şablon yok." hint="Önce bir şablon ekleyin." />
			<div v-else class="template-grid">
				<button
					v-for="t in templates"
					:key="t.id"
					type="button"
					class="template-card"
					:class="{ selected: selectedTemplate === t.id }"
					@click="selectedTemplate = t.id"
				>
					<div class="template-preview">
						<img v-if="t.preview_url" :src="t.preview_url" :alt="t.name" />
						<span v-else class="no-preview">Önizleme yok</span>
					</div>
					<div class="template-meta">
						<span class="template-name">{{ t.name }}</span>
						<span class="template-dim">{{ t.width }}×{{ t.height }}</span>
					</div>
					<span v-if="selectedTemplate === t.id" class="check-badge"><Check :size="13" :stroke-width="3" /></span>
				</button>
			</div>
		</Card>

		<!-- Adım 2: Ürünler -->
		<Card title="2. Ürün Seç">
			<template #actions>
				<div class="card-search">
					<Search :size="13" />
					<input v-model="search" type="text" placeholder="Ürün ara..." />
				</div>
				<button type="button" class="link-btn" @click="toggleAll">
					{{ allFilteredSelected ? 'Seçimi kaldır' : 'Tümünü seç' }}
				</button>
			</template>

			<EmptyState v-if="products.length === 0" :icon="PackageSearch" title="Ürün bulunamadı." />
			<div v-else class="product-grid">
				<button
					v-for="p in filteredProducts"
					:key="p.id"
					type="button"
					class="product-card"
					:class="{ selected: selectedProducts.has(p.id) }"
					@click="toggleProduct(p.id)"
				>
					<div class="product-thumb">
						<img v-if="p.cover" :src="p.cover" :alt="p.name" />
						<span v-else class="no-preview">{{ p.name.charAt(0) }}</span>
					</div>
					<span class="product-name">{{ p.name }}</span>
					<span class="select-dot" :class="{ on: selectedProducts.has(p.id) }"></span>
				</button>
			</div>
		</Card>

		<!-- Aksiyon çubuğu -->
		<div class="action-bar">
			<div class="selection-summary">
				<strong>{{ selectedProducts.size }}</strong> ürün ×
				<strong>{{ selectedTemplate ? 1 : 0 }}</strong> şablon =
				<strong>{{ selectedTemplate ? selectedProducts.size : 0 }}</strong> görsel
			</div>
			<label class="format-select" title="Sosyal medya çıktı boyutu">
				<span class="fs-label">Format</span>
				<select v-model="selectedFormat">
					<option v-for="f in formats" :key="f.key" :value="f.key">{{ f.label }} · {{ f.width }}×{{ f.height }}</option>
				</select>
			</label>
			<label class="ai-toggle" :class="{ on: useAi }" title="Ham ürün fotoğrafı yerine AI ile kurgulanmış sahne + giydirme kullan">
				<input v-model="useAi" type="checkbox" />
				<span class="ai-dot"></span>
				<span class="ai-label"><Sparkles :size="12" /> AI sahne / giydirme</span>
			</label>
			<label
				class="ai-toggle"
				:class="{ on: useCopyAi }"
				:title="hasCopySlots ? 'Marka kriterlerine (BrandKit) uygun başlık/alt başlık/CTA metni üret' : 'Seçili şablonda headline/sub_headline/cta_button slotu yok — etkisiz'"
			>
				<input v-model="useCopyAi" type="checkbox" />
				<span class="ai-dot"></span>
				<span class="ai-label"><PenLine :size="12" /> AI metin (başlık/CTA)</span>
			</label>
			<span v-if="useCopyAi && !hasCopySlots" class="hint copy-hint">Bu şablonda metin slotu yok</span>
			<label
				v-if="props.ai_compose_available"
				class="ai-toggle"
				:class="{ on: useAiCompose }"
				title="SVG şablonu yerine fal.ai (Flux) ile tam post kompozisyonu üret — OCR ile doğrulanmış metin gerektirir"
			>
				<input v-model="useAiCompose" type="checkbox" />
				<span class="ai-dot"></span>
				<span class="ai-label"><ImageIcon :size="12" /> AI kompozisyon</span>
			</label>
			<label v-if="useAi" class="pose-select" title="AI mankeninin duruşu (poz planlaması)">
				<span class="fs-label">Poz</span>
				<select v-model="selectedPose">
					<option v-for="p in poseOptions" :key="p.label" :value="p.value">{{ p.label }}</option>
				</select>
			</label>
			<Button
				v-if="can('creative.generate')"
				variant="primary"
				with-icon
				:disabled="!canGenerate"
				:loading="busy"
				@click="generate"
			>
				<template #leading><Play :size="14" /></template>
				Görselleri Üret
			</Button>
		</div>
	</div>
</template>

<script setup>
import { ref, reactive, computed, inject } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import { LayoutGrid, LayoutTemplate, Search, PackageSearch, Check, Sparkles, PenLine, Image as ImageIcon, Play } from 'lucide-vue-next'
import AppLayout from '@/Layouts/AppLayout.vue'
import Breadcrumb from '@/Components/Breadcrumb.vue'
import PageHeader from '@/Components/PageHeader.vue'
import Card from '@/Components/Card.vue'
import Button from '@/Components/Button.vue'
import EmptyState from '@/Components/EmptyState.vue'
import CreativeNav from '../Components/CreativeNav.vue'
import { useCan } from '@/composables/useCan'

defineOptions({ layout: AppLayout })

const { can } = useCan()

const props = defineProps({
	templates: { type: Array, default: () => [] },
	products: { type: Array, default: () => [] },
	formats: { type: Array, default: () => [] },
	default_format: { type: String, default: null },
	ai_compose_available: { type: Boolean, default: false },
})

const showToast = inject('showToast', null)
usePage()

const selectedTemplate = ref(props.templates.length === 1 ? props.templates[0].id : null)
const selectedProducts = reactive(new Set())
const search = ref('')
const busy = ref(false)
const useAi = ref(false)
const useCopyAi = ref(false)
const useAiCompose = ref(false)
const selectedFormat = ref(props.default_format || props.formats[0]?.key || null)

const COPY_SLOT_KEYS = ['headline', 'sub_headline', 'cta_button']
const hasCopySlots = computed(() => {
	const t = props.templates.find(t => t.id === selectedTemplate.value)
	return Array.isArray(t?.slots) && t.slots.some(s => COPY_SLOT_KEYS.includes(s?.key))
})

// Poz planlaması: değer doğrudan AI prompt'una giden duruş yönergesidir.
// Boş değer = "Otomatik": backend ürün adına göre kürate poz seçer.
const poseOptions = [
	{ value: '', label: 'Otomatik (ürüne göre)' },
	{ value: 'a relaxed three-quarter standing pose, weight on one leg and shoulders angled slightly to camera', label: 'Üç-çeyrek duruş' },
	{ value: 'a confident frontal stance with chin level and arms resting naturally so the product stays fully visible', label: 'Frontal / güçlü duruş' },
	{ value: 'a dynamic walking pose mid-stride that conveys movement while keeping the product in sharp focus', label: 'Yürüyüş / hareketli' },
	{ value: 'a seated editorial pose with an elongated silhouette and the product clearly presented to camera', label: 'Oturma / editorial' },
]
const selectedPose = ref('')

const filteredProducts = computed(() => {
	const q = search.value.trim().toLowerCase()
	if (!q) return props.products
	return props.products.filter(p => p.name.toLowerCase().includes(q))
})

const allFilteredSelected = computed(() =>
	filteredProducts.value.length > 0 &&
	filteredProducts.value.every(p => selectedProducts.has(p.id)),
)

const canGenerate = computed(() => selectedTemplate.value !== null && selectedProducts.size > 0)

function toggleProduct(id) {
	if (selectedProducts.has(id)) selectedProducts.delete(id)
	else selectedProducts.add(id)
}

function toggleAll() {
	if (allFilteredSelected.value) {
		filteredProducts.value.forEach(p => selectedProducts.delete(p.id))
	} else {
		filteredProducts.value.forEach(p => selectedProducts.add(p.id))
	}
}

function generate() {
	if (!canGenerate.value || busy.value) return
	busy.value = true
	router.post('/creative/generate', {
		template_id: selectedTemplate.value,
		product_ids: Array.from(selectedProducts),
		use_ai: useAi.value,
		use_copy_ai: useCopyAi.value,
		render_engine: useAiCompose.value ? 'ai_compose' : 'svg',
		format: selectedFormat.value,
		pose: useAi.value ? (selectedPose.value || null) : null,
	}, {
		onError: (errs) => {
			showToast?.({
				type: 'error',
				title: 'Üretim başlatılamadı',
				message: Object.values(errs)[0] || 'Doğrulama hatası.',
			})
		},
		onFinish: () => { busy.value = false },
	})
}
</script>

<style scoped>
.hint { font-size: 12px; color: var(--color-muted); white-space: nowrap; }

.card-search { display: flex; align-items: center; gap: 6px; background: var(--color-surface-container-low); border: 1px solid var(--color-outline-variant); border-radius: 8px; padding: 5px 10px; min-width: 200px; }
.card-search svg { color: var(--color-muted); flex-shrink: 0; }
.card-search input { border: none; background: none; outline: none; font-family: inherit; font-size: 13px; color: var(--color-ink); width: 100%; }
.card-search input::placeholder { color: var(--color-muted); }
.link-btn { background: none; border: none; color: var(--color-primary); font-size: 12px; font-weight: 600; cursor: pointer; font-family: inherit; }

/* Şablonlar */
.template-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 14px; }
.template-card { position: relative; text-align: left; padding: 0; border: 2px solid var(--color-outline-variant); border-radius: 12px; background: var(--color-surface); cursor: pointer; overflow: hidden; transition: border-color .15s, box-shadow .15s; font-family: inherit; }
.template-card:hover { border-color: color-mix(in srgb, var(--color-primary) 35%, transparent); }
.template-card.selected { border-color: var(--color-primary); box-shadow: 0 0 0 3px color-mix(in srgb, var(--color-primary) 12%, transparent); }
.template-preview { aspect-ratio: 1; background: var(--color-surface-container-low); display: flex; align-items: center; justify-content: center; overflow: hidden; }
.template-preview img { width: 100%; height: 100%; object-fit: cover; }
.no-preview { font-size: 12px; color: var(--color-muted); font-weight: 700; }
.template-meta { padding: 9px 11px; display: flex; flex-direction: column; gap: 2px; }
.template-name { font-size: 13px; font-weight: 600; color: var(--color-ink); }
.template-dim { font-size: 11px; color: var(--color-muted); font-family: 'SF Mono', Menlo, Consolas, monospace; }
.check-badge { position: absolute; top: 8px; right: 8px; width: 22px; height: 22px; border-radius: 50%; background: var(--color-primary); color: var(--color-on-primary); display: flex; align-items: center; justify-content: center; }

/* Ürünler */
.product-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap: 12px; }
.product-card { position: relative; text-align: center; padding: 12px 10px; border: 2px solid var(--color-outline-variant); border-radius: 12px; background: var(--color-surface); cursor: pointer; transition: border-color .15s, background .15s; font-family: inherit; display: flex; flex-direction: column; align-items: center; gap: 8px; }
.product-card:hover { border-color: color-mix(in srgb, var(--color-primary) 35%, transparent); }
.product-card.selected { border-color: var(--color-primary); background: var(--color-primary-soft); }
.product-thumb { width: 56px; height: 56px; border-radius: 10px; background: linear-gradient(135deg, var(--color-primary-soft), var(--color-surface-container-high)); display: flex; align-items: center; justify-content: center; overflow: hidden; }
.product-thumb img { width: 100%; height: 100%; object-fit: cover; }
.product-thumb .no-preview { color: var(--color-primary); font-size: 20px; }
.product-name { font-size: 12px; font-weight: 600; color: var(--color-ink); line-height: 1.3; }
.select-dot { position: absolute; top: 8px; right: 8px; width: 16px; height: 16px; border-radius: 50%; border: 2px solid color-mix(in srgb, var(--color-primary) 35%, transparent); transition: all .15s; }
.select-dot.on { background: var(--color-primary); border-color: var(--color-primary); box-shadow: inset 0 0 0 2px var(--color-surface); }

/* Aksiyon çubuğu */
.action-bar { position: sticky; bottom: 0; display: flex; align-items: center; justify-content: space-between; gap: 16px; background: var(--color-surface); border: 1px solid var(--color-outline-variant); border-radius: 14px; padding: 14px 18px; box-shadow: 0 -2px 12px rgba(0,0,0,.05); }
.selection-summary { font-size: 13px; color: var(--color-on-surface-variant); margin-right: auto; }
.selection-summary strong { color: var(--color-ink); }

/* Format seçici */
.format-select { display: flex; align-items: center; gap: 8px; padding: 6px 12px; border: 1px solid var(--color-outline-variant); border-radius: 10px; }
.format-select .fs-label { font-size: 12px; font-weight: 600; color: var(--color-on-surface-variant); }
.format-select select { border: none; background: none; outline: none; font-family: inherit; font-size: 12.5px; color: var(--color-ink); cursor: pointer; max-width: 230px; }

/* Poz seçici */
.pose-select { display: flex; align-items: center; gap: 8px; padding: 6px 12px; border: 1px solid var(--color-outline-variant); border-radius: 10px; }
.pose-select .fs-label { font-size: 12px; font-weight: 600; color: var(--color-on-surface-variant); }
.pose-select select { border: none; background: none; outline: none; font-family: inherit; font-size: 12.5px; color: var(--color-ink); cursor: pointer; max-width: 190px; }

/* AI anahtarı */
.ai-toggle { display: flex; align-items: center; gap: 8px; cursor: pointer; user-select: none; padding: 7px 12px; border: 1px solid var(--color-outline-variant); border-radius: 10px; transition: all .15s; }
.ai-toggle:hover { border-color: color-mix(in srgb, var(--color-primary) 35%, transparent); }
.ai-toggle.on { border-color: var(--color-primary); background: var(--color-primary-soft); }
.ai-toggle input { display: none; }
.ai-dot { width: 32px; height: 18px; border-radius: 10px; background: color-mix(in srgb, var(--color-primary) 35%, transparent); position: relative; transition: background .15s; flex-shrink: 0; }
.ai-dot::after { content: ''; position: absolute; top: 2px; left: 2px; width: 14px; height: 14px; border-radius: 50%; background: var(--color-surface); transition: transform .15s; }
.ai-toggle.on .ai-dot { background: var(--color-primary); }
.ai-toggle.on .ai-dot::after { transform: translateX(14px); }
.ai-label { display: inline-flex; align-items: center; gap: 4px; font-size: 12px; font-weight: 600; color: var(--color-on-surface-variant); white-space: nowrap; }
.ai-toggle.on .ai-label { color: var(--color-primary); }
.copy-hint { font-size: 11px; color: var(--color-warning); white-space: nowrap; }

/* ── Dar ekran (telefon) ── */
@media (max-width: 640px) {
	.card-search { min-width: 0; width: 100%; }

	.template-grid { grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 10px; }
	.product-grid { grid-template-columns: repeat(auto-fill, minmax(100px, 1fr)); gap: 8px; }

	.action-bar {
		position: static;
		flex-wrap: wrap;
		gap: 10px;
		padding: 14px;
	}
	.selection-summary { width: 100%; margin-right: 0; order: 1; }
	.format-select, .pose-select { flex: 1 1 140px; order: 2; }
	.format-select select, .pose-select select { max-width: none; flex: 1; width: 0; }
	.ai-toggle { order: 3; width: 100%; justify-content: center; }
	.action-bar .btn.btn-primary { order: 4; width: 100%; justify-content: center; }
}
</style>
