import { reactive } from 'vue'

let _id = 0
const state = reactive({
  toasts: []
})

export function useToast() {
  function showToast({ type = 'info', title, message = '', duration = 4000 }) {
    const id = ++_id
    const toast = { id, type, title, message, duration, leaving: false }
    state.toasts.push(toast)

    setTimeout(() => dismissToast(id), duration)
  }

  function dismissToast(id) {
    const t = state.toasts.find(x => x.id === id)
    if (!t || t.leaving) return
    t.leaving = true
    setTimeout(() => {
      const idx = state.toasts.findIndex(x => x.id === id)
      if (idx > -1) state.toasts.splice(idx, 1)
    }, 240)
  }

  return { toasts: state.toasts, showToast, dismissToast }
}
