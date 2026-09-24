import { reactive } from 'vue'

// Promise tabanlı onay penceresi. OzShell içindeki OzConfirmHost çizer.
//   const confirm = useOzConfirm()
//   if (await confirm({ title: 'Silinsin mi?', message: '#SP-10474 kalıcı olarak silinecek', tone: 'danger', confirmText: 'Sil' })) { ... }
//   await confirm.danger('Silinsin mi?', '...', { requireText: 'SİL' })  // yazarak onay
// Aynı anda tek onay açık olur; yenisi gelirse öncekisi false ile kapanır.
const state = reactive({ open: false, opts: {}, resolve: null })

const defaults = {
  title: 'Emin misiniz?',
  message: '',
  confirmText: 'Onayla',
  cancelText: 'Vazgeç',
  tone: 'accent',
  icon: '',
  requireText: '',
}
const toneIcons = { accent: 'help', info: 'info', warning: 'warning', danger: 'trash', success: 'success' }

function open(opts = {}) {
  state.resolve?.(false)
  const o = { ...defaults, ...opts }
  if (!o.icon) o.icon = toneIcons[o.tone] ?? 'help'
  state.opts = o
  state.open = true
  return new Promise((resolve) => { state.resolve = resolve })
}

function settle(result) {
  state.resolve?.(result)
  state.resolve = null
  state.open = false
}

const confirm = (opts) => open(opts)
confirm.danger = (title, message = '', opts = {}) => open({ confirmText: 'Sil', ...opts, tone: 'danger', title, message })
confirm.warning = (title, message = '', opts = {}) => open({ ...opts, tone: 'warning', title, message })

export function useOzConfirm() {
  return confirm
}

// Yalnızca OzConfirmHost kullanır
export const ozConfirmInternals = { state, settle }
