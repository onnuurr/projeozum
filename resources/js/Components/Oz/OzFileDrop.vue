<script setup>
import { ref, computed } from 'vue'
import OzIcon from './OzIcon.vue'
import { useOzField } from './useOzField'

// Sürükle-bırak dosya alanı. v-model: File[] (tekli modda da dizi, en fazla 1 öğe)
// accept: '.pdf,image/*' gibi; maxSize: bayt (0 = sınırsız)
// Kurala uymayan dosyalar eklenmez, 'reject' olayı ile [{ file, reason }] yayılır.
const props = defineProps({
  accept: { type: String, default: '' },
  multiple: { type: Boolean, default: false },
  maxSize: { type: Number, default: 0 },
  maxFiles: { type: Number, default: 0 },
  title: { type: String, default: 'Dosyaları buraya sürükleyin' },
  hint: { type: String, default: '' },
  disabled: { type: Boolean, default: false },
  invalid: { type: Boolean, default: false },
  id: { type: String, default: '' },
})

const model = defineModel({ type: Array, default: () => [] })
const emit = defineEmits(['reject'])
const { inputId, invalid, describedBy } = useOzField(props)

const inputEl = ref(null)
const dragging = ref(false)
let dragDepth = 0

const autoHint = computed(() => {
  if (props.hint) return props.hint
  const parts = []
  if (props.accept) parts.push(props.accept.split(',').map((s) => s.trim().replace(/^\./, '').toUpperCase()).join(', '))
  if (props.maxSize) parts.push(`en fazla ${formatSize(props.maxSize)}`)
  return parts.join(' · ')
})

function formatSize(b) {
  if (b < 1024) return `${b} B`
  if (b < 1024 ** 2) return `${(b / 1024).toFixed(0)} KB`
  return `${(b / 1024 ** 2).toFixed(1).replace('.0', '')} MB`
}

function matchesAccept(file) {
  if (!props.accept) return true
  const name = file.name.toLowerCase()
  return props.accept.split(',').map((s) => s.trim().toLowerCase()).some((rule) => {
    if (rule.startsWith('.')) return name.endsWith(rule)
    if (rule.endsWith('/*')) return file.type.startsWith(rule.slice(0, -1))
    return file.type === rule
  })
}

function add(fileList) {
  if (props.disabled) return
  const incoming = Array.from(fileList)
  const rejected = []
  let next = props.multiple ? [...model.value] : []
  for (const file of props.multiple ? incoming : incoming.slice(0, 1)) {
    if (!matchesAccept(file)) rejected.push({ file, reason: 'type' })
    else if (props.maxSize && file.size > props.maxSize) rejected.push({ file, reason: 'size' })
    else if (props.multiple && props.maxFiles && next.length >= props.maxFiles) rejected.push({ file, reason: 'count' })
    else if (next.some((f) => f.name === file.name && f.size === file.size)) continue
    else next.push(file)
  }
  model.value = next
  if (rejected.length) emit('reject', rejected)
}

function onChange(e) {
  add(e.target.files)
  e.target.value = ''
}

function onDragEnter() { if (!props.disabled) { dragDepth++; dragging.value = true } }
function onDragLeave() { if (--dragDepth <= 0) { dragDepth = 0; dragging.value = false } }
function onDrop(e) {
  dragDepth = 0
  dragging.value = false
  add(e.dataTransfer.files)
}

function remove(i) {
  model.value = model.value.filter((_, j) => j !== i)
}

const iconFor = (f) => (f.type.startsWith('image/') ? 'eye' : 'file')
</script>

