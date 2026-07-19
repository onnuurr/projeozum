"""Giysi detay görseli sınıflandırma: stdin JSON -> stdout JSON.

Kullanıcının try-on ekranında yüklediği "detay" fotoğrafları (yaka yakın çekimi,
düğme, kol ucu vb.) bugüne kadar tamamen elle yazılan serbest metin etikete
dayanıyordu. Bu betik, açık bir kategori sözlüğüne karşı yerel bir zero-shot
CLIP modeliyle (open_clip) görüntü-metin benzerliği hesaplayarak her detay
görseli için otomatik etiket ÖNERİSİ üretir — Gemini/bulut çağrısı yapmaz,
model ağırlığı bir kez indirilip yerelde (CPU) çalışır.

Görsel zaten fotoğrafçı tarafından çekilmiş bir YAKIN ÇEKİM olduğundan (tam
ürün fotoğrafı içinde bölge bulma değil), bu bir nesne tespiti değil, sınıf-
landırma problemidir: "bu kapalı görsel hangi detayı gösteriyor?".

Payload:
  {
    "image_paths": ["/abs/path/detail1.jpg", ...],  # zorunlu
    "top_k": 3,                                      # görsel başına dönecek aday sayısı
    "multi_label_threshold": 0.15                     # bu skorun altındaki adaylar elenir
  }

Çıktı:
  {
    "results": [
      {"path": "/abs/path/detail1.jpg", "labels": [{"key":"yaka","display":"Yaka","score":0.82}, ...]},
      ...
    ]
  }

Bir görsel işlenemezse (bozuk dosya vb.) o girdi için "labels": [] döner —
tüm betiğin başarısız sayılması yalnızca model/ağırlık yüklenemediğinde ya da
payload geçersiz olduğunda olur (non-zero exit code). Çağıran taraf
(PythonGarmentDetailClassifier) her durumda "öneri yok" olarak ele alıp
kullanıcının elle girdiği/gireceği etikete düşer — bu adım asla giydirme
pipeline'ını bozmaz.

Not: prepare_garment.py / inspect_template.py ile aynı ailede ama JSON-in/
JSON-out sözleşmesini (inspect_template.py) izler — çıktısı bir görsel değil,
yapılandırılmış veridir.
"""

import json
import sys

# (key, display, prompt şablonları) — CLIP ağırlıkları esas olarak İngilizce
# eğitildiği için prompt'lar İngilizce; parantez içindeki Türkçe kelime yalnızca
# insan-okunur "display" alanında kullanılır.
CATEGORIES = [
    ("yaka", "Yaka", [
        "a close-up photo of a garment collar",
        "a close-up photo of a shirt neckline",
    ]),
    ("dugme", "Düğme", [
        "a close-up photo of a button on a garment",
        "a close-up photo of buttons on a shirt",
    ]),
    ("kol_ucu", "Kol Ucu", [
        "a close-up photo of a sleeve cuff",
        "a close-up photo of a garment sleeve end",
    ]),
    ("cep", "Cep", [
        "a close-up photo of a pocket on a garment",
    ]),
    ("fermuar", "Fermuar", [
        "a close-up photo of a zipper on a garment",
    ]),
    ("citcit", "Çıtçıt", [
        "a close-up photo of a snap button on a garment",
    ]),
    ("percin", "Perçin", [
        "a close-up photo of a rivet on a garment",
    ]),
    ("etek", "Etek", [
        "a close-up photo of the bottom hem of a garment",
    ]),
    ("kapusen", "Kapüşon", [
        "a close-up photo of a hood on a garment",
    ]),
    ("dikis", "Dikiş", [
        "a close-up photo of visible stitching or a seam on fabric",
    ]),
    ("kumas_dokusu", "Kumaş Dokusu", [
        "a macro close-up photo of fabric texture",
        "a close-up photo of a knit or woven fabric pattern",
    ]),
    ("firfir", "Fırfır / Volan", [
        "a close-up photo of a ruffle on a garment",
        "a close-up photo of frilled or gathered fabric trim",
    ]),
    ("puf_kol", "Puf Kol", [
        "a close-up photo of a puffed sleeve",
        "a close-up photo of a gathered sleeve with volume",
    ]),
    ("logo", "Logo", [
        "a close-up photo of a logo on a garment",
    ]),
    ("baski_desen", "Baskı / Desen", [
        "a close-up photo of a printed graphic or pattern on fabric",
    ]),
    ("nakis", "Nakış", [
        "a close-up photo of embroidery on a garment",
    ]),
    ("aksesuar_detay", "Aksesuar / Detay", [
        "a close-up photo of a decorative hardware detail, buckle or trim on a garment",
    ]),
    ("arkadan", "Arkadan", [
        "a photo of the back of a garment",
    ]),
    ("yandan", "Yandan", [
        "a photo of the side profile of a garment",
    ]),
]


