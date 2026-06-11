# Superadmin Dinamik Menü Sistemi — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Navigasyonu hardcoded `AppLayout.vue` dizilerinden, Superadmin'de sürükle-bırak ile yönetilen veritabanı tabanlı bir menü sistemine taşımak; sol sidebar kök menüleri, header aktif kökün child'larını gösterir.

**Architecture:** `superadmin_menus` (self-referencing, sınırsız derinlik) tablosu; `MenuTreeBuilder` servisi izinleri server-side filtreleyip ağaç kurar; ağaç `HandleInertiaRequests::share` ile her response'a `menu` prop'u olarak verilir. Superadmin `Superadmin::Menus` sayfasında `vuedraggable` ile sıralama/nesting yapar. `AppLayout.vue` paylaşılan ağacı render eder.

**Tech Stack:** Laravel 11, nwidart/laravel-modules, Spatie Permission, Inertia + Vue 3, vuedraggable, PHPUnit (Tests\TestCase + RefreshDatabase). JS test runner yok → Vue görevleri manuel doğrulanır.

**Spec:** `docs/superpowers/specs/2026-06-11-superadmin-dynamic-menu-design.md`

---

## Dosya yapısı

**Backend (oluştur):**
- `Modules/Superadmin/database/migrations/2026_06_11_120000_create_superadmin_menus_table.php`
- `Modules/Superadmin/Models/Menu.php`
- `Modules/Superadmin/Services/MenuTreeBuilder.php`
- `Modules/Superadmin/Http/Controllers/MenuController.php`
- `Modules/Superadmin/Http/Requests/StoreMenuRequest.php`
- `Modules/Superadmin/Http/Requests/UpdateMenuRequest.php`
- `Modules/Superadmin/Http/Requests/ReorderMenuRequest.php`
- `Modules/Superadmin/database/seeders/MenuSeeder.php`

**Backend (değiştir):**
- `Modules/Superadmin/routes/web.php` — menü route'ları
- `app/Http/Middleware/HandleInertiaRequests.php` — `menu` paylaşımı

**Frontend (oluştur):**
- `resources/js/menuIcons.js` — ortak ikon seti (key → SVG path)
- `Modules/Superadmin/Resources/assets/js/Pages/Menus.vue` — yönetim sayfası
- `Modules/Superadmin/Resources/assets/js/Components/MenuTree.vue` — recursive draggable ağaç

**Frontend (değiştir):**
- `resources/js/Layouts/AppLayout.vue` — sidebar + header DB menüsünden

**Test (oluştur):**
- `tests/Unit/Superadmin/MenuTreeBuilderTest.php`
- `tests/Feature/Superadmin/MenuManagementTest.php`
- `tests/Feature/Superadmin/MenuSeederTest.php`

---

## Task 1: Migration + Menu modeli

**Files:**
- Create: `Modules/Superadmin/database/migrations/2026_06_11_120000_create_superadmin_menus_table.php`
- Create: `Modules/Superadmin/Models/Menu.php`

- [ ] **Step 1: Migration'ı yaz**

`Modules/Superadmin/database/migrations/2026_06_11_120000_create_superadmin_menus_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('superadmin_menus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()
                ->constrained('superadmin_menus')->cascadeOnDelete();
            $table->string('label', 100);
            $table->string('icon', 64)->nullable();
            $table->string('route_name', 150)->nullable();
            $table->string('url', 255)->nullable();
            $table->string('permission', 150)->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['parent_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('superadmin_menus');
    }
};
```

- [ ] **Step 2: Migration'ı çalıştır ve tabloyu doğrula**

Run: `php artisan migrate`
Expected: `superadmin_menus` tablosu oluşur, hata yok.

- [ ] **Step 3: Menu modelini yaz**

`Modules/Superadmin/Models/Menu.php`:

```php
<?php

namespace Modules\Superadmin\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Route;

class Menu extends Model
{
    protected $table = 'superadmin_menus';

    protected $fillable = [
        'parent_id', 'label', 'icon', 'route_name', 'url',
        'permission', 'sort_order', 'is_active',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
        'parent_id'  => 'integer',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    public function scopeRoots(Builder $q): Builder
    {
        return $q->whereNull('parent_id');
    }

    public function scopeOrdered(Builder $q): Builder
    {
        return $q->orderBy('sort_order');
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    /**
     * permission null → herkese görünür.
     * Aksi halde kullanıcının izni olmalı (superadmin Gate::before ile geçer).
     */
    public function isVisibleTo(?User $user): bool
    {
        if (! $this->permission) {
            return true;
        }
        return (bool) $user?->can($this->permission);
    }

    /**
     * Frontend 'to' değeri: route varsa göreli path, yoksa url, ikisi de yoksa null.
     * route(..., [], false) → "/products" gibi göreli yol (active tespiti path ile çalışır).
     */
    public function resolveTo(): ?string
    {
        if ($this->route_name && Route::has($this->route_name)) {
            return route($this->route_name, [], false);
        }
        return $this->url ?: null;
    }
}
```

- [ ] **Step 4: Tinker ile modeli doğrula**

Run: `php artisan tinker --execute="use Modules\Superadmin\Models\Menu; \$m = Menu::create(['label'=>'Test','sort_order'=>0]); echo \$m->isVisibleTo(null) ? 'visible' : 'hidden'; \$m->delete();"`
Expected: `visible` yazar, hata yok.

- [ ] **Step 5: Commit**

```bash
git add Modules/Superadmin/database/migrations/2026_06_11_120000_create_superadmin_menus_table.php Modules/Superadmin/Models/Menu.php
git commit -m "feat(superadmin): superadmin_menus tablosu ve Menu modeli"
```

---

## Task 2: MenuTreeBuilder servisi (TDD)

**Files:**
- Create: `Modules/Superadmin/Services/MenuTreeBuilder.php`
- Test: `tests/Unit/Superadmin/MenuTreeBuilderTest.php`

- [ ] **Step 1: Başarısız testi yaz**

`tests/Unit/Superadmin/MenuTreeBuilderTest.php`:

