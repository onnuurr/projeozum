# Kalıp Sayısallaştırma + AI Tasarım Platformu — Teknik Yol Haritası

**Hazırlanma tarihi:** Haziran 2026
**Mimari:** Laravel (ana) + Vue (arayüz) + Python/FastAPI (dönüştürücü) + Görsel AI API

---

## 1. Projenin Özeti ve Doğrulanmış Gerçekler

İki yetenekten oluşan bir platform kuruluyor:

1. **Toplu kalıp sayısallaştırma** — Yüzlerce PDF kalıbı sisteme tanımlayıp DXF'e çevirmek.
2. **AI destekli konsept tasarımı** — Text-to-image ile ürün görseli üretip, bunu DXF'lerle birlikte kalıpçıya/tasarımcıya iletmek.

### Prototipte kanıtlanmış olanlar
- Örnek PDF **vektör** (sayfa başına 100+ gerçek vektör çizim objesi).
- Kalıp çizgileri watermark'tan **renk + kalınlıkla ayrılabiliyor** (siyah `(0,0,0)`, 0.12 kalınlık → 512 temiz segment; watermark `color=None` → atılır).
- Sayfalar tam **A4 (210×297mm)** → ölçek bilinir, kalibrasyon mümkün.
- Köşe metni `(satır, sütun)` → **birleştirme ızgarası otomatik okunuyor** (5×5).
- Parça etiketleri PDF metninde mevcut (Рукав, Капюшон, Воротник, beden aralığı vb.) → **metadata otomatik çıkarılabilir**.
- 57MB ham çıktı → renk filtresiyle **96KB temiz DXF**.

### Kaynak durumu (kullanıcı teyidi)
PDF'ler **aynı tür**: vektör, tek satıcı. Bu, "karışık kaynak" riskini büyük ölçüde ortadan kaldırır ve otomasyon oranını yükseltir.

---

## 2. Dürüst Kısıtlar (Baştan Bilinmesi Gerekenler)

Bu kısıtlar teknik gerçeklerdir; planı bunlara göre kurmak gerekir.

### 2.1 İç içe bedenler tek katmanda
Kalıpta 4 beden (116-122-128-134) iç içe çizili. Vektör çizgiler beden etiketi taşımadığı için, hangi çizginin hangi bedene ait olduğu **programatik olarak güvenilir biçimde ayrılamaz**. İlk sürümde DXF tek katmanda çıkar; beden ayrımı ayrı bir Ar-Ge konusudur.

### 2.2 AI çıktısı kalıba dönüşmez
Text-to-image bir **konsept görsel** üretir — desen, renk, stil fikri. Bu görselin **ölçülü, dikilebilir bir kalıba otomatik dönüşümü yoktur**. AI konseptinin üretime bağlanması, altındaki DXF kütüphanesine ve bir kalıpçının yorumuna bağlıdır. Bu yüzden iki yetenek **paralel** yürütülür: kütüphane dolmadan AI tek başına üretim değeri vermez.

### 2.3 Ölçek hassasiyeti kritik
Tek milimetre sapma kesimde fire demektir. Her DXF, "kontrol karesi 10×10 cm" referansıyla **otomatik doğrulanmalı**, sapma varsa işaretlenmelidir.

### 2.4 Otomasyon oranı gerçekçi beklentisi
Tek tip vektör kaynak sayesinde otomasyon oranı yüksek olacak, ancak yine de **%100 değil**. İstisna dosyalar (bozuk ızgara, farklı renk şeması, parça eksikliği) için bir insan onay kuyruğu şarttır. Sistemin değeri "sıfır insan" değil, "insanı yalnızca gereken yerde kullanmak"tır.

---

## 3. Görsel AI Motoru Önerisi

Pazar 2026'da hızla değişiyor ve modeller 12 ayda orta sıraya düşüyor. Tek bir modele kilitlenmek yerine **model-agnostik bir soyutlama** kurmak en sağlam yaklaşımdır: kodda tek bir `ImageGenerator` arayüzü, arkasında değiştirilebilir sağlayıcı.

### Önerilen başlangıç stratejisi
- **Birincil (kalite):** GPT Image 1.5 veya Flux 2 Pro — kalite zirvesinde, aralarındaki fark istatistiksel hata payında. Kahraman/katalog görselleri için.
- **Hızlı/ucuz (taslak):** Nano Banana 2 (~$0.013/görsel) veya Seedream lite (~$0.032) — tasarımcının hızlı iterasyonu için, saniyeler içinde.
- **Erişim katmanı:** Tek tek sağlayıcılarla ayrı ayrı sözleşme yerine, çok-modelli bir geçit (ör. Replicate, fal.ai gibi unified API) ile başlayıp hacim büyüdükçe doğrudan sağlayıcıya geçiş değerlendirilebilir.

> Not: Birim fiyatlar Şubat–Mayıs 2026 kaynaklarına dayanır ve değişebilir; üretim planı öncesi sağlayıcının güncel fiyat sayfasından teyit edilmelidir. Higgsfield de bağlıdır ve görsel üretebilir; bir POC'de denenmesi ucuz bir testtir.

