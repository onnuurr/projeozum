---
name: laravel-controller
description: Use when creating or editing a Laravel controller in this project (web Inertia or REST API under app/Http/Controllers/ or Modules/*/Http/Controllers/). Covers Form Request vs inline validate, spatie/permission authorization, delegating to services, and DB::transaction placement.
---

# Laravel Controller — Proje Konvansiyonu

Bu proje **nwidart/laravel-modules** üzerine kurulu. Web tarafı **Inertia + Vue**,
API tarafı **JsonResource / JsonCollection**. Yetkilendirme **spatie/laravel-permission**.

## Nereye koyulur

- **Web (Inertia)** — `Modules/<Mod>/Http/Controllers/<Mod>Controller.php`
  - Örn: `Modules/Product/Http/Controllers/ProductController.php`
- **API** — `Modules/<Mod>/Http/Controllers/Api/<Mod>ApiController.php`
  - Örn: `Modules/Tenant/Http/Controllers/Api/TenantApiController.php`
- **Paylaşılan** (profil, auth) — `app/Http/Controllers/...`
- Base class **her zaman** `App\Http\Controllers\Controller`.

## Validation kararı

| Senaryo | Tercih |
|---|---|
| API endpoint (`store`, `update`) | **Form Request** (`Store<Model>Request`, `Update<Model>Request`) |
| Inertia POST'u resmi bir kayıt oluşturuyor | **Form Request** |
| Tek-alanlı küçük POST (toggle, switch) veya esnek form | `inline $request->validate()` veya `private validateX()` |

Mevcut örnekler:
- Form Request: `Modules/Tenant/Http/Controllers/Api/TenantApiController.php:25`
- Inline + private helper: `Modules/Product/Http/Controllers/ProductController.php` içindeki `validateProduct()`

## Yetkilendirme

> **ÖNCE `laravel-authorization` skill'ini çalıştır.** Yeni action yazarken
> "hangi yetkiye bağlı?" sorusunun cevabı belirlenmeden middleware string'i
> yazma; mevcut yetkilerden seç ya da yeni yetkiyi modülün PermissionSeeder'ına
> ekle. Aynı string buradan, Form Request `authorize()`'dan ve sidebar/Vue
> tarafından **aynı** kullanılır.

Tek yer yerine **iki katman**:

1. **Route middleware** — `middleware('can:tenant.manage')` veya `['auth:sanctum','role:superadmin']`
2. **Form Request `authorize()`** — `$this->user()?->hasPermissionTo('tenant.manage') ?? false`
   (`Modules/Tenant/Http/Requests/StoreTenantRequest.php:9`)

Inline controller içinde `$user->isSuperadmin()` ile scope by-pass + `tenant_id`
karşılaştırması çoklu-tenant veride **zorunlu**; route middleware'i tek başına
yeterli değildir. Referans: `TenantMarketplaceController::authorizeTenantScope`.

## Servise delege

Controller iş kuralını kendisi taşımaz; `Modules/<Mod>/Services/<X>Service.php`'ye delege eder.

```php
public function __construct(private TenantService $service) {}

public function store(StoreTenantRequest $request): JsonResponse
{
    $tenant = $this->service->create($request->validated());
    return (new TenantResource($tenant->load('type')))->response()->setStatusCode(201);
}
```

Referans: `Modules/Tenant/Http/Controllers/Api/TenantApiController.php`

## DB::transaction nerede

- **Tek satırlık** Eloquent oluşturma/güncelleme → transaction'a gerek yok.
- **Birden fazla tabloyu** etkileyen iş (ör. ürün + varyant + stok hareketi birlikte)
  → controller içinde `DB::transaction(function () { ... })` veya servis metodunun
  başında. `Modules/Product/Http/Controllers/ProductController::store` örneğine bak.

## Return tipleri

| Yer | Return |
|---|---|
| Web (Inertia sayfa) | `Inertia::render('Path/Page', [...])` |
| Web (form sonrası) | `RedirectResponse` (`back()`, `to_route()`) |
| API single | `JsonResource` veya `JsonResponse` |
| API list | `JsonCollection` |
| API 204 / hata | `response()->json($payload, $status)` |

Mümkünse **return tipini imzaya yaz**: `public function show(Tenant $tenant): TenantResource`.

## Eager-loading & N+1

Listeleme route'larında ilişkileri controller'da yükle:
`Tenant::with('type','users')->get()`. Pricing benzeri toplu çözümleri
private helper'a topla (`ProductController::loadTenantPricing()`).

## Checklist

- [ ] Doğru dizinde (Inertia mı API mı?)
- [ ] Base class `App\Http\Controllers\Controller`
- [ ] Validation: Form Request (resmi POST) veya inline (`validate()`)
- [ ] Yetki: route middleware **+** request `authorize()` veya scope
- [ ] İş kuralı service'e delege; controller "ince" kaldı
- [ ] Çok-tablolu işlemde `DB::transaction`
- [ ] Eager-load; N+1 yok
- [ ] Method imzasına return tipi yazıldı