```php
<?php

namespace Tests\Unit\Superadmin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Superadmin\Models\Menu;
use Modules\Superadmin\Services\MenuTreeBuilder;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class MenuTreeBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_builds_nested_tree_ordered(): void
    {
        $root = Menu::create(['label' => 'Katalog', 'icon' => 'package', 'sort_order' => 0]);
        Menu::create(['parent_id' => $root->id, 'label' => 'Ürünler', 'url' => '/products', 'sort_order' => 1]);
        Menu::create(['parent_id' => $root->id, 'label' => 'Kategoriler', 'url' => '/categories', 'sort_order' => 0]);

        $tree = MenuTreeBuilder::forUser(null);

        $this->assertCount(1, $tree);
        $this->assertSame('Katalog', $tree[0]['label']);
        $this->assertSame('package', $tree[0]['icon']);
        // sort_order'a göre: Kategoriler (0) önce, Ürünler (1) sonra
        $this->assertSame('Kategoriler', $tree[0]['children'][0]['label']);
        $this->assertSame('Ürünler', $tree[0]['children'][1]['label']);
        $this->assertSame('/products', $tree[0]['children'][1]['to']);
    }

    public function test_hides_menu_without_permission_and_drops_subtree(): void
    {
        Permission::firstOrCreate(['name' => 'atelier.manage', 'guard_name' => 'web']);
        $root = Menu::create(['label' => 'Atölye', 'permission' => 'atelier.manage', 'sort_order' => 0]);
        Menu::create(['parent_id' => $root->id, 'label' => 'Modeller', 'url' => '/atelier', 'sort_order' => 0]);

        $user = User::factory()->create(); // izni yok

        $tree = MenuTreeBuilder::forUser($user);

        $this->assertCount(0, $tree); // parent gizli → alt ağaç da düşer
    }

    public function test_shows_permitted_menu_to_user_with_permission(): void
    {
        Permission::firstOrCreate(['name' => 'atelier.manage', 'guard_name' => 'web']);
        Menu::create(['label' => 'Atölye', 'permission' => 'atelier.manage', 'sort_order' => 0]);

        $user = User::factory()->create();
        $user->givePermissionTo('atelier.manage');

        $tree = MenuTreeBuilder::forUser($user);

        $this->assertCount(1, $tree);
        $this->assertSame('Atölye', $tree[0]['label']);
    }

    public function test_excludes_inactive_menus(): void
    {
        Menu::create(['label' => 'Gizli', 'is_active' => false, 'sort_order' => 0]);

        $this->assertCount(0, MenuTreeBuilder::forUser(null));
    }
}
```

- [ ] **Step 2: Testi çalıştır, başarısız olduğunu gör**

Run: `php artisan test tests/Unit/Superadmin/MenuTreeBuilderTest.php`
Expected: FAIL — `Class "Modules\Superadmin\Services\MenuTreeBuilder" not found`.

- [ ] **Step 3: Servisi yaz**

`Modules/Superadmin/Services/MenuTreeBuilder.php`:

```php
<?php

namespace Modules\Superadmin\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Modules\Superadmin\Models\Menu;

class MenuTreeBuilder
{
    /**
     * Aktif + izinli menülerden, kullanıcıya görünür ağacı kurar.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function forUser(?User $user): array
    {
        $all = Menu::query()->active()->ordered()->get();

        return self::build($all, null, $user);
    }

    /**
     * @param  Collection<int, Menu>  $all
     * @return array<int, array<string, mixed>>
     */
    private static function build(Collection $all, ?int $parentId, ?User $user): array
    {
        return $all
            ->where('parent_id', $parentId)
            ->filter(fn (Menu $m) => $m->isVisibleTo($user))
            ->map(fn (Menu $m) => [
                'id'         => $m->id,
                'label'      => $m->label,
                'icon'       => $m->icon,
                'to'         => $m->resolveTo(),
                'permission' => $m->permission,
                'children'   => self::build($all, $m->id, $user),
            ])
            ->values()
            ->all();
    }
}
```

- [ ] **Step 4: Testi çalıştır, geçtiğini gör**

Run: `php artisan test tests/Unit/Superadmin/MenuTreeBuilderTest.php`
Expected: PASS (4 test).

- [ ] **Step 5: Commit**

```bash
git add Modules/Superadmin/Services/MenuTreeBuilder.php tests/Unit/Superadmin/MenuTreeBuilderTest.php
git commit -m "feat(superadmin): MenuTreeBuilder izin-filtreli menu agaci"
```

---

## Task 3: Inertia paylaşımı (`menu` prop'u)

**Files:**
- Modify: `app/Http/Middleware/HandleInertiaRequests.php`
- Test: `tests/Feature/Superadmin/MenuManagementTest.php` (ilk test burada başlar)

- [ ] **Step 1: Başarısız testi yaz**

`tests/Feature/Superadmin/MenuManagementTest.php`:

```php
<?php

namespace Tests\Feature\Superadmin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Superadmin\Models\Menu;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MenuManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $superadmin;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);
        $this->superadmin = User::factory()->create();
        $this->superadmin->assignRole($role);
    }

    public function test_menu_tree_is_shared_with_inertia_responses(): void
    {
        Menu::create(['label' => 'Pano', 'icon' => 'dashboard', 'url' => '/tenant/dashboard', 'sort_order' => 0]);

        $this->actingAs($this->superadmin)
            ->get('/superadmin/settings')
            ->assertInertia(fn ($page) => $page
                ->where('menu.0.label', 'Pano')
                ->where('menu.0.icon', 'dashboard')
                ->where('menu.0.to', '/tenant/dashboard')
            );
    }
}
```

- [ ] **Step 2: Testi çalıştır, başarısız olduğunu gör**

Run: `php artisan test tests/Feature/Superadmin/MenuManagementTest.php`
Expected: FAIL — `menu.0.label` prop'u yok.

- [ ] **Step 3: HandleInertiaRequests'e paylaşım ekle**

`app/Http/Middleware/HandleInertiaRequests.php` içinde, üst kısımdaki use bloğuna ekle:

```php
use Modules\Superadmin\Services\MenuTreeBuilder;
```

`share()` dönüş dizisine `cart` satırının yanına ekle:

```php
            'menu' => fn () => MenuTreeBuilder::forUser($user),
```

Sonuç (`share` metodu):

```php
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user'        => $user,
                'permissions' => fn () => $user?->getAllPermissions()?->pluck('name')->all() ?? [],
            ],
            'app' => [
                'name' => config('app.name'),
            ],
            'cart' => fn () => $this->cartPayload($user?->id),
            'menu' => fn () => MenuTreeBuilder::forUser($user),
        ];
    }
```

- [ ] **Step 4: Testi çalıştır, geçtiğini gör**

Run: `php artisan test tests/Feature/Superadmin/MenuManagementTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Middleware/HandleInertiaRequests.php tests/Feature/Superadmin/MenuManagementTest.php
git commit -m "feat(superadmin): menu agacini her Inertia response'unda paylas"
```

---

## Task 4: FormRequest'ler

**Files:**
- Create: `Modules/Superadmin/Http/Requests/StoreMenuRequest.php`
- Create: `Modules/Superadmin/Http/Requests/UpdateMenuRequest.php`
- Create: `Modules/Superadmin/Http/Requests/ReorderMenuRequest.php`

- [ ] **Step 1: StoreMenuRequest'i yaz**

`Modules/Superadmin/Http/Requests/StoreMenuRequest.php`:

