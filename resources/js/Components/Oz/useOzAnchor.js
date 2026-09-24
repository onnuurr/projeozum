import { ref, nextTick } from 'vue'

// Tetikleyiciye göre `position: fixed` konum hesaplar (dropdown, tooltip).
// Fixed + .oz-root'a teleport sayesinde tablo/kart gibi overflow:hidden kapsayıcılarda kırpılmaz.
// placement: 'bottom-start' | 'bottom-end' | 'top' | 'bottom'
// Sığmazsa karşı tarafa çevrilir, yatayda ekran kenarına yaslanır.
export function useOzAnchor({ placement = 'bottom-start', offset = 6, margin = 8 } = {}) {
  const style = ref({ top: '0px', left: '0px' })
  const side = ref(placement.startsWith('top') ? 'top' : 'bottom')

  // floating: eleman veya getter; getter, nextTick sonrası (eleman çizildikten sonra) okunur
  async function update(triggerEl, floating) {
    if (!triggerEl) return
    await nextTick()
    const floatingEl = typeof floating === 'function' ? floating() : floating
    const t = triggerEl.getBoundingClientRect()
    const fw = floatingEl?.offsetWidth ?? 0
    const fh = floatingEl?.offsetHeight ?? 0
    const vw = window.innerWidth
    const vh = window.innerHeight

    let s = placement.startsWith('top') ? 'top' : 'bottom'
    if (s === 'bottom' && t.bottom + offset + fh > vh - margin && t.top - offset - fh >= margin) s = 'top'
    else if (s === 'top' && t.top - offset - fh < margin) s = 'bottom'
    side.value = s

    let left
    if (placement.endsWith('-end')) left = t.right - fw
    else if (placement.endsWith('-start')) left = t.left
    else left = t.left + t.width / 2 - fw / 2
    left = Math.min(Math.max(left, margin), vw - fw - margin)

    const top = s === 'bottom' ? t.bottom + offset : t.top - offset - fh
    style.value = { top: `${Math.round(top)}px`, left: `${Math.round(left)}px` }
  }

  return { style, side, update }
}
