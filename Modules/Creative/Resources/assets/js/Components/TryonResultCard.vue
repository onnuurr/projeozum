<script setup>
import { computed, ref } from 'vue'
import { Link } from '@inertiajs/vue3'
import { Maximize2, Star, ZoomIn, Check, X, ClipboardList, MessageCircle, RefreshCw, AlertTriangle, CheckCircle2, XCircle } from 'lucide-vue-next'
import Badge from '@/Components/Badge.vue'
import Button from '@/Components/Button.vue'

// Ürün Giydirme sonuç kartı. Kontrol bulguları kartta yalnız rozet (✓ / ⚠ n / ✕); ayrıntı rozete
// tıklayınca açılan küçük pencerede. Kombin bilgisi kartta değil, set başlığında (bkz. CreativeTryon).
const props = defineProps({
	r: { type: Object, required: true },
	canManage: { type: Boolean, default: false },
	// Ürün detayında ürün adı / manken set başlığında yazdığı için gizlenir.
	showProduct: { type: Boolean, default: true },
	showMannequin: { type: Boolean, default: true },
	busyReview: { type: [Number, null], default: null },
	stopBusy: { type: Boolean, default: false },
	mockupBusy: { type: Boolean, default: false },
})
const emit = defineEmits(['preview', 'approve', 'reject', 'stop', 'cover', 'closeups', 'destroy', 'rerun'])

const STATUS_LABELS = { queued: 'Sırada', generating: 'Üretiliyor', done: 'Hazır', failed: 'Başarısız' }
const STATUS_COLORS = { queued: 'neutral', generating: 'warning', done: 'success', failed: 'danger' }
const REVIEW_LABELS = { pending: 'Onay Bekliyor', approved: 'Onaylı', rejected: 'Reddedildi' }
const REVIEW_COLORS = { pending: 'warning', approved: 'success', rejected: 'danger' }
const QA_LABELS = { pass: 'Kontrol geçti', warn: 'Kontrol uyarısı', fail: 'Kontrolden kaldı', unknown: 'Kontrol yapılamadı' }

const detailsOpen = ref(false)
const qa = computed(() => props.r.qa)
const qaTone = computed(() => ({ pass: 'pass', warn: 'warn', unknown: 'warn', fail: 'fail' })[qa.value?.status] ?? null)
const sub = computed(() => [props.showMannequin ? props.r.mannequin_name : null, props.r.pose_label, props.r.shot_label].filter(Boolean).join(' · '))
const hasDetails = computed(() => qa.value || props.r.creator_name || props.r.tryon_model)
const active = computed(() => props.r.status === 'queued' || props.r.status === 'generating')
</script>

