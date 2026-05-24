# Tenant Modülü — Task Planı

## Genel Bilgi

- Strateji: Tek veritabanı, tenant_id ile izolasyon
- Tenant = müşteri firma (B2B dropship)
- Ödeme işlemi: Superadmin modülünde, kayıt burada
- Kullanıcı/rol: Superadmin modülünde
- Bağımlı modüller: Sipariş, Ürün/Katalog

---

## Task 1 — Migration & Model

**Amaç:** Tenant veritabanı yapısını kur

**Dokunulacak dosyalar:**

- `database/migrations/xxxx_create_tenants_table.php` (yeni)
- `database/migrations/xxxx_create_tenant_price_lists_table.php` (yeni)
- `database/migrations/xxxx_create_tenant_invoices_table.php` (yeni)
- `app/Models/Tenant.php` (yeni)
- `app/Models/TenantPriceList.php` (yeni)
- `app/Models/TenantInvoice.php` (yeni)

**Tablo alanları:**

tenants:

- id, name, slug, email, phone
- address, city, country
- logo_path
- status (active/passive/suspended)
- settings (JSON — tenant bazlı config)
- created_by (superadmin user_id)
- timestamps, softDeletes

tenant_price_lists:

- id, tenant_id, product_group_id
- discount_rate, special_price
- valid_from, valid_until
- is_active, timestamps

tenant_invoices:

- id, tenant_id, order_id (nullable)
- amount, currency
- status (pending/paid/cancelled)
- due_date, paid_at
- note, timestamps

**Model ilişkileri:**

- Tenant → hasMany TenantPriceList
- Tenant → hasMany TenantInvoice
- TenantPriceList → belongsTo Tenant
- TenantInvoice → belongsTo Tenant

**Kabul kriterleri:**

- [ ] 3 migration hatasız çalışıyor
- [ ] Soft delete sadece Tenant'ta var
- [ ] settings alanı JSON cast'li
- [ ] Tüm ilişkiler tanımlı

**Bağımlılık:** Yok (ilk task)

---

## Task 2 — TenantService

**Amaç:** İş mantığını controller'dan ayır, service katmanı kur

**Dokunulacak dosyalar:**

- `app/Services/TenantService.php` (yeni)

**Metodlar:**

- `create(array $data): Tenant`
- `update(Tenant $tenant, array $data): Tenant`
- `suspend(Tenant $tenant): void`
- `activate(Tenant $tenant): void`
- `getActiveTenants(): Collection`
- `getPriceListForTenant(Tenant $tenant): Collection`
- `addPriceList(Tenant $tenant, array $data): TenantPriceList`
- `createInvoice(Tenant $tenant, array $data): TenantInvoice`
- `markInvoicePaid(TenantInvoice $invoice): void`

**Kabul kriterleri:**

- [ ] Her metod tek iş yapıyor
- [ ] Validation service'te değil, ayrı FormRequest'te olacak
- [ ] Exception fırlatıyor, try/catch controller'da

**Bağımlılık:** Task 1

---

## Task 3 — Controller & Routes

**Amaç:** API endpoint'lerini kur

**Dokunulacak dosyalar:**

- `app/Http/Controllers/Api/TenantController.php` (yeni)
- `app/Http/Controllers/Api/TenantPriceListController.php` (yeni)
- `app/Http/Controllers/Api/TenantInvoiceController.php` (yeni)
- `app/Http/Requests/Tenant/` (yeni klasör)
    - `StoreTenantRequest.php`
    - `UpdateTenantRequest.php`
    - `StorePriceListRequest.php`
    - `StoreInvoiceRequest.php`
- `routes/api.php` (güncellenir)

**Endpoint listesi:**
GET /api/tenants
POST /api/tenants
GET /api/tenants/{tenant}
PUT /api/tenants/{tenant}
DELETE /api/tenants/{tenant}
PATCH /api/tenants/{tenant}/suspend
PATCH /api/tenants/{tenant}/activate
GET /api/tenants/{tenant}/price-lists
POST /api/tenants/{tenant}/price-lists
DELETE /api/tenants/{tenant}/price-lists/{priceList}
GET /api/tenants/{tenant}/invoices
POST /api/tenants/{tenant}/invoices
PATCH /api/tenants/{tenant}/invoices/{invoice}/mark-paid

**Kabul kriterleri:**

- [ ] Tüm route'lar `superadmin` middleware ile korunuyor
- [ ] Her endpoint TenantService üzerinden çalışıyor
- [ ] Silme işlemi soft delete (tenant), hard delete (price list)
- [ ] Responses: `ApiResource` formatında

**Bağımlılık:** Task 2

---

## Task 4 — API Resource & Transformer

**Amaç:** API çıktısını standartlaştır

**Dokunulacak dosyalar:**

- `app/Http/Resources/TenantResource.php` (yeni)
- `app/Http/Resources/TenantCollection.php` (yeni)
- `app/Http/Resources/TenantPriceListResource.php` (yeni)
- `app/Http/Resources/TenantInvoiceResource.php` (yeni)

