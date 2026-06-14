# Atelier (Üretim) Modülü Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Karma (kendi atölye + fason) çocuk giyim üretimini uçtan uca takip eden Atelier modülü: hammadde stoğu, reçete (BOM), esnek rota, iş emri, maliyet roll-up ve biten ürünün Product stoğuna otomatik girişi.

**Architecture:** `Modules/Atelier` içinde yeni tablolar/modeller/servisler. Hammadde tamamen Atelier'de yaşar; yalnızca biten ürün mevcut Product `Stock`/`StockMovement`/`Warehouse` yapısına yazılır (Yaklaşım A). Esnek rota = operasyon kataloğu + iş emri başına rota örneği. Tek izin `atelier.manage` (superadmin/iç).

**Tech Stack:** Laravel 12, nwidart/laravel-modules, Inertia + Vue 3, spatie/laravel-permission, PostgreSQL (test: sqlite :memory:), PHPUnit.

**Spec:** `docs/superpowers/specs/2026-06-14-atelier-uretim-modulu-design.md`

**CLAUDE.md disiplini (her migration için):** gerçek `down()`; hareket/log tablolarına `Prunable`; `schema:audit` temiz kalmalı.

**Test komutu:** `php artisan test --filter=Atelier`
**Migrate (tek modül, çakışma riski için):** `php artisan migrate --path=Modules/Atelier/database/migrations`

---

## Faz 0 — Modül temizliği ve hazırlık

### Task 0.1: Kullanılmayan dxf paketini kaldır

**Files:**
- Modify: `composer.json`

- [ ] **Step 1: `composer.json`'dan satırı kaldır**

`"adamasantares/dxf": "^0.1.36",` satırını `require` bloğundan sil.

- [ ] **Step 2: Lock güncelle**

Run: `composer update adamasantares/dxf 2>&1 | tail -5` (paket kaldırıldığı için lock'tan düşer) veya `composer remove adamasantares/dxf`
Expected: paket kaldırılır, hata yok.

- [ ] **Step 3: Commit**

```bash
git add composer.json composer.lock
git commit -m "chore(atelier): kullanilmayan dxf paketini kaldir (ileride AI model->DXF icin geri gelecek)"
```

### Task 0.2: Atelier web route'larını iş emri yapısına hazırla (stub controller'ları temizle)

**Files:**
- Modify: `Modules/Atelier/Http/Controllers/AtelierController.php`
- Modify: `Modules/Atelier/routes/web.php`
- Modify: `Modules/Atelier/routes/api.php`

- [ ] **Step 1: Stub `AtelierController`'ı dashboard'a indir**

`Modules/Atelier/Http/Controllers/AtelierController.php` tamamını değiştir:

```php
<?php

namespace Modules\Atelier\Http\Controllers;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class AtelierController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Atelier::Dashboard', [
            'stats' => [],
        ]);
    }
}
```

(Gerçek dashboard verisi Faz 8'de `DashboardController`'a taşınacak; şimdilik sayfa açılsın.)

- [ ] **Step 2: `routes/web.php`'i sade tut**

`Modules/Atelier/routes/web.php` tamamını değiştir:

```php
<?php

use Illuminate\Support\Facades\Route;
use Modules\Atelier\Http\Controllers\AtelierController;

Route::middleware(['auth', 'verified', 'role:superadmin', 'can:atelier.manage'])
    ->prefix('atelier')
    ->name('atelier.')
    ->group(function () {
        Route::get('/', [AtelierController::class, 'index'])->name('dashboard');
    });
```

- [ ] **Step 3: `routes/api.php`'i boşalt (v1'de API yok)**

`Modules/Atelier/routes/api.php` tamamını değiştir:

```php
<?php

// Atelier v1 yalnızca web (Inertia) üzerinden çalışır; API ucu yoktur.
// İleride fason portalı/entegrasyon gerekirse buraya eklenecek.
```

- [ ] **Step 4: Build kontrol**

Run: `php artisan route:list --path=atelier`
Expected: yalnızca `atelier.dashboard` GET listelenir, hata yok.

- [ ] **Step 5: Commit**

```bash
git add Modules/Atelier/Http/Controllers/AtelierController.php Modules/Atelier/routes/
git commit -m "refactor(atelier): stub resource route'larini dashboard'a indir"
```

---

## Faz 1 — Hammadde (materials + movements)

### Task 1.1: `materials` ve `material_movements` migration'ları

**Files:**
- Create: `Modules/Atelier/database/migrations/2026_06_14_100000_create_materials_table.php`
- Create: `Modules/Atelier/database/migrations/2026_06_14_100100_create_material_movements_table.php`

- [ ] **Step 1: materials migration**

`Modules/Atelier/database/migrations/2026_06_14_100000_create_materials_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('materials', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name', 191);
            $table->string('type', 32)->default('kumas'); // kumas/aksesuar/etiket
            $table->string('unit', 16)->default('adet');   // metre/adet/kg
            $table->decimal('unit_cost', 12, 2)->default(0);
            $table->decimal('current_stock', 14, 3)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('type');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('materials');
    }
};
```

- [ ] **Step 2: material_movements migration**

`Modules/Atelier/database/migrations/2026_06_14_100100_create_material_movements_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('material_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_id')->constrained('materials')->cascadeOnDelete();
            $table->enum('type', ['in', 'out', 'adjust']);
            $table->decimal('quantity', 14, 3);          // signed: out negatif
            $table->decimal('unit_cost', 12, 2)->default(0);
            $table->string('reason', 32);                // purchase/consume/scrap/correction
            $table->decimal('before_stock', 14, 3);
            $table->decimal('after_stock', 14, 3);
            $table->unsignedBigInteger('production_order_id')->nullable(); // tüketim izi (FK Faz 4'te eklenir)
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['material_id', 'created_at']);
            $table->index('reason');
            $table->index('production_order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_movements');
    }
};
```

- [ ] **Step 3: Migrate ve doğrula**

Run: `php artisan migrate --path=Modules/Atelier/database/migrations`
Expected: iki tablo oluşur, hata yok.

- [ ] **Step 4: Commit**

```bash
git add Modules/Atelier/database/migrations/
git commit -m "feat(atelier): materials + material_movements tablolari"
```

### Task 1.2: `Material` ve `MaterialMovement` modelleri

**Files:**
- Create: `Modules/Atelier/Models/Material.php`
- Create: `Modules/Atelier/Models/MaterialMovement.php`

- [ ] **Step 1: Material model**

`Modules/Atelier/Models/Material.php`:

```php
<?php

namespace Modules\Atelier\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Material extends Model
{
    use SoftDeletes;

    protected $table = 'materials';

    protected $fillable = [
        'code', 'name', 'type', 'unit', 'unit_cost', 'current_stock', 'is_active',
    ];

    protected $casts = [
        'unit_cost'     => 'decimal:2',
        'current_stock' => 'decimal:3',
        'is_active'     => 'boolean',
    ];

    public function movements(): HasMany
    {
        return $this->hasMany(MaterialMovement::class);
    }
}
```

- [ ] **Step 2: MaterialMovement model (Prunable)**

`Modules/Atelier/Models/MaterialMovement.php`:

```php
<?php

namespace Modules\Atelier\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MaterialMovement extends Model
{
    use Prunable;
    use SoftDeletes;

    /** Soft-delete kalıntısının silineceği gün eşiği. */
    public const PRUNE_AFTER_DAYS = 730; // ~2 yıl (log niteliğinde)

    public const TYPE_IN     = 'in';
    public const TYPE_OUT    = 'out';
    public const TYPE_ADJUST = 'adjust';

    protected $table = 'material_movements';

    protected $fillable = [
        'material_id', 'type', 'quantity', 'unit_cost', 'reason',
        'before_stock', 'after_stock', 'production_order_id', 'note', 'created_by',
    ];

    protected $casts = [
        'quantity'     => 'decimal:3',
        'unit_cost'    => 'decimal:2',
        'before_stock' => 'decimal:3',
        'after_stock'  => 'decimal:3',
    ];

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function prunable(): Builder
    {
        return static::onlyTrashed()->where('deleted_at', '<=', now()->subDays(self::PRUNE_AFTER_DAYS));
    }
}
```

- [ ] **Step 3: Commit**

```bash
git add Modules/Atelier/Models/
git commit -m "feat(atelier): Material + MaterialMovement modelleri"
```

### Task 1.3: `MaterialStockService` (TDD)

**Files:**
- Create: `Modules/Atelier/Services/MaterialStockService.php`
- Test: `tests/Feature/Atelier/MaterialStockServiceTest.php`

- [ ] **Step 1: Failing test yaz**

`tests/Feature/Atelier/MaterialStockServiceTest.php`:

```php
<?php

namespace Tests\Feature\Atelier;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Atelier\Models\Material;
use Modules\Atelier\Models\MaterialMovement;
use Modules\Atelier\Services\MaterialStockService;
use Tests\TestCase;

class MaterialStockServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): MaterialStockService
    {
        return app(MaterialStockService::class);
    }

    private function material(float $stock = 0): Material
    {
        return Material::create([
            'code' => 'M-' . uniqid(), 'name' => 'Pamuk Kumaş',
            'type' => 'kumas', 'unit' => 'metre', 'unit_cost' => 10,
            'current_stock' => $stock,
        ]);
    }

    public function test_in_movement_increases_stock_and_records_movement(): void
    {
        $material = $this->material(5);

        $movement = $this->service()->record($material, MaterialMovement::TYPE_IN, 10, 'purchase');

        $this->assertSame('15.000', $material->fresh()->current_stock);
        $this->assertSame('5.000', $movement->before_stock);
        $this->assertSame('15.000', $movement->after_stock);
        $this->assertSame('10.000', $movement->quantity);
    }

    public function test_out_movement_decreases_stock_with_negative_quantity(): void
    {
        $material = $this->material(20);

        $movement = $this->service()->record($material, MaterialMovement::TYPE_OUT, 8, 'consume');

        $this->assertSame('12.000', $material->fresh()->current_stock);
        $this->assertSame('-8.000', $movement->quantity);
    }

    public function test_out_movement_below_zero_throws(): void
    {
        $material = $this->material(3);

        $this->expectExceptionMessage('Hammadde stoğu negatife düşemez');

        $this->service()->record($material, MaterialMovement::TYPE_OUT, 5, 'consume');
    }
}
```

- [ ] **Step 2: Test'i çalıştır, başarısız olduğunu gör**

Run: `php artisan test --filter=MaterialStockServiceTest`
Expected: FAIL — `Class "Modules\Atelier\Services\MaterialStockService" not found`.

- [ ] **Step 3: Servisi yaz**

`Modules/Atelier/Services/MaterialStockService.php`:

```php
<?php

namespace Modules\Atelier\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Atelier\Models\Material;
use Modules\Atelier\Models\MaterialMovement;

class MaterialStockService
{
    /**
     * Hammadde hareketi yazar ve current_stock'u günceller.
     *
     * @param  string  $type    in|out|adjust
     * @param  float   $quantity Pozitif girilir; out için içeride negatife çevrilir.
     * @param  string  $reason  purchase|consume|scrap|correction
     */
    public function record(
        Material $material,
        string $type,
        float $quantity,
        string $reason,
        array $opts = []
    ): MaterialMovement {
        return DB::transaction(function () use ($material, $type, $quantity, $reason, $opts) {
            $locked = Material::query()->lockForUpdate()->findOrFail($material->id);
            $before = (float) $locked->current_stock;

            $signed = match ($type) {
                MaterialMovement::TYPE_IN     => abs($quantity),
                MaterialMovement::TYPE_OUT    => -abs($quantity),
                MaterialMovement::TYPE_ADJUST => (float) $quantity,
                default => throw new InvalidArgumentException("Geçersiz hareket tipi: {$type}"),
            };

            $after = $before + $signed;
            if ($after < 0) {
                throw new InvalidArgumentException('Hammadde stoğu negatife düşemez.');
            }

            $locked->update(['current_stock' => $after]);

            $movement = MaterialMovement::create([
                'material_id'         => $locked->id,
                'type'                => $type,
                'quantity'            => $signed,
                'unit_cost'           => $opts['unit_cost'] ?? $locked->unit_cost,
                'reason'              => $reason,
                'before_stock'        => $before,
                'after_stock'         => $after,
                'production_order_id' => $opts['production_order_id'] ?? null,
                'note'                => $opts['note'] ?? null,
                'created_by'          => $opts['created_by'] ?? auth()->id(),
            ]);

            $material->setRawAttributes($locked->getAttributes());

            return $movement;
        });
    }
}
```

- [ ] **Step 4: Test'i çalıştır, geçtiğini gör**

Run: `php artisan test --filter=MaterialStockServiceTest`
Expected: PASS (3 test).

- [ ] **Step 5: Commit**

```bash
git add Modules/Atelier/Services/MaterialStockService.php tests/Feature/Atelier/MaterialStockServiceTest.php
git commit -m "feat(atelier): MaterialStockService + testleri"
```

### Task 1.4: `MaterialController` (TDD) + route

**Files:**
- Create: `Modules/Atelier/Http/Controllers/MaterialController.php`
- Modify: `Modules/Atelier/routes/web.php`
- Test: `tests/Feature/Atelier/MaterialControllerTest.php`

- [ ] **Step 1: Failing test yaz**

`tests/Feature/Atelier/MaterialControllerTest.php`:

```php
<?php

namespace Tests\Feature\Atelier;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Atelier\Models\Material;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MaterialControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $perm = Permission::firstOrCreate(['name' => 'atelier.manage', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);
        $role->givePermissionTo($perm);
        $this->admin = User::factory()->create();
        $this->admin->assignRole($role);
    }

    public function test_store_creates_material(): void
    {
        $this->actingAs($this->admin)
            ->post('/atelier/materials', [
                'code' => 'KUM-001', 'name' => 'Pamuk Kumaş',
                'type' => 'kumas', 'unit' => 'metre', 'unit_cost' => 12.5,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('materials', ['code' => 'KUM-001', 'name' => 'Pamuk Kumaş']);
    }

    public function test_movement_increases_stock(): void
    {
        $material = Material::create([
            'code' => 'KUM-002', 'name' => 'Kot', 'type' => 'kumas',
            'unit' => 'metre', 'unit_cost' => 20, 'current_stock' => 0,
        ]);

        $this->actingAs($this->admin)
            ->post('/atelier/materials/movement', [
                'material_id' => $material->id, 'type' => 'in',
                'quantity' => 50, 'reason' => 'purchase',
            ])
            ->assertRedirect();

        $this->assertSame('50.000', $material->fresh()->current_stock);
    }

    public function test_requires_permission(): void
    {
        $plain = User::factory()->create();

        $this->actingAs($plain)
            ->post('/atelier/materials', [
                'code' => 'X', 'name' => 'X', 'type' => 'kumas', 'unit' => 'adet', 'unit_cost' => 1,
            ])
            ->assertForbidden();
    }
}
```

- [ ] **Step 2: Test'i çalıştır, başarısız olduğunu gör**

Run: `php artisan test --filter=MaterialControllerTest`
Expected: FAIL — route/controller yok (404 veya class not found).

- [ ] **Step 3: Controller'ı yaz**

`Modules/Atelier/Http/Controllers/MaterialController.php`:

