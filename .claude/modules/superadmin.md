# Superadmin Modülü

## Genel

- **Amaç:** Platformun merkezi kontrol paneli. Yeni kullanıcı/tenant kaydı açma-kapama, ödeme yöntemi ve firma/API belirleme, roller/izinler gibi sadece yetkili kişilerin düzenlemesi gereken ve tüm sistemi etkileyen ayarların yönetildiği modül. Tenant CRUD, ürün yönetimi gibi domain-spesifik işlemler bu modülün kapsamı dışındadır — onlar kendi modüllerinde kalır.
- Path: `Modules/Superadmin/`
- Middleware: `auth`, `verified`, `role:superadmin`
- Permission: `superadmin.genel`
- Tech: Laravel 12 + Inertia.js + Vue 3 (SFC `<script setup>`)

## Envanter

| Tür        | Adet       | Dosyalar                                                                                                              |
| ---------- | ---------- | --------------------------------------------------------------------------------------------------------------------- |
| Migration  | 1          | `2026_05_16_120000_create_superadmin_settings_table.php`                                                              |
| Model      | 1          | `Setting.php` — JSON value, encrypted sensitive keys (`Crypt`), cache layer (1h TTL), KVKK uyumlu                     |
| Controller | 5 (1 stub) | `SettingsController`, `SystemInfoController`, `RoleController`, `PermissionController`, `SuperadminController` (STUB) |
| Service    | 1          | `SystemInfoService` — CPU/RAM/disk/uptime (Windows uyumlu PowerShell), MySQL/Redis version, Reverb status             |
| Vue Page   | 1          | `Settings.vue` (~3174 satır, 10 sekme)                                                                                |
| Seeder     | 2          | `SettingsSeeder`, `SuperadminDatabaseSeeder`                                                                          |

## Controller Detayları

| Controller             | Metodlar                                                       | Not                                                                                                                                                                                                                        |
| ---------------------- | -------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `SettingsController`   | `index`, `update`                                              | 8 ayar grubu (general/security/mail/notifications/billing/storage/api/performance) + roller/izinler payload + sistem bilgisi. Mail grubu `.env`'e yazar (`writeMailEnv`). Hassas alanlar maskelenir (`MASK = '••••••••'`). |
| `SystemInfoController` | `__invoke`                                                     | JSON polling endpoint. `SystemInfoService::payload()` çağırır.                                                                                                                                                             |
| `RoleController`       | `store`, `update`, `destroy`, `permissions`, `syncPermissions` | Spatie `Role` CRUD + izin sync. superadmin rolü düzenlenemez/silinemez. Kullanıcı bağlıysa silinemez.                                                                                                                      |
| `PermissionController` | `store`, `update`, `destroy`                                   | Spatie `Permission` CRUD. Role bağlıysa silinemez.                                                                                                                                                                         |
| `SuperadminController` | 7 boş metod                                                    | ❌ STUB — `index/create/store/show/edit/update/destroy` hepsi boş. Temizlenmeli veya kaldırılmalı.                                                                                                                         |

## Settings.vue Sekmeleri

1. **Genel** — sistem adı, URL, dil, timezone, tarih formatı, para birimi, kayıt açık/kapalı, bakım modu
2. **Güvenlik** — parola politikası, 2FA zorunluluğu, oturum süresi, max login denemesi, IP whitelist, audit log, CAPTCHA
3. **Roller & İzinler** — rol listesi (kart), izin matrisi (tablo), izin CRUD, rol CRUD
4. **E-posta (SMTP)** — driver, host, port, şifreleme, kullanıcı/parola, gönderici, test butonu
5. **Bildirimler** — e-posta bildirimleri (5 toggle), Slack webhook, push/in-app toggle
6. **Ödeme & Faturalama** — iyzico/Stripe API key, KDV, deneme süresi, fatura öneki, sandbox modu
7. **Depolama & Yedekleme** — driver (local/S3/GCS), bucket/region/key, max upload, yedekleme schedule
8. **API & Geliştirici** — rate limit, API versiyonu, webhook secret, CORS, sandbox, Swagger toggle
9. **Performans & Cache** — cache/queue/session driver, log seviyesi, debug bar, query log, cache temizle butonları
10. **Sistem Bilgisi** — CPU/RAM/disk bar, uptime, yazılım sürümleri, tenant/user/order/queue sayıları, Reverb durumu

