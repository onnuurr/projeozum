"""converter.py için sentetik regresyon testi (gerçek PDF gerektirmez).

2×2 ızgaralı A4 karolar + 1 Letter kapak içeren bir PDF kurar; dönüştürücünün
kapağı atlayıp karoları birleştirmesini, ürün tipi/beden çıkarımını doğrular.

Çalıştırma: python test_converter.py   (veya pytest)
"""
import os

import fitz

from converter import convert_pdf, probe_pdf, A4_W_MM, A4_H_MM

LATZEE = os.path.join(os.path.dirname(__file__), "..", "..", "..", "..",
                      "docs", "superpowers", "plans",
                      "salopeta-Ebook-LATZEE_compressed-2.pdf")

A4_W_PT, A4_H_PT = A4_W_MM / 0.352777, A4_H_MM / 0.352777  # ~595.3 x 841.9


def _build_pdf() -> bytes:
    doc = fitz.open()
    # Letter kapak (atlanmalı)
    # Not: PyMuPDF varsayılan fontu Cyrillic render etmez; gerçek PDF'te gömülü
    # font vardı (manuel doğrulandı). Burada detection mantığını ASCII anahtarlarla
    # sınıyoruz: "tulum" (PRODUCT_LEXICON) ve "p.116-134" (SIZE_RE Latin 'p').
    cover = doc.new_page(width=612, height=792)
    cover.insert_text((72, 72), "Kapak / tulum p.116-134")
    # 2x2 A4 karo, her birinde 15 siyah ince çizgi + "(r,c)" etiketi
    for r in range(1, 3):
        for c in range(1, 3):
            page = doc.new_page(width=A4_W_PT, height=A4_H_PT)
            page.insert_text((40, 40), f"({r}, {c})")
            for k in range(15):
                y = 100 + k * 30
                page.draw_line((60, y), (300, y), color=(0, 0, 0), width=0.12)
    return doc.tobytes()


def test_skips_cover_and_stitches_grid():
    out = convert_pdf(_build_pdf(), "kombinezon_116-134.pdf")
    assert out.metadata["grid"] == {"rows": 2, "cols": 2}, out.metadata
    assert out.metadata["tiles"] == 4
    assert out.metadata["segment_count"] == 60  # 4 karo × 15 çizgi
    assert out.dxf and "ENTITIES" in out.dxf


def test_extracts_product_and_size():
    out = convert_pdf(_build_pdf(), "x.pdf")
    assert out.metadata["product_type"] == "tulum"   # комбинезон
    assert out.metadata["size_range"] == "116-134"   # р.116-134
    assert out.metadata["scale_verified"] is True


def test_classification_green_when_complete():
    out = convert_pdf(_build_pdf(), "x.pdf")
    assert out.classification == "green", (out.classification, out.errors)


def test_exact_a4_has_zero_deviation():
    out = convert_pdf(_build_pdf(), "x.pdf")
    assert out.metadata["scale_verified"] is True
    assert out.metadata["scale_deviation_mm"] == 0.0


def test_non_uniform_tiles_flagged():
    # Bir karoyu farklı boyda yap → tekdüzelik bozulur → ölçek doğrulanmaz.
    doc = fitz.open()
    doc.new_page(width=612, height=792)  # kapak
    for r in range(1, 3):
        for c in range(1, 3):
            w = A4_W_PT + (40 if (r, c) == (2, 2) else 0)  # (2,2) ~14mm geniş
            page = doc.new_page(width=w, height=A4_H_PT)
            page.insert_text((40, 40), f"({r}, {c})")
            page.draw_line((60, 100), (300, 100), color=(0, 0, 0), width=0.12)
    out = convert_pdf(doc.tobytes(), "x.pdf")
    assert out.metadata["scale_verified"] is False
    assert out.classification == "yellow"
    assert any("tekdüze" in e or "sapıyor" in e for e in out.errors), out.errors


def test_probe_detects_vector_tiled():
    # 2×2 ızgara, karo başına 25 çizgi (>20 eşik), resim yok.
    doc = fitz.open()
    doc.new_page(width=612, height=792)  # kapak
    for r in range(1, 3):
        for c in range(1, 3):
            page = doc.new_page(width=A4_W_PT, height=A4_H_PT)
            page.insert_text((40, 40), f"({r}, {c})")
            for k in range(25):
                page.draw_line((60, 100 + k * 25), (300, 100 + k * 25), color=(0, 0, 0), width=0.12)
    p = probe_pdf(doc.tobytes(), "x.pdf")
    assert p["kind"] == "vector_tiled", p


