<script setup>
import { ref, computed, provide, watch, onScopeDispose } from 'vue'
import { router } from '@inertiajs/vue3'
import { useEventListener } from '@vueuse/core'
import {
  OzShell, OzTopbar, OzRail, OzMobileNav, OzCommandPalette, OzAccountSheet,
  useOzPalette, useOzToast, useOzLoadingBar,
} from '@/Components/Oz'
import FilterDrawer from '@/Components/FilterDrawer.vue'
import { ozIconForMenu, menuIconName } from '@/ozMenuIcons.js'
import { useAppShell } from '@/composables/useAppShell.js'

// Oz kit uygulama kabuğu (Inertia). Veri ve sayfa sözleşmeleri eski kabukla aynı kaynaktan gelir:
// useAppShell → DB menüsü (page.props.menu), oturum kullanıcısı, bildirimler (Reverb dahil), demo roller,
// provide('showToast' | '$swal' | 'drawer'), flash mesajları. Sayfalar değişmeden çalışır.
//   Kök menüler      → sol şerit (rail) + mobil alt çubuk
//   Kökün alt menüsü → rail paneli, <OzModuleTabs />, mobil çipler, Ctrl K paleti
//   3. seviye        → rail panelinde girintili liste
// Alt sayfalar: masaüstünde rail paneli (OzRailFlyout), mobilde başlıktaki çipler.
// Sayfa kendi başlığının altına <OzModuleTabs /> koyarak sekme gösterebilir (saas'taki desen).
// Sayfaya özel palet komutları: :commands="[{ label, items: [{ key, label, icon, to? | run? }] }]"
const props = defineProps({
  commands: { type: Array, default: () => [] },
})

const shell = useAppShell()
const { page, currentUser, activeRoot } = shell

/* ── menü: DB ağacı → Oz modülleri ── */
// superadmin_menus.icon (Lucide adı ya da eski menuIcons.js anahtarı) → Lucide bileşeni (ortak eşleme: ozMenuIcons.js)
const iconOf = ozIconForMenu

function firstLink(node) {
  if (node.to) return node.to
  for (const c of node.children ?? []) {
    const l = firstLink(c)
    if (l) return l
  }
  return null
}

const modules = computed(() => (page.props.menu ?? []).map((root) => ({
  key: `m${root.id}`,
  id: root.id,
  title: root.label,
  icon: iconOf(root.icon),
  iconName: menuIconName(root.icon),
  to: firstLink(root) ?? undefined,
  children: (root.children ?? []).map((c) => ({
    key: `m${c.id}`,
    id: c.id,
    title: c.label,
    // DB'de ikonu olmayan alt menüler ikonsuz (sade liste) çizilir
    icon: c.icon ? iconOf(c.icon) : '',
    to: firstLink(c) ?? undefined,
    children: (c.children ?? []).map((g) => ({ key: `m${g.id}`, id: g.id, title: g.label, to: firstLink(g) ?? undefined })),
  })),
})))

// "Ayarlar" gibi yönetim kökleri şeridin altına sabitlenir (ikon: settings)
const railItems = computed(() => modules.value.filter((m) => m.iconName !== 'settings'))
const railBottom = computed(() => modules.value.filter((m) => m.iconName === 'settings'))

/* ── etkin modül / alt sayfa (useAppShell ile aynı eşleştirme: en uzun URL öneki) ── */
function urlMatches(to) {
  if (!to) return false
  if (page.url === to) return true
  if (to.includes('?')) return false
  const path = page.url.split('?')[0]
  return path === to || path.startsWith(`${to}/`)
}
const activeModule = computed(() => modules.value.find((m) => m.id === activeRoot.value?.id) ?? null)
const activeChild = computed(() => {
  let best = { child: null, len: -1 }
  for (const c of activeModule.value?.children ?? []) {
    for (const node of [c, ...(c.children ?? [])]) {
      if (node.to && urlMatches(node.to) && node.to.length > best.len) best = { child: c, len: node.to.length }
    }
  }
  return best.child
})
provide('ozNav', { module: activeModule, child: activeChild })

function go(to) {
  if (to && to !== page.url) router.visit(to)
}

