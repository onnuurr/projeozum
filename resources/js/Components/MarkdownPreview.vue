<template>
	<div class="md-preview" v-html="rendered"></div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
	source: { type: String, default: '' },
})

/**
 * Minimal ve güvenli markdown → HTML render. Ürün açıklaması için yeter:
 * paragraph, **bold**, *italic*, - liste, satır sonu.
 * Öncelikle XSS'e karşı escape edilir; sonra desteklenen desenler HTML'e döner.
 */
function escapeHtml(s) {
	return String(s)
		.replace(/&/g, '&amp;')
		.replace(/</g, '&lt;')
		.replace(/>/g, '&gt;')
		.replace(/"/g, '&quot;')
		.replace(/'/g, '&#39;')
}

function renderMarkdown(src) {
	const escaped = escapeHtml(src || '')
	if (!escaped.trim()) return '<p class="md-empty">Henüz metin yok.</p>'

	// Blok: liste
	const withLists = escaped.replace(/(^|\n)((?:- [^\n]+(?:\n|$))+)/g, (m, pre, block) => {
		const items = block.trim().split(/\n/).map((l) => l.replace(/^-\s+/, '').trim())
		return pre + '<ul>' + items.map((i) => `<li>${i}</li>`).join('') + '</ul>'
	})

	// Inline: **bold**, *italic*
	const inline = withLists
		.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
		.replace(/(?<!\*)\*([^*\n]+)\*(?!\*)/g, '<em>$1</em>')

	// Paragraph: iki satır sonu → </p><p>; tek satır sonu → <br>
	const blocks = inline.split(/\n{2,}/).map((b) => {
		if (b.startsWith('<ul>')) return b
		return '<p>' + b.replace(/\n/g, '<br>') + '</p>'
	})

	return blocks.join('')
}

const rendered = computed(() => renderMarkdown(props.source))
</script>

<style scoped>
.md-preview {
	font-size: 13.5px;
	color: #1a1a2e;
	line-height: 1.55;
	padding: 12px 14px;
	background: #fafafe;
	border: 1px solid #f0f0f6;
	border-radius: 10px;
}
.md-preview :deep(p) { margin: 0 0 8px; }
.md-preview :deep(p:last-child) { margin-bottom: 0; }
.md-preview :deep(ul) { margin: 6px 0 10px; padding-left: 22px; }
.md-preview :deep(li) { margin-bottom: 3px; }
.md-preview :deep(strong) { color: #0f172a; }
.md-preview :deep(.md-empty) { color: #aaa; font-style: italic; }
</style>
