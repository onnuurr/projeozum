"""Tüm sayfaları tarar: boyut, siyah segment, (satır,sütun) köşe etiketi, parça adı adayları."""
import sys, re, collections
import fitz

sys.stdout.reconfigure(encoding="utf-8")
PT_TO_MM = 25.4 / 72.0
doc = fitz.open(sys.argv[1])

def black_segs(page):
    n = 0
    for d in page.get_drawings():
        c = d.get("color"); w = d.get("width") or 0
        if c and all(float(x) <= 0.18 for x in c[:3]) and (not w or w <= 0.6):
            n += len(d.get("items", []))
    return n

coord_re = re.compile(r"\((\d+)\s*,\s*(\d+)\)")
all_words = collections.Counter()
rows = cols = 0
print("idx | size(mm)        | segs | corner-coord")
for i in range(doc.page_count):
    p = doc[i]
    txt = p.get_text("text")
    m = coord_re.search(txt.replace("\n", " "))
    coord = m.group(0) if m else "-"
    if m:
        rows = max(rows, int(m.group(1))); cols = max(cols, int(m.group(2)))
    sz = f"{p.rect.width*PT_TO_MM:5.1f}x{p.rect.height*PT_TO_MM:5.1f}"
    print(f"{i:3d} | {sz} | {black_segs(p):4d} | {coord}")
    for w in p.get_text("words"):
        t = w[4].strip()
        if t and not coord_re.search(t) and not t.replace(",", "").replace("(", "").replace(")", "").isdigit():
            all_words[t.lower()] += 1

print(f"\nIZGARA (max satır x sütun) = {rows} x {cols}")
print("\nen sık metin token'ları (parça adı/beden adayları):")
for t, n in all_words.most_common(40):
    print(f"  {t!r}: {n}")
