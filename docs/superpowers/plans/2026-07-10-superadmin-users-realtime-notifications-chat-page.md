# Superadmin Kullanıcı Yönetimi + Anlık Bildirim + Chatbot Tam Sayfa Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Üç bağımsız iyileştirmeyi uygulamak: (A) Superadmin'in kullanıcı oluşturup rol/tenant
ataması yapabildiği bir ekran, (B) Creative onay bildirimlerinin sayfa yenilenmeden anlık
gelmesi (Reverb üzerinden), (C) reddedilen görseller için chatbot'un gömülü panel yerine
kullanışlı bir tam sayfada çalışması.

**Architecture:** A — klasik Inertia CRUD (Tenants.vue kalıbı). B — Laravel'in yerleşik
notification broadcasting'i (`ShouldBroadcast` + Echo private user kanalı); mevcut `database`
kanalına EK olarak çalışır, mevcut davranışı bozmaz. C — `ReviewChatController`'a GET `show*`
action'ları eklenip yeni bir Inertia sayfası (`ReviewChat.vue`) render edilir; mevcut POST
send/apply endpoint'leri DEĞİŞMEDEN kullanılır.

**Tech Stack:** Laravel 12, Inertia.js + Vue 3 (`<script setup>`), spatie/laravel-permission,
Laravel Reverb + laravel-echo/pusher-js (zaten kurulu), Pest/PHPUnit (`Tests\Feature\*`).

## Global Constraints

- Migration'lar forward-only; prod'a gitmiş migration DÜZENLENMEZ (bkz. proje CLAUDE.md).
- `users` tablosu **core/paylaşılan** tablo → migration `database/migrations/`'a gider (Modül
  dizinine DEĞİL).
- Yeni izinler mevcut seeder'ların **kısa-isim** geleneğine uyar (`users.view`, `users.manage`
  — `superadmin.user.*` gibi önekli DEĞİL, `SuperadminPermissionSeeder`'daki `logs.view`,
  `rbac.manage` ile aynı desen).
- Kullanıcı/rol yönetimi rotaları **hassas meta-yönetim** sayılır — `Modules/Superadmin/routes/web.php`'deki
  roller/izinler bloğuyla AYNI double-lock deseni (`can:*` + `role:superadmin`) kullanılır;
  başka rollere delege edilmez (privilege-escalation riski: `users.manage` sahibi olmayan bir
  rol, yeni kullanıcıya `superadmin` rolü atayabilir).
- Toast: `inject('showToast')`; Onay modalı: `inject('$swal')` → `.dangerConfirm()`/`.fire()`.
  Yeni composable/Pinia AÇMA — mevcut `reactive(emptyForm())` + `watch(formOpen)` deseni kullanılır.
- İmport'lar `@/` veya `@Modules/` alias'ından; relative import (`../../../`) yok — modül
  içi bileşenler zaten `../Components/...` kullanıyor (mevcut desen, dokunma).
- PHP testleri `Tests\Feature\<Alan>\...TestCase`, `RefreshDatabase`, sqlite `:memory:`
  (`phpunit.xml`). Inertia sayfa testlerinde `config(['inertia.testing.ensure_pages_exist' => false]);`
  şart (modül sayfaları test view-finder'ında çözülmez — bkz. mevcut `CreativeReviewWorkflowTest.php`).

---

## PHASE A — Superadmin Kullanıcı Yönetimi

### Task 1 (A1): `users` tablosuna phone/job_title migration'ı

**Files:**
- Create: `database/migrations/2026_07_10_160000_add_profile_columns_to_users_table.php`
- Test: manuel (`php artisan migrate`) — şema değişikliği, iş mantığı yok, ayrı test dosyası gerekmez.

**Interfaces:**
- Consumes: yok.
- Produces: `users.phone` (string, nullable), `users.job_title` (string, nullable) — Task A3'te
  `UserController` bunları okur/yazar. `App\Models\User::$fillable` zaten bu iki alanı içeriyor
  (kontrol edildi), model değişikliği GEREKMEZ.

- [ ] **Step 1: Migration dosyasını yaz**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'phone')) {
                $table->string('phone', 30)->nullable()->after('email');
            }
            if (! Schema::hasColumn('users', 'job_title')) {
                $table->string('job_title', 100)->nullable()->after('phone');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'job_title')) {
                $table->dropColumn('job_title');
            }
            if (Schema::hasColumn('users', 'phone')) {
                $table->dropColumn('phone');
            }
        });
    }
};
```

- [ ] **Step 2: Migration'ı çalıştır ve doğrula**

Run: `php artisan migrate`
Expected: `2026_07_10_160000_add_profile_columns_to_users_table ... DONE` çıktısı.

Run: `php artisan tinker --execute="print_r(Illuminate\Support\Facades\Schema::getColumnListing('users'));"`
Expected: çıktıda `phone` ve `job_title` görünür.

- [ ] **Step 3: Commit**

```bash
git add database/migrations/2026_07_10_160000_add_profile_columns_to_users_table.php
git commit -m "feat(superadmin): users tablosuna phone/job_title kolonları ekle"
```

---

### Task 2 (A2): `users.view`/`users.manage` izinlerini ekle

**Files:**
- Modify: `Modules/Superadmin/database/seeders/SuperadminPermissionSeeder.php`
- Test: Create `tests/Feature/Superadmin/UserManagementTest.php` (bu task sadece izin
  seed'ini doğrulayan ilk testi ekler; Task A3'te aynı dosyaya CRUD testleri eklenecek).

**Interfaces:**
- Consumes: `SuperadminPermissionSeeder` mevcut `$permissions` dizisi (satır 13-18).
- Produces: DB'de `users.view`/`users.manage` Permission kayıtları, `superadmin` rolüne atanmış.

- [ ] **Step 1: Seeder dizisine izinleri ekle**

`Modules/Superadmin/database/seeders/SuperadminPermissionSeeder.php` satır 13-18'i değiştir:

```php
        $permissions = [
            'logs.view'       => 'Log Görüntüleme',
            'settings.manage' => 'Sistem Ayarları Yönet',
            'menu.manage'     => 'Menü Yönet',
            'rbac.manage'     => 'Rol & İzin Yönet',
            'users.view'      => 'Kullanıcıları Görüntüle',
            'users.manage'    => 'Kullanıcı Yönet',
        ];
```

- [ ] **Step 2: Seeder'ı çalıştır ve doğrula**

Run: `php artisan db:seed --class="Modules\Superadmin\Database\Seeders\SuperadminPermissionSeeder"`
Expected: hatasız biter.

Run: `php artisan tinker --execute="echo Spatie\Permission\Models\Role::where('name','superadmin')->first()->hasPermissionTo('users.manage') ? 'YES' : 'NO';"`
Expected: `YES`

- [ ] **Step 3: Failing test yaz (izin var mı)**

Create `tests/Feature/Superadmin/UserManagementTest.php`:

```php
<?php

namespace Tests\Feature\Superadmin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['inertia.testing.ensure_pages_exist' => false]);
        Permission::firstOrCreate(['name' => 'users.view', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'users.manage', 'guard_name' => 'web']);
    }

    private function superadmin(): User
    {
        $role = Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);
        $admin = User::factory()->create();
        $admin->assignRole($role);
        $admin->givePermissionTo('users.view', 'users.manage');

        return $admin;
    }

    public function test_plain_user_is_forbidden_from_users_index(): void
    {
        $plain = User::factory()->create();

        $this->actingAs($plain)->get('/superadmin/users')->assertForbidden();
    }
}
```

- [ ] **Step 4: Test'i çalıştır (henüz route yok, 404 bekleniyor — bilerek fail)**

Run: `php artisan test tests/Feature/Superadmin/UserManagementTest.php`
Expected: FAIL (route `/superadmin/users` henüz tanımlı değil, 404 döner — `assertForbidden`
403 bekler). Bu beklenen bir fail; Task A3 route+controller'ı ekleyince geçecek.

- [ ] **Step 5: Commit**

```bash
git add Modules/Superadmin/database/seeders/SuperadminPermissionSeeder.php tests/Feature/Superadmin/UserManagementTest.php
git commit -m "feat(superadmin): users.view/users.manage izinlerini ekle"
```

---

### Task 3 (A3): `UserController` + route'lar + CRUD testleri

**Files:**
- Create: `Modules/Superadmin/Http/Controllers/UserController.php`
- Modify: `Modules/Superadmin/routes/web.php`
- Modify: `tests/Feature/Superadmin/UserManagementTest.php` (Task A2'de oluşturuldu)

**Interfaces:**
- Consumes: `App\Models\User` (`fillable`: name, email, password, phone, job_title, tenant_id,
  is_active — hepsi mevcut), `Spatie\Permission\Models\Role`, `Modules\Tenant\Models\Tenant`.
- Produces: `index/store/update/destroy/toggleActive` — sonraki task'lar (A4 frontend) bu
  route'ları ve `index()`'in döndürdüğü prop şeklini (`users`, `roles`, `tenants`) tüketir.

- [ ] **Step 1: Controller'ı yaz**

`Modules/Superadmin/Http/Controllers/UserController.php`:

```php
<?php