### Telif ve güvenlik
Mevcut marka ürün fotoğraflarını referans vermek türev/telif riski doğurur. **Kendi tarif ve desen havuzunuzla** çalışmak güvenli yoldur. Bunu Faz 1 tasarımında baştan kurgulayın.

---

## 4. Sistem Mimarisi

```
┌─────────────────────────────────────────────────────────┐
│                      Vue Arayüzü                          │
│  AI Konsept Stüdyosu │ Yükleme/Onay │ Kütüphane │ Atölye  │
└───────────────┬─────────────────────────────────────────┘
                │ HTTP/JSON
┌───────────────┴─────────────────────────────────────────┐
│                   Laravel (Ana Uygulama)                  │
│  Auth • Kuyruk (Queue) • DB • Storage • İş akışı • API    │
└──────┬───────────────────────────┬───────────────────────┘
       │ HTTP                       │ HTTP
┌──────┴──────────────┐   ┌─────────┴────────────────────────┐
│ Python/FastAPI       │   │ Görsel AI Geçidi                  │
│ PDF→DXF dönüştürücü   │   │ (model-agnostik soyutlama)        │
│ • analiz/sınıflandır  │   │ • taslak modeli (ucuz/hızlı)      │
│ • ızgara okuma        │   │ • kalite modeli (kahraman görsel) │
│ • renk filtresi       │   └───────────────────────────────────┘
│ • ölçek kalibrasyon   │
│ • metadata çıkarımı   │
└──────────────────────┘
```

### Neden ayrı FastAPI mikroservisi
- PDF/DXF kütüphaneleri (PyMuPDF, ezdxf) Python ekosisteminde olgun.
- Ağır dönüştürme işi Laravel'i bloklamaz; kuyrukla asenkron çalışır.
- Bağımsız ölçeklenir (yüzlerce dosya toplu işlenirken).

---

## 5. Modüller

### A — AI Konsept Stüdyosu
Tasarımcı bir tarif girer (ürün tipi, desen, renk, beden aralığı) → görsel AI birkaç konsept üretir → beğenilen "tasarım kartı"na dönüşür. Tasarım kartı sonradan kütüphanedeki en yakın kalıpla eşlenebilir.

### B — Kalıp Sayısallaştırma
Toplu PDF yükleme → otomatik analiz/sınıflandırma (yeşil/sarı/kırmızı) → otomatik DXF + ölçek kalibrasyonu + metadata → hafif insan onayı → kütüphaneye kayıt.

### C — DXF Kütüphanesi
Her kalıp: ürün tipi, parça listesi (kol/ön/arka/kapüşon...), beden aralığı, etiketler, DXF + önizleme. Aranabilir arşiv.

### D — Atölye İş Akışı
Tasarım kartı + ilişkili DXF → kalıpçıya/tasarımcıya atama → durum takibi → dosya teslimi/bildirim. Teknik risk düşük.

---

## 6. Fazlı Yol Haritası

### Faz 1 — Çekirdek (paralel iki kol)
**Kol 1: PDF→DXF üretim hattı**
- FastAPI servisini prototipten üretime taşı (hata yönetimi, loglama, kuyruk).
- Sınıflandırma + güven skoru (yeşil/sarı/kırmızı).
- Ölçek kalibrasyonu (kontrol karesi otomatik ölçümü).
- Metadata çıkarımı (parça adı, beden, adet).
- Vue: toplu yükleme + DXF önizleme + onayla/reddet kuyruğu.

**Kol 2: AI konsept stüdyosu (hızlı kazanım)**
- Model-agnostik görsel üretim soyutlaması.
- Tarif formu → konsept üretimi → tasarım kartı kaydı.
- Tek sağlayıcıyla başla, soyutlama sayesinde sonradan değiştir.

### Faz 2 — Bağlama
- DXF kütüphanesi (arama, filtre, parça/beden metadatası).
- Atölye iş akışı (atama, durum, teslim).
- Tasarım kartı ↔ en yakın kalıp eşleme (manuel seçim destekli).

