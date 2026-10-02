import { isTouchPrimary } from './ozTouch'

const TEXT_FIELD = 'input:not([type=checkbox]):not([type=radio]):not([type=button]):not([type=submit]):not([type=range]):not([type=file]):not([type=color]), textarea, [contenteditable="true"]'

// Adres/araç çubuğu gibi küçük farklar klavye sayılmaz (titremeyi önler).
const MIN_KEYBOARD_PX = 80

// Dokunmatikte kullanıcı bir metin alanına dokunduğunda sanal klavye açılır ve alan
// (özellikle modal/drawer içinde ya da sayfanın altında) klavyenin arkasında kalabilir.
// app.js'den bir kez çağrılır; iki şey yapar:
//
// 1) --oz-kb: klavyenin kapladığı yükseklik (px) <html>'e CSS değişkeni olarak yazılır.
//    Android'de viewport meta'daki interactive-widget=resizes-content sayfayı zaten
//    küçültür (değer 0 kalır); iOS bunu desteklemez, klavye sabit (position: fixed)
//    iskeletin üstüne biner. Sabit katmanlar (OzShell kökü, OzModal/OzDrawer/AppModal
//    arka planı, Offcanvas) `bottom: var(--oz-kb, 0px)` ile klavyenin üstünde biter;
//    böylece Kaydet/İptal ve formun alt kısmı erişilebilir kalır.
// 2) Odaktaki alan, klavye açılma animasyonu bitince görünür bölgenin ortasına kaydırılır
//    (iOS bunu her zaman kendisi yapmıyor).
export function installKeyboardAvoidance() {
  if (typeof window === 'undefined' || !isTouchPrimary()) return

  const vv = window.visualViewport
  const root = document.documentElement
  let timer = null

  const reveal = () => {
    const el = document.activeElement
    if (!el?.matches?.(TEXT_FIELD)) return
    el.scrollIntoView({ block: 'center', behavior: 'smooth' })
  }

  const syncInset = () => {
    if (!vv) return
    const covered = Math.round(window.innerHeight - vv.height - vv.offsetTop)
    root.style.setProperty('--oz-kb', `${covered >= MIN_KEYBOARD_PX ? covered : 0}px`)
  }

  document.addEventListener('focusin', (e) => {
    if (!e.target?.matches?.(TEXT_FIELD)) return
    clearTimeout(timer)
    timer = setTimeout(reveal, 300)
  })

  // Klavye açılıp kapanınca (ve açıkken öneri çubuğu/cihaz dönüşü ile) görünür alan
  // yeniden boyutlanır: boşluğu güncelle, odaktaki alanı yine görünür tut.
  vv?.addEventListener('resize', () => {
    syncInset()
    clearTimeout(timer)
    timer = setTimeout(reveal, 100)
  })
  vv?.addEventListener('scroll', syncInset)
  syncInset()
}