```php
<?php

namespace Modules\Superadmin\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMenuRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasRole('superadmin');
    }

    public function rules(): array
    {
        return [
            'parent_id'  => ['nullable', 'integer', 'exists:superadmin_menus,id'],
            'label'      => ['required', 'string', 'max:100'],
            'icon'       => ['nullable', 'string', 'max:64'],
            'route_name' => ['nullable', 'string', 'max:150'],
            'url'        => ['nullable', 'string', 'max:255'],
            'permission' => ['nullable', 'string', 'exists:permissions,name'],
            'sort_order' => ['nullable', 'integer'],
            'is_active'  => ['boolean'],
        ];
    }
}
```

- [ ] **Step 2: UpdateMenuRequest'i yaz**

`Modules/Superadmin/Http/Requests/UpdateMenuRequest.php`:

```php
<?php

namespace Modules\Superadmin\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMenuRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasRole('superadmin');
    }

    public function rules(): array
    {
        return [
            'parent_id'  => ['nullable', 'integer', 'exists:superadmin_menus,id'],
            'label'      => ['required', 'string', 'max:100'],
            'icon'       => ['nullable', 'string', 'max:64'],
            'route_name' => ['nullable', 'string', 'max:150'],
            'url'        => ['nullable', 'string', 'max:255'],
            'permission' => ['nullable', 'string', 'exists:permissions,name'],
            'sort_order' => ['nullable', 'integer'],
            'is_active'  => ['boolean'],
        ];
    }
}
```

- [ ] **Step 3: ReorderMenuRequest'i yaz**

`Modules/Superadmin/Http/Requests/ReorderMenuRequest.php`:

```php
<?php

namespace Modules\Superadmin\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReorderMenuRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasRole('superadmin');
    }

    public function rules(): array
    {
        return [
            'items'             => ['required', 'array'],
            'items.*.id'        => ['required', 'integer', 'exists:superadmin_menus,id'],
            'items.*.parent_id' => ['nullable', 'integer', 'exists:superadmin_menus,id'],
            'items.*.sort_order'=> ['required', 'integer'],
        ];
    }
}
```

- [ ] **Step 4: Sözdizimini doğrula**

Run: `php -l Modules/Superadmin/Http/Requests/StoreMenuRequest.php && php -l Modules/Superadmin/Http/Requests/UpdateMenuRequest.php && php -l Modules/Superadmin/Http/Requests/ReorderMenuRequest.php`
Expected: 3 satır "No syntax errors detected".

- [ ] **Step 5: Commit**

```bash
git add Modules/Superadmin/Http/Requests/StoreMenuRequest.php Modules/Superadmin/Http/Requests/UpdateMenuRequest.php Modules/Superadmin/Http/Requests/ReorderMenuRequest.php
git commit -m "feat(superadmin): menu FormRequest'leri (store/update/reorder)"
```

---

## Task 5: MenuController + route'lar (CRUD + reorder, TDD)

**Files:**
- Create: `Modules/Superadmin/Http/Controllers/MenuController.php`
- Modify: `Modules/Superadmin/routes/web.php`
- Test: `tests/Feature/Superadmin/MenuManagementTest.php` (testler eklenir)

- [ ] **Step 1: CRUD + reorder + döngü testlerini ekle**

`tests/Feature/Superadmin/MenuManagementTest.php` sınıfına aşağıdaki metotları ekle:

```php
    public function test_superadmin_can_create_menu(): void
    {
        $this->actingAs($this->superadmin)
            ->post('/superadmin/menus', [
                'label'      => 'Katalog',
                'icon'       => 'package',
                'url'        => '/products',
                'sort_order' => 0,
                'is_active'  => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('superadmin_menus', ['label' => 'Katalog', 'icon' => 'package']);
    }

    public function test_superadmin_can_update_menu(): void
    {
        $menu = Menu::create(['label' => 'Eski', 'sort_order' => 0]);

        $this->actingAs($this->superadmin)
            ->put("/superadmin/menus/{$menu->id}", [
                'label'     => 'Yeni',
                'is_active' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('superadmin_menus', ['id' => $menu->id, 'label' => 'Yeni']);
    }

    public function test_deleting_menu_cascades_to_children(): void
    {
        $root  = Menu::create(['label' => 'Kök', 'sort_order' => 0]);
        $child = Menu::create(['parent_id' => $root->id, 'label' => 'Çocuk', 'sort_order' => 0]);

        $this->actingAs($this->superadmin)
            ->delete("/superadmin/menus/{$root->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('superadmin_menus', ['id' => $root->id]);
        $this->assertDatabaseMissing('superadmin_menus', ['id' => $child->id]);
    }

    public function test_reorder_updates_sort_and_parent(): void
    {
        $a = Menu::create(['label' => 'A', 'sort_order' => 0]);
        $b = Menu::create(['label' => 'B', 'sort_order' => 1]);

        $this->actingAs($this->superadmin)
            ->post('/superadmin/menus/reorder', [
                'items' => [
                    ['id' => $a->id, 'parent_id' => null, 'sort_order' => 1],
                    ['id' => $b->id, 'parent_id' => $a->id, 'sort_order' => 0],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('superadmin_menus', ['id' => $a->id, 'sort_order' => 1, 'parent_id' => null]);
        $this->assertDatabaseHas('superadmin_menus', ['id' => $b->id, 'sort_order' => 0, 'parent_id' => $a->id]);
    }

    public function test_reorder_rejects_cycle(): void
    {
        $parent = Menu::create(['label' => 'Parent', 'sort_order' => 0]);
        $child  = Menu::create(['parent_id' => $parent->id, 'label' => 'Child', 'sort_order' => 0]);

        // parent'ı kendi çocuğunun altına taşımak döngü yaratır → reddedilmeli
        $this->actingAs($this->superadmin)
            ->post('/superadmin/menus/reorder', [
                'items' => [
                    ['id' => $parent->id, 'parent_id' => $child->id, 'sort_order' => 0],
                ],
            ])
            ->assertSessionHasErrors('items');

        // Değişmemiş olmalı
        $this->assertDatabaseHas('superadmin_menus', ['id' => $parent->id, 'parent_id' => null]);
    }

    public function test_non_superadmin_cannot_manage_menus(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/superadmin/menus', ['label' => 'X', 'is_active' => true])
            ->assertForbidden();
    }
```

- [ ] **Step 2: Testleri çalıştır, başarısız olduğunu gör**

Run: `php artisan test tests/Feature/Superadmin/MenuManagementTest.php`
Expected: FAIL — `/superadmin/menus` route'ları tanımlı değil (404/MethodNotAllowed).

- [ ] **Step 3: MenuController'ı yaz**

`Modules/Superadmin/Http/Controllers/MenuController.php`:

