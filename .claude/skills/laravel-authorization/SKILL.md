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
| Tenant | `tenant.view` | Tenant'ları görüntüle |
| Tenant | `tenant.manage` | Tenant CRUD |
| Tenant | `tenant-type.manage` | Tenant tipi yönet |
| Tenant | `tenant-access.manage` | Tenant erişim kuralları |
| Tenant | `marketplace.manage` | Pazaryeri bağlantı (tenant role'üne de verilir, controller scope'lu) |
| Product | `product.view` | Ürünleri görüntüle |
| Product | `brand.manage` | Marka |
| Product | `warehouse.manage` | Depo |
| Product | `stock.manage` | Stok |
| Product | `price-list.manage` | Fiyat listesi |
| Creative | `creative.manage` | Stüdyo, şablon, onay |

Seeder dosyaları:
- `Modules/Tenant/database/seeders/TenantPermissionSeeder.php`
- `Modules/Product/database/seeders/ProductPermissionSeeder.php`
- `Modules/Creative/database/seeders/CreativePermissionSeeder.php`

> **Not (güncel tutma):** Bu liste değişiyor; başlamadan önce `php artisan db:seed
> --class=...PermissionSeeder` çıktısına ya da seed dosyalarına bir kez bak ve
> hâlâ güncel mi doğrula. Yeni eklenmiş bir permission burada listelenmemiş olabilir.

## Bilinen tutarsızlık (ÖNCE OKU)

`Modules/Product/routes/web.php` şu yetkilere middleware veriyor:
- `can:product.add` (satır 24, 27, 84)
- `can:product.delete` (satır 31)

**Bunlar seed'li değil.** Yani bu route'lar **herhangi bir kullanıcıya** açık
(spatie default'unda `can:` middleware var-olmayan permission için fail eder; ama
test/prod'da seed'li değilse pratik etkisi kontrol edilmemiş).

Yeni route eklerken **seed'siz bir yetki adı uydurma**. Önce bu seedlere ekle.
İstersen ayrıca bu eski tutarsızlığı temizleyen bir görev planı çıkar (skill'in
işi değil, ayrı PR).

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
