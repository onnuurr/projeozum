import { ref, computed, watch, toValue, onScopeDispose } from 'vue'
import { usePreferredReducedMotion } from '@vueuse/core'
import { animate } from 'motion-v'

// Sayıyı eski değerinden yenisine akıcı şekilde saydırır (ilk çizimde 0'dan).
// source: sayı, "₺482.600" / "%4,2" gibi TR biçimli metin ya da bunları döndüren getter.
// Dönen `text` aynı biçimde (önek, binlik ayırıcı, ondalık basamak, sonek korunarak) yazılır.
//   const { text } = useOzCountUp(() => props.value)
const FORMAT = /^(\D*?)(-?\d{1,3}(?:\.\d{3})+|-?\d+)(?:,(\d+))?(.*)$/

export function parseTrNumber(input) {
  if (typeof input === 'number') return { value: input, prefix: '', suffix: '', decimals: Number.isInteger(input) ? 0 : 1, raw: false }
  const m = FORMAT.exec(String(input).trim())
  if (!m) return null
  const [, prefix, int, frac = '', suffix] = m
  return { value: Number(`${int.replace(/\./g, '')}.${frac || 0}`), prefix, suffix, decimals: frac.length, raw: true }
}

export function useOzCountUp(source, { duration = 0.9 } = {}) {
  const reduced = usePreferredReducedMotion()
  const parsed = computed(() => parseTrNumber(toValue(source)))
  const current = ref(0)
  let controls

  watch(() => parsed.value?.value, (to) => {
    controls?.stop()
    if (to == null) return
    if (reduced.value === 'reduce') { current.value = to; return }
    controls = animate(current.value, to, {
      duration,
      ease: [0.16, 1, 0.3, 1],
      onUpdate: (v) => { current.value = v },
    })
  }, { immediate: true })

  onScopeDispose(() => controls?.stop())

  const text = computed(() => {
    const p = parsed.value
    if (!p) return String(toValue(source))
    const n = current.value.toLocaleString('tr-TR', { minimumFractionDigits: p.decimals, maximumFractionDigits: p.decimals })
    return `${p.prefix}${n}${p.suffix}`
  })

  return { value: current, text }
}
