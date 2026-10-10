<script setup>
import { inject, computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import { useModuleSettings } from '@/composables/useModuleSettings'
import OzIcon from './OzIcon.vue'

// Etkin modülün alt sayfaları, sayfa başlığının altında rota sekmeleri olarak.
// Veriyi OzAppLayout sağlar (DB menüsündeki kök menünün alt menüleri); sayfanın yalnızca yerleştirmesi yeterli:
//   <OzPageHeader ... />
//   <OzModuleTabs />
// Modülün alt sayfası yoksa hiçbir şey çizmez. Mobilde gizlenir: orada OzMobileNav başlığının altındaki alt sayfa çipleri (ve çark) var.
const nav = inject('ozNav', null)

const module = computed(() => nav?.module.value)
const children = computed(() => module.value?.children ?? [])
const activeKey = computed(() => nav?.child.value?.key)

// Modülün tek ayar sayfası: sekmelerin en sağında çark (mobilde OzMobileNav çiplerinin sonunda).
const { settings, settingsActive } = useModuleSettings(module)
</script>

<template>
  <nav v-if="children.length || settings" class="oz-module-tabs" :aria-label="`${module.title} sayfaları`">
    <template v-for="c in children" :key="c.key">
      <Link
        v-if="c.to"
        :href="c.to"
        class="tab"
        :class="{ active: !settingsActive && c.key === activeKey }"
        :aria-current="!settingsActive && c.key === activeKey ? 'page' : undefined"
      >
        <OzIcon v-if="c.icon" :name="c.icon" :size="14" />
        {{ c.title }}
        <span v-if="c.count != null" class="count">{{ c.count }}</span>
      </Link>
      <span v-else class="tab soon" title="Yakında" aria-disabled="true">
        <OzIcon v-if="c.icon" :name="c.icon" :size="14" />
        {{ c.title }}
      </span>
    </template>
    <Link
      v-if="settings"
      :href="settings.url"
      class="tab settings"
      :class="{ active: settingsActive }"
      :aria-current="settingsActive ? 'page' : undefined"
      :title="`${module.title} ayarları`"
      :aria-label="`${module.title} ayarları`"
    >
      <OzIcon name="settings" :size="16" />
    </Link>
  </nav>
</template>

<style scoped>
/* Alt çizgi border değil içe gölge: sekmelerin 2px aktif çizgisi onun üstüne biner ama kutudan taşmaz.
   (border + margin-bottom:-1px, overflow-x:auto ile 1px dikey taşma yapıyordu → tekerlek şeridi
   1px oynatıyor, fare üzerindeyken sayfa kaymıyordu.) overflow-y: hidden aynı nedenle açıkça kapalı. */
.oz-module-tabs {
  display: flex; gap: 20px; margin: -4px 0 16px; padding: 0 2px;
  box-shadow: inset 0 -1px 0 var(--oz-border);
  overflow-x: auto; overflow-y: hidden; scrollbar-width: none;
}
.oz-module-tabs::-webkit-scrollbar { display: none; }
.tab {
  display: inline-flex; align-items: center; gap: 7px; flex-shrink: 0; white-space: nowrap;
  padding: 9px 0 10px; border-bottom: 2px solid transparent;
  font-size: 12.5px; font-weight: 600; color: var(--oz-muted); text-decoration: none;
  transition: color .15s, border-color .15s;
}
a.tab:hover { color: var(--oz-ink); }
a.tab:focus-visible { outline: 2px solid var(--oz-accent); outline-offset: 2px; border-radius: 4px; }
.tab.active { color: var(--oz-accent); border-bottom-color: var(--oz-accent); }
.tab.soon { opacity: .45; cursor: default; }
/* Ayar çarkı: sekmelerden ayrık, satırın en sağına yaslı */
.tab.settings { margin-left: auto; padding-left: 4px; padding-right: 4px; }
@media (max-width: 768px) { .oz-module-tabs { display: none; } }
.count {
  min-width: 18px; height: 18px; padding: 0 5px; border-radius: 999px;
  display: inline-flex; align-items: center; justify-content: center;
  font-size: 10px; font-weight: 700; font-variant-numeric: tabular-nums;
  background: var(--oz-danger-soft); color: var(--oz-danger); border: 1px solid rgba(var(--oz-danger-rgb), .2);
}
</style>
