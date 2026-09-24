// Türkçe karakter ve büyük/küçük harf duyarsız karşılaştırma için metni sadeleştirir
// ("ÜRÜN", "urun", "Ürün" → "urun"; "IZMIR", "izmir" → "izmir").
export function ozFold(s) {
  return String(s ?? '')
    .toLocaleLowerCase('tr')
    .replace(/ı/g, 'i')
    .normalize('NFD')
    .replace(/[̀-ͯ]/g, '')
}