```php
<?php

namespace Modules\Superadmin\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Superadmin\Http\Requests\ReorderMenuRequest;
use Modules\Superadmin\Http\Requests\StoreMenuRequest;
use Modules\Superadmin\Http\Requests\UpdateMenuRequest;
use Modules\Superadmin\Models\Menu;
use Spatie\Permission\Models\Permission;

class MenuController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Superadmin::Menus', [
            'menus'       => Menu::query()->ordered()->get([
                'id', 'parent_id', 'label', 'icon', 'route_name',
                'url', 'permission', 'sort_order', 'is_active',
            ]),
            'permissions' => Permission::query()->orderBy('name')->pluck('name'),
        ]);
    }

    public function store(StoreMenuRequest $request): RedirectResponse
    {
        Menu::create($request->validated());

        return back()->with('flash', [
            'toast' => ['type' => 'success', 'title' => 'Menü eklendi'],
        ]);
    }

    public function update(UpdateMenuRequest $request, Menu $menu): RedirectResponse
    {
        $menu->update($request->validated());

        return back()->with('flash', [
            'toast' => ['type' => 'success', 'title' => 'Menü güncellendi'],
        ]);
    }

    public function destroy(Menu $menu): RedirectResponse
    {
        // FK cascadeOnDelete alt ağacı da siler.
        $menu->delete();

        return back()->with('flash', [
            'toast' => ['type' => 'info', 'title' => 'Menü silindi'],
        ]);
    }

    public function reorder(ReorderMenuRequest $request): RedirectResponse
    {
        $items = $request->validated()['items'];

        // Döngü kontrolü: önerilen parent zincirinde öğenin kendisi geçiyorsa reddet.
        $proposed = collect($items)->mapWithKeys(
            fn ($i) => [$i['id'] => $i['parent_id'] ?? null]
        );
        // Mevcut parent'ları da hesaba kat (payload kısmi olabilir).
        $existing = Menu::query()->pluck('parent_id', 'id');
        $resolve  = fn ($id) => $proposed->has($id) ? $proposed[$id] : ($existing[$id] ?? null);

        foreach ($items as $item) {
            $cursor = $resolve($item['id']);
            $guard  = 0;
            while ($cursor !== null) {
                if ($cursor === $item['id'] || ++$guard > 1000) {
                    return back()->withErrors([
                        'items' => 'Bir menü kendi alt menüsünün altına taşınamaz (döngü).',
                    ]);
                }
                $cursor = $resolve($cursor);
            }
        }

        DB::transaction(function () use ($items) {
            foreach ($items as $item) {
                Menu::query()->whereKey($item['id'])->update([
                    'parent_id'  => $item['parent_id'] ?? null,
                    'sort_order' => $item['sort_order'],
                ]);
            }
        });

        return back()->with('flash', [
            'toast' => ['type' => 'success', 'title' => 'Menü sıralaması kaydedildi'],
        ]);
    }
}
```

- [ ] **Step 4: Route'ları ekle**

`Modules/Superadmin/routes/web.php` içinde use bloğuna ekle:

```php
use Modules\Superadmin\Http\Controllers\MenuController;
```

`role:superadmin` grubunun içine (kapanış `});` öncesi) ekle:

```php
    // Menüler
    Route::get('superadmin/menus', [MenuController::class, 'index'])->name('superadmin.menus');
    Route::post('superadmin/menus/reorder', [MenuController::class, 'reorder'])->name('superadmin.menus.reorder');
    Route::post('superadmin/menus', [MenuController::class, 'store'])->name('superadmin.menus.store');
    Route::put('superadmin/menus/{menu}', [MenuController::class, 'update'])->name('superadmin.menus.update');
    Route::delete('superadmin/menus/{menu}', [MenuController::class, 'destroy'])->name('superadmin.menus.destroy');
```

> Not: `reorder` route'u `{menu}` parametreli route'lardan **önce** tanımlanır ki `menus/reorder` yanlışlıkla `{menu}=reorder` olarak eşleşmesin.

- [ ] **Step 5: Testleri çalıştır, geçtiğini gör**

Run: `php artisan test tests/Feature/Superadmin/MenuManagementTest.php`
Expected: PASS (tüm testler: paylaşım + CRUD + reorder + döngü + yetki).

- [ ] **Step 6: Commit**

```bash
git add Modules/Superadmin/Http/Controllers/MenuController.php Modules/Superadmin/routes/web.php tests/Feature/Superadmin/MenuManagementTest.php
git commit -m "feat(superadmin): MenuController CRUD + reorder (dongu engeli) ve route'lar"
```

---

## Task 6: MenuSeeder — mevcut menüleri taşı (TDD)

**Files:**
- Create: `Modules/Superadmin/database/seeders/MenuSeeder.php`
- Test: `tests/Feature/Superadmin/MenuSeederTest.php`

- [ ] **Step 1: Başarısız testi yaz**

`tests/Feature/Superadmin/MenuSeederTest.php`:

```php
<?php

namespace Tests\Feature\Superadmin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Superadmin\database\seeders\MenuSeeder;
use Modules\Superadmin\Models\Menu;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class MenuSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_root_menus_with_children(): void
    {
        Permission::firstOrCreate(['name' => 'atelier.manage', 'guard_name' => 'web']);

        (new MenuSeeder())->run();

        // 10 kök menü
        $this->assertSame(10, Menu::roots()->count());

        // Pano kökü ikonlu ve child'lı
        $pano = Menu::where('label', 'Pano')->whereNull('parent_id')->first();
        $this->assertNotNull($pano);
        $this->assertSame('dashboard', $pano->icon);
        $this->assertGreaterThan(0, $pano->children()->count());

        // Atölye izne bağlı
        $atelier = Menu::where('label', 'Atölye')->whereNull('parent_id')->first();
        $this->assertSame('atelier.manage', $atelier->permission);
    }

    public function test_seeder_is_idempotent(): void
    {
        Permission::firstOrCreate(['name' => 'atelier.manage', 'guard_name' => 'web']);

        (new MenuSeeder())->run();
        (new MenuSeeder())->run();

        $this->assertSame(10, Menu::roots()->count());
    }
}
```

- [ ] **Step 2: Testi çalıştır, başarısız olduğunu gör**

Run: `php artisan test tests/Feature/Superadmin/MenuSeederTest.php`
Expected: FAIL — `MenuSeeder` sınıfı yok.

- [ ] **Step 3: Seeder'ı yaz**

`Modules/Superadmin/database/seeders/MenuSeeder.php`:

