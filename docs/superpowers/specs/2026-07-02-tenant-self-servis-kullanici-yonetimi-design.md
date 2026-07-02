# Tenant Self-Servis Kullanıcı Yönetimi — Tasarım

**Tarih:** 2026-07-02
**Modül:** `Modules/Tenant`
**Durum:** Onaylandı (brainstorming), plan aşamasına geçiliyor

## Amaç

Bir tenant yöneticisi, kendi tenant'ı içinde alt kullanıcılar tanımlayabilsin. Alt
kullanıcılar yalnızca kendi tenant'larının portal alanına erişir ve yönetici tarafından
seçilen portal izinlerine sahip olur. Baseline olarak her alt kullanıcı otomatik
`portal.access` iznini (giriş) alır.

Bu, dört alanlık tenant geliştirme kapsamının **A alt-projesidir** (diğerleri: B tenant
ayarları & marka, C portal özellik iyileştirmeleri, D pazaryeri entegrasyonları — ayrı
spec'ler). A ilk sırada çünkü en net tarif edilen gereksinim, RBAC'te doğrudan bir boşluk
ve B/C/D'yi gerçek kullanıcılarla test edebilmenin ön koşulu.

## Bağlam (mevcut durum)

- RBAC yalnızca iki rol içerir: `superadmin`, `tenant` (guard `web`). `tenant` rolü tüm
  portal izinlerini taşır; "yönetici vs alt kullanıcı" ayrımı yoktur.
- Portal, subdomain grubunda `can:portal.access` ile, alt rotalar `portal.orders.view`,
  `portal.checkout` vb. ile korunur (`Modules/Tenant/routes/portal.php`).
- `ResolveTenantFromSubdomain` middleware'i "kullanıcı sadece kendi tenant'ına erişebilir"
  kuralını uygular (superadmin bypass).
- Kullanıcılar tenant'a `users.tenant_id` ile bağlanır.
- `users` tablosu kolonları: `id, name, email, email_verified_at, password,
  remember_token, created_at, updated_at, tenant_id`. **`is_active` ve `deleted_at` yok.**
- Mail: SMTP `127.0.0.1` (lokal yakalayıcı; davet maili lokalde çalışır).
- Not: `User` modelinin `$fillable`'ında DB'de olmayan kolonlar var (bilinen şema drift'i);
  bu spec kapsamında değil, dokunulmaz.

## Kararlar (brainstorming çıktısı)

1. **Yönetim modeli:** Tenant yöneticisi. Superadmin'in oluşturduğu ilk kullanıcı =
   yönetici; alt kullanıcıları yalnızca yönetici oluşturur/yönetir. Alt kullanıcıların
   yönetim yetkisi yoktur.
2. **Alt kullanıcı erişimi:** Yönetici her alt kullanıcı için portal izinlerini kutucuklarla
   **kendisi seçer** (granüler). Baseline `portal.access` otomatik.
3. **Kimlik oluşturma:** İkisi de — (Faz 1) yönetici geçici şifre belirler; (Faz 2) e-posta
   davet bağlantısı.
4. **Yaşam döngüsü:** İzinleri sonradan düzenleme, aktif/pasif yapma, şifre sıfırlama, silme
   (soft-delete). Yönetici kendini ve başka yöneticiyi yönetemez.
5. **RBAC modeli (Yaklaşım A):** `tenant-user` rolü baseline (`portal.access`) + kullanıcı-
   başına doğrudan izin ataması.

## Mimari — RBAC modeli

**Yeni izin** (`TenantPermissionSeeder`):
- `portal.users.manage` → "Portal — Kullanıcı Yönet". `tenant` rolüne (yöneticiler) atanır.
  Kullanıcılar ekranı ve tüm yönetim rotaları bununla korunur.

**Yeni rol** (`RolePermissionSeeder` rol listesi + `TenantPermissionSeeder` ataması):
- `tenant-user` (guard `web`) → yalnızca `portal.access` taşır. Bu, alt kullanıcının
  "otomatik permission" baseline'ıdır.

**`tenant` rolü değişikliği:** Mevcut portal izinlerine ek olarak `portal.users.manage`.

**Alt kullanıcıya atanabilir izin kataloğu** (admin-özel izinler hariç):
```
portal.orders.view, portal.invoices.view, portal.credit.view,
portal.catalog.view, portal.checkout,
marketplace.view-sales, marketplace.sync,
portal.financials.view, portal.calculator.use, portal.feed.access
```
- `portal.access` seçilmez (baseline, rol ile gelir).
- `portal.users.manage` alt kullanıcıya **asla** verilemez.

Alt kullanıcı temsili: `tenant_id` = yöneticinin tenant'ı; rol = `tenant-user`; ek yetkiler =
doğrudan atanmış `portal.*` izinleri. `can:` middleware hem rolden hem doğrudan izinden
gelen yetkileri görür.

## Veri modeli — migration'lar

CLAUDE.md DB disiplinine uyar: ileri-yönlü, gerçek `down()`, sonrasında `schema:audit`.

1. **`users.is_active`** — boolean, default `true`, NOT NULL. Aktif/pasif için.
   `down()`: kolonu düşürür.
2. **`users.deleted_at`** — SoftDeletes (`softDeletes()`). `User` modeline `SoftDeletes`
   trait eklenir. `down()`: kolonu düşürür + trait geri alınır.
   - Yan etki: soft-delete edilen kullanıcı auth sorgularından otomatik dışlanır → giriş
     yapamaz (istenen davranış).

## Backend bileşenleri (`Modules/Tenant`)

**Rotalar** (`routes/portal.php`, `can:portal.users.manage` grubu):
```
GET    /users                       → index          (tenant kullanıcı listesi)
POST   /users                       → store          (alt kullanıcı oluştur)
PUT    /users/{user}                → update         (ad + izinleri güncelle)
DELETE /users/{user}                → destroy        (soft-delete)
POST   /users/{user}/toggle-active  → toggleActive   (aktif/pasif)
POST   /users/{user}/reset-password → resetPassword  (yeni geçici şifre)
POST   /users/{user}/invite         → invite         (Faz 2 — davet maili)
```

**Controller:** `Portal/PortalUserController` (Inertia). Her aksiyon `current_tenant_id`'ye
scope'lar; iş mantığını servise delege eder. `{user}` route-model binding'i policy ile
tenant sahipliğine göre doğrulanır.

**Service:** `TenantUserService` — `create / update / delete / toggleActive /
resetPassword / invite`. `DB::transaction` içinde: kullanıcı yaratır → `tenant_id` **zorla**
current tenant → `tenant-user` rolü atar → seçili izinleri `syncPermissions` ile doğrudan
atar. Şifre `Hash::make`, `email_verified_at = now()` (şifre-belirleme akışı).

**Form Request'ler:**
- `StorePortalUserRequest`, `UpdatePortalUserRequest`
- `authorize()`: `can('portal.users.manage')` (laravel-authorization skill'i ile izin dizesi
  tüm tüketim noktalarına yayılır).
- Kurallar: `name` zorunlu; `email` `Rule::unique('users','email')` (update'te ignore);
  `password` (davet değilse) zorunlu + `confirmed` + min; `permissions` array, her eleman
  atanabilir katalogda (`Rule::in($assignable)`).

**Policy:** `PortalUserPolicy` — hedef kullanıcı için:
1. `target.tenant_id === actor.tenant_id` (başka tenant'a dokunamaz).
2. Yönetici kendini silemez/pasifleştiremez.
3. Yönetici başka bir yöneticiyi (`tenant` rolü) yönetemez — yalnız `tenant-user`.
   `Gate::before` superadmin'i geçirir.

**is_active zorlaması:** Yeni hafif middleware `EnsureUserActive` → portal grubuna eklenir;
oturumu açık pasif kullanıcı için 403 + logout. Ayrıca login anında pasif kullanıcı reddedilir.

## Frontend (Vue / Inertia portal)

Proje Vue kurallarına uyar (FormField + FormInput, useDrawer/useModal/useToast, `Components/`
— legacy `Components2/` değil).

**Sayfa:** `Portal/Users/Index.vue`
- Tablo: Ad, E-posta, Durum (aktif/pasif rozet), İzin özeti, Rol (Yönetici / Kullanıcı).
- "Yeni Kullanıcı" butonu.
- Satır aksiyonları: Düzenle · Aktif/Pasif · Şifre Sıfırla · Sil. Yönetici satırında ve
  kendi satırında ilgili aksiyonlar gizli/disabled.

**Oluştur/Düzenle drawer'ı (useDrawer):**
- Ad, E-posta.
- Kimlik yöntemi (radio): "Şifre belirle" (şifre + tekrar) · "Davet gönder" (Faz 2; mail
  yoksa disabled + uyarı).
- İzin kutucukları, gruplu:
  - Siparişler & Faturalar: orders.view, invoices.view, credit.view
  - Katalog & Sipariş: catalog.view, checkout
  - Pazaryeri: marketplace.view-sales, marketplace.sync
  - Finans & Araçlar: financials.view, calculator.use, feed.access
- `portal.access` gösterilmez (baseline).

**Onaylı aksiyonlar (useModal):** Şifre sıfırla, sil, pasifleştir → onay diyaloğu + useToast.

**Navigasyon:** "Kullanıcılar" menü girişi yalnızca `portal.users.manage` yetkisi olan
kullanıcıya gösterilir (mevcut sidebar yetki-görünürlük deseni).

**Controller→sayfa propları:** `users` (id, name, email, is_active, rol etiketi, sahip olunan
portal izinleri), `assignablePermissions` (etiket + grup meta), `filters`.

## Hata yönetimi & kenar durumlar (hepsi sunucu tarafında)

- E-posta global unique — çakışmada 422.
- Yönetici kendini silemez/pasifleştiremez → 403.
- Yönetici başka yöneticiyi yönetemez → 403.
- Alt kullanıcıya `portal.users.manage` / admin / superadmin izni verilemez → `Rule::in`
  reddeder (422).
- `tenant_id` her zaman `current_tenant_id`'den zorlanır, input'tan alınmaz.
- Silinen/pasif kullanıcı: aktif oturum 403 + logout; SoftDeletes ile auth'tan dışlanır.
- Davet yöntemi seçilip mail yoksa → zarif 422/uyarı; kullanıcı yine "şifre belirle" ile
  açılabilir.

## Test (`tests/Feature/Tenant/Portal/...`)

1. Yönetici seçili izinlerle alt kullanıcı oluşturur → kullanıcı `portal.access` + seçilenleri alır.
2. Alt kullanıcı seçilmeyen izne ait rotaya erişemez (403); seçilene erişir (200).
3. Alt kullanıcı "Kullanıcılar" sayfasını göremez (403).
4. Yönetici başka tenant'ın kullanıcısını yönetemez (403).
5. Yönetici kendini silemez/pasifleştiremez (403).
6. Şifre sıfırlama çalışır; yeni şifreyle giriş.
7. İzin güncelleme doğrudan izinleri yeniden senkronlar (`syncPermissions`).
8. Pasif kullanıcı girişi engellenir.
9. Alt kullanıcıya admin izni verme denemesi reddedilir (422).

## Fazlama

- **Faz 1:** Migration'lar + RBAC (izin/rol seeder) + CRUD (oluştur / düzenle-izin /
  aktif-pasif / şifre-sıfırla / sil) + şifre-belirleme akışı + is_active zorlaması + Vue
  ekranı + testler. → Çalışan, sevk edilebilir dilim.
- **Faz 2:** E-posta davet akışı (davet token'ı, mail, "kendi şifreni belirle" sayfası).

## Migration sonrası

`php artisan schema:audit` çalıştırılır (CLAUDE.md gereği), orphan/şişme kontrolü.