```php
<?php

namespace Modules\Atelier\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Atelier\Models\Material;
use Modules\Atelier\Models\MaterialMovement;
use Modules\Atelier\Services\MaterialStockService;

class MaterialController extends Controller
{
    public function __construct(private MaterialStockService $stock) {}

    public function index(): Response
    {
        $materials = Material::query()
            ->orderBy('name')
            ->get()
            ->map(fn (Material $m) => [
                'id'           => $m->id,
                'code'         => $m->code,
                'name'         => $m->name,
                'type'         => $m->type,
                'unit'         => $m->unit,
                'unitCost'     => (float) $m->unit_cost,
                'currentStock' => (float) $m->current_stock,
                'isActive'     => $m->is_active,
            ]);

        return Inertia::render('Atelier::Materials', [
            'materials' => $materials,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Material::create($this->validateMaterial($request));

        return redirect()->route('atelier.materials.index')->with('success', 'Hammadde eklendi.');
    }

    public function update(Request $request, Material $material): RedirectResponse
    {
        $material->update($this->validateMaterial($request, $material->id));

        return redirect()->route('atelier.materials.index')->with('success', 'Hammadde güncellendi.');
    }

    public function destroy(Material $material): RedirectResponse
    {
        $material->delete();

        return redirect()->route('atelier.materials.index')->with('success', 'Hammadde silindi.');
    }

    public function movement(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'material_id' => ['required', 'integer', Rule::exists('materials', 'id')],
            'type'        => ['required', Rule::in(['in', 'out', 'adjust'])],
            'quantity'    => ['required', 'numeric', 'not_in:0'],
            'reason'      => ['required', Rule::in(['purchase', 'consume', 'scrap', 'correction'])],
            'unit_cost'   => ['nullable', 'numeric', 'min:0'],
            'note'        => ['nullable', 'string', 'max:1000'],
        ]);

        $material = Material::findOrFail($data['material_id']);

        try {
            $this->stock->record(
                $material,
                $data['type'],
                (float) $data['quantity'],
                $data['reason'],
                ['unit_cost' => $data['unit_cost'] ?? null, 'note' => $data['note'] ?? null],
            );
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['quantity' => $e->getMessage()]);
        }

        return redirect()->route('atelier.materials.index')->with('success', 'Stok hareketi kaydedildi.');
    }

    private function validateMaterial(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'code'      => ['required', 'string', 'max:32', Rule::unique('materials', 'code')->ignore($ignoreId)],
            'name'      => ['required', 'string', 'max:191'],
            'type'      => ['required', Rule::in(['kumas', 'aksesuar', 'etiket'])],
            'unit'      => ['required', Rule::in(['metre', 'adet', 'kg'])],
            'unit_cost' => ['required', 'numeric', 'min:0'],
            'is_active' => ['boolean'],
        ]);
    }
}
```

- [ ] **Step 4: Route'ları ekle**

`Modules/Atelier/routes/web.php` içindeki grup'a `dashboard` route'unun altına ekle:

```php
        // Hammaddeler
        Route::get('materials', [\Modules\Atelier\Http\Controllers\MaterialController::class, 'index'])->name('materials.index');
        Route::post('materials', [\Modules\Atelier\Http\Controllers\MaterialController::class, 'store'])->name('materials.store');
        Route::put('materials/{material}', [\Modules\Atelier\Http\Controllers\MaterialController::class, 'update'])->name('materials.update');
        Route::delete('materials/{material}', [\Modules\Atelier\Http\Controllers\MaterialController::class, 'destroy'])->name('materials.destroy');
        Route::post('materials/movement', [\Modules\Atelier\Http\Controllers\MaterialController::class, 'movement'])->name('materials.movement');
```

- [ ] **Step 5: Test'i çalıştır, geçtiğini gör**

Run: `php artisan test --filter=MaterialControllerTest`
Expected: PASS (3 test).

- [ ] **Step 6: Commit**

```bash
git add Modules/Atelier/Http/Controllers/MaterialController.php Modules/Atelier/routes/web.php tests/Feature/Atelier/MaterialControllerTest.php
git commit -m "feat(atelier): MaterialController (CRUD + stok hareketi) + testleri"
```

### Task 1.5: Hammaddeler sayfası (Vue)

**Files:**
- Create: `Modules/Atelier/Resources/assets/js/Pages/Materials.vue`

- [ ] **Step 1: Sayfayı yaz**

`Modules/Atelier/Resources/assets/js/Pages/Materials.vue`:

```vue
<script setup>
import { ref } from 'vue'
import { router, useForm } from '@inertiajs/vue3'

const props = defineProps({ materials: Array })

const form = useForm({ id: null, code: '', name: '', type: 'kumas', unit: 'metre', unit_cost: 0, is_active: true })
const editing = ref(false)

function edit(m) {
  editing.value = true
  form.id = m.id; form.code = m.code; form.name = m.name; form.type = m.type
  form.unit = m.unit; form.unit_cost = m.unitCost; form.is_active = m.isActive
}
function reset() {
  editing.value = false
  form.reset(); form.id = null
}
function submit() {
  if (editing.value) {
    form.put(`/atelier/materials/${form.id}`, { onSuccess: reset })
  } else {
    form.post('/atelier/materials', { onSuccess: reset })
  }
}
function remove(m) {
  if (confirm(`${m.name} silinsin mi?`)) router.delete(`/atelier/materials/${m.id}`)
}

const move = useForm({ material_id: null, type: 'in', quantity: 0, reason: 'purchase', note: '' })
function openMove(m) { move.material_id = m.id; move.type = 'in'; move.quantity = 0; move.reason = 'purchase' }
function submitMove() {
  move.post('/atelier/materials/movement', { onSuccess: () => { move.reset(); move.material_id = null } })
}
</script>

<template>
  <div class="p-6 space-y-6">
    <h1 class="text-xl font-semibold">Hammaddeler</h1>

    <form @submit.prevent="submit" class="grid grid-cols-6 gap-2 items-end bg-white p-4 rounded shadow-sm">
      <label class="flex flex-col text-sm">Kod<input v-model="form.code" class="border rounded px-2 py-1" /></label>
      <label class="flex flex-col text-sm col-span-2">Ad<input v-model="form.name" class="border rounded px-2 py-1" /></label>
      <label class="flex flex-col text-sm">Tür
        <select v-model="form.type" class="border rounded px-2 py-1">
          <option value="kumas">Kumaş</option><option value="aksesuar">Aksesuar</option><option value="etiket">Etiket</option>
        </select>
      </label>
      <label class="flex flex-col text-sm">Birim
        <select v-model="form.unit" class="border rounded px-2 py-1">
          <option value="metre">Metre</option><option value="adet">Adet</option><option value="kg">Kg</option>
        </select>
      </label>
      <label class="flex flex-col text-sm">Birim Maliyet<input v-model="form.unit_cost" type="number" step="0.01" class="border rounded px-2 py-1" /></label>
      <div class="col-span-6 flex gap-2">
        <button class="bg-indigo-600 text-white px-4 py-1.5 rounded" :disabled="form.processing">{{ editing ? 'Güncelle' : 'Ekle' }}</button>
        <button v-if="editing" type="button" @click="reset" class="px-4 py-1.5 rounded border">Vazgeç</button>
      </div>
    </form>

    <table class="w-full text-sm bg-white rounded shadow-sm">
      <thead class="text-left border-b">
        <tr><th class="p-2">Kod</th><th class="p-2">Ad</th><th class="p-2">Tür</th><th class="p-2">Birim</th><th class="p-2 text-right">Stok</th><th class="p-2 text-right">Maliyet</th><th class="p-2"></th></tr>
      </thead>
      <tbody>
        <tr v-for="m in materials" :key="m.id" class="border-b">
          <td class="p-2">{{ m.code }}</td>
          <td class="p-2">{{ m.name }}</td>
          <td class="p-2">{{ m.type }}</td>
          <td class="p-2">{{ m.unit }}</td>
          <td class="p-2 text-right">{{ m.currentStock }}</td>
          <td class="p-2 text-right">{{ m.unitCost }}</td>
          <td class="p-2 text-right space-x-2">
            <button @click="openMove(m)" class="text-emerald-600">Hareket</button>
            <button @click="edit(m)" class="text-indigo-600">Düzenle</button>
            <button @click="remove(m)" class="text-red-600">Sil</button>
          </td>
        </tr>
      </tbody>
    </table>

    <div v-if="move.material_id" class="fixed inset-0 bg-black/40 flex items-center justify-center" @click.self="move.material_id = null">
      <form @submit.prevent="submitMove" class="bg-white p-6 rounded space-y-3 w-80">
        <h2 class="font-semibold">Stok Hareketi</h2>
        <label class="flex flex-col text-sm">Tip
          <select v-model="move.type" class="border rounded px-2 py-1">
            <option value="in">Giriş</option><option value="out">Çıkış</option><option value="adjust">Düzeltme</option>
          </select>
        </label>
        <label class="flex flex-col text-sm">Miktar<input v-model="move.quantity" type="number" step="0.001" class="border rounded px-2 py-1" /></label>
        <label class="flex flex-col text-sm">Sebep
          <select v-model="move.reason" class="border rounded px-2 py-1">
            <option value="purchase">Satın alma</option><option value="consume">Tüketim</option><option value="scrap">Fire</option><option value="correction">Düzeltme</option>
          </select>
        </label>
        <div v-if="move.errors.quantity" class="text-red-600 text-sm">{{ move.errors.quantity }}</div>
        <div class="flex gap-2">
          <button class="bg-indigo-600 text-white px-4 py-1.5 rounded">Kaydet</button>
          <button type="button" @click="move.material_id = null" class="px-4 py-1.5 rounded border">Kapat</button>
        </div>
      </form>
    </div>
  </div>
</template>
```

- [ ] **Step 2: Build kontrol**

Run: `npm run build`
Expected: hata yok.

- [ ] **Step 3: Commit**

```bash
git add Modules/Atelier/Resources/assets/js/Pages/Materials.vue
git commit -m "feat(atelier): Hammaddeler sayfasi"
```

---

## Faz 2 — Reçete (BOM)

### Task 2.1: `product_boms` + `bom_lines` migration'ları

**Files:**
- Create: `Modules/Atelier/database/migrations/2026_06_14_110000_create_product_boms_table.php`
- Create: `Modules/Atelier/database/migrations/2026_06_14_110100_create_bom_lines_table.php`

- [ ] **Step 1: product_boms migration**

`Modules/Atelier/database/migrations/2026_06_14_110000_create_product_boms_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_boms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('name', 191)->default('Varsayılan Reçete');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['product_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_boms');
    }
};
```

- [ ] **Step 2: bom_lines migration**

`Modules/Atelier/database/migrations/2026_06_14_110100_create_bom_lines_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bom_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bom_id')->constrained('product_boms')->cascadeOnDelete();
            $table->foreignId('material_id')->constrained('materials')->restrictOnDelete();
            $table->decimal('quantity_per_unit', 12, 4);
            $table->decimal('waste_pct', 5, 2)->default(0); // % fire
            $table->timestamps();

            $table->index('bom_id');
            $table->unique(['bom_id', 'material_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bom_lines');
    }
};
```

- [ ] **Step 3: Migrate ve doğrula**

Run: `php artisan migrate --path=Modules/Atelier/database/migrations`
Expected: iki tablo oluşur.

- [ ] **Step 4: Commit**

```bash
git add Modules/Atelier/database/migrations/
git commit -m "feat(atelier): product_boms + bom_lines tablolari"
```

### Task 2.2: `ProductBom` + `BomLine` modelleri

**Files:**
- Create: `Modules/Atelier/Models/ProductBom.php`
- Create: `Modules/Atelier/Models/BomLine.php`

- [ ] **Step 1: ProductBom model**

`Modules/Atelier/Models/ProductBom.php`:

```php
<?php

namespace Modules\Atelier\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Product\Models\Product;

class ProductBom extends Model
{
    protected $table = 'product_boms';

    protected $fillable = ['product_id', 'name', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(BomLine::class, 'bom_id');
    }
}
```

- [ ] **Step 2: BomLine model**

`Modules/Atelier/Models/BomLine.php`:

```php
<?php

namespace Modules\Atelier\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BomLine extends Model
{
    protected $table = 'bom_lines';

    protected $fillable = ['bom_id', 'material_id', 'quantity_per_unit', 'waste_pct'];

    protected $casts = [
        'quantity_per_unit' => 'decimal:4',
        'waste_pct'         => 'decimal:2',
    ];

    public function bom(): BelongsTo
    {
        return $this->belongsTo(ProductBom::class, 'bom_id');
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }
}
```

- [ ] **Step 3: Commit**

```bash
git add Modules/Atelier/Models/
git commit -m "feat(atelier): ProductBom + BomLine modelleri"
```

### Task 2.3: `BomService` (TDD)

**Files:**
- Create: `Modules/Atelier/Services/BomService.php`
- Test: `tests/Feature/Atelier/BomServiceTest.php`

- [ ] **Step 1: Failing test yaz**

`tests/Feature/Atelier/BomServiceTest.php`:

```php
<?php

namespace Tests\Feature\Atelier;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Atelier\Models\Material;
use Modules\Atelier\Models\ProductBom;
use Modules\Atelier\Services\BomService;
use Modules\Product\Models\Category;
use Modules\Product\Models\Product;
use Tests\TestCase;

class BomServiceTest extends TestCase
{
    use RefreshDatabase;

    private function product(): Product
    {
        $cat = Category::create(['name' => 'Tişört', 'slug' => 'tisort-' . uniqid(), 'status' => 'active', 'sort_order' => 0]);

        return Product::create([
            'category_id' => $cat->id, 'name' => 'Bebek Tişört',
            'sku' => 'SKU-' . uniqid(), 'gender' => 'Unisex', 'price' => 50,
        ]);
    }

    public function test_requirements_compute_quantity_and_cost_with_waste(): void
    {
        $product = $this->product();
        $kumas = Material::create(['code' => 'K1', 'name' => 'Kumaş', 'type' => 'kumas', 'unit' => 'metre', 'unit_cost' => 10]);
        $dugme = Material::create(['code' => 'D1', 'name' => 'Düğme', 'type' => 'aksesuar', 'unit' => 'adet', 'unit_cost' => 0.5]);

        $bom = ProductBom::create(['product_id' => $product->id, 'name' => 'Std', 'is_active' => true]);
        $bom->lines()->create(['material_id' => $kumas->id, 'quantity_per_unit' => 1.0, 'waste_pct' => 10]); // 1m + %10 fire
        $bom->lines()->create(['material_id' => $dugme->id, 'quantity_per_unit' => 4, 'waste_pct' => 0]);

        $req = app(BomService::class)->requirementsFor($product, 100);

        // kumaş: 100 * 1.0 * 1.10 = 110 metre, maliyet 110 * 10 = 1100
        $kumasReq = collect($req)->firstWhere('material_id', $kumas->id);
        $this->assertEqualsWithDelta(110.0, $kumasReq['required_qty'], 0.001);
        $this->assertEqualsWithDelta(1100.0, $kumasReq['line_cost'], 0.001);

        // düğme: 100 * 4 = 400 adet, maliyet 400 * 0.5 = 200
        $dugmeReq = collect($req)->firstWhere('material_id', $dugme->id);
        $this->assertEqualsWithDelta(400.0, $dugmeReq['required_qty'], 0.001);
        $this->assertEqualsWithDelta(200.0, $dugmeReq['line_cost'], 0.001);
    }

    public function test_returns_empty_when_no_active_bom(): void
    {
        $product = $this->product();

        $this->assertSame([], app(BomService::class)->requirementsFor($product, 10));
    }
}
```

- [ ] **Step 2: Test'i çalıştır, başarısız olduğunu gör**

Run: `php artisan test --filter=BomServiceTest`
Expected: FAIL — `BomService` not found.

- [ ] **Step 3: Servisi yaz**

`Modules/Atelier/Services/BomService.php`:

