# Proje İnceleme Raporu ve Gelişim Planı

**Hazırlanma tarihi:** 2026-07-11
**Kapsam:** Tüm kod tabanı (6 modül, ~470 modül PHP dosyası, 151 Vue bileşeni, 111 migration, 103 test)
**Amaç:** Genel durum tespiti + projenin gelişimini etkileyecek somut bulgular + fazlı iyileştirme planı

---

## 1. Genel Bakış

**Ne bu proje:** Çok kiracılı (multi-tenant) bir **tekstil B2B + üretim + AI tasarım platformu**.
Laravel 12 (PHP 8.2) çekirdek, Inertia 2 + Vue 3 arayüz, `nwidart/laravel-modules` ile modüler
mimari, ayrıca PDF→DXF dönüşümü için harici bir **Python/FastAPI** mikroservisi.

**Teknoloji yığını:** Laravel 12 · Inertia/Vue 3 · Sanctum · Spatie Permission · Reverb (WebSocket)
· Predis/Redis · maatwebsite/excel · Tailwind · Pinia.

**Modüller:**

| Modül | Sorumluluk | Olgunluk |
|-------|-----------|----------|
| **Tenant** | Çok-kiracılık, pazaryeri entegrasyonları (Trendyol/N11/Hepsiburada/Çiçeksepeti), kredi defteri, self-servis kullanıcı yönetimi | En büyük (122 dosya), driver/contract deseni olgun |
| **Product** | Katalog, varyant, sipariş, stok, pazaryeri listeleme | Olgun (112 dosya, 16 test) |
| **Creative** | AI görsel üretimi (manken, try-on, sahne) — Gemini + Fal + Mock sürücüler | Olgun soyutlama, test ince |
| **Atelier** | Kalıp sayısallaştırma (PDF→DXF), BOM, üretim emirleri | İyi test edilmiş (24 test), Python entegrasyonu |
| **Finance** | Fatura, e-Fatura (Trendyol), banka ekstresi (MT940/CSV), mutabakat | Kritik iş mantığı, test ince (4) |
| **Superadmin** | Dinamik menü, ayarlar | Küçük, sağlam |

---

## 2. Güçlü Yönler

- **Örnek alınacak servis katmanı disiplini** — Contract + Driver + Mock deseni her yerde tutarlı
  (AI sağlayıcıları, pazaryerleri, PDF dönüştürücü). Model-agnostik soyutlama yol haritasındaki
  plana birebir uyuyor.
- **DB bakım disiplini gerçekten uygulanmış** — `CLAUDE.md` kuralları soyut değil:
  `routes/console.php`'de retention zamanlamaları, `schema:audit` komutu, `Prunable` modeller mevcut.
- **Güvenlik temelleri sağlam** — pazaryeri kimlik bilgileri `encrypted` cast ile şifreli
  (`Modules/Tenant/Models/TenantMarketplaceCredential.php`), webhook doğrulayıcı var, hata loglama
  merkezi (`app/Logging/ErrorLogger`).
- **Zengin dokümantasyon** — `docs/superpowers/` altında plan + tasarım spec'leri her özellik için mevcut.

---

## 3. Bulgular (Öncelik Sıralı)

### 🔴 K1 — Tenant izolasyonu "emniyet ağı" bağlı ama hiçbir modele takılı değil

`app/Models/Traits/BelongsToTenant.php` global scope trait'i **var** ve `current_tenant_id` her web
isteğinde `app/Http/Middleware/SetTenantContext.php` ile container'a **bağlanıyor**. Ama bu trait'i
kullanan **tek bir model yok** (`MarketplaceSale`, `TenantPriceList`, `TenantProductAccess` vb. hepsi
düz `Model`). İzolasyon tamamen elle yazılmış `where('tenant_id')` filtrelerine (~24 yer) ve route
seviyesi `tenant.owns` middleware'ine (`EnforceTenantOwnership`) dayanıyor.

**Risk:** Tek bir unutulan `where` → kiracılar arası veri sızıntısı. B2B pazaryeri verisinde bu en
yüksek etkili risk.

**Not / nüans:** Context superadmin isteklerinde de bağlanıyor; global scope naif uygulanırsa
superadmin'in kiracılar-arası görünümlerini bozar. Çözümde superadmin bypass gerekir
(ör. superadmin için `current_tenant_id` bind etme veya `scopeWithoutTenantScope()` kullan).

### 🔴 K2 — `App\Models\Partner` kırık referans (latent bug)

