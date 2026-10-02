<script setup>
import { ref, computed, watch, inject, onUnmounted } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import {
	OzPageHeader, OzModuleTabs, OzStatStrip, OzCard, OzButton, OzPill, OzEmptyState, OzModal, OzTabs,
	OzField, OzInput, OzTextarea, OzCombobox, OzSpinner, OzAlert, useOzConfirm,
} from '@/Components/Oz'
import { useCan } from '@/composables/useCan'

defineOptions({ layout: AppLayout })

const props = defineProps({
	cards: { type: Array, default: () => [] },
	patterns: { type: Array, default: () => [] },
	maxVariants: { type: Number, default: 4 },
	driver: { type: String, default: 'mock' },
})

const { can } = useCan()
const canDesign = computed(() => can('atelier.design.manage'))
const showToast = inject('showToast', null)
const confirm = useOzConfirm()
const errorToast = (title) => (errs) => showToast?.({ type: 'error', title, message: Object.values(errs)[0] || 'Sunucu hatası.' })

/* ── özet ── */
const ready = computed(() => props.cards.filter((c) => !['processing', 'failed'].includes(c.generationStatus)))
const matched = computed(() => props.cards.filter((c) => c.pattern).length)
const busyOrFailed = computed(() => props.cards.filter((c) => ['processing', 'failed'].includes(c.generationStatus)).length)
const stats = computed(() => [
	{ key: 'total', label: 'Toplam Konsept', value: props.cards.length, icon: 'sparkles', tone: 'accent' },
	{ key: 'matched', label: 'Kalıba Eşlenen', value: matched.value, icon: 'link', tone: 'success' },
	{ key: 'waiting', label: 'Eşleme Bekleyen', value: ready.value.filter((c) => !c.pattern).length, icon: 'clock', tone: 'info' },
	{ key: 'busy', label: 'Üretimde / Hatalı', value: busyOrFailed.value, icon: 'refresh', tone: busyOrFailed.value ? 'warning' : 'success' },
])

/* ── tarif formu ── */
const form = useForm({
	source: 'concept_first',
	pattern_id: '',
	product_type: '',
	motif: '',
	palette: '',
	style: '',
	description: '',
	size_range: '',
	count: 2,
})

const flowTabs = [
	{ key: 'concept_first', label: 'Önce Konsept' },
	{ key: 'pattern_first', label: 'Önce Kalıp' },
]
const flowHint = computed(() => form.source === 'pattern_first'
	? 'Akış X — kütüphaneden kalıbı seç; konsept baştan o kalıba bağlı doğar.'
	: 'Akış Y — önce serbestçe konsept üret; sonra uygun kalıba eşlersin.')
watch(() => form.source, (s) => { if (s === 'concept_first') form.pattern_id = '' })

const countTabs = computed(() => Array.from({ length: props.maxVariants }, (_, i) => ({ key: String(i + 1), label: String(i + 1) })))
const countModel = computed({ get: () => String(form.count), set: (v) => { form.count = Number(v) } })

const patternOptions = computed(() => props.patterns.map((p) => ({ value: p.id, label: p.name, description: p.productType })))
const typeOptions = computed(() => [...new Set(props.patterns.map((p) => p.productType).filter(Boolean))])

// Kalıp seçilince boş bırakılmış ürün tipi / beden aralığı kalıptan doldurulur
watch(() => form.pattern_id, (id) => {
	const p = props.patterns.find((x) => x.id === id)
	if (!p) return
	if (!form.product_type.trim()) form.product_type = p.productType
	if (!form.size_range.trim() && p.sizeRange) form.size_range = p.sizeRange
})

const canGenerate = computed(() => !!form.product_type.trim() && (form.source !== 'pattern_first' || !!form.pattern_id))

function generate() {
	if (!canGenerate.value || form.processing) return
	form.transform((d) => ({ ...d, pattern_id: d.pattern_id || null })).post('/atelier/concepts', {
		preserveScroll: true,
		onError: errorToast('Konsept üretilemedi'),
	})
}

