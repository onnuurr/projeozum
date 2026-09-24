import { reactive } from 'vue'

// Oz kit bildirimleri. OzShell içindeki OzToastHost bu listeyi çizer.
//   const toast = useOzToast()
//   toast.success('Kaydedildi', 'İş emri #IE-2292 oluşturuldu')
//   toast({ tone: 'danger', title: 'Hata', description: '...', duration: 0 })  // 0 = kendiliğinden kapanmaz
//   toast.info('Arşivlendi', '3 sipariş', { action: { label: 'Geri al', onClick: () => {} } })
const MAX_VISIBLE = 4
const DEFAULT_DURATION = 4500

const state = reactive({ items: [] })
let seq = 0
const timers = new Map()

function remove(id) {
  clearTimeout(timers.get(id))
  timers.delete(id)
  const i = state.items.findIndex((t) => t.id === id)
  if (i !== -1) state.items.splice(i, 1)
}

function schedule(item) {
  if (!item.duration) return
  item.startedAt = Date.now()
  timers.set(item.id, setTimeout(() => remove(item.id), item.remaining))
}

// Fare üstündeyken süre durur, ayrılınca kalan süreden devam eder
function pause(id) {
  const item = state.items.find((t) => t.id === id)
  if (!item?.duration || !timers.has(id)) return
  clearTimeout(timers.get(id))
  timers.delete(id)
  item.remaining -= Date.now() - item.startedAt
}

function resume(id) {
  const item = state.items.find((t) => t.id === id)
  if (item?.duration && !timers.has(id)) schedule(item)
}

function push({ tone = 'info', title, description = '', duration = DEFAULT_DURATION, action = null }) {
  const item = { id: ++seq, tone, title, description, duration, remaining: duration, action }
  state.items.push(item)
  while (state.items.length > MAX_VISIBLE) remove(state.items[0].id)
  schedule(item)
  return item.id
}

const toast = (opts) => push(opts)
for (const tone of ['success', 'info', 'warning', 'danger']) {
  toast[tone] = (title, description = '', opts = {}) => push({ ...opts, tone, title, description })
}
toast.dismiss = remove

export function useOzToast() {
  return toast
}

// Yalnızca OzToastHost kullanır
export const ozToastInternals = { state, remove, pause, resume }
