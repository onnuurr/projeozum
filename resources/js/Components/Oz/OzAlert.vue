<script setup>
import { ref } from 'vue'
import OzIcon from './OzIcon.vue'

// Sayfa/kart içi uyarı şeridi. tone: info | success | warning | danger | accent
// Slots: default (açıklama), actions (sağdaki butonlar)
//   <OzAlert tone="warning" title="Kapasite %92" dismissible>Dikim Atölyesi 1 dolmak üzere</OzAlert>
const props = defineProps({
  tone: { type: String, default: 'info', validator: (v) => ['info', 'success', 'warning', 'danger', 'accent'].includes(v) },
  title: { type: String, default: '' },
  // Varsayılan ikon tone'a göre seçilir; false/'' verilirse ikon gösterilmez
  icon: { type: [String, Boolean], default: null },
  dismissible: { type: Boolean, default: false },
})

const emit = defineEmits(['dismiss'])
const visible = ref(true)

const defaultIcons = { info: 'info', success: 'success', warning: 'warning', danger: 'error', accent: 'sparkles' }
const iconName = () => (props.icon === null ? defaultIcons[props.tone] : props.icon)

function dismiss() {
  visible.value = false
  emit('dismiss')
}
</script>

<template>
  <Transition name="oz-alert">
    <div v-if="visible" class="oz-alert" :class="`tone-${tone}`" :role="tone === 'danger' ? 'alert' : 'status'">
      <OzIcon v-if="iconName()" :name="iconName()" :size="16" class="ico" />
      <div class="body">
        <div v-if="title" class="title">{{ title }}</div>
        <div v-if="$slots.default" class="desc"><slot /></div>
      </div>
      <div v-if="$slots.actions" class="actions"><slot name="actions" /></div>
      <button v-if="dismissible" type="button" class="close" aria-label="Kapat" @click="dismiss">
        <OzIcon name="x" :size="14" />
      </button>
    </div>
  </Transition>
</template>

<style scoped>
.oz-alert {
  --c: var(--oz-info); --rgb: var(--oz-info-rgb); --bg: var(--oz-info-soft);
  display: flex; align-items: flex-start; gap: 11px;
  padding: 12px 14px; border-radius: 11px;
  background: var(--bg); border: 1px solid rgba(var(--rgb), .22);
  box-shadow: inset 3px 0 0 var(--c);
}
.tone-success { --c: var(--oz-success); --rgb: var(--oz-success-rgb); --bg: var(--oz-success-soft); }
.tone-warning { --c: var(--oz-warning); --rgb: var(--oz-warning-rgb); --bg: var(--oz-warning-soft); }
.tone-danger { --c: var(--oz-danger); --rgb: var(--oz-danger-rgb); --bg: var(--oz-danger-soft); }
.tone-accent { --c: var(--oz-accent); --rgb: var(--oz-accent-rgb); --bg: var(--oz-accent-soft); }
.ico { color: var(--c); flex-shrink: 0; margin-top: 1px; }
.body { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 3px; }
.title { font-size: 12.5px; font-weight: 700; color: var(--oz-ink-soft); }
.desc { font-size: 12px; color: var(--oz-muted); line-height: 1.5; }
.actions { display: flex; gap: 6px; align-items: center; flex-shrink: 0; align-self: center; }
.close {
  width: 24px; height: 24px; margin: -3px -5px 0 0; border-radius: 6px; flex-shrink: 0;
  display: flex; align-items: center; justify-content: center;
  background: none; border: none; color: var(--oz-muted); cursor: pointer;
}
.close:hover { background: rgba(var(--rgb), .12); color: var(--c); }
.close:focus-visible { outline: 2px solid var(--c); outline-offset: 1px; }

.oz-alert-leave-active { transition: opacity .15s ease, transform .15s ease; }
.oz-alert-leave-to { opacity: 0; transform: translateY(-4px); }

@media (max-width: 520px) {
  .oz-alert { display: grid; grid-template-columns: auto minmax(0, 1fr) auto; column-gap: 11px; row-gap: 10px; }
  .ico { grid-column: 1; grid-row: 1; }
  .body { grid-column: 2; grid-row: 1; }
  .close { grid-column: 3; grid-row: 1; }
  .actions { grid-column: 2 / -1; grid-row: 2; align-self: start; flex-wrap: wrap; }
}
</style>
