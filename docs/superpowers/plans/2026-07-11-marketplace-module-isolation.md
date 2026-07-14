# Marketplace Modül İzolasyonu Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Product ve Tenant modüllerinin Marketplace'e kalan doğrudan bağımlılıklarını (route, controller, model ilişkisi, Vue import) kapatıp tek yönlü bağımlılık (Marketplace → Product, Marketplace → Tenant) elde etmek.

**Architecture:** Marketplace, Product'ın `Category`/`Product` modellerini ve Tenant'ın `Tenant` modelini salt-okunur okumaya devam eder (kaçınılmaz). Product ve Tenant artık Marketplace'in somut sınıflarını asla import etmez — Tenant'ın finans hesaplaması, Marketplace'in yayınladığı `MarketplaceFinancialsContract` arayüzü üzerinden çalışır. Kategori↔pazaryeri eşleme ve ürün↔pazaryeri listeleme yönetim ekranları Product'tan Marketplace'in kendi sayfalarına taşınır.

**Tech Stack:** Laravel 11 (laravel-modules / nwidart), Inertia.js + Vue 3 (`<script setup>`), Pest/PHPUnit (`php artisan test`), spatie/laravel-permission.

## Global Constraints

- Bu PostgreSQL projesi migration disiplinine tabidir (`CLAUDE.md`) — bu plan **hiçbir migration gerektirmiyor** (yalnız kod/route/izin/Vue değişikliği).
- Yeni/taşınan hiçbir route yetkisiz bırakılmayacak — her mutating route `can:marketplace.catalog.manage` ile korunur (bkz. tasarım dokümanı kararı).
- URL yolları tutarlılık için `/marketplace/...` önekine taşınır (eski `/products/...` yolları kaldırılır) — bu bilinçli bir netlik kararıdır, spesifikasyonu ihlal etmez (route SAHİPLİĞİ Marketplace'e geçer, hangi spesifik URL'nin kullanılacağı implementasyon detayıdır).
- Her görev sonunda `php artisan test --filter=<ilgili>` çalıştırılıp yeşil olmalı.
- Spec: `docs/superpowers/specs/2026-07-11-marketplace-module-isolation-design.md`

---

## Task 1: MarketplaceFinancialsContract + MarketplaceFinancialsService + CommissionRateLookup

**Files:**
- Create: `Modules/Marketplace/Services/Contracts/MarketplaceFinancialsContract.php`
- Create: `Modules/Marketplace/Services/CommissionRateLookup.php`
- Create: `Modules/Marketplace/Services/MarketplaceFinancialsService.php`
- Modify: `Modules/Marketplace/Services/AbstractMarketplaceService.php`
- Modify: `Modules/Marketplace/Providers/MarketplaceServiceProvider.php`
- Test: `tests/Feature/Marketplace/MarketplaceFinancialsServiceTest.php`

**Interfaces:**
- Produces: `Modules\Marketplace\Services\Contracts\MarketplaceFinancialsContract` — `salesTotal(int $tenantId, DateTimeInterface $from, DateTimeInterface $to): float`, `expensesTotal(int $tenantId, DateTimeInterface $from, DateTimeInterface $to): float`, `salesByMarketplace(int $tenantId, DateTimeInterface $from, DateTimeInterface $to): array`, `salesByMonth(int $tenantId, int $months): array`, `commissionAndShippingRates(string $marketplace, int $categoryId): array` (returns `[commission_rate, shipping_rate]`). Task 2 ve Task 3 bu arayüzü constructor'dan alacak.
- Produces: `Modules\Marketplace\Services\CommissionRateLookup::rates(string $marketplace, int $categoryId): array` — tek kaynak komisyon/kargo sorgusu.

- [ ] **Step 1: `CommissionRateLookup` sınıfını yaz**

`Modules/Marketplace/Services/CommissionRateLookup.php`:

```php
<?php

namespace Modules\Marketplace\Services;

use Modules\Marketplace\Models\MarketplaceCommissionRate;

/**
 * Komisyon ve kargo oranı lookup'ı: önce kategori-spesifik kayıt, yoksa default
 * (category_id NULL). AbstractMarketplaceService ve MarketplaceFinancialsService
 * ortak kullanır — tek kaynak, kopya sorgu yok.
 */
class CommissionRateLookup
{
    /**
     * @return array{0:float,1:float} [commission_rate, shipping_rate]
     */
    public function rates(string $marketplace, int $categoryId): array
    {
        $today = now()->toDateString();

        $row = MarketplaceCommissionRate::query()
            ->where('marketplace', $marketplace)
            ->where('category_id', $categoryId)
            ->where('valid_from', '<=', $today)
            ->where(function ($q) use ($today) {
                $q->whereNull('valid_until')->orWhere('valid_until', '>=', $today);
            })
            ->orderByDesc('valid_from')
            ->first();

        if (! $row) {
            $row = MarketplaceCommissionRate::query()
                ->where('marketplace', $marketplace)
                ->whereNull('category_id')
                ->where('valid_from', '<=', $today)
                ->where(function ($q) use ($today) {
                    $q->whereNull('valid_until')->orWhere('valid_until', '>=', $today);
                })
                ->orderByDesc('valid_from')
                ->first();
        }

        if (! $row) {
            return [0.0, 0.0];
        }

        return [(float) $row->commission_rate, (float) $row->shipping_rate];
    }
}
```

- [ ] **Step 2: `MarketplaceFinancialsContract` arayüzünü yaz**

`Modules/Marketplace/Services/Contracts/MarketplaceFinancialsContract.php`:

```php
<?php

namespace Modules\Marketplace\Services\Contracts;

use DateTimeInterface;

/**
 * Tenant modülünün pazaryeri satış/gider/komisyon verisine erişimi bu dar
 * arayüz üzerinden olur — Marketplace'in somut tablolarına/modellerine
 * (marketplace_sales, marketplace_expenses, MarketplaceCommissionRate)
 * doğrudan bağımlılık yasak. Implementasyon: MarketplaceFinancialsService.
 */
interface MarketplaceFinancialsContract
{
    public function salesTotal(int $tenantId, DateTimeInterface $from, DateTimeInterface $to): float;

    public function expensesTotal(int $tenantId, DateTimeInterface $from, DateTimeInterface $to): float;

    /**
     * @return array<int, array{marketplace: string, revenue: float, commission: float, net: float}>
     */
    public function salesByMarketplace(int $tenantId, DateTimeInterface $from, DateTimeInterface $to): array;

    /**
     * @return array<int, array{month: string, revenue: float, net: float}>
     */
    public function salesByMonth(int $tenantId, int $months): array;

    /**
     * @return array{0:float,1:float} [commission_rate, shipping_rate]
     */
    public function commissionAndShippingRates(string $marketplace, int $categoryId): array;
}
```

- [ ] **Step 3: `MarketplaceFinancialsService` implementasyonunu yaz**

`Modules/Marketplace/Services/MarketplaceFinancialsService.php`:

```php
<?php

namespace Modules\Marketplace\Services;

use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Modules\Marketplace\Services\Contracts\MarketplaceFinancialsContract;

class MarketplaceFinancialsService implements MarketplaceFinancialsContract
{
    public function __construct(private CommissionRateLookup $rates) {}

    public function salesTotal(int $tenantId, DateTimeInterface $from, DateTimeInterface $to): float
    {
        return (float) DB::table('marketplace_sales')
            ->where('tenant_id', $tenantId)
            ->whereBetween('sold_at', [$from, $to])
            ->sum(DB::raw('sold_price * qty'));
    }

    public function expensesTotal(int $tenantId, DateTimeInterface $from, DateTimeInterface $to): float
    {
        return (float) DB::table('marketplace_expenses')
            ->where('tenant_id', $tenantId)
            ->whereBetween('occurred_at', [$from, $to])
            ->sum('amount');
    }

    public function salesByMarketplace(int $tenantId, DateTimeInterface $from, DateTimeInterface $to): array
    {
        return DB::table('marketplace_sales')
            ->where('tenant_id', $tenantId)
            ->whereBetween('sold_at', [$from, $to])
            ->select(
                'marketplace',
                DB::raw('SUM(sold_price * qty) as revenue'),
                DB::raw('SUM(commission) as commission'),
                DB::raw('SUM(net_revenue) as net'),
            )
            ->groupBy('marketplace')
            ->get()
            ->map(fn ($r) => [
                'marketplace' => $r->marketplace,
                'revenue'     => (float) $r->revenue,
                'commission'  => (float) $r->commission,
                'net'         => (float) $r->net,
            ])
            ->all();
    }

    public function salesByMonth(int $tenantId, int $months): array
    {
        $since = now()->subMonths($months)->startOfMonth();
        $driver = DB::connection()->getDriverName();
        $monthExpr = match ($driver) {
            'pgsql'             => "to_char(sold_at, 'YYYY-MM')",
            'mysql', 'mariadb'  => "DATE_FORMAT(sold_at, '%Y-%m')",
            default             => "strftime('%Y-%m', sold_at)",
        };

        return DB::table('marketplace_sales')
            ->where('tenant_id', $tenantId)
            ->where('sold_at', '>=', $since)
            ->select(
                DB::raw("$monthExpr as month"),
                DB::raw('SUM(sold_price * qty) as revenue'),
                DB::raw('SUM(net_revenue) as net'),
            )
            ->groupBy(DB::raw($monthExpr))
            ->orderBy('month')
            ->get()
            ->map(fn ($r) => [
                'month'   => (string) $r->month,
                'revenue' => (float) $r->revenue,
                'net'     => (float) $r->net,
            ])
            ->all();
    }

    public function commissionAndShippingRates(string $marketplace, int $categoryId): array
    {
        return $this->rates->rates($marketplace, $categoryId);
    }
}
```

- [ ] **Step 4: `AbstractMarketplaceService::lookupRates()`'i `CommissionRateLookup`'a delege et**

`Modules/Marketplace/Services/AbstractMarketplaceService.php` — dosyanın başındaki import satırını kaldır ve `lookupRates` gövdesini değiştir:

Kaldır (satır 8):
```php
use Modules\Marketplace\Models\MarketplaceCommissionRate;
```

Değiştir — eski (satır 147-178, tüm metot gövdesi):
```php
    protected function lookupRates(string $marketplace, int $categoryId): array
    {
        $today = now()->toDateString();

        $row = MarketplaceCommissionRate::query()
            ->where('marketplace', $marketplace)
            ->where('category_id', $categoryId)
            ->where('valid_from', '<=', $today)
            ->where(function ($q) use ($today) {
                $q->whereNull('valid_until')->orWhere('valid_until', '>=', $today);
            })
            ->orderByDesc('valid_from')
            ->first();

        if (! $row) {
            $row = MarketplaceCommissionRate::query()
                ->where('marketplace', $marketplace)
                ->whereNull('category_id')
                ->where('valid_from', '<=', $today)
                ->where(function ($q) use ($today) {
                    $q->whereNull('valid_until')->orWhere('valid_until', '>=', $today);
                })
                ->orderByDesc('valid_from')
                ->first();
        }

        if (! $row) {
            return [0.0, 0.0];
        }

        return [(float) $row->commission_rate, (float) $row->shipping_rate];
    }
```

Yeni:
```php
    /**
     * Komisyon ve kargo oranı lookup'ı — tek kaynak: CommissionRateLookup.
     *
     * @return array{0:float,1:float} [commission_rate, shipping_rate]
     */
    protected function lookupRates(string $marketplace, int $categoryId): array
    {
        return app(CommissionRateLookup::class)->rates($marketplace, $categoryId);
    }
```

- [ ] **Step 5: `MarketplaceServiceProvider`'a contract binding'i ekle**

`Modules/Marketplace/Providers/MarketplaceServiceProvider.php` — `boot()` metodundan önce `register()` ekle:

```php
<?php

namespace Modules\Marketplace\Providers;

use Modules\Marketplace\Services\Contracts\MarketplaceFinancialsContract;
use Modules\Marketplace\Services\MarketplaceFinancialsService;
use Nwidart\Modules\Support\ModuleServiceProvider;

class MarketplaceServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Marketplace';

    protected string $nameLower = 'marketplace';

    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    public function register(): void
    {
        parent::register();

        $this->app->bind(MarketplaceFinancialsContract::class, MarketplaceFinancialsService::class);
    }

    public function boot(): void
    {
        parent::boot();

        // Inertia testing view-finder'a "Marketplace::" namespace hint'i tanıt ki
        // assertInertia(component('Marketplace::Portal/Marketplace/Index')) çağrısı
        // modül sayfalarını dosya sisteminde bulabilsin.
        $this->app->resolving('inertia.testing.view-finder', function ($finder) {
            $finder->addNamespace('Marketplace', module_path('Marketplace', 'Resources/assets/js/Pages'));
        });
    }
}
```

- [ ] **Step 6: Testi yaz**

`tests/Feature/Marketplace/MarketplaceFinancialsServiceTest.php`:

```php
<?php

namespace Tests\Feature\Marketplace;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Marketplace\Models\MarketplaceExpense;
use Modules\Marketplace\Models\MarketplaceSale;
use Modules\Marketplace\Services\Contracts\MarketplaceFinancialsContract;
use Modules\Tenant\Models\Tenant;
use Tests\TestCase;

class MarketplaceFinancialsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_total_sums_price_times_qty_for_tenant(): void
    {
        $tenant = Tenant::factory()->create();

        MarketplaceSale::create([
            'tenant_id' => $tenant->id, 'marketplace' => 'trendyol',
            'external_order_id' => 'TY-1', 'external_line_id' => 'L-1',
            'sold_price' => 100, 'qty' => 2, 'net_revenue' => 180,
            'status' => 'delivered', 'sold_at' => now()->subDays(5),
        ]);

        $svc = app(MarketplaceFinancialsContract::class);
        $total = $svc->salesTotal($tenant->id, now()->subMonth(), now()->addDay());

        $this->assertSame(200.0, $total);
    }

    public function test_expenses_total_sums_amount_for_tenant(): void
    {
        $tenant = Tenant::factory()->create();

        MarketplaceExpense::create([
            'tenant_id' => $tenant->id, 'marketplace' => 'trendyol',
            'expense_type' => 'commission', 'amount' => 20,
            'occurred_at' => now()->subDays(5),
        ]);

        $svc = app(MarketplaceFinancialsContract::class);
        $total = $svc->expensesTotal($tenant->id, now()->subMonth(), now()->addDay());

        $this->assertSame(20.0, $total);
    }

    public function test_commission_and_shipping_rates_delegates_to_lookup(): void
    {
        \Modules\Marketplace\Models\MarketplaceCommissionRate::create([
            'marketplace' => 'trendyol', 'category_id' => null,
            'commission_rate' => 15.0, 'shipping_rate' => 3.0,
            'valid_from' => '2026-01-01',
        ]);

        $svc = app(MarketplaceFinancialsContract::class);
        [$commission, $shipping] = $svc->commissionAndShippingRates('trendyol', 999999);

        $this->assertSame(15.0, $commission);
        $this->assertSame(3.0, $shipping);
    }
}
```

- [ ] **Step 7: Testleri çalıştır**