<template>
  <div class="oz-filedrop" :class="{ disabled, invalid }">
    <label
      :for="inputId"
      class="zone"
      :class="{ dragging }"
      @dragenter.prevent="onDragEnter"
      @dragover.prevent
      @dragleave.prevent="onDragLeave"
      @drop.prevent="onDrop"
    >
      <input
        :id="inputId"
        ref="inputEl"
        type="file"
        class="sr"
        :accept="accept || undefined"
        :multiple="multiple"
        :disabled="disabled"
        :aria-invalid="invalid || undefined"
        :aria-describedby="describedBy"
        @change="onChange"
      >
      <span class="ico"><OzIcon name="upload" :size="18" /></span>
      <span class="title">{{ dragging ? 'Bırakın' : title }}</span>
      <span class="sub">veya <u>bilgisayardan seçin</u></span>
      <span v-if="autoHint" class="hint">{{ autoHint }}</span>
    </label>

    <TransitionGroup v-if="model.length" name="oz-file" tag="ul" class="files">
      <li v-for="(f, i) in model" :key="f.name + f.size" class="file">
        <span class="f-ico"><OzIcon :name="iconFor(f)" :size="14" /></span>
        <span class="f-text">
          <span class="f-name">{{ f.name }}</span>
          <span class="f-size">{{ formatSize(f.size) }}</span>
        </span>
        <button type="button" class="f-remove" :aria-label="`${f.name} kaldır`" :disabled="disabled" @click="remove(i)">
          <OzIcon name="trash" :size="13" />
        </button>
      </li>
    </TransitionGroup>
  </div>
</template>

<style scoped>
.oz-filedrop { display: flex; flex-direction: column; gap: 10px; }
.sr { position: absolute; width: 1px; height: 1px; opacity: 0; overflow: hidden; }
.zone {
  position: relative; display: flex; flex-direction: column; align-items: center; gap: 4px;
  padding: 22px 16px; border-radius: 12px; text-align: center; cursor: pointer;
  border: 1.5px dashed var(--oz-border-strong); background: var(--oz-surface-2);
  transition: border-color .15s, background .15s, box-shadow .15s;
}
.zone:hover, .zone.dragging { border-color: var(--oz-accent); background: var(--oz-accent-soft); }
.zone.dragging { box-shadow: 0 0 0 4px rgba(var(--oz-accent-rgb), .12); }
.zone:has(input:focus-visible) { border-color: var(--oz-accent); box-shadow: 0 0 0 3px rgba(var(--oz-accent-rgb), .2); }
.invalid .zone { border-color: rgba(var(--oz-danger-rgb), .6); }
.disabled .zone { opacity: .55; cursor: not-allowed; }
.ico {
  width: 40px; height: 40px; border-radius: 11px; margin-bottom: 6px;
  display: flex; align-items: center; justify-content: center;
  background: var(--oz-surface); color: var(--oz-accent); border: 1px solid var(--oz-border);
  box-shadow: var(--oz-shadow-sm); transition: transform .15s;
}
.dragging .ico { transform: translateY(-3px); }
.title { font-size: 13px; font-weight: 700; color: var(--oz-ink-soft); }
.sub { font-size: 12px; color: var(--oz-muted); }
.sub u { color: var(--oz-accent); font-weight: 600; text-decoration: none; }
.zone:hover .sub u { text-decoration: underline; }
.hint { margin-top: 4px; font-size: 11px; color: var(--oz-muted-2); }

.files { display: flex; flex-direction: column; gap: 6px; margin: 0; padding: 0; list-style: none; }
.file {
  display: flex; align-items: center; gap: 10px; padding: 8px 8px 8px 10px;
  border-radius: 10px; border: 1px solid var(--oz-border); background: var(--oz-surface);
}
.f-ico { width: 30px; height: 30px; border-radius: 8px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; background: var(--oz-accent-soft); color: var(--oz-accent); }
.f-text { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 1px; }
.f-name { font-size: 12.5px; font-weight: 600; color: var(--oz-ink-soft); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.f-size { font-size: 11px; color: var(--oz-muted); font-variant-numeric: tabular-nums; }
.f-remove { width: 28px; height: 28px; border-radius: 7px; display: flex; align-items: center; justify-content: center; border: none; background: none; color: var(--oz-muted); cursor: pointer; }
.f-remove:hover { background: var(--oz-danger-soft); color: var(--oz-danger); }
.f-remove:focus-visible { outline: 2px solid var(--oz-danger); outline-offset: 1px; }

.oz-file-enter-active, .oz-file-leave-active { transition: opacity .15s, transform .15s; }
.oz-file-enter-from, .oz-file-leave-to { opacity: 0; transform: translateY(-4px); }
</style>