```php
<?php

namespace Modules\Atelier\Services;

use Modules\Atelier\Models\ProductBom;
use Modules\Product\Models\Product;

class BomService
{
    /**
     * Verilen ürün ve adet için gereken malzeme listesini hesaplar.
     *
     * @return array<int, array{material_id:int, material_name:string, unit:string,
     *   required_qty:float, unit_cost:float, line_cost:float}>
     */
    public function requirementsFor(Product $product, float $quantity): array
    {
        $bom = ProductBom::query()
            ->with('lines.material')
            ->where('product_id', $product->id)
            ->where('is_active', true)
            ->latest('id')
            ->first();

        if (! $bom) {
            return [];
        }

        return $bom->lines->map(function ($line) use ($quantity) {
            $perUnit = (float) $line->quantity_per_unit * (1 + (float) $line->waste_pct / 100);
            $required = $perUnit * $quantity;
            $unitCost = (float) ($line->material->unit_cost ?? 0);

            return [
                'material_id'   => $line->material_id,
                'material_name' => $line->material->name ?? '',
                'unit'          => $line->material->unit ?? '',
                'required_qty'  => round($required, 3),
                'unit_cost'     => $unitCost,
                'line_cost'     => round($required * $unitCost, 2),
            ];
        })->all();
    }
}
```

- [ ] **Step 4: Test'i çalıştır, geçtiğini gör**

Run: `php artisan test --filter=BomServiceTest`
Expected: PASS (2 test).

- [ ] **Step 5: Commit**

```bash
git add Modules/Atelier/Services/BomService.php tests/Feature/Atelier/BomServiceTest.php
git commit -m "feat(atelier): BomService (malzeme gereksinim hesabi) + testleri"
```

### Task 2.4: `BomController` (TDD) + route

**Files:**
- Create: `Modules/Atelier/Http/Controllers/BomController.php`
- Modify: `Modules/Atelier/routes/web.php`
- Test: `tests/Feature/Atelier/BomControllerTest.php`

- [ ] **Step 1: Failing test yaz**

`tests/Feature/Atelier/BomControllerTest.php`:

```php
<?php

namespace Tests\Feature\Atelier;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Atelier\Models\Material;
use Modules\Product\Models\Category;
use Modules\Product\Models\Product;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BomControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $perm = Permission::firstOrCreate(['name' => 'atelier.manage', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);
        $role->givePermissionTo($perm);
        $this->admin = User::factory()->create();
        $this->admin->assignRole($role);
    }

    public function test_save_creates_bom_with_lines(): void
    {
        $cat = Category::create(['name' => 'C', 'slug' => 'c-' . uniqid(), 'status' => 'active', 'sort_order' => 0]);
        $product = Product::create(['category_id' => $cat->id, 'name' => 'P', 'sku' => 'S-' . uniqid(), 'gender' => 'Unisex', 'price' => 10]);
        $material = Material::create(['code' => 'K', 'name' => 'Kumaş', 'type' => 'kumas', 'unit' => 'metre', 'unit_cost' => 10]);

        $this->actingAs($this->admin)
            ->post('/atelier/boms', [
                'product_id' => $product->id,
                'name' => 'Std',
                'lines' => [
                    ['material_id' => $material->id, 'quantity_per_unit' => 1.5, 'waste_pct' => 5],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('product_boms', ['product_id' => $product->id, 'name' => 'Std', 'is_active' => true]);
        $this->assertDatabaseHas('bom_lines', ['material_id' => $material->id, 'quantity_per_unit' => 1.5]);
    }
}
```

- [ ] **Step 2: Test'i çalıştır, başarısız olduğunu gör**

Run: `php artisan test --filter=BomControllerTest`
Expected: FAIL — route yok.

- [ ] **Step 3: Controller'ı yaz**

`Modules/Atelier/Http/Controllers/BomController.php`:

```php
<?php

namespace Modules\Atelier\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Atelier\Models\Material;
use Modules\Atelier\Models\ProductBom;
use Modules\Product\Models\Product;

class BomController extends Controller
{
    public function index(): Response
    {
        $boms = ProductBom::query()
            ->with(['product:id,name,sku', 'lines.material:id,name,unit'])
            ->where('is_active', true)
            ->get()
            ->map(fn (ProductBom $b) => [
                'id'          => $b->id,
                'productId'   => $b->product_id,
                'productName' => $b->product?->name,
                'name'        => $b->name,
                'lines'       => $b->lines->map(fn ($l) => [
                    'materialId'      => $l->material_id,
                    'materialName'    => $l->material?->name,
                    'unit'            => $l->material?->unit,
                    'quantityPerUnit' => (float) $l->quantity_per_unit,
                    'wastePct'        => (float) $l->waste_pct,
                ]),
            ]);

        return Inertia::render('Atelier::Boms', [
            'boms'      => $boms,
            'products'  => Product::query()->orderBy('name')->get(['id', 'name', 'sku']),
            'materials' => Material::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'unit']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateBom($request);

        DB::transaction(function () use ($data) {
            // Ürün başına tek aktif reçete: öncekini pasifle.
            ProductBom::where('product_id', $data['product_id'])->update(['is_active' => false]);

            $bom = ProductBom::create([
                'product_id' => $data['product_id'],
                'name'       => $data['name'],
                'is_active'  => true,
            ]);

            foreach ($data['lines'] as $line) {
                $bom->lines()->create([
                    'material_id'       => $line['material_id'],
                    'quantity_per_unit' => $line['quantity_per_unit'],
                    'waste_pct'         => $line['waste_pct'] ?? 0,
                ]);
            }
        });

        return redirect()->route('atelier.boms.index')->with('success', 'Reçete kaydedildi.');
    }

    public function destroy(ProductBom $bom): RedirectResponse
    {
        $bom->delete();

        return redirect()->route('atelier.boms.index')->with('success', 'Reçete silindi.');
    }

    private function validateBom(Request $request): array
    {
        return $request->validate([
            'product_id'                 => ['required', 'integer', Rule::exists('products', 'id')],
            'name'                       => ['required', 'string', 'max:191'],
            'lines'                      => ['required', 'array', 'min:1'],
            'lines.*.material_id'        => ['required', 'integer', Rule::exists('materials', 'id')],
            'lines.*.quantity_per_unit'  => ['required', 'numeric', 'min:0.0001'],
            'lines.*.waste_pct'          => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);
    }
}
```

- [ ] **Step 4: Route'ları ekle**

`Modules/Atelier/routes/web.php` grubuna ekle:

```php
        // Reçeteler (BOM)
        Route::get('boms', [\Modules\Atelier\Http\Controllers\BomController::class, 'index'])->name('boms.index');
        Route::post('boms', [\Modules\Atelier\Http\Controllers\BomController::class, 'store'])->name('boms.store');
        Route::delete('boms/{bom}', [\Modules\Atelier\Http\Controllers\BomController::class, 'destroy'])->name('boms.destroy');
```

- [ ] **Step 5: Test'i çalıştır, geçtiğini gör**

Run: `php artisan test --filter=BomControllerTest`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add Modules/Atelier/Http/Controllers/BomController.php Modules/Atelier/routes/web.php tests/Feature/Atelier/BomControllerTest.php
git commit -m "feat(atelier): BomController + testleri"
```

### Task 2.5: Reçeteler sayfası (Vue)

**Files:**
- Create: `Modules/Atelier/Resources/assets/js/Pages/Boms.vue`

- [ ] **Step 1: Sayfayı yaz**

`Modules/Atelier/Resources/assets/js/Pages/Boms.vue`:

```vue
<script setup>
import { useForm, router } from '@inertiajs/vue3'

const props = defineProps({ boms: Array, products: Array, materials: Array })

const form = useForm({ product_id: '', name: 'Varsayılan Reçete', lines: [{ material_id: '', quantity_per_unit: 1, waste_pct: 0 }] })

function addLine() { form.lines.push({ material_id: '', quantity_per_unit: 1, waste_pct: 0 }) }
function removeLine(i) { form.lines.splice(i, 1) }
function submit() {
  form.post('/atelier/boms', { onSuccess: () => { form.reset(); form.lines = [{ material_id: '', quantity_per_unit: 1, waste_pct: 0 }] } })
}
function remove(b) { if (confirm('Reçete silinsin mi?')) router.delete(`/atelier/boms/${b.id}`) }
</script>

<template>
  <div class="p-6 space-y-6">
    <h1 class="text-xl font-semibold">Reçeteler (BOM)</h1>

    <form @submit.prevent="submit" class="bg-white p-4 rounded shadow-sm space-y-3">
      <div class="flex gap-3">
        <label class="flex flex-col text-sm flex-1">Ürün
          <select v-model="form.product_id" class="border rounded px-2 py-1">
            <option value="">Seçin…</option>
            <option v-for="p in products" :key="p.id" :value="p.id">{{ p.name }} ({{ p.sku }})</option>
          </select>
        </label>
        <label class="flex flex-col text-sm flex-1">Reçete adı<input v-model="form.name" class="border rounded px-2 py-1" /></label>
      </div>

      <div v-for="(line, i) in form.lines" :key="i" class="flex gap-2 items-end">
        <label class="flex flex-col text-sm flex-1">Malzeme
          <select v-model="line.material_id" class="border rounded px-2 py-1">
            <option value="">Seçin…</option>
            <option v-for="m in materials" :key="m.id" :value="m.id">{{ m.name }} ({{ m.unit }})</option>
          </select>
        </label>
        <label class="flex flex-col text-sm w-32">Birim/adet<input v-model="line.quantity_per_unit" type="number" step="0.0001" class="border rounded px-2 py-1" /></label>
        <label class="flex flex-col text-sm w-24">Fire %<input v-model="line.waste_pct" type="number" step="0.01" class="border rounded px-2 py-1" /></label>
        <button type="button" @click="removeLine(i)" class="text-red-600 pb-1.5">Sil</button>
      </div>

      <div class="flex gap-2">
        <button type="button" @click="addLine" class="px-3 py-1 rounded border">+ Satır</button>
        <button class="bg-indigo-600 text-white px-4 py-1.5 rounded" :disabled="form.processing">Kaydet</button>
      </div>
    </form>

    <div v-for="b in boms" :key="b.id" class="bg-white p-4 rounded shadow-sm">
      <div class="flex justify-between">
        <h3 class="font-medium">{{ b.productName }} — {{ b.name }}</h3>
        <button @click="remove(b)" class="text-red-600 text-sm">Sil</button>
      </div>
      <ul class="text-sm mt-2 space-y-1">
        <li v-for="(l, i) in b.lines" :key="i">{{ l.materialName }}: {{ l.quantityPerUnit }} {{ l.unit }} (fire %{{ l.wastePct }})</li>
      </ul>
    </div>
  </div>
</template>
```

- [ ] **Step 2: Build kontrol**

Run: `npm run build`
Expected: hata yok.

- [ ] **Step 3: Commit**

```bash
git add Modules/Atelier/Resources/assets/js/Pages/Boms.vue
git commit -m "feat(atelier): Receteler sayfasi"
```

---

## Faz 3 — Operasyonlar + Fasoncular

### Task 3.1: `operations` + `fason_suppliers` migration'ları

**Files:**
- Create: `Modules/Atelier/database/migrations/2026_06_14_120000_create_operations_table.php`
- Create: `Modules/Atelier/database/migrations/2026_06_14_120100_create_fason_suppliers_table.php`

- [ ] **Step 1: operations migration**

`Modules/Atelier/database/migrations/2026_06_14_120000_create_operations_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operations', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name', 191);
            $table->enum('default_location', ['in_house', 'fason'])->default('in_house');
            $table->decimal('default_unit_cost', 12, 2)->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operations');
    }
};
```

- [ ] **Step 2: fason_suppliers migration**

`Modules/Atelier/database/migrations/2026_06_14_120100_create_fason_suppliers_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fason_suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 191);
            $table->string('contact_name', 191)->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('email', 191)->nullable();
            $table->text('address')->nullable();
            $table->string('tax_no', 32)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fason_suppliers');
    }
};
```

- [ ] **Step 3: Migrate**

Run: `php artisan migrate --path=Modules/Atelier/database/migrations`
Expected: iki tablo oluşur.

- [ ] **Step 4: Commit**

```bash
git add Modules/Atelier/database/migrations/
git commit -m "feat(atelier): operations + fason_suppliers tablolari"
```

### Task 3.2: `Operation` + `FasonSupplier` modelleri

**Files:**
- Create: `Modules/Atelier/Models/Operation.php`
- Create: `Modules/Atelier/Models/FasonSupplier.php`

- [ ] **Step 1: Operation model**

`Modules/Atelier/Models/Operation.php`:

```php
<?php

namespace Modules\Atelier\Models;

use Illuminate\Database\Eloquent\Model;

class Operation extends Model
{
    protected $table = 'operations';

    protected $fillable = ['code', 'name', 'default_location', 'default_unit_cost', 'sort_order'];

    protected $casts = [
        'default_unit_cost' => 'decimal:2',
        'sort_order'        => 'integer',
    ];
}
```

- [ ] **Step 2: FasonSupplier model**

`Modules/Atelier/Models/FasonSupplier.php`:

```php
<?php

namespace Modules\Atelier\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FasonSupplier extends Model
{
    use SoftDeletes;

    protected $table = 'fason_suppliers';

    protected $fillable = [
        'name', 'contact_name', 'phone', 'email', 'address', 'tax_no', 'notes', 'is_active',
    ];

    protected $casts = ['is_active' => 'boolean'];
}
```

- [ ] **Step 3: Commit**

```bash
git add Modules/Atelier/Models/
git commit -m "feat(atelier): Operation + FasonSupplier modelleri"
```

### Task 3.3: Operasyon seeder (varsayılan operasyonlar)

**Files:**
- Create: `Modules/Atelier/database/seeders/OperationSeeder.php`
- Modify: `Modules/Atelier/database/seeders/AtelierDatabaseSeeder.php`

- [ ] **Step 1: OperationSeeder yaz**

`Modules/Atelier/database/seeders/OperationSeeder.php`:

```php
<?php

namespace Modules\Atelier\database\seeders;

use Illuminate\Database\Seeder;
use Modules\Atelier\Models\Operation;

class OperationSeeder extends Seeder
{
    public function run(): void
    {
        $operations = [
            ['code' => 'kesim',  'name' => 'Kesim',          'default_location' => 'in_house', 'sort_order' => 10],
            ['code' => 'dikim',  'name' => 'Dikim',          'default_location' => 'fason',    'sort_order' => 20],
            ['code' => 'baski',  'name' => 'Baskı',          'default_location' => 'fason',    'sort_order' => 30],
            ['code' => 'nakis',  'name' => 'Nakış',          'default_location' => 'fason',    'sort_order' => 40],
            ['code' => 'utu',    'name' => 'Ütü',            'default_location' => 'in_house', 'sort_order' => 50],
            ['code' => 'kalite', 'name' => 'Kalite Kontrol', 'default_location' => 'in_house', 'sort_order' => 60],
            ['code' => 'paket',  'name' => 'Paketleme',      'default_location' => 'in_house', 'sort_order' => 70],
        ];

        foreach ($operations as $op) {
            Operation::firstOrCreate(['code' => $op['code']], $op);
        }
    }
}
```

- [ ] **Step 2: AtelierDatabaseSeeder'dan çağır**

`Modules/Atelier/database/seeders/AtelierDatabaseSeeder.php` içindeki `run()` metoduna ekle (mevcut içeriği oku, `$this->call([...])` listesine `OperationSeeder::class` ekle; liste yoksa oluştur):

```php
        $this->call([
            \Modules\Atelier\database\seeders\OperationSeeder::class,
        ]);
```

- [ ] **Step 3: Seed çalıştır ve doğrula**

Run: `php artisan db:seed --class="Modules\Atelier\database\seeders\OperationSeeder"`
Expected: 7 operasyon oluşur, tekrar çalışınca duplicate olmaz (idempotent).

- [ ] **Step 4: Commit**

```bash
git add Modules/Atelier/database/seeders/
git commit -m "feat(atelier): varsayilan operasyon seeder'i"
```

### Task 3.4: `OperationController` + `FasonSupplierController` (TDD) + route'lar

**Files:**
- Create: `Modules/Atelier/Http/Controllers/OperationController.php`
- Create: `Modules/Atelier/Http/Controllers/FasonSupplierController.php`
- Modify: `Modules/Atelier/routes/web.php`
- Test: `tests/Feature/Atelier/OperationFasonControllerTest.php`

- [ ] **Step 1: Failing test yaz**

`tests/Feature/Atelier/OperationFasonControllerTest.php`:

```php
<?php

