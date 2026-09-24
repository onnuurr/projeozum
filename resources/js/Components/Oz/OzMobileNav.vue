<script setup>
import { ref, computed, watch, onMounted, nextTick } from 'vue'
import { useOzTheme } from '@/composables/useOzTheme'
import OzIcon from './OzIcon.vue'
import OzAvatar from './OzAvatar.vue'
import OzModal from './OzModal.vue'
import OzRailFlyout from './OzRailFlyout.vue'

// Mobil gezinme (uygulama düzeni). OzShell'in `mobile` slot'una konur, masaüstünde görünmez.
//  • Üst başlık: logo · marka · arama · bildirim (okunmamış rozeti) · avatar, altında etkin modülün alt sayfa çipleri
//    (sayfa adı sayfanın kendi OzPageHeader'ında; başlıkta tekrarlanmaz)
//  • Alt sekme çubuğu: `primary` modüller + "Daha fazla" (kalan modüller, tema)
// Modüle dokunmak ilk açılabilir alt sayfasına götürür; etkin modüle tekrar dokunmak
// alt sayfa listesini alttan açar. Veri OzRail ile aynıdır (items / bottomItems).
const props = defineProps({
  items: { type: Array, required: true },
  bottomItems: { type: Array, default: () => [] },
  active: { type: String, default: '' },
  activeChild: { type: String, default: '' },
  // Alt çubukta gösterilecek modül key'leri (en fazla 4 önerilir); boşsa ilk 4 modül
  primary: { type: Array, default: () => [] },
  user: { type: Object, required: true },
  unread: { type: Number, default: 0 },
  // Logoya dokununca gidilecek adres
  homeTo: { type: String, default: '/' },
  brandName: { type: String, default: 'OzumServer' },
  // Tema düğmesini "Daha fazla" panelinde göster
  themeToggle: { type: Boolean, default: true },
  brandTagline: { type: String, default: 'Üretim Platformu' },
})

// select: modül ya da alt sayfa ({ to }) · search / notifications / account: başlık düğmeleri
const emit = defineEmits(['select', 'search', 'notifications', 'account'])

const { theme, toggleTheme } = useOzTheme()

const all = computed(() => [...props.items, ...props.bottomItems])
const primaryItems = computed(() => {
  const keys = props.primary.length ? props.primary : props.items.slice(0, 4).map((m) => m.key)
  return keys.map((k) => all.value.find((m) => m.key === k)).filter(Boolean)
})
const moreItems = computed(() => all.value.filter((m) => !primaryItems.value.includes(m)))
const activeModule = computed(() => all.value.find((m) => m.key === props.active))
const moreActive = computed(() => moreItems.value.some((m) => m.key === props.active))

const enabled = (c) => !!(c.to || c.run)
const hasCount = (m) => m.children?.some((c) => c.count && enabled(c))

/* ── modül seçimi ── */
const sheetModule = ref(null)
const moreOpen = ref(false)

function pick(m) {
  // hiç sayfası olmayan yaprak modül: hiçbir şey yapma (Daha fazla paneli açık kalır);
  // alt sayfaları "Yakında" olan modülde liste yine açılır
  if (!m.to && !m.children?.length) return
  moreOpen.value = false
  if (!m.children?.length) {
    emit('select', m)
    return
  }
  const first = m.children.find(enabled)
  // başka modüle geçiş: doğrudan ilk sayfa; etkin modül ya da açılabilir sayfası yoksa liste
  if (first && m.key !== props.active) emit('select', first)
  else sheetModule.value = m
}

function onSheetSelect(c) {
  sheetModule.value = null
  emit('select', c)
}

/* ── alt sayfa çipleri ── */
const subItems = computed(() => activeModule.value?.children ?? [])
const subEl = ref(null)

// Konum ekran koordinatlarından hesaplanır (offsetLeft, offsetParent'a göre değiştiği için güvenilmez)
function revealChip(smooth = false) {
  const bar = subEl.value
  const chip = bar?.querySelector('.chip.active')
  if (!chip || bar.scrollWidth <= bar.clientWidth) return
  const left = bar.scrollLeft + chip.getBoundingClientRect().left - bar.getBoundingClientRect().left - 12
  bar.scrollTo({ left, behavior: smooth ? 'smooth' : 'instant' })
}
onMounted(() => {
  revealChip()
  // yazı tipi yüklenince çip genişlikleri değişir, bir kez daha hizala
  document.fonts?.ready.then(() => revealChip())
})
watch(() => props.activeChild, () => nextTick(() => revealChip(true)))
</script>

