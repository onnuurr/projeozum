<script setup>
import { ref, computed, watch } from 'vue'

// Kullanıcı avatarı.
//   <OzAvatar name="Elif Yalçın" />                       → "EY"
//   <OzAvatar name="Elif Yalçın" src="/img/elif.jpg" />   → fotoğraf (yüklenemezse baş harfler)
//   <OzAvatar initials="EY" :size="36" tone="auto" status="online" />
// tone: accent (varsayılan) | auto (isme göre sabit renk) | info | success | warning | danger | neutral
// status: online | away | busy | offline
const props = defineProps({
  name: { type: String, default: '' },
  initials: { type: String, default: '' },
  src: { type: String, default: '' },
  size: { type: Number, default: 28 },
  tone: { type: String, default: 'accent', validator: (v) => ['accent', 'auto', 'info', 'success', 'warning', 'danger', 'neutral'].includes(v) },
  status: { type: String, default: '', validator: (v) => ['', 'online', 'away', 'busy', 'offline'].includes(v) },
  shape: { type: String, default: 'circle', validator: (v) => ['circle', 'square'].includes(v) },
})

const STATUS_LABELS = { online: 'Çevrimiçi', away: 'Uzakta', busy: 'Meşgul', offline: 'Çevrimdışı' }
const AUTO_TONES = ['accent', 'info', 'success', 'warning', 'danger', 'violet', 'teal', 'pink']

const imgFailed = ref(false)
watch(() => props.src, () => { imgFailed.value = false })

const text = computed(() => {
  if (props.initials) return props.initials.slice(0, 2).toLocaleUpperCase('tr')
  const parts = props.name.trim().split(/\s+/).filter(Boolean)
  if (!parts.length) return '?'
  const letters = parts.length === 1 ? parts[0].slice(0, 2) : parts[0][0] + parts.at(-1)[0]
  return letters.toLocaleUpperCase('tr')
})

// Aynı isim her zaman aynı rengi alsın
const resolvedTone = computed(() => {
  if (props.tone !== 'auto') return props.tone
  const key = props.name || props.initials
  let h = 0
  for (const ch of key) h = (h * 31 + ch.codePointAt(0)) >>> 0
  return AUTO_TONES[h % AUTO_TONES.length]
})

const showImage = computed(() => props.src && !imgFailed.value)
const label = computed(() => [props.name || props.initials, STATUS_LABELS[props.status]].filter(Boolean).join(' · ') || undefined)
</script>

<template>
  <span
    class="oz-avatar"
    :class="[`tone-${resolvedTone}`, `is-${shape}`, { 'has-image': showImage }]"
    :style="{ width: size + 'px', height: size + 'px', fontSize: Math.round(size * 0.38) + 'px' }"
    role="img"
    :aria-label="label"
  >
    <img v-if="showImage" :src="src" alt="" loading="lazy" @error="imgFailed = true">
    <span v-else class="text" aria-hidden="true">{{ text }}</span>
    <span
      v-if="status"
      class="status"
      :class="`st-${status}`"
      :style="{ width: Math.max(7, Math.round(size * 0.28)) + 'px', height: Math.max(7, Math.round(size * 0.28)) + 'px' }"
      aria-hidden="true"
    />
  </span>
</template>

<style scoped>
.oz-avatar {
  position: relative; flex-shrink: 0;
  display: inline-flex; align-items: center; justify-content: center;
  color: #fff; font-weight: 700; letter-spacing: -.01em; line-height: 1;
  user-select: none;
}
.is-circle, .is-circle img { border-radius: 50%; }
.is-square, .is-square img { border-radius: 28%; }
img { width: 100%; height: 100%; object-fit: cover; display: block; background: var(--oz-surface-2); }

.tone-accent { background: linear-gradient(135deg, var(--oz-accent), var(--oz-accent-hover)); }
.tone-info { background: linear-gradient(135deg, #3b82f6, #2563eb); }
.tone-success { background: linear-gradient(135deg, #22c55e, #16a34a); }
.tone-warning { background: linear-gradient(135deg, #f59e0b, #d97706); }
.tone-danger { background: linear-gradient(135deg, #f87171, #dc2626); }
.tone-violet { background: linear-gradient(135deg, #a78bfa, #7c3aed); }
.tone-teal { background: linear-gradient(135deg, #2dd4bf, #0d9488); }
.tone-pink { background: linear-gradient(135deg, #f472b6, #db2777); }
.tone-neutral { background: var(--oz-surface-2); color: var(--oz-muted); box-shadow: inset 0 0 0 1px var(--oz-border); }
.has-image { background: none; }

.status {
  position: absolute; right: 0; bottom: 0; border-radius: 50%;
  box-shadow: 0 0 0 2px var(--oz-surface);
}
.is-square .status { right: -2px; bottom: -2px; }
.st-online { background: var(--oz-success); }
.st-away { background: var(--oz-warning); }
.st-busy { background: var(--oz-danger); }
.st-offline { background: var(--oz-muted-2); }
</style>
