"""Giydirme öncesi ürün/giysi görseli hazırlığı: stdin payload -> stdout PNG baytları.

Kullanıcının yüklediği ürün/detay fotoğrafları (telefon kamerası vb.) çoğunlukla
yanlış EXIF döndürmeli, gereksiz geniş beyaz/düz arka planlı ve devasa boyutlu
gelir. Bu, AI try-on modeline (Gemini/idm-vton) giden referansın kalitesini
düşürür. Üç adım:
  1) EXIF orientation'a göre düzelt (telefon fotoğrafları yan yatmış gelebilir).
  2) Kenarlardaki neredeyse tek-renk (düz stüdyo/beyaz arka plan) boşluğu kırp —
     ürünü kadraja daha büyük oturtur, modelin "hangi görsel neyi gösteriyor"
     karışıklığını azaltır. Arka plan düz DEĞİLSE (çeşitlilik yüksekse) dokunmaz.
  3) En uzun kenarı sınırla (upload/API payload boyutu için).

Payload:
  {
    "input_path": "/.../garment_123.jpg",  # zorunlu
    "max_side": 2048,                      # en uzun kenar sınırı (0 = sınırsız)
    "trim_border": true,                   # düz arka plan kırpma açık/kapalı
    "mime": "image/png"
  }

Not: render.py / enhance.py ile aynı stdin-JSON / stdout-bytes konvansiyonunu
izler; PHP tarafı PythonGarmentPreparer ile aynı Process desenini kullanır.
Herhangi bir adım başarısız olursa o adım atlanır (asla hata ile durmaz) —
çağıran taraf (PythonGarmentPreparer) da tüm betik başarısız olursa orijinal
dosyayı aynen kullanır.
"""

import io
import json
import sys

from PIL import Image, ImageChops, ImageOps


def _log(msg):
    """Hata ayıklama mesajını stderr'e yazar (stdout sadece görsel baytı taşır)."""
    print(msg, file=sys.stderr)


def _trim_flat_border(im, tolerance=12, min_kept_ratio=0.15):
    """Kenarlardaki neredeyse tek-renk çerçeveyi kırpar.

    Dört köşenin ortalama rengini "arka plan" varsayar; bu renge `tolerance`
    içinde yakın olan dış bölgeyi keser. Kırpma sonucu görselin %`min_kept_ratio`
    kadarından FAZLASINI silecekse (yani arka plan aslında düz değilse / ürün
    zaten kadrajı dolduruyorsa) hiç dokunmadan orijinali döner — yanlışlıkla
    ürünün bir parçasını kesmek riskini engeller.
    """
    try:
        w, h = im.size
        rgb = im.convert("RGB")
        corners = [
            rgb.getpixel((0, 0)),
            rgb.getpixel((w - 1, 0)),
            rgb.getpixel((0, h - 1)),
            rgb.getpixel((w - 1, h - 1)),
        ]
        bg = tuple(sum(c[i] for c in corners) // 4 for i in range(3))

        bg_layer = Image.new("RGB", (w, h), bg)
        diff = ImageChops.difference(rgb, bg_layer)
        # Kanal farkını tek bir "uzaklık" haritasına indir, sonra eşikle.
        diff = diff.convert("L").point(lambda p: 255 if p > tolerance else 0)
        bbox = diff.getbbox()
        if not bbox:
            return im

        kept_w = (bbox[2] - bbox[0]) / w
        kept_h = (bbox[3] - bbox[1]) / h
        if kept_w < min_kept_ratio or kept_h < min_kept_ratio:
            # Arka plan düz değildi ya da ürün zaten kadrajı dolduruyordu.
            return im

        # Ürünün etrafında küçük bir nefes payı bırak.
        pad_x = max(4, int((bbox[2] - bbox[0]) * 0.03))
        pad_y = max(4, int((bbox[3] - bbox[1]) * 0.03))
        left = max(0, bbox[0] - pad_x)
        top = max(0, bbox[1] - pad_y)
        right = min(w, bbox[2] + pad_x)
        bottom = min(h, bbox[3] + pad_y)

        return im.crop((left, top, right, bottom))
    except Exception as e:
        _log(f"prepare_garment: kenar kırpma atlandı: {e}")
        return im


def _clamp_max_side(im, max_side):
    """En uzun kenarı max_side ile sınırla (orantılı küçült)."""
    if not max_side or max_side <= 0:
        return im
    w, h = im.size
    longest = max(w, h)
    if longest <= max_side:
        return im
    ratio = max_side / float(longest)
    return im.resize((max(1, int(w * ratio)), max(1, int(h * ratio))), Image.LANCZOS)


def main():
    payload = json.load(sys.stdin)

    input_path = payload["input_path"]

    max_side = int(payload.get("max_side", 2048))
    trim_border = bool(payload.get("trim_border", True))
    mime = payload.get("mime") or "image/png"

    im = Image.open(input_path)

    # 1) EXIF orientation düzeltmesi (telefon fotoğrafları).
    im = ImageOps.exif_transpose(im)
    if im.mode not in ("RGB", "RGBA"):
        im = im.convert("RGB")

    # 2) Düz arka plan kırpma (opsiyonel, güvenli fallback'li).
    if trim_border:
        im = _trim_flat_border(im)

    # 3) Boyut sınırı.
    im = _clamp_max_side(im, max_side)

    buf = io.BytesIO()
    if mime == "image/jpeg":
        im.convert("RGB").save(buf, format="JPEG", quality=92)
    else:
        im.save(buf, format="PNG")

    sys.stdout.buffer.write(buf.getvalue())


if __name__ == "__main__":
    main()