Run: `php artisan test tests/Feature/Marketplace/MarketplaceFinancialsServiceTest.php tests/Feature/Marketplace/CommissionLookupTest.php`
Expected: tüm testler PASS (CommissionLookupTest, `lookupRates`'in delege sonrası aynı davrandığını doğrular).

- [ ] **Step 8: Commit**

```bash
git add Modules/Marketplace/Services/Contracts/MarketplaceFinancialsContract.php Modules/Marketplace/Services/CommissionRateLookup.php Modules/Marketplace/Services/MarketplaceFinancialsService.php Modules/Marketplace/Services/AbstractMarketplaceService.php Modules/Marketplace/Providers/MarketplaceServiceProvider.php tests/Feature/Marketplace/MarketplaceFinancialsServiceTest.php
git commit -m "feat(marketplace): add MarketplaceFinancialsContract + shared commission lookup"
```

---

## Task 2: TenantFinancialsService'i contract'a bağla

**Files:**
- Modify: `Modules/Tenant/Services/TenantFinancialsService.php`
- Test (var olan, değişmeden geçmeli): `tests/Feature/Tenant/Financials/TenantFinancialsServiceTest.php`

**Interfaces:**
- Consumes: `Modules\Marketplace\Services\Contracts\MarketplaceFinancialsContract` (Task 1)

- [ ] **Step 1: `TenantFinancialsService`'i yeniden yaz**

`Modules/Tenant/Services/TenantFinancialsService.php` — dosyanın tamamını değiştir:

```php
<?php

namespace Modules\Tenant\Services;

use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Modules\Marketplace\Services\Contracts\MarketplaceFinancialsContract;
use Modules\Tenant\Models\Tenant;

/**
 * Pazaryeri satışları + giderleri + faturalar üzerinden tenant'ın P&L tablosu.
 * Pazaryeri verisi MarketplaceFinancialsContract üzerinden okunur — bu servis
 * Marketplace'in tablolarını/modellerini doğrudan bilmez.
 */
class TenantFinancialsService
{
    public function __construct(private MarketplaceFinancialsContract $marketplace) {}

    public function summary(Tenant $tenant, DateTimeInterface $from, DateTimeInterface $to): array
    {
        $sales    = $this->marketplace->salesTotal($tenant->id, $from, $to);
        $expenses = $this->marketplace->expensesTotal($tenant->id, $from, $to);

        $invoiced = (float) DB::table('tenant_invoices')
            ->where('tenant_id', $tenant->id)
            ->whereBetween('created_at', [$from, $to])
            ->sum('amount');

        $net = $sales - $expenses - $invoiced;

        return [
            'sales_total'    => round($sales, 2),
            'expenses_total' => round($expenses, 2),
            'invoiced_total' => round($invoiced, 2),
            'net'            => round($net, 2),
            'from'           => $from->format('Y-m-d'),
            'to'             => $to->format('Y-m-d'),
        ];
    }

    public function byMarketplace(Tenant $tenant, DateTimeInterface $from, DateTimeInterface $to): array
    {
        return $this->marketplace->salesByMarketplace($tenant->id, $from, $to);
    }

    public function byMonth(Tenant $tenant, int $months = 12): array
    {
        return $this->marketplace->salesByMonth($tenant->id, $months);
    }
}
```

- [ ] **Step 2: Var olan testi çalıştır (regresyon olmadığını doğrula)**

Run: `php artisan test tests/Feature/Tenant/Financials/TenantFinancialsServiceTest.php`
Expected: `test_summary_sums_sales_expenses_invoices_correctly` ve `test_excludes_other_tenants` PASS — kod değişmeden geçmeli çünkü test `app(TenantFinancialsService::class)` ile container-resolve ediyor ve `MarketplaceFinancialsContract` Task 1'de bind edildi.

- [ ] **Step 3: Portal Financials/ProfitCalculator sayfalarının bu servisi kullanan testlerini de çalıştır**

Run: `php artisan test --filter=Financials`
Expected: tüm testler PASS.

- [ ] **Step 4: Commit**

```bash
git add Modules/Tenant/Services/TenantFinancialsService.php
git commit -m "refactor(tenant): TenantFinancialsService artık MarketplaceFinancialsContract kullanıyor"
```

---

## Task 3: ProfitCalculatorService'i contract'a bağla

**Files:**
- Modify: `Modules/Tenant/Services/ProfitCalculatorService.php`
- Test (var olan, değişmeden geçmeli): `tests/Feature/Tenant/Financials/ProfitCalculatorTest.php`, `tests/Feature/Marketplace/CommissionLookupTest.php`

**Interfaces:**
- Consumes: `Modules\Marketplace\Services\Contracts\MarketplaceFinancialsContract::commissionAndShippingRates()` (Task 1)

- [ ] **Step 1: `ProfitCalculatorService`'i yeniden yaz**

`Modules/Tenant/Services/ProfitCalculatorService.php` — dosyanın tamamını değiştir:

```php
<?php

namespace Modules\Tenant\Services;

use Modules\Marketplace\Services\Contracts\MarketplaceFinancialsContract;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductVariant;
use Modules\Tenant\Models\Tenant;
use Modules\Tenant\Services\DTOs\ProfitBreakdown;

/**
 * "Bu ürünü Y pazaryerinde Z TL'ye satarsam ne kazanırım?" hesabı.
 *
 * Pure service — persistence yok. Lookup:
 *  - our_cost: products.purchase_price (ana firma maliyet)
 *  - tenant_cost: TenantAccessService::priceFor (bayinin bizden aldığı fiyat)
 *  - commission/shipping: MarketplaceFinancialsContract (kategori-spesifik → default)
 *  - vat: tenant.settings['vat_rate'] (default 18)
 */
class ProfitCalculatorService
{
    public function __construct(
        private TenantAccessService $access,
        private MarketplaceFinancialsContract $marketplace,
    ) {}

    public function calculate(
        Tenant $tenant,
        Product $product,
        ?ProductVariant $variant,
        string $marketplace,
        float $sellPrice,
        int $qty = 1,
    ): ProfitBreakdown {
        $ourCost    = (float) ($product->purchase_price ?? 0);
        $tenantCost = $this->access->priceFor($tenant, $product, $variant);

        [$commissionRate, $shippingRate] = $this->marketplace->commissionAndShippingRates(
            $marketplace,
            (int) ($product->category_id ?? 0),
        );

        $gross      = $sellPrice * $qty;
        $commission = $gross * ($commissionRate / 100);
        $shipping   = $gross * ($shippingRate / 100);

        // VAT — tenant ayarlarından flat oran (default 18%).
        $settings = $tenant->settings ?? [];
        $vatRate  = (float) ($settings['vat_rate'] ?? 18);
        $vat      = $gross * ($vatRate / 100) / (1 + $vatRate / 100); // KDV dahil fiyattan KDV çıkar

        $costTotal = ($tenantCost * $qty) + $commission + $shipping + $vat;
        $netProfit = $gross - $costTotal;
        $marginPct = $gross > 0 ? ($netProfit / $gross) * 100 : 0;

        return new ProfitBreakdown(
            ourCost: $ourCost * $qty,
            tenantCost: $tenantCost * $qty,
            sellPrice: $gross,
            qty: $qty,
            commission: $commission,
            shipping: $shipping,
            vat: $vat,
            netProfit: $netProfit,
            marginPct: $marginPct,
            marketplace: $marketplace,
        );
    }
}
```

Not: `lookupRates()` private metodu tamamen kaldırıldı (contract'a taşındı); `use Modules\Marketplace\Models\MarketplaceCommissionRate;` importu artık yok.

- [ ] **Step 2: Var olan testleri çalıştır**

Run: `php artisan test tests/Feature/Tenant/Financials/ProfitCalculatorTest.php tests/Feature/Marketplace/CommissionLookupTest.php`
Expected: tüm testler PASS (davranış birebir aynı, sadece sorgu Marketplace'in kendi contract'ı üzerinden çalışıyor).

- [ ] **Step 3: Commit**

```bash
git add Modules/Tenant/Services/ProfitCalculatorService.php
git commit -m "refactor(tenant): ProfitCalculatorService artık MarketplaceFinancialsContract kullanıyor"
```

---

## Task 4: Yeni izin — marketplace.catalog.manage

**Files:**
- Modify: `Modules/Marketplace/database/seeders/MarketplacePermissionSeeder.php`
- Modify: `Modules/Product/database/seeders/ProductPermissionSeeder.php`
- Test: `tests/Feature/Marketplace/MarketplaceCatalogPermissionTest.php`

- [ ] **Step 1: `marketplace.catalog.manage` iznini ekle — yalnız superadmin'e ata**

`Modules/Marketplace/database/seeders/MarketplacePermissionSeeder.php` — `$permissions` dizisine ekle ve superadmin-only ata:

```php
<?php

namespace Modules\Marketplace\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Pazaryeri (Marketplace) modülünün izin kataloğu + role atamaları (idempotent).
 *
 * RolePermissionSeeder tarafından, roller oluşturulduktan SONRA çağrılır.
 * Atamalar additif (givePermissionTo) olduğundan tekrar çalıştırmak güvenlidir.
 * Superadmin ayrıca Gate::before ile tüm yetenekleri geçer; açık atama netlik içindir.
 */
class MarketplacePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'marketplace.manage'         => 'Pazaryeri Bağlantı Yönet',
            'marketplace.sync'           => 'Portal — Pazaryeri Senkronizasyon',
            'marketplace.view-sales'     => 'Portal — Pazaryeri Satışları Görüntüle',
            'marketplace.catalog.manage' => 'İç Katalog — Kategori/Ürün Pazaryeri Eşleme Yönet',
        ];

        $created = [];
        foreach ($permissions as $name => $displayName) {
            $perm = Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                ['display_name' => $displayName],
            );
            if (! $perm->wasRecentlyCreated && $perm->display_name !== $displayName) {
                $perm->update(['display_name' => $displayName]);
            }
            $created[] = $perm->name;
        }

        $superadmin = Role::where('name', 'superadmin')->where('guard_name', 'web')->first();
        if ($superadmin) {
            $superadmin->givePermissionTo($created);
        }

        // Tenant rolü kendi pazaryeri credential'larını yönetir + portal satış/senkron işlerini yapar.
        // marketplace.catalog.manage BUNA dahil DEĞİL — iç katalog yönetimi (kategori eşleme,
        // ürün listeleme) yalnız superadmin'e özgü; tenant kullanıcısına verilirse iç admin
        // ekranlarına erişim sızar.
        $tenantRole = Role::where('name', 'tenant')->where('guard_name', 'web')->first();
        if ($tenantRole) {
            $tenantRole->givePermissionTo(['marketplace.manage', 'marketplace.sync', 'marketplace.view-sales']);
        }
    }
}
```

- [ ] **Step 2: `category.manage` görünen adını güncelle (pazaryeri eşleme artık ona ait değil)**

`Modules/Product/database/seeders/ProductPermissionSeeder.php` — `$permissions` dizisindeki tek satırı değiştir:

Eski:
```php
            'category.manage'     => 'Kategori & Pazaryeri Eşleme Yönet',
```

Yeni:
```php
            'category.manage'     => 'Kategori Yönet',
```

- [ ] **Step 3: Seeder'ları çalıştır ve izin atamalarını doğrula**

Run:
```bash
php artisan tinker --execute="
(new Modules\Marketplace\Database\Seeders\MarketplacePermissionSeeder())->run();
\$super = Spatie\Permission\Models\Role::where('name','superadmin')->where('guard_name','web')->first();
\$tenant = Spatie\Permission\Models\Role::where('name','tenant')->where('guard_name','web')->first();
echo 'superadmin: ' . (\$super->hasPermissionTo('marketplace.catalog.manage') ? 'VAR' : 'YOK') . PHP_EOL;
echo 'tenant: ' . (\$tenant->hasPermissionTo('marketplace.catalog.manage') ? 'VAR (HATA)' : 'yok (doğru)') . PHP_EOL;
"
```
Expected: `superadmin: VAR`, `tenant: yok (doğru)`.

- [ ] **Step 4: Testi yaz**

`tests/Feature/Marketplace/MarketplaceCatalogPermissionTest.php`:

```php
<?php

namespace Tests\Feature\Marketplace;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Marketplace\Database\Seeders\MarketplacePermissionSeeder;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MarketplaceCatalogPermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_superadmin_role_gets_catalog_manage_permission(): void
    {
        Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'tenant', 'guard_name' => 'web']);

        (new MarketplacePermissionSeeder())->run();

        $superadmin = Role::where('name', 'superadmin')->where('guard_name', 'web')->first();
        $tenant     = Role::where('name', 'tenant')->where('guard_name', 'web')->first();

        $this->assertTrue($superadmin->hasPermissionTo('marketplace.catalog.manage'));
        $this->assertFalse($tenant->hasPermissionTo('marketplace.catalog.manage'));
        $this->assertTrue($tenant->hasPermissionTo('marketplace.manage'));
    }
}
```

- [ ] **Step 5: Testi çalıştır**

Run: `php artisan test tests/Feature/Marketplace/MarketplaceCatalogPermissionTest.php`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add Modules/Marketplace/database/seeders/MarketplacePermissionSeeder.php Modules/Product/database/seeders/ProductPermissionSeeder.php tests/Feature/Marketplace/MarketplaceCatalogPermissionTest.php
git commit -m "feat(marketplace): marketplace.catalog.manage izni ekle (yalnız superadmin)"
```

---

## Task 5: Marketplace'e CategoryMappingController + route'lar (ekle, henüz eskisini silme)

**Files:**
- Create: `Modules/Marketplace/Http/Controllers/CategoryMappingController.php`
- Modify: `Modules/Marketplace/routes/web.php`
- Test: `tests/Feature/Marketplace/CategoryMappingTest.php`

**Interfaces:**
- Consumes: `Modules\Product\Models\Category` (salt-okunur), `Modules\Marketplace\Models\{Marketplace,CategoryMarketplaceMapping}`
- Produces: route'lar `marketplace.categories.index`, `marketplace.categories.connect`, `marketplace.categories.mappings.store`, `marketplace.categories.mappings.destroy`

- [ ] **Step 1: `CategoryMappingController`'ı yaz**

`Modules/Marketplace/Http/Controllers/CategoryMappingController.php`:

```php
<?php

namespace Modules\Marketplace\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Marketplace\Models\CategoryMarketplaceMapping;
use Modules\Marketplace\Models\Marketplace;
use Modules\Product\Models\Category;

class CategoryMappingController extends Controller
{
    public function index(): Response
    {
        $marketplaces = Marketplace::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $mappingsByCategory = CategoryMarketplaceMapping::query()->get()->groupBy('category_id');

        $categories = Category::query()
            ->with('parent:id,name')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(function (Category $c) use ($marketplaces, $mappingsByCategory) {
                $mine = $mappingsByCategory->get($c->id, collect());

                $mpByKey = [];
                foreach ($marketplaces as $mp) {
                    $mapping = $mine->firstWhere('marketplace_id', $mp->id);
                    $mpByKey[$mp->key] = $mapping
                        ? [
                            'mapped'         => true,
                            'categoryPath'   => $mapping->category_path,
                            'externalId'     => $mapping->external_id,
                            'syncedProducts' => $mapping->synced_products,
                            'lastSync'       => optional($mapping->last_synced_at)?->diffForHumans() ?? '—',
                        ]
                        : ['mapped' => false];
                }

                return [
                    'id'           => $c->id,
                    'name'         => $c->name,
                    'slug'         => $c->slug,
                    'parent'       => $c->parent?->name ?? '—',
                    'parent_id'    => $c->parent_id,
                    'icon'         => $c->icon ?? '📦',
                    'productCount' => 0,
                    'status'       => $c->status,
                    'updatedAt'    => optional($c->updated_at)->format('Y-m-d'),
                    'marketplaces' => $mpByKey,
                ];
            });

        return Inertia::render('Marketplace::CategoryMapping', [
            'categories'   => $categories,
            'marketplaces' => $marketplaces->map(fn (Marketplace $m) => [
                'id'        => $m->id,
                'key'       => $m->key,
                'name'      => $m->name,
                'logoText'  => $m->logo_text,
                'color'     => $m->color,
                'connected' => $m->connected,
            ]),
        ]);
    }

    public function connect(Marketplace $marketplace): RedirectResponse
    {
        $marketplace->update(['connected' => true]);

        return redirect()->route('marketplace.categories.index')
            ->with('success', "{$marketplace->name} bağlandı.");
    }

    public function storeMapping(Request $request, Category $category, Marketplace $marketplace): RedirectResponse
    {
        $data = $request->validate([
            'category_path' => ['required', 'string', 'max:255'],
            'external_id'   => ['nullable', 'string', 'max:64'],
        ]);

        CategoryMarketplaceMapping::updateOrCreate(
            ['category_id' => $category->id, 'marketplace_id' => $marketplace->id],
            [
                'category_path'  => $data['category_path'],
                'external_id'    => $data['external_id'] ?? 'auto-' . random_int(1000, 9999),
                'last_synced_at' => now(),
            ],
        );

        return redirect()->route('marketplace.categories.index')
            ->with('success', "{$category->name} → {$marketplace->name} eşleştirildi.");
    }

    public function destroyMapping(Category $category, Marketplace $marketplace): RedirectResponse
    {
        CategoryMarketplaceMapping::query()
            ->where('category_id', $category->id)
            ->where('marketplace_id', $marketplace->id)
            ->delete();

        return redirect()->route('marketplace.categories.index')
            ->with('success', "{$category->name} → {$marketplace->name} eşleştirmesi kaldırıldı.");
    }
}
```

- [ ] **Step 2: Route'ları ekle**

`Modules/Marketplace/routes/web.php` — dosyanın tamamını değiştir:

```php
<?php

use Illuminate\Support\Facades\Route;
use Modules\Marketplace\Http\Controllers\CategoryMappingController;
use Modules\Marketplace\Http\Controllers\TenantMarketplaceController;

Route::middleware(['auth', 'verified'])->group(function () {
    // ─── Pazaryeri Bağlantıları ──────────────────────────────────────────
    // (literal /tenants/{tenant}/marketplace yolları — /tenants/{tenant} catch-all'dan önce)
    // Permission tek başına yetmez: controller'da scope check (superadmin tümü, tenant kullanıcı kendisi).
    Route::prefix('tenants/{tenant}/marketplace')->name('tenants.marketplace.')
        ->middleware('can:marketplace.manage')->whereNumber('tenant')
        ->group(function () {
            Route::get('/', [TenantMarketplaceController::class, 'index'])->name('index');
            Route::post('/', [TenantMarketplaceController::class, 'store'])->name('store');
            Route::put('/{credential}', [TenantMarketplaceController::class, 'update'])
                ->whereNumber('credential')->name('update');
            Route::post('/{credential}/toggle', [TenantMarketplaceController::class, 'toggle'])
                ->whereNumber('credential')->name('toggle');
            Route::delete('/{credential}', [TenantMarketplaceController::class, 'destroy'])
                ->whereNumber('credential')->name('destroy');
        });

    // ─── İç Katalog: Kategori ↔ Pazaryeri Eşleme ─────────────────────────
    // Product'ın Kategoriler sayfasından taşındı — iç/admin-only, marketplace.catalog.manage.
    Route::prefix('marketplace/categories')->name('marketplace.categories.')->group(function () {
        Route::get('/', [CategoryMappingController::class, 'index'])->name('index');
        Route::post('/marketplaces/{marketplace}/connect', [CategoryMappingController::class, 'connect'])
            ->middleware('can:marketplace.catalog.manage')->name('connect');
        Route::post('/{category}/marketplaces/{marketplace}', [CategoryMappingController::class, 'storeMapping'])
            ->middleware('can:marketplace.catalog.manage')->name('mappings.store');
        Route::delete('/{category}/marketplaces/{marketplace}', [CategoryMappingController::class, 'destroyMapping'])
            ->middleware('can:marketplace.catalog.manage')->name('mappings.destroy');
    });
});
```

- [ ] **Step 3: Testi yaz**

`tests/Feature/Marketplace/CategoryMappingTest.php`:

```php
<?php

namespace Tests\Feature\Marketplace;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Marketplace\Database\Seeders\MarketplacePermissionSeeder;
use Modules\Marketplace\Models\CategoryMarketplaceMapping;
use Modules\Marketplace\Models\Marketplace;
use Modules\Product\Models\Category;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CategoryMappingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $role = Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);
        $this->admin = User::factory()->create();
        $this->admin->assignRole($role);
        (new MarketplacePermissionSeeder())->run();
    }

    private function makeCategory(): Category
    {
        return Category::create([
            'name' => 'Hırka', 'slug' => 'hirka-' . uniqid(), 'status' => 'active', 'sort_order' => 0,
        ]);
    }

    public function test_index_page_renders(): void
    {
        $this->actingAs($this->admin)
            ->get('/marketplace/categories')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Marketplace::CategoryMapping', false));
    }

    public function test_store_mapping_creates_mapping(): void
    {
        $category = $this->makeCategory();
        $mp = Marketplace::create(['key' => 'n11', 'name' => 'N11', 'logo_text' => 'n11', 'color' => '#f5a623']);

        $this->actingAs($this->admin)
            ->post("/marketplace/categories/{$category->id}/marketplaces/{$mp->id}", [
                'category_path' => 'Giyim > Üst Giyim > Hırka',
            ])
            ->assertRedirect(route('marketplace.categories.index'));

        $this->assertDatabaseHas('category_marketplace_mappings', [
            'category_id' => $category->id,
            'marketplace_id' => $mp->id,
            'category_path' => 'Giyim > Üst Giyim > Hırka',
        ]);
    }

    public function test_destroy_mapping_removes_mapping(): void
    {
        $category = $this->makeCategory();
        $mp = Marketplace::create(['key' => 'n11', 'name' => 'N11', 'logo_text' => 'n11', 'color' => '#f5a623']);
        CategoryMarketplaceMapping::create([
            'category_id' => $category->id, 'marketplace_id' => $mp->id,
            'category_path' => 'X', 'external_id' => 'auto-1',
        ]);

        $this->actingAs($this->admin)
            ->delete("/marketplace/categories/{$category->id}/marketplaces/{$mp->id}")
            ->assertRedirect(route('marketplace.categories.index'));

        $this->assertDatabaseMissing('category_marketplace_mappings', [
            'category_id' => $category->id, 'marketplace_id' => $mp->id,
        ]);
    }

    public function test_store_mapping_requires_catalog_manage_permission(): void
    {
        $category = $this->makeCategory();
        $mp = Marketplace::create(['key' => 'n11', 'name' => 'N11', 'logo_text' => 'n11', 'color' => '#f5a623']);
        $plainUser = User::factory()->create();

        $this->actingAs($plainUser)
            ->post("/marketplace/categories/{$category->id}/marketplaces/{$mp->id}", [
                'category_path' => 'X',
            ])
            ->assertForbidden();
    }
}
```

- [ ] **Step 4: Testleri çalıştır**

Run: `php artisan test tests/Feature/Marketplace/CategoryMappingTest.php`
Expected: 4 test PASS.

- [ ] **Step 5: Commit**

```bash
git add Modules/Marketplace/Http/Controllers/CategoryMappingController.php Modules/Marketplace/routes/web.php tests/Feature/Marketplace/CategoryMappingTest.php
git commit -m "feat(marketplace): CategoryMappingController + route'lar ekle (Product'takiler henüz duruyor)"
```

---

## Task 6: Marketplace::CategoryMapping.vue sayfasını oluştur

**Files:**
- Create: `Modules/Marketplace/Resources/assets/js/Pages/CategoryMapping.vue`

**Interfaces:**
- Consumes: `Modules\Marketplace\Http\Controllers\CategoryMappingController` route'ları (Task 5), var olan bileşenler `Modules/Marketplace/Resources/assets/js/Components/{MarketplaceConnectDrawer,MarketplaceCategoryPickerModal}.vue`

- [ ] **Step 1: Sayfayı yaz**

`Modules/Marketplace/Resources/assets/js/Pages/CategoryMapping.vue`:

```vue
<template>
	<Head title="Kategori ↔ Pazaryeri Eşleştirme" />
	<div class="page-category-mapping">
		<Breadcrumb
			:items="[
				{ label: 'Ana Sayfa', to: '/workflow', icon: 'home' },
				{ label: 'Pazaryeri' },
				{ label: 'Kategori Eşleştirme' },
			]"
		/>

		<div class="page-header">
			<div>
				<h1 class="page-title">Kategori ↔ Pazaryeri Eşleştirme</h1>
				<p class="page-subtitle">Kategorileri pazaryeri entegrasyonlarıyla eşleştir</p>
			</div>
		</div>

		<div class="stats-grid">
			<div class="stat-card">
				<div class="stat-icon" style="background: rgb(var(--color-primary-soft))">📂</div>
				<div class="stat-content">
					<div class="stat-label">Toplam Kategori</div>
					<div class="stat-value">{{ stats.total }}</div>
				</div>
			</div>
			<div class="stat-card">
				<div class="stat-icon" style="background: rgb(var(--color-primary-soft))">🏪</div>
				<div class="stat-content">
					<div class="stat-label">Aktif Pazaryeri</div>
					<div class="stat-value">{{ stats.activeMarketplaces }} / {{ marketplaces.length }}</div>
				</div>
			</div>
			<div class="stat-card">
				<div class="stat-icon" style="background: #fff7ed">🔗</div>
				<div class="stat-content">
					<div class="stat-label">Ortalama Eşleştirme</div>
					<div class="stat-value">{{ stats.mappingRate }}%</div>
				</div>
			</div>
		</div>

		<div class="card">
			<div class="card-header">
				<h3>Kategori Listesi</h3>
				<div class="card-search">
					<svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
						<circle cx="11" cy="11" r="8" /><path d="M21 21l-4.35-4.35" />
					</svg>
					<input v-model="searchQuery" type="text" placeholder="Kategori ara..." />
				</div>
				<CustomSelect
					v-model="parentFilter"
					:options="parentFilterOptions"
					:show-label="false"
					style="width: 160px"
				/>
				<CustomSelect
					v-model="statusFilter"
					:options="statusFilterOptions"
					:show-label="false"
					style="width: 130px"
				/>
			</div>

			<table class="data-table">
				<thead>
					<tr>
						<th style="width: 30%">Kategori</th>
						<th style="width: 15%">Durum</th>
						<th style="width: 55%">Pazaryeri Entegrasyonları</th>
					</tr>
				</thead>
				<tbody>
					<tr v-if="paginated.length === 0">
						<td colspan="3" class="empty-row">Kayıt bulunamadı</td>
					</tr>
					<tr v-for="cat in paginated" :key="cat.id">
						<td>
							<div class="cat-cell">
								<div class="cat-icon">{{ cat.icon }}</div>
								<div class="cat-info">
									<span class="cat-name">{{ cat.name }}</span>
									<span class="cat-parent">{{ cat.parent }}</span>
								</div>
							</div>
						</td>
						<td>
							<span class="status-pill" :class="`status-${cat.status}`">
								<span class="dot"></span>
								{{ cat.status === 'active' ? 'Aktif' : 'Pasif' }}
							</span>
						</td>
						<td>
							<div class="mp-logos">
								<button
									v-for="mp in marketplaces"
									:key="mp.key"
									class="mp-chip"
									:class="mpChipClass(cat, mp)"
									:style="mpChipStyle(cat, mp)"
									@click="openMarketplaceCheck(cat, mp)"
									:title="mpTooltip(cat, mp)"
								>
									{{ mp.logoText }}
									<span
										v-if="cat.marketplaces[mp.key]?.mapped && mp.connected"
										class="mp-dot mp-dot-success"
									></span>
									<span
										v-else-if="!mp.connected"
										class="mp-dot mp-dot-error"
									></span>
								</button>
							</div>
						</td>
					</tr>
				</tbody>
			</table>

			<div class="pagination">
				<div class="pagination-info">
					Toplam <span>{{ filtered.length }}</span> kategori
					(<span>{{ startIndex }}</span>-<span>{{ endIndex }}</span> arası)
				</div>
				<div class="pagination-controls">
					<button class="pagination-btn" :disabled="currentPage === 1" @click="currentPage--">‹</button>
					<button
						v-for="page in visiblePages"
						:key="page"
						class="pagination-btn"
						:class="{ active: currentPage === page }"
						@click="currentPage = page"
					>{{ page }}</button>
					<button class="pagination-btn" :disabled="currentPage === totalPages || totalPages === 0" @click="currentPage++">›</button>
				</div>
			</div>
		</div>

		<MarketplaceConnectDrawer
			v-model="connectDrawerOpen"
			:marketplace="connectingMarketplace"
			:category="connectingCategory"
			@submit="handleConnectSubmit"
		/>

		<MarketplaceCategoryPickerModal
			v-model="mapperOpen"
			:category="mappingCategory"
			:marketplace="mappingMarketplace"
			:tree="mappingMarketplace?.categoryTree || []"
			:initial-path="mappingInitialPath"
			@submit="handleMapperSubmit"
		/>
	</div>
