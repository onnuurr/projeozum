// Dokunmatik birincil cihaz (telefon/tablet) mı? Buralarda bir metin alanına programatik
// odak vermek sanal klavyeyi açıp ekranın yarısını kapatır; bu yüzden otomatik odak
// yalnızca fare/klavye cihazlarında yapılır, dokunmatikte kullanıcı alana kendisi dokunur.
export function isTouchPrimary() {
  return typeof window !== 'undefined' && !!window.matchMedia?.('(pointer: coarse)').matches
}

// Seçim kutularında (OzCombobox, CustomSelect) arama kutusu gösterilsin mi? Dokunmatikte
// kısa listede arama gereksizdir ve kutuya yanlışlıkla dokunmak klavyeyi açıp listeyi
// örter; bu yüzden gizlenir. Masaüstünde her zaman gösterilir (klavye ile gezinme ona bağlı).
// Yeni kayıt ekleme (create) açıksa arama kutusu ekleme metninin girildiği yer olduğundan kalır.
export const TOUCH_SEARCH_MIN_OPTIONS = 8

export function shouldShowSelectSearch(optionCount, canCreate = false) {
  return canCreate || !isTouchPrimary() || optionCount >= TOUCH_SEARCH_MIN_OPTIONS
}

// type="number" alanında mobil klavye tipi. Negatif olamayan tam sayı alanlarında (min >= 0,
// step yok ya da tam sayı) "numeric" → yalnızca rakam tuş takımı (tam klavyeden küçük, sayfayı
// daha az kapatır). Ondalıklı alanlara "decimal" VERİLMEZ: Türkçe yerelde iOS ondalık tuş takımı
// virgül yazar ve type="number" bunu boş değer olarak okuyabilir. Açık inputmode her zaman kazanır.
export function numberInputMode(type, { inputmode, min, step } = {}) {
  if (inputmode) return inputmode
  if (type !== 'number' || min === undefined || min === null || min === '') return undefined
  if (!(Number(min) >= 0)) return undefined
  const hasStep = step !== undefined && step !== null && step !== ''
  if (hasStep && !Number.isInteger(Number(step))) return undefined // 'any' → NaN → ondalıklı sayılır
  return 'numeric'
}
