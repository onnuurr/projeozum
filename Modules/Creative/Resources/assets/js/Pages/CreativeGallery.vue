<template>
	<Head title="Creative Galeri" />
	<div class="page-gallery">
		<Breadcrumb
			:items="[
				{ label: 'Ana Sayfa', to: '/workflow', icon: 'home' },
				{ label: 'Creative' },
				{ label: 'Galeri' },
			]"
		/>

		<div class="page-header">
			<div>
				<h1 class="page-title">Creative Galeri</h1>
				<p class="page-subtitle"><strong>{{ assets.total }}</strong> üretilmiş görsel</p>
			</div>
			<Link href="/creative/studio" class="btn btn-primary btn-with-icon">
				<svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
					<path d="M12 5v14M5 12h14" />
				</svg>
				Yeni Üretim
			</Link>
		</div>

		<div v-if="assets.data.length === 0" class="card">
			<div class="empty-block">Henüz görsel üretilmedi. Stüdyodan başlayın.</div>
		</div>

		<div v-else class="asset-grid">
			<div v-for="a in assets.data" :key="a.id" class="asset-card" :class="reviewClass(a)">
				<div class="asset-image">
					<img v-if="a.image_url" :src="a.image_url" :alt="a.product?.name" />
					<div v-else class="asset-placeholder">
						<span v-if="a.status === 'failed'" class="status-icon failed">⚠️</span>
						<span v-else class="status-icon spin">⏳</span>
						<span class="status-text">{{ statusLabel(a.status) }}</span>
					</div>
					<span class="status-chip" :class="`s-${a.status}`">{{ statusLabel(a.status) }}</span>
					<span v-if="a.review_status !== 'pending'" class="review-chip" :class="`r-${a.review_status}`">
						{{ reviewLabel(a.review_status) }}
					</span>
				</div>

				<div class="asset-meta">
					<span class="asset-product">{{ a.product?.name ?? '—' }}</span>
					<span class="asset-template">{{ a.template?.name ?? '—' }}</span>
					<span v-if="a.error" class="asset-error" :title="a.error">{{ a.error }}</span>
					<span class="asset-date">{{ a.created_at }}</span>
				</div>

				<div class="asset-actions">
					<template v-if="a.status === 'done'">
						<button
							v-if="a.review_status !== 'approved'"
							class="act-btn approve"
							:disabled="busy === a.id"
							@click="action(a, 'approve')"
						>✓ Onayla</button>
						<button
							v-if="a.review_status !== 'rejected'"
							class="act-btn reject"
							:disabled="busy === a.id"
							@click="action(a, 'reject')"
						>✕ Reddet</button>
					</template>
					<button
						class="act-btn regen"
						:disabled="busy === a.id || a.status === 'queued' || a.status === 'processing'"
						@click="action(a, 'regenerate')"
					>↻ Yeniden</button>
				</div>
			</div>
		</div>

		<!-- Sayfalama -->
		<div v-if="assets.last_page > 1" class="pagination">
			<button
				v-for="link in assets.links"
				:key="link.label"
				class="page-btn"
				:class="{ active: link.active, disabled: !link.url }"
				:disabled="!link.url"
				v-html="link.label"
				@click="goTo(link.url)"
			></button>
		</div>
	</div>
</template>

<script setup>
import { ref, inject } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Breadcrumb from '@/Components/Breadcrumb.vue'

defineOptions({ layout: AppLayout })

defineProps({
	assets: { type: Object, default: () => ({ data: [], links: [], total: 0, last_page: 1 }) },
})

const showToast = inject('showToast', null)
const busy = ref(null)

const STATUS_LABELS = { queued: 'Kuyrukta', processing: 'İşleniyor', done: 'Hazır', failed: 'Başarısız' }
const REVIEW_LABELS = { pending: 'Bekliyor', approved: 'Onaylı', rejected: 'Reddedildi' }

function statusLabel(s) { return STATUS_LABELS[s] ?? s }
function reviewLabel(r) { return REVIEW_LABELS[r] ?? r }
function reviewClass(a) { return a.review_status === 'approved' ? 'is-approved' : a.review_status === 'rejected' ? 'is-rejected' : '' }

const ACTION_MSG = {
	approve: { type: 'success', title: 'Görsel onaylandı' },
	reject: { type: 'warning', title: 'Görsel reddedildi' },
	regenerate: { type: 'info', title: 'Yeniden üretim kuyruğa alındı' },
}

