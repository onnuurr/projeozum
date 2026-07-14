<template>
	<Head title="AI Konsept Stüdyosu" />
	<div class="page-concepts">
		<Breadcrumb
			:items="[
				{ label: 'Ana Sayfa', to: '/workflow', icon: 'home' },
				{ label: 'Üretim Atölyesi', to: '/atelier' },
				{ label: 'Konsept Stüdyosu' },
			]"
		/>

		<AtelierNav current="concepts" />

		<div class="page-header">
			<div>
				<h1 class="page-title">AI Konsept Stüdyosu</h1>
				<p class="page-subtitle">
					Tariften görsel konsept üret, beğendiğini bir kalıba bağla.
				</p>
			</div>
			<span class="driver-badge" :class="driver">
				<span class="dot"></span>
				{{ driver === 'gemini' ? 'Gemini' : 'Demo modu' }}
			</span>
		</div>

		<div class="studio-grid">
			<!-- Sol: Tarif tezgâhı -->
			<aside class="composer card">
				<div class="card-header">
					<h3>Tarif</h3>
				</div>
				<form @submit.prevent="generate" class="composer-body">
					<!-- Akış seçimi -->
					<div class="flow-toggle" role="tablist">
						<button
							type="button" role="tab"
							:class="{ active: form.source === 'concept_first' }"
							@click="setFlow('concept_first')"
						>
							Önce Konsept
							<small>Akış Y</small>
						</button>
						<button
							type="button" role="tab"
							:class="{ active: form.source === 'pattern_first' }"
							@click="setFlow('pattern_first')"
						>
							Önce Kalıp
							<small>Akış X</small>
						</button>
					</div>
					<p class="flow-hint">{{ flowHint }}</p>

					<div v-if="form.source === 'pattern_first'" class="form-row">
						<label class="form-label">Kalıp <span class="req">*</span></label>
						<select v-model="form.pattern_id" class="form-input" @change="syncTypeFromPattern">
							<option :value="null" disabled>Kütüphaneden seç…</option>
							<option v-for="p in patterns" :key="p.id" :value="p.id">
								{{ p.name }} · {{ p.productType }}
							</option>
						</select>
						<span v-if="form.errors.pattern_id" class="form-error">{{ form.errors.pattern_id }}</span>
						<span v-if="form.source === 'pattern_first' && patterns.length === 0" class="form-error">
							Onaylı kalıp yok — önce kalıp kütüphanesine ekleyin.
						</span>
					</div>

					<div class="form-row">
						<label class="form-label">Ürün tipi <span class="req">*</span></label>
						<input v-model="form.product_type" list="concept-types" class="form-input" placeholder="tulum, ceket, kapüşonlu…" />
						<datalist id="concept-types">
							<option v-for="t in typeOptions" :key="t" :value="t" />
						</datalist>
						<span v-if="form.errors.product_type" class="form-error">{{ form.errors.product_type }}</span>
					</div>

					<div class="form-row">
						<label class="form-label">Desen / motif</label>
						<input v-model="form.motif" class="form-input" placeholder="küçük çiçekler, geometrik…" />
					</div>

					<div class="form-row">
						<label class="form-label">Renk yönü</label>
						<input v-model="form.palette" class="form-input" placeholder="lacivert + krem" />
					</div>

					<div class="form-row">
						<label class="form-label">Stil</label>
						<input v-model="form.style" class="form-input" placeholder="minimal, retro…" />
					</div>

					<div class="form-row">
						<label class="form-label">Serbest tarif</label>
						<textarea v-model="form.description" rows="3" class="form-input" placeholder="Aklındaki konsepti birkaç cümleyle anlat."></textarea>
					</div>

					<div class="form-row">
						<label class="form-label">Beden aralığı</label>
						<input v-model="form.size_range" class="form-input" placeholder="116-122-128-134" />
					</div>

					<div class="form-row">
						<label class="form-label">Varyant sayısı</label>
						<div class="count-pills">
							<button
								v-for="n in maxVariants" :key="n" type="button"
								class="count-pill" :class="{ active: form.count === n }"
								@click="form.count = n"
							>{{ n }}</button>
						</div>
					</div>

					<button type="submit" class="btn btn-primary generate-btn" :disabled="form.processing || !canGenerate">
						<span v-if="form.processing" class="gen-spinner"></span>
						{{ form.processing ? 'Üretiliyor…' : 'Konsept üret' }}
					</button>
					<p v-if="form.processing" class="processing-note">
						AI görselleri hazırlanıyor, bu birkaç saniye sürebilir.
					</p>
				</form>
			</aside>

			<!-- Sağ: Konsept galerisi -->
			<section class="gallery">
				<div class="gallery-head">
					<h3>Üretilen Konseptler</h3>
					<span class="gallery-count">{{ cards.length }}</span>
				</div>

				<div v-if="cards.length === 0" class="gallery-empty">
					<div class="empty-art">✦</div>
					<p>Henüz konsept yok.</p>
					<span>Soldaki tarifi doldurup “Konsept üret”e bas.</span>
				</div>

				<div v-else class="concept-grid">
					<article v-for="card in cards" :key="card.id" class="concept-card">
						<div v-if="card.generationStatus === 'processing'" class="thumb-state processing">
							<span class="gen-spinner"></span>
							<span>üretiliyor…</span>
						</div>
						<div v-else-if="card.generationStatus === 'failed'" class="thumb-state failed" :title="card.generationError">
							<span class="fail-mark">!</span>
							<span>üretilemedi</span>
						</div>
						<div v-else class="thumb-strip" :data-count="card.images.length">
							<button
								v-for="(img, i) in card.images" :key="i"
								type="button" class="thumb" @click="zoom = img"
								:style="{ backgroundImage: `url(${img})` }"
								:aria-label="`Varyant ${i + 1}`"
							></button>
						</div>
						<div class="concept-meta">
							<div class="meta-row">
								<span class="source-badge" :class="card.source">
									{{ card.source === 'pattern_first' ? 'Akış X' : 'Akış Y' }}
								</span>
								<span class="type-pill">{{ card.productType }}</span>
								<span v-if="card.targetSize" class="size-chip">{{ card.targetSize }}</span>
							</div>
							<p v-if="card.prompt" class="concept-prompt">{{ card.prompt }}</p>

							<div class="concept-foot">
								<button v-if="card.generationStatus === 'failed'" class="btn-link" @click="regenerate(card)">Yeniden dene</button>
								<span v-else-if="card.generationStatus === 'processing'" class="foot-muted">hazırlanıyor…</span>
								<span v-else-if="card.pattern" class="matched-link">
									⟶ {{ card.pattern.name }}
								</span>
								<button v-else class="btn-link" @click="openMatch(card)">Kalıba eşle</button>
								<button class="archive-btn" @click="archive(card)" title="Arşivle">🗑️</button>
							</div>
						</div>
					</article>
				</div>
			</section>
		</div>

		<!-- Eşleme modalı (Akış Y) -->
		<div v-if="match.card" class="modal-overlay" @click.self="closeMatch">
			<div class="modal-box">
				<div class="modal-head">
					<span class="modal-title">Kalıba eşle</span>
					<button class="modal-close" @click="closeMatch">✕</button>
				</div>
				<p class="match-sub">Konsepti kütüphanedeki en yakın kalıba bağla.</p>
				<form @submit.prevent="submitMatch" class="form-grid">
					<div class="form-row">
						<label class="form-label">Kalıp <span class="req">*</span></label>
						<select v-model="match.form.pattern_id" class="form-input">
							<option :value="null" disabled>Seç…</option>
							<option v-for="p in patterns" :key="p.id" :value="p.id">{{ p.name }} · {{ p.productType }}</option>
						</select>
					</div>
					<div class="modal-foot">
						<button type="button" class="btn btn-ghost" @click="closeMatch">Kapat</button>
						<button type="submit" class="btn btn-primary" :disabled="match.form.processing || !match.form.pattern_id">Eşle</button>
					</div>
				</form>
			</div>
		</div>

		<!-- Görsel yakınlaştırma -->
		<div v-if="zoom" class="zoom-overlay" @click="zoom = null">
			<img :src="zoom" alt="Konsept görseli" />
		</div>
	</div>