/* ── kullanıcı / bildirimler ── */
// Görünen ad: ad(lar) + soyadın baş harfi ("Onur Karaosmanoğlu" → "Onur K.", "Ali Veli Yılmaz" → "Ali Veli Y.")
function shortName(full = '') {
  const parts = full.trim().split(/\s+/).filter(Boolean)
  if (parts.length < 2) return parts[0] ?? ''
  const last = parts.pop()
  return `${parts.join(' ')} ${last[0].toLocaleUpperCase('tr')}.`
}

const user = computed(() => ({
  name: shortName(currentUser.value.name),
  initials: currentUser.value.initials,
  role: currentUser.value.roleLabel,
}))

const NOTIF_ICONS = { success: 'success', info: 'info', warning: 'warning', error: 'error', danger: 'error' }
const notifications = computed(() => shell.visibleNotifications.value.map((n) => ({
  id: n.id,
  title: n.title,
  desc: n.desc,
  time: n.time,
  unread: !n.read,
  icon: NOTIF_ICONS[n.iconType] ?? 'bell',
  raw: n,
})))
const unreadCount = computed(() => notifications.value.filter((n) => n.unread).length)

// Eski kabuğun kullanıcı menüsü (SVG/emoji ikonlu) → Oz ikon adları
const USER_ICONS = {
  'Profilim': 'user', 'Firma Profili': 'building', 'Üretim Atölyesi': 'scissors', 'Süper Admin Paneli': 'shield',
  'Menü Yönetimi': 'sliders', 'Kullanıcı Yönetimi': 'users', 'Ayarlar': 'settings', 'Bildirimler': 'bell',
  'Kısayollar': 'zap', 'Yardım & Destek': 'help', 'Çıkış Yap': 'logout',
}
const userMenu = computed(() => shell.userMenu.value.map((it, i) => {
  if (it.divider || it.heading) return it
  return {
    key: `u${i}`,
    label: it.label,
    icon: USER_ICONS[it.label] ?? (it.demoRole ? 'user' : 'chevron-right'),
    danger: it.danger,
    active: it.activeDemoRole,
    raw: it,
  }
}))
function onUserMenu(key) {
  const item = userMenu.value.find((i) => i.key === key)
  if (item) shell.handleUserAction(item.raw)
}

/* ── mobil hesap / bildirim paneli ── */
const sheetOpen = ref(false)
const sheetView = ref('account')
function openSheet(view) {
  sheetView.value = view
  sheetOpen.value = true
  if (view === 'notifications') shell.markNotificationsReadOnOpen()
}

/* ── toast köprüsü: eski useToast / inject('showToast') / flash mesajları Oz bildirimi olarak ── */
const ozToast = useOzToast()
const TONES = { success: 'success', error: 'danger', danger: 'danger', warning: 'warning', info: 'info' }
watch(() => shell.toasts.map((t) => t.id), () => {
  for (const t of [...shell.toasts]) {
    if (t.leaving) continue
    ozToast({ tone: TONES[t.type] ?? 'info', title: t.title || t.message, description: t.title ? t.message : '', duration: t.duration ?? 4500 })
    shell.toasts.splice(shell.toasts.indexOf(t), 1)
  }
}, { immediate: true })

/* ── Ctrl K paleti (eski arama modalının yerine) ── */
const { paletteOpen, togglePalette } = useOzPalette()
const paletteGroups = computed(() => [
  ...props.commands,
  {
    label: 'Sayfalar',
    items: modules.value.flatMap((m) => {
      const pages = m.children.flatMap((c) => [c, ...c.children.map((g) => ({ ...g, title: `${c.title} › ${g.title}`, icon: c.icon }))])
        .filter((c) => c.to)
        .map((c) => ({ key: `p-${c.key}`, label: `${m.title} › ${c.title}`, icon: c.icon, to: c.to, keywords: m.title, hint: urlMatches(c.to) ? 'Buradasınız' : '' }))
      return pages.length ? pages : (m.to ? [{ key: `p-${m.key}`, label: m.title, icon: m.icon, to: m.to }] : [])
    }),
  },
])
function onCommand(item) {
  if (item.run) item.run()
  else if (item.to) go(item.to)
}
// useAppShell de Ctrl K dinliyor (eski arama modalı); Oz kabuğunda o modal çizilmez, palet açılır
useEventListener(window, 'keydown', (e) => {
  if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
    e.preventDefault()
    togglePalette()
  }
})

