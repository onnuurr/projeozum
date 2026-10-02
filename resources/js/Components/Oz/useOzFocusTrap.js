import { ref, watch, nextTick, onBeforeUnmount } from 'vue'
import { useEventListener } from '@vueuse/core'
import { isTouchPrimary } from './ozTouch'

// Modal/drawer gibi diyaloglar için ortak klavye ve odak davranışı:
// açılınca ilk form alanına (yoksa alttaki ilk butona) odaklanır, Tab'ı içeride tutar,
// Esc ile onEscape'i çağırır, kapanınca odağı açan öğeye geri verir.
// İç içe açılan diyaloglarda (drawer içinden modal) klavyeyi yalnızca en üstteki dinler;
// dönen `layer` z-index'i açılma sırasına göre artar, son açılan her zaman üstte görünür.
const stack = []
const BASE_LAYER = 100
const FOCUSABLE = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'

// initialFocus: 'auto' (ilk alan → alt buton) | 'container' (diyaloğun kendisi; içerik ağırlıklı panellerde kaydırmayı önler)
export function useOzFocusTrap(openRef, containerRef, { onEscape, footerSelector = '.foot', initialFocus = 'auto' } = {}) {
  let lastFocused = null
  const token = Symbol('oz-dialog')
  const layer = ref(BASE_LAYER)

  function onKeydown(e) {
    if (!openRef.value || stack.at(-1) !== token) return
    if (e.key === 'Escape') {
      e.stopPropagation()
      onEscape?.()
      return
    }
    if (e.key === 'Tab' && containerRef.value) {
      const items = [...containerRef.value.querySelectorAll(FOCUSABLE)]
      if (!items.length) return
      const first = items[0]
      const last = items[items.length - 1]
      const onContainer = document.activeElement === containerRef.value
      if (e.shiftKey && (document.activeElement === first || onContainer)) { e.preventDefault(); last.focus() }
      else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus() }
    }
  }

  watch(openRef, async (v) => {
    if (v) {
      lastFocused = document.activeElement
      stack.push(token)
      layer.value = BASE_LAYER + stack.length * 2
      await nextTick()
      const el = containerRef.value
      // Dokunmatikte ilk forma odaklanmak klavyeyi açar ve diyaloğu örter → diyaloğun kendisine odaklan
      if (initialFocus === 'container' || isTouchPrimary()) { el?.focus({ preventScroll: true }); return }
      const target = el?.querySelector('input:not([type=hidden]), select, textarea')
        || el?.querySelector(`${footerSelector} button:not([disabled])`)
        || el?.querySelector(FOCUSABLE)
      target?.focus()
    } else {
      release()
      lastFocused?.focus?.()
    }
  }, { immediate: true })

  function release() {
    const i = stack.indexOf(token)
    if (i !== -1) stack.splice(i, 1)
  }

  useEventListener(document, 'keydown', onKeydown)

  onBeforeUnmount(release)

  return { layer }
}