</template>

<script setup>
import { ref, computed, watch, onUnmounted } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Breadcrumb from '@/Components/Breadcrumb.vue'
import AtelierNav from '../Components/AtelierNav.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
	cards: { type: Array, default: () => [] },
	patterns: { type: Array, default: () => [] },
	maxVariants: { type: Number, default: 4 },
	driver: { type: String, default: 'mock' },
})

const form = useForm({
	source: 'concept_first',
	pattern_id: null,
	product_type: '',
	motif: '',
	palette: '',
	style: '',
	description: '',
	size_range: '',
	count: 2,
})

const zoom = ref(null)

const typeOptions = computed(() =>
	[...new Set(props.patterns.map((p) => p.productType).filter(Boolean))]
)

const flowHint = computed(() =>
	form.source === 'pattern_first'
		? 'Kütüphaneden kalıbı seç; konsept baştan o kalıba bağlı doğar.'
		: 'Önce serbestçe konsept üret; sonra uygun kalıba eşlersin.'
)

const canGenerate = computed(() => {
	if (!form.product_type.trim()) return false
	if (form.source === 'pattern_first' && !form.pattern_id) return false
	return true
})

function setFlow(flow) {
	form.source = flow
	if (flow === 'concept_first') form.pattern_id = null
}

function syncTypeFromPattern() {
	const p = props.patterns.find((x) => x.id === form.pattern_id)
	if (p && !form.product_type.trim()) form.product_type = p.productType
	if (p && !form.size_range.trim() && p.sizeRange) form.size_range = p.sizeRange
}

