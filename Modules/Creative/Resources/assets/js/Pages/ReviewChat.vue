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
						<span v-for="t in reviewTags" :key="t" class="context-tag">{{ t }}</span>
					</div>
					<p v-if="reviewNote" class="context-note">✕ Reddedildi: {{ reviewNote }}</p>
					<p v-else-if="reviewTags && reviewTags.length" class="context-note">✕ Reddedildi</p>
				</div>
			</div>
		</div>

		<div v-if="suggestion" class="suggestion-banner">
			<div>
				<span class="suggestion-label">Önerilen düzeltme talimatı</span>
				<p class="suggestion-text">{{ suggestion }}</p>
			</div>
			<button class="btn btn-primary" :disabled="applying" @click="apply">
				{{ applying ? 'Uygulanıyor…' : '✓ Bu talimatla yeniden üret' }}
			</button>
		</div>

		<div ref="logEl" class="chat-log">
			<div v-if="messages.length === 0" class="empty-log">
				Henüz mesaj yok — asistana neyin düzeltilmesi gerektiğini sorarak başlayın.
			</div>
			<div v-for="m in messages" :key="m.id" class="chat-row" :class="m.role">
				<div class="chat-avatar">{{ m.role === 'assistant' ? '🤖' : (m.user_name?.[0] ?? '🙂') }}</div>
				<div class="chat-bubble">
					<span class="chat-author">{{ m.role === 'assistant' ? 'AI Asistan' : (m.user_name || 'Kullanıcı') }}</span>
					<p class="chat-text">{{ m.content }}</p>
					<span class="chat-time">{{ m.created_at }}</span>
				</div>
			</div>
			<div v-if="sending" class="chat-row assistant">
				<div class="chat-avatar">🤖</div>
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
			<button type="submit" class="btn btn-primary" :disabled="sending || !draft.trim()">Gönder</button>
		</form>
	</div>
</template>

<script setup>
import { ref, computed, inject, nextTick, onMounted } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

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

const base = computed(() => props.subjectType === 'mannequin'
	? `/creative/mannequins/${props.subjectId}`
	: `/creative/tryon/${props.subjectId}`)

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
.chat-context { padding: 16px 0 12px; border-bottom: 1px solid #f0f0f5; }
.back-link { font-size: 12px; color: rgb(var(--color-primary)); font-weight: 600; text-decoration: none; }
.context-body { display: flex; align-items: center; gap: 14px; margin-top: 10px; }
.context-thumb { width: 56px; height: 56px; border-radius: 10px; object-fit: cover; background: #f5f5f8; }
.context-title { font-size: 18px; font-weight: 700; color: #1a1a2e; }
.context-tags { display: flex; flex-wrap: wrap; align-items: center; gap: 5px; margin-top: 5px; }
.context-tags-label { font-size: 11px; font-weight: 600; color: #888; }
.context-tag { font-size: 11px; font-weight: 600; padding: 2px 8px; border-radius: 10px; background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
.context-note { font-size: 12.5px; color: #b91c1c; margin-top: 2px; }

.suggestion-banner { position: sticky; top: 0; z-index: 5; display: flex; align-items: center; justify-content: space-between; gap: 16px; background: #fff7ed; border: 1px solid #fed7aa; border-radius: 12px; padding: 12px 16px; margin: 12px 0; }
.suggestion-label { font-size: 10.5px; font-weight: 700; color: #c2410c; text-transform: uppercase; letter-spacing: .03em; }
.suggestion-text { font-size: 13px; color: #1a1a2e; margin-top: 2px; }

.chat-log { flex: 1; overflow-y: auto; padding: 16px 0; display: flex; flex-direction: column; gap: 14px; }
.empty-log { text-align: center; color: #aaa; font-style: italic; font-size: 13px; margin-top: 40px; }
.chat-row { display: flex; gap: 10px; max-width: 78%; }
.chat-row.user { align-self: flex-end; flex-direction: row-reverse; }
.chat-avatar { width: 30px; height: 30px; border-radius: 50%; background: #f0f0f5; display: flex; align-items: center; justify-content: center; font-size: 14px; flex-shrink: 0; }
.chat-bubble { background: #f5f5f8; border-radius: 14px; padding: 10px 14px; }
.chat-row.user .chat-bubble { background: rgb(var(--color-primary-soft)); }
.chat-row.assistant .chat-bubble { background: #eef2ff; }
.chat-author { display: block; font-size: 10.5px; font-weight: 700; color: #888; margin-bottom: 3px; }
.chat-text { font-size: 13.5px; color: #1a1a2e; white-space: pre-wrap; line-height: 1.5; }
.chat-time { display: block; font-size: 10px; color: #aaa; margin-top: 4px; }
.chat-bubble.typing { display: flex; gap: 4px; align-items: center; padding: 12px 16px; }
.chat-bubble.typing span { width: 6px; height: 6px; border-radius: 50%; background: #bbb; animation: pulse 1.2s infinite ease-in-out; }
.chat-bubble.typing span:nth-child(2) { animation-delay: .2s; }
.chat-bubble.typing span:nth-child(3) { animation-delay: .4s; }
@keyframes pulse { 0%, 80%, 100% { opacity: .3; } 40% { opacity: 1; } }

.chat-input-bar { position: sticky; bottom: 0; display: flex; gap: 10px; align-items: flex-end; background: #fff; border-top: 1px solid #f0f0f5; padding: 12px 0; }
.chat-input-bar textarea { flex: 1; resize: none; border: 1px solid #e8e8f0; border-radius: 10px; padding: 10px 12px; font-family: inherit; font-size: 13.5px; outline: none; max-height: 160px; }
.chat-input-bar textarea:focus { border-color: rgb(var(--color-primary)); }

/* ── Dar ekran (telefon) ── */
@media (max-width: 640px) {
	.chat-page { height: calc(100vh - 48px); }
	.suggestion-banner { flex-wrap: wrap; }
	.suggestion-banner .btn { width: 100%; justify-content: center; }
	.chat-row { max-width: 92%; }
}
</style>
