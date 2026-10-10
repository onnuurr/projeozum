---
name: laravel-authorization
description: Use BEFORE writing or editing any controller action, route, Form Request, policy, sidebar entry, or Inertia page that should be gated by authorization in this project (spatie/laravel-permission). This skill REQUIRES Claude to stop and ask the user "Bu özellik hangi yetkiye bağlı?" with the existing permission list, then either reuse an existing permission string or register a new one in the correct module's permission seeder — and propagate the SAME string to every consumption site (route middleware, Form Request authorize(), Vue page guard).
---

# Laravel Authorization — Karar Skill'i

> **Bu skill bir karar zorlar.** Yetki gerektirebilecek bir özelliği yazmaya
> başlamadan **önce**, "hangi permission?" sorusunun cevabı netleşmeden kod yazma.

## Ne zaman bu skill devreye girer

- Yeni controller action / route eklerken
- Form Request `authorize()` doldururken
- Sidebar / menü öğesi eklerken (görünürlük yetkiye bağlı)
- Inertia sayfası eklerken (link veya butonu yetkiye göre gizleyeceksen)
- Yeni bir policy yazarken (proje şu an policy kullanmıyor; istisna ise gerekçesini PR'da yaz)

## Karar akışı (atlama yok)

1. **DUR.** Önce bu skill'in alt kısmındaki "Mevcut yetki kaydı"nı oku.
2. **Yapılan iş bir yetkiye bağlı mı?** Üç olasılık:
   - **Genel erişim** (login'li herkes görür, tenant'ına bakar) → permission gerekmez,
     ama scope kontrolü gerekebilir (bkz. `TenantMarketplaceController::authorizeTenantScope`).
   - **Mevcut bir yetkinin altına düşer** (örn. tenant kayıt ekranı zaten `tenant.manage`'e bağlı, ona ek action ekliyoruz).
   - **Yeni bir yetki gerekir** (örn. ürün ihraç/import gibi yeni bir yetenek).
3. **`AskUserQuestion` ile kullanıcıya sor.** Şu üç şıklı soruyu hazırla:
   - "Mevcut: `<önerilen-string>`" (en olası 2-3 mevcut yetki)
   - "Yeni: `<resource>.<action>`" (önerini doldur — aşağıdaki isim deseni)
   - "Yetki gerektirmiyor (gerekçe: …)"
4. **Yeni** seçilirse:
   - İlgili modülün **PermissionSeeder**'ına `Permission::firstOrCreate(...)` satırı ekle.
   - Superadmin role'üne `givePermissionTo(...)` ile ata.
   - Diğer rollere atanması gerekiyorsa kullanıcıya tekrar sor.
5. **Aynı string'i** tüm tüketim noktalarına yerleştir:
   - Route middleware: `->middleware('can:<string>')`
   - Form Request `authorize()`
   - (Gerekiyorsa) Vue sayfasında `auth.permissions.includes('<string>')`
   - (Gerekiyorsa) sidebar/menü görünürlüğü

## Mevcut yetki kaydı (snapshot, repo'da seed'li olanlar)

| Module | Permission | Açıklama |
|---|---|---|
| Tenant | `tenant.view` | İş ortağı listesi + başvuru kuyruğunu görüntüle |
| Tenant | `tenant.manage` | İş ortağı CRUD (web + `/api/v1/tenants*`, API'de `tenant.owns` scope'lu) |
| Tenant | `tenant-type.manage` | İş ortağı tipi yönet |
| Tenant | `tenant-access.manage` | İş ortağı ürün erişim kuralları |
| Tenant | `tenant.product.customize` | İş ortağına özel ürün metni (erişim ekranında) |
| Tenant | `tenant-application.approve` | Başvuru onay/red + KYB belgesi indirme (2026-09-24) |
| Bagisto | `tenant-application-mail.manage` | Bayi E-postaları › Başvuru grubu: bayi başvuru e-posta metinleri + önizleme/test (2026-09-27, migration ile seed'li) |
| Bagisto | `order-return-mail.manage` | Bayi E-postaları › İade grubu: iade e-posta metinleri + önizleme/test (2026-09-27, migration ile seed'li; grup yetkisi controller'da) |
| Bagisto | `dealer-agreement.manage` | Bayi Sözleşmeleri: bayi portalı sözleşme/onay metinleri, sürümlü yayın + önizleme (2026-10-02, migration ile seed'li; yalnız superadmin) |
| Product | `product.view` | Katalog okuma: ürün listesi/detay (tenant rolünde de var) |
| Product | `warehouse.view` | Depolar sayfası (tenant'a verilmez) |
| Product | `brand.view` | Markalar sayfası (tenant'a verilmez) |
| Product | `category.view` | Kategoriler sayfası (pazaryeri eşleme + özellik tanımlarını da gösterir; tenant'a verilmez) |
| Product | `product.card.view` | Ürün Kartı + pazaryeri listeleme GET (tüm fiyat listeleri + depo stokları; iç kullanıcı, tenant'a verilmez) (2026-09-24) |
| Product | `product.add` | Ürün ekle/düzenle, görsel, barkod, listeleme kaydet (isim kurala uymuyor, bilerek korunuyor) |
| Product | `product.delete` | Ürün sil / toplu sil |
| Product | `product.ai.generate` | AI açıklama üret |
| Product | `category.manage` | Kategori CRUD + pazaryeri eşleme + özellik tanımları |
| Product | `category.create` | Ürün formundan hızlı kategori ekleme (dar yetki) |
| Product | `brand.manage` | Marka |
| Product | `product-settings.view` | Ürün Ayarları sayfası (`/products/settings`) + Katalog/Stok/Siparişler sekme satırındaki çark ikonu; sekmeler ayrıca kendi yetkileriyle kilitli (2026-10-02) |
| Product | `variant-option.view` / `.create` / `.update` / `.delete` | Ürün Ayarları › Bedenler/Renkler sekmeleri: ürün formundaki seçim listeleri (`product_sizes`, `product_colors`); her işlem ayrı yetki (2026-10-02) |
| Product | `warehouse.manage` | Depo |
| Product | `stock.manage` | Stok sayfaları + hareket |
| Product | `price-list.manage` | Fiyat listesi |
| Product | `order.view` / `order.manage` | Sipariş listesi-detay / durum geçişi |
| Product | `carrier.manage` | Kargo firmaları |
| Creative | `creative.view` | Creative okuma (stüdyo, galeri, manken, poz, try-on, şablon, marka kiti, iş ortağı profili) + dışa aktarma |
| Creative | `creative.generate` | Görsel üret / yeniden üret / caption (throttle'lı) |
| Creative | `creative.approve` | Asset/manken/try-on onay-ret (+ ret sohbetine erişim) |
| Creative | `creative.asset.manage` | Manken/poz/try-on yönetimi + Parça Tespiti etiketleme |
| Creative | `creative.template.manage` / `creative.brandkit.manage` / `creative.partnerbrandkit.manage` | Şablon / marka kiti / iş ortağı kreatif profili yazma |
| Creative | `creative.rejection-reasons.manage` / `creative.review-reports.view` | Ret maddeleri / ret analiz raporları (Superadmin menüsünde) |
| Atelier | `atelier.view` | Atölye okuma (panel, hammadde, reçete, operasyon, fasoncu, iş emri, atama, konsept, sayısallaştırma) |
| Atelier | `atelier.{material,bom,operation,fason,production,assignment,conversion,design}.manage` | Alt-özellik bazlı yazma (hibrit granüler) |
| Atelier | `atelier.pattern.view` / `atelier.pattern.manage` | Kalıp Kütüphanesi okuma / yazma + tracer |
| PatternEngine | `patternengine.manage` | Kalıp Motoru işleri (üret/indir/sayısallaştır) |
| PatternEngine | `patternengine.profile.manage` | Ölçü profilleri (üretimden ayrı, modelist için) |
| Finance | `finance.view` | Tüm Finance GET'leri: raporlar, fatura/proforma/banka listeleri, dosya indirme (2026-09-24) |
| Finance | `finance.manage` | Finance yazma: fatura/proforma/banka CRUD, ödendi, dönüştür, ekstre içe aktar, mutabakat |
| Finance | `finance.einvoice.send` | e-Faturayı GİB'e gönder — geri alınamaz, manage'den ayrı |
| Superadmin | `settings.manage` | Sistem ayarları, sistem bilgisi, başarısız işler; Superadmin menü kökü + Dashboard (route ayrıca `role:superadmin`) |
| Superadmin | `menu.manage` | Menü Ayarları |
| Superadmin | `logs.view` | Log görüntüleyici (+ ayrı kapı şifresi `log.access`) |
| Superadmin | `backups.view` / `backups.manage` | Yedek listesi / yedek tetikle |
| Superadmin | `architecture-doctor.view` / `architecture-doctor.manage` | Rapor + tarama / otomatik düzeltme |
| Superadmin | `users.view` / `users.manage` | Kullanıcı yönetimi — `role:superadmin` çift kilit, delege edilmez |
| Superadmin | `rbac.manage` | Rol & izin yönetimi — `role:superadmin` çift kilit, delege edilmez |

Seeder dosyaları:
- `Modules/Tenant/database/seeders/TenantPermissionSeeder.php`
- `Modules/Product/database/seeders/ProductPermissionSeeder.php`
- `Modules/Creative/database/seeders/CreativePermissionSeeder.php`
- `Modules/Atelier/database/seeders/AtelierPermissionSeeder.php`
- `Modules/PatternEngine/database/seeders/PatternEnginePermissionSeeder.php`

> **Not (güncel tutma):** Bu liste değişiyor; başlamadan önce `php artisan db:seed
> --class=...PermissionSeeder` çıktısına ya da seed dosyalarına bir kez bak ve
> hâlâ güncel mi doğrula. Yeni eklenmiş bir permission burada listelenmemiş olabilir.

## Modül ayar sayfası deseni (2026-10-02)

Bir modülü ilgilendiren ayar/referans veri süper admin'e değil, o modülün **tek ayar sayfasına**
sekme olarak eklenir. Sayfa yetkisi `<modül>-settings.view` (örn. `product-settings.view`);
her sekme ayrıca kendi `<resource>.<action>` yetkileriyle kilitlenir ve yetkisiz sekmenin verisi
backend'den `null` gönderilir. Modül, ServiceProvider `boot()`'unda
`App\Support\ModuleSettingsRegistry::register(key, url, permission, menuRoots)` çağırır;
`OzModuleTabs` yetkili kullanıcıya sekme satırının en sağında çark ikonu çizer. Menüye ayrı
"Ayarlar" öğesi eklenmez. Örnek: `Modules/Product/Http/Controllers/ProductSettingsController.php`.

## Bilinen isim istisnaları

`product.add` (anlamı: ekle/düzenle) ve `tenant.product.customize` aşağıdaki isim desenine
uymuyor. Kullanıcı kararıyla (2026-09-24) **yeniden adlandırılmıyor** — ikisi de seed'li ve
tutarlı kullanılıyor. Yeni yetkilerde bu desenleri kopyalama.

## İsim deseni

Format: `<resource>.<action>`

Resource:
- Tek kelime: `tenant`, `product`, `brand`
- Çok kelime: kebab-case `tenant-type`, `price-list`, `tenant-access`

Action sözlüğü (mevcut + önerilen):
| Action | Anlam |
|---|---|
| `view` | Salt okuma (listele, gör) |
| `manage` | Tam CRUD (default) |
| `create` | Sadece ekleme |
| `update` | Sadece düzenleme |
| `delete` | Sadece silme |
| `export` | Dışa aktarma |
| `import` | İçe aktarma |
| `approve` | Onay verme |

`add` kelimesini kullanma (`create` tercih); `edit` kelimesini kullanma (`update`).

## Yeni yetki kaydı: seeder şablonu

`Modules/<Mod>/database/seeders/<Mod>PermissionSeeder.php` dosyasına:

```php
$permissions = [
    'tenant.view'  => 'Tenant\'ları Görüntüle',
    'tenant.manage'=> 'Tenant Yönet',
    // ... mevcutlar ...

    // YENİ:
    'tenant.export' => 'Tenant Dışa Aktarma',
];

foreach ($permissions as $name => $displayName) {
    Permission::firstOrCreate(
        ['name' => $name, 'guard_name' => 'web'],
        ['display_name' => $displayName]
    );
}

$superadmin = Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);
$superadmin->givePermissionTo(array_keys($permissions));
```

Yeni yetki başka bir role'e de gerekiyorsa (ör. `tenant` role'üne):
```php
$tenant = Role::firstOrCreate(['name' => 'tenant', 'guard_name' => 'web']);
$tenant->givePermissionTo('marketplace.manage');
```

**Sonra seeder'ı çalıştır:**
```bash
php artisan db:seed --class="Modules\\<Mod>\\Database\\Seeders\\<Mod>PermissionSeeder"
```

## Tüketim noktaları — string'in gideceği yerler

Tek bir yetki için **birden fazla yer** güncellenir. Birini unutursan delik kalır.

### 1) Route middleware
`Modules/<Mod>/routes/web.php` veya `api.php`:
```php
Route::post('/tenants/{tenant}/export', [TenantController::class, 'export'])
    ->middleware('can:tenant.export')
    ->name('tenants.export');
```

### 2) Form Request `authorize()`
```php
public function authorize(): bool
{
    return $this->user()?->hasPermissionTo('tenant.export') ?? false;
}
```

### 3) Controller (scope kontrolü gerekiyorsa)
Çoklu tenant'ta `tenant.manage`'i olan tenant kullanıcısı **kendi tenant'ı dışına
bakamamalı**. Bkz. `Modules/Tenant/Http/Controllers/TenantMarketplaceController::authorizeTenantScope`:
```php
private function authorizeTenantScope(Request $request, Tenant $tenant): void
{
    $user = $request->user();
    if ($user->isSuperadmin()) return;
    abort_unless($user->tenant_id === $tenant->id, 403);
}
```
Yetki middleware'i + scope kontrolü iki ayrı katmandır; ikisini de uygula.

### 4) Inertia (frontend tarafında buton/link gizleme)
Backend zaten `auth.permissions` array'ini paylaşıyor — bkz.
`app/Http/Middleware/HandleInertiaRequests.php`. Vue tarafında:
```vue
<FormButton
    v-if="$page.props.auth.permissions.includes('tenant.export')"
    @click="exportTenant"
>
    Dışa Aktar
</FormButton>
```

> Bu desen şu an projede **az kullanılıyor**. Yeni gating için kanonik yol budur;
> bir helper composable (örn. `useCan()`) açmak istersen önce ekle, sonra yaygınlaştır.

### 5) Sidebar / menü
Aynı array kontrol edilerek menü öğesi gizlenir. Sidebar bileşenine örnek
verilemiyor (proje genelinde örnek tek tip değil); mevcut menünüze entegre et.

## `isSuperadmin()` kısa-yolu

`app/Models/User.php`'de tanımlı (line 83-86): `$this->hasRole('superadmin')`.
Spatie zaten `superadmin` role'üne her permission'ı verdiği için
`hasPermissionTo()` otomatik true döner — explicit `isSuperadmin()` çoğunlukla
**gerekmez**. Sadece **scope by-pass** için kullanılır (yukarıdaki tenant scope örneği).

## Karar kontrol listesi

- [ ] "Hangi yetki?" sorusunu kullanıcıya `AskUserQuestion` ile sordum
- [ ] Mevcut yetki listesini cevaba opsiyon olarak sundum
- [ ] Yeni yetki ise: ilgili modülün PermissionSeeder'ına eklendi
- [ ] Yeni yetki ise: superadmin role'üne assign edildi; başka role gerekiyor mu sordum
- [ ] Yetki adı `<resource>.<action>` formatında; `view` veya `manage` tercih
- [ ] Route middleware (`can:<string>`) güncellendi
- [ ] Form Request `authorize()` güncellendi
- [ ] (Gerekiyorsa) Inertia frontend'de `auth.permissions` ile buton/link gizlendi
- [ ] (Gerekiyorsa) controller'da scope kontrolü eklendi (tenant by-pass)
- [ ] `php artisan db:seed --class=...PermissionSeeder` çalıştırıldı / talimat verildi

## Yapma listesi

- **Yapma:** Seed'li olmayan permission adı uydurma (`product.add` gibi).
- **Yapma:** `authorize()` içinde `return true;`.
- **Yapma:** Sadece middleware koy, scope kontrolünü atla — `tenant.manage` yetkisi
  olan tenant kullanıcısı başka tenant'a bakabilir hâle gelir.
- **Yapma:** Yeni `Gate::define` ile global gate ekleme; bu projede yetki kaynağı
  **spatie permission seeder'larıdır**.
- **Yapma:** `User` üzerinde `is<RoleName>()` metotları çoğaltma; `hasRole(...)` yeter.
