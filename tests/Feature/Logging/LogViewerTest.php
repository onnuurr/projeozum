<?php

namespace Tests\Feature\Logging;

use App\Models\ActivityLog;
use App\Models\ErrorLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Superadmin log görüntüleyici bütünleşik testleri.
 *
 * sqlite :memory: kullanır; RefreshDatabase ile her test temiz başlar.
 *
 * Tasarım notu:
 * - EnsureLogAccess middleware'i LogAccessGateTest'te kapsamlı test edilmiş.
 * - Burada içerik testleri için middleware'i devre dışı bırakıp sadece controller
 *   davranışını test ediyoruz (doğru Inertia bileşeni + prop'lar).
 * - Redirect testi için ayrı probe route kullanılır (LogAccessGateTest deseni).
 */
class LogViewerTest extends TestCase
{
    use RefreshDatabase;

    private User $superadmin;
    private User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();

        $role              = Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);
        $this->superadmin  = User::factory()->create();
        $this->superadmin->assignRole($role);

        Permission::firstOrCreate(['name' => 'logs.view', 'guard_name' => 'web']);

        $regularRole       = Role::firstOrCreate(['name' => 'tenant', 'guard_name' => 'web']);
        $this->regularUser = User::factory()->create();
        $this->regularUser->assignRole($regularRole);
    }

    /**
     * Superadmin için log.access oturum bayrağıyla GET isteği.
     * Controller davranışını ve Inertia prop'larını test eder.
     * withoutMiddleware yerine withSession kullanılır — HandleInertiaRequests
     * devre dışı kalmasin diye (aksi hâlde assertInertia çalışmaz).
     */
    private function superadminGet(string $url, array $query = []): \Illuminate\Testing\TestResponse
    {
        $fullUrl = $url . ($query ? '?' . http_build_query($query) : '');

        return $this->actingAs($this->superadmin)
            ->withSession(['superadmin.log_access_confirmed_at' => now()->timestamp])
            ->get($fullUrl);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 1. Kilit bayrağı olmadan erişim → unlock sayfasına yönlendirilir
    //    (EnsureLogAccess middleware'in session doğrulaması)
    // ─────────────────────────────────────────────────────────────────────────

    public function test_without_log_access_session_redirects_to_unlock(): void
    {
        \Illuminate\Support\Facades\Route::middleware(['web', 'auth', 'role:superadmin', 'log.access'])
            ->get('/_test/logviewer-probe', fn () => response('ok'))
            ->name('_test.logviewer.probe');

        $response = $this->actingAs($this->superadmin)
            ->get('/_test/logviewer-probe');

        $response->assertRedirect(route('superadmin.logs.unlock'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 2. Kilit bayrağı ile erişim → 200 + Inertia bileşeni + gerekli prop'lar
    // ─────────────────────────────────────────────────────────────────────────

    public function test_with_log_access_returns_200_and_inertia_component(): void
    {
        ActivityLog::factory()->create([
            'module'      => 'auth',
            'action'      => 'auth.login',
            'description' => 'Kullanıcı giriş yaptı',
            'level'       => 'info',
        ]);

        ErrorLog::factory()->create([
            'module'  => 'product',
            'level'   => 'error',
            'message' => 'Test hatası',
        ]);

        $response = $this->superadminGet(route('superadmin.logs'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $p) => $p
            ->component('Superadmin::LogViewer', false)
            ->has('activity.data')
            ->has('errors.data')
            ->has('modules')
            ->has('activeTab')
            ->has('filters')
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 3. activity.data içinde kayıt gelir
    // ─────────────────────────────────────────────────────────────────────────

    public function test_activity_data_is_present_in_props(): void
    {
        ActivityLog::factory()->create([
            'module'      => 'product',
            'action'      => 'product.created',
            'description' => 'Ürün oluşturuldu',
            'level'       => 'info',
        ]);

        $response = $this->superadminGet(route('superadmin.logs'));

        $response->assertInertia(fn (Assert $p) => $p
            ->component('Superadmin::LogViewer', false)
            ->has('activity.data', 1)
            ->where('activity.data.0.action', 'product.created')
            ->where('activity.data.0.module', 'product')
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 4. errors.data içinde kayıt gelir
    // ─────────────────────────────────────────────────────────────────────────

    public function test_errors_data_is_present_in_props(): void
    {
        ErrorLog::factory()->create([
            'module'  => 'auth',
            'level'   => 'critical',
            'message' => 'Kritik hata oluştu',
        ]);

        $response = $this->superadminGet(route('superadmin.logs'));

        $response->assertInertia(fn (Assert $p) => $p
            ->component('Superadmin::LogViewer', false)
            ->has('errors.data', 1)
            ->where('errors.data.0.message', 'Kritik hata oluştu')
            ->where('errors.data.0.level', 'critical')
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 5. Modül filtresi sonuçları daraltır
    // ─────────────────────────────────────────────────────────────────────────

    public function test_module_filter_narrows_activity_results(): void
    {
        ActivityLog::factory()->create(['module' => 'auth',    'action' => 'auth.login',      'description' => 'Giriş', 'level' => 'info']);
        ActivityLog::factory()->create(['module' => 'product', 'action' => 'product.created', 'description' => 'Ürün',  'level' => 'info']);

        $response = $this->superadminGet(route('superadmin.logs'), ['module' => 'auth']);

        $response->assertInertia(fn (Assert $p) => $p
            ->component('Superadmin::LogViewer', false)
            ->has('activity.data', 1)
            ->where('activity.data.0.module', 'auth')
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 6. Non-superadmin kullanıcı 403 alır
    // ─────────────────────────────────────────────────────────────────────────

    public function test_non_superadmin_gets_403(): void
    {
        $response = $this->actingAs($this->regularUser)
            ->get(route('superadmin.logs'));

        $response->assertStatus(403);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 7. Sekme parametresi activeTab prop'una yansır
    // ─────────────────────────────────────────────────────────────────────────

    public function test_tab_parameter_is_passed_to_active_tab_prop(): void
    {
        $response = $this->superadminGet(route('superadmin.logs'), ['tab' => 'errors']);

        $response->assertInertia(fn (Assert $p) => $p
            ->component('Superadmin::LogViewer', false)
            ->where('activeTab', 'errors')
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 8. Hata log filtresi sonuçları daraltır
    // ─────────────────────────────────────────────────────────────────────────

    public function test_module_filter_narrows_error_results(): void
    {
        ErrorLog::factory()->create(['module' => 'auth',    'level' => 'error',    'message' => 'Auth hatası']);
        ErrorLog::factory()->create(['module' => 'product', 'level' => 'critical', 'message' => 'Product hatası']);

        $response = $this->superadminGet(
            route('superadmin.logs'),
            ['tab' => 'errors', 'module' => 'product']
        );

        $response->assertInertia(fn (Assert $p) => $p
            ->component('Superadmin::LogViewer', false)
            ->has('errors.data', 1)
            ->where('errors.data.0.module', 'product')
        );
    }
}