```php
<?php

namespace Modules\Superadmin\database\seeders;

use Illuminate\Database\Seeder;
use Modules\Superadmin\Models\Menu;

class MenuSeeder extends Seeder
{
    /**
     * Mevcut hardcoded AppLayout menülerini (baseNavItems) DB'ye taşır.
     * Kökler ikonlu (sidebar), child'lar path'li (header). Idempotent:
     * aynı label+parent için tekrar oluşturmaz.
     */
    public function run(): void
    {
        foreach ($this->definition() as $order => $root) {
            $rootMenu = Menu::firstOrCreate(
                ['label' => $root['label'], 'parent_id' => null],
                [
                    'icon'       => $root['icon'],
                    'url'        => $root['url'] ?? null,
                    'permission' => $root['permission'] ?? null,
                    'sort_order' => $order,
                    'is_active'  => true,
                ],
            );

            foreach ($root['children'] as $childOrder => $child) {
                Menu::firstOrCreate(
                    ['label' => $child['label'], 'parent_id' => $rootMenu->id],
                    [
                        'url'        => $child['url'] ?? null,
                        'sort_order' => $childOrder,
                        'is_active'  => true,
                    ],
                );
            }
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function definition(): array
    {
        return [
            ['label' => 'Pano', 'icon' => 'dashboard', 'url' => '/tenant/dashboard', 'children' => [
                ['label' => 'Satış & Pazaryeri', 'url' => '/tenant/dashboard'],
                ['label' => 'Kar Marjı Hesaplayıcı', 'url' => '/tenant/margin-calculator'],
                ['label' => 'Stok Analizi'],
            ]],
            ['label' => 'İlişkiler', 'icon' => 'relations', 'url' => '/tenants', 'children' => [
                ['label' => 'Tenant\'lar', 'url' => '/tenants'],
                ['label' => 'Tenant Tipleri', 'url' => '/tenants/types'],
                ['label' => 'Müşteriler'],
                ['label' => 'Tedarikçiler', 'url' => '/suppliers'],
                ['label' => 'Kullanıcılar', 'url' => '/users'],
                ['label' => 'Partnerler'],
            ]],
            ['label' => 'Katalog', 'icon' => 'package', 'url' => '/products', 'children' => [
                ['label' => 'Tüm Ürünler', 'url' => '/products'],
                ['label' => 'Kategoriler', 'url' => '/products/categories'],
                ['label' => 'Markalar', 'url' => '/products/brands'],
                ['label' => 'Koleksiyonlar'],
            ]],
            ['label' => 'Siparişler', 'icon' => 'orders', 'url' => '/orders', 'children' => [
                ['label' => 'Sipariş Listesi', 'url' => '/orders'],
                ['label' => 'Yeni Sipariş'],
                ['label' => 'Teklifler'],
                ['label' => 'İadeler'],
            ]],
            ['label' => 'Stok', 'icon' => 'layers', 'url' => '/products/stocks', 'children' => [
                ['label' => 'Stok Durumu', 'url' => '/products/stocks'],
                ['label' => 'Stok Hareketleri', 'url' => '/products/stocks/history'],
                ['label' => 'Depolar', 'url' => '/products/warehouses'],
                ['label' => 'Sayım'],
            ]],
            ['label' => 'Takvim', 'icon' => 'calendar', 'children' => [
                ['label' => 'Üretim Takvimi'],
                ['label' => 'Toplantılar'],
                ['label' => 'Tatil Günleri'],
            ]],
            ['label' => 'Atölye', 'icon' => 'scissors', 'url' => '/atelier', 'permission' => 'atelier.manage', 'children' => [
                ['label' => 'Tüm Modeller', 'url' => '/atelier'],
                ['label' => 'Taslaklar', 'url' => '/atelier?status=draft'],
                ['label' => 'İnceleme Bekleyenler', 'url' => '/atelier?status=in_review'],
                ['label' => 'Onaylanmış', 'url' => '/atelier?status=approved'],
            ]],
            ['label' => 'İş Emirleri', 'icon' => 'workflow', 'url' => '/workflow', 'children' => [
                ['label' => 'Yeni İş Emri', 'url' => '/workflow'],
                ['label' => 'Aktif Emirler', 'url' => '/workflow'],
                ['label' => 'Tamamlanan'],
                ['label' => 'Geciken'],
            ]],
            ['label' => 'Raporlar', 'icon' => 'reports', 'children' => [
                ['label' => 'Üretim Raporu'],
                ['label' => 'Stok Raporu'],
                ['label' => 'Satış Raporu'],
                ['label' => 'Maliyet Analizi'],
            ]],
            ['label' => 'Sevkiyat', 'icon' => 'shipping', 'children' => [
                ['label' => 'Sevkiyat Listesi'],
                ['label' => 'Yeni Sevkiyat'],
                ['label' => 'Kargo Takibi'],
                ['label' => 'Adresler'],
            ]],
        ];
    }
}
```

- [ ] **Step 4: Testi çalıştır, geçtiğini gör**

Run: `php artisan test tests/Feature/Superadmin/MenuSeederTest.php`
Expected: PASS (2 test).

- [ ] **Step 5: Seeder'ı gerçek DB'ye uygula**

Run: `php artisan db:seed --class="Modules\\Superadmin\\database\\seeders\\MenuSeeder"`
Expected: Hata yok; `superadmin_menus` 10 kök + child'larla dolar.

- [ ] **Step 6: Commit**

```bash
git add Modules/Superadmin/database/seeders/MenuSeeder.php tests/Feature/Superadmin/MenuSeederTest.php
git commit -m "feat(superadmin): MenuSeeder mevcut menuleri DB'ye tasir"
```

---

## Task 7: Ortak ikon seti `menuIcons.js`

**Files:**
- Create: `resources/js/menuIcons.js`

- [ ] **Step 1: İkon modülünü yaz**

`resources/js/menuIcons.js`:

```js
// Ortak menü ikon seti. Anahtar DB'de saklanır (superadmin_menus.icon),
// SVG path'i burada yaşar. Hem yönetim sayfası picker'ı hem AppLayout sidebar
// bu tek kaynağı kullanır. Yeni ikon = buraya bir anahtar ekle.

export const menuIcons = {
	dashboard: '<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>',
	relations: '<path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/>',
	package: '<line x1="16.5" y1="9.4" x2="7.5" y2="4.21"/><path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/>',
	orders: '<circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 002 1.61h9.72a2 2 0 002-1.61L23 6H6"/>',
	layers: '<polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/>',
	calendar: '<rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>',
	scissors: '<circle cx="6" cy="6" r="3"/><circle cx="6" cy="18" r="3"/><line x1="20" y1="4" x2="8.12" y2="15.88"/><line x1="14.47" y1="14.48" x2="20" y2="20"/><line x1="8.12" y1="8.12" x2="12" y2="12"/>',
	workflow: '<line x1="6" y1="3" x2="6" y2="15"/><circle cx="18" cy="6" r="3"/><circle cx="6" cy="18" r="3"/><path d="M18 9a9 9 0 01-9 9"/>',
	reports: '<line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>',
	shipping: '<line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>',
	documents: '<path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/>',
	star: '<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>',
	settings: '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 01-2.83 2.83l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.6 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.6a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/>',
}

// Bir ikon anahtarını tam <svg> string'ine sarar (sidebar/picker render için).
export function renderMenuIcon(key, size = 14) {
	const path = menuIcons[key]
	if (!path) return ''
	return `<svg width="${size}" height="${size}" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">${path}</svg>`
}

