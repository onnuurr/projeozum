# Navigasyon / Bilgi Mimarisi Denetimi

**Durum:** Taslak — salt-okuma bulgular, hiçbir kod/route/menü değişikliği içermiyor.
**Tarih:** 2026-09-22
**Kapsam:** Admin panelin ana navigasyonu (`superadmin_menus` tablosu → `MenuTreeBuilder` →
`useAppShell.js` → `MainNav.vue`/`ActionBar.vue`). Tenant Portal (`Modules/Tenant/.../Portal/`)
kapsam dışı — ayrı bir kabuk ve ayrı bir menü kaynağı kullanıyor.

> **Not:** Bu doküman canlı veritabanı sorgulanarak (`php artisan tinker`, salt-okuma) hazırlandı,
> sadece kod/seeder dosyası okunarak değil — aşağıdaki sayılar gerçek çalışan sistemi yansıtıyor.
> `Modules/Superadmin/database/seeders/MenuSeeder.php` dosyasının kod tanımı ile canlı DB arasında
> fark var (bkz. §5), bu yüzden sadece seeder dosyasına bakarak çıkarılacak sonuçlar yanıltıcı olur.

## Özet

| Metrik | Değer |
|---|---|
| Toplam menü kaydı (canlı DB) | 64 |
| Kök (üst seviye) menü | 11 |
| Gerçek derinlik | **1** (kök + 1 alt seviye — bileşenler daha derini destekliyor ama veri hiç kullanmıyor) |
| Tamamen çalışmayan kayıt (`resolveTo()` → null, tıklanamaz `#`) | 11 |
| **Sessizce kırık** kayıt (URL var ama route yok, tıklanır ama 404 verir) | **2** |
| Aynı sayfaya giden 2 farklı menü etiketi | 1 çift |
| Tutarsız/çevrilmemiş etiket | 5 |

---

## 1. Açıkça ölü linkler (11 kayıt, `#` olarak render olur)

`Menu::resolveTo()` hem `route_name` hem `url` boşsa `null` döner, `MainNav.vue`/`ActionBar.vue`
bunu `href="#"` yapar — tıklanınca hiçbir şey olmaz.

| Kök | Ölü alt öğe(ler) |
|---|---|
| **Raporlar** (kökün kendisi de ölü) | Üretim Raporu, Stok Raporu, Satış Raporu, Maliyet Analizi |
| Siparişler | Yeni Sipariş, Teklifler, İadeler |
| İlişkiler | Müşteriler, Partnerler |
| Stok | Sayım |

**Öneri (kullanıcı onayına açık):** Her biri için üç seçenek var — (a) gerçek sayfayı/route'u
inşa et, (b) `Menus.vue` admin ekranından `is_active=false` yap (nav'dan kaybolur, kayıt DB'de
kalır — ileride tekrar açılabilir), (c) kalıcı olarak sil. "Raporlar" kökü tamamen boş bir kabuk
olduğu için muhtemelen (b) veya (c) en uygunu; diğerleri gerçek özellik olarak inşa edilmeyi
bekliyor olabilir (ör. "Yeni Sipariş" akışı gerçekten yoksa bu, panelin kendisinde eksik bir
işlev — sadece bir menü sorunu değil).

## 2. Sessizce kırık linkler (2 kayıt) — en yüksek öncelikli bulgu

Bunlar `url` alanına sahip olduğu için `resolveTo()` onları "canlı" sanıyor ve normal bir link
gibi render ediyor — kullanıcı tıklar, sayfa **404 verir**. Açık `#` linklerden daha kötü çünkü
kullanıcıya "bu çalışıyor" izlenimi veriyor.

| Menü öğesi | Kök | Menüdeki hedef | Gerçek route | Durum |
|---|---|---|---|---|
| **Tedarikçiler** | İlişkiler | `/suppliers` | `atelier/fason-suppliers` (`Modules/Atelier/routes/web.php`) | 404 |
| **Kullanıcılar** | İlişkiler | `/users` | `superadmin/users` (`Modules/Superadmin`) | 404 |