namespace Modules\Superadmin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Tenant\Models\Tenant;
use Spatie\Permission\Models\Role;

/**
 * Platform genelinde (staff + tenant) kullanıcı CRUD'u + tek-rol ataması.
 *
 * Hassas meta-yönetim: route'lar `can:users.*` + `role:superadmin` double-lock taşır
 * (bkz. Modules/Superadmin/routes/web.php) — bir rol bu izni devralsa bile superadmin
 * DEĞİLSE erişemez. Sebep: bu ekrandan HERHANGİ bir role (superadmin dahil) atama
 * yapılabiliyor; delege edilirse privilege-escalation riski oluşur.
 */
class UserController extends Controller
{
    public function index(): Response
    {
        $users = User::query()
            ->with(['roles:id,name', 'tenant:id,name'])
            ->orderBy('name')
            ->get()
            ->map(fn (User $u) => [
                'id'         => $u->id,
                'name'       => $u->name,
                'email'      => $u->email,
                'phone'      => $u->phone,
                'job_title'  => $u->job_title,
                'is_active'  => (bool) $u->is_active,
                'role'       => $u->roles->first()?->name,
                'tenant'     => $u->tenant ? ['id' => $u->tenant->id, 'name' => $u->tenant->name] : null,
                'created_at' => $u->created_at?->toDateTimeString(),
            ]);

        return Inertia::render('Superadmin::Users', [
            'users'   => $users,
            'roles'   => Role::query()->orderBy('name')->get(['id', 'name', 'display_name']),
            'tenants' => Tenant::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request, null);

        $user = User::create([
            'name'      => $data['name'],
            'email'     => $data['email'],
            'password'  => $data['password'],
            'phone'     => $data['phone'] ?? null,
            'job_title' => $data['job_title'] ?? null,
            'tenant_id' => $data['tenant_id'] ?? null,
            'is_active' => true,
        ]);
        $user->assignRole($data['role']);

        return back()->with('success', 'Kullanıcı oluşturuldu.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $this->validated($request, $user);

        $user->fill([
            'name'      => $data['name'],
            'email'     => $data['email'],
            'phone'     => $data['phone'] ?? null,
            'job_title' => $data['job_title'] ?? null,
            'tenant_id' => $data['tenant_id'] ?? null,
        ]);
        if (! empty($data['password'])) {
            $user->password = $data['password'];
        }
        $user->save();

        // Zaten superadmin olan bir kullanıcının rolü bu ekrandan değiştirilemez
        // (kilitlenmeyi önler — bilerek değiştirmek `tinker` gerektirir).
        if (! $user->hasRole('superadmin')) {
            $user->syncRoles([$data['role']]);
        }

        return back()->with('success', 'Kullanıcı güncellendi.');
    }

    public function destroy(User $user): RedirectResponse
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'Kendi hesabınızı silemezsiniz.');
        }
        if ($user->hasRole('superadmin') && User::role('superadmin')->count() <= 1) {
            return back()->with('error', 'Son superadmin hesabı silinemez.');
        }

        $user->delete();

        return back()->with('success', 'Kullanıcı silindi.');
    }

    public function toggleActive(User $user): RedirectResponse
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'Kendi hesabınızı pasifleştiremezsiniz.');
        }

        $user->update(['is_active' => ! $user->is_active]);

        return back()->with('success', $user->is_active ? 'Kullanıcı aktifleştirildi.' : 'Kullanıcı pasifleştirildi.');
    }

    /**
     * @return array<string,mixed>
     */
    private function validated(Request $request, ?User $editing): array
    {
        return $request->validate([
            'name'      => ['required', 'string', 'max:150'],
            'email'     => ['required', 'email', Rule::unique('users', 'email')->ignore($editing?->id)],
            'password'  => [$editing ? 'nullable' : 'required', 'string', 'min:8', 'confirmed'],
            'phone'     => ['nullable', 'string', 'max:30'],
            'job_title' => ['nullable', 'string', 'max:100'],
            'role'      => ['required', 'string', 'exists:roles,name'],
            'tenant_id' => ['nullable', 'integer', 'exists:tenants,id'],
        ]);
    }
}
```

- [ ] **Step 2: Route'ları ekle**

`Modules/Superadmin/routes/web.php`'ye `use` satırı ekle (satır 7'den sonra, alfabetik):

```php
use Modules\Superadmin\Http\Controllers\UserController;
```

Roller/izinler bloğunun (satır 59-72) hemen ÜSTÜNE, AYNI double-lock desenini kullanan yeni bir
blok ekle:

```php
    // Kullanıcı Yönetimi (hassas meta-yönetim) — users.* izni + role:superadmin çift kilit.
    // Bkz. Global Constraints: bu ekrandan superadmin dahil her role atama yapılabildiği için
    // delege edilmez.
    Route::middleware('role:superadmin')->group(function () {
        Route::get('superadmin/users', [UserController::class, 'index'])
            ->middleware('can:users.view')->name('superadmin.users.index');
        Route::post('superadmin/users', [UserController::class, 'store'])
            ->middleware('can:users.manage')->name('superadmin.users.store');
        Route::put('superadmin/users/{user}', [UserController::class, 'update'])
            ->middleware('can:users.manage')->name('superadmin.users.update');
        Route::delete('superadmin/users/{user}', [UserController::class, 'destroy'])
            ->middleware('can:users.manage')->name('superadmin.users.destroy');
        Route::post('superadmin/users/{user}/toggle', [UserController::class, 'toggleActive'])
            ->middleware('can:users.manage')->name('superadmin.users.toggle');
    });

```

- [ ] **Step 3: CRUD testlerini `UserManagementTest.php`'ye ekle**

`tests/Feature/Superadmin/UserManagementTest.php`'ye, mevcut `test_plain_user_is_forbidden_from_users_index`
metodunun ALTINA ekle:

```php
    public function test_superadmin_can_list_users(): void
    {
        $admin = $this->superadmin();

        $this->actingAs($admin)
            ->get('/superadmin/users')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Superadmin::Users')
                ->has('users', 1)
                ->has('roles')
                ->has('tenants'));
    }

    public function test_superadmin_can_create_user_with_role(): void
    {
        $admin = $this->superadmin();
        Role::firstOrCreate(['name' => 'yonetim', 'guard_name' => 'web']);

        $this->actingAs($admin)->post('/superadmin/users', [
            'name'                  => 'Test Yönetici',
            'email'                 => 'yonetici@example.test',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
            'role'                  => 'yonetim',
        ])->assertRedirect();

        $created = User::where('email', 'yonetici@example.test')->first();
        $this->assertNotNull($created);
        $this->assertTrue($created->hasRole('yonetim'));
        $this->assertTrue($created->is_active);
    }

    public function test_update_without_password_keeps_existing_password(): void
    {
        $admin  = $this->superadmin();
        Role::firstOrCreate(['name' => 'yonetim', 'guard_name' => 'web']);
        $target = User::factory()->create(['password' => 'original-hash-marker']);
        $target->assignRole('yonetim');
        $originalHash = $target->password;

        $this->actingAs($admin)->put("/superadmin/users/{$target->id}", [
            'name'  => 'Güncellenmiş İsim',
            'email' => $target->email,
            'role'  => 'yonetim',
        ])->assertRedirect();

        $target->refresh();
        $this->assertSame('Güncellenmiş İsim', $target->name);
        $this->assertSame($originalHash, $target->password);
    }

    public function test_superadmin_role_cannot_be_changed_via_update(): void
    {
        $admin = $this->superadmin();
        Role::firstOrCreate(['name' => 'yonetim', 'guard_name' => 'web']);
        $otherSuperadmin = User::factory()->create();
        $otherSuperadmin->assignRole('superadmin');

        $this->actingAs($admin)->put("/superadmin/users/{$otherSuperadmin->id}", [
            'name'  => $otherSuperadmin->name,
            'email' => $otherSuperadmin->email,
            'role'  => 'yonetim',
        ])->assertRedirect();

        $this->assertTrue($otherSuperadmin->fresh()->hasRole('superadmin'));
    }

    public function test_cannot_delete_self(): void
    {
        $admin = $this->superadmin();

        $this->actingAs($admin)
            ->delete("/superadmin/users/{$admin->id}")
            ->assertRedirect();

        $this->assertNotNull($admin->fresh());
    }

    public function test_can_delete_a_superadmin_when_another_remains(): void
    {
        // İki superadmin varken birini silmek serbest olmalı (bir tane kalır).
        $admin       = $this->superadmin();
        $secondAdmin = User::factory()->create();
        $secondAdmin->assignRole('superadmin');

        $this->actingAs($admin)
            ->delete("/superadmin/users/{$secondAdmin->id}")
            ->assertRedirect();

        $this->assertNull($secondAdmin->fresh());
    }

    public function test_last_superadmin_guard_blocks_deleting_the_sole_superadmin(): void
    {
        // Route zaten role:superadmin ile kilitli olduğundan, tek superadmin varken
        // silme isteğini atan hesap ZORUNLU olarak hedefin kendisidir — bu durumda
        // "kendini silemez" guard'ı devreye girer (last-superadmin guard'ı ise
        // gelecekte izin başka bir role delege edilirse devreye girecek ikinci bir
        // güvenlik katmanıdır). Burada ikisinin birlikte hedefi engellediğini doğrularız.
        $onlyAdmin = $this->superadmin();

        $this->actingAs($onlyAdmin)
            ->delete("/superadmin/users/{$onlyAdmin->id}")
            ->assertRedirect();

        $this->assertNotNull($onlyAdmin->fresh());
        $this->assertSame(1, User::role('superadmin')->count());
    }

    public function test_toggle_active_flips_status(): void
    {
        $admin  = $this->superadmin();
        Role::firstOrCreate(['name' => 'yonetim', 'guard_name' => 'web']);
        $target = User::factory()->create(['is_active' => true]);
        $target->assignRole('yonetim');

        $this->actingAs($admin)
            ->post("/superadmin/users/{$target->id}/toggle")
            ->assertRedirect();

        $this->assertFalse($target->fresh()->is_active);
    }
