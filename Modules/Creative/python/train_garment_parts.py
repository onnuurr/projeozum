"""Giysi parça dedektörünü kendi etiketli verinizle fine-tune eder: stdin JSON -> stdout JSON.

FAZ G.4 İSKELETİ — bu betik yazılmıştır ama proje henüz yeterli manuel
etiketli örnek biriktirmediği için ilk gerçek koşusu YAPILMAMIŞTIR (bkz.
TrainGarmentDetectorCommand::MIN_EXAMPLES kontrolü — çağıran taraf yetersiz
veri varken bu betiği hiç başlatmaz).

Lisans kararı gereği (ROADMAP.md Faz G) DeepFashion2/Fashionpedia gibi hazır
dataset'ler KULLANILMAZ: COCO-pretrained bir torchvision backbone'undan
(genel nesne tanıma, ticari kullanımda yaygın kabul gören transfer-learning
başlangıcı) başlanır, yalnızca bu mağazanın kendi ürün fotoğraflarından
TrainGarmentDetectorCommand tarafından üretilen COCO-format anotasyonlarla
(creative_garment_scans.detections[source=manual]) fine-tune edilir. Böylece
üretilen ağırlık dosyası tamamen bu işletmenin kendi verisine dayanır.

Payload:
  {
    "images_dir": "/abs/path/to/images",
    "annotations_path": "/abs/path/to/coco_annotations.json",  # COCO detection formatı
    "output_path": "/abs/path/to/garment_parts_latest.pt",
    "model_version": "garment_parts_2026_08_01",
    "backbone": "fasterrcnn_resnet50_fpn_v2",   # | fcos_resnet50_fpn
    "epochs": 10,
    "batch_size": 2,
    "learning_rate": 0.005
  }

Çıktı:
  {"status": "ok", "output_path": "...", "epochs_run": 10, "final_loss": 0.42,
   "label_count": 9, "duration_s": 1834}

Eğitim veri kümesi ondalıklı ise ya da hiçbir görsel/anotasyon bulunamazsa
non-zero exit + stderr mesajıyla çıkılır — TrainGarmentDetectorCommand bunu
yakalayıp konsola "eğitim başarısız" olarak raporlar, mevcut (varsa) ağırlık
dosyasına dokunmaz.

Not: pycocotools gerektirir (yalnız bu betik kullanır, inference/detect_garment_parts.py
etkilenmez — requirements.txt'te ayrıca not edilmiştir).
"""

import json
import sys
import time


def _log(msg):
    print(msg, file=sys.stderr)


def _build_model(backbone, num_classes):
    from torchvision.models.detection import (
        fasterrcnn_resnet50_fpn_v2,
        fcos_resnet50_fpn,
    )
    from torchvision.models.detection.faster_rcnn import FastRCNNPredictor

    if backbone == "fcos_resnet50_fpn":
        # COCO-pretrained ağırlıklardan başla (transfer learning), sınıf sayısını
        # kendi etiket setimize göre değiştir.
        model = fcos_resnet50_fpn(weights="DEFAULT")
        return model

    model = fasterrcnn_resnet50_fpn_v2(weights="DEFAULT")
    in_features = model.roi_heads.box_predictor.cls_score.in_features
    model.roi_heads.box_predictor = FastRCNNPredictor(in_features, num_classes)
    return model


def _load_dataset(images_dir, annotations_path):
    from torchvision.datasets import CocoDetection
    from torchvision.transforms.functional import to_tensor

    class _Dataset(CocoDetection):
        def __getitem__(self, idx):
            image, target = super().__getitem__(idx)
            image = to_tensor(image)

            boxes, labels = [], []
            for ann in target:
                x, y, w, h = ann["bbox"]
                if w <= 0 or h <= 0:
                    continue
                boxes.append([x, y, x + w, y + h])
                labels.append(ann["category_id"])

            return image, {
                "boxes": _as_tensor(boxes, "float32"),
                "labels": _as_tensor(labels, "int64"),
            }

    return _Dataset(root=images_dir, annFile=annotations_path)


def _as_tensor(values, dtype):
    import torch

    if not values:
        shape = (0, 4) if dtype == "float32" else (0,)
        return torch.zeros(shape, dtype=getattr(torch, dtype))
    return torch.as_tensor(values, dtype=getattr(torch, dtype))


def _label_map_from_coco(annotations_path):
    """COCO categories -> {index(str): {key, display}} (index 0 arka plandır)."""
    with open(annotations_path, "r", encoding="utf-8") as f:
        coco = json.load(f)

    label_map = {}
    for cat in coco["categories"]:
        label_map[str(cat["id"])] = {"key": cat.get("key", cat["name"]), "display": cat["name"]}

    return label_map


def _train(model, torch, dataset, epochs, batch_size, learning_rate):
    from torch.utils.data import DataLoader

    def collate_fn(batch):
        return tuple(zip(*batch))

    loader = DataLoader(dataset, batch_size=batch_size, shuffle=True, collate_fn=collate_fn)

    device = torch.device("cuda" if torch.cuda.is_available() else "cpu")
    model.to(device)
    model.train()

    params = [p for p in model.parameters() if p.requires_grad]
    optimizer = torch.optim.SGD(params, lr=learning_rate, momentum=0.9, weight_decay=0.0005)

    final_loss = None
    for epoch in range(epochs):
        epoch_loss = 0.0
        batches = 0
        for images, targets in loader:
            images = [img.to(device) for img in images]
            targets = [{k: v.to(device) for k, v in t.items()} for t in targets]

            loss_dict = model(images, targets)
            loss = sum(loss_dict.values())

            optimizer.zero_grad()
            loss.backward()
            optimizer.step()

            epoch_loss += float(loss.item())
            batches += 1

        final_loss = epoch_loss / max(batches, 1)
        _log(f"train_garment_parts: epoch {epoch + 1}/{epochs} loss={final_loss:.4f}")

    model.eval()
    return final_loss


def main():
    payload = json.load(sys.stdin)

    images_dir = payload["images_dir"]
    annotations_path = payload["annotations_path"]
    output_path = payload["output_path"]
    model_version = payload.get("model_version") or f"garment_parts_{int(time.time())}"
    backbone = payload.get("backbone", "fasterrcnn_resnet50_fpn_v2")
    epochs = int(payload.get("epochs", 10))
    batch_size = int(payload.get("batch_size", 2))
    learning_rate = float(payload.get("learning_rate", 0.005))

    started = time.monotonic()

    label_map = _label_map_from_coco(annotations_path)
    if not label_map:
        raise RuntimeError("Anotasyon dosyasında hiç kategori yok — eğitim için yetersiz veri.")

    dataset = _load_dataset(images_dir, annotations_path)
    if len(dataset) == 0:
        raise RuntimeError("Eğitim veri kümesi boş.")

    model = _build_model(backbone, num_classes=len(label_map) + 1)

    import torch

    final_loss = _train(model, torch, dataset, epochs, batch_size, learning_rate)

    torch.save({
        "model_state_dict": model.state_dict(),
        "label_map": label_map,
        "model_version": model_version,
        "backbone": backbone,
    }, output_path)

    sys.stdout.write(json.dumps({
        "status": "ok",
        "output_path": output_path,
        "epochs_run": epochs,
        "final_loss": final_loss,
        "label_count": len(label_map),
        "duration_s": int(time.monotonic() - started),
    }, ensure_ascii=False))


if __name__ == "__main__":
    try:
        main()
    except Exception as e:  # noqa: BLE001
        _log(f"train_garment_parts: {e}")
        sys.exit(1)
