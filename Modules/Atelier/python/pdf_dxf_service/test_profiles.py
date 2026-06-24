from profiles.base import color_matches, scan_lexicon, ExtractionProfile


def test_color_matches_within_tolerance():
    assert color_matches((0.0, 0.5, 0.0), (0.0, 0.5, 0.0), 0.08) is True
    assert color_matches((0.05, 0.52, 0.02), (0.0, 0.5, 0.0), 0.08) is True
    assert color_matches((0.0, 0.0, 0.0), (0.0, 0.5, 0.0), 0.08) is False
    assert color_matches(None, (0.0, 0.5, 0.0), 0.08) is False


def test_scan_lexicon_finds_first_key():
    lex = {"latzhose": "tulum", "jacke": "ceket"}
    assert scan_lexicon("eine coole latzhose", lex) == "tulum"
    assert scan_lexicon("nichts hier", lex) is None


def test_base_profile_defaults():
    p = ExtractionProfile()
    assert p.name == "base"
    assert p.assembly == "grid"
    assert p.color_role((0.1, 0.1, 0.1)) == "ortak"
    assert p.parse_grid("anything") is None
    assert p.parse_parts("anything") == []


import fitz
from profiles.ruslan import RuslanProfile

A4_W_PT, A4_H_PT = 210.0 / 0.352777, 297.0 / 0.352777


def _ruslan_doc():
    doc = fitz.open()
    doc.new_page(width=612, height=792)  # kapak
    for r in range(1, 3):
        for c in range(1, 3):
            page = doc.new_page(width=A4_W_PT, height=A4_H_PT)
            page.insert_text((40, 40), f"({r}, {c})")
            for k in range(15):
                page.draw_line((60, 100 + k * 30), (300, 100 + k * 30),
                               color=(0, 0, 0), width=0.12)
    return doc


def test_ruslan_matches_paren_grid_black():
    assert RuslanProfile().match(_ruslan_doc()) >= 0.5


def test_ruslan_accept_line_black_thin_only():
    p = RuslanProfile()
    assert p.accept_line((0, 0, 0), 0.12) is True
    assert p.accept_line((0, 0, 0), 0.72) is False        # kalın → ret
    assert p.accept_line((0, 0.5, 0), 0.12) is False       # yeşil → ret
    assert p.color_role((0, 0, 0)) == "ortak"


def test_ruslan_parse_grid_and_size():
    p = RuslanProfile()
    assert p.parse_grid("köşe (2, 3) etiket") == (2, 3)
    assert p.parse_size("tulum p.116-134") == "116-134"
    assert p.parse_product("tulum modeli") == "tulum"


import os
from profiles.nipnaps import NipnapsProfile

LATZEE = os.path.join(os.path.dirname(__file__), "..", "..", "..", "..",
                      "docs", "superpowers", "plans",
                      "salopeta-Ebook-LATZEE_compressed-2.pdf")


def test_nipnaps_matches_real_latzee():
    doc = fitz.open(LATZEE)
    p = NipnapsProfile()
    assert p.match(doc) >= 0.5
    # Rusça profili bu dosyaya düşük skor vermeli (palet renkli + Almanca)
    assert RuslanProfile().match(doc) < 0.3


def test_nipnaps_color_role_palette():
    p = NipnapsProfile()
    assert p.color_role((0.0, 0.0, 0.0)) == "ortak"
    assert p.color_role((0.0, 0.5, 0.0)) == "yesil"
    assert p.color_role((0.0, 1.0, 1.0)) == "cyan"
    assert p.color_role((0.5, 0.5, 0.5)) is None  # palet dışı


def test_nipnaps_accept_thick_colored_line():
    p = NipnapsProfile()
    assert p.accept_line((0.0, 0.5, 0.0), 0.72) is True   # 0.72 (RU'da elenirdi)
    assert p.accept_line((0.0, 0.5, 0.0), 1.2) is True
    assert p.accept_line((0.5, 0.5, 0.5), 0.72) is False  # palet dışı renk


def test_nipnaps_detects_tile_pages_from_31():
    doc = fitz.open(LATZEE)
    p = NipnapsProfile()
    tile_pages = [i for i in range(doc.page_count) if p.is_tile_page(doc[i])]
    assert min(tile_pages) >= 30          # 0-index → sayfa 31
    assert len(tile_pages) >= 8


def test_nipnaps_parts_from_zuschnitt():
    doc = fitz.open(LATZEE)
    text = " ".join(doc[i].get_text("text") for i in range(doc.page_count))
    parts = NipnapsProfile().parse_parts(text)
    names = {pp["part_name"] for pp in parts}
    assert "Arka" in names and "Ön" in names and "Askı" in names
    arka = next(pp for pp in parts if pp["part_name"] == "Arka")
    assert arka["quantity"] == 1


def test_nipnaps_measurements_best_effort():
    doc = fitz.open(LATZEE)
    m = NipnapsProfile().parse_measurements(doc)
    # Best-effort: ya yapılandırılmış matris ya None; None değilse etiketler dolu.
    assert m is None or set(m["labels"]) >= {"OW", "TW", "HW"}


from profiles import detect_profile, REGISTRY


def test_registry_has_both_profiles():
    names = {p.name for p in REGISTRY}
    assert {"ruslan", "nipnaps"} <= names


def test_detect_picks_nipnaps_for_latzee():
    doc = fitz.open(LATZEE)
    profile, score, candidates = detect_profile(doc)
    assert profile is not None and profile.name == "nipnaps"
    assert score >= 0.5


def test_detect_picks_ruslan_for_synthetic():
    profile, score, candidates = detect_profile(_ruslan_doc())
    assert profile is not None and profile.name == "ruslan"


def test_detect_returns_none_for_blank():
    doc = fitz.open()
    doc.new_page(width=612, height=792)  # boş, çizimsiz
    profile, score, candidates = detect_profile(doc)
    assert profile is None
