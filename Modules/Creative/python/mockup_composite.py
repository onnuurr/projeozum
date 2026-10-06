"""Mokap giydirme — birleştirme.

Model çıktısı (kırpılmış bölge için üretilmiş) kırpma boyutuna ölçeklenir ve baz görselle
birleştirilir: bölge maskesi 5 px yumuşatılır, önde kalanlar (saç/eller/üst...) 1,2 px yumuşak
kenarla bazdan en üste konur. Kol/el maskesi bazın renginden inceltilir (arm_matte): ten renkli,
arka plan ve gri yer tutucu renksizdir; maskenin taştığı renksiz pikseller (elin dış kenarı, parmak
arası boşluk) bazdan değil model çıktısından gelir — aksi halde yeni giysinin üstünde elin çevresinde
açık renkli hale ve parmak arasında gri leke kalıyordu (2026-10-06, sonuç #12). Uzun ürünlerde birleşik sonuç yeniden segmente edilir; hem çıktıda
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


ARMS = 14                 # mockup_segment etiketi (kol + el teni)
SKIN_CHROMA = (28, 45)    # max(RGB)-min(RGB): bu aralıkta tenlik 0 → 1 (ten iç ≥ ~43, arka plan/yer tutucu ≤ ~22)
ARM_ERODE = 3             # px: bazdaki elin dış kenarı arka planla karışık (açık renkli) — alınmaz


def arm_matte(Bb, occl_c, lab_c):
    """Önde kalan maskesi (0–1): kol/el piksellerinde bazın ten rengine göre yumuşak, ARM_ERODE px içeriden
    (2026-10-06 ölçümü: dış 2 px'in parlaklığı arka planla karışık, ~205 / iç ten ~178 — beyaz kontur yapıyordu);
    diğer önde kalanlar (saç, üst, ayakkabı) olduğu gibi."""
    occ = occl_c.astype(np.float32)
    if lab_c is None:
        return occ
    arm = occl_c & (lab_c == ARMS)
    if not arm.any():
        return occ
    chroma = Bb.max(2) - Bb.min(2)
    skin = np.clip((chroma - SKIN_CHROMA[0]) / (SKIN_CHROMA[1] - SKIN_CHROMA[0]), 0, 1)
    inner = np.asarray(Image.fromarray(arm.astype(np.uint8) * 255).filter(ImageFilter.MinFilter(2 * ARM_ERODE + 1))) > 127
    occ[arm] = (inner * skin)[arm]
    return occ


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

    try:
        lab_c = np.load(prefix + "_labels.npy")[y0:y1, x0:x1]
    except (OSError, ValueError):
        lab_c = None
    occ_c = occl[y0:y1, x0:x1]
    # Bölge maskesi önde kalanları dışlar; kutusu içindeki kol/el pikselleri bölgeye geri katılır ki
    # hangisinin bazdan geleceğine ten matı karar versin (parmak arası boşluk → model çıktısı).
    reff = Rb.copy()
    if lab_c is not None and Rb.any():
        ys, xs = np.where(Rb)
        inbox = np.zeros_like(Rb); inbox[ys.min():ys.max() + 1, xs.min():xs.max() + 1] = True
        reff |= occ_c & (lab_c == ARMS) & inbox
    a = (blur(reff, 5) * (1 - blur(arm_matte(Bb, occ_c, lab_c), 1.2)))[..., None]
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