function generate() {
	form.post('/atelier/concepts', { preserveScroll: true })
}

const match = ref({ card: null, form: useForm({ pattern_id: null }) })
function openMatch(card) {
	match.value.card = card
	match.value.form.pattern_id = null
}
function closeMatch() {
	match.value.card = null
}
function submitMatch() {
	match.value.form.post(`/atelier/concepts/${match.value.card.id}/match`, {
		preserveScroll: true,
		onSuccess: closeMatch,
	})
}

function archive(card) {
	router.delete(`/atelier/concepts/${card.id}`, { preserveScroll: true })
}
function regenerate(card) {
	router.post(`/atelier/concepts/${card.id}/regenerate`, {}, { preserveScroll: true })
}

/* Üretim sürerken galeriyi hafifçe taze tut.
   İşçi çalışmıyorsa kart kalıcı 'processing' kalır → sonsuz dönmesin diye
   en çok MAX_POLLS deneme (≈80sn) sonra durur (yeni üretim sıfırlar). */
let pollTimer = null
let pollsLeft = 0
const MAX_POLLS = 20
function syncPolling() {
	const busy = props.cards.some((c) => c.generationStatus === 'processing')
	if (busy) {
		if (!pollTimer) {
			pollsLeft = MAX_POLLS
			pollTimer = setInterval(() => {
				if (pollsLeft-- <= 0) { clearInterval(pollTimer); pollTimer = null; return }
				router.reload({ only: ['cards'], preserveScroll: true })
			}, 4000)
		}
	} else if (pollTimer) {
		clearInterval(pollTimer); pollTimer = null
	}
}
watch(() => props.cards, syncPolling, { immediate: true, deep: false })
onUnmounted(() => { if (pollTimer) clearInterval(pollTimer) })
</script>

