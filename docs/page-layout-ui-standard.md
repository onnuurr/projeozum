# Sayfa Düzeni ve UX Standardı

**Durum:** Onaylandı, uygulama aşamalı (bkz. §6). **Kapsam:** admin panel sayfaları
(`resources/js/Pages/`, `Modules/*/Resources/assets/js/Pages/`) — Tenant Portal
müşteri sayfaları (`Modules/Tenant/.../Pages/Portal/**`) kendi shell'ine sahip,
bu standardın dışında.

## Neden bu doküman var

Bu bir SaaS ürünü — sayfa düzeni ve etkileşim kalıplarının **her modülde aynı**
olması kullanıcı deneyiminin temeli. Superadmin modülünü yeni component kit'ine
geçirirken (bkz. `.claude/skills/admin-page-ui/SKILL.md`) yapılan denetimde şu
ortaya çıktı: breadcrumb zaten tutarlı ama sayfa başlığı 48 ayrı dosyada
kopyala-yapıştır CSS olarak duruyor, **kaydet butonu için 4 farklı yerleşim**
kullanılıyor, ve hiçbir buton component'i (sadece ham `.btn` class'ları)
kullanılmıyor. Bu doküman, denetimde bulunanları temel alarak **tek bir
standart** tanımlıyor — mevcut iyi kalıpları kodlayıp, çelişen kalıplardan
birini seçiyor.

---

## 1. Breadcrumb

**Zaten de-facto standart — bu bölüm sadece kodluyor, değiştirmiyor.**

