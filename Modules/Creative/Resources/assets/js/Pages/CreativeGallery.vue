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
					<img v-if="a.image_url" :src="a.image_url" :alt="a.product?.name" class="clickable" @click="openPreview(a)" />
					<button v-if="a.image_url" class="zoom-badge" title="Büyük önizleme" @click="openPreview(a)">⤢</button>
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
					<span class="asset-template">
						{{ a.template?.name ?? '—' }}
						<span v-if="a.format_label" class="asset-format">{{ a.format_label }}</span>
					</span>
					<span v-if="a.error" class="asset-error" :title="a.error">{{ a.error }}</span>
					<span class="asset-date">{{ a.created_at }}</span>
					<div v-if="a.review_tags && a.review_tags.length" class="review-tags">
						<span v-for="t in a.review_tags" :key="t" class="review-tag">{{ t }}</span>
					</div>
				</div>

				<div v-if="a.status === 'done' && can('creative.generate')" class="asset-caption">
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
					<template v-if="a.status === 'done' && can('creative.approve') && a.can_review">
						<button
							class="act-btn approve"
							:disabled="busy === a.id"
							@click="action(a, 'approve')"
						>✓ Onayla</button>
						<button
							class="act-btn reject"
							:disabled="busy === a.id"
							@click="reject(a)"
						>✕ Reddet</button>
					</template>
					<button
						v-if="can('creative.generate')"
						class="act-btn regen"
						:disabled="busy === a.id || a.status === 'queued' || a.status === 'processing'"
						@click="action(a, 'regenerate')"
					>↻ Yeniden</button>
				</div>
				<Link v-if="a.can_chat" :href="`/creative/assets/${a.id}/review-chat`" class="chat-link">
					💬 AI ile Konuş <span v-if="a.review_chats?.length">({{ a.review_chats.length }} mesaj)</span>
				</Link>
			</div>
		</div>

		<!-- Büyük önizleme (lightbox) -->
		<Teleport to="body">
			<div v-if="preview" class="lightbox" @click.self="closePreview">
				<div class="lb-box">
					<button class="lb-close" @click="closePreview">✕</button>
					<div class="lb-img-wrap">
						<img :src="preview.image_url" :alt="preview.product?.name" />
					</div>
					<div class="lb-side">
						<h3 class="lb-title">{{ preview.product?.name ?? '—' }}</h3>
						<div class="lb-tags-meta">
							<span v-if="preview.format_label" class="lb-chip">{{ preview.format_label }}</span>
							<span v-if="preview.width" class="lb-chip dim">{{ preview.width }}×{{ preview.height }}</span>
							<span v-if="preview.template?.name" class="lb-chip ghost">{{ preview.template.name }}</span>
						</div>
						<div v-if="preview.caption" class="lb-caption">{{ preview.caption }}</div>
						<div v-if="preview.hashtags?.length" class="lb-hashtags">{{ preview.hashtags.join(' ') }}</div>
						<a :href="preview.image_url" target="_blank" :download="`creative-${preview.id}.png`" class="btn btn-primary lb-download">
							<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M7 10l5 5 5-5M12 15V3" /></svg>
							İndir
						</a>
					</div>
				</div>
			</div>
		</Teleport>

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
import { ref, reactive, inject, onMounted, onUnmounted } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Breadcrumb from '@/Components/Breadcrumb.vue'
import CreativeNav from '../Components/CreativeNav.vue'
import { useCan } from '@/composables/useCan'
import { openRejectDialog } from '../support/rejectDialog'

defineOptions({ layout: AppLayout })

const { can } = useCan()

const props = defineProps({
	assets: { type: Object, default: () => ({ data: [], links: [], total: 0, last_page: 1 }) },
	stats: { type: Object, default: () => ({ total: 0, done: 0, failed: 0, pending: 0, approved: 0, success_rate: null, avg_render_ms: null, per_template: [] }) },
	rejectionReasons: { type: Array, default: () => [] },
})

const $swal = inject('$swal')

function fmtMs(ms) {
	if (ms == null) return '—'
	return ms >= 1000 ? (ms / 1000).toFixed(1) + 's' : ms + 'ms'
}

const showToast = inject('showToast', null)
const busy = ref(null)
const preview = ref(null)