def test_probe_detects_raster():
    # Gömülü resimli sayfalar, vektör yok, ızgara yok → raster.
    doc = fitz.open()
    pix = fitz.Pixmap(fitz.csRGB, fitz.IRect(0, 0, 60, 60))
    pix.clear_with(180)
    for _ in range(5):
        page = doc.new_page(width=A4_W_PT, height=A4_H_PT)
        page.insert_image(fitz.Rect(40, 40, 500, 700), pixmap=pix)
    p = probe_pdf(doc.tobytes(), "scan.pdf")
    assert p["kind"] == "raster", p


def test_probe_invalid_for_non_pdf():
    assert probe_pdf(b"not a pdf", "junk.pdf")["kind"] == "invalid"


def test_non_pdf_is_red():
    out = convert_pdf(b"not a pdf", "junk.pdf")
    assert out.classification == "red"
    assert out.dxf is None


def test_grid_profile_sets_metadata():
    out = convert_pdf(_build_pdf(), "kombinezon_116-134.pdf")
    assert out.metadata["profile"] == "ruslan"
    assert out.metadata["assembly"] == "grid"
    assert out.metadata["grid"] == {"rows": 2, "cols": 2}
    assert out.metadata["segment_count"] == 60
    assert "BEDEN_" not in out.dxf or "KALIP" in out.dxf  # ruslan tek katman


def test_latzee_converts_with_color_layers():
    data = open(LATZEE, "rb").read()
    out = convert_pdf(data, "latzee.pdf")
    assert out.metadata["profile"] == "nipnaps"
    assert out.metadata["assembly"] == "contact_sheet"
    assert out.metadata["segment_count"] > 200
    assert out.dxf and "ENTITIES" in out.dxf
    # En az iki beden rengi ayrı katmana yazıldı.
    assert len(out.metadata["size_layers"]) >= 2
    assert any(L.startswith("BEDEN_") for L in out.metadata["size_layers"])
    assert out.classification in ("green", "yellow")


def test_latzee_probe_is_vector_tiled():
    data = open(LATZEE, "rb").read()
    p = probe_pdf(data, "latzee.pdf")
    assert p["kind"] == "vector_tiled"
    assert p["profile"] == "nipnaps"


def _build_unbranded_indirect_grid_pdf() -> bytes:
    """Hiçbir bilinen satıcı profiliyle EŞLEŞMEYEN (marka sözlüğü/imzası yok,
    Burda'nın SABİT punto eşiklerinin de dışında) ama yine de dolaylı-ızgara
    YAPISI taşıyan bir PDF: ayrı bir 'genel bakış' sayfasında küçük/tekdüze
    numaralı ızgara + her karo sayfasında o sayfanın gövde metninden istatistiksel
    olarak çok büyük, TEK BAŞINA duran bir seri numarası (bkz. profiles/generic.py)."""
    doc = fitz.open()
    overview = doc.new_page(width=A4_W_PT, height=A4_H_PT)
    grid = [["11", "12", "13"], ["21", "22", "23"], ["31", "32", "33"]]
    for r, row in enumerate(grid):
        for c, lbl in enumerate(row):
            overview.insert_text((100 + c * 130, 100 + r * 130), lbl, fontsize=45)
    for row in grid:
        for lbl in row:
            page = doc.new_page(width=A4_W_PT, height=A4_H_PT)
            page.insert_text((40, 40), "dikiş payı dahil değildir", fontsize=9)
            page.insert_text((200, 400), lbl, fontsize=55)
            page.draw_line((60, 100), (300, 100), color=(0, 0, 0), width=0.3)
            page.draw_line((60, 100), (60, 300), color=(0, 0, 0), width=0.3)
    return doc.tobytes()


def test_unbranded_pdf_assembles_via_generic_grid_fallback():
    """Marka önemli değil: bilinen hiçbir satıcı profili tutmasa da, PDF'in
    KENDİ yapısal ipuçları (genel bakış ızgarası) varsa gerçekten birleştirilmiş
    bir kalıp DXF'i üretilmeli — sayfa-yanyana ham dökümle YETİNİLMEMELİ."""
    out = convert_pdf(_build_unbranded_indirect_grid_pdf(), "bilinmeyen_marka.pdf")
    assert out.metadata["profile"] == "generic_grid"
    assert out.metadata["grid"] == {"rows": 3, "cols": 3}
    assert out.metadata["tiles"] == 9
    assert out.metadata["scale_verified"] is True
    assert out.metadata["segment_count"] == 18  # 9 karo × 2 çizgi
    assert out.classification == "yellow"  # marka doğrulanmadı ama montaj güvenilir
    assert out.dxf and "ENTITIES" in out.dxf


