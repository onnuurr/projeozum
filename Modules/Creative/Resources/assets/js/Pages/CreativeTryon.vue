<template>
	<Head title="Ürün Giydirme" />
	<div class="page-tryon">
		<Breadcrumb
			:items="[
				{ label: 'Ana Sayfa', to: '/workflow', icon: 'home' },
				{ label: 'Creative' },
				{ label: 'Ürün Giydirme' },
			]"
		/>

		<CreativeNav current="tryon" />

		<div class="page-header">
			<div>
				<h1 class="page-title">Ürün Giydirme</h1>
				<p class="page-subtitle">Ürünü bir mankenin pozlarına giydir, ürün görseli olarak ekle</p>
			</div>
			<button type="button" class="btn btn-ghost" @click="refresh">Yenile</button>
		</div>

		<!-- Adım 1: Ürün -->
		<div class="card">
			<div class="card-header">
				<h3>1. Ürün Seç</h3>
				<div class="card-search">
					<input v-model="search" type="text" placeholder="Ürün ara..." />
				</div>
			</div>
			<div class="card-body">
				<div v-if="products.length === 0" class="empty-block">Ürün bulunamadı.</div>
				<p v-else-if="hasProductsWithoutGarment" class="garment-note">
					Soluk görünen ürünlerin giydirilecek fotoğrafı yok; önce ürüne fotoğraf ekleyin.
				</p>
				<div v-if="products.length > 0" class="product-grid">
					<button
						v-for="p in filteredProducts"
						:key="p.id"
						type="button"
						class="product-card"
						:class="{ selected: selectedProduct === p.id, disabled: !p.has_garment }"
						:disabled="!p.has_garment"
						:title="p.has_garment ? p.name : `${p.name} — giydirilecek fotoğrafı yok`"
						@click="p.has_garment && (selectedProduct = p.id)"
					>
						<div class="product-thumb">
							<img v-if="p.cover" :src="p.cover" :alt="p.name" />
							<span v-else class="no-preview">{{ p.name.charAt(0) }}</span>
						</div>
						<span class="product-name">{{ p.name }}</span>
						<span v-if="!p.has_garment" class="no-garment-badge">Fotoğraf yok</span>
					</button>
				</div>
			</div>
		</div>

		<!-- Adım 2: Manken -->
		<div class="card">
			<div class="card-header">
				<h3>2. Manken Seç</h3>
				<span class="hint">{{ mannequins.length }} uygun manken</span>
			</div>
			<div class="card-body">
				<div v-if="mannequins.length === 0" class="empty-block">
					Giydirmeye uygun manken yok. Önce bir manken üretin (durum: hazır).
				</div>
				<div v-else class="mannequin-row">
					<button
						v-for="m in mannequins"
						:key="m.id"
						type="button"
						class="mannequin-pill"
						:class="{ selected: selectedMannequin === m.id }"
						@click="selectedMannequin = m.id"
					>
						<div class="pill-thumb"><img v-if="m.reference_url" :src="m.reference_url" :alt="m.name" /></div>
						<span>{{ m.name }}</span>
					</button>
				</div>
			</div>
		</div>

		<!-- Adım 3: Pozlar (bağımsız kütüphane) -->
		<div class="card">
			<div class="card-header">
				<h3>3. Pozlar Seç</h3>
				<button v-if="poses.length" type="button" class="link-btn" @click="toggleAllPoses">
					{{ allPosesSelected ? 'Seçimi kaldır' : 'Tümünü seç' }}
				</button>
				<span class="hint">{{ selectedPoses.size }}/{{ poses.length }} seçili</span>
			</div>
			<div class="card-body">
				<div v-if="poses.length === 0" class="empty-block">
					Hazır poz yok. Önce <Link href="/creative/poses" class="inline-link">Pozlar</Link> sayfasından üretin.
				</div>
				<div v-else class="pose-grid">
					<button
						v-for="pose in poses"
						:key="pose.id"
						type="button"
						class="pose-pick-card"
						:class="{ selected: selectedPoses.has(pose.id) }"
						@click="togglePose(pose.id)"
					>
						<img v-if="pose.preview_url" :src="pose.preview_url" :alt="pose.label" />
						<span class="pose-pick-label">{{ pose.label }}</span>
						<span class="pose-check" :class="{ on: selectedPoses.has(pose.id) }"></span>
					</button>
				</div>
			</div>
		</div>

		<!-- Aksiyon -->
		<div class="action-bar">
			<div class="selection-summary">
				<strong>{{ selectedPoses.size }}</strong> poz ×
				<strong>{{ selectedProduct ? 1 : 0 }}</strong> ürün =
				<strong>{{ selectedProduct ? selectedPoses.size : 0 }}</strong> görsel
			</div>
			<button class="btn btn-primary btn-with-icon" :disabled="!canGenerate || busy" @click="generate">
				<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M5 3l14 9-14 9V3z" /></svg>
				{{ busy ? 'Kuyruğa alınıyor…' : 'Giydir ve Ürün Görseli Yap' }}
			</button>
		</div>

		<!-- Sonuçlar -->
		<div class="card">
			<div class="card-header"><h3>Son Giydirmeler</h3><span class="hint">{{ results.length }} kayıt</span></div>
			<div class="card-body">
				<div v-if="results.length === 0" class="empty-block">Henüz giydirme yok.</div>
				<div v-else class="result-grid">
					<div v-for="r in results" :key="r.id" class="result-card">
						<div class="result-thumb">
							<img v-if="r.image_url" :src="r.image_url" :alt="r.product_name" />
							<span v-else class="no-preview">{{ statusLabel(r.status) }}</span>
							<span class="status-badge" :class="r.status">{{ statusLabel(r.status) }}</span>
							<span v-if="r.is_cover" class="cover-badge">★ Kapak</span>
							<span v-if="r.review_status" class="review-badge" :class="`r-${r.review_status}`">
								{{ reviewLabel(r.review_status) }}
							</span>
						</div>
						<div class="result-meta">
							<span class="result-product">{{ r.product_name }}</span>
							<span class="result-sub">{{ r.mannequin_name }} · {{ r.pose_label }}</span>
							<span v-if="r.creator_name" class="result-creator">Üreten: {{ r.creator_name }}{{ r.is_own ? ' (siz)' : '' }}</span>
							<div v-if="r.review_tags && r.review_tags.length" class="review-tags">
								<span v-for="t in r.review_tags" :key="t" class="review-tag">{{ t }}</span>
							</div>
							<p v-if="r.error" class="result-error" :title="r.error">⚠ {{ r.error }}</p>
							<div v-if="r.can_review" class="review-actions">
								<button type="button" class="act-btn approve" :disabled="busyReview === r.id" @click="approve(r)">✓ Onayla</button>
								<button type="button" class="act-btn reject" :disabled="busyReview === r.id" @click="reject(r)">✕ Reddet</button>
							</div>
							<div v-if="r.status === 'done'" class="result-actions">
								<template v-if="r.review_status === 'approved'">
									<button v-if="!r.is_cover" type="button" class="link-btn" @click="setCover(r)">Kapak yap</button>
									<span v-else class="is-cover-note">Kapak</span>
								</template>
								<button type="button" class="link-btn danger" @click="destroyResult(r)">Sil</button>
							</div>
						</div>
						<Link v-if="r.can_chat" :href="`/creative/tryon/${r.id}/review-chat`" class="chat-link">
							💬 AI ile Konuş <span v-if="r.review_chats?.length">({{ r.review_chats.length }} mesaj)</span>
						</Link>
					</div>
				</div>
			</div>
		</div>
	</div>
