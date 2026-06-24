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
