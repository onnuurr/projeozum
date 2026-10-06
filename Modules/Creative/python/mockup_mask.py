"""Mokap giydirme — bölge maskesi, önde kalanlar, ayakkabı maskesi ve kırpma kutusu.

Girdi: mockup_segment.py'nin etiket haritası (.npy). Bölge tipleri (bkz. tasarım dokümanı):
  bottom  — kısa: bel → çorap üst kenarı; uzun (midi/maksi): bel → görsel altı, ayakkabı dahil
  top     — boyun → bel + %8, kollar dahil
  set / one_piece — üst ∪ alt
Altta (bottom/set/one_piece) belin hemen üstündeki üst-giysi şeridi ("tuck şeridi") da bölgeye
girer: "üst içeride" talimatı tutmasa bile model üstü içeri sokabilir.

Kullanım:
  python mockup_mask.py mask <labels.npy> <çıktı_öneki> <region> <length>
     → <önek>_region.png, <önek>_occluders.png, <önek>_shoes.png ; stdout JSON {crop, long, ...}
  python mockup_mask.py crop <görsel> <x0> <y0> <x1> <y1> <çıktı.png>
  python mockup_mask.py pair <sol> <sağ> <çıktı.jpg>     (kontrol için yan yana, 1200 px yükseklik)
"""
import json
import sys

import numpy as np
from PIL import Image

HAIR, UPPER, LOWER, SHOES, FACE, LEGS, ARMS = 2, 4, 6, 9, 11, 12, 14
TUCK_BAND = 0.05      # belin ±%5'i (görsel yüksekliğine göre)


def bottom_region(lab, long):
    H, W = lab.shape
    lower = lab == LOWER; legs = lab == LEGS; shoes = lab == SHOES
    if not lower.any():
        raise RuntimeError("Alt giysi (yer tutucu) bulunamadı.")
    ys, xs = np.where(lower); waist = ys.min()
    _, lxs = np.where(lower | legs)
    left, right = lxs.min(), lxs.max()
    m = int((xs.max() - xs.min()) * (0.45 if long else 0.30))   # kloş/geniş paça payı
    if long or not shoes.any():
        bottom = H
    else:
        bottom = np.where(shoes)[0].min() - int(H * 0.004)       # çorap/ayakkabı üst kenarı
    reg = np.zeros_like(lower)
    reg[max(0, waist - int(H * 0.01)):bottom, max(0, left - m):min(W, right + m)] = True
    band = int(H * TUCK_BAND)
    tuck = np.zeros_like(lower)
    tuck[max(0, waist - band):waist + band, max(0, left - m):min(W, right + m)] = True
    tuck &= lab == UPPER
    occl = np.isin(lab, [HAIR, UPPER, ARMS]) & ~tuck
    if not long:
        occl |= shoes
    return (reg | tuck) & ~occl, occl


def top_region(lab):
    H, W = lab.shape
    upper = lab == UPPER; arms = lab == ARMS; lower = lab == LOWER
    if not upper.any():
        raise RuntimeError("Üst giysi (yer tutucu) bulunamadı.")
    ys, _ = np.where(upper); top = ys.min()
    waist = np.where(lower)[0].min() if lower.any() else ys.max()
    rows = slice(max(0, top - int(H * 0.02)), min(H, waist + int(H * 0.08)))
    band = np.zeros_like(upper); band[rows, :] = True
    _, xs = np.where((upper | arms) & band)
    m = int(W * 0.04)
    reg = np.zeros_like(upper); reg[rows, max(0, xs.min() - m):min(W, xs.max() + m)] = True
    occl = np.isin(lab, [HAIR, FACE])
    return reg & ~occl, occl


def crop_box(reg, H, W):
    """Bölgeyi %8–10 payla saran, tam 3:4 oranlı kırpma kutusu (Gemini çıktısı 3:4)."""
    ys, xs = np.where(reg)
    y0, y1, x0, x1 = ys.min(), ys.max(), xs.min(), xs.max()
    my, mx = int((y1 - y0) * 0.08), int((x1 - x0) * 0.10)
    y0, y1, x0, x1 = y0 - my, y1 + my, x0 - mx, x1 + mx
    h, w = y1 - y0, x1 - x0
    if w / h < 0.75:
        w = int(round(h * 0.75))
    else:
        h = int(round(w / 0.75))
    if h > H:
        h = H; w = int(round(h * 0.75))
    if w > W:
        w = W; h = int(round(w / 0.75))
    cy, cx = (y0 + y1) // 2, (x0 + x1) // 2
    top = min(max(0, cy - h // 2), H - h); left = min(max(0, cx - w // 2), W - w)
    return [int(left), int(top), int(left + w), int(top + h)]


def save(m, path):
    Image.fromarray((m * 255).astype(np.uint8)).save(path)


def cmd_mask(labels, prefix, region, length):
    lab = np.load(labels); H, W = lab.shape
    long = length in ("midi", "maxi")
    if region == "bottom":
        reg, occl = bottom_region(lab, long)
    elif region == "top":
        reg, occl = top_region(lab)
    elif region in ("set", "one_piece"):
        rb, _ = bottom_region(lab, long); rt, _ = top_region(lab)
        occl = np.isin(lab, [HAIR, FACE]); reg = (rb | rt) & ~occl
    else:
        raise RuntimeError(f"Bilinmeyen bölge: {region}")
    save(reg, prefix + "_region.png"); save(occl, prefix + "_occluders.png"); save(lab == SHOES, prefix + "_shoes.png")
    return {"crop": crop_box(reg, H, W), "long": long, "region_px": int(reg.sum()), "size": [W, H]}


def cmd_pair(left, right, out, height=1200):
    ims = [Image.open(p).convert("RGB") for p in (left, right)]
    ims = [im.resize((int(im.width * height / im.height), height), Image.LANCZOS) for im in ims]
    canvas = Image.new("RGB", (ims[0].width + ims[1].width + 40, height), "white")
    canvas.paste(ims[0], (0, 0)); canvas.paste(ims[1], (ims[0].width + 40, 0))
    canvas.save(out, quality=90)
    return {"size": list(canvas.size)}


def cmd_crop(src, x0, y0, x1, y1, out):
    Image.open(src).convert("RGB").crop((int(x0), int(y0), int(x1), int(y1))).save(out)
    return {"size": [int(x1) - int(x0), int(y1) - int(y0)]}


if __name__ == "__main__":
    try:
        cmd, args = sys.argv[1], sys.argv[2:]
        res = {"mask": cmd_mask, "crop": cmd_crop, "pair": cmd_pair}[cmd](*args)
        print(json.dumps({"ok": True, **res}))
    except Exception as e:
        print(json.dumps({"ok": False, "error": str(e)}))
        sys.exit(1)