function openPreview(a) {
	if (a.image_url) preview.value = a
}
function closePreview() { preview.value = null }

function onKey(e) { if (e.key === 'Escape') closePreview() }
onMounted(() => window.addEventListener('keydown', onKey))
onUnmounted(() => window.removeEventListener('keydown', onKey))

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
		onError: (errs) => showToast?.({ type: 'error', title: 'Kaydedilemedi', message: Object.values(errs)[0] || 'Sunucu hatası.' }),
		onFinish: () => { busy.value = null },
	})
}

const STATUS_LABELS = { queued: 'Kuyrukta', processing: 'İşleniyor', done: 'Hazır', failed: 'Başarısız' }
const REVIEW_LABELS = { pending: 'Bekliyor', approved: 'Onaylı', rejected: 'Reddedildi' }

function statusLabel(s) { return STATUS_LABELS[s] ?? s }
function reviewLabel(r) { return REVIEW_LABELS[r] ?? r }
function reviewClass(a) { return a.review_status === 'approved' ? 'is-approved' : a.review_status === 'rejected' ? 'is-rejected' : '' }

function action(a, kind) {
	if (busy.value) return
	busy.value = a.id
	router.post(`/creative/assets/${a.id}/${kind}`, {}, {
		preserveScroll: true,
		preserveState: false,
		onError: (errs) => {
			showToast?.({ type: 'error', title: 'İşlem başarısız', message: Object.values(errs)[0] || 'Sunucu hatası.' })
		},
		onFinish: () => { busy.value = null },
	})
}