</template>

<script setup>
import { ref, computed, inject, watch, onBeforeUnmount } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Breadcrumb from '@/Components/Breadcrumb.vue'
import CustomSelect from '@/Components/CustomSelect.vue'
import MarketplaceConnectDrawer from '../Components/MarketplaceConnectDrawer.vue'
import MarketplaceCategoryPickerModal from '../Components/MarketplaceCategoryPickerModal.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
	categories: { type: Array, default: () => [] },
	marketplaces: { type: Array, default: () => [] },
})

const showToast = inject('showToast')
const $swal = inject('$swal')

const categories = ref(props.categories.map((c) => ({ ...c })))
const marketplaces = ref(props.marketplaces.map((m) => ({ ...m })))

watch(
	() => props.categories,
	(list) => { categories.value = list.map((c) => ({ ...c })) },
	{ deep: true },
)
watch(
	() => props.marketplaces,
	(list) => { marketplaces.value = list.map((m) => ({ ...m })) },
	{ deep: true },
)

const connectDrawerOpen = ref(false)
const connectingMarketplace = ref(null)
const connectingCategory = ref(null)

const mapperOpen = ref(false)
const mappingCategory = ref(null)
const mappingMarketplace = ref(null)
const mappingInitialPath = ref('')
const mappingCurrent = ref(null)

watch(connectDrawerOpen, (open) => {
	document.body.style.overflow = open ? 'hidden' : ''
})
onBeforeUnmount(() => { document.body.style.overflow = '' })

const stats = computed(() => {
	const totalCells = categories.value.length * marketplaces.value.length
	const mappedCells = categories.value.reduce((acc, c) => {
		return acc + marketplaces.value.filter((mp) => c.marketplaces[mp.key]?.mapped).length
	}, 0)
	return {
		total: categories.value.length,
		activeMarketplaces: marketplaces.value.filter((mp) => mp.connected).length,
		mappingRate: totalCells > 0 ? Math.round((mappedCells / totalCells) * 100) : 0,
	}
})

