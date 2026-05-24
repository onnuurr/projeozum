import { reactive } from 'vue'

const state = reactive({
  modals: {}  // { id: boolean }
})

export function useModal() {
  function openModal(id) {
    state.modals[id] = true
    document.body.style.overflow = 'hidden'
  }

  function closeModal(id) {
    state.modals[id] = false
    // Hiç açık modal kalmadıysa scroll'u serbest bırak
    const anyOpen = Object.values(state.modals).some(v => v)
    if (!anyOpen) document.body.style.overflow = ''
  }

  function isOpen(id) {
    return !!state.modals[id]
  }

  return { state, openModal, closeModal, isOpen }
}