async function reject(a) {
	if (busy.value) return
	const result = await openRejectDialog($swal, props.rejectionReasons)
	if (!result) return
	busy.value = a.id
	router.post(`/creative/assets/${a.id}/reject`, { reason: result.reason, tags: result.tags }, {
		preserveScroll: true,
		preserveState: false,
		onError: (errs) => {
			showToast?.({ type: 'error', title: 'Reddedilemedi', message: Object.values(errs)[0] || 'Sunucu hatası.' })
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
.tpl-bar-fill { display: block; height: 100%; background: rgb(var(--color-primary)); border-radius: 3px; }
.tpl-bar-num { color: #999; font-family: 'SF Mono', Menlo, Consolas, monospace; white-space: nowrap; }

.card { background: #fff; border-radius: 16px; border: 1px solid #ebebf0; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,.04); }
.empty-block { text-align: center; color: #aaa; padding: 48px 0; font-style: italic; font-size: 13px; }

.asset-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 16px; }
.asset-card { background: #fff; border: 1px solid #ebebf0; border-radius: 14px; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,.04); display: flex; flex-direction: column; transition: border-color .15s; }
.asset-card.is-approved { border-color: #bbf7d0; }
.asset-card.is-rejected { border-color: #fecaca; opacity: .75; }

.asset-image { position: relative; aspect-ratio: 1; background: #f5f5f8; display: flex; align-items: center; justify-content: center; overflow: hidden; }
.asset-image img { width: 100%; height: 100%; object-fit: cover; }
.asset-image img.clickable { cursor: zoom-in; }
.zoom-badge { position: absolute; bottom: 8px; right: 8px; width: 28px; height: 28px; border: none; border-radius: 8px; background: rgba(26,26,46,.6); color: #fff; font-size: 14px; cursor: pointer; display: flex; align-items: center; justify-content: center; opacity: 0; transition: opacity .15s; }
.asset-card:hover .zoom-badge { opacity: 1; }
.zoom-badge:hover { background: rgba(26,26,46,.85); }
.asset-format { display: inline-block; margin-left: 6px; padding: 1px 6px; border-radius: 5px; background: rgb(var(--color-primary-soft)); color: rgb(var(--color-primary-hover)); font-size: 10px; font-weight: 700; }
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
.caption-input:focus { outline: none; border-color: rgb(var(--color-primary) / .35); background: rgb(var(--color-primary-soft)); }
.hashtag-input { width: 100%; border: 1px solid #e8e8f0; border-radius: 8px; padding: 6px 9px; font-size: 11.5px; font-family: 'SF Mono', Menlo, Consolas, monospace; color: rgb(var(--color-primary)); }
.hashtag-input:focus { outline: none; border-color: rgb(var(--color-primary) / .35); background: rgb(var(--color-primary-soft)); }
.act-btn.save-caption { background: rgb(var(--color-primary-soft)); color: rgb(var(--color-primary-hover)); }
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

.review-tags { display: flex; flex-wrap: wrap; gap: 4px; margin-top: 4px; }
.review-tag { font-size: 10px; font-weight: 600; padding: 2px 7px; border-radius: 10px; background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
.chat-link { display: block; text-align: center; padding: 8px 12px; margin: 0 13px 13px; background: #eef2ff; color: #4338ca; border-radius: 8px; font-size: 12px; font-weight: 600; text-decoration: none; }
.chat-link:hover { background: #e0e7ff; }

.pagination { display: flex; gap: 4px; justify-content: center; margin-top: 24px; flex-wrap: wrap; }
.page-btn { min-width: 34px; height: 34px; padding: 0 10px; border: 1px solid #e8e8f0; background: #fff; border-radius: 8px; font-size: 13px; color: #555; cursor: pointer; font-family: inherit; }
.page-btn:hover:not(.disabled):not(.active) { background: rgb(var(--color-primary-soft)); border-color: rgb(var(--color-primary) / .35); }
.page-btn.active { background: rgb(var(--color-primary)); border-color: rgb(var(--color-primary)); color: #fff; font-weight: 700; }
.page-btn.disabled { opacity: .4; cursor: not-allowed; }

/* Lightbox */
.lightbox { position: fixed; inset: 0; z-index: 9999; background: rgba(15,15,25,.82); display: flex; align-items: center; justify-content: center; padding: 32px; backdrop-filter: blur(3px); }
.lb-box { display: flex; gap: 0; max-width: 1100px; max-height: 90vh; background: #fff; border-radius: 16px; overflow: hidden; box-shadow: 0 24px 80px rgba(0,0,0,.4); position: relative; }
.lb-close { position: absolute; top: 12px; right: 12px; z-index: 2; width: 34px; height: 34px; border: none; border-radius: 50%; background: rgba(255,255,255,.9); color: #1a1a2e; font-size: 16px; cursor: pointer; box-shadow: 0 2px 8px rgba(0,0,0,.2); }
.lb-close:hover { background: #fff; }
.lb-img-wrap { background: #11111b repeating-conic-gradient(#1a1a26 0% 25%, #15151f 0% 50%) 0 / 24px 24px; display: flex; align-items: center; justify-content: center; min-width: 0; }
.lb-img-wrap img { max-width: 62vw; max-height: 90vh; object-fit: contain; display: block; }
.lb-side { width: 300px; flex-shrink: 0; padding: 22px; display: flex; flex-direction: column; gap: 14px; overflow-y: auto; }
.lb-title { font-size: 17px; font-weight: 700; color: #1a1a2e; }
.lb-tags-meta { display: flex; flex-wrap: wrap; gap: 6px; }
.lb-chip { font-size: 11px; font-weight: 700; padding: 3px 9px; border-radius: 6px; background: rgb(var(--color-primary-soft)); color: rgb(var(--color-primary-hover)); }
.lb-chip.dim { background: #f0f0f5; color: #555; font-family: 'SF Mono', Menlo, Consolas, monospace; }
.lb-chip.ghost { background: #f5f5f8; color: #888; }
.lb-caption { font-size: 13px; line-height: 1.5; color: #333; white-space: pre-wrap; }
.lb-hashtags { font-size: 12px; color: rgb(var(--color-primary)); font-family: 'SF Mono', Menlo, Consolas, monospace; line-height: 1.5; }
.lb-download { margin-top: auto; justify-content: center; }
@media (max-width: 820px) { .lb-box { flex-direction: column; } .lb-img-wrap img { max-width: 86vw; max-height: 50vh; } .lb-side { width: auto; } }

/* ── Dar ekran (telefon) ── */
@media (max-width: 640px) {
	.page-header { flex-wrap: wrap; }
	.header-btns { width: 100%; flex-wrap: wrap; }
	.header-btns .btn { flex: 1 1 auto; justify-content: center; }
	.stat-templates { min-width: 0; max-width: none; width: 100%; margin-left: 0; }
	.asset-grid { grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 10px; }
}
</style>
