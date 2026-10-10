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
  python mockup_mask.py inset <birleşik> <ürün_foto> <x0> <y0> <x1> <y1> <labels.npy|-> <çıktı.png>
     → köşeye yuvarlak detay (kutu ürün fotoğrafında 0..1); stdout JSON {corner, diameter}
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
        # Kol + el bütünüyle bölgede (modelden): üst bölge kolu bel + %8'de kesiyor, alt bölge kolu dışlıyor →
        # el ortasından ikiye bölünüyor, parmaklar bazdan (açık arka planlı) geliyor, siyah elbisede beyaz hale
        # ve model eli eteğin arkasına koyunca havada kalan yarım el oluyordu (2026-10-10, sonuç #30/#31).
        # SAM el maskesinin kenarı da (açık arka plan karışık) kalmasın diye birkaç px genişletilir.
        import cv2
        r = max(2, int(W * 0.004))
        arms = cv2.dilate((lab == ARMS).astype(np.uint8), cv2.getStructuringElement(cv2.MORPH_ELLIPSE, (2 * r + 1, 2 * r + 1))).astype(bool)
        occl = np.isin(lab, [HAIR, FACE]); reg = (rb | rt | arms) & ~occl
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


INSET_D = 0.30        # yuvarlak detay çapı (görsel genişliğine göre)
INSET_MARGIN = 0.035
INSET_MAX_SCALE = 2.0


def lift_dark(img):
    """Koyu üründe (siyah kumaşta siyah fiyonk) detay okunmuyordu: yalnız parlaklık kanalında yerel kontrast
    (CLAHE) + hafif gölge açma; renk tonu değişmez. Açık ürünlere dokunulmaz."""
    import cv2
    lab = cv2.cvtColor(np.asarray(img), cv2.COLOR_RGB2LAB)
    L = lab[..., 0]
    if L.mean() > 90:
        return img
    L = cv2.createCLAHE(clipLimit=2.0, tileGridSize=(4, 4)).apply(L)
    L = (255 * (L / 255.0) ** 0.8).astype(np.uint8)
    lab[..., 0] = L
    return Image.fromarray(cv2.cvtColor(lab, cv2.COLOR_LAB2RGB))


def cmd_inset(composite, photo, x0, y0, x1, y1, labels, out):
    """Mokap pozunun bir köşesine ürün fotoğrafından yuvarlak detay işler (2026-10-10). Kutu ürün
    fotoğrafında 0..1 normalize; kare kırpılıp çapa büyütülür. Köşe = mankenle en az çakışan köşe
    (etiket haritası; yoksa sağ alt). Beyaz çerçeve + yumuşak gölge."""
    from PIL import ImageDraw, ImageFilter
    im = Image.open(composite).convert("RGB"); W, H = im.size
    ph = Image.open(photo).convert("RGB"); pw, phh = ph.size
    x0, y0, x1, y1 = (float(v) for v in (x0, y0, x1, y1))
    cx, cy = (x0 + x1) / 2 * pw, (y0 + y1) / 2 * phh
    side = min(max((x1 - x0) * pw, (y1 - y0) * phh) * 1.1, pw, phh)
    D = int(W * INSET_D); m = int(W * INSET_MARGIN); ring = max(4, int(W * 0.007))
    # En fazla INSET_MAX_SCALE büyütme: düşük çözünürlüklü fotoğrafta küçük kutu bloklaşıyordu (640 px, x3,5);
    # kutu gerekirse merkezinden büyütülür (detay biraz daha geniş görünür ama net kalır).
    side = min(max(side, D / INSET_MAX_SCALE), pw, phh)
    left = min(max(0, cx - side / 2), pw - side); top = min(max(0, cy - side / 2), phh - side)
    from mockup_upscale import upscale_to
    detail = upscale_to(ph.crop((int(left), int(top), int(left + side), int(top + side))), D, D)
    detail = lift_dark(detail)

    corners = {"bottom_right": (W - m - D, H - m - D), "bottom_left": (m, H - m - D),
               "top_right": (W - m - D, m), "top_left": (m, m)}
    corner = "bottom_right"
    if labels != "-":
        lab = np.load(labels)
        if lab.shape != (H, W):
            lab = np.asarray(Image.fromarray(lab).resize((W, H), Image.NEAREST))
        person = lab != BG
        cost = {k: person[y:y + D, x:x + D].mean() for k, (x, y) in corners.items()}
        corner = min(cost, key=lambda k: (round(cost[k], 3), list(corners).index(k)))   # eşitlikte sağ alt önce
    x, y = corners[corner]

    ss = 4                                                         # kenar yumuşatma için büyük çizilip küçültülür
    big = Image.new("L", (D * ss, D * ss), 0); ImageDraw.Draw(big).ellipse((0, 0, D * ss - 1, D * ss - 1), fill=255)
    circle = big.resize((D, D), Image.LANCZOS)
    pad = ring * 4
    shadow = Image.new("L", (D + 2 * pad, D + 2 * pad), 0)
    shadow.paste(circle.point(lambda v: v * 0.35), (pad, pad + ring))
    shadow = shadow.filter(ImageFilter.GaussianBlur(ring * 1.5))
    im.paste(Image.new("RGB", shadow.size, (0, 0, 0)), (x - pad, y - pad), shadow)
    outer = Image.new("L", (D + 2 * ring, D + 2 * ring), 0)
    bigo = Image.new("L", (outer.width * ss, outer.height * ss), 0)
    ImageDraw.Draw(bigo).ellipse((0, 0, bigo.width - 1, bigo.height - 1), fill=255)
    outer = bigo.resize(outer.size, Image.LANCZOS)
    im.paste(Image.new("RGB", outer.size, (255, 255, 255)), (x - ring, y - ring), outer)
    im.paste(detail, (x, y), circle)
    im.save(out)
    return {"corner": corner, "diameter": D}


def cmd_crop(src, x0, y0, x1, y1, out):
    Image.open(src).convert("RGB").crop((int(x0), int(y0), int(x1), int(y1))).save(out)
    return {"size": [int(x1) - int(x0), int(y1) - int(y0)]}


if __name__ == "__main__":
    try:
        cmd, args = sys.argv[1], sys.argv[2:]
        res = {"mask": cmd_mask, "crop": cmd_crop, "pair": cmd_pair, "closeup": cmd_closeup, "inset": cmd_inset}[cmd](*args)
        print(json.dumps({"ok": True, **res}))
    except Exception as e:
        print(json.dumps({"ok": False, "error": str(e)}))
        sys.exit(1)
