<script setup>
import { ref, watch, onBeforeUnmount } from 'vue'
import { useEditor, EditorContent } from '@tiptap/vue-3'
import StarterKit from '@tiptap/starter-kit'
import TextAlign from '@tiptap/extension-text-align'
import { TableKit } from '@tiptap/extension-table'
import {
	Bold, Italic, Underline, Strikethrough, Heading2, Heading3, List, ListOrdered, Quote, Minus,
	AlignLeft, AlignCenter, AlignRight, AlignJustify, Link, Unlink, RemoveFormatting, Undo2, Redo2,
	Table, BetweenHorizontalEnd, BetweenVerticalEnd, Rows3, Columns3, PanelTop, Trash2,
} from 'lucide-vue-next'

// Bayi sözleşmesi zengin metin editörü (Tiptap). Çıktı HTML'dir; sunucu HTMLPurifier ile yalnızca
// izinli biçimleri bırakır (DealerAgreementService::sanitize — buradaki araçlar o listeyle uyumlu:
// kalın, italik, altı çizili, üstü çizili, H2/H3, madde/numaralı liste, alıntı, ayraç, hizalama, bağlantı, tablo).
// Tablo: imleç tablodayken satır/sütun ekle-sil, başlık satırı, tabloyu sil araçları görünür.
// mode="consent": onay kutusu metni — yalnızca satır içi biçimler (başlık/liste/hizalama yok).
// insertText(text): etiket çipleri ({{company.legal_name}}) imlecin olduğu yere ekler.
// Dışarıdan gelen değer (taslağa dön, eski sürümü yükle) editör içeriğinden farklıysa içerik değiştirilir;
// bu değişiklik update olayı üretmez — v-model yalnızca kullanıcının düzenlemesiyle güncellenir.

const props = defineProps({
	mode: { type: String, default: 'document', validator: (v) => ['document', 'consent'].includes(v) },
	invalid: { type: Boolean, default: false },
	placeholder: { type: String, default: '' },
})
const emit = defineEmits(['focus'])
const model = defineModel({ type: String, default: '' })

const editor = useEditor({
	content: model.value,
	extensions: [
		StarterKit.configure({
			heading: { levels: [2, 3] },
			code: false,
			codeBlock: false,
			link: { openOnClick: false, autolink: true, defaultProtocol: 'https' },
		}),
		TextAlign.configure({ types: ['heading', 'paragraph'] }),
		...(props.mode === 'document' ? [TableKit.configure({ table: { resizable: false } })] : []),
	],
	editorProps: {
		attributes: { class: 'agreement-prose', spellcheck: 'true', 'aria-label': 'Sözleşme metni', role: 'textbox', 'aria-multiline': 'true' },
	},
	onUpdate: ({ editor: e }) => {
		model.value = e.isEmpty ? '' : e.getHTML()
	},
	onFocus: () => emit('focus'),
})

watch(model, (value) => {
	const e = editor.value
	if (!e) return
	const current = e.isEmpty ? '' : e.getHTML()
	if ((value ?? '') !== current) e.commands.setContent(value ?? '', { emitUpdate: false })
})

onBeforeUnmount(() => editor.value?.destroy())

const is = (name, attrs) => editor.value?.isActive(name, attrs) ?? false
const run = (fn) => {
	if (!editor.value) return
	fn(editor.value.chain().focus()).run()
}

// ── Bağlantı (tarayıcı penceresi yerine satır içi alan) ─────────────────────
const linkOpen = ref(false)
const linkUrl = ref('')
function openLink() {
	linkUrl.value = editor.value?.getAttributes('link').href ?? ''
	linkOpen.value = true
}
function applyLink() {
	const url = linkUrl.value.trim()
	if (url) run((c) => c.extendMarkRange('link').setLink({ href: url }))
	else run((c) => c.extendMarkRange('link').unsetLink())
	linkOpen.value = false
}

function insertText(text) {
	run((c) => c.insertContent(text))
}

defineExpose({ insertText, focus: () => editor.value?.commands.focus() })
</script>