### Faz 3 — İleri (kütüphane olgunlaşınca)
- Beden ayrımı denemesi (en içteki/dıştaki kontur mantığı + manuel onay).
- Parametrik varyant türetme (mevcut DXF'ten stil/beden türevleri) — Ar-Ge.
- AI konsept → kütüphane otomatik öneri eşleme iyileştirmesi.

---

## 7. İlk Sprint İçin Somut İşler

1. **FastAPI servisi** prototipten alınıp Docker'a konur, kuyruk entegrasyonu yapılır.
2. **Sınıflandırma mantığı** yazılır: her PDF için vektör mü / ızgara okundu mu / kalıp rengi ayrıştı mı → güven skoru.
3. **Ölçek doğrulama** eklenir: kontrol karesi ölçülür, DXF mm'ye kalibre edilir, sapma raporlanır.
4. **Vue onay ekranı**: yüklenen PDF + üretilen DXF önizlemesi yan yana, onayla/reddet.
5. **Görsel AI POC**: bir-iki sağlayıcı (örn. ucuz model + Higgsfield) tek bir tarifle test edilir, kalite/maliyet kıyaslanır.
6. **Veri modeli**: `patterns`, `pattern_parts`, `design_cards`, `conversion_jobs` tabloları tasarlanır.

---

## 8. Açık Riskler ve İzlenecekler

| Risk | Etki | Azaltma |
|------|------|---------|
| Bazı PDF'ler farklı renk şeması | Renk filtresi tutmaz | Sınıflandırmada sarı kovaya düşür, operatör seçer |
| İç içe beden ayrımı | Kalıpçı manuel ayırmak zorunda | Faz 3'e ertelendi, baştan beklenti yönetildi |
| AI model fiyat/erişim değişimi | Maliyet/kesinti | Model-agnostik soyutlama |
| Ölçek sapması | Kesimde fire | Otomatik kontrol karesi doğrulaması |
| AI telif/türev | Hukuki | Kendi desen havuzu, marka fotoğrafı kullanma |

---

## 9. Veri Modeli ve "AI → Kalıp → DXF" Zinciri

### 9.1 Kritik kavram: AI doğrudan DXF üretmez

Üç ayrı katman vardır ve aralarında **otomatik dönüşüm yoktur**:

- **AI çıktısı** = piksel görsel (desen, renk, stil). Ölçü/dikiş payı/geometri içermez.
- **DXF** = matematiksel, milimetrik, dikilebilir geometri. Görselden değil, geometriden üretilir.
- **Köprü** = sayısallaştırılmış DXF kütüphaneniz.

Doğru zincir:

```
AI konsept görseli  →  metadata ile kütüphaneden kalıp eşleme  →  mevcut DXF  →  kalıpçı uyarlar
```

DXF, AI'dan değil **kütüphaneden** gelir. Bu yüzden gereken yapı **Seviye 1**'dir:
metadata veritabanında (eşleme buradan yapılır), geometri (DXF) dosyada.
Geometriyi VT'ye koymak (Seviye 2) eşlemeye katkı sağlamaz; yalnızca parametrik
türetme (Faz 3 Ar-Ge) gerekseydi anlamlı olurdu.

### 9.2 İki akış, tek veri modeli

- **Akış X — Önce kalıp seç, sonra AI üret:** kullanıcı kütüphaneden kalıp seçer →
  AI o ürüne desen/renk konsepti üretir → tasarım kartı baştan kalıba bağlı doğar.
- **Akış Y — Önce AI konsept, sonra kalıp öner:** kullanıcı tarif girer → AI görsel üretir →
  sistem metadataya (şimdilik **ürün tipi**) göre uygun kalıpları önerir → kullanıcı seçer.

Her ikisi de aynı `design_cards` tablosuyla taşınır; fark `source` alanı ve
`pattern_id`'nin ne zaman dolduğudur (X'te baştan, Y'de eşleme sonrası).

### 9.3 Kavramsal tablolar

**patterns** — kalıp ana kaydı
- product_type (tulum/ceket... — birincil filtre alanı)
- size_range (örn. "116-122-128-134")
- vendor / collection
- scale_verified (bool), scale_deviation_mm
- dxf_path, pdf_path, preview_image_path
- status (taslak/onaylı/reddedildi)

**pattern_parts** — kalıbın parçaları (patterns'a bire-çok)
- pattern_id (FK)
- part_name (kol/ön/arka/kapüşon/yaka/manşet...)
- quantity (kaç adet)
- size_range

**pattern_tags** — esnek etiketleme (ileride filtre genişletme için)
- pattern_id (FK), tag

**design_cards** — AI konseptleri
- source (akış X / akış Y)
- prompt / tarif
- generated_images (üretilen görseller)
- product_type, target_size
- pattern_id (FK, nullable — Y'de eşleme sonrası dolar)
- status

**conversion_jobs** — PDF→DXF işleri (onay kuyruğunu besler)
- source_pdf_path
- classification (yeşil/sarı/kırmızı)
- confidence_score
- error_report
- output_dxf_path
- reviewed_by, reviewed_at

### 9.4 Bu yapı her iki akışı nasıl taşır

- **Akış X:** `design_cards.pattern_id` kayıt anında doludur (kalıp önce seçildi).
- **Akış Y:** önce `design_cards` oluşur (`pattern_id` null), kullanıcı önerilen kalıbı
  seçince `pattern_id` atanır.
- **Filtreleme:** şimdilik `patterns.product_type` üzerinden; `pattern_tags` ve
  `pattern_parts` ileride filtre yelpazesini genişletmek için hazırdır.

### 9.5 Neden geometri VT'de değil

512+ segment × yüzlerce dosya = sorgulaması zor, faydası düşük bir yük.
"Model üretiminde seçim" bir **metadata sorusudur**, geometri sorusu değil.
Geometri dosyada (DXF) kalır; VT yalnızca seçilebilir/aranabilir alanları tutar.
İhtiyaç (parametrik türetme) kanıtlanırsa Seviye 2'ye geçiş sonradan yapılabilir.