namespace Tests\Feature\Atelier;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OperationFasonControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $perm = Permission::firstOrCreate(['name' => 'atelier.manage', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);
        $role->givePermissionTo($perm);
        $this->admin = User::factory()->create();
        $this->admin->assignRole($role);
    }

    public function test_create_operation(): void
    {
        $this->actingAs($this->admin)
            ->post('/atelier/operations', [
                'code' => 'overlok', 'name' => 'Overlok',
                'default_location' => 'fason', 'default_unit_cost' => 1.25, 'sort_order' => 25,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('operations', ['code' => 'overlok', 'default_location' => 'fason']);
    }

    public function test_create_fason_supplier(): void
    {
        $this->actingAs($this->admin)
            ->post('/atelier/fason-suppliers', [
                'name' => 'Yılmaz Dikim', 'phone' => '0555', 'is_active' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('fason_suppliers', ['name' => 'Yılmaz Dikim']);
    }
}
```

- [ ] **Step 2: Test'i çalıştır, başarısız olduğunu gör**

Run: `php artisan test --filter=OperationFasonControllerTest`
Expected: FAIL — route yok.

- [ ] **Step 3: OperationController yaz**

`Modules/Atelier/Http/Controllers/OperationController.php`:

```php
<?php

namespace Modules\Atelier\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Atelier\Models\Operation;

class OperationController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Atelier::Operations', [
            'operations' => Operation::query()->orderBy('sort_order')->get()->map(fn (Operation $o) => [
                'id'              => $o->id,
                'code'            => $o->code,
                'name'            => $o->name,
                'defaultLocation' => $o->default_location,
                'defaultUnitCost' => (float) $o->default_unit_cost,
                'sortOrder'       => $o->sort_order,
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Operation::create($this->validateOperation($request));

        return redirect()->route('atelier.operations.index')->with('success', 'Operasyon eklendi.');
    }

    public function update(Request $request, Operation $operation): RedirectResponse
    {
        $operation->update($this->validateOperation($request, $operation->id));

        return redirect()->route('atelier.operations.index')->with('success', 'Operasyon güncellendi.');
    }

    public function destroy(Operation $operation): RedirectResponse
    {
        $operation->delete();

        return redirect()->route('atelier.operations.index')->with('success', 'Operasyon silindi.');
    }

    private function validateOperation(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'code'              => ['required', 'string', 'max:32', Rule::unique('operations', 'code')->ignore($ignoreId)],
            'name'              => ['required', 'string', 'max:191'],
            'default_location'  => ['required', Rule::in(['in_house', 'fason'])],
            'default_unit_cost' => ['required', 'numeric', 'min:0'],
            'sort_order'        => ['nullable', 'integer', 'min:0'],
        ]);
    }
}
```

- [ ] **Step 4: FasonSupplierController yaz**

`Modules/Atelier/Http/Controllers/FasonSupplierController.php`:

```php
<?php

namespace Modules\Atelier\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Atelier\Models\FasonSupplier;

class FasonSupplierController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Atelier::FasonSuppliers', [
            'suppliers' => FasonSupplier::query()->orderBy('name')->get()->map(fn (FasonSupplier $s) => [
                'id'          => $s->id,
                'name'        => $s->name,
                'contactName' => $s->contact_name,
                'phone'       => $s->phone,
                'email'       => $s->email,
                'address'     => $s->address,
                'taxNo'       => $s->tax_no,
                'notes'       => $s->notes,
                'isActive'    => $s->is_active,
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        FasonSupplier::create($this->validateSupplier($request));

        return redirect()->route('atelier.fason-suppliers.index')->with('success', 'Fasoncu eklendi.');
    }

    public function update(Request $request, FasonSupplier $fasonSupplier): RedirectResponse
    {
        $fasonSupplier->update($this->validateSupplier($request));

        return redirect()->route('atelier.fason-suppliers.index')->with('success', 'Fasoncu güncellendi.');
    }

    public function destroy(FasonSupplier $fasonSupplier): RedirectResponse
    {
        $fasonSupplier->delete();

        return redirect()->route('atelier.fason-suppliers.index')->with('success', 'Fasoncu silindi.');
    }

    private function validateSupplier(Request $request): array
    {
        return $request->validate([
            'name'         => ['required', 'string', 'max:191'],
            'contact_name' => ['nullable', 'string', 'max:191'],
            'phone'        => ['nullable', 'string', 'max:32'],
            'email'        => ['nullable', 'email', 'max:191'],
            'address'      => ['nullable', 'string', 'max:2000'],
            'tax_no'       => ['nullable', 'string', 'max:32'],
            'notes'        => ['nullable', 'string', 'max:2000'],
            'is_active'    => ['boolean'],
        ]);
    }
}
```

- [ ] **Step 5: Route'ları ekle**

`Modules/Atelier/routes/web.php` grubuna ekle:

```php
        // Operasyonlar
        Route::get('operations', [\Modules\Atelier\Http\Controllers\OperationController::class, 'index'])->name('operations.index');
        Route::post('operations', [\Modules\Atelier\Http\Controllers\OperationController::class, 'store'])->name('operations.store');
        Route::put('operations/{operation}', [\Modules\Atelier\Http\Controllers\OperationController::class, 'update'])->name('operations.update');
        Route::delete('operations/{operation}', [\Modules\Atelier\Http\Controllers\OperationController::class, 'destroy'])->name('operations.destroy');

        // Fasoncular
        Route::get('fason-suppliers', [\Modules\Atelier\Http\Controllers\FasonSupplierController::class, 'index'])->name('fason-suppliers.index');
        Route::post('fason-suppliers', [\Modules\Atelier\Http\Controllers\FasonSupplierController::class, 'store'])->name('fason-suppliers.store');
        Route::put('fason-suppliers/{fasonSupplier}', [\Modules\Atelier\Http\Controllers\FasonSupplierController::class, 'update'])->name('fason-suppliers.update');
        Route::delete('fason-suppliers/{fasonSupplier}', [\Modules\Atelier\Http\Controllers\FasonSupplierController::class, 'destroy'])->name('fason-suppliers.destroy');
```

- [ ] **Step 6: Test'i çalıştır, geçtiğini gör**

Run: `php artisan test --filter=OperationFasonControllerTest`
Expected: PASS (2 test).

- [ ] **Step 7: Commit**

```bash
git add Modules/Atelier/Http/Controllers/OperationController.php Modules/Atelier/Http/Controllers/FasonSupplierController.php Modules/Atelier/routes/web.php tests/Feature/Atelier/OperationFasonControllerTest.php
git commit -m "feat(atelier): Operation + FasonSupplier controller'lari + testleri"
```

### Task 3.5: Operasyonlar + Fasoncular sayfaları (Vue)

**Files:**
- Create: `Modules/Atelier/Resources/assets/js/Pages/Operations.vue`
- Create: `Modules/Atelier/Resources/assets/js/Pages/FasonSuppliers.vue`

- [ ] **Step 1: Operations.vue yaz**

`Modules/Atelier/Resources/assets/js/Pages/Operations.vue`:

```vue
<script setup>
import { ref } from 'vue'
import { useForm, router } from '@inertiajs/vue3'

const props = defineProps({ operations: Array })
const form = useForm({ id: null, code: '', name: '', default_location: 'in_house', default_unit_cost: 0, sort_order: 0 })
const editing = ref(false)

function edit(o) {
  editing.value = true
  form.id = o.id; form.code = o.code; form.name = o.name
  form.default_location = o.defaultLocation; form.default_unit_cost = o.defaultUnitCost; form.sort_order = o.sortOrder
}
function reset() { editing.value = false; form.reset(); form.id = null }
function submit() {
  editing.value ? form.put(`/atelier/operations/${form.id}`, { onSuccess: reset }) : form.post('/atelier/operations', { onSuccess: reset })
}
function remove(o) { if (confirm('Operasyon silinsin mi?')) router.delete(`/atelier/operations/${o.id}`) }
</script>

<template>
  <div class="p-6 space-y-6">
    <h1 class="text-xl font-semibold">Operasyonlar</h1>
    <form @submit.prevent="submit" class="grid grid-cols-6 gap-2 items-end bg-white p-4 rounded shadow-sm">
      <label class="flex flex-col text-sm">Kod<input v-model="form.code" class="border rounded px-2 py-1" /></label>
      <label class="flex flex-col text-sm col-span-2">Ad<input v-model="form.name" class="border rounded px-2 py-1" /></label>
      <label class="flex flex-col text-sm">Varsayılan yer
        <select v-model="form.default_location" class="border rounded px-2 py-1">
          <option value="in_house">İç atölye</option><option value="fason">Fason</option>
        </select>
      </label>
      <label class="flex flex-col text-sm">Birim işçilik<input v-model="form.default_unit_cost" type="number" step="0.01" class="border rounded px-2 py-1" /></label>
      <label class="flex flex-col text-sm">Sıra<input v-model="form.sort_order" type="number" class="border rounded px-2 py-1" /></label>
      <div class="col-span-6 flex gap-2">
        <button class="bg-indigo-600 text-white px-4 py-1.5 rounded">{{ editing ? 'Güncelle' : 'Ekle' }}</button>
        <button v-if="editing" type="button" @click="reset" class="px-4 py-1.5 rounded border">Vazgeç</button>
      </div>
    </form>
    <table class="w-full text-sm bg-white rounded shadow-sm">
      <thead class="text-left border-b"><tr><th class="p-2">Kod</th><th class="p-2">Ad</th><th class="p-2">Yer</th><th class="p-2 text-right">İşçilik</th><th class="p-2 text-right">Sıra</th><th class="p-2"></th></tr></thead>
      <tbody>
        <tr v-for="o in operations" :key="o.id" class="border-b">
          <td class="p-2">{{ o.code }}</td><td class="p-2">{{ o.name }}</td>
          <td class="p-2">{{ o.defaultLocation === 'fason' ? 'Fason' : 'İç' }}</td>
          <td class="p-2 text-right">{{ o.defaultUnitCost }}</td><td class="p-2 text-right">{{ o.sortOrder }}</td>
          <td class="p-2 text-right space-x-2"><button @click="edit(o)" class="text-indigo-600">Düzenle</button><button @click="remove(o)" class="text-red-600">Sil</button></td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
```

- [ ] **Step 2: FasonSuppliers.vue yaz**

`Modules/Atelier/Resources/assets/js/Pages/FasonSuppliers.vue`:

```vue
<script setup>
import { ref } from 'vue'
import { useForm, router } from '@inertiajs/vue3'

const props = defineProps({ suppliers: Array })
const form = useForm({ id: null, name: '', contact_name: '', phone: '', email: '', address: '', tax_no: '', notes: '', is_active: true })
const editing = ref(false)

function edit(s) {
  editing.value = true
  Object.assign(form, { id: s.id, name: s.name, contact_name: s.contactName, phone: s.phone, email: s.email, address: s.address, tax_no: s.taxNo, notes: s.notes, is_active: s.isActive })
}
function reset() { editing.value = false; form.reset(); form.id = null }
function submit() {
  editing.value ? form.put(`/atelier/fason-suppliers/${form.id}`, { onSuccess: reset }) : form.post('/atelier/fason-suppliers', { onSuccess: reset })
}
function remove(s) { if (confirm('Fasoncu silinsin mi?')) router.delete(`/atelier/fason-suppliers/${s.id}`) }
</script>

<template>
  <div class="p-6 space-y-6">
    <h1 class="text-xl font-semibold">Fasoncular</h1>
    <form @submit.prevent="submit" class="grid grid-cols-4 gap-2 items-end bg-white p-4 rounded shadow-sm">
      <label class="flex flex-col text-sm">Ad<input v-model="form.name" class="border rounded px-2 py-1" /></label>
      <label class="flex flex-col text-sm">Yetkili<input v-model="form.contact_name" class="border rounded px-2 py-1" /></label>
      <label class="flex flex-col text-sm">Telefon<input v-model="form.phone" class="border rounded px-2 py-1" /></label>
      <label class="flex flex-col text-sm">E-posta<input v-model="form.email" class="border rounded px-2 py-1" /></label>
      <label class="flex flex-col text-sm col-span-2">Adres<input v-model="form.address" class="border rounded px-2 py-1" /></label>
      <label class="flex flex-col text-sm">Vergi No<input v-model="form.tax_no" class="border rounded px-2 py-1" /></label>
      <div class="flex gap-2 items-end">
        <button class="bg-indigo-600 text-white px-4 py-1.5 rounded">{{ editing ? 'Güncelle' : 'Ekle' }}</button>
        <button v-if="editing" type="button" @click="reset" class="px-4 py-1.5 rounded border">Vazgeç</button>
      </div>
    </form>
    <table class="w-full text-sm bg-white rounded shadow-sm">
      <thead class="text-left border-b"><tr><th class="p-2">Ad</th><th class="p-2">Yetkili</th><th class="p-2">Telefon</th><th class="p-2">E-posta</th><th class="p-2"></th></tr></thead>
      <tbody>
        <tr v-for="s in suppliers" :key="s.id" class="border-b">
          <td class="p-2">{{ s.name }}</td><td class="p-2">{{ s.contactName }}</td><td class="p-2">{{ s.phone }}</td><td class="p-2">{{ s.email }}</td>
          <td class="p-2 text-right space-x-2"><button @click="edit(s)" class="text-indigo-600">Düzenle</button><button @click="remove(s)" class="text-red-600">Sil</button></td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
```

- [ ] **Step 3: Build kontrol**

Run: `npm run build`
Expected: hata yok.

- [ ] **Step 4: Commit**

```bash
git add Modules/Atelier/Resources/assets/js/Pages/Operations.vue Modules/Atelier/Resources/assets/js/Pages/FasonSuppliers.vue
git commit -m "feat(atelier): Operasyonlar + Fasoncular sayfalari"
```

---

## Faz 4 — İş emri çekirdeği (tablolar + modeller)

### Task 4.1: İş emri migration'ları

**Files:**
- Create: `Modules/Atelier/database/migrations/2026_06_14_130000_create_production_orders_table.php`
- Create: `Modules/Atelier/database/migrations/2026_06_14_130100_create_production_order_items_table.php`
- Create: `Modules/Atelier/database/migrations/2026_06_14_130200_create_production_order_steps_table.php`
- Create: `Modules/Atelier/database/migrations/2026_06_14_130300_add_production_order_fk_to_material_movements.php`

- [ ] **Step 1: production_orders migration**

`Modules/Atelier/database/migrations/2026_06_14_130000_create_production_orders_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_orders', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->enum('status', ['draft', 'planned', 'in_progress', 'completed', 'cancelled'])->default('draft');
            $table->unsignedInteger('planned_qty')->default(0);
            $table->unsignedInteger('produced_qty')->default(0);
            $table->date('planned_start')->nullable();
            $table->date('due_date')->nullable();
            $table->decimal('material_cost', 14, 2)->default(0);
            $table->decimal('fason_cost', 14, 2)->default(0);
            $table->decimal('labor_cost', 14, 2)->default(0);
            $table->decimal('total_cost', 14, 2)->default(0);
            $table->decimal('unit_cost', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('due_date');
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_orders');
    }
};
```

- [ ] **Step 2: production_order_items migration**

`Modules/Atelier/database/migrations/2026_06_14_130100_create_production_order_items_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_order_id')->constrained('production_orders')->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained('product_variants')->restrictOnDelete();
            $table->unsignedInteger('planned_qty')->default(0);
            $table->unsignedInteger('produced_qty')->default(0);
            $table->unsignedInteger('scrap_qty')->default(0);
            $table->timestamps();

            $table->unique(['production_order_id', 'product_variant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_order_items');
    }
};
```

- [ ] **Step 3: production_order_steps migration**

`Modules/Atelier/database/migrations/2026_06_14_130200_create_production_order_steps_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_order_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_order_id')->constrained('production_orders')->cascadeOnDelete();
            $table->foreignId('operation_id')->constrained('operations')->restrictOnDelete();
            $table->unsignedInteger('sequence')->default(0);
            $table->enum('location_type', ['in_house', 'fason'])->default('in_house');
            $table->foreignId('fason_supplier_id')->nullable()->constrained('fason_suppliers')->nullOnDelete();
            $table->enum('status', ['pending', 'in_progress', 'done'])->default('pending');
            $table->unsignedInteger('input_qty')->default(0);
            $table->unsignedInteger('output_qty')->default(0);
            $table->unsignedInteger('scrap_qty')->default(0);
            $table->decimal('unit_cost', 12, 2)->default(0);
            $table->decimal('step_cost', 14, 2)->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['production_order_id', 'sequence']);
            $table->index('status');
            $table->index('fason_supplier_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_order_steps');
    }
};
```

- [ ] **Step 4: material_movements'a FK ekle**

`Modules/Atelier/database/migrations/2026_06_14_130300_add_production_order_fk_to_material_movements.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('material_movements', function (Blueprint $table) {
            $table->foreign('production_order_id')
                ->references('id')->on('production_orders')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('material_movements', function (Blueprint $table) {
            $table->dropForeign(['production_order_id']);
        });
    }
};
```

- [ ] **Step 5: Migrate**

Run: `php artisan migrate --path=Modules/Atelier/database/migrations`
Expected: 4 migration çalışır, hata yok.

- [ ] **Step 6: Commit**

```bash
git add Modules/Atelier/database/migrations/
git commit -m "feat(atelier): production_orders/items/steps tablolari + material_movements FK"
```

### Task 4.2: İş emri modelleri

**Files:**
- Create: `Modules/Atelier/Models/ProductionOrder.php`
- Create: `Modules/Atelier/Models/ProductionOrderItem.php`
- Create: `Modules/Atelier/Models/ProductionOrderStep.php`

- [ ] **Step 1: ProductionOrder model**

`Modules/Atelier/Models/ProductionOrder.php`:

```php
<?php

