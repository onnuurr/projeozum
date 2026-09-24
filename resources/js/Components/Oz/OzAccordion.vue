<script setup>
import { computed, useId } from 'vue'
import OzIcon from './OzIcon.vue'

// Açılır bölümler. items: [{ key, title, subtitle?, icon?, disabled? }]
// İçerik: key adıyla slot. v-model: açık bölümlerin key dizisi.
// multiple=false iken aynı anda tek bölüm açık kalır.
//   <OzAccordion v-model="open" :items="items"><template #kargo>...</template></OzAccordion>
const props = defineProps({
  items: { type: Array, required: true },
  multiple: { type: Boolean, default: false },
  // Kart içinde kenarlıksız kullanım için
  flush: { type: Boolean, default: false },
})

const model = defineModel({ type: Array, default: () => [] })
const uid = useId()
const openSet = computed(() => new Set(model.value))

function toggle(item) {
  if (item.disabled) return
  const isOpen = openSet.value.has(item.key)
  if (isOpen) model.value = model.value.filter((k) => k !== item.key)
  else model.value = props.multiple ? [...model.value, item.key] : [item.key]
}

const headId = (k) => `oz-acc-${uid}-${k}`
const panelId = (k) => `oz-accp-${uid}-${k}`
</script>

<template>
  <div class="oz-accordion" :class="{ 'is-flush': flush }">
    <div v-for="item in items" :key="item.key" class="section" :class="{ open: openSet.has(item.key), disabled: item.disabled }">
      <h3 class="h">
        <button
          :id="headId(item.key)"
          type="button"
          class="head"
          :aria-expanded="openSet.has(item.key)"
          :aria-controls="panelId(item.key)"
          :disabled="item.disabled"
          @click="toggle(item)"
        >
          <span v-if="item.icon" class="ico"><OzIcon :name="item.icon" :size="15" /></span>
          <span class="titles">
            <span class="title">{{ item.title }}</span>
            <span v-if="item.subtitle" class="sub">{{ item.subtitle }}</span>
          </span>
          <OzIcon name="chevron-down" :size="15" class="chev" />
        </button>
      </h3>
      <div :id="panelId(item.key)" class="panel-wrap" role="region" :aria-labelledby="headId(item.key)" :inert="!openSet.has(item.key) || undefined">
        <div class="panel-inner">
          <div class="panel"><slot :name="item.key" /></div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.oz-accordion { border: 1px solid var(--oz-border); border-radius: 12px; background: var(--oz-surface); overflow: hidden; }
.oz-accordion.is-flush { border: none; border-radius: 0; background: transparent; }
.section + .section { border-top: 1px solid var(--oz-border-soft); }
.h { margin: 0; }
.head {
  width: 100%; display: flex; align-items: center; gap: 11px;
  padding: 13px 16px; background: none; border: none; cursor: pointer; text-align: left;
  font: inherit; color: var(--oz-ink-soft);
  transition: background .12s;
}
.head:hover:not(:disabled) { background: var(--oz-surface-2); }
.head:focus-visible { outline: 2px solid var(--oz-accent); outline-offset: -2px; }
.head:disabled { opacity: .45; cursor: not-allowed; }
.ico {
  width: 30px; height: 30px; border-radius: 8px; flex-shrink: 0;
  display: flex; align-items: center; justify-content: center;
  background: var(--oz-surface-2); color: var(--oz-muted); border: 1px solid var(--oz-border);
  transition: background .15s, color .15s, border-color .15s;
}
.open .ico { background: var(--oz-accent-soft); color: var(--oz-accent); border-color: rgba(var(--oz-accent-rgb), .22); }
.titles { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 2px; }
.title { font-size: 13px; font-weight: 700; }
.sub { font-size: 11.5px; color: var(--oz-muted); font-weight: 500; }
.chev { color: var(--oz-muted); flex-shrink: 0; transition: transform .2s ease; }
.open .chev { transform: rotate(180deg); color: var(--oz-accent); }

/* grid-template-rows 0fr→1fr: yükseklik ölçmeden akıcı aç/kapa */
.panel-wrap { display: grid; grid-template-rows: 0fr; transition: grid-template-rows .22s cubic-bezier(0.16, 1, 0.3, 1); }
.open .panel-wrap { grid-template-rows: 1fr; }
.panel-inner { overflow: hidden; min-height: 0; }
.panel { padding: 2px 16px 16px; font-size: 12.5px; color: var(--oz-muted); line-height: 1.55; }
.section:has(.ico) .panel { padding-left: 57px; }
@media (max-width: 520px) { .section:has(.ico) .panel { padding-left: 16px; } }
</style>
