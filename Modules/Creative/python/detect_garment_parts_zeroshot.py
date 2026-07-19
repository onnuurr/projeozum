"""Zero-shot giysi parça tespiti: stdin JSON -> stdout JSON.

detect_garment_parts.py'nin GERÇEK (fine-tune edilmiş) sürücüsüdür; bu betik ise
henüz hiç fine-tune ağırlığı yokken (Faz G.4 ilk koşusundan önce) BOOTSTRAP
amaçlı öneri kutuları üretir — OWLv2 (HuggingFace `transformers`, Apache-2.0,
Ultralytics YOLO'nun aksine ticari kullanımda ek lisans gerektirmez, bkz.
ROADMAP.md Faz G lisans kararı) ile açık-kelime (zero-shot) nesne tespiti yapar.
Eğitim verisi GEREKTİRMEZ — sabit bir İngilizce sorgu sözlüğüyle çalışır
(classify_garment_detail.py'nin CATEGORIES desenine benzer, ama sınıflandırma
değil KONUM tespiti için).

Bu betiğin çıktısı asla doğrudan güvenilmez: çağıran taraf (PHP tarafında
PythonZeroShotGarmentPartDetector) her tespite 'source': 'zeroshot' damgası
ekler — bu, GarmentScanService.cropsForTryOn() (yalnız source='auto' okur) ve
creative:train-garment-detector (yalnız source='manual' okur) tarafından bir
insan etiketleme aracında onaylayana (source→manual) kadar HİÇ görülmez.

Payload:
  {
    "image_path": "/abs/path/garment.jpg",   # zorunlu
    "model_name": "google/owlv2-base-patch16-ensemble",
    "min_confidence": 0.15,
    "max_detections": 20
  }

Çıktı:
  {
    "detections": [
      {"label_key": "yaka", "label_display": "Yaka",
       "bbox": {"x": 0.12, "y": 0.05, "w": 0.30, "h": 0.18}, "confidence": 0.23},
      ...
    ],
    "model_version": "google/owlv2-base-patch16-ensemble_zeroshot",
    "inference_ms": 2140
  }

bbox alanları detect_garment_parts.py ile AYNI şekilde 0..1 normalize edilmiştir.

Model indirilemezse/yüklenemezse ya da payload geçersizse non-zero exit code ile
çıkılır — çağıran taraf bunu boş tespit listesine düşürür.
"""

import json
import sys
import time


def _log(msg):
    """Hata ayıklama mesajını stderr'e yazar (stdout sadece JSON taşır)."""
    print(msg, file=sys.stderr)


# (label_key, display, sorgu ifadeleri) — OWLv2 esas olarak İngilizce eğitildiği
# için sorgular İngilizce; display alanı insan-okunur Türkçe isim taşır.
# 'arkadan'/'yandan' BİLİNÇLİ OLARAK YOK — bunlar bir bbox değil, tüm fotoğrafın
# açısını tanımlıyor, yerelleştirilebilir bir "parça" değil.
QUERIES = [
    ("yaka", "Yaka", ["a collar of a garment", "a shirt collar"]),
    ("kol_ucu", "Kol Ucu", ["a sleeve cuff", "the end of a garment sleeve"]),
    ("cep", "Cep", ["a pocket on a garment"]),
    ("etek", "Etek", ["the bottom hem of a garment"]),
    ("kapusen", "Kapüşon", ["a hood on a garment"]),
    ("dugme", "Düğme", ["a button on a garment"]),
    ("fermuar", "Fermuar", ["a zipper on a garment"]),
    ("citcit", "Çıtçıt", ["a snap button on a garment"]),
    ("percin", "Perçin", ["a rivet on a garment"]),
    ("aksesuar_detay", "Aksesuar / Detay", ["a decorative hardware detail or buckle on a garment"]),
    ("logo", "Logo", ["a logo on a garment"]),
    ("baski_desen", "Baskı / Desen", ["a printed graphic or pattern on fabric"]),
    ("nakis", "Nakış", ["embroidery on a garment"]),
    ("kumas_dokusu", "Kumaş Dokusu", ["a patch of fabric texture"]),
    ("dikis", "Dikiş", ["visible stitching or a seam on fabric"]),
    ("firfir", "Fırfır / Volan", ["a ruffle on a garment", "a frilled or gathered fabric trim"]),
    ("puf_kol", "Puf Kol", ["a puffed sleeve", "a gathered sleeve with volume"]),
]