<template>
	<div class="result-card">
		<div class="result-thumb">
			<img v-if="r.image_url" class="clickable" :src="r.image_url" :alt="r.product_name" loading="lazy" decoding="async" @click="emit('preview', r)" />
			<span v-else class="no-preview">{{ STATUS_LABELS[r.status] || r.status }}</span>
			<button v-if="r.image_url" class="zoom-badge" title="Büyük önizleme" @click="emit('preview', r)"><Maximize2 :size="13" /></button>
			<Badge v-if="r.status !== 'done'" class="status-badge" :color="STATUS_COLORS[r.status] ?? 'neutral'" :label="STATUS_LABELS[r.status] || r.status" variant="filled" />
			<Badge v-if="r.is_cover" class="cover-badge" :icon="Star" label="Kapak" color="neutral" variant="filled" />
			<Badge v-if="r.review_status" class="review-badge" :color="REVIEW_COLORS[r.review_status] ?? 'neutral'" :label="REVIEW_LABELS[r.review_status] || r.review_status" variant="filled" />
		</div>

		<div class="result-meta">
			<span v-if="showProduct" class="result-product">{{ r.product_name }}</span>
			<div class="meta-row">
				<span class="result-sub" :title="sub">{{ sub }}</span>
				<!-- Kontrol rozeti: tıklayınca bulgular + üreten / model -->
				<button v-if="hasDetails" type="button" class="qa-chip" :class="qaTone ? `is-${qaTone}` : 'is-info'"
					:title="qa ? QA_LABELS[qa.status] : 'Ayrıntılar'" @click="detailsOpen = !detailsOpen">
					<CheckCircle2 v-if="qaTone === 'pass'" :size="12" />
					<XCircle v-else-if="qaTone === 'fail'" :size="12" />
					<AlertTriangle v-else-if="qaTone === 'warn'" :size="12" />
					<ClipboardList v-else :size="12" />
					<span v-if="qa?.issues?.length">{{ qa.issues.length }}</span>
				</button>
			</div>

			<div v-if="detailsOpen" class="details-pop" @click.stop>
				<div class="pop-head">
					<strong>{{ qa ? QA_LABELS[qa.status] : 'Ayrıntılar' }}<template v-if="qa?.attempts > 1"> · yeniden denendi</template></strong>
					<button type="button" class="pop-close" title="Kapat" @click="detailsOpen = false"><X :size="12" /></button>
				</div>
				<ul v-if="qa?.issues?.length" class="pop-issues">
					<li v-for="(i, idx) in qa.issues" :key="idx">{{ i }}</li>
				</ul>
				<p v-if="r.creator_name" class="pop-line">Üreten: {{ r.creator_name }}{{ r.is_own ? ' (siz)' : '' }}</p>
				<p v-if="r.tryon_model" class="pop-line mono">{{ r.tryon_model }}</p>
			</div>

			<div v-if="r.review_tags?.length" class="review-tags" :title="r.review_tags.join(', ')">
				<span class="tag">{{ r.review_tags[0] }}</span>
				<span v-if="r.review_tags.length > 1" class="tag more">+{{ r.review_tags.length - 1 }}</span>
			</div>
			<p v-if="r.error" class="result-error" :title="r.error"><AlertTriangle :size="11" /> {{ r.error }}</p>

			<div v-if="r.can_review" class="review-actions">
				<Button variant="success" size="sm" :disabled="busyReview === r.id" @click="emit('approve', r)"><template #leading><Check :size="11" /></template>Onayla</Button>
				<Button variant="danger" size="sm" :disabled="busyReview === r.id" @click="emit('reject', r)"><template #leading><X :size="11" /></template>Reddet</Button>
			</div>
			<div v-if="active && canManage" class="result-actions">
				<button type="button" class="link-btn danger" :disabled="stopBusy" @click="emit('stop', r)">Durdur</button>
			</div>
			<div v-if="(r.status === 'done' || r.status === 'failed') && canManage" class="result-actions">
				<template v-if="r.status === 'done' && r.review_status === 'approved'">
					<button v-if="!r.is_cover" type="button" class="link-btn" @click="emit('cover', r)">Kapak yap</button>
					<span v-else class="is-cover-note">Kapak</span>
					<button v-if="r.shot === 'full'" type="button" class="link-btn" @click="emit('closeups', r)">Yakın plan</button>
				</template>
				<button type="button" class="link-btn danger" @click="emit('destroy', r)">Sil</button>
			</div>
		</div>

		<div class="card-links">
			<Link :href="`/creative/tryon/${r.id}`" class="card-link" title="Üretim detayı"><ClipboardList :size="13" /> Detay</Link>
			<Link v-if="r.can_chat" :href="`/creative/tryon/${r.id}/review-chat`" class="card-link" title="Ret sohbeti">
				<MessageCircle :size="13" /> AI<span v-if="r.review_chats?.length"> ({{ r.review_chats.length }})</span>
			</Link>
		</div>
		<!-- Faz 5: reddedilen mokap → ret nedeninin aşamasından düzeltmeyle yeniden üret -->
		<button v-if="r.rerun && canManage" type="button" class="rerun-btn" :disabled="mockupBusy" :title="`${r.rerun.label} aşamasından yeniden üret`" @click="emit('rerun', r)">
			<RefreshCw :size="12" /> {{ r.rerun.label }}dan yeniden üret
		</button>
	</div>
</template>