namespace Modules\Atelier\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Product\Models\Product;
use Modules\Product\Models\Warehouse;

class ProductionOrder extends Model
{
    use SoftDeletes;

    public const STATUS_DRAFT       = 'draft';
    public const STATUS_PLANNED     = 'planned';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED   = 'completed';
    public const STATUS_CANCELLED   = 'cancelled';

    protected $table = 'production_orders';

    protected $fillable = [
        'code', 'product_id', 'warehouse_id', 'status', 'planned_qty', 'produced_qty',
        'planned_start', 'due_date', 'material_cost', 'fason_cost', 'labor_cost',
        'total_cost', 'unit_cost', 'notes', 'created_by',
    ];

    protected $casts = [
        'planned_qty'   => 'integer',
        'produced_qty'  => 'integer',
        'planned_start' => 'date',
        'due_date'      => 'date',
        'material_cost' => 'decimal:2',
        'fason_cost'    => 'decimal:2',
        'labor_cost'    => 'decimal:2',
        'total_cost'    => 'decimal:2',
        'unit_cost'     => 'decimal:2',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProductionOrderItem::class);
    }

    public function steps(): HasMany
    {
        return $this->hasMany(ProductionOrderStep::class)->orderBy('sequence');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
```

- [ ] **Step 2: ProductionOrderItem model**

`Modules/Atelier/Models/ProductionOrderItem.php`:

```php
<?php

namespace Modules\Atelier\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Product\Models\ProductVariant;

class ProductionOrderItem extends Model
{
    protected $table = 'production_order_items';

    protected $fillable = [
        'production_order_id', 'product_variant_id', 'planned_qty', 'produced_qty', 'scrap_qty',
    ];

    protected $casts = [
        'planned_qty'  => 'integer',
        'produced_qty' => 'integer',
        'scrap_qty'    => 'integer',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class, 'production_order_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
```

- [ ] **Step 3: ProductionOrderStep model**

`Modules/Atelier/Models/ProductionOrderStep.php`:

```php
<?php

namespace Modules\Atelier\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionOrderStep extends Model
{
    public const STATUS_PENDING     = 'pending';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_DONE        = 'done';

    protected $table = 'production_order_steps';

    protected $fillable = [
        'production_order_id', 'operation_id', 'sequence', 'location_type', 'fason_supplier_id',
        'status', 'input_qty', 'output_qty', 'scrap_qty', 'unit_cost', 'step_cost',
        'started_at', 'completed_at', 'note',
    ];

    protected $casts = [
        'sequence'     => 'integer',
        'input_qty'    => 'integer',
        'output_qty'   => 'integer',
        'scrap_qty'    => 'integer',
        'unit_cost'    => 'decimal:2',
        'step_cost'    => 'decimal:2',
        'started_at'   => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class, 'production_order_id');
    }

    public function operation(): BelongsTo
    {
        return $this->belongsTo(Operation::class);
    }

    public function fasonSupplier(): BelongsTo
    {
        return $this->belongsTo(FasonSupplier::class);
    }
}
```

- [ ] **Step 4: Commit**

```bash
git add Modules/Atelier/Models/
git commit -m "feat(atelier): ProductionOrder/Item/Step modelleri"
```

---

## Faz 5 — ProductionOrderService (durum geçişleri + BOM düşümü + maliyet)

### Task 5.1: `FinishedGoodsService` (TDD) — biten ürün Product stoğuna girer

**Files:**
- Create: `Modules/Atelier/Services/FinishedGoodsService.php`
- Test: `tests/Feature/Atelier/FinishedGoodsServiceTest.php`

- [ ] **Step 1: Failing test yaz**

`tests/Feature/Atelier/FinishedGoodsServiceTest.php`:

```php
<?php

namespace Tests\Feature\Atelier;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Atelier\Models\ProductionOrder;
use Modules\Atelier\Services\FinishedGoodsService;
use Modules\Product\Models\Category;
use Modules\Product\Models\Product;
use Modules\Product\Models\Stock;
use Modules\Product\Models\StockMovement;
use Modules\Product\Models\Warehouse;
use Tests\TestCase;

class FinishedGoodsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_completing_order_writes_stock_and_movement_per_variant(): void
    {
        $cat = Category::create(['name' => 'C', 'slug' => 'c-' . uniqid(), 'status' => 'active', 'sort_order' => 0]);
        $product = Product::create(['category_id' => $cat->id, 'name' => 'P', 'sku' => 'S-' . uniqid(), 'gender' => 'Unisex', 'price' => 10]);
        $variant = $product->variants()->create(['sku' => 'V-' . uniqid(), 'size' => '2', 'color_name' => 'Mavi', 'price' => 10, 'stock' => 0, 'sort_order' => 0]);
        $warehouse = Warehouse::create(['name' => 'Ana', 'code' => 'W-' . uniqid()]);

        $order = ProductionOrder::create([
            'code' => 'IE-' . uniqid(), 'product_id' => $product->id, 'warehouse_id' => $warehouse->id,
            'status' => ProductionOrder::STATUS_IN_PROGRESS, 'planned_qty' => 50,
        ]);
        $order->items()->create(['product_variant_id' => $variant->id, 'planned_qty' => 50, 'produced_qty' => 48]);

        app(FinishedGoodsService::class)->receiveIntoStock($order);

        $this->assertDatabaseHas('stocks', [
            'product_variant_id' => $variant->id, 'warehouse_id' => $warehouse->id, 'quantity' => 48,
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'product_variant_id' => $variant->id, 'warehouse_id' => $warehouse->id,
            'type' => StockMovement::TYPE_IN, 'quantity' => 48,
            'reference_type' => ProductionOrder::class, 'reference_id' => $order->id,
        ]);
        $this->assertSame(48, (int) $variant->fresh()->stock);
    }
}
```

- [ ] **Step 2: Test'i çalıştır, başarısız olduğunu gör**

Run: `php artisan test --filter=FinishedGoodsServiceTest`
Expected: FAIL — `FinishedGoodsService` not found.

- [ ] **Step 3: Servisi yaz**

`Modules/Atelier/Services/FinishedGoodsService.php`:

```php
<?php

namespace Modules\Atelier\Services;

use Illuminate\Support\Facades\DB;
use Modules\Atelier\Models\ProductionOrder;
use Modules\Atelier\Models\ProductionOrderItem;
use Modules\Product\Models\ProductVariant;
use Modules\Product\Models\Stock;
use Modules\Product\Models\StockMovement;

class FinishedGoodsService
{
    /**
     * İş emrinin biten ürünlerini (varyant bazında produced_qty) hedef depoya stok girişi yapar.
     * StockController@movement deseniyle birebir: Stock upsert + StockMovement + variant denormalize.
     */
    public function receiveIntoStock(ProductionOrder $order): void
    {
        DB::transaction(function () use ($order) {
            $order->loadMissing('items');

            foreach ($order->items as $item) {
                $qty = (int) $item->produced_qty;
                if ($qty <= 0) {
                    continue;
                }

                $this->addStock($order, $item, $qty);
            }
        });
    }

    private function addStock(ProductionOrder $order, ProductionOrderItem $item, int $qty): void
    {
        $stock = Stock::query()
            ->where('product_variant_id', $item->product_variant_id)
            ->where('warehouse_id', $order->warehouse_id)
            ->lockForUpdate()
            ->first();

        if (! $stock) {
            $stock = Stock::create([
                'product_variant_id' => $item->product_variant_id,
                'warehouse_id'       => $order->warehouse_id,
                'quantity'           => 0,
            ]);
        }

        $before = (int) $stock->quantity;
        $after = $before + $qty;
        $stock->update(['quantity' => $after]);

        StockMovement::create([
            'product_variant_id' => $item->product_variant_id,
            'warehouse_id'       => $order->warehouse_id,
            'type'               => StockMovement::TYPE_IN,
            'quantity'           => $qty,
            'before_quantity'    => $before,
            'after_quantity'     => $after,
            'reference_type'     => ProductionOrder::class,
            'reference_id'       => $order->id,
            'note'               => "Üretim emri {$order->code} girişi",
            'user_id'            => auth()->id(),
        ]);

        $variant = ProductVariant::find($item->product_variant_id);
        $variant?->update([
            'stock' => (int) Stock::where('product_variant_id', $variant->id)->sum('quantity'),
        ]);
    }
}
```

- [ ] **Step 4: Test'i çalıştır, geçtiğini gör**

Run: `php artisan test --filter=FinishedGoodsServiceTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add Modules/Atelier/Services/FinishedGoodsService.php tests/Feature/Atelier/FinishedGoodsServiceTest.php
git commit -m "feat(atelier): FinishedGoodsService (Product stok entegrasyonu) + testleri"
```

### Task 5.2: `ProductionOrderService` — plan() (BOM düşümü + malzeme maliyeti) (TDD)

**Files:**
- Create: `Modules/Atelier/Services/ProductionOrderService.php`
- Test: `tests/Feature/Atelier/ProductionOrderServiceTest.php`

- [ ] **Step 1: Failing test yaz**

`tests/Feature/Atelier/ProductionOrderServiceTest.php`:

```php
<?php

namespace Tests\Feature\Atelier;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Atelier\Models\Material;
use Modules\Atelier\Models\Operation;
use Modules\Atelier\Models\ProductBom;
use Modules\Atelier\Models\ProductionOrder;
use Modules\Atelier\Services\ProductionOrderService;
use Modules\Product\Models\Category;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductVariant;
use Modules\Product\Models\Warehouse;
use Tests\TestCase;

class ProductionOrderServiceTest extends TestCase
{
    use RefreshDatabase;

    private function scenario(): array
    {
        $cat = Category::create(['name' => 'C', 'slug' => 'c-' . uniqid(), 'status' => 'active', 'sort_order' => 0]);
        $product = Product::create(['category_id' => $cat->id, 'name' => 'P', 'sku' => 'S-' . uniqid(), 'gender' => 'Unisex', 'price' => 10]);
        $variant = $product->variants()->create(['sku' => 'V-' . uniqid(), 'size' => '2', 'color_name' => 'Mavi', 'price' => 10, 'stock' => 0, 'sort_order' => 0]);
        $warehouse = Warehouse::create(['name' => 'Ana', 'code' => 'W-' . uniqid()]);

        $kumas = Material::create(['code' => 'K-' . uniqid(), 'name' => 'Kumaş', 'type' => 'kumas', 'unit' => 'metre', 'unit_cost' => 10, 'current_stock' => 1000]);
        $bom = ProductBom::create(['product_id' => $product->id, 'name' => 'Std', 'is_active' => true]);
        $bom->lines()->create(['material_id' => $kumas->id, 'quantity_per_unit' => 1.0, 'waste_pct' => 0]);

        return compact('product', 'variant', 'warehouse', 'kumas');
    }

    private function draftOrder(array $s, int $qty = 100): ProductionOrder
    {
        $order = ProductionOrder::create([
            'code' => 'IE-' . uniqid(), 'product_id' => $s['product']->id, 'warehouse_id' => $s['warehouse']->id,
            'status' => ProductionOrder::STATUS_DRAFT, 'planned_qty' => $qty,
        ]);
        $order->items()->create(['product_variant_id' => $s['variant']->id, 'planned_qty' => $qty]);

        return $order;
    }

    public function test_plan_consumes_materials_and_sets_material_cost(): void
    {
        $s = $this->scenario();
        $order = $this->draftOrder($s, 100);

        app(ProductionOrderService::class)->plan($order);

        // 100 adet * 1m = 100m tüketim; stok 1000 -> 900
        $this->assertSame('900.000', $s['kumas']->fresh()->current_stock);
        $this->assertDatabaseHas('material_movements', [
            'material_id' => $s['kumas']->id, 'reason' => 'consume', 'production_order_id' => $order->id,
        ]);
        // malzeme maliyeti = 100m * 10 = 1000
        $this->assertSame('1000.00', $order->fresh()->material_cost);
        $this->assertSame(ProductionOrder::STATUS_PLANNED, $order->fresh()->status);
    }

    public function test_plan_fails_when_material_stock_insufficient(): void
    {
        $s = $this->scenario();
        $s['kumas']->update(['current_stock' => 50]);
        $order = $this->draftOrder($s, 100); // 100m gerek, 50m var

        $this->expectExceptionMessage('Yetersiz hammadde');

        app(ProductionOrderService::class)->plan($order);
    }

    public function test_complete_rolls_up_cost_and_receives_stock(): void
    {
        $s = $this->scenario();
        $order = $this->draftOrder($s, 100);
        app(ProductionOrderService::class)->plan($order);

        // bir fason adımı tamamlanmış gibi step ekle
        $op = Operation::create(['code' => 'dikim', 'name' => 'Dikim', 'default_location' => 'fason']);
        $order->steps()->create([
            'operation_id' => $op->id, 'sequence' => 1, 'location_type' => 'fason',
            'status' => 'done', 'input_qty' => 100, 'output_qty' => 98, 'unit_cost' => 2, 'step_cost' => 196,
        ]);
        $order->items()->first()->update(['produced_qty' => 98]);
        $order->update(['status' => ProductionOrder::STATUS_IN_PROGRESS]);

        app(ProductionOrderService::class)->complete($order->fresh());

        $fresh = $order->fresh();
        $this->assertSame(ProductionOrder::STATUS_COMPLETED, $fresh->status);
        $this->assertSame(98, $fresh->produced_qty);
        $this->assertSame('196.00', $fresh->fason_cost);
        // total = material 1000 + fason 196 + labor 0 = 1196; unit = 1196/98 = 12.20
        $this->assertSame('1196.00', $fresh->total_cost);
        $this->assertSame('12.20', $fresh->unit_cost);
        // stok girişi yapıldı
        $this->assertDatabaseHas('stocks', ['product_variant_id' => $s['variant']->id, 'quantity' => 98]);
    }
}
```

- [ ] **Step 2: Test'i çalıştır, başarısız olduğunu gör**

Run: `php artisan test --filter=ProductionOrderServiceTest`
Expected: FAIL — `ProductionOrderService` not found.

- [ ] **Step 3: Servisi yaz**

`Modules/Atelier/Services/ProductionOrderService.php`:

```php
<?php

namespace Modules\Atelier\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Atelier\Models\Material;
use Modules\Atelier\Models\MaterialMovement;
use Modules\Atelier\Models\ProductionOrder;

class ProductionOrderService
{
    public function __construct(
        private BomService $bom,
        private MaterialStockService $materialStock,
        private FinishedGoodsService $finishedGoods,
    ) {}

    /**
     * draft -> planned: BOM gereksinimini hesaplar, hammadde stoğunu düşer, malzeme maliyetini yazar.
     */
    public function plan(ProductionOrder $order): ProductionOrder
    {
        if ($order->status !== ProductionOrder::STATUS_DRAFT) {
            throw new InvalidArgumentException('Yalnızca taslak iş emri planlanabilir.');
        }

        return DB::transaction(function () use ($order) {
            $order->loadMissing('product');
            $requirements = $this->bom->requirementsFor($order->product, (float) $order->planned_qty);

            // Önce stok yeterliliğini kontrol et (tek tek düşmeden).
            foreach ($requirements as $req) {
                $material = Material::lockForUpdate()->find($req['material_id']);
                if (! $material || (float) $material->current_stock < $req['required_qty']) {
                    throw new InvalidArgumentException(
                        "Yetersiz hammadde: {$req['material_name']} (gerekli {$req['required_qty']}, mevcut " .
                        ($material ? $material->current_stock : 0) . ')'
                    );
                }
            }

            $materialCost = 0.0;
            foreach ($requirements as $req) {
                $material = Material::find($req['material_id']);
                $this->materialStock->record(
                    $material,
                    MaterialMovement::TYPE_OUT,
                    $req['required_qty'],
                    'consume',
                    ['production_order_id' => $order->id, 'note' => "Üretim emri {$order->code} tüketimi"],
                );
                $materialCost += $req['line_cost'];
            }

            $order->update([
                'status'        => ProductionOrder::STATUS_PLANNED,
                'material_cost' => $materialCost,
            ]);

            return $order;
        });
    }

    /**
     * -> completed: adım maliyetlerini toplar, biten ürünü stoğa alır, birim maliyeti hesaplar.
     */
    public function complete(ProductionOrder $order): ProductionOrder
    {
        if (! in_array($order->status, [ProductionOrder::STATUS_PLANNED, ProductionOrder::STATUS_IN_PROGRESS], true)) {
            throw new InvalidArgumentException('Bu durumdaki iş emri tamamlanamaz.');
        }

        return DB::transaction(function () use ($order) {
            $order->loadMissing('steps', 'items');

            $fasonCost = (float) $order->steps->where('location_type', 'fason')->sum('step_cost');
            $laborCost = (float) $order->steps->where('location_type', 'in_house')->sum('step_cost');
            $producedQty = (int) $order->items->sum('produced_qty');

            $totalCost = (float) $order->material_cost + $fasonCost + $laborCost;
            $unitCost = $producedQty > 0 ? $totalCost / $producedQty : 0;

            $order->update([
                'fason_cost'   => $fasonCost,
                'labor_cost'   => $laborCost,
                'produced_qty' => $producedQty,
                'total_cost'   => $totalCost,
                'unit_cost'    => round($unitCost, 2),
            ]);

            $this->finishedGoods->receiveIntoStock($order);

            $order->update(['status' => ProductionOrder::STATUS_COMPLETED]);

            return $order;
        });
    }
}
```

- [ ] **Step 4: Test'i çalıştır, geçtiğini gör**

Run: `php artisan test --filter=ProductionOrderServiceTest`
Expected: PASS (3 test).

- [ ] **Step 5: Commit**

```bash
git add Modules/Atelier/Services/ProductionOrderService.php tests/Feature/Atelier/ProductionOrderServiceTest.php
git commit -m "feat(atelier): ProductionOrderService (plan/complete + maliyet roll-up) + testleri"
```

---

## Faz 6 — İş emri controller + UI

### Task 6.1: `ProductionOrderController` (TDD) + route'lar

**Files:**
- Create: `Modules/Atelier/Http/Controllers/ProductionOrderController.php`
- Modify: `Modules/Atelier/routes/web.php`
- Test: `tests/Feature/Atelier/ProductionOrderControllerTest.php`

- [ ] **Step 1: Failing test yaz**

`tests/Feature/Atelier/ProductionOrderControllerTest.php`:

```php
<?php

namespace Tests\Feature\Atelier;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Atelier\Models\Material;
use Modules\Atelier\Models\Operation;
use Modules\Atelier\Models\ProductBom;
use Modules\Atelier\Models\ProductionOrder;
use Modules\Product\Models\Category;
use Modules\Product\Models\Product;
use Modules\Product\Models\Warehouse;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProductionOrderControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $perm = Permission::firstOrCreate(['name' => 'atelier.manage', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);
        $role->givePermissionTo($perm);
        $this->admin = User::factory()->create();
        $this->admin->assignRole($role);
    }

    private function scenario(): array
    {
        $cat = Category::create(['name' => 'C', 'slug' => 'c-' . uniqid(), 'status' => 'active', 'sort_order' => 0]);
        $product = Product::create(['category_id' => $cat->id, 'name' => 'P', 'sku' => 'S-' . uniqid(), 'gender' => 'Unisex', 'price' => 10]);
        $variant = $product->variants()->create(['sku' => 'V-' . uniqid(), 'size' => '2', 'color_name' => 'Mavi', 'price' => 10, 'stock' => 0, 'sort_order' => 0]);
        $warehouse = Warehouse::create(['name' => 'Ana', 'code' => 'W-' . uniqid()]);
        $kumas = Material::create(['code' => 'K-' . uniqid(), 'name' => 'Kumaş', 'type' => 'kumas', 'unit' => 'metre', 'unit_cost' => 10, 'current_stock' => 1000]);
        $bom = ProductBom::create(['product_id' => $product->id, 'name' => 'Std', 'is_active' => true]);
        $bom->lines()->create(['material_id' => $kumas->id, 'quantity_per_unit' => 1.0, 'waste_pct' => 0]);
        $op = Operation::create(['code' => 'dikim', 'name' => 'Dikim', 'default_location' => 'fason']);

        return compact('product', 'variant', 'warehouse', 'op');
    }

    public function test_store_creates_order_with_items_and_steps(): void
    {
        $s = $this->scenario();

        $this->actingAs($this->admin)
            ->post('/atelier/production-orders', [
                'product_id'   => $s['product']->id,
                'warehouse_id' => $s['warehouse']->id,
                'planned_qty'  => 100,
                'items'        => [['product_variant_id' => $s['variant']->id, 'planned_qty' => 100]],
                'steps'        => [['operation_id' => $s['op']->id, 'sequence' => 1, 'location_type' => 'fason', 'unit_cost' => 2]],
            ])
            ->assertRedirect();

        $order = ProductionOrder::first();
        $this->assertNotNull($order);
        $this->assertDatabaseHas('production_order_items', ['production_order_id' => $order->id, 'planned_qty' => 100]);
        $this->assertDatabaseHas('production_order_steps', ['production_order_id' => $order->id, 'operation_id' => $s['op']->id]);
    }

    public function test_plan_action_consumes_materials(): void
    {
        $s = $this->scenario();
        $order = ProductionOrder::create([
            'code' => 'IE-' . uniqid(), 'product_id' => $s['product']->id, 'warehouse_id' => $s['warehouse']->id,
            'status' => ProductionOrder::STATUS_DRAFT, 'planned_qty' => 10,
        ]);
        $order->items()->create(['product_variant_id' => $s['variant']->id, 'planned_qty' => 10]);

        $this->actingAs($this->admin)
            ->post("/atelier/production-orders/{$order->id}/plan")
            ->assertRedirect();

        $this->assertSame(ProductionOrder::STATUS_PLANNED, $order->fresh()->status);
    }
}
```

- [ ] **Step 2: Test'i çalıştır, başarısız olduğunu gör**

Run: `php artisan test --filter=ProductionOrderControllerTest`
Expected: FAIL — route yok.

- [ ] **Step 3: Controller'ı yaz**

`Modules/Atelier/Http/Controllers/ProductionOrderController.php`:

```php
<?php

