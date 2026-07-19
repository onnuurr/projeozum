"""Giysi parça tespiti: stdin JSON -> stdout JSON.

classify_garment_detail.py bir SINIFLANDIRMA betiğiydi ("bu kapalı görsel
hangi detayı gösteriyor?"); bu betik ise bir NESNE TESPİTİ betiğidir: tam
ürün fotoğrafı içinde yaka/cep/etek/kol ucu gibi parçaların KONUMUNU (bbox)
bulur. torchvision.models.detection kullanır (BSD lisanslı, Ultralytics
YOLO'nun aksine ticari iç kullanımda ek lisans gerektirmez — bkz. ROADMAP.md
Faz G lisans kararı) ve DeepFashion2/Fashionpedia GİBİ hazır dataset'ler
yerine, bu projenin kendi ürün fotoğraflarıyla fine-tune edilmiş bir ağırlık
dosyasını (train_garment_parts.py çıktısı) yükler.

Ağırlık dosyası kendini betimler: state_dict'in yanında bir label_map
(sınıf index -> {key, display}) ve model_version taşır — bu betik ayrı bir
etiket sözlüğüne bağımlı değildir.

Payload:
  {
    "image_path": "/abs/path/garment.jpg",   # zorunlu
    "weights_path": "/abs/path/garment_parts_latest.pt",  # zorunlu
    "min_confidence": 0.35,
    "max_detections": 20
  }

Çıktı:
  {
    "detections": [
      {"label_key": "yaka", "label_display": "Yaka",
       "bbox": {"x": 0.12, "y": 0.05, "w": 0.30, "h": 0.18}, "confidence": 0.83},
      ...
    ],
    "model_version": "garment_parts_2026_08_01",
    "inference_ms": 412
  }

bbox alanları görsel genişlik/yüksekliğine göre 0..1 NORMALIZE edilmiştir
(çözünürlükten bağımsız; PHP tarafı hem UI overlay hem kırpma için doğrudan
kullanır — bkz. GarmentScanService).

Ağırlık dosyası yoksa/yüklenemezse ya da payload geçersizse non-zero exit
code ile çıkılır (stderr'e neden yazılır) — çağıran taraf
(PythonGarmentPartDetector) bunu boş tespit listesine düşürür, giydirme
pipeline'ı asla bu adım yüzünden bozulmaz.
"""

import json
import sys
import time


def _log(msg):
    """Hata ayıklama mesajını stderr'e yazar (stdout sadece JSON taşır)."""
    print(msg, file=sys.stderr)


def _load_checkpoint(weights_path):
    import torch

    checkpoint = torch.load(weights_path, map_location="cpu", weights_only=False)

    label_map = checkpoint["label_map"]  # {index(str): {"key":..., "display":...}}
    backbone = checkpoint.get("backbone", "fasterrcnn_resnet50_fpn_v2")
    model_version = checkpoint.get("model_version", "unknown")

    model = _build_model(backbone, num_classes=len(label_map) + 1)
    model.load_state_dict(checkpoint["model_state_dict"])
    model.eval()

    return model, label_map, model_version, torch


def _build_model(backbone, num_classes):
    """num_classes: arka plan (0) + gerçek parça sınıfları."""
    from torchvision.models.detection import (
        fasterrcnn_resnet50_fpn_v2,
        fcos_resnet50_fpn,
    )
    from torchvision.models.detection.faster_rcnn import FastRCNNPredictor

    if backbone == "fcos_resnet50_fpn":
        model = fcos_resnet50_fpn(weights=None, num_classes=num_classes)
        return model

    # Varsayılan: Faster R-CNN v2 (daha yüksek doğruluk, CPU'da yeterince hızlı
    # — try-on pipeline'ında zaten dakikalar süren AI adımlarının yanında).
    model = fasterrcnn_resnet50_fpn_v2(weights=None, weights_backbone=None)
    in_features = model.roi_heads.box_predictor.cls_score.in_features
    model.roi_heads.box_predictor = FastRCNNPredictor(in_features, num_classes)
    return model


def _detect(model, torch, image_path, label_map, min_confidence, max_detections):
    from PIL import Image
    from torchvision.transforms.functional import to_tensor

    image = Image.open(image_path).convert("RGB")
    width, height = image.size
    tensor = to_tensor(image)

    with torch.no_grad():
        output = model([tensor])[0]

    boxes = output["boxes"].tolist()
    labels = output["labels"].tolist()
    scores = output["scores"].tolist()

    rows = sorted(zip(boxes, labels, scores), key=lambda r: r[2], reverse=True)

    detections = []
    for box, label_idx, score in rows:
        if score < min_confidence:
            continue
        info = label_map.get(str(label_idx))
        if info is None:
            continue

        x0, y0, x1, y1 = box
        detections.append({
            "label_key": info["key"],
            "label_display": info["display"],
            "bbox": {
                "x": max(0.0, x0 / width),
                "y": max(0.0, y0 / height),
                "w": min(1.0, (x1 - x0) / width),
                "h": min(1.0, (y1 - y0) / height),
            },
            "confidence": round(score, 4),
        })
        if len(detections) >= max_detections:
            break

    return detections


def main():
    payload = json.load(sys.stdin)

    image_path = payload["image_path"]
    weights_path = payload["weights_path"]
    min_confidence = float(payload.get("min_confidence", 0.35))
    max_detections = int(payload.get("max_detections", 20))

    started = time.monotonic()
    model, label_map, model_version, torch = _load_checkpoint(weights_path)
    detections = _detect(model, torch, image_path, label_map, min_confidence, max_detections)
    inference_ms = int((time.monotonic() - started) * 1000)

    sys.stdout.write(json.dumps({
        "detections": detections,
        "model_version": model_version,
        "inference_ms": inference_ms,
    }, ensure_ascii=False))


if __name__ == "__main__":
    try:
        main()
    except Exception as e:  # noqa: BLE001 - stderr'e yazıp non-zero çıkmak yeterli
        _log(f"detect_garment_parts: {e}")
        sys.exit(1)
