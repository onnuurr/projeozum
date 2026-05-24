import { reactive } from 'vue'

const state = reactive({
  open: false
})

export function useDrawer() {
  function openDrawer() {
    state.open = true
    document.body.style.overflow = 'hidden'
  }
  function closeDrawer() {
    state.open = false
    document.body.style.overflow = ''
  }
  return { state, openDrawer, closeDrawer }
}
