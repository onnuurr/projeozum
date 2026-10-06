"""Mokap giydirme — bölge maskesi, önde kalanlar, ayakkabı maskesi ve kırpma kutusu.

Girdi: mockup_segment.py'nin etiket haritası (.npy). Bölge tipleri (bkz. tasarım dokümanı):
  bottom  — kısa: bel → çorap üst kenarı; uzun (midi/maksi): bel → görsel altı, ayakkabı dahil
  top     — boyun → bel + %8, kollar dahil
  outerwear — omuz → ürün boyu (bel + %6…%48), gövde + kollar; iç kombinin üstüne, yer tutucusuz
  set / one_piece — üst ∪ alt
Altta (bottom/set/one_piece) belin hemen üstündeki üst-giysi şeridi ("tuck şeridi") da bölgeye
girer: "üst içeride" talimatı tutmasa bile model üstü içeri sokabilir.

Kullanım:
  python mockup_mask.py mask <labels.npy> <çıktı_öneki> <region> <length>
     → <önek>_region.png, <önek>_occluders.png, <önek>_shoes.png ; stdout JSON {crop, long, ...}
  python mockup_mask.py crop <görsel> <x0> <y0> <x1> <y1> <çıktı.png>
  python mockup_mask.py pair <sol> <sağ> <çıktı.jpg>     (kontrol için yan yana, 1200 px yükseklik)
  python mockup_mask.py closeup <birleşik> <baz> <labels.npy> <shot>
     → stdout JSON {box}: AI'sız yakın plan kadrajı (product|upper_body|lower_body|neckline|hem), 3:4
"""
import json
import sys

import numpy as np
from PIL import Image

BG, HAIR, UPPER, LOWER, SHOES, FACE, LEGS, ARMS = 0, 2, 4, 6, 9, 11, 12, 14
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


def outerwear_region(lab, length):
    """Dış giyim (ceket/mont/yelek): omuzdan ürün boyuna gövde + kollar, ceketin taşma payıyla. Ürün iç
    kombinin ÜSTÜNE giyilir; yer tutucu yoktur. Saç ve yüz bazdan korunur; eller bölgede (kol boyu modelde)."""
    H, W = lab.shape
    upper = lab == UPPER; arms = lab == ARMS; lower = lab == LOWER
    if not upper.any():
        raise RuntimeError("Üst giysi (iç kombin) bulunamadı.")
    ys, _ = np.where(upper)
    top = ys.min() - int(H * 0.03)
    waist = np.where(lower)[0].min() if lower.any() else ys.max()
    drop = {"mini": 0.06, "knee": 0.20, "midi": 0.32, "maxi": 0.48}.get(length, 0.10)
    bottom = min(H, waist + int(H * drop))
    rows = slice(max(0, top), bottom)
    band = np.zeros_like(upper); band[rows, :] = True
    _, xs = np.where((upper | arms | lower) & band)
    m = int(W * 0.06)
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
    elif region == "outerwear":
        reg, occl = outerwear_region(lab, length)
    elif region in ("set", "one_piece"):
        rb, _ = bottom_region(lab, long); rt, _ = top_region(lab)
        occl = np.isin(lab, [HAIR, FACE]); reg = (rb | rt) & ~occl
    else:
        raise RuntimeError(f"Bilinmeyen bölge: {region}")
    save(reg, prefix + "_region.png"); save(occl, prefix + "_occluders.png"); save(lab == SHOES, prefix + "_shoes.png")
    return {"crop": crop_box(reg, H, W), "long": long, "region_px": int(reg.sum()), "size": [W, H]}


def fit_portrait(x0, y0, x1, y1, H, W, ratio=0.75):
    """Kutuyu merkezinden tam 3:4 dikey orana genişletir ve görsele sığdırır."""
    w, h = x1 - x0, y1 - y0
    if w / max(h, 1) < ratio:
        w = h * ratio
    else:
        h = w / ratio
    if h > H:
        h = H; w = h * ratio
    if w > W:
        w = W; h = w / ratio
    cx, cy = (x0 + x1) / 2, (y0 + y1) / 2
    left = min(max(0, cx - w / 2), W - w); top = min(max(0, cy - h / 2), H - h)
    return [int(left), int(top), int(left + w), int(top + h)]


def cmd_closeup(composite, base, labels, shot):
    """Birleşik mokap görselinden AI'sız yakın plan kutusu. Ürün = bazdan farklı pikseller (gerçekten
    giydirilen alan); bel = bazdaki yer tutucu alt giysinin üst kenarı; kişi = segmentasyon."""
    import cv2
    R = np.asarray(Image.open(composite).convert("RGB")).astype(np.int16)
    B = np.asarray(Image.open(base).convert("RGB").resize((R.shape[1], R.shape[0]))).astype(np.int16)
    lab = np.load(labels)
    if lab.shape != R.shape[:2]:
        lab = np.asarray(Image.fromarray(lab).resize((R.shape[1], R.shape[0]), Image.NEAREST))
    H, W = lab.shape
    changed = cv2.morphologyEx((np.abs(R - B).max(2) > 18).astype(np.uint8), cv2.MORPH_OPEN, np.ones((7, 7), np.uint8)) > 0
    if changed.sum() < H * W * 0.005:
        raise RuntimeError("Ürün alanı bulunamadı (birleşik görsel bazla aynı).")
    ys, xs = np.where(changed)
    py0, py1 = np.percentile(ys, [1, 99]); px0, px1 = np.percentile(xs, [1, 99])
    pys, pxs = np.where(lab != BG)
    top, bot, left, right = pys.min(), pys.max(), pxs.min(), pxs.max()
    lower = np.where(lab == LOWER)[0]
    waist = lower.min() if lower.size else (py0 + py1) / 2
    ph, body = py1 - py0, bot - top
    m = 0.06 * body
    boxes = {
        "product":    (px0 - m, py0 - m, px1 + m, py1 + m),
        "neckline":   (px0, py0 - 0.12 * ph, px1, py0 + 0.30 * ph),
        "hem":        (px0, py1 - 0.30 * ph, px1, py1 + 0.12 * ph),
        "upper_body": (left - m, top - m, right + m, waist + 0.12 * body),
        "lower_body": (left - m, waist - 0.06 * body, right + m, bot + m),
    }
    if shot not in boxes:
        raise RuntimeError(f"Kırpılamayan kadraj: {shot}")
    return {"box": fit_portrait(*boxes[shot], H, W), "product_box": [int(px0), int(py0), int(px1), int(py1)]}


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
        res = {"mask": cmd_mask, "crop": cmd_crop, "pair": cmd_pair, "closeup": cmd_closeup}[cmd](*args)
        print(json.dumps({"ok": True, **res}))
    except Exception as e:
        print(json.dumps({"ok": False, "error": str(e)}))
        sys.exit(1)
