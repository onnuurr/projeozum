<script setup>
import { computed } from 'vue'
import OzModal from './OzModal.vue'
import OzAvatar from './OzAvatar.vue'
import OzIcon from './OzIcon.vue'

// Mobil hesap / bildirim paneli (topbar mobilde gizli). OzModal mobilde alttan açılan sayfa olarak çizilir.
// view: 'notifications' (başlıktaki zil) | 'account' (avatar: kullanıcı bilgisi + menü)
const props = defineProps({
  // { name, initials, role }
  user: { type: Object, required: true },
  // [{ id, title, desc, time, unread, icon }]
  notifications: { type: Array, default: () => [] },
  // [{ key, label, icon, danger?, active? } | { divider: true } | { heading }]
  userMenu: { type: Array, default: () => [] },
  view: { type: String, default: 'account', validator: (v) => ['account', 'notifications'].includes(v) },
})

const open = defineModel('open', { type: Boolean, default: false })
const emit = defineEmits(['mark-all-read', 'notification-click', 'user-menu-select'])

const unread = computed(() => props.notifications.filter((n) => n.unread).length)

function act(fn) {
  open.value = false
  fn()
}
</script>

<template>
  <OzModal v-if="view === 'notifications'" v-model:open="open" size="sm" title="Bildirimler" icon="bell">
    <div v-if="unread" class="notif-bar">
      <span>{{ unread }} okunmamış</span>
      <button type="button" class="link" @click="emit('mark-all-read')">Tümünü okundu işaretle</button>
    </div>
    <div v-if="notifications.length" class="notifs">
      <button
        v-for="n in notifications"
        :key="n.id ?? n.title"
        type="button"
        class="notif"
        :class="{ unread: n.unread }"
        @click="act(() => emit('notification-click', n))"
      >
        <span class="notif-ico"><OzIcon :name="n.icon || 'bell'" :size="15" /></span>
        <span class="notif-text">
          <span class="notif-title">{{ n.title }}</span>
          <span v-if="n.desc" class="notif-desc">{{ n.desc }}</span>
          <span v-if="n.time" class="notif-time">{{ n.time }}</span>
        </span>
        <span v-if="n.unread" class="unread-dot" aria-label="okunmamış" />
      </button>
    </div>
    <p v-else class="empty">Yeni bildirim yok</p>
  </OzModal>

  <OzModal v-else v-model:open="open" size="sm">
    <template #header>
      <div class="me">
        <OzAvatar :initials="user.initials" :size="40" />
        <div class="me-text">
          <div class="me-name">{{ user.name }}</div>
          <div v-if="user.role" class="me-role">{{ user.role }}</div>
        </div>
      </div>
    </template>

    <section class="menu">
      <template v-for="(item, i) in userMenu" :key="item.key ?? `sep-${i}`">
        <div v-if="item.divider" class="sep" role="separator" />
        <div v-else-if="item.heading" class="heading">{{ item.heading }}</div>
        <button
          v-else
          type="button"
          class="menu-item"
          :class="{ danger: item.danger, active: item.active }"
          @click="act(() => emit('user-menu-select', item.key))"
        >
          <OzIcon :name="item.icon" :size="15" />
          {{ item.label }}
          <OzIcon v-if="item.active" name="check" :size="14" :stroke-width="2.5" class="chev on" />
          <OzIcon v-else-if="!item.danger" name="chevron-right" :size="14" class="chev" />
        </button>
      </template>
    </section>
  </OzModal>
</template>

<style scoped>
.me { display: flex; align-items: center; gap: 12px; flex: 1; min-width: 0; }
.me-text { min-width: 0; }
.me-name { font-size: 15px; font-weight: 700; color: var(--oz-ink-soft); }
.me-role { font-size: 12px; color: var(--oz-muted); margin-top: 2px; }

.notif-bar { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin: -4px 0 10px; font-size: 12px; font-weight: 600; color: var(--oz-muted); }
.unread-dot { width: 8px; height: 8px; border-radius: 50%; background: var(--oz-accent); flex-shrink: 0; margin-top: 6px; }

.link { border: none; background: none; padding: 4px 0; font: inherit; font-size: 11.5px; font-weight: 600; color: var(--oz-accent); cursor: pointer; }

.notifs { display: flex; flex-direction: column; gap: 2px; border: 1px solid var(--oz-border-soft); border-radius: 12px; padding: 4px; }
.notif { display: flex; gap: 11px; align-items: flex-start; width: 100%; padding: 10px; border: none; border-radius: 9px; background: none; font: inherit; text-align: left; cursor: pointer; }
.notif:active { background: var(--oz-surface-2); }
.notif-ico { width: 32px; height: 32px; border-radius: 9px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; background: var(--oz-surface-2); color: var(--oz-muted); }
.notif.unread .notif-ico { background: var(--oz-accent-soft); color: var(--oz-accent); }
.notif-text { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 2px; }
.notif-title { font-size: 12.5px; font-weight: 600; color: var(--oz-ink-soft); }
.notif.unread .notif-title { font-weight: 700; }
.notif-desc { font-size: 11.5px; color: var(--oz-muted); }
.notif-time { font-size: 10.5px; color: var(--oz-muted-2); }
.empty { margin: 0; font-size: 12px; color: var(--oz-muted); }

.menu { display: flex; flex-direction: column; gap: 2px; margin-top: 0; }
.menu-item { display: flex; align-items: center; gap: 12px; width: 100%; height: 44px; padding: 0 10px; border: none; border-radius: 10px; background: none; font: inherit; font-size: 13px; font-weight: 600; color: var(--oz-ink); cursor: pointer; }
.menu-item :deep(svg) { color: var(--oz-muted); }
.menu-item:active { background: var(--oz-surface-2); }
.menu-item .chev { margin-left: auto; color: var(--oz-muted-2); }
.menu-item.danger, .menu-item.danger :deep(svg) { color: var(--oz-danger); }
.menu-item.active { color: var(--oz-accent); }
.menu-item .chev.on { color: var(--oz-accent); }
.sep { height: 1px; margin: 6px 4px; background: var(--oz-border-soft); }
.heading { padding: 10px 10px 4px; font-size: 10.5px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: var(--oz-muted-2); }
</style>
