<script setup>
import { computed } from 'vue'
import OzAvatar from './OzAvatar.vue'
import OzTooltip from './OzTooltip.vue'

// Üst üste binen avatar grubu; sığmayanlar "+N" olarak gösterilir.
//   <OzAvatarGroup :users="[{ name: 'Elif Yalçın', src: '…' }, …]" :max="4" />
// users: [{ name, initials?, src?, tone?, status? }]
// total: listede olmayanlar dahil gerçek kişi sayısı (API sayfalı döndüğünde)
const props = defineProps({
  users: { type: Array, required: true },
  max: { type: Number, default: 4 },
  size: { type: Number, default: 28 },
  total: { type: Number, default: null },
  tone: { type: String, default: 'auto' },
  // Üzerine gelince isim ipucu
  tooltips: { type: Boolean, default: true },
})

const visible = computed(() => props.users.slice(0, props.max))
const hidden = computed(() => props.users.slice(props.max))
const extra = computed(() => Math.max(0, (props.total ?? props.users.length) - visible.value.length))

const nameOf = (u) => u.name || u.initials || ''

// "+3" ipucu: gizli isimler, listede olmayanlar "ve N kişi daha"
const extraLabel = computed(() => {
  const names = hidden.value.map(nameOf).filter(Boolean)
  const unnamed = extra.value - names.length
  if (!names.length) return `${extra.value} kişi daha`
  return unnamed > 0 ? `${names.join(', ')} ve ${unnamed} kişi daha` : names.join(', ')
})

const groupLabel = computed(() => `${props.total ?? props.users.length} kişi: ${props.users.map(nameOf).join(', ')}${extra.value > hidden.value.length ? '…' : ''}`)
const overlap = computed(() => `${-Math.round(props.size * 0.22)}px`)
const moreFont = computed(() => `${Math.round(props.size * (extra.value > 99 ? 0.3 : 0.36))}px`)
</script>

<template>
  <div class="oz-avatar-group" role="group" :aria-label="groupLabel" :style="{ '--overlap': overlap }">
    <span v-for="(u, i) in visible" :key="u.id ?? (nameOf(u) || i)" class="slot">
      <OzTooltip v-if="tooltips && nameOf(u)" :text="nameOf(u)">
        <OzAvatar :name="u.name" :initials="u.initials" :src="u.src" :status="u.status" :tone="u.tone || tone" :size="size" class="member" />
      </OzTooltip>
      <OzAvatar v-else :name="u.name" :initials="u.initials" :src="u.src" :status="u.status" :tone="u.tone || tone" :size="size" class="member" />
    </span>

    <span v-if="extra" class="slot">
      <OzTooltip :text="extraLabel">
        <span
          class="more"
          :style="{ width: size + 'px', height: size + 'px', fontSize: moreFont }"
          tabindex="0"
          :aria-label="`+${extra}: ${extraLabel}`"
        >+{{ extra > 99 ? '99' : extra }}</span>
      </OzTooltip>
    </span>
  </div>
</template>

<style scoped>
.oz-avatar-group { display: inline-flex; align-items: center; padding-left: calc(var(--overlap) * -1); }
.slot { display: inline-flex; margin-left: var(--overlap); position: relative; transition: transform .15s ease; }
.slot:hover, .slot:focus-within { z-index: 2; transform: translateY(-2px); }
.member, .more { box-shadow: 0 0 0 2px var(--oz-surface); }
.more {
  display: inline-flex; align-items: center; justify-content: center; border-radius: 50%;
  background: var(--oz-surface-2); color: var(--oz-muted); font-weight: 700; font-variant-numeric: tabular-nums;
  border: 1px solid var(--oz-border); cursor: default; outline: none;
}
.more:focus-visible { box-shadow: 0 0 0 2px var(--oz-surface), 0 0 0 4px var(--oz-accent); }
</style>