```

- [ ] **Step 4: Testleri çalıştır**

Run: `php artisan test tests/Feature/Superadmin/UserManagementTest.php`
Expected: tüm testler PASS (9 test).

- [ ] **Step 5: Commit**

```bash
git add Modules/Superadmin/Http/Controllers/UserController.php Modules/Superadmin/routes/web.php tests/Feature/Superadmin/UserManagementTest.php
git commit -m "feat(superadmin): kullanıcı CRUD + tek-rol atama controller'ı ve route'ları"
```

---

### Task 4 (A4): `Users.vue` sayfası + kullanıcı menüsüne link

**Files:**
- Create: `Modules/Superadmin/Resources/assets/js/Pages/Users.vue`
- Modify: `resources/js/Layouts/AppLayout.vue:141` (userMenu computed)

**Interfaces:**
- Consumes: Task A3'ün `index()`'inden gelen `users`/`roles`/`tenants` prop'ları; route'lar
  `/superadmin/users` (GET/POST), `/superadmin/users/{id}` (PUT/DELETE), `/superadmin/users/{id}/toggle` (POST).
- Produces: yok (uç nokta sayfa).

- [ ] **Step 1: `Users.vue`'yu yaz**

`Modules/Superadmin/Resources/assets/js/Pages/Users.vue` — `Tenants.vue` kalıbı:

```vue
<template>
	<Head title="Kullanıcılar" />
	<div class="page-users">
		<Breadcrumb
			:items="[
				{ label: 'Ana Sayfa', to: '/workflow', icon: 'home' },
				{ label: 'Süper Admin' },
				{ label: 'Kullanıcılar' },
			]"
		/>

		<div class="page-header">
			<div>
				<h1 class="page-title">Kullanıcılar</h1>
				<p class="page-subtitle"><strong>{{ users.length }}</strong> kullanıcı kayıtlı</p>
			</div>
			<button class="btn btn-primary btn-with-icon" @click="openNew">
				<svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
					<path d="M12 5v14M5 12h14" />
				</svg>
				Yeni Kullanıcı
			</button>
		</div>

		<div class="card">
			<div class="card-header">
				<h3>Kullanıcı Listesi</h3>
				<div class="card-search">
					<input v-model="searchQuery" type="text" placeholder="İsim, e-posta, telefon ara..." />
				</div>
				<select v-model="filterRole" class="filter-select">
					<option value="">Tüm roller</option>
					<option v-for="r in roles" :key="r.id" :value="r.name">{{ r.display_name || r.name }}</option>
				</select>
				<select v-model="filterActive" class="filter-select">
					<option value="">Tüm durumlar</option>
					<option value="1">Aktif</option>
					<option value="0">Pasif</option>
				</select>
			</div>

			<table class="data-table">
				<thead>
					<tr>
						<th>Kullanıcı</th>
						<th>Telefon</th>
						<th>Unvan</th>
						<th>Rol</th>
						<th>Tenant</th>
						<th>Durum</th>
						<th>Kayıt Tarihi</th>
						<th>İşlemler</th>
					</tr>
				</thead>
				<tbody>
					<tr v-if="filtered.length === 0">
						<td colspan="8" class="empty-row">Kayıt bulunamadı</td>
					</tr>
					<tr v-for="u in filtered" :key="u.id">
						<td>
							<div class="user-cell">
								<div class="user-avatar">{{ initials(u.name) }}</div>
								<div class="user-info">
									<span class="user-name">{{ u.name }}</span>
									<span class="user-email">{{ u.email }}</span>
								</div>
							</div>
						</td>
						<td>{{ u.phone || '—' }}</td>
						<td>{{ u.job_title || '—' }}</td>
						<td><span v-if="u.role" class="badge-role">{{ u.role }}</span><span v-else class="dim">—</span></td>
						<td>{{ u.tenant?.name || '—' }}</td>
						<td>
							<span :class="['status-pill', u.is_active ? 'active' : 'inactive']">
								{{ u.is_active ? 'Aktif' : 'Pasif' }}
							</span>
						</td>
						<td>{{ u.created_at }}</td>
						<td>
							<div class="table-actions">
								<button class="table-action-btn view" @click="edit(u)" title="Düzenle">✏️</button>
								<button class="table-action-btn toggle" @click="toggle(u)" :title="u.is_active ? 'Pasifleştir' : 'Aktifleştir'">
									{{ u.is_active ? '⏸️' : '▶️' }}
								</button>
								<button class="table-action-btn delete" @click="confirmDelete(u)" title="Sil">🗑️</button>
							</div>
						</td>
					</tr>
				</tbody>
			</table>
		</div>

		<AppModal v-model="formOpen" :title="editing ? 'Kullanıcı Düzenle' : 'Yeni Kullanıcı'" size="md" variant="info">
			<form class="form-grid" @submit.prevent="submit">
				<div class="form-row-2">
					<div class="form-row">
						<label class="form-label">Ad Soyad <span class="req">*</span></label>
						<input v-model="form.name" type="text" class="form-input" />
						<span v-if="errors.name" class="form-error">{{ errors.name }}</span>
					</div>
					<div class="form-row">
						<label class="form-label">E-posta <span class="req">*</span></label>
						<input v-model="form.email" type="email" class="form-input" />
						<span v-if="errors.email" class="form-error">{{ errors.email }}</span>
					</div>
				</div>
				<div class="form-row-2">
					<div class="form-row">
						<label class="form-label">Telefon</label>
						<input v-model="form.phone" type="text" class="form-input" />
					</div>
					<div class="form-row">
						<label class="form-label">Unvan</label>
						<input v-model="form.job_title" type="text" class="form-input" />
					</div>
				</div>
				<div class="form-row-2">
					<div class="form-row">
						<label class="form-label">Şifre <span v-if="!editing" class="req">*</span></label>
						<input v-model="form.password" type="password" class="form-input" :placeholder="editing ? 'Boş bırakılırsa değişmez' : ''" />
						<span v-if="errors.password" class="form-error">{{ errors.password }}</span>
					</div>
					<div class="form-row">
						<label class="form-label">Şifre Tekrar</label>
						<input v-model="form.password_confirmation" type="password" class="form-input" />
					</div>
				</div>
				<div class="form-row-2">
					<div class="form-row">
						<label class="form-label">Rol <span class="req">*</span></label>
						<select v-model="form.role" class="form-input" :disabled="editing?.role === 'superadmin'">
							<option value="">Seçin</option>
							<option v-for="r in roles" :key="r.id" :value="r.name">{{ r.display_name || r.name }}</option>
						</select>
						<span v-if="editing?.role === 'superadmin'" class="form-hint">Superadmin rolü bu ekrandan değiştirilemez.</span>
						<span v-if="errors.role" class="form-error">{{ errors.role }}</span>
					</div>
					<div class="form-row">
						<label class="form-label">Tenant</label>
						<select v-model="form.tenant_id" class="form-input">
							<option :value="null">Yok</option>
							<option v-for="t in tenants" :key="t.id" :value="t.id">{{ t.name }}</option>
						</select>
					</div>
				</div>
			</form>
			<template #footer="{ close }">
				<button class="btn btn-ghost" @click="close" :disabled="busy">İptal</button>
				<button class="btn btn-primary" @click="submit" :disabled="busy">
					{{ editing ? 'Kaydet' : 'Ekle' }}
				</button>
			</template>
		</AppModal>
	</div>
