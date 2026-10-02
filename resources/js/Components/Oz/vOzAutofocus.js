import { isTouchPrimary } from './ozTouch'

// v-autofocus: yerel `autofocus` attribute'unun yerine kullanılır (app.js'de global kayıtlı).
// Masaüstünde alanı odaklar; dokunmatikte hiçbir şey yapmaz — aksi halde sanal klavye
// sayfa açılır açılmaz ekranın yarısını kapatır (bkz. ozTouch.js). Bir bileşene
// (ör. <OzInput v-autofocus>) konursa kökün içindeki ilk metin alanını bulur.
export const vOzAutofocus = {
  mounted(el) {
    if (isTouchPrimary()) return
    const target = el.matches?.('input, textarea, select')
      ? el
      : el.querySelector?.('input:not([type=hidden]), textarea, select')
    target?.focus({ preventScroll: true })
  },
}