export const menuIconKeys = Object.keys(menuIcons)
```

- [ ] **Step 2: Doğrula (build kırılmıyor)**

Run: `node -e "import('./resources/js/menuIcons.js').then(m => console.log(m.menuIconKeys.length, m.renderMenuIcon('dashboard').startsWith('<svg')))"`
Expected: `13 true` (13 anahtar, render `<svg` ile başlar).

> Node ESM import çalışmazsa bu adımı atla; doğrulama Task 9'daki `npm run build` ile yapılır.

- [ ] **Step 3: Commit**

```bash
git add resources/js/menuIcons.js
git commit -m "feat(ui): ortak menu ikon seti (menuIcons.js)"
```

---

## Task 8: Yönetim sayfası `Menus.vue` + `MenuTree.vue`

JS test runner yok → doğrulama manuel (Task 9'da toplu). Bu görev tam bileşen kodunu üretir.

**Files:**
- Create: `Modules/Superadmin/Resources/assets/js/Components/MenuTree.vue`
- Create: `Modules/Superadmin/Resources/assets/js/Pages/Menus.vue`

- [ ] **Step 1: Recursive draggable ağaç bileşenini yaz**

`Modules/Superadmin/Resources/assets/js/Components/MenuTree.vue`:

```vue
<template>
	<draggable
		:list="nodes"
		:group="{ name: 'menus' }"
		item-key="id"
		handle=".mt-handle"
		class="mt-list"
		@change="$emit('changed')"
	>
		<template #item="{ element }">
			<div class="mt-node">
				<div class="mt-row" :class="{ inactive: !element.is_active }">
					<span class="mt-handle" title="Sürükle">⋮⋮</span>
					<span class="mt-label">{{ element.label }}</span>
					<span v-if="element.permission" class="mt-badge">{{ element.permission }}</span>
					<span class="mt-actions">
						<button type="button" @click="$emit('edit', element)" title="Düzenle">✎</button>
						<button type="button" @click="$emit('add-child', element)" title="Alt menü ekle">＋</button>
						<button type="button" class="danger" @click="$emit('remove', element)" title="Sil">🗑</button>
					</span>
				</div>
				<MenuTree
					:nodes="element.children"
					class="mt-children"
					@changed="$emit('changed')"
					@edit="$emit('edit', $event)"
					@add-child="$emit('add-child', $event)"
					@remove="$emit('remove', $event)"
				/>
			</div>
		</template>
	</draggable>
</template>

<script setup>
import draggable from 'vuedraggable'

defineProps({
	nodes: { type: Array, required: true },
})

defineEmits(['changed', 'edit', 'add-child', 'remove'])
</script>

