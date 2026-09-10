import shutil

import pytest
from pydantic import ValidationError

from pattern_core.engine import spec as spec_module
from pattern_core.engine.resolver import resolve
from pattern_core.engine.spec import (
    InvalidProfileNameError,
    ProtectedProfileError,
    create_or_update_profile,
    list_profiles,
    validate_profile_name,
)


@pytest.fixture
def isolated_profiles_dir(tmp_path, monkeypatch):
    """`spec.PROFILES_DIR`'ı geçici bir dizine yönlendirir — testler gerçek
    repo profillerine yazmaz. `ts_default` referans olarak kopyalanır (create_or_update_profile
    onu `base_profile` olarak kullanabilsin diye)."""
    real_ts_default = spec_module.PROFILES_DIR / "ts_default"
    fake_root = tmp_path / "profiles"
    shutil.copytree(real_ts_default, fake_root / "ts_default")
    monkeypatch.setattr(spec_module, "PROFILES_DIR", fake_root)
    return fake_root


def test_list_profiles_includes_ts_default(isolated_profiles_dir):
    names = [p["name"] for p in list_profiles()]
    assert names == ["ts_default"]


@pytest.mark.parametrize("bad_name", ["../etc", "Foo Bar", "", "a" * 65, "café", "musteri/x", "_leading"])
def test_validate_profile_name_rejects_unsafe_names(bad_name):
    with pytest.raises(InvalidProfileNameError):
        validate_profile_name(bad_name)


def test_validate_profile_name_accepts_safe_slug():
    validate_profile_name("musteri_x-2")  # raise etmemeli


def test_create_or_update_profile_rejects_ts_default(isolated_profiles_dir):
    with pytest.raises(ProtectedProfileError):
        create_or_update_profile("ts_default", {"6Y": {}}, {"source": "x"})


def test_create_or_update_profile_rejects_path_traversal_name(isolated_profiles_dir):
    with pytest.raises(InvalidProfileNameError):
        create_or_update_profile("../evil", {"6Y": {}}, {"source": "x"})


def test_create_or_update_profile_writes_new_profile_and_copies_rules(isolated_profiles_dir):
    sizes = {
        "6Y": {
            "height": 1160, "chest_circ": 620, "waist_circ": 570, "shoulder_width": 285,
            "neck_circ": 280, "back_width": 270, "arm_length": 385, "armhole_depth": 142,
            "upper_arm_circ": 215, "body_length": 440,
        },
    }
    provenance = {"source": "musteri_x_modelist", "verified_by_modelist": True}

    spec = create_or_update_profile("musteri_x", sizes, provenance)

    assert spec.profile == "musteri_x"
    assert spec.sizes.provenance.verified_by_modelist is True
    assert (isolated_profiles_dir / "musteri_x" / "rules.yaml").is_file()
    assert (isolated_profiles_dir / "musteri_x" / "tolerances.yaml").is_file()
    assert (isolated_profiles_dir / "musteri_x" / "meta.yaml").is_file()

    # Kopyalanan formüllerle (base_profile'dan) gerçek çözümleme çalışmalı.
    measurements = resolve(spec, "6Y")
    assert measurements["chest_garment"] == 620 + spec.rules.ease["chest"]

    names = [p["name"] for p in list_profiles()]
    assert "musteri_x" in names


def test_create_or_update_profile_is_invalid_data_does_not_touch_disk(isolated_profiles_dir):
    with pytest.raises(ValidationError):
        create_or_update_profile("bad_profile", {"6Y": {"chest_circ": "not-a-number"}}, {"source": "x"})

    assert not (isolated_profiles_dir / "bad_profile").exists()


def test_create_or_update_profile_second_call_does_not_overwrite_rules(isolated_profiles_dir):
    """Sonradan gelen bir güncelleme, elle özelleştirilmiş rules.yaml'ı ezmemeli."""
    create_or_update_profile("musteri_y", {"8Y": {"chest_circ": 700}}, {"source": "x"})
    (isolated_profiles_dir / "musteri_y" / "rules.yaml").write_text("garment: custom\nunit: mm\nderived: {}\n")

    create_or_update_profile("musteri_y", {"8Y": {"chest_circ": 710}}, {"source": "x"})

    content = (isolated_profiles_dir / "musteri_y" / "rules.yaml").read_text()
    assert "custom" in content


def test_create_or_update_profile_merges_sizes_without_losing_existing_ones(isolated_profiles_dir):
    """Bir profile yeni beden eklemek, önceden girilmiş diğer bedenleri SİLMEMELİ.

    UI'daki 'yeni profil' formu her seferinde yalnızca değiştirdiği bedenleri
    gönderir — çağıran taraf her seferinde TÜM bedenleri yeniden göndermek
    zorunda kalmamalı, aksi halde sessiz veri kaybı olur (§P4).
    """
    create_or_update_profile("musteri_z", {"6Y": {"chest_circ": 620}}, {"source": "x"})
    spec = create_or_update_profile("musteri_z", {"8Y": {"chest_circ": 670}}, {"source": "x"})

    assert set(spec.sizes.sizes.keys()) == {"6Y", "8Y"}
    assert spec.sizes.sizes["6Y"]["chest_circ"] == 620
    assert spec.sizes.sizes["8Y"]["chest_circ"] == 670


def test_create_or_update_profile_updates_existing_size_in_place(isolated_profiles_dir):
    """Aynı beden anahtarı tekrar gönderilirse GÜNCELLENMELİ (birleşme, o beden için ezme demektir)."""
    create_or_update_profile("musteri_w", {"8Y": {"chest_circ": 670}}, {"source": "x"})
    spec = create_or_update_profile("musteri_w", {"8Y": {"chest_circ": 680}}, {"source": "x"})

    assert spec.sizes.sizes["8Y"]["chest_circ"] == 680
