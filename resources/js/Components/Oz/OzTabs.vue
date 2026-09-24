<script setup>
import { ref, useId } from 'vue'
import OzIcon from './OzIcon.vue'

// Sekmeler. tabs: [{ key, label, icon?, count?, disabled? }]
// variant: underline (kart başlığı altı) | segment (hap şeklinde grup)
// Panel içeriği: key adıyla slot verilirse sekmenin altında gösterilir
//   <OzTabs v-model="tab" :tabs="tabs"><template #genel>...</template></OzTabs>
// Slot verilmezse yalnızca sekme çubuğu çizilir, içerik sayfada v-if ile yönetilir.
const props = defineProps({
  tabs: { type: Array, required: true },
  variant: { type: String, default: 'underline', validator: (v) => ['underline', 'segment'].includes(v) },
  ariaLabel: { type: String, default: '' },
})

const model = defineModel({ type: String, default: '' })
const uid = useId()
const tabEls = ref([])

const tabId = (key) => `oz-tab-${uid}-${key}`
const panelId = (key) => `oz-tabpanel-${uid}-${key}`

function select(tab) {
  if (!tab.disabled) model.value = tab.key
}

// WAI-ARIA: ok tuşları ve Home/End ile sekmeler arasında gezinme
function onKeydown(e) {
  const enabled = props.tabs.filter((t) => !t.disabled)
  const i = enabled.findIndex((t) => t.key === model.value)
  let next = null
  if (e.key === 'ArrowRight') next = enabled[(i + 1) % enabled.length]
  else if (e.key === 'ArrowLeft') next = enabled[(i - 1 + enabled.length) % enabled.length]
  else if (e.key === 'Home') next = enabled[0]
  else if (e.key === 'End') next = enabled[enabled.length - 1]
  if (!next) return
  e.preventDefault()
  model.value = next.key
  const idx = props.tabs.findIndex((t) => t.key === next.key)
  tabEls.value[idx]?.focus()
}
</script>

<template>
  <div class="oz-tabs" :class="`is-${variant}`">
    <div class="list" role="tablist" :aria-label="ariaLabel || undefined" @keydown="onKeydown">
      <button
        v-for="tab in tabs"
        :id="tabId(tab.key)"
        :key="tab.key"
        ref="tabEls"
        type="button"
        role="tab"
        class="tab"
        :class="{ active: tab.key === model }"
        :aria-selected="tab.key === model"
        :aria-controls="$slots[tab.key] ? panelId(tab.key) : undefined"
        :tabindex="tab.key === model ? 0 : -1"
        :disabled="tab.disabled"
        @click="select(tab)"
      >
        <OzIcon v-if="tab.icon" :name="tab.icon" :size="14" />
        {{ tab.label }}
        <span v-if="tab.count != null" class="count">{{ tab.count }}</span>
      </button>
    </div>

    <template v-for="tab in tabs" :key="tab.key">
      <div
        v-if="$slots[tab.key] && tab.key === model"
        :id="panelId(tab.key)"
        class="panel"
        role="tabpanel"
        :aria-labelledby="tabId(tab.key)"
        tabindex="0"
      >
        <slot :name="tab.key" />
      </div>
    </template>
  </div>
</template>

<style scoped>
/* overflow-y açıkça kapalı: overflow-x:auto dikeyi de auto yapar; 1px taşma bile tekerlekle şeridi oynatır. */
.list { display: flex; gap: 4px; overflow-x: auto; overflow-y: hidden; scrollbar-width: none; }
.list::-webkit-scrollbar { display: none; }
.tab {
  display: inline-flex; align-items: center; gap: 7px; white-space: nowrap;
  font: inherit; font-size: 12.5px; font-weight: 600; color: var(--oz-muted);
  background: none; border: none; cursor: pointer;
  transition: color .15s, background .15s, box-shadow .15s, border-color .15s;
}
.tab:hover:not(:disabled) { color: var(--oz-ink); }
.tab:disabled { opacity: .45; cursor: not-allowed; }
.tab:focus-visible { outline: 2px solid var(--oz-accent); outline-offset: -2px; border-radius: 7px; }
.count {
  min-width: 18px; height: 18px; padding: 0 5px; border-radius: 999px;
  display: inline-flex; align-items: center; justify-content: center;
  font-size: 10px; font-weight: 700; font-variant-numeric: tabular-nums;
  background: var(--oz-surface-2); color: var(--oz-muted); border: 1px solid var(--oz-border);
}
.tab.active .count { background: var(--oz-accent-soft); color: var(--oz-accent); border-color: rgba(var(--oz-accent-rgb), .22); }
.panel:focus-visible { outline: none; }

/* underline — alt çizgi border değil içe gölge: aktif 2px çizgi üstüne biner ama kutudan taşmaz (OzModuleTabs ile aynı) */
.is-underline .list { box-shadow: inset 0 -1px 0 var(--oz-border); padding: 0 18px; gap: 18px; }
.is-underline .tab { padding: 10px 0 11px; border-bottom: 2px solid transparent; }
.is-underline .tab.active { color: var(--oz-accent); border-bottom-color: var(--oz-accent); }

/* segment */
.is-segment .list {
  display: inline-flex; padding: 3px; border-radius: 10px;
  background: var(--oz-surface-2); border: 1px solid var(--oz-border); max-width: 100%;
}
.is-segment .tab { height: 30px; padding: 0 12px; border-radius: 7px; }
.is-segment .tab.active { background: var(--oz-surface); color: var(--oz-ink-soft); box-shadow: var(--oz-shadow-sm); }
</style>
