"""Creative render motoru: stdin payload -> stdout PNG/JPEG baytları.

Payload:
  {
    "svg_path": "...", "width": int, "height": int,
    "slots": [...],                      # PHP'den (bilgi amaçlı; SVG yeniden ayrıştırılır)
    "values": {"product_name": "..."},   # metin slotu değerleri
    "images": {"product_image": "C:/.../local.png"},  # görsel slotu yerel yolları
    "fonts": {"regular": "...ttf", "bold": "...ttf"},
    "resvg_bin": "" | "resvg",           # boşsa salt-Pillow fallback
    "mime": "image/png" | "image/jpeg"
  }

İki render yolu:
  - resvg varsa: metinler SVG XML'e gömülür, resvg rasterize eder.
  - resvg yoksa: beyaz tuval + Pillow ile metin çizilir (fallback).
Her iki modda görsel slotları Pillow ile kompozit edilir.
"""

import io
import json
import os
import shutil
import subprocess
import sys
import tempfile
import xml.etree.ElementTree as ET

from PIL import Image, ImageColor, ImageDraw, ImageFont

import _svgcommon as svg

# Sistem fontu fallback adayları (config yolları yoksa).
_SYSTEM_FONTS = {
    "regular": [
        r"C:\Windows\Fonts\arial.ttf",
        "/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf",
        "/Library/Fonts/Arial.ttf",
    ],
    "bold": [
        r"C:\Windows\Fonts\arialbd.ttf",
        "/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf",
        "/Library/Fonts/Arial Bold.ttf",
    ],
}

_font_cache = {}


def _first_existing(paths):
    for p in paths:
        if p and os.path.isfile(p):
            return p
    return None


def load_font(fonts_cfg, bold, size):
    """Savunmacı font yükleyici: config yolu -> sistem fontu -> Pillow default."""
    kind = "bold" if bold else "regular"
    size = max(1, int(round(size)))
    cache_key = (kind, size)
    if cache_key in _font_cache:
        return _font_cache[cache_key]

    candidate = _first_existing(
        [fonts_cfg.get(kind)] + _SYSTEM_FONTS[kind]
    )
    # bold istendi ama bold font yoksa regular'a düş.
    if candidate is None and bold:
        candidate = _first_existing(
            [fonts_cfg.get("regular")] + _SYSTEM_FONTS["regular"]
        )

    try:
        font = ImageFont.truetype(candidate, size) if candidate else ImageFont.load_default()
    except OSError:
        font = ImageFont.load_default()

    _font_cache[cache_key] = font
    return font


def _color(value, default=(0, 0, 0, 255)):
    try:
        rgb = ImageColor.getrgb(value)
    except (ValueError, AttributeError):
        return default
    if len(rgb) == 3:
        return rgb + (255,)
    return rgb