`app/Models/User.php` `use App\Models\Partner;` yapıp `partner()` ilişkisinde `belongsTo(Partner::class)`
döndürüyor — ama **`Partner` sınıfı projede hiç yok**. `$user->partner` çağıran her kod "Class not found"
ile patlar. İronik biçimde bu tam olarak `CLAUDE.md`'nin uyardığı "özellik kaldırıldı, kalıntı kaldı"
durumu. `partner_id` kolonu ve `isPartner()` hâlâ kullanımda.

**Karar gerekiyor:** Ya `Partner` modeli oluşturulacak ya da `partner()` ilişkisi + `partner_id` kolonu
ileri-tarihli migration ile kaldırılacak (CLAUDE.md deseni: `down()` gerçek ters işlem yapmalı).

### 🟠 K3 — CI/CD boru hattı yok

`.github/workflows/` boş. 103 test, `pint`, ve `schema:audit` aracı var ama hiçbir PR'da otomatik
çalışmıyor. Bu kadar katı DB disiplini olan bir projede otomatik kapı olmaması en büyük süreç açığı.
(`session-start-hook` skill'i bunun için birebir uygun.)

### 🟠 K4 — Statik analiz yok

`phpstan/larastan/rector` yok. 470+ dosyalık modüler kod tabanı için tip güvenliği ağı eksik —
K1/K2 gibi hatalar statik analizle CI'da yakalanırdı.

### 🟡 K5 — Test kapsamı dengesiz

Atelier (24) ve Product (16) iyi; ama **para-kritik** yerler ince: Finance (4 — MT940 ayrıştırma,
mutabakat, e-Fatura), pazaryeri sipariş eşleme/webhook doğrulama. Yanlış hesap = gerçek para kaybı.

### 🟡 K6 — README stok Laravel şablonu

6 modül + Python mikroservis + Reverb olan bir sistemde onboarding yok. `docs/` iyi ama kök README
kurulum/mimariyi anlatmıyor.

### ⚪ K7 — Küçükler

- Pint kurulu ama `pint.json` yok (stil kuralı tanımsız).
- Portal domain `env()` fallback'i ile okunuyor (`routes/web.php`) — `config:cache` sonrası `env()`
  null döner; her zaman `config()` üzerinden okunmalı.

---

## 4. Öncelikli Plan

### Faz 0 — Emniyet & Kapı (1–2 gün, en yüksek getiri)
1. **K2**: `Partner` kararını ver — ya modeli oluştur ya da `partner()` ilişkisini + kolonu
   ileri-tarihli migration ile kaldır (CLAUDE.md deseni: `down()` gerçek ters işlem). Hızlı, riski kapatır.
2. **K3**: GitHub Actions workflow — `composer test` + `pint --test` + `schema:audit`.
3. **K7**: `pint.json` ekle (Laravel preset); `env()` → `config()` düzeltmesi.

### Faz 1 — Tenant izolasyonunu sağlamlaştır (K1, 2–3 gün)
4. Tenant'a ait tüm modellere `BelongsToTenant` trait'ini uygula. **Kritik nüans:** superadmin
   bypass'ı olmadan global scope superadmin görünümlerini bozar.
5. Her tenant-owned tabloya kapsayan izolasyon feature testi ekle (kiracı A, kiracı B'nin kaydını
   göremez).

### Faz 2 — Kalite ağı (K4, K5)
6. `larastan` ekle, `phpstan.neon` (level 5'ten başla), CI'ya bağla.
7. Finance ve Marketplace için hedefli testler: MT940 parser edge-case'leri, mutabakat eşleştirme,
   Trendyol webhook imza doğrulama, sipariş→satır eşleme.

### Faz 3 — Onboarding & süreklilik (K6)
8. Proje README'sini yaz (mimari diyagram, modül haritası, Python servis kurulumu, `composer dev` akışı).

---

## 5. Özet

Kod tabanı mimari olarak **olgun ve disiplinli**; asıl açıklar süreç ve emniyet-ağı katmanında:
- En kritik iki teknik borç **K1 (kullanılmayan tenant scope)** ve **K2 (Partner kırık referans)** —
  ikisi de somut ve düzeltmeye hazır.
- En yüksek getirili süreç iyileştirmesi **K3 (CI/CD)**.
- Orta vadede **K4 (statik analiz)** ve **K5 (para-kritik test kapsamı)** teknik borcu sabitler.
