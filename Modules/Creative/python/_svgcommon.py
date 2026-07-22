"""Ortak SVG ayrıştırma yardımcıları (yalnız stdlib).

inspect_template.py ve render.py paylaşır. Slot sözleşmesi:
  - data-slot="<key>" taşıyan eleman bir slottur.
  - <text data-slot="...">  -> metin slotu (values[key] ile değiştirilir).
  - <rect|image data-slot="..."> -> görsel slotu (images[key] kompozit edilir).
Koordinatlar kök düzlemde (parent transform'suz) varsayılır.
"""

import re
import xml.etree.ElementTree as ET

SVG_NS = "http://www.w3.org/2000/svg"

# Serileştirmede ns0: öneki yerine düz <svg> üretmek için.
ET.register_namespace("", SVG_NS)

_TEXT_TAGS = {"text", "tspan"}
_IMAGE_TAGS = {"rect", "image"}

_NUM_RE = re.compile(r"-?\d*\.?\d+")


def local_tag(elem):
    """'{ns}tag' -> 'tag'."""
    tag = elem.tag
    if isinstance(tag, str) and tag.startswith("{"):
        return tag.split("}", 1)[1]
    return tag


def parse_length(value, default=0.0):
    """'120px', '12.5', '3em' -> float (birim eki yok sayılır)."""
    if value is None:
        return default
    if isinstance(value, (int, float)):
        return float(value)
    m = _NUM_RE.search(str(value))
    return float(m.group()) if m else default


def load_tree(svg_path):
    """SVG dosyasını ayrıştırıp kök elemanı döndürür."""
    tree = ET.parse(svg_path)
    return tree.getroot()


def dimensions(root):
    """width/height attribute'larından, yoksa viewBox'tan (w,h) döndürür."""
    w = parse_length(root.get("width"), 0.0)
    h = parse_length(root.get("height"), 0.0)
    if w <= 0 or h <= 0:
        vb = root.get("viewBox") or root.get("viewbox")
        if vb:
            nums = _NUM_RE.findall(vb)
            if len(nums) == 4:
                w = w if w > 0 else float(nums[2])
                h = h if h > 0 else float(nums[3])
    return int(round(w)), int(round(h))


def _align_from_anchor(anchor):
    return {"start": "left", "middle": "center", "end": "right"}.get(
        (anchor or "start").strip(), "left"
    )


def iter_slots(root):
    """data-slot taşıyan elemanları (elem, slot_dict) çiftleri olarak üretir.

    slot_dict alanları:
      metin:  key,type=text,x,y,font_size,align,fill,bold
      görsel: key,type=image,x,y,w,h,fit
    """
    for elem in root.iter():
        key = elem.get("data-slot")
        if not key:
            continue
        tag = local_tag(elem)

        if tag in _TEXT_TAGS:
            weight = (elem.get("font-weight") or "").strip().lower()
            bold = weight in ("bold", "bolder") or weight.isdigit() and int(weight) >= 600
            yield elem, {
                "key": key,
                "type": "text",
                "x": parse_length(elem.get("x")),
                "y": parse_length(elem.get("y")),
                "font_size": parse_length(elem.get("font-size"), 16.0),
                "align": _align_from_anchor(elem.get("text-anchor")),
                "fill": (elem.get("fill") or "#000000").strip(),
                "bold": bool(bold),
                # Render'ı ETKİLEMEZ (metin SVG'de gerçek bir "width"e sahip değil) —
                # yalnız backend'deki karakter-tavanı denetimi (CreativeCopyRuleEngine)
                # için saklanan, custom bir attribute (bkz. apply_slots.py::_apply_text).
                "w": parse_length(elem.get("data-w")),
            }
        elif tag in _IMAGE_TAGS:
            yield elem, {
                "key": key,
                "type": "image",
                "x": parse_length(elem.get("x")),
                "y": parse_length(elem.get("y")),
                "w": parse_length(elem.get("width")),
                "h": parse_length(elem.get("height")),
                "fit": (elem.get("data-fit") or "cover").strip().lower(),
            }


def slots_of(root):
    """Yalnız slot sözlüklerinin listesi (eleman referansı olmadan)."""
    return [slot for _elem, slot in iter_slots(root)]
