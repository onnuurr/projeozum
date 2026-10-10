"""Mokap giydirme — insan/kıyafet ayrıştırma (etiket haritası).

Lisans: yalnız Apache 2.0 bileşenler — MediaPipe Selfie Multiclass + Pose Landmarker
(Google) ve SAM 2.1 (Meta, `facebook/sam2.1-hiera-small`). NVIDIA SegFormer türevleri
(ör. segformer_b2_clothes) ticari olmayan lisanslıdır, bu hatta KULLANILMAZ
(bkz. docs/superpowers/specs/2026-10-05-creative-mokap-giydirme-design.md "Lisans kontrolü").

Yöntem:
  1. MediaPipe Multiclass (256x256) → kaba sınıf: arka plan/saç/ten/yüz/kıyafet/diğer.
  2. MediaPipe Pose → 33 vücut noktası (omuz, kalça, diz, bilek, topuk, ayak ucu, el).
  3. SAM 2.1 → noktaları istem olarak kullanıp tam çözünürlükte üst giysi, alt giysi,
     ayakkabı ve el maskeleri.

Çıktı etiketleri (mokap maske koduyla uyumlu numaralar):
  0 arka plan, 2 saç, 4 üst giysi, 6 alt giysi, 9 ayakkabı+çorap, 11 yüz,
  12 bacak (açık ten), 14 kol/el.

Kullanım:
  python mockup_segment.py <görsel> <çıktı.npy> [önizleme.jpg]
stdout: JSON {"ok": true, "labels": {...piksel sayıları}, "landmarks": {...}, "ms": ...}
"""
import json
import os
import sys
import time

HERE = os.path.dirname(os.path.abspath(__file__))
MODELS = os.path.join(HERE, "models", "mockup")
os.environ.setdefault("HF_HOME", os.path.join(MODELS, "hf"))

import numpy as np  # noqa: E402
from PIL import Image  # noqa: E402

BG, HAIR, UPPER, LOWER, SHOES, FACE, LEGS, ARMS = 0, 2, 4, 6, 9, 11, 12, 14
SAM_ID = "facebook/sam2.1-hiera-small"

# MediaPipe Pose nokta indeksleri
L_SH, R_SH, L_EL, R_EL, L_WR, R_WR = 11, 12, 13, 14, 15, 16
L_HAND = (15, 17, 19, 21)
R_HAND = (16, 18, 20, 22)
L_HIP, R_HIP, L_KN, R_KN, L_AN, R_AN = 23, 24, 25, 26, 27, 28
L_HEEL, R_HEEL, L_FOOT, R_FOOT = 29, 30, 31, 32


def mediapipe_pass(rgb):
    import mediapipe as mp
    from mediapipe.tasks.python import BaseOptions
    from mediapipe.tasks.python import vision

    image = mp.Image(image_format=mp.ImageFormat.SRGB, data=np.ascontiguousarray(rgb))
    with vision.ImageSegmenter.create_from_options(vision.ImageSegmenterOptions(
            base_options=BaseOptions(model_asset_path=os.path.join(MODELS, "selfie_multiclass_256x256.tflite")),
            output_category_mask=True)) as seg:
        cat = np.squeeze(seg.segment(image).category_mask.numpy_view()).astype(np.uint8).copy()
    with vision.PoseLandmarker.create_from_options(vision.PoseLandmarkerOptions(
            base_options=BaseOptions(model_asset_path=os.path.join(MODELS, "pose_landmarker_heavy.task")),
            num_poses=1)) as pose:
        res = pose.detect(image)
    if not res.pose_landmarks:
        raise RuntimeError("Görselde insan pozu bulunamadı.")
    h, w = rgb.shape[:2]
    pts = np.array([[p.x * w, p.y * h, p.visibility] for p in res.pose_landmarks[0]], dtype=np.float32)
    return cat, pts