function action(a, kind) {
	if (busy.value) return
	busy.value = a.id
	router.post(`/creative/assets/${a.id}/${kind}`, {}, {
		preserveScroll: true,
		preserveState: false,
		onSuccess: () => {
			const m = ACTION_MSG[kind]
			showToast?.({ type: m.type, title: m.title, message: a.product?.name ?? '' })
		},
		onError: (errs) => {
			showToast?.({ type: 'error', title: 'İşlem başarısız', message: Object.values(errs)[0] || 'Sunucu hatası.' })
		},
		onFinish: () => { busy.value = null },
	})
}

function goTo(url) {
	if (!url) return
	router.get(url, {}, { preserveScroll: true, preserveState: false })
}
</script>

<style scoped>
.page-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 20px; gap: 16px; }
.page-title { font-size: 22px; font-weight: 700; color: #1a1a2e; line-height: 1.2; }
.page-subtitle { font-size: 13px; color: #888; margin-top: 4px; }

.card { background: #fff; border-radius: 16px; border: 1px solid #ebebf0; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,.04); }
.empty-block { text-align: center; color: #aaa; padding: 48px 0; font-style: italic; font-size: 13px; }

.asset-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 16px; }
.asset-card { background: #fff; border: 1px solid #ebebf0; border-radius: 14px; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,.04); display: flex; flex-direction: column; transition: border-color .15s; }
.asset-card.is-approved { border-color: #bbf7d0; }
.asset-card.is-rejected { border-color: #fecaca; opacity: .75; }

.asset-image { position: relative; aspect-ratio: 1; background: #f5f5f8; display: flex; align-items: center; justify-content: center; overflow: hidden; }
.asset-image img { width: 100%; height: 100%; object-fit: cover; }
.asset-placeholder { display: flex; flex-direction: column; align-items: center; gap: 8px; color: #aaa; }
.status-icon { font-size: 28px; }
.status-icon.spin { animation: pulse 1.4s ease-in-out infinite; }
@keyframes pulse { 0%,100% { opacity: .4 } 50% { opacity: 1 } }
.status-text { font-size: 12px; }

.status-chip { position: absolute; top: 8px; left: 8px; padding: 3px 8px; border-radius: 6px; font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .03em; }
.s-queued { background: #fef3c7; color: #b45309; }
.s-processing { background: #dbeafe; color: #1d4ed8; }
.s-done { background: #dcfce7; color: #15803d; }
.s-failed { background: #fee2e2; color: #b91c1c; }
.review-chip { position: absolute; top: 8px; right: 8px; padding: 3px 8px; border-radius: 6px; font-size: 10.5px; font-weight: 700; }
.r-approved { background: #16a34a; color: #fff; }
.r-rejected { background: #dc2626; color: #fff; }

.asset-meta { padding: 11px 13px; display: flex; flex-direction: column; gap: 3px; }
.asset-product { font-size: 13px; font-weight: 600; color: #1a1a2e; }
.asset-template { font-size: 11.5px; color: #888; }
.asset-error { font-size: 11px; color: #dc2626; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.asset-date { font-size: 11px; color: #aaa; font-family: 'SF Mono', Menlo, Consolas, monospace; }

.asset-actions { display: flex; gap: 6px; padding: 0 13px 13px; flex-wrap: wrap; }
.act-btn { flex: 1; min-width: 70px; border: none; cursor: pointer; font-size: 12px; font-weight: 600; padding: 7px 6px; border-radius: 8px; font-family: inherit; transition: all .15s; }
.act-btn:disabled { opacity: .45; cursor: not-allowed; }
.act-btn.approve { background: #dcfce7; color: #15803d; }
.act-btn.approve:hover:not(:disabled) { background: #bbf7d0; }
.act-btn.reject { background: #fee2e2; color: #b91c1c; }
.act-btn.reject:hover:not(:disabled) { background: #fecaca; }
.act-btn.regen { background: #f0f0f5; color: #555; }
.act-btn.regen:hover:not(:disabled) { background: #e5e5ee; }

.pagination { display: flex; gap: 4px; justify-content: center; margin-top: 24px; flex-wrap: wrap; }
.page-btn { min-width: 34px; height: 34px; padding: 0 10px; border: 1px solid #e8e8f0; background: #fff; border-radius: 8px; font-size: 13px; color: #555; cursor: pointer; font-family: inherit; }
.page-btn:hover:not(.disabled):not(.active) { background: #faf8ff; border-color: #d8d4f0; }
.page-btn.active { background: #7c3aed; border-color: #7c3aed; color: #fff; font-weight: 700; }
.page-btn.disabled { opacity: .4; cursor: not-allowed; }
</style>
