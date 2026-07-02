# Tenant Self-Servis Kullanıcı Yönetimi — Uygulama Planı (Faz 1)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Tenant yöneticisinin, kendi tenant'ı içinde granüler portal izinleriyle alt kullanıcılar oluşturup yönetebilmesi (şifre-belirleme akışı).

**Architecture:** Yeni `portal.users.manage` izni yöneticiyi (`tenant` rolü) kapıda tutar. Alt kullanıcı = `tenant-user` rolü (baseline `portal.access`) + yöneticinin seçtiği doğrudan `portal.*` izinleri. Yönetim ekranı portal subdomain'inde yaşar, `TenantUserService` üzerinden yürür, `Gate::define('portal-user.manage')` ile tenant-scope + kendi/yönetici koruması yapılır.

**Tech Stack:** Laravel 11, PostgreSQL, spatie/laravel-permission, Inertia + Vue 3, Pest/PHPUnit.

## Global Constraints

- DB disiplini (CLAUDE.md): ileri-yönlü migration, gerçek `down()`, migration sonrası `php artisan schema:audit`.
- Yetki dizesi tek kaynak: yeni izinler `TenantPermissionSeeder`, roller `RolePermissionSeeder`.
- Vue: `FormField`+`FormInput`, `useDrawer`/`useModal`/`useToast`; legacy `Components2/` KULLANMA.
- `tenant_id` asla request input'undan alınmaz; her zaman `current_tenant_id`/route tenant'ından zorlanır.
- Portal test domain'i mevcut testlerle aynı: `http://{slug}.bizimsite.test/...`.
- Alt kullanıcıya `portal.access`, `portal.users.manage` veya admin izinleri **doğrudan** verilmez.

## Dosya Yapısı

- `Modules/Tenant/database/migrations/2026_07_02_120000_add_is_active_to_users_table.php` — `users.is_active`.
- `Modules/Tenant/database/migrations/2026_07_02_120100_add_soft_deletes_to_users_table.php` — `users.deleted_at`.
- `app/Models/User.php` — `SoftDeletes` trait, `is_active` fillable+cast.
- `database/seeders/RolePermissionSeeder.php` — `tenant-user` rolü ekle.
- `Modules/Tenant/database/seeders/TenantPermissionSeeder.php` — `portal.users.manage` izni + `tenant-user` role ataması.
- `Modules/Tenant/Services/TenantUserService.php` — CRUD orkestrasyonu + atanabilir izin kataloğu.
- `Modules/Tenant/Http/Requests/StorePortalUserRequest.php`, `UpdatePortalUserRequest.php`.
- `Modules/Tenant/Http/Middleware/EnsureUserActive.php` — pasif kullanıcı 403+logout.
- `app/Http/Requests/Auth/LoginRequest.php` — login'de pasif kullanıcı reddi.
- `bootstrap/app.php` — `active` middleware alias.
- `Modules/Tenant/Providers/TenantServiceProvider.php` — `Gate::define('portal-user.manage')`.
- `Modules/Tenant/Http/Controllers/Portal/PortalUserController.php` — index/store/update/destroy/toggleActive/resetPassword.
- `Modules/Tenant/routes/portal.php` — `/users` rota grubu.
- `Modules/Tenant/Resources/assets/js/Pages/Portal/Users/Index.vue` — liste + drawer.
- `resources/js/Layouts/TenantPortalLayout.vue` — "Kullanıcılar" nav girişi (yetkiye bağlı).
- Testler: `tests/Feature/Tenant/Portal/PortalUserManagementTest.php`.

---

### Task 1: `users` migration'ları + User modeli (is_active + soft delete)

**Files:**
- Create: `Modules/Tenant/database/migrations/2026_07_02_120000_add_is_active_to_users_table.php`
- Create: `Modules/Tenant/database/migrations/2026_07_02_120100_add_soft_deletes_to_users_table.php`
- Modify: `app/Models/User.php`
- Test: `tests/Feature/Tenant/Portal/UserSoftDeleteAndActiveTest.php`

**Interfaces:**
- Produces: `User` modeli `is_active` (bool, cast) + `SoftDeletes` (deleted_at) taşır.

- [ ] **Step 1: Failing test yaz**

```php
<?php
// tests/Feature/Tenant/Portal/UserSoftDeleteAndActiveTest.php
namespace Tests\Feature\Tenant\Portal;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserSoftDeleteAndActiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_defaults_to_active(): void
    {
        $user = User::factory()->create();
        $this->assertTrue($user->fresh()->is_active);
    }

    public function test_user_soft_deletes(): void
    {
        $user = User::factory()->create();
        $id = $user->id;
        $user->delete();

        $this->assertSoftDeleted('users', ['id' => $id]);
        $this->assertNull(User::find($id));
        $this->assertNotNull(User::withTrashed()->find($id));
    }
}
```

- [ ] **Step 2: Testi çalıştır, başarısız olduğunu gör**

Run: `php artisan test tests/Feature/Tenant/Portal/UserSoftDeleteAndActiveTest.php`
Expected: FAIL — `is_active` kolonu yok / `assertSoftDeleted` başarısız.

- [ ] **Step 3: is_active migration'ı yaz**

```php
<?php
// 2026_07_02_120000_add_is_active_to_users_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};
```

- [ ] **Step 4: soft delete migration'ı yaz**

```php
<?php
// 2026_07_02_120100_add_soft_deletes_to_users_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
```

- [ ] **Step 5: User modelini güncelle**

`app/Models/User.php` içinde:
- `use Illuminate\Database\Eloquent\SoftDeletes;` import et.
- `use HasFactory, Notifiable, HasRoles;` → `use HasFactory, Notifiable, HasRoles, SoftDeletes;`
- `$fillable` dizisine `'is_active',` ekle (mevcut `'tenant_id',` satırının altına).
- `casts()` dönüşüne `'is_active' => 'boolean',` ekle.

- [ ] **Step 6: Testi çalıştır, geçtiğini gör**

Run: `php artisan test tests/Feature/Tenant/Portal/UserSoftDeleteAndActiveTest.php`
Expected: PASS (2 test).

- [ ] **Step 7: schema:audit çalıştır**

