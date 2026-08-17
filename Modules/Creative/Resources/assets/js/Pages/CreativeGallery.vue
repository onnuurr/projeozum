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

		<PageHeader title="Creative Galeri">
			<template #subtitle><strong>{{ assets.total }}</strong> üretilmiş görsel</template>
			<template #actions>
				<a
					v-if="stats.approved > 0"
					href="/creative/export"
					class="btn btn-ghost btn-with-icon"
					title="Onaylanmış görselleri ZIP olarak indir"
				>
					<Download :size="14" />
					ZIP indir ({{ stats.approved }})
				</a>
				<Button variant="primary" with-icon @click="router.visit('/creative/studio')">
					<template #leading><Plus :size="13" /></template>
					Yeni Üretim
				</Button>
			</template>
		</PageHeader>

		<!-- Analitik üst barı -->
		<div v-if="stats.total > 0" class="counter-grid">
			<div class="counter-card">
				<div class="counter-icon ci-primary">
					<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1.5" /><rect x="14" y="3" width="7" height="7" rx="1.5" /><rect x="3" y="14" width="7" height="7" rx="1.5" /><rect x="14" y="14" width="7" height="7" rx="1.5" /></svg>
				</div>
				<div><div class="counter-value">{{ stats.total }}</div><div class="counter-label">Toplam</div></div>
			</div>
			<div class="counter-card">
				<div class="counter-icon ci-green">
					<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20 6L9 17l-5-5" /></svg>
				</div>
				<div><div class="counter-value">{{ stats.done }}</div><div class="counter-label">Hazır</div></div>
			</div>
			<div class="counter-card">
				<div class="counter-icon ci-amber">
					<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" /><polyline points="12 6 12 12 16 14" /></svg>
				</div>
				<div><div class="counter-value">{{ stats.pending }}</div><div class="counter-label">İşlemde</div></div>
			</div>
			<div class="counter-card" :class="{ 'counter-danger': stats.failed > 0 }">
				<div class="counter-icon ci-red">
					<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" /><line x1="12" y1="8" x2="12" y2="12" /><line x1="12" y1="16" x2="12.01" y2="16" /></svg>
				</div>
				<div><div class="counter-value">{{ stats.failed }}</div><div class="counter-label">Başarısız</div></div>
			</div>
			<div class="counter-card">
				<div class="counter-icon ci-primary">
					<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 11-5.93-9.14" /><polyline points="22 4 12 14.01 9 11.01" /></svg>
				</div>
				<div><div class="counter-value">{{ stats.approved }}</div><div class="counter-label">Onaylı</div></div>
			</div>
			<div class="counter-card" v-if="stats.success_rate !== null">
				<div class="counter-icon ci-green">
					<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18" /><polyline points="17 6 23 6 23 12" /></svg>
				</div>
				<div><div class="counter-value">%{{ stats.success_rate }}</div><div class="counter-label">Başarı</div></div>
			</div>
			<div class="counter-card" v-if="stats.avg_render_ms !== null">
				<div class="counter-icon ci-purple">
					<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2" /></svg>
				</div>
				<div><div class="counter-value">{{ fmtMs(stats.avg_render_ms) }}</div><div class="counter-label">Ort. süre</div></div>
			</div>
		</div>

		<section v-if="stats.total > 0 && stats.per_template.length" class="sa-panel">
			<header class="sa-panel-head"><h2>Şablon Performansı</h2></header>
			<div class="tpl-bars">
				<div v-for="t in stats.per_template" :key="t.name" class="tpl-bar" :title="`${t.name}: ${t.done}/${t.total} hazır`">
					<span class="tpl-bar-name">{{ t.name }}</span>
					<span class="tpl-bar-track"><span class="tpl-bar-fill" :style="{ width: (t.total ? t.done / t.total * 100 : 0) + '%' }"></span></span>
					<span class="tpl-bar-num">{{ t.done }}/{{ t.total }}</span>
				</div>
			</div>
		</section>

		<div v-if="assets.data.length === 0" class="card">
			<div class="empty-block">Henüz görsel üretilmedi. Stüdyodan başlayın.</div>
		</div>

		<div v-else class="asset-grid">
			<div v-for="a in assets.data" :key="a.id" class="asset-card" :class="reviewClass(a)">
				<div class="asset-image">
					<img v-if="a.image_url" :src="a.image_url" :alt="a.product?.name" class="clickable" @click="openPreview(a)" />
					<button v-if="a.image_url" class="zoom-badge" title="Büyük önizleme" @click="openPreview(a)"><Maximize2 :size="14" /></button>
					<div v-else class="asset-placeholder">
						<AlertTriangle v-if="a.status === 'failed'" class="status-icon failed" :size="24" />
						<Loader2 v-else class="status-icon spin" :size="24" />
						<span class="status-text">{{ statusLabel(a.status) }}</span>
					</div>
					<Badge class="status-chip" :color="statusColor(a.status)" :label="statusLabel(a.status)" variant="tonal" />
					<Badge
						v-if="a.review_status !== 'pending'"
						class="review-chip"
						:color="reviewColor(a.review_status)"
						:label="reviewLabel(a.review_status)"
						variant="filled"
					/>
				</div>

				<div class="asset-meta">
					<span class="asset-product">{{ a.product?.name ?? '—' }}</span>
					<span class="asset-template">
						{{ a.template?.name ?? '—' }}
						<Tag v-if="a.format_label" class="asset-format" :label="a.format_label" color="primary" />
					</span>
					<span v-if="a.error" class="asset-error" :title="a.error">{{ a.error }}</span>
					<span class="asset-date">{{ a.created_at }}</span>
					<div v-if="a.review_tags && a.review_tags.length" class="review-tags">
						<Tag v-for="t in a.review_tags" :key="t" :label="t" color="danger" />
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
					><Save :size="12" /> Caption kaydet</button>
				</div>

				<div class="asset-actions">
					<template v-if="a.status === 'done' && can('creative.approve') && a.can_review">
						<button
							class="act-btn approve"
							:disabled="busy === a.id"
							@click="action(a, 'approve')"
						><Check :size="12" /> Onayla</button>
						<button
							class="act-btn reject"
							:disabled="busy === a.id"
							@click="reject(a)"
						><X :size="12" /> Reddet</button>
					</template>
					<button
						v-if="can('creative.generate')"
						class="act-btn regen"
						:disabled="busy === a.id || a.status === 'queued' || a.status === 'processing'"
						@click="action(a, 'regenerate')"
					><RotateCw :size="12" /> Yeniden</button>
				</div>
				<Link v-if="a.can_chat" :href="`/creative/assets/${a.id}/review-chat`" class="chat-link">
					<MessageCircle :size="13" /> AI ile Konuş <span v-if="a.review_chats?.length">({{ a.review_chats.length }} mesaj)</span>
				</Link>
			</div>
		</div>

		<!-- Büyük önizleme (lightbox) -->
		<Teleport to="body">
			<div v-if="preview" class="lightbox" @click.self="closePreview">
				<div class="lb-box">
					<button class="lb-close" @click="closePreview"><X :size="16" /></button>
					<div class="lb-img-wrap">
						<img :src="preview.image_url" :alt="preview.product?.name" />
					</div>
					<div class="lb-side">
						<h3 class="lb-title">{{ preview.product?.name ?? '—' }}</h3>
						<div class="lb-tags-meta">
							<Tag v-if="preview.format_label" :label="preview.format_label" color="primary" />
							<Tag v-if="preview.width" class="font-mono" :label="`${preview.width}×${preview.height}`" color="neutral" />
							<Tag v-if="preview.template?.name" :label="preview.template.name" color="neutral" />
						</div>
						<div v-if="preview.caption" class="lb-caption">{{ preview.caption }}</div>
						<div v-if="preview.hashtags?.length" class="lb-hashtags">{{ preview.hashtags.join(' ') }}</div>
						<a :href="preview.image_url" target="_blank" :download="`creative-${preview.id}.${extOf(preview.image_url)}`" class="btn btn-primary lb-download">
							<Download :size="14" />
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
import { Download, Plus, Maximize2, AlertTriangle, Loader2, Save, Check, X, RotateCw, MessageCircle } from 'lucide-vue-next'
import AppLayout from '@/Layouts/AppLayout.vue'
import Breadcrumb from '@/Components/Breadcrumb.vue'
import PageHeader from '@/Components/PageHeader.vue'
import Button from '@/Components/Button.vue'
import Badge from '@/Components/Badge.vue'
import Tag from '@/Components/Tag.vue'
import CreativeNav from '../Components/CreativeNav.vue'
import { useCan } from '@/composables/useCan'
import { openRejectDialog } from '../support/rejectDialog'
import { extOf } from '../support/mediaExt'

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