const searchQuery = ref('')
const parentFilter = ref('all')
const statusFilter = ref('all')
const currentPage = ref(1)
const itemsPerPage = 10

const parentFilterOptions = computed(() => {
	const uniqueParents = [...new Set(categories.value.map((c) => c.parent))]
	return [
		{ value: 'all', label: 'Tüm Üst Kategoriler' },
		...uniqueParents.map((p) => ({ value: p, label: p })),
	]
})

const statusFilterOptions = [
	{ value: 'all', label: 'Tüm Durumlar' },
	{ value: 'active', label: 'Aktif' },
	{ value: 'passive', label: 'Pasif' },
]

const filtered = computed(() => {
	const q = searchQuery.value.trim().toLowerCase()
	return categories.value.filter((c) => {
		const matchesSearch = !q || c.name.toLowerCase().includes(q) || c.parent.toLowerCase().includes(q)
		const matchesParent = parentFilter.value === 'all' || c.parent === parentFilter.value
		const matchesStatus = statusFilter.value === 'all' || c.status === statusFilter.value
		return matchesSearch && matchesParent && matchesStatus
	})
})

const totalPages = computed(() => Math.max(1, Math.ceil(filtered.value.length / itemsPerPage)))
const startIndex = computed(() => filtered.value.length ? (currentPage.value - 1) * itemsPerPage + 1 : 0)
const endIndex = computed(() => Math.min(currentPage.value * itemsPerPage, filtered.value.length))

const paginated = computed(() => {
	const start = (currentPage.value - 1) * itemsPerPage
	return filtered.value.slice(start, start + itemsPerPage)
})

const visiblePages = computed(() => {
	const total = totalPages.value
	const current = currentPage.value
	if (total <= 7) return Array.from({ length: total }, (_, i) => i + 1)
	if (current <= 4) return [1, 2, 3, 4, 5, total]
	if (current >= total - 3) return [1, total - 4, total - 3, total - 2, total - 1, total]
	return [1, current - 1, current, current + 1, total]
})

function mpChipClass(cat, mp) {
	if (!mp.connected) return 'mp-disconnected'
	if (cat.marketplaces[mp.key]?.mapped) return 'mp-mapped'
	return 'mp-unmapped'
}

function mpChipStyle(cat, mp) {
	if (mp.connected && cat.marketplaces[mp.key]?.mapped) {
		return { background: mp.color, color: '#fff', borderColor: mp.color }
	}
	return {}
}

function mpTooltip(cat, mp) {
	if (!mp.connected) return `${mp.name} — Bağlantı yok`
	if (cat.marketplaces[mp.key]?.mapped) {
		const m = cat.marketplaces[mp.key]
		return `${mp.name} — Eşleştirildi · ${m.syncedProducts} ürün senkron`
	}
	return `${mp.name} — Eşleştirilmemiş`
}

async function openMarketplaceCheck(cat, mp) {
	if (!mp.connected) {
		openConnectDrawer(cat, mp)
		return
	}

	const mapping = cat.marketplaces[mp.key]

	if (mapping?.mapped) {
		const result = await $swal.fire({
			icon: 'success',
			title: `${cat.name} ↔ ${mp.name}`,
			html: `
				<div class="mp-info">
					<div class="mp-info-row">
						<span class="mp-info-label">Pazaryeri kategorisi</span>
						<span class="mp-info-value">${mapping.categoryPath}</span>
					</div>
					<div class="mp-info-row">
						<span class="mp-info-label">Harici ID</span>
						<span class="mp-info-value mono">${mapping.externalId}</span>
					</div>
					<div class="mp-info-row">
						<span class="mp-info-label">Senkron ürün</span>
						<span class="mp-info-value"><strong>${mapping.syncedProducts}</strong> / ${cat.productCount}</span>
					</div>
					<div class="mp-info-row">
						<span class="mp-info-label">Son senkron</span>
						<span class="mp-info-value">${mapping.lastSync}</span>
					</div>
				</div>
			`,
			showCancelButton: true,
			showDenyButton: true,
			confirmButtonText: '✏️ Düzenle',
			denyButtonText: 'Eşleştirmeyi Kaldır',
			cancelButtonText: 'Kapat',
			customClass: {
				popup: 'tek-swal',
				title: 'tek-swal-title',
				htmlContainer: 'tek-swal-body',
				icon: 'tek-swal-icon',
				actions: 'tek-swal-actions',
				confirmButton: 'btn btn-secondary',
				denyButton: 'btn btn-outline-danger',
				cancelButton: 'btn btn-ghost',
			},
		})

		if (result.isConfirmed) {
			openMappingEditor(cat, mp, mapping)
		} else if (result.isDenied) {
			await confirmUnmap(cat, mp)
		}
		return
	}

	const ok = await $swal.confirm({
		icon: 'warning',
		title: `${mp.name}'e Eşleştir`,
		html: `<strong>${cat.name}</strong> kategorisi <strong>${mp.name}</strong>'te henüz eşleştirilmemiş.<br>Şimdi eşleştirebilirsin.`,
		confirmText: 'Şimdi Eşleştir',
		cancelText: 'Vazgeç',
	})
	if (ok) openMappingEditor(cat, mp, null)
}

function openMappingEditor(cat, mp, currentMapping) {
	mappingCategory.value = cat
	mappingMarketplace.value = mp
	mappingCurrent.value = currentMapping ?? null
	mappingInitialPath.value = currentMapping?.categoryPath ?? ''
	mapperOpen.value = true
}

function handleMapperSubmit(payload) {
	const cat = categories.value.find((c) => c.id === payload.categoryId)
	const mp = mappingMarketplace.value
	if (!cat || !mp) return

	const wasEmpty = !cat.marketplaces[mp.key]?.mapped

	router.post(
		`/marketplace/categories/${cat.id}/marketplaces/${mp.id}`,
		{ category_path: payload.path },
		{
			preserveScroll: true,
			preserveState: true,
			onSuccess: () => {
				mapperOpen.value = false
				showToast?.({
					type: 'success',
					title: wasEmpty ? 'Eşleştirildi' : 'Güncellendi',
					message: `${cat.name} → ${mp.name} ${wasEmpty ? 'eşleştirildi' : 'yolu güncellendi'}.`,
				})
			},
			onError: (errs) => {
				showToast?.({ type: 'error', title: 'Eşleştirme Başarısız', message: Object.values(errs)[0] || 'Sunucu hatası.' })
			},
		},
	)
}

function openConnectDrawer(cat, mp) {
	connectingCategory.value = cat
	connectingMarketplace.value = mp
	connectDrawerOpen.value = true
}

async function handleConnectSubmit(payload) {
	const mp = marketplaces.value.find((m) => m.key === payload.marketplaceKey)
	if (!mp) return

	router.post(
		`/marketplace/categories/marketplaces/${mp.id}/connect`,
		{},
		{
			preserveScroll: true,
			preserveState: true,
			onSuccess: () => {
				showToast?.({
					type: 'success',
					title: `${mp.name} Bağlandı`,
					message: `${payload.syncScopes?.length ?? 0} veri tipi senkronize edilecek.`,
				})
				connectDrawerOpen.value = false

				if (payload.autoMapAfterConnect && payload.categoryId) {
					const cat = categories.value.find((c) => c.id === payload.categoryId)
					if (cat) setTimeout(() => openMappingEditor(cat, mp, null), 320)
				}
			},
			onError: (errs) => {
				showToast?.({ type: 'error', title: 'Bağlantı Başarısız', message: Object.values(errs)[0] || 'Sunucu hatası.' })
			},
		},
	)
}

async function confirmUnmap(cat, mp) {
	const ok = await $swal.dangerConfirm({
		title: 'Eşleştirmeyi Kaldır',
		html: `<strong>${cat.name}</strong> kategorisinin <strong>${mp.name}</strong> eşleştirmesi kaldırılacak.`,
		confirmText: 'Kaldır',
		cancelText: 'Vazgeç',
	})
	if (!ok) return

	router.delete(`/marketplace/categories/${cat.id}/marketplaces/${mp.id}`, {
		preserveScroll: true,
		preserveState: true,
		onSuccess: () => {
			showToast?.({ type: 'warning', title: 'Eşleştirme Kaldırıldı', message: `${cat.name} → ${mp.name} ayrıldı.` })
		},
		onError: (errs) => {
			showToast?.({ type: 'error', title: 'Kaldırma Başarısız', message: Object.values(errs)[0] || 'Sunucu hatası.' })
		},
	})
}
</script>

