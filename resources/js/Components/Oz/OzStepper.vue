<script setup>
import { computed } from 'vue'
import OzIcon from './OzIcon.vue'

// Adım göstergesi (sihirbaz formları, sipariş akışı).
// steps: [{ key, label, description?, icon? }]   v-model: geçerli adımın key'i
// clickable: tamamlanan adımlara tıklayarak geri dönülebilir
// orientation: horizontal | vertical
const props = defineProps({
  steps: { type: Array, required: true },
  clickable: { type: Boolean, default: false },
  orientation: { type: String, default: 'horizontal', validator: (v) => ['horizontal', 'vertical'].includes(v) },
})

const model = defineModel({ type: String, default: '' })
const currentIndex = computed(() => Math.max(props.steps.findIndex((s) => s.key === model.value), 0))

const stateOf = (i) => (i < currentIndex.value ? 'done' : i === currentIndex.value ? 'current' : 'upcoming')

function go(step, i) {
  if (props.clickable && i < currentIndex.value) model.value = step.key
}
</script>

<template>
  <ol class="oz-stepper" :class="`is-${orientation}`">
    <li v-for="(step, i) in steps" :key="step.key" class="step" :class="stateOf(i)" :aria-current="stateOf(i) === 'current' ? 'step' : undefined">
      <component
        :is="clickable && stateOf(i) === 'done' ? 'button' : 'div'"
        :type="clickable && stateOf(i) === 'done' ? 'button' : undefined"
        class="node"
        @click="go(step, i)"
      >
        <span class="dot">
          <OzIcon v-if="stateOf(i) === 'done'" name="check" :size="13" :stroke-width="3" />
          <OzIcon v-else-if="step.icon" :name="step.icon" :size="13" />
          <template v-else>{{ i + 1 }}</template>
        </span>
        <span class="text">
          <span class="label">{{ step.label }}</span>
          <span v-if="step.description" class="desc">{{ step.description }}</span>
        </span>
      </component>
      <span v-if="i < steps.length - 1" class="line" aria-hidden="true" />
    </li>
  </ol>
</template>

<style scoped>
.oz-stepper { display: flex; margin: 0; padding: 0; list-style: none; }
.step { display: flex; min-width: 0; }
.node {
  display: flex; align-items: center; gap: 10px; min-width: 0;
  background: none; border: none; padding: 0; font: inherit; text-align: left; color: inherit;
}
button.node { cursor: pointer; border-radius: 9px; }
button.node:hover .label { color: var(--oz-accent); }
button.node:focus-visible { outline: 2px solid var(--oz-accent); outline-offset: 3px; }
.dot {
  width: 28px; height: 28px; border-radius: 50%; flex-shrink: 0;
  display: flex; align-items: center; justify-content: center;
  font-size: 12px; font-weight: 700; font-variant-numeric: tabular-nums;
  background: var(--oz-surface); color: var(--oz-muted); border: 1.5px solid var(--oz-border-strong);
  transition: background .2s, color .2s, border-color .2s, box-shadow .2s;
}
.done .dot { background: var(--oz-accent); border-color: var(--oz-accent); color: #fff; }
.current .dot { border-color: var(--oz-accent); color: var(--oz-accent); background: var(--oz-accent-soft); box-shadow: 0 0 0 4px rgba(var(--oz-accent-rgb), .12); }
.text { display: flex; flex-direction: column; gap: 1px; min-width: 0; }
.label { font-size: 12.5px; font-weight: 700; color: var(--oz-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; transition: color .15s; }
.done .label, .current .label { color: var(--oz-ink-soft); }
.desc { font-size: 11px; color: var(--oz-muted-2); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.line { background: var(--oz-border); border-radius: 2px; transition: background .2s; }
.done .line { background: var(--oz-accent); }

/* yatay */
.is-horizontal { align-items: center; gap: 12px; }
.is-horizontal .step { flex: 1; align-items: center; gap: 12px; }
.is-horizontal .step:last-child { flex: 0 0 auto; }
.is-horizontal .line { flex: 1; min-width: 16px; height: 2px; }

/* dikey */
.is-vertical { flex-direction: column; }
.is-vertical .step { flex-direction: column; }
.is-vertical .node { align-items: flex-start; }
.is-vertical .text { padding-top: 4px; }
.is-vertical .line { width: 2px; height: 22px; margin: 4px 0 4px 13px; }

@media (max-width: 640px) {
  .is-horizontal .desc { display: none; }
  .is-horizontal { gap: 8px; }
  .is-horizontal .step { gap: 8px; }
  .is-horizontal .step:not(.current) .text { display: none; }
  .is-horizontal .step.current .node, .is-horizontal .step.current .text { flex-shrink: 0; }
  .is-horizontal .step.current .label { overflow: visible; }
}
</style>