</template>

<script setup>
import { ref, reactive, computed, inject, watch } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Breadcrumb from '@/Components/Breadcrumb.vue'
import AppModal from '@/Components/AppModal.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
	users: { type: Array, default: () => [] },
	roles: { type: Array, default: () => [] },
	tenants: { type: Array, default: () => [] },
})

const showToast = inject('showToast')
const $swal = inject('$swal')

const searchQuery = ref('')
const filterRole = ref('')
const filterActive = ref('')

const filtered = computed(() => {
	const q = searchQuery.value.trim().toLowerCase()
	return props.users.filter(u => {
		if (filterRole.value && u.role !== filterRole.value) return false
		if (filterActive.value !== '' && (u.is_active ? '1' : '0') !== String(filterActive.value)) return false
		if (q) {
			const hay = [u.name, u.email, u.phone].filter(Boolean).join(' ').toLowerCase()
			if (!hay.includes(q)) return false
		}
		return true
	})
})

function initials(name) {
	return (name || '?').split(' ').filter(Boolean).slice(0, 2).map(w => w[0]?.toUpperCase()).join('')
}

const formOpen = ref(false)
const editing = ref(null)
const busy = ref(false)
const errors = ref({})

const emptyForm = () => ({
	name: '', email: '', phone: '', job_title: '',
	password: '', password_confirmation: '', role: '', tenant_id: null,
})

const form = reactive(emptyForm())

watch(formOpen, (open) => {
	if (!open) {
		setTimeout(() => {
			editing.value = null
			errors.value = {}
			Object.assign(form, emptyForm())
		}, 250)
	}
})

function openNew() {
	editing.value = null
	errors.value = {}
	Object.assign(form, emptyForm())
	formOpen.value = true
}

function edit(u) {
	editing.value = u
	errors.value = {}
	Object.assign(form, {
		name: u.name, email: u.email, phone: u.phone ?? '', job_title: u.job_title ?? '',
		password: '', password_confirmation: '', role: u.role ?? '', tenant_id: u.tenant?.id ?? null,
	})
	formOpen.value = true
}

function submit() {
	if (busy.value) return
	busy.value = true
	errors.value = {}
	const opts = {
		preserveScroll: true,
		preserveState: true,
		onSuccess: () => {
			formOpen.value = false
			showToast?.({ type: 'success', title: editing.value ? 'Kullanıcı güncellendi' : 'Kullanıcı eklendi', message: form.name })
		},
		onError: (errs) => {
			errors.value = errs
			showToast?.({ type: 'error', title: 'Kayıt başarısız', message: Object.values(errs)[0] || 'Doğrulama hatası.' })
		},
		onFinish: () => { busy.value = false },
	}
	if (editing.value) {
		router.put(`/superadmin/users/${editing.value.id}`, { ...form }, opts)
	} else {
		router.post('/superadmin/users', { ...form }, opts)
	}
}

function toggle(u) {
	router.post(`/superadmin/users/${u.id}/toggle`, {}, {
		preserveScroll: true, preserveState: true,
		onSuccess: () => showToast?.({ type: 'success', title: u.is_active ? 'Kullanıcı pasifleştirildi' : 'Kullanıcı aktifleştirildi', message: u.name }),
		onError: (errs) => showToast?.({ type: 'error', title: 'İşlem başarısız', message: Object.values(errs)[0] || 'Sunucu hatası.' }),
	})
}

async function confirmDelete(u) {
	const ok = await $swal.dangerConfirm({
		title: 'Kullanıcıyı Sil',
		html: `<strong>${u.name}</strong> silinecek.`,
		confirmText: 'Sil',
		cancelText: 'Vazgeç',
	})
	if (!ok) return
	router.delete(`/superadmin/users/${u.id}`, {
		preserveScroll: true, preserveState: true,
		onSuccess: () => showToast?.({ type: 'warning', title: 'Kullanıcı silindi', message: u.name }),
		onError: (errs) => showToast?.({ type: 'error', title: 'Silme başarısız', message: Object.values(errs)[0] || 'Sunucu hatası.' }),
	})
}
</script>