namespace Modules\Atelier\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Atelier\Models\Operation;
use Modules\Atelier\Models\ProductionOrder;
use Modules\Atelier\Models\ProductionOrderStep;
use Modules\Atelier\Services\BomService;
use Modules\Atelier\Services\ProductionOrderService;
use Modules\Product\Models\Product;
use Modules\Product\Models\Warehouse;

class ProductionOrderController extends Controller
{
    public function __construct(
        private ProductionOrderService $service,
        private BomService $bom,
    ) {}

    public function index(): Response
    {
        $orders = ProductionOrder::query()
            ->with(['product:id,name'])
            ->orderByDesc('id')
            ->get()
            ->map(fn (ProductionOrder $o) => [
                'id'          => $o->id,
                'code'        => $o->code,
                'productName' => $o->product?->name,
                'status'      => $o->status,
                'plannedQty'  => $o->planned_qty,
                'producedQty' => $o->produced_qty,
                'dueDate'     => optional($o->due_date)->format('Y-m-d'),
                'totalCost'   => (float) $o->total_cost,
            ]);

        return Inertia::render('Atelier::ProductionOrders', [
            'orders'     => $orders,
            'products'   => Product::query()->orderBy('name')->get(['id', 'name', 'sku']),
            'warehouses' => Warehouse::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']),
            'operations' => Operation::query()->orderBy('sort_order')->get(['id', 'name', 'code', 'default_location', 'default_unit_cost']),
        ]);
    }

    /** Sihirbaz için: seçilen ürünün varyantları + BOM gereksinim önizlemesi. */
    public function planPreview(Request $request): array
    {
        $product = Product::with('variants:id,product_id,size,color_name,sku')->findOrFail($request->integer('product_id'));
        $qty = max(0, $request->integer('planned_qty'));

        return [
            'variants'     => $product->variants->map(fn ($v) => [
                'id' => $v->id, 'size' => $v->size, 'colorName' => $v->color_name, 'sku' => $v->sku,
            ]),
            'requirements' => $this->bom->requirementsFor($product, (float) $qty),
        ];
    }

    public function show(ProductionOrder $productionOrder): Response
    {
        $productionOrder->load([
            'product:id,name,sku', 'warehouse:id,name,code',
            'items.variant:id,size,color_name,sku',
            'steps.operation:id,name', 'steps.fasonSupplier:id,name',
        ]);

        return Inertia::render('Atelier::ProductionOrderDetail', [
            'order' => [
                'id'           => $productionOrder->id,
                'code'         => $productionOrder->code,
                'productName'  => $productionOrder->product?->name,
                'warehouse'    => $productionOrder->warehouse?->name,
                'status'       => $productionOrder->status,
                'plannedQty'   => $productionOrder->planned_qty,
                'producedQty'  => $productionOrder->produced_qty,
                'materialCost' => (float) $productionOrder->material_cost,
                'fasonCost'    => (float) $productionOrder->fason_cost,
                'laborCost'    => (float) $productionOrder->labor_cost,
                'totalCost'    => (float) $productionOrder->total_cost,
                'unitCost'     => (float) $productionOrder->unit_cost,
                'items'        => $productionOrder->items->map(fn ($i) => [
                    'id' => $i->id, 'size' => $i->variant?->size, 'colorName' => $i->variant?->color_name,
                    'plannedQty' => $i->planned_qty, 'producedQty' => $i->produced_qty, 'scrapQty' => $i->scrap_qty,
                ]),
                'steps'        => $productionOrder->steps->map(fn (ProductionOrderStep $st) => [
                    'id' => $st->id, 'operationName' => $st->operation?->name, 'sequence' => $st->sequence,
                    'locationType' => $st->location_type, 'fasonSupplier' => $st->fasonSupplier?->name,
                    'status' => $st->status, 'inputQty' => $st->input_qty, 'outputQty' => $st->output_qty,
                    'scrapQty' => $st->scrap_qty, 'unitCost' => (float) $st->unit_cost, 'stepCost' => (float) $st->step_cost,
                ]),
            ],
            'fasonSuppliers' => \Modules\Atelier\Models\FasonSupplier::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'product_id'              => ['required', 'integer', Rule::exists('products', 'id')],
            'warehouse_id'            => ['required', 'integer', Rule::exists('warehouses', 'id')],
            'planned_qty'             => ['required', 'integer', 'min:1'],
            'due_date'                => ['nullable', 'date'],
            'notes'                   => ['nullable', 'string', 'max:2000'],
            'items'                   => ['required', 'array', 'min:1'],
            'items.*.product_variant_id' => ['required', 'integer', Rule::exists('product_variants', 'id')],
            'items.*.planned_qty'     => ['required', 'integer', 'min:0'],
            'steps'                   => ['required', 'array', 'min:1'],
            'steps.*.operation_id'    => ['required', 'integer', Rule::exists('operations', 'id')],
            'steps.*.sequence'        => ['required', 'integer', 'min:1'],
            'steps.*.location_type'   => ['required', Rule::in(['in_house', 'fason'])],
            'steps.*.fason_supplier_id' => ['nullable', 'integer', Rule::exists('fason_suppliers', 'id')],
            'steps.*.unit_cost'       => ['nullable', 'numeric', 'min:0'],
        ]);

        DB::transaction(function () use ($data, $request) {
            $order = ProductionOrder::create([
                'code'         => 'IE-' . strtoupper(Str::random(8)),
                'product_id'   => $data['product_id'],
                'warehouse_id' => $data['warehouse_id'],
                'status'       => ProductionOrder::STATUS_DRAFT,
                'planned_qty'  => $data['planned_qty'],
                'due_date'     => $data['due_date'] ?? null,
                'notes'        => $data['notes'] ?? null,
                'created_by'   => $request->user()?->id,
            ]);

            foreach ($data['items'] as $item) {
                $order->items()->create([
                    'product_variant_id' => $item['product_variant_id'],
                    'planned_qty'        => $item['planned_qty'],
                ]);
            }

            foreach ($data['steps'] as $step) {
                $order->steps()->create([
                    'operation_id'      => $step['operation_id'],
                    'sequence'          => $step['sequence'],
                    'location_type'     => $step['location_type'],
                    'fason_supplier_id' => $step['fason_supplier_id'] ?? null,
                    'unit_cost'         => $step['unit_cost'] ?? 0,
                    'status'            => ProductionOrderStep::STATUS_PENDING,
                ]);
            }
        });

        return redirect()->route('atelier.production-orders.index')->with('success', 'İş emri oluşturuldu.');
    }

    public function plan(ProductionOrder $productionOrder): RedirectResponse
    {
        try {
            $this->service->plan($productionOrder);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['plan' => $e->getMessage()]);
        }