**Kabul kriterleri:**

- [ ] Hassas alanlar (created_by vb.) dışarı çıkmıyor
- [ ] İlişkiler `whenLoaded` ile lazy yükleniyor
- [ ] Tarihler ISO 8601 formatında

**Bağımlılık:** Task 3

---

## Task 5 — Tenant Middleware & Scope

**Amaç:** Tüm sorgularda tenant izolasyonunu garantile

**Dokunulacak dosyalar:**

- `app/Http/Middleware/SetTenantContext.php` (yeni)
- `app/Models/Traits/BelongsToTenant.php` (yeni trait)
- `bootstrap/app.php` (middleware kaydı)

**BelongsToTenant trait içeriği:**

- `bootBelongsToTenant()` — otomatik tenant_id filtresi (global scope)
- `creating` event'te otomatik tenant_id ata

**Kabul kriterleri:**

- [ ] Tenant A, Tenant B'nin verisini göremez
- [ ] Global scope tüm sorgulara otomatik ekleniyor
- [ ] Superadmin global scope'u bypass edebiliyor (`withoutTenantScope()`)

**Bağımlılık:** Task 1

---

## Task 6 — Tenant Ayarları (Settings)

**Amaç:** Her tenant'ın kendine özel konfigürasyonu olsun

**Dokunulacak dosyalar:**

- `app/Services/TenantSettingsService.php` (yeni)
- `app/Http/Controllers/Api/TenantSettingsController.php` (yeni)
- `routes/api.php` (2 endpoint eklenir)

**Settings alanları (JSON şeması):**

```json
{
    "currency": "TRY",
    "language": "tr",
    "notification_email": "",
    "order_auto_confirm": false,
    "invoice_prefix": "INV",
    "shipping_address_default": {}
}
```

**Endpoint:**
GET /api/tenants/{tenant}/settings
PATCH /api/tenants/{tenant}/settings

**Kabul kriterleri:**

- [ ] Sadece izin verilen key'ler güncellenebiliyor
- [ ] Bilinmeyen key gelirse 422 dönüyor
- [ ] Mevcut settings merge ediliyor, üzerine yazılmıyor

**Bağımlılık:** Task 3

---

## Task 7 — Testler

**Amaç:** Tüm modülü test kapsamına al

**Dokunulacak dosyalar:**

- `tests/Feature/Tenant/TenantCrudTest.php`
- `tests/Feature/Tenant/TenantPriceListTest.php`
- `tests/Feature/Tenant/TenantInvoiceTest.php`
- `tests/Feature/Tenant/TenantSettingsTest.php`
- `tests/Feature/Tenant/TenantIsolationTest.php`
- `database/factories/TenantFactory.php`
- `database/factories/TenantPriceListFactory.php`
- `database/factories/TenantInvoiceFactory.php`

**Test senaryoları:**

TenantCrudTest:

- [ ] Tenant oluşturulabiliyor
- [ ] Tenant güncellenebiliyor
- [ ] Tenant suspend/activate çalışıyor
- [ ] Soft delete çalışıyor, listede görünmüyor
- [ ] Non-superadmin erişemiyor (403)

TenantIsolationTest:

- [ ] Tenant A, Tenant B'nin verilerini göremez
- [ ] Superadmin tüm tenant'ları görebilir

TenantSettingsTest:

- [ ] Geçerli key güncellenebiliyor
- [ ] Bilinmeyen key 422 dönüyor
- [ ] Merge çalışıyor, diğer key'ler korunuyor

**Bağımlılık:** Task 1-6 tamamlanmış olmalı

---

## Özet & Sıra

| Task | İş                  | Bağımlılık |
| ---- | ------------------- | ---------- |
| 1    | Migration & Model   | —          |
| 2    | TenantService       | 1          |
| 3    | Controller & Routes | 2          |
| 4    | API Resource        | 3          |
| 5    | Middleware & Scope  | 1          |
| 6    | Tenant Ayarları     | 3          |
| 7    | Testler             | 1-6        |

## Durum

- [x] Task 1 — Migration & Model (2026-05-24: tenants'a slug/logo_path/settings/created_by eklendi, tenant_price_lists ve tenant_invoices tabloları oluşturuldu)
- [x] Task 2 — TenantService (`Modules/Tenant/Services/TenantService.php` oluşturuldu)
- [x] Task 3 — API Controller & Routes (3 API controller + 4 FormRequest + 15 API route eklendi)
- [x] Task 4 — API Resource (TenantResource, TenantCollection, TenantPriceListResource, TenantInvoiceResource)
- [x] Task 5 — Middleware & Scope (SetTenantContext middleware + BelongsToTenant trait, bootstrap/app.php'ye kaydedildi)
- [x] Task 6 — Tenant Ayarları (TenantSettingsService + TenantSettingsApiController, GET/PATCH /settings endpoint)
- [x] Task 7 — Testler (5 test dosyası + 3 factory oluşturuldu)
