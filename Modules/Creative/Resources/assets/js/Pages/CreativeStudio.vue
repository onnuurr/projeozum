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

		<div class="page-header">
			<div>
				<h1 class="page-title">Creative Stüdyo</h1>
				<p class="page-subtitle">Bir şablon ve ürün seçip sosyal medya görseli üretin</p>
			</div>
			<Link href="/creative/gallery" class="btn btn-ghost btn-with-icon">
				<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
					<rect x="3" y="3" width="7" height="7" rx="1" /><rect x="14" y="3" width="7" height="7" rx="1" />
					<rect x="3" y="14" width="7" height="7" rx="1" /><rect x="14" y="14" width="7" height="7" rx="1" />
				</svg>
				Galeri
			</Link>
		</div>

		<!-- Adım 1: Şablon -->
		<div class="card">
			<div class="card-header">
				<h3>1. Şablon Seç</h3>
				<span class="hint">{{ templates.length }} aktif şablon</span>
			</div>
			<div class="card-body">
				<div v-if="templates.length === 0" class="empty-block">
					Aktif şablon yok. Önce bir şablon ekleyin.
				</div>
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
						<span v-if="selectedTemplate === t.id" class="check-badge">✓</span>
					</button>
				</div>
			</div>
		</div>

		<!-- Adım 2: Ürünler -->
		<div class="card">
			<div class="card-header">
				<h3>2. Ürün Seç</h3>
				<div class="card-search">
					<svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
						<circle cx="11" cy="11" r="8" /><path d="M21 21l-4.35-4.35" />
					</svg>
					<input v-model="search" type="text" placeholder="Ürün ara..." />
				</div>
				<button type="button" class="link-btn" @click="toggleAll">
					{{ allFilteredSelected ? 'Seçimi kaldır' : 'Tümünü seç' }}
				</button>
			</div>
			<div class="card-body">
				<div v-if="products.length === 0" class="empty-block">Ürün bulunamadı.</div>
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
			</div>
		</div>

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
				<span class="ai-label">✨ AI sahne / giydirme</span>
			</label>
			<label v-if="useAi" class="pose-select" title="AI mankeninin duruşu (poz planlaması)">
				<span class="fs-label">Poz</span>
				<select v-model="selectedPose">
					<option v-for="p in poseOptions" :key="p.label" :value="p.value">{{ p.label }}</option>
				</select>
			</label>
			<button
				class="btn btn-primary btn-with-icon"
				:disabled="!canGenerate || busy"
				@click="generate"
			>
				<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
					<path d="M5 3l14 9-14 9V3z" />
				</svg>
				{{ busy ? 'Kuyruğa alınıyor…' : 'Görselleri Üret' }}
			</button>
		</div>
	</div>
</template>

<script setup>
import { ref, reactive, computed, inject } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Breadcrumb from '@/Components/Breadcrumb.vue'
import CreativeNav from '../Components/CreativeNav.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
	templates: { type: Array, default: () => [] },
	products: { type: Array, default: () => [] },
	formats: { type: Array, default: () => [] },
	default_format: { type: String, default: null },
})

const showToast = inject('showToast', null)
usePage()