<template>
	<div class="agreement-editor" :class="{ 'is-invalid': invalid, 'is-consent': mode === 'consent' }">
		<div class="toolbar" role="toolbar" aria-label="Biçimlendirme">
			<button type="button" title="Kalın (Ctrl+B)" :class="{ on: is('bold') }" @click="run((c) => c.toggleBold())"><Bold :size="15" /></button>
			<button type="button" title="İtalik (Ctrl+I)" :class="{ on: is('italic') }" @click="run((c) => c.toggleItalic())"><Italic :size="15" /></button>
			<button type="button" title="Altı çizili (Ctrl+U)" :class="{ on: is('underline') }" @click="run((c) => c.toggleUnderline())"><Underline :size="15" /></button>
			<button type="button" title="Üstü çizili" :class="{ on: is('strike') }" @click="run((c) => c.toggleStrike())"><Strikethrough :size="15" /></button>

			<template v-if="mode === 'document'">
				<span class="sep" />
				<button type="button" title="Başlık" :class="{ on: is('heading', { level: 2 }) }" @click="run((c) => c.toggleHeading({ level: 2 }))"><Heading2 :size="15" /></button>
				<button type="button" title="Alt başlık" :class="{ on: is('heading', { level: 3 }) }" @click="run((c) => c.toggleHeading({ level: 3 }))"><Heading3 :size="15" /></button>
				<button type="button" title="Madde listesi" :class="{ on: is('bulletList') }" @click="run((c) => c.toggleBulletList())"><List :size="15" /></button>
				<button type="button" title="Numaralı liste" :class="{ on: is('orderedList') }" @click="run((c) => c.toggleOrderedList())"><ListOrdered :size="15" /></button>
				<button type="button" title="Alıntı / not" :class="{ on: is('blockquote') }" @click="run((c) => c.toggleBlockquote())"><Quote :size="15" /></button>
				<button type="button" title="Ayraç çizgisi" @click="run((c) => c.setHorizontalRule())"><Minus :size="15" /></button>
				<button type="button" title="Tablo ekle (3×3, başlık satırlı)" :class="{ on: is('table') }" @click="run((c) => c.insertTable({ rows: 3, cols: 3, withHeaderRow: true }))"><Table :size="15" /></button>

				<span class="sep" />
				<button type="button" title="Sola hizala" :class="{ on: is({ textAlign: 'left' }) }" @click="run((c) => c.setTextAlign('left'))"><AlignLeft :size="15" /></button>
				<button type="button" title="Ortala" :class="{ on: is({ textAlign: 'center' }) }" @click="run((c) => c.setTextAlign('center'))"><AlignCenter :size="15" /></button>
				<button type="button" title="Sağa hizala" :class="{ on: is({ textAlign: 'right' }) }" @click="run((c) => c.setTextAlign('right'))"><AlignRight :size="15" /></button>
				<button type="button" title="İki yana yasla" :class="{ on: is({ textAlign: 'justify' }) }" @click="run((c) => c.setTextAlign('justify'))"><AlignJustify :size="15" /></button>
			</template>

			<span class="sep" />
			<button type="button" title="Bağlantı ekle" :class="{ on: is('link') || linkOpen }" @click="openLink"><Link :size="15" /></button>
			<button v-if="is('link')" type="button" title="Bağlantıyı kaldır" @click="run((c) => c.extendMarkRange('link').unsetLink())"><Unlink :size="15" /></button>
			<button type="button" title="Biçimi temizle" @click="run((c) => c.unsetAllMarks().clearNodes())"><RemoveFormatting :size="15" /></button>

			<span class="spacer" />
			<button type="button" title="Geri al (Ctrl+Z)" :disabled="!editor?.can().undo()" @click="run((c) => c.undo())"><Undo2 :size="15" /></button>
			<button type="button" title="Yinele (Ctrl+Y)" :disabled="!editor?.can().redo()" @click="run((c) => c.redo())"><Redo2 :size="15" /></button>
		</div>

		<div v-if="mode === 'document' && is('table')" class="toolbar table-tools" role="toolbar" aria-label="Tablo">
			<span class="tools-label">Tablo</span>
			<button type="button" title="Alta satır ekle" @click="run((c) => c.addRowAfter())"><BetweenHorizontalEnd :size="15" /></button>
			<button type="button" title="Sağa sütun ekle" @click="run((c) => c.addColumnAfter())"><BetweenVerticalEnd :size="15" /></button>
			<button type="button" title="Satırı sil" @click="run((c) => c.deleteRow())"><Rows3 :size="15" /></button>
			<button type="button" title="Sütunu sil" @click="run((c) => c.deleteColumn())"><Columns3 :size="15" /></button>
			<button type="button" title="Başlık satırı aç/kapa" @click="run((c) => c.toggleHeaderRow())"><PanelTop :size="15" /></button>
			<button type="button" title="Tabloyu sil" @click="run((c) => c.deleteTable())"><Trash2 :size="15" /></button>
		</div>

		<div v-if="linkOpen" class="link-row">
			<input v-model="linkUrl" type="url" placeholder="https://…" aria-label="Bağlantı adresi" @keydown.enter.prevent="applyLink" @keydown.esc="linkOpen = false">
			<button type="button" class="apply" @click="applyLink">Uygula</button>
			<button type="button" @click="linkOpen = false">Vazgeç</button>
		</div>

		<EditorContent :editor="editor" class="content" />
	</div>
