---
name: laravel-service
description: Use when creating or editing a service class under Modules/*/Services/ in this project. Covers multi-method orchestrator pattern, constructor DI, DB::transaction usage, domain exceptions, and when NOT to add a repository.
---

# Laravel Service — Proje Konvansiyonu

## Nereye koyulur

`Modules/<Mod>/Services/<X>Service.php`, namespace `Modules\<Mod>\Services`.

Örnekler:
- `Modules/Tenant/Services/TenantService.php` (basit, multi-method)
- `Modules/Creative/Services/CreativeRenderService.php` (DI ağır, exception fırlatan)

## Stil: **multi-method orchestrator**

Bu projede service'ler **tek-aksiyon invokable değil**. Bir aggregate veya use-case
ailesi etrafında **çok sayıda metot toplayan** sınıflardır.

```php
class TenantService
{
    public function create(array $data): Tenant { ... }
    public function update(Tenant $tenant, array $data): Tenant { ... }
    public function suspend(Tenant $tenant): void { ... }
    public function activate(Tenant $tenant): void { ... }
    public function getActiveTenants(): Collection { ... }
}
```

Yeni "action" sınıfı eklemeden önce sor: ilgili use-case için **mevcut bir Service var mı**?
Varsa metot ekle, yeni sınıf açma.

## Dependency Injection

Bağımlılıklar **constructor**'dan, **property promotion** ile:

```php
public function __construct(
    private RendererContract $renderer,
    private CanvasAssetResolver $assets,
    private BrandTokenService $tokens,
) {}
```

Referans: `Modules/Creative/Services/CreativeRenderService.php`

Auth kullanıcısı `auth()->id()` ile alınabilir; ayrıca DI gerekmez.

## DB::transaction

Service metodu **birden fazla tabloyu** değiştiriyorsa:

```php
public function create(array $data): Tenant
{
    return DB::transaction(function () use ($data) {
        $tenant = Tenant::create($data);
        $tenant->priceLists()->createMany($data['price_lists'] ?? []);
        return $tenant;
    });
}
```

Tek satırlık `Model::create()` için transaction'a gerek yok.

## Return tipi

| İçerik | Return |
|---|---|
| Tek model (yeni veya güncel) | `Model` (ör. `Tenant`) — gerekirse `->fresh()` |
| Liste | `Collection` |
| Hiçbir şey döndürmeyen mutasyon | `void` |

DTO **bu projede kullanılmıyor**. Eklemek istiyorsan önce CLAUDE.md güncelle ve
ekibe yaz; tek başına başlatma.

## Hata yönetimi

Beklenen iş kuralı ihlali için **domain exception** fırlat. Genel kuralı taklit etme;
örnek desen `Modules/Creative/Exceptions/PermanentRenderException.php`. Yeni exception
ilgili modülün `Exceptions/` dizinine konulur.

Validation hataları service'e ulaşmaz — Form Request veya controller'da bitmiş olmalı.

## Repository pattern

**Yok.** Sorgular doğrudan Eloquent model + scope üzerinden yapılır
(`Tenant::where('is_active', true)->with('type')->get()` gibi).

Yeni "repository" katmanı **ekleme**; mevcut Service'i kullan veya yeni scope tanımla.

## Checklist

- [ ] Dosya doğru modülün `Services/` dizininde
- [ ] Multi-method orchestrator stili; yeni dosya yerine mevcut Service'e metot ekledim
- [ ] Bağımlılıklar constructor + property promotion
- [ ] Çok-tablolu işlemde `DB::transaction`
- [ ] Return tipi yazıldı, DTO yok
- [ ] Domain exception modülün `Exceptions/` dizininde
- [ ] Repository / generic CRUD katmanı eklenmedi
