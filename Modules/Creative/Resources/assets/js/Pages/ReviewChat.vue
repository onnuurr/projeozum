<template>
	<Head :title="`Sohbet — ${title}`" />
	<div class="chat-page">
		<div class="chat-context">
			<Link :href="backUrl" class="back-link">← Geri dön</Link>
			<div class="context-body">
				<img v-if="imageUrl" :src="imageUrl" :alt="title" class="context-thumb" />
				<div class="context-info">
					<h1 class="context-title">{{ title }}</h1>
					<div v-if="reviewTags && reviewTags.length" class="context-tags">
						<span class="context-tags-label">Düzeltilecek alanlar:</span>
						<Tag v-for="t in reviewTags" :key="t" :label="t" color="danger" />
					</div>
					<p v-if="reviewNote" class="context-note"><X :size="12" /> Reddedildi: {{ reviewNote }}</p>
					<p v-else-if="reviewTags && reviewTags.length" class="context-note"><X :size="12" /> Reddedildi</p>
				</div>
			</div>
		</div>

		<div v-if="suggestion" class="suggestion-banner">
			<div>
				<span class="suggestion-label">Önerilen düzeltme talimatı</span>
				<p class="suggestion-text">{{ suggestion }}</p>
			</div>
			<Button variant="primary" with-icon :loading="applying" @click="apply">
				<template #leading><Check :size="13" /></template>
				Bu talimatla yeniden üret
			</Button>
		</div>

		<div ref="logEl" class="chat-log">
			<EmptyState
				v-if="messages.length === 0"
				:icon="MessageCircle"
				title="Henüz mesaj yok"
				hint="Asistana neyin düzeltilmesi gerektiğini sorarak başlayın."
			/>
			<div v-for="m in messages" :key="m.id" class="chat-row" :class="m.role">
				<div class="chat-avatar">
					<Bot v-if="m.role === 'assistant'" :size="15" />
					<template v-else>{{ m.user_name?.[0] ?? '?' }}</template>
				</div>
				<div class="chat-bubble">
					<span class="chat-author">{{ m.role === 'assistant' ? 'AI Asistan' : (m.user_name || 'Kullanıcı') }}</span>
					<p class="chat-text">{{ m.content }}</p>
					<span class="chat-time">{{ m.created_at }}</span>
				</div>
			</div>
			<div v-if="sending" class="chat-row assistant">
				<div class="chat-avatar"><Bot :size="15" /></div>
				<div class="chat-bubble typing"><span></span><span></span><span></span></div>
			</div>
		</div>

		<form class="chat-input-bar" @submit.prevent="send">
			<textarea
				ref="inputEl"
				v-model="draft"
				rows="1"
				placeholder="Mesajınızı yazın… (Enter=gönder, Shift+Enter=yeni satır)"
				:disabled="sending"
				@keydown.enter.exact.prevent="send"
				@input="autoGrow"
			></textarea>
			<Button type="submit" variant="primary" :disabled="sending || !draft.trim()">Gönder</Button>
		</form>
	</div>
</template>

<script setup>
import { ref, computed, inject, nextTick, onMounted } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { X, Check, Bot, MessageCircle } from 'lucide-vue-next'
import AppLayout from '@/Layouts/AppLayout.vue'
import Button from '@/Components/Button.vue'
import Tag from '@/Components/Tag.vue'
import EmptyState from '@/Components/EmptyState.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
	subjectType: { type: String, required: true },
	subjectId: { type: Number, required: true },
	title: { type: String, default: '' },
	imageUrl: { type: String, default: null },
	reviewNote: { type: String, default: null },
	reviewTags: { type: Array, default: () => [] },
	backUrl: { type: String, required: true },
	chats: { type: Array, default: () => [] },
	suggestion: { type: String, default: null },
})

const showToast = inject('showToast', null)

const messages = ref([...props.chats])
const draft = ref('')
const sending = ref(false)
const applying = ref(false)
const logEl = ref(null)
const inputEl = ref(null)

const base = computed(() => {
	if (props.subjectType === 'mannequin') return `/creative/mannequins/${props.subjectId}`
	if (props.subjectType === 'asset') return `/creative/assets/${props.subjectId}`
	return `/creative/tryon/${props.subjectId}`
})

function scrollToBottom() {
	nextTick(() => { if (logEl.value) logEl.value.scrollTop = logEl.value.scrollHeight })
}

onMounted(scrollToBottom)

function autoGrow(e) {
	e.target.style.height = 'auto'
	e.target.style.height = Math.min(e.target.scrollHeight, 160) + 'px'
}