Run: `php artisan schema:audit`
Expected: Çalışır, `storage/app/schema-audit.json` üretir; `users.is_active`/`deleted_at` orphan olarak işaretlenmez (modelde tanımlı).

- [ ] **Step 8: Commit**

```bash
git add Modules/Tenant/database/migrations/2026_07_02_1200*.php app/Models/User.php tests/Feature/Tenant/Portal/UserSoftDeleteAndActiveTest.php
git commit -m "feat(tenant): users tablosuna is_active + soft delete"
```

---

### Task 2: RBAC — `portal.users.manage` izni + `tenant-user` rolü

**Files:**
- Modify: `database/seeders/RolePermissionSeeder.php:37`
- Modify: `Modules/Tenant/database/seeders/TenantPermissionSeeder.php`
- Test: `tests/Feature/Tenant/Portal/PortalUserRbacTest.php`

**Interfaces:**
- Produces: `portal.users.manage` izni (`tenant` rolünde), `tenant-user` rolü (`portal.access` taşır).

- [ ] **Step 1: Failing test yaz**

```php
<?php
// tests/Feature/Tenant/Portal/PortalUserRbacTest.php
namespace Tests\Feature\Tenant\Portal;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PortalUserRbacTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_users_manage_and_tenant_user_role(): void
    {
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);

        $tenant = Role::where('name', 'tenant')->where('guard_name', 'web')->first();
        $this->assertNotNull($tenant);
        $this->assertTrue($tenant->hasPermissionTo('portal.users.manage'));

        $tenantUser = Role::where('name', 'tenant-user')->where('guard_name', 'web')->first();
        $this->assertNotNull($tenantUser);
        $this->assertTrue($tenantUser->hasPermissionTo('portal.access'));
        $this->assertFalse($tenantUser->hasPermissionTo('portal.users.manage'));
        $this->assertFalse($tenantUser->hasPermissionTo('portal.checkout'));
    }
}
```

- [ ] **Step 2: Testi çalıştır, başarısız olduğunu gör**

Run: `php artisan test tests/Feature/Tenant/Portal/PortalUserRbacTest.php`
Expected: FAIL — `tenant-user` rolü yok / `portal.users.manage` izni yok.

- [ ] **Step 3: RolePermissionSeeder'a rolü ekle**

`database/seeders/RolePermissionSeeder.php` içinde rol döngüsünü güncelle:

```php
// 1) Roller — izin seeder'ları bunlara atama yaptığı için ÖNCE oluşturulur.
foreach (['superadmin', 'tenant', 'tenant-user'] as $role) {
    Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
}
```

- [ ] **Step 4: TenantPermissionSeeder'a izin + atamaları ekle**

`Modules/Tenant/database/seeders/TenantPermissionSeeder.php`:

`$adminPermissions` dizisine ekle:
```php
'portal.users.manage'      => 'Portal — Kullanıcı Yönet',
```

`$tenantRole` bloğunun `givePermissionTo([...])` listesine `'portal.users.manage',` ekle.

