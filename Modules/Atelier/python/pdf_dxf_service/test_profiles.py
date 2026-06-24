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
