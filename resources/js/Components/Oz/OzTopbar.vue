<script setup>
import { ref, computed } from 'vue'
import OzIcon from './OzIcon.vue'
import OzAvatar from './OzAvatar.vue'
import OzFloatingPanel from './OzFloatingPanel.vue'

const props = defineProps({
  brandName: { type: String, default: 'OzumServer' },
  brandTagline: { type: String, default: 'Üretim Platformu' },
  searchPlaceholder: { type: String, default: 'Sipariş, ürün, iş ortağı ara…' },
  // { name, initials, role }
  user: { type: Object, required: true },
  // [{ id, title, desc, time, unread, icon }]
  notifications: { type: Array, default: () => [] },
  // Rozet sayısı; verilmezse okunmamış bildirim sayısı gösterilir
  notificationCount: { type: Number, default: null },
  // [{ key, label, icon, danger?, active? } | { divider: true } | { heading: 'Başlık' }]
  userMenu: {
    type: Array,
    default: () => [
      { key: 'profile', label: 'Profilim', icon: 'user' },
      { key: 'settings', label: 'Ayarlar', icon: 'settings' },
      { key: 'logout', label: 'Çıkış Yap', icon: 'logout', danger: true },
    ],
  },
})

// notifications-open: bildirim paneli açıldığında (ör. sessizce okundu işaretlemek için)
const emit = defineEmits(['search', 'messages', 'mark-all-read', 'notification-click', 'user-menu-select', 'notifications-open'])

const badge = computed(() => props.notificationCount ?? props.notifications.filter((n) => n.unread).length)

const notifBtn = ref(null)
const userBtn = ref(null)
const notifOpen = ref(false)
const userOpen = ref(false)

function toggleNotif() {
  userOpen.value = false
  notifOpen.value = !notifOpen.value
  if (notifOpen.value) emit('notifications-open')
}

function toggleUser() {
  notifOpen.value = false
  userOpen.value = !userOpen.value
}

function selectUserItem(item) {
  userOpen.value = false
  emit('user-menu-select', item.key)
}
</script>

<template>
  <header class="oz-topbar">
    <div class="brand">
      <div class="brand-mark">
        <OzIcon name="layers" :stroke-width="2.5" />
      </div>
      <div class="brand-name">{{ brandName }}<small>{{ brandTagline }}</small></div>
    </div>

    <div class="actions">
      <slot name="actions-start" />

      <button type="button" class="search-box" @click="emit('search')">
        <OzIcon name="search" :size="14" />
        <span>{{ searchPlaceholder }}</span>
        <kbd>Ctrl K</kbd>
      </button>

      <button type="button" class="icon-btn" title="Mesajlar" @click="emit('messages')">
        <OzIcon name="message" />
      </button>

      <button ref="notifBtn" type="button" class="icon-btn" title="Bildirimler" @click="toggleNotif">
        <OzIcon name="bell" />
        <span v-if="badge" class="dot">{{ badge > 9 ? '9+' : badge }}</span>
      </button>

      <button ref="userBtn" type="button" class="user-chip" :class="{ open: userOpen }" @click="toggleUser">
        <OzAvatar :initials="user.initials" />
        <span class="user-chip-name">{{ user.name }}</span>
        <OzIcon class="chev" name="chevron-down" :size="12" :stroke-width="2.5" />
      </button>
    </div>

    <OzFloatingPanel v-model:open="notifOpen" :anchor="notifBtn">
      <div class="fp-head">
        <h4>Bildirimler</h4>
        <button type="button" @click="emit('mark-all-read')">Tümünü okundu işaretle</button>
      </div>
      <div v-if="notifications.length">
        <div
          v-for="n in notifications"
          :key="n.id ?? n.title"
          class="notif-row"
          :class="{ unread: n.unread }"
          @click="emit('notification-click', n)"
        >
          <div class="notif-ico"><OzIcon :name="n.icon || 'bell'" :size="15" /></div>
          <div>
            <div class="notif-title">{{ n.title }}</div>
            <div v-if="n.desc" class="notif-desc">{{ n.desc }}</div>
            <div v-if="n.time" class="notif-time">{{ n.time }}</div>
          </div>
        </div>
      </div>
      <div v-else class="notif-empty">Yeni bildirim yok</div>
    </OzFloatingPanel>

    <OzFloatingPanel v-model:open="userOpen" :anchor="userBtn">
      <div class="ud-head">
        <OzAvatar :initials="user.initials" :size="36" />
        <div>
          <div class="ud-name">{{ user.name }}</div>
          <div v-if="user.role" class="ud-role">{{ user.role }}</div>
        </div>
      </div>
      <div class="ud-list">
        <template v-for="(item, i) in userMenu" :key="item.key ?? `sep-${i}`">
          <div v-if="item.divider" class="ud-sep" role="separator" />
          <div v-else-if="item.heading" class="ud-heading">{{ item.heading }}</div>
          <button
            v-else
            type="button"
            class="ud-item"
            :class="{ danger: item.danger, active: item.active }"
            :aria-current="item.active || undefined"
            @click="selectUserItem(item)"
          >
            <OzIcon :name="item.icon" :size="14" />
            <span class="ud-label">{{ item.label }}</span>
            <OzIcon v-if="item.active" name="check" :size="13" :stroke-width="2.5" class="ud-check" />
          </button>
        </template>
      </div>
    </OzFloatingPanel>
  </header>
