"""Kontrol karesi (10x10cm) var mı? Kalibrasyon anahtar sözcükleri + ~100mm kare arar."""
import sys, re
import fitz

sys.stdout.reconfigure(encoding="utf-8")
PT_TO_MM = 25.4 / 72.0
doc = fitz.open(sys.argv[1])

# 1) Kalibrasyon anahtar sözcükleri (RU/TR/EN)
KEYS = ["контроль", "тест", "квадрат", "10x10", "10 x 10", "10см", "10 см",
        "kontrol", "kare", "cm", "см", "10x10cm", "масштаб", "scale", "calibrat"]
print("=== kalibrasyon anahtar sözcük taraması ===")
for i in range(doc.page_count):
    t = doc[i].get_text("text").lower()
    hits = [k for k in KEYS if k in t]
    if hits:
        print(f"  sayfa {i}: {hits}")

# 2) Her sayfada TÜM çizimlerde ~100mm (90-110mm) eksen-hizalı dikdörtgen ara
print("\n=== ~100mm eksen-hizalı dikdörtgen taraması (tüm renkler) ===")
def near(a, b, tol=10): return abs(a - b) <= tol
found_any = False
for i in range(doc.page_count):
    for d in doc[i].get_drawings():
        rect = d.get("rect")
        if rect:
            w, h = rect.width * PT_TO_MM, rect.height * PT_TO_MM
            if near(w, 100) and near(h, 100):
                print(f"  sayfa {i}: rect {w:.1f}x{h:.1f}mm color={d.get('color')} fill={d.get('fill')}")
                found_any = True
        # 're' item'larını da kontrol et
        for it in d.get("items", []):
            if it[0] == "re":
                r = it[1]
                w, h = abs(r.x1 - r.x0) * PT_TO_MM, abs(r.y1 - r.y0) * PT_TO_MM
                if near(w, 100) and near(h, 100):
                    print(f"  sayfa {i}: re-item {w:.1f}x{h:.1f}mm")
                    found_any = True
if not found_any:
    print("  ~100mm kare BULUNAMADI")

# 3) Sayfa 0 (kapak) tüm metni
print("\n=== sayfa 0 (kapak) metni ===")
print(doc[0].get_text("text")[:800])
