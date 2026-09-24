import { useStorage } from '@vueuse/core'

// Oz kit teması: tüm Oz sayfaları aynı ref'i paylaşır, seçim localStorage'da tutulur
// (useStorage: erişilemeyen depolamada sessizce bellekte kalır, diğer sekmelerle eşitlenir).
const STORAGE_KEY = 'dashboard-theme'

const theme = useStorage(STORAGE_KEY, 'light')
if (theme.value !== 'light' && theme.value !== 'dark') theme.value = 'light'

export function useOzTheme() {
  function setTheme(value) {
    theme.value = value === 'dark' ? 'dark' : 'light'
  }

  function toggleTheme() {
    setTheme(theme.value === 'dark' ? 'light' : 'dark')
  }

  return { theme, setTheme, toggleTheme }
}