class Sam:
    def __init__(self, pil):
        import torch
        from transformers import Sam2Model, Sam2Processor
        self.torch = torch
        self.proc = Sam2Processor.from_pretrained(SAM_ID)
        self.model = Sam2Model.from_pretrained(SAM_ID).eval()
        self.pil = pil
        self.inputs = self.proc(images=pil, return_tensors="pt")
        with torch.no_grad():
            self.emb = self.model.get_image_embeddings(self.inputs["pixel_values"])

    def mask(self, pos, neg=(), box=None):
        """pos/neg: [(x,y)...] görsel pikseli; box: [x0,y0,x1,y1]. En yüksek skorlu maske."""
        masks, scores = self.masks(pos, neg, box)
        return masks[int(scores.argmax())]

    def masks(self, pos, neg=(), box=None):
        """SAM'in üç aday maskesi (bool, (3,H,W)) ve skorları."""
        pts = [list(map(float, p)) for p in list(pos) + list(neg)]
        labels = [1] * len(pos) + [0] * len(neg)
        kw = {}
        if pts:
            kw["input_points"] = [[pts]]
            kw["input_labels"] = [[labels]]
        if box is not None:
            kw["input_boxes"] = [[list(map(float, box))]]
        enc = self.proc(images=self.pil, return_tensors="pt", **kw)
        with self.torch.no_grad():
            out = self.model(image_embeddings=self.emb, input_points=enc.get("input_points"),
                             input_labels=enc.get("input_labels"), input_boxes=enc.get("input_boxes"),
                             multimask_output=True)
        masks = self.proc.post_process_masks(out.pred_masks, enc["original_sizes"])[0][0]  # (3,H,W)
        return masks.numpy().astype(bool), out.iou_scores[0, 0].numpy()


def clean(mask, h, w):
    """Küçük delikleri kapatır, küçük adacıkları atar (maske kenarında leke/delik bırakmasın)."""
    import cv2
    r = max(2, int(w * 0.003))
    m = cv2.morphologyEx(mask.astype(np.uint8), cv2.MORPH_CLOSE, cv2.getStructuringElement(cv2.MORPH_ELLIPSE, (2 * r + 1, 2 * r + 1)))
    n, comp, stats, _ = cv2.connectedComponentsWithStats(m, connectivity=8)
    keep = np.zeros(n, bool)
    keep[1:] = stats[1:, cv2.CC_STAT_AREA] >= h * w * 0.0002
    return keep[comp]


def seg_line_mask(h, w, a, b, radius):
    """a→b doğru parçasına `radius` uzaklıktaki pikseller."""
    yy, xx = np.mgrid[0:h, 0:w]
    a = np.asarray(a, np.float32); b = np.asarray(b, np.float32)
    ab = b - a; L2 = float((ab ** 2).sum()) or 1.0
    t = np.clip(((xx - a[0]) * ab[0] + (yy - a[1]) * ab[1]) / L2, 0, 1)
    d2 = (xx - (a[0] + t * ab[0])) ** 2 + (yy - (a[1] + t * ab[1])) ** 2
    return d2 <= radius ** 2