        return back()->with('success', 'İş emri planlandı, hammadde düşüldü.');
    }

    public function complete(ProductionOrder $productionOrder): RedirectResponse
    {
        try {
            $this->service->complete($productionOrder);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['complete' => $e->getMessage()]);
        }

        return back()->with('success', 'İş emri tamamlandı, ürünler stoğa alındı.');
    }

    /** Adım durumu/miktar/fason güncelle. */
    public function updateStep(Request $request, ProductionOrder $productionOrder, ProductionOrderStep $step): RedirectResponse
    {
        abort_unless($step->production_order_id === $productionOrder->id, 404);

        $data = $request->validate([
            'status'            => ['required', Rule::in(['pending', 'in_progress', 'done'])],
            'input_qty'         => ['nullable', 'integer', 'min:0'],
            'output_qty'        => ['nullable', 'integer', 'min:0'],
            'scrap_qty'         => ['nullable', 'integer', 'min:0'],
            'fason_supplier_id' => ['nullable', 'integer', Rule::exists('fason_suppliers', 'id')],
            'unit_cost'         => ['nullable', 'numeric', 'min:0'],
            'note'              => ['nullable', 'string', 'max:1000'],
        ]);

        $output = (int) ($data['output_qty'] ?? 0);
        $unitCost = (float) ($data['unit_cost'] ?? $step->unit_cost);

        $step->update([
            'status'            => $data['status'],
            'input_qty'         => $data['input_qty'] ?? $step->input_qty,
            'output_qty'        => $output,
            'scrap_qty'         => $data['scrap_qty'] ?? $step->scrap_qty,
            'fason_supplier_id' => $data['fason_supplier_id'] ?? $step->fason_supplier_id,
            'unit_cost'         => $unitCost,
            'step_cost'         => $output * $unitCost,
            'started_at'        => $data['status'] !== 'pending' ? ($step->started_at ?? now()) : null,
            'completed_at'      => $data['status'] === 'done' ? now() : null,
            'note'              => $data['note'] ?? $step->note,
        ]);

        if ($productionOrder->status === ProductionOrder::STATUS_PLANNED && $data['status'] !== 'pending') {
            $productionOrder->update(['status' => ProductionOrder::STATUS_IN_PROGRESS]);
        }

        return back()->with('success', 'Adım güncellendi.');
    }

    /** Varyant üretilen/fire miktarı güncelle. */
    public function updateItem(Request $request, ProductionOrder $productionOrder): RedirectResponse
    {
        $data = $request->validate([
            'items'                 => ['required', 'array', 'min:1'],
            'items.*.id'            => ['required', 'integer'],
            'items.*.produced_qty'  => ['required', 'integer', 'min:0'],
            'items.*.scrap_qty'     => ['nullable', 'integer', 'min:0'],
        ]);

        foreach ($data['items'] as $row) {
            $productionOrder->items()->where('id', $row['id'])->update([
                'produced_qty' => $row['produced_qty'],
                'scrap_qty'    => $row['scrap_qty'] ?? 0,
            ]);
        }

        return back()->with('success', 'Üretim miktarları güncellendi.');
    }

    public function cancel(ProductionOrder $productionOrder): RedirectResponse
    {
        $productionOrder->update(['status' => ProductionOrder::STATUS_CANCELLED]);

        return back()->with('success', 'İş emri iptal edildi.');
    }
}
```

- [ ] **Step 4: Route'ları ekle**

`Modules/Atelier/routes/web.php` grubuna ekle:

```php
        // İş emirleri
        Route::get('production-orders', [\Modules\Atelier\Http\Controllers\ProductionOrderController::class, 'index'])->name('production-orders.index');
        Route::get('production-orders/plan-preview', [\Modules\Atelier\Http\Controllers\ProductionOrderController::class, 'planPreview'])->name('production-orders.plan-preview');
        Route::get('production-orders/{productionOrder}', [\Modules\Atelier\Http\Controllers\ProductionOrderController::class, 'show'])->name('production-orders.show');
        Route::post('production-orders', [\Modules\Atelier\Http\Controllers\ProductionOrderController::class, 'store'])->name('production-orders.store');
        Route::post('production-orders/{productionOrder}/plan', [\Modules\Atelier\Http\Controllers\ProductionOrderController::class, 'plan'])->name('production-orders.plan');
        Route::post('production-orders/{productionOrder}/complete', [\Modules\Atelier\Http\Controllers\ProductionOrderController::class, 'complete'])->name('production-orders.complete');
        Route::post('production-orders/{productionOrder}/cancel', [\Modules\Atelier\Http\Controllers\ProductionOrderController::class, 'cancel'])->name('production-orders.cancel');
        Route::put('production-orders/{productionOrder}/items', [\Modules\Atelier\Http\Controllers\ProductionOrderController::class, 'updateItem'])->name('production-orders.items.update');
        Route::put('production-orders/{productionOrder}/steps/{step}', [\Modules\Atelier\Http\Controllers\ProductionOrderController::class, 'updateStep'])->name('production-orders.steps.update');
```

> **Not:** `plan-preview` ve `show` route sırasına dikkat — `plan-preview` `{productionOrder}`'dan ÖNCE tanımlanmalı (aksi halde "plan-preview" id sanılır).

- [ ] **Step 5: Test'i çalıştır, geçtiğini gör**

Run: `php artisan test --filter=ProductionOrderControllerTest`
Expected: PASS (2 test).

- [ ] **Step 6: Commit**

```bash
git add Modules/Atelier/Http/Controllers/ProductionOrderController.php Modules/Atelier/routes/web.php tests/Feature/Atelier/ProductionOrderControllerTest.php
git commit -m "feat(atelier): ProductionOrderController (sihirbaz + plan/complete/adim) + testleri"
```

### Task 6.2: İş Emirleri liste + sihirbaz sayfası (Vue)

**Files:**
- Create: `Modules/Atelier/Resources/assets/js/Pages/ProductionOrders.vue`

- [ ] **Step 1: Sayfayı yaz**

`Modules/Atelier/Resources/assets/js/Pages/ProductionOrders.vue`:

```vue
<script setup>
import { ref } from 'vue'
import { useForm, router } from '@inertiajs/vue3'
import axios from 'axios'

const props = defineProps({ orders: Array, products: Array, warehouses: Array, operations: Array })

const STATUS = {
  draft: 'Taslak', planned: 'Planlandı', in_progress: 'Üretimde', completed: 'Tamamlandı', cancelled: 'İptal',
}

const showWizard = ref(false)
const variants = ref([])
const requirements = ref([])

const form = useForm({
  product_id: '', warehouse_id: '', planned_qty: 0, due_date: '', notes: '',
  items: [], steps: [],
})

async function onProductOrQty() {
  if (!form.product_id || !form.planned_qty) return
  const { data } = await axios.get('/atelier/production-orders/plan-preview', {
    params: { product_id: form.product_id, planned_qty: form.planned_qty },
  })
  variants.value = data.variants
  requirements.value = data.requirements
  // varyant satırlarını eşit dağıtma yapmadan 0 ile başlat
  form.items = data.variants.map(v => ({ product_variant_id: v.id, planned_qty: 0, label: `${v.size} / ${v.colorName}` }))
}

function addStep() {
  form.steps.push({ operation_id: '', sequence: form.steps.length + 1, location_type: 'in_house', fason_supplier_id: null, unit_cost: 0 })
}
function removeStep(i) { form.steps.splice(i, 1); form.steps.forEach((s, idx) => s.sequence = idx + 1) }

function onOperationChange(step) {
  const op = props.operations.find(o => o.id === Number(step.operation_id))
  if (op) { step.location_type = op.default_location; step.unit_cost = op.default_unit_cost }
}

function submit() {
  form.transform(d => ({
    ...d,
    items: d.items.map(({ product_variant_id, planned_qty }) => ({ product_variant_id, planned_qty })),
  })).post('/atelier/production-orders', {
    onSuccess: () => { showWizard.value = false; form.reset(); variants.value = []; requirements.value = [] },
  })
}
</script>

<template>
  <div class="p-6 space-y-6">
    <div class="flex justify-between items-center">
      <h1 class="text-xl font-semibold">İş Emirleri</h1>
      <button @click="showWizard = true" class="bg-indigo-600 text-white px-4 py-1.5 rounded">+ Yeni İş Emri</button>
    </div>

    <table class="w-full text-sm bg-white rounded shadow-sm">
      <thead class="text-left border-b"><tr><th class="p-2">Kod</th><th class="p-2">Ürün</th><th class="p-2">Durum</th><th class="p-2 text-right">Planlanan</th><th class="p-2 text-right">Üretilen</th><th class="p-2">Termin</th><th class="p-2 text-right">Maliyet</th><th class="p-2"></th></tr></thead>
      <tbody>
        <tr v-for="o in orders" :key="o.id" class="border-b">
          <td class="p-2">{{ o.code }}</td>
          <td class="p-2">{{ o.productName }}</td>
          <td class="p-2">{{ STATUS[o.status] }}</td>
          <td class="p-2 text-right">{{ o.plannedQty }}</td>
          <td class="p-2 text-right">{{ o.producedQty }}</td>
          <td class="p-2">{{ o.dueDate || '—' }}</td>
          <td class="p-2 text-right">{{ o.totalCost }}</td>
          <td class="p-2 text-right"><button @click="router.get(`/atelier/production-orders/${o.id}`)" class="text-indigo-600">Detay</button></td>
        </tr>
      </tbody>
    </table>

    <div v-if="showWizard" class="fixed inset-0 bg-black/40 flex items-start justify-center overflow-auto py-10" @click.self="showWizard = false">
      <form @submit.prevent="submit" class="bg-white p-6 rounded space-y-4 w-[900px]">
        <h2 class="font-semibold text-lg">Yeni İş Emri</h2>

        <div class="grid grid-cols-4 gap-3">
          <label class="flex flex-col text-sm">Ürün
            <select v-model="form.product_id" @change="onProductOrQty" class="border rounded px-2 py-1">
              <option value="">Seçin…</option>
              <option v-for="p in products" :key="p.id" :value="p.id">{{ p.name }}</option>
            </select>
          </label>
          <label class="flex flex-col text-sm">Adet<input v-model="form.planned_qty" @change="onProductOrQty" type="number" class="border rounded px-2 py-1" /></label>
          <label class="flex flex-col text-sm">Depo
            <select v-model="form.warehouse_id" class="border rounded px-2 py-1">
              <option value="">Seçin…</option>
              <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
            </select>
          </label>
          <label class="flex flex-col text-sm">Termin<input v-model="form.due_date" type="date" class="border rounded px-2 py-1" /></label>
        </div>

        <div v-if="form.items.length">
          <h3 class="font-medium text-sm mb-1">Varyant adetleri</h3>
          <div v-for="(it, i) in form.items" :key="i" class="flex gap-2 items-center text-sm mb-1">
            <span class="w-40">{{ it.label }}</span>
            <input v-model="it.planned_qty" type="number" class="border rounded px-2 py-1 w-28" />
          </div>
        </div>

        <div v-if="requirements.length" class="bg-amber-50 p-3 rounded text-sm">
          <h3 class="font-medium mb-1">Malzeme gereksinimi (önizleme)</h3>
          <div v-for="(r, i) in requirements" :key="i">{{ r.material_name }}: {{ r.required_qty }} {{ r.unit }} (≈{{ r.line_cost }} ₺)</div>
        </div>

        <div>
          <h3 class="font-medium text-sm mb-1">Rota</h3>
          <div v-for="(s, i) in form.steps" :key="i" class="flex gap-2 items-end text-sm mb-1">
            <span class="w-6">{{ s.sequence }}.</span>
            <select v-model="s.operation_id" @change="onOperationChange(s)" class="border rounded px-2 py-1">
              <option value="">Operasyon…</option>
              <option v-for="op in operations" :key="op.id" :value="op.id">{{ op.name }}</option>
            </select>
            <select v-model="s.location_type" class="border rounded px-2 py-1">
              <option value="in_house">İç</option><option value="fason">Fason</option>
            </select>
            <input v-model="s.unit_cost" type="number" step="0.01" class="border rounded px-2 py-1 w-24" placeholder="Birim ₺" />
            <button type="button" @click="removeStep(i)" class="text-red-600">Sil</button>
          </div>
          <button type="button" @click="addStep" class="px-3 py-1 rounded border text-sm">+ Adım</button>
        </div>

        <div class="flex gap-2">
          <button class="bg-indigo-600 text-white px-4 py-1.5 rounded" :disabled="form.processing">Oluştur</button>
          <button type="button" @click="showWizard = false" class="px-4 py-1.5 rounded border">Kapat</button>
        </div>
      </form>
    </div>
  </div>
</template>
```

- [ ] **Step 2: Build kontrol**

Run: `npm run build`
Expected: hata yok (axios mevcut bağımlılık; değilse `import { router } from '@inertiajs/vue3'` ile fetch kullan — ama projede axios global kayıtlı, `resources/js/bootstrap.js` kontrol et).

- [ ] **Step 3: Commit**

```bash
git add Modules/Atelier/Resources/assets/js/Pages/ProductionOrders.vue
git commit -m "feat(atelier): Is Emirleri liste + olusturma sihirbazi"
```

### Task 6.3: İş Emri Detay sayfası (Vue)

**Files:**
- Create: `Modules/Atelier/Resources/assets/js/Pages/ProductionOrderDetail.vue`

- [ ] **Step 1: Sayfayı yaz**

`Modules/Atelier/Resources/assets/js/Pages/ProductionOrderDetail.vue`:

```vue
<script setup>
import { reactive } from 'vue'
import { router } from '@inertiajs/vue3'

const props = defineProps({ order: Object, fasonSuppliers: Array })

const STATUS = { draft: 'Taslak', planned: 'Planlandı', in_progress: 'Üretimde', completed: 'Tamamlandı', cancelled: 'İptal' }
const STEP_STATUS = { pending: 'Bekliyor', in_progress: 'Devam', done: 'Bitti' }

const stepForms = reactive(Object.fromEntries(props.order.steps.map(s => [s.id, {
  status: s.status, input_qty: s.inputQty, output_qty: s.outputQty, scrap_qty: s.scrapQty,
  fason_supplier_id: null, unit_cost: s.unitCost, note: '',
}])))

const itemForms = reactive(Object.fromEntries(props.order.items.map(i => [i.id, {
  produced_qty: i.producedQty, scrap_qty: i.scrapQty,
}])))

function saveStep(id) {
  router.put(`/atelier/production-orders/${props.order.id}/steps/${id}`, stepForms[id], { preserveScroll: true })
}
function saveItems() {
  const items = props.order.items.map(i => ({ id: i.id, produced_qty: itemForms[i.id].produced_qty, scrap_qty: itemForms[i.id].scrap_qty }))
  router.put(`/atelier/production-orders/${props.order.id}/items`, { items }, { preserveScroll: true })
}
function plan() { router.post(`/atelier/production-orders/${props.order.id}/plan`, {}, { preserveScroll: true }) }
function complete() { router.post(`/atelier/production-orders/${props.order.id}/complete`, {}, { preserveScroll: true }) }
function cancel() { if (confirm('İptal edilsin mi?')) router.post(`/atelier/production-orders/${props.order.id}/cancel`, {}, { preserveScroll: true }) }
</script>