<template>
  <div class="oz-mnav">
    <header class="m-top">
      <div class="m-bar">
        <button type="button" class="m-brand" aria-label="Ana sayfa" @click="emit('select', { to: homeTo })">
          <span class="m-logo"><OzIcon name="layers" :size="16" :stroke-width="2.5" /></span>
          <span class="m-name">{{ brandName }}<small>{{ brandTagline }}</small></span>
        </button>
        <div class="m-actions">
          <button type="button" class="m-icon" aria-label="Ara" @click="emit('search')">
            <OzIcon name="search" :size="18" />
          </button>
          <button
            type="button"
            class="m-icon"
            :aria-label="`Bildirimler${unread ? ` (${unread} okunmamış)` : ''}`"
            @click="emit('notifications')"
          >
            <OzIcon name="bell" :size="18" />
            <span v-if="unread" class="m-dot">{{ unread > 9 ? '9+' : unread }}</span>
          </button>
          <button type="button" class="m-account" aria-label="Hesap" @click="emit('account')">
            <OzAvatar :initials="user.initials" :size="32" />
          </button>
        </div>
      </div>

      <nav v-if="subItems.length" ref="subEl" class="m-sub" :aria-label="`${activeModule.title} sayfaları`">
        <button
          v-for="c in subItems"
          :key="c.key"
          type="button"
          class="chip"
          :class="{ active: c.key === activeChild, soon: !enabled(c) }"
          :aria-current="c.key === activeChild ? 'page' : undefined"
          :aria-disabled="!enabled(c) || undefined"
          @click="enabled(c) && emit('select', c)"
        >
          {{ c.title }}
          <span v-if="c.count != null && enabled(c)" class="chip-count">{{ c.count }}</span>
        </button>
      </nav>
    </header>

    <nav class="m-tabs" aria-label="Modüller">
      <button
        v-for="m in primaryItems"
        :key="m.key"
        type="button"
        class="tab"
        :class="{ active: m.key === active }"
        :aria-current="m.key === active ? 'page' : undefined"
        @click="pick(m)"
      >
        <span class="tab-ico">
          <OzIcon :name="m.icon" :size="19" />
          <span v-if="hasCount(m)" class="tab-dot" />
        </span>
        <span class="tab-label">{{ m.title }}</span>
      </button>
      <button
        v-if="moreItems.length"
        type="button"
        class="tab"
        :class="{ active: moreActive }"
        aria-haspopup="dialog"
        @click="moreOpen = true"
      >
        <span class="tab-ico">
          <OzIcon name="grid" :size="19" />
          <span v-if="moreItems.some(hasCount)" class="tab-dot" />
        </span>
        <span class="tab-label">Daha fazla</span>
      </button>
    </nav>

    <OzModal v-model:open="moreOpen" title="Tüm modüller" size="sm">
      <div class="more-grid">
        <button
          v-for="m in moreItems"
          :key="m.key"
          type="button"
          class="more-item"
          :class="{ active: m.key === active, soon: !m.to && !m.children?.some(enabled) }"
          @click="pick(m)"
        >
          <span class="more-ico">
            <OzIcon :name="m.icon" :size="20" />
            <span v-if="hasCount(m)" class="tab-dot" />
          </span>
          <span class="more-label">{{ m.title }}</span>
          <span v-if="!m.to && !m.children?.some(enabled)" class="more-soon">Yakında</span>
        </button>
      </div>
      <button v-if="themeToggle" type="button" class="theme-row" @click="toggleTheme">
        <OzIcon :name="theme === 'dark' ? 'sun' : 'moon'" :size="16" />
        {{ theme === 'dark' ? 'Açık temaya geç' : 'Koyu temaya geç' }}
      </button>
    </OzModal>

    <OzRailFlyout :module="sheetModule" :active-child="sheetModule?.key === active ? activeChild : ''" @select="onSheetSelect" @close="sheetModule = null" />
  </div>
</template>

<style scoped>
/* kök OzShell grid'ine karışmaz: başlık 1. satıra, sekme çubuğu 3. satıra yerleşir */
.oz-mnav { display: contents; }