/* ── sayfa geçişlerinde üst yükleme çubuğu (Inertia olayları) ── */
const loadingBar = useOzLoadingBar()
const offStart = router.on('start', () => loadingBar.start())
const offFinish = router.on('finish', () => loadingBar.done())
onScopeDispose(() => { offStart(); offFinish() })
</script>

<template>
  <OzShell>
    <template #topbar>
      <OzTopbar
        :brand-name="page.props.app?.name || 'OzumServer'"
        :user="user"
        :notifications="notifications"
        :user-menu="userMenu"
        @search="paletteOpen = true"
        @mark-all-read="shell.markAllNotificationsRead"
        @notifications-open="shell.markNotificationsReadOnOpen"
        @notification-click="(n) => shell.onNotificationClick(n.raw)"
        @user-menu-select="onUserMenu"
      />
    </template>
    <template #rail>
      <OzRail
        :items="railItems"
        :bottom-items="railBottom"
        :active="activeModule?.key"
        :active-child="activeChild?.key"
        :theme-toggle="false"
        @select="go($event.to)"
      />
    </template>
    <template #mobile>
      <OzMobileNav
        :items="railItems"
        :bottom-items="railBottom"
        :active="activeModule?.key"
        :active-child="activeChild?.key"
        :user="user"
        :unread="unreadCount"
        :brand-name="page.props.app?.name || 'OzumServer'"
        :home-to="modules[0]?.to || '/dashboard'"
        :theme-toggle="false"
        @select="go($event.to)"
        @search="paletteOpen = true"
        @notifications="openSheet('notifications')"
        @account="openSheet('account')"
      />
    </template>

    <!-- Sayfa geçişi (saas'taki .page-enter deseni, daha belirgin: 450ms soluklaşma + 16px yukarı kayma).
         Kabuk kalıcı layout olduğu için yalnızca içerik canlanır. Anahtar URL değil bileşen adı:
         aynı sayfadaki filtre/sayfalama ziyaretleri (preserveState) yeniden kurulup canlanmasın. -->
    <div :key="page.component" class="oz-page">
      <slot />
    </div>

    <OzCommandPalette
      v-model:open="paletteOpen"
      :groups="paletteGroups"
      :idle-hint="{ title: 'Aramak için yazmaya başlayın', description: 'ör. “sipariş”, “stok”, “kalıp”' }"
      @select="onCommand"
    />
    <OzAccountSheet
      v-model:open="sheetOpen"
      :view="sheetView"
      :user="user"
      :notifications="notifications"
      :user-menu="userMenu"
      @mark-all-read="shell.markAllNotificationsRead"
      @notification-click="(n) => shell.onNotificationClick(n.raw)"
      @user-menu-select="onUserMenu"
    />
    <!-- inject('drawer').open() kullanan sayfalar için eski filtre çekmecesi -->
    <FilterDrawer v-model="shell.drawerOpen.value" @apply="shell.onFilterApply" />
  </OzShell>
</template>

<style>
/* Yalnızca giriş animasyonu: çıkış beklenmez, geçiş gecikmez. fill-mode yok → bitince transform
   kalkar (içerideki position: fixed öğeler için kalıcı bir containing block oluşmaz). */
/* Süre / kayma buradan ayarlanır (saas: 250ms · 8px — hızlı geçişlerde fark edilmiyordu). */
.oz-page {
  --oz-page-duration: .45s;
  --oz-page-shift: 16px;
  animation: oz-page-in var(--oz-page-duration) cubic-bezier(.25, .7, .3, 1);
  padding:10px;
}
@keyframes oz-page-in {
  from { opacity: 0; transform: translateY(var(--oz-page-shift)); }
}
@media (prefers-reduced-motion: reduce) {
  .oz-page { animation: none; }
}
</style>