const STATUS_COLORS = { queued: 'warning', processing: 'info', done: 'success', failed: 'danger' }
const REVIEW_COLORS = { approved: 'success', rejected: 'danger' }
function statusColor(s) { return STATUS_COLORS[s] ?? 'neutral' }
function reviewColor(r) { return REVIEW_COLORS[r] ?? 'neutral' }

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
/* Analitik üst barı — sayım kartları (bkz. Superadmin/Dashboard.vue .counter-grid) */
.counter-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px; margin-bottom: 16px; }
.counter-card { display: flex; align-items: center; gap: 12px; background: #fff; border: 1px solid #ebebf0; border-radius: 12px; padding: 14px 16px; }
.counter-card.counter-danger { border-color: #fca5a5; background: rgba(220,38,38,.04); }
.counter-icon { width: 38px; height: 38px; border-radius: 10px; display: grid; place-items: center; flex-shrink: 0; }
.ci-primary { background: var(--color-primary-soft); color: var(--color-primary); }
.ci-green { background: rgba(22,163,74,.12); color: #16a34a; }
.ci-amber { background: rgba(202,138,4,.14); color: #ca8a04; }
.ci-red { background: rgba(220,38,38,.12); color: #dc2626; }
.ci-purple { background: var(--color-primary-soft); color: var(--color-primary-hover); }
.counter-value { font-size: 20px; font-weight: 700; line-height: 1.1; color: #1a1a2e; }
.counter-label { font-size: 12px; color: #888; margin-top: 2px; }

/* Panel bölümü (bkz. Superadmin/Dashboard.vue .sa-panel) */
.sa-panel { background: #fff; border: 1px solid #ebebf0; border-radius: 12px; padding: 18px 20px; margin-bottom: 18px; }
.sa-panel-head { display: flex; align-items: baseline; justify-content: space-between; margin-bottom: 14px; }
.sa-panel-head h2 { font-size: 15px; font-weight: 600; margin: 0; color: #1a1a2e; }
.tpl-bars { display: flex; flex-direction: column; gap: 8px; }
.tpl-bar { display: flex; align-items: center; gap: 10px; font-size: 12px; }
.tpl-bar-name { width: 110px; color: #555; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.tpl-bar-track { flex: 1; height: 6px; background: #ebebf0; border-radius: 999px; overflow: hidden; }
.tpl-bar-fill { display: block; height: 100%; background: var(--color-primary); border-radius: 999px; transition: width .4s ease; }
.tpl-bar-num { color: #999; font-family: 'SF Mono', Menlo, Consolas, monospace; white-space: nowrap; }

.card { background: #fff; border-radius: 12px; border: 1px solid #ebebf0; overflow: hidden; }
.empty-block { text-align: center; color: #aaa; padding: 48px 0; font-style: italic; font-size: 13px; }

.asset-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 16px; }
.asset-card { background: #fff; border: 1px solid #ebebf0; border-radius: 12px; overflow: hidden; display: flex; flex-direction: column; transition: border-color .15s, box-shadow .15s; }
.asset-card:hover { box-shadow: 0 2px 8px rgba(0,0,0,.06); }
.asset-card.is-approved { border-color: #bbf7d0; }
.asset-card.is-rejected { border-color: #fecaca; opacity: .75; }

.asset-image { position: relative; aspect-ratio: 1; background: #f5f5f8; display: flex; align-items: center; justify-content: center; overflow: hidden; }
.asset-image img { width: 100%; height: 100%; object-fit: cover; }
.asset-image img.clickable { cursor: zoom-in; }
.zoom-badge { position: absolute; bottom: 8px; right: 8px; width: 28px; height: 28px; border: none; border-radius: 8px; background: rgba(26,26,46,.6); color: #fff; font-size: 14px; cursor: pointer; display: flex; align-items: center; justify-content: center; opacity: 0; transition: opacity .15s; }
.asset-card:hover .zoom-badge { opacity: 1; }
.zoom-badge:hover { background: rgba(26,26,46,.85); }
.asset-format { margin-left: 6px; }
.asset-placeholder { display: flex; flex-direction: column; align-items: center; gap: 8px; color: #aaa; }
.status-icon.failed { color: #dc2626; }
.status-icon.spin { animation: spin 1.1s linear infinite; }
@keyframes spin { from { transform: rotate(0deg) } to { transform: rotate(360deg) } }
.status-text { font-size: 12px; }

.status-chip { position: absolute; top: 8px; left: 8px; }
.review-chip { position: absolute; top: 8px; right: 8px; }

.asset-meta { padding: 11px 13px; display: flex; flex-direction: column; gap: 3px; }
.asset-product { font-size: 13px; font-weight: 600; color: #1a1a2e; }
.asset-template { font-size: 11.5px; color: #888; }
.asset-error { font-size: 11px; color: #dc2626; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.asset-date { font-size: 11px; color: #aaa; font-family: 'SF Mono', Menlo, Consolas, monospace; }

.asset-caption { padding: 0 13px 10px; display: flex; flex-direction: column; gap: 6px; }
.caption-input { width: 100%; resize: vertical; border: 1px solid #e8e8f0; border-radius: 8px; padding: 7px 9px; font-size: 12px; font-family: inherit; color: #333; line-height: 1.4; }
.caption-input:focus { outline: none; border-color: color-mix(in srgb, var(--color-primary) 35%, transparent); background: var(--color-primary-soft); }
.hashtag-input { width: 100%; border: 1px solid #e8e8f0; border-radius: 8px; padding: 6px 9px; font-size: 11.5px; font-family: 'SF Mono', Menlo, Consolas, monospace; color: var(--color-primary); }
.hashtag-input:focus { outline: none; border-color: color-mix(in srgb, var(--color-primary) 35%, transparent); background: var(--color-primary-soft); }
.act-btn.save-caption { background: var(--color-primary-soft); color: var(--color-primary-hover); }
.act-btn.save-caption:hover:not(:disabled) { background: #ddd6fe; }

.asset-actions { display: flex; gap: 6px; padding: 0 13px 13px; flex-wrap: wrap; }
.act-btn { flex: 1; min-width: 70px; border: none; cursor: pointer; font-size: 12px; font-weight: 600; padding: 7px 6px; border-radius: 8px; font-family: inherit; transition: all .15s; display: inline-flex; align-items: center; justify-content: center; gap: 4px; }
.act-btn:disabled { opacity: .45; cursor: not-allowed; }
.act-btn.approve { background: #dcfce7; color: #15803d; }
.act-btn.approve:hover:not(:disabled) { background: #bbf7d0; }
.act-btn.reject { background: #fee2e2; color: #b91c1c; }
.act-btn.reject:hover:not(:disabled) { background: #fecaca; }
.act-btn.regen { background: #f0f0f5; color: #555; }
.act-btn.regen:hover:not(:disabled) { background: #e5e5ee; }

.review-tags { display: flex; flex-wrap: wrap; gap: 4px; margin-top: 4px; }
.chat-link { display: flex; align-items: center; justify-content: center; gap: 6px; padding: 8px 12px; margin: 0 13px 13px; background: #eef2ff; color: #4338ca; border-radius: 8px; font-size: 12px; font-weight: 600; text-decoration: none; }
.chat-link:hover { background: #e0e7ff; }

.pagination { display: flex; gap: 4px; justify-content: center; margin-top: 24px; flex-wrap: wrap; }
.page-btn { min-width: 34px; height: 34px; padding: 0 10px; border: 1px solid #e8e8f0; background: #fff; border-radius: 8px; font-size: 13px; color: #555; cursor: pointer; font-family: inherit; }
.page-btn:hover:not(.disabled):not(.active) { background: var(--color-primary-soft); border-color: color-mix(in srgb, var(--color-primary) 35%, transparent); }
.page-btn.active { background: var(--color-primary); border-color: var(--color-primary); color: #fff; font-weight: 700; }
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
.lb-caption { font-size: 13px; line-height: 1.5; color: #333; white-space: pre-wrap; }
.lb-hashtags { font-size: 12px; color: var(--color-primary); font-family: 'SF Mono', Menlo, Consolas, monospace; line-height: 1.5; }
.lb-download { margin-top: auto; justify-content: center; }
@media (max-width: 820px) { .lb-box { flex-direction: column; } .lb-img-wrap img { max-width: 86vw; max-height: 50vh; } .lb-side { width: auto; } }

/* ── Dar ekran (telefon) ── */
@media (max-width: 640px) {
	.counter-grid { grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); }
	.asset-grid { grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 10px; }
}
</style>
