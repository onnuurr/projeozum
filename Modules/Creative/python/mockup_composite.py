"""Mokap giydirme — birleştirme.

Model çıktısı (kırpılmış bölge için üretilmiş) kırpma boyutuna ölçeklenir ve baz görselle
birleştirilir: bölge maskesi 5 px yumuşatılır, önde kalanlar (saç/eller/üst...) 1,2 px yumuşak
kenarla bazdan en üste konur. Uzun ürünlerde birleşik sonuç yeniden segmente edilir; hem çıktıda
hem bazda "ayakkabı" olan pikseller bazdan geri gelir (kumaşın ayakkabıyı örttüğü yer çıktıda kalır).
Keskinleştirme YAPILMAZ (pilotta model dokusunu bozdu); yalnız eksik kumlanma eşitlenir.

Kullanım:
  python mockup_composite.py <baz.png> <model_çıktısı.png> <önek> <x0> <y0> <x1> <y1> <sonuç.png> <ayakkabı_geri:0|1>
stdout: JSON {ok, align_mae, outside_unchanged, shoe_px_restored}
"""
import json
import sys

import numpy as np
from PIL import Image, ImageFilter


def f32(a):
    return a.astype(np.float32)


def blur(m, r):
    return f32(np.asarray(Image.fromarray((np.clip(m, 0, 1) * 255).astype(np.uint8)).filter(ImageFilter.GaussianBlur(r)))) / 255


def load(p):
    return np.asarray(Image.open(p)) > 127


def highpass_std(img, m):
    g = img.mean(2)
    b = f32(np.asarray(Image.fromarray(np.clip(g, 0, 255).astype(np.uint8)).filter(ImageFilter.GaussianBlur(1.5))))
    return float((g - b)[m].std()) if m.sum() > 1000 else 0.0


def main(base, out, prefix, x0, y0, x1, y1, result, restore):
    x0, y0, x1, y1 = map(int, (x0, y0, x1, y1))
    B = f32(np.asarray(Image.open(base).convert("RGB")))
    reg = load(prefix + "_region.png"); occl = load(prefix + "_occluders.png")
    # Düşük çözünürlükte (1K) üretilen çıktı kırpma boyutundan küçükse önce FSRCNN ile büyütülür.
    from mockup_upscale import upscale_to
    O = f32(np.asarray(upscale_to(Image.open(out).convert("RGB"), x1 - x0, y1 - y0)))
    Bb = B[y0:y1, x0:x1]; Rb = reg[y0:y1, x0:x1]

    keep = ~(blur(Rb, 25) > 0.01)
    mae = float(np.abs(O.mean(2) - Bb.mean(2))[keep].mean()) if keep.any() else -1.0

    hb, ho = highpass_std(Bb, Rb), highpass_std(O, Rb)
    if ho < hb:
        O = O + np.random.RandomState(0).normal(0, np.sqrt(hb ** 2 - ho ** 2) * 0.8, O.shape[:2])[..., None]

    a = (blur(Rb, 5) * (1 - blur(occl[y0:y1, x0:x1], 1.2)))[..., None]
    R = B.copy()
    R[y0:y1, x0:x1] = Bb * (1 - a) + np.clip(O, 0, 255) * a

    restored = 0
    if restore == "1":
        from mockup_segment import SHOES, segment
        lab = segment(Image.fromarray(R.astype(np.uint8)))[0]
        sh = (lab == SHOES) & load(prefix + "_shoes.png")
        r = blur(sh, 1.5)[..., None]
        R = R * (1 - r) + B * r
        restored = int(sh.sum())

    Image.fromarray(np.clip(R, 0, 255).astype(np.uint8)).save(result)
    outside = ~(blur(reg, 5) > 0.001)
    if restore == "1":
        outside &= ~load(prefix + "_shoes.png")
    unchanged = float((np.abs(R - B).max(2)[outside] < 1).mean()) if outside.any() else 1.0
    return {"align_mae": round(mae, 2), "outside_unchanged": round(unchanged, 5), "shoe_px_restored": restored}


if __name__ == "__main__":
    try:
        print(json.dumps({"ok": True, **main(*sys.argv[1:10])}))
    except Exception as e:
        print(json.dumps({"ok": False, "error": str(e)}))
        sys.exit(1)