- Sayfa kökünün **ilk çocuğu**, her şeyden önce gelir (nav/page-header'dan önce).
- `<Breadcrumb :items="[...]">` — `resources/js/Components/Breadcrumb.vue`.
- İlk öğe her zaman `{ label: 'Ana Sayfa', to: '/workflow', icon: 'home' }`
  (60 dosyada birebir aynı, hiç sapma yok — böyle kalsın).
- 2-4 seviye: liste sayfaları 2-3, detay/form sayfaları 3-4 (son öğe `to`'suz,
  tıklanamaz "şu an buradasın" etiketi).
- Ara seviyeler bazen tıklanamaz bölüm etiketi olabilir (`{ label: 'İlişkiler' }`,
  `to` yok) — bu geçerli bir kullanım, zorunlu değil ama yasak da değil.

**Kapatılması gereken tek boşluk:** Her modülün kök/Dashboard sayfası breadcrumb
taşımalı. Şu an `Superadmin/Dashboard.vue`, `Finance/Dashboard.vue`,
`Atelier/Dashboard.vue` taşıyor ama `resources/js/Pages/Dashboard.vue` (app-level
ana sayfa) ve `Tenant/.../Portal/Dashboard.vue` taşımıyor — tutarsız. Yeni/
düzenlenen her Dashboard sayfası breadcrumb almalı (Portal, kendi shell'i olduğu
için bu kuralın dışında, bkz. yukarı).

## 2. Sayfa başlığı (Page Header)

**Hedef durum zaten var (`PageHeader.vue`), ama sadece Superadmin'de kullanılıyor.**
48 dosyada aynı ham markup + aynı `.page-header`/`.page-title`/`.page-subtitle`/
`.header-actions` CSS'i kopyalanmış durumda.

**Standart:** Liste/detay/ayarlar tipi sayfalar için tek doğru yol
`resources/js/Components/PageHeader.vue`:

```vue
<PageHeader title="Ürün Kategorileri" subtitle="Katalog kategori ağacını yönet">
  <template #actions>
    <Button variant="primary" with-icon @click="openNew">
      <template #leading><Plus :size="13" /></template>
      Yeni Kategori
    </Button>
  </template>
</PageHeader>
```

- `badge` prop **opsiyonel** — sadece sayfanın gerçek bir bağlam/kimlik rozetine
  ihtiyacı varsa (ör. "Süper Admin"). Her modüle otomatik rozet eklenmez.
- `#actions` slot'u sağ üstte — "+ Yeni X" gibi liste-sayfası aksiyonları,
  durum-geçiş butonları (Planla/Tamamla/İptal gibi), ve §3'te tanımlanan
  tam-sayfa-form Kaydet/İptal çifti burada yaşar.

### Tam-sayfa form istisnası: "Form Topbar"

`ProductForm.vue` gibi büyük, çok bölümlü, kendi başına bir sayfa olan formlar
(genel liste/detay sayfası değil) **ayrı, sabitlenmiş (sticky) bir başlık
çubuğu** kullanır — geri oku + başlık/alt başlık + sağda İptal/Kaydet:

```
[← ] Ürünü Düzenle                                    [İptal] [Kaydet]
     SKU-1234
```

Bu şu an `ProductForm.vue`'ya özel ham `.form-topbar` markup'ı olarak var. Kısa
vadede **bu deseni bozma** — geçerli ve doğru bir ayrım (uzun form ≠ liste
sayfası). Orta vadede (`PageHeader.vue`'ya `backHref` + `sticky` prop'u eklenip
bu iki desen tek component'te birleştirilebilir — bkz. §6 Adım 2) ama bu
dokümanın onayladığı **yerleşim kararı** zaten doğru, sadece component'e
taşınması gerekiyor.

## 3. Kaydet / İptal butonu — iki kanonik yerleşim

Denetimde 4 farklı yerleşim bulundu (modal footer / sayfa-içi form kartı /
sticky topbar / header-actions). **Tek bir evrensel konum gerçekçi değil**
(tam-sayfa form ile hızlı ekle/düzenle farklı ihtiyaçlar) — bunun yerine
**iki sanctioned şekil**, üçüncüsü yasak:

| Sayfa tipi | Kaydet/İptal nerede | Örnek |
|---|---|---|
| **Drawer içi CRUD** (liste sayfasında "+ Yeni"/"Düzenle" formu) | `AppDrawer`'ın `#footer` slot'u — İptal solda (`ghost`), Kaydet sağda (`primary`) | `Brands.vue`, `Categories.vue` (CategoryFormDrawer) |
| **Tam-sayfa form** (kendi route'u olan, uzun/çok bölümlü form) | §2'deki "Form Topbar" — sağ üstte İptal + Kaydet, sabit (sticky) | `ProductForm.vue:28-38` |

> **2026-09-09 güncellemesi:** Bu satır önceden "Modal içi CRUD" idi ve
> `AppModal` kullanıyordu (bkz. eski örnek `TenantTypes.vue:110-116`). Kullanıcı
> onayıyla standart **drawer'a** çevrildi — "+ Yeni"/"Düzenle" gibi tek-varlık
> ekle/düzenle formları artık `AppModal` değil `AppDrawer` kullanır. Bu geçiş
> aynı gün Tenant/Atelier/Finance/Superadmin/Product/Creative modüllerindeki
> ilgili tüm sayfalarda uygulandı (bkz. §6). `AppModal` hâlâ geçerli — ama
> yalnızca gerçek CRUD-form OLMAYAN diyaloglar için: salt-okuma onay/red
> diyaloğu (ör. `TenantApplications.vue` başvuru reddi, `OrderDetail.vue`
> durum onayı), silme onayı (ör. `Settings.vue` rol silme), veya bir aksiyonu
> tetikleyen ama kalıcı bir varlık oluşturmayan diyaloglar (ör.
> `CreativeTemplates.vue`'de AI üretim tetikleyici, `Conversions.vue`'de
> inceleme/karar diyaloğu). Şüpheli durumda: formda kaydedilen alanlar var ve
> İptal/Kaydet çifti mi var → drawer; yoksa (onay/bilgi/tetikleyici) → modal.

**Yasak:** "sayfa-içi form kartı" (header'dan bağımsız, formun kendi altında
gömülü Kaydet butonu — ör. `Materials.vue:59-61`, `BankAccounts.vue:65`) ve
"header-actions içinde gizli 'Kaydet' aksiyonu" (ör. bir liste sayfasının üst
header'ında kaydet gibi davranan ama aslında farklı bir işlem yapan buton) yeni
sayfalarda **kullanılmaz** — mevcut sayfalar §6'ya göre kademeli taşınır.

### Buton tipi

Her iki yerleşimde de:
- Component her zaman `resources/js/Components/Button.vue` (ham
  `<button class="btn btn-primary">` yazılmaz — bkz. `admin-page-ui` skill §"Buton
  konvansiyonu").
- İptal → `variant="ghost"`. Kaydet → `variant="primary"`.
- **Yükleniyor durumu → `:loading="form.processing"` prop'u, metin
  değiştirme değil.** Denetimde bulunan gerçek çelişki: mevcut kod hemen hemen
  hiçbir yerde spinner kullanmıyor, "Kaydediliyor…" gibi metin değişimi veya
  sadece `:disabled` kullanıyor (spinner state'i hiç yok). Bu standart, zaten
  Superadmin'de kurulu olan `Button.vue`'nun dahili spinner'ını (`loading`
  prop'u) **tüm modüller için** doğru davranış ilan ediyor — metin değiştirme
  deseni terk ediliyor. Buton metni sabit kalır ("Kaydet"), sadece spinner
  görünür/kaybolur.
- Metin: sabit bir kelime zorunlu değil — "Kaydet" (genel), "{{ editing ?
  'Güncelle' : 'Ekle' }}" (oluştur/düzenle ayrımı olan sayfalar) gibi anlamlı
  fiil+nesne kombinasyonları kabul; icat edilmiş yeni varyasyonlar
  ("Kaydet ve Devam Et" gibi) eklenmeden önce ayrıca tartışılmalı — şu an
  hiçbir sayfada yok, sessizce eklenmesin.

## 4. Diğer "hangi UI şekli → hangi component" kuralları

Bu zaten `.claude/skills/admin-page-ui/SKILL.md`'de kodlu (StatWidget/
StatusIndicator/ProgressBar/Badge/Card/QuickActionWidget/DataTable/EmptyState
tablosu, ikon kuralı, renk token kuralı) — tekrar etmiyoruz, o skill bu
dokümanın operasyonel (Claude Code oturumları için) karşılığı.

## 5. Kapsam dışı / bilinçli sapmalar

- **Tenant Portal** (`Modules/Tenant/.../Pages/Portal/**`) — müşteri yüzü,
  kendi tasarım dili olabilir, bu standart admin paneli için.
- **`Modules/Product/.../Pages/ProductDetail.vue`** — admin liste/detay sayfası değil,
  tenant'a (bayi) gösterilen B2B katalog/storefront önizlemesi (sepete ekle, favori,
  galeri zoom, kargo/iade "perk" bilgileri — `ProductController::show()`, tenant
  fiyatlandırması içeriyor). `PageHeader` bu sayfanın hero-galeri düzenine uymuyor;
  `.btn`/ham markup kullanımı bilinçli, "raw markup" taraması bu dosyayı yanlışlıkla
  yakalar — `ProductCard.vue` (gerçek admin ürün kartı, SAP-tarzı) ile karıştırılmamalı.
- **Auth/Profile sayfaları** (`resources/js/Pages/Auth/**`, `Profile/**`) —
  özel, izole tasarım (login ekranı gibi), bu standardın kapsamı dışında.
- **`DataTable`'ın server-side pagination desteklememesi** ve **`Breadcrumb`'ın
  sadece `'home'` icon'unu işlemesi** — bilinen sınırlamalar, bu dokümanın
  konusu değil (bkz. skill).

## 6. Uygulama durumu ve sıradaki adımlar

**2026-09-09 itibarıyla uyumlu:** `Superadmin`, `Product`, `Atelier`, `Finance`,
`Tenant` (Portal hariç), `Creative` modüllerindeki liste/detay sayfalarının
büyük çoğunluğu — `PageHeader`, `Button`, breadcrumb, ve (§3'teki 2026-09-09
güncellemesiyle) `AppDrawer`-tabanlı ekle/düzenle formları artık standart.
Bu geçiş tek oturumda, modül modül yapıldı (Product önce elle, sonra
Atelier/Finance/Superadmin/Tenant/Creative/Product'ın geri kalanı üç paralel
ajanla) — her `AppModal` kullanımı tek tek incelenip gerçek CRUD-form olanlar
`AppDrawer`'a taşındı, onay/bilgi/tetikleyici diyaloglar bilinçli olarak
`AppModal` olarak bırakıldı (liste §3'teki not).

**2026-09-22 itibarıyla ek olarak tamamlandı:** Son 6 ham (Teleport ile elle
yazılmış) drawer/modal — `FilterDrawer.vue`, `CartDrawer.vue`,
`UserFormDrawer.vue`, `SupplierDetailDrawer.vue`,
`MarketplaceCategoryPickerModal.vue`, `CategoryAttributesModal.vue` — artık
sanctioned `AppDrawer`/`AppModal` primitiflerini kullanıyor (kendi
Teleport/`.drawer`/`.modal` iskeletlerini elle tekrar etmiyorlar). Bu geçişte
2 gerçek davranış eksikliği bulundu ve düzeltildi: `UserFormDrawer.vue`'nun
hiç ESC/dışa-tıkla-kapat mantığı yoktu (başka bir componentin artık
kaldırılmış global CSS sızıntısına güveniyordu), `CartDrawer.vue`'nun da
ESC handling'i yoktu. `AppDrawer.vue`'nun kendisine, `FilterDrawer.vue`'da
zaten var olan mobil tam-ekran davranışı (`@media max-width:480px`) taşındı
— artık bunu kullanan her drawer bu davranışı bedava alıyor. Ayrıca
`MarketplaceCategoryPickerModal.vue`'nun barındırdığı, "Categories.vue
AppModal'ı import etmiyor" diye açıklanan tam bir AppModal-shell CSS kopyası
artık gereksizdi (Categories.vue'daki her iki modal component'i de artık
gerçek `AppModal`'ı import ediyor) — silindi.
- `UserFormDrawer.vue` ve `SupplierDetailDrawer.vue` **hâlâ hiçbir sayfadan
  import edilmiyor** (grep ile doğrulandı) — dönüştürüldüler ama aktif
  kullanılmıyorlar, `resources/js/Layouts/AppLayoutClassic.vue` ile aynı
  yetim-dosya deseni.

**Hâlâ bilinçli olarak dışarıda:**
- **Tenant Portal** (`Modules/Tenant/.../Pages/Portal/**`) — kendi
  `TenantPortalLayout`'u var, bu standardın kapsamı dışında (bkz. §5).
- **`Modules/Product/.../Pages/ProductDetail.vue`** — admin sayfası değil,
  tenant'a gösterilen B2B storefront önizlemesi, bkz. §5.
- `ButtonShowcase.vue` / `ComponentShowcase.vue` — kasıtlı olarak ham `.btn`
  class'larını sergileyen tasarım-sistemi referans sayfaları, dönüştürülmez.

**Sıradaki adımlar (opsiyonel, düşük öncelik):**
1. Yukarıdaki ham Teleport drawer/modal'ları `AppDrawer`/`AppModal`
   component'ine taşı (yapısal risk taşıdığı için ayrı, dikkatli bir geçiş).
2. **`PageHeader.vue`'yu genişlet**: `backHref` (opsiyonel geri oku) + `sticky`
   prop'u ekle, `ProductForm.vue`'nun `.form-topbar`'ını bu component'e taşı —
   böylece §2'deki iki desen (liste-sayfası header / form-topbar) tek
   component'in iki varyasyonu olur.
