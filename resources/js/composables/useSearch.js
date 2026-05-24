import { reactive } from 'vue'

const state = reactive({
  open: false
})

export function useSearch() {
  function openSearch() { state.open = true }
  function closeSearch() { state.open = false }
  return { state, openSearch, closeSearch }
}