def segment(pil, with_feet=False):
    """PIL RGB → (etiket haritası uint8 (H,W), poz noktaları (33,3), kontroller dict). Birleştirme de kullanır.

    kontroller: shoe_px_ratio (ayak/ayakkabı maskesinin görsel alanına oranı) ve shoe_skin_ratio
    (o maskenin MediaPipe'ın TEN dediği kısmı — çıplak ayakta yüksek, ayakkabıda düşük; Faz 2 baz kontrolü).
    with_feet: dördüncü değer olarak çorapsız ayakkabı maskesi (SHOES etiketi çorabı da kapsar).
    """
    rgb = np.asarray(pil)
    H, W = rgb.shape[:2]

    cat, P = mediapipe_pass(rgb)
    cat = np.asarray(Image.fromarray(cat).resize((W, H), Image.NEAREST))
    person = cat != 0
    hair_c, skin_c, face_c, cloth_c = cat == 1, cat == 2, cat == 3, (cat == 4) | (cat == 5)

    xy = lambda i: P[i, :2]
    mid = lambda i, j: (P[i, :2] + P[j, :2]) / 2
    sh_w = float(np.linalg.norm(xy(L_SH) - xy(R_SH)))
    hip_y = float(mid(L_HIP, R_HIP)[1]); sh_y = float(mid(L_SH, R_SH)[1])
    chest = mid(L_SH, R_SH) * 0.55 + mid(L_HIP, R_HIP) * 0.45
    thighs = [xy(L_HIP) * 0.55 + xy(L_KN) * 0.45, xy(R_HIP) * 0.55 + xy(R_KN) * 0.45]

    sam = Sam(pil)

    # Alt giysi: uyluk ortaları pozitif, göğüs negatif. Üst giysi: göğüs pozitif, uyluklar negatif.
    lower = sam.mask(pos=thighs, neg=[chest]) & person
    upper = sam.mask(pos=[chest, mid(L_SH, R_SH) * 0.3 + chest * 0.7], neg=thighs) & person & ~lower

    # Ayakkabılar: ayak noktalarından kutu + topuk/ayak ucu pozitif, diz negatif.
    shoes = np.zeros((H, W), bool)
    for an, he, fo, kn in ((L_AN, L_HEEL, L_FOOT, L_KN), (R_AN, R_HEEL, R_FOOT, R_KN)):
        fx = [P[an, 0], P[he, 0], P[fo, 0]]; fy = [P[an, 1], P[he, 1], P[fo, 1]]
        m = sh_w * 0.22
        box = [min(fx) - m, min(fy) - m * 0.6, max(fx) + m, max(fy) + m * 0.8]
        # Zemin/gölge negatif: ayağın hemen altı. Adaylardan ayak noktalarını içeren, kutuyu
        # aşırı doldurmayan (gölge/zemin yutmamış) en yüksek skorlu olan seçilir.
        floor = (float(mid(he, fo)[0]), min(H - 1.0, max(fy) + m * 0.7))
        cands, sc = sam.masks(pos=[mid(he, fo)], neg=[xy(kn), floor], box=box)
        bx = np.zeros((H, W), bool); bx[int(max(0, box[1])):int(min(H, box[3])), int(max(0, box[0])):int(min(W, box[2]))] = True
        box_area = max(1, int(bx.sum()))
        best, best_s = None, -1.0
        for cm, cs in zip(cands, sc):
            cm = cm & bx
            heel, toe = P[he, :2].astype(int), P[fo, :2].astype(int)
            inside = sum(bool(cm[min(H - 1, max(0, q[1])), min(W - 1, max(0, q[0]))]) for q in (heel, toe))
            fill = cm.sum() / box_area
            if inside >= 1 and fill <= 0.75 and cs > best_s:
                best, best_s = cm, float(cs)
        shoes |= best if best is not None else (cands[int(sc.argmax())] & bx)
    import cv2
    k = lambda r: cv2.getStructuringElement(cv2.MORPH_ELLIPSE, (2 * r + 1, 2 * r + 1))
    # Çorap: yalnız ayak bileği çevresinde (bilek x ± omuz genişliğinin %22'si, dizden bileğe yolun
    # alt %45'i ile topuk arası), alt giysinin (genişletilmiş) dışında kalan kıyafet pikselleri.
    lower_d = cv2.dilate(lower.astype(np.uint8), k(max(4, int(W * 0.008)))).astype(bool)
    yy, xx = np.mgrid[0:H, 0:W]
    socks = np.zeros((H, W), bool)
    for an, kn, he in ((L_AN, L_KN, L_HEEL), (R_AN, R_KN, R_HEEL)):
        top = P[kn, 1] + 0.55 * (P[an, 1] - P[kn, 1]); bot = max(P[an, 1], P[he, 1])
        socks |= (yy >= top) & (yy <= bot) & (np.abs(xx - P[an, 0]) <= sh_w * 0.22)
    socks &= cloth_c & ~lower_d & person
    shoes |= socks

    # Eller: el noktaları pozitif (her el ayrı), bilek çevresi daireyle sınırlı.
    hands = np.zeros((H, W), bool)
    for idx, el in ((L_HAND, L_EL), (R_HAND, R_EL)):
        c = P[list(idx), :2].mean(0)
        r = max(float(np.linalg.norm(P[idx[0], :2] - P[idx[2], :2])) * 2.4, sh_w * 0.18)
        hm = sam.mask(pos=[tuple(p) for p in P[list(idx), :2]], neg=[xy(el)])
        yy, xx = np.mgrid[0:H, 0:W]
        hands |= hm & (((xx - c[0]) ** 2 + (yy - c[1]) ** 2) <= r ** 2)
    # Kollar: omuz→dirsek→bilek çizgileri etrafındaki ten/kıyafet (üst giysinin kolu dahil).
    arm_r = sh_w * 0.16
    arm_zone = np.zeros((H, W), bool)
    for a, b in ((L_SH, L_EL), (L_EL, L_WR), (R_SH, R_EL), (R_EL, R_WR)):
        arm_zone |= seg_line_mask(H, W, xy(a), xy(b), arm_r)
    # Kol = kol çizgisi etrafındaki TEN + üst giysinin kolu + eller. Genel "kıyafet" sınıfı dahil
    # EDİLMEZ: kolun yanındaki alt giysi şeridi kola karışıp "önde kalan" olarak bazdan yapıştırılıyor
    # ve eteğin üstünde gri iz bırakıyordu (2026-10-06, sonuç #12).
    # Kol teni SAM ile tam çözünürlükte (dirsek, ön kol ortası, bilek pozitif; kalça ve uyluk negatif):
    # MediaPipe teninin kaba kenarı kol ile alt giysi arasındaki boşluğu da kola katıyordu.
    sam_arms = np.zeros((H, W), bool)
    for sh, el, wr, hp in ((L_SH, L_EL, L_WR, L_HIP), (R_SH, R_EL, R_WR, R_HIP)):
        pos = [xy(el), (xy(el) + xy(wr)) / 2, xy(wr) * 0.8 + xy(el) * 0.2]
        neg = [xy(hp), thighs[0] if hp == L_HIP else thighs[1]]
        sam_arms |= sam.mask(pos=pos, neg=neg)
    arms = (sam_arms & arm_zone & ~lower) | (arm_zone & upper) | hands
    arms = clean(arms, H, W)
    hands = clean(hands, H, W)
    lower = clean(lower, H, W)

    lab = np.zeros((H, W), np.uint8)
    # Bacak = kalçanın altındaki ten; boyun/kol teni kalçanın üstünde. MediaPipe silüetinin büyütülmüş
    # kaba kenarı (sınıfsız hale) etiketlenmez — aksi halde maske kutusu yatayda gereksiz genişler.
    rows = np.mgrid[0:H, 0:W][0]
    lab[skin_c & (rows >= hip_y)] = LEGS
    lab[skin_c & (rows < hip_y)] = ARMS
    lab[upper] = UPPER
    lab[arms & ~upper] = ARMS
    lab[lower] = LOWER
    lab[shoes] = SHOES
    lab[hands] = ARMS                        # eller alt giysinin önünde olabilir (el belde)
    lab[face_c] = FACE
    lab[hair_c] = HAIR
    lab[~person & ~shoes] = BG
    feet = shoes & ~socks
    checks = {
        "shoe_px_ratio": round(float(shoes.sum()) / (H * W), 5),
        "shoe_skin_ratio": round(float((feet & skin_c).sum()) / max(1, int(feet.sum())), 3),
    }
    return (lab, P, checks, feet) if with_feet else (lab, P, checks)


