"""Renk sadakati (Faz Q) için paylaşılan yardımcılar — yalnız import edilir.

`_svgcommon.py` ile aynı konvansiyon: alt çizgiyle başlayan dosya, tek başına
çalıştırılan bir betik değil, `color_audit.py`/`color_lock.py` tarafından import
edilen ortak numpy/Pillow mantığıdır.

Bilinçli olarak OpenCV/scipy KULLANILMAZ — bu sunucuda `opencv-contrib-python`
kurulu değil (bkz. `enhance.py`'nin Lanczos fallback'i), ekstra bir bağımlılık
riskine girmeden yalnız `numpy` + `Pillow` (ikisi de zaten zorunlu) ile çalışır.
"""

import numpy as np
from PIL import Image, ImageFilter

# --- sRGB <-> CIE Lab (D65 referans beyaz), ICC profili YOK, kapalı-form numpy ---

_D65 = (0.95047, 1.00000, 1.08883)

_RGB_TO_XYZ = np.array([
    [0.4124564, 0.3575761, 0.1804375],
    [0.2126729, 0.7151522, 0.0721750],
    [0.0193339, 0.1191920, 0.9503041],
])
_XYZ_TO_RGB = np.linalg.inv(_RGB_TO_XYZ)


def _srgb_to_linear(c):
    return np.where(c <= 0.04045, c / 12.92, ((c + 0.055) / 1.055) ** 2.4)


def _linear_to_srgb(c):
    c = np.clip(c, 0.0, 1.0)
    return np.where(c <= 0.0031308, c * 12.92, 1.055 * (c ** (1 / 2.4)) - 0.055)


def _f(t):
    delta = 6 / 29
    return np.where(t > delta ** 3, np.cbrt(t), t / (3 * delta ** 2) + 4 / 29)


def _f_inv(t):
    delta = 6 / 29
    return np.where(t > delta, t ** 3, 3 * delta ** 2 * (t - 4 / 29))


def rgb_to_lab(rgb_uint8):
    """(H,W,3) uint8 RGB -> (H,W,3) float64 Lab."""
    rgb = rgb_uint8.astype(np.float64) / 255.0
    lin = _srgb_to_linear(rgb)
    xyz = lin @ _RGB_TO_XYZ.T
    xn, yn, zn = _D65
    fx, fy, fz = _f(xyz[..., 0] / xn), _f(xyz[..., 1] / yn), _f(xyz[..., 2] / zn)
    L = 116 * fy - 16
    a = 500 * (fx - fy)
    b = 200 * (fy - fz)
    return np.stack([L, a, b], axis=-1)


def lab_to_rgb(lab):
    """(H,W,3) float64 Lab -> (H,W,3) uint8 RGB (kırpılmış)."""
    L, a, b = lab[..., 0], lab[..., 1], lab[..., 2]
    fy = (L + 16) / 116
    fx = fy + a / 500
    fz = fy - b / 200
    xn, yn, zn = _D65
    xyz = np.stack([_f_inv(fx) * xn, _f_inv(fy) * yn, _f_inv(fz) * zn], axis=-1)
    lin = xyz @ _XYZ_TO_RGB.T
    srgb = _linear_to_srgb(lin)
    return np.clip(np.round(srgb * 255.0), 0, 255).astype(np.uint8)


def delta_e76(lab1, lab2):
    """İki tek Lab renk (3,) arasındaki CIE76 Delta E (basit Öklid mesafesi)."""
    return float(np.sqrt(np.sum((np.asarray(lab1) - np.asarray(lab2)) ** 2)))


# --- Maskeleme yardımcıları ---

def load_rgb(path):
    """Dosyayı RGB PIL.Image olarak açar (EXIF'e dokunmaz — çağıran hazırlamış olmalı)."""
    return Image.open(path).convert('RGB')


def resize_to_match(img, target_size):
    """`img`'i `target_size` (w,h) ile aynı boyuta getirir (farklıysa Lanczos)."""
    if img.size == target_size:
        return img
    return img.resize(target_size, Image.LANCZOS)


def _clean_mask(mask_bool, erode_px=1, dilate_px=2):
    """Küçük gürültü lekelerini eler (poor-man's erode/dilate — scipy YOK).

    PIL'in MinFilter/MaxFilter'ı ikili (bool) maskede erozyon/genişleme görevi
    görür: MinFilter komşuluktaki en KÜÇÜK değeri alır (0 varsa 0'a çeker =
    erosion), MaxFilter tersini yapar (dilation).
    """
    im = Image.fromarray((mask_bool.astype(np.uint8)) * 255, mode='L')
    if erode_px > 0:
        im = im.filter(ImageFilter.MinFilter(2 * erode_px + 1))
    if dilate_px > 0:
        im = im.filter(ImageFilter.MaxFilter(2 * dilate_px + 1))
    return np.asarray(im) > 127


def diff_mask(posed_img, output_img, threshold=28.0):
    """İki aynı-boyutlu RGB görsel arasındaki piksel farkını eşikleyip bool maske döner.

    `posed_img`: giydirmeden ÖNCEKİ manken görseli. `output_img`: giydirmeden
    SONRAKİ görsel. Prompt zaten kişi/poz/sahneyi birebir koru dediği için,
    aradaki fark büyük ölçüde giysi bölgesidir (bkz. ROADMAP.md Faz Q).
    """
    a = np.asarray(posed_img).astype(np.float64)
    b = np.asarray(output_img).astype(np.float64)
    dist = np.sqrt(np.sum((a - b) ** 2, axis=-1))
    mask = dist > threshold
    return _clean_mask(mask)


def foreground_mask_by_corner_bg(img, tolerance=24.0):
    """Dört köşenin ortalama rengini arka plan varsayıp ondan uzak pikselleri
    ürün (foreground) sayar — `prepare_garment.py::_trim_flat_border`'daki
    "düz arka plan" fikrinin maskeye çevrilmiş hali. Arka plan düz DEĞİLSE
    (varyans yüksekse) sonuç güvenilmez olur; çağıran taraf mask_ratio ile
    bunu ayıklar.
    """
    arr = np.asarray(img).astype(np.float64)
    h, w = arr.shape[:2]
    corners = np.array([arr[0, 0], arr[0, w - 1], arr[h - 1, 0], arr[h - 1, w - 1]])
    bg = corners.mean(axis=0)
    dist = np.sqrt(np.sum((arr - bg) ** 2, axis=-1))
    mask = dist > tolerance
    return _clean_mask(mask, erode_px=1, dilate_px=1)


def dominant_lab_in_mask(img, mask_bool):
    """Maskelenmiş bölgenin medyan Lab rengini döner (None = maske boş)."""
    if not mask_bool.any():
        return None
    lab = rgb_to_lab(np.asarray(img))
    pixels = lab[mask_bool]
    return np.median(pixels, axis=0)


def feather_mask(mask_bool, radius=6):
    """İkili maskeyi 0..1 float alfa'ya yumuşatır (blend sınırında sert kenar olmasın)."""
    im = Image.fromarray((mask_bool.astype(np.uint8)) * 255, mode='L')
    im = im.filter(ImageFilter.GaussianBlur(radius))
    return np.asarray(im).astype(np.float64) / 255.0