<style scoped>
.page-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 20px; gap: 16px; }
.page-title { font-size: 22px; font-weight: 700; color: #1a1a2e; line-height: 1.2; }
.page-subtitle { font-size: 13px; color: #888; margin-top: 4px; }

.stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 20px; }
@media (max-width: 900px) { .stats-grid { grid-template-columns: 1fr; } }

.stat-card {
	background: #fff; border-radius: 14px; border: 1px solid #ebebf0;
	padding: 18px 20px; display: flex; align-items: center; gap: 14px;
	min-height: 80px; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
}
.stat-icon { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 20px; flex-shrink: 0; }
.stat-content { flex: 1; }
.stat-label { font-size: 12px; color: #888; font-weight: 500; }
.stat-value { font-size: 24px; font-weight: 700; color: #1a1a2e; }

.card { background: #fff; border-radius: 16px; border: 1px solid #ebebf0; overflow: hidden; box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04); }
.card-header { padding: 14px 18px; display: flex; align-items: center; gap: 12px; border-bottom: 1px solid #f0f0f5; }
.card-header h3 { font-size: 15px; font-weight: 700; color: #1a1a2e; }

.card-search {
	display: flex; align-items: center; gap: 6px;
	background: #f5f5f8; border: 1px solid #e8e8f0;
	border-radius: 8px; padding: 5px 10px; margin-left: auto; min-width: 220px;
}
.card-search svg { color: #aaa; flex-shrink: 0; }
.card-search input { border: none; background: none; outline: none; font-family: inherit; font-size: 13px; color: #1a1a2e; width: 100%; }
.card-search input::placeholder { color: #bbb; }

.data-table { width: 100%; border-collapse: separate; border-spacing: 0; }
.data-table thead tr { background: #f8f8fc; }
.data-table th {
	text-align: left; padding: 12px 16px; font-size: 11px; font-weight: 600; color: #aaa;
	border-bottom: 1px solid #f0f0f5; text-transform: uppercase; letter-spacing: 0.04em;
}
.data-table td { padding: 12px 16px; font-size: 13px; color: #444; border-bottom: 1px solid #f5f5f8; vertical-align: middle; }
.data-table tr:last-child td { border-bottom: none; }
.data-table tr:hover td { background: #fafafe; }

.empty-row { text-align: center !important; color: #aaa; padding: 32px 0 !important; font-style: italic; }

.cat-cell { display: flex; align-items: center; gap: 12px; }
.cat-icon {
	width: 36px; height: 36px; border-radius: 10px; background: rgb(var(--color-primary-soft));
	display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0;
}
.cat-info { display: flex; flex-direction: column; gap: 2px; }
.cat-name { font-weight: 600; color: #1a1a2e; font-size: 13px; }
.cat-parent { font-size: 10.5px; color: #888; font-weight: 500; }

.mp-logos { display: flex; gap: 5px; flex-wrap: wrap; }
.mp-chip {
	position: relative; width: 32px; height: 32px; border-radius: 9px;
	background: #fff; border: 1.5px solid #e8e8f0; color: #aaa;
	font-family: inherit; font-size: 10px; font-weight: 800; letter-spacing: 0.04em;
	cursor: pointer; display: inline-flex; align-items: center; justify-content: center;
	transition: transform .12s, box-shadow .12s, border-color .12s; padding: 0;
}
.mp-chip:hover { transform: translateY(-1px); box-shadow: 0 4px 10px rgba(0, 0, 0, 0.08); }
.mp-chip.mp-mapped { border-color: transparent; }
.mp-chip.mp-mapped::after {
	content: ''; position: absolute; inset: 0; border-radius: 9px;
	box-shadow: inset 0 0 0 1.5px rgba(255, 255, 255, 0.25); pointer-events: none;
}
.mp-chip.mp-unmapped { background: #fafafe; color: #c4c4d0; border-style: dashed; border-color: #e0e0ea; }
.mp-chip.mp-unmapped:hover { border-color: #c0c0d8; color: #888; }
.mp-chip.mp-disconnected { background: #fef2f2; color: #dc2626; border-color: #fecaca; }
.mp-chip.mp-disconnected:hover { border-color: #fca5a5; }
.mp-dot { position: absolute; top: -3px; right: -3px; width: 10px; height: 10px; border-radius: 50%; border: 2px solid #fff; }
.mp-dot-success { background: #16a34a; }
.mp-dot-error { background: #dc2626; }

.status-pill { display: inline-flex; align-items: center; gap: 5px; padding: 3px 10px; border-radius: 999px; font-size: 11px; font-weight: 600; }
.status-pill .dot { width: 5px; height: 5px; border-radius: 50%; background: currentColor; }
.status-active { background: #dcfce7; color: #16a34a; }
.status-passive { background: #fee2e2; color: #dc2626; }

.pagination { display: flex; align-items: center; justify-content: space-between; padding: 16px 20px; border-top: 1px solid #f0f0f5; }
.pagination-info { font-size: 13px; color: #888; }
.pagination-info span { color: #1a1a2e; font-weight: 600; }
.pagination-controls { display: flex; align-items: center; gap: 4px; }
.pagination-btn {
	min-width: 32px; height: 32px; padding: 0 8px; border: 1px solid #e8e8f0; border-radius: 6px;
	background: #fff; color: #666; font-size: 13px; font-weight: 500; cursor: pointer;
	display: flex; align-items: center; justify-content: center; transition: all .15s;
}
.pagination-btn:hover:not(:disabled) { border-color: #ccc; color: #333; background: #f9f9fb; }
.pagination-btn.active { background: #1a1a2e; color: #fff; border-color: #1a1a2e; }
.pagination-btn:disabled { opacity: 0.4; cursor: not-allowed; }
</style>

<style>
.mp-info { text-align: left; display: flex; flex-direction: column; gap: 8px; margin-top: 8px; padding: 12px 14px; background: #fafafe; border: 1px solid #f0f0f5; border-radius: 10px; }
.mp-info-row { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; font-size: 12.5px; }
.mp-info-label { font-size: 11px; font-weight: 700; color: #888; text-transform: uppercase; letter-spacing: 0.04em; flex-shrink: 0; }
.mp-info-value { color: #1a1a2e; text-align: right; font-weight: 500; }
.mp-info-value strong { color: #1a1a2e; font-weight: 800; }
.mp-info-value.mono { font-family: 'SF Mono', Menlo, Consolas, monospace; font-size: 11.5px; }
.tek-swal-body .muted { color: #888; font-size: 11.5px; }
</style>
```

- [ ] **Step 2: Inertia render testinin geçtiğini doğrula**

Run: `php artisan test tests/Feature/Marketplace/CategoryMappingTest.php --filter=test_index_page_renders`
Expected: PASS.

- [ ] **Step 3: Dev sunucusunda manuel doğrulama**

Vite dev sunucusu çalışıyorsa (`npm run dev`), tarayıcıda superadmin ile giriş yapıp `/marketplace/categories` adresini aç. Kontrol et: kategori listesi görünüyor, bir pazaryeri chip'ine tıklayınca connect/mapping akışı önceki Categories.vue'deki gibi çalışıyor.

- [ ] **Step 4: Commit**

```bash
git add Modules/Marketplace/Resources/assets/js/Pages/CategoryMapping.vue
git commit -m "feat(marketplace): CategoryMapping.vue sayfasini ekle"
```

---

## Task 7: Product\CategoryController'dan pazaryeri kodunu kaldır

**Files:**
- Modify: `Modules/Product/Http/Controllers/CategoryController.php`
- Modify: `Modules/Product/routes/web.php`

- [ ] **Step 1: `CategoryController`'ı sadeleştir**

`Modules/Product/Http/Controllers/CategoryController.php` — dosyanın tamamını değiştir:

```php
<?php

namespace Modules\Product\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Product\Models\Category;

class CategoryController extends Controller
{
    public function index(): Response
    {
        $categories = Category::query()
            ->with('parent:id,name')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (Category $c) => [
                'id'           => $c->id,
                'name'         => $c->name,
                'slug'         => $c->slug,
                'parent'       => $c->parent?->name ?? '—',
                'parent_id'    => $c->parent_id,
                'icon'         => $c->icon ?? '📦',
                'sort_order'   => $c->sort_order,
                'productCount' => 0,
                'status'       => $c->status,
                'updatedAt'    => optional($c->updated_at)->format('Y-m-d'),
            ]);

        return Inertia::render('Product::Categories', [
            'categories' => $categories,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Category::create($this->validateCategory($request));

        return redirect()->route('products.categories.index')
            ->with('success', 'Kategori eklendi.');
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $data = $this->validateCategory($request, $category->id);

        if (! empty($data['parent_id']) && (int) $data['parent_id'] === $category->id) {
            return back()->withErrors(['parent_id' => 'Bir kategori kendisinin üstü olamaz.']);
        }

        $category->update($data);

        return redirect()->route('products.categories.index')
            ->with('success', 'Kategori güncellendi.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        $category->delete();

        return redirect()->route('products.categories.index')
            ->with('success', 'Kategori silindi.');
    }

    /**
     * Birden çok kategoriyi topluca siler.
     */
    public function bulkDestroy(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids'   => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:product_categories,id'],
        ]);

        $count = 0;
        DB::transaction(function () use ($data, &$count) {
            foreach (Category::whereIn('id', $data['ids'])->get() as $category) {
                $category->delete();
                $count++;
            }
        });

        return redirect()->route('products.categories.index')
            ->with('success', "{$count} kategori silindi.");
    }

    private function validateCategory(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'name'       => ['required', 'string', 'max:191'],
            'slug'       => [
                'required',
                'string',
                'max:191',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('product_categories', 'slug')->ignore($ignoreId),
            ],
            'icon'       => ['nullable', 'string', 'max:16'],
            'parent_id'  => ['nullable', 'integer', Rule::exists('product_categories', 'id')],
            'status'     => ['required', Rule::in(['active', 'passive'])],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ], [
            'slug.regex' => 'Slug yalnızca küçük harf, rakam ve tire içerebilir.',
        ]);
    }
}
```

- [ ] **Step 2: `Modules/Product/routes/web.php`'den pazaryeri route'larını kaldır**

`Modules/Product/routes/web.php` — kaldır (satır 68-75, `products/categories` grubunun içindeki 2 satır + grup dışındaki connect route'u):

Eski (kaldırılacak):
```php
        Route::post('/{category}/marketplaces/{marketplace}', [CategoryController::class, 'storeMapping'])
            ->middleware('can:category.manage')->name('marketplaces.store');
        Route::delete('/{category}/marketplaces/{marketplace}', [CategoryController::class, 'destroyMapping'])
            ->middleware('can:category.manage')->name('marketplaces.destroy');
    });

    Route::post('/products/marketplaces/{marketplace}/connect', [CategoryController::class, 'connectMarketplace'])
        ->middleware('can:category.manage')->name('products.marketplaces.connect');
```

Yeni (grup sadece kapanır, connect route'u tamamen silinir):
```php
    });
```

Sonuç olarak `products/categories` route grubu şu hale gelmeli:
```php
    Route::prefix('products/categories')->name('products.categories.')->group(function () {
        Route::get('/', [CategoryController::class, 'index'])->name('index');
        Route::post('/', [CategoryController::class, 'store'])
            ->middleware('can:category.manage')->name('store');
        Route::put('/{category}', [CategoryController::class, 'update'])
            ->middleware('can:category.manage')->name('update');
        Route::delete('/{category}', [CategoryController::class, 'destroy'])
            ->middleware('can:category.manage')->name('destroy');
        Route::post('/bulk-destroy', [CategoryController::class, 'bulkDestroy'])
            ->middleware('can:category.manage')->name('bulk-destroy');
    });
```

- [ ] **Step 3: Eski URL'lerin artık 404 döndüğünü, yenilerinin çalıştığını doğrula**

Run:
```bash
php artisan route:list --name=products.categories
php artisan route:list --name=marketplace.categories
```
Expected: `products.categories.marketplaces.*` ve `products.marketplaces.connect` **listede yok**; `marketplace.categories.*` listede **var** (Task 5'ten).

- [ ] **Step 4: İlgili test paketlerini çalıştır**

Run: `php artisan test --filter=Marketplace --filter=Product`
Expected: tüm testler PASS (özellikle `CategoryMappingTest`, yeni yerinde hâlâ çalışıyor; Product tarafında kategori CRUD testleri varsa onlar da PASS).

- [ ] **Step 5: Commit**

```bash
git add Modules/Product/Http/Controllers/CategoryController.php Modules/Product/routes/web.php
git commit -m "refactor(product): CategoryController'dan pazaryeri eşleme kodu kaldırıldı (Marketplace'e taşındı)"
```

---

## Task 8: Categories.vue'den pazaryeri UI'sini kaldır

**Files:**
- Modify: `Modules/Product/Resources/assets/js/Pages/Categories.vue`

- [ ] **Step 1: Sayfayı sadeleştirilmiş haliyle yeniden yaz**

`Modules/Product/Resources/assets/js/Pages/Categories.vue` — dosyanın tamamını değiştir:

```vue
<template>
	<Head title="Kategoriler" />
	<div class="page-categories">
		<Breadcrumb
			:items="[
				{ label: 'Ana Sayfa', to: '/workflow', icon: 'home' },
				{ label: 'Katalog', to: '/products' },
				{ label: 'Kategoriler' },
			]"
		/>

		<div class="page-header">
			<div>
				<h1 class="page-title">Kategoriler</h1>
				<p class="page-subtitle">Ürün kategorilerini yönet</p>
			</div>
			<button class="btn btn-primary btn-with-icon" @click="openNewCategoryModal">
				<svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
					<path d="M12 5v14M5 12h14" />
				</svg>
				Yeni Kategori
			</button>
		</div>

		<div class="stats-grid">
			<div class="stat-card">
				<div class="stat-icon" style="background: rgb(var(--color-primary-soft))">📂</div>
				<div class="stat-content">
					<div class="stat-label">Toplam Kategori</div>
					<div class="stat-value">{{ stats.total }}</div>
				</div>
			</div>
			<div class="stat-card">
				<div class="stat-icon" style="background: #f0fdf4">📦</div>
				<div class="stat-content">
					<div class="stat-label">Toplam Ürün</div>
					<div class="stat-value">{{ stats.products.toLocaleString('tr-TR') }}</div>
				</div>
			</div>
		</div>

		<div class="card">
			<div class="card-header">
				<h3>Kategori Listesi</h3>
				<div class="card-search">
					<svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
						<circle cx="11" cy="11" r="8" /><path d="M21 21l-4.35-4.35" />
					</svg>
					<input v-model="searchQuery" type="text" placeholder="Kategori ara..." />
				</div>
				<CustomSelect
					v-model="parentFilter"
					:options="parentFilterOptions"
					:show-label="false"
					style="width: 160px"
				/>
				<CustomSelect
					v-model="statusFilter"
					:options="statusFilterOptions"
					:show-label="false"
					style="width: 130px"
				/>
			</div>

			<div v-if="selectedCount > 0" class="bulk-bar">
				<span class="bulk-info"><strong>{{ selectedCount }}</strong> kategori seçildi</span>
				<div class="bulk-actions">
					<button class="btn btn-ghost btn-sm" @click="clearSelection">Vazgeç</button>
					<button class="btn btn-danger btn-sm btn-with-icon" :disabled="bulkBusy" @click="bulkDelete">
						🗑️ Seçilenleri Sil
					</button>
				</div>
			</div>

			<table class="data-table">
				<thead>
					<tr>
						<th class="col-check">
							<input type="checkbox" :checked="allVisibleSelected" @change="toggleSelectAllVisible" aria-label="Tümünü seç" />
						</th>
						<th style="width: 32%">Kategori</th>
						<th style="width: 14%">Ürün</th>
						<th style="width: 14%">Durum</th>
						<th style="width: 16%">Güncelleme</th>
						<th style="width: 18%">İşlemler</th>
					</tr>
				</thead>
				<tbody>
					<tr v-if="paginated.length === 0">
						<td colspan="6" class="empty-row">Kayıt bulunamadı</td>
					</tr>
					<tr v-for="cat in paginated" :key="cat.id" :class="{ 'row-selected': isSelected(cat.id) }">
						<td class="col-check">
							<input type="checkbox" :checked="isSelected(cat.id)" @change="toggleRow(cat.id)" :aria-label="`${cat.name} seç`" />
						</td>
						<td>
							<div class="cat-cell">
								<div class="cat-icon">{{ cat.icon }}</div>
								<div class="cat-info">
									<span class="cat-name">{{ cat.name }}</span>
									<span class="cat-parent">{{ cat.parent }}</span>
								</div>
							</div>
						</td>
						<td>
							<span class="product-count">{{ cat.productCount }}</span>
						</td>
						<td>
							<span class="status-pill" :class="`status-${cat.status}`">
								<span class="dot"></span>
								{{ cat.status === 'active' ? 'Aktif' : 'Pasif' }}
							</span>
						</td>
						<td class="dim">{{ cat.updatedAt }}</td>
						<td>
							<div class="table-actions">
								<button class="table-action-btn view" @click="editCategory(cat)" title="Düzenle">✏️ Düzenle</button>
								<button class="table-action-btn delete" @click="confirmDelete(cat)" title="Sil">🗑️</button>
							</div>
						</td>
					</tr>
				</tbody>
			</table>

			<div class="pagination">
				<div class="pagination-info">
					Toplam <span>{{ filtered.length }}</span> kategori
					(<span>{{ startIndex }}</span>-<span>{{ endIndex }}</span> arası)
				</div>
				<div class="pagination-controls">
					<button class="pagination-btn" :disabled="currentPage === 1" @click="currentPage--">‹</button>
					<button
						v-for="page in visiblePages"
						:key="page"
						class="pagination-btn"
						:class="{ active: currentPage === page }"
						@click="currentPage = page"
					>{{ page }}</button>
					<button class="pagination-btn" :disabled="currentPage === totalPages || totalPages === 0" @click="currentPage++">›</button>
				</div>
			</div>
		</div>

		<CategoryFormDrawer
			v-model="formDrawerOpen"
			:category="editingCategory"
			:parent-categories="categories"
			:busy="formBusy"
			:errors="formErrors"
			@submit="handleFormSubmit"
		/>
	</div>
</template>

<script setup>
import { ref, computed, inject, watch } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Breadcrumb from '@/Components/Breadcrumb.vue'
import CustomSelect from '@/Components/CustomSelect.vue'
import CategoryFormDrawer from '@/Components/CategoryFormDrawer.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
	categories: { type: Array, default: () => [] },
})

const showToast = inject('showToast')
const $swal = inject('$swal')

const categories = ref(props.categories.map((c) => ({ ...c })))

watch(
	() => props.categories,
	(list) => { categories.value = list.map((c) => ({ ...c })) },
	{ deep: true },
)

const formDrawerOpen = ref(false)
const editingCategory = ref(null)
const formBusy = ref(false)
const formErrors = ref({})

watch(formDrawerOpen, (open) => {
	if (!open) {
		setTimeout(() => {
			editingCategory.value = null
			formErrors.value = {}
		}, 250)
	}
})

const stats = computed(() => ({
	total: categories.value.length,
	products: categories.value.reduce((acc, c) => acc + c.productCount, 0),
}))

const selected = ref(new Set())
const bulkBusy = ref(false)
const selectedCount = computed(() => selected.value.size)

function isSelected(id) { return selected.value.has(id) }
function toggleRow(id) {
	const next = new Set(selected.value)
	next.has(id) ? next.delete(id) : next.add(id)
	selected.value = next
}
const allVisibleSelected = computed(() =>
	paginated.value.length > 0 && paginated.value.every((c) => selected.value.has(c.id)),
)
function toggleSelectAllVisible() {
	const ids = paginated.value.map((c) => c.id)
	const next = new Set(selected.value)
	if (allVisibleSelected.value) ids.forEach((id) => next.delete(id))
	else ids.forEach((id) => next.add(id))
	selected.value = next
}
function clearSelection() { selected.value = new Set() }

async function bulkDelete() {
	const ids = [...selected.value]
	if (!ids.length) return
	const ok = await $swal.dangerConfirm({
		title: 'Kategorileri Sil',
		html: `<strong>${ids.length}</strong> kategori kalıcı olarak silinecek.`,
		confirmText: 'Sil',
		cancelText: 'Vazgeç',
	})
	if (!ok) return

	bulkBusy.value = true
	router.post('/products/categories/bulk-destroy', { ids }, {
		preserveScroll: true,
		preserveState: false,
		onSuccess: () => {
			showToast?.({ type: 'warning', title: 'Kategoriler Silindi', message: `${ids.length} kategori kaldırıldı.` })
			clearSelection()
		},
		onError: (errs) => {
			showToast?.({ type: 'error', title: 'Silme Başarısız', message: Object.values(errs)[0] || 'Sunucu hatası.' })
		},
		onFinish: () => { bulkBusy.value = false },
	})
}

const searchQuery = ref('')
const parentFilter = ref('all')
const statusFilter = ref('all')
const currentPage = ref(1)
const itemsPerPage = 8

const parentFilterOptions = computed(() => {
	const uniqueParents = [...new Set(categories.value.map((c) => c.parent))]
	return [
		{ value: 'all', label: 'Tüm Üst Kategoriler' },
		...uniqueParents.map((p) => ({ value: p, label: p })),
	]
})

const statusFilterOptions = [
	{ value: 'all', label: 'Tüm Durumlar' },
	{ value: 'active', label: 'Aktif' },
	{ value: 'passive', label: 'Pasif' },
]

const filtered = computed(() => {
	const q = searchQuery.value.trim().toLowerCase()
	return categories.value.filter((c) => {
		const matchesSearch = !q || c.name.toLowerCase().includes(q) || c.parent.toLowerCase().includes(q)
		const matchesParent = parentFilter.value === 'all' || c.parent === parentFilter.value
		const matchesStatus = statusFilter.value === 'all' || c.status === statusFilter.value
		return matchesSearch && matchesParent && matchesStatus
	})
})

const totalPages = computed(() => Math.max(1, Math.ceil(filtered.value.length / itemsPerPage)))
const startIndex = computed(() => filtered.value.length ? (currentPage.value - 1) * itemsPerPage + 1 : 0)
const endIndex = computed(() => Math.min(currentPage.value * itemsPerPage, filtered.value.length))

const paginated = computed(() => {
	const start = (currentPage.value - 1) * itemsPerPage
	return filtered.value.slice(start, start + itemsPerPage)
})

const visiblePages = computed(() => {
	const total = totalPages.value
	const current = currentPage.value
	if (total <= 7) return Array.from({ length: total }, (_, i) => i + 1)
	if (current <= 4) return [1, 2, 3, 4, 5, total]
	if (current >= total - 3) return [1, total - 4, total - 3, total - 2, total - 1, total]
	return [1, current - 1, current, current + 1, total]
})

function openNewCategoryModal() {
	editingCategory.value = null
	formErrors.value = {}
	formDrawerOpen.value = true
}

function editCategory(cat) {
	editingCategory.value = { ...cat }
	formErrors.value = {}
	formDrawerOpen.value = true
}

function handleFormSubmit({ mode, id, payload }) {
	if (formBusy.value) return
	formBusy.value = true
	formErrors.value = {}

	const opts = {
		preserveScroll: true,
		preserveState: true,
		onSuccess: () => {
			formDrawerOpen.value = false
			showToast?.({
				type: 'success',
				title: mode === 'edit' ? 'Kategori Güncellendi' : 'Kategori Eklendi',
				message: payload.name,
			})
		},
		onError: (errs) => {
			formErrors.value = errs
			const first = Object.values(errs)[0]
			showToast?.({ type: 'error', title: 'Kayıt Başarısız', message: first || 'Doğrulama hatası.' })
		},
		onFinish: () => { formBusy.value = false },
	}

	if (mode === 'edit') {
		router.put(`/products/categories/${id}`, payload, opts)
	} else {
		router.post('/products/categories', payload, opts)
	}
}

async function confirmDelete(cat) {
	const ok = await $swal.dangerConfirm({
		title: 'Kategoriyi Sil',
		html: `<strong>${cat.name}</strong> silinecek.<br>Bu kategoride <strong>${cat.productCount}</strong> ürün var.`,
		confirmText: 'Sil',
		cancelText: 'Vazgeç',
	})
	if (!ok) return

	router.delete(`/products/categories/${cat.id}`, {
		preserveScroll: true,
		preserveState: true,
		onSuccess: () => {
			showToast?.({ type: 'warning', title: 'Kategori Silindi', message: `${cat.name} kalıcı olarak silindi.` })
		},
		onError: (errs) => {
			showToast?.({ type: 'error', title: 'Silme Başarısız', message: Object.values(errs)[0] || 'Sunucu hatası.' })
		},
	})
}
</script>

<style scoped>
.page-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 20px; gap: 16px; }
.page-title { font-size: 22px; font-weight: 700; color: #1a1a2e; line-height: 1.2; }
.page-subtitle { font-size: 13px; color: #888; margin-top: 4px; }

.stats-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; margin-bottom: 20px; }
@media (max-width: 620px) { .stats-grid { grid-template-columns: 1fr; } }

.stat-card {
	background: #fff; border-radius: 14px; border: 1px solid #ebebf0;
	padding: 18px 20px; display: flex; align-items: center; gap: 14px;
	min-height: 80px; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
}
.stat-icon { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 20px; flex-shrink: 0; }
.stat-content { flex: 1; }
.stat-label { font-size: 12px; color: #888; font-weight: 500; }
.stat-value { font-size: 24px; font-weight: 700; color: #1a1a2e; }

.card { background: #fff; border-radius: 16px; border: 1px solid #ebebf0; overflow: hidden; box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04); }
.card-header { padding: 14px 18px; display: flex; align-items: center; gap: 12px; border-bottom: 1px solid #f0f0f5; }
.card-header h3 { font-size: 15px; font-weight: 700; color: #1a1a2e; }

.card-search {
	display: flex; align-items: center; gap: 6px;
	background: #f5f5f8; border: 1px solid #e8e8f0;
	border-radius: 8px; padding: 5px 10px; margin-left: auto; min-width: 220px;
}
.card-search svg { color: #aaa; flex-shrink: 0; }
.card-search input { border: none; background: none; outline: none; font-family: inherit; font-size: 13px; color: #1a1a2e; width: 100%; }
.card-search input::placeholder { color: #bbb; }

.bulk-bar {
	display: flex; align-items: center; justify-content: space-between; gap: 12px;
	padding: 10px 18px; background: #faf5ff; border-bottom: 1px solid #f0e9fb;
}
.bulk-info { font-size: 13px; color: rgb(var(--color-primary-hover)); }
.bulk-info strong { font-weight: 800; }
.bulk-actions { display: flex; gap: 8px; }
.btn-danger { background: #dc2626; color: #fff; border: none; }
.btn-danger:hover:not(:disabled) { background: #b91c1c; }
.btn-danger:disabled { opacity: .55; cursor: not-allowed; }

.col-check { width: 40px; text-align: center; padding-left: 16px; padding-right: 0; }
.col-check input { width: 15px; height: 15px; accent-color: rgb(var(--color-primary)); cursor: pointer; }
.data-table tr.row-selected td { background: #faf5ff; }

.data-table { width: 100%; border-collapse: separate; border-spacing: 0; }
.data-table thead tr { background: #f8f8fc; }
.data-table th {
	text-align: left; padding: 12px 16px; font-size: 11px; font-weight: 600; color: #aaa;
	border-bottom: 1px solid #f0f0f5; text-transform: uppercase; letter-spacing: 0.04em;
}
.data-table td { padding: 12px 16px; font-size: 13px; color: #444; border-bottom: 1px solid #f5f5f8; vertical-align: middle; }
.data-table tr:last-child td { border-bottom: none; }
.data-table tr:hover td { background: #fafafe; }

.empty-row { text-align: center !important; color: #aaa; padding: 32px 0 !important; font-style: italic; }
.dim { font-size: 12px; color: #888; }

.cat-cell { display: flex; align-items: center; gap: 12px; }
.cat-icon {
	width: 36px; height: 36px; border-radius: 10px; background: rgb(var(--color-primary-soft));
	display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0;
}
.cat-info { display: flex; flex-direction: column; gap: 2px; }
.cat-name { font-weight: 600; color: #1a1a2e; font-size: 13px; }
.cat-parent { font-size: 10.5px; color: #888; font-weight: 500; }

.product-count {
	display: inline-block; padding: 2px 9px; background: #f0f0f5; color: #555;
	border-radius: 6px; font-size: 11.5px; font-weight: 700;
	font-family: 'SF Mono', Menlo, Consolas, monospace;
}

.status-pill { display: inline-flex; align-items: center; gap: 5px; padding: 3px 10px; border-radius: 999px; font-size: 11px; font-weight: 600; }
.status-pill .dot { width: 5px; height: 5px; border-radius: 50%; background: currentColor; }
.status-active { background: #dcfce7; color: #16a34a; }
.status-passive { background: #fee2e2; color: #dc2626; }

.table-actions { display: flex; gap: 4px; }
.table-action-btn {
	background: #f3f4f6; border: none; cursor: pointer; font-size: 11.5px;
	padding: 5px 9px; border-radius: 6px; font-weight: 500; color: #6b7280; transition: all .15s;
}
.table-action-btn.view:hover { background: rgb(var(--color-primary-soft)); color: rgb(var(--color-primary)); }
.table-action-btn.delete:hover { background: #fee2e2; color: #dc2626; }

.pagination { display: flex; align-items: center; justify-content: space-between; padding: 16px 20px; border-top: 1px solid #f0f0f5; }
.pagination-info { font-size: 13px; color: #888; }
.pagination-info span { color: #1a1a2e; font-weight: 600; }
.pagination-controls { display: flex; align-items: center; gap: 4px; }
.pagination-btn {
	min-width: 32px; height: 32px; padding: 0 8px; border: 1px solid #e8e8f0; border-radius: 6px;
	background: #fff; color: #666; font-size: 13px; font-weight: 500; cursor: pointer;
	display: flex; align-items: center; justify-content: center; transition: all .15s;
}
.pagination-btn:hover:not(:disabled) { border-color: #ccc; color: #333; background: #f9f9fb; }
.pagination-btn.active { background: #1a1a2e; color: #fff; border-color: #1a1a2e; }
.pagination-btn:disabled { opacity: 0.4; cursor: not-allowed; }
</style>
```

Not: bu sürümde `MarketplaceConnectDrawer`, `MarketplaceCategoryPickerModal` import'ları ve `marketplaces` prop'u tamamen yok; `confirmDelete` artık pazaryeri eşleştirme sayısından bahsetmiyor (Category, Marketplace'i bilmiyor).

- [ ] **Step 2: Frontend build/tip kontrolü**

Run: `npm run build` (proje kökünde) veya ilgili modülün build komutu.
Expected: derleme hatasız tamamlanır (kullanılmayan import kalmadı).

- [ ] **Step 3: Dev sunucusunda manuel doğrulama**

`/products/categories` sayfasını aç: kategori CRUD (ekle/düzenle/sil/toplu sil) çalışıyor, pazaryeri sütunu artık yok.

- [ ] **Step 4: Commit**

```bash
git add Modules/Product/Resources/assets/js/Pages/Categories.vue
git commit -m "refactor(product): Categories.vue'den pazaryeri UI'si kaldırıldı (Marketplace::CategoryMapping'e taşındı)"
```

---

## Task 9: Marketplace'e ProductListingsController + route'lar + drawer URL güncellemesi

**Files:**
- Create: `Modules/Marketplace/Http/Controllers/ProductListingsController.php`
- Modify: `Modules/Marketplace/routes/web.php`
- Modify: `Modules/Marketplace/Resources/assets/js/Components/MarketplaceListingDrawer.vue`
- Test: relocate `tests/Feature/Product/MarketplaceListingTest.php` → `tests/Feature/Marketplace/ProductListingTest.php` (URL güncellemesiyle) + yeni `tests/Feature/Marketplace/ProductListingsPageTest.php`

**Interfaces:**
- Produces: route'lar `marketplace.products.index`, `marketplace.products.search`, `marketplace.products.listings.show`, `marketplace.products.listings.upsert`

- [ ] **Step 1: `ProductListingsController`'ı yaz**

`Modules/Marketplace/Http/Controllers/ProductListingsController.php`:

```php
<?php

namespace Modules\Marketplace\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Marketplace\Models\Marketplace;
use Modules\Product\Models\Product;

class ProductListingsController extends Controller
{
    public function index(): Response
    {
        $marketplaces = Marketplace::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'key', 'name', 'color', 'logo_text', 'connected'])
            ->map(fn (Marketplace $m) => [
                'key'       => $m->key,
                'name'      => $m->name,
                'color'     => $m->color,
                'logoText'  => $m->logo_text,
                'connected' => $m->connected,
            ]);

        return Inertia::render('Marketplace::ProductListings', [
            'marketplaces' => $marketplaces,
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        if ($q === '') {
            return response()->json(['data' => []]);
        }

        $products = Product::query()
            ->where(fn ($query) => $query
                ->where('name', 'like', "%{$q}%")
                ->orWhere('sku', 'like', "%{$q}%"))
            ->orderBy('name')
            ->limit(10)
            ->get(['id', 'name', 'sku'])
            ->map(fn (Product $p) => ['id' => $p->id, 'name' => $p->name, 'sku' => $p->sku]);

        return response()->json(['data' => $products]);
    }
}
```

- [ ] **Step 2: Route'ları ekle (ve eski `products.listings.*` route'larını taşı)**

`Modules/Marketplace/routes/web.php` — Task 5'te oluşturduğun dosyaya, `marketplace/categories` grubundan sonra ekle (import satırlarını da güncelle):

```php
<?php

use Illuminate\Support\Facades\Route;
use Modules\Marketplace\Http\Controllers\CategoryMappingController;
use Modules\Marketplace\Http\Controllers\ProductListingsController;
use Modules\Marketplace\Http\Controllers\ProductMarketplaceListingController;
use Modules\Marketplace\Http\Controllers\TenantMarketplaceController;

Route::middleware(['auth', 'verified'])->group(function () {
    // ─── Pazaryeri Bağlantıları ──────────────────────────────────────────
    // (literal /tenants/{tenant}/marketplace yolları — /tenants/{tenant} catch-all'dan önce)
    // Permission tek başına yetmez: controller'da scope check (superadmin tümü, tenant kullanıcı kendisi).
    Route::prefix('tenants/{tenant}/marketplace')->name('tenants.marketplace.')
        ->middleware('can:marketplace.manage')->whereNumber('tenant')
        ->group(function () {
            Route::get('/', [TenantMarketplaceController::class, 'index'])->name('index');
            Route::post('/', [TenantMarketplaceController::class, 'store'])->name('store');
            Route::put('/{credential}', [TenantMarketplaceController::class, 'update'])
                ->whereNumber('credential')->name('update');
            Route::post('/{credential}/toggle', [TenantMarketplaceController::class, 'toggle'])
                ->whereNumber('credential')->name('toggle');
            Route::delete('/{credential}', [TenantMarketplaceController::class, 'destroy'])
                ->whereNumber('credential')->name('destroy');
        });

    // ─── İç Katalog: Kategori ↔ Pazaryeri Eşleme ─────────────────────────
    // Product'ın Kategoriler sayfasından taşındı — iç/admin-only, marketplace.catalog.manage.
    Route::prefix('marketplace/categories')->name('marketplace.categories.')->group(function () {
        Route::get('/', [CategoryMappingController::class, 'index'])->name('index');
        Route::post('/marketplaces/{marketplace}/connect', [CategoryMappingController::class, 'connect'])
            ->middleware('can:marketplace.catalog.manage')->name('connect');
        Route::post('/{category}/marketplaces/{marketplace}', [CategoryMappingController::class, 'storeMapping'])
            ->middleware('can:marketplace.catalog.manage')->name('mappings.store');
        Route::delete('/{category}/marketplaces/{marketplace}', [CategoryMappingController::class, 'destroyMapping'])
            ->middleware('can:marketplace.catalog.manage')->name('mappings.destroy');
    });

    // ─── İç Katalog: Ürün ↔ Pazaryeri Listeleme ──────────────────────────
    // Product'ın Ürünler sayfasından taşındı — iç/admin-only, marketplace.catalog.manage.
    Route::prefix('marketplace/products')->name('marketplace.products.')->group(function () {
        Route::get('/', [ProductListingsController::class, 'index'])->name('index');
        Route::get('/search', [ProductListingsController::class, 'search'])->name('search');
        Route::get('/{product:id}/{marketplace}/listing', [ProductMarketplaceListingController::class, 'show'])
            ->whereNumber('product')->name('listings.show');
        Route::put('/{product:id}/{marketplace}/listing', [ProductMarketplaceListingController::class, 'upsert'])
            ->whereNumber('product')->middleware('can:marketplace.catalog.manage')->name('listings.upsert');
    });
});
```

- [ ] **Step 3: `MarketplaceListingDrawer.vue`'daki axios URL'lerini yeni route'a güncelle**

`Modules/Marketplace/Resources/assets/js/Components/MarketplaceListingDrawer.vue` — iki axios çağrısını güncelle:

Eski (satır ~200):
```js
		const { data } = await axios.get(`/products/${props.productId}/marketplaces/${props.marketplace.key}/listing`)
```
Yeni:
```js
		const { data } = await axios.get(`/marketplace/products/${props.productId}/${props.marketplace.key}/listing`)
```

Eski (satır ~216-217):
```js
		const { data } = await axios.put(
			`/products/${props.productId}/marketplaces/${props.marketplace.key}/listing`,
```
Yeni:
```js
		const { data } = await axios.put(
			`/marketplace/products/${props.productId}/${props.marketplace.key}/listing`,
```

- [ ] **Step 4: Eski test dosyasını yeni konuma taşı ve URL'lerini güncelle**

`tests/Feature/Product/MarketplaceListingTest.php` dosyasını sil, yerine `tests/Feature/Marketplace/ProductListingTest.php` oluştur:

```php
<?php

namespace Tests\Feature\Marketplace;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Product\Models\Category;
use Modules\Marketplace\Models\Marketplace;
use Modules\Product\Models\Product;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProductListingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $role = Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);
        $this->admin = User::factory()->create();
        $this->admin->assignRole($role);
    }

    private function makeProduct(): Product
    {
        $category = Category::create([
            'name' => 'Hırka', 'slug' => 'hirka-' . uniqid(), 'status' => 'active', 'sort_order' => 0,
        ]);

        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Test Süveter',
            'sku' => 'SKU-' . uniqid(),
            'gender' => 'Unisex',
            'price' => 107.88,
        ]);

        $product->variants()->create([
            'sku' => 'V-' . uniqid(), 'price' => 107.88, 'stock' => 5, 'sort_order' => 0,
        ]);

        return $product;
    }

    public function test_show_returns_default_draft_when_no_listing_exists(): void
    {
        $product = $this->makeProduct();
        Marketplace::create(['key' => 'n11', 'name' => 'N11', 'logo_text' => 'n11', 'color' => '#f5a623']);

        $this->actingAs($this->admin)
            ->getJson("/marketplace/products/{$product->id}/n11/listing")
            ->assertOk()
            ->assertJsonPath('listing.is_sent', false)
            ->assertJsonPath('listing.approval_status', 'not_sent')
            ->assertJsonPath('listing.title', 'Test Süveter')
            ->assertJsonPath('marketplace.key', 'n11')
            ->assertJsonCount(1, 'variants');
    }

    public function test_show_returns_404_for_unknown_marketplace(): void
    {
        $product = $this->makeProduct();

        $this->actingAs($this->admin)
            ->getJson("/marketplace/products/{$product->id}/bilinmeyen/listing")
            ->assertNotFound();
    }

    public function test_upsert_creates_listing_and_marks_sent(): void
    {
        $product = $this->makeProduct();
        $mp = Marketplace::create(['key' => 'n11', 'name' => 'N11', 'logo_text' => 'n11', 'color' => '#f5a623']);
        $variantId = $product->variants->first()->id;

        $this->actingAs($this->admin)
            ->putJson("/marketplace/products/{$product->id}/n11/listing", [
                'product_status' => 'active',
                'store_name' => '#1 - TEST MAĞAZA',
                'model_code' => 'P3946S523',
                'title' => 'Şah Desenli Triko Süveter',
                'price' => 115.87,
                'currency' => 'TL',
                'variant_extra_price' => 0,
                'shipping_time' => 2,
                'variants' => [
                    ['product_variant_id' => $variantId, 'marketplace_variant' => 'Std', 'stock_code' => 'SK1', 'barcode' => '8690000000001'],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('listing.isSent', true)
            ->assertJsonPath('listing.price', 115.87);

        $this->assertDatabaseHas('product_marketplace_listings', [
            'product_id' => $product->id,
            'marketplace_id' => $mp->id,
            'is_sent' => true,
            'model_code' => 'P3946S523',
            'approval_status' => 'pending',
        ]);
    }

    public function test_upsert_requires_catalog_manage_permission(): void
    {
        $product = $this->makeProduct();
        Marketplace::create(['key' => 'n11', 'name' => 'N11', 'logo_text' => 'n11', 'color' => '#f5a623']);

        $plainUser = User::factory()->create(); // rolsüz → yetkisiz

        $this->actingAs($plainUser)
            ->putJson("/marketplace/products/{$product->id}/n11/listing", [
                'product_status' => 'active', 'title' => 'X', 'price' => 10, 'shipping_time' => 1,
            ])
            ->assertForbidden();
    }
}
```

- [ ] **Step 5: Ürün arama + sayfa render testini yaz**

`tests/Feature/Marketplace/ProductListingsPageTest.php`:

```php
<?php

namespace Tests\Feature\Marketplace;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Product\Models\Category;
use Modules\Product\Models\Product;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProductListingsPageTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $role = Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);
        $this->admin = User::factory()->create();
        $this->admin->assignRole($role);
    }

    public function test_index_page_renders(): void
    {
        $this->actingAs($this->admin)
            ->get('/marketplace/products')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Marketplace::ProductListings', false));
    }

    public function test_search_returns_matching_products_by_name_or_sku(): void
    {
        $category = Category::create(['name' => 'Hırka', 'slug' => 'hirka-' . uniqid(), 'status' => 'active', 'sort_order' => 0]);
        Product::create(['category_id' => $category->id, 'name' => 'Mavi Kazak', 'sku' => 'KZK-001', 'gender' => 'Unisex', 'price' => 100]);
        Product::create(['category_id' => $category->id, 'name' => 'Kırmızı Bluz', 'sku' => 'BLZ-002', 'gender' => 'Unisex', 'price' => 80]);

        $this->actingAs($this->admin)
            ->getJson('/marketplace/products/search?q=Kazak')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Mavi Kazak');
    }

    public function test_search_with_empty_query_returns_empty_list(): void
    {
        $this->actingAs($this->admin)
            ->getJson('/marketplace/products/search?q=')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }
}
```

- [ ] **Step 6: Testleri çalıştır**

Run: `php artisan test tests/Feature/Marketplace/ProductListingTest.php tests/Feature/Marketplace/ProductListingsPageTest.php`
Expected: tüm testler PASS.

- [ ] **Step 7: Commit**

```bash
git add Modules/Marketplace/Http/Controllers/ProductListingsController.php Modules/Marketplace/routes/web.php Modules/Marketplace/Resources/assets/js/Components/MarketplaceListingDrawer.vue tests/Feature/Marketplace/ProductListingTest.php tests/Feature/Marketplace/ProductListingsPageTest.php
git rm tests/Feature/Product/MarketplaceListingTest.php
git commit -m "feat(marketplace): ProductListingsController + route'lar ekle, listing URL'lerini /marketplace/products altına taşı"
```

---

## Task 10: Marketplace::ProductListings.vue sayfasını oluştur

**Files:**
- Create: `Modules/Marketplace/Resources/assets/js/Pages/ProductListings.vue`

**Interfaces:**
- Consumes: `GET /marketplace/products/search?q=`, var olan `Modules/Marketplace/Resources/assets/js/Components/MarketplaceListingDrawer.vue`

- [ ] **Step 1: Sayfayı yaz**

`Modules/Marketplace/Resources/assets/js/Pages/ProductListings.vue`:

```vue
<template>
	<Head title="Ürün ↔ Pazaryeri Bağlantıları" />
	<div class="page-product-listings">
		<Breadcrumb
			:items="[
				{ label: 'Ana Sayfa', to: '/workflow', icon: 'home' },
				{ label: 'Pazaryeri' },
				{ label: 'Ürün Bağlantıları' },
			]"
		/>

		<div class="page-header">
			<div>
				<h1 class="page-title">Ürün ↔ Pazaryeri Bağlantıları</h1>
				<p class="page-subtitle">Bir ürün seç, pazaryeri listeleme bilgilerini düzenle</p>
			</div>
		</div>

		<div class="card search-card">
			<div class="search-box">
				<svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
					<circle cx="11" cy="11" r="8" /><path d="M21 21l-4.35-4.35" />
				</svg>
				<input v-model="query" type="text" placeholder="Ürün adı veya SKU ile ara..." @input="onSearchInput" />
			</div>

			<div v-if="loading" class="search-status">Aranıyor…</div>
			<div v-else-if="query && results.length === 0" class="search-status">Sonuç bulunamadı.</div>

			<ul v-if="results.length" class="result-list">
				<li v-for="p in results" :key="p.id">
					<button type="button" class="result-item" @click="selectProduct(p)">
						<span class="result-name">{{ p.name }}</span>
						<span class="result-sku">{{ p.sku }}</span>
					</button>
				</li>
			</ul>
		</div>

		<div v-if="activeProduct" class="card active-product-card">
			<div class="active-product-header">
				<span class="active-product-name">{{ activeProduct.name }}</span>
				<span class="active-product-sku">{{ activeProduct.sku }}</span>
			</div>
			<div class="mp-list">
				<button
					v-for="mp in marketplaces"
					:key="mp.key"
					type="button"
					class="mp-item"
					:title="mp.name"
					@click="openListing(mp)"
				>
					<span class="mp-badge" :style="{ background: mp.color || '#888' }">{{ mp.logoText }}</span>
					<span class="mp-name">{{ mp.name }}</span>
				</button>
			</div>
		</div>

		<MarketplaceListingDrawer
			:open="listingOpen"
			:product-id="activeProduct?.id"
			:marketplace="activeMarketplace"
			@close="listingOpen = false"
			@saved="onListingSaved"
		/>
	</div>
</template>

<script setup>
import { ref, inject } from 'vue'
import { Head } from '@inertiajs/vue3'
import axios from 'axios'
import AppLayout from '@/Layouts/AppLayout.vue'
import Breadcrumb from '@/Components/Breadcrumb.vue'
import MarketplaceListingDrawer from '../Components/MarketplaceListingDrawer.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
	marketplaces: { type: Array, default: () => [] },
})

const showToast = inject('showToast')

const query = ref('')
const results = ref([])
const loading = ref(false)
let searchTimer = null

function onSearchInput() {
	clearTimeout(searchTimer)
	if (!query.value.trim()) {
		results.value = []
		return
	}
	searchTimer = setTimeout(runSearch, 300)
}

async function runSearch() {
	loading.value = true
	try {
		const { data } = await axios.get('/marketplace/products/search', { params: { q: query.value.trim() } })
		results.value = data.data
	} finally {
		loading.value = false
	}
}

const activeProduct = ref(null)
function selectProduct(p) {
	activeProduct.value = p
	results.value = []
	query.value = ''
}

const listingOpen = ref(false)
const activeMarketplace = ref(null)
function openListing(mp) {
	activeMarketplace.value = mp
	listingOpen.value = true
}
function onListingSaved() {
	showToast?.({ type: 'success', title: 'Kaydedildi', message: 'Pazaryeri listeleme bilgisi güncellendi.' })
}
</script>

<style scoped>
.page-header { margin-bottom: 20px; }
.page-title { font-size: 22px; font-weight: 700; color: #1a1a2e; line-height: 1.2; }
.page-subtitle { font-size: 13px; color: #888; margin-top: 4px; }

.card { background: #fff; border-radius: 16px; border: 1px solid #ebebf0; box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04); padding: 18px 20px; }
.search-card { margin-bottom: 16px; }

.search-box {
	display: flex; align-items: center; gap: 8px;
	background: #f5f5f8; border: 1px solid #e8e8f0; border-radius: 9px; padding: 9px 14px;
}
.search-box svg { color: #aaa; flex-shrink: 0; }
.search-box input { border: none; background: none; outline: none; font-family: inherit; font-size: 13.5px; width: 100%; }
.search-box input::placeholder { color: #bbb; }

.search-status { padding: 12px 4px; font-size: 12.5px; color: #888; }

.result-list { list-style: none; margin: 10px 0 0; padding: 0; border-top: 1px solid #f0f0f5; }
.result-item {
	width: 100%; display: flex; justify-content: space-between; align-items: center;
	background: none; border: none; text-align: left; cursor: pointer;
	padding: 10px 6px; font-family: inherit; border-bottom: 1px solid #f5f5f8;
}
.result-item:hover { background: #fafafe; }
.result-name { font-size: 13.5px; font-weight: 600; color: #1a1a2e; }
.result-sku { font-size: 11.5px; color: #888; font-family: 'SF Mono', Consolas, monospace; }

.active-product-header { display: flex; align-items: baseline; gap: 10px; margin-bottom: 14px; }
.active-product-name { font-size: 15px; font-weight: 700; color: #1a1a2e; }
.active-product-sku { font-size: 12px; color: #888; font-family: 'SF Mono', Consolas, monospace; }

.mp-list { display: flex; flex-wrap: wrap; gap: 10px; }
.mp-item {
	display: flex; align-items: center; gap: 8px;
	background: #fafafe; border: 1.5px solid #e8e8f0; border-radius: 10px;
	padding: 8px 14px; cursor: pointer; font-family: inherit; transition: border-color .15s, background .15s;
}
.mp-item:hover { border-color: rgb(var(--color-primary)); background: #fff; }
.mp-badge {
	display: inline-flex; align-items: center; justify-content: center;
	width: 26px; height: 26px; color: #fff; font-size: 10px; font-weight: 800;
	border-radius: 7px; letter-spacing: 0.02em;
}
.mp-name { font-size: 13px; font-weight: 600; color: #1a1a2e; }
</style>
```

- [ ] **Step 2: Inertia render testinin geçtiğini doğrula**

Run: `php artisan test tests/Feature/Marketplace/ProductListingsPageTest.php`
Expected: PASS.

- [ ] **Step 3: Dev sunucusunda manuel doğrulama**

`/marketplace/products` sayfasını aç, bir ürün ara ve seç, bir pazaryeri rozetine tıkla — drawer açılıp mevcut/varsayılan listeleme verisini gösteriyor, kaydetme çalışıyor.

- [ ] **Step 4: Commit**

```bash
git add Modules/Marketplace/Resources/assets/js/Pages/ProductListings.vue
git commit -m "feat(marketplace): ProductListings.vue sayfasini ekle"
```

---

## Task 11: Products.vue'den pazaryeri UI'sini kaldır

**Files:**
- Modify: `Modules/Product/Resources/assets/js/Pages/Products.vue`

- [ ] **Step 1: Template'den pazaryeri sütununu ve drawer'ı kaldır**

`Modules/Product/Resources/assets/js/Pages/Products.vue` — tablo başlığından `<th class="col-mp">Pazaryeri</th>` satırını sil (satır 156).

Eski:
```html
						<th class="col-id">ID</th>
						<th class="col-img">Görsel</th>
						<th class="col-name">Ürün Adı</th>
						<th class="col-price">Site Fiyatı</th>
						<th class="col-mp">Pazaryeri</th>
						<th class="col-stock">Stok</th>
```
Yeni:
```html
						<th class="col-id">ID</th>
						<th class="col-img">Görsel</th>
						<th class="col-name">Ürün Adı</th>
						<th class="col-price">Site Fiyatı</th>
						<th class="col-stock">Stok</th>
```

Tablo gövdesinden `<td class="col-mp">` bloğunu tamamen sil (satır 200-223):

Eski:
```html
						<td class="col-mp">
							<div v-if="marketplacesFor(p).length" class="mp-list">
								<button
									v-for="mp in marketplacesFor(p)"
									:key="mp.key"
									type="button"
									class="mp-item"
									:class="{ sent: listingSummary(p, mp)?.isSent }"
									:title="mp.name"
									@click="openListing(p, mp)"
								>
									<img
										v-if="!logoFailed[mp.key]"
										class="mp-logo-img"
										:src="`/images/marketplaces/${mp.key}.svg`"
										:alt="mp.name"
										@error="onLogoError(mp.key)"
									/>
									<span v-else class="mp-badge" :style="{ background: mp.color || '#888' }">{{ mp.logoText }}</span>
									<span class="mp-price">{{ mpPrice(p, mp) }}</span>
								</button>
							</div>
							<span v-else class="mp-empty">—</span>
						</td>
						<td class="col-stock">
```
Yeni:
```html
						<td class="col-stock">
```

`<MarketplaceListingDrawer .../>` bloğunu (satır 272-278) sil; `</div>` template'in son kapanışı olarak kalır:

Eski:
```html
		<MarketplaceListingDrawer
			:open="listingOpen"
			:product-id="activeProduct?.id"
			:marketplace="activeMarketplace"
			@close="listingOpen = false"
			@saved="onListingSaved"
		/>
	</div>
</template>
```
Yeni:
```html
	</div>
</template>
```

- [ ] **Step 2: Script'ten pazaryeri kodunu kaldır**

Import satırını sil:
```js
import MarketplaceListingDrawer from '@Modules/Marketplace/Resources/assets/js/Components/MarketplaceListingDrawer.vue'
```

`defineProps`'tan `marketplaces` alanını sil:

Eski:
```js
const props = defineProps({
	products: { type: Array, default: () => [] },
	categories: { type: Array, default: () => [] },
	brands: { type: Array, default: () => [] },
	favoriteIds: { type: Array, default: () => [] },
	marketplaces: { type: Array, default: () => [] },
})
```
Yeni:
```js
const props = defineProps({
	products: { type: Array, default: () => [] },
	categories: { type: Array, default: () => [] },
	brands: { type: Array, default: () => [] },
	favoriteIds: { type: Array, default: () => [] },
})
```

Şu bloğu tamamen sil (`/* ── Pazaryeri rozetleri ── */`'den `/* ── Filtre işlemi ── */`'ye kadar olan her şey — `marketplacesFor`, listing drawer state, `logoFailed`/`onLogoError`, `MP_PRICE_FACTOR`/`platformPrice`):

```js
/* ── Pazaryeri rozetleri ──
 * Ürünün kategorisine eşlenmiş pazaryerleri varsa onları kullan; yoksa
 * (yapım aşaması) test amaçlı tüm pazaryerlerini göster. */
function marketplacesFor(p) {
	return (p.marketplaces && p.marketplaces.length) ? p.marketplaces : props.marketplaces
}

/* ── Pazaryeri listeleme drawer ── */
const listingOpen = ref(false)
const activeProduct = ref(null)
const activeMarketplace = ref(null)
const listingOverlay = reactive({}) // `${productId}:${key}` -> { price, isSent }

function listingSummary(p, mp) {
	return listingOverlay[`${p.id}:${mp.key}`] ?? p.listings?.[mp.key] ?? null
}
function mpPrice(p, mp) {
	const s = listingSummary(p, mp)
	return s && s.price != null ? formatPrice(s.price) : platformPrice(p, mp)
}
function openListing(p, mp) {
	activeProduct.value = p
	activeMarketplace.value = mp
	listingOpen.value = true
}
function onListingSaved(payload) {
	if (!activeProduct.value) return
	listingOverlay[`${activeProduct.value.id}:${payload.marketplaceKey}`] = {
		price: payload.price,
		isSent: payload.isSent,
	}
}

// Logo görseli yüklenemezse renkli text rozet'e düş.
const logoFailed = ref({})
function onLogoError(key) { logoFailed.value[key] = true }

// Platforma özel fiyat henüz yok; test için site fiyatından deterministik
// bir placeholder üret (her pazaryeri için sabit çarpan).
const MP_PRICE_FACTOR = {
	trendyol: 1.00,
	hepsiburada: 1.05,
	amazon: 1.08,
	n11: 1.03,
	gittigidiyor: 1.02,
}
function platformPrice(p, mp) {
	const factor = MP_PRICE_FACTOR[mp.key] ?? 1
	return formatPrice((p.price ?? 0) * factor)
}

```

`import { ref, computed, watch, inject, reactive } from 'vue'` satırındaki `reactive` importunu kaldır (artık kullanılmıyor):

Eski:
```js
import { ref, computed, watch, inject, reactive } from 'vue'
```
Yeni:
```js
import { ref, computed, watch, inject } from 'vue'
```

- [ ] **Step 3: Style'dan pazaryeri CSS'ini kaldır**

`.col-mp` genişlik tanımını sil:
```css
.col-mp { width: 360px; }
```

`.mp-list`, `.mp-item`, `.mp-badge`, `.mp-logo-img`, `.mp-price`, `.mp-empty` bloklarının tamamını sil:
```css
.mp-list {
	display: flex; flex-wrap: nowrap; gap: 8px;
	overflow-x: auto; padding-bottom: 2px;
}
.mp-list::-webkit-scrollbar { height: 4px; }
.mp-list::-webkit-scrollbar-thumb { background: #ddd; border-radius: 4px; }
.mp-item { display: flex; flex-direction: column; align-items: center; gap: 3px; flex: 0 0 auto; background: none; border: none; cursor: pointer; padding: 2px; opacity: .55; transition: opacity .12s; }
.mp-item:hover { opacity: 1; }
.mp-item.sent { opacity: 1; }
.mp-badge {
	display: inline-flex; align-items: center; justify-content: center;
	width: 26px; height: 26px;
	color: #fff; font-size: 10px; font-weight: 800;
	border-radius: 7px; letter-spacing: 0.02em;
}
.mp-logo-img { width: 26px; height: 26px; display: block; border-radius: 7px; }
.mp-price { font-size: 11px; font-weight: 600; color: #555; white-space: nowrap; }
.mp-empty { color: #ccc; }
```

Son medya sorgusunu güncelle:

Eski:
```css
@media (max-width: 760px) {
	.col-mp, .col-id { display: none; }
}
```
Yeni:
```css
@media (max-width: 760px) {
	.col-id { display: none; }
}
```

- [ ] **Step 4: Frontend build kontrolü**

Run: `npm run build`
Expected: derleme hatasız tamamlanır.

- [ ] **Step 5: Dev sunucusunda manuel doğrulama**

`/products` sayfasını aç: katalog tablosu çalışıyor, pazaryeri sütunu artık yok, ürün ekle/düzenle/sil işlemleri etkilenmedi.

- [ ] **Step 6: Commit**

```bash
git add Modules/Product/Resources/assets/js/Pages/Products.vue
git commit -m "refactor(product): Products.vue'den pazaryeri UI'si kaldırıldı (Marketplace::ProductListings'e taşındı)"
```

---

## Task 12: Product/Category modellerinden ve ProductController/Presenter'dan Marketplace referanslarını kaldır

**Files:**
- Modify: `Modules/Product/Models/Product.php`
- Modify: `Modules/Product/Models/Category.php`
- Modify: `Modules/Product/Http/Controllers/ProductController.php`
- Modify: `Modules/Product/Services/ProductCatalogPresenter.php`

- [ ] **Step 1: `Product::listings()` ilişkisini kaldır**

`Modules/Product/Models/Product.php` — importu kaldır:

Eski:
```php
use Illuminate\Support\Str;
use Modules\Marketplace\Models\ProductMarketplaceListing;
```
Yeni:
```php
use Illuminate\Support\Str;
```

Metodu kaldır:

Eski:
```php
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    public function listings(): HasMany
    {
        return $this->hasMany(ProductMarketplaceListing::class);
    }

    public function favorites(): HasMany
```
Yeni:
```php
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    public function favorites(): HasMany
```

- [ ] **Step 2: `Category::marketplaceMappings()` ilişkisini kaldır**

`Modules/Product/Models/Category.php` — importu kaldır:

Eski:
```php
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Marketplace\Models\CategoryMarketplaceMapping;
```
Yeni:
```php
use Illuminate\Database\Eloquent\SoftDeletes;
```

Metodu kaldır:

Eski:
```php
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function marketplaceMappings(): HasMany
    {
        return $this->hasMany(CategoryMarketplaceMapping::class);
    }

    public function products(): HasMany
```
Yeni:
```php
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function products(): HasMany
```

- [ ] **Step 3: `ProductController@index`'ten Marketplace kullanımını kaldır**

`Modules/Product/Http/Controllers/ProductController.php` — importu kaldır:

Eski:
```php
use Modules\Product\Models\Category;
use Modules\Marketplace\Models\Marketplace;
use Modules\Product\Models\Product;
```
Yeni:
```php
use Modules\Product\Models\Category;
use Modules\Product\Models\Product;
```

`index()` metodundaki eager-load'ları sadeleştir — eski:
```php
        $productCollection = Product::query()
            ->accessibleToTenant($tenant?->id)
            ->with([
                'category:id,name,slug',
                'category.marketplaceMappings:id,category_id,marketplace_id',
                'category.marketplaceMappings.marketplace:id,key,name,color,logo_text',
                'brand:id,slug,name',
                'variants',
                'images' => fn ($q) => $q->orderByDesc('is_cover')->orderBy('sort_order'),
                'listings.marketplace:id,key',
            ])
            ->withSum('variants as variants_total_stock', 'stock')
            ->orderByDesc('is_new')
            ->orderByDesc('id')
            ->get();
```
Yeni:
```php
        $productCollection = Product::query()
            ->accessibleToTenant($tenant?->id)
            ->with([
                'category:id,name,slug',
                'brand:id,slug,name',
                'variants',
                'images' => fn ($q) => $q->orderByDesc('is_cover')->orderBy('sort_order'),
            ])
            ->withSum('variants as variants_total_stock', 'stock')
            ->orderByDesc('is_new')
            ->orderByDesc('id')
            ->get();
```

`$marketplaces` bloğunu ve Inertia prop'unu kaldır — eski:
```php
        $favoriteIds = ProductFavorite::query()
            ->where('user_id', $user->id)
            ->pluck('product_id')
            ->all();

        // Tüm pazaryeri listesi (eşleşmesi olmayan üründe de ikon göstermek için).
        $marketplaces = Marketplace::query()
            ->orderBy('sort_order')
            ->get(['key', 'name', 'color', 'logo_text'])
            ->map(fn (Marketplace $m) => [
                'key'      => $m->key,
                'name'     => $m->name,
                'color'    => $m->color,
                'logoText' => $m->logo_text,
            ]);

        return Inertia::render('Product::Products', [
            'products'     => $products,
            'categories'   => $categories,
            'brands'       => $brands,
            'favoriteIds'  => $favoriteIds,
            'marketplaces' => $marketplaces,
        ]);
```
Yeni:
```php
        $favoriteIds = ProductFavorite::query()
            ->where('user_id', $user->id)
            ->pluck('product_id')
            ->all();

        return Inertia::render('Product::Products', [
            'products'    => $products,
            'categories'  => $categories,
            'brands'      => $brands,
            'favoriteIds' => $favoriteIds,
        ]);
```

- [ ] **Step 4: `ProductCatalogPresenter::catalogRow()`'dan pazaryeri alanlarını kaldır**

`Modules/Product/Services/ProductCatalogPresenter.php` — eski:
```php
            'barcode'           => $p->barcode,
            'desi'              => $p->desi !== null ? (float) $p->desi : null,
            'marketplaces'      => optional($p->category)->marketplaceMappings
                ?->map(fn ($m) => [
                    'key'      => $m->marketplace?->key,
                    'name'     => $m->marketplace?->name,
                    'color'    => $m->marketplace?->color,
                    'logoText' => $m->marketplace?->logo_text,
                ])->filter(fn ($x) => $x['key'])->values()->all() ?? [],
            'listings'          => $p->listings->mapWithKeys(fn ($l) => [
                $l->marketplace->key => [
                    'price'  => $l->price !== null ? (float) $l->price : null,
                    'isSent' => (bool) $l->is_sent,
                ],
            ])->all(),
            'stock'             => (int) ($p->variants_total_stock ?? 0),
```
Yeni:
```php
            'barcode'           => $p->barcode,
            'desi'              => $p->desi !== null ? (float) $p->desi : null,
            'stock'             => (int) ($p->variants_total_stock ?? 0),
```

- [ ] **Step 5: Product modülünde artık hiçbir `Modules\Marketplace` referansı kalmadığını doğrula**

Run:
```bash
grep -rn "Modules\\\\Marketplace" Modules/Product --include="*.php"
grep -rn "@Modules/Marketplace" Modules/Product --include="*.vue"
```
Expected: **hiçbir çıktı yok** (boş).

- [ ] **Step 6: Product test paketini çalıştır**

Run: `php artisan test --filter=Product`
Expected: tüm testler PASS.

- [ ] **Step 7: Commit**

```bash
git add Modules/Product/Models/Product.php Modules/Product/Models/Category.php Modules/Product/Http/Controllers/ProductController.php Modules/Product/Services/ProductCatalogPresenter.php
git commit -m "refactor(product): Product/Category modellerinden ve ProductController'dan Marketplace bağımlılığı kaldırıldı"
```

---

## Task 13: Tam regresyon doğrulaması

**Files:** (yok — yalnız doğrulama)

- [ ] **Step 1: Tüm ilgili test paketlerini çalıştır**

Run:
```bash
php artisan test --filter=Product
php artisan test --filter=Marketplace
php artisan test --filter=Tenant
```
Expected: hepsi PASS, hiç FAIL yok.

- [ ] **Step 2: Route listesinin beklenen şekilde olduğunu doğrula**

Run:
```bash
php artisan route:list --name=marketplace
php artisan route:list --name=products.categories
php artisan route:list --name=products.listings
```
Expected: `marketplace.categories.*` ve `marketplace.products.*` route'ları listede var; `products.categories.marketplaces.*`, `products.marketplaces.connect`, `products.listings.*` **listede yok**.

- [ ] **Step 3: Tam test suite'ini çalıştır (genel regresyon)**

Run: `php artisan test`
Expected: tüm suite PASS (bu görev kapsamında değiştirilmemiş modüller de dahil regresyon yok).

- [ ] **Step 4: Frontend build**

Run: `npm run build`
Expected: hatasız tamamlanır.

- [ ] **Step 5: `schema:audit` çalıştır (CLAUDE.md disiplini — bu iş migration içermiyor ama garanti olsun)**

Run: `php artisan schema:audit`
Expected: yeni orphan tablo/kolon raporlanmaz (bu iş şema değiştirmedi).

- [ ] **Step 6: Tasarım dokümanını "Uygulandı" olarak işaretle**

`docs/superpowers/specs/2026-07-11-marketplace-module-isolation-design.md` dosyasının başındaki `**Durum:**` satırını güncelle:

Eski:
```
**Durum:** Onaylandı, uygulama planı bekleniyor
```
Yeni:
```
**Durum:** Uygulandı (2026-07-11)
```

- [ ] **Step 7: Son commit**

```bash
git add docs/superpowers/specs/2026-07-11-marketplace-module-isolation-design.md
git commit -m "docs: Marketplace modül izolasyonu tasarımını 'uygulandı' olarak işaretle"
```