<template>
  <div class="p-6 space-y-6">
    <div class="flex justify-between items-center">
      <div>
        <h1 class="text-xl font-semibold">{{ order.code }} — {{ order.productName }}</h1>
        <p class="text-sm text-gray-500">Durum: {{ STATUS[order.status] }} • Depo: {{ order.warehouse }}</p>
      </div>
      <div class="flex gap-2">
        <button v-if="order.status === 'draft'" @click="plan" class="bg-amber-600 text-white px-4 py-1.5 rounded">Planla (hammadde düş)</button>
        <button v-if="['planned','in_progress'].includes(order.status)" @click="complete" class="bg-emerald-600 text-white px-4 py-1.5 rounded">Tamamla → Stoğa al</button>
        <button v-if="!['completed','cancelled'].includes(order.status)" @click="cancel" class="px-4 py-1.5 rounded border text-red-600">İptal</button>
      </div>
    </div>

    <div class="grid grid-cols-5 gap-3 text-sm">
      <div class="bg-white p-3 rounded shadow-sm">Malzeme<br><b>{{ order.materialCost }} ₺</b></div>
      <div class="bg-white p-3 rounded shadow-sm">Fason<br><b>{{ order.fasonCost }} ₺</b></div>
      <div class="bg-white p-3 rounded shadow-sm">İşçilik<br><b>{{ order.laborCost }} ₺</b></div>
      <div class="bg-white p-3 rounded shadow-sm">Toplam<br><b>{{ order.totalCost }} ₺</b></div>
      <div class="bg-white p-3 rounded shadow-sm">Birim<br><b>{{ order.unitCost }} ₺</b></div>
    </div>

    <div class="bg-white p-4 rounded shadow-sm">
      <h2 class="font-medium mb-2">Varyant üretim</h2>
      <div v-for="i in order.items" :key="i.id" class="flex gap-3 items-center text-sm mb-1">
        <span class="w-40">{{ i.size }} / {{ i.colorName }}</span>
        <span class="text-gray-500">Plan: {{ i.plannedQty }}</span>
        <label>Üretilen <input v-model="itemForms[i.id].produced_qty" type="number" class="border rounded px-2 py-1 w-24" /></label>
        <label>Fire <input v-model="itemForms[i.id].scrap_qty" type="number" class="border rounded px-2 py-1 w-20" /></label>
      </div>
      <button @click="saveItems" class="mt-2 px-3 py-1 rounded border text-sm">Üretim miktarlarını kaydet</button>
    </div>

    <div class="bg-white p-4 rounded shadow-sm">
      <h2 class="font-medium mb-2">Rota / Aşamalar</h2>
      <div v-for="s in order.steps" :key="s.id" class="border-b py-2 text-sm flex flex-wrap gap-2 items-end">
        <span class="w-44">{{ s.sequence }}. {{ s.operationName }} ({{ s.locationType === 'fason' ? 'Fason' : 'İç' }})</span>
        <select v-model="stepForms[s.id].status" class="border rounded px-2 py-1">
          <option value="pending">Bekliyor</option><option value="in_progress">Devam</option><option value="done">Bitti</option>
        </select>
        <label>Giren <input v-model="stepForms[s.id].input_qty" type="number" class="border rounded px-2 py-1 w-20" /></label>
        <label>Çıkan <input v-model="stepForms[s.id].output_qty" type="number" class="border rounded px-2 py-1 w-20" /></label>
        <label>Fire <input v-model="stepForms[s.id].scrap_qty" type="number" class="border rounded px-2 py-1 w-16" /></label>
        <label>Birim ₺ <input v-model="stepForms[s.id].unit_cost" type="number" step="0.01" class="border rounded px-2 py-1 w-20" /></label>
        <select v-if="s.locationType === 'fason'" v-model="stepForms[s.id].fason_supplier_id" class="border rounded px-2 py-1">
          <option :value="null">Fasoncu…</option>
          <option v-for="f in fasonSuppliers" :key="f.id" :value="f.id">{{ f.name }}</option>
        </select>
        <button @click="saveStep(s.id)" class="px-3 py-1 rounded border">Kaydet</button>
      </div>
    </div>
  </div>
</template>
```

- [ ] **Step 2: Build kontrol**

Run: `npm run build`
Expected: hata yok.

- [ ] **Step 3: Commit**

```bash
git add Modules/Atelier/Resources/assets/js/Pages/ProductionOrderDetail.vue
git commit -m "feat(atelier): Is Emri Detay sayfasi (asama ilerletme + maliyet)"
```

---

## Faz 7 — Dashboard + navigasyon

### Task 7.1: `DashboardController` + dashboard verisi (TDD)

**Files:**
- Create: `Modules/Atelier/Http/Controllers/DashboardController.php`
- Modify: `Modules/Atelier/routes/web.php` (dashboard route'unu yeni controller'a yönlendir)
- Test: `tests/Feature/Atelier/DashboardTest.php`

- [ ] **Step 1: Failing test yaz**

`tests/Feature/Atelier/DashboardTest.php`:

```php
<?php

namespace Tests\Feature\Atelier;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_renders_for_admin(): void
    {
        $perm = Permission::firstOrCreate(['name' => 'atelier.manage', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);
        $role->givePermissionTo($perm);
        $admin = User::factory()->create();
        $admin->assignRole($role);

        $this->actingAs($admin)
            ->get('/atelier')
            ->assertOk();
    }

    public function test_dashboard_forbidden_without_permission(): void
    {
        $plain = User::factory()->create();

        $this->actingAs($plain)
            ->get('/atelier')
            ->assertForbidden();
    }
}
```

- [ ] **Step 2: Test'i çalıştır**

Run: `php artisan test --filter=DashboardTest`
Expected: `test_dashboard_renders_for_admin` muhtemelen PASS (stub mevcut), `forbidden` PASS. Yine de controller'ı zenginleştireceğiz.

- [ ] **Step 3: DashboardController yaz**

`Modules/Atelier/Http/Controllers/DashboardController.php`:

```php
<?php

namespace Modules\Atelier\Http\Controllers;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Atelier\Models\Material;
use Modules\Atelier\Models\ProductionOrder;
use Modules\Atelier\Models\ProductionOrderStep;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $active = ProductionOrder::query()
            ->whereIn('status', [ProductionOrder::STATUS_PLANNED, ProductionOrder::STATUS_IN_PROGRESS])
            ->with('product:id,name')
            ->orderBy('due_date')
            ->get()
            ->map(fn (ProductionOrder $o) => [
                'id' => $o->id, 'code' => $o->code, 'productName' => $o->product?->name,
                'status' => $o->status, 'dueDate' => optional($o->due_date)->format('Y-m-d'),
                'isLate' => $o->due_date && $o->due_date->isPast(),
            ]);

        $fasonPending = ProductionOrderStep::query()
            ->where('location_type', 'fason')
            ->whereIn('status', ['pending', 'in_progress'])
            ->whereHas('order', fn ($q) => $q->whereNotIn('status', ['completed', 'cancelled']))
            ->count();

        $lowStock = Material::query()
            ->where('is_active', true)
            ->where('current_stock', '<=', 0)
            ->count();

        return Inertia::render('Atelier::Dashboard', [
            'activeOrders' => $active,
            'fasonPending' => $fasonPending,
            'lowStock'     => $lowStock,
        ]);
    }
}
```

- [ ] **Step 4: Route'u güncelle**

`Modules/Atelier/routes/web.php` içinde dashboard satırını değiştir:

```php
        Route::get('/', [\Modules\Atelier\Http\Controllers\DashboardController::class, 'index'])->name('dashboard');
```

(Eski `AtelierController` import'unu kaldırabilirsin; başka kullanımı yoksa `AtelierController.php` dosyasını da sil.)

- [ ] **Step 5: Test'i çalıştır, geçtiğini gör**

Run: `php artisan test --filter=DashboardTest`
Expected: PASS (2 test).

- [ ] **Step 6: Commit**

```bash
git add Modules/Atelier/Http/Controllers/DashboardController.php Modules/Atelier/routes/web.php tests/Feature/Atelier/DashboardTest.php
git rm Modules/Atelier/Http/Controllers/AtelierController.php
git commit -m "feat(atelier): DashboardController + paneli"
```

### Task 7.2: AtelierNav + Dashboard sayfası (Vue)

**Files:**
- Create: `Modules/Atelier/Resources/assets/js/Components/AtelierNav.vue`
- Create: `Modules/Atelier/Resources/assets/js/Pages/Dashboard.vue`

- [ ] **Step 1: AtelierNav yaz**

`Modules/Atelier/Resources/assets/js/Components/AtelierNav.vue`:

```vue
<script setup>
import { Link, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

const tabs = [
  { label: 'Panel', href: '/atelier' },
  { label: 'İş Emirleri', href: '/atelier/production-orders' },
  { label: 'Hammaddeler', href: '/atelier/materials' },
  { label: 'Reçeteler', href: '/atelier/boms' },
  { label: 'Operasyonlar', href: '/atelier/operations' },
  { label: 'Fasoncular', href: '/atelier/fason-suppliers' },
]
const current = computed(() => usePage().url)
function active(href) {
  return href === '/atelier' ? current.value === '/atelier' : current.value.startsWith(href)
}
</script>

<template>
  <nav class="flex gap-1 border-b bg-white px-4">
    <Link v-for="t in tabs" :key="t.href" :href="t.href"
      class="px-3 py-2 text-sm border-b-2"
      :class="active(t.href) ? 'border-indigo-600 text-indigo-600 font-medium' : 'border-transparent text-gray-600 hover:text-gray-900'">
      {{ t.label }}
    </Link>
  </nav>
</template>
```

- [ ] **Step 2: Dashboard.vue yaz**

`Modules/Atelier/Resources/assets/js/Pages/Dashboard.vue`:

```vue
<script setup>
import { router } from '@inertiajs/vue3'

const props = defineProps({ activeOrders: { type: Array, default: () => [] }, fasonPending: { type: Number, default: 0 }, lowStock: { type: Number, default: 0 } })
const STATUS = { planned: 'Planlandı', in_progress: 'Üretimde' }
</script>

<template>
  <div class="p-6 space-y-6">
    <h1 class="text-xl font-semibold">Atölye Paneli</h1>

    <div class="grid grid-cols-3 gap-3 text-sm">
      <div class="bg-white p-4 rounded shadow-sm">Aktif iş emri<br><b class="text-2xl">{{ activeOrders.length }}</b></div>
      <div class="bg-white p-4 rounded shadow-sm">Fasonda bekleyen adım<br><b class="text-2xl">{{ fasonPending }}</b></div>
      <div class="bg-white p-4 rounded shadow-sm">Tükenen hammadde<br><b class="text-2xl text-red-600">{{ lowStock }}</b></div>
    </div>

    <div class="bg-white p-4 rounded shadow-sm">
      <h2 class="font-medium mb-2">Devam eden iş emirleri</h2>
      <table class="w-full text-sm">
        <thead class="text-left border-b"><tr><th class="p-2">Kod</th><th class="p-2">Ürün</th><th class="p-2">Durum</th><th class="p-2">Termin</th><th class="p-2"></th></tr></thead>
        <tbody>
          <tr v-for="o in activeOrders" :key="o.id" class="border-b" :class="o.isLate ? 'bg-red-50' : ''">
            <td class="p-2">{{ o.code }}</td><td class="p-2">{{ o.productName }}</td>
            <td class="p-2">{{ STATUS[o.status] }}</td>
            <td class="p-2">{{ o.dueDate || '—' }} <span v-if="o.isLate" class="text-red-600">(gecikti)</span></td>
            <td class="p-2 text-right"><button @click="router.get(`/atelier/production-orders/${o.id}`)" class="text-indigo-600">Detay</button></td>
          </tr>
          <tr v-if="!activeOrders.length"><td colspan="5" class="p-4 text-center text-gray-400">Aktif iş emri yok.</td></tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
```

- [ ] **Step 3: AtelierNav'ı tüm sayfalara ekle**

Her Atelier sayfasının (`Dashboard.vue`, `ProductionOrders.vue`, `ProductionOrderDetail.vue`, `Materials.vue`, `Boms.vue`, `Operations.vue`, `FasonSuppliers.vue`) `<template>` kök elemanının EN ÜSTÜNE ekle ve script'e import et:

```js
import AtelierNav from '../Components/AtelierNav.vue'
```

Template kökünü şu desene çevir (örnek Materials için):

```vue
<template>
  <div>
    <AtelierNav />
    <div class="p-6 space-y-6">
      <!-- mevcut içerik -->
    </div>
  </div>
</template>
```

(Her sayfada mevcut `<div class="p-6 space-y-6">`'yi `<div><AtelierNav /><div class="p-6 space-y-6">…</div></div>` ile sar.)

- [ ] **Step 4: Build kontrol**

Run: `npm run build`
Expected: hata yok.

- [ ] **Step 5: Commit**

```bash
git add Modules/Atelier/Resources/assets/js/
git commit -m "feat(atelier): AtelierNav + Panel sayfasi; tum sayfalara nav"
```

---

## Faz 8 — Doğrulama ve kapanış

### Task 8.1: Tüm Atelier testleri + schema:audit

**Files:** (yok — doğrulama)

- [ ] **Step 1: Tüm Atelier testlerini çalıştır**

Run: `php artisan test --filter=Atelier`
Expected: tüm testler PASS (MaterialStock, Bom, FinishedGoods, ProductionOrder servis + Material/Bom/OperationFason/ProductionOrder/Dashboard controller testleri).

- [ ] **Step 2: schema:audit çalıştır**

Run: `php artisan schema:audit`
Expected: yeni Atelier tabloları orphan olarak işaretlenmez (hepsi modeli olan tablolar). Çıktı `storage/app/schema-audit.json`. Orphan listesinde Atelier tablosu olmamalı.

- [ ] **Step 3: Migration down() doğrulaması (geri-alınabilirlik)**

Run: `php artisan migrate:rollback --path=Modules/Atelier/database/migrations --step=15 --pretend`
Expected: tüm Atelier migration'larının down() ifadeleri hatasız listelenir (gerçek rollback yapma — `--pretend`).

- [ ] **Step 4: Route listesi kontrol**

Run: `php artisan route:list --path=atelier`
Expected: dashboard, materials.*, boms.*, operations.*, fason-suppliers.*, production-orders.* route'ları görünür; hepsi `role:superadmin` + `can:atelier.manage` korumalı.

### Task 8.2: RBAC + sidebar/menü entegrasyonu kontrolü

**Files:**
- (varsa) Modify: sidebar menü kaydı

- [ ] **Step 1: RBAC seeder doğrula**

Run: `php artisan db:seed --class=RolePermissionSeeder`
Expected: `atelier.manage` izni superadmin'de mevcut (idempotent).

- [ ] **Step 2: Sidebar'a Atölye linki ekle (varsa menü sistemi)**

Mevcut uygulamada sidebar nasıl kuruluysa (Superadmin Menu veya statik) "Atölye" girişi `/atelier`'e eklenir. Önce kontrol et:

Run: `grep -rn "creative\|Creative" resources/js --include=*.vue -l | head`

Sonuca göre: Creative sidebar'da yok (sayfa-içi nav), Atelier de aynı deseni izler → ek sidebar değişikliği gerekmez. Eğer ana sidebar'da modül linkleri varsa oraya `/atelier` (izin: `atelier.manage`) eklenir.

- [ ] **Step 3: Manuel duman testi (opsiyonel, kullanıcıyla)**

`php artisan serve` + `npm run dev`; superadmin ile `/atelier` → panel; bir hammadde + reçete + operasyon + iş emri oluştur → planla → adım ilerlet → tamamla → Product Stoklar sayfasında girişi gör.

- [ ] **Step 4: Final commit (varsa değişiklik)**

```bash
git add -A
git commit -m "chore(atelier): RBAC + navigasyon dogrulamasi"
```

---

## Self-Review notları (plan yazarı tarafından doğrulandı)

- **Spec kapsamı karşılandı:** materials+movements (Faz 1), BOM (Faz 2), operations+fason (Faz 3), iş emri tablo/model (Faz 4), durum geçişleri+BOM düşümü+maliyet (Faz 5: ProductionOrderService), Product stok entegrasyonu (Faz 5: FinishedGoodsService), 7 UI sayfası + nav (Faz 1-7), test stratejisi (her serviste + controller'da Feature test), dxf kaldırma (Faz 0).
- **Tip tutarlılığı:** servis metod adları sabit — `MaterialStockService::record`, `BomService::requirementsFor`, `ProductionOrderService::plan`/`complete`, `FinishedGoodsService::receiveIntoStock`. Sabitler `ProductionOrder::STATUS_*`, `ProductionOrderStep::STATUS_*`, `MaterialMovement::TYPE_*` her yerde aynı.
- **CLAUDE.md:** her migration'da gerçek `down()`; `material_movements` Prunable; FK ekleme/silme ayrı migration ile geri-alınabilir; schema:audit Faz 8'de doğrulanır.
- **Decimal cast notu:** PostgreSQL'de decimal kolonlar string döner; testlerdeki `'900.000'` gibi string assertion'lar bu yüzden bilinçli. SQLite test ortamında da Laravel `decimal:N` cast'i string döndürür (tutarlı).