</template>

<script setup>
import { ref, reactive, computed, inject, onMounted, onUnmounted } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Breadcrumb from '@/Components/Breadcrumb.vue'
import CreativeNav from '../Components/CreativeNav.vue'
import { openRejectDialog } from '../support/rejectDialog'

defineOptions({ layout: AppLayout })

const props = defineProps({
	products: { type: Array, default: () => [] },
	mannequins: { type: Array, default: () => [] },
	poses: { type: Array, default: () => [] },
	results: { type: Array, default: () => [] },
	rejectionReasons: { type: Array, default: () => [] },
})

const showToast = inject('showToast', null)
const $swal = inject('$swal')

const search = ref('')
const busy = ref(false)
const busyReview = ref(null)
const selectedProduct = ref(null)
const selectedMannequin = ref(null)
const selectedPoses = reactive(new Set())

const filteredProducts = computed(() => {
	const q = search.value.trim().toLowerCase()
	if (!q) return props.products
	return props.products.filter(p => p.name.toLowerCase().includes(q))
})

const hasProductsWithoutGarment = computed(() => props.products.some(p => !p.has_garment))

const allPosesSelected = computed(() =>
	props.poses.length > 0 && props.poses.every(p => selectedPoses.has(p.id)),
)
const canGenerate = computed(() =>
	selectedProduct.value !== null && selectedMannequin.value !== null && selectedPoses.size > 0,
)
const hasPending = computed(() => props.results.some(r => r.status === 'queued' || r.status === 'generating'))