## Bilinen Sorunlar

- `SuperadminController` tamamen stub — 7 metod boş. API route'ları (`api/v1/superadmins`) da bu stub'a bağlı.
- User CRUD yok — kullanıcı yönetimi sadece DB üzerinden yapılabiliyor.
- Audit log yok — ayar değişiklikleri, rol/izin değişiklikleri loglanmıyor.
- Backup UI eksik — "Şimdi Yedekle" butonu var ama `runBackup()` metodu yok.

---

## ROADMAP: User CRUD

### Ön Bilgi

- `users` tablosu: `id, name, email, email_verified_at, password, remember_token, created_at, updated_at, tenant_id`
- User model fillable'da tanımlı ama DB'de **olmayan** alanlar: `partner_id, uuid, phone, job_title, department, bio, address, two_factor_enabled, two_factor_secret`
- Sidebar'da `/users` linki var (İlişkiler menüsü) ama route/controller/sayfa yok
- Mevcut roller: `superadmin` (tüm izinler), `management` (2 izin), `tenant` (1 izin)

### ADIM 1 — Migration

Dosya: `Modules/Superadmin/database/migrations/2026_05_24_100000_add_profile_columns_to_users_table.php`

```
users tablosuna eklenecek kolonlar:
- phone (string, nullable) — telefon numarası
- job_title (string, nullable) — unvan
- is_active (boolean, default true) — aktif/pasif durumu
```

NOT: `partner_id, uuid, bio, address, department, two_factor_*` alanları bu fazda eklenmeyecek. Model fillable'dan da temizlenmeli veya migration'a taşınmalı — tutarsızlık bırakılmamalı.

### ADIM 2 — UserController

Dosya: `Modules/Superadmin/Http/Controllers/UserController.php`

```
index()
  - Inertia::render('Superadmin::Users', [...])
  - User::with('roles', 'tenant')->get()
  - Her kullanıcıya: id, name, email, phone, job_title, is_active, created_at, role (ilk rol adı), tenant (id+name)
  - Props: users, roles (Role::all()), tenants (Tenant::where('is_active',true)->get())
  - Permission: user.view

store(Request)
  - Validate: name (required), email (required|unique), password (required|min:8|confirmed), role (required|exists:roles,name), tenant_id (nullable|exists), phone, job_title
  - User::create + assignRole
  - Permission: user.manage
  - Redirect back + success toast

update(Request, User)
  - Validate: name, email (unique:users,email,{id}), password (nullable|min:8|confirmed), role, tenant_id, phone, job_title
  - Şifre boşsa güncelleme — mevcut şifre korunur
  - syncRoles (tek rol)
  - Permission: user.manage
  - superadmin kullanıcının rolü değiştirilemez (koruma)

destroy(User)
  - Kendini silemez (auth()->id() !== $user->id)
  - superadmin rolündeki son kullanıcıyı silemez
  - Permission: user.manage
  - Soft delete değil hard delete (users tablosunda softDeletes yok)

toggleActive(User)
  - $user->update(['is_active' => !$user->is_active])
  - Kendini pasifleştiremez
  - Permission: user.manage
```

### ADIM 3 — Routes

Dosya: `Modules/Superadmin/routes/web.php` (mevcut route grubuna ekle)

```
Route::get('superadmin/users', [UserController::class, 'index'])->name('superadmin.users.index');
Route::post('superadmin/users', [UserController::class, 'store'])->name('superadmin.users.store');
Route::put('superadmin/users/{user}', [UserController::class, 'update'])->name('superadmin.users.update');
Route::delete('superadmin/users/{user}', [UserController::class, 'destroy'])->name('superadmin.users.destroy');
Route::post('superadmin/users/{user}/toggle', [UserController::class, 'toggleActive'])->name('superadmin.users.toggle');
```

### ADIM 4 — Permission Seeder

Dosya: `Modules/Superadmin/database/seeders/UserPermissionSeeder.php` (yeni)

