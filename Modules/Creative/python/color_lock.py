"""Renk kilidi: stdin payload -> stdout PNG baytları.

Giydirme çıktısındaki giysi bölgesinin a/b (renk) kanallarını, orijinal ürün
fotoğrafının ortalama rengine doğru kaydırır; L (parlaklık — gölge, kırışıklık,
kumaş dokusu) kanalına DOKUNMAZ. Böylece kumaşın vücuda oturması AI çıktısından,
rengi orijinal üründen gelir (bkz. ROADMAP.md Faz Q, kullanıcının LAB transfer
fikri). Giysi bölgesi `color_audit.py` ile AYNI diff-mask yöntemiyle bulunur
(posed_path vs output_path piksel farkı) — ayrı bir segmentasyon modeli YOK.

Payload:
  {
    "output_path": "/.../out_xxx.png",         # zorunlu — düzeltilecek görsel
    "original_path": "/.../garment_123.jpg",   # zorunlu — hedef renk kaynağı
    "posed_path": "/.../posed_xxx.png",        # zorunlu — giydirmeden ÖNCEKİ manken
    "strength": 0.85,                          # 0..1, ne kadarı uygulanır
    "diff_threshold": 28.0, "bg_tolerance": 24.0,
    "mask_min_ratio": 0.02, "mask_max_ratio": 0.6,
    "feather_radius": 6,
    "mime": "image/png"
  }

Maske güvenilmezse (çok küçük/çok büyük ya da orijinal/çıktı rengi
okunamazsa) görsele HİÇ dokunulmaz, `output_path` baytları aynen döner —
bu adım hiçbir zaman görseli bozma riski almaz.
"""

import io
import json
import sys

import numpy as np
from PIL import Image

import _color_common as cc


def _log(msg):
    print(msg, file=sys.stderr)


def _passthrough(path):
    with open(path, 'rb') as f:
        sys.stdout.buffer.write(f.read())


def main():
    payload = json.load(sys.stdin)

    output_path = payload['output_path']
    original_path = payload['original_path']
    posed_path = payload['posed_path']
    strength = float(payload.get('strength', 0.85))
    diff_threshold = float(payload.get('diff_threshold', 28.0))
    bg_tolerance = float(payload.get('bg_tolerance', 24.0))
    mask_min_ratio = float(payload.get('mask_min_ratio', 0.02))
    mask_max_ratio = float(payload.get('mask_max_ratio', 0.6))
    feather_radius = int(payload.get('feather_radius', 6))

    try:
        original_img = cc.load_rgb(original_path)
        posed_img = cc.load_rgb(posed_path)
        output_img = cc.load_rgb(output_path)
    except Exception as e:  # noqa: BLE001
        _log(f'okuma hatası, düzeltme atlandı: {e}')
        _passthrough(output_path)
        return

    posed_img = cc.resize_to_match(posed_img, output_img.size)

    original_mask = cc.foreground_mask_by_corner_bg(original_img, tolerance=bg_tolerance)
    original_lab = cc.dominant_lab_in_mask(original_img, original_mask)

    diff = cc.diff_mask(posed_img, output_img, threshold=diff_threshold)
    mask_ratio = float(diff.mean())
    output_lab = cc.dominant_lab_in_mask(output_img, diff)

    if (
        original_lab is None
        or output_lab is None
        or mask_ratio < mask_min_ratio
        or mask_ratio > mask_max_ratio
    ):
        _log(f'düşük güvenli maske (ratio={mask_ratio:.4f}), düzeltme atlandı')
        _passthrough(output_path)
        return

    delta_a = original_lab[1] - output_lab[1]
    delta_b = original_lab[2] - output_lab[2]

    alpha = cc.feather_mask(diff, radius=feather_radius) * strength  # (H,W) 0..1

    lab = cc.rgb_to_lab(np.asarray(output_img))
    lab[..., 1] = lab[..., 1] + alpha * delta_a
    lab[..., 2] = lab[..., 2] + alpha * delta_b
    # L (parlaklık) kanalına dokunulmadı — kumaşın gölge/kırışıklık/doku bilgisi korunur.

    corrected = cc.lab_to_rgb(lab)
    out_img = Image.fromarray(corrected, mode='RGB')

    buf = io.BytesIO()
    out_img.save(buf, format='PNG')
    sys.stdout.buffer.write(buf.getvalue())


if __name__ == '__main__':
    main()