const STATUS_LABELS = { queued: 'Sırada', generating: 'Üretiliyor', done: 'Hazır', failed: 'Başarısız' }
const REVIEW_LABELS = { pending: 'Onay Bekliyor', approved: 'Onaylı', rejected: 'Reddedildi' }
function statusLabel(s) { return STATUS_LABELS[s] || s }
function reviewLabel(r) { return REVIEW_LABELS[r] || r }

function approve(r) {
	if (busyReview.value) return
	busyReview.value = r.id
	router.post(`/creative/tryon/${r.id}/approve`, {}, {
		preserveScroll: true,
		preserveState: false,
		onError: (errs) => showToast?.({ type: 'error', title: 'Onaylanamadı', message: Object.values(errs)[0] || 'Sunucu hatası.' }),
		onFinish: () => { busyReview.value = null },
	})
}

async function reject(r) {
	const result = await openRejectDialog($swal, props.rejectionReasons)
	if (!result) return

	busyReview.value = r.id
	router.post(`/creative/tryon/${r.id}/reject`, { reason: result.reason, tags: result.tags }, {
		preserveScroll: true,
		preserveState: false,
		onError: (errs) => showToast?.({ type: 'error', title: 'Reddedilemedi', message: Object.values(errs)[0] || 'Sunucu hatası.' }),
		onFinish: () => { busyReview.value = null },
	})
}

function togglePose(id) {
	if (selectedPoses.has(id)) selectedPoses.delete(id)
	else selectedPoses.add(id)
}

function toggleAllPoses() {
	if (allPosesSelected.value) props.poses.forEach(p => selectedPoses.delete(p.id))
	else props.poses.forEach(p => selectedPoses.add(p.id))
}

function generate() {
	if (!canGenerate.value || busy.value) return
	busy.value = true
	router.post('/creative/tryon', {
		product_id: selectedProduct.value,
		mannequin_id: selectedMannequin.value,
		pose_ids: Array.from(selectedPoses),
	}, {
		preserveScroll: true,
		onSuccess: () => {
			selectedPoses.clear()
		},
		onError: (errs) => showToast?.({ type: 'error', title: 'Başlatılamadı', message: Object.values(errs)[0] || 'Doğrulama hatası.' }),
		onFinish: () => { busy.value = false },
	})
}

function setCover(r) {
	router.post(`/creative/tryon/${r.id}/cover`, {}, {
		preserveScroll: true,
		onError: (errs) => showToast?.({ type: 'error', title: 'Yapılamadı', message: Object.values(errs)[0] || 'Hata.' }),
	})
}

