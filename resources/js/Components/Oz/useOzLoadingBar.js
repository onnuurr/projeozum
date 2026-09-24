import { reactive } from 'vue'

// Sayfanın en üstündeki ince ilerleme çubuğunun durumu (OzLoadingBar çizer).
// OzAppLayout rota geçişlerinde kendisi çağırır; uzun işlemler için sayfalar da kullanabilir:
//   const bar = useOzLoadingBar(); bar.start(); await kaydet(); bar.done()
// İç içe çağrılar sayılır: son done() gelene kadar çubuk bitmez.
const state = reactive({ active: false, progress: 0, pending: 0 })
let trickle = null

function start() {
  state.pending++
  if (state.active) return
  state.active = true
  state.progress = 0.08
  clearInterval(trickle)
  // gerçek yüzde bilinmediği için %90'a yaklaşarak yavaşlar
  trickle = setInterval(() => {
    state.progress = Math.min(0.9, state.progress + (0.9 - state.progress) * 0.12)
  }, 250)
}

function done() {
  state.pending = Math.max(0, state.pending - 1)
  if (state.pending || !state.active) return
  clearInterval(trickle)
  state.progress = 1
  state.active = false
}

const api = { start, done }

export function useOzLoadingBar() {
  return api
}

// Yalnızca OzLoadingBar kullanır
export const ozLoadingBarState = state
