<?php

namespace Tests\Feature\Tenant\Portal;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tenant\Models\Tenant;
use Modules\Tenant\Models\TenantInvoice;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PortalInvoiceDownloadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['portal.access', 'portal.invoices.view'] as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }
        Role::firstOrCreate(['name' => 'tenant', 'guard_name' => 'web'])
            ->givePermissionTo(['portal.access', 'portal.invoices.view']);
    }

    public function test_invoice_payload_returns_correct_shape(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'inv-' . uniqid()]);
        $invoice = TenantInvoice::factory()->create(['tenant_id' => $tenant->id, 'amount' => 250.50]);

        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $user->assignRole('tenant');

        $this->actingAs($user)
            ->getJson("http://{$tenant->slug}.bizimsite.test/invoices/{$invoice->id}/payload")
            ->assertOk()
            ->assertJsonPath('data.invoice.id', $invoice->id)
            ->assertJsonPath('data.invoice.amount', 250.50)
            ->assertJsonPath('data.tenant.name', $tenant->name);
    }

    public function test_cross_tenant_invoice_payload_returns_403(): void
    {
        $tenantA = Tenant::factory()->create(['slug' => 'a-' . uniqid()]);
        $tenantB = Tenant::factory()->create(['slug' => 'b-' . uniqid()]);
        $invoiceB = TenantInvoice::factory()->create(['tenant_id' => $tenantB->id]);

        $userOfA = User::factory()->create(['tenant_id' => $tenantA->id]);
        $userOfA->assignRole('tenant');

        // Subdomain A'dan B'nin invoice'ını çekmeye çalış: middleware tenant resolve eder ve
        // route param invoice → policy::view false → 403.
        $this->actingAs($userOfA)
            ->getJson("http://{$tenantA->slug}.bizimsite.test/invoices/{$invoiceB->id}/payload")
            ->assertForbidden();
    }
}