const selectedTemplate = ref(props.templates.length === 1 ? props.templates[0].id : null)
const selectedProducts = reactive(new Set())
const search = ref('')
const busy = ref(false)
const useAi = ref(false)
const selectedFormat = ref(props.default_format || props.formats[0]?.key || null)

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
.page-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 20px; gap: 16px; }
.page-title { font-size: 22px; font-weight: 700; color: #1a1a2e; line-height: 1.2; }
.page-subtitle { font-size: 13px; color: #888; margin-top: 4px; }

.card { background: #fff; border-radius: 16px; border: 1px solid #ebebf0; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,.04); margin-bottom: 18px; }
.card-header { padding: 14px 18px; display: flex; align-items: center; gap: 12px; border-bottom: 1px solid #f0f0f5; }
.card-header h3 { font-size: 15px; font-weight: 700; color: #1a1a2e; }
.card-header .hint { font-size: 12px; color: #aaa; margin-left: auto; }
.card-body { padding: 18px; }
.empty-block { text-align: center; color: #aaa; padding: 28px 0; font-style: italic; font-size: 13px; }

.card-search { display: flex; align-items: center; gap: 6px; background: #f5f5f8; border: 1px solid #e8e8f0; border-radius: 8px; padding: 5px 10px; margin-left: auto; min-width: 200px; }
.card-search svg { color: #aaa; flex-shrink: 0; }
.card-search input { border: none; background: none; outline: none; font-family: inherit; font-size: 13px; color: #1a1a2e; width: 100%; }
.card-search input::placeholder { color: #bbb; }
.link-btn { background: none; border: none; color: rgb(var(--color-primary)); font-size: 12px; font-weight: 600; cursor: pointer; font-family: inherit; }

/* Şablonlar */
.template-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 14px; }
.template-card { position: relative; text-align: left; padding: 0; border: 2px solid #ebebf0; border-radius: 12px; background: #fff; cursor: pointer; overflow: hidden; transition: border-color .15s, box-shadow .15s; font-family: inherit; }
.template-card:hover { border-color: rgb(var(--color-primary) / .35); }
.template-card.selected { border-color: rgb(var(--color-primary)); box-shadow: 0 0 0 3px rgb(var(--color-primary) / .12); }
.template-preview { aspect-ratio: 1; background: #f5f5f8; display: flex; align-items: center; justify-content: center; overflow: hidden; }
.template-preview img { width: 100%; height: 100%; object-fit: cover; }
.no-preview { font-size: 12px; color: #bbb; font-weight: 700; }
.template-meta { padding: 9px 11px; display: flex; flex-direction: column; gap: 2px; }
.template-name { font-size: 13px; font-weight: 600; color: #1a1a2e; }
.template-dim { font-size: 11px; color: #999; font-family: 'SF Mono', Menlo, Consolas, monospace; }
.check-badge { position: absolute; top: 8px; right: 8px; width: 22px; height: 22px; border-radius: 50%; background: rgb(var(--color-primary)); color: #fff; font-size: 12px; display: flex; align-items: center; justify-content: center; font-weight: 700; }

/* Ürünler */
.product-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap: 12px; }
.product-card { position: relative; text-align: center; padding: 12px 10px; border: 2px solid #ebebf0; border-radius: 12px; background: #fff; cursor: pointer; transition: border-color .15s, background .15s; font-family: inherit; display: flex; flex-direction: column; align-items: center; gap: 8px; }
.product-card:hover { border-color: rgb(var(--color-primary) / .35); }
.product-card.selected { border-color: rgb(var(--color-primary)); background: rgb(var(--color-primary-soft)); }
.product-thumb { width: 56px; height: 56px; border-radius: 10px; background: linear-gradient(135deg, rgb(var(--color-primary-soft)), #ddd6fe); display: flex; align-items: center; justify-content: center; overflow: hidden; }
.product-thumb img { width: 100%; height: 100%; object-fit: cover; }
.product-thumb .no-preview { color: rgb(var(--color-primary)); font-size: 20px; }
.product-name { font-size: 12px; font-weight: 600; color: #1a1a2e; line-height: 1.3; }
.select-dot { position: absolute; top: 8px; right: 8px; width: 16px; height: 16px; border-radius: 50%; border: 2px solid rgb(var(--color-primary) / .35); transition: all .15s; }
.select-dot.on { background: rgb(var(--color-primary)); border-color: rgb(var(--color-primary)); box-shadow: inset 0 0 0 2px #fff; }

/* Aksiyon çubuğu */
.action-bar { position: sticky; bottom: 0; display: flex; align-items: center; justify-content: space-between; gap: 16px; background: #fff; border: 1px solid #ebebf0; border-radius: 14px; padding: 14px 18px; box-shadow: 0 -2px 12px rgba(0,0,0,.05); }
.selection-summary { font-size: 13px; color: #666; margin-right: auto; }
.selection-summary strong { color: #1a1a2e; }

/* Format seçici */
.format-select { display: flex; align-items: center; gap: 8px; padding: 6px 12px; border: 1px solid #ebebf0; border-radius: 10px; }
.format-select .fs-label { font-size: 12px; font-weight: 600; color: #555; }
.format-select select { border: none; background: none; outline: none; font-family: inherit; font-size: 12.5px; color: #1a1a2e; cursor: pointer; max-width: 230px; }

/* Poz seçici */
.pose-select { display: flex; align-items: center; gap: 8px; padding: 6px 12px; border: 1px solid #ebebf0; border-radius: 10px; }
.pose-select .fs-label { font-size: 12px; font-weight: 600; color: #555; }
.pose-select select { border: none; background: none; outline: none; font-family: inherit; font-size: 12.5px; color: #1a1a2e; cursor: pointer; max-width: 190px; }

/* AI anahtarı */
.ai-toggle { display: flex; align-items: center; gap: 8px; cursor: pointer; user-select: none; padding: 7px 12px; border: 1px solid #ebebf0; border-radius: 10px; transition: all .15s; }
.ai-toggle:hover { border-color: rgb(var(--color-primary) / .35); }
.ai-toggle.on { border-color: rgb(var(--color-primary)); background: rgb(var(--color-primary-soft)); }
.ai-toggle input { display: none; }
.ai-dot { width: 32px; height: 18px; border-radius: 10px; background: rgb(var(--color-primary) / .35); position: relative; transition: background .15s; flex-shrink: 0; }
.ai-dot::after { content: ''; position: absolute; top: 2px; left: 2px; width: 14px; height: 14px; border-radius: 50%; background: #fff; transition: transform .15s; }
.ai-toggle.on .ai-dot { background: rgb(var(--color-primary)); }
.ai-toggle.on .ai-dot::after { transform: translateX(14px); }
.ai-label { font-size: 12px; font-weight: 600; color: #555; white-space: nowrap; }
.ai-toggle.on .ai-label { color: rgb(var(--color-primary)); }

/* ── Dar ekran (telefon) ── */
@media (max-width: 640px) {
	.page-header { flex-wrap: wrap; }
	.page-header .btn { width: 100%; justify-content: center; }

	.card-header { flex-wrap: wrap; row-gap: 8px; }
	.card-header .hint { margin-left: 0; }
	.card-search { margin-left: 0; min-width: 0; width: 100%; order: 3; }
	.link-btn { margin-left: auto; }

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