</template>

<style scoped>
.oz-topbar {
  height: 100%;
  background: var(--oz-surface);
  border: 1px solid var(--oz-border);
  border-radius: var(--oz-radius);
  box-shadow: var(--oz-shadow-sm);
  display: flex; align-items: center;
  padding: 0 18px;
  gap: 20px;
}
.brand { display: flex; align-items: center; gap: 10px; font-weight: 800; font-size: 15px; color: var(--oz-ink-soft); white-space: nowrap; }
.brand-mark {
  width: 32px; height: 32px; border-radius: 8px; flex-shrink: 0;
  display: flex; align-items: center; justify-content: center;
  background: linear-gradient(135deg, var(--oz-accent), var(--oz-accent-hover));
  color: #fff;
  box-shadow: 0 4px 12px rgba(var(--oz-accent-rgb), .35);
}
.brand-name small { display: block; font-size: 9.5px; font-weight: 600; color: var(--oz-muted); letter-spacing: .04em; text-transform: uppercase; margin-top: 1px; }

.actions { display: flex; align-items: center; gap: 10px; margin-left: auto; }

.search-box {
  display: flex; align-items: center; gap: 8px;
  background: var(--oz-surface-2); border: 1px solid var(--oz-border);
  /* arama, ikon düğmeleri ve kullanıcı düğmesi aynı yükseklikte (36px) */
  height: 36px; box-sizing: border-box; border-radius: 8px; padding: 0 8px 0 12px; cursor: text; font: inherit; line-height: 1;
  color: var(--oz-muted-2); min-width: 240px; text-align: left;
  transition: border-color .15s, background .15s, box-shadow .15s;
}
.search-box:hover, .search-box:focus-visible {
  outline: none;
  border-color: var(--oz-border-strong);
  box-shadow: 0 0 0 3px rgba(var(--oz-accent-rgb), 0.08);
  background: var(--oz-search-hover-bg);
}
.search-box span { font-size: 12.5px; line-height: 1; color: var(--oz-muted); flex: 1; }
.search-box kbd { font-family: inherit; font-size: 10px; line-height: 1; color: var(--oz-muted); background: var(--oz-surface); border: 1px solid var(--oz-border); border-radius: 4px; padding: 3px 6px; font-weight: 600; }

.icon-btn {
  width: 36px; height: 36px; border-radius: 8px;
  border: 1px solid var(--oz-border); background: var(--oz-surface); color: var(--oz-muted);
  display: flex; align-items: center; justify-content: center;
  cursor: pointer; position: relative; flex-shrink: 0;
  transition: border-color .15s, color .15s, background .15s, transform .1s;
}
.icon-btn:hover { border-color: var(--oz-muted-2); color: var(--oz-ink); background: var(--oz-surface-2); transform: translateY(-1px); }
.icon-btn .dot {
  position: absolute; top: -3px; right: -3px;
  min-width: 16px; height: 16px; border-radius: 999px; padding: 0 3px;
  background: var(--oz-danger); color: #fff; font-size: 9px; font-weight: 700;
  display: flex; align-items: center; justify-content: center;
  border: 2px solid var(--oz-surface);
}