<style scoped>
.result-card { position: relative; border: 1px solid var(--color-outline-variant); border-radius: 12px; overflow: visible; background: var(--color-surface); display: flex; flex-direction: column; }
.result-thumb { position: relative; aspect-ratio: 3/4; background: var(--color-surface-container-low); display: flex; align-items: center; justify-content: center; border-radius: 12px 12px 0 0; overflow: hidden; }
.result-thumb img { width: 100%; height: 100%; object-fit: cover; }
.result-thumb img.clickable { cursor: zoom-in; }
.zoom-badge { position: absolute; bottom: 6px; right: 6px; width: 26px; height: 26px; border: none; border-radius: 8px; background: rgba(26,26,46,.6); color: #fff; cursor: pointer; display: flex; align-items: center; justify-content: center; opacity: 0; transition: opacity .15s; z-index: 1; }
.result-card:hover .zoom-badge { opacity: 1; }
.no-preview { font-size: 11px; color: var(--color-muted); font-weight: 700; }
.status-badge { position: absolute; top: 6px; left: 6px; }
.cover-badge { position: absolute; top: 6px; right: 6px; }
.review-badge { position: absolute; bottom: 6px; left: 6px; }
.result-meta { padding: 7px 9px; flex: 1; }
.result-product { font-size: 12px; font-weight: 700; color: var(--color-ink); display: block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.meta-row { display: flex; align-items: center; gap: 6px; }
.result-sub { flex: 1; min-width: 0; font-size: 11px; color: var(--color-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.qa-chip { display: inline-flex; align-items: center; gap: 3px; border: none; border-radius: 10px; padding: 2px 6px; font-size: 10.5px; font-weight: 700; cursor: pointer; font-family: inherit; flex-shrink: 0; }
.qa-chip.is-pass { background: color-mix(in srgb, var(--color-success) 14%, transparent); color: var(--color-success); }
.qa-chip.is-warn { background: color-mix(in srgb, var(--color-warning) 16%, transparent); color: var(--color-warning); }
.qa-chip.is-fail { background: color-mix(in srgb, var(--color-danger) 14%, transparent); color: var(--color-danger); }
.qa-chip.is-info { background: var(--color-surface-container-low); color: var(--color-muted); }
.details-pop { position: absolute; left: 6px; right: 6px; z-index: 5; margin-top: 4px; padding: 8px 10px; border-radius: 10px; background: var(--color-surface); border: 1px solid var(--color-outline-variant); box-shadow: 0 8px 24px rgba(0,0,0,.12); font-size: 11px; line-height: 1.45; }
.pop-head { display: flex; align-items: center; justify-content: space-between; gap: 6px; margin-bottom: 4px; }
.pop-close { border: none; background: none; cursor: pointer; color: var(--color-muted); padding: 2px; display: inline-flex; }
.pop-issues { margin: 0 0 4px; padding-left: 15px; color: var(--color-ink); max-height: 160px; overflow: auto; }
.pop-line { margin: 2px 0 0; color: var(--color-muted); }
.mono { font-family: monospace; font-size: 10px; }
.review-tags { display: flex; gap: 4px; margin-top: 4px; }
.tag { font-size: 10px; font-weight: 600; padding: 1px 6px; border-radius: 8px; background: color-mix(in srgb, var(--color-danger) 12%, transparent); color: var(--color-danger); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 110px; }
.tag.more { max-width: none; }
.result-error { display: flex; align-items: center; gap: 4px; font-size: 10px; color: var(--color-danger); margin: 4px 0 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.review-actions { display: flex; gap: 6px; margin-top: 6px; }
.result-actions { display: flex; align-items: center; gap: 10px; margin-top: 6px; }
.link-btn { background: none; border: none; color: var(--color-primary); font-size: 12px; font-weight: 600; cursor: pointer; font-family: inherit; padding: 0; }
.link-btn.danger { color: var(--color-danger); margin-left: auto; }
.is-cover-note { font-size: 11px; color: var(--color-success); font-weight: 600; }
.card-links { display: flex; gap: 6px; padding: 0 9px 8px; }
.card-link { flex: 1; display: inline-flex; align-items: center; justify-content: center; gap: 4px; padding: 5px 6px; border-radius: 7px; background: var(--color-primary-soft); color: var(--color-primary-hover); font-size: 11px; font-weight: 600; text-decoration: none; }
.rerun-btn { margin: 0 9px 9px; display: inline-flex; align-items: center; justify-content: center; gap: 4px; padding: 5px 6px; border: none; border-radius: 7px; background: color-mix(in srgb, var(--color-warning) 14%, transparent); color: var(--color-ink); font-size: 11px; font-weight: 600; cursor: pointer; font-family: inherit; }
.rerun-btn:disabled { opacity: .6; cursor: default; }
</style>