<style scoped>
.page-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 20px; gap: 16px; }
.page-title { font-size: 22px; font-weight: 700; color: #1a1a2e; }
.page-subtitle { font-size: 13px; color: #888; margin-top: 4px; }

.driver-badge { display: inline-flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 600; padding: 5px 11px; border-radius: 20px; }
.driver-badge .dot { width: 7px; height: 7px; border-radius: 50%; }
.driver-badge.gemini { background: #ecfdf5; color: #059669; }
.driver-badge.gemini .dot { background: #10b981; }
.driver-badge.mock { background: #fff7ed; color: #c2410c; }
.driver-badge.mock .dot { background: #f97316; }

.card { background: #fff; border-radius: 16px; border: 1px solid #ebebf0; box-shadow: 0 1px 4px rgba(0,0,0,.04); }
.card-header { padding: 14px 18px; border-bottom: 1px solid #f0f0f5; }
.card-header h3 { font-size: 15px; font-weight: 700; color: #1a1a2e; }

/* Layout */
.studio-grid { display: grid; grid-template-columns: 360px 1fr; gap: 18px; align-items: start; }
.composer { position: sticky; top: 16px; overflow: hidden; }
.composer-body { padding: 16px 18px; display: flex; flex-direction: column; gap: 14px; }

.form-row { display: flex; flex-direction: column; gap: 6px; }
.form-label { font-size: 12px; font-weight: 600; color: #1a1a2e; }
.form-label .req { color: #ef4444; }
.form-input { padding: 9px 12px; border: 1px solid #e8e8f0; border-radius: 8px; font-family: inherit; font-size: 13px; color: #1a1a2e; background: #fff; outline: none; transition: border-color .15s; width: 100%; }
.form-input:focus { border-color: rgb(var(--color-primary)); }
textarea.form-input { resize: vertical; }
.form-error { font-size: 11.5px; color: #ef4444; }

/* Akış toggle */
.flow-toggle { display: grid; grid-template-columns: 1fr 1fr; gap: 4px; background: #f4f4f8; padding: 4px; border-radius: 10px; }
.flow-toggle button { display: flex; flex-direction: column; align-items: center; gap: 1px; padding: 8px 6px; border: none; background: transparent; border-radius: 7px; font-size: 12.5px; font-weight: 600; color: #888; cursor: pointer; transition: all .15s; }
.flow-toggle button small { font-size: 10px; font-weight: 600; opacity: .65; }
.flow-toggle button.active { background: #fff; color: rgb(var(--color-primary)); box-shadow: 0 1px 3px rgba(0,0,0,.08); }
.flow-hint { font-size: 11.5px; color: #999; line-height: 1.4; margin-top: -6px; }

.count-pills { display: flex; gap: 6px; }
.count-pill { width: 38px; height: 34px; border: 1px solid #e8e8f0; background: #fff; border-radius: 8px; font-size: 13px; font-weight: 600; color: #888; cursor: pointer; transition: all .15s; }
.count-pill.active { background: rgb(var(--color-primary)); border-color: rgb(var(--color-primary)); color: #fff; }

.generate-btn { margin-top: 4px; width: 100%; display: inline-flex; align-items: center; justify-content: center; gap: 8px; }
.gen-spinner { width: 14px; height: 14px; border: 2px solid rgba(255,255,255,.45); border-top-color: #fff; border-radius: 50%; animation: spin .7s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
.processing-note { font-size: 11.5px; color: #999; text-align: center; }

/* Galeri */
.gallery { min-width: 0; }
.gallery-head { display: flex; align-items: center; gap: 10px; margin-bottom: 14px; }
.gallery-head h3 { font-size: 15px; font-weight: 700; color: #1a1a2e; }
.gallery-count { font-size: 12px; font-weight: 600; color: #888; background: #f0f0f5; padding: 2px 9px; border-radius: 20px; }

.gallery-empty { background: #fff; border: 1px dashed #dcdce6; border-radius: 16px; padding: 56px 20px; text-align: center; color: #999; }
.gallery-empty .empty-art { font-size: 34px; color: rgb(var(--color-primary)); opacity: .5; margin-bottom: 10px; }
.gallery-empty p { font-size: 14px; font-weight: 600; color: #555; }
.gallery-empty span { font-size: 12.5px; }

.concept-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(230px, 1fr)); gap: 16px; }
.concept-card { background: #fff; border: 1px solid #ebebf0; border-radius: 14px; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,.04); transition: box-shadow .15s, transform .15s; }
.concept-card:hover { box-shadow: 0 6px 22px rgba(0,0,0,.09); transform: translateY(-2px); }

.thumb-strip { display: grid; gap: 2px; background: #f0f0f5; aspect-ratio: 4 / 3; }
.thumb-strip[data-count="1"] { grid-template-columns: 1fr; }
.thumb-strip[data-count="2"] { grid-template-columns: 1fr 1fr; }
.thumb-strip[data-count="3"], .thumb-strip[data-count="4"] { grid-template-columns: 1fr 1fr; grid-auto-rows: 1fr; }
.thumb { border: none; padding: 0; cursor: zoom-in; background-size: cover; background-position: center; background-color: #e8e8ee; transition: opacity .15s; }
.thumb:hover { opacity: .88; }

.thumb-state { aspect-ratio: 4 / 3; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 8px; font-size: 12.5px; font-weight: 600; }
.thumb-state.processing { background: #f4f4f8; color: #6b7280; }
.thumb-state.failed { background: #fef2f2; color: #dc2626; cursor: help; }
.thumb-state .gen-spinner { border-color: rgba(0,0,0,.12); border-top-color: rgb(var(--color-primary)); width: 20px; height: 20px; }
.fail-mark { width: 24px; height: 24px; border-radius: 50%; background: #dc2626; color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 800; }
.foot-muted { font-size: 12px; color: #aaa; }

.concept-meta { padding: 12px 13px; display: flex; flex-direction: column; gap: 9px; }
.meta-row { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
.source-badge { font-size: 10.5px; font-weight: 700; padding: 2px 8px; border-radius: 6px; letter-spacing: .02em; }
.source-badge.concept_first { background: #eef2ff; color: #4f46e5; }
.source-badge.pattern_first { background: #ecfdf5; color: #059669; }
.type-pill { display: inline-block; padding: 2px 9px; background: #f4f4f8; color: #555; border-radius: 6px; font-size: 11px; font-weight: 600; }
.size-chip { font-family: 'SF Mono', Menlo, Consolas, monospace; font-size: 10.5px; color: #888; background: #f7f7fb; padding: 2px 7px; border-radius: 5px; }
.concept-prompt { font-size: 12px; color: #777; line-height: 1.45; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }

.concept-foot { display: flex; align-items: center; justify-content: space-between; gap: 8px; border-top: 1px solid #f5f5f8; padding-top: 9px; }
.matched-link { font-size: 12px; font-weight: 600; color: #059669; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.btn-link { background: none; border: none; padding: 0; font-size: 12.5px; font-weight: 600; color: rgb(var(--color-primary)); cursor: pointer; }
.btn-link:hover { text-decoration: underline; }
.archive-btn { background: #f3f4f6; border: none; cursor: pointer; font-size: 12px; padding: 5px 8px; border-radius: 6px; transition: all .15s; }
.archive-btn:hover { background: #fee2e2; }

/* Modal */
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,.4); display: flex; align-items: center; justify-content: center; z-index: 9000; }
.modal-box { background: #fff; border-radius: 16px; padding: 24px; width: 380px; max-width: calc(100vw - 32px); display: flex; flex-direction: column; gap: 14px; box-shadow: 0 8px 40px rgba(0,0,0,.15); }
.modal-head { display: flex; align-items: center; justify-content: space-between; }
.modal-title { font-size: 16px; font-weight: 700; color: #1a1a2e; }
.modal-close { background: none; border: none; cursor: pointer; font-size: 15px; color: #888; padding: 2px 6px; border-radius: 6px; }
.modal-close:hover { background: #f0f0f5; color: #1a1a2e; }
.match-sub { font-size: 12.5px; color: #888; margin-top: -4px; }
.form-grid { display: flex; flex-direction: column; gap: 14px; }
.modal-foot { display: flex; gap: 8px; justify-content: flex-end; }

/* Zoom */
.zoom-overlay { position: fixed; inset: 0; background: rgba(15,15,25,.82); display: flex; align-items: center; justify-content: center; z-index: 9500; cursor: zoom-out; padding: 32px; }
.zoom-overlay img { max-width: 90vw; max-height: 90vh; border-radius: 10px; box-shadow: 0 12px 50px rgba(0,0,0,.5); }

.btn { padding: 9px 16px; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; border: 1px solid transparent; transition: all .15s; }
.btn-primary { background: rgb(var(--color-primary)); color: #fff; }
.btn-primary:disabled { opacity: .55; cursor: not-allowed; }
.btn-ghost { background: #f3f4f6; color: #555; }

@media (max-width: 900px) {
	.studio-grid { grid-template-columns: 1fr; }
	.composer { position: static; }
}

@media (max-width: 640px) {
	.page-header { flex-wrap: wrap; }
	.count-pills { flex-wrap: wrap; }
	.concept-grid { grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 10px; }
}
</style>
