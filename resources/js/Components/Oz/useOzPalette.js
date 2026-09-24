import { ref } from 'vue'

// Ctrl K paletinin açık/kapalı durumu. OzAppLayout paleti çizer; sayfalar buradan açabilir.
//   const { openPalette } = useOzPalette()
const open = ref(false)

export function useOzPalette() {
  return {
    paletteOpen: open,
    openPalette: () => { open.value = true },
    closePalette: () => { open.value = false },
    togglePalette: () => { open.value = !open.value },
  }
}