.m-top { grid-column: 1; grid-row: 1; display: flex; flex-direction: column; gap: 8px; min-width: 0; z-index: 20; }
.m-bar {
  display: flex; align-items: center; gap: 12px; height: 54px; padding: 0 10px 0 9px;
  background: var(--oz-surface); border: 1px solid var(--oz-border); border-radius: var(--oz-radius);
  box-shadow: var(--oz-shadow-sm);
}
.m-brand {
  flex: 1; min-width: 0; display: flex; align-items: center; gap: 10px;
  padding: 0; border: none; background: none; cursor: pointer; text-align: left; border-radius: 10px;
}
.m-logo {
  width: 36px; height: 36px; border-radius: 10px; flex-shrink: 0;
  display: flex; align-items: center; justify-content: center;
  background: linear-gradient(135deg, var(--oz-accent), var(--oz-accent-hover)); color: #fff;
  box-shadow: 0 4px 12px rgba(var(--oz-accent-rgb), .3);
}
.m-name { min-width: 0; font-size: 14.5px; font-weight: 800; color: var(--oz-ink-soft); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.m-name small { display: block; margin-top: 1px; font-size: 9.5px; font-weight: 600; color: var(--oz-muted); letter-spacing: .04em; text-transform: uppercase; }
.m-actions { display: flex; align-items: center; gap: 4px; flex-shrink: 0; }
.m-icon {
  position: relative; width: 38px; height: 38px; border-radius: 10px; flex-shrink: 0;
  display: flex; align-items: center; justify-content: center;
  border: none; background: none; color: var(--oz-muted); cursor: pointer;
  -webkit-tap-highlight-color: transparent;
}
.m-icon:active { background: var(--oz-surface-2); color: var(--oz-ink); }
.m-account { display: flex; margin-left: 4px; padding: 0; border: none; background: none; border-radius: 50%; cursor: pointer; flex-shrink: 0; }
.m-dot {
  position: absolute; top: 3px; right: 2px; min-width: 16px; height: 16px; padding: 0 4px; border-radius: 999px;
  display: flex; align-items: center; justify-content: center;
  font-size: 9.5px; font-weight: 700; background: var(--oz-danger); color: #fff; box-shadow: 0 0 0 2px var(--oz-surface);
}
.m-brand:focus-visible, .m-icon:focus-visible, .m-account:focus-visible, .chip:focus-visible, .tab:focus-visible, .more-item:focus-visible { outline: 2px solid var(--oz-accent); outline-offset: 2px; }

.m-sub { position: relative; display: flex; gap: 6px; overflow-x: auto; scrollbar-width: none; padding: 0 1px; }
.m-sub::-webkit-scrollbar { display: none; }
.chip {
  display: inline-flex; align-items: center; gap: 6px; flex-shrink: 0; height: 32px; padding: 0 14px;
  border-radius: 999px; border: 1px solid var(--oz-border); background: var(--oz-surface);
  font: inherit; font-size: 12.5px; font-weight: 600; color: var(--oz-muted); white-space: nowrap; cursor: pointer;
  transition: background .15s, color .15s, border-color .15s;
  -webkit-tap-highlight-color: transparent;
}
.chip.active { background: var(--oz-ink-soft); border-color: var(--oz-ink-soft); color: var(--oz-surface); }
.chip.soon { opacity: .45; cursor: default; }
.chip-count { min-width: 17px; height: 17px; padding: 0 5px; border-radius: 999px; display: inline-flex; align-items: center; justify-content: center; font-size: 10px; font-weight: 700; background: var(--oz-danger); color: #fff; }

.m-tabs {
  grid-column: 1; grid-row: 3; z-index: 20;
  display: grid; grid-auto-flow: column; grid-auto-columns: minmax(0, 1fr);
  height: 62px; padding: 6px;
  background: var(--oz-surface); border: 1px solid var(--oz-border); border-radius: 18px;
  box-shadow: 0 -2px 16px -6px rgba(15, 23, 42, .12), var(--oz-shadow-sm);
}
.tab {
  display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 3px; min-width: 0;
  border: none; background: none; border-radius: 12px; cursor: pointer; color: var(--oz-muted-2);
  font: inherit; -webkit-tap-highlight-color: transparent;
}
.tab-ico { position: relative; display: flex; align-items: center; justify-content: center; width: 44px; height: 28px; border-radius: 999px; transition: background .18s, color .18s; }
.tab-label { max-width: 100%; font-size: 10.5px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; padding: 0 2px; }
.tab.active { color: var(--oz-accent); }
.tab.active .tab-ico { background: var(--oz-accent-soft); }
.tab.active .tab-label { font-weight: 700; }
.tab:active .tab-ico { transform: scale(.94); }
.tab-dot { position: absolute; top: 2px; right: 9px; width: 7px; height: 7px; border-radius: 50%; background: var(--oz-danger); box-shadow: 0 0 0 2px var(--oz-surface); }

.more-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 8px; }
.more-item {
  position: relative; display: flex; flex-direction: column; align-items: center; gap: 8px; padding: 14px 6px 12px;
  border: 1px solid var(--oz-border-soft); border-radius: 14px; background: var(--oz-surface-2);
  font: inherit; cursor: pointer; color: var(--oz-ink-soft);
}
.more-ico { position: relative; width: 42px; height: 42px; border-radius: 12px; display: flex; align-items: center; justify-content: center; background: var(--oz-surface); color: var(--oz-muted); border: 1px solid var(--oz-border); }
.more-ico .tab-dot { top: -2px; right: -2px; }
.more-label { font-size: 11.5px; font-weight: 600; text-align: center; line-height: 1.25; }
.more-item.active { border-color: rgba(var(--oz-accent-rgb), .35); background: var(--oz-accent-soft); }
.more-item.active .more-ico { background: var(--oz-accent); color: #fff; border-color: transparent; }
.more-item.soon .more-ico, .more-item.soon .more-label { opacity: .5; }
.more-soon { font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: var(--oz-muted-2); margin-top: -4px; }
.theme-row {
  width: 100%; display: flex; align-items: center; gap: 10px; margin-top: 14px; height: 44px; padding: 0 12px;
  border: 1px solid var(--oz-border); border-radius: 12px; background: var(--oz-surface);
  font: inherit; font-size: 13px; font-weight: 600; color: var(--oz-ink-soft); cursor: pointer;
}
.theme-row :deep(svg) { color: var(--oz-muted); }
</style>
