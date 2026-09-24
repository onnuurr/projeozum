<script setup>
import OzIcon from './OzIcon.vue'
import OzSpinner from './OzSpinner.vue'

// variant: primary | ghost | danger   size: md (36px) | sm (mini, onay satırları)
// loading: ikon yerine dönen gösterge çıkar, buton tıklanamaz (kaydetme vb. sürerken)
defineProps({
  variant: { type: String, default: 'ghost', validator: (v) => ['primary', 'ghost', 'danger'].includes(v) },
  size: { type: String, default: 'md', validator: (v) => ['md', 'sm'].includes(v) },
  icon: { type: String, default: '' },
  type: { type: String, default: 'button' },
  disabled: { type: Boolean, default: false },
  loading: { type: Boolean, default: false },
})
</script>

<template>
  <button
    :type="type"
    class="oz-btn"
    :class="[`oz-btn--${variant}`, `oz-btn--${size}`, { 'is-loading': loading }]"
    :disabled="disabled || loading"
    :aria-busy="loading || undefined"
  >
    <OzSpinner v-if="loading" :size="size === 'sm' ? 12 : 14" tone="current" label="İşleniyor" />
    <OzIcon v-else-if="icon" :name="icon" :size="14" :stroke-width="icon === 'plus' ? 2.5 : 2" />
    <slot />
  </button>
</template>

<style scoped>
.oz-btn {
  display: inline-flex; align-items: center; gap: 6px; cursor: pointer; white-space: nowrap;
  font-family: inherit; border: 1px solid transparent;
  transition: background .15s, border-color .15s, color .15s, transform .1s, box-shadow .15s;
}
.oz-btn:disabled { opacity: .45; cursor: not-allowed; pointer-events: none; }
/* yüklenirken soluklaşmaz, yalnızca tıklanamaz */
.oz-btn.is-loading:disabled { opacity: .85; cursor: progress; }
.oz-btn:focus-visible { outline: 2px solid var(--oz-accent); outline-offset: 2px; }

.oz-btn--md { height: 36px; padding: 0 14px; border-radius: 9px; font-size: 12.5px; font-weight: 600; }
.oz-btn--md:active { transform: scale(0.98); }
.oz-btn--sm { font-size: 11px; font-weight: 700; padding: 5px 11px; border-radius: 7px; }
.oz-btn--sm:active { transform: translateY(0); }

.oz-btn--md.oz-btn--primary { background: var(--oz-accent); color: #fff; box-shadow: 0 4px 12px rgba(var(--oz-accent-rgb), .3); }
.oz-btn--md.oz-btn--primary:hover { background: var(--oz-accent-hover); box-shadow: 0 6px 18px rgba(var(--oz-accent-rgb), 0.4); }

.oz-btn--danger { background: var(--oz-danger); color: #fff; box-shadow: 0 4px 12px rgba(var(--oz-danger-rgb), .28); }
.oz-btn--danger:hover { filter: brightness(.92); box-shadow: 0 6px 18px rgba(var(--oz-danger-rgb), .38); }
.oz-btn--danger:focus-visible { outline-color: var(--oz-danger); }

.oz-btn--ghost { background: var(--oz-surface); color: var(--oz-ink); border-color: var(--oz-border); box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04); }
.oz-btn--ghost:hover { border-color: var(--oz-muted-2); background: var(--oz-surface-2); transform: translateY(-1px); }
.oz-btn--sm.oz-btn--ghost:hover { box-shadow: 0 3px 8px -1px rgba(15, 23, 42, 0.1); }

.oz-btn--sm.oz-btn--primary { background: linear-gradient(135deg, var(--oz-accent) 0%, var(--oz-accent-hover) 100%); color: #fff; border-color: transparent; box-shadow: 0 2px 6px rgba(var(--oz-accent-rgb), 0.35); }
.oz-btn--sm.oz-btn--primary:hover { background: linear-gradient(135deg, var(--oz-accent-hover) 0%, var(--oz-accent-strong) 100%); box-shadow: 0 4px 12px rgba(var(--oz-accent-rgb), 0.45); transform: translateY(-1px); }
</style>