</template>

<style scoped>
.agreement-editor {
	border: 1px solid var(--oz-border);
	border-radius: var(--oz-radius-sm);
	background: var(--oz-surface);
	overflow: hidden;
	transition: border-color .15s, box-shadow .15s;
}
.agreement-editor:focus-within { border-color: var(--oz-accent); box-shadow: 0 0 0 3px var(--oz-accent-soft); }
.agreement-editor.is-invalid { border-color: var(--oz-danger); }

.toolbar {
	display: flex; flex-wrap: wrap; align-items: center; gap: 2px;
	padding: 6px; border-bottom: 1px solid var(--oz-border-soft); background: var(--oz-surface-2);
	position: sticky; top: 0; z-index: 1;
}
.toolbar button {
	display: inline-grid; place-items: center; width: 30px; height: 30px;
	border: 1px solid transparent; border-radius: 7px; background: none;
	color: var(--oz-muted); cursor: pointer;
}
.toolbar button:hover:not(:disabled) { background: var(--oz-surface); border-color: var(--oz-border); color: var(--oz-ink-soft); }
.toolbar button.on { background: var(--oz-accent-soft); color: var(--oz-accent); }
.toolbar button:disabled { opacity: .35; cursor: default; }
.sep { width: 1px; height: 18px; margin: 0 4px; background: var(--oz-border); }
.spacer { flex: 1; }

.link-row { display: flex; gap: 6px; padding: 6px; border-bottom: 1px solid var(--oz-border-soft); }
.link-row input {
	flex: 1; min-width: 0; height: 30px; padding: 0 10px;
	border: 1px solid var(--oz-border); border-radius: 7px; background: var(--oz-surface);
	font: inherit; font-size: 12.5px; color: var(--oz-ink-soft);
}
.link-row input:focus { outline: none; border-color: var(--oz-accent); box-shadow: none; }
.link-row button { height: 30px; padding: 0 10px; border: 1px solid var(--oz-border); border-radius: 7px; background: var(--oz-surface); font: inherit; font-size: 12.5px; cursor: pointer; color: var(--oz-ink-soft); }
.link-row button.apply { background: var(--oz-accent); border-color: var(--oz-accent); color: #fff; }

.content :deep(.agreement-prose) {
	min-height: 360px; max-height: 640px; overflow-y: auto;
	padding: 14px 16px; outline: none;
	font-size: 13.5px; line-height: 1.7; color: var(--oz-ink-soft);
}
.is-consent .content :deep(.agreement-prose) { min-height: 110px; }
.content :deep(.agreement-prose > * + *) { margin-top: 10px; }
.content :deep(.agreement-prose p) { margin: 0; }
.content :deep(.agreement-prose h2) { margin: 18px 0 6px; font-size: 16px; font-weight: 700; color: var(--oz-ink); }
.content :deep(.agreement-prose h3) { margin: 14px 0 4px; font-size: 14px; font-weight: 700; color: var(--oz-ink); }
.content :deep(.agreement-prose ul) { padding-left: 22px; list-style: disc; }
.content :deep(.agreement-prose ol) { padding-left: 22px; list-style: decimal; }
.content :deep(.agreement-prose li > p) { margin: 0; }
.content :deep(.agreement-prose blockquote) { margin: 0; padding: 6px 12px; border-left: 3px solid var(--oz-accent); background: var(--oz-surface-2); border-radius: 0 6px 6px 0; }
.content :deep(.agreement-prose hr) { border: none; border-top: 1px solid var(--oz-border); margin: 14px 0; }
.content :deep(.agreement-prose a) { color: var(--oz-accent); text-decoration: underline; }
.content :deep(.agreement-prose s) { color: var(--oz-muted); }
.content :deep(.agreement-prose table) { width: 100%; border-collapse: collapse; table-layout: fixed; }
.content :deep(.agreement-prose th), .content :deep(.agreement-prose td) { border: 1px solid var(--oz-border); padding: 6px 8px; vertical-align: top; }
.content :deep(.agreement-prose th) { background: var(--oz-surface-2); font-weight: 600; text-align: left; }
.content :deep(.agreement-prose td p), .content :deep(.agreement-prose th p) { margin: 0; }
.content :deep(.agreement-prose .selectedCell) { background: var(--oz-accent-soft); }
.table-tools { position: static; border-top: none; }
.tools-label { margin: 0 6px 0 4px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: var(--oz-muted); }
</style>
