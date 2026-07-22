"""Creative AI-kompozisyon metin doğrulama: stdin payload -> stdout JSON kelime listesi.

LayoutConstraintEngine'in fal.ai/Flux ile üretilen bir görselde istenen
headline/CTA metninin gerçekten (ve hatasız) basılıp basılmadığını
doğrulayabilmesi için pytesseract ile kelime/kutu/confidence çıkarır.

Payload:
  {
    "input_path": "C:/.../ai_xxx.png",  # zorunlu
    "lang": "eng",                      # tesseract dil kodu (ör. "eng+tur")
    "min_confidence": 0.4               # 0-1 arası; altındaki kelimeler elenir
  }

Çıktı (stdout, JSON):
  {"words": [{"text": "ESINTI", "x": 120, "y": 40, "w": 200, "h": 60, "confidence": 0.92}, ...]}

Not: Bu betik enhance.py/render.py'nin stdin-JSON konvansiyonunu izler, ama
çıktı PNG baytı değil JSON'dur — PythonTesseractTextRecognizer bunu bekler.
Herhangi bir hata (tesseract kurulu değil, dosya bulunamadı) stderr'e yazılıp
sys.exit(1) ile işaretlenir; PHP tarafı bunu boş kelime listesi olarak yorumlar
(bkz. PythonTesseractTextRecognizer::recognize).
"""

import json
import os
import sys


def _log(msg):
    print(msg, file=sys.stderr)


def main():
    payload = json.load(sys.stdin)

    input_path = payload["input_path"]
    if not os.path.isfile(input_path):
        raise RuntimeError(f"ocr_text: girdi görseli bulunamadı: {input_path}")

    lang = (payload.get("lang") or "eng").strip()
    min_confidence = float(payload.get("min_confidence", 0.4))

    import pytesseract
    from PIL import Image

    im = Image.open(input_path).convert("RGB")
    data = pytesseract.image_to_data(im, lang=lang, output_type=pytesseract.Output.DICT)

    words = []
    n = len(data.get("text", []))
    for i in range(n):
        text = (data["text"][i] or "").strip()
        if not text:
            continue

        # tesseract confidence -1..100 arası verir; -1 "kutu değil" demektir.
        raw_conf = float(data.get("conf", ["-1"] * n)[i])
        confidence = max(0.0, raw_conf) / 100.0
        if confidence < min_confidence:
            continue

        words.append({
            "text": text,
            "x": int(data["left"][i]),
            "y": int(data["top"][i]),
            "w": int(data["width"][i]),
            "h": int(data["height"][i]),
            "confidence": round(confidence, 4),
        })

    sys.stdout.write(json.dumps({"words": words}, ensure_ascii=False))


if __name__ == "__main__":
    main()