**Öneri:** İkisi de mekanik bir düzeltme — `Menus.vue` üzerinden (veya doğrudan `route_name`
alanına `atelier.fason-suppliers.index` / `superadmin.users.index` yazılarak) URL'leri gerçek
route'a taşımak. `route_name` kullanmak burada `url`'den daha güvenli, çünkü `Menu::resolveTo()`
sadece `route_name` verilince `Route::has()` ile doğruluyor — `url` alanı hiç doğrulanmıyor, aynı
hatanın tekrarını önlemek için yeni menü kayıtları eklenirken **`url` yerine `route_name` tercih
edilmeli** (genel bir kural olarak `admin-page-ui` skill'ine eklenebilir).

## 3. Aynı sayfaya giden 2 farklı menü etiketi

**Atölye** kökünün altında hem **"Kalıp Kütüphanesi"** (id 111) hem **"Atelier Patterns"**
(id 127) `/atelier/patterns`'e gidiyor — aynı sayfa için iki farklı, biri Türkçe biri İngilizce
etiketli menü satırı. Kullanıcı bunların farklı sayfalar olduğunu düşünüp ikisini de deneyebilir.

**Öneri:** "Atelier Patterns" (id 127) kaldırılsın, "Kalıp Kütüphanesi" (id 111, zaten doğru
Türkçe etiketli) tek kayıt olarak kalsın.

## 4. Tutarsız / çevrilmemiş etiketler

Atölye kökü altında ve Patternengine kökünde, geri kalan tüm menünün Türkçe kullanım diline uymayan,
ham/teknik görünümlü etiketler var — muhtemelen `Menus.vue` üzerinden hızlıca eklenip düzgün
Türkçe etiket verilmeden bırakılmış:

- "Atelier Assignments", "Atelier Boms", "Atelier Production Orders" (Atölye kökü altında —
  aynı kökteki "Taslaklar", "İnceleme Bekleyenler", "Onaylanmış" gibi düzgün Türkçe etiketlerle
  yan yana duruyor)
- "Patternengine" (kök) ve "Patternengine Profiles" (alt öğe) — modül adı doğrudan etikete sızmış

**Öneri:** Bu 5 kaydın etiketleri `Menus.vue` üzerinden düzeltilsin (ör. "Atölye Görev Atamaları",
"Malzeme Reçeteleri (BOM)", "Üretim Emirleri", "Kalıp Motoru", "Kalıp Profilleri"). Mekanik, düşük
riskli bir düzeltme — kod değişikliği gerektirmiyor, sadece DB'deki `label` alanı.

## 5. Seeder dosyası ↔ canlı DB uyumsuzluğu (geliştirici tarafı risk)

`MenuSeeder.php`'nin `definition()` metodu hâlâ şu kökleri/alt öğeleri içeriyor, ama bunlar **canlı
DB'de hiç yok** (muhtemelen daha önce `Menus.vue` üzerinden elle silinmiş, ama seeder dosyası
güncellenmemiş):

- Kök **"Takvim"** (3 alt öğesiyle: Üretim Takvimi, Toplantılar, Tatil Günleri) — hiçbiri canlıda yok
- Kök **"Sevkiyat"** (4 alt öğesiyle: Sevkiyat Listesi, Yeni Sevkiyat, Kargo Takibi, Adresler) — hiçbiri canlıda yok
- "Katalog" kökü altında **"Koleksiyonlar"** alt öğesi — canlıda yok

Bunlar şu an kullanıcıya görünmüyor, yani **aktif bir karmaşa kaynağı değil**. Ama `MenuSeeder`
idempotent (`firstOrCreate`) olduğu için, seeder ileride yeni bir ortamda (staging, test, felaket
kurtarma) tekrar çalıştırılırsa bu 2 tamamen ölü kök + 1 ölü alt öğe sessizce geri gelir.

**Öneri:** `MenuSeeder.php`'den "Takvim", "Sevkiyat" köklerini ve "Koleksiyonlar" alt öğesini
kaldırmak — mevcut davranışı değiştirmiyor (zaten canlıda yoklar), sadece gelecekte aynı sorunun
başka bir ortamda tekrar oluşmasını engelliyor. Düşük riskli, tek dosyalık bir temizlik.

## 6. Mobilde alt menü erişilemiyor

`MainNav.vue`'nun mobil menüsü (`Offcanvas`, satır 160-176) sadece **kök seviye** öğeleri
(`menuItems`) render ediyor — masaüstünde `ActionBar.vue`'nun gösterdiği alt öğeler (her kökün
çocukları) mobilde **hiçbir yerde** görünmüyor. Örnek: mobil kullanıcı "Atölye" kökünü tıklayıp
`/atelier` panosuna gidebilir, ama "Taslaklar", "Kalıp Kütüphanesi", "Üretim Emirleri" gibi 8 alt
sayfaya mobilden ulaşmanın **hiçbir yolu yok** (derin link paylaşılmadıkça).

**Öneri:** `Offcanvas` içine, aktif kökün altına açılır/genişleyen bir alt-liste eklenmesi (basit
bir accordion) — `useAppShell.js`'in zaten hesapladığı `navItems`'ı mobilde de tüketecek şekilde.
Bu bir kod değişikliği, Parça 1'in kapsamına eklenebilecek küçük, kendi içinde tamamlanmış bir iş.

