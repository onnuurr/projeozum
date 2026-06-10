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

		<CreativeNav current="gallery" />

		<div class="page-header">
			<div>
				<h1 class="page-title">Creative Galeri</h1>
				<p class="page-subtitle"><strong>{{ assets.total }}</strong> üretilmiş görsel</p>
			</div>
			<div class="header-btns">
				<a
					v-if="stats.approved > 0"
					href="/creative/export"
					class="btn btn-ghost btn-with-icon"
					title="Onaylanmış görselleri ZIP olarak indir"
				>
					<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
						<path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M7 10l5 5 5-5M12 15V3" />
					</svg>
					ZIP indir ({{ stats.approved }})
				</a>
				<Link href="/creative/studio" class="btn btn-primary btn-with-icon">
					<svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
						<path d="M12 5v14M5 12h14" />
					</svg>
					Yeni Üretim
				</Link>
			</div>
		</div>

		<!-- Analitik üst barı -->
		<div v-if="stats.total > 0" class="stats-bar">
			<div class="stat"><span class="stat-val">{{ stats.total }}</span><span class="stat-lbl">Toplam</span></div>
			<div class="stat"><span class="stat-val ok">{{ stats.done }}</span><span class="stat-lbl">Hazır</span></div>
			<div class="stat"><span class="stat-val warn">{{ stats.pending }}</span><span class="stat-lbl">İşlemde</span></div>
			<div class="stat"><span class="stat-val bad">{{ stats.failed }}</span><span class="stat-lbl">Başarısız</span></div>
			<div class="stat"><span class="stat-val">{{ stats.approved }}</span><span class="stat-lbl">Onaylı</span></div>
			<div class="stat" v-if="stats.success_rate !== null"><span class="stat-val">%{{ stats.success_rate }}</span><span class="stat-lbl">Başarı</span></div>
			<div class="stat" v-if="stats.avg_render_ms !== null"><span class="stat-val">{{ fmtMs(stats.avg_render_ms) }}</span><span class="stat-lbl">Ort. süre</span></div>
			<div class="stat-templates" v-if="stats.per_template.length">
				<span class="stat-lbl">Şablon performansı</span>
				<div class="tpl-bars">
					<div v-for="t in stats.per_template" :key="t.name" class="tpl-bar" :title="`${t.name}: ${t.done}/${t.total} hazır`">
						<span class="tpl-bar-name">{{ t.name }}</span>
						<span class="tpl-bar-track"><span class="tpl-bar-fill" :style="{ width: (t.total ? t.done / t.total * 100 : 0) + '%' }"></span></span>
						<span class="tpl-bar-num">{{ t.done }}/{{ t.total }}</span>
					</div>
				</div>
			</div>
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

				<div v-if="a.status === 'done'" class="asset-caption">
					<textarea
						class="caption-input"
						rows="3"
						placeholder="Caption üretilmedi — elle yazabilirsiniz…"
						:value="draft(a).caption"
						@input="onCaptionInput(a, $event.target.value)"
					></textarea>
					<input
						class="hashtag-input"
						type="text"
						placeholder="#etiket #etiket2"
						:value="draft(a).hashtags"
						@input="onHashtagInput(a, $event.target.value)"
					/>
					<button
						class="act-btn save-caption"
						:disabled="busy === a.id || !isDirty(a)"
						@click="saveCaption(a)"
					>💾 Caption kaydet</button>
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
import { ref, reactive, inject } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Breadcrumb from '@/Components/Breadcrumb.vue'
import CreativeNav from '../Components/CreativeNav.vue'

defineOptions({ layout: AppLayout })

defineProps({
	assets: { type: Object, default: () => ({ data: [], links: [], total: 0, last_page: 1 }) },
	stats: { type: Object, default: () => ({ total: 0, done: 0, failed: 0, pending: 0, approved: 0, success_rate: null, avg_render_ms: null, per_template: [] }) },
})

function fmtMs(ms) {
	if (ms == null) return '—'
	return ms >= 1000 ? (ms / 1000).toFixed(1) + 's' : ms + 'ms'
}