<style scoped>
.mt-list { min-height: 12px; }
.mt-children { margin-left: 22px; border-left: 1px dashed #e0e0ea; padding-left: 8px; }
.mt-node { margin: 3px 0; }
.mt-row {
	display: flex; align-items: center; gap: 8px;
	padding: 7px 10px; background: #fff;
	border: 1px solid #ebebf0; border-radius: 8px;
	transition: border-color .12s;
}
.mt-row:hover { border-color: #c8c8d8; }
.mt-row.inactive { opacity: .5; }
.mt-handle { cursor: grab; color: #bbb; user-select: none; font-size: 12px; }
.mt-label { font-size: 13px; font-weight: 500; color: #1a1a2e; }
.mt-badge {
	font-size: 10px; color: #8a6d00; background: #fff8e1;
	border-radius: 4px; padding: 1px 6px;
}
.mt-actions { margin-left: auto; display: flex; gap: 4px; }
.mt-actions button {
	width: 26px; height: 26px; border: 1px solid #ebebf0;
	background: #fff; border-radius: 6px; cursor: pointer; color: #666;
}
.mt-actions button:hover { background: #f5f5fb; color: #1a1a2e; }
.mt-actions button.danger:hover { background: #fef2f2; color: #dc2626; border-color: #fecaca; }
</style>
```

> `vuedraggable`'ın `:group="{ name: 'menus' }"` ayarı tüm seviyeler arası sürüklemeyi (nesting) sağlar; recursive bileşen sınırsız derinliği destekler.

- [ ] **Step 2: Yönetim sayfasını yaz**

`Modules/Superadmin/Resources/assets/js/Pages/Menus.vue`:

```vue
<template>
	<div class="menus-page">
		<header class="mp-header">
			<div>
				<h1>Menü Yönetimi</h1>
				<p>Sürükle-bırak ile sırala ve iç içe taşı. Kökler sidebar'da, alt menüler header'da görünür.</p>
			</div>
			<div class="mp-header-actions">
				<button class="btn-ghost" :disabled="!dirty" @click="resetTree">Geri Al</button>
				<button class="btn-primary" :disabled="!dirty || saving" @click="saveOrder">
					{{ saving ? 'Kaydediliyor…' : 'Sıralamayı Kaydet' }}
				</button>
				<button class="btn-primary" @click="openCreate(null)">+ Yeni Kök Menü</button>
			</div>
		</header>

		<MenuTree
			:nodes="tree"
			@changed="dirty = true"
			@edit="openEdit"
			@add-child="openCreate"
			@remove="removeMenu"
		/>

		<AppModal v-model="modalOpen" :title="editing ? 'Menüyü Düzenle' : 'Yeni Menü'">
			<form class="mp-form" @submit.prevent="submitForm">
				<label>Etiket *
					<input v-model="form.label" type="text" maxlength="100" required />
				</label>

				<label>İkon
					<div class="mp-icon-picker">
						<button
							v-for="key in iconKeys"
							:key="key"
							type="button"
							class="mp-icon"
							:class="{ active: form.icon === key }"
							:title="key"
							@click="form.icon = (form.icon === key ? null : key)"
							v-html="renderMenuIcon(key, 16)"
						></button>
					</div>
				</label>

				<label>Route ismi
					<input v-model="form.route_name" type="text" maxlength="150" placeholder="products.index" />
				</label>

				<label>URL (path)
					<input v-model="form.url" type="text" maxlength="255" placeholder="/products" />
				</label>

				<label>İzin (permission)
					<select v-model="form.permission">
						<option :value="null">— Herkese açık —</option>
						<option v-for="p in permissions" :key="p" :value="p">{{ p }}</option>
					</select>
				</label>

				<label class="mp-check">
					<input v-model="form.is_active" type="checkbox" /> Aktif
				</label>

				<div class="mp-form-actions">
					<button type="button" class="btn-ghost" @click="modalOpen = false">Vazgeç</button>
					<button type="submit" class="btn-primary" :disabled="saving">Kaydet</button>
				</div>
			</form>
		</AppModal>
	</div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import AppModal from '@/Components/AppModal.vue'
import { menuIconKeys, renderMenuIcon } from '@/menuIcons.js'
import MenuTree from '../Components/MenuTree.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
	menus: { type: Array, required: true },
	permissions: { type: Array, required: true },
})

const iconKeys = menuIconKeys

// Düz listeyi ağaca çevir (server düz gönderiyor).
function toTree(flat) {
	const byId = new Map(flat.map((m) => [m.id, { ...m, children: [] }]))
	const roots = []
	for (const node of byId.values()) {
		if (node.parent_id && byId.has(node.parent_id)) {
			byId.get(node.parent_id).children.push(node)
		} else {
			roots.push(node)
		}
	}
	return roots
}

const tree = ref(toTree(props.menus))
const dirty = ref(false)
const saving = ref(false)

function resetTree() {
	tree.value = toTree(props.menus)
	dirty.value = false
}

// Ağacı reorder payload'ına düzleştir: her düğüm için id/parent_id/sort_order.
function flatten(nodes, parentId, acc) {
	nodes.forEach((node, index) => {
		acc.push({ id: node.id, parent_id: parentId, sort_order: index })
		if (node.children?.length) flatten(node.children, node.id, acc)
	})
	return acc
}

function saveOrder() {
	saving.value = true
	router.post(
		route('superadmin.menus.reorder'),
		{ items: flatten(tree.value, null, []) },
		{
			preserveScroll: true,
			onSuccess: () => { dirty.value = false },
			onFinish: () => { saving.value = false },
		}
	)
}

/* ── Form (create/edit) ── */
const modalOpen = ref(false)
const editing = ref(null) // düzenlenen menü ya da null
const form = ref(emptyForm())

function emptyForm() {
	return { label: '', icon: null, route_name: '', url: '', permission: null, is_active: true, parent_id: null }
}

function openCreate(parent) {
	editing.value = null
	form.value = { ...emptyForm(), parent_id: parent?.id ?? null }
	modalOpen.value = true
}

function openEdit(menu) {
	editing.value = menu
	form.value = {
		label: menu.label,
		icon: menu.icon,
		route_name: menu.route_name ?? '',
		url: menu.url ?? '',
		permission: menu.permission ?? null,
		is_active: !!menu.is_active,
		parent_id: menu.parent_id ?? null,
	}
	modalOpen.value = true
}

function submitForm() {
	saving.value = true
	const payload = { ...form.value }
	const opts = {
		preserveScroll: true,
		onSuccess: () => { modalOpen.value = false; router.reload({ only: ['menus'] }) },
		onFinish: () => { saving.value = false },
	}
	if (editing.value) {
		router.put(route('superadmin.menus.update', editing.value.id), payload, opts)
	} else {
		router.post(route('superadmin.menus.store'), payload, opts)
	}
}

function removeMenu(menu) {
	if (!window.confirm(`"${menu.label}" ve tüm alt menüleri silinecek. Emin misiniz?`)) return
	router.delete(route('superadmin.menus.destroy', menu.id), {
		preserveScroll: true,
		onSuccess: () => router.reload({ only: ['menus'] }),
	})
}
</script>

<style scoped>
.menus-page { max-width: 860px; }
.mp-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 18px; gap: 16px; }
.mp-header h1 { font-size: 20px; font-weight: 700; color: #1a1a2e; }
.mp-header p { font-size: 13px; color: #888; margin-top: 4px; }
.mp-header-actions { display: flex; gap: 8px; flex-shrink: 0; }
.btn-primary { background: #1a1a2e; color: #fff; border: none; border-radius: 8px; padding: 8px 14px; font-size: 13px; font-weight: 600; cursor: pointer; }
.btn-primary:disabled { opacity: .5; cursor: default; }
.btn-ghost { background: #fff; color: #555; border: 1px solid #e8e8f0; border-radius: 8px; padding: 8px 14px; font-size: 13px; cursor: pointer; }
.btn-ghost:disabled { opacity: .5; cursor: default; }
.mp-form { display: flex; flex-direction: column; gap: 12px; }
.mp-form label { display: flex; flex-direction: column; gap: 5px; font-size: 12.5px; font-weight: 600; color: #444; }
.mp-form input[type=text], .mp-form select { border: 1px solid #e0e0ea; border-radius: 8px; padding: 8px 10px; font-size: 13px; }
.mp-check { flex-direction: row !important; align-items: center; gap: 8px; }
.mp-icon-picker { display: grid; grid-template-columns: repeat(8, 1fr); gap: 6px; }
.mp-icon { width: 100%; aspect-ratio: 1; display: flex; align-items: center; justify-content: center; border: 1px solid #e8e8f0; border-radius: 8px; background: #fff; color: #666; cursor: pointer; }
.mp-icon.active { border-color: #4a6cf7; color: #4a6cf7; background: #eef0ff; }
.mp-form-actions { display: flex; justify-content: flex-end; gap: 8px; margin-top: 6px; }
</style>
```

> İkon picker, route_name/url ve permission alanları spec'teki form sözleşmesini karşılar. `MenuTree` modül-içi olduğundan **göreli** yolla (`../Components/MenuTree.vue`) import edilir; `@/menuIcons.js`, `@/Layouts/AppLayout.vue`, `@/Components/AppModal.vue` ise ana app build'inde root `resources/js`'e çözülür (mevcut Settings.vue ile aynı desen).

- [ ] **Step 3: Commit**

```bash
git add Modules/Superadmin/Resources/assets/js/Components/MenuTree.vue Modules/Superadmin/Resources/assets/js/Pages/Menus.vue
git commit -m "feat(superadmin): menu yonetim sayfasi (suruk-birak agac + ikon picker)"
```

---

## Task 9: AppLayout'u DB menüsüne bağla

**Files:**
- Modify: `resources/js/Layouts/AppLayout.vue`

- [ ] **Step 1: İkon import'unu ve menü computed'larını ekle**

`resources/js/Layouts/AppLayout.vue` — script bloğunun en üstündeki import'lara ekle:

```js
import { renderMenuIcon } from '@/menuIcons.js'
```

- [ ] **Step 2: Hardcoded `baseNavItems` ve `componentToNavKey`/`activeNavKey` bloğunu kaldır, DB menüsüyle değiştir**

`componentToNavKey` sabiti (`const componentToNavKey = {...}`), `activeNavKey` computed'ı, `baseNavItems` dizisi ve mevcut `navItems` computed'ı **silinir**. Yerine (aynı konuma) şu eklenir:

```js
/* ── DB menüsü (Inertia paylaşımı) ── */
const menuTree = computed(() => page.props.menu ?? [])

// Bir düğüm ya da en yakın torununun gerçek link'i (kök tıklanınca nereye gitsin).
function firstLink(node) {
	if (node.to) return node.to
	for (const child of node.children || []) {
		const link = firstLink(child)
		if (link) return link
	}
	return null
}

// Aktif kök: page.url'i ağaçta en uzun prefix ile eşleştir, kök ataya yürü.
const activeRoot = computed(() => {
	const url = page.url
	let best = { root: null, len: -1 }
	const walk = (node, root) => {
		const r = root || node
		if (node.to && url.startsWith(node.to) && node.to.length > best.len) {
			best = { root: r, len: node.to.length }
		}
		;(node.children || []).forEach((c) => walk(c, r))
	}
	menuTree.value.forEach((n) => walk(n, null))
	return best.root || menuTree.value[0] || null
})

/* ── Üst menü (header) = aktif kökün child'ları ── */
const navItems = computed(() => {
	const root = activeRoot.value
	if (!root) return []
	return (root.children || []).map((child) => ({
		name: child.label,
		to: child.to || undefined,
		active: !!(child.to && page.url.startsWith(child.to)),
		children: (child.children || []).map((g) => ({ label: g.label, to: g.to || undefined })),
	}))
})
```

- [ ] **Step 3: Hardcoded `sidebarTopAll`/`sidebarTop` bloğunu kök menülerle değiştir**

`const SVG = ...` helper'ı, `sidebarTopAll` dizisi ve mevcut `sidebarTop` computed'ı **silinir** (`sidebarBottom` ref'i **korunur**). Yerine:

```js
/* ── Sidebar = kök menüler ── */
const sidebarTop = computed(() =>
	menuTree.value.map((node) => ({
		label: node.label,
		icon: renderMenuIcon(node.icon),
		to: firstLink(node) || undefined,
		active: node.id === activeRoot.value?.id,
	}))
)
```

> `STAFF_ROLES`/`isStaff` ve `userMenu` içindeki rol filtreleri **korunur** (user menüsü menü sistemi değil). İzin filtresi artık menüler için server-side yapılıyor.

- [ ] **Step 4: Superadmin user menüsüne "Menü Yönetimi" linki ekle**

`userMenu` computed'ında, "Süper Admin Paneli" satırının hemen altına ekle:

```js
		{ label: 'Menü Yönetimi', icon: '<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>', to: '/superadmin/menus', visible: role === 'superadmin' },
```

- [ ] **Step 5: Build'i çalıştır, derleme hatası olmadığını doğrula**

Run: `npm run build`
Expected: Hatasız tamamlanır (menuIcons import, Menus.vue, AppLayout çözülür).

- [ ] **Step 6: Manuel doğrulama (tarayıcı)**

Run: `php artisan serve` + `npm run dev` (ayrı terminallerde)
Sırayla doğrula:
1. Superadmin olarak giriş → sol sidebar'da 10 kök menü ikonu görünür (tooltip'te label).
2. Bir kök menüye tıkla (örn. Katalog) → ilgili sayfaya gider, sidebar'da o kök `active`, header'da child'ları (Tüm Ürünler, Kategoriler…) görünür.
3. Header child'ın torunu varsa hover'da dropdown açılır.
4. Atölye kökü: `atelier.manage` izni olmayan rolde (örn. tenant demo rolü) sidebar'da **görünmez**; staff rolünde görünür.
5. User menüsü → "Menü Yönetimi" → `/superadmin/menus` açılır.

- [ ] **Step 7: Commit**

```bash
git add resources/js/Layouts/AppLayout.vue
git commit -m "feat(ui): AppLayout sidebar+header'i DB menusunden render et"
```

---

## Task 10: Yönetim sayfasında sürükle-bırak doğrulama + tam akış testi

**Files:** (yeni dosya yok — manuel uçtan uca doğrulama)

- [ ] **Step 1: Tüm backend testlerini çalıştır**

Run: `php artisan test tests/Unit/Superadmin tests/Feature/Superadmin`
Expected: PASS — MenuTreeBuilder (4) + MenuManagement (7) + MenuSeeder (2).

- [ ] **Step 2: Yönetim sayfasında sürükle-bırak manuel doğrulama**

`/superadmin/menus` aç:
1. Bir child'ı kök seviyesine sürükle → "Sıralamayı Kaydet" aktifleşir → kaydet → sayfa yenile, değişiklik kalıcı.
2. Bir kökü kendi child'ının altına sürükleyip kaydetmeyi dene → backend döngü hatası toast/uyarısı (kayıt değişmez).
3. Yeni kök menü ekle (ikon seç, url gir) → listede görünür + sidebar'da (reload sonrası) belirir.
4. Bir menüyü sil → onay diyaloğu → alt menüleriyle birlikte kaybolur.
5. İkon picker'dan ikon değiştir → kaydet → sidebar ikonu güncellenir.

- [ ] **Step 3: Regresyon — diğer modül testleri kırılmadı**

Run: `php artisan test`
Expected: Mevcut suite yeşil (menü değişikliği `auth`/`cart` paylaşımını bozmadı).

- [ ] **Step 4: Commit (varsa düzeltme); yoksa atla**

```bash
git add -A
git commit -m "test(superadmin): dinamik menu uctan uca dogrulama"
```

---

## Self-Review Notları (plan yazarından)

- **Spec kapsamı:** Tablo (T1), Menu modeli+isVisibleTo (T1), MenuTreeBuilder izin filtresi (T2), Inertia share (T3), FormRequest'ler (T4), Controller CRUD+reorder+döngü engeli+kademeli sil (T5), Seeder (T6), ikon seti (T7), yönetim sayfası+draggable+picker (T8), AppLayout sidebar/header+aktif kök tespiti (T9), uçtan uca (T10). Tüm spec bölümleri karşılandı.
- **Tip tutarlılığı:** `resolveTo()`, `isVisibleTo()`, `MenuTreeBuilder::forUser()`, ağaç düğüm şekli `{id,label,icon,to,permission,children}` tüm görevlerde aynı. Frontend `flatten()` payload'ı `{id,parent_id,sort_order}` controller `ReorderMenuRequest` ile birebir uyumlu.
- **Kademeli silme:** FK `cascadeOnDelete` (T1) + controller `delete()` (T5) + frontend `confirm` (T8) tutarlı.
- **route sırası:** `menus/reorder` `{menu}` route'undan önce (T5 not).