`run()` metodunun sonuna (return'dan önce) `tenant-user` rolü baseline ataması ekle:
```php
        // Alt kullanıcılar için baseline rol: yalnız giriş (portal.access).
        // Granüler portal.* izinleri kullanıcıya doğrudan atanır (TenantUserService).
        $tenantUserRole = Role::where('name', 'tenant-user')->where('guard_name', 'web')->first();
        if ($tenantUserRole) {
            $tenantUserRole->givePermissionTo('portal.access');
        }
```

- [ ] **Step 5: Testi çalıştır, geçtiğini gör**

Run: `php artisan test tests/Feature/Tenant/Portal/PortalUserRbacTest.php`
Expected: PASS.

- [ ] **Step 6: Seeder'ı gerçek DB'ye uygula**

Run: `php artisan db:seed --class="Database\Seeders\RolePermissionSeeder" --force`
Expected: `INFO Seeding database.` — idempotent.

- [ ] **Step 7: Commit**

```bash
git add database/seeders/RolePermissionSeeder.php Modules/Tenant/database/seeders/TenantPermissionSeeder.php tests/Feature/Tenant/Portal/PortalUserRbacTest.php
git commit -m "feat(tenant): portal.users.manage izni + tenant-user rolü"
```

---

### Task 3: `TenantUserService`

**Files:**
- Create: `Modules/Tenant/Services/TenantUserService.php`
- Test: `tests/Feature/Tenant/Portal/TenantUserServiceTest.php`

**Interfaces:**
- Produces:
  - `TenantUserService::ASSIGNABLE_PERMISSIONS` (array<string,array{label,group}>)
  - `TenantUserService::assignableNames(): array` (izin adı listesi)
  - `create(Tenant $tenant, array $data): User` — data: `name,email,password,permissions[]`
  - `update(User $user, array $data): User` — data: `name,permissions[]`
  - `resetPassword(User $user, string $password): void`
  - `toggleActive(User $user): void`
  - `delete(User $user): void`

- [ ] **Step 1: Failing test yaz**

```php
<?php
// tests/Feature/Tenant/Portal/TenantUserServiceTest.php
namespace Tests\Feature\Tenant\Portal;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tenant\Models\Tenant;
use Modules\Tenant\Services\TenantUserService;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TenantUserServiceTest extends TestCase
{
    use RefreshDatabase;

    private function bootRbac(): void
    {
        foreach (['portal.access', 'portal.orders.view', 'portal.checkout', 'portal.users.manage'] as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }
        Role::firstOrCreate(['name' => 'tenant-user', 'guard_name' => 'web'])
            ->givePermissionTo('portal.access');
    }

    public function test_create_assigns_role_and_only_selected_permissions(): void
    {
        $this->bootRbac();
        $tenant = Tenant::factory()->create(['slug' => 'svc-' . uniqid()]);
        $service = app(TenantUserService::class);

        $user = $service->create($tenant, [
            'name' => 'Alt Kullanıcı',
            'email' => 'alt@example.test',
            'password' => 'sifre1234',
            'permissions' => ['portal.orders.view', 'portal.users.manage'], // ikincisi katalog dışı
        ]);

        $this->assertSame($tenant->id, $user->tenant_id);
        $this->assertTrue($user->is_active);
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue($user->hasRole('tenant-user'));
        $this->assertTrue($user->can('portal.access'));      // rolden
        $this->assertTrue($user->can('portal.orders.view')); // doğrudan
        $this->assertFalse($user->can('portal.users.manage')); // katalog dışı → atlandı
    }

    public function test_update_resyncs_direct_permissions(): void
    {
        $this->bootRbac();
        $tenant = Tenant::factory()->create(['slug' => 'svc-' . uniqid()]);
        $service = app(TenantUserService::class);
        $user = $service->create($tenant, [
            'name' => 'A', 'email' => 'b@example.test', 'password' => 'sifre1234',
            'permissions' => ['portal.orders.view'],
        ]);

        $service->update($user, ['name' => 'A2', 'permissions' => ['portal.checkout']]);
        $user = $user->fresh();

        $this->assertSame('A2', $user->name);
        $this->assertFalse($user->can('portal.orders.view'));
        $this->assertTrue($user->can('portal.checkout'));
        $this->assertTrue($user->can('portal.access')); // baseline korunur
    }

    public function test_toggle_active_and_delete(): void
    {
        $this->bootRbac();
        $tenant = Tenant::factory()->create(['slug' => 'svc-' . uniqid()]);
        $service = app(TenantUserService::class);
        $user = $service->create($tenant, [
            'name' => 'A', 'email' => 'c@example.test', 'password' => 'sifre1234', 'permissions' => [],
        ]);

        $service->toggleActive($user);
        $this->assertFalse($user->fresh()->is_active);

        $service->delete($user);
        $this->assertSoftDeleted('users', ['id' => $user->id]);
    }
}
```

- [ ] **Step 2: Testi çalıştır, başarısız olduğunu gör**

Run: `php artisan test tests/Feature/Tenant/Portal/TenantUserServiceTest.php`
Expected: FAIL — `TenantUserService` sınıfı yok.

- [ ] **Step 3: Service'i yaz**

```php
<?php
// Modules/Tenant/Services/TenantUserService.php
namespace Modules\Tenant\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Tenant\Models\Tenant;

class TenantUserService
{
    /**
     * Yöneticinin alt kullanıcıya atayabileceği portal izinleri kataloğu.
     * portal.access baseline (tenant-user rolü) — burada YOK.
     * portal.users.manage ve admin izinleri kasıtlı olarak YOK.
     */
    public const ASSIGNABLE_PERMISSIONS = [
        'portal.orders.view'     => ['label' => 'Siparişleri Görüntüle', 'group' => 'Siparişler & Faturalar'],
        'portal.invoices.view'   => ['label' => 'Faturaları Görüntüle',  'group' => 'Siparişler & Faturalar'],
        'portal.credit.view'     => ['label' => 'Kredi Hareketleri',     'group' => 'Siparişler & Faturalar'],
        'portal.catalog.view'    => ['label' => 'Katalog Görüntüle',     'group' => 'Katalog & Sipariş'],
        'portal.checkout'        => ['label' => 'Sipariş Aç (Checkout)', 'group' => 'Katalog & Sipariş'],
        'marketplace.view-sales' => ['label' => 'Pazaryeri Satışları',   'group' => 'Pazaryeri'],
        'marketplace.sync'       => ['label' => 'Pazaryeri Senkron',     'group' => 'Pazaryeri'],
        'portal.financials.view' => ['label' => 'Kâr/Zarar Dashboard',   'group' => 'Finans & Araçlar'],
        'portal.calculator.use'  => ['label' => 'Kâr Hesabı',            'group' => 'Finans & Araçlar'],
        'portal.feed.access'     => ['label' => 'XML Feed Erişimi',      'group' => 'Finans & Araçlar'],
    ];

    /** @return list<string> */
    public static function assignableNames(): array
    {
        return array_keys(self::ASSIGNABLE_PERMISSIONS);
    }

    public function create(Tenant $tenant, array $data): User
    {
        return DB::transaction(function () use ($tenant, $data) {
            $user = User::create([
                'name'      => $data['name'],
                'email'     => $data['email'],
                'password'  => $data['password'], // 'hashed' cast otomatik hash'ler
                'tenant_id' => $tenant->id,
                'is_active' => true,
            ]);
            $user->forceFill(['email_verified_at' => now()])->save();

            $user->assignRole('tenant-user');
            $user->syncPermissions($this->safePermissions($data['permissions'] ?? []));

            return $user->fresh();
        });
    }

    public function update(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data) {
            $user->update(['name' => $data['name']]);
            $user->syncPermissions($this->safePermissions($data['permissions'] ?? []));

            return $user->fresh();
        });
    }

    public function resetPassword(User $user, string $password): void
    {
        $user->update(['password' => $password]); // 'hashed' cast
    }

    public function toggleActive(User $user): void
    {
        $user->update(['is_active' => ! $user->is_active]);
    }

    public function delete(User $user): void
    {
        $user->delete(); // soft delete
    }

    /** Katalog dışı/izinsiz permission isteğini süz. @return list<string> */
    private function safePermissions(array $requested): array
    {
        return array_values(array_intersect($requested, self::assignableNames()));
    }
}
```

- [ ] **Step 4: Testi çalıştır, geçtiğini gör**

Run: `php artisan test tests/Feature/Tenant/Portal/TenantUserServiceTest.php`
Expected: PASS (3 test).

- [ ] **Step 5: Commit**

```bash
git add Modules/Tenant/Services/TenantUserService.php tests/Feature/Tenant/Portal/TenantUserServiceTest.php
git commit -m "feat(tenant): TenantUserService — alt kullanıcı CRUD + izin kataloğu"
```

---

### Task 4: `Gate::define('portal-user.manage')` — tenant-scope + koruma

**Files:**
- Modify: `Modules/Tenant/Providers/TenantServiceProvider.php:28`
- Test: `tests/Feature/Tenant/Portal/PortalUserGateTest.php`

**Interfaces:**
- Consumes: `TenantUserService` (roller), `User` modeli.
- Produces: Gate ability `portal-user.manage` — `(User $actor, User $target)`; superadmin `Gate::before` ile geçer.

- [ ] **Step 1: Failing test yaz**

```php
<?php
// tests/Feature/Tenant/Portal/PortalUserGateTest.php
namespace Tests\Feature\Tenant\Portal;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Modules\Tenant\Models\Tenant;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PortalUserGateTest extends TestCase
{
    use RefreshDatabase;

    private function bootRbac(): void
    {
        foreach (['portal.access', 'portal.users.manage'] as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }
        Role::firstOrCreate(['name' => 'tenant', 'guard_name' => 'web'])
            ->givePermissionTo(['portal.access', 'portal.users.manage']);
        Role::firstOrCreate(['name' => 'tenant-user', 'guard_name' => 'web'])
            ->givePermissionTo('portal.access');
    }

    public function test_admin_can_manage_own_sub_user_but_not_other_admin_or_cross_tenant(): void
    {
        $this->bootRbac();
        $tenantA = Tenant::factory()->create(['slug' => 'ga-' . uniqid()]);
        $tenantB = Tenant::factory()->create(['slug' => 'gb-' . uniqid()]);

        $admin = User::factory()->create(['tenant_id' => $tenantA->id]);
        $admin->assignRole('tenant');

        $subUser = User::factory()->create(['tenant_id' => $tenantA->id]);
        $subUser->assignRole('tenant-user');

        $otherAdmin = User::factory()->create(['tenant_id' => $tenantA->id]);
        $otherAdmin->assignRole('tenant');

        $crossSub = User::factory()->create(['tenant_id' => $tenantB->id]);
        $crossSub->assignRole('tenant-user');

        $this->assertTrue(Gate::forUser($admin)->allows('portal-user.manage', $subUser));
        $this->assertFalse(Gate::forUser($admin)->allows('portal-user.manage', $otherAdmin));
        $this->assertFalse(Gate::forUser($admin)->allows('portal-user.manage', $crossSub));
    }
}
```

- [ ] **Step 2: Testi çalıştır, başarısız olduğunu gör**

Run: `php artisan test tests/Feature/Tenant/Portal/PortalUserGateTest.php`
Expected: FAIL — ability tanımlı değil (tüm allows false değil; `otherAdmin`/`crossSub` bile true dönebilir çünkü ability yok → false, ama `subUser` de false → assertion 1 patlar).

- [ ] **Step 3: Gate::define ekle**

`Modules/Tenant/Providers/TenantServiceProvider.php` `boot()` içinde, mevcut `Gate::policy(...)` satırlarının altına:

```php
        // Portal alt kullanıcı yönetimi: hedef aynı tenant'a ait bir tenant-user olmalı.
        // (Aktör admin=tenant rolü; portal.users.manage rota middleware'inde kontrol edilir.
        //  Bu ability yalnız hedef-scope + kendini/başka admini yönetememe kuralını uygular.
        //  Superadmin Gate::before ile geçer.)
        Gate::define('portal-user.manage', function (\App\Models\User $actor, \App\Models\User $target): bool {
            return (int) $actor->tenant_id === (int) $target->tenant_id
                && $target->hasRole('tenant-user');
        });
```

- [ ] **Step 4: Testi çalıştır, geçtiğini gör**

Run: `php artisan test tests/Feature/Tenant/Portal/PortalUserGateTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add Modules/Tenant/Providers/TenantServiceProvider.php tests/Feature/Tenant/Portal/PortalUserGateTest.php
git commit -m "feat(tenant): portal-user.manage gate — tenant-scope + admin/self koruması"
```

---

### Task 5: Form Request'ler (Store / Update)

**Files:**
- Create: `Modules/Tenant/Http/Requests/StorePortalUserRequest.php`
- Create: `Modules/Tenant/Http/Requests/UpdatePortalUserRequest.php`
- Test: `tests/Feature/Tenant/Portal/PortalUserRequestValidationTest.php` (Task 7'deki HTTP testlerinde de dolaylı kapsanır; burada birim doğrulama)

**Interfaces:**
- Consumes: `TenantUserService::assignableNames()`.
- Produces: Doğrulanmış `name,email,password?,permissions[]`.

- [ ] **Step 1: StorePortalUserRequest yaz**

```php
<?php
// Modules/Tenant/Http/Requests/StorePortalUserRequest.php
namespace Modules\Tenant\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Tenant\Services\TenantUserService;

class StorePortalUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('portal.users.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'name'          => ['required', 'string', 'max:191'],
            'email'         => ['required', 'email', 'max:191', Rule::unique('users', 'email')],
            'password'      => ['required', 'string', 'min:8', 'confirmed'],
            'permissions'   => ['array'],
            'permissions.*' => [Rule::in(TenantUserService::assignableNames())],
        ];
    }
}
```

- [ ] **Step 2: UpdatePortalUserRequest yaz**

```php
<?php
// Modules/Tenant/Http/Requests/UpdatePortalUserRequest.php
namespace Modules\Tenant\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Tenant\Services\TenantUserService;

class UpdatePortalUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('portal.users.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'name'          => ['required', 'string', 'max:191'],
            'permissions'   => ['array'],
            'permissions.*' => [Rule::in(TenantUserService::assignableNames())],
        ];
    }
}
```

- [ ] **Step 3: Commit** (testler Task 7 HTTP akışında koşacak)

```bash
git add Modules/Tenant/Http/Requests/StorePortalUserRequest.php Modules/Tenant/Http/Requests/UpdatePortalUserRequest.php
git commit -m "feat(tenant): portal kullanıcı Store/Update Form Request'leri"
```

---

### Task 6: `EnsureUserActive` middleware + login reddi

**Files:**
- Create: `Modules/Tenant/Http/Middleware/EnsureUserActive.php`
- Modify: `bootstrap/app.php` (alias + portal grubuna değil — alias'ı tanımla; portal grubuna Task 7'de eklenir)
- Modify: `app/Http/Requests/Auth/LoginRequest.php:45-53`
- Test: `tests/Feature/Tenant/Portal/InactiveUserTest.php`

**Interfaces:**
- Produces: `active` middleware alias; login pasif kullanıcıyı reddeder.

- [ ] **Step 1: Failing test yaz**

```php
<?php
// tests/Feature/Tenant/Portal/InactiveUserTest.php
namespace Tests\Feature\Tenant\Portal;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InactiveUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_inactive_user_cannot_login(): void
    {
        $user = User::factory()->create([
            'password'  => 'sifre1234',
            'is_active' => false,
        ]);

        $response = $this->post('/login', [
            'email'    => $user->email,
            'password' => 'sifre1234',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }
}
```

- [ ] **Step 2: Testi çalıştır, başarısız olduğunu gör**

Run: `php artisan test tests/Feature/Tenant/Portal/InactiveUserTest.php`
Expected: FAIL — pasif kullanıcı giriş yapabiliyor (assertGuest başarısız).

- [ ] **Step 3: LoginRequest'e pasif reddi ekle**

`app/Http/Requests/Auth/LoginRequest.php` `authenticate()` içinde, başarılı `Auth::attempt` sonrası (`RateLimiter::clear(...)` öncesi):

```php
        if (! Auth::user()->is_active) {
            Auth::logout();
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => 'Hesabınız pasif durumda. Yöneticinizle iletişime geçin.',
            ]);
        }

        RateLimiter::clear($this->throttleKey());
```

- [ ] **Step 4: Testi çalıştır, geçtiğini gör**

Run: `php artisan test tests/Feature/Tenant/Portal/InactiveUserTest.php`
Expected: PASS.

- [ ] **Step 5: EnsureUserActive middleware'ı yaz (aktif oturum koruması)**

```php
<?php
// Modules/Tenant/Http/Middleware/EnsureUserActive.php
namespace Modules\Tenant\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user && ! $user->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            abort(403, 'Hesabınız pasif durumda.');
        }

        return $next($request);
    }
}
```

- [ ] **Step 6: Alias'ı kaydet**

`bootstrap/app.php` `$middleware->alias([...])` dizisine ekle:

```php
            'active'             => \Modules\Tenant\Http\Middleware\EnsureUserActive::class,
```

- [ ] **Step 7: Commit**

```bash
git add Modules/Tenant/Http/Middleware/EnsureUserActive.php bootstrap/app.php app/Http/Requests/Auth/LoginRequest.php tests/Feature/Tenant/Portal/InactiveUserTest.php
git commit -m "feat(tenant): pasif kullanıcı login reddi + EnsureUserActive middleware"
```

---

### Task 7: `PortalUserController` + rotalar (uçtan uca HTTP)

**Files:**
- Create: `Modules/Tenant/Http/Controllers/Portal/PortalUserController.php`
- Modify: `Modules/Tenant/routes/portal.php`
- Test: `tests/Feature/Tenant/Portal/PortalUserManagementTest.php`

**Interfaces:**
- Consumes: `TenantUserService`, `StorePortalUserRequest`, `UpdatePortalUserRequest`, `portal-user.manage` gate.
- Produces: `/users` CRUD rotaları (`portal.users.*` isimli), Inertia `Tenant::Portal/Users/Index`.

- [ ] **Step 1: Failing test yaz**

```php
<?php
// tests/Feature/Tenant/Portal/PortalUserManagementTest.php
namespace Tests\Feature\Tenant\Portal;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tenant\Models\Tenant;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PortalUserManagementTest extends TestCase
{
    use RefreshDatabase;

    private function bootRbac(): void
    {
        $perms = [
            'portal.access', 'portal.users.manage', 'portal.orders.view',
            'portal.checkout', 'portal.invoices.view',
        ];
        foreach ($perms as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }
        Role::firstOrCreate(['name' => 'tenant', 'guard_name' => 'web'])->givePermissionTo($perms);
        Role::firstOrCreate(['name' => 'tenant-user', 'guard_name' => 'web'])->givePermissionTo('portal.access');
        Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web'])->givePermissionTo($perms);
    }

    private function admin(Tenant $tenant): User
    {
        $u = User::factory()->create(['tenant_id' => $tenant->id]);
        $u->assignRole('tenant');
        return $u;
    }

    private function base(Tenant $t): string
    {
        return 'http://' . $t->slug . '.bizimsite.test';
    }

    public function test_admin_creates_sub_user_with_selected_permissions(): void
    {
        $this->bootRbac();
        $tenant = Tenant::factory()->create(['slug' => 'pm-' . uniqid()]);
        $this->actingAs($this->admin($tenant));

        $this->post($this->base($tenant) . '/users', [
            'name' => 'Alt', 'email' => 'alt@pm.test',
            'password' => 'sifre1234', 'password_confirmation' => 'sifre1234',
            'permissions' => ['portal.orders.view'],
        ])->assertRedirect();

        $sub = User::where('email', 'alt@pm.test')->first();
        $this->assertNotNull($sub);
        $this->assertSame($tenant->id, $sub->tenant_id);
        $this->assertTrue($sub->hasRole('tenant-user'));
        $this->assertTrue($sub->can('portal.orders.view'));
        $this->assertFalse($sub->can('portal.checkout'));
    }

    public function test_sub_user_cannot_access_unselected_route_but_can_selected(): void
    {
        $this->bootRbac();
        $tenant = Tenant::factory()->create(['slug' => 'pm-' . uniqid()]);
        $admin = $this->admin($tenant);
        $this->actingAs($admin);
        $this->post($this->base($tenant) . '/users', [
            'name' => 'Alt', 'email' => 'alt2@pm.test',
            'password' => 'sifre1234', 'password_confirmation' => 'sifre1234',
            'permissions' => ['portal.orders.view'],
        ]);
        $sub = User::where('email', 'alt2@pm.test')->first();

        $this->actingAs($sub);
        $this->get($this->base($tenant) . '/orders')->assertOk();      // seçili
        $this->get($this->base($tenant) . '/checkout')->assertForbidden(); // seçilmemiş
        $this->get($this->base($tenant) . '/users')->assertForbidden();    // yönetim yok
    }

    public function test_admin_cannot_manage_cross_tenant_user(): void
    {
        $this->bootRbac();
        $tenantA = Tenant::factory()->create(['slug' => 'pa-' . uniqid()]);
        $tenantB = Tenant::factory()->create(['slug' => 'pb-' . uniqid()]);
        $adminA = $this->admin($tenantA);

        $subB = User::factory()->create(['tenant_id' => $tenantB->id]);
        $subB->assignRole('tenant-user');

        $this->actingAs($adminA);
        // adminA, kendi subdomain'inden tenantB kullanıcısını silmeye çalışır
        $this->delete($this->base($tenantA) . '/users/' . $subB->id)->assertForbidden();
    }

    public function test_admin_cannot_delete_self_or_other_admin(): void
    {
        $this->bootRbac();
        $tenant = Tenant::factory()->create(['slug' => 'ps-' . uniqid()]);
        $admin = $this->admin($tenant);
        $otherAdmin = $this->admin($tenant);

        $this->actingAs($admin);
        $this->delete($this->base($tenant) . '/users/' . $admin->id)->assertForbidden();
        $this->delete($this->base($tenant) . '/users/' . $otherAdmin->id)->assertForbidden();
    }

    public function test_reject_admin_permission_grant_to_sub_user(): void
    {
        $this->bootRbac();
        $tenant = Tenant::factory()->create(['slug' => 'pr-' . uniqid()]);
        $this->actingAs($this->admin($tenant));

        $this->post($this->base($tenant) . '/users', [
            'name' => 'Alt', 'email' => 'alt3@pm.test',
            'password' => 'sifre1234', 'password_confirmation' => 'sifre1234',
            'permissions' => ['portal.users.manage'], // katalog dışı
        ])->assertSessionHasErrors('permissions.0');
    }

    public function test_toggle_active_reset_password_and_delete(): void
    {
        $this->bootRbac();
        $tenant = Tenant::factory()->create(['slug' => 'pt-' . uniqid()]);
        $admin = $this->admin($tenant);
        $this->actingAs($admin);
        $this->post($this->base($tenant) . '/users', [
            'name' => 'Alt', 'email' => 'alt4@pm.test',
            'password' => 'sifre1234', 'password_confirmation' => 'sifre1234', 'permissions' => [],
        ]);
        $sub = User::where('email', 'alt4@pm.test')->first();

        $this->post($this->base($tenant) . '/users/' . $sub->id . '/toggle-active')->assertRedirect();
        $this->assertFalse($sub->fresh()->is_active);

        $this->post($this->base($tenant) . '/users/' . $sub->id . '/reset-password', [
            'password' => 'yenisifre99', 'password_confirmation' => 'yenisifre99',
        ])->assertRedirect();

        $this->delete($this->base($tenant) . '/users/' . $sub->id)->assertRedirect();
        $this->assertSoftDeleted('users', ['id' => $sub->id]);
    }
}
```

- [ ] **Step 2: Testi çalıştır, başarısız olduğunu gör**

Run: `php artisan test tests/Feature/Tenant/Portal/PortalUserManagementTest.php`
Expected: FAIL — `/users` rotaları yok (404/500).

- [ ] **Step 3: Controller'ı yaz**

```php
<?php
// Modules/Tenant/Http/Controllers/Portal/PortalUserController.php
namespace Modules\Tenant\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Tenant\Http\Requests\StorePortalUserRequest;
use Modules\Tenant\Http\Requests\UpdatePortalUserRequest;
use Modules\Tenant\Models\Tenant;
use Modules\Tenant\Services\TenantUserService;

class PortalUserController extends Controller
{
    public function __construct(private TenantUserService $service) {}

    public function index(Request $request): Response
    {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('tenant');

        $users = User::query()
            ->where('tenant_id', $tenant->id)
            ->orderBy('name')
            ->get()
            ->map(fn (User $u) => [
                'id'          => $u->id,
                'name'        => $u->name,
                'email'       => $u->email,
                'is_active'   => (bool) $u->is_active,
                'is_admin'    => $u->hasRole('tenant'),
                'permissions' => $u->getDirectPermissions()->pluck('name')->values(),
            ]);

        return Inertia::render('Tenant::Portal/Users/Index', [
            'tenant'                => [
                'id' => $tenant->id, 'code' => $tenant->code,
                'name' => $tenant->name, 'slug' => $tenant->slug,
            ],
            'users'                 => $users,
            'assignablePermissions' => collect(TenantUserService::ASSIGNABLE_PERMISSIONS)
                ->map(fn ($meta, $name) => ['name' => $name, 'label' => $meta['label'], 'group' => $meta['group']])
                ->values(),
        ]);
    }

    public function store(StorePortalUserRequest $request): RedirectResponse
    {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('tenant');

        $this->service->create($tenant, $request->validated());

        return redirect()->route('portal.users.index')->with('success', 'Kullanıcı eklendi.');
    }

    public function update(UpdatePortalUserRequest $request, User $user): RedirectResponse
    {
        Gate::authorize('portal-user.manage', $user);

        $this->service->update($user, $request->validated());

        return redirect()->route('portal.users.index')->with('success', 'Kullanıcı güncellendi.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('portal-user.manage', $user);
        if ($user->id === $request->user()->id) {
            abort(403, 'Kendinizi silemezsiniz.');
        }

        $this->service->delete($user);

        return redirect()->route('portal.users.index')->with('success', 'Kullanıcı silindi.');
    }

    public function toggleActive(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('portal-user.manage', $user);
        if ($user->id === $request->user()->id) {
            abort(403, 'Kendinizi pasifleştiremezsiniz.');
        }

        $this->service->toggleActive($user);

        return back()->with('success', $user->fresh()->is_active ? 'Kullanıcı aktifleştirildi.' : 'Kullanıcı pasifleştirildi.');
    }

    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('portal-user.manage', $user);

        $data = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $this->service->resetPassword($user, $data['password']);

        return back()->with('success', 'Şifre güncellendi.');
    }
}
```

- [ ] **Step 4: Rotaları ekle**

`Modules/Tenant/routes/portal.php` sonuna ekle (import'u dosya başına ekle: `use Modules\Tenant\Http\Controllers\Portal\PortalUserController;`):

```php
Route::middleware('can:portal.users.manage')->group(function () {
    Route::get('/users',                          [PortalUserController::class, 'index'])->name('users.index');
    Route::post('/users',                         [PortalUserController::class, 'store'])->name('users.store');
    Route::put('/users/{user}',                   [PortalUserController::class, 'update'])->whereNumber('user')->name('users.update');
    Route::delete('/users/{user}',                [PortalUserController::class, 'destroy'])->whereNumber('user')->name('users.destroy');
    Route::post('/users/{user}/toggle-active',    [PortalUserController::class, 'toggleActive'])->whereNumber('user')->name('users.toggle-active');
    Route::post('/users/{user}/reset-password',   [PortalUserController::class, 'resetPassword'])->whereNumber('user')->name('users.reset-password');
});
```

- [ ] **Step 5: Portal grubuna `active` middleware ekle**

`routes/web.php` portal grubunun middleware dizisine `active` ekle (satır ~19):

```php
        ->middleware(['auth', 'verified', 'active', 'tenant.subdomain', 'can:portal.access'])
```

- [ ] **Step 6: Testi çalıştır, geçtiğini gör**

Run: `php artisan test tests/Feature/Tenant/Portal/PortalUserManagementTest.php`
Expected: PASS (6 test).

- [ ] **Step 7: Regresyon — tüm Tenant portal testleri**

Run: `php artisan test tests/Feature/Tenant`
Expected: PASS (mevcut portal testleri `active` middleware'inden etkilenmemeli; kullanıcılar default `is_active=true`).

- [ ] **Step 8: Commit**

```bash
git add Modules/Tenant/Http/Controllers/Portal/PortalUserController.php Modules/Tenant/routes/portal.php routes/web.php tests/Feature/Tenant/Portal/PortalUserManagementTest.php
git commit -m "feat(tenant): portal kullanıcı yönetimi controller + rotalar"
```

---

### Task 8: Vue — `Portal/Users/Index.vue` + nav girişi

**Files:**
- Create: `Modules/Tenant/Resources/assets/js/Pages/Portal/Users/Index.vue`
- Modify: `resources/js/Layouts/TenantPortalLayout.vue:20-21`

**Interfaces:**
- Consumes: controller propları `users`, `assignablePermissions`, `tenant`; rota isimleri `portal.users.*`.

- [ ] **Step 1: Nav girişini ekle (yetkiye bağlı)**

`resources/js/Layouts/TenantPortalLayout.vue` — `<script setup>` içinde `usePage`'den izinleri al (dosyada `page` zaten var; yoksa `import { usePage } from '@inertiajs/vue3'; const page = usePage();`). Nav'da "Ayarlar" satırının üstüne ekle:

```vue
				<Link
					v-if="page.props.auth.permissions.includes('portal.users.manage')"
					href="/users"
					class="portal-nav-link"
					:class="{ active: page.url.startsWith('/users') }"
				>Kullanıcılar</Link>
```

- [ ] **Step 2: Index.vue sayfasını yaz**

```vue
<template>
	<Head title="Kullanıcılar" />
	<div class="portal-users">
		<header class="page-header">
			<div>
				<h1 class="page-title">Kullanıcılar</h1>
				<p class="page-subtitle">Tenant ekibinizi yönetin</p>
			</div>
			<button class="btn-primary" @click="openCreate">Yeni Kullanıcı</button>
		</header>

		<section class="card">
			<table class="data-table">
				<thead>
					<tr><th>Ad</th><th>E-posta</th><th>Rol</th><th>Durum</th><th>İzin</th><th></th></tr>
				</thead>
				<tbody>
					<tr v-for="u in users" :key="u.id">
						<td>{{ u.name }}</td>
						<td class="mono">{{ u.email }}</td>
						<td>{{ u.is_admin ? 'Yönetici' : 'Kullanıcı' }}</td>
						<td>
							<span :class="['badge', u.is_active ? 'ok' : 'off']">
								{{ u.is_active ? 'Aktif' : 'Pasif' }}
							</span>
						</td>
						<td>{{ u.permissions.length }}</td>
						<td class="row-actions">
							<template v-if="!u.is_admin">
								<button @click="openEdit(u)">Düzenle</button>
								<button @click="toggleActive(u)">{{ u.is_active ? 'Pasifleştir' : 'Aktifleştir' }}</button>
								<button @click="openReset(u)">Şifre</button>
								<button class="danger" @click="confirmDelete(u)">Sil</button>
							</template>
							<span v-else class="muted">—</span>
						</td>
					</tr>
				</tbody>
			</table>
		</section>

		<!-- Oluştur / Düzenle drawer -->
		<div v-if="drawer.open" class="drawer-backdrop" @click.self="drawer.open = false">
			<div class="drawer">
				<h2>{{ drawer.mode === 'create' ? 'Yeni Kullanıcı' : 'Kullanıcıyı Düzenle' }}</h2>
				<form @submit.prevent="submit">
					<label class="field">
						<span>Ad</span>
						<input v-model="form.name" type="text" required />
						<small v-if="form.errors.name" class="err">{{ form.errors.name }}</small>
					</label>

					<template v-if="drawer.mode === 'create'">
						<label class="field">
							<span>E-posta</span>
							<input v-model="form.email" type="email" required />
							<small v-if="form.errors.email" class="err">{{ form.errors.email }}</small>
						</label>
						<label class="field">
							<span>Şifre</span>
							<input v-model="form.password" type="password" required />
							<small v-if="form.errors.password" class="err">{{ form.errors.password }}</small>
						</label>
						<label class="field">
							<span>Şifre (tekrar)</span>
							<input v-model="form.password_confirmation" type="password" required />
						</label>
					</template>

					<fieldset class="perms">
						<legend>Portal İzinleri</legend>
						<div v-for="(items, group) in groupedPermissions" :key="group" class="perm-group">
							<h4>{{ group }}</h4>
							<label v-for="p in items" :key="p.name" class="perm-check">
								<input type="checkbox" :value="p.name" v-model="form.permissions" />
								<span>{{ p.label }}</span>
							</label>
						</div>
					</fieldset>

					<div class="drawer-actions">
						<button type="button" @click="drawer.open = false">Vazgeç</button>
						<button type="submit" class="btn-primary" :disabled="form.processing">Kaydet</button>
					</div>
				</form>
			</div>
		</div>

		<!-- Şifre sıfırla drawer -->
		<div v-if="reset.open" class="drawer-backdrop" @click.self="reset.open = false">
			<div class="drawer">
				<h2>Şifre Sıfırla — {{ reset.user?.name }}</h2>
				<form @submit.prevent="submitReset">
					<label class="field">
						<span>Yeni şifre</span>
						<input v-model="resetForm.password" type="password" required />
						<small v-if="resetForm.errors.password" class="err">{{ resetForm.errors.password }}</small>
					</label>
					<label class="field">
						<span>Yeni şifre (tekrar)</span>
						<input v-model="resetForm.password_confirmation" type="password" required />
					</label>
					<div class="drawer-actions">
						<button type="button" @click="reset.open = false">Vazgeç</button>
						<button type="submit" class="btn-primary" :disabled="resetForm.processing">Güncelle</button>
					</div>
				</form>
			</div>
		</div>
	</div>
</template>

<script setup>
import { Head, useForm } from '@inertiajs/vue3'
import { computed, reactive } from 'vue'
import TenantPortalLayout from '@/Layouts/TenantPortalLayout.vue'

defineOptions({ layout: TenantPortalLayout })

const props = defineProps({
	tenant: Object,
	users: Array,
	assignablePermissions: Array,
})

const groupedPermissions = computed(() => {
	const out = {}
	for (const p of props.assignablePermissions) {
		;(out[p.group] ??= []).push(p)
	}
	return out
})

const drawer = reactive({ open: false, mode: 'create', userId: null })
const form = useForm({ name: '', email: '', password: '', password_confirmation: '', permissions: [] })

function openCreate() {
	drawer.mode = 'create'; drawer.userId = null
	form.reset(); form.clearErrors()
	drawer.open = true
}
function openEdit(u) {
	drawer.mode = 'edit'; drawer.userId = u.id
	form.reset(); form.clearErrors()
	form.name = u.name
	form.permissions = [...u.permissions]
	drawer.open = true
}
function submit() {
	if (drawer.mode === 'create') {
		form.post('/users', { onSuccess: () => (drawer.open = false) })
	} else {
		form.put(`/users/${drawer.userId}`, { onSuccess: () => (drawer.open = false) })
	}
}

function toggleActive(u) {
	useForm({}).post(`/users/${u.id}/toggle-active`)
}
function confirmDelete(u) {
	if (confirm(`${u.name} kullanıcısını silmek istediğinize emin misiniz?`)) {
		useForm({}).delete(`/users/${u.id}`)
	}
}

const reset = reactive({ open: false, user: null })
const resetForm = useForm({ password: '', password_confirmation: '' })
function openReset(u) {
	reset.user = u; resetForm.reset(); resetForm.clearErrors()
	reset.open = true
}
function submitReset() {
	resetForm.post(`/users/${reset.user.id}/reset-password`, { onSuccess: () => (reset.open = false) })
}
</script>

<style scoped>
.page-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px; }
.btn-primary { background: #4338ca; color: #fff; border: 0; padding: 8px 14px; border-radius: 8px; cursor: pointer; }
.btn-primary:disabled { opacity: .5; cursor: not-allowed; }
.data-table { width: 100%; border-collapse: collapse; }
.data-table th, .data-table td { text-align: left; padding: 8px 10px; border-bottom: 1px solid #2a2a44; }
.badge.ok { color: #16a34a; } .badge.off { color: #b91c1c; }
.row-actions { display: flex; gap: 6px; } .row-actions .danger { color: #b91c1c; }
.muted { color: #6b7280; }
.drawer-backdrop { position: fixed; inset: 0; background: rgba(0,0,0,.4); display: flex; justify-content: flex-end; }
.drawer { width: 420px; max-width: 90vw; background: #14142a; height: 100%; padding: 20px; overflow-y: auto; }
.field { display: flex; flex-direction: column; gap: 4px; margin-bottom: 12px; }
.field input { padding: 8px; border-radius: 6px; border: 1px solid #2a2a44; background: #0f0f22; color: #fff; }
.err { color: #f87171; font-size: 12px; }
.perms { border: 1px solid #2a2a44; border-radius: 8px; padding: 10px; margin-bottom: 12px; }
.perm-group h4 { margin: 8px 0 4px; font-size: 12px; color: #9ca3af; }
.perm-check { display: flex; gap: 8px; align-items: center; padding: 3px 0; }
.drawer-actions { display: flex; justify-content: flex-end; gap: 8px; }
</style>
```

- [ ] **Step 3: Frontend build (derleme hatası yok doğrulaması)**

Run: `npm run build`
Expected: Başarılı derleme; `Portal/Users/Index.vue` hatasız.

- [ ] **Step 4: Commit**

```bash
git add Modules/Tenant/Resources/assets/js/Pages/Portal/Users/Index.vue resources/js/Layouts/TenantPortalLayout.vue
git commit -m "feat(tenant): portal kullanıcı yönetimi ekranı + nav girişi"
```

---

### Task 9: Manuel doğrulama + tam test turu

**Files:** (yok — doğrulama)

- [ ] **Step 1: Tüm Tenant testleri**

Run: `php artisan test tests/Feature/Tenant`
Expected: Hepsi PASS.

- [ ] **Step 2: schema:audit tekrar**

Run: `php artisan schema:audit`
Expected: `users.is_active`/`deleted_at` orphan değil; yeni şişme yok.

- [ ] **Step 3: Manuel duman testi (opsiyonel, lokal)**

`http://dss.proje.localhost/login` → `dss@proje.localhost` / `password` ile gir → nav'da "Kullanıcılar" görünür → yeni alt kullanıcı oluştur (bir izin seç) → çıkış → yeni kullanıcı ile gir → yalnız seçili alanların erişilebildiğini, "Kullanıcılar"ın görünmediğini doğrula.

---

## Faz 2 (ayrı plan — bu planın kapsamı DIŞINDA)

E-posta davet akışı: davet token tablosu/kolonu, `POST /users/{user}/invite` + `InvitePortalUser` mailable, imzalı "şifreni belirle" public sayfası, drawer'da "Davet gönder" radyo seçeneğinin aktifleştirilmesi. Ayrı spec + plan olarak ele alınacak.

## Self-Review Notları

- **Spec kapsamı:** Faz 1'in tüm maddeleri (RBAC, migration'lar, CRUD, is_active zorlaması, granüler izin, tenant-scope koruması, Vue ekranı, 9 test senaryosu) task'lara eşlendi. Faz 2 (davet) kapsam dışı, açıkça işaretlendi.
- **Placeholder taraması:** Yok — her adımda tam kod/komut var.
- **Tip tutarlılığı:** `assignableNames()`, `ASSIGNABLE_PERMISSIONS`, `portal-user.manage`, `is_admin`/`is_active` prop adları tüm task'larda tutarlı.
