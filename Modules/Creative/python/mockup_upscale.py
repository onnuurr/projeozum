"""Mokap giydirme — ücretsiz çözünürlük artırma (OpenCV dnn_superres FSRCNN, CPU).

Maliyet için görseller düşük çözünürlükte (1K) üretilir ve burada büyütülür: 2026-10-06 ölçümü,
2K→1K→FSRCNN x2 yüz/el/kumaşta PSNR 41 dB (gözle fark yok denecek kadar). Model dosyası yoksa
LANCZOS'a düşer. Keskinleştirme YAPILMAZ (model dokusunu bozuyor).

Kullanım: python mockup_upscale.py <girdi> <çıktı.png> <hedef_genişlik> <hedef_yükseklik>
"""
import json
import os
import sys

import numpy as np
from PIL import Image

MODELS = os.path.join(os.path.dirname(os.path.abspath(__file__)), "models")


def upscale_to(img, w, h):
    """PIL RGB → tam (w, h). Büyütme oranı ≥1,25 ise önce FSRCNN (x2 / x4), sonra LANCZOS ile tam boya."""
    ratio = max(w / img.width, h / img.height)
    if ratio >= 1.25:
        scale = 4 if ratio > 2.5 else 2
        model = os.path.join(MODELS, f"FSRCNN_x{scale}.pb")
        try:
            import cv2
            sr = cv2.dnn_superres.DnnSuperResImpl_create()
            sr.readModel(model)
            sr.setModel("fsrcnn", scale)
            out = sr.upsample(cv2.cvtColor(np.asarray(img), cv2.COLOR_RGB2BGR))
            img = Image.fromarray(cv2.cvtColor(out, cv2.COLOR_BGR2RGB))
        except Exception:
            pass   # opencv/model yok → doğrudan LANCZOS
    return img if img.size == (w, h) else img.resize((w, h), Image.LANCZOS)


if __name__ == "__main__":
    try:
        src, out, w, h = sys.argv[1], sys.argv[2], int(sys.argv[3]), int(sys.argv[4])
        im = Image.open(src).convert("RGB")
        before = im.size
        upscale_to(im, w, h).save(out)
        print(json.dumps({"ok": True, "from": list(before), "to": [w, h]}))
    except Exception as e:
        print(json.dumps({"ok": False, "error": str(e)}))
        sys.exit(1)