## 7. Menü derinliği: aslında sorun değil (önceki değerlendirmeyi düzeltiyor)

Bu denetimin ilk taslağında (statik kod okumasıyla) "4 seviyeli iç içe flyout, kaybolmak kolay"
denmişti. **Canlı veriyle doğrulandı: gerçek derinlik sadece 1** (kök → 1 alt seviye). `ActionBar.vue`
bileşeni teknik olarak daha fazla seviyeyi render edebilir ama hiçbir menü kaydı bunu kullanmıyor.
**Bu madde aktif bir sorun değil** — düzeltme gerekmez, sadece kayıt altına alınıyor.

## 8. Modül sınırı ↔ menü gruplama tutarlılığı

- **"İlişkiler" kökü 3 farklı modülü tek başlık altında karıştırıyor:** Tenant modülü
  (Tenant'lar, Başvurular, Tenant Tipleri), Atölye modülü (Tedarikçiler → aslında
  `fason-suppliers`, Atölye'ye ait), Superadmin modülü (Kullanıcılar). Genel/belirsiz
  "İlişkiler" adı bu karışımı gizliyor — bir kullanıcı "tedarikçi" ararken bunun Atölye'ye ait
  olduğunu tahmin etmesi zor.
- **"Ürünler" ve "Stok" ayrı köklerde ama ikisi de Product modülü** — savunulabilir bir ayrım
  (katalog vs. stok operasyonları), yanlış değil ama bilinçli bir tasarım kararı olarak
  onaylanmalı, tesadüfi değil.
- Geri kalan kökler (Katalog→Product, Siparişler→Product, Atölye→Atelier, Finans→Finance,
  Creative Studio→Creative, Superadmin→Superadmin, Patternengine→PatternEngine) modül
  sınırlarıyla temiz eşleşiyor.

**Öneri (IA derinlik/gruplama değişikliği — sert onay kapısına tabi):** "İlişkiler" kökünün
"Tedarikçiler" ve "Kullanıcılar" öğelerini kendi asıl modüllerinin altına taşımak (ör. Atölye
kökünün altına "Fason Tedarikçiler", Superadmin kökünün altına "Kullanıcılar") ve "İlişkiler"i
sadece gerçek Tenant-ilişkili öğelerle sınırlamak (belki adını da "Bayiler"/"Tenant Yönetimi"
gibi daha açık bir isme çevirmek). **Bu, alışkanlık/bookmark kıran bir değişiklik — plandaki sert
onay kuralı gereği kullanıcı onayı olmadan uygulanmayacak.**

## 9. Geliştirici tarafı: yetim `AppLayoutClassic.vue`

`resources/js/Layouts/AppLayoutClassic.vue`, `AppLayout.vue` ile ~%98 aynı ama **hiçbir gerçek
sayfa onu import etmiyor** (doğrulandı — sadece kendi dosyasına ve `SaasKit/` referans
prototipindeki bir yorum satırına değiniliyor). Kullanıcı-yüzü navigasyonla ilgisi yok ama
geliştirici tarafında "hangi layout'u kullanmalıyım" karmaşasının kaynağı.

**Öneri:** Dosyayı silmek (düşük risk, git geçmişinde kalır) — Parça 1'in bir parçası olarak
ayrıca ele alınabilir, IA onay kapısına tabi değil.

---

## Önerilen sıralama

Düşük riskli, hızlı onaylanabilir maddeler (madde 1-6, 9) bir arada; IA/gruplama değişikliği
(madde 8) ayrı ve daha temkinli ele alınabilir:

1. **Hemen yapılabilir (mekanik, düşük risk):** §2 (2 kırık link → `route_name`'e taşı), §3
   (yinelenen "Atelier Patterns" kaydını sil), §4 (5 etiketi düzelt), §5 (seeder'dan Takvim/Sevkiyat/
   Koleksiyonlar'ı temizle), §9 (`AppLayoutClassic.vue`'yu sil) — hepsi `Menus.vue` admin ekranı
   veya tek dosyalık düzenlemelerle yapılabilir, geri alınabilir.
2. **Karar gerektirir ama düşük riskli:** §1 (11 ölü kayıt için build/gizle/sil kararı, madde
   madde) — kullanıcıya sorulacak.
3. **Kod değişikliği, orta kapsam:** §6 (mobil alt menü erişimi) — Parça 1'in bir sonraki
   adımına eklenebilir.
4. **Sert onay kapısı:** §8 ("İlişkiler" kökünün yeniden gruplanması) — sadece kullanıcı
   açıkça onaylarsa yapılır, alışkanlık/bookmark kırıyor.