def _load_pipeline(model_name):
    from transformers import pipeline

    return pipeline("zero-shot-object-detection", model=model_name)


def _phrase_index():
    """sorgu ifadesi (küçük harf) -> (key, display)."""
    index = {}
    for key, display, phrases in QUERIES:
        for phrase in phrases:
            index[phrase.lower()] = (key, display)
    return index


def _iou(a, b):
    x0 = max(a["x"], b["x"])
    y0 = max(a["y"], b["y"])
    x1 = min(a["x"] + a["w"], b["x"] + b["w"])
    y1 = min(a["y"] + a["h"], b["y"] + b["h"])
    inter = max(0.0, x1 - x0) * max(0.0, y1 - y0)
    if inter <= 0:
        return 0.0
    area_a = a["w"] * a["h"]
    area_b = b["w"] * b["h"]
    union = area_a + area_b - inter
    return inter / union if union > 0 else 0.0


def _dedupe(detections):
    """Aynı label_key için çakışan (IoU > 0.5) kutulardan yalnız en yüksek
    skorluyu tutar — birden fazla sorgu ifadesi aynı bölgeye çarptığında
    etiketleme aracında neredeyse özdeş öneri çiftleri görünmesin diye."""
    kept = []
    for d in sorted(detections, key=lambda r: r["confidence"], reverse=True):
        if any(
            k["label_key"] == d["label_key"] and _iou(k["bbox"], d["bbox"]) > 0.5
            for k in kept
        ):
            continue
        kept.append(d)
    return kept


def _detect(detector, image_path, min_confidence, max_detections):
    from PIL import Image

    image = Image.open(image_path).convert("RGB")
    width, height = image.size

    candidate_labels = [phrase for _key, _display, phrases in QUERIES for phrase in phrases]
    phrase_index = _phrase_index()

    raw = detector(image, candidate_labels=candidate_labels)

    detections = []
    for r in raw:
        score = float(r["score"])
        if score < min_confidence:
            continue
        info = phrase_index.get(str(r["label"]).lower())
        if info is None:
            continue
        key, display = info

        box = r["box"]
        x0, y0, x1, y1 = box["xmin"], box["ymin"], box["xmax"], box["ymax"]
        w = x1 - x0
        h = y1 - y0
        if w <= 0 or h <= 0:
            continue

        detections.append({
            "label_key": key,
            "label_display": display,
            "bbox": {
                "x": max(0.0, x0 / width),
                "y": max(0.0, y0 / height),
                "w": min(1.0, w / width),
                "h": min(1.0, h / height),
            },
            "confidence": round(score, 4),
        })

    detections = _dedupe(detections)
    detections.sort(key=lambda d: d["confidence"], reverse=True)

    return detections[:max_detections]


def main():
    payload = json.load(sys.stdin)

    image_path = payload["image_path"]
    model_name = payload.get("model_name", "google/owlv2-base-patch16-ensemble")
    min_confidence = float(payload.get("min_confidence", 0.15))
    max_detections = int(payload.get("max_detections", 20))

    started = time.monotonic()
    detector = _load_pipeline(model_name)
    detections = _detect(detector, image_path, min_confidence, max_detections)
    inference_ms = int((time.monotonic() - started) * 1000)

    sys.stdout.write(json.dumps({
        "detections": detections,
        "model_version": f"{model_name}_zeroshot",
        "inference_ms": inference_ms,
    }, ensure_ascii=False))


if __name__ == "__main__":
    try:
        main()
    except Exception as e:  # noqa: BLE001 - stderr'e yazıp non-zero çıkmak yeterli
        _log(f"detect_garment_parts_zeroshot: {e}")
        sys.exit(1)