def main():
    src, out_npy = sys.argv[1], sys.argv[2]
    preview = sys.argv[3] if len(sys.argv) > 3 else None
    t0 = time.time()
    pil = Image.open(src).convert("RGB")
    rgb = np.asarray(pil)
    lab, P, checks = segment(pil)

    np.save(out_npy, lab)
    if preview:
        pal = np.zeros((256, 3), np.uint8)
        for k, c in {HAIR: (40, 40, 220), UPPER: (240, 150, 120), LOWER: (210, 120, 200), SHOES: (60, 200, 120),
                     FACE: (120, 220, 120), LEGS: (150, 150, 150), ARMS: (60, 220, 220)}.items():
            pal[k] = c
        ov = (rgb * 0.45 + pal[lab] * 0.55).astype(np.uint8)
        ov[lab == BG] = rgb[lab == BG]
        im = Image.fromarray(ov); im.thumbnail((800, 800)); im.save(preview, quality=85)

    names = {BG: "bg", HAIR: "hair", UPPER: "upper", LOWER: "lower", SHOES: "shoes", FACE: "face", LEGS: "legs", ARMS: "arms"}
    counts = {names[int(k)]: int(v) for k, v in zip(*np.unique(lab, return_counts=True))}
    lm = {k: [round(float(v), 1) for v in P[i, :2]] for k, i in
          {"hip_l": L_HIP, "hip_r": R_HIP, "knee_l": L_KN, "knee_r": R_KN, "ankle_l": L_AN, "ankle_r": R_AN,
           "wrist_l": L_WR, "wrist_r": R_WR, "shoulder_l": L_SH, "shoulder_r": R_SH}.items()}
    print(json.dumps({"ok": True, "labels": counts, "landmarks": lm, "checks": checks, "ms": int((time.time() - t0) * 1000)}))


if __name__ == "__main__":
    try:
        main()
    except Exception as e:  # PHP tarafı JSON bekler
        print(json.dumps({"ok": False, "error": str(e)}))
        sys.exit(1)