/* ── kart aksiyonları ── */
const zoom = ref(null)
const zoomOpen = computed({ get: () => !!zoom.value, set: (v) => { if (!v) zoom.value = null } })

const matchCard = ref(null)
const matchOpen = computed({ get: () => !!matchCard.value, set: (v) => { if (!v) matchCard.value = null } })
const matchForm = useForm({ pattern_id: '' })
function openMatch(card) {
	matchCard.value = card
	matchForm.reset()
	matchForm.clearErrors()
}
function submitMatch() {
	if (!matchForm.pattern_id || matchForm.processing) return
	matchForm.post(`/atelier/concepts/${matchCard.value.id}/match`, {
		preserveScroll: true,
		onSuccess: () => { matchCard.value = null },
		onError: errorToast('Eşlenemedi'),
	})
}

async function archive(card) {
	const ok = await confirm.danger('Konsept arşivlensin mi?', `${card.productType} konsepti galeriden kaldırılacak.`, { confirmText: 'Arşivle' })
	if (ok) router.delete(`/atelier/concepts/${card.id}`, { preserveScroll: true, onError: errorToast('Arşivlenemedi') })
}
function regenerate(card) {
	router.post(`/atelier/concepts/${card.id}/regenerate`, {}, { preserveScroll: true, onError: errorToast('Yeniden denenemedi') })
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

<template>
	<Head title="AI Konsept Stüdyosu" />

	<OzPageHeader title="AI Konsept Stüdyosu" subtitle="Tariften görsel konsept üret, beğendiğini bir kalıba bağla.">
		<template #actions>
			<OzPill :tone="driver === 'gemini' ? 'success' : 'warning'">{{ driver === 'gemini' ? 'Gemini' : 'Demo modu' }}</OzPill>
		</template>
	</OzPageHeader>
	<OzModuleTabs />

	<OzStatStrip :items="stats" />

	<div class="studio" :class="{ 'no-composer': !canDesign }">
		<!-- Sol: tarif -->
		<OzCard v-if="canDesign" title="Tarif" class="composer">
			<form class="form" @submit.prevent="generate">
				<div class="flow">
					<OzTabs v-model="form.source" :tabs="flowTabs" variant="segment" aria-label="Akış" />
					<p class="hint">{{ flowHint }}</p>
				</div>

				<template v-if="form.source === 'pattern_first'">
					<OzField label="Kalıp" required :error="form.errors.pattern_id">
						<OzCombobox v-model="form.pattern_id" :options="patternOptions" placeholder="Kütüphaneden seçin" search-placeholder="Kalıp ara…" />
					</OzField>
					<OzAlert v-if="!patterns.length" tone="warning" title="Onaylı kalıp yok">Önce kalıp kütüphanesine kalıp ekleyip onaylayın.</OzAlert>
				</template>

				<OzField label="Ürün tipi" required :error="form.errors.product_type">
					<OzInput v-model="form.product_type" list="concept-types" placeholder="tulum, ceket, kapüşonlu…" />
					<datalist id="concept-types"><option v-for="t in typeOptions" :key="t" :value="t" /></datalist>
				</OzField>
				<div class="row-2">
					<OzField label="Desen / motif" :error="form.errors.motif">
						<OzInput v-model="form.motif" placeholder="küçük çiçekler…" />
					</OzField>
					<OzField label="Renk yönü" :error="form.errors.palette">
						<OzInput v-model="form.palette" placeholder="lacivert + krem" />
					</OzField>
				</div>
				<div class="row-2">
					<OzField label="Stil" :error="form.errors.style">
						<OzInput v-model="form.style" placeholder="minimal, retro…" />
					</OzField>
					<OzField label="Beden aralığı" :error="form.errors.size_range">
						<OzInput v-model="form.size_range" placeholder="116-122-128-134" />
					</OzField>
				</div>
				<OzField label="Serbest tarif" :error="form.errors.description">
					<OzTextarea v-model="form.description" :rows="3" :maxlength="1000" placeholder="Aklındaki konsepti birkaç cümleyle anlat." />
				</OzField>
				<OzField label="Varyant sayısı" :error="form.errors.count">
					<OzTabs v-model="countModel" :tabs="countTabs" variant="segment" aria-label="Varyant sayısı" />
				</OzField>

				<OzButton type="submit" variant="primary" icon="sparkles" class="generate" :disabled="!canGenerate" :loading="form.processing">
					{{ form.processing ? 'Üretiliyor…' : 'Konsept Üret' }}
				</OzButton>
				<p v-if="form.processing" class="hint center">AI görselleri hazırlanıyor, bu birkaç saniye sürebilir.</p>
			</form>
		</OzCard>

		<!-- Sağ: galeri -->
		<OzCard title="Üretilen Konseptler" :meta="`${cards.length} konsept`" class="gallery">
			<OzEmptyState v-if="!cards.length" icon="sparkles" title="Henüz konsept yok" :description="canDesign ? 'Soldaki tarifi doldurup “Konsept Üret”e basın.' : ''" />
			<div v-else class="grid">
				<article v-for="card in cards" :key="card.id" class="concept">
					<div v-if="card.generationStatus === 'processing'" class="state">
						<OzSpinner :size="20" />
						<span>üretiliyor…</span>
					</div>
					<div v-else-if="card.generationStatus === 'failed'" class="state is-failed" :title="card.generationError">
						<span class="fail">!</span>
						<span>üretilemedi</span>
					</div>
					<div v-else class="thumbs" :data-count="card.images.length">
						<button
							v-for="(img, i) in card.images"
							:key="i"
							type="button"
							class="thumb"
							:style="{ backgroundImage: `url(${img})` }"
							:aria-label="`Varyant ${i + 1} büyüt`"
							@click="zoom = img"
						/>
					</div>
					<div class="meta">
						<div class="tags">
							<OzPill :tone="card.source === 'pattern_first' ? 'success' : 'accent'">{{ card.source === 'pattern_first' ? 'Akış X' : 'Akış Y' }}</OzPill>
							<span class="tag">{{ card.productType }}</span>
							<span v-if="card.targetSize" class="tag mono">{{ card.targetSize }}</span>
						</div>
						<p v-if="card.prompt" class="prompt">{{ card.prompt }}</p>
						<footer class="foot">
							<span v-if="card.pattern" class="matched">→ {{ card.pattern.name }}</span>
							<template v-else-if="canDesign">
								<OzButton v-if="card.generationStatus === 'failed'" size="sm" icon="refresh" @click="regenerate(card)">Yeniden Dene</OzButton>
								<span v-else-if="card.generationStatus === 'processing'" class="dim">hazırlanıyor…</span>
								<OzButton v-else size="sm" icon="link" @click="openMatch(card)">Kalıba Eşle</OzButton>
							</template>
							<span v-else />
							<OzButton v-if="canDesign" size="sm" icon="trash" :aria-label="`${card.productType} konseptini arşivle`" @click="archive(card)" />
						</footer>
					</div>
				</article>
			</div>
		</OzCard>
	</div>

	<!-- Kalıba eşle (Akış Y) -->
	<OzModal v-model:open="matchOpen" title="Kalıba Eşle" subtitle="Konsepti kütüphanedeki en yakın kalıba bağla" icon="link" size="sm" :persistent="matchForm.processing">
		<OzField label="Kalıp" required :error="matchForm.errors.pattern_id">
			<OzCombobox v-model="matchForm.pattern_id" :options="patternOptions" placeholder="Kalıp seçin" search-placeholder="Kalıp ara…" />
		</OzField>
		<template #footer>
			<OzButton :disabled="matchForm.processing" @click="matchCard = null">Vazgeç</OzButton>
			<OzButton variant="primary" icon="check" :disabled="!matchForm.pattern_id" :loading="matchForm.processing" @click="submitMatch">Eşle</OzButton>
		</template>
	</OzModal>

	<!-- Görsel büyütme -->
	<OzModal v-model:open="zoomOpen" title="Konsept görseli" icon="sparkles" size="lg">
		<img v-if="zoom" :src="zoom" alt="Konsept görseli" class="zoom-img">
	</OzModal>
</template>

<style scoped>

.dim { color: var(--oz-muted); font-size: 12px; }
.mono { font-family: ui-monospace, monospace; }

.studio { display: grid; grid-template-columns: 380px minmax(0, 1fr); gap: 16px; align-items: start; }
.studio.no-composer { grid-template-columns: 1fr; }
.composer { position: sticky; top: 0; }
.form { display: flex; flex-direction: column; gap: 14px; padding: 0 18px 18px; }
.flow { display: flex; flex-direction: column; gap: 6px; }
.flow :deep(.list) { width: 100%; }
.flow :deep(.tab) { flex: 1; justify-content: center; }
.hint { margin: 0; font-size: 11.5px; line-height: 1.45; color: var(--oz-muted); }
.hint.center { text-align: center; }
.row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
.generate { width: 100%; justify-content: center; }

.grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(min(230px, 100%), 1fr)); gap: 14px; padding: 0 18px 18px; }
.concept { display: flex; flex-direction: column; border: 1px solid var(--oz-border); border-radius: var(--oz-radius-sm); overflow: hidden; background: var(--oz-surface); transition: box-shadow .15s, transform .15s; }
.concept:hover { box-shadow: var(--oz-shadow); transform: translateY(-2px); }
.thumbs { display: grid; gap: 2px; aspect-ratio: 4 / 3; background: var(--oz-surface-2); }
.thumbs[data-count="1"] { grid-template-columns: 1fr; }
.thumbs[data-count="2"] { grid-template-columns: 1fr 1fr; }
.thumbs[data-count="3"], .thumbs[data-count="4"] { grid-template-columns: 1fr 1fr; grid-auto-rows: 1fr; }
.thumb { border: none; padding: 0; cursor: zoom-in; background-color: var(--oz-surface-2); background-size: cover; background-position: center; transition: opacity .15s; }
.thumb:hover { opacity: .88; }
.thumb:focus-visible { outline: 2px solid var(--oz-accent); outline-offset: -2px; }
.state { aspect-ratio: 4 / 3; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 8px; font-size: 12.5px; font-weight: 600; color: var(--oz-muted); background: var(--oz-surface-2); }
.state.is-failed { color: var(--oz-danger); background: var(--oz-danger-soft); cursor: help; }
.fail { width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; background: var(--oz-danger); color: #fff; font-weight: 800; }
.meta { display: flex; flex-direction: column; gap: 9px; padding: 11px 12px; flex: 1; }
.tags { display: flex; align-items: center; flex-wrap: wrap; gap: 6px; }
.tag { font-size: 11px; color: var(--oz-muted); background: var(--oz-surface-2); border: 1px solid var(--oz-border-soft); padding: 1px 7px; border-radius: 5px; }
.prompt { margin: 0; font-size: 12px; line-height: 1.45; color: var(--oz-muted); display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.foot { display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-top: auto; padding-top: 9px; border-top: 1px solid var(--oz-border-soft); }
.matched { font-size: 12px; font-weight: 600; color: var(--oz-success); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.zoom-img { display: block; max-width: 100%; max-height: 70vh; margin: 0 auto; border-radius: var(--oz-radius-sm); }

@media (max-width: 960px) {
	.studio { grid-template-columns: 1fr; }
	.composer { position: static; }
}
@media (max-width: 560px) {
	.row-2 { grid-template-columns: 1fr; }
}
</style>
