# Özel ikonlar (`oz:` seti)

Bu klasördeki her `.svg`, sistemin ortak ikon kaydına (`resources/js/lucideIcons.js`)
`oz:<dosya-adı>` adıyla otomatik eklenir ve **tüm ikon seçicilerde** (Menü Yönetimi,
Kategori formu, ileride eklenecekler) görünür. Kod değişikliği gerekmez — dosyayı ekle,
frontend build'i al.

Örnek: `cocuk-etek.svg` → DB'de `oz:cocuk-etek` olarak saklanır.

## Çizim kuralları (Lucide ile aynı aileden görünsün diye)

- Tuval **24×24**, `viewBox="0 0 24 24"`; kenarlardan ~1–2px boşluk bırak.
- **Sadece çizgi**: `fill="none"`, `stroke="currentColor"`, `stroke-width="2"`,
  `stroke-linecap="round"`, `stroke-linejoin="round"` (kök `<svg>`'de; renk temadan gelir).
- Yalnızca düz şekil öğeleri: `path`, `rect`, `circle`, `ellipse`, `line`, `polyline`, `polygon`.
  **`<g>`, `transform`, dolgu, gradyan, metin, gömülü görsel yok** — okuyucu bunları yok sayar.
  Figma/Inkscape'ten dışa aktarırken grupları çöz ("flatten"/"ungroup"), transform'ları uygula.
- Küçük boyutta (16–24px) okunmalı: 2px çizgiler arasında en az 2px boşluk, gereksiz detay yok.
- `<title>` içine Türkçe ad yaz — seçicide etiket ve arama için kullanılır.

## Dosya adı

- Küçük harf, ASCII, kelimeler tire ile: `cocuk-etek.svg` (ç/ğ/ı/ö/ş/ü yok).
- `oz:` öneki dahil en fazla 64 karakter (DB kolonu).
- **Yeniden adlandırma / silme:** ad DB'de saklandığı için o ikonu kullanan kayıtlar varsayılan
  ikona düşer. Gerekirse eski adı kullanan kayıtları bir migration ile yeni ada taşı.

## Şablon

```svg
<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
  <title>Türkçe Ad</title>
  <path d="..." />
</svg>
```