def _log(msg):
    """Hata ayıklama mesajını stderr'e yazar (stdout sadece JSON taşır)."""
    print(msg, file=sys.stderr)


def _load_model(model_name, pretrained):
    import open_clip
    import torch

    model, _, preprocess = open_clip.create_model_and_transforms(
        model_name, pretrained=pretrained,
    )
    tokenizer = open_clip.get_tokenizer(model_name)
    model.eval()
    return model, preprocess, tokenizer, torch


def _text_features(model, tokenizer, torch):
    """Her kategori için birden fazla prompt'un ortalama metin embedding'ini çıkarır."""
    all_prompts = []
    owner = []
    for key, _display, prompts in CATEGORIES:
        for p in prompts:
            all_prompts.append(p)
            owner.append(key)

    tokens = tokenizer(all_prompts)
    with torch.no_grad():
        feats = model.encode_text(tokens)
        feats = feats / feats.norm(dim=-1, keepdim=True)

    # Aynı kategoriye ait prompt embedding'lerini ortalayıp tekrar normalize et.
    by_key = {}
    for key, vec in zip(owner, feats):
        by_key.setdefault(key, []).append(vec)

    keys = [c[0] for c in CATEGORIES]
    merged = torch.stack([torch.stack(by_key[k]).mean(dim=0) for k in keys])
    merged = merged / merged.norm(dim=-1, keepdim=True)
    return keys, merged


def _classify_one(path, model, preprocess, torch, text_keys, text_feats, top_k, threshold):
    from PIL import Image

    try:
        im = Image.open(path).convert("RGB")
    except Exception as e:
        _log(f"classify_garment_detail: görsel açılamadı, atlanıyor: {path}: {e}")
        return []

    image_input = preprocess(im).unsqueeze(0)
    with torch.no_grad():
        image_feats = model.encode_image(image_input)
        image_feats = image_feats / image_feats.norm(dim=-1, keepdim=True)
        # Zero-shot CLIP benzerlik skorları (softmax ile 0-1 aralığına çekilir).
        logits = (100.0 * image_feats @ text_feats.T).softmax(dim=-1)[0]

    display_by_key = {c[0]: c[1] for c in CATEGORIES}
    scored = sorted(
        zip(text_keys, logits.tolist()), key=lambda kv: kv[1], reverse=True,
    )

    labels = []
    for key, score in scored[:top_k]:
        if score < threshold:
            continue
        labels.append({"key": key, "display": display_by_key[key], "score": round(score, 4)})

    return labels


def main():
    payload = json.load(sys.stdin)

    image_paths = payload["image_paths"]
    top_k = int(payload.get("top_k", 3))
    threshold = float(payload.get("multi_label_threshold", 0.15))
    model_name = payload.get("model_name", "ViT-B-32")
    pretrained = payload.get("pretrained", "laion2b_s34b_b79k")

    model, preprocess, tokenizer, torch = _load_model(model_name, pretrained)
    text_keys, text_feats = _text_features(model, tokenizer, torch)

    results = []
    for path in image_paths:
        labels = _classify_one(
            path, model, preprocess, torch, text_keys, text_feats, top_k, threshold,
        )
        results.append({"path": path, "labels": labels})

    sys.stdout.write(json.dumps({"results": results}, ensure_ascii=False))


if __name__ == "__main__":
    main()