<style scoped>
.page-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 20px; gap: 16px; }
.page-title { font-size: 22px; font-weight: 700; color: #1a1a2e; line-height: 1.2; }
.page-subtitle { font-size: 13px; color: #888; margin-top: 4px; }

.card { background: #fff; border-radius: 16px; border: 1px solid #ebebf0; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,.04); }
.card-header { padding: 14px 18px; display: flex; align-items: center; gap: 12px; border-bottom: 1px solid #f0f0f5; flex-wrap: wrap; }
.card-header h3 { font-size: 15px; font-weight: 700; color: #1a1a2e; }
.card-search { display: flex; align-items: center; gap: 6px; background: #f5f5f8; border: 1px solid #e8e8f0; border-radius: 8px; padding: 5px 10px; margin-left: auto; min-width: 240px; }
.card-search input { border: none; background: none; outline: none; font-family: inherit; font-size: 13px; width: 100%; }
.filter-select { border: 1px solid #e8e8f0; background: #f5f5f8; border-radius: 8px; padding: 6px 10px; font-size: 12.5px; font-family: inherit; outline: none; }

.data-table { width: 100%; border-collapse: separate; border-spacing: 0; }
.data-table thead tr { background: #f8f8fc; }
.data-table th { text-align: left; padding: 12px 16px; font-size: 11px; font-weight: 600; color: #aaa; border-bottom: 1px solid #f0f0f5; text-transform: uppercase; letter-spacing: 0.04em; }
.data-table td { padding: 12px 16px; font-size: 13px; color: #444; border-bottom: 1px solid #f5f5f8; vertical-align: middle; }
.data-table tr:last-child td { border-bottom: none; }
.empty-row { text-align: center !important; color: #aaa; padding: 32px 0 !important; font-style: italic; }
.dim { color: #aaa; font-size: 12px; }

.user-cell { display: flex; align-items: center; gap: 12px; }
.user-avatar { width: 36px; height: 36px; border-radius: 50%; background: linear-gradient(135deg, #dbeafe, #bfdbfe); display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 800; color: #2563eb; flex-shrink: 0; }
.user-info { display: flex; flex-direction: column; gap: 2px; }
.user-name { font-weight: 600; color: #1a1a2e; font-size: 13px; }
.user-email { font-size: 11px; color: #888; }

.badge-role { display: inline-block; padding: 3px 9px; background: rgb(var(--color-primary-soft)); color: #4338ca; border-radius: 6px; font-size: 11px; font-weight: 600; }

.status-pill { display: inline-block; padding: 3px 9px; border-radius: 6px; font-size: 11px; font-weight: 600; }
.status-pill.active { background: #dcfce7; color: #15803d; }
.status-pill.inactive { background: #fee2e2; color: #b91c1c; }

.table-actions { display: flex; gap: 4px; }
.table-action-btn { background: #f3f4f6; border: none; cursor: pointer; font-size: 13px; padding: 5px 9px; border-radius: 6px; color: #6b7280; transition: all .15s; }
.table-action-btn.view:hover { background: rgb(var(--color-primary-soft)); color: rgb(var(--color-primary)); }
.table-action-btn.toggle:hover { background: #fef3c7; color: #b45309; }
.table-action-btn.delete:hover { background: #fee2e2; color: #dc2626; }

.form-grid { display: flex; flex-direction: column; gap: 14px; }
.form-row { display: flex; flex-direction: column; gap: 6px; }
.form-row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.form-label { font-size: 12px; font-weight: 600; color: #1a1a2e; }
.form-label .req { color: #ef4444; }
.form-input { padding: 9px 12px; border: 1px solid #e8e8f0; border-radius: 8px; font-family: inherit; font-size: 13px; color: #1a1a2e; background: #fff; outline: none; }
.form-input:focus { border-color: rgb(var(--color-primary)); }
.form-input:disabled { background: #f5f5f8; color: #999; }
.form-error { font-size: 11.5px; color: #ef4444; }
.form-hint { font-size: 11px; color: #aaa; }
</style>
```

- [ ] **Step 2: `AppLayout.vue` kullanıcı menüsüne link ekle**

`resources/js/Layouts/AppLayout.vue` satır 141'in ("Menü Yönetimi") ALTINA ekle:

```js
		{ label: 'Kullanıcı Yönetimi', icon: '<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>', to: '/superadmin/users', visible: role === 'superadmin' },
```

- [ ] **Step 3: Frontend'i derle**

Run: `npm run build`
Expected: hatasız biter, `public/build/assets/Users-*.js` üretilir.

- [ ] **Step 4: Manuel doğrulama**

1. `php artisan serve` (veya laragon) ile uygulamayı aç, superadmin ile giriş yap.
2. Sağ üst kullanıcı menüsünde "Kullanıcı Yönetimi" linkine tıkla → `/superadmin/users` açılmalı.
3. "Yeni Kullanıcı" → formu doldur, rolü "yonetim" seç → kaydet → tabloda satır oluşmalı.
4. Düzenle → şifreyi boş bırak → kaydet → şifre değişmemiş olmalı (aynı hesapla tekrar giriş yapılabilir).
5. Toggle → aktif/pasif değişmeli. Sil → SweetAlert2 onayından sonra silinmeli.

- [ ] **Step 5: Commit**

```bash
git add Modules/Superadmin/Resources/assets/js/Pages/Users.vue resources/js/Layouts/AppLayout.vue
git commit -m "feat(superadmin): Kullanıcılar sayfası + kullanıcı menüsü linki"
```

---

## PHASE B — Anlık Bildirim Altyapısı

### Task 5 (B1): Broadcasting config + `routes/channels.php`

**Files:**
- Create: `config/broadcasting.php` (artisan tarafından üretilir)
- Create: `routes/channels.php` (artisan tarafından üretilir, sonra düzenlenir)
- Modify: `bootstrap/app.php` (artisan tarafından güncellenir)
- Modify: `.env.example`

**Interfaces:**
- Consumes: `.env`'deki mevcut `REVERB_*`/`BROADCAST_CONNECTION=reverb` (zaten tanımlı).
- Produces: `/broadcasting/auth` route'u (Task B2'nin Echo authorizer'ının hedefi),
  `App.Models.User.{id}` private kanalı (Task B3/B4'ün kullandığı kanal adı).

- [ ] **Step 1: Broadcasting scaffolding'i kur**

Run: `php artisan install:broadcasting --reverb --no-interaction`

Expected çıktı: `config/broadcasting.php` oluşturulur, `routes/channels.php` oluşturulur,
`bootstrap/app.php`'e `->withBroadcasting(...)` eklenir, `BROADCAST_CONNECTION` zaten `reverb`
olduğu için `.env`'e dokunmaz (zaten mevcut). Reverb server config sorularına `.env`'deki
mevcut `REVERB_*` değerleri korunacak şekilde "hayır/mevcut değeri kullan" cevabı verilir
(komut `--reverb` ile zaten mevcut Reverb kurulumunu algılar).

- [ ] **Step 2: `bootstrap/app.php`'i doğrula**

Read `bootstrap/app.php` — `->withRouting(...)` zincirine `->withBroadcasting(channels:
__DIR__.'/../routes/channels.php', attributes: ['middleware' => ['web', 'auth']])` eklenmiş
olmalı. Eklenmediyse (artisan komutu farklı bir yere eklerse) elle şu satırı `->withRouting(...)`
çağrısının hemen ALTINA ekle:

```php
    ->withBroadcasting(
        channels: __DIR__.'/../routes/channels.php',
        attributes: ['middleware' => ['web', 'auth']],
    )
```

- [ ] **Step 3: `routes/channels.php`'i varsayılan private-user kanalıyla değiştir**

`routes/channels.php` içeriğini şu şekilde olduğundan emin ol (artisan varsayılan olarak
bunu üretir; üretmediyse elle yaz):

```php
<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, int $id) {
    return (int) $user->id === $id;
});
```

- [ ] **Step 4: `.env.example`'a Reverb değişkenlerini ekle**

`.env.example`'daki `BROADCAST_CONNECTION=log` satırını (satır 36) şu blokla değiştir:

```
BROADCAST_CONNECTION=reverb

REVERB_APP_ID=
REVERB_APP_KEY=
REVERB_APP_SECRET=
REVERB_HOST="127.0.0.1"
REVERB_PORT=8080
REVERB_SCHEME=http

VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```

- [ ] **Step 5: Route'u doğrula**

Run: `php artisan route:list | grep broadcasting`
Expected: `POST broadcasting/auth` satırı görünür.

- [ ] **Step 6: Commit**

```bash
git add config/broadcasting.php routes/channels.php bootstrap/app.php .env.example
git commit -m "feat(broadcasting): Reverb private kullanıcı kanalı altyapısını kur"
```

---

### Task 6 (B2): `bootstrap.js` — CSRF-güvenli Echo authorizer

**Files:**
- Modify: `resources/js/bootstrap.js`

**Interfaces:**
- Consumes: `window.axios` (zaten CSRF için XSRF-TOKEN cookie mekanizmasıyla yapılandırılmış —
  bkz. dosyanın satır 6-12'deki yorum, `[[project_web_csrf_gotcha]]`).
- Produces: `window.Echo` — Task B4'ün `Echo.private(...)` çağrısının kullandığı obje,
  artık `/broadcasting/auth`'a **axios üzerinden** (stale CSRF token riski olmadan) istek atar.

**Neden gerekli:** Laravel Echo'nun varsayılan pusher-js authorizer'ı `/broadcasting/auth`'a
kendi XHR mekanizmasıyla istek atar ve `<meta csrf-token>`'ı okur — bu proje bunu KASITLI
olarak terk etmişti (statik token bayatlayıp 419 veriyordu, bkz. proje CLAUDE.md/memory). Aynı
hatayı tekrarlamamak için authorizer axios'a bağlanır.

- [ ] **Step 1: `window.Echo` tanımını güncelle**

`resources/js/bootstrap.js` satır 37-46'yı değiştir:

```js
window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY || 'nmd655cwaniqz6omotn2',
    wsHost: '127.0.0.1',
    wsPort: 8080,
    wssPort: 8080,
    forceTLS: false,
    useTLS: false,
    enabledTransports: ['ws'],
    // Varsayılan XHR authorizer YERİNE axios kullanılır — statik <meta csrf-token>
    // yerine axios'un XSRF-TOKEN cookie mekanizması (bkz. dosya başı yorumu) devreye
    // girer. Aksi halde login sonrası session rotasyonunda /broadcasting/auth 419 verir.
    authorizer: (channel) => ({
        authorize: (socketId, callback) => {
            window.axios
                .post('/broadcasting/auth', { socket_id: socketId, channel_name: channel.name })
                .then((response) => callback(false, response.data))
                .catch((error) => callback(true, error));
        },
    }),
});
```

- [ ] **Step 2: Frontend'i derle**

Run: `npm run build`
Expected: hatasız biter.

- [ ] **Step 3: Commit**

```bash
git add resources/js/bootstrap.js
git commit -m "feat(broadcasting): Echo authorizer'ı axios/XSRF-TOKEN'a bağla (419 riskini önle)"
```

---

### Task 7 (B3): Creative bildirimlerini `ShouldBroadcast` yap

**Files:**
- Modify: `Modules/Creative/Notifications/ImagePendingReviewNotification.php`
- Modify: `Modules/Creative/Notifications/ImageReviewDecisionNotification.php`
- Modify: `tests/Feature/Creative/CreativeReviewWorkflowTest.php`

**Interfaces:**
- Consumes: mevcut `toArray()` metodları (değişmiyor).
- Produces: `via()` artık `['database', 'broadcast']` döner; `toBroadcast()` YOK (Laravel'in
  `Illuminate\Notifications\Notification` temel sınıfı, `toBroadcast()` tanımlı değilse
  `toArray()`'i otomatik `BroadcastMessage` içine sarar — ek kod gerekmez).

- [ ] **Step 1: `ImagePendingReviewNotification`'ı güncelle**

`Modules/Creative/Notifications/ImagePendingReviewNotification.php`'de:

```php
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Notification;
use Modules\Creative\Models\Mannequin;
use Modules\Creative\Models\TryonResult;

class ImagePendingReviewNotification extends Notification implements ShouldBroadcast
{
    use Queueable;

    public function __construct(private Mannequin|TryonResult $subject) {}

    /** @return array<int,string> */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }
```

(Sınıfın geri kalanı — `toArray()` — DEĞİŞMEDEN kalır.)

- [ ] **Step 2: `ImageReviewDecisionNotification`'ı aynı şekilde güncelle**

`Modules/Creative/Notifications/ImageReviewDecisionNotification.php`'de aynı değişiklik:
`use Illuminate\Contracts\Broadcasting\ShouldBroadcast;` import'u eklenir, sınıf
`implements ShouldBroadcast` olur, `via()` → `['database', 'broadcast']`. `toArray()` DEĞİŞMEZ.

- [ ] **Step 3: Failing test yaz**

`tests/Feature/Creative/CreativeReviewWorkflowTest.php`'ye, `use` bloğuna
`use Illuminate\Contracts\Broadcasting\ShouldBroadcast;` ekle ve dosyanın sonuna (son `}`'dan
ÖNCE) şu testi ekle:

```php
    public function test_review_notifications_implement_should_broadcast(): void
    {
        $this->assertInstanceOf(ShouldBroadcast::class, new ImagePendingReviewNotification($this->pendingMannequin($this->creator())));

        $decision = new ImageReviewDecisionNotification($this->pendingMannequin($this->creator()), true);
        $this->assertInstanceOf(ShouldBroadcast::class, $decision);
    }
```

- [ ] **Step 4: Testleri çalıştır**

Run: `php artisan test tests/Feature/Creative/CreativeReviewWorkflowTest.php`
Expected: tüm testler (yeni dahil) PASS. (`Notification::fake()` kullanan mevcut testler
`assertSentTo` ile hâlâ çalışır — `ShouldBroadcast` eklemek `database` kanalını kaldırmaz.)

- [ ] **Step 5: Commit**

```bash
git add Modules/Creative/Notifications/ImagePendingReviewNotification.php Modules/Creative/Notifications/ImageReviewDecisionNotification.php tests/Feature/Creative/CreativeReviewWorkflowTest.php
git commit -m "feat(creative): onay bildirimlerini ShouldBroadcast yap (anlık teslim)"
```

---

### Task 8 (B4): `AppLayout.vue` — genel Echo bildirim dinleyicisi

**Files:**
- Modify: `resources/js/Layouts/AppLayout.vue`

**Interfaces:**
- Consumes: `window.Echo` (Task B2), `currentUser.value.id`, `notifications` (mevcut
  `computed(() => page.props.notifications || [])`, satır 160).
- Produces: yok — bu, `ShouldBroadcast` implement eden HERHANGİ bir Notification için
  otomatik çalışan genel bir dinleyicidir (Creative'e özel değildir).

- [ ] **Step 1: Echo dinleyicisini ekle**

`resources/js/Layouts/AppLayout.vue`'da, flash watcher bloğunun (satır 296-en sonu, `watch(
() => page.props.flash, ...)`) HEMEN ALTINA ekle:

```js
/* ── Anlık bildirim (Reverb) ── */
// ShouldBroadcast implement eden HERHANGİ bir Notification için otomatik çalışır — sayfa
// yenilenmeden çana eklenir. Şekil, HandleInertiaRequests::notificationsPayload() ile aynı
// olacak şekilde normalize edilir (TopNav.vue bu alanları bekler: icon/iconType/title/desc/time/read/link).
let echoChannel = null
onMounted(() => {
	const userId = currentUser.value.id
	if (!userId || !window.Echo) return
	echoChannel = window.Echo.private(`App.Models.User.${userId}`)
	echoChannel.notification((notification) => {
		notifications.value.unshift({
			id: notification.id,
			icon: notification.icon ?? '🔔',
			iconType: notification.iconType ?? 'info',
			title: notification.title ?? 'Bildirim',
			desc: notification.desc ?? '',
			time: 'şimdi',
			read: false,
			link: notification.link ?? null,
		})
	})
})
onBeforeUnmount(() => {
	if (echoChannel) window.Echo.leave(`App.Models.User.${currentUser.value.id}`)
})
```

`onMounted`/`onBeforeUnmount` zaten satır 50'de import edilmiş (mevcut import listesine
dokunma gerekmez).

- [ ] **Step 2: Frontend'i derle**

Run: `npm run build`
Expected: hatasız biter.

- [ ] **Step 3: Manuel doğrulama (iki oturum)**

1. `php artisan reverb:start` çalışır durumda olsun (ayrı terminal), queue worker da çalışsın
   (`php artisan queue:work` — notification'lar `Queueable` trait taşıyor).
2. İki farklı tarayıcı (veya bir normal + bir gizli pencere) ile iki farklı hesapla giriş yap:
   biri üretici (Creative'de manken/tryon üretici), biri `creative.approve` sahibi başka bir
   hesap.
3. Üretici hesap bir manken üretsin (onaya düşer).
4. Diğer tarayıcıda, **sayfa yenilemeden**, birkaç saniye içinde bildirim çanında yeni bir
   bildirim belirmeli.

- [ ] **Step 4: Commit**

```bash
git add resources/js/Layouts/AppLayout.vue
git commit -m "feat(broadcasting): AppLayout'a genel anlık bildirim dinleyicisi ekle"
```

---

## PHASE C — Chatbot Tam Sayfa

### Task 9 (C1): `ReviewChatController` — GET `show*` action'ları + route'lar

**Files:**
- Modify: `Modules/Creative/Http/Controllers/ReviewChatController.php`
- Modify: `Modules/Creative/routes/web.php`
- Modify: `tests/Feature/Creative/ReviewChatTest.php`

**Interfaces:**
- Consumes: `ReviewChatService::history()`/`latestSuggestion()` (mevcut, değişmiyor),
  `authorizeChat()` (mevcut private metod — `protected` yapılması GEREKMEZ, aynı sınıf
  içinden çağrılıyor).
- Produces: `Inertia::render('Creative::ReviewChat', [...])` — Task C2'nin tükettiği prop
  şekli: `subjectType` ('mannequin'|'tryon'), `subjectId`, `title`, `imageUrl`, `reviewNote`,
  `chats` (array), `suggestion` (string|null), `backUrl`.

- [ ] **Step 1: Controller'a `showMannequin`/`showTryon` ekle**

`Modules/Creative/Http/Controllers/ReviewChatController.php`'nin başına `use` ekle:

```php
use App\Support\Media;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
```

`sendMannequin` metodunun ÜSTÜNE iki yeni metod ekle:

```php
    public function showMannequin(Mannequin $mannequin, ReviewChatService $chat): Response
    {
        $this->authorizeChat($mannequin);

        return $this->renderChatPage(
            subjectType: 'mannequin',
            subjectId: $mannequin->id,
            title: $mannequin->name,
            imageUrl: $mannequin->reference_image_path
                ? Storage::disk(config('creative.disk', 'public'))->url($mannequin->reference_image_path)
                : null,
            reviewNote: $mannequin->review_note,
            chat: $chat,
            subject: $mannequin,
            backUrl: '/creative/mannequins',
        );
    }

    public function showTryon(TryonResult $result, ReviewChatService $chat): Response
    {
        $this->authorizeChat($result);
        $result->loadMissing('product:id,name');

        return $this->renderChatPage(
            subjectType: 'tryon',
            subjectId: $result->id,
            title: $result->product?->name ?? 'Ürün',
            imageUrl: Media::url($result->staged_image_path),
            reviewNote: $result->review_note,
            chat: $chat,
            subject: $result,
            backUrl: '/creative/tryon',
        );
    }

    private function renderChatPage(
        string $subjectType,
        int $subjectId,
        string $title,
        ?string $imageUrl,
        ?string $reviewNote,
        ReviewChatService $chat,
        Mannequin|TryonResult $subject,
        string $backUrl,
    ): Response {
        return Inertia::render('Creative::ReviewChat', [
            'subjectType' => $subjectType,
            'subjectId'   => $subjectId,
            'title'       => $title,
            'imageUrl'    => $imageUrl,
            'reviewNote'  => $reviewNote,
            'backUrl'     => $backUrl,
            'chats'       => $chat->history($subject)->map(fn ($c) => [
                'id'         => $c->id,
                'role'       => $c->role,
                'content'    => $c->content,
                'user_name'  => $c->user?->name,
                'created_at' => $c->created_at?->toDateTimeString(),
            ])->values(),
            'suggestion'  => $chat->latestSuggestion($subject),
        ]);
    }
```

`ReviewChatService::history()` sonucu `.map()`'lenirken `user` ilişkisi eager-load edilmemiş
olabilir (N+1) — `ReviewChatService::history()` zaten `$subject->reviewChats()->orderBy(...)->get()`
döndürüyor; bu görsel-başına en fazla birkaç mesaj olduğundan (chat geçmişi) N+1 burada kabul
edilebilir ölçekte (Product listeleri gibi büyük değil). Optimize etmek istenirse
`ReviewChatService::history()`'e `->with('user:id,name')` eklenebilir — bu plan kapsamında
GEREKLİ değil.

- [ ] **Step 2: Route'ları ekle**

`Modules/Creative/routes/web.php`'deki "Onay sohbet asistanı" bloğuna (satır 89-97), MEVCUT
POST route'ların HEMEN ÜSTÜNE GET route'larını ekle:

```php
    // ─── Onay sohbet asistanı (reddedilen manken/tryon için) ──────────────
    Route::get('/mannequins/{mannequin}/review-chat', [ReviewChatController::class, 'showMannequin'])
        ->middleware('can:creative.view')->whereNumber('mannequin')->name('mannequins.review-chat.show');
    Route::get('/tryon/{result}/review-chat', [ReviewChatController::class, 'showTryon'])
        ->middleware('can:creative.view')->whereNumber('result')->name('tryon.review-chat.show');
    Route::post('/mannequins/{mannequin}/review-chat', [ReviewChatController::class, 'sendMannequin'])
        ->middleware('can:creative.view')->whereNumber('mannequin')->name('mannequins.review-chat');
    Route::post('/mannequins/{mannequin}/review-chat/apply', [ReviewChatController::class, 'applyMannequin'])
        ->middleware('can:creative.view')->whereNumber('mannequin')->name('mannequins.review-chat.apply');
    Route::post('/tryon/{result}/review-chat', [ReviewChatController::class, 'sendTryon'])
        ->middleware('can:creative.view')->whereNumber('result')->name('tryon.review-chat');
    Route::post('/tryon/{result}/review-chat/apply', [ReviewChatController::class, 'applyTryon'])
        ->middleware('can:creative.view')->whereNumber('result')->name('tryon.review-chat.apply');
```

(GET ve POST aynı path'i paylaşır — HTTP metodu farklı olduğu için route çakışması olmaz.)

- [ ] **Step 3: Failing testleri yaz**

`tests/Feature/Creative/ReviewChatTest.php`'nin sonuna (son `}`'dan ÖNCE) ekle:

```php
    public function test_show_page_requires_participant(): void
    {
        config(['inertia.testing.ensure_pages_exist' => false]);
        $creator   = $this->creator();
        $unrelated = $this->creator();
        $m         = $this->rejectedMannequin($creator);

        $this->actingAs($unrelated)
            ->get("/creative/mannequins/{$m->id}/review-chat")
            ->assertForbidden();
    }

    public function test_show_page_requires_rejected_status(): void
    {
        config(['inertia.testing.ensure_pages_exist' => false]);
        $creator = $this->creator();
        $m       = $this->rejectedMannequin($creator);
        $m->update(['review_status' => Mannequin::REVIEW_PENDING]);

        $this->actingAs($creator)
            ->get("/creative/mannequins/{$m->id}/review-chat")
            ->assertStatus(422);
    }

    public function test_show_page_renders_for_creator(): void
    {
        config(['inertia.testing.ensure_pages_exist' => false]);
        $creator = $this->creator();
        $m       = $this->rejectedMannequin($creator);

        $this->actingAs($creator)
            ->get("/creative/mannequins/{$m->id}/review-chat")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Creative::ReviewChat')
                ->where('subjectType', 'mannequin')
                ->where('subjectId', $m->id)
                ->where('reviewNote', 'Işık çok sert.'));
    }
```

- [ ] **Step 4: Testleri çalıştır**

Run: `php artisan test tests/Feature/Creative/ReviewChatTest.php`
Expected: tüm testler PASS (mevcut 6 + yeni 3 = 9 test).

- [ ] **Step 5: Commit**

```bash
git add Modules/Creative/Http/Controllers/ReviewChatController.php Modules/Creative/routes/web.php tests/Feature/Creative/ReviewChatTest.php
git commit -m "feat(creative): chatbot için GET show sayfası action'ları ve route'ları"
```

---

### Task 10 (C2): `ReviewChat.vue` tam sayfa

**Files:**
- Create: `Modules/Creative/Resources/assets/js/Pages/ReviewChat.vue`

**Interfaces:**
- Consumes: Task C1'in `props` şekli (`subjectType`, `subjectId`, `title`, `imageUrl`,
  `reviewNote`, `backUrl`, `chats`, `suggestion`), mevcut POST endpoint'leri
  (`/creative/{mannequins|tryon}/{id}/review-chat` ve `.../apply`, `ReviewChatController`'da
  DEĞİŞMEDEN duruyor).
- Produces: yok (uç nokta sayfa).

- [ ] **Step 1: Sayfayı yaz**

`Modules/Creative/Resources/assets/js/Pages/ReviewChat.vue`:

```vue
<template>
	<Head :title="`Sohbet — ${title}`" />
	<div class="chat-page">
		<div class="chat-context">
			<Link :href="backUrl" class="back-link">← Geri dön</Link>
			<div class="context-body">
				<img v-if="imageUrl" :src="imageUrl" :alt="title" class="context-thumb" />
				<div class="context-info">
					<h1 class="context-title">{{ title }}</h1>
					<p class="context-note">✕ Reddedildi: {{ reviewNote }}</p>
				</div>
			</div>
		</div>

		<div v-if="suggestion" class="suggestion-banner">
			<div>
				<span class="suggestion-label">Önerilen düzeltme talimatı</span>
				<p class="suggestion-text">{{ suggestion }}</p>
			</div>
			<button class="btn btn-primary" :disabled="applying" @click="apply">
				{{ applying ? 'Uygulanıyor…' : '✓ Bu talimatla yeniden üret' }}
			</button>
		</div>

		<div ref="logEl" class="chat-log">
			<div v-if="messages.length === 0" class="empty-log">
				Henüz mesaj yok — asistana neyin düzeltilmesi gerektiğini sorarak başlayın.
			</div>
			<div v-for="m in messages" :key="m.id" class="chat-row" :class="m.role">
				<div class="chat-avatar">{{ m.role === 'assistant' ? '🤖' : (m.user_name?.[0] ?? '🙂') }}</div>
				<div class="chat-bubble">
					<span class="chat-author">{{ m.role === 'assistant' ? 'AI Asistan' : (m.user_name || 'Kullanıcı') }}</span>
					<p class="chat-text">{{ m.content }}</p>
					<span class="chat-time">{{ m.created_at }}</span>
				</div>
			</div>
			<div v-if="sending" class="chat-row assistant">
				<div class="chat-avatar">🤖</div>
				<div class="chat-bubble typing"><span></span><span></span><span></span></div>
			</div>
		</div>

		<form class="chat-input-bar" @submit.prevent="send">
			<textarea
				ref="inputEl"
				v-model="draft"
				rows="1"
				placeholder="Mesajınızı yazın… (Enter=gönder, Shift+Enter=yeni satır)"
				:disabled="sending"
				@keydown.enter.exact.prevent="send"
				@input="autoGrow"
			></textarea>
			<button type="submit" class="btn btn-primary" :disabled="sending || !draft.trim()">Gönder</button>
		</form>
	</div>
</template>

<script setup>
import { ref, ref as vueRef, computed, inject, nextTick, onMounted } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
	subjectType: { type: String, required: true },
	subjectId: { type: Number, required: true },
	title: { type: String, default: '' },
	imageUrl: { type: String, default: null },
	reviewNote: { type: String, default: null },
	backUrl: { type: String, required: true },
	chats: { type: Array, default: () => [] },
	suggestion: { type: String, default: null },
})

const showToast = inject('showToast', null)

const messages = ref([...props.chats])
const draft = ref('')
const sending = ref(false)
const applying = ref(false)
const logEl = ref(null)
const inputEl = ref(null)

const base = computed(() => props.subjectType === 'mannequin'
	? `/creative/mannequins/${props.subjectId}`
	: `/creative/tryon/${props.subjectId}`)

function scrollToBottom() {
	nextTick(() => { if (logEl.value) logEl.value.scrollTop = logEl.value.scrollHeight })
}

onMounted(scrollToBottom)

function autoGrow(e) {
	e.target.style.height = 'auto'
	e.target.style.height = Math.min(e.target.scrollHeight, 160) + 'px'
}

function send() {
	const text = draft.value.trim()
	if (!text || sending.value) return
	sending.value = true

	router.post(`${base.value}/review-chat`, { message: text }, {
		preserveScroll: true,
		preserveState: true,
		only: ['chats', 'suggestion'],
		onSuccess: (page) => {
			messages.value = page.props.chats ?? messages.value
			draft.value = ''
			scrollToBottom()
		},
		onError: (errs) => showToast?.({ type: 'error', title: 'Gönderilemedi', message: Object.values(errs)[0] || 'Sunucu hatası.' }),
		onFinish: () => { sending.value = false },
	})
}

function apply() {
	if (applying.value) return
	applying.value = true
	router.post(`${base.value}/review-chat/apply`, {}, {
		onError: (errs) => showToast?.({ type: 'error', title: 'Uygulanamadı', message: Object.values(errs)[0] || 'Sunucu hatası.' }),
		onFinish: () => { applying.value = false },
	})
}
</script>

<style scoped>
.chat-page { display: flex; flex-direction: column; height: calc(100vh - 64px); max-width: 780px; margin: 0 auto; }
.chat-context { padding: 16px 0 12px; border-bottom: 1px solid #f0f0f5; }
.back-link { font-size: 12px; color: rgb(var(--color-primary)); font-weight: 600; text-decoration: none; }
.context-body { display: flex; align-items: center; gap: 14px; margin-top: 10px; }
.context-thumb { width: 56px; height: 56px; border-radius: 10px; object-fit: cover; background: #f5f5f8; }
.context-title { font-size: 18px; font-weight: 700; color: #1a1a2e; }
.context-note { font-size: 12.5px; color: #b91c1c; margin-top: 2px; }

.suggestion-banner { position: sticky; top: 0; z-index: 5; display: flex; align-items: center; justify-content: space-between; gap: 16px; background: #fff7ed; border: 1px solid #fed7aa; border-radius: 12px; padding: 12px 16px; margin: 12px 0; }
.suggestion-label { font-size: 10.5px; font-weight: 700; color: #c2410c; text-transform: uppercase; letter-spacing: .03em; }
.suggestion-text { font-size: 13px; color: #1a1a2e; margin-top: 2px; }

.chat-log { flex: 1; overflow-y: auto; padding: 16px 0; display: flex; flex-direction: column; gap: 14px; }
.empty-log { text-align: center; color: #aaa; font-style: italic; font-size: 13px; margin-top: 40px; }
.chat-row { display: flex; gap: 10px; max-width: 78%; }
.chat-row.user { align-self: flex-end; flex-direction: row-reverse; }
.chat-avatar { width: 30px; height: 30px; border-radius: 50%; background: #f0f0f5; display: flex; align-items: center; justify-content: center; font-size: 14px; flex-shrink: 0; }
.chat-bubble { background: #f5f5f8; border-radius: 14px; padding: 10px 14px; }
.chat-row.user .chat-bubble { background: rgb(var(--color-primary-soft)); }
.chat-row.assistant .chat-bubble { background: #eef2ff; }
.chat-author { display: block; font-size: 10.5px; font-weight: 700; color: #888; margin-bottom: 3px; }
.chat-text { font-size: 13.5px; color: #1a1a2e; white-space: pre-wrap; line-height: 1.5; }
.chat-time { display: block; font-size: 10px; color: #aaa; margin-top: 4px; }
.chat-bubble.typing { display: flex; gap: 4px; align-items: center; padding: 12px 16px; }
.chat-bubble.typing span { width: 6px; height: 6px; border-radius: 50%; background: #bbb; animation: pulse 1.2s infinite ease-in-out; }
.chat-bubble.typing span:nth-child(2) { animation-delay: .2s; }
.chat-bubble.typing span:nth-child(3) { animation-delay: .4s; }
@keyframes pulse { 0%, 80%, 100% { opacity: .3; } 40% { opacity: 1; } }

.chat-input-bar { position: sticky; bottom: 0; display: flex; gap: 10px; align-items: flex-end; background: #fff; border-top: 1px solid #f0f0f5; padding: 12px 0; }
.chat-input-bar textarea { flex: 1; resize: none; border: 1px solid #e8e8f0; border-radius: 10px; padding: 10px 12px; font-family: inherit; font-size: 13.5px; outline: none; max-height: 160px; }
.chat-input-bar textarea:focus { border-color: rgb(var(--color-primary)); }
</style>
```

- [ ] **Step 2: Frontend'i derle**

Run: `npm run build`
Expected: hatasız biter.

- [ ] **Step 3: Commit**

```bash
git add Modules/Creative/Resources/assets/js/Pages/ReviewChat.vue
git commit -m "feat(creative): reddedilen görseller için tam sayfa chatbot arayüzü"
```

---

### Task 11 (C3): Liste sayfalarını linke indirge, `ReviewChatPanel.vue` kaldır

**Files:**
- Modify: `Modules/Creative/Resources/assets/js/Pages/CreativeMannequins.vue`
- Modify: `Modules/Creative/Resources/assets/js/Pages/CreativeTryon.vue`
- Delete: `Modules/Creative/Resources/assets/js/Components/ReviewChatPanel.vue`

**Interfaces:**
- Consumes: mevcut `can_chat` prop'u (controller'lar zaten sağlıyor, DEĞİŞMİYOR).
- Produces: yok.

- [ ] **Step 1: `CreativeMannequins.vue`'da paneli linke çevir**

`Modules/Creative/Resources/assets/js/Pages/CreativeMannequins.vue`'daki şu bloğu:

```vue
						<ReviewChatPanel
							v-if="m.can_chat"
							subject-type="mannequin"
							:subject-id="m.id"
							:review-note="m.review_note"
							:chats="m.review_chats"
							:suggestion="m.chat_suggestion"
						/>
```

şununla değiştir:

```vue
						<Link v-if="m.can_chat" :href="`/creative/mannequins/${m.id}/review-chat`" class="chat-link">
							💬 AI ile Konuş <span v-if="m.review_chats?.length">({{ m.review_chats.length }} mesaj)</span>
						</Link>
```

Script bloğunda `import ReviewChatPanel from '../Components/ReviewChatPanel.vue'` satırını
SİL; `import { Head, router } from '@inertiajs/vue3'` satırını
`import { Head, Link, router } from '@inertiajs/vue3'` yap (`Link` eklenir).

`<style scoped>` bloğuna ekle:

```css
.chat-link { display: block; text-align: center; padding: 8px 12px; margin: 0 12px 12px; background: #eef2ff; color: #4338ca; border-radius: 8px; font-size: 12px; font-weight: 600; text-decoration: none; }
.chat-link:hover { background: #e0e7ff; }
```

- [ ] **Step 2: `CreativeTryon.vue`'da aynı değişikliği yap**

Aynı desende: `ReviewChatPanel` bloğunu (satır 159-166 civarı) `Link` ile değiştir:

```vue
						<Link v-if="r.can_chat" :href="`/creative/tryon/${r.id}/review-chat`" class="chat-link">
							💬 AI ile Konuş <span v-if="r.review_chats?.length">({{ r.review_chats.length }} mesaj)</span>
						</Link>
```

`import ReviewChatPanel from '../Components/ReviewChatPanel.vue'` satırını SİL; script'teki
`import { Head, Link, router } from '@inertiajs/vue3'` zaten `Link` içeriyor (CreativeTryon.vue
zaten `Link` kullanıyor — kontrol et, gerekirse ekle). Aynı `.chat-link` CSS'ini `<style scoped>`
bloğuna ekle.

- [ ] **Step 3: `ReviewChatPanel.vue`'yu sil**

```bash
git rm Modules/Creative/Resources/assets/js/Components/ReviewChatPanel.vue
```

- [ ] **Step 4: Frontend'i derle**

Run: `npm run build`
Expected: hatasız biter (silinen bileşene hiçbir import kalmadığından emin ol — derleme
hata verirse eksik bir import/kullanım kalmış demektir).

- [ ] **Step 5: Manuel doğrulama**

1. Reddedilmiş bir mankende "💬 AI ile Konuş" linkine tıkla → `ReviewChat.vue` tam sayfası açılmalı.
2. Mesaj yaz, Enter'a bas → mesaj balonu sağda, birkaç saniye sonra asistan yanıtı solda görünmeli.
3. Yanıt bir `FINAL_INSTRUCTION` içeriyorsa üstte turuncu öneri banner'ı belirmeli.
4. "Bu talimatla yeniden üret" → tıkla → `/creative/mannequins`'e geri dönmeli, manken
   "Üretiliyor" durumuna geçmeli.

- [ ] **Step 6: Commit**

```bash
git add Modules/Creative/Resources/assets/js/Pages/CreativeMannequins.vue Modules/Creative/Resources/assets/js/Pages/CreativeTryon.vue
git commit -m "refactor(creative): gömülü sohbet panelini tam sayfa linkine indirge"
```

---

## Self-Review Notu (plan yazarı için)

- **Spec kapsaması:** A (kullanıcı CRUD) → Task A1-A4. B (anlık bildirim) → Task B1-B4.
  C (chatbot sayfa) → Task C1-C3. Spec'teki her madde bir task'a karşılık geliyor.
- **Tip tutarlılığı:** `ReviewChatService::history()`/`latestSuggestion()` imzaları Task C1'de
  spec'teki ve mevcut koddaki (`ReviewChatService.php`) ile birebir aynı kullanıldı.
  `UserController` metod isimleri (`index/store/update/destroy/toggleActive`) Task A3-A4
  arasında tutarlı.
- **Placeholder taraması:** Yok — her adımda tam kod var; "benzer şekilde" ifadesi sadece
  Task C3 Step 2'de kullanıldı çünkü CreativeTryon.vue'nun tam güncel içeriği Task C3
  yürütüldüğünde okunacak (dosya A/B fazlarında değişmez, ama plan yazımı sırasında satır
  numaraları kaymış olabilir — bu yüzden "bloğu bul, değiştir" talimatı somut kod örneğiyle
  verildi, TBD değil).