function send() {
	const text = draft.value.trim()
	if (!text || sending.value) return
	sending.value = true

	router.post(`${base.value}/review-chat`, { message: text }, {
		preserveScroll: true,
		preserveState: true,
		only: ['chats', 'suggestion'],
		onSuccess: (page) => {
			messages.value = page.props.chats ?? messages.value
			draft.value = ''
			scrollToBottom()
		},
		onError: (errs) => showToast?.({ type: 'error', title: 'Gönderilemedi', message: Object.values(errs)[0] || 'Sunucu hatası.' }),
		onFinish: () => { sending.value = false },
	})
}

function apply() {
	if (applying.value) return
	applying.value = true
	router.post(`${base.value}/review-chat/apply`, {}, {
		onError: (errs) => showToast?.({ type: 'error', title: 'Uygulanamadı', message: Object.values(errs)[0] || 'Sunucu hatası.' }),
		onFinish: () => { applying.value = false },
	})
}
</script>

<style scoped>
.chat-page { display: flex; flex-direction: column; height: calc(100vh - 64px); max-width: 780px; margin: 0 auto; }
.chat-context { padding: 16px 0 12px; border-bottom: 1px solid var(--color-outline-variant); }
.back-link { font-size: 12px; color: var(--color-primary); font-weight: 600; text-decoration: none; }
.context-body { display: flex; align-items: center; gap: 14px; margin-top: 10px; }
.context-thumb { width: 56px; height: 56px; border-radius: 10px; object-fit: cover; background: var(--color-surface-container-low); }
.context-title { font-size: 18px; font-weight: 700; color: var(--color-ink); }
.context-tags { display: flex; flex-wrap: wrap; align-items: center; gap: 5px; margin-top: 5px; }
.context-tags-label { font-size: 11px; font-weight: 600; color: var(--color-muted); }
.context-note { display: flex; align-items: center; gap: 4px; font-size: 12.5px; color: var(--color-danger); margin-top: 2px; }

.suggestion-banner { position: sticky; top: 0; z-index: 5; display: flex; align-items: center; justify-content: space-between; gap: 16px; background: color-mix(in srgb, var(--color-warning) 8%, transparent); border: 1px solid color-mix(in srgb, var(--color-warning) 35%, transparent); border-radius: 12px; padding: 12px 16px; margin: 12px 0; }
.suggestion-label { font-size: 10.5px; font-weight: 700; color: var(--color-warning); text-transform: uppercase; letter-spacing: .03em; }
.suggestion-text { font-size: 13px; color: var(--color-ink); margin-top: 2px; }

.chat-log { flex: 1; overflow-y: auto; padding: 16px 0; display: flex; flex-direction: column; gap: 14px; }
.chat-row { display: flex; gap: 10px; max-width: 78%; }
.chat-row.user { align-self: flex-end; flex-direction: row-reverse; }
.chat-avatar { width: 30px; height: 30px; border-radius: 50%; background: var(--color-surface-container-low); display: flex; align-items: center; justify-content: center; font-size: 14px; flex-shrink: 0; color: var(--color-muted); }
.chat-bubble { background: var(--color-surface-container-low); border-radius: 14px; padding: 10px 14px; }
.chat-row.user .chat-bubble { background: var(--color-primary-soft); }
.chat-row.assistant .chat-bubble { background: color-mix(in srgb, var(--color-info) 8%, transparent); }
.chat-author { display: block; font-size: 10.5px; font-weight: 700; color: var(--color-muted); margin-bottom: 3px; }
.chat-text { font-size: 13.5px; color: var(--color-ink); white-space: pre-wrap; line-height: 1.5; }
.chat-time { display: block; font-size: 10px; color: var(--color-muted); margin-top: 4px; }
.chat-bubble.typing { display: flex; gap: 4px; align-items: center; padding: 12px 16px; }
.chat-bubble.typing span { width: 6px; height: 6px; border-radius: 50%; background: var(--color-muted); animation: pulse 1.2s infinite ease-in-out; }
.chat-bubble.typing span:nth-child(2) { animation-delay: .2s; }
.chat-bubble.typing span:nth-child(3) { animation-delay: .4s; }
@keyframes pulse { 0%, 80%, 100% { opacity: .3; } 40% { opacity: 1; } }

.chat-input-bar { position: sticky; bottom: 0; display: flex; gap: 10px; align-items: flex-end; background: var(--color-surface); border-top: 1px solid var(--color-outline-variant); padding: 12px 0; }
.chat-input-bar textarea { flex: 1; resize: none; border: 1px solid var(--color-outline-variant); border-radius: 10px; padding: 10px 12px; font-family: inherit; font-size: 13.5px; outline: none; max-height: 160px; }
.chat-input-bar textarea:focus { border-color: var(--color-primary); }

/* ── Dar ekran (telefon) ── */
@media (max-width: 640px) {
	.chat-page { height: calc(100vh - 48px); }
	.suggestion-banner { flex-wrap: wrap; }
	.suggestion-banner .btn { width: 100%; justify-content: center; }
	.chat-row { max-width: 92%; }
}
</style>
