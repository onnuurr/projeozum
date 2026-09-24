import { computed, useAttrs } from 'vue'

// inheritAttrs: false olan form bileşenlerinde class/style dış çerçeveye,
// geri kalan attribute'lar (min, autocomplete, name, on* …) iç <input>/<select>/<textarea>'ya gider.
export function useOzSplitAttrs() {
  const attrs = useAttrs()
  const rootAttrs = computed(() => ({ class: attrs.class, style: attrs.style }))
  const inputAttrs = computed(() => {
    const { class: _c, style: _s, ...rest } = attrs
    return rest
  })
  return { rootAttrs, inputAttrs }
}
