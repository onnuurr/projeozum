"""Slot düzenleyici: tasarımcıdan gelen slot listesini SVG'ye geri yazar.

Render motoru slotları SVG'deki data-slot elemanlarından okur; bu yüzden
tasarımcıda yapılan konum/boyut/özellik değişiklikleri kalıcı olması için
SVG XML'ine işlenmelidir (DB'deki slots kopyası tek başına render'ı etkilemez).

Payload (stdin JSON):
  {
    "svg_path": "/abs/path.svg",
    "slots": [
      {"ref": "product_image"|null, "key":"...", "type":"text"|"image",
       "x":..,"y":..,"w":..,"h":..,"fit":"cover"|"contain",
       "font_size":..,"bold":bool,"align":"left"|"center"|"right","fill":"#.."}
    ]
  }

- ref: slotun mevcut (eski) data-slot anahtarı; null ise yeni eleman oluşturulur.
- payload'da ref'i bulunmayan mevcut data-slot elemanları silinir.
Çıktı: değişiklik sonrası slot sayısını içeren küçük JSON (bilgi amaçlı).
"""

import json
import sys
import xml.etree.ElementTree as ET

import _svgcommon as svg

SVG_NS = svg.SVG_NS
_ALIGN_TO_ANCHOR = {"left": "start", "center": "middle", "right": "end"}


def _num(value, default=0):
    try:
        return round(float(value), 2)
    except (TypeError, ValueError):
        return default


def _build_parent_map(root):
    return {child: parent for parent in root.iter() for child in parent}


def _apply_text(elem, slot):
    elem.set("data-slot", slot["key"])
    elem.set("x", str(_num(slot.get("x"))))
    elem.set("y", str(_num(slot.get("y"))))
    elem.set("font-size", str(_num(slot.get("font_size"), 16)))
    elem.set("text-anchor", _ALIGN_TO_ANCHOR.get(slot.get("align", "left"), "start"))
    elem.set("font-weight", "bold" if slot.get("bold") else "normal")
    if slot.get("fill"):
        elem.set("fill", str(slot["fill"]))
    if not (elem.text and elem.text.strip()):
        elem.text = slot["key"]


def _apply_image(elem, slot):
    elem.set("data-slot", slot["key"])
    elem.set("x", str(_num(slot.get("x"))))
    elem.set("y", str(_num(slot.get("y"))))
    elem.set("width", str(_num(slot.get("w"))))
    elem.set("height", str(_num(slot.get("h"))))
    elem.set("data-fit", slot.get("fit", "cover"))


def main():
    payload = json.load(sys.stdin)
    svg_path = payload["svg_path"]
    slots = payload.get("slots") or []

    root = svg.load_tree(svg_path)
    parent_map = _build_parent_map(root)

    # Mevcut slot elemanlarını data-slot anahtarına göre indeksle.
    existing = {}
    for elem in root.iter():
        key = elem.get("data-slot")
        if key:
            existing[key] = elem

    kept_refs = {s.get("ref") for s in slots if s.get("ref")}

    # payload'da olmayan eski slotları sil.
    for key, elem in list(existing.items()):
        if key not in kept_refs:
            parent = parent_map.get(elem)
            if parent is not None:
                parent.remove(elem)

    # Slotları uygula / oluştur.
    for slot in slots:
        ref = slot.get("ref")
        elem = existing.get(ref) if ref else None

        if elem is None:
            tag = "text" if slot.get("type") == "text" else "rect"
            elem = ET.SubElement(root, f"{{{SVG_NS}}}{tag}")
            if tag == "rect":
                elem.set("fill", "none")

        if slot.get("type") == "text":
            _apply_text(elem, slot)
        else:
            _apply_image(elem, slot)

    ET.ElementTree(root).write(svg_path, encoding="utf-8", xml_declaration=True)

    sys.stdout.write(json.dumps({"slots": len(slots)}))


if __name__ == "__main__":
    main()