```
Permission'lar (firstOrCreate, idempotent):
- user.view  — "Kullanıcıları Görüntüle"
- user.manage — "Kullanıcı Yönet"

Tümü superadmin rolüne atanır.
SuperadminDatabaseSeeder'a eklenir.
```

### ADIM 5 — Users.vue

Dosya: `Modules/Superadmin/Resources/assets/js/Pages/Users.vue`

```
Yapı (Tenants.vue referans alınır):

<script setup>
  - defineOptions({ layout: AppLayout })
  - import: Head, router, usePage, AppLayout, Breadcrumb, AppModal
  - inject: showToast, $swal
  - props: users (Array), roles (Array), tenants (Array)
  - computed: canManage (user.manage permission check)

Breadcrumb:
  Ana Sayfa → Süper Admin → Kullanıcılar

Tablo kolonları:
  - Kullanıcı (avatar initials + isim + email)
  - Telefon
  - Unvan
  - Rol (badge, renkli)
  - Tenant (tenant adı veya "—")
  - Durum (Aktif/Pasif pill)
  - Kayıt Tarihi
  - İşlemler (düzenle, toggle, sil)

Filtreler:
  - Arama input (isim, email, telefon)
  - Rol dropdown (tüm roller + "Tüm roller")
  - Durum dropdown (Tüm / Aktif / Pasif)

Modal form alanları:
  - Ad Soyad (text, required)
  - E-posta (email, required)
  - Telefon (tel, optional)
  - Unvan (text, optional)
  - Şifre + Şifre Tekrar (password, required:yeni / optional:düzenle)
  - Rol (select, required — roles prop'undan)
  - Tenant (select, optional — tenants prop'undan, "Yok" seçeneği ile)

Silme: SweetAlert2 onay ($swal inject)
Bildirim: showToast inject
Form submit: router.post / router.put (Inertia)
Form state: reactive + watch(formOpen) ile reset
```

### ADIM 6 — AppLayout Güncelleme

Dosya: `resources/js/Layouts/AppLayout.vue`

```
componentToNavKey'e ekle:
  'Superadmin::Users': null   (ana menüde aktif değil, superadmin panelinden açılır)

Sidebar'daki mevcut "Kullanıcılar" linki (/users) → /superadmin/users olarak güncelle
```

### Konvansiyonlar (tüm adımlar için geçerli)

- Layout: `defineOptions({ layout: AppLayout })`
- Toast: `const showToast = inject('showToast')` → `showToast?.({ type, title, message })`
- Onay: `const $swal = inject('$swal')` → `$swal.fire({ ... })`
- Permission: `(page.props.auth?.permissions ?? []).includes('user.manage')`
- Form submit: `router.post/put/delete` ile `preserveScroll: true, preserveState: true, onSuccess, onError, onFinish`
- Form reset: `reactive(emptyForm())` + `watch(formOpen)` ile otomatik reset
- Inline hatalar: `errors.value = errs` → `<span class="field-error">{{ errors.fieldName }}</span>`
- CSS: Scoped `<style>`, projede Tailwind var ama modül sayfaları custom CSS kullanıyor (Settings.vue, Tenants.vue pattern'i)

### Doğrulama

İmplementasyon sonrası:

1. `php artisan module:migrate Superadmin` — yeni migration çalışmalı
2. `php artisan db:seed --class=Modules\\Superadmin\\Database\\Seeders\\UserPermissionSeeder` — permission'lar oluşmalı
3. `npx vite build` — Users.vue bundle'ı üretilmeli, hata olmamalı
4. `/superadmin/users` — superadmin olarak açılmalı, boş tablo görünmeli
5. "Yeni Kullanıcı" → form doldur → kaydet → tabloda satır oluşmalı
6. Düzenle → şifre boş bırak → kaydet → mevcut şifre korunmalı
7. Toggle → aktif/pasif değişmeli
8. Sil → SweetAlert2 onay → silme başarılı (kendini silemez)

---

## Sonraki Fazlar (User CRUD sonrası)

- Stub temizliği: `SuperadminController` kaldırılması + API route'larının düzenlenmesi
- Audit Log: Spatie ActivityLog veya custom tablo ile kritik işlem logları
- Backup UI: `runBackup()` metodu + queue job
- User import/export: CSV ile toplu kullanıcı ekleme