const showToast = inject('showToast', null)
const busy = ref(null)

// Asset id → düzenlenmekte olan caption taslağı (hashtag'ler boşlukla ayrık metin).
const drafts = reactive({})

function draft(a) {
	if (!drafts[a.id]) {
		drafts[a.id] = {
			caption: a.caption ?? '',
			hashtags: (a.hashtags ?? []).join(' '),
		}
	}
	return drafts[a.id]
}

function onCaptionInput(a, value) { draft(a).caption = value }
function onHashtagInput(a, value) { draft(a).hashtags = value }

function isDirty(a) {
	const d = draft(a)
	return d.caption !== (a.caption ?? '') || d.hashtags !== (a.hashtags ?? []).join(' ')
}

function parseHashtags(text) {
	return (text.match(/#?[\p{L}\p{N}_]+/gu) ?? [])
		.map(t => '#' + t.replace(/^#/, ''))
		.filter(t => t.length > 1)
}

function saveCaption(a) {
	if (busy.value) return
	busy.value = a.id
	const d = draft(a)
	router.put(`/creative/assets/${a.id}/caption`, {
		caption: d.caption || null,
		hashtags: parseHashtags(d.hashtags),
	}, {
		preserveScroll: true,
		preserveState: false,
		onSuccess: () => showToast?.({ type: 'success', title: 'Caption kaydedildi', message: a.product?.name ?? '' }),
		onError: (errs) => showToast?.({ type: 'error', title: 'Kaydedilemedi', message: Object.values(errs)[0] || 'Sunucu hatası.' }),
		onFinish: () => { busy.value = null },
	})
}

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
.header-btns { display: flex; gap: 10px; align-items: center; flex-shrink: 0; }

/* Analitik üst barı */
.stats-bar { display: flex; align-items: center; gap: 22px; flex-wrap: wrap; background: #fff; border: 1px solid #ebebf0; border-radius: 14px; padding: 14px 20px; margin-bottom: 18px; box-shadow: 0 1px 4px rgba(0,0,0,.04); }
.stat { display: flex; flex-direction: column; gap: 2px; }
.stat-val { font-size: 20px; font-weight: 700; color: #1a1a2e; line-height: 1; }
.stat-val.ok { color: #15803d; }
.stat-val.warn { color: #b45309; }
.stat-val.bad { color: #b91c1c; }
.stat-lbl { font-size: 11px; color: #999; text-transform: uppercase; letter-spacing: .03em; }
.stat-templates { margin-left: auto; min-width: 240px; max-width: 360px; }
.tpl-bars { display: flex; flex-direction: column; gap: 4px; margin-top: 5px; }
.tpl-bar { display: flex; align-items: center; gap: 8px; font-size: 11px; }
.tpl-bar-name { width: 90px; color: #555; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.tpl-bar-track { flex: 1; height: 6px; background: #f0f0f5; border-radius: 3px; overflow: hidden; }
.tpl-bar-fill { display: block; height: 100%; background: #7c3aed; border-radius: 3px; }
.tpl-bar-num { color: #999; font-family: 'SF Mono', Menlo, Consolas, monospace; white-space: nowrap; }

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

.asset-caption { padding: 0 13px 10px; display: flex; flex-direction: column; gap: 6px; }
.caption-input { width: 100%; resize: vertical; border: 1px solid #e8e8f0; border-radius: 8px; padding: 7px 9px; font-size: 12px; font-family: inherit; color: #333; line-height: 1.4; }
.caption-input:focus { outline: none; border-color: #d8d4f0; background: #faf8ff; }
.hashtag-input { width: 100%; border: 1px solid #e8e8f0; border-radius: 8px; padding: 6px 9px; font-size: 11.5px; font-family: 'SF Mono', Menlo, Consolas, monospace; color: #7c3aed; }
.hashtag-input:focus { outline: none; border-color: #d8d4f0; background: #faf8ff; }
.act-btn.save-caption { background: #ede9fe; color: #6d28d9; }
.act-btn.save-caption:hover:not(:disabled) { background: #ddd6fe; }

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