def fit_image(img, box_w, box_h, fit):
    """Görseli box_w x box_h RGBA karoya oturtur (cover: kırp, contain: sığdır)."""
    box_w = max(1, int(round(box_w)))
    box_h = max(1, int(round(box_h)))
    img = img.convert("RGBA")
    iw, ih = img.size
    if iw == 0 or ih == 0:
        return Image.new("RGBA", (box_w, box_h), (0, 0, 0, 0))

    if fit == "contain":
        scale = min(box_w / iw, box_h / ih)
        nw, nh = max(1, int(iw * scale)), max(1, int(ih * scale))
        resized = img.resize((nw, nh), Image.LANCZOS)
        tile = Image.new("RGBA", (box_w, box_h), (0, 0, 0, 0))
        tile.paste(resized, ((box_w - nw) // 2, (box_h - nh) // 2), resized)
        return tile

    # cover (varsayılan)
    scale = max(box_w / iw, box_h / ih)
    nw, nh = max(1, int(iw * scale)), max(1, int(ih * scale))
    resized = img.resize((nw, nh), Image.LANCZOS)
    left = (nw - box_w) // 2
    top = (nh - box_h) // 2
    return resized.crop((left, top, left + box_w, top + box_h))


def composite_images(canvas, image_slots, images):
    """Görsel slotlarını canvas üzerine kompozit eder."""
    for slot in image_slots:
        key = slot["key"]
        local = images.get(key)
        if not local or not os.path.isfile(local):
            continue
        try:
            src = Image.open(local)
        except OSError:
            continue
        w = int(round(slot.get("w") or 0))
        h = int(round(slot.get("h") or 0))
        if w <= 0 or h <= 0:
            continue
        tile = fit_image(src, w, h, slot.get("fit", "cover"))
        canvas.alpha_composite(tile, (int(round(slot["x"])), int(round(slot["y"]))))


def draw_texts(canvas, text_slots, values, fonts_cfg):
    """Metin slotlarını Pillow ile çizer (fallback modu)."""
    draw = ImageDraw.Draw(canvas)
    anchor_map = {"left": "ls", "center": "ms", "right": "rs"}
    for slot in text_slots:
        text = values.get(slot["key"])
        if text is None or text == "":
            continue
        font = load_font(fonts_cfg, slot.get("bold", False), slot.get("font_size", 16))
        anchor = anchor_map.get(slot.get("align", "left"), "ls")
        draw.text(
            (slot["x"], slot["y"]),
            str(text),
            font=font,
            fill=_color(slot.get("fill", "#000000")),
            anchor=anchor,
        )


def render_with_resvg(resvg_bin, root, values, fonts_cfg, width, height):
    """Metinleri SVG'ye gömüp resvg ile rasterize eder; RGBA Image döndürür."""
    for elem, slot in svg.iter_slots(root):
        if slot["type"] == "text" and slot["key"] in values:
            elem.text = str(values[slot["key"]])

    svg_bytes = ET.tostring(root, encoding="utf-8")

    tmp_in = tempfile.NamedTemporaryFile(suffix=".svg", delete=False)
    tmp_out = tempfile.NamedTemporaryFile(suffix=".png", delete=False)
    tmp_in.write(svg_bytes)
    tmp_in.close()
    tmp_out.close()
    try:
        cmd = [resvg_bin]
        for key in ("regular", "bold"):
            path = fonts_cfg.get(key)
            if path and os.path.isfile(path):
                cmd += ["--use-font-file", path]
        if width > 0 and height > 0:
            cmd += ["--width", str(width), "--height", str(height)]
        cmd += [tmp_in.name, tmp_out.name]

        subprocess.run(cmd, check=True, capture_output=True)
        img = Image.open(tmp_out.name).convert("RGBA")
        if width > 0 and height > 0 and img.size != (width, height):
            img = img.resize((width, height), Image.LANCZOS)
        return img
    finally:
        for p in (tmp_in.name, tmp_out.name):
            try:
                os.unlink(p)
            except OSError:
                pass


def main():
    payload = json.load(sys.stdin)

    svg_path = payload["svg_path"]
    values = payload.get("values") or {}
    images = payload.get("images") or {}
    fonts_cfg = payload.get("fonts") or {}
    resvg_bin = (payload.get("resvg_bin") or "").strip()
    mime = payload.get("mime") or "image/png"

    root = svg.load_tree(svg_path)
    width, height = svg.dimensions(root)
    if payload.get("width"):
        width = int(payload["width"])
    if payload.get("height"):
        height = int(payload["height"])
    width = max(1, width)
    height = max(1, height)

    slots = svg.slots_of(root)
    text_slots = [s for s in slots if s["type"] == "text"]
    image_slots = [s for s in slots if s["type"] == "image"]

    resvg_ok = bool(resvg_bin) and shutil.which(resvg_bin) is not None

    if resvg_ok:
        # resvg yeniden ayrıştırılmış ağaca metin gömer; bu yüzden taze kök al.
        root2 = svg.load_tree(svg_path)
        canvas = render_with_resvg(resvg_bin, root2, values, fonts_cfg, width, height)
        composite_images(canvas, image_slots, images)
    else:
        # Salt-Pillow fallback: beyaz tuval.
        canvas = Image.new("RGBA", (width, height), (255, 255, 255, 255))
        composite_images(canvas, image_slots, images)
        draw_texts(canvas, text_slots, values, fonts_cfg)

    buf = io.BytesIO()
    if mime == "image/jpeg":
        flat = Image.new("RGB", canvas.size, (255, 255, 255))
        flat.paste(canvas, mask=canvas.split()[-1])
        flat.save(buf, format="JPEG", quality=90)
    else:
        canvas.save(buf, format="PNG")

    sys.stdout.buffer.write(buf.getvalue())


if __name__ == "__main__":
    main()