async function destroyResult(r) {
	const ok = await $swal.dangerConfirm({
		title: 'Sonucu Sil',
		html: `<strong>${r.product_name}</strong> için üretilen ürün görseli silinecek.`,
		confirmText: 'Sil',
		cancelText: 'Vazgeç',
	})
	if (!ok) return
	router.delete(`/creative/tryon/${r.id}`, {
		preserveScroll: true,
	})
}

function refresh() {
	router.reload({ only: ['results'] })
}

let timer = null
onMounted(() => { timer = setInterval(() => { if (hasPending.value) refresh() }, 5000) })
onUnmounted(() => { if (timer) clearInterval(timer) })
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
.hint { font-size: 12px; color: #aaa; }

.card-search { margin-left: auto; background: #f5f5f8; border: 1px solid #e8e8f0; border-radius: 8px; padding: 5px 10px; min-width: 200px; }
.card-search input { border: none; background: none; outline: none; font-family: inherit; font-size: 13px; width: 100%; }
.link-btn { background: none; border: none; color: rgb(var(--color-primary)); font-size: 12px; font-weight: 600; cursor: pointer; font-family: inherit; padding: 0; }

/* Ürünler */
.product-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 12px; }
.product-card { position: relative; text-align: center; padding: 12px 10px; border: 2px solid #ebebf0; border-radius: 12px; background: #fff; cursor: pointer; display: flex; flex-direction: column; align-items: center; gap: 8px; font-family: inherit; }
.product-card.selected { border-color: rgb(var(--color-primary)); background: rgb(var(--color-primary-soft)); }
.product-card.disabled { opacity: .45; cursor: not-allowed; }
.product-card.disabled:hover { border-color: #ebebf0; }
.no-garment-badge { position: absolute; top: 6px; right: 6px; font-size: 9px; font-weight: 700; padding: 2px 6px; border-radius: 20px; background: #dc2626; color: #fff; }
.garment-note { font-size: 12px; color: #b45309; background: #fffbeb; border: 1px solid #fde68a; border-radius: 8px; padding: 8px 12px; margin-bottom: 14px; }
.product-thumb { width: 56px; height: 56px; border-radius: 10px; background: linear-gradient(135deg, rgb(var(--color-primary-soft)), #ddd6fe); display: flex; align-items: center; justify-content: center; overflow: hidden; }
.product-thumb img { width: 100%; height: 100%; object-fit: cover; }
.product-thumb .no-preview { color: rgb(var(--color-primary)); font-size: 20px; }
.product-name { font-size: 12px; font-weight: 600; color: #1a1a2e; line-height: 1.3; }

/* Mankenler */
.mannequin-row { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 16px; }
.mannequin-pill { display: flex; align-items: center; gap: 8px; padding: 6px 12px 6px 6px; border: 2px solid #ebebf0; border-radius: 30px; background: #fff; cursor: pointer; font-family: inherit; font-size: 13px; font-weight: 600; color: #1a1a2e; }
.mannequin-pill.selected { border-color: rgb(var(--color-primary)); background: rgb(var(--color-primary-soft)); }
.mannequin-pill .pill-thumb { width: 30px; height: 30px; border-radius: 50%; overflow: hidden; background: #f0f0f5; flex-shrink: 0; }
.mannequin-pill .pill-thumb img { width: 100%; height: 100%; object-fit: cover; }
.mannequin-pill small { color: #999; font-weight: 500; }

.pose-pick-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px; }
.pose-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(110px, 1fr)); gap: 10px; }
.pose-pick-card { position: relative; border: 2px solid #ebebf0; border-radius: 10px; overflow: hidden; background: #fff; cursor: pointer; padding: 0; font-family: inherit; }
.pose-pick-card.selected { border-color: rgb(var(--color-primary)); }
.pose-pick-card img { width: 100%; aspect-ratio: 3/4; object-fit: cover; display: block; }
.pose-pick-label { display: block; font-size: 11px; font-weight: 600; color: #1a1a2e; padding: 5px 6px; }
.pose-check { position: absolute; top: 6px; right: 6px; width: 16px; height: 16px; border-radius: 50%; border: 2px solid #fff; box-shadow: 0 0 0 1px rgb(var(--color-primary) / .35); background: rgba(255,255,255,.7); }
.pose-check.on { background: rgb(var(--color-primary)); box-shadow: 0 0 0 1px rgb(var(--color-primary)); }

/* Aksiyon */
.action-bar { position: sticky; bottom: 0; display: flex; align-items: center; justify-content: space-between; gap: 16px; background: #fff; border: 1px solid #ebebf0; border-radius: 14px; padding: 14px 18px; box-shadow: 0 -2px 12px rgba(0,0,0,.05); margin-bottom: 18px; }
.selection-summary { font-size: 13px; color: #666; }
.selection-summary strong { color: #1a1a2e; }

/* Sonuçlar */
.result-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 14px; }
.result-card { border: 1px solid #ebebf0; border-radius: 12px; overflow: hidden; background: #fff; }
.result-thumb { position: relative; aspect-ratio: 3/4; background: #f5f5f8; display: flex; align-items: center; justify-content: center; }
.result-thumb img { width: 100%; height: 100%; object-fit: cover; }
.no-preview { font-size: 11px; color: #bbb; font-weight: 700; }
.status-badge { position: absolute; top: 6px; left: 6px; font-size: 9px; font-weight: 700; padding: 2px 7px; border-radius: 20px; color: #fff; text-transform: uppercase; }
.status-badge.queued { background: #9ca3af; }
.status-badge.generating { background: #f59e0b; }
.status-badge.done { background: #16a34a; }
.status-badge.failed { background: #dc2626; }
.cover-badge { position: absolute; top: 6px; right: 6px; font-size: 9px; font-weight: 700; padding: 2px 7px; border-radius: 20px; background: #1a1a2e; color: #fff; }
.review-badge { position: absolute; bottom: 6px; left: 6px; padding: 2px 7px; border-radius: 20px; font-size: 9px; font-weight: 700; text-transform: uppercase; }
.review-badge.r-pending { background: #f59e0b; color: #fff; }
.review-badge.r-approved { background: #16a34a; color: #fff; }
.review-badge.r-rejected { background: #dc2626; color: #fff; }
.result-meta { padding: 8px 10px; }
.result-product { font-size: 12px; font-weight: 700; color: #1a1a2e; display: block; }
.result-sub { font-size: 11px; color: #888; }
.result-creator { font-size: 10px; color: #aaa; display: block; }
.review-tags { display: flex; flex-wrap: wrap; gap: 4px; margin-top: 4px; }
.review-tag { font-size: 10px; font-weight: 600; padding: 2px 7px; border-radius: 10px; background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
.result-error { font-size: 10px; color: #dc2626; margin-top: 4px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.review-actions { display: flex; gap: 6px; margin-top: 6px; }
.act-btn { flex: 1; border: none; cursor: pointer; font-size: 11px; font-weight: 600; padding: 6px; border-radius: 8px; font-family: inherit; transition: all .15s; }
.act-btn:disabled { opacity: .45; cursor: not-allowed; }
.act-btn.approve { background: #dcfce7; color: #15803d; }
.act-btn.approve:hover:not(:disabled) { background: #bbf7d0; }
.act-btn.reject { background: #fee2e2; color: #b91c1c; }
.act-btn.reject:hover:not(:disabled) { background: #fecaca; }
.result-actions { display: flex; align-items: center; gap: 10px; margin-top: 6px; }
.link-btn { background: none; border: none; color: rgb(var(--color-primary)); font-size: 11px; font-weight: 600; cursor: pointer; font-family: inherit; padding: 0; }
.link-btn.danger { color: #dc2626; margin-left: auto; }
.is-cover-note { font-size: 11px; color: #16a34a; font-weight: 600; }
.chat-link { display: block; text-align: center; padding: 8px 12px; margin: 0 12px 12px; background: #eef2ff; color: #4338ca; border-radius: 8px; font-size: 12px; font-weight: 600; text-decoration: none; }
.chat-link:hover { background: #e0e7ff; }
</style>
