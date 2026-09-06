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


def test_nipnaps_measurements_real_latzee_matrix():
    """Gerçek LATZEE Maßtabelle'si beden-kolonu hizalı, Sprungwerte ayıklanmış çıkar."""
    doc = fitz.open(LATZEE)
    m = NipnapsProfile().parse_measurements(doc)
    assert m is not None, "Maßtabelle parse edilemedi (None döndü)"
    assert set(m["labels"]) >= {"OW", "TW", "HW", "SL", "KH"}
    # 21 KIKO bedeni: 56..176
    assert len(m["sizes"]) == 21
    assert m["sizes"][0] == "56" and m["sizes"][-1] == "176"
    # OW (Oberweite): beden 56 -> 22.5, beden 176 -> 46.0 (atlama değerleri elenmiş)
    ow = m["matrix"]["OW"]
    assert len(ow) == 21
    assert ow[0] == 22.5 and ow[-1] == 46.0
    assert None not in ow
    # KH (Körperhöhe) satırı bedenlerin kendisidir
    kh = m["matrix"]["KH"]
    assert kh[0] == 56.0 and kh[-1] == 176.0
    # SL (Seitenlänge) tablo 1'den (tablo 2 değil): 56 -> 27.5
    assert m["matrix"]["SL"][0] == 27.5


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


from profiles.generic import GenericGridProfile

GEN_A4_W_PT, GEN_A4_H_PT = 595.0, 842.0


def test_generic_grid_never_auto_selected():
    # match() her zaman 0 — REGISTRY skorlamasına katılmaz (yalnızca converter'ın
    # açık fallback yolundan çağrılır, bkz. converter._try_generic_grid).
    assert GenericGridProfile().match(fitz.open()) == 0.0
    names = {p.name for p in REGISTRY}
    assert "generic_grid" not in names


def test_generic_grid_direct_paren_pattern():
    p = GenericGridProfile()
    assert p.parse_grid("köşe (2, 3) etiket") == (2, 3)
    assert p.parse_grid("etiket yok") is None


def test_generic_own_tile_label_is_statistical_outlier():
    doc = fitz.open()
    page = doc.new_page(width=GEN_A4_W_PT, height=GEN_A4_H_PT)
    page.insert_text((40, 40), "dikiş payı dahil değildir", fontsize=9)
    page.insert_text((200, 400), "07", fontsize=55)  # Burda'nın 100pt eşiğinin ALTINDA
    p = GenericGridProfile()
    assert p.own_tile_label(page) == "07"


def test_generic_own_tile_label_none_when_no_outlier():
    doc = fitz.open()
    page = doc.new_page(width=GEN_A4_W_PT, height=GEN_A4_H_PT)
    page.insert_text((40, 40), "sıradan metin 07 burada", fontsize=10)
    p = GenericGridProfile()
    assert p.own_tile_label(page) is None


def test_generic_build_grid_map_clusters_overview_page():
    doc = fitz.open()
    overview = doc.new_page(width=GEN_A4_W_PT, height=GEN_A4_H_PT)
    grid = [["11", "12", "13"], ["21", "22", "23"], ["31", "32", "33"]]
    for r, row in enumerate(grid):
        for c, lbl in enumerate(row):
            overview.insert_text((100 + c * 130, 100 + r * 130), lbl, fontsize=45)
    p = GenericGridProfile()
    gm = p.build_grid_map(doc)
    assert gm == {
        "11": (1, 1), "12": (1, 2), "13": (1, 3),
        "21": (2, 1), "22": (2, 2), "23": (2, 3),
        "31": (3, 1), "32": (3, 2), "33": (3, 3),
    }


def test_generic_build_grid_map_none_when_too_few_labels():
    doc = fitz.open()
    page = doc.new_page(width=GEN_A4_W_PT, height=GEN_A4_H_PT)
    page.insert_text((100, 100), "11", fontsize=14)
    page.insert_text((100, 130), "12", fontsize=14)
    assert GenericGridProfile().build_grid_map(doc) is None