.user-chip {
  display: flex; align-items: center; gap: 9px;
  height: 36px; box-sizing: border-box; font: inherit; line-height: 1;
  padding: 0 12px 0 3px; border: 1px solid var(--oz-border); border-radius: 999px;
  cursor: pointer; user-select: none; background: var(--oz-surface); color: inherit;
  transition: border-color .15s, box-shadow .15s;
}
.user-chip:hover { border-color: var(--oz-muted-2); }
.user-chip.open { border-color: rgba(var(--oz-accent-rgb), .55); box-shadow: 0 0 0 3px rgba(var(--oz-accent-rgb), .14); }
.user-chip-name { font-size: 12.5px; font-weight: 600; color: var(--oz-ink-soft); }
.user-chip .chev { color: var(--oz-muted); transition: transform .15s; }
.user-chip.open .chev { transform: rotate(180deg); }

.fp-head { padding: 13px 16px 11px; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid var(--oz-border-soft); }
.fp-head h4 { margin: 0; font-size: 13.5px; font-weight: 700; color: var(--oz-ink-soft); }
.fp-head button { font-size: 11px; font-weight: 600; color: var(--oz-accent); background: none; border: none; cursor: pointer; }
.notif-row { display: flex; gap: 11px; padding: 11px 16px; cursor: pointer; transition: background .12s; }
.notif-row:hover { background: var(--oz-surface-2); }
.notif-row + .notif-row { border-top: 1px solid var(--oz-border-soft); }
.notif-ico { width: 32px; height: 32px; border-radius: 9px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; }
.notif-row.unread .notif-ico { background: var(--oz-accent-soft); color: var(--oz-accent); }
.notif-row:not(.unread) .notif-ico { background: var(--oz-surface-2); color: var(--oz-muted); }
.notif-title { font-size: 12px; font-weight: 600; color: var(--oz-ink-soft); line-height: 1.35; }
.notif-desc { font-size: 11px; color: var(--oz-muted); margin-top: 2px; }
.notif-time { font-size: 10px; color: var(--oz-muted-2); margin-top: 4px; }
.notif-empty { padding: 20px 16px; font-size: 12px; color: var(--oz-muted); text-align: center; }

.ud-head { display: flex; gap: 12px; align-items: center; padding: 14px 16px; border-bottom: 1px solid var(--oz-border-soft); }
.ud-name { font-size: 13px; font-weight: 700; color: var(--oz-ink-soft); }
.ud-role { font-size: 11px; color: var(--oz-muted); margin-top: 1px; }
.ud-list { padding: 6px; max-height: min(520px, calc(100dvh - 140px)); overflow-y: auto; }
.ud-sep { height: 1px; margin: 5px 6px; background: var(--oz-border-soft); }
.ud-heading { padding: 8px 12px 4px; font-size: 10.5px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: var(--oz-muted-2); }
.ud-label { flex: 1; min-width: 0; }
.ud-item.active { color: var(--oz-accent); font-weight: 600; }
.ud-check { color: var(--oz-accent); }
.ud-item { width: 100%; display: flex; align-items: center; gap: 9px; padding: 9px 12px; border: none; background: none; text-align: left; border-radius: 9px; font-size: 12.5px; font-weight: 500; color: var(--oz-ink); cursor: pointer; transition: background .12s, color .12s; }
.ud-item :deep(svg) { color: var(--oz-muted); }
.ud-item:hover { background: var(--oz-accent-soft); color: var(--oz-accent); }
.ud-item:hover :deep(svg) { color: var(--oz-accent); }
.ud-item.danger, .ud-item.danger :deep(svg) { color: var(--oz-danger); }
.ud-item.danger:hover { background: var(--oz-danger-soft); }

@media (max-width: 768px) {
  .search-box { min-width: 0; width: 36px; padding: 0; justify-content: center; }
  .search-box span, .search-box kbd { display: none; }
}
@media (max-width: 640px) {
  .user-chip { padding: 0 3px; border: none; }
  .user-chip-name, .user-chip .chev { display: none; }
}
</style>
