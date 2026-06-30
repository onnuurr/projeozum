"""Creative görsel iyileştirme: stdin payload -> stdout PNG baytları.

AI ile üretilen ham görselin çözünürlüğünü/keskinliğini artırıp web için doğru
sRGB renklerle sunmak içindir. İki aşama:
  1) Upscale: OpenCV dnn_superres (FSRCNN/EDSR/LapSRN/ESPCN). Model yoksa veya
     OpenCV import edilemezse Pillow LANCZOS ile `scale`× büyütmeye düşer.
  2) Son dokunuş: Pillow UnsharpMask + kontrast + doygunluk + sRGB profil etiketi.

Payload:
  {
    "input_path": "C:/.../ai_xxx.png",      # zorunlu
    "model_path": ".../models/FSRCNN_x2.pb", # opsiyonel; yoksa Lanczos fallback
    "model_name": "fsrcnn",                  # fsrcnn | edsr | lapsrn | espcn
    "scale": 2,
    "unsharp_radius": 2.0, "unsharp_percent": 120, "unsharp_threshold": 3,
    "contrast": 1.04, "saturation": 1.03,
    "max_side": 2048,                        # en uzun kenar sınırı (0 = sınırsız)
    "mime": "image/png"
  }

Not: Bu betik render motorunun (render.py) stdin-JSON / stdout-bytes konvansiyonunu
izler; PHP tarafı PythonImageEnhancer ile aynı Process desenini kullanır.
"""

import io
import json
import os
import sys

from PIL import Image, ImageEnhance, ImageFilter, ImageCms


def _log(msg):
    """Hata ayıklama mesajını stderr'e yazar (stdout sadece görsel baytı taşır)."""
    print(msg, file=sys.stderr)


def _upscale_opencv(input_path, model_path, model_name, scale):
    """OpenCV dnn_superres ile büyütür; RGB PIL.Image döner. Olmazsa None."""
    if not model_path or not os.path.isfile(model_path):
        return None
    try:
        import cv2  # opencv-contrib-python (dnn_superres bu pakette)
        import numpy as np  # noqa: F401  (cv2 ile birlikte gelir)
    except ImportError as e:
        _log(f"enhance: opencv import edilemedi, Lanczos fallback: {e}")
        return None

    try:
        sr = cv2.dnn_superres.DnnSuperResImpl_create()
        sr.readModel(model_path)
        sr.setModel(model_name, scale)
        img = cv2.imread(input_path, cv2.IMREAD_COLOR)  # BGR
        if img is None:
            _log("enhance: cv2.imread None döndü, Lanczos fallback")
            return None
        result = sr.upsample(img)  # BGR, scale× büyük
        rgb = cv2.cvtColor(result, cv2.COLOR_BGR2RGB)
        return Image.fromarray(rgb)
    except Exception as e:  # cv2.error dahil
        _log(f"enhance: dnn_superres başarısız, Lanczos fallback: {e}")
        return None


def _upscale_lanczos(im, scale):
    """Pillow LANCZOS ile `scale`× büyütür (fallback)."""
    w, h = im.size
    return im.resize((max(1, w * scale), max(1, h * scale)), Image.LANCZOS)


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


def _to_srgb(im):
    """Görseli sRGB profiliyle etiketler (web'de doğru renk için)."""
    try:
        srgb = ImageCms.createProfile("sRGB")
        im.info["icc_profile"] = ImageCms.ImageCmsProfile(srgb).tobytes()
    except Exception as e:  # ImageCms her ortamda olmayabilir
        _log(f"enhance: sRGB profili eklenemedi (atlanıyor): {e}")
    return im


def main():
    payload = json.load(sys.stdin)

    input_path = payload["input_path"]
    if not os.path.isfile(input_path):
        raise RuntimeError(f"enhance: girdi görseli bulunamadı: {input_path}")

    model_path = (payload.get("model_path") or "").strip()
    model_name = (payload.get("model_name") or "fsrcnn").strip().lower()
    scale = max(1, int(payload.get("scale", 2)))
    max_side = int(payload.get("max_side", 2048))
    mime = payload.get("mime") or "image/png"

    unsharp_radius = float(payload.get("unsharp_radius", 2.0))
    unsharp_percent = int(payload.get("unsharp_percent", 120))
    unsharp_threshold = int(payload.get("unsharp_threshold", 3))
    contrast = float(payload.get("contrast", 1.04))
    saturation = float(payload.get("saturation", 1.03))

    # 1) Upscale (opencv super-res -> yoksa Lanczos)
    im = None
    if scale > 1:
        im = _upscale_opencv(input_path, model_path, model_name, scale)
        if im is None:
            im = _upscale_lanczos(Image.open(input_path).convert("RGB"), scale)
    else:
        im = Image.open(input_path).convert("RGB")

    if im.mode != "RGB":
        im = im.convert("RGB")

    # 2) Devasa çıktıları sınırla
    im = _clamp_max_side(im, max_side)

    # 3) Son dokunuş: keskinlik + kontrast + doygunluk
    if unsharp_percent > 0:
        im = im.filter(ImageFilter.UnsharpMask(
            radius=unsharp_radius, percent=unsharp_percent, threshold=unsharp_threshold))
    if contrast != 1.0:
        im = ImageEnhance.Contrast(im).enhance(contrast)
    if saturation != 1.0:
        im = ImageEnhance.Color(im).enhance(saturation)

    # 4) sRGB etiketi
    im = _to_srgb(im)

    buf = io.BytesIO()
    if mime == "image/jpeg":
        im.save(buf, format="JPEG", quality=92, icc_profile=im.info.get("icc_profile"))
    else:
        im.save(buf, format="PNG", icc_profile=im.info.get("icc_profile"))

    sys.stdout.buffer.write(buf.getvalue())


if __name__ == "__main__":
    main()