def test_no_structure_still_falls_back_to_raw_dump():
    # Vektör çizgi var ama ne (satır,sütun) ne de dolaylı-ızgara ipucu var →
    # generic_grid da kuramaz. Ama en az bir sayfa gerçek kesim-karosu gibi
    # (az kelime, çok çizgi) → ham sayfa-yanyana dökümüne düşülmeli (eski davranış).
    doc = fitz.open()
    page = doc.new_page(width=A4_W_PT, height=A4_H_PT)
    page.insert_text((40, 40), "az metin", fontsize=10)  # 2 kelime — çizim/kelime oranı yüksek kalır
    for k in range(20):
        page.draw_line((60, 100 + k * 20), (300, 100 + k * 20), color=(0, 0, 0), width=0.3)
    out = convert_pdf(doc.tobytes(), "yapisiz.pdf")
    assert out.metadata.get("source") == "vector_generic"
    assert out.metadata.get("profile") is None


def _build_instruction_booklet_pdf() -> bytes:
    """Gerçek vaka: yalnızca dikiş TALİMATI/kılavuzu (kesim şablonu YOK) — her
    sayfa yoğun paragraf metni + en fazla birkaç küçük diyagram çizimi taşır.
    Hiçbir sayfa 'az kelime + çok çizgi' kalıp-karosu imzasını taşımaz."""
    doc = fitz.open()
    paragraph = " ".join(["Bu adımda parçaları birleştirip dikin ve ütüleyin."] * 12)
    for i in range(6):
        page = doc.new_page(width=A4_W_PT, height=A4_H_PT)
        page.insert_text((40, 40), f"Adım {i + 1}: {paragraph}", fontsize=10)
        # Diyagram/tablo çizimi VAR ama paragraf metnine oranla azınlıkta (talimat
        # sayfalarının gerçek imzası — bkz. converter._has_plausible_pattern_page).
        for k in range(3):
            page.draw_line((60, 500 + k * 15), (200, 500 + k * 15), color=(0, 0, 0), width=0.3)
    return doc.tobytes()


def test_instruction_only_pdf_is_rejected_not_dumped():
    out = convert_pdf(_build_instruction_booklet_pdf(), "Instruktsia-ornek.pdf")
    assert out.classification == "red"
    assert out.dxf is None
    assert any("talimat" in e for e in out.errors), out.errors


def _build_vector_logo_cover_pdf() -> bytes:
    """Gerçek vaka: bir kapak/başlık sayfasının YAZISI harf-harf vektör dolgu
    path'i olarak basılmış (get_text() bunu METİN saymaz) — az kelime + çok
    path OBJESİ, salt orana bakan eski sürüm bunu yanlışlıkla 'kesim karosu'
    sanıyordu ("BARBIE DRESS FOR GIRLS INSTRUCTIONS" başlık sayfası vakası).
    Her 'harf' path'i KÜÇÜK (birkaç mm) — gerçek kesim çizgisi BÜYÜKLÜĞÜNDE
    değil (bkz. converter.PATTERN_PAGE_MIN_DRAWING_MM)."""
    doc = fitz.open()
    page = doc.new_page(width=A4_W_PT, height=A4_H_PT)
    page.insert_text((40, 40), "logo", fontsize=10)  # 1 kelime — oranı düşürmesin
    for k in range(20):
        shape = page.new_shape()
        shape.draw_rect(fitz.Rect(60 + k * 8, 100, 64 + k * 8, 108))  # ~1.4mm — harf glifi boyutu
        shape.finish(color=None, fill=(0, 0, 0))
        shape.commit()
    return doc.tobytes()


def test_vector_text_logo_cover_is_rejected_not_mistaken_for_pattern():
    out = convert_pdf(_build_vector_logo_cover_pdf(), "kapak.pdf")
    assert out.classification == "red"
    assert out.dxf is None


if __name__ == "__main__":
    passed = 0
    for name, fn in list(globals().items()):
        if name.startswith("test_") and callable(fn):
            fn()
            print(f"PASS {name}")
            passed += 1
    print(f"\n{passed} test geçti.")
