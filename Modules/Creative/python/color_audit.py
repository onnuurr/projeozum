"""Renk sadakati ölçümü: stdin payload -> stdout JSON.

Orijinal ürün fotoğrafının rengiyle, giydirme çıktısındaki giysi bölgesinin
rengi arasındaki farkı (CIE76 Delta E) ölçer. Segmentasyon modeli KURULMAZ —
giysi bölgesi, giydirmeden ÖNCEKİ manken görseli (`posed_path`) ile SONRAKİ
görsel (`output_path`) arasındaki piksel farkından (diff-mask) çıkarılır; bu
ikisi zaten `ProductOnModelService::generate()` içinde üretiliyor (bkz.
ROADMAP.md Faz Q — zero-shot dedektörün 190-230sn/görsel maliyeti bu betiği
canlı bir denetim için elverişsiz kılıyor, diff-mask sıfır ek maliyetli).

Payload:
  {
    "original_path": "/.../garment_123.jpg",  # zorunlu — giydirilen ürün görseli
    "posed_path": "/.../posed_xxx.png",        # zorunlu — giydirmeden ÖNCEKİ manken
    "output_path": "/.../out_xxx.png",         # zorunlu — giydirmeden SONRAKİ görsel
    "diff_threshold": 28.0,
    "bg_tolerance": 24.0,
    "mask_min_ratio": 0.02,
    "mask_max_ratio": 0.6
  }

Çıktı (stdout, JSON):
  {
    "delta_e": 6.42,                # CIE76 Delta E, null = ölçülemedi
    "original_lab": [L, a, b],
    "output_lab": [L, a, b],
    "mask_ratio": 0.18,
    "confidence": "high" | "low"
  }

Herhangi bir adım başarısız olursa (dosya okunamaz, maske boş) süreç 0 ile
çıkar ve `{"delta_e": null, "confidence": "low", "error": "..."}` yazar —
çağıran taraf (PythonColorAuditor) bunu asla exception olarak görmez.
"""

import io
import json
import sys

import _color_common as cc


def _log(msg):
    print(msg, file=sys.stderr)


def main():
    payload = json.load(sys.stdin)

    original_path = payload['original_path']
    posed_path = payload['posed_path']
    output_path = payload['output_path']
    diff_threshold = float(payload.get('diff_threshold', 28.0))
    bg_tolerance = float(payload.get('bg_tolerance', 24.0))
    mask_min_ratio = float(payload.get('mask_min_ratio', 0.02))
    mask_max_ratio = float(payload.get('mask_max_ratio', 0.6))

    try:
        original_img = cc.load_rgb(original_path)
        posed_img = cc.load_rgb(posed_path)
        output_img = cc.load_rgb(output_path)
    except Exception as e:  # noqa: BLE001 - hiçbir zaman crash etmemeli
        json.dump({'delta_e': None, 'confidence': 'low', 'error': f'okuma hatası: {e}'}, sys.stdout)
        return

    posed_img = cc.resize_to_match(posed_img, output_img.size)

    original_mask = cc.foreground_mask_by_corner_bg(original_img, tolerance=bg_tolerance)
    original_lab = cc.dominant_lab_in_mask(original_img, original_mask)

    diff = cc.diff_mask(posed_img, output_img, threshold=diff_threshold)
    mask_ratio = float(diff.mean())
    output_lab = cc.dominant_lab_in_mask(output_img, diff)

    confidence = 'high'
    if original_lab is None or output_lab is None:
        confidence = 'low'
    elif mask_ratio < mask_min_ratio or mask_ratio > mask_max_ratio:
        confidence = 'low'

    delta_e = None
    if original_lab is not None and output_lab is not None:
        delta_e = round(cc.delta_e76(original_lab, output_lab), 2)

    json.dump({
        'delta_e': delta_e,
        'original_lab': None if original_lab is None else [round(float(v), 2) for v in original_lab],
        'output_lab': None if output_lab is None else [round(float(v), 2) for v in output_lab],
        'mask_ratio': round(mask_ratio, 4),
        'confidence': confidence,
    }, sys.stdout)


if __name__ == '__main__':
    main()
